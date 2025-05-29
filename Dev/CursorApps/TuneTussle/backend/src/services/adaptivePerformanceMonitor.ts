import { logger } from '../utils/logger';
import { GameStorageManager } from './gameStorageManager';
import { ResourcePoolManager } from './resourcePoolManager';

interface PerformanceMetrics {
  timestamp: number;
  cpuUsage: number;
  memoryUsageMB: number;
  activeGames: number;
  socketConnections: number;
  eventThroughput: number;
  averageResponseTime: number;
}

interface PerformanceThresholds {
  cpuWarning: number;
  cpuCritical: number;
  memoryWarningMB: number;
  memoryCriticalMB: number;
  responseTimeWarningMs: number;
  responseTimeCriticalMs: number;
}

interface MonitoringConfig {
  baseIntervalMs: number;
  adaptiveScaling: boolean;
  metricsRetentionCount: number;
  alertingEnabled: boolean;
}

/**
 * Adaptive Performance Monitor - Dynamically adjusts monitoring based on system load
 * and provides real-time performance insights for scaling to hundreds of games.
 */
export class AdaptivePerformanceMonitor {
  private static instance: AdaptivePerformanceMonitor;
  private metrics: PerformanceMetrics[] = [];
  private config: MonitoringConfig;
  private thresholds: PerformanceThresholds;
  private monitoringInterval: NodeJS.Timeout | null = null;
  private currentIntervalMs: number;
  private gameStorageManager: GameStorageManager | null = null;
  private resourcePoolManager: ResourcePoolManager | null = null;
  
  // Performance tracking
  private eventCounter = 0;
  private responseTimeSum = 0;
  private responseTimeCount = 0;
  private lastEventReset = Date.now();

  private constructor(
    config: Partial<MonitoringConfig> = {},
    thresholds: Partial<PerformanceThresholds> = {}
  ) {
    // Detect development mode
    const isDevelopment = process.env.NODE_ENV !== 'production';
    
    this.config = {
      baseIntervalMs: 30000, // 30 seconds base interval
      adaptiveScaling: true,
      metricsRetentionCount: 100, // Keep last 100 metrics
      alertingEnabled: !isDevelopment, // Disable alerting in development mode
      ...config
    };

    // Use relaxed thresholds in development mode since ts-node uses more memory
    const defaultThresholds = isDevelopment ? {
      cpuWarning: 85, // 85% CPU (relaxed from 70%)
      cpuCritical: 95, // 95% CPU (relaxed from 85%)
      memoryWarningMB: 1500, // 1.5GB warning (relaxed for ts-node)
      memoryCriticalMB: 2000, // 2GB critical (relaxed for ts-node)
      responseTimeWarningMs: 2000, // 2 seconds (relaxed from 1s)
      responseTimeCriticalMs: 5000, // 5 seconds (relaxed from 3s)
    } : {
      cpuWarning: 70, // 70% CPU
      cpuCritical: 85, // 85% CPU
      memoryWarningMB: 400, // 400MB
      memoryCriticalMB: 600, // 600MB
      responseTimeWarningMs: 1000, // 1 second
      responseTimeCriticalMs: 3000, // 3 seconds
    };

    this.thresholds = {
      ...defaultThresholds,
      ...thresholds
    };

    this.currentIntervalMs = this.config.baseIntervalMs;
    this.startMonitoring();

    logger.info('AdaptivePerformanceMonitor initialized', {
      mode: isDevelopment ? 'development' : 'production',
      baseIntervalMs: this.config.baseIntervalMs,
      adaptiveScaling: this.config.adaptiveScaling,
      alertingEnabled: this.config.alertingEnabled,
      thresholds: this.thresholds
    });
  }

  static getInstance(
    config?: Partial<MonitoringConfig>,
    thresholds?: Partial<PerformanceThresholds>
  ): AdaptivePerformanceMonitor {
    if (!AdaptivePerformanceMonitor.instance) {
      AdaptivePerformanceMonitor.instance = new AdaptivePerformanceMonitor(config, thresholds);
    }
    return AdaptivePerformanceMonitor.instance;
  }

