import { GameSessionManager } from '../gameSessionManager';
import { DependencyContainer } from '../utils/dependencyContainer';
import { Server as SocketIOServer } from 'socket.io';
// MockGameStorage is used via jest.mock in the test file, no direct import needed here for that purpose

/**
 * Test wrapper for GameSessionManager.
 * Relies on module-level mocks (e.g., for GameStorageManager) set up in test files.
 */
export class TestGameSessionManager extends GameSessionManager {
  // private static testContainer: DependencyContainer; // Removed static container
  private _testHookService: any; // Store reference for testing

  static createForTesting(io?: SocketIOServer): TestGameSessionManager {
    const testContainer = new DependencyContainer(); // Always create a new container
    // testContainer.reset(); // Not needed if newing it up each time
    if (io) {
      testContainer.registerSocketIO(io);
    }
    const baseManager = testContainer.get<GameSessionManager>('gameSessionManager');

    // The GameSessionManager constructor (via super) will use the mocked GameStorageManager
    // due to jest.mock in the test file.
    return new TestGameSessionManager(
      (baseManager as any).hookService,
      (baseManager as any).participantManagementService,
      (baseManager as any).gameLifecycleService,
      (baseManager as any).roundManagementService,
      (baseManager as any).answerSubmissionService,
      io
    );
  }

  constructor(
    hookService: any,
    participantManagementService: any,
    gameLifecycleService: any,
    roundManagementService: any,
    answerSubmissionService: any,
    io?: SocketIOServer
  ) {
    super(
      hookService,
      participantManagementService,
      gameLifecycleService,
      roundManagementService,
      answerSubmissionService,
      io
    );
    this._testHookService = hookService; // Store for testing access
    // No need to manually replace gameStorage, jest.mock should handle it.
    // Performance monitor might still need careful handling if it interacts with timers.
    // If issues persist, performanceMonitor and resourcePoolManager should also be mocked at module level.
  }

  /**
   * Test-only method to get detailed resource state for verification
   * Adjusted for a mocked environment where some stats may not be relevant.
   */
  getTestResourceState(): {
    activeGames: number;
    memoryUsageMB: number;
    timerPools: number;
    batchProcessors: number;
  } {
    const storageStats = (this as any).gameStorage.getStats ? (this as any).gameStorage.getStats() : { totalGames: 0, activeGames: 0 };
    return {
      activeGames: storageStats.activeGames,
      memoryUsageMB: 0, // Mock environment doesn't track real memory
      timerPools: 0,    // Mock environment doesn't use real timer pools
      batchProcessors: 0 // Mock environment doesn't use real batch processors
    };
  }

  // hasTimerBeenCleared and getActiveTimerCount might be less relevant or need adjustment
  // if ResourcePoolManager (which manages timers) is not fully mocked or controlled.
  // For now, assuming they might still be called by tests and rely on getScalingStats which calls gameStorage.getStats.

  hasTimerBeenCleared(gameCode: string): boolean {
    // This implementation might be misleading if resourcePool is not mocked.
    // In a fully mocked setup, this should likely always return true or be removed.
    const scalingStats = this.getScalingStats();
    if (scalingStats && scalingStats.resources && scalingStats.resources.timerPools) {
        return scalingStats.resources.timerPools.totalGamesServed === 0;
    }
    return true; // Default to true in a heavily mocked scenario
  }

  getActiveTimerCount(): number {
    // Similar to hasTimerBeenCleared, depends on mocking of resourcePool.
    const scalingStats = this.getScalingStats();
    if (scalingStats && scalingStats.resources && scalingStats.resources.timerPools) {
        return scalingStats.resources.timerPools.totalGamesServed;
    }
    return 0; // Default to 0
  }

  /**
   * Public method to access hookService for testing purposes
   */
  getHookService() {
    return this._testHookService;
  }

  /**
   * Test-only: expose the internal gameStorage for direct manipulation in integration tests.
   */
  public getGameStorage(): any {
    // @ts-ignore: Accessing private property for test purposes only
    return (this as any).gameStorage;
  }

  /**
   * Test cleanup: properly destroy the manager to prevent hanging intervals
   */
  public cleanup(): void {
    this.destroy(); // Call the parent destroy method to clean up intervals
    this.DEBUG_resetGames(); // Clean up all game data
  }
} 