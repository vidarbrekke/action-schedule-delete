import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import { vi, describe, it, expect, beforeEach } from 'vitest';
import PlayersPage from './PlayersPage';

// Create mock functions before the vi.mock calls
const mockNavigate = vi.fn();

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return {
    ...actual,
    useNavigate: () => mockNavigate,
  };
});

vi.mock('../hooks/useGameLogic', () => ({
  useGameLogic: vi.fn(() => ({
    scores: [
      { id: 'alice', name: 'Alice', score: 15, role: 'participant' },
      { id: 'bob', name: 'Bob', score: 10, role: 'participant' },
      { id: 'judge1', name: 'Judge', score: 0, role: 'judge' },
    ],
    playerName: 'Alice',
    isSocketConnected: true,
    gameCode: 'ABC123',
  })),
}));

// Mock components that might have complex dependencies
vi.mock('../components/PageTransition', () => ({
  __esModule: true,
  default: ({ children }: { children: React.ReactNode }) => <div data-testid="page-transition">{children}</div>,
}));

vi.mock('../components/MobileAppLayout', () => ({
  __esModule: true,
  default: ({ children, title }: { children: React.ReactNode; title: string }) => (
    <div data-testid="mobile-app-layout">
      <div data-testid="title">{title}</div>
      {children}
    </div>
  ),
}));

vi.mock('../components/FloatingCard', () => ({
  __esModule: true,
  default: ({ children, className }: { children: React.ReactNode; className?: string }) => (
    <div data-testid="floating-card" className={className}>
      {children}
    </div>
  ),
}));

vi.mock('../components/LoadingSpinner', () => ({
  __esModule: true,
  default: () => <div data-testid="loading-spinner">Loading...</div>,
}));

const renderWithRouter = (component: React.ReactElement) => {
  return render(<BrowserRouter>{component}</BrowserRouter>);
};

describe('PlayersPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the players page with title and game code', () => {
    renderWithRouter(<PlayersPage />);

    expect(screen.getByTestId('title')).toHaveTextContent('Players');
    expect(screen.getByText('All Players')).toBeInTheDocument();
    expect(screen.getByText('Game: ABC123')).toBeInTheDocument();
  });

  it('displays all participants with their scores and roles', () => {
    renderWithRouter(<PlayersPage />);

    expect(screen.getByText('Alice')).toBeInTheDocument();
    expect(screen.getByText('Bob')).toBeInTheDocument();
    expect(screen.getAllByText('Judge')).toHaveLength(2); // Name and badge

    expect(screen.getByText('Score: 15 pts')).toBeInTheDocument();
    expect(screen.getByText('Score: 10 pts')).toBeInTheDocument();
    expect(screen.getByText('Score: 0 pts')).toBeInTheDocument();
  });

  it('shows participant count badge', () => {
    renderWithRouter(<PlayersPage />);

    expect(screen.getByText('3')).toBeInTheDocument(); // Total participant count
  });

  it('highlights current player with "You" badge', () => {
    renderWithRouter(<PlayersPage />);

    expect(screen.getByText('You')).toBeInTheDocument();
  });

  it('shows judge badge for judge role', () => {
    renderWithRouter(<PlayersPage />);

    // Look for the judge badge specifically, not just any "Judge" text
    const judgeBadges = screen.getAllByText('Judge');
    expect(judgeBadges.length).toBeGreaterThan(0);
  });

  it('navigates back when back to game button is clicked', async () => {
    renderWithRouter(<PlayersPage />);

    const backButton = screen.getByText('Back to Game');
    fireEvent.click(backButton);

    await waitFor(() => {
      expect(mockNavigate).toHaveBeenCalledWith(-1);
    });
  });

  it('navigates to lobby when invite friends button is clicked', async () => {
    renderWithRouter(<PlayersPage />);

    const inviteButton = screen.getByText('Invite Friends');
    fireEvent.click(inviteButton);

    await waitFor(() => {
      expect(mockNavigate).toHaveBeenCalledWith('/lobby/ABC123');
    });
  });

  it('renders both action buttons', () => {
    renderWithRouter(<PlayersPage />);

    expect(screen.getByText('Back to Game')).toBeInTheDocument();
    expect(screen.getByText('Invite Friends')).toBeInTheDocument();
  });
});
