import { GameSession } from '../models/types';
import { logger } from '../utils/logger';

interface GameStorageEntry {
  game: GameSession;
  lastActivity: number;
  accessCount: number;
  createdAt: number;
}

interface StorageConfig {
  maxGames: number;
  idleTimeoutMs: number; // TTL for idle games
  cleanupIntervalMs: number;
  maxMemoryMB: number;
}

/**
 * Memory-optimized game storage with automatic cleanup and TTL management.
 * Designed to handle hundreds of concurrent games efficiently.
 */
export class GameStorageManager {
  private games = new Map<string, GameStorageEntry>();
  private cleanupInterval!: NodeJS.Timeout;
  private config: StorageConfig;
  private memoryWarningThreshold: number;
  private static _instances: Set<GameStorageManager> = new Set();

  constructor(config: Partial<StorageConfig> = {}) {
    this.config = {
      maxGames: 50, // Reduced from 500 for 1GB servers
      idleTimeoutMs: 10 * 60 * 1000, // Reduced from 30 to 10 minutes
      cleanupIntervalMs: 2 * 60 * 1000, // Reduced from 5 to 2 minutes
      maxMemoryMB: 256, // Reduced from 512 to 256MB
      ...config
    };

    this.memoryWarningThreshold = this.config.maxMemoryMB * 0.7; // Reduced from 80% to 70%

    // Start automatic cleanup
    this.startCleanupProcess();
    GameStorageManager._instances.add(this);
    
    logger.info('GameStorageManager initialized', {
      maxGames: this.config.maxGames,
      idleTimeoutMs: this.config.idleTimeoutMs,
      cleanupIntervalMs: this.config.cleanupIntervalMs
    });
  }

  /**
   * Store a game with automatic activity tracking
   */
  set(code: string, game: GameSession): void {
    const now = Date.now();
    
    // Check capacity limits
    if (this.games.size >= this.config.maxGames && !this.games.has(code)) {
      this.performEmergencyCleanup();
      
      if (this.games.size >= this.config.maxGames) {
        throw new Error(`Maximum game capacity reached: ${this.config.maxGames}`);
      }
    }

    const entry: GameStorageEntry = {
      game,
      lastActivity: now,
      accessCount: this.games.has(code) ? this.games.get(code)!.accessCount : 0,
      createdAt: this.games.has(code) ? this.games.get(code)!.createdAt : now
    };

    this.games.set(code, entry);
    
    logger.debug(`Game stored: ${code}`, {
      totalGames: this.games.size,
      gameState: game.gameState,
      participants: game.participants.size
    });
  }

  /**
   * Retrieve a game and update activity tracking
   */
  get(code: string): GameSession | undefined {
    const entry = this.games.get(code);
    if (!entry) {
      return undefined;
    }

    // Update activity tracking
    entry.lastActivity = Date.now();
    entry.accessCount++;

    return entry.game;
  }

  /**
   * Check if a game exists without updating activity
   */
  has(code: string): boolean {
    return this.games.has(code);
  }

  /**
   * Delete a game and clean up resources
   */
  delete(code: string): boolean {
    const entry = this.games.get(code);
    if (entry) {
      // Clean up any game-specific resources
      this.cleanupGameResources(entry.game);
      
      const deleted = this.games.delete(code);
      
      logger.debug(`Game deleted: ${code}`, {
        totalGames: this.games.size,
        gameAge: Date.now() - entry.createdAt,
        accessCount: entry.accessCount
      });
      
      return deleted;
    }
    return false;
  }

  /**
   * Get all game codes (for compatibility)
   */
  keys(): IterableIterator<string> {
    return this.games.keys();
  }

  /**
   * Get current storage statistics
   */
  getStats(): {
    totalGames: number;
    memoryUsageMB: number;
    oldestGameAge: number;
    averageAccessCount: number;
    idleGames: number;
    activeGames: number;
  } {
    const now = Date.now();
    const memoryUsage = process.memoryUsage();
    const memoryUsageMB = memoryUsage.heapUsed / (1024 * 1024);
    
    let oldestGameAge = 0;
    let totalAccessCount = 0;
    let idleGames = 0;
    let activeGames = 0;

    for (const entry of this.games.values()) {
      const gameAge = now - entry.createdAt;
      const timeSinceActivity = now - entry.lastActivity;
      
      if (gameAge > oldestGameAge) {
        oldestGameAge = gameAge;
      }
      
      totalAccessCount += entry.accessCount;
      
      if (timeSinceActivity > this.config.idleTimeoutMs / 2) {
        idleGames++;
      } else {
        activeGames++;
      }
    }

    return {
      totalGames: this.games.size,
      memoryUsageMB: Math.round(memoryUsageMB * 100) / 100,
      oldestGameAge,
      averageAccessCount: this.games.size > 0 ? totalAccessCount / this.games.size : 0,
      idleGames,
      activeGames
    };
  }