  /**
   * Reset singleton instance (for test cleanup)
   */
  static resetInstance(): void {
    if (AdaptivePerformanceMonitor.instance) {
      AdaptivePerformanceMonitor.instance.destroy();
      AdaptivePerformanceMonitor.instance = null as any;
    }
  }

  /**
   * Set external managers for enhanced monitoring
   */
  setManagers(
    gameStorageManager: GameStorageManager,
    resourcePoolManager: ResourcePoolManager
  ): void {
    this.gameStorageManager = gameStorageManager;
    this.resourcePoolManager = resourcePoolManager;
  }

  /**
   * Track an event for throughput monitoring
   */
  trackEvent(): void {
    this.eventCounter++;
  }

  /**
   * Track response time for performance monitoring
   */
  trackResponseTime(responseTimeMs: number): void {
    this.responseTimeSum += responseTimeMs;
    this.responseTimeCount++;
  }

  /**
   * Get current performance metrics
   */
  getCurrentMetrics(): PerformanceMetrics {
    const memoryUsage = process.memoryUsage();
    const now = Date.now();
    
    // Calculate event throughput (events per second)
    const timeSinceReset = now - this.lastEventReset;
    const eventThroughput = timeSinceReset > 0 ? 
      (this.eventCounter * 1000) / timeSinceReset : 0;
    
    // Calculate average response time
    const averageResponseTime = this.responseTimeCount > 0 ? 
      this.responseTimeSum / this.responseTimeCount : 0;

    // Get CPU usage (approximation based on event loop delay)
    const cpuUsage = this.estimateCpuUsage();

    return {
      timestamp: now,
      cpuUsage,
      memoryUsageMB: Math.round(memoryUsage.heapUsed / (1024 * 1024) * 100) / 100,
      activeGames: this.gameStorageManager?.getStats().totalGames || 0,
      socketConnections: this.resourcePoolManager?.getResourceStats().batchProcessors.activeProcessors || 0,
      eventThroughput: Math.round(eventThroughput * 100) / 100,
      averageResponseTime: Math.round(averageResponseTime * 100) / 100
    };
  }

  /**
   * Get performance statistics and trends
   */
  getPerformanceStats(): {
    current: PerformanceMetrics;
    trends: {
      cpuTrend: 'increasing' | 'decreasing' | 'stable';
      memoryTrend: 'increasing' | 'decreasing' | 'stable';
      throughputTrend: 'increasing' | 'decreasing' | 'stable';
    };
    alerts: string[];
    recommendations: string[];
  } {
    const current = this.getCurrentMetrics();
    const alerts: string[] = [];
    const recommendations: string[] = [];

    // Check thresholds and generate alerts
    if (current.cpuUsage > this.thresholds.cpuCritical) {
      alerts.push(`CRITICAL: CPU usage at ${current.cpuUsage}%`);
      recommendations.push('Consider scaling horizontally or optimizing CPU-intensive operations');
    } else if (current.cpuUsage > this.thresholds.cpuWarning) {
      alerts.push(`WARNING: CPU usage at ${current.cpuUsage}%`);
      recommendations.push('Monitor CPU usage closely and prepare for scaling');
    }

    if (current.memoryUsageMB > this.thresholds.memoryCriticalMB) {
      alerts.push(`CRITICAL: Memory usage at ${current.memoryUsageMB}MB`);
      recommendations.push('Immediate memory cleanup required - check for memory leaks');
    } else if (current.memoryUsageMB > this.thresholds.memoryWarningMB) {
      alerts.push(`WARNING: Memory usage at ${current.memoryUsageMB}MB`);
      recommendations.push('Consider increasing cleanup frequency or reducing game TTL');
    }

    if (current.averageResponseTime > this.thresholds.responseTimeCriticalMs) {
      alerts.push(`CRITICAL: Response time at ${current.averageResponseTime}ms`);
      recommendations.push('Optimize database queries and reduce processing complexity');
    } else if (current.averageResponseTime > this.thresholds.responseTimeWarningMs) {
      alerts.push(`WARNING: Response time at ${current.averageResponseTime}ms`);
      recommendations.push('Monitor response times and consider performance optimizations');
    }

    // Generate performance recommendations
    if (current.activeGames > 100) {
      recommendations.push('High game count detected - ensure resource pooling is optimized');
    }

    if (current.eventThroughput > 1000) {
      recommendations.push('High event throughput - consider increasing batch window size');
    }

    return {
      current,
      trends: this.calculateTrends(),
      alerts,
      recommendations
    };
  }

