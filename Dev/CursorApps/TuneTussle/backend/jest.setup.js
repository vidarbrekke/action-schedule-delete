// Jest global setup for all backend tests
jest.mock('axios');
const axios = require('axios');
axios.get = jest.fn().mockResolvedValue({ data: {} });

// Provide a global, mutable mock for envConfig
const globalMockEnvState = {
  OPENROUTER_API_KEY: null,
  OPENROUTER_MODEL_ID: null,
  OPENROUTER_API_BASE_URL: null,
  YOUTUBE_API_KEY: null,
  NODE_ENV: 'test',
  PORT: '4000',
};

// Mock only the correct import path for envConfig
jest.mock('./src/utils/envConfig', () => ({
  __esModule: true,
  default: new Proxy(globalMockEnvState, {
    get: (target, prop) => target[prop],
    set: (target, prop, value) => { target[prop] = value; return true; }
  }),
  validateRequiredEnvVars: jest.fn().mockReturnValue(true),
}));

global.__MOCK_ENV_STATE__ = globalMockEnvState; 

// Global cleanup function for test isolation - only clean up if modules are loaded
function cleanupSingletons() {
  try {
    // Only clean up if modules are in the cache (have been required)
    const moduleCache = require.cache;
    
    // Clean up batch event processors if loaded
    const batchFactoryPath = require.resolve('./src/services/batchEventProcessorFactory');
    if (moduleCache[batchFactoryPath]) {
      const batchEventProcessorFactory = require('./src/services/batchEventProcessorFactory');
      if (batchEventProcessorFactory && typeof batchEventProcessorFactory.cleanupAll === 'function') {
        batchEventProcessorFactory.cleanupAll();
      }
    }

    // Clean up game storage manager instances if loaded
    const gameStoragePath = require.resolve('./src/services/gameStorageManager');
    if (moduleCache[gameStoragePath]) {
      const { GameStorageManager } = require('./src/services/gameStorageManager');
      if (GameStorageManager && typeof GameStorageManager.cleanupAllInstances === 'function') {
        GameStorageManager.cleanupAllInstances();
      }
    }

    // Clean up resource pool manager if loaded
    const resourcePoolPath = require.resolve('./src/services/resourcePoolManager');
    if (moduleCache[resourcePoolPath]) {
      const { ResourcePoolManager } = require('./src/services/resourcePoolManager');
      if (ResourcePoolManager && typeof ResourcePoolManager.resetInstance === 'function') {
        ResourcePoolManager.resetInstance();
      }
    }

    // Clean up adaptive performance monitor if loaded
    const performanceMonitorPath = require.resolve('./src/services/adaptivePerformanceMonitor');
    if (moduleCache[performanceMonitorPath]) {
      const { AdaptivePerformanceMonitor } = require('./src/services/adaptivePerformanceMonitor');
      if (AdaptivePerformanceMonitor && typeof AdaptivePerformanceMonitor.resetInstance === 'function') {
        AdaptivePerformanceMonitor.resetInstance();
      }
    }
  } catch (error) {
    // Silently ignore cleanup errors to prevent test failures
    // console.warn('Singleton cleanup warning:', error.message);
  }
}

// Only run cleanup after each test, not before (to avoid premature cleanup)
afterEach(() => {
  cleanupSingletons();
});

// Export for manual use in test files if needed
global.__CLEANUP_SINGLETONS__ = cleanupSingletons; 

// Set environment to test mode
process.env.NODE_ENV = 'test';

// Mock GameStorageManager at the module level 