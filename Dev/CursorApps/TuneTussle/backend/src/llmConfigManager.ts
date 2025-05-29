// llmConfigManager.ts – Robust LLM configuration manager

import env from './utils/envConfig';

// Load environment variables from .env file
// dotenv.config(); // This call is redundant as it's done in src/index.ts (application entry point)

export interface LlmConfig {
  apiKey: string | null;
  modelId: string | null;
  apiBaseUrl?: string | null;
  youtubeApiKey?: string | null;
}

class LlmConfigManager {
  private overrides: Partial<LlmConfig> = {};

  /** Read defaults from environment variables */
  private readEnvConfig(): Partial<LlmConfig> {
    return {
      apiKey: env.OPENROUTER_API_KEY ?? '',
      modelId: env.OPENROUTER_MODEL_ID ?? '',
      apiBaseUrl: env.OPENROUTER_API_BASE_URL ?? '',
      youtubeApiKey: env.YOUTUBE_API_KEY,
    };
  }

  /**
   * Get the effective LLM configuration.
   * @param requireApiKey - If true, throws when apiKey is missing.
   */
  public getConfig(requireApiKey = true): LlmConfig {
    const envConfig = this.readEnvConfig();
    // Use hasOwnProperty to allow explicit null overrides
    const config: LlmConfig = {
      apiKey: this.overrides.hasOwnProperty('apiKey') ? (this.overrides.apiKey === undefined ? '' : this.overrides.apiKey) : (envConfig.apiKey ?? ''),
      modelId: this.overrides.hasOwnProperty('modelId') ? (this.overrides.modelId === undefined ? '' : this.overrides.modelId) : (envConfig.modelId ?? ''),
      apiBaseUrl: this.overrides.hasOwnProperty('apiBaseUrl') ? this.overrides.apiBaseUrl : (envConfig.apiBaseUrl ?? ''),
      youtubeApiKey: this.overrides.hasOwnProperty('youtubeApiKey') ? this.overrides.youtubeApiKey : envConfig.youtubeApiKey,
    };
    // Normalize unset/empty/undefined/empty string to '' (except youtubeApiKey)
    config.apiKey = config.apiKey === undefined ? '' : config.apiKey;
    config.modelId = config.modelId === undefined ? '' : config.modelId;
    config.apiBaseUrl = config.apiBaseUrl === undefined || config.apiBaseUrl === '' ? undefined : (config.apiBaseUrl === null ? null : config.apiBaseUrl);
    config.youtubeApiKey = config.youtubeApiKey === undefined ? undefined : (config.youtubeApiKey === null ? null : config.youtubeApiKey);
    if (requireApiKey && !config.apiKey) {
      throw new Error('LLM API key is required but not set.');
    }
    return config;
  }

  /**
   * Update in-memory configuration overrides.
   * @param updates - Partial new configuration values.
   */
  public updateConfig(updates: Partial<LlmConfig>): LlmConfig {
    // Only update properties that are not undefined
    const filteredUpdates: Partial<LlmConfig> = {};
    for (const key in updates) {
      const value = updates[key as keyof LlmConfig];
      if (value === undefined) continue;
      // If null, store and return null
      if (value === null) {
        filteredUpdates[key as keyof LlmConfig] = null;
        continue;
      }
      // If empty string, treat as null for API contract
      if (value === '') {
        filteredUpdates[key as keyof LlmConfig] = null;
        continue;
      }
      // Whitespace-only string is invalid for apiKey/modelId/apiBaseUrl
      if ((key === 'apiKey' || key === 'modelId' || key === 'apiBaseUrl') && typeof value === 'string' && value.trim() === '') {
        if (key === 'apiKey') {
          throw new Error('Invalid API key provided. Cannot be empty or whitespace.');
        } else if (key === 'modelId') {
          throw new Error('Invalid model ID provided. Cannot be empty or whitespace.');
        } else {
          throw new Error(`Invalid ${key} provided. Cannot be empty or whitespace.`);
        }
      }
      filteredUpdates[key as keyof LlmConfig] = value;
    }
    this.overrides = { ...this.overrides, ...filteredUpdates };
    // Do not require API key when just updating, let getConfig handle that if called externally.
    return this.getConfig(false);
  }

  /** Reset in-memory overrides to environment defaults */
  public resetConfig(): LlmConfig {
    this.overrides = {};
    // Do not require API key when just resetting, let getConfig handle that.
    return this.getConfig(false);
  }
}

/** Singleton instance for application-wide use */
const manager = new LlmConfigManager();

/** Backward-compatible module-level exports */
export const getLlmConfig = (requireApiKey: boolean = true): LlmConfig => manager.getConfig(requireApiKey); // Allow requireApiKey override
export const updateLlmConfig = (newConfig: Partial<LlmConfig>): LlmConfig =>
  manager.updateConfig(newConfig);
export const resetLlmConfig = (): LlmConfig => manager.resetConfig(); 