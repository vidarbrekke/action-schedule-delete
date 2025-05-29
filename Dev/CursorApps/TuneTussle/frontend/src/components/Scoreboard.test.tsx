import { render, screen } from '@testing-library/react';
import Scoreboard from './Scoreboard';
import type { PlayerScore } from './Scoreboard';

const mockScores: PlayerScore[] = [
  { id: '1', name: 'Player Alpha', score: 100 },
  { id: '2', name: 'Player Beta', score: 150 },
  { id: '3', name: 'Player Gamma', score: 50 },
];

describe('Scoreboard', () => {
  it('renders the title', () => {
    render(<Scoreboard scores={[]} title="Game Over Scores" />);
    expect(screen.getByText('Game Over Scores')).toBeInTheDocument();
  });

  it('renders default title if none provided', () => {
    render(<Scoreboard scores={[]} />);
    expect(screen.getByText('Scoreboard')).toBeInTheDocument();
  });

  it('renders a message when there are no scores', () => {
    render(<Scoreboard scores={[]} />);
    expect(screen.getByText('No scores yet. The game is about to start!')).toBeInTheDocument();
  });

  it('renders player scores sorted by score (descending)', () => {
    render(<Scoreboard scores={mockScores} />);
    const scoreItems = screen.getAllByRole('listitem');
    
    // Check order based on score
    expect(scoreItems[0]).toHaveTextContent('Player Beta');
    expect(scoreItems[0]).toHaveTextContent('150');
    expect(scoreItems[1]).toHaveTextContent('Player Alpha');
    expect(scoreItems[1]).toHaveTextContent('100');
    expect(scoreItems[2]).toHaveTextContent('Player Gamma');
    expect(scoreItems[2]).toHaveTextContent('50');
  });

  it('displays player names and scores correctly', () => {
    render(<Scoreboard scores={mockScores} />);
    mockScores.forEach(player => {
      expect(screen.getByText(new RegExp(player.name))).toBeInTheDocument();
      expect(screen.getByText(player.score.toString())).toBeInTheDocument();
    });
  });

  it('highlights the current turn player', () => {
    const scoresWithCurrentTurn: PlayerScore[] = [
      { id: '1', name: 'Player A', score: 10, isCurrentTurn: false },
      { id: '2', name: 'Player B', score: 20, isCurrentTurn: true },
      { id: '3', name: 'Player C', score: 5, isCurrentTurn: false },
    ];
    render(<Scoreboard scores={scoresWithCurrentTurn} />);
    const playerBItem = screen.getByText(/Player B/).closest('li');
    expect(playerBItem).toHaveClass('bg-blue-100', 'ring-2', 'ring-blue-500');

    const playerAItem = screen.getByText(/Player A/).closest('li');
    expect(playerAItem).not.toHaveClass('bg-blue-100');
    expect(playerAItem).toHaveClass('bg-gray-50'); // Check for default class
  });

  it('highlights winners with specific styling and trophy emoji', () => {
    render(<Scoreboard scores={mockScores} highlightWinners={['2']} />); // Player Beta is a winner
    
    const playerBetaItem = screen.getByText(/Player Beta/).closest('li');
    expect(playerBetaItem).toHaveClass('bg-yellow-200', 'ring-2', 'ring-yellow-500');
    expect(playerBetaItem).toHaveTextContent('🏆');

    const playerAlphaItem = screen.getByText(/Player Alpha/).closest('li');
    expect(playerAlphaItem).not.toHaveClass('bg-yellow-200');
    expect(playerAlphaItem).not.toHaveTextContent('🏆');
    expect(playerAlphaItem).toHaveClass('bg-gray-50');
  });

  it('highlights multiple winners if provided', () => {
    const tieScores: PlayerScore[] = [
      { id: '1', name: 'Player Alpha', score: 150 },
      { id: '2', name: 'Player Beta', score: 150 },
      { id: '3', name: 'Player Gamma', score: 50 },
    ];
    render(<Scoreboard scores={tieScores} highlightWinners={['1', '2']} />);
    
    const playerAlphaItem = screen.getByText(/Player Alpha/).closest('li');
    expect(playerAlphaItem).toHaveClass('bg-yellow-200', 'ring-yellow-500');
    expect(playerAlphaItem).toHaveTextContent('🏆');

    const playerBetaItem = screen.getByText(/Player Beta/).closest('li');
    expect(playerBetaItem).toHaveClass('bg-yellow-200', 'ring-yellow-500');
    expect(playerBetaItem).toHaveTextContent('🏆');

    const playerGammaItem = screen.getByText(/Player Gamma/).closest('li');
    expect(playerGammaItem).not.toHaveClass('bg-yellow-200');
  });

  it('does not apply winner styling if highlightWinners is empty or not provided', () => {
    render(<Scoreboard scores={mockScores} />);
    const playerItems = screen.getAllByText(/Player Alpha/);
    playerItems.forEach(item => {
      expect(item.closest('li')).not.toHaveClass('bg-yellow-200');
      expect(item.closest('li')).not.toHaveTextContent('🏆');
    });
  });

  it('winner highlighting takes precedence over current turn highlighting', () => {
    const scoresWinnerAndTurn: PlayerScore[] = [
      { id: '1', name: 'Player Winner', score: 200, isCurrentTurn: true },
      { id: '2', name: 'Player Other', score: 100, isCurrentTurn: false },
    ];
    render(<Scoreboard scores={scoresWinnerAndTurn} highlightWinners={['1']} />);
    
    const playerWinnerItem = screen.getByText(/Player Winner/).closest('li');
    expect(playerWinnerItem).toHaveClass('bg-yellow-200', 'ring-yellow-500'); // Winner style
    expect(playerWinnerItem).not.toHaveClass('bg-blue-100'); // Not current turn style
    expect(playerWinnerItem).toHaveTextContent('🏆');
  });
}); 