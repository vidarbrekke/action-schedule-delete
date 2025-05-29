// Remove dotenv mock as we will mock envConfig directly
// import dotenv from 'dotenv';
// jest.mock('dotenv');
// const mockDotenvConfig = dotenv.config as jest.Mock;
// mockDotenvConfig.mockReturnValue({ parsed: {} });

// Default state for our envConfig mock
let currentMockEnvState = {
  OPENROUTER_API_KEY: undefined as string | undefined,
  OPENROUTER_MODEL_ID: undefined as string | undefined,
  OPENROUTER_API_BASE_URL: undefined as string | undefined,
  YOUTUBE_API_KEY: undefined as string | undefined,
  NODE_ENV: 'test' as string | undefined,
  PORT: '4000' as string | undefined,
  // Add any other properties that envConfig.ts normally exports in its default object
};

// Mock the entire envConfig module
jest.mock('../utils/envConfig', () => ({
  __esModule: true,
  default: new Proxy(currentMockEnvState, { // Use a Proxy to allow tests to modify currentMockEnvState
    get: (target, prop) => target[prop as keyof typeof currentMockEnvState],
    set: (target, prop, value) => {
      (target as any)[prop as keyof typeof currentMockEnvState] = value;
      return true;
    }
  }),
  validateRequiredEnvVars: jest.fn(), // Mock the validator function as well
}));

import {
  getLlmConfig,
  updateLlmConfig,
  resetLlmConfig,
  LlmConfig,
} from '../llmConfigManager';
import { resetAllMocksAndModules } from '../../testUtils/resetTestEnv';

// Store original process.env - this might become less relevant if all config comes from mocked envConfig
const originalEnv = { ...process.env };

