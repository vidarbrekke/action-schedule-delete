import { TimerService } from './timerService';
import { BatchEventProcessorFactory } from './batchEventProcessorFactory';
import { logger } from '../utils/logger';

interface PoolConfig {
  maxTimerPools: number;
  maxBatchProcessors: number;
  resourceCleanupIntervalMs: number;
  poolUtilizationThreshold: number;
}

interface ResourceUsage {
  timerId: string;
  gameCode: string;
  lastUsed: number;
  usageCount: number;
}

/**
 * Manages shared resource pools to optimize CPU and memory usage across hundreds of games.
 * Implements resource sharing, pooling, and intelligent cleanup strategies.
 */
export class ResourcePoolManager {
  private static instance: ResourcePoolManager;
  private timerPools = new Map<string, TimerService>();
  private timerUsage = new Map<string, ResourceUsage[]>();
  private batchProcessorFactory: BatchEventProcessorFactory;
  private config: PoolConfig;
  private cleanupInterval!: NodeJS.Timeout;
  private poolAssignments = new Map<string, string>(); // gameCode -> poolId

  private constructor(config: Partial<PoolConfig> = {}) {
    this.config = {
      maxTimerPools: 10, // Share timers across games
      maxBatchProcessors: 50, // Limit batch processors
      resourceCleanupIntervalMs: 2 * 60 * 1000, // Cleanup every 2 minutes
      poolUtilizationThreshold: 0.8, // 80% utilization threshold
      ...config
    };

    this.batchProcessorFactory = BatchEventProcessorFactory.getInstance();
    this.initializeTimerPools();
    this.startResourceCleanup();

    logger.info('ResourcePoolManager initialized', {
      maxTimerPools: this.config.maxTimerPools,
      maxBatchProcessors: this.config.maxBatchProcessors
    });
  }

  static getInstance(config?: Partial<PoolConfig>): ResourcePoolManager {
    if (!ResourcePoolManager.instance) {
      ResourcePoolManager.instance = new ResourcePoolManager(config);
    }
    return ResourcePoolManager.instance;
  }

  /**
   * Reset singleton instance (for test cleanup)
   */
  static resetInstance(): void {
    if (ResourcePoolManager.instance) {
      ResourcePoolManager.instance.destroy();
      ResourcePoolManager.instance = null as any;
    }
  }

  /**
   * Get a shared timer service for a game
   */
  getTimerService(gameCode: string): TimerService {
    // Check if game already has a pool assignment
    let poolId = this.poolAssignments.get(gameCode);
    
    if (!poolId) {
      // Assign to least utilized pool
      poolId = this.findOptimalTimerPool();
      this.poolAssignments.set(gameCode, poolId);
    }

    const timerService = this.timerPools.get(poolId)!;
    
    // Track usage
    this.trackTimerUsage(poolId, gameCode);
    
    return timerService;
  }

  /**
   * Release timer service for a game
   */
  releaseTimerService(gameCode: string): void {
    const poolId = this.poolAssignments.get(gameCode);
    if (poolId) {
      // Clear any active timers for this game
      const timerService = this.timerPools.get(poolId);
      if (timerService) {
        timerService.clearTimer(gameCode);
      }
      
      // Remove usage tracking
      this.removeTimerUsage(poolId, gameCode);
      this.poolAssignments.delete(gameCode);
      
      logger.debug(`Released timer service for game ${gameCode} from pool ${poolId}`);
    }
  }

  /**
   * Get batch processor factory (already optimized)
   */
  getBatchProcessorFactory(): BatchEventProcessorFactory {
    return this.batchProcessorFactory;
  }

  /**
   * Get resource utilization statistics
   */
  getResourceStats(): {
    timerPools: {
      totalPools: number;
      averageUtilization: number;
      peakUtilization: number;
      totalGamesServed: number;
    };
    batchProcessors: {
      activeProcessors: number;
      memoryUsage: number;
    };
    memoryUsage: {
      heapUsedMB: number;
      heapTotalMB: number;
      externalMB: number;
    };
  } {
    const memoryUsage = process.memoryUsage();
    
    // Calculate timer pool utilization
    let totalGamesServed = 0;
    let peakUtilization = 0;
    
    for (const [poolId, usage] of this.timerUsage.entries()) {
      const currentUtilization = usage.length;
      totalGamesServed += currentUtilization;
      
      if (currentUtilization > peakUtilization) {
        peakUtilization = currentUtilization;
      }
    }
    
    const averageUtilization = this.timerPools.size > 0 ? 
      totalGamesServed / this.timerPools.size : 0;

    // Get batch processor stats
    const batchStats = this.batchProcessorFactory.getStats();

    return {
      timerPools: {
        totalPools: this.timerPools.size,
        averageUtilization,
        peakUtilization,
        totalGamesServed
      },
      batchProcessors: {
        activeProcessors: batchStats.activeProcessors,
        memoryUsage: batchStats.memoryUsage
      },
      memoryUsage: {
        heapUsedMB: Math.round(memoryUsage.heapUsed / (1024 * 1024) * 100) / 100,
        heapTotalMB: Math.round(memoryUsage.heapTotal / (1024 * 1024) * 100) / 100,
        externalMB: Math.round(memoryUsage.external / (1024 * 1024) * 100) / 100
      }
    };
  }

  /**
   * Optimize resource allocation based on current usage
   */
  optimizeResources(): void {
    const stats = this.getResourceStats();
    
    // Check if we need more timer pools
    if (stats.timerPools.averageUtilization > this.config.poolUtilizationThreshold) {
      this.expandTimerPools();
    }
    
    // Check if we can consolidate timer pools
    if (stats.timerPools.averageUtilization < 0.3 && this.timerPools.size > 2) {
      this.consolidateTimerPools();
    }
    
    logger.debug('Resource optimization completed', stats);
  }

