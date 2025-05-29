import { resetAllMocksAndModules } from '../../testUtils/resetTestEnv';

// Mock youtubeService BEFORE it's imported or required anywhere else in this test file.
// This ensures that any subsequent require or import gets the mocked version.
jest.mock('./youtubeService', () => ({
  __esModule: true, // if youtubeService is an ES module
  fetchYouTubeLink: jest.fn(),
  // If musicProviderService or tests need other exports from youtubeService,
  // you might need to provide them here, e.g.:
  // someOtherExport: jest.fn(), or jest.requireActual('./youtubeService').someOtherExport
}));

jest.mock('./youtubeScrapeService', () => ({
  __esModule: true,
  fetchYouTubeLinkByScraping: jest.fn(),
}));

jest.mock('./spotifyService', () => ({
  __esModule: true,
  fetchSpotifyPreviewUrl: jest.fn(),
}));

jest.mock('../utils/envConfig', () => ({
  __esModule: true,
  default: {
    get SPOTIFY_CLIENT_ID() {
      return process.env.SPOTIFY_CLIENT_ID;
    },
    get SPOTIFY_CLIENT_SECRET() {
      return process.env.SPOTIFY_CLIENT_SECRET;
    },
    get YOUTUBE_API_KEY() {
      return process.env.YOUTUBE_API_KEY;
    },
  },
}));

// Now, we can import the mocked function and the function to test.
// Note: Direct imports are fine now because the mock is established.
import { fetchTrackLink } from './musicProviderService';
const mockedFetchYouTubeLinkApi = require('./youtubeService').fetchYouTubeLink as jest.Mock;
const mockedFetchYouTubeLinkScrape = require('./youtubeScrapeService').fetchYouTubeLinkByScraping as jest.Mock;
const mockedFetchSpotifyPreviewUrl = require('./spotifyService').fetchSpotifyPreviewUrl as jest.Mock;

beforeEach(() => {
  // resetAllMocksAndModules probably calls jest.resetModules() and jest.clearAllMocks() or similar.
  // This means the top-level jest.mock is effectively re-applied for fresh modules if resetModules is used.
  resetAllMocksAndModules();
  // Ensure the mock is clean before each test if resetAllMocksAndModules doesn't cover jest.fn().mockClear()
  mockedFetchYouTubeLinkApi.mockClear();
  mockedFetchYouTubeLinkScrape.mockClear();
  mockedFetchSpotifyPreviewUrl.mockClear();
  
  // Set default environment variables
  process.env.YOUTUBE_FETCH_METHOD = 'SCRAPE';
  process.env.SPOTIFY_CLIENT_ID = 'test-spotify-client-id';
  process.env.SPOTIFY_CLIENT_SECRET = 'test-spotify-client-secret';
  process.env.YOUTUBE_API_KEY = 'test-youtube-api-key';
});

