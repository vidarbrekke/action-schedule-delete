import { renderHook, act } from '@testing-library/react';
import { useGameAnimations } from './useGameAnimations';
import { vi, describe, it, expect, afterEach, beforeEach } from 'vitest';

beforeEach(() => {
  vi.useFakeTimers();
});
afterEach(() => {
  vi.useRealTimers();
});

describe('useGameAnimations', () => {
  it('should trigger shake animation and reset after 500ms', () => {
    const { result } = renderHook(() => useGameAnimations());
    expect(result.current.shakeInput).toBe(false);
    act(() => {
      result.current.triggerShake();
    });
    expect(result.current.shakeInput).toBe(true);
    act(() => {
      vi.advanceTimersByTime(500);
    });
    expect(result.current.shakeInput).toBe(false);
  });

  it('should trigger confetti and reset after 3000ms', () => {
    const { result } = renderHook(() => useGameAnimations());
    expect(result.current.showConfetti).toBe(false);
    act(() => {
      result.current.triggerConfetti();
    });
    expect(result.current.showConfetti).toBe(true);
    act(() => {
      vi.advanceTimersByTime(3000);
    });
    expect(result.current.showConfetti).toBe(false);
  });

  it('should trigger pulse and reset after 500ms', () => {
    const { result } = renderHook(() => useGameAnimations());
    expect(result.current.pulse).toBe(false);
    act(() => {
      result.current.triggerPulse();
    });
    expect(result.current.pulse).toBe(true);
    act(() => {
      vi.advanceTimersByTime(500);
    });
    expect(result.current.pulse).toBe(false);
  });
}); 