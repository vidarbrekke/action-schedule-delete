import { useEffect } from 'react';
import type { Socket } from 'socket.io-client';
import type { GameState, GameAction } from '../types/game';

export function useSocketEvents(socket: Socket | null, dispatch: (action: GameAction) => void) {
  useEffect(() => {
    if (!socket) return;

    const handleGameState = (data: GameState) => {
      dispatch({ type: 'GAME_STATE_INIT', payload: data });
    };

    socket.on('gameState', handleGameState);

    return () => {
      socket.off('gameState', handleGameState);
    };
  }, [socket, dispatch]);
}