describe('musicProviderService', () => {
  const apiKey = 'test-youtube-api-key';

  describe('Input validation', () => {
    it('should return undefined for empty title', async () => {
      const result = await fetchTrackLink('', 'Artist', 'youtube', apiKey);
      expect(result).toBeUndefined();
      expect(mockedFetchYouTubeLinkApi).not.toHaveBeenCalled();
      expect(mockedFetchYouTubeLinkScrape).not.toHaveBeenCalled();
    });

    it('should return undefined for empty artist', async () => {
      const result = await fetchTrackLink('Song', '', 'youtube', apiKey);
      expect(result).toBeUndefined();
      expect(mockedFetchYouTubeLinkApi).not.toHaveBeenCalled();
      expect(mockedFetchYouTubeLinkScrape).not.toHaveBeenCalled();
    });

    it('should return undefined for whitespace-only inputs', async () => {
      const result = await fetchTrackLink('   ', '   ', 'youtube', apiKey);
      expect(result).toBeUndefined();
      expect(mockedFetchYouTubeLinkApi).not.toHaveBeenCalled();
      expect(mockedFetchYouTubeLinkScrape).not.toHaveBeenCalled();
    });
  });

  describe('YouTube provider', () => {
    it('should use API method when YOUTUBE_FETCH_METHOD is API and API key is provided', async () => {
      process.env.YOUTUBE_FETCH_METHOD = 'API';
      mockedFetchYouTubeLinkApi.mockResolvedValue('https://youtube.com/api-link');
      
      const link = await fetchTrackLink('Song', 'Artist', 'youtube', apiKey);
      
      expect(link).toBe('https://youtube.com/api-link');
      expect(mockedFetchYouTubeLinkApi).toHaveBeenCalledWith('Song', 'Artist', apiKey);
      expect(mockedFetchYouTubeLinkScrape).not.toHaveBeenCalled();
    });

    it('should use SCRAPE method when YOUTUBE_FETCH_METHOD is SCRAPE', async () => {
      process.env.YOUTUBE_FETCH_METHOD = 'SCRAPE';
      mockedFetchYouTubeLinkScrape.mockResolvedValue('https://youtube.com/scrape-link');
      
      const link = await fetchTrackLink('Song', 'Artist', 'youtube', apiKey);
      
      expect(link).toBe('https://youtube.com/scrape-link');
      expect(mockedFetchYouTubeLinkScrape).toHaveBeenCalledWith('Song', 'Artist');
      expect(mockedFetchYouTubeLinkApi).not.toHaveBeenCalled();
    });

    it('should throw error when API method is set but no API key provided', async () => {
      process.env.YOUTUBE_FETCH_METHOD = 'API';
      delete process.env.YOUTUBE_API_KEY;
      
      await expect(fetchTrackLink('Song', 'Artist', 'youtube', undefined))
        .rejects.toThrow('YouTube API key is required when YOUTUBE_FETCH_METHOD is set to API');
    });

    it('should handle API errors by propagating them', async () => {
      process.env.YOUTUBE_FETCH_METHOD = 'API';
      mockedFetchYouTubeLinkApi.mockRejectedValue(new Error('YouTube API error'));
      
      await expect(fetchTrackLink('Song', 'Artist', 'youtube', apiKey))
        .rejects.toThrow('YouTube API error');
    });

    it('should handle scraping errors gracefully', async () => {
      process.env.YOUTUBE_FETCH_METHOD = 'SCRAPE';
      mockedFetchYouTubeLinkScrape.mockRejectedValue(new Error('Scraping failed'));
      
      const result = await fetchTrackLink('Song', 'Artist', 'youtube');
      
      expect(result).toBeUndefined();
      expect(mockedFetchYouTubeLinkScrape).toHaveBeenCalled();
    });
  });

  describe('Spotify provider', () => {
    it('should fetch from Spotify when provider is spotify', async () => {
      mockedFetchSpotifyPreviewUrl.mockResolvedValue('https://p.scdn.co/mp3-preview/spotify');
      
      const link = await fetchTrackLink('Song', 'Artist', 'spotify');
      
      expect(link).toBe('https://p.scdn.co/mp3-preview/spotify');
      expect(mockedFetchSpotifyPreviewUrl).toHaveBeenCalledWith(
        'Song', 
        'Artist', 
        'test-spotify-client-id', 
        'test-spotify-client-secret'
      );
    });

    it('should return undefined when Spotify credentials are missing', async () => {
      delete process.env.SPOTIFY_CLIENT_ID;
      delete process.env.SPOTIFY_CLIENT_SECRET;
      
      const result = await fetchTrackLink('Song', 'Artist', 'spotify');
      
      expect(result).toBeUndefined();
      expect(mockedFetchSpotifyPreviewUrl).not.toHaveBeenCalled();
    });

    it('should handle Spotify errors gracefully', async () => {
      mockedFetchSpotifyPreviewUrl.mockRejectedValue(new Error('Spotify API error'));
      
      const result = await fetchTrackLink('Song', 'Artist', 'spotify');
      
      expect(result).toBeUndefined();
      expect(mockedFetchSpotifyPreviewUrl).toHaveBeenCalled();
    });
  });

  describe('Provider validation', () => {
    it('should throw error for unsupported provider', async () => {
      await expect(fetchTrackLink('Song', 'Artist', 'apple' as any))
        .rejects.toThrow('Music provider "apple" not supported');
    });
  });

  describe('Success and failure cases', () => {
    it('should return undefined when no track is found', async () => {
      process.env.YOUTUBE_FETCH_METHOD = 'SCRAPE';
      mockedFetchYouTubeLinkScrape.mockResolvedValue(null);
      
      const result = await fetchTrackLink('Unknown Song', 'Unknown Artist', 'youtube');
      
      expect(result).toBeUndefined();
    });

    it('should handle undefined return from provider', async () => {
      mockedFetchSpotifyPreviewUrl.mockResolvedValue(undefined);
      
      const result = await fetchTrackLink('Song', 'Artist', 'spotify');
      
      expect(result).toBeUndefined();
    });
  });
}); 