import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { TrackLinkButton } from './TrackLinkButton';
import { vi } from 'vitest';

describe('TrackLinkButton', () => {
  beforeEach(() => {
    global.fetch = vi.fn();
  });

  afterEach(() => {
    vi.resetAllMocks();
  });

  it('fetches and displays a YouTube link when button is clicked', async () => {
    const mockFetch = vi.mocked(fetch);
    mockFetch.mockResolvedValue({
      ok: true,
      json: async () => ({ link: 'https://youtube.com/link' }),
    } as Response);
    render(<TrackLinkButton title="Song" artist="Artist" />);
    fireEvent.click(screen.getByText('Get Track Link'));
    await waitFor(() => {
      expect(screen.getByText('Listen')).toBeInTheDocument();
      expect(screen.getByText('Listen')).toHaveAttribute('href', 'https://youtube.com/link');
    });
  });

  it('shows an error if fetch fails', async () => {
    const mockFetch = vi.mocked(fetch);
    mockFetch.mockResolvedValue({ ok: false } as Response);
    render(<TrackLinkButton title="Song" artist="Artist" />);
    fireEvent.click(screen.getByText('Get Track Link'));
    await waitFor(() => {
      expect(screen.getByText(/Failed to fetch track link/)).toBeInTheDocument();
    });
  });
}); 