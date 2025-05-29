/**
 * Shared Test Setup Utilities
 * Consolidates repetitive beforeEach/afterEach patterns across all test files
 */

import { jest } from '@jest/globals';

/**
 * Standard mock setup for backend service tests
 */
export function setupBackendServiceMocks() {
  beforeEach(() => {
    jest.clearAllMocks();
    jest.useFakeTimers();
    
    // Standard environment variables for testing
    process.env.YOUTUBE_FETCH_METHOD = 'SCRAPE';
    process.env.SPOTIFY_CLIENT_ID = 'test-spotify-client-id';
    process.env.SPOTIFY_CLIENT_SECRET = 'test-spotify-client-secret';
    process.env.YOUTUBE_API_KEY = 'test-youtube-api-key';
  });

  afterEach(() => {
    jest.useRealTimers();
    jest.resetAllMocks();
  });
}

/**
 * Standard mock setup for GameSessionManager tests
 */
export function setupGameSessionManagerMocks() {
  beforeEach(() => {
    jest.clearAllMocks();
    jest.useFakeTimers();
    
    // Clear game storage if available
    const GsmMock = require('../services/__mocks__/gameStorageManager').GameStorageManager;
    if (GsmMock && typeof GsmMock.clearAllGames === 'function') {
      GsmMock.clearAllGames();
    }
  });

  afterEach(() => {
    jest.useRealTimers();
    
    // Cleanup game storage
    const GsmMock = require('../services/__mocks__/gameStorageManager').GameStorageManager;
    if (GsmMock && typeof GsmMock.clearAllGames === 'function') {
      GsmMock.clearAllGames();
    }
  });
}

/**
 * Standard mock setup for route tests
 */
export function setupRouteMocks() {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  afterEach(() => {
    jest.resetAllMocks();
  });
}

/**
 * Creates a mock fetch for frontend tests
 */
export function setupFetchMock(): jest.MockedFunction<typeof fetch> {
  const mockFetch = jest.fn() as jest.MockedFunction<typeof fetch>;
  global.fetch = mockFetch;
  return mockFetch;
}

/**
 * Standard cleanup for fetch mocks
 */
export function cleanupFetchMock() {
  if (global.fetch && jest.isMockFunction(global.fetch)) {
    (global.fetch as jest.MockedFunction<typeof fetch>).mockRestore();
  }
}

/**
 * Factory for creating standard mock responses
 */
export const mockResponseFactory = {
  success: (data: any) => ({
    ok: true,
    status: 200,
    json: jest.fn(() => Promise.resolve(data)),
  }),
  
  error: (status = 500, message = 'Server Error') => ({
    ok: false,
    status,
    statusText: message,
    json: jest.fn(() => Promise.resolve({ error: message })),
  }),
  
  networkError: () => Promise.reject(new Error('Network Error')),
};

/**
 * Common test data factory
 */
export const testDataFactory = {
  gameSettings: (overrides = {}) => ({
    prompt: 'Test Prompt',
    numberOfRounds: 3,
    llmModel: 'test-model',
    ...overrides,
  }),
  
  song: (overrides = {}) => ({
    title: 'Test Song',
    artist: 'Test Artist',
    trackUrl: 'https://example.com/track',
    used: false,
    ...overrides,
  }),
  
  participant: (overrides = {}) => ({
    id: 'test-player',
    name: 'Test Player',
    score: 0,
    role: 'participant' as const,
    ...overrides,
  }),
}; 