import { render, screen } from '@testing-library/react';
import QuestionDisplay from './QuestionDisplay';

describe('QuestionDisplay', () => {
  it('renders default text when no props are provided', () => {
    render(<QuestionDisplay />);
    expect(screen.getByText('Waiting for the next question...')).toBeInTheDocument();
  });

  it('renders question text when provided', () => {
    render(<QuestionDisplay questionText="What is the answer?" />);
    expect(screen.getByText(/what is the answer\?/i)).toBeInTheDocument();
  });

  it('renders round information when provided', () => {
    render(<QuestionDisplay currentRound={1} totalRounds={10} />);
    const roundElements = screen.getAllByText((_, node) => !!node && /Round\s*1\s*\/\s*10/i.test(node.textContent || ''));
    expect(roundElements.length).toBeGreaterThan(0);
  });

  it('renders category when provided and round is not complete', () => {
    render(<QuestionDisplay category="80s Pop" isRoundComplete={false} questionText="Q?" />);
    const catElements = screen.getAllByText((_, node) => !!node && /category:\s*80s pop/i.test(node.textContent || ''));
    expect(catElements.length).toBeGreaterThan(0);
  });

  it('renders all information when provided and round is not complete', () => {
    render(
      <QuestionDisplay
        questionText="Name the artist."
        currentRound={3}
        totalRounds={5}
        category="Rock Anthems"
        isRoundComplete={false}
      />
    );
    expect(screen.getByText(/name the artist\./i)).toBeInTheDocument();
    const roundEls = screen.getAllByText((_, node) => !!node && /Round\s*3\s*\/\s*5/i.test(node.textContent || ''));
    expect(roundEls.length).toBeGreaterThan(0);
    const catEls = screen.getAllByText((_, node) => !!node && /category:\s*rock anthems/i.test(node.textContent || ''));
    expect(catEls.length).toBeGreaterThan(0);
    expect(screen.queryByText(/the correct answer was:/i)).not.toBeInTheDocument();
  });

  it('displays correct answer when isRoundComplete is true and correctAnswerText is provided', () => {
    render(
      <QuestionDisplay
        questionText="What was it?"
        isRoundComplete={true}
        correctAnswerText="The Answer! - Artist"
        category="Some Category"
      />
    );
    expect(screen.getByText(/the correct answer was:/i)).toBeInTheDocument();
    expect(screen.getByText(/the answer! - artist/i)).toBeInTheDocument();
    expect(screen.queryByText(/category:/i)).not.toBeInTheDocument();
  });

  it('does not display category when isRoundComplete is true', () => {
    render(<QuestionDisplay isRoundComplete={true} correctAnswerText="X" category="Y" />);
    expect(screen.queryByText(/category:/i)).not.toBeInTheDocument();
  });

  it('shows "Correct answer not available" when isRoundComplete is true but no correctAnswerText', () => {
    render(<QuestionDisplay isRoundComplete={true} correctAnswerText={null} />);
    expect(screen.getByText(/answer not available/i)).toBeInTheDocument();
  });

  it('does not show correct answer section when round is not complete', () => {
    render(<QuestionDisplay isRoundComplete={false} questionText="Q?" />);
    expect(screen.queryByText(/the correct answer was:/i)).not.toBeInTheDocument();
  });

  it('still shows category when round is not complete and category is provided', () => {
    render(<QuestionDisplay category="Funk" isRoundComplete={false} questionText="Q?" />);
    const catEls = screen.getAllByText((_, node) => !!node && /category:\s*funk/i.test(node.textContent || ''));
    expect(catEls.length).toBeGreaterThan(0);
  });

  it('shows artwork only to judges', () => {
    const artworkUrl = 'https://example.com/artwork.jpg';
    
    // Test participant (not judge) - should not see artwork
    const { rerender } = render(
      <QuestionDisplay 
        questionText="Test Question" 
        artworkUrl={artworkUrl} 
        isJudge={false} 
        isRoundComplete={false}
      />
    );
    expect(screen.queryByAltText('Album artwork')).not.toBeInTheDocument();
    
    // Test judge - should see artwork
    rerender(
      <QuestionDisplay 
        questionText="Test Question" 
        artworkUrl={artworkUrl} 
        isJudge={true} 
        isRoundComplete={false}
      />
    );
    expect(screen.getByAltText('Album artwork')).toBeInTheDocument();
    expect(screen.getByAltText('Album artwork')).toHaveAttribute('src', artworkUrl);
  });
}); 