import { useEffect, useRef } from 'react';
import type { Socket } from 'socket.io-client';
import { socketEventBatcher } from './socketEventBatcher';

interface OptimizedSocketConfig {
  enableBatching: boolean;
  batchingExclusions: string[]; // Events that should never be batched
  performanceMonitoring: boolean;
}

interface BatchProcessedResult {
  success: boolean;
  batchId: string;
  eventsCount: number;
  processingTime: number;
  errors?: Array<{ eventType: string; error: string }>;
}

type SocketCallback = (...args: unknown[]) => void;

/**
 * Optimized Socket Service - Provides intelligent event batching and performance monitoring
 * while maintaining backward compatibility with existing socket operations.
 */
export class OptimizedSocketService {
  private socket: Socket | null = null;
  private config: OptimizedSocketConfig;
  private eventMetrics = new Map<string, { count: number; totalTime: number; lastEmit: number }>();
  private performanceStartTime = Date.now();
  private performanceMonitoringInterval: NodeJS.Timeout | null = null;
  private statsLoggingEnabled = process.env.NODE_ENV === 'development';

  get isConnected(): boolean {
    return this.socket && this.socket.connected;
  }

  constructor(config: Partial<OptimizedSocketConfig> = {}) {
    this.config = {
      enableBatching: true,
      batchingExclusions: ['buzzIn', 'submitAnswer', 'heartbeat', 'disconnect', 'connect'],
      performanceMonitoring: process.env.NODE_ENV === 'development',
      ...config
    };
  }

  /**
   * Initialize the service with a socket connection
   */
  initialize(socket: Socket): void {
    this.socket = socket;
    socketEventBatcher.setSocket(socket);

    if (this.config.enableBatching) {
      socketEventBatcher.enable();
    } else {
      socketEventBatcher.disable();
    }

    // Set up batch processing result handler
    this.socket.on('batchProcessed', (result: BatchProcessedResult) => {
      this.handleBatchProcessed(result);
    });

    if (this.config.performanceMonitoring) {
      this.startPerformanceMonitoring();
    }

    if (this.statsLoggingEnabled) {
      console.log('[OptimizedSocket] Performance stats:', {
        ...socketEventBatcher.getStats(),
        activeConnections: this.socket ? 1 : 0,
        isConnected: this.isConnected
      });
    }
  }

  /**
   * Emit an event with automatic batching optimization
   */
  emit(eventType: string, payload: unknown, callback?: SocketCallback): void {
    if (!this.socket) {
      console.warn('[OptimizedSocketService] Socket not initialized');
      return;
    }

    const startTime = Date.now();

    // Track event metrics
    if (this.config.performanceMonitoring) {
      this.trackEventMetrics(eventType, startTime);
    }

    // Check if event should be batched
    const shouldBatch = this.config.enableBatching &&
      !this.config.batchingExclusions.includes(eventType);

    if (shouldBatch) {
      // Use the event batcher for non-critical events
      socketEventBatcher.emit(eventType, payload, callback);
    } else {
      // Direct emission for critical events
      this.socket.emit(eventType, payload, callback);
    }

    // Log performance metrics in development
    if (this.config.performanceMonitoring) {
      const duration = Date.now() - startTime;
      console.log(`[OptimizedSocketService] ${eventType} emitted in ${duration}ms (batched: ${shouldBatch})`);
    }
  }

  /**
   * Emit multiple events as a single batch (manual batching)
   */
  emitBatch(events: Array<{ type: string; payload: unknown }>): void {
    if (!this.socket) {
      console.warn('[OptimizedSocketService] Socket not initialized');
      return;
    }

    socketEventBatcher.emitBatch(events);

    if (this.config.performanceMonitoring) {
      console.log(`[OptimizedSocketService] Manual batch emitted with ${events.length} events`);
    }
  }

  /**
   * Force immediate flush of all pending batched events
   */
  flushPendingEvents(): void {
    socketEventBatcher.forceFlush();
  }

  /**
   * Enable or disable event batching
   */
  setBatchingEnabled(enabled: boolean): void {
    this.config.enableBatching = enabled;

    if (enabled) {
      socketEventBatcher.enable();
    } else {
      socketEventBatcher.disable();
    }
  }

  /**
   * Add events to batching exclusion list
   */
  addBatchingExclusions(eventTypes: string[]): void {
    this.config.batchingExclusions.push(...eventTypes);
  }

  /**
   * Get current performance statistics
   */
  getPerformanceStats(): {
    totalEvents: number;
    batchedEvents: number;
    directEvents: number;
    averageEventTime: number;
    uptime: number;
    eventBreakdown: Array<{ event: string; count: number; avgTime: number; lastEmit: number }>;
  } {
    const totalEvents = Array.from(this.eventMetrics.values())
      .reduce((sum, metric) => sum + metric.count, 0);

    const totalTime = Array.from(this.eventMetrics.values())
      .reduce((sum, metric) => sum + metric.totalTime, 0);

    return {
      totalEvents,
      batchedEvents: totalEvents - this.getDirectEventCount(),
      directEvents: this.getDirectEventCount(),
      averageEventTime: totalEvents > 0 ? totalTime / totalEvents : 0,
      uptime: Date.now() - this.performanceStartTime,
      eventBreakdown: Array.from(this.eventMetrics.entries()).map(([event, metric]) => ({
        event,
        count: metric.count,
        avgTime: metric.count > 0 ? metric.totalTime / metric.count : 0,
        lastEmit: metric.lastEmit
      }))
    };
  }

  /**
   * Get current batching statistics
   */
  getBatchingStats(): {
    pendingEvents: number;
    isScheduled: boolean;
    config: unknown;
  } {
    return socketEventBatcher.getStats();
  }

  /**
   * Reset performance metrics
   */
  resetMetrics(): void {
    this.eventMetrics.clear();
    this.performanceStartTime = Date.now();
  }

  /**
   * Clean up resources
   */
  destroy(): void {
    if (this.socket) {
      this.socket.off('batchProcessed');
    }

    // Clean up performance monitoring interval
    if (this.performanceMonitoringInterval) {
      clearInterval(this.performanceMonitoringInterval);
      this.performanceMonitoringInterval = null;
    }

    socketEventBatcher.destroy();
    this.eventMetrics.clear();
  }

  private trackEventMetrics(eventType: string, startTime: number): void {
    const existing = this.eventMetrics.get(eventType) || { count: 0, totalTime: 0, lastEmit: 0 };
    const duration = Date.now() - startTime;

    this.eventMetrics.set(eventType, {
      count: existing.count + 1,
      totalTime: existing.totalTime + duration,
      lastEmit: Date.now()
    });
  }

  private getDirectEventCount(): number {
    return this.config.batchingExclusions
      .map(eventType => this.eventMetrics.get(eventType)?.count || 0)
      .reduce((sum, count) => sum + count, 0);
  }

  private handleBatchProcessed(result: BatchProcessedResult): void {
    if (this.config.performanceMonitoring) {
      console.log('[OptimizedSocketService] Batch processed:', {
        batchId: result.batchId,
        processed: result.eventsCount,
        errors: result.errors?.length || 0,
        total: result.eventsCount
      });
    }

    // Handle any failed events in the batch
    if (result.errors && result.errors.length > 0) {
      console.warn('[OptimizedSocketService] Some batched events failed:', result.errors);
    }
  }

  private startPerformanceMonitoring(): void {
    // Clean up any existing interval
    if (this.performanceMonitoringInterval) {
      clearInterval(this.performanceMonitoringInterval);
    }

    // Log performance stats every 30 seconds in development
    this.performanceMonitoringInterval = setInterval(() => {
      const stats = this.getPerformanceStats();
      console.log('[OptimizedSocketService] Performance Stats:', {
        totalEvents: stats.totalEvents,
        batchedEvents: stats.batchedEvents,
        directEvents: stats.directEvents,
        avgEventTime: `${stats.averageEventTime.toFixed(2)}ms`,
        uptime: `${(stats.uptime / 1000).toFixed(1)}s`
      });
    }, 30000);
  }
}

// React hook for using the optimized socket service
export function useOptimizedSocket(socket: Socket | null) {
  const serviceRef = useRef<OptimizedSocketService | null>(null);

  useEffect(() => {
    if (socket) {
      // Create a new service instance for this socket
      serviceRef.current = new OptimizedSocketService();
      serviceRef.current.initialize(socket);
    }

    return () => {
      if (serviceRef.current) {
        serviceRef.current.destroy();
        serviceRef.current = null;
      }
    };
  }, [socket]);

  return {
    emit: (eventType: string, payload: unknown, callback?: SocketCallback) => {
      serviceRef.current?.emit(eventType, payload, callback);
    },
    emitBatch: (events: Array<{ type: string; payload: unknown }>) => {
      serviceRef.current?.emitBatch(events);
    },
    flushPendingEvents: () => {
      serviceRef.current?.flushPendingEvents();
    },
    setBatchingEnabled: (enabled: boolean) => {
      serviceRef.current?.setBatchingEnabled(enabled);
    },
    getPerformanceStats: () => {
      return serviceRef.current?.getPerformanceStats() || {
        totalEvents: 0,
        batchedEvents: 0,
        directEvents: 0,
        averageEventTime: 0,
        uptime: 0,
        eventBreakdown: []
      };
    },
    getBatchingStats: () => {
      return serviceRef.current?.getBatchingStats() || {
        pendingEvents: 0,
        isScheduled: false,
        config: {}
      };
    },
    resetMetrics: () => {
      serviceRef.current?.resetMetrics();
    }
  };
}
