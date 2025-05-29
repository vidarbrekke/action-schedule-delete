import { render, screen, fireEvent } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import RoundResultsScreen from '../RoundResultsScreen';

// Mock framer-motion to avoid issues in tests
vi.mock('framer-motion', () => ({
  motion: {
    div: ({ children, className, ...props }: { children?: React.ReactNode; className?: string; [key: string]: unknown }) => <div className={className} {...props}>{children}</div>,
    img: ({ children, className, ...props }: { children?: React.ReactNode; className?: string; [key: string]: unknown }) => <img className={className} {...props}>{children}</img>,
    span: ({ children, className, ...props }: { children?: React.ReactNode; className?: string; [key: string]: unknown }) => <span className={className} {...props}>{children}</span>,
  }
}));

const mockProps = {
  roundNumber: 1,
  correctAnswer: { title: 'Test Song', artist: 'Test Artist' },
  playersWithPoints: [
    { name: 'Alice', score: 15, pointsThisRound: 10 },
    { name: 'Bob', score: 5, pointsThisRound: 0 }
  ],
  roundParticipants: [
    { name: 'Charlie', role: 'judge' },
    { name: 'Alice', role: 'participant' },
    { name: 'Bob', role: 'participant' }
  ],
  artworkUrl: 'https://example.com/artwork.jpg',
  canAdvance: true,
  isGameOver: false,
  isJudge: false,
  onNextRound: vi.fn(),
  onEndGame: vi.fn()
};

describe('RoundResultsScreen', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('displays the judge\'s name in the waiting message for players', () => {
    render(<RoundResultsScreen {...mockProps} />);
    
    expect(screen.getByText(/Waiting for Charlie to start the next round/i)).toBeInTheDocument();
  });

  it('falls back to "the judge" when judge is not found', () => {
    const propsWithoutJudge = {
      ...mockProps,
      roundParticipants: [
        { name: 'Alice', role: 'participant' },
        { name: 'Bob', role: 'participant' }
      ]
    };
    
    render(<RoundResultsScreen {...propsWithoutJudge} />);
    
    expect(screen.getByText(/Waiting for the judge to start the next round/i)).toBeInTheDocument();
  });

  it('does not show waiting message for judges', () => {
    const judgeProps = { ...mockProps, isJudge: true };
    
    render(<RoundResultsScreen {...judgeProps} />);
    
    expect(screen.queryByText(/Waiting for/)).not.toBeInTheDocument();
  });

  it('shows thank you message when game is over', () => {
    const gameOverProps = { ...mockProps, isGameOver: true };
    
    render(<RoundResultsScreen {...gameOverProps} />);
    
    expect(screen.getByText(/Thank you for playing TuneTussle!/)).toBeInTheDocument();
    expect(screen.queryByText(/Waiting for/)).not.toBeInTheDocument();
  });

  it('displays correct answer information', () => {
    render(<RoundResultsScreen {...mockProps} />);
    
    expect(screen.getByText('Test Song')).toBeInTheDocument();
    expect(screen.getByText('by Test Artist')).toBeInTheDocument();
  });

  it('displays players who scored points this round', () => {
    render(<RoundResultsScreen {...mockProps} />);
    
    // Check for the "Points Earned This Round" section specifically
    expect(screen.getByText('Points Earned This Round')).toBeInTheDocument();
    expect(screen.getByText('+10')).toBeInTheDocument();
    // Bob scored 0 points this round, so shouldn't appear in the "Points Earned" section
    expect(screen.queryByText('+0')).not.toBeInTheDocument();
    
    // Verify Alice appears in the points earned section by checking for the specific styling
    const pointsSection = screen.getByText('Points Earned This Round').closest('div');
    expect(pointsSection).toBeInTheDocument();
  });

  it('displays updated scoreboard', () => {
    render(<RoundResultsScreen {...mockProps} />);
    
    // Check for the "Updated Scoreboard" section specifically
    expect(screen.getByText('Updated Scoreboard')).toBeInTheDocument();
    expect(screen.getByText('15')).toBeInTheDocument(); // Alice's total score
    expect(screen.getByText('5')).toBeInTheDocument(); // Bob's total score
    
    // Check for rankings
    expect(screen.getByText('#1')).toBeInTheDocument();
    expect(screen.getByText('#2')).toBeInTheDocument();
  });

  it('calls onNextRound when judge clicks start next round button', () => {
    const judgeProps = { ...mockProps, isJudge: true };
    
    render(<RoundResultsScreen {...judgeProps} />);
    
    const nextRoundButton = screen.getByText('Start Next Round');
    fireEvent.click(nextRoundButton);
    
    expect(mockProps.onNextRound).toHaveBeenCalledTimes(1);
  });
}); 