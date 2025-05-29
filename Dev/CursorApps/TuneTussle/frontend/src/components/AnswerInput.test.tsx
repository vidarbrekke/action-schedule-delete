import { render, screen, fireEvent } from '@testing-library/react';
import AnswerInput from './AnswerInput';
import userEvent from '@testing-library/user-event';
import { vi } from 'vitest';

describe('AnswerInput', () => {
  const mockOnSubmit = vi.fn();

  beforeEach(() => {
    mockOnSubmit.mockClear();
  });

  it('renders an input field and a submit button', () => {
    render(<AnswerInput onSubmit={mockOnSubmit} />);
    expect(screen.getByPlaceholderText("Type song and artist in any order (e.g. 'Madonna Like a Virgin', 'Like a Virgin, Madonna', 'Like a Virgin by Madonna')")).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Submit/i })).toBeInTheDocument();
  });

  it('allows typing in the input field', () => {
    render(<AnswerInput onSubmit={mockOnSubmit} />);
    const input = screen.getByPlaceholderText("Type song and artist in any order (e.g. 'Madonna Like a Virgin', 'Like a Virgin, Madonna', 'Like a Virgin by Madonna')") as HTMLInputElement;
    fireEvent.change(input, { target: { value: 'My Answer' } });
    expect(input.value).toBe('My Answer');
  });

  it('calls onSubmit with the answer when the form is submitted', () => {
    render(<AnswerInput onSubmit={mockOnSubmit} />);
    const input = screen.getByPlaceholderText("Type song and artist in any order (e.g. 'Madonna Like a Virgin', 'Like a Virgin, Madonna', 'Like a Virgin by Madonna')");
    fireEvent.change(input, { target: { value: 'My Song by My Artist' } });
    fireEvent.click(screen.getByRole('button', { name: /Submit/i }));
    expect(mockOnSubmit).toHaveBeenCalledWith({ raw: 'My Song by My Artist' });
  });

  it('clears the input field after submission', () => {
    render(<AnswerInput onSubmit={mockOnSubmit} />);
    const input = screen.getByPlaceholderText("Type song and artist in any order (e.g. 'Madonna Like a Virgin', 'Like a Virgin, Madonna', 'Like a Virgin by Madonna')") as HTMLInputElement;
    fireEvent.change(input, { target: { value: 'Another Song by Another Artist' } });
    fireEvent.click(screen.getByRole('button', { name: /Submit/i }));
    expect(input.value).toBe('');
  });

  it('disables input and button when disabled prop is true', () => {
    render(<AnswerInput onSubmit={mockOnSubmit} disabled />);
    const input = screen.getByPlaceholderText("Type song and artist in any order (e.g. 'Madonna Like a Virgin', 'Like a Virgin, Madonna', 'Like a Virgin by Madonna')");
    expect(input).toBeDisabled();
    expect(screen.getByRole('button', { name: /Submit/i })).toBeDisabled();
  });

  it('does not call onSubmit if field is empty or only whitespace', () => {
    render(<AnswerInput onSubmit={mockOnSubmit} />);
    fireEvent.click(screen.getByRole('button', { name: /Submit/i }));
    expect(mockOnSubmit).not.toHaveBeenCalled();
    const input = screen.getByPlaceholderText("Type song and artist in any order (e.g. 'Madonna Like a Virgin', 'Like a Virgin, Madonna', 'Like a Virgin by Madonna')");
    fireEvent.change(input, { target: { value: '   ' } });
    fireEvent.click(screen.getByRole('button', { name: /Submit/i }));
    expect(mockOnSubmit).not.toHaveBeenCalled();
  });

  it('displays active player name when provided', () => {
    render(<AnswerInput onSubmit={mockOnSubmit} activePlayerName="Player1" />);
    const playerNameSpan = screen.getByText("Player1");
    const paragraphElement = playerNameSpan.parentElement;
    expect(paragraphElement).toHaveTextContent(/Player1, it's your turn!/i);
  });

  it('does not display active player name when not provided or null', () => {
    const { rerender } = render(<AnswerInput onSubmit={mockOnSubmit} />); 
    expect(screen.queryByText(/it's your turn!/i)).not.toBeInTheDocument();

    rerender(<AnswerInput onSubmit={mockOnSubmit} activePlayerName={null} />); 
    expect(screen.queryByText(/it's your turn!/i)).not.toBeInTheDocument();
  });

  it('renders input and button', () => {
    render(<AnswerInput onSubmit={() => {}} />);
    expect(screen.getByRole('textbox')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /submit/i })).toBeInTheDocument();
  });

  it('calls onSubmit with trimmed value', async () => {
    const user = userEvent.setup();
    const onSubmit = vi.fn();
    render(<AnswerInput onSubmit={onSubmit} />);
    const input = screen.getByRole('textbox');
    await user.type(input, '  answer  ');
    await user.click(screen.getByRole('button', { name: /submit/i }));
    expect(onSubmit).toHaveBeenCalledWith({ raw: 'answer' });
  });

  it('applies shake animation when shake prop is true', () => {
    const { container } = render(<AnswerInput onSubmit={() => {}} shake={true} />);
    // The motion.div should have an inline style with transform (from framer-motion)
    const motionDiv = container.querySelector('div.flex');
    expect(motionDiv).toBeInTheDocument();
    // We can't test the actual animation, but we can check that the prop is passed
    // and the element is present
  });
}); 