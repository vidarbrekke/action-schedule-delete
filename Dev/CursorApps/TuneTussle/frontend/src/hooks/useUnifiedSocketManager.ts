import { useEffect, useRef, useCallback } from 'react';
import type { Socket } from 'socket.io-client';

interface HeartbeatAck {
  success: boolean;
  message?: string;
}

interface JoinRoomAck {
  success: boolean;
  role?: string;
  message?: string;
}

interface ConnectionState {
  wasHidden: boolean;
  isHeartbeatActive: boolean;
  lastHeartbeat: number;
  reconnectionAttempts: number;
}

interface UnifiedSocketManagerProps {
  socket: Socket | null;
  isConnected: boolean;
  gameCode: string | null;
  playerName: string | null;
  hasJoinedGame: boolean;
  onStateRefresh: (gameStateData: unknown) => void;
  onConnectionLost: () => void;
  onConnectionRestored: () => void;
  onRejoinSuccess: (ack: JoinRoomAck) => void;
  onRejoinError: (error: string) => void;
}

export const useUnifiedSocketManager = ({
  socket,
  isConnected,
  gameCode,
  playerName,
  hasJoinedGame,
  onStateRefresh,
  onConnectionLost,
  onConnectionRestored,
  onRejoinSuccess,
  onRejoinError,
}: UnifiedSocketManagerProps) => {
  const connectionStateRef = useRef<ConnectionState>({
    wasHidden: false,
    isHeartbeatActive: false,
    lastHeartbeat: Date.now(),
    reconnectionAttempts: 0,
  });

  const heartbeatIntervalRef = useRef<NodeJS.Timeout | null>(null);
  const reconnectionTimeoutRef = useRef<NodeJS.Timeout | null>(null);
  const stateRefreshTimeoutRef = useRef<NodeJS.Timeout | null>(null);

  // Cleanup function for all timeouts and intervals
  const cleanup = useCallback(() => {
    if (heartbeatIntervalRef.current) {
      clearInterval(heartbeatIntervalRef.current);
      heartbeatIntervalRef.current = null;
    }
    if (reconnectionTimeoutRef.current) {
      clearTimeout(reconnectionTimeoutRef.current);
      reconnectionTimeoutRef.current = null;
    }
    if (stateRefreshTimeoutRef.current) {
      clearTimeout(stateRefreshTimeoutRef.current);
      stateRefreshTimeoutRef.current = null;
    }
    connectionStateRef.current.isHeartbeatActive = false;
  }, []);

  // Heartbeat management
  const startHeartbeat = useCallback(() => {
    if (!socket || !isConnected || !gameCode || connectionStateRef.current.isHeartbeatActive) {
      return;
    }

    cleanup(); // Ensure no duplicate intervals

    heartbeatIntervalRef.current = setInterval(() => {
      if (socket && isConnected && gameCode) {
        const now = Date.now();
        socket.emit('heartbeat', { gameCode, timestamp: now }, (ack: HeartbeatAck) => {
          if (ack?.success) {
            connectionStateRef.current.lastHeartbeat = now;
            connectionStateRef.current.reconnectionAttempts = 0;
          } else {
            console.warn('[UnifiedSocketManager] Heartbeat failed, triggering state refresh');
            requestStateRefresh();
          }
        });
      }
    }, 10000); // Heartbeat every 10 seconds

    connectionStateRef.current.isHeartbeatActive = true;
    console.log('[UnifiedSocketManager] Heartbeat started');
  }, [socket, isConnected, gameCode, cleanup, requestStateRefresh]);

  const stopHeartbeat = useCallback(() => {
    if (heartbeatIntervalRef.current) {
      clearInterval(heartbeatIntervalRef.current);
      heartbeatIntervalRef.current = null;
      connectionStateRef.current.isHeartbeatActive = false;
      console.log('[UnifiedSocketManager] Heartbeat stopped');
    }
  }, []);

  // State refresh with debouncing
  const requestStateRefresh = useCallback(() => {
    if (!socket || !isConnected || !gameCode) {
      return;
    }

    // Clear any pending state refresh
    if (stateRefreshTimeoutRef.current) {
      clearTimeout(stateRefreshTimeoutRef.current);
    }

    // Debounce state refresh requests
    stateRefreshTimeoutRef.current = setTimeout(() => {
      console.log('[UnifiedSocketManager] Requesting state refresh');
      socket.emit('getGameState', { gameCode }, (gameStateData: unknown) => {
        if (gameStateData) {
          console.log('[UnifiedSocketManager] Received fresh game state');
          onStateRefresh(gameStateData);
        } else {
          console.warn('[UnifiedSocketManager] Failed to get fresh game state');
        }
      });
    }, 500); // 500ms debounce
  }, [socket, isConnected, gameCode, onStateRefresh]);

  // Rejoin game room
  const rejoinGameRoom = useCallback(() => {
    if (!socket || !isConnected || !gameCode || !playerName || !hasJoinedGame) {
      return;
    }

    console.log('[UnifiedSocketManager] Rejoining game room');
    socket.emit('joinGameRoom', { gameCode, playerName }, (ack: JoinRoomAck) => {
      if (ack?.success) {
        console.log('[UnifiedSocketManager] Successfully rejoined room');
        onRejoinSuccess(ack);
        onConnectionRestored();
        connectionStateRef.current.reconnectionAttempts = 0;
      } else {
        console.error('[UnifiedSocketManager] Failed to rejoin room:', ack?.message);
        connectionStateRef.current.reconnectionAttempts++;
        onRejoinError(ack?.message || 'Unknown error');

        // Retry with exponential backoff if attempts are reasonable
        if (connectionStateRef.current.reconnectionAttempts < 3) {
          const delay = Math.min(1000 * Math.pow(2, connectionStateRef.current.reconnectionAttempts), 10000);
          setTimeout(() => rejoinGameRoom(), delay);
        } else {
          onConnectionLost();
        }
      }
    });
  }, [socket, isConnected, gameCode, playerName, hasJoinedGame, onRejoinSuccess, onConnectionRestored, onRejoinError, onConnectionLost]);

  // Complete recovery process
  const performRecovery = useCallback(() => {
    console.log('[UnifiedSocketManager] Performing connection recovery');

    // Clear any existing recovery timeout
    if (reconnectionTimeoutRef.current) {
      clearTimeout(reconnectionTimeoutRef.current);
    }

    reconnectionTimeoutRef.current = setTimeout(() => {
      if (socket && isConnected) {
        console.log('[UnifiedSocketManager] Socket appears connected, performing recovery');
        rejoinGameRoom();
        requestStateRefresh();
        startHeartbeat();
      } else {
        console.log('[UnifiedSocketManager] Socket not connected, waiting for reconnection');
        onConnectionLost();
      }
    }, 1000);
  }, [socket, isConnected, rejoinGameRoom, requestStateRefresh, startHeartbeat, onConnectionLost]);

  // Page visibility change handler
  const handleVisibilityChange = useCallback(() => {
    const isHidden = document.hidden;
    console.log('[UnifiedSocketManager] Page visibility changed:', isHidden ? 'hidden' : 'visible');

    if (isHidden) {
      connectionStateRef.current.wasHidden = true;
      stopHeartbeat();
    } else {
      if (connectionStateRef.current.wasHidden) {
        console.log('[UnifiedSocketManager] Page visible after being hidden, initiating recovery');
        performRecovery();
        connectionStateRef.current.wasHidden = false;
      } else {
        // Page became visible but wasn't previously hidden (initial load)
        startHeartbeat();
      }
    }
  }, [stopHeartbeat, performRecovery, startHeartbeat]);

  // Socket event handlers
  const handleReconnect = useCallback(() => {
    console.log('[UnifiedSocketManager] Socket reconnected');
    connectionStateRef.current.reconnectionAttempts = 0;

    if (connectionStateRef.current.wasHidden || !document.hidden) {
      performRecovery();
    } else {
      startHeartbeat();
    }
  }, [performRecovery, startHeartbeat]);

  const handleDisconnect = useCallback((reason: string) => {
    console.log('[UnifiedSocketManager] Socket disconnected:', reason);
    stopHeartbeat();
    onConnectionLost();

    // Auto-reconnect for server-initiated disconnections
    if (reason === 'io server disconnect') {
      socket?.connect();
    }
  }, [stopHeartbeat, onConnectionLost, socket]);

  // Set up page visibility listener
  useEffect(() => {
    if (typeof document !== 'undefined') {
      document.addEventListener('visibilitychange', handleVisibilityChange);

      // Start heartbeat if page is initially visible and conditions are met
      if (!document.hidden && socket && isConnected && gameCode) {
        startHeartbeat();
      }

      return () => {
        document.removeEventListener('visibilitychange', handleVisibilityChange);
      };
    }
  }, [handleVisibilityChange, startHeartbeat, socket, isConnected, gameCode]);

  // Set up socket event listeners
  useEffect(() => {
    if (!socket) return;

    socket.on('reconnect', handleReconnect);
    socket.on('disconnect', handleDisconnect);

    return () => {
      socket.off('reconnect', handleReconnect);
      socket.off('disconnect', handleDisconnect);
    };
  }, [socket, handleReconnect, handleDisconnect]);

  // Cleanup on unmount
  useEffect(() => {
    return () => {
      cleanup();
    };
  }, [cleanup]);

  // Start/stop heartbeat based on connection state
  useEffect(() => {
    if (socket && isConnected && gameCode && hasJoinedGame && !document.hidden) {
      if (!connectionStateRef.current.isHeartbeatActive) {
        startHeartbeat();
      }
    } else {
      stopHeartbeat();
    }
  }, [socket, isConnected, gameCode, hasJoinedGame, startHeartbeat, stopHeartbeat]);

  return {
    forceStateRefresh: requestStateRefresh,
    forceRejoin: rejoinGameRoom,
    isHeartbeatActive: connectionStateRef.current.isHeartbeatActive,
    lastHeartbeat: connectionStateRef.current.lastHeartbeat,
    reconnectionAttempts: connectionStateRef.current.reconnectionAttempts,
  };
};
