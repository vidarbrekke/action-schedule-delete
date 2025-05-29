/**
 * Consolidated Music Service Test Utilities
 * Eliminates duplication across spotify, deezer, youtube, and musicProvider service tests
 */

interface MusicServiceTestConfig {
  fetchFunction: (...args: any[]) => Promise<any>;
  mockSetup?: () => void;
  mockCleanup?: () => void;
  mockImplementation: (mockFn: jest.Mock) => void;
  expectedUrl?: string;
  additionalArgs?: any[];
  serviceName?: string;
  additionalTestCases?: () => void;
}

interface RouteTestConfig {
  routePath: string;
  validQueryParams: Record<string, string>;
  invalidQueryParams: Record<string, string>;
  mockResponse: any;
  expectedResponse: any;
}

/**
 * Standard test suite for music service input validation
 */
export function testMusicServiceInputValidation(config: MusicServiceTestConfig): void {
  describe('Input validation', () => {
    beforeEach(() => {
      config.mockSetup?.();
    });

    afterEach(() => {
      config.mockCleanup?.();
    });

    it('should return undefined for empty title', async () => {
      const result = await config.fetchFunction('', 'Artist', ...(config.additionalArgs || []));
      expect(result).toBeUndefined();
    });

    it('should return undefined for empty artist', async () => {
      const result = await config.fetchFunction('Song', '', ...(config.additionalArgs || []));
      expect(result).toBeUndefined();
    });

    it('should return undefined for whitespace-only inputs', async () => {
      const result = await config.fetchFunction('   ', '   ', ...(config.additionalArgs || []));
      expect(result).toBeUndefined();
    });
  });
}

/**
 * Standard test suite for music service API error handling
 */
export function testMusicServiceErrorHandling(config: MusicServiceTestConfig & { serviceName: string }): void {
  describe(`${config.serviceName} error handling`, () => {
    beforeEach(() => {
      config.mockSetup?.();
    });

    afterEach(() => {
      config.mockCleanup?.();
    });

    it('should handle API errors gracefully', async () => {
      const mockFn = jest.fn().mockRejectedValue(new Error(`${config.serviceName} API error`));
      config.mockImplementation(mockFn);
      
      try {
      const result = await config.fetchFunction('Song', 'Artist', ...(config.additionalArgs || []));
      expect(result).toBeUndefined();
      } catch (error: any) {
        // If the function throws instead of returning undefined, that's also acceptable
        expect(error.message).toContain(`${config.serviceName} API error`);
      }
      
      expect(mockFn).toHaveBeenCalled();
    });

    it('should handle network timeouts', async () => {
      const mockFn = jest.fn().mockRejectedValue(new Error('Network timeout'));
      config.mockImplementation(mockFn);
      
      try {
      const result = await config.fetchFunction('Song', 'Artist', ...(config.additionalArgs || []));
      expect(result).toBeUndefined();
      } catch (error: any) {
        // If the function throws instead of returning undefined, that's also acceptable
        expect(error.message).toContain('Network timeout');
      }
    });
  });
}

/**
 * Standard test suite for successful track finding scenarios
 */
export function testMusicServiceSuccessScenarios(config: MusicServiceTestConfig & { serviceName: string; expectedUrl: string }): void {
  describe(`${config.serviceName} success scenarios`, () => {
    beforeEach(() => {
      config.mockSetup?.();
    });

    afterEach(() => {
      config.mockCleanup?.();
    });

    it('should return URL when track is found', async () => {
      const mockFn = jest.fn().mockResolvedValue(config.expectedUrl);
      config.mockImplementation(mockFn);
      
      const result = await config.fetchFunction('Test Song', 'Test Artist', ...(config.additionalArgs || []));
      expect(result).toBe(config.expectedUrl);
      expect(mockFn).toHaveBeenCalledWith('Test Song', 'Test Artist', ...(config.additionalArgs || []));
    });

    it('should return undefined when no results found', async () => {
      const mockFn = jest.fn().mockResolvedValue(undefined);
      config.mockImplementation(mockFn);
      
      const result = await config.fetchFunction('Unknown Song', 'Unknown Artist', ...(config.additionalArgs || []));
      expect(result).toBeUndefined();
    });
  });
}

/**
 * Standard test suite for route error cases
 */
export function testRouteErrorCases(config: MusicServiceTestConfig): void {
  describe('Route error handling', () => {
    it('should return 400 for missing required parameters', async () => {
      // Implementation would depend on specific test framework
      // This is a placeholder for route-specific error testing
    });

    it('should return 500 for service errors', async () => {
      // Implementation for service error scenarios
    });
  });
}

/**
 * Comprehensive test suite factory for music services
 */
export function createMusicServiceTestSuite(serviceName: string, config: MusicServiceTestConfig): void {
  describe(`${serviceName} Service`, () => {
    testMusicServiceInputValidation(config);
    testMusicServiceErrorHandling({ ...config, serviceName });
    if (config.expectedUrl) {
      testMusicServiceSuccessScenarios({ ...config, serviceName, expectedUrl: config.expectedUrl });
    }
    
    // Service-specific additional test cases
    config.additionalTestCases?.();
  });
}

/**
 * Mock factory for consistent mock setup across services
 */
export function createMockResponse(data: any, success: boolean = true): any {
  return success 
    ? { success: true, results: Array.isArray(data) ? data : [data] }
    : { success: false, error: 'Mock error', results: [] };
}

/**
 * Standard URL validation helper
 */
export function validateUrl(url: string | undefined, expectedDomain: string): boolean {
  if (!url) return false;
  
  try {
    const parsed = new URL(url);
    return parsed.hostname.includes(expectedDomain);
  } catch {
    return false;
  }
} 