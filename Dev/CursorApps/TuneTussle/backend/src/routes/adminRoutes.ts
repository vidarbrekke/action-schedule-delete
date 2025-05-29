import express from 'express';
import { getLlmConfig, updateLlmConfig, LlmConfig } from '../llmConfigManager';

const router = express.Router();

/**
 * GET /api/admin/llm-config - Get LLM configuration
 * Returns the current LLM API configuration
 */
router.get('/llm-config', (req: express.Request, res: express.Response) => {
  try {
    const config = getLlmConfig(false); // Do not require API key
    // Normalize all fields to always be present and null if unset/empty/undefined
    const normalizedConfig = {
      apiKey: config.apiKey === undefined || config.apiKey === '' ? null : config.apiKey,
      modelId: config.modelId === undefined || config.modelId === '' ? null : config.modelId,
      apiBaseUrl: config.apiBaseUrl === undefined || config.apiBaseUrl === '' ? null : config.apiBaseUrl,
      youtubeApiKey: config.youtubeApiKey === undefined || config.youtubeApiKey === '' ? null : config.youtubeApiKey,
    };
    res.json({ success: true, config: normalizedConfig });
  } catch (error) {
    res.status(500).json({ success: false, message: 'Failed to retrieve LLM config.' });
  }
});

/**
 * PUT /api/admin/llm-config - Update LLM configuration
 * Expects JSON with some or all of these fields:
 * - apiKey: string | null (API key for LLM provider)
 * - modelId: string | null (Model ID to use)
 * - apiBaseUrl: string | null (Base URL for API calls)
 */
router.put('/llm-config', (req: express.Request, res: express.Response) => {
  try {
    const { body } = req;
    const newConfig: Partial<LlmConfig> = {};
    let recognizedFieldEncountered = false;

    if (body.hasOwnProperty('apiKey')) {
      recognizedFieldEncountered = true;
      const apiKeyVal = body.apiKey;
      if (apiKeyVal === null || typeof apiKeyVal === 'string') {
        newConfig.apiKey = apiKeyVal === "" ? null : apiKeyVal;
      } else {
        res.status(400).json({ success: false, message: 'Invalid apiKey format. Must be a string or null.' });
        return;
      }
    }

    if (body.hasOwnProperty('modelId')) {
      recognizedFieldEncountered = true;
      const modelIdVal = body.modelId;
      if (modelIdVal === null || typeof modelIdVal === 'string') {
        newConfig.modelId = modelIdVal === "" ? null : modelIdVal;
      } else {
        res.status(400).json({ success: false, message: 'Invalid modelId format. Must be a string or null.' });
        return;
      }
    }
    
    if (body.hasOwnProperty('apiBaseUrl')) {
      recognizedFieldEncountered = true;
      const apiBaseUrlVal = body.apiBaseUrl;
      if (apiBaseUrlVal === null || typeof apiBaseUrlVal === 'string') {
        newConfig.apiBaseUrl = apiBaseUrlVal === "" ? null : apiBaseUrlVal;
      } else {
        res.status(400).json({ success: false, message: 'Invalid apiBaseUrl format. Must be a string or null.' });
        return;
      }
    }

    if (!recognizedFieldEncountered && Object.keys(body).length === 0) {
      // Handles empty request body: {}
      res.status(400).json({ success: false, message: 'Request body must contain apiKey, modelId, and/or apiBaseUrl.' });
      return;
    } else if (!recognizedFieldEncountered && Object.keys(body).length > 0) {
      // Handles request body with only unrecognized fields: {"unknown": "value"}
      res.status(400).json({
        success: false,
        message: 'No recognized configuration parameters provided. Accepted parameters are: apiKey, modelId, apiBaseUrl.'
      });
      return;
    }

    const updatedConfig = updateLlmConfig(newConfig);
    // Normalize all fields to always be present and null if unset/empty/undefined
    const normalizedConfig = {
      apiKey: updatedConfig.apiKey === undefined || updatedConfig.apiKey === '' ? null : updatedConfig.apiKey,
      modelId: updatedConfig.modelId === undefined || updatedConfig.modelId === '' ? null : updatedConfig.modelId,
      apiBaseUrl: updatedConfig.apiBaseUrl === undefined || updatedConfig.apiBaseUrl === '' ? null : updatedConfig.apiBaseUrl,
      youtubeApiKey: updatedConfig.youtubeApiKey === undefined || updatedConfig.youtubeApiKey === '' ? null : updatedConfig.youtubeApiKey,
    };
    res.json({ success: true, message: 'LLM configuration updated successfully.', config: normalizedConfig });
  } catch (error) {
    // General error catch, though llmConfigManager is synchronous and unlikely to throw here.
    res.status(500).json({ success: false, message: 'Failed to update LLM config due to an unexpected error.' });
  }
});

export default router; 