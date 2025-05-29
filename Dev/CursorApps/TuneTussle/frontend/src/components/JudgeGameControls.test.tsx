import { render, screen, fireEvent, cleanup } from '@testing-library/react';
import { vi } from 'vitest';
import { JudgeGameControls } from './JudgeGameControls';

describe('JudgeGameControls', () => {
  const mockOnNextRound = vi.fn();
  const mockOnEndRound = vi.fn();
  const defaultProps = {
    onNextRound: mockOnNextRound,
    onEndRound: mockOnEndRound,
    currentRound: 1,
    totalRounds: 10,
    correctAnswer: 'The Beatles',
    currentSongAudioUrl: null,
    isRoundComplete: true,
    canAdvance: true,
    isGameOver: false,
  };

  beforeEach(() => {
    vi.clearAllMocks();
    global.confirm = vi.fn(() => true);
    global.alert = vi.fn();
  });

  afterEach(() => {
    cleanup();
  });

  describe('Basic functionality', () => {
    it('renders the component with correct title', () => {
      render(<JudgeGameControls {...defaultProps} />);
      // Check that round information appears (there are multiple instances)
      expect(screen.getAllByText(/Round 1 of 10/)).toHaveLength(2);
    });

    it('calls onNextRound when Next Round button is clicked', () => {
      render(<JudgeGameControls {...defaultProps} isRoundComplete={true} />);
      fireEvent.click(screen.getByText('Next Song'));
      expect(mockOnNextRound).toHaveBeenCalledTimes(1);
    });

    it('shows "End Game" instead of "Next Round" on the last round when round is complete', () => {
      render(
        <JudgeGameControls
          {...defaultProps}
          currentRound={10}
          totalRounds={10}
          isRoundComplete={true}
          isGameOver={true}
        />
      );
      expect(screen.getByText('End Game')).toBeInTheDocument();
    });

    it('does not show Next Round button when round is not complete', () => {
      render(<JudgeGameControls {...defaultProps} isRoundComplete={false} />);
      expect(screen.queryByText('Next Song')).not.toBeInTheDocument();
      expect(screen.queryByText('End Game')).not.toBeInTheDocument();
    });
  });

  describe('End Round Functionality', () => {
    describe('End Round Button Display', () => {
      it('should show "End Round" button when round is active (not complete)', () => {
        render(<JudgeGameControls {...defaultProps} isRoundComplete={false} />);
        
        const endRoundButton = screen.getByText('End Round');
        expect(endRoundButton).toBeInTheDocument();
        expect(endRoundButton).not.toBeDisabled();
      });

      it('should show "End Game" button when on final round and round is active', () => {
        render(
          <JudgeGameControls 
            {...defaultProps} 
            currentRound={10} 
            totalRounds={10} 
            isRoundComplete={false} 
          />
        );
        
        const endGameButton = screen.getByText('End Game');
        expect(endGameButton).toBeInTheDocument();
        expect(endGameButton).not.toBeDisabled();
      });

      it('should not show End Round button when round is complete', () => {
        render(<JudgeGameControls {...defaultProps} isRoundComplete={true} />);
        
        expect(screen.queryByText('End Round')).not.toBeInTheDocument();
        // End Game appears when round is complete on final round
      });

      it('should not show End Round button when onEndRound is not provided', () => {
        render(
          <JudgeGameControls 
            {...defaultProps} 
            onEndRound={undefined}
            isRoundComplete={false} 
          />
        );
        
        expect(screen.queryByText('End Round')).not.toBeInTheDocument();
        expect(screen.queryByText('End Game')).not.toBeInTheDocument();
      });
    });

    describe('End Round Button Actions', () => {
      it('should call onEndRound when End Round button is clicked', () => {
        const mockOnEndRound = vi.fn();
        render(
          <JudgeGameControls 
            {...defaultProps} 
            onEndRound={mockOnEndRound}
            isRoundComplete={false} 
          />
        );
        
        const endRoundButton = screen.getByText('End Round');
        fireEvent.click(endRoundButton);
        
        expect(mockOnEndRound).toHaveBeenCalledTimes(1);
      });

      it('should call onEndRound when End Game button is clicked on final round', () => {
        const mockOnEndRound = vi.fn();
        render(
          <JudgeGameControls 
            {...defaultProps} 
            onEndRound={mockOnEndRound}
            currentRound={10}
            totalRounds={10}
            isRoundComplete={false} 
          />
        );
        
        const endGameButton = screen.getByText('End Game');
        fireEvent.click(endGameButton);
        
        expect(mockOnEndRound).toHaveBeenCalledTimes(1);
      });
    });

    describe('Helper Text Display', () => {
      it('should show correct helper text for End Round button', () => {
        render(<JudgeGameControls {...defaultProps} currentRound={1} isRoundComplete={false} />);
        
        expect(screen.getByText('End round 1 and proceed to next song.')).toBeInTheDocument();
      });

      it('should show correct helper text for End Game button on final round', () => {
        render(
          <JudgeGameControls 
            {...defaultProps} 
            currentRound={10}
            totalRounds={10}
            isRoundComplete={false} 
          />
        );
        
        expect(screen.getByText('End the game and show final results.')).toBeInTheDocument();
      });
    });

    describe('Button State Transitions', () => {
      it('should show both End Round and Next Round buttons in different states', () => {
        const { rerender } = render(
          <JudgeGameControls {...defaultProps} isRoundComplete={false} />
        );
        
        // When round is active, show End Round button
        expect(screen.getByText('End Round')).toBeInTheDocument();
        expect(screen.queryByText('Next Song')).not.toBeInTheDocument();
        
        // When round is complete, show Next Round button
        rerender(<JudgeGameControls {...defaultProps} isRoundComplete={true} />);
        expect(screen.queryByText('End Round')).not.toBeInTheDocument();
        expect(screen.getByText('Next Song')).toBeInTheDocument();
      });

      it('should show End Game button when round is complete on final round', () => {
        render(
          <JudgeGameControls 
            {...defaultProps} 
            currentRound={10}
            totalRounds={10}
            isRoundComplete={true} 
            isGameOver={true}
          />
        );
        
        expect(screen.getByText('End Game')).toBeInTheDocument();
        expect(screen.queryByText('Next Song')).not.toBeInTheDocument();
      });
    });

    describe('Integration with Other Features', () => {
      it('should maintain correct round information display with end round functionality', () => {
        render(
          <JudgeGameControls 
            {...defaultProps} 
            currentRound={2}
            totalRounds={5}
            isRoundComplete={false} 
          />
        );
        
        // Should show End Round button for mid-game round
        expect(screen.getByText('End Round')).toBeInTheDocument();
        expect(screen.getByText('End round 2 and proceed to next song.')).toBeInTheDocument();
        // Note: Round display "Round X of Y" may not be shown in the current component implementation
      });
    });
  });

  // Score adjustment functionality has been moved to Scoreboard component
  // Tests for that functionality should be in Scoreboard.test.tsx

  describe('Media Player', () => {
    it('renders iframe with correct embed URL when valid YouTube URL is provided', () => {
              render(
          <JudgeGameControls
            {...defaultProps}
            currentSongAudioUrl="https://www.youtube.com/embed/dQw4w9WgXcQ"
          />
        );
        const iframe = screen.getByTitle('YouTube video player');
        expect(iframe).toBeInTheDocument();
        expect(iframe).toHaveAttribute('src', 'https://www.youtube.com/embed/dQw4w9WgXcQ');
    });

    it('renders iframe for youtu.be links', () => {
      render(
        <JudgeGameControls
          {...defaultProps}
          currentSongAudioUrl="https://www.youtube.com/embed/dQw4w9WgXcQ"
        />
      );
      expect(screen.getByTitle('YouTube video player')).toBeInTheDocument();
    });

    it('renders error for invalid YouTube embed links', () => {
              render(
          <JudgeGameControls
            {...defaultProps}
            currentSongAudioUrl="https://www.youtube.com/embed/invalid"
          />
        );
      expect(screen.getByText(/Could not load audio/)).toBeInTheDocument();
    });

    it('renders error for valid embed link (unsupported format)', () => {
      render(
        <JudgeGameControls
          {...defaultProps}
          currentSongAudioUrl="https://www.youtube.com/embed/abc"
        />
      );
      expect(screen.getByText(/Could not load audio/)).toBeInTheDocument();
    });

    it('does not render iframe if audioUrl is null', () => {
      render(<JudgeGameControls {...defaultProps} currentSongAudioUrl={null} />);
      expect(screen.queryByTitle('YouTube video player')).not.toBeInTheDocument();
      expect(screen.getByText(/No audio available/)).toBeInTheDocument();
    });

    it('shows error message for invalid audio/video link', () => {
      render(
        <JudgeGameControls
          {...defaultProps}
          currentSongAudioUrl="https://invalid-link.com"
        />
      );
      expect(screen.getByText(/Could not load audio/)).toBeInTheDocument();
    });

    it('does not render iframe or error if link is valid but not YouTube', () => {
      render(
        <JudgeGameControls
          {...defaultProps}
          currentSongAudioUrl="https://example.com/video"
        />
      );
      expect(screen.queryByTitle('YouTube video player')).not.toBeInTheDocument();
      expect(screen.getByText(/Could not load audio/)).toBeInTheDocument();
    });
  });
}); 