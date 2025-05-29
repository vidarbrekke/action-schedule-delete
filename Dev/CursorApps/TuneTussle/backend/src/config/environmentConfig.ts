interface EnvironmentConfig {
  // Server settings
  port: number;
  nodeEnv: 'development' | 'production' | 'test';
  
  // Memory management (1GB server optimized)
  memoryLimits: {
    gameStorageMaxMB: number;
    performanceWarningMB: number;
    performanceCriticalMB: number;
    isDevelopmentMode: boolean;
  };
  
  // Game settings
  gameSettings: {
    maxGames: number;
    idleTimeoutMs: number;
    cleanupIntervalMs: number;
  };
  
  // Resource pooling
  resourcePools: {
    maxTimerPools: number;
    maxBatchProcessors: number;
    resourceCleanupIntervalMs: number;
  };
  
  // Performance monitoring
  monitoring: {
    baseIntervalMs: number;
    adaptiveScaling: boolean;
    alertingEnabled: boolean;
  };
}

export const createEnvironmentConfig = (): EnvironmentConfig => {
  const nodeEnv = (process.env.NODE_ENV || 'development') as 'development' | 'production' | 'test';
  const isDevelopment = nodeEnv === 'development';
  const isTest = nodeEnv === 'test';
  
  // Base configuration for 1GB server
  const baseConfig: EnvironmentConfig = {
    port: parseInt(process.env.PORT || '4000', 10),
    nodeEnv,
    
    memoryLimits: {
      gameStorageMaxMB: 256, // 256MB for game storage
      performanceWarningMB: 600, // Warning at 600MB 
      performanceCriticalMB: 750, // Critical at 750MB (250MB system buffer)
      isDevelopmentMode: isDevelopment
    },
    
    gameSettings: {
      maxGames: 50, // Reduced for 1GB server
      idleTimeoutMs: 30 * 60 * 1000, // Increased from 10 to 30 minutes for better URL sharing
      cleanupIntervalMs: 5 * 60 * 1000 // Increased from 2 to 5 minutes for less aggressive cleanup
    },
    
    resourcePools: {
      maxTimerPools: 3, // Reduced from 10
      maxBatchProcessors: 10, // Reduced from 50
      resourceCleanupIntervalMs: 1 * 60 * 1000 // 1 minute
    },
    
    monitoring: {
      baseIntervalMs: 30000, // 30 seconds
      adaptiveScaling: true,
      alertingEnabled: !isDevelopment && !isTest
    }
  };

  // Development mode adjustments (ts-node uses more memory)
  if (isDevelopment) {
    return {
      ...baseConfig,
      memoryLimits: {
        ...baseConfig.memoryLimits,
        performanceWarningMB: 179.2, // Use production threshold / 8 for dev mode
        performanceCriticalMB: 224, // Use production threshold / 8 for dev mode
      },
      monitoring: {
        ...baseConfig.monitoring,
        baseIntervalMs: 15000, // Faster monitoring in dev
      }
    };
  }

  // Test mode adjustments
  if (isTest) {
    return {
      ...baseConfig,
      memoryLimits: {
        ...baseConfig.memoryLimits,
        performanceWarningMB: 1000, // Relaxed for test environment
        performanceCriticalMB: 1500,
      },
      gameSettings: {
        maxGames: 10, // Fewer games for tests
        idleTimeoutMs: 30 * 1000, // 30 seconds
        cleanupIntervalMs: 10 * 1000 // 10 seconds
      },
      monitoring: {
        ...baseConfig.monitoring,
        alertingEnabled: false
      }
    };
  }

  return baseConfig;
};

export const ENV_CONFIG = createEnvironmentConfig(); 