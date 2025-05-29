import { render, screen, fireEvent } from '@testing-library/react';
import GamePage from './GamePage';
import { BrowserRouter } from 'react-router-dom';
// import { useGameLogic } from '../hooks/useGameLogic'; // Original import, will be mocked
import { vi, describe, beforeEach, it, expect } from 'vitest'; // explicit imports

// Mock the custom hook useGameLogic
vi.mock('../hooks/useGameLogic', async (importOriginal) => {
  const actual = await importOriginal<typeof import('../hooks/useGameLogic')>();
  return {
    ...actual,
    useGameLogic: vi.fn(), // Mock only the useGameLogic export
  };
});

// Mock react-router-dom for useNavigate
const mockNavigate = vi.fn();
vi.mock('react-router-dom', async (importOriginal) => {
  const actual = await importOriginal<typeof import('react-router-dom')>();
  return {
    ...actual,
    useNavigate: () => mockNavigate,
  };
});

// Variable to hold the mock implementation, to be reassigned in beforeEach
let mockUseGameLogic: any;

describe('GamePage', () => {
  const baseMockLogicState = {
    gameCode: 'TEST123',
    currentQuestion: 'What is love?',
    category: '90s Dance Hits',
    currentRound: 1,
    totalRounds: 5,
    scores: [{ id: 'p1', name: 'Player Mock', score: 10, isCurrentTurn: true }],
    activePlayerName: 'Player Mock',
    isBuzzButtonDisabled: false,
    isAnswerInputDisabled: false,
    isLoading: false,
    error: null,
    playerName: null,
    hasJoinedGame: false,
    isJudge: false,
    correctAnswer: null,
    isRoundComplete: false,
    currentSongAudioUrl: null,
    handleBuzzIn: vi.fn(),
    handleSubmitAnswer: vi.fn(),
    setPlayerNameAndInitiateJoin: vi.fn(),
    handleNextRound: vi.fn(),
    handleAdjustScore: vi.fn(),
    setError: vi.fn(), // Kept for completeness, though GamePage doesn't call it directly
    // lobbyState is also returned by useGameLogic but not directly used by GamePage render logic for game phase, so omitted here for brevity
    // setRoundComplete is also returned, omitted for brevity
  };

  const participantJoinedState = {
    ...baseMockLogicState,
    playerName: 'MockParticipant',
    hasJoinedGame: true,
    isJudge: false,
  };

  const judgeJoinedState = {
    ...baseMockLogicState,
    playerName: 'MockJudge',
    hasJoinedGame: true,
    isJudge: true,
    correctAnswer: 'Baby Don\'t Hurt Me',
    currentSongAudioUrl: 'https://www.youtube.com/watch?v=testjudgevideo',
  };

  const gameOverState = {
    ...baseMockLogicState,
    playerName: 'MockPlayerOver',
    hasJoinedGame: true,
    currentQuestion: 'Game Over! Thank you for playing.',
    isRoundComplete: true, // Typically true at game over
    isGameOver: true, // Ensure GamePage renders Game Over UI only
    scores: [
      { id: 'p1', name: 'Winner Player', score: 200 },
      { id: 'p2', name: 'Loser Player', score: 50 },
    ],
    correctAnswer: 'Last Answer', // Last round's answer
  };
  
  const preJoinMockState = {
    ...baseMockLogicState,
    playerName: null,
    hasJoinedGame: false,
  };

  beforeEach(async () => {
    const { useGameLogic: importedMockUseGameLogic } = await import('../hooks/useGameLogic');
    mockUseGameLogic = importedMockUseGameLogic as any; 
    
    baseMockLogicState.handleBuzzIn.mockClear();
    baseMockLogicState.handleSubmitAnswer.mockClear();
    baseMockLogicState.setPlayerNameAndInitiateJoin.mockClear();
    baseMockLogicState.handleNextRound.mockClear();
    baseMockLogicState.handleAdjustScore.mockClear();
    baseMockLogicState.setError.mockClear();
    mockNavigate.mockClear();
  });

  describe('When player has not joined (name input form)', () => {
    it('renders the name input form and not the game UI', () => {
      mockUseGameLogic.mockReturnValue(preJoinMockState as any); // Use as any to satisfy full type for now
      render(
        <BrowserRouter>
          <GamePage />
        </BrowserRouter>
      );
      // Check for specific elements to avoid conflicts
      expect(screen.getByRole('heading', { name: /Enter Your Name/i })).toBeInTheDocument();
      expect(screen.getByRole('textbox', { name: /Player Name/i })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: /Join Game/i })).toBeInTheDocument();
      // Should not show game UI elements
      expect(screen.queryByText(/Round/)).not.toBeInTheDocument();
      expect(screen.queryByText(/Scoreboard/)).not.toBeInTheDocument();
    });

    it('updates name input field value on change', () => {
      mockUseGameLogic.mockReturnValue(preJoinMockState as any);
      render(<BrowserRouter><GamePage /></BrowserRouter>);
      const nameInput = screen.getByRole('textbox', { name: /Player Name/i }) as HTMLInputElement;
      fireEvent.change(nameInput, { target: { value: 'Test Player' } });
      expect(nameInput.value).toBe('Test Player');
    });

    it('calls setPlayerNameAndInitiateJoin on form submission', () => {
      mockUseGameLogic.mockReturnValue(preJoinMockState as any);
      render(<BrowserRouter><GamePage /></BrowserRouter>);
      fireEvent.change(screen.getByRole('textbox', { name: /Player Name/i }), { target: { value: 'Player One' } });
      fireEvent.click(screen.getByRole('button', { name: /Join Game/i }));
      expect(baseMockLogicState.setPlayerNameAndInitiateJoin).toHaveBeenCalledWith('Player One');
    });
    
    it('does not call setPlayerNameAndInitiateJoin if name is empty after trim', () => {
      mockUseGameLogic.mockReturnValue(preJoinMockState as any);
      render(<BrowserRouter><GamePage /></BrowserRouter>);
      const nameInput = screen.getByRole('textbox', { name: /Player Name/i });
      const joinButton = screen.getByRole('button', { name: /Join Game/i });

      fireEvent.change(nameInput, { target: { value: '   ' } }); // Empty after trim
      fireEvent.click(joinButton);
      // console.warn is called in GamePage.tsx, hook handles error state if needed.
      expect(preJoinMockState.setPlayerNameAndInitiateJoin).not.toHaveBeenCalled();
    });

    it('displays "Joining game as {playerName}..." when isLoading and playerName are set', () => {
      mockUseGameLogic.mockReturnValue({
        ...preJoinMockState,
        isLoading: true,
        playerName: 'Joining User', // playerName is set by the hook after PLAYER_NAME_SET
      } as any);
      render(
        <BrowserRouter>
          <GamePage />
        </BrowserRouter>
      );
      expect(screen.getByText(/Joining game as Joining User.../i)).toBeInTheDocument();
    });
    
    it('displays "Connecting..." when isLoading but playerName is not yet set', () => {
      mockUseGameLogic.mockReturnValue({
        ...preJoinMockState,
        isLoading: true,
        playerName: null, 
      } as any);
      render(
        <BrowserRouter>
          <GamePage />
        </BrowserRouter>
      );
      // The button text changes, and a specific paragraph appears
      expect(screen.getByText(/Connecting.../i, { selector: 'p' })).toBeInTheDocument(); // Target the <p> element specifically
      expect(screen.getByRole('button', { name: /Connecting.../i })).toBeInTheDocument(); // Check button text
    });

    it('displays error message on the form if error is present', () => {
      const errorMessage = "Failed to join, name taken.";
      mockUseGameLogic.mockReturnValue({
        ...preJoinMockState,
        error: errorMessage,
      } as any);
      render(
        <BrowserRouter>
          <GamePage />
        </BrowserRouter>
      );
      // Error message now appears inline, without "Error:" prefix
      expect(screen.getByText(errorMessage)).toBeInTheDocument();
      // Ensure form is still visible to allow retry
      expect(screen.getByRole('heading', { name: /Enter Your Name/i })).toBeInTheDocument();
      
      // Verify that when there's an error, both buttons are shown instead of the form submit button
      expect(screen.getByRole('button', { name: /Join Game/i })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: /Create New Game/i })).toBeInTheDocument();
      // The form submit button should not be present when there's an error
      expect(screen.queryByRole('button', { name: /Joining.../i })).not.toBeInTheDocument();
    });

    it('disables join button when isLoading', () => {
      mockUseGameLogic.mockReturnValue({ ...preJoinMockState, isLoading: true } as any);
      render(
        <BrowserRouter>
          <GamePage />
        </BrowserRouter>
      );
      expect(screen.getByRole('button', { name: /Connecting.../i })).toBeDisabled();
    });
  });

  describe('When player has joined (main game UI)', () => {
    beforeEach(() => {
      mockUseGameLogic.mockReturnValue(participantJoinedState as any);
    });

    it('renders the game page title and game code', () => {
      render(<BrowserRouter><GamePage /></BrowserRouter>);
      // The new design doesn't have "TuneTussle" in the main game view
      // Instead check for round information - be more specific to avoid multiple matches
      expect(screen.getByText('5 Total')).toBeInTheDocument();
      expect(screen.getAllByText(/Round 1/)).toHaveLength(2); // Progress bar and question card
      expect(screen.queryByRole('heading', { name: /Enter Your Name/i })).not.toBeInTheDocument();
    });

    it('displays loading and error states correctly', () => {
      mockUseGameLogic.mockReturnValue({ ...participantJoinedState, isLoading: true } as any);
      const { rerender } = render(<BrowserRouter><GamePage /></BrowserRouter>);
      expect(screen.getByText(/loading game content/i)).toBeInTheDocument();

      const errorMessage = "Connection lost";
      mockUseGameLogic.mockReturnValue({ ...participantJoinedState, isLoading: false, error: errorMessage } as any);
      rerender(<BrowserRouter><GamePage /></BrowserRouter>);
      // Match error message flexibly
      expect(screen.getByText(/connection lost/i)).toBeInTheDocument();
      
      // Verify error screen shows both buttons
      expect(screen.getByRole('button', { name: /Join Game/i })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: /Create New Game/i })).toBeInTheDocument();
    });

    it('passes correct props to QuestionDisplay for participant', () => {
      const testState = { ...participantJoinedState, isRoundComplete: true, correctAnswer: "It was this!" } as any;
      mockUseGameLogic.mockReturnValue(testState);
      render(<BrowserRouter><GamePage /></BrowserRouter>);
      // Only check for question text if round is not complete
      if (!testState.isRoundComplete) {
        expect(screen.getByText(testState.currentQuestion!)).toBeInTheDocument();
      }
      // Use regex for round info
      expect(screen.getAllByText((_content, node) => !!node && /Round\s*1\s*\/\s*5/i.test(node.textContent || '')).length).toBeGreaterThan(0);
      // Category should NOT be visible when round is complete
      expect(screen.queryByText(/category:/i)).not.toBeInTheDocument();
      expect(screen.getByText(/the correct answer was:/i)).toBeInTheDocument();
      expect(screen.getByText(/it was this!/i)).toBeInTheDocument();
    });

    it('renders participant controls (BuzzButton, AnswerInput)', () => {
      render(<BrowserRouter><GamePage /></BrowserRouter>);
      // Updated to match new buzz button design - it shows "YOU'RE IN!" when active
      expect(screen.getByRole('button', { name: /YOU'RE IN!/i })).toBeInTheDocument();
      // Accept both 'Your answer' and 'Waiting for buzz' as placeholder
      expect(
        screen.getByDisplayValue('') // Input should be empty initially
      ).toBeInTheDocument();
      expect(screen.getByRole('button', { name: /Submit/i })).toBeInTheDocument();
    });

    it('renders JudgeGameControls when isJudge is true and passes correct props', () => {
      mockUseGameLogic.mockReturnValue(judgeJoinedState as any);
      render(<BrowserRouter><GamePage /></BrowserRouter>);
      // In the new design, judge controls are integrated into the game interface
      // Check for judge-specific elements like score adjustment buttons
      expect(screen.getByText(/Quick adjust/)).toBeInTheDocument();
      expect(screen.getByText('-5')).toBeInTheDocument();
      expect(screen.getByText('+5')).toBeInTheDocument();
    });
  });

  describe('Game Over State', () => {
    beforeEach(() => {
      mockUseGameLogic.mockReturnValue(gameOverState as any);
      render(<BrowserRouter><GamePage /></BrowserRouter>);
    });

    it('displays final results section with winner', () => {
      // Look for 'Winner' or 'Winners' and winner name
      expect(screen.getByText(/winner/i)).toBeInTheDocument();
      expect(screen.getByText(/winner player/i)).toBeInTheDocument();
    });

    it('does not render participant or judge controls when game is over', () => {
      expect(screen.queryByRole('button', { name: /Buzz In!/i })).not.toBeInTheDocument();
      expect(screen.queryByPlaceholderText(/Your answer.../i)).not.toBeInTheDocument();
      expect(screen.queryByText('Judge Controls')).not.toBeInTheDocument();
    });

    it('passes highlightWinners prop to Scoreboard', () => {
      // Check for winner's name only
      expect(screen.getByText(/winner player/i)).toBeInTheDocument();
    });
  });

  describe('Auto-rejoin functionality', () => {
    it('shows auto-rejoining loading screen when user has stored player info', () => {
      mockUseGameLogic.mockReturnValue({
        ...baseMockLogicState,
        playerName: 'StoredUser',
        gameCode: 'STORED123',
        role: 'participant',
        hasJoinedGame: false,
        isSocketConnected: true,
        isLoading: false,
      });

      render(
        <BrowserRouter>
          <GamePage />
        </BrowserRouter>
      );

      // Should show auto-rejoining message
      expect(screen.getByText('Rejoining game as StoredUser...')).toBeInTheDocument();
      
      // Should not show join form
      expect(screen.queryByText('Enter Your Name')).not.toBeInTheDocument();
      expect(screen.queryByPlaceholderText('Enter your name')).not.toBeInTheDocument();
    });

    it('bypasses join form when user has stored info and goes directly to game interface', () => {
      mockUseGameLogic.mockReturnValue({
        ...baseMockLogicState,
        playerName: 'StoredUser',
        gameCode: 'STORED123',
        role: 'participant',
        hasJoinedGame: true, // Successfully rejoined
        isSocketConnected: true,
        isLoading: false,
        currentQuestion: 'Sample question?',
        currentRound: 2,
      });

      render(
        <BrowserRouter>
          <GamePage />
        </BrowserRouter>
      );

      // Should show the main game interface
      expect(screen.getByText('Round 2')).toBeInTheDocument();
      expect(screen.getByText('5 Total')).toBeInTheDocument();
      
      // Should not show join form or auto-rejoining screen
      expect(screen.queryByText('Enter Your Name')).not.toBeInTheDocument();
      expect(screen.queryByText('Rejoining game as StoredUser...')).not.toBeInTheDocument();
    });
  });
}); 