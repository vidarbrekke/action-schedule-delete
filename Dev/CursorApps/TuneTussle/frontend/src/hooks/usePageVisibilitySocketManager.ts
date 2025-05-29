import { useEffect, useRef, useCallback } from 'react';
import type { Socket } from 'socket.io-client';

interface HeartbeatAck {
  success: boolean;
  message?: string;
}

interface JoinRoomAck {
  success: boolean;
  message?: string;
}

interface PageVisibilitySocketManagerProps {
  socket: Socket | null;
  isConnected: boolean;
  gameCode: string | null;
  playerName: string | null;
  hasJoinedGame: boolean;
  onStateRefresh: () => void;
  onConnectionLost: () => void;
  onConnectionRestored: () => void;
}

export const usePageVisibilitySocketManager = ({
  socket,
  isConnected,
  gameCode,
  playerName,
  hasJoinedGame,
  onStateRefresh,
  onConnectionLost,
  onConnectionRestored,
}: PageVisibilitySocketManagerProps) => {
  const wasHiddenRef = useRef(false);
  const reconnectionTimeoutRef = useRef<NodeJS.Timeout | null>(null);
  const heartbeatIntervalRef = useRef<NodeJS.Timeout | null>(null);
  const lastHeartbeatRef = useRef<number>(Date.now());

  // Heartbeat mechanism to detect stale connections
  const startHeartbeat = useCallback(() => {
    if (heartbeatIntervalRef.current) {
      clearInterval(heartbeatIntervalRef.current);
    }

    heartbeatIntervalRef.current = setInterval(() => {
      if (socket && isConnected && gameCode) {
        const now = Date.now();
        socket.emit('heartbeat', { gameCode, timestamp: now }, (ack: HeartbeatAck) => {
          if (ack?.success) {
            lastHeartbeatRef.current = now;
          } else {
            console.warn('[PageVisibilitySocketManager] Heartbeat failed, connection may be stale');
            // If heartbeat fails, trigger a state refresh
            onStateRefresh();
          }
        });
      }
    }, 10000); // Heartbeat every 10 seconds
  }, [socket, isConnected, gameCode, onStateRefresh]);

  const stopHeartbeat = useCallback(() => {
    if (heartbeatIntervalRef.current) {
      clearInterval(heartbeatIntervalRef.current);
      heartbeatIntervalRef.current = null;
    }
  }, []);

  // Force a complete state synchronization
  const forceStateSync = useCallback(() => {
    if (!socket || !isConnected || !gameCode || !playerName || !hasJoinedGame) {
      return;
    }

    console.log('[PageVisibilitySocketManager] Forcing state synchronization');

    // Request fresh game state
    socket.emit('getGameState', { gameCode }, (gameStateData: unknown) => {
      if (gameStateData) {
        console.log('[PageVisibilitySocketManager] Received fresh game state:', gameStateData);
        onStateRefresh();
      } else {
        console.warn('[PageVisibilitySocketManager] Failed to get fresh game state');
      }
    });

    // Also rejoin the room to ensure we're receiving events
    socket.emit('joinGameRoom', { gameCode, playerName }, (ack: JoinRoomAck) => {
      if (ack?.success) {
        console.log('[PageVisibilitySocketManager] Successfully rejoined room after visibility change');
        onConnectionRestored();
      } else {
        console.error('[PageVisibilitySocketManager] Failed to rejoin room:', ack?.message);
        onConnectionLost();
      }
    });
  }, [socket, isConnected, gameCode, playerName, hasJoinedGame, onStateRefresh, onConnectionRestored, onConnectionLost]);

  // Handle page visibility changes
  const handleVisibilityChange = useCallback(() => {
    const isHidden = document.hidden;

    console.log('[PageVisibilitySocketManager] Page visibility changed:', isHidden ? 'hidden' : 'visible');

    if (isHidden) {
      // Page is now hidden (user navigated to another tab/app, etc.)
      wasHiddenRef.current = true;
      stopHeartbeat();
      console.log('[PageVisibilitySocketManager] Page hidden, stopping heartbeat');
    } else {
      // Page is now visible (user returned from another tab/app, etc.)
      if (wasHiddenRef.current) {
        console.log('[PageVisibilitySocketManager] Page visible after being hidden, initiating recovery');

        // Clear any existing reconnection timeout
        if (reconnectionTimeoutRef.current) {
          clearTimeout(reconnectionTimeoutRef.current);
        }

        // Give the socket a moment to stabilize, then force sync
        reconnectionTimeoutRef.current = setTimeout(() => {
          if (socket && isConnected) {
            console.log('[PageVisibilitySocketManager] Socket appears connected, forcing state sync');
            forceStateSync();
          } else {
            console.log('[PageVisibilitySocketManager] Socket not connected, waiting for reconnection');
            onConnectionLost();
          }
          startHeartbeat();
        }, 1000);

        wasHiddenRef.current = false;
      } else {
        // Page became visible but wasn't previously hidden (initial load)
        startHeartbeat();
      }
    }
  }, [socket, isConnected, forceStateSync, startHeartbeat, stopHeartbeat, onConnectionLost]);

  // Set up page visibility listener
  useEffect(() => {
    if (typeof document !== 'undefined') {
      document.addEventListener('visibilitychange', handleVisibilityChange);

      // Start heartbeat if page is initially visible
      if (!document.hidden) {
        startHeartbeat();
      }

      return () => {
        document.removeEventListener('visibilitychange', handleVisibilityChange);
        stopHeartbeat();
        if (reconnectionTimeoutRef.current) {
          clearTimeout(reconnectionTimeoutRef.current);
        }
      };
    }
  }, [handleVisibilityChange, startHeartbeat, stopHeartbeat]);

  // Handle socket reconnection events
  useEffect(() => {
    if (!socket) return;

    const handleReconnect = () => {
      console.log('[PageVisibilitySocketManager] Socket reconnected');
      if (wasHiddenRef.current || !document.hidden) {
        // If we were hidden or are currently visible, force a state sync
        setTimeout(() => forceStateSync(), 500);
      }
      startHeartbeat();
    };

    const handleDisconnect = (reason: string) => {
      console.log('[PageVisibilitySocketManager] Socket disconnected:', reason);
      stopHeartbeat();
      onConnectionLost();
    };

    socket.on('reconnect', handleReconnect);
    socket.on('disconnect', handleDisconnect);

    return () => {
      socket.off('reconnect', handleReconnect);
      socket.off('disconnect', handleDisconnect);
    };
  }, [socket, forceStateSync, startHeartbeat, stopHeartbeat, onConnectionLost]);

  // Cleanup on unmount
  useEffect(() => {
    return () => {
      stopHeartbeat();
      if (reconnectionTimeoutRef.current) {
        clearTimeout(reconnectionTimeoutRef.current);
      }
    };
  }, [stopHeartbeat]);

  return {
    forceStateSync,
    isHeartbeatActive: !!heartbeatIntervalRef.current,
    lastHeartbeat: lastHeartbeatRef.current,
  };
};
