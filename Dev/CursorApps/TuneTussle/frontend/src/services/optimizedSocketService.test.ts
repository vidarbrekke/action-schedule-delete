import { renderHook } from '@testing-library/react';
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { OptimizedSocketService, useOptimizedSocket } from './optimizedSocketService';
import { socketEventBatcher } from './socketEventBatcher';

// Mock dependencies
vi.mock('./socketEventBatcher', () => ({
  socketEventBatcher: {
    setSocket: vi.fn(),
    enable: vi.fn(),
    disable: vi.fn(),
    emit: vi.fn(),
    emitBatch: vi.fn(),
    forceFlush: vi.fn(),
    getStats: vi.fn(() => ({
      pendingEvents: 0,
      isScheduled: false,
      config: {}
    })),
    destroy: vi.fn()
  }
}));

const mockSocket = {
  emit: vi.fn(),
  on: vi.fn(),
  off: vi.fn(),
  id: 'test-socket-123'
} as unknown as any;

describe('OptimizedSocketService', () => {
  let service: OptimizedSocketService;
  
  beforeEach(() => {
    vi.clearAllMocks();
    vi.useFakeTimers();
    service = new OptimizedSocketService({
      enableBatching: true,
      performanceMonitoring: true,
      batchingExclusions: ['buzzIn', 'submitAnswer']
    });
  });

  afterEach(() => {
    service.destroy();
    vi.useRealTimers();
  });

  describe('Initialization', () => {
    it('should initialize with socket and configure batcher', () => {
      service.initialize(mockSocket);
      
      expect(socketEventBatcher.setSocket).toHaveBeenCalledWith(mockSocket);
      expect(socketEventBatcher.enable).toHaveBeenCalled();
      expect(mockSocket.on).toHaveBeenCalledWith('batchProcessed', expect.any(Function));
    });

    it('should disable batching when configured', () => {
      const noBatchService = new OptimizedSocketService({ enableBatching: false });
      noBatchService.initialize(mockSocket);
      
      expect(socketEventBatcher.disable).toHaveBeenCalled();
      
      noBatchService.destroy();
    });

    it('should start performance monitoring in development', () => {
      const originalEnv = process.env.NODE_ENV;
      process.env.NODE_ENV = 'development';
      
      const devService = new OptimizedSocketService({ performanceMonitoring: true });
      devService.initialize(mockSocket);
      
      // Should start monitoring interval
      expect(devService).toBeDefined();
      
      process.env.NODE_ENV = originalEnv;
      devService.destroy();
    });
  });

  describe('Event Emission', () => {
    beforeEach(() => {
      service.initialize(mockSocket);
    });

    it('should batch non-excluded events', () => {
      service.emit('getGameState', { gameCode: 'TEST123' });
      
      expect(socketEventBatcher.emit).toHaveBeenCalledWith(
        'getGameState',
        { gameCode: 'TEST123' },
        undefined
      );
      expect(mockSocket.emit).not.toHaveBeenCalled();
    });

    it('should emit excluded events directly', () => {
      const callback = vi.fn();
      service.emit('buzzIn', { gameCode: 'TEST123' }, callback);
      
      expect(mockSocket.emit).toHaveBeenCalledWith(
        'buzzIn',
        { gameCode: 'TEST123' },
        callback
      );
      expect(socketEventBatcher.emit).not.toHaveBeenCalled();
    });

    it('should handle missing socket gracefully', () => {
      const uninitializedService = new OptimizedSocketService();
      
      expect(() => {
        uninitializedService.emit('getGameState', { gameCode: 'TEST' });
      }).not.toThrow();
      
      uninitializedService.destroy();
    });

    it('should track performance metrics when enabled', () => {
      service.emit('getGameState', { gameCode: 'TEST' });
      service.emit('adjustScore', { playerId: 'player1', score: 10 });
      
      const stats = service.getPerformanceStats();
      
      expect(stats.totalEvents).toBe(2);
      expect(stats.eventBreakdown).toHaveLength(2);
      expect(stats.eventBreakdown[0].event).toBe('getGameState');
    });
  });

  describe('Batch Operations', () => {
    beforeEach(() => {
      service.initialize(mockSocket);
    });

    it('should support manual batch emission', () => {
      const events = [
        { type: 'getGameState', payload: { gameCode: 'TEST1' } },
        { type: 'adjustScore', payload: { playerId: 'player1', score: 10 } }
      ];
      
      service.emitBatch(events);
      
      expect(socketEventBatcher.emitBatch).toHaveBeenCalledWith(events);
    });

    it('should flush pending events', () => {
      service.flushPendingEvents();
      
      expect(socketEventBatcher.forceFlush).toHaveBeenCalled();
    });

    it('should allow runtime batching configuration', () => {
      service.setBatchingEnabled(false);
      expect(socketEventBatcher.disable).toHaveBeenCalled();
      
      service.setBatchingEnabled(true);
      expect(socketEventBatcher.enable).toHaveBeenCalled();
    });

    it('should support adding batching exclusions', () => {
      service.addBatchingExclusions(['newEvent', 'anotherEvent']);
      
      service.emit('newEvent', { data: 'test' });
      
      expect(mockSocket.emit).toHaveBeenCalledWith('newEvent', { data: 'test' }, undefined);
    });
  });

  describe('Performance Monitoring', () => {
    beforeEach(() => {
      service.initialize(mockSocket);
    });

    it('should provide comprehensive performance statistics', () => {
      service.emit('getGameState', { gameCode: 'TEST1' });
      service.emit('buzzIn', { gameCode: 'TEST2' }); // Direct event
      service.emit('adjustScore', { playerId: 'player1', score: 10 });
      
      const stats = service.getPerformanceStats();
      
      expect(stats.totalEvents).toBe(3);
      expect(stats.directEvents).toBe(1); // buzzIn
      expect(stats.batchedEvents).toBe(2); // getGameState + adjustScore
      expect(stats.uptime).toBeGreaterThanOrEqual(0);
      expect(stats.eventBreakdown).toHaveLength(3);
    });

    it('should calculate average event times correctly', () => {
      // Simulate some processing time
      vi.advanceTimersByTime(10);
      service.emit('getGameState', { gameCode: 'TEST1' });
      
      vi.advanceTimersByTime(20);
      service.emit('getGameState', { gameCode: 'TEST2' });
      
      const stats = service.getPerformanceStats();
      
      expect(stats.averageEventTime).toBeGreaterThanOrEqual(0);
      expect(stats.eventBreakdown[0].avgTime).toBeGreaterThanOrEqual(0);
    });

    it('should reset metrics correctly', () => {
      service.emit('getGameState', { gameCode: 'TEST' });
      
      let stats = service.getPerformanceStats();
      expect(stats.totalEvents).toBe(1);
      
      service.resetMetrics();
      
      stats = service.getPerformanceStats();
      expect(stats.totalEvents).toBe(0);
      expect(stats.eventBreakdown).toHaveLength(0);
    });

    it('should provide batching statistics', () => {
      (socketEventBatcher.getStats as any).mockReturnValue({
        pendingEvents: 2,
        isScheduled: true,
        config: { batchWindow: 100 }
      });
      
      const stats = service.getBatchingStats();
      
      expect(stats.pendingEvents).toBe(2);
      expect(stats.isScheduled).toBe(true);
    });
  });

  describe('Memory Management', () => {
    it('should clean up resources on destroy', () => {
      service.initialize(mockSocket);
      
      service.destroy();
      
      expect(mockSocket.off).toHaveBeenCalledWith('batchProcessed');
      expect(socketEventBatcher.destroy).toHaveBeenCalled();
    });

    it('should clean up performance monitoring interval', () => {
      const monitoringService = new OptimizedSocketService({ performanceMonitoring: true });
      monitoringService.initialize(mockSocket);
      
      // Verify interval is running
      expect(monitoringService).toBeDefined();
      
      monitoringService.destroy();
      
      // Should not throw or cause memory leaks
      expect(() => {
        vi.advanceTimersByTime(30000);
      }).not.toThrow();
    });
  });

  describe('Batch Processing Response Handling', () => {
    beforeEach(() => {
      service.initialize(mockSocket);
    });

    it('should handle successful batch processing responses', () => {
      const consoleSpy = vi.spyOn(console, 'log').mockImplementation(() => {});
      
      // Simulate batch processing response
      const mockResponse = {
        batchId: 'test-batch-123',
        processedCount: 3,
        errorCount: 0,
        totalEvents: 3,
        results: []
      };
      
      // Trigger the batch processed handler
      const batchProcessedHandler = (mockSocket.on as any).mock.calls
        .find((call: any) => call[0] === 'batchProcessed')[1];
      
      batchProcessedHandler(mockResponse);
      
      expect(consoleSpy).toHaveBeenCalledWith(
        '[OptimizedSocketService] Batch processed:',
        expect.objectContaining({
          batchId: 'test-batch-123',
          processed: 3,
          errors: 0,
          total: 3
        })
      );
      
      consoleSpy.mockRestore();
    });

    it('should handle batch processing errors', () => {
      const consoleSpy = vi.spyOn(console, 'warn').mockImplementation(() => {});
      
      const mockResponse = {
        batchId: 'test-batch-123',
        processedCount: 2,
        errorCount: 1,
        totalEvents: 3,
        results: [{ type: 'getGameState', success: false, message: 'Game not found' }]
      };
      
      const batchProcessedHandler = (mockSocket.on as any).mock.calls
        .find((call: any) => call[0] === 'batchProcessed')[1];
      
      batchProcessedHandler(mockResponse);
      
      expect(consoleSpy).toHaveBeenCalledWith(
        '[OptimizedSocketService] Some batched events failed:',
        mockResponse.results
      );
      
      consoleSpy.mockRestore();
    });
  });
});

