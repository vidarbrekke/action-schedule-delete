import React, { createContext, useContext, useEffect, useState, useCallback } from 'react';
import type { ReactNode } from 'react';
import io from 'socket.io-client';
import { getSocketUrl, SOCKET_CONFIG } from '../constants/socketConfig';

// Use a more flexible socket type to work around Socket.IO typing complexities
interface SocketInstance {
  id?: string;
  connected: boolean;
  disconnected: boolean;
  on: (event: string, listener: (...args: unknown[]) => void) => void;
  off: (event: string, listener?: (...args: unknown[]) => void) => void;
  emit: (event: string, ...args: unknown[]) => void;
  disconnect: () => void;
}

interface SocketContextType {
  socket: SocketInstance | null;
  isConnected: boolean;
  joinRoom: (gameCode: string, playerName?: string) => void;
}

const SocketContext = createContext<SocketContextType | undefined>(undefined);

// eslint-disable-next-line react-refresh/only-export-components
export const useSocket = (): SocketContextType => {
  const context = useContext(SocketContext);
  if (!context) {
    throw new Error('useSocket must be used within a SocketProvider');
  }
  return context;
};

interface SocketProviderProps {
  children: ReactNode;
}

interface JoinGameRoomResponse {
  success?: boolean;
  message?: string;
}

export const SocketProvider: React.FC<SocketProviderProps> = ({ children }) => {
  const [socket, setSocket] = useState<SocketInstance | null>(null);
  const [isConnected, setIsConnected] = useState(false);

  useEffect(() => {
    const newSocket = io(getSocketUrl(), SOCKET_CONFIG);
    setSocket(newSocket);

    newSocket.on('connect', () => {
      console.log('[SocketProvider] Connected to socket server with ID:', newSocket.id);
      setIsConnected(true);
    });

    newSocket.on('disconnect', (reason: string) => {
      console.log('[SocketProvider] Disconnected from socket server:', reason);
      setIsConnected(false);
    });

    newSocket.on('connect_error', (err: Error) => {
      if (err && err.message && err.message.includes('timeout')) {
        console.error('[SocketProvider] Socket connection timeout. Backend may not be running or is unreachable.', err);
        // Optionally, trigger a toast or UI notification here
      } else {
        console.error('[SocketProvider] Socket connection error:', err);
      }
      setIsConnected(false);
    });

    return () => {
      console.log('[SocketProvider] Cleaning up socket connection.');
      newSocket.disconnect();
      setSocket(null);
      setIsConnected(false);
    };
  }, []);

  // Add a joinRoom method to the context
  const joinRoom = useCallback((gameCode: string, playerName?: string) => {
    if (!socket || !isConnected) {
      console.error('[SocketProvider] Cannot join room: socket not connected');
      return;
    }

    if (playerName) {
      // If playerName is provided, use joinGameRoom with player info
      console.log(`[SocketProvider] Emitting joinGameRoom for ${gameCode} as ${playerName}`);
      socket.emit('joinGameRoom', { gameCode, playerName }, (response: JoinGameRoomResponse) => {
        if (response?.success) {
          console.log(`[SocketProvider] Successfully joined room ${gameCode} as ${playerName}`);
        } else {
          console.error(`[SocketProvider] Failed to join room ${gameCode}:`, response?.message || 'Unknown error');
        }
      });
    } else {
      // Legacy format without player name (just basic room join)
      console.log(`[SocketProvider] Joining room ${gameCode}`);
      socket.emit('joinGameRoom', { gameCode }, (response: JoinGameRoomResponse) => {
        if (response?.success) {
          console.log(`[SocketProvider] Successfully joined room ${gameCode}`);
        } else {
          console.error(`[SocketProvider] Failed to join room ${gameCode}:`, response?.message || 'Unknown error');
        }
      });
    }
  }, [socket, isConnected]);

  return (
    <SocketContext.Provider value={{ socket, isConnected, joinRoom }}>
      {children}
    </SocketContext.Provider>
  );
}; 