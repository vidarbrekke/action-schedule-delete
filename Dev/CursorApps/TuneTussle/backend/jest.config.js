/** @type {import('ts-jest').JestConfigWithTsJest} **/
module.exports = {
  preset: 'ts-jest',
  testEnvironment: 'node',
  testMatch: [
    '**/__tests__/**/*.test.ts'
  ],
  setupFilesAfterEnv: ['<rootDir>/jest.setup.js'],
  forceExit: true,
  detectOpenHandles: true,
  testTimeout: 10000,
  coverageDirectory: 'coverage',
  collectCoverageFrom: [
    'src/**/*.ts',
    '!src/**/*.test.ts',
    '!src/testUtils/**',
    '!src/__tests__/**'
  ],
  // Enhanced test isolation and performance settings
  maxWorkers: 1, // Run tests serially to prevent resource conflicts
  // Clear mocks between tests for better isolation but keep modules
  clearMocks: true,
  resetMocks: false, // Keep our global mocks
  restoreMocks: false, // Don't automatically restore mocks
};