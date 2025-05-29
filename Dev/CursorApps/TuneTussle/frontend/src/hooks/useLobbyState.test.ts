import { renderHook, act } from '@testing-library/react';
import { useLobbyState } from './useLobbyState';

describe('useLobbyState', () => {
  it('initializes with default lobby state', () => {
    const { result } = renderHook(() => useLobbyState({ songList: null }, null));
    const [lobbyState] = result.current;
    expect(lobbyState.isLoadingRole).toBe(true);
    expect(lobbyState.isGeneratingSongs).toBe(true);
    expect(lobbyState.isGameReady).toBe(false);
  });

  it('sets isGameReady true when enough songs are generated', () => {
    const { result, rerender } = renderHook(
      ({ songList, expected }) => useLobbyState({ songList }, expected),
      { initialProps: { songList: ['a', 'b', 'c'], expected: 3 } }
    );
    let [lobbyState] = result.current;
    expect(lobbyState.isGameReady).toBe(true);
    expect(lobbyState.isGeneratingSongs).toBe(false);

    rerender({ songList: ['a', 'b'], expected: 3 });
    [lobbyState] = result.current;
    expect(lobbyState.isGameReady).toBe(false);
    expect(lobbyState.isGeneratingSongs).toBe(true);
  });

  it('allows manual updates to lobby state', () => {
    const { result } = renderHook(() => useLobbyState({ songList: [] }, 2));
    const [, setLobbyState] = result.current;
    act(() => {
      setLobbyState((prev: any) => ({ ...prev, isLoadingRole: false }));
    });
    const [lobbyState] = result.current;
    expect(lobbyState.isLoadingRole).toBe(false);
  });

  it('does not cause infinite re-renders when gameState changes frequently', () => {
    let renderCount = 0;
    const { result, rerender } = renderHook(
      ({ gameState, expected }) => {
        renderCount++;
        return useLobbyState(gameState, expected);
      },
      { 
        initialProps: { 
          gameState: { songList: [] as string[], expectedSongCount: 3 }, 
          expected: 3 
        } 
      }
    );

    // Reset render count after initial render
    renderCount = 0;

    // Simulate multiple rapid gameState changes
    for (let i = 0; i < 5; i++) {
      rerender({ 
        gameState: { 
          songList: Array(i).fill('song') as string[], 
          expectedSongCount: 3
        }, 
        expected: 3 
      });
    }

    // Should not have excessive re-renders (allowing some reasonable number)
    expect(renderCount).toBeLessThan(20);
    
    const [lobbyState] = result.current;
    expect(lobbyState.isGameReady).toBe(true); // 4 songs >= 3 expected
    expect(lobbyState.isLoadingRole).toBe(false); // role is set
  });
}); 