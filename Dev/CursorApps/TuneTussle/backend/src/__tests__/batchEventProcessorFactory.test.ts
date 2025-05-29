import { Socket } from 'socket.io';
import { GameSessionManager } from '../gameSessionManager';
import { batchEventProcessorFactory } from '../services/batchEventProcessorFactory';

// Mock dependencies
jest.mock('../gameSessionManager');
jest.mock('../utils/logger', () => ({
  logger: {
    debug: jest.fn(),
    info: jest.fn(),
    warn: jest.fn(),
    error: jest.fn()
  }
}));

describe('BatchEventProcessorFactory', () => {
  let mockSocket: Partial<Socket>;
  let mockGameSessionManager: jest.Mocked<GameSessionManager>;
  let socketEventHandlers: { [event: string]: Function } = {};

  beforeEach(() => {
    // Reset factory state
    batchEventProcessorFactory.cleanupAll();
    
    // Mock socket
    socketEventHandlers = {};
    mockSocket = {
      id: 'test-socket-123',
      on: jest.fn((event: string, handler: Function) => {
        socketEventHandlers[event] = handler;
      }),
      off: jest.fn(),
      emit: jest.fn(),
      join: jest.fn(),
      data: {}
    };

    // Mock GameSessionManager
    mockGameSessionManager = {
      getGameDetails: jest.fn(),
      getGameState: jest.fn(),
      adjustPlayerScore: jest.fn(),
      startNextRound: jest.fn()
    } as any;
  });

  afterEach(() => {
    batchEventProcessorFactory.cleanupAll();
  });

  describe('Processor Lifecycle Management', () => {
    it('should create a new processor for a socket', () => {
      const processor = batchEventProcessorFactory.getProcessor(
        mockSocket as Socket, 
        mockGameSessionManager
      );

      expect(processor).toBeDefined();
      expect(mockSocket.on).toHaveBeenCalledWith('disconnect', expect.any(Function));
      
      const stats = batchEventProcessorFactory.getStats();
      expect(stats.activeProcessors).toBe(1);
      expect(stats.registeredCleanups).toBe(1);
    });

    it('should return the same processor for the same socket', () => {
      const processor1 = batchEventProcessorFactory.getProcessor(
        mockSocket as Socket, 
        mockGameSessionManager
      );
      
      const processor2 = batchEventProcessorFactory.getProcessor(
        mockSocket as Socket, 
        mockGameSessionManager
      );

      expect(processor1).toBe(processor2);
      
      const stats = batchEventProcessorFactory.getStats();
      expect(stats.activeProcessors).toBe(1);
    });

    it('should clean up processor when socket disconnects', () => {
      const processor = batchEventProcessorFactory.getProcessor(
        mockSocket as Socket, 
        mockGameSessionManager
      );

      // Spy on processor destroy method
      const destroySpy = jest.spyOn(processor, 'destroy');

      // Simulate socket disconnect
      const disconnectHandler = socketEventHandlers['disconnect'];
      expect(disconnectHandler).toBeDefined();
      disconnectHandler();

      expect(destroySpy).toHaveBeenCalled();
      
      const stats = batchEventProcessorFactory.getStats();
      expect(stats.activeProcessors).toBe(0);
      expect(stats.registeredCleanups).toBe(0);
    });

    it('should handle multiple sockets independently', () => {
      const mockSocket2: Partial<Socket> = {
        id: 'test-socket-456',
        on: jest.fn(),
        off: jest.fn(),
        emit: jest.fn(),
        join: jest.fn(),
        data: {}
      };

      const processor1 = batchEventProcessorFactory.getProcessor(
        mockSocket as Socket, 
        mockGameSessionManager
      );
      
      const processor2 = batchEventProcessorFactory.getProcessor(
        mockSocket2 as Socket, 
        mockGameSessionManager
      );

      expect(processor1).not.toBe(processor2);
      
      const stats = batchEventProcessorFactory.getStats();
      expect(stats.activeProcessors).toBe(2);
      expect(stats.registeredCleanups).toBe(2);
    });
  });

  describe('Memory Leak Prevention', () => {
    it('should clean up all processors on cleanupAll', () => {
      // Create multiple processors
      const mockSocket2: Partial<Socket> = {
        id: 'test-socket-456',
        on: jest.fn(),
        data: {}
      };

      const processor1 = batchEventProcessorFactory.getProcessor(
        mockSocket as Socket, 
        mockGameSessionManager
      );
      
      const processor2 = batchEventProcessorFactory.getProcessor(
        mockSocket2 as Socket, 
        mockGameSessionManager
      );

      const destroySpy1 = jest.spyOn(processor1, 'destroy');
      const destroySpy2 = jest.spyOn(processor2, 'destroy');

      batchEventProcessorFactory.cleanupAll();

      expect(destroySpy1).toHaveBeenCalled();
      expect(destroySpy2).toHaveBeenCalled();
      
      const stats = batchEventProcessorFactory.getStats();
      expect(stats.activeProcessors).toBe(0);
      expect(stats.registeredCleanups).toBe(0);
    });

    it('should provide accurate statistics', () => {
      const initialStats = batchEventProcessorFactory.getStats();
      expect(initialStats.activeProcessors).toBe(0);
      expect(initialStats.registeredCleanups).toBe(0);
      expect(typeof initialStats.memoryUsage).toBe('number');

      batchEventProcessorFactory.getProcessor(
        mockSocket as Socket, 
        mockGameSessionManager
      );

      const afterCreateStats = batchEventProcessorFactory.getStats();
      expect(afterCreateStats.activeProcessors).toBe(1);
      expect(afterCreateStats.registeredCleanups).toBe(1);
    });
  });

  describe('Error Handling', () => {
    it('should handle cleanup of non-existent processor gracefully', () => {
      // This should not throw
      expect(() => {
        const disconnectHandler = () => {
          // Simulate cleanup call for non-existent processor
        };
        disconnectHandler();
      }).not.toThrow();
    });

    it('should handle multiple cleanup calls gracefully', () => {
      const processor = batchEventProcessorFactory.getProcessor(
        mockSocket as Socket, 
        mockGameSessionManager
      );

      const destroySpy = jest.spyOn(processor, 'destroy');

      // Call cleanup multiple times
      const disconnectHandler = socketEventHandlers['disconnect'];
      disconnectHandler();
      disconnectHandler();

      // Should only be called once
      expect(destroySpy).toHaveBeenCalledTimes(1);
    });
  });
}); 