import { render, screen, fireEvent } from '@testing-library/react';
import ResultsPage from './ResultsPage';
import { BrowserRouter } from 'react-router-dom';
import { vi } from 'vitest';

// Mock react-confetti to avoid canvas errors in jsdom
vi.mock('react-confetti', () => ({
  __esModule: true,
  default: (props: any) => <div data-testid="confetti-mock" {...props} />, 
}));

const mockNavigate = vi.fn();
let mockLocationState: any = {};
vi.mock('react-router-dom', async (importOriginal) => {
  const actual = await importOriginal<typeof import('react-router-dom')>();
  return {
    ...actual,
    useNavigate: () => mockNavigate,
    useLocation: () => mockLocationState,
  };
});

describe('ResultsPage', () => {
  beforeEach(() => {
    mockNavigate.mockClear();
    // Default state for most tests
    mockLocationState = {
      state: {
        scores: [
          { id: 'p1', name: 'Winner Player', score: 200 },
          { id: 'p2', name: 'Loser Player', score: 50 },
        ],
        gameCode: 'TEST123',
      },
    };
  });

  it('renders Game Over message and winner', () => {
    render(
      <BrowserRouter>
        <ResultsPage />
      </BrowserRouter>
    );
    expect(screen.getByText(/game over/i)).toBeInTheDocument();
    expect(screen.getByText(/final scores/i)).toBeInTheDocument();
    expect(screen.getAllByText(/winner player/i).length).toBeGreaterThan(0);
  });

  it('renders Play Another Game and Create New Game buttons', () => {
    render(
      <BrowserRouter>
        <ResultsPage />
      </BrowserRouter>
    );
    expect(screen.getByRole('button', { name: /play another game/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /create new game/i })).toBeInTheDocument();
  });

  it('navigates to home on Play Another Game click', () => {
    render(
      <BrowserRouter>
        <ResultsPage />
      </BrowserRouter>
    );
    fireEvent.click(screen.getByRole('button', { name: /play another game/i }));
    expect(mockNavigate).toHaveBeenCalledWith('/');
  });

  it('navigates to create-game on Create New Game click', () => {
    render(
      <BrowserRouter>
        <ResultsPage />
      </BrowserRouter>
    );
    fireEvent.click(screen.getByRole('button', { name: /create new game/i }));
    expect(mockNavigate).toHaveBeenCalledWith('/create-game');
  });

  it('shows confetti when there is a winner', () => {
    render(
      <BrowserRouter>
        <ResultsPage />
      </BrowserRouter>
    );
    expect(screen.getByTestId('confetti-mock')).toBeInTheDocument();
  });

  it('renders draw message when all scores are equal', () => {
    mockLocationState = {
      state: {
        scores: [
          { id: 'p1', name: 'Player A', score: 0 },
          { id: 'p2', name: 'Player B', score: 0 },
        ],
        gameCode: 'DRAW123',
      },
    };
    render(
      <BrowserRouter>
        <ResultsPage />
      </BrowserRouter>
    );
    expect(screen.getAllByText(/draw|no winner|no one scored/i).length).toBeGreaterThan(0);
  });

  it('renders fallback when no scores are provided', () => {
    mockLocationState = {
      state: {
        scores: [],
        gameCode: 'NOSCORES',
      },
    };
    render(
      <BrowserRouter>
        <ResultsPage />
      </BrowserRouter>
    );
    expect(screen.getAllByText(/no scores|no results|no players/i).length).toBeGreaterThan(0);
  });

  it('renders fallback when location state is missing', () => {
    mockLocationState = {};
    render(
      <BrowserRouter>
        <ResultsPage />
      </BrowserRouter>
    );
    expect(screen.getAllByText(/no scores|no results|no players|error/i).length).toBeGreaterThan(0);
  });

  it('renders all players when there are many scores', () => {
    const manyScores = Array.from({ length: 50 }, (_, i) => ({
      id: `p${i + 1}`,
      name: `Player ${i + 1}`,
      score: Math.floor(Math.random() * 100),
    }));
    mockLocationState = {
      state: {
        scores: manyScores,
        gameCode: 'MANY',
      },
    };
    render(
      <BrowserRouter>
        <ResultsPage />
      </BrowserRouter>
    );
    expect(screen.getAllByText(/player \d+/i).length).toBeGreaterThanOrEqual(50);
  });
});
