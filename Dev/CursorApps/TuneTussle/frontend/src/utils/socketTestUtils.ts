/**
 * Shared socket testing utilities
 * Eliminates duplication across socketEventBatcher, optimizedSocketService, and SocketContext tests
 */

import { vi } from 'vitest';

export interface SocketTestConfig {
  isConnected?: boolean;
  socketId?: string;
  emitDelay?: number;
  shouldThrowErrors?: boolean;
}

/**
 * Creates a comprehensive mock socket for testing
 */
export function createMockSocket(config: SocketTestConfig = {}) {
  const {
    isConnected = true,
    socketId = 'test-socket-id',
    emitDelay = 0,
    shouldThrowErrors = false
  } = config;

  const mockSocket = {
    id: socketId,
    connected: isConnected,
    emit: vi.fn((event: string, data?: any, callback?: Function) => {
      if (shouldThrowErrors && event === 'error-event') {
        throw new Error('Mock socket error');
      }
      
      if (callback) {
        if (emitDelay > 0) {
          setTimeout(() => callback({ success: true, event, data }), emitDelay);
        } else {
          callback({ success: true, event, data });
        }
      }
    }),
    on: vi.fn(),
    off: vi.fn(),
    once: vi.fn(),
    disconnect: vi.fn(),
    connect: vi.fn(),
    removeAllListeners: vi.fn()
  };

  return mockSocket;
}

/**
 * Standard socket connection tests
 */
export function testSocketConnection(getSocket: () => any) {
  describe('Socket connection', () => {
    it('should emit events successfully', () => {
      const socket = getSocket();
      const callback = vi.fn();
      
      socket.emit('test-event', { data: 'test' }, callback);
      
      expect(socket.emit).toHaveBeenCalledWith('test-event', { data: 'test' }, callback);
      expect(callback).toHaveBeenCalledWith(expect.objectContaining({ success: true }));
    });

    it('should handle event listeners', () => {
      const socket = getSocket();
      const listener = vi.fn();
      
      socket.on('test-event', listener);
      socket.off('test-event', listener);
      
      expect(socket.on).toHaveBeenCalledWith('test-event', listener);
      expect(socket.off).toHaveBeenCalledWith('test-event', listener);
    });

    it('should handle disconnection', () => {
      const socket = getSocket();
      
      socket.disconnect();
      
      expect(socket.disconnect).toHaveBeenCalled();
    });
  });
}

/**
 * Standard socket error handling tests
 */
export function testSocketErrorHandling(getSocket: () => any) {
  describe('Socket error handling', () => {
    it('should handle emit errors gracefully', () => {
      const socket = getSocket();
      
      expect(() => {
        socket.emit('error-event');
      }).not.toThrow();
    });

    it('should clean up listeners on disconnect', () => {
      const socket = getSocket();
      
      socket.disconnect();
      
      expect(socket.removeAllListeners).toHaveBeenCalled();
    });
  });
}

/**
 * Mock socket.io-client module
 */
export function mockSocketIO() {
  return vi.mock('socket.io-client', () => ({
    io: vi.fn(() => createMockSocket()),
  }));
} 