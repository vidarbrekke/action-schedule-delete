/**
 * Shared route testing utilities
 * Eliminates duplication across adminRoutes, gameRoutes, and musicLinkRoutes tests
 */

import express, { Express } from 'express';
import request from 'supertest';

export interface RouteTestConfig {
  routePath: string;
  middlewares?: any[];
  mocks?: Record<string, any>;
}

/**
 * Creates a standardized Express app for route testing
 */
export function createTestApp(routeHandler: any, config: RouteTestConfig): Express {
  const app = express();
  
  // Standard middleware setup
  app.use(express.json());
  app.use(express.urlencoded({ extended: true }));
  
  // Apply custom middlewares if provided
  if (config.middlewares) {
    config.middlewares.forEach(middleware => app.use(middleware));
  }
  
  // Mount the route handler
  app.use(config.routePath, routeHandler);
  
  return app;
}

/**
 * Standard route test helpers
 */
export class RouteTestHelper {
  private app: Express;
  private request: any;

  constructor(app: Express) {
    this.app = app;
    this.request = request(app);
  }

  /**
   * Test standard GET endpoint
   */
  async testGet(endpoint: string, expectedStatus: number = 200) {
    const response = await this.request.get(endpoint);
    expect(response.status).toBe(expectedStatus);
    return response;
  }

  /**
   * Test standard POST endpoint
   */
  async testPost(endpoint: string, data: any, expectedStatus: number = 200) {
    const response = await this.request.post(endpoint).send(data);
    expect(response.status).toBe(expectedStatus);
    return response;
  }

  /**
   * Test standard PUT endpoint
   */
  async testPut(endpoint: string, data: any, expectedStatus: number = 200) {
    const response = await this.request.put(endpoint).send(data);
    expect(response.status).toBe(expectedStatus);
    return response;
  }

  /**
   * Test standard DELETE endpoint
   */
  async testDelete(endpoint: string, expectedStatus: number = 200) {
    const response = await this.request.delete(endpoint);
    expect(response.status).toBe(expectedStatus);
    return response;
  }

  /**
   * Test validation error scenarios
   */
  async testValidationError(endpoint: string, method: 'get' | 'post' | 'put' | 'delete', data?: any) {
    let response;
    
    switch (method) {
      case 'get':
        response = await this.request.get(endpoint);
        break;
      case 'post':
        response = await this.request.post(endpoint).send(data || {});
        break;
      case 'put':
        response = await this.request.put(endpoint).send(data || {});
        break;
      case 'delete':
        response = await this.request.delete(endpoint);
        break;
    }
    
    expect(response.status).toBe(400);
    expect(response.body.success).toBe(false);
    expect(response.body.error || response.body.message).toBeDefined();
    
    return response;
  }

  /**
   * Test 404 scenarios
   */
  async testNotFound(endpoint: string, method: 'get' | 'post' | 'put' | 'delete' = 'get', data?: any) {
    let response;
    
    switch (method) {
      case 'get':
        response = await this.request.get(endpoint);
        break;
      case 'post':
        response = await this.request.post(endpoint).send(data || {});
        break;
      case 'put':
        response = await this.request.put(endpoint).send(data || {});
        break;
      case 'delete':
        response = await this.request.delete(endpoint);
        break;
    }
    
    expect(response.status).toBe(404);
    return response;
  }
}

/**
 * Standard test patterns for API responses
 */
export function testSuccessResponse(response: any, expectedData?: any) {
  expect(response.body.success).toBe(true);
  if (expectedData) {
    expect(response.body).toMatchObject(expectedData);
  }
}

export function testErrorResponse(response: any, expectedMessage?: string) {
  expect(response.body.success).toBe(false);
  if (expectedMessage) {
    expect(response.body.error || response.body.message).toContain(expectedMessage);
  }
}

/**
 * Common mock setups for route testing
 */
export function setupCommonMocks() {
  // Mock environment variables
  const originalEnv = process.env;
  
  beforeEach(() => {
    process.env = {
      ...originalEnv,
      NODE_ENV: 'test',
      OPENROUTER_API_KEY: 'test-key',
      OPENROUTER_MODEL_ID: 'test-model',
      OPENROUTER_API_BASE_URL: 'https://test.api.com',
      YOUTUBE_API_KEY: 'test-youtube-key'
    };
  });

  afterEach(() => {
    process.env = originalEnv;
    jest.clearAllMocks();
  });
}

/**
 * Shared test patterns for CRUD operations
 */
export function testCrudOperations(helper: RouteTestHelper, config: {
  createEndpoint: string;
  readEndpoint: string;
  updateEndpoint: string;
  deleteEndpoint: string;
  validData: any;
  invalidData: any;
}) {
  describe('CRUD Operations', () => {
    it('should create resource successfully', async () => {
      const response = await helper.testPost(config.createEndpoint, config.validData, 201);
      testSuccessResponse(response);
    });

    it('should read resource successfully', async () => {
      const response = await helper.testGet(config.readEndpoint);
      testSuccessResponse(response);
    });

    it('should update resource successfully', async () => {
      const response = await helper.testPut(config.updateEndpoint, config.validData);
      testSuccessResponse(response);
    });

    it('should delete resource successfully', async () => {
      const response = await helper.testDelete(config.deleteEndpoint);
      testSuccessResponse(response);
    });

    it('should return validation error for invalid data', async () => {
      await helper.testValidationError(config.createEndpoint, 'post', config.invalidData);
    });

    it('should return 404 for non-existent resource', async () => {
      await helper.testNotFound('/api/nonexistent');
    });
  });
} 