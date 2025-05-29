import { useEffect } from 'react';
import type { Socket } from 'socket.io-client';
import type { GameAction } from '../types/game';

interface ReconnectionGameState {
  gameCode: string | null;
  playerName: string | null;
  hasJoinedGame: boolean;
}

interface JoinRoomAck {
  success: boolean;
  role?: string;
  message?: string;
}

export function useSocketReconnectionEffect(
  socket: Socket | null,
  gameState: ReconnectionGameState,
  dispatch: (action: GameAction) => void
) {
  useEffect(() => {
    if (!socket) return;

    const handleReconnect = () => {
      console.log('[useGameLogic] Socket reconnected, attempting to rejoin game');
      if (gameState.gameCode && gameState.playerName && gameState.hasJoinedGame) {
        console.log('[useGameLogic] Rejoining game:', { gameCode: gameState.gameCode, playerName: gameState.playerName });
        socket.emit('joinGameRoom', {
          gameCode: gameState.gameCode,
          playerName: gameState.playerName
        }, (ack: JoinRoomAck) => {
          console.log('[useGameLogic] Rejoin acknowledgement:', ack);
          if (ack && ack.success) {
            dispatch({ type: 'JOIN_SUCCESS', payload: { role: ack.role } });
            socket.emit('getGameState', { gameCode: gameState.gameCode }, (gameStateData: unknown) => {
              dispatch({ type: 'SET_FULL_GAME_STATE', payload: gameStateData });
            });
          } else {
            console.error('[useGameLogic] Failed to rejoin game:', ack?.message || 'Unknown error');
            dispatch({
              type: 'SET_ERROR',
              payload: {
                message: `Failed to rejoin game: ${ack?.message || 'Unknown error'}`,
                isCritical: true
              }
            });
          }
        });
      }
    };

    const handleDisconnect = (reason: string) => {
      console.log('[useGameLogic] Socket disconnected:', reason);
      if (reason === 'io server disconnect') {
        socket.connect();
      }
    };

    socket.on('reconnect', handleReconnect);
    socket.on('disconnect', handleDisconnect);

    return () => {
      socket.off('reconnect', handleReconnect);
      socket.off('disconnect', handleDisconnect);
    };
  }, [socket, gameState.gameCode, gameState.playerName, gameState.hasJoinedGame, dispatch]);
}
