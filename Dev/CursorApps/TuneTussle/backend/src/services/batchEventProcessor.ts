import { Socket } from 'socket.io';
import { GameSessionManager } from '../gameSessionManager';
import { logger } from '../utils/logger';

interface BatchedEvent {
  type: string;
  payload: any;
  timestamp: number;
  priority: 'high' | 'normal' | 'low';
}

interface BatchPayload {
  events: BatchedEvent[];
  batchId: string;
  batchSize: number;
}

/**
 * Processes batched socket events to reduce server load and network overhead
 */
export class BatchEventProcessor {
  private gameSessionManager: GameSessionManager;
  private processedBatches = new Set<string>();
  private batchCleanupInterval: NodeJS.Timeout;

  constructor(gameSessionManager: GameSessionManager) {
    this.gameSessionManager = gameSessionManager;
    
    // Clean up processed batch IDs every 5 minutes to prevent memory leaks
    this.batchCleanupInterval = setInterval(() => {
      this.processedBatches.clear();
    }, 5 * 60 * 1000);
  }

  /**
   * Process a batch of events from a client
   */
  processBatch(socket: Socket, batchPayload: BatchPayload): void {
    const { events, batchId, batchSize } = batchPayload;

    // Prevent duplicate processing
    if (this.processedBatches.has(batchId)) {
      logger.warn(`Duplicate batch received: ${batchId}`, { socketId: socket.id });
      return;
    }

    this.processedBatches.add(batchId);
    
    logger.debug(`Processing batch ${batchId} with ${batchSize} events`, {
      socketId: socket.id,
      batchSize,
      eventTypes: events.map(e => e.type)
    });

    const results: Array<{ type: string; success: boolean; message?: string }> = [];
    let processedCount = 0;
    let errorCount = 0;

    // Process events in priority order (already sorted by client)
    for (const event of events) {
      try {
        const result = this.processEvent(socket, event);
        results.push({
          type: event.type,
          success: result.success,
          message: result.message
        });

        if (result.success) {
          processedCount++;
        } else {
          errorCount++;
        }
      } catch (error) {
        logger.error(`Error processing batched event ${event.type}`, error, 'BatchProcessor');
        results.push({
          type: event.type,
          success: false,
          message: error instanceof Error ? error.message : 'Unknown error'
        });
        errorCount++;
      }
    }

    // Send batch processing results back to client
    socket.emit('batchProcessed', {
      batchId,
      processedCount,
      errorCount,
      totalEvents: batchSize,
      results: results // Send all results, not just failures
    });

    logger.performance(`Batch processing`, Date.now() - events[0]?.timestamp || 0, 'BatchProcessor');
  }

  /**
   * Process individual event within a batch
   */
  private processEvent(socket: Socket, event: BatchedEvent): { success: boolean; message?: string } {
    const { type, payload } = event;

    try {
      switch (type) {
        case 'getGameState':
          return this.handleGetGameState(payload);
          
        case 'adjustScore':
          return this.handleAdjustScore(socket, payload);
          
        case 'nextRound':
          return this.handleNextRound(socket, payload);
          
        case 'joinGameRoom':
          return this.handleJoinGameRoom(socket, payload);
          
        default:
          logger.warn(`Unknown batched event type: ${type}`, { socketId: socket.id });
          return { success: false, message: `Unknown event type: ${type}` };
      }
    } catch (error) {
      logger.error(`Error processing event ${type}`, error, 'BatchProcessor');
      return { 
        success: false, 
        message: error instanceof Error ? error.message : 'Processing error' 
      };
    }
  }

  private handleGetGameState(payload: any): { success: boolean; message?: string } {
    const { gameCode } = payload;
    
    if (!gameCode) {
      return { success: false, message: 'Game code required' };
    }

    const gameState = this.gameSessionManager.getGameState(gameCode);
    return { success: !!gameState };
  }

  private handleAdjustScore(socket: Socket, payload: any): { success: boolean; message?: string } {
    const { gameCode, playerId, newScore } = payload;
    const playerName = socket.data.playerName;

    if (!playerName) {
      return { success: false, message: 'Player not identified' };
    }

    if (!gameCode || !playerId || typeof newScore !== 'number') {
      return { success: false, message: 'Invalid adjust score parameters' };
    }

    // Verify the requesting player is the judge
    const gameDetailsResult = this.gameSessionManager.getGameDetails(gameCode);
    if (!gameDetailsResult.success) {
      return { success: false, message: 'Game not found' };
    }

    const gameDetails = gameDetailsResult.data;
    if (gameDetails.judgeName !== playerName) {
      return { success: false, message: 'Only judge can adjust scores' };
    }

    const result = this.gameSessionManager.adjustPlayerScore(gameCode, playerId, newScore);
    return { success: result.success, message: result.message };
  }

  private handleNextRound(socket: Socket, payload: any): { success: boolean; message?: string } {
    const { gameCode } = payload;
    const playerName = socket.data.playerName;

    if (!playerName) {
      return { success: false, message: 'Player not identified' };
    }

    if (!gameCode) {
      return { success: false, message: 'Game code required' };
    }

    const gameDetailsResult = this.gameSessionManager.getGameDetails(gameCode);
    if (!gameDetailsResult.success) {
      return { success: false, message: 'Game not found' };
    }

    const gameDetails = gameDetailsResult.data;
    if (gameDetails.judgeName !== playerName) {
      return { success: false, message: 'Only judge can start next round' };
    }

    const result = this.gameSessionManager.startNextRound(gameCode);
    return { success: result.success, message: result.message };
  }

  private handleJoinGameRoom(socket: Socket, payload: any): { success: boolean; message?: string } {
    const { gameCode, playerName } = payload;

    if (!gameCode || !playerName) {
      return { success: false, message: 'Game code and player name required' };
    }

    // Store player name in socket data for future requests
    socket.data.playerName = playerName;
    socket.data.gameCode = gameCode;

    // Join the socket to the game room
    socket.join(gameCode);

    return { success: true };
  }

  /**
   * Get processing statistics
   */
  getStats(): {
    processedBatches: number;
    memoryUsage: number;
  } {
    return {
      processedBatches: this.processedBatches.size,
      memoryUsage: process.memoryUsage().heapUsed
    };
  }

  /**
   * Clean up resources
   */
  destroy(): void {
    if (this.batchCleanupInterval) {
      clearInterval(this.batchCleanupInterval);
    }
    this.processedBatches.clear();
  }
} 