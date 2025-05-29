import { Socket } from 'socket.io';
import { GameSessionManager } from '../gameSessionManager';
import { BatchEventProcessor } from './batchEventProcessor';
import { logger } from '../utils/logger';

/**
 * Factory for managing BatchEventProcessor instances with proper lifecycle management.
 * Prevents memory leaks by ensuring processors are cleaned up when sockets disconnect.
 */
export class BatchEventProcessorFactory {
  private static instance: BatchEventProcessorFactory;
  private processors = new Map<string, BatchEventProcessor>();
  private cleanupRegistry = new Map<string, () => void>();

  private constructor() {}

  static getInstance(): BatchEventProcessorFactory {
    if (!BatchEventProcessorFactory.instance) {
      BatchEventProcessorFactory.instance = new BatchEventProcessorFactory();
    }
    return BatchEventProcessorFactory.instance;
  }

  /**
   * Create or retrieve a BatchEventProcessor for a socket connection
   */
  getProcessor(socket: Socket, gameSessionManager: GameSessionManager): BatchEventProcessor {
    const socketId = socket.id;

    // Return existing processor if available
    if (this.processors.has(socketId)) {
      return this.processors.get(socketId)!;
    }

    // Create new processor
    const processor = new BatchEventProcessor(gameSessionManager);
    this.processors.set(socketId, processor);

    // Register cleanup for this socket
    const cleanup = () => {
      this.cleanupProcessor(socketId);
    };

    this.cleanupRegistry.set(socketId, cleanup);

    // Auto-cleanup on socket disconnect
    socket.on('disconnect', cleanup);

    logger.debug(`Created BatchEventProcessor for socket ${socketId}`, {
      totalProcessors: this.processors.size
    });

    return processor;
  }

  /**
   * Manually clean up a processor (called on disconnect or error)
   */
  private cleanupProcessor(socketId: string): void {
    const processor = this.processors.get(socketId);
    if (processor) {
      processor.destroy();
      this.processors.delete(socketId);
      
      logger.debug(`Cleaned up BatchEventProcessor for socket ${socketId}`, {
        remainingProcessors: this.processors.size
      });
    }

    // Remove cleanup function
    this.cleanupRegistry.delete(socketId);
  }

  /**
   * Force cleanup of all processors (for testing or shutdown)
   */
  cleanupAll(): void {
    logger.info(`Cleaning up all BatchEventProcessors`, {
      totalProcessors: this.processors.size
    });

    for (const [socketId, processor] of this.processors) {
      processor.destroy();
    }

    this.processors.clear();
    this.cleanupRegistry.clear();
  }

  /**
   * Get current factory statistics
   */
  getStats(): {
    activeProcessors: number;
    registeredCleanups: number;
    memoryUsage: number;
  } {
    return {
      activeProcessors: this.processors.size,
      registeredCleanups: this.cleanupRegistry.size,
      memoryUsage: process.memoryUsage().heapUsed
    };
  }
}

// Export singleton instance
export const batchEventProcessorFactory = BatchEventProcessorFactory.getInstance(); 