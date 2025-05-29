import { render, screen, fireEvent } from '@testing-library/react';
import '@testing-library/jest-dom';
import { vi } from 'vitest';
import Button from './Button';

describe('Button Component', () => {
  test('renders with correct label', () => {
    render(<Button label="Click Me" onClick={() => {}} />);
    expect(screen.getByRole('button', { name: /Click Me/i })).toBeInTheDocument();
  });

  test('calls onClick handler when clicked', () => {
    const handleClick = vi.fn();
    render(<Button label="Submit" onClick={handleClick} />);
    fireEvent.click(screen.getByRole('button', { name: /Submit/i }));
    expect(handleClick).toHaveBeenCalledTimes(1);
  });

  test('applies the correct type attribute', () => {
    render(<Button label="Reset" onClick={() => {}} type="reset" />);
    expect(screen.getByRole('button', { name: /Reset/i })).toHaveAttribute('type', 'reset');
  });

  test('defaults to type "button" if no type is provided', () => {
    render(<Button label="Default Type" onClick={() => {}} />);
    expect(screen.getByRole('button', { name: /Default Type/i })).toHaveAttribute('type', 'button');
  });

  test('is disabled when disabled prop is true', () => {
    const handleClick = vi.fn();
    render(<Button label="Disabled" onClick={handleClick} disabled={true} />);
    const buttonElement = screen.getByRole('button', { name: /Disabled/i });
    expect(buttonElement).toBeDisabled();
    fireEvent.click(buttonElement);
    expect(handleClick).not.toHaveBeenCalled();
  });

  test('is not disabled by default', () => {
    render(<Button label="Enabled" onClick={() => {}} />);
    expect(screen.getByRole('button', { name: /Enabled/i })).not.toBeDisabled();
  });
}); 