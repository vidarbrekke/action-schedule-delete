import { render, screen, fireEvent, waitFor, act } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import '@testing-library/jest-dom';
import { vi } from 'vitest'; // Use vi from vitest for mocking

import HomePage from './HomePage';
import LobbyPage from './LobbyPage'; // Import LobbyPage for route definition
import { useSocket } from '../contexts/SocketContext'; // Import the hook to mock
import * as apiService from '../api/apiService'; // Import apiService to mock

// Mock the useSocket hook
vi.mock('../contexts/SocketContext', () => ({
  useSocket: vi.fn(),
}));

// Mock apiService instead of global fetch
vi.mock('../api/apiService', () => ({
  default: {
    joinGame: vi.fn(),
  },
}));

// Mock react-router-dom navigation
const mockNavigate = vi.fn();
vi.mock('react-router-dom', async () => {
  const originalModule = await vi.importActual('react-router-dom');
  return {
    ...originalModule,
    useNavigate: () => mockNavigate,
  };
});

describe('HomePage Component - Join Game Flow', () => {
  let mockJoinRoom: ReturnType<typeof vi.fn>;

  beforeEach(() => {
    // Reset mocks before each test
    vi.clearAllMocks();
    // Setup default mock implementation for useSocket
    mockJoinRoom = vi.fn();
    (useSocket as ReturnType<typeof vi.fn>).mockReturnValue({
      socket: null,
      isConnected: false,
      participants: [],
      joinRoom: mockJoinRoom,
    });
    // Setup default mock for apiService.joinGame (successful join)
    (apiService.default.joinGame as ReturnType<typeof vi.fn>).mockResolvedValue({ success: true });
  });

  // Helper function to render HomePage within router context
  const renderComponent = () => {
    render(
      <MemoryRouter initialEntries={['/']}>
        <Routes>
          <Route path="/" element={<HomePage />} />
          {/* Define Lobby route for navigation testing */}
          <Route path="/lobby/:gameCode" element={<LobbyPage />} />
        </Routes>
      </MemoryRouter>
    );
  };

  test('renders the join game form', () => {
    renderComponent();
    expect(screen.getByRole('heading', { name: /Join a Game/i })).toBeInTheDocument();
    expect(screen.getByLabelText(/Game Code/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/Your Name/i)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Join Game/i })).toBeInTheDocument();
  });

  test('allows typing game code and player name', () => {
    renderComponent();
    const gameCodeInput = screen.getByLabelText(/Game Code/i);
    const playerNameInput = screen.getByLabelText(/Your Name/i);

    fireEvent.change(gameCodeInput, { target: { value: 'ABCDEF' } });
    fireEvent.change(playerNameInput, { target: { value: 'TestPlayer' } });

    expect(gameCodeInput).toHaveValue('ABCDEF');
    expect(playerNameInput).toHaveValue('TestPlayer');
  });

  test('shows validation error if fields are empty on submit', async () => {
    renderComponent();
    const joinButton = screen.getByRole('button', { name: /Join Game/i });
    
    act(() => {
        fireEvent.click(joinButton);
    });

    // Use findByTestId to locate the error message element
    const errorMessage = await screen.findByTestId('join-error-message');
    expect(errorMessage).toBeInTheDocument();
    // Optionally check the text content as well
    expect(errorMessage).toHaveTextContent('Game Code and Player Name are required.');

    // Verify no side effects occurred
    expect(apiService.default.joinGame).not.toHaveBeenCalled();
    expect(mockJoinRoom).not.toHaveBeenCalled();
    expect(mockNavigate).not.toHaveBeenCalled();
  });

  test('calls API, joinRoom, and navigates on successful join', async () => {
    renderComponent();
    const gameCodeInput = screen.getByLabelText(/Game Code/i);
    const playerNameInput = screen.getByLabelText(/Your Name/i);
    const joinButton = screen.getByRole('button', { name: /Join Game/i });

    fireEvent.change(gameCodeInput, { target: { value: 'GOODCD' } });
    fireEvent.change(playerNameInput, { target: { value: 'Tester1' } });
    
    act(() => {
      fireEvent.click(joinButton);
    });

    // Wait for async operations (API call, state updates)
    await waitFor(() => {
      expect(apiService.default.joinGame).toHaveBeenCalledWith('GOODCD', { playerName: 'Tester1' });
    });

    await waitFor(() => {
      expect(mockJoinRoom).toHaveBeenCalledWith('GOODCD', 'Tester1');
    });

    await waitFor(() => {
      expect(mockNavigate).toHaveBeenCalledWith(
        '/lobby/GOODCD', 
        { state: { playerName: 'Tester1', isJudge: false } }
      );
    });
  });

  test('shows API error message on failed join', async () => {
    // Override default apiService.joinGame mock for failure case
    (apiService.default.joinGame as ReturnType<typeof vi.fn>).mockRejectedValueOnce({
      response: { data: { error: 'Game not found.' } }
    });

    renderComponent();
    const gameCodeInput = screen.getByLabelText(/Game Code/i);
    const playerNameInput = screen.getByLabelText(/Your Name/i);
    const joinButton = screen.getByRole('button', { name: /Join Game/i });

    fireEvent.change(gameCodeInput, { target: { value: 'BADCD' } });
    fireEvent.change(playerNameInput, { target: { value: 'TesterFail' } });
    
    act(() => {
      fireEvent.click(joinButton);
    });

    // Wait for error message to appear
    await waitFor(() => {
      expect(screen.getByText(/Game not found./i)).toBeInTheDocument();
    });

    expect(mockJoinRoom).not.toHaveBeenCalled();
    expect(mockNavigate).not.toHaveBeenCalled();
  });
}); 