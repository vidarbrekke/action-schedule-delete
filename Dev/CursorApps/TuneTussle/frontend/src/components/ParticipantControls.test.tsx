import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { ParticipantControls } from './ParticipantControls';

describe('ParticipantControls', () => {
  it('renders the ready status indicator', () => {
    render(<ParticipantControls />);
    expect(screen.getByText('Ready to Play')).toBeInTheDocument();
    
    // Check that the status indicator has the correct styling
    const statusElement = screen.getByRole('status');
    expect(statusElement).toHaveAttribute('aria-label', 'Participant is ready');
  });

  it('displays the player status heading', () => {
    render(<ParticipantControls />);
    expect(screen.getByText('Player Status')).toBeInTheDocument();
  });

  it('shows waiting message for judge', () => {
    render(<ParticipantControls />);
    expect(screen.getByText('Waiting for the judge to start the game')).toBeInTheDocument();
  });

  it('should be accessible with role and aria-label', () => {
    render(<ParticipantControls />);
    const status = screen.getByText('Ready to Play').closest('span');
    expect(status).toHaveAttribute('role', 'status');
    expect(status).toHaveAttribute('aria-label', 'Participant is ready');
  });

  it('should match snapshot', () => {
    const { container } = render(<ParticipantControls />);
    expect(container.firstChild).toMatchSnapshot();
  });
}); 