import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import BuzzButton from './BuzzButton';
import { gameAudioManager } from '../services/gameAudioManager';

// Mock the gameAudioManager
vi.mock('../services/gameAudioManager', () => ({
  gameAudioManager: {
    playBuzzerSound: vi.fn()
  }
}));

describe('BuzzButton', () => {
  const mockOnClick = vi.fn();

  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('Basic functionality', () => {
    it('renders correctly', () => {
      render(<BuzzButton onClick={mockOnClick} />);
      expect(screen.getByRole('button')).toBeInTheDocument();
    });

    it('calls onClick and plays sound when clicked and not disabled', () => {
      render(<BuzzButton onClick={mockOnClick} />);
      fireEvent.click(screen.getByRole('button'));
      expect(mockOnClick).toHaveBeenCalledTimes(1);
      expect(gameAudioManager.playBuzzerSound).toHaveBeenCalledTimes(1);
    });

    it('renders with default "BUZZ IN!" content when no label provided', () => {
      render(<BuzzButton onClick={mockOnClick} />);
      expect(screen.getByText('BUZZ')).toBeInTheDocument();
      expect(screen.getByText('IN!')).toBeInTheDocument();
    });

    it('shows active state when isActive prop is true', () => {
      render(<BuzzButton onClick={mockOnClick} isActive />);
      expect(screen.getByText("YOU'RE")).toBeInTheDocument();
      expect(screen.getByText("IN!")).toBeInTheDocument();
      expect(screen.getByRole('button')).toHaveClass('from-green-400');
    });

    it('has default styling when not disabled or active', () => {
      render(<BuzzButton onClick={mockOnClick} />);
      const button = screen.getByRole('button');
      expect(button).toHaveClass('from-red-500');
    });
  });

  describe('Disabled state', () => {
    it('does not call onClick when disabled', () => {
      render(<BuzzButton onClick={mockOnClick} disabled />);
      fireEvent.click(screen.getByRole('button'));
      expect(mockOnClick).not.toHaveBeenCalled();
      expect(gameAudioManager.playBuzzerSound).not.toHaveBeenCalled();
    });

    it('shows disabled styling when disabled', () => {
      render(<BuzzButton onClick={mockOnClick} disabled />);
      const button = screen.getByRole('button');
      expect(button).toBeDisabled();
      expect(button).toHaveClass('bg-gray-300');
      expect(screen.getByText('WAIT')).toBeInTheDocument();
    });

    it('has correct accessibility attributes when disabled', () => {
      render(<BuzzButton onClick={mockOnClick} disabled />);
      expect(screen.getByRole('button')).toBeDisabled();
    });
  });

  describe('Active state', () => {
    it('shows green styling when active', () => {
      render(<BuzzButton onClick={mockOnClick} isActive />);
      expect(screen.getByRole('button')).toHaveClass('from-green-400');
    });

    it('still calls onClick when active and clicked', () => {
      render(<BuzzButton onClick={mockOnClick} isActive />);
      fireEvent.click(screen.getByRole('button'));
      expect(mockOnClick).toHaveBeenCalledTimes(1);
      expect(gameAudioManager.playBuzzerSound).toHaveBeenCalledTimes(1);
    });
  });

  describe('Accessibility', () => {
    it('button has proper role and is focusable', () => {
      render(<BuzzButton onClick={mockOnClick} />);
      const button = screen.getByRole('button');
      expect(button).toBeInTheDocument();
      expect(button.tagName).toBe('BUTTON');
    });

    it('button responds to keyboard events', () => {
      render(<BuzzButton onClick={mockOnClick} />);
      const button = screen.getByRole('button');
      button.focus();
      fireEvent.keyDown(button, { key: 'Enter' });
      fireEvent.keyUp(button, { key: 'Enter' });
      expect(button).toHaveFocus();
    });
  });

  describe('Sound functionality', () => {
    it('plays sound on click when enabled', () => {
      render(<BuzzButton onClick={mockOnClick} />);
      fireEvent.click(screen.getByRole('button'));
      expect(gameAudioManager.playBuzzerSound).toHaveBeenCalledTimes(1);
    });

    it('does not play sound when disabled', () => {
      render(<BuzzButton onClick={mockOnClick} disabled />);
      fireEvent.click(screen.getByRole('button'));
      expect(gameAudioManager.playBuzzerSound).not.toHaveBeenCalled();
    });

    it('plays sound when active and clicked', () => {
      render(<BuzzButton onClick={mockOnClick} isActive />);
      fireEvent.click(screen.getByRole('button'));
      expect(gameAudioManager.playBuzzerSound).toHaveBeenCalledTimes(1);
    });
  });

  describe('Visual states', () => {
    it('should show correct visual states regardless of sound functionality', () => {
      // Mock sound failure but visual should still work
      vi.mocked(gameAudioManager.playBuzzerSound).mockImplementation(() => {
        throw new Error('Sound failed');
      });
      
      render(<BuzzButton onClick={mockOnClick} />);
      
      const button = screen.getByRole('button');
      expect(button).toHaveClass('from-red-500');
      expect(button).not.toHaveClass('bg-yellow-500');
      
      // Click should still work even if sound fails
      expect(() => fireEvent.click(button)).not.toThrow();
      expect(mockOnClick).toHaveBeenCalledTimes(1);
    });
  });
}); 