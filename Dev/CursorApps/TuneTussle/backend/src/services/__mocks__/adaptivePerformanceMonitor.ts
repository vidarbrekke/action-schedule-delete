/**
 * Mock of AdaptivePerformanceMonitor for tests.
 */
export class AdaptivePerformanceMonitor {
  private static mockInstance: AdaptivePerformanceMonitor | null = null;

  static getInstance(config?: any, thresholds?: any) {
    if (!AdaptivePerformanceMonitor.mockInstance) {
      AdaptivePerformanceMonitor.mockInstance = new AdaptivePerformanceMonitor();
    }
    return AdaptivePerformanceMonitor.mockInstance;
  }

  constructor(config?: any, thresholds?: any) {
    // For the mock, we typically don't need to do anything with config/thresholds here.
  }
  
  // Ensure all methods called by the application are mocked
  trackEvent = jest.fn();
  setManagers = jest.fn();
  shutdown = jest.fn();
  getPerformanceStats = jest.fn(() => ({
    cpuUsage: 0,
    memoryUsageMB: 0,
    averageResponseTimeMs: 0,
    activeGameSessions: 0,
    eventsProcessed: 0
  }));
} 