import { Router } from 'express';
import { container } from '../utils/dependencyContainer';
import { GameSessionManager } from '../gameSessionManager';
import { AdaptivePerformanceMonitor } from '../services/adaptivePerformanceMonitor';
import { ResourcePoolManager } from '../services/resourcePoolManager';

const router = Router();

/**
 * GET /api/performance/stats
 * Get comprehensive performance statistics for scaling monitoring
 */
router.get('/stats', (req, res) => {
  try {
    const gameSessionManager = container.get<GameSessionManager>('gameSessionManager');
    const performanceMonitor = AdaptivePerformanceMonitor.getInstance();
    const resourcePool = ResourcePoolManager.getInstance();
    
    const stats = {
      scaling: gameSessionManager.getScalingStats(),
      performance: performanceMonitor.getPerformanceStats(),
      resources: resourcePool.getResourceStats(),
      timestamp: new Date().toISOString()
    };
    
    res.json(stats);
  } catch (error: any) {
    res.status(500).json({
      error: 'Failed to retrieve performance stats',
      message: error.message
    });
  }
});

/**
 * GET /api/performance/health
 * Get system health check for scaling readiness
 */
router.get('/health', (req, res) => {
  try {
    const performanceMonitor = AdaptivePerformanceMonitor.getInstance();
    const healthCheck = performanceMonitor.performHealthCheck();
    
    const statusCode = healthCheck.status === 'critical' ? 503 : 
                      healthCheck.status === 'warning' ? 200 : 200;
    
    res.status(statusCode).json({
      ...healthCheck,
      timestamp: new Date().toISOString(),
      uptime: process.uptime(),
      version: process.version
    });
  } catch (error: any) {
    res.status(500).json({
      status: 'error',
      error: 'Health check failed',
      message: error.message
    });
  }
});

/**
 * GET /api/performance/metrics
 * Get real-time performance metrics
 */
router.get('/metrics', (req, res) => {
  try {
    const performanceMonitor = AdaptivePerformanceMonitor.getInstance();
    const metrics = performanceMonitor.getCurrentMetrics();
    
    res.json({
      ...metrics,
      timestamp: new Date().toISOString()
    });
  } catch (error: any) {
    res.status(500).json({
      error: 'Failed to retrieve metrics',
      message: error.message
    });
  }
});

/**
 * POST /api/performance/optimize
 * Trigger manual resource optimization
 */
router.post('/optimize', (req, res) => {
  try {
    const resourcePool = ResourcePoolManager.getInstance();
    const performanceMonitor = AdaptivePerformanceMonitor.getInstance();
    
    // Trigger optimizations
    resourcePool.optimizeResources();
    const healthCheck = performanceMonitor.performHealthCheck();
    
    res.json({
      message: 'Optimization triggered successfully',
      healthStatus: healthCheck.status,
      timestamp: new Date().toISOString()
    });
  } catch (error: any) {
    res.status(500).json({
      error: 'Optimization failed',
      message: error.message
    });
  }
});

/**
 * GET /api/performance/games
 * Get game-specific performance breakdown
 */
router.get('/games', (req, res) => {
  try {
    const gameSessionManager = container.get<GameSessionManager>('gameSessionManager');
    const scalingStats = gameSessionManager.getScalingStats();
    
    const gameBreakdown = {
      totalGames: scalingStats.storage.totalGames,
      activeGames: scalingStats.storage.activeGames,
      idleGames: scalingStats.storage.idleGames,
      memoryUsageMB: scalingStats.storage.memoryUsageMB,
      averageAccessCount: scalingStats.storage.averageAccessCount,
      oldestGameAge: scalingStats.storage.oldestGameAge,
      resourceUtilization: {
        timerPools: scalingStats.resources.timerPools,
        batchProcessors: scalingStats.resources.batchProcessors
      }
    };
    
    res.json({
      ...gameBreakdown,
      timestamp: new Date().toISOString()
    });
  } catch (error: any) {
    res.status(500).json({
      error: 'Failed to retrieve game performance data',
      message: error.message
    });
  }
});

export default router; 