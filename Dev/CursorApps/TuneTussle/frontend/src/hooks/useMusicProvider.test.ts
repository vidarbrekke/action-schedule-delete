import { renderHook, act } from '@testing-library/react';
import { useMusicProvider } from './useMusicProvider';
import { vi } from 'vitest';

describe('useMusicProvider', () => {
  beforeEach(() => {
    global.fetch = vi.fn();
  });

  afterEach(() => {
    vi.resetAllMocks();
  });

  it('fetches a YouTube link from the backend', async () => {
    (fetch as any).mockResolvedValue({
      ok: true,
      json: async () => ({ link: 'https://youtube.com/link' }),
    });
    const { result } = renderHook(() => useMusicProvider('youtube'));
    let link;
    await act(async () => {
      link = await result.current.fetchTrackLink('Song', 'Artist');
    });
    expect(link).toBe('https://youtube.com/link');
    expect(fetch).toHaveBeenCalledWith(
      expect.stringContaining('/api/music-link?'),
    );
    expect(fetch).toHaveBeenCalledWith(
      expect.stringContaining('provider=youtube'),
    );
  });

  it('throws if fetch returns not ok', async () => {
    (fetch as any).mockResolvedValue({ ok: false });
    const { result } = renderHook(() => useMusicProvider('youtube'));
    await expect(result.current.fetchTrackLink('Song', 'Artist')).rejects.toThrow('Failed to fetch track link');
  });

  it('throws if fetch throws (network error)', async () => {
    (fetch as any).mockRejectedValue(new Error('Network error'));
    const { result } = renderHook(() => useMusicProvider('youtube'));
    await expect(result.current.fetchTrackLink('Song', 'Artist')).rejects.toThrow('Network error');
  });

  it('returns undefined if link is missing in response', async () => {
    (fetch as any).mockResolvedValue({ ok: true, json: async () => ({}) });
    const { result } = renderHook(() => useMusicProvider('youtube'));
    let link;
    await act(async () => {
      link = await result.current.fetchTrackLink('Song', 'Artist');
    });
    expect(link).toBeUndefined();
  });
}); 