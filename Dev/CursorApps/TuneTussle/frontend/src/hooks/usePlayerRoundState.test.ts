import { renderHook, act } from '@testing-library/react';
import { usePlayerRoundState } from './usePlayerRoundState';

describe('usePlayerRoundState', () => {
  it('initializes to IDLE', () => {
    const { result } = renderHook(() => usePlayerRoundState());
    expect(result.current.playerState.state).toBe('IDLE');
  });

  it('transitions through states', () => {
    const { result } = renderHook(() => usePlayerRoundState());
    act(() => result.current.buzzIn());
    expect(result.current.playerState.state).toBe('BUZZED_IN');
    act(() => result.current.startAnswering({ raw: 'A by B' }));
    expect(result.current.playerState.state).toBe('ANSWERING');
    expect(result.current.playerState.answer).toEqual({ raw: 'A by B' });
    act(() => result.current.answered({ raw: 'A by B' }));
    expect(result.current.playerState.state).toBe('ANSWERED');
    expect(result.current.playerState.answer).toEqual({ raw: 'A by B' });
    act(() => result.current.timeout());
    expect(result.current.playerState.state).toBe('TIMED_OUT');
    act(() => result.current.reset());
    expect(result.current.playerState.state).toBe('IDLE');
  });
}); 