  /**
   * Force cleanup of idle games
   */
  performCleanup(): number {
    const now = Date.now();
    const gamesToDelete: string[] = [];

    for (const [code, entry] of this.games.entries()) {
      const timeSinceActivity = now - entry.lastActivity;
      const gameAge = now - entry.createdAt;

      // Mark for deletion if:
      // 1. Game is idle beyond timeout
      // 2. Game is finished and old
      // 3. Game is in error state
      if (
        timeSinceActivity > this.config.idleTimeoutMs ||
        (entry.game.gameState === 'finished' && gameAge > 5 * 60 * 1000) || // Reduced from 10 to 5 min for finished games
        entry.game.gameState === 'error'
      ) {
        gamesToDelete.push(code);
      }
    }

    // Delete identified games
    let deletedCount = 0;
    for (const code of gamesToDelete) {
      if (this.delete(code)) {
        deletedCount++;
      }
    }

    if (deletedCount > 0) {
      logger.info(`Cleanup completed: ${deletedCount} games removed`, {
        remainingGames: this.games.size,
        memoryUsageMB: this.getStats().memoryUsageMB
      });
    }

    return deletedCount;
  }

  /**
   * Emergency cleanup when approaching limits
   */
  private performEmergencyCleanup(): void {
    logger.warn('Performing emergency cleanup - approaching capacity limits');
    
    // First, try normal cleanup
    const normalCleanup = this.performCleanup();
    
    if (this.games.size >= this.config.maxGames * 0.8) { // Reduced from 0.9 to 0.8 for 1GB servers
      // If still near capacity, remove oldest idle games
      const entries = Array.from(this.games.entries())
        .map(([code, entry]) => ({ code, ...entry }))
        .sort((a, b) => a.lastActivity - b.lastActivity);

      const toRemove = Math.min(20, entries.length * 0.2); // Increased from 10% to 20% for more aggressive cleanup
      
      for (let i = 0; i < toRemove; i++) {
        this.delete(entries[i].code);
      }
      
      logger.warn(`Emergency cleanup: removed ${toRemove} oldest games`, {
        totalRemoved: normalCleanup + toRemove,
        remainingGames: this.games.size
      });
    }
  }

  /**
   * Clean up game-specific resources
   */
  private cleanupGameResources(game: GameSession): void {
    // Clear any game-specific data that might hold references
    if (game.participants) {
      game.participants.clear();
    }
    if (game.scores) {
      game.scores.clear();
    }
    if (game.answers) {
      game.answers.clear();
    }
    
    // Clear arrays to free memory
    if (game.rounds) {
      game.rounds.length = 0;
    }
    if (game.buzzOrder) {
      game.buzzOrder.length = 0;
    }
  }

  /**
   * Start automatic cleanup process
   */
  private startCleanupProcess(): void {
    // Skip interval creation in test environment to prevent Jest open handle warnings
    if (process.env.NODE_ENV === 'test') {
      logger.debug('GameStorageManager: Skipping cleanup interval creation in test environment');
      return;
    }

    this.cleanupInterval = setInterval(() => {
      try {
        const stats = this.getStats();
        
        // Check memory usage
        if (stats.memoryUsageMB > this.memoryWarningThreshold) {
          logger.warn('High memory usage detected', {
            currentMB: stats.memoryUsageMB,
            thresholdMB: this.memoryWarningThreshold,
            totalGames: stats.totalGames
          });
          this.performEmergencyCleanup();
        } else {
          // Normal cleanup
          this.performCleanup();
        }
        
        // Log periodic stats
        if (stats.totalGames > 0) {
          logger.debug('Storage stats', stats);
        }
        
      } catch (error) {
        logger.error('Error during cleanup process', error);
      }
    }, this.config.cleanupIntervalMs);
  }

  /**
   * Static: Clean up all GameStorageManager instances (for test teardown)
   */
  public static cleanupAllInstances(): void {
    for (const instance of GameStorageManager._instances) {
      instance.destroy();
    }
    GameStorageManager._instances.clear();
  }

  /**
   * Shutdown and cleanup
   */
  destroy(): void {
    if (this.cleanupInterval) {
      clearInterval(this.cleanupInterval);
    }
    
    // Clean up all games
    for (const [code] of this.games.entries()) {
      this.delete(code);
    }
    
    logger.info('GameStorageManager destroyed', {
      finalGameCount: this.games.size
    });
    GameStorageManager._instances.delete(this);
  }
} 