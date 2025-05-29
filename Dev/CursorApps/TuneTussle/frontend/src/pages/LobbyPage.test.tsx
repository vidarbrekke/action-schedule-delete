import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
// import { MemoryRouter, Route, Routes } from 'react-router-dom'; // Not strictly needed if not testing routing from LobbyPage
import '@testing-library/jest-dom';
import { vi, type Mock } from 'vitest';
import { useGameLogic } from '../hooks/useGameLogic';

import LobbyPage from './LobbyPage';
// REMOVE: import * as SocketContextModule from '../contexts/SocketContext';

// REMOVE: const mockUseSocket = vi.spyOn(SocketContextModule, 'useSocket');

// Mock the useGameLogic hook
vi.mock('../hooks/useGameLogic');

// Mock child components to isolate LobbyPage logic
vi.mock('../components/JudgeControls', () => ({
  JudgeControls: vi.fn(({ onStartGame, isStartingGame, startGameError, participants = [] }) => {
    const playerCount = participants.filter((p: any) => p.role === 'participant').length;
    const hasPlayers = playerCount > 0;
    const isButtonDisabled = isStartingGame || !hasPlayers;
    
    return (
      <div>
        <h3>Game Controls</h3>
        <button 
          data-testid="start-game-btn" 
          onClick={!isButtonDisabled ? onStartGame : undefined} 
          disabled={isButtonDisabled}
        >
          {isStartingGame ? 'Starting...' : 'Start Game'}
        </button>
        {!hasPlayers && !isStartingGame && (
          <p>Waiting for players to join...</p>
        )}
        {startGameError && <p data-testid="judge-controls-error">{startGameError}</p>}
      </div>
    );
  }),
}));

vi.mock('../components/ParticipantControls', () => ({
  ParticipantControls: vi.fn(() => (
    <div>
      <h3>Player Status</h3>
      <div>Ready to Play</div>
      <p>Waiting for the judge to start the game</p>
    </div>
  )),
}));

vi.mock('../components/Scoreboard', () => ({
    Scoreboard: vi.fn(() => <div>Scoreboard Mock</div>),
  }));

const mockUseGameLogic = useGameLogic as Mock;

// REMOVE: Helper to create a mock context value
// REMOVE: const createMockSocketContextValue = ... (whole function)

