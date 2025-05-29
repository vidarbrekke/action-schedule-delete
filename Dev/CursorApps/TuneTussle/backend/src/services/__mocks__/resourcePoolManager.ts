/**
 * Mock of ResourcePoolManager for tests.
 */
import { TimerService } from './timerService'; // Corrected path to local mock

export class ResourcePoolManager {
  // Ensure this instance is of the MOCKED TimerService
  private static timerServiceInstance = new TimerService(); 

  private static mockInstance = {
    getTimerService: jest.fn((gameCode?: string) => ResourcePoolManager.timerServiceInstance),
    releaseTimerService: jest.fn(),
    getResourceStats: jest.fn(() => ({
      timerPools: { totalGamesServed: 0, activeTimers: 0 },
      batchProcessors: { activeProcessors: 0, pendingTasks: 0 },
    })),
    getGlobalResourceLevels: jest.fn(() => ({ low: false, critical: false })),
    destroy: jest.fn(),
  };

  static getInstance(config?: any) {
    // Config is ignored by mock, but real one takes it
    return ResourcePoolManager.mockInstance;
  }

  constructor(config?: any){
    // Mock constructor, no-op as instance is static
  }

  // These are instance methods on the real class, but our mock uses a static mockInstance.
  // To match potential calls if tests somehow got a real ResourcePoolManager that then calls these internal methods.
  getTimerService(gameCode: string) { 
    return ResourcePoolManager.mockInstance.getTimerService(gameCode);
  }

  releaseTimerService(gameCode: string): void {
    ResourcePoolManager.mockInstance.releaseTimerService(gameCode);
  }

  destroy(): void {
    ResourcePoolManager.mockInstance.destroy();
  }

  // ... any other methods if needed ...
} 