import request from 'supertest';
import express, { Express } from 'express';
import * as musicProviderService from '../services/musicProviderService';
import envConfig from '../utils/envConfig'; // Import to ascertain structure for mocking

// Mock the envConfig from utils
jest.mock('../utils/envConfig', () => ({
  __esModule: true, // if it's an ES module
  default: { // Assuming envConfig exports env as default: export default env;
    YOUTUBE_API_KEY: 'default-test-youtube-key', // Provide a default mock value
    // ... other env variables if needed by any code path, though not directly by this route
  },
}));

// Mock the musicProviderService
jest.mock('../services/musicProviderService', () => ({
  __esModule: true,
  fetchTrackLink: jest.fn(),
}));

describe('/api/music-link', () => {
  let app: Express;
  // Get a typed reference to the mocked musicProviderService
  const mockedMusicProviderService = musicProviderService as jest.Mocked<typeof musicProviderService>;
  // Get a typed reference to the default export of the mocked envConfig
  const mockedEnvConfig = envConfig as { YOUTUBE_API_KEY?: string }; // Adjust type as needed

  beforeEach(() => {
    // Reset service mocks
    mockedMusicProviderService.fetchTrackLink.mockClear();

    // Default: API key is available via the mocked envConfig
    // The mock is defined above, ensure its YOUTUBE_API_KEY is set if needed for default
    // Or, more explicitly, set it here if the top-level mock is minimal:
    mockedEnvConfig.YOUTUBE_API_KEY = 'test-api-key-from-beforeEach';
    
    // Set default fetch method
    process.env.YOUTUBE_FETCH_METHOD = 'SCRAPE';

    app = express();
    app.use(express.json());
    
    // Dynamically require the router here to ensure it picks up fresh mocks
    const musicLinkRouterForTest = require('./musicLinkRoutes').default;
    app.use('/api', musicLinkRouterForTest);
  });

  it('returns a YouTube link for valid input', async () => {
    mockedMusicProviderService.fetchTrackLink.mockResolvedValue('https://youtube.com/link');
    const res = await request(app)
      .get('/api/music-link')
      .query({ title: 'Song', artist: 'Artist', provider: 'youtube' });
    expect(res.status).toBe(200);
    expect(res.body.link).toBe('https://youtube.com/link');
  });

  it('returns 400 if title or artist is missing', async () => {
    const res = await request(app)
      .get('/api/music-link')
      .query({ title: 'Song' });
    expect(res.status).toBe(400);
    expect(res.body.error).toMatch(/Missing title or artist/);
  });

  it('returns 500 on provider error from fetchTrackLink', async () => {
    mockedMusicProviderService.fetchTrackLink.mockRejectedValue(new Error('Provider error'));
    const res = await request(app)
      .get('/api/music-link')
      .query({ title: 'Song', artist: 'Artist', provider: 'youtube' });
    expect(res.status).toBe(500);
    expect(res.body.error).toMatch(/Provider error/);
  });

  it('returns 500 on unsupported provider error from fetchTrackLink', async () => {
    mockedMusicProviderService.fetchTrackLink.mockImplementation(() => {
      throw new Error('Music provider "spotify" not supported');
    });
    const res = await request(app)
      .get('/api/music-link')
      .query({ title: 'Song', artist: 'Artist', provider: 'spotify' });
    expect(res.status).toBe(500);
    expect(res.body.error).toMatch(/not supported/);
  });

  it('works without API key when using SCRAPE method', async () => {
    mockedEnvConfig.YOUTUBE_API_KEY = undefined;
    process.env.YOUTUBE_FETCH_METHOD = 'SCRAPE';
    mockedMusicProviderService.fetchTrackLink.mockResolvedValue('https://youtube.com/scraped-link');
    
    // Re-require the router after changing the env mock
    const currentApp = express();
    currentApp.use(express.json());
    const freshRouterForThisTest = require('./musicLinkRoutes').default;
    currentApp.use('/api', freshRouterForThisTest);

    const res = await request(currentApp)
      .get('/api/music-link')
      .query({ title: 'Song', artist: 'Artist', provider: 'youtube' });
    expect(res.status).toBe(200);
    expect(res.body.link).toBe('https://youtube.com/scraped-link');
    expect(mockedMusicProviderService.fetchTrackLink).toHaveBeenCalledWith('Song', 'Artist', 'youtube', undefined);
  });

  it('returns 503 when API method is set but no API key provided', async () => {
    mockedEnvConfig.YOUTUBE_API_KEY = undefined;
    process.env.YOUTUBE_FETCH_METHOD = 'API';
    
    // Re-require the router after changing the env mock
    const currentApp = express();
    currentApp.use(express.json());
    const freshRouterForThisTest = require('./musicLinkRoutes').default;
    currentApp.use('/api', freshRouterForThisTest);

    const res = await request(currentApp)
      .get('/api/music-link')
      .query({ title: 'Song', artist: 'Artist', provider: 'youtube' });
    expect(res.status).toBe(503);
    expect(res.body.error).toMatch(/API key required for API method/);
    expect(mockedMusicProviderService.fetchTrackLink).not.toHaveBeenCalled();
  });

  it('handles undefined link from fetchTrackLink', async () => {
    mockedMusicProviderService.fetchTrackLink.mockResolvedValue(undefined);
    const res = await request(app)
      .get('/api/music-link')
      .query({ title: 'Song', artist: 'Artist', provider: 'youtube' });
    expect(res.status).toBe(200);
    expect(res.body.link).toBeUndefined();
  });
}); 