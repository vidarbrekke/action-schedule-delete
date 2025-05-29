import { render, screen, fireEvent } from '@testing-library/react';
import { vi } from 'vitest';
import { JudgeControls } from './JudgeControls'; // Adjust path as necessary

describe('JudgeControls', () => {
  let mockOnStartGame: ReturnType<typeof vi.fn>;

  const mockParticipants = [
    { id: 'judge1', name: 'Judge', role: 'judge' as const },
    { id: 'player1', name: 'Player 1', role: 'participant' as const },
    { id: 'player2', name: 'Player 2', role: 'participant' as const },
  ];

  const mockJudgeOnly = [
    { id: 'judge1', name: 'Judge', role: 'judge' as const },
  ];

  beforeEach(() => {
    mockOnStartGame = vi.fn();
  });

  it('renders the "Start Game" button', () => {
    render(
      <JudgeControls
        onStartGame={mockOnStartGame}
        isStartingGame={false}
        startGameError={null}
        isGameReady={true}
        participants={mockParticipants}
      />
    );
    expect(screen.getByRole('button', { name: /start game/i })).toBeInTheDocument();
  });

  it('button is enabled when not starting game, game is ready, and has participants', () => {
    render(
      <JudgeControls
        onStartGame={mockOnStartGame}
        isStartingGame={false}
        startGameError={null}
        isGameReady={true}
        participants={mockParticipants}
      />
    );
    expect(screen.getByRole('button', { name: /start game/i })).toBeEnabled();
  });

  it('button is disabled when there are no participants (only judge)', () => {
    render(
      <JudgeControls
        onStartGame={mockOnStartGame}
        isStartingGame={false}
        startGameError={null}
        isGameReady={true}
        participants={mockJudgeOnly}
      />
    );
    expect(screen.getByRole('button', { name: /start game/i })).toBeDisabled();
    expect(screen.getByText('Waiting for players to join...')).toBeInTheDocument();
  });

  it('button is disabled when game is not ready even with participants', () => {
    render(
      <JudgeControls
        onStartGame={mockOnStartGame}
        isStartingGame={false}
        startGameError={null}
        isGameReady={false}
        participants={mockParticipants}
      />
    );
    expect(screen.getByRole('button', { name: /start game/i })).toBeDisabled();
  });

  it('button is disabled and shows "Starting..." text when starting game', () => {
    render(
      <JudgeControls
        participants={mockParticipants}
        isStartingGame={true}
        onStartGame={mockOnStartGame}
        startGameError={null}
        isGameReady={true}
      />
    );
    expect(screen.getByText('Starting game...')).toBeInTheDocument();
    expect(screen.queryByTestId('start-game-btn')).not.toBeInTheDocument();
  });

  it('calls onStartGame when the button is clicked and not disabled', () => {
    render(
      <JudgeControls
        onStartGame={mockOnStartGame}
        isStartingGame={false}
        startGameError={null}
        isGameReady={true}
        participants={mockParticipants}
      />
    );
    fireEvent.click(screen.getByRole('button', { name: /start game/i }));
    expect(mockOnStartGame).toHaveBeenCalledTimes(1);
  });

  it('does not call onStartGame when the button is clicked and disabled due to no participants', () => {
    render(
      <JudgeControls
        onStartGame={mockOnStartGame}
        isStartingGame={false}
        startGameError={null}
        isGameReady={true}
        participants={mockJudgeOnly}
      />
    );
    fireEvent.click(screen.getByRole('button', { name: /start game/i }));
    expect(mockOnStartGame).not.toHaveBeenCalled();
  });

  it('does not call onStartGame when the button is clicked and disabled due to starting', () => {
    render(
      <JudgeControls
        participants={mockParticipants}
        isStartingGame={true}
        onStartGame={mockOnStartGame}
        startGameError={null}
        isGameReady={true}
      />
    );
    expect(screen.queryByTestId('start-game-btn')).not.toBeInTheDocument();
    expect(mockOnStartGame).not.toHaveBeenCalled();
  });

  it('displays an error message when startGameError is provided', () => {
    const errorMessage = 'Network error';
    render(
      <JudgeControls
        onStartGame={mockOnStartGame}
        isStartingGame={false}
        startGameError={errorMessage}
        isGameReady={true}
        participants={mockParticipants}
      />
    );
    expect(screen.getByText(`Error: ${errorMessage}`)).toBeInTheDocument();
    expect(screen.getByTestId('start-game-error')).toHaveTextContent(`Error: ${errorMessage}`);
  });

  it('does not display an error message when startGameError is null', () => {
    render(
      <JudgeControls
        onStartGame={mockOnStartGame}
        isStartingGame={false}
        startGameError={null}
        isGameReady={true}
        participants={mockParticipants}
      />
    );
    expect(screen.queryByTestId('start-game-error')).not.toBeInTheDocument();
  });

  it('shows "Waiting for playlist to be ready..." when game is not ready but has players', () => {
    render(
      <JudgeControls
        onStartGame={mockOnStartGame}
        isStartingGame={false}
        startGameError={null}
        isGameReady={false}
        participants={mockParticipants}
      />
    );
    expect(screen.getByText('Waiting for playlist to be ready...')).toBeInTheDocument();
  });

  it('shows "Generating songs..." when isGeneratingSongs is true', () => {
    render(
      <JudgeControls
        onStartGame={mockOnStartGame}
        isStartingGame={false}
        startGameError={null}
        isGameReady={false}
        isGeneratingSongs={true}
        participants={mockParticipants}
      />
    );
    expect(screen.getByText('Generating songs for your game. Please wait...')).toBeInTheDocument();
  });
}); 