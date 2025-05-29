import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { SocketEventBatcher } from './socketEventBatcher';

// Mock socket.io-client
const mockSocket = {
  emit: vi.fn(),
  on: vi.fn(),
  off: vi.fn()
} as unknown as any;

describe('SocketEventBatcher', () => {
  let batcher: SocketEventBatcher;
  
  beforeEach(() => {
    vi.clearAllMocks();
    vi.useFakeTimers();
    batcher = new SocketEventBatcher({
      batchWindow: 100,
      maxBatchSize: 3, // Small for testing
      highPriorityEvents: ['buzzIn', 'submitAnswer']
    });
    batcher.setSocket(mockSocket);
  });

  afterEach(() => {
    batcher.destroy();
    vi.useRealTimers();
  });

  describe('High-Priority Event Bypass', () => {
    it('should emit high-priority events immediately without batching', () => {
      const callback = vi.fn();
      
      batcher.emit('buzzIn', { gameCode: 'TEST123' }, callback);
      
      expect(mockSocket.emit).toHaveBeenCalledWith(
        'buzzIn', 
        { gameCode: 'TEST123' }, 
        callback
      );
      expect(mockSocket.emit).toHaveBeenCalledTimes(1);
    });

    it('should bypass batching for submitAnswer events', () => {
      batcher.emit('submitAnswer', { answer: 'test' });
      
      expect(mockSocket.emit).toHaveBeenCalledWith(
        'submitAnswer', 
        { answer: 'test' }, 
        undefined
      );
    });
  });

  describe('Event Batching Behavior', () => {
    it('should batch normal priority events', () => {
      batcher.emit('getGameState', { gameCode: 'TEST123' });
      batcher.emit('adjustScore', { playerId: 'player1', score: 10 });
      
      // Should not emit immediately
      expect(mockSocket.emit).not.toHaveBeenCalled();
      
      // Should emit after timeout
      vi.advanceTimersByTime(100);
      
      expect(mockSocket.emit).toHaveBeenCalledWith('batchedEvents', expect.objectContaining({
        events: expect.arrayContaining([
          expect.objectContaining({ type: 'getGameState' }),
          expect.objectContaining({ type: 'adjustScore' })
        ]),
        batchSize: 2,
        batchId: expect.any(String)
      }));
    });

    it('should flush batch when max size is reached', () => {
      batcher.emit('getGameState', { gameCode: 'TEST1' });
      batcher.emit('getGameState', { gameCode: 'TEST2' });
      batcher.emit('getGameState', { gameCode: 'TEST3' }); // Should trigger flush
      
      expect(mockSocket.emit).toHaveBeenCalledWith('batchedEvents', expect.objectContaining({
        batchSize: 3
      }));
    });

    it('should sort events by priority in batch', () => {
      batcher.emit('adjustScore', { score: 1 }); // low priority
      batcher.emit('getGameState', { gameCode: 'TEST' }); // normal priority
      batcher.emit('nextRound', { gameCode: 'TEST' }); // low priority
      
      vi.advanceTimersByTime(100);
      
      const batchCall = (mockSocket.emit as any).mock.calls.find(
        (call: any) => call[0] === 'batchedEvents'
      );
      
      expect(batchCall[1].events[0].type).toBe('getGameState'); // normal priority first
    });
  });

  describe('Callback Handling', () => {
    it('should execute callbacks immediately for batched events', () => {
      const callback = vi.fn((result) => {
        expect(result).toEqual({ success: true, batched: true });
      });
      
      batcher.emit('getGameState', { gameCode: 'TEST' }, callback);
      expect(callback).toHaveBeenCalledWith({ success: true, batched: true });
    });

    it('should not interfere with high-priority event callbacks', () => {
      const callback = vi.fn();
      
      batcher.emit('buzzIn', { gameCode: 'TEST' }, callback);
      
      expect(mockSocket.emit).toHaveBeenCalledWith('buzzIn', expect.any(Object), callback);
    });
  });

  describe('Configuration and Control', () => {
    it('should respect custom batch window configuration', () => {
      const customBatcher = new SocketEventBatcher({ batchWindow: 200 });
      customBatcher.setSocket(mockSocket);
      
      customBatcher.emit('getGameState', { gameCode: 'TEST' });
      
      vi.advanceTimersByTime(100);
      expect(mockSocket.emit).not.toHaveBeenCalled();
      
      vi.advanceTimersByTime(100);
      expect(mockSocket.emit).toHaveBeenCalled();
      
      customBatcher.destroy();
    });

    it('should allow enabling and disabling batching', () => {
      batcher.disable();
      batcher.emit('getGameState', { gameCode: 'TEST' });
      
      expect(mockSocket.emit).toHaveBeenCalledWith('getGameState', expect.any(Object), undefined);
      
      batcher.enable();
      (mockSocket.emit as any).mockClear();
      
      batcher.emit('getGameState', { gameCode: 'TEST2' });
      expect(mockSocket.emit).not.toHaveBeenCalled();
    });

    it('should force flush pending events', () => {
      batcher.emit('getGameState', { gameCode: 'TEST1' });
      batcher.emit('getGameState', { gameCode: 'TEST2' });
      
      batcher.forceFlush();
      
      expect(mockSocket.emit).toHaveBeenCalledWith('batchedEvents', expect.objectContaining({
        batchSize: 2
      }));
    });
  });

  describe('Statistics and Monitoring', () => {
    it('should provide accurate batch statistics', () => {
      batcher.emit('getGameState', { gameCode: 'TEST1' });
      batcher.emit('getGameState', { gameCode: 'TEST2' });
      
      const stats = batcher.getStats();
      
      expect(stats.pendingEvents).toBe(2);
      expect(stats.isScheduled).toBe(true);
      expect(stats.config.batchWindow).toBe(100);
    });

    it('should reset statistics after flush', () => {
      batcher.emit('getGameState', { gameCode: 'TEST' });
      
      vi.advanceTimersByTime(100);
      
      const stats = batcher.getStats();
      expect(stats.pendingEvents).toBe(0);
      expect(stats.isScheduled).toBe(false);
    });
  });

  describe('Memory Management', () => {
    it('should clean up timeouts on destroy', () => {
      batcher.emit('getGameState', { gameCode: 'TEST' });
      
      const stats = batcher.getStats();
      expect(stats.isScheduled).toBe(true);
      
      batcher.destroy();
      
      // Should not emit after destroy
      vi.advanceTimersByTime(100);
      expect(mockSocket.emit).not.toHaveBeenCalled();
    });

    it('should clear pending events on destroy', () => {
      batcher.emit('getGameState', { gameCode: 'TEST1' });
      batcher.emit('getGameState', { gameCode: 'TEST2' });
      
      batcher.destroy();
      
      const stats = batcher.getStats();
      expect(stats.pendingEvents).toBe(0);
    });

    it('should handle socket disconnection gracefully', () => {
      batcher.setSocket(null);
      
      expect(() => {
        batcher.emit('getGameState', { gameCode: 'TEST' });
      }).not.toThrow();
    });
  });

  describe('Manual Batching', () => {
    it('should support manual batch emission', () => {
      const events = [
        { type: 'getGameState', payload: { gameCode: 'TEST1' } },
        { type: 'adjustScore', payload: { playerId: 'player1', score: 10 } }
      ];
      
      batcher.emitBatch(events);
      
      expect(mockSocket.emit).toHaveBeenCalledWith('batchedEvents', expect.objectContaining({
        events: expect.arrayContaining([
          expect.objectContaining({ type: 'getGameState' }),
          expect.objectContaining({ type: 'adjustScore' })
        ]),
        batchId: expect.any(String)
      }));
    });
  });

  describe('Error Handling', () => {
    it('should handle missing socket gracefully', () => {
      const batcherWithoutSocket = new SocketEventBatcher();
      
      expect(() => {
        batcherWithoutSocket.emit('getGameState', { gameCode: 'TEST' });
      }).not.toThrow();
      
      batcherWithoutSocket.destroy();
    });

    it('should handle invalid event types gracefully', () => {
      expect(() => {
        batcher.emit('', { data: 'test' });
        batcher.emit(null as any, { data: 'test' });
      }).not.toThrow();
    });
  });
}); 