  /**
   * Force immediate performance check and optimization
   */
  performHealthCheck(): {
    status: 'healthy' | 'warning' | 'critical';
    issues: string[];
    actions: string[];
  } {
    const stats = this.getPerformanceStats();
    const issues: string[] = [];
    const actions: string[] = [];
    
    let status: 'healthy' | 'warning' | 'critical' = 'healthy';
    const isDevelopment = process.env.NODE_ENV !== 'production';

    // In development mode, be more lenient with status reporting
    if (isDevelopment) {
      // Only report critical if truly critical (above development thresholds)
      if (stats.current.cpuUsage > this.thresholds.cpuCritical ||
          stats.current.memoryUsageMB > this.thresholds.memoryCriticalMB ||
          stats.current.averageResponseTime > this.thresholds.responseTimeCriticalMs) {
        status = 'warning'; // Downgrade to warning in dev mode
        issues.push('Development mode: High resource usage detected');
        actions.push('This is normal for development with ts-node');
      }
    } else {
      // Production mode: use normal thresholds
    if (stats.current.cpuUsage > this.thresholds.cpuCritical ||
        stats.current.memoryUsageMB > this.thresholds.memoryCriticalMB ||
        stats.current.averageResponseTime > this.thresholds.responseTimeCriticalMs) {
      status = 'critical';
      issues.push('System is under critical load');
      actions.push('Immediate intervention required');
    } else if (stats.alerts.length > 0) {
      status = 'warning';
      issues.push(...stats.alerts);
      actions.push(...stats.recommendations);
      }
    }

    // Trigger automatic optimizations if enabled
    if (status !== 'healthy' && this.config.adaptiveScaling) {
      this.triggerAutoOptimizations(stats.current);
    }

    return { status, issues, actions };
  }

  /**
   * Calculate performance trends
   */
  private calculateTrends(): {
    cpuTrend: 'increasing' | 'decreasing' | 'stable';
    memoryTrend: 'increasing' | 'decreasing' | 'stable';
    throughputTrend: 'increasing' | 'decreasing' | 'stable';
  } {
    if (this.metrics.length < 3) {
      return {
        cpuTrend: 'stable',
        memoryTrend: 'stable',
        throughputTrend: 'stable'
      };
    }

    const recent = this.metrics.slice(-3);
    const cpuTrend = this.calculateTrend(recent.map(m => m.cpuUsage));
    const memoryTrend = this.calculateTrend(recent.map(m => m.memoryUsageMB));
    const throughputTrend = this.calculateTrend(recent.map(m => m.eventThroughput));

    return { cpuTrend, memoryTrend, throughputTrend };
  }

  /**
   * Calculate trend for a series of values
   */
  private calculateTrend(values: number[]): 'increasing' | 'decreasing' | 'stable' {
    if (values.length < 2) return 'stable';
    
    const first = values[0];
    const last = values[values.length - 1];
    const change = (last - first) / first;
    
    if (change > 0.1) return 'increasing';
    if (change < -0.1) return 'decreasing';
    return 'stable';
  }

  /**
   * Estimate CPU usage based on event loop delay and system metrics
   */
  private estimateCpuUsage(): number {
    // Use event loop lag as a proxy for CPU usage
    const start = process.hrtime.bigint();
    
    // Synchronous measurement of event loop delay
    let delay = 0;
    setImmediate(() => {
      delay = Number(process.hrtime.bigint() - start) / 1000000; // Convert to ms
    });
    
    // Use process CPU usage from Node.js
    const cpuUsage = process.cpuUsage();
    const totalCpu = cpuUsage.user + cpuUsage.system;
    
    // Convert microseconds to percentage (rough approximation)
    const cpuPercent = Math.min(100, (totalCpu / 10000) % 100);
    
    // Combine event loop delay with CPU metrics for better estimation
    const eventLoopFactor = Math.min(100, delay * 2); // Event loop delay factor
    const combinedCpu = Math.max(cpuPercent, eventLoopFactor * 0.1);
    
    return Math.round(Math.min(100, combinedCpu));
  }

  /**
   * Trigger automatic optimizations based on current metrics
   */
  private triggerAutoOptimizations(metrics: PerformanceMetrics): void {
    logger.info('Triggering automatic optimizations', metrics);

    // Adjust monitoring frequency based on load
    if (metrics.cpuUsage > this.thresholds.cpuWarning || 
        metrics.memoryUsageMB > this.thresholds.memoryWarningMB) {
      // Increase monitoring frequency during high load
      this.adjustMonitoringFrequency(0.5); // 2x faster
    } else if (metrics.cpuUsage < 30 && metrics.memoryUsageMB < 200) {
      // Decrease monitoring frequency during low load
      this.adjustMonitoringFrequency(2); // 2x slower
    }

    // Trigger resource optimizations
    if (this.resourcePoolManager) {
      this.resourcePoolManager.optimizeResources();
    }

    // Trigger storage cleanup if memory is high
    if (metrics.memoryUsageMB > this.thresholds.memoryWarningMB && this.gameStorageManager) {
      this.gameStorageManager.performCleanup();
    }
  }

  /**
   * Adjust monitoring frequency
   */
  private adjustMonitoringFrequency(multiplier: number): void {
    const newInterval = Math.max(5000, this.config.baseIntervalMs * multiplier);
    
    if (newInterval !== this.currentIntervalMs) {
      this.currentIntervalMs = newInterval;
      this.restartMonitoring();
      
      logger.info(`Adjusted monitoring frequency`, {
        oldIntervalMs: this.currentIntervalMs / multiplier,
        newIntervalMs: this.currentIntervalMs,
        multiplier
      });
    }
  }

  /**
   * Start monitoring process
   */
  private startMonitoring(): void {
    // Skip interval creation in test environment to prevent Jest open handle warnings
    if (process.env.NODE_ENV === 'test') {
      logger.debug('AdaptivePerformanceMonitor: Skipping interval creation in test environment');
      return;
    }

    this.monitoringInterval = setInterval(() => {
      try {
        const metrics = this.getCurrentMetrics();
        this.metrics.push(metrics);
        
        // Trim metrics to retention limit
        if (this.metrics.length > this.config.metricsRetentionCount) {
          this.metrics = this.metrics.slice(-this.config.metricsRetentionCount);
        }
        
        // Reset counters
        this.eventCounter = 0;
        this.responseTimeSum = 0;
        this.responseTimeCount = 0;
        this.lastEventReset = Date.now();
        
        // Log metrics if significant activity
        if (metrics.activeGames > 0 || metrics.eventThroughput > 0) {
          logger.debug('Performance metrics', metrics);
        }
        
        // Perform health check if alerting is enabled
        if (this.config.alertingEnabled) {
          const healthCheck = this.performHealthCheck();
          if (healthCheck.status !== 'healthy') {
            logger.warn('Performance health check', healthCheck);
          }
        }
        
      } catch (error) {
        logger.error('Error during performance monitoring', error);
      }
    }, this.currentIntervalMs);
  }

  /**
   * Restart monitoring with new interval
   */
  private restartMonitoring(): void {
    if (this.monitoringInterval) {
      clearInterval(this.monitoringInterval);
    }
    this.startMonitoring();
  }

  /**
   * Shutdown monitoring
   */
  destroy(): void {
    if (this.monitoringInterval) {
      clearInterval(this.monitoringInterval);
      this.monitoringInterval = null;
    }
    
    this.metrics = [];
    logger.info('AdaptivePerformanceMonitor destroyed');
  }
} 