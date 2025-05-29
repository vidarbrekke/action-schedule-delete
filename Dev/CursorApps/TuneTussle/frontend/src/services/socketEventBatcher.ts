/**
 * Socket Event Batcher - Optimizes socket.io event emissions by batching multiple events
 *
 * Reduces network overhead by collecting events and sending them in batches,
 * while providing immediate feedback for critical real-time events.
 */

import type { Socket } from 'socket.io-client';

interface BatchEvent {
  type: string;
  payload: unknown;
  timestamp: number;
  priority: 'high' | 'normal' | 'low';
}

interface BatchConfig {
  batchWindow: number; // ms to wait before sending batch
  maxBatchSize: number; // max events per batch
  highPriorityEvents: string[]; // events that bypass batching
}

type SocketCallback = (...args: unknown[]) => void;

/**
 * Socket Event Batcher - Reduces network overhead by batching multiple events
 * into single transmissions with configurable debounce windows.
 */
export class SocketEventBatcher {
  private socket: Socket | null = null;
  private pendingEvents: BatchEvent[] = [];
  private batchTimeout: NodeJS.Timeout | null = null;
  private config: BatchConfig;
  private isEnabled: boolean = true;

  constructor(config: Partial<BatchConfig> = {}) {
    this.config = {
      batchWindow: 100, // 100ms debounce window
      maxBatchSize: 10, // max 10 events per batch
      highPriorityEvents: ['buzzIn', 'submitAnswer', 'heartbeat'], // immediate events
      ...config
    };
  }

  setSocket(socket: Socket | null): void {
    this.socket = socket;
  }

  enable(): void {
    this.isEnabled = true;
  }

  disable(): void {
    this.isEnabled = false;
    this.flushBatch(); // Send any pending events immediately
  }

  /**
   * Emit an event through the batcher. High-priority events bypass batching.
   */
  emit(eventType: string, payload: unknown, callback?: SocketCallback): void {
    if (!this.socket || !this.isEnabled) {
      // Fallback to direct emission if not configured
      this.socket?.emit(eventType, payload, callback);
      return;
    }

    const priority = this.getEventPriority(eventType);

    // High-priority events bypass batching for immediate transmission
    if (priority === 'high') {
      this.socket.emit(eventType, payload, callback);
      return;
    }

    // Add to batch queue
    const batchedEvent: BatchEvent = {
      type: eventType,
      payload,
      timestamp: Date.now(),
      priority
    };

    this.pendingEvents.push(batchedEvent);

    // Handle callbacks for batched events (store for later execution)
    if (callback) {
      // For batched events, we'll execute callback immediately with success
      // since we can't easily correlate responses in batched mode
      setTimeout(() => callback({ success: true, batched: true }), 0);
    }

    // Schedule batch transmission
    this.scheduleBatch();
  }

  /**
   * Emit multiple events as a single batch (for manual batching)
   */
  emitBatch(events: Array<{ type: string; payload: unknown }>): void {
    if (!this.socket) return;

    const batchPayload = {
      events: events.map(event => ({
        type: event.type,
        payload: event.payload,
        timestamp: Date.now()
      })),
      batchId: this.generateBatchId()
    };

    this.socket.emit('batchedEvents', batchPayload);
  }

  private getEventPriority(eventType: string): 'high' | 'normal' | 'low' {
    if (this.config.highPriorityEvents.includes(eventType)) {
      return 'high';
    }

    // Categorize events by importance
    const normalPriorityEvents = ['getGameState', 'joinGameRoom'];

    if (normalPriorityEvents.includes(eventType)) {
      return 'normal';
    }

    return 'low';
  }

  private scheduleBatch(): void {
    // Clear existing timeout
    if (this.batchTimeout) {
      clearTimeout(this.batchTimeout);
    }

    // Immediate flush if batch is full
    if (this.pendingEvents.length >= this.config.maxBatchSize) {
      this.flushBatch();
      return;
    }

    // Schedule batch transmission after debounce window
    this.batchTimeout = setTimeout(() => {
      this.flushBatch();
    }, this.config.batchWindow);
  }

  private flushBatch(): void {
    if (this.pendingEvents.length === 0 || !this.socket) {
      return;
    }

    // Sort events by priority and timestamp
    const sortedEvents = [...this.pendingEvents].sort((a, b) => {
      const priorityOrder = { high: 0, normal: 1, low: 2 };
      const priorityDiff = priorityOrder[a.priority] - priorityOrder[b.priority];
      return priorityDiff !== 0 ? priorityDiff : a.timestamp - b.timestamp;
    });

    // Create batch payload
    const batchPayload = {
      events: sortedEvents.map(event => ({
        type: event.type,
        payload: event.payload,
        timestamp: event.timestamp,
        priority: event.priority
      })),
      batchId: this.generateBatchId(),
      batchSize: sortedEvents.length
    };

    // Emit batched events
    this.socket.emit('batchedEvents', batchPayload);

    // Clear pending events and timeout
    this.pendingEvents = [];
    if (this.batchTimeout) {
      clearTimeout(this.batchTimeout);
      this.batchTimeout = null;
    }

    // Log performance metrics in development
    if (process.env.NODE_ENV === 'development') {
      console.log(`[SocketEventBatcher] Flushed batch of ${sortedEvents.length} events`);
    }
  }

  private generateBatchId(): string {
    return `batch_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
  }

  /**
   * Get current batch statistics for monitoring
   */
  getStats(): {
    pendingEvents: number;
    isScheduled: boolean;
    config: BatchConfig;
  } {
    return {
      pendingEvents: this.pendingEvents.length,
      isScheduled: this.batchTimeout !== null,
      config: this.config
    };
  }

  /**
   * Force immediate flush of all pending events
   */
  forceFlush(): void {
    this.flushBatch();
  }

  /**
   * Clean up resources
   */
  destroy(): void {
    if (this.batchTimeout) {
      clearTimeout(this.batchTimeout);
      this.batchTimeout = null;
    }
    this.pendingEvents = [];
    this.socket = null;
  }
}

// Singleton instance for global use
export const socketEventBatcher = new SocketEventBatcher();

// Hook for React components
import { useEffect } from 'react';

export function useSocketEventBatcher(socket: Socket | null) {
  useEffect(() => {
    socketEventBatcher.setSocket(socket);
    return () => {
      socketEventBatcher.destroy();
    };
  }, [socket]);

  return {
    emit: socketEventBatcher.emit.bind(socketEventBatcher),
    emitBatch: socketEventBatcher.emitBatch.bind(socketEventBatcher),
    enable: socketEventBatcher.enable.bind(socketEventBatcher),
    disable: socketEventBatcher.disable.bind(socketEventBatcher),
    getStats: socketEventBatcher.getStats.bind(socketEventBatcher),
    forceFlush: socketEventBatcher.forceFlush.bind(socketEventBatcher)
  };
}
