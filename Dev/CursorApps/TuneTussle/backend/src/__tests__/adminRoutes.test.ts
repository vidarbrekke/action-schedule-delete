import request from 'supertest';
import express from 'express';
import adminRoutes from '../routes/adminRoutes';
import { resetLlmConfig, updateLlmConfig } from '../llmConfigManager'; // To reset state between tests

process.env.OPENROUTER_API_KEY = '';
process.env.OPENROUTER_MODEL_ID = '';
process.env.OPENROUTER_API_BASE_URL = '';
process.env.YOUTUBE_API_KEY = '';

const app = express();
app.use(express.json());
app.use('/api/admin', adminRoutes);

beforeAll(() => {
  updateLlmConfig({
    apiKey: null,
    modelId: null,
    apiBaseUrl: null,
    youtubeApiKey: null,
  });
});

describe('Admin LLM Config Routes', () => {
  beforeEach(() => {
    // Reset LLM config state before each test
    updateLlmConfig({
      apiKey: null,
      modelId: null,
      apiBaseUrl: null,
      youtubeApiKey: null,
    });
  });

  describe('GET /api/admin/llm-config', () => {
    it('should return the initial LLM config (all nulls)', async () => {
      const res = await request(app).get('/api/admin/llm-config');
      expect(res.status).toBe(200);
      expect(res.body.success).toBe(true);
      expect(res.body.config).toEqual({ apiKey: null, modelId: null, apiBaseUrl: null, youtubeApiKey: null });
    });
  });

  describe('PUT /api/admin/llm-config', () => {
    it('should update the LLM config with valid data', async () => {
      const newConfig = { apiKey: 'test-key', modelId: 'test-model', apiBaseUrl: 'https://new.api.com' };
      const expectedConfigInResponse = { ...newConfig, youtubeApiKey: null }; 

      const res = await request(app)
        .put('/api/admin/llm-config')
        .send(newConfig);
      
      expect(res.status).toBe(200);
      expect(res.body.success).toBe(true);
      expect(res.body.message).toBe('LLM configuration updated successfully.');
      expect(res.body.config).toEqual(expectedConfigInResponse);

      const getRes = await request(app).get('/api/admin/llm-config');
      expect(getRes.body.config).toEqual(expectedConfigInResponse);
    });

    it('should partially update the LLM config (only apiKey)', async () => {
      const partialConfig = { apiKey: 'only-api-key' };
      const res = await request(app)
        .put('/api/admin/llm-config')
        .send(partialConfig);
      expect(res.status).toBe(200);
      expect(res.body.config).toEqual({ apiKey: 'only-api-key', modelId: null, apiBaseUrl: null, youtubeApiKey: null }); 
    });

    it('should partially update the LLM config (only modelId)', async () => {
      const partialConfig = { modelId: 'only-model-id' };
      const res = await request(app)
        .put('/api/admin/llm-config')
        .send(partialConfig);
      expect(res.status).toBe(200);
      expect(res.body.config).toEqual({ apiKey: null, modelId: 'only-model-id', apiBaseUrl: null, youtubeApiKey: null });
    });
    
    it('should partially update the LLM config (only apiBaseUrl)', async () => {
      const partialConfig = { apiBaseUrl: 'https://partial.url.com' };
      const res = await request(app)
        .put('/api/admin/llm-config')
        .send(partialConfig);
      expect(res.status).toBe(200);
      expect(res.body.config).toEqual({ apiKey: null, modelId: null, apiBaseUrl: 'https://partial.url.com', youtubeApiKey: null });
    });

    it('should treat empty string as null for apiKey', async () => {
      await request(app).put('/api/admin/llm-config').send({ apiKey: 'somekey', modelId: 'id', apiBaseUrl: 'url' });
      const res = await request(app)
        .put('/api/admin/llm-config')
        .send({ apiKey: '' });
      expect(res.status).toBe(200);
      expect(res.body.config).toEqual({ apiKey: null, modelId: 'id', apiBaseUrl: 'url', youtubeApiKey: null });
    });

    it('should treat empty string as null for modelId', async () => {
      await request(app).put('/api/admin/llm-config').send({ apiKey: 'key', modelId: 'somemodel', apiBaseUrl: 'url' });
      const res = await request(app)
        .put('/api/admin/llm-config')
        .send({ modelId: '' });
      expect(res.status).toBe(200);
      expect(res.body.config).toEqual({ apiKey: 'key', modelId: null, apiBaseUrl: 'url', youtubeApiKey: null });
    });
    
    it('should treat empty string as null for apiBaseUrl', async () => {
      await request(app).put('/api/admin/llm-config').send({ apiKey: 'key', modelId: 'id', apiBaseUrl: 'https://some.url/v1' });
      const res = await request(app)
        .put('/api/admin/llm-config')
        .send({ apiBaseUrl: '' });
      expect(res.status).toBe(200);
      expect(res.body.config).toEqual({ apiKey: 'key', modelId: 'id', apiBaseUrl: null, youtubeApiKey: null });
    });

    it('should accept explicit null for apiKey', async () => {
      const res = await request(app)
        .put('/api/admin/llm-config')
        .send({ apiKey: null, modelId: 'test-model', apiBaseUrl: 'https://url.com' });
      expect(res.status).toBe(200);
      expect(res.body.config).toEqual({ apiKey: null, modelId: 'test-model', apiBaseUrl: 'https://url.com', youtubeApiKey: null });
    });

    it('should accept explicit null for modelId', async () => {
      const res = await request(app)
        .put('/api/admin/llm-config')
        .send({ apiKey: 'test-key', modelId: null, apiBaseUrl: 'https://url.com' });
      expect(res.status).toBe(200);
      expect(res.body.config).toEqual({ apiKey: 'test-key', modelId: null, apiBaseUrl: 'https://url.com', youtubeApiKey: null });
    });

    it('should accept explicit null for apiBaseUrl', async () => {
      const res = await request(app)
        .put('/api/admin/llm-config')
        .send({ apiKey: 'test-key', modelId: 'test-model', apiBaseUrl: null });
      expect(res.status).toBe(200);
      expect(res.body.config).toEqual({ apiKey: 'test-key', modelId: 'test-model', apiBaseUrl: null, youtubeApiKey: null });
    });

    it('should return 400 if apiKey is not a string or null', async () => {
      const res = await request(app)
        .put('/api/admin/llm-config')
        .send({ apiKey: 123 });
      expect(res.status).toBe(400);
      expect(res.body.success).toBe(false);
      expect(res.body.message).toBe('Invalid apiKey format. Must be a string or null.');
    });

    it('should return 400 if modelId is not a string or null', async () => {
      const res = await request(app)
        .put('/api/admin/llm-config')
        .send({ modelId: true });
      expect(res.status).toBe(400);
      expect(res.body.success).toBe(false);
      expect(res.body.message).toBe('Invalid modelId format. Must be a string or null.');
    });

    it('should return 400 if apiBaseUrl is not a string or null', async () => {
      const res = await request(app)
        .put('/api/admin/llm-config')
        .send({ apiBaseUrl: 123 });
      expect(res.status).toBe(400);
      expect(res.body.success).toBe(false);
      expect(res.body.message).toBe('Invalid apiBaseUrl format. Must be a string or null.');
    });

    it('should return 400 if no valid parameters are provided', async () => {
      const res = await request(app)
        .put('/api/admin/llm-config')
        .send({}); // Empty body
      expect(res.status).toBe(400);
      expect(res.body.success).toBe(false);
      expect(res.body.message).toBe('Request body must contain apiKey, modelId, and/or apiBaseUrl.');
    });

    it('should return 400 if only invalid parameters are provided', async () => {
      const res = await request(app)
        .put('/api/admin/llm-config')
        .send({ someOtherParam: 'value' }); // Irrelevant param
      expect(res.status).toBe(400);
      expect(res.body.success).toBe(false);
      expect(res.body.message).toBe('No recognized configuration parameters provided. Accepted parameters are: apiKey, modelId, apiBaseUrl.');
    });
  });
}); 