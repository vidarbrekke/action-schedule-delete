import { renderHook, act } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { useAudioAutoPause } from '../useAudioAutoPause';

interface MockAudioElement extends Partial<HTMLAudioElement> {
  pause: () => void;
  paused: boolean;
}

describe('useAudioAutoPause', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('should initialize with correct default values', () => {
    const { result } = renderHook(() => useAudioAutoPause({}));

    expect(result.current.isAutoPaused).toBe(false);
    expect(result.current.audioRef).toBeDefined();
    expect(typeof result.current.resetAutoPause).toBe('function');
  });

  it('should auto-pause audio when player buzzes in', () => {
    const mockPause = vi.fn();
    const { result, rerender } = renderHook(
      ({ activePlayerName }: { activePlayerName: string | null }) => useAudioAutoPause({ activePlayerName }),
      { initialProps: { activePlayerName: null } }
    );

    // Mock the audio element
    result.current.audioRef.current = {
      pause: mockPause,
      paused: false,
    } as MockAudioElement;

    // Initially no auto-pause
    expect(result.current.isAutoPaused).toBe(false);

    // Player buzzes in
    rerender({ activePlayerName: 'Alice' });

    expect(result.current.isAutoPaused).toBe(true);
    expect(mockPause).toHaveBeenCalledOnce();
  });

  it('should not auto-pause if audio is already paused', () => {
    const mockPause = vi.fn();
    const { result, rerender } = renderHook(
      ({ activePlayerName }: { activePlayerName: string | null }) => useAudioAutoPause({ activePlayerName }),
      { initialProps: { activePlayerName: null } }
    );

    // Mock the audio element as already paused
    result.current.audioRef.current = {
      pause: mockPause,
      paused: true,
    } as MockAudioElement;

    rerender({ activePlayerName: 'Alice' });

    expect(result.current.isAutoPaused).toBe(false);
    expect(mockPause).not.toHaveBeenCalled();
  });

  it('should reset auto-pause state when no player is active', () => {
    const { result, rerender } = renderHook(
      ({ activePlayerName }: { activePlayerName: string | null }) => useAudioAutoPause({ activePlayerName }),
      { initialProps: { activePlayerName: null } }
    );

    // Mock the audio element
    result.current.audioRef.current = {
      pause: vi.fn(),
      paused: false,
    } as MockAudioElement;

    // Player buzzes in
    rerender({ activePlayerName: 'Alice' });
    expect(result.current.isAutoPaused).toBe(true);

    // No player active
    rerender({ activePlayerName: null });
    expect(result.current.isAutoPaused).toBe(false);
  });

  it('should reset auto-pause state when song changes', () => {
    const { result, rerender } = renderHook(
      ({ currentSongUrl, activePlayerName }: { currentSongUrl: string; activePlayerName: string | null }) =>
        useAudioAutoPause({ currentSongUrl, activePlayerName }),
      {
        initialProps: {
          currentSongUrl: 'song1.mp3',
          activePlayerName: null
        }
      }
    );

    // Mock the audio element
    result.current.audioRef.current = {
      pause: vi.fn(),
      paused: false,
    } as MockAudioElement;

    // Player buzzes in
    rerender({ currentSongUrl: 'song1.mp3', activePlayerName: 'Alice' });
    expect(result.current.isAutoPaused).toBe(true);

    // Song changes
    rerender({ currentSongUrl: 'song2.mp3', activePlayerName: 'Alice' });
    expect(result.current.isAutoPaused).toBe(false);
  });

  it('should not auto-pause when disabled', () => {
    const mockPause = vi.fn();
    const { result, rerender } = renderHook(
      ({ activePlayerName, enabled }: { activePlayerName: string | null; enabled: boolean }) =>
        useAudioAutoPause({ activePlayerName, enabled }),
      {
        initialProps: {
          activePlayerName: null,
          enabled: false
        }
      }
    );

    // Mock the audio element
    result.current.audioRef.current = {
      pause: mockPause,
      paused: false,
    } as MockAudioElement;

    rerender({ activePlayerName: 'Alice', enabled: false });

    expect(result.current.isAutoPaused).toBe(false);
    expect(mockPause).not.toHaveBeenCalled();
  });

  it('should manually reset auto-pause state', () => {
    const { result, rerender } = renderHook(
      ({ activePlayerName }: { activePlayerName: string | null }) => useAudioAutoPause({ activePlayerName }),
      { initialProps: { activePlayerName: null } }
    );

    // Mock the audio element
    result.current.audioRef.current = {
      pause: vi.fn(),
      paused: false,
    } as MockAudioElement;

    // Player buzzes in
    rerender({ activePlayerName: 'Alice' });
    expect(result.current.isAutoPaused).toBe(true);

    // Manual reset
    act(() => {
      result.current.resetAutoPause();
    });

    expect(result.current.isAutoPaused).toBe(false);
  });

  it('should pause audio on unmount', () => {
    const mockPause = vi.fn();
    const { result, unmount } = renderHook(() => useAudioAutoPause({}));

    // Mock the audio element
    result.current.audioRef.current = {
      pause: mockPause,
      paused: false,
    } as MockAudioElement;

    unmount();

    expect(mockPause).toHaveBeenCalledOnce();
  });

  it('should handle missing audio element gracefully', () => {
    const { result, rerender } = renderHook(
      ({ activePlayerName }: { activePlayerName: string | null }) => useAudioAutoPause({ activePlayerName }),
      { initialProps: { activePlayerName: null } }
    );

    // Set audio ref to null to simulate missing element
    (result.current.audioRef as React.MutableRefObject<HTMLAudioElement | null>).current = null;

    // Should not throw error when audio element is null
    expect(() => {
      rerender({ activePlayerName: 'Alice' });
    }).not.toThrow();

    expect(result.current.isAutoPaused).toBe(false);
  });
});