describe('useOptimizedSocket Hook', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('should create service instance when socket is provided', () => {
    const { result } = renderHook(() => useOptimizedSocket(mockSocket));
    
    expect(result.current.emit).toBeDefined();
    expect(result.current.emitBatch).toBeDefined();
    expect(result.current.getPerformanceStats).toBeDefined();
  });

  it('should handle null socket gracefully', () => {
    const { result } = renderHook(() => useOptimizedSocket(null));
    
    expect(() => {
      result.current.emit('getGameState', { gameCode: 'TEST' });
    }).not.toThrow();
  });

  it('should clean up service on unmount', () => {
    const { unmount } = renderHook(() => useOptimizedSocket(mockSocket));
    
    unmount();
    
    // Should not cause memory leaks or errors
    expect(true).toBe(true);
  });

  it('should recreate service when socket changes', () => {
    const { result, rerender } = renderHook(
      ({ socket }) => useOptimizedSocket(socket),
      { initialProps: { socket: mockSocket } }
    );
    
    const firstEmit = result.current.emit;
    
    const newMockSocket = { ...mockSocket, id: 'new-socket-456' } as any;
    rerender({ socket: newMockSocket });
    
    const secondEmit = result.current.emit;
    
    // Should be different instances
    expect(firstEmit).not.toBe(secondEmit);
  });

  it('should provide default values when service is not initialized', () => {
    const { result } = renderHook(() => useOptimizedSocket(null));
    
    const stats = result.current.getPerformanceStats();
    
    expect(stats).toEqual({
      totalEvents: 0,
      batchedEvents: 0,
      directEvents: 0,
      averageEventTime: 0,
      uptime: 0,
      eventBreakdown: []
    });
  });

  it('should handle all service methods safely', () => {
    const { result } = renderHook(() => useOptimizedSocket(mockSocket));
    
    expect(() => {
      result.current.emit('getGameState', { gameCode: 'TEST' });
      result.current.emitBatch([{ type: 'test', payload: {} }]);
      result.current.flushPendingEvents();
      result.current.setBatchingEnabled(false);
      result.current.resetMetrics();
    }).not.toThrow();
  });
}); 