import { GameStorageManager } from '../src/services/gameStorageManager';
import { ResourcePoolManager } from '../src/services/resourcePoolManager';
import { AdaptivePerformanceMonitor } from '../src/services/adaptivePerformanceMonitor';
import { BatchEventProcessorFactory } from '../src/services/batchEventProcessorFactory';

/**
 * Comprehensive test environment reset that handles all background services
 * and async resources that could cause test hanging or memory leaks.
 */
export function resetAllMocksAndModules() {
  // Clear Jest state
  jest.resetModules();
  jest.clearAllMocks();
  jest.clearAllTimers();
  
  // Force cleanup of all singleton instances and background services
  try {
    // Clean up GameStorageManager instances (handles cleanup intervals)
    GameStorageManager.cleanupAllInstances();
  } catch (error) {
    // Silent fail for test environment - instances may not exist
  }
  
  try {
    // Clean up ResourcePoolManager (handles timer pools and cleanup intervals)
    const resourceManager = ResourcePoolManager.getInstance();
    resourceManager.destroy();
  } catch (error) {
    // Silent fail for test environment
  }
  
  try {
    // Clean up AdaptivePerformanceMonitor (handles monitoring intervals)
    const performanceMonitor = AdaptivePerformanceMonitor.getInstance();
    performanceMonitor.destroy();
  } catch (error) {
    // Silent fail for test environment
  }
  
  try {
    // Clean up BatchEventProcessorFactory (handles batch processing)
    const batchFactory = BatchEventProcessorFactory.getInstance();
    batchFactory.cleanupAll();
  } catch (error) {
    // Silent fail for test environment
  }
  
  // Clear any remaining timers that Jest might have missed
  if (typeof global !== 'undefined' && (global as any).gc) {
    // Force garbage collection if available (in test environment)
    (global as any).gc();
  }
  
  // Give event loop a chance to clear any remaining async operations
  return new Promise(resolve => {
    setImmediate(() => {
      process.nextTick(resolve);
    });
  });
}

/**
 * Async version for use in test teardown where you can await the cleanup
 */
export async function resetAllMocksAndModulesAsync(): Promise<void> {
  await resetAllMocksAndModules();
} 