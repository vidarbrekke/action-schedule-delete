import { useState, useEffect } from 'react';

export interface LobbyState {
  isLoadingRole: boolean;
  isStartingGame: boolean;
  startGameError: string | null;
  isGeneratingSongs: boolean;
  isGameReady: boolean;
  expectedSongCount: number | null;
}

interface GameStateForLobby {
  role?: string;
  songList?: unknown[];
  expectedSongCount?: number;
  expected?: number;
}

type InitialLobbyState = LobbyState | number | null;

export function useLobbyState(gameState: GameStateForLobby, initialState: InitialLobbyState = null) {
  const initial = typeof initialState === 'number'
    ? { expectedSongCount: initialState, expected: initialState }
    : initialState;

  const [lobbyState, setLobbyState] = useState<LobbyState>(() => ({
    isLoadingRole: true,
    isGeneratingSongs: true,
    isGameReady: false,
    startGameError: null,
    isStartingGame: false,
    expectedSongCount: null,
    ...initial,
  }));

  // Handle role loading
  useEffect(() => {
    if (gameState?.role) {
      setLobbyState((prev: LobbyState) => ({
        ...prev,
        isLoadingRole: false,
      }));
    }
  }, [gameState?.role]);

  // Handle song generation status
  useEffect(() => {
    const expected = (typeof initialState === 'number' ? initialState : initialState?.expectedSongCount)
      ?? gameState?.expectedSongCount
      ?? gameState?.expected
      ?? null;

    if (Array.isArray(gameState?.songList) && expected !== null) {
      const songCount = gameState.songList.length;
      const hasEnoughSongs = songCount >= expected;

      setLobbyState((prev: LobbyState) => ({
        ...prev,
        isGeneratingSongs: !hasEnoughSongs,
        isGameReady: hasEnoughSongs,
      }));
    }
  }, [gameState?.songList?.length, gameState?.expectedSongCount, gameState?.expected, gameState.songList, initialState]);

  return [lobbyState, setLobbyState] as const;
}