describe('LobbyPage Component', () => { // Changed describe name for clarity, was 'LobbyPage'
  // REMOVE: Store the mock context setter to simulate updates
  // REMOVE: let mockSetContextValue: (value: SocketContextModule.SocketContextState) => void;

  // REMOVE: Custom wrapper to provide router and mock context
  // REMOVE: const TestWrapper = ... (whole component)

  beforeEach(() => {
    vi.clearAllMocks();
    // mockUseGameLogic.mockClear(); // useGameLogic is vi.mocked, clearAllMocks should cover it.
  });

  // REMOVE OBSOLETE TESTS (4 of them)
  // test('renders game code and initial participants', ...) 
  // test('renders waiting message when no participants', ...)
  // test('updates participant list when context changes', ...)
  // test('shows disconnected status', ...)

  // KEEP THESE TESTS (The ones starting with 'it')
  const mockParticipants = [
    { id: '1', name: 'Player Alice', role: 'participant' as 'participant' | 'judge' },
    { id: 'judge1', name: 'Judge Bob', role: 'judge' as 'participant' | 'judge' },
  ];
  
  const mockParticipantsWithTestPlayer = [
    { id: '1', name: 'TestPlayer', role: 'participant' as 'participant' | 'judge' },
    { id: 'judge1', name: 'Judge Bob', role: 'judge' as 'participant' | 'judge' },
  ];
  
  const mockParticipantsWithJudgePlayer = [
    { id: '1', name: 'Player Alice', role: 'participant' as 'participant' | 'judge' },
    { id: 'judge1', name: 'JudgePlayer', role: 'judge' as 'participant' | 'judge' },
  ];
  
  const mockGameCode = 'TEST123';

  it('renders loading state initially', () => {
    mockUseGameLogic.mockReturnValue({
      lobbyState: {
        isLoadingRole: true,
        role: null,
        isStartingGame: false,
        startGameError: null,
      },
      isJudge: false,
      scores: [],
      gameCode: '',
      startGame: vi.fn(),
      isSocketConnected: false,
      playerName: null,
      error: null,
      isLoading: true,
      isBuzzActive: false,
      currentRound: null,
      currentSong: null,
      activePlayerName: null,
      judgingPlayerName: null,
      gameEnded: false,
      finalScores: null,
      handleBuzzIn: vi.fn(),
      submitAnswer: vi.fn(),
      setPlayerNameAndInitiateJoin: vi.fn(),
    });

    render(<MemoryRouter><LobbyPage /></MemoryRouter>);
    expect(screen.getByText('Connecting...')).toBeInTheDocument();
    expect(screen.getByText('Establishing connection to the game server.')).toBeInTheDocument();
  });

  it('renders participant view correctly when not loading and not judge', () => {
    mockUseGameLogic.mockReturnValue({
      lobbyState: {
        isLoadingRole: false,
        role: 'participant',
        isStartingGame: false,
        startGameError: null,
      },
      isJudge: false,
      scores: mockParticipantsWithTestPlayer,
      gameCode: mockGameCode,
      startGame: vi.fn(),
      isSocketConnected: true,
      playerName: 'TestPlayer',
      error: null,
      isLoading: false,
      isBuzzActive: false,
      currentRound: null,
      currentSong: null,
      activePlayerName: null,
      judgingPlayerName: null,
      gameEnded: false,
      finalScores: null,
      handleBuzzIn: vi.fn(),
      submitAnswer: vi.fn(),
      setPlayerNameAndInitiateJoin: vi.fn(),
    });

    render(<MemoryRouter><LobbyPage /></MemoryRouter>);
    expect(screen.getByText(mockGameCode)).toBeInTheDocument(); // Large prominent code
    expect(screen.getByText(/Share this code with players/)).toBeInTheDocument();
    expect(screen.getByText('TestPlayer')).toBeInTheDocument(); // Updated to match mock participant
    expect(screen.getByText(/Judge Bob/)).toBeInTheDocument();
    expect(screen.getAllByText('Judge').length).toBeGreaterThanOrEqual(1); // Judge badge in participant list only
    expect(screen.getByText(/Player Status/)).toBeInTheDocument();
    expect(screen.getByText(/Waiting for the judge to start/)).toBeInTheDocument();
    expect(screen.queryByTestId('start-game-btn')).not.toBeInTheDocument();
    
    // Check for participant-specific "You" badge next to player name
    expect(screen.getByText('You')).toBeInTheDocument(); // "You" badge should appear next to TestPlayer
    expect(screen.getByText('TestPlayer')).toBeInTheDocument();
  });

  it('renders judge view with JudgeControls correctly when not loading and is judge', () => {
    const mockStartGame = vi.fn();
    mockUseGameLogic.mockReturnValue({
      lobbyState: {
        isLoadingRole: false,
        role: 'judge',
        isStartingGame: false,
        startGameError: null,
      },
      isJudge: true,
      scores: mockParticipantsWithJudgePlayer,
      gameCode: mockGameCode,
      startGame: mockStartGame,
      isSocketConnected: true,
      playerName: 'JudgePlayer',
      error: null,
      isLoading: false,
      isBuzzActive: false,
      currentRound: null,
      currentSong: null,
      activePlayerName: null,
      judgingPlayerName: null,
      gameEnded: false,
      finalScores: null,
      handleBuzzIn: vi.fn(),
      submitAnswer: vi.fn(),
      setPlayerNameAndInitiateJoin: vi.fn(),
    });

    render(<MemoryRouter><LobbyPage /></MemoryRouter>);
    expect(screen.getByText(mockGameCode)).toBeInTheDocument(); // Large prominent code
    expect(screen.getByText(/Share this code with players/)).toBeInTheDocument();
    expect(screen.getByText(/Player Alice/)).toBeInTheDocument();
    expect(screen.getByText('JudgePlayer')).toBeInTheDocument(); // Updated to match mock participant
    expect(screen.getAllByText('Judge').length).toBeGreaterThanOrEqual(1); // Judge badge in participant list only
    expect(screen.getByTestId('start-game-btn')).toBeInTheDocument();
    expect(screen.getByText(/Game Controls/)).toBeInTheDocument();
    
    // Check for judge-specific "You" badge next to player name
    expect(screen.getByText('You')).toBeInTheDocument(); // "You" badge should appear next to JudgePlayer
    expect(screen.getByText('JudgePlayer')).toBeInTheDocument();
  });

  it('displays participant list correctly', () => {
    mockUseGameLogic.mockReturnValue({
      lobbyState: {
        isLoadingRole: false,
        role: 'participant',
        isStartingGame: false,
        startGameError: null,
      },
      isJudge: false,
      scores: mockParticipants,
      gameCode: mockGameCode,
      startGame: vi.fn(),
      isSocketConnected: true,
      playerName: 'TestPlayer2',
      error: null,
      isLoading: false,
      isBuzzActive: false,
      currentRound: null,
      currentSong: null,
      activePlayerName: null,
      judgingPlayerName: null,
      gameEnded: false,
      finalScores: null,
      handleBuzzIn: vi.fn(),
      submitAnswer: vi.fn(),
      setPlayerNameAndInitiateJoin: vi.fn(),
    });

    render(<MemoryRouter><LobbyPage /></MemoryRouter>);
    // Check that both participants are displayed
    expect(screen.getByText('Player Alice')).toBeInTheDocument();
    expect(screen.getByText('Judge Bob')).toBeInTheDocument();
    // Check for the new design where participants count is shown as a badge
    expect(screen.getByText('Participants')).toBeInTheDocument();
    expect(screen.getByText('2')).toBeInTheDocument();
  });

  it('calls startGame from JudgeControls when judge clicks start', () => {
    const mockStartGame = vi.fn();
    mockUseGameLogic.mockReturnValue({
      lobbyState: {
        isLoadingRole: false,
        role: 'judge',
        isStartingGame: false,
        startGameError: null,
      },
      isJudge: true,
      scores: mockParticipants,
      gameCode: mockGameCode,
      startGame: mockStartGame,
      isSocketConnected: true,
      playerName: 'JudgePlayer2',
      error: null,
      isLoading: false,
      isBuzzActive: false,
      currentRound: null,
      currentSong: null,
      activePlayerName: null,
      judgingPlayerName: null,
      gameEnded: false,
      finalScores: null,
      handleBuzzIn: vi.fn(),
      submitAnswer: vi.fn(),
      setPlayerNameAndInitiateJoin: vi.fn(),
    });

    render(<MemoryRouter><LobbyPage /></MemoryRouter>);
    const startButton = screen.getByTestId('start-game-btn');
    fireEvent.click(startButton);
    expect(mockStartGame).toHaveBeenCalledTimes(1);
  });

  it('copies join URL to clipboard when game code is clicked', async () => {
    // Mock clipboard API
    const mockWriteText = vi.fn().mockResolvedValue(undefined);
    Object.assign(navigator, {
      clipboard: {
        writeText: mockWriteText,
      },
    });

    mockUseGameLogic.mockReturnValue({
      lobbyState: {
        isLoadingRole: false,
        role: 'participant',
        isStartingGame: false,
        startGameError: null,
      },
      isJudge: false,
      scores: mockParticipantsWithTestPlayer,
      gameCode: mockGameCode,
      startGame: vi.fn(),
      isSocketConnected: true,
      playerName: 'TestPlayer',
      error: null,
      isLoading: false,
      isBuzzActive: false,
      currentRound: null,
      currentSong: null,
      activePlayerName: null,
      judgingPlayerName: null,
      gameEnded: false,
      finalScores: null,
      handleBuzzIn: vi.fn(),
      submitAnswer: vi.fn(),
      setPlayerNameAndInitiateJoin: vi.fn(),
    });

    render(<MemoryRouter><LobbyPage /></MemoryRouter>);
    
    // Find the game code element and click it
    const gameCodeElement = screen.getByText(mockGameCode);
    fireEvent.click(gameCodeElement.closest('div')!);
    
    // Wait for the async operation
    await waitFor(() => {
      expect(mockWriteText).toHaveBeenCalledWith(`${window.location.origin}/game/${mockGameCode}`);
    });
  });
}); 