  /**
   * Initialize timer pools
   */
  private initializeTimerPools(): void {
    for (let i = 0; i < Math.min(this.config.maxTimerPools, 3); i++) {
      const poolId = `timer-pool-${i}`;
      this.timerPools.set(poolId, new TimerService());
      this.timerUsage.set(poolId, []);
    }
    
    logger.debug(`Initialized ${this.timerPools.size} timer pools`);
  }

  /**
   * Find the optimal timer pool for assignment
   */
  private findOptimalTimerPool(): string {
    let optimalPoolId = '';
    let minUtilization = Infinity;
    
    for (const [poolId, usage] of this.timerUsage.entries()) {
      if (usage.length < minUtilization) {
        minUtilization = usage.length;
        optimalPoolId = poolId;
      }
    }
    
    return optimalPoolId;
  }

  /**
   * Track timer usage for a game
   */
  private trackTimerUsage(poolId: string, gameCode: string): void {
    const usage = this.timerUsage.get(poolId) || [];
    
    // Check if already tracking this game
    const existingIndex = usage.findIndex(u => u.gameCode === gameCode);
    
    if (existingIndex >= 0) {
      // Update existing usage
      usage[existingIndex].lastUsed = Date.now();
      usage[existingIndex].usageCount++;
    } else {
      // Add new usage tracking
      usage.push({
        timerId: poolId,
        gameCode,
        lastUsed: Date.now(),
        usageCount: 1
      });
    }
    
    this.timerUsage.set(poolId, usage);
  }

  /**
   * Remove timer usage tracking for a game
   */
  private removeTimerUsage(poolId: string, gameCode: string): void {
    const usage = this.timerUsage.get(poolId) || [];
    const filteredUsage = usage.filter(u => u.gameCode !== gameCode);
    this.timerUsage.set(poolId, filteredUsage);
  }

  /**
   * Expand timer pools when utilization is high
   */
  private expandTimerPools(): void {
    if (this.timerPools.size >= this.config.maxTimerPools) {
      logger.warn('Cannot expand timer pools - at maximum capacity', {
        currentPools: this.timerPools.size,
        maxPools: this.config.maxTimerPools
      });
      return;
    }
    
    const newPoolId = `timer-pool-${this.timerPools.size}`;
    this.timerPools.set(newPoolId, new TimerService());
    this.timerUsage.set(newPoolId, []);
    
    logger.info(`Expanded timer pools: added ${newPoolId}`, {
      totalPools: this.timerPools.size
    });
  }

  /**
   * Consolidate timer pools when utilization is low
   */
  private consolidateTimerPools(): void {
    // Find the least utilized pool
    let leastUtilizedPoolId = '';
    let minUtilization = Infinity;
    
    for (const [poolId, usage] of this.timerUsage.entries()) {
      if (usage.length < minUtilization && usage.length === 0) {
        minUtilization = usage.length;
        leastUtilizedPoolId = poolId;
      }
    }
    
    if (leastUtilizedPoolId) {
      // Clean up the pool
      const timerService = this.timerPools.get(leastUtilizedPoolId);
      if (timerService) {
        timerService.clearAllTimers();
      }
      
      this.timerPools.delete(leastUtilizedPoolId);
      this.timerUsage.delete(leastUtilizedPoolId);
      
      logger.info(`Consolidated timer pools: removed ${leastUtilizedPoolId}`, {
        remainingPools: this.timerPools.size
      });
    }
  }

  /**
   * Start resource cleanup process
   */
  private startResourceCleanup(): void {
    // Skip interval creation in test environment to prevent Jest open handle warnings
    if (process.env.NODE_ENV === 'test') {
      logger.debug('ResourcePoolManager: Skipping cleanup interval creation in test environment');
      return;
    }

    this.cleanupInterval = setInterval(() => {
      try {
        this.cleanupStaleUsage();
        this.optimizeResources();
        
        // Log resource stats periodically
        const stats = this.getResourceStats();
        if (stats.timerPools.totalGamesServed > 0) {
          logger.debug('Resource pool stats', stats);
        }
        
      } catch (error) {
        logger.error('Error during resource cleanup', error);
      }
    }, this.config.resourceCleanupIntervalMs);
  }

  /**
   * Clean up stale usage tracking
   */
  private cleanupStaleUsage(): void {
    const now = Date.now();
    const staleThreshold = 10 * 60 * 1000; // 10 minutes
    
    for (const [poolId, usage] of this.timerUsage.entries()) {
      const activeUsage = usage.filter(u => now - u.lastUsed < staleThreshold);
      
      if (activeUsage.length !== usage.length) {
        this.timerUsage.set(poolId, activeUsage);
        
        // Remove stale pool assignments
        for (const staleUsage of usage) {
          if (now - staleUsage.lastUsed >= staleThreshold) {
            this.poolAssignments.delete(staleUsage.gameCode);
          }
        }
      }
    }
  }

  /**
   * Shutdown and cleanup all resources
   */
  destroy(): void {
    if (this.cleanupInterval) {
      clearInterval(this.cleanupInterval);
    }
    
    // Clean up all timer pools
    for (const [poolId, timerService] of this.timerPools.entries()) {
      timerService.clearAllTimers();
      logger.debug(`Cleaned up timer pool: ${poolId}`);
    }
    
    this.timerPools.clear();
    this.timerUsage.clear();
    this.poolAssignments.clear();
    
    // Clean up batch processor factory
    this.batchProcessorFactory.cleanupAll();
    
    logger.info('ResourcePoolManager destroyed');
  }
} 