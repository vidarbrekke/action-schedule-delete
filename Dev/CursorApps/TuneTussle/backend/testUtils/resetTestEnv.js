"use strict";
var __awaiter = (this && this.__awaiter) || function (thisArg, _arguments, P, generator) {
    function adopt(value) { return value instanceof P ? value : new P(function (resolve) { resolve(value); }); }
    return new (P || (P = Promise))(function (resolve, reject) {
        function fulfilled(value) { try { step(generator.next(value)); } catch (e) { reject(e); } }
        function rejected(value) { try { step(generator["throw"](value)); } catch (e) { reject(e); } }
        function step(result) { result.done ? resolve(result.value) : adopt(result.value).then(fulfilled, rejected); }
        step((generator = generator.apply(thisArg, _arguments || [])).next());
    });
};
Object.defineProperty(exports, "__esModule", { value: true });
exports.resetAllMocksAndModules = resetAllMocksAndModules;
exports.resetAllMocksAndModulesAsync = resetAllMocksAndModulesAsync;
const gameStorageManager_1 = require("../src/services/gameStorageManager");
const resourcePoolManager_1 = require("../src/services/resourcePoolManager");
const adaptivePerformanceMonitor_1 = require("../src/services/adaptivePerformanceMonitor");
const batchEventProcessorFactory_1 = require("../src/services/batchEventProcessorFactory");
/**
 * Comprehensive test environment reset that handles all background services
 * and async resources that could cause test hanging or memory leaks.
 */
function resetAllMocksAndModules() {
    // Clear Jest state
    jest.resetModules();
    jest.clearAllMocks();
    jest.clearAllTimers();
    // Force cleanup of all singleton instances and background services
    try {
        // Clean up GameStorageManager instances (handles cleanup intervals)
        gameStorageManager_1.GameStorageManager.cleanupAllInstances();
    }
    catch (error) {
        // Silent fail for test environment - instances may not exist
    }
    try {
        // Clean up ResourcePoolManager (handles timer pools and cleanup intervals)
        const resourceManager = resourcePoolManager_1.ResourcePoolManager.getInstance();
        resourceManager.destroy();
    }
    catch (error) {
        // Silent fail for test environment
    }
    try {
        // Clean up AdaptivePerformanceMonitor (handles monitoring intervals)
        const performanceMonitor = adaptivePerformanceMonitor_1.AdaptivePerformanceMonitor.getInstance();
        performanceMonitor.destroy();
    }
    catch (error) {
        // Silent fail for test environment
    }
    try {
        // Clean up BatchEventProcessorFactory (handles batch processing)
        const batchFactory = batchEventProcessorFactory_1.BatchEventProcessorFactory.getInstance();
        batchFactory.cleanupAll();
    }
    catch (error) {
        // Silent fail for test environment
    }
    // Clear any remaining timers that Jest might have missed
    if (typeof global !== 'undefined' && global.gc) {
        // Force garbage collection if available (in test environment)
        global.gc();
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
function resetAllMocksAndModulesAsync() {
    return __awaiter(this, void 0, void 0, function* () {
        yield resetAllMocksAndModules();
    });
}