describe('LlmConfigManager', () => {
  beforeEach(() => {
    // resetAllMocksAndModules(); // May not be needed if we control envConfig mock state directly
    
    // Reset the state of our mock envConfig for each test
    currentMockEnvState.OPENROUTER_API_KEY = undefined;
    currentMockEnvState.OPENROUTER_MODEL_ID = undefined;
    currentMockEnvState.OPENROUTER_API_BASE_URL = undefined;
    currentMockEnvState.YOUTUBE_API_KEY = undefined;
    currentMockEnvState.NODE_ENV = 'test';
    currentMockEnvState.PORT = '4000';

    // Clear process.env variables that might interfere or be read by llmConfigManager if it has fallbacks
    // (though current llmConfigManager primarily reads from the imported env object)
    delete process.env.OPENROUTER_API_KEY;
    delete process.env.OPENROUTER_MODEL_ID;
    delete process.env.OPENROUTER_API_BASE_URL;
    delete process.env.YOUTUBE_API_KEY;

    // This is crucial: it resets the LlmConfigManager's internal overrides
    // and forces it to re-evaluate its config from our (now reset) mocked envConfig.
    resetLlmConfig();
  });

  // afterAll(() => {
  //   process.env = originalEnv; // Restore original process.env
  // });

  // afterEach(() => {
  //   resetAllMocksAndModules(); // May not be needed
  // });

  describe('getLlmConfig', () => {
    it('should throw an error if API key is not set (default behavior)', () => {
      // env and overrides are empty
      expect(() => getLlmConfig()).toThrow('LLM API key is required but not set.');
    });

    it('should return empty strings for apiKey and modelId if not set and not required', () => {
      // Need to access manager directly to call getConfig(false)
      // This requires either exposing manager or adding a test-specific export from llmConfigManager.ts
      // For now, we'll test the public API. updateLlmConfig calls getConfig(false) internally.
      const config = updateLlmConfig({}); // updates and then gets config(false)
      expect(config.apiKey).toBe('');
      expect(config.modelId).toBe('');
      expect(config.apiBaseUrl).toBeUndefined();
      expect(config.youtubeApiKey).toBeUndefined();
    });

    it('should load API key from mocked envConfig', () => {
      currentMockEnvState.OPENROUTER_API_KEY = 'env-api-key';
      const config = getLlmConfig();
      expect(config.apiKey).toBe('env-api-key');
      expect(config.modelId).toBe(''); 
    });

    it('should load model ID from mocked envConfig', () => {
      currentMockEnvState.OPENROUTER_MODEL_ID = 'env-model-id';
      // Still need API key to not throw
      currentMockEnvState.OPENROUTER_API_KEY = 'dummy-key'; 
      const config = getLlmConfig();
      expect(config.modelId).toBe('env-model-id');
      expect(config.apiKey).toBe('dummy-key');
    });

    it('should load API base URL from mocked envConfig', () => {
      currentMockEnvState.OPENROUTER_API_BASE_URL = 'https://env.api.com/v1';
      currentMockEnvState.OPENROUTER_API_KEY = 'dummy-key';
      const config = getLlmConfig();
      expect(config.apiBaseUrl).toBe('https://env.api.com/v1');
    });

    it('should load YouTube API key from mocked envConfig', () => {
      currentMockEnvState.YOUTUBE_API_KEY = 'env-youtube-key';
      currentMockEnvState.OPENROUTER_API_KEY = 'dummy-key';
      const config = getLlmConfig();
      expect(config.youtubeApiKey).toBe('env-youtube-key');
    });

    it('should prioritize overrides over envConfig', () => {
      currentMockEnvState.OPENROUTER_API_KEY = 'env-api-key';
      currentMockEnvState.OPENROUTER_MODEL_ID = 'env-model-id';
      updateLlmConfig({ apiKey: 'override-api-key', modelId: 'override-model-id' });
      const config = getLlmConfig();
      expect(config.apiKey).toBe('override-api-key');
      expect(config.modelId).toBe('override-model-id');
    });

    it('override for one value does not affect other env values', () => {
      currentMockEnvState.OPENROUTER_API_KEY = 'env-api-key';
      currentMockEnvState.OPENROUTER_MODEL_ID = 'env-model-id';
      currentMockEnvState.OPENROUTER_API_BASE_URL = 'https://env.url.com';
      
      updateLlmConfig({ apiKey: 'override-api-key' });
      let config = getLlmConfig(false);

      expect(config.apiKey).toBe('override-api-key');
      expect(config.modelId).toBe('env-model-id');
      expect(config.apiBaseUrl).toBe('https://env.url.com');
    });
  });

  describe('updateLlmConfig', () => {
    it('should update apiKey and modelId in memory', () => {
      updateLlmConfig({ apiKey: 'updated-key', modelId: 'updated-model' });
      let config = getLlmConfig(false);
      expect(config.apiKey).toBe('updated-key');
      expect(config.modelId).toBe('updated-model');
    });

    it('should return the updated config, not requiring API key for the return', () => {
      const updatedConfig = updateLlmConfig({ modelId: 'specific-model' });
      // apiKey will be '' because env is not set, and it was not part of this update
      expect(updatedConfig.apiKey).toBe(''); 
      expect(updatedConfig.modelId).toBe('specific-model');
    });

    it('should throw error for empty string apiKey update', () => {
      expect(() => updateLlmConfig({ apiKey: ' ' })).toThrow('Invalid API key provided. Cannot be empty or whitespace.');
    });

    it('should throw error for empty string modelId update', () => {
      expect(() => updateLlmConfig({ modelId: ' ' })).toThrow('Invalid model ID provided. Cannot be empty or whitespace.');
    });

    it('does not clear override when updating with undefined (should retain previous value)', () => {
      // Setup with valid values first
      updateLlmConfig({ apiKey: 'key1', modelId: 'model1' });
      let config = getLlmConfig(false);
      expect(config.apiKey).toBe('key1');
      expect(config.modelId).toBe('model1');

      // Attempt to update with undefined (should not clear the override)
      updateLlmConfig({ apiKey: undefined, modelId: undefined });
      // Set env API key so getLlmConfig() does not throw
      currentMockEnvState.OPENROUTER_API_KEY = 'dummy-key';
      config = getLlmConfig();
      expect(config.apiKey).toBe('key1'); // Retains previous override
      expect(config.modelId).toBe('model1'); // Retains previous override
    });
  });

  describe('resetLlmConfig', () => {
    it('should reset overrides, falling back to envConfig values', () => {
      currentMockEnvState.OPENROUTER_API_KEY = 'env-key-for-reset';
      currentMockEnvState.OPENROUTER_MODEL_ID = 'env-model-for-reset';
      
      // Set some overrides
      updateLlmConfig({ apiKey: 'override-key', modelId: 'override-model' });
      let config = getLlmConfig(false); // Will use overrides
      expect(config.apiKey).toBe('override-key');
      expect(config.modelId).toBe('override-model');

      resetLlmConfig(); // This calls getConfig(false) internally
      config = getLlmConfig(false); // Now get with requireApiKey = true
      
      expect(config.apiKey).toBe('env-key-for-reset');
      expect(config.modelId).toBe('env-model-for-reset');
    });

    it('should result in empty strings if envConfig is also empty after reset', () => {
      updateLlmConfig({ apiKey: 'override-key', modelId: 'override-model' });
      resetLlmConfig(); // Resets overrides, env is empty
      // getLlmConfig() would throw here if apiKey is required and empty
      // So, let's check what resetLlmConfig() itself returns (which is getConfig(false))
      const configAfterReset = resetLlmConfig(); 
      expect(configAfterReset.apiKey).toBe('');
      expect(configAfterReset.modelId).toBe('');
    });
  });

  describe('Configuration Precedence: Override > Env > Default (empty string)', () => {
    it('Override takes precedence over Env', () => {
      currentMockEnvState.OPENROUTER_API_KEY = 'env-key';
      updateLlmConfig({ apiKey: 'override-key' });
      expect(getLlmConfig().apiKey).toBe('override-key');
    });

    it('Env takes precedence over Default if no override', () => {
      currentMockEnvState.OPENROUTER_API_KEY = 'env-key';
      // No override set for apiKey, resetLlmConfig clears any existing ones
      resetLlmConfig(); 
      expect(getLlmConfig().apiKey).toBe('env-key');
    });

    it("Default (empty string) is used if no override and no Env (will throw if key required)", () => {
      // Env is empty (from beforeEach), no overrides
      resetLlmConfig();
      // Test getConfig(false) via updateLlmConfig({}) return value
      const config = updateLlmConfig({});
      expect(config.apiKey).toBe('');
      expect(() => getLlmConfig()).toThrow('LLM API key is required but not set.');
    });

    it('youtubeApiKey: Override > Env > Undefined', () => {
      expect(updateLlmConfig({}).youtubeApiKey).toBeUndefined(); // Default
      currentMockEnvState.YOUTUBE_API_KEY = 'env-youtube';
      expect(resetLlmConfig().youtubeApiKey).toBe('env-youtube'); // Env
      updateLlmConfig({ youtubeApiKey: 'override-youtube' });
      // For getLlmConfig to not throw, API key needs to be present from override or env
      currentMockEnvState.OPENROUTER_API_KEY = 'dummy-key'; 
      expect(getLlmConfig().youtubeApiKey).toBe('override-youtube'); // Override
    });
  });
});
