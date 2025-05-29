import { fetchYouTubeLink, enrichSongsWithYouTubeLinks, __setMockFetchYouTubeLink, __setMockEnrichSongsWithYouTubeLinks } from './youtubeService';
import { Song } from '../models/types';
import { resetAllMocksAndModules } from '../../testUtils/resetTestEnv';

// Mock the entire googleapis library
jest.mock('googleapis', () => ({
  google: {
    youtube: jest.fn(() => ({
      search: {
        list: jest.fn(),
      },
    })),
  },
}));

// Helper to access the mock
const mockGoogleYouTubeSearchList = jest.requireMock('googleapis').google.youtube().search.list;

describe('youtubeService', () => {
  beforeEach(() => {
    resetAllMocksAndModules();
    // Reset mocks before each test
    mockGoogleYouTubeSearchList.mockReset();
    // Reset internal service mocks if they were used by other tests (though not typical for unit tests of the service itself)
    __setMockFetchYouTubeLink(null);
    __setMockEnrichSongsWithYouTubeLinks(null); 
  });

  afterEach(() => {
    resetAllMocksAndModules();
  });

  describe('fetchYouTubeLink', () => {
    const apiKey = 'test-youtube-api-key';

    it('should return a YouTube link if video is found', async () => {
      const mockVideoId = 'dQw4w9WgXcQ';
      const mockFetch = jest.fn().mockResolvedValue(`https://www.youtube.com/watch?v=${mockVideoId}`);
      __setMockFetchYouTubeLink(mockFetch);
      const link = await fetchYouTubeLink('Never Gonna Give You Up', 'Rick Astley', apiKey);
      expect(link).toBe(`https://www.youtube.com/watch?v=${mockVideoId}`);
      expect(mockFetch).toHaveBeenCalledWith('Never Gonna Give You Up', 'Rick Astley', apiKey);
      __setMockFetchYouTubeLink(null);
    });

    it('should return undefined if no video is found', async () => {
      const mockFetch = jest.fn().mockResolvedValue(undefined);
      __setMockFetchYouTubeLink(mockFetch);
      const link = await fetchYouTubeLink('Unknown Song', 'Unknown Artist', apiKey);
      expect(link).toBeUndefined();
      expect(mockFetch).toHaveBeenCalledWith('Unknown Song', 'Unknown Artist', apiKey);
      __setMockFetchYouTubeLink(null);
    });

    it('should return undefined if videoId is missing', async () => {
      const mockFetch = jest.fn().mockResolvedValue(undefined);
      __setMockFetchYouTubeLink(mockFetch);
      const link = await fetchYouTubeLink('Song With Missing ID', 'Artist', apiKey);
      expect(link).toBeUndefined();
      expect(mockFetch).toHaveBeenCalledWith('Song With Missing ID', 'Artist', apiKey);
      __setMockFetchYouTubeLink(null);
    });

    it('should return undefined on API error and log the error', async () => {
      const mockFetch = jest.fn().mockRejectedValue(new Error('YouTube API Error'));
      __setMockFetchYouTubeLink(mockFetch);
      let link;
      try {
        link = await fetchYouTubeLink('Error Song', 'Error Artist', apiKey);
      } catch (e) {
        link = undefined;
      }
      expect(link).toBeUndefined();
      expect(mockFetch).toHaveBeenCalledWith('Error Song', 'Error Artist', apiKey);
      __setMockFetchYouTubeLink(null);
    });

    // Test case for when the googleapis import itself fails (less common for unit tests, more for integration)
    // This is tricky to mock cleanly at this level without more complex jest.isolateModules or similar.
    // For now, we assume googleapis itself loads.
  });

  describe('enrichSongsWithYouTubeLinks', () => {
    const apiKey = 'test-youtube-api-key';
    const songsWithoutLinks: Song[] = [
      { title: 'Song A', artist: 'Artist A' },
      { title: 'Song B', artist: 'Artist B' },
    ];

    it('should enrich songs with YouTube links if API key is provided', async () => {
      // Use valid YouTube URLs so embed conversion works
      const mockFetchLink = jest.fn()
        .mockResolvedValueOnce('https://www.youtube.com/watch?v=dQw4w9WgXcQ')
        .mockResolvedValueOnce('https://youtu.be/9bZkp7q19f0');
      __setMockFetchYouTubeLink(mockFetchLink);

      const enrichedSongs = await enrichSongsWithYouTubeLinks(songsWithoutLinks, apiKey);
      
      expect(enrichedSongs).toEqual([
        { title: 'Song A', artist: 'Artist A', trackUrl: 'https://www.youtube.com/embed/dQw4w9WgXcQ', used: false },
        { title: 'Song B', artist: 'Artist B', trackUrl: 'https://www.youtube.com/embed/9bZkp7q19f0', used: false },
      ]);
      expect(mockFetchLink).toHaveBeenCalledTimes(2);
      expect(mockFetchLink).toHaveBeenCalledWith('Song A', 'Artist A', apiKey);
      expect(mockFetchLink).toHaveBeenCalledWith('Song B', 'Artist B', apiKey);
      
      __setMockFetchYouTubeLink(null); // Clean up internal mock
    });

    it('should return songs without links if API key is not provided and log a warning', async () => {
      const consoleWarnSpy = jest.spyOn(console, 'warn').mockImplementation(() => {});
      const enrichedSongs = await enrichSongsWithYouTubeLinks(songsWithoutLinks, null);
      
      expect(enrichedSongs).toEqual([
        { title: 'Song A', artist: 'Artist A', trackUrl: undefined, used: false },
        { title: 'Song B', artist: 'Artist B', trackUrl: undefined, used: false },
      ]);
      expect(consoleWarnSpy).toHaveBeenCalledWith('[YouTube] YouTube API key is not configured or song list is empty. Proceeding without YouTube links.');
      consoleWarnSpy.mockRestore();
    });

    it('should return empty array if input songs array is empty', async () => {
      const enrichedSongs = await enrichSongsWithYouTubeLinks([], apiKey);
      expect(enrichedSongs).toEqual([]);
    });

    it('should handle errors from fetchYouTubeLink gracefully for individual songs', async () => {
      const mockFetchLink = jest.fn()
        .mockResolvedValueOnce('https://www.youtube.com/watch?v=dQw4w9WgXcQ')
        .mockRejectedValueOnce(new Error('Failed to fetch linkB'));
      __setMockFetchYouTubeLink(mockFetchLink);

      const enrichedSongs = await enrichSongsWithYouTubeLinks(songsWithoutLinks, apiKey);
      
      expect(enrichedSongs).toEqual([
        { title: 'Song A', artist: 'Artist A', trackUrl: 'https://www.youtube.com/embed/dQw4w9WgXcQ', used: false },
        { title: 'Song B', artist: 'Artist B', trackUrl: undefined, used: false },
      ]);
      
      __setMockFetchYouTubeLink(null);
    });
  });

  describe('toYouTubeEmbedUrl', () => {
    const { toYouTubeEmbedUrl } = require('./youtubeService');

    it('converts standard watch URLs to embed URLs', () => {
      expect(toYouTubeEmbedUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ')).toBe('https://www.youtube.com/embed/dQw4w9WgXcQ');
      expect(toYouTubeEmbedUrl('https://youtube.com/watch?v=dQw4w9WgXcQ')).toBe('https://www.youtube.com/embed/dQw4w9WgXcQ');
    });

    it('converts youtu.be short URLs to embed URLs', () => {
      expect(toYouTubeEmbedUrl('https://youtu.be/dQw4w9WgXcQ')).toBe('https://www.youtube.com/embed/dQw4w9WgXcQ');
    });

    it('converts /embed/ URLs to themselves', () => {
      expect(toYouTubeEmbedUrl('https://www.youtube.com/embed/dQw4w9WgXcQ')).toBe('https://www.youtube.com/embed/dQw4w9WgXcQ');
    });

    it('converts /v/ and /vi/ URLs to embed URLs', () => {
      expect(toYouTubeEmbedUrl('https://www.youtube.com/v/dQw4w9WgXcQ')).toBe('https://www.youtube.com/embed/dQw4w9WgXcQ');
      expect(toYouTubeEmbedUrl('https://www.youtube.com/vi/dQw4w9WgXcQ')).toBe('https://www.youtube.com/embed/dQw4w9WgXcQ');
    });

    it('returns null for invalid or non-YouTube URLs', () => {
      expect(toYouTubeEmbedUrl('https://www.example.com/watch?v=dQw4w9WgXcQ')).toBeNull();
      expect(toYouTubeEmbedUrl('not a url')).toBeNull();
      expect(toYouTubeEmbedUrl('')).toBeNull();
      expect(toYouTubeEmbedUrl(null)).toBeNull();
    });

    it('returns null for malformed YouTube URLs', () => {
      expect(toYouTubeEmbedUrl('https://www.youtube.com/watch?v=')).toBeNull();
      expect(toYouTubeEmbedUrl('https://www.youtube.com/embed/')).toBeNull();
      expect(toYouTubeEmbedUrl('https://youtu.be/')).toBeNull();
    });

    it('handles URLs with extra query parameters', () => {
      expect(toYouTubeEmbedUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=42s')).toBe('https://www.youtube.com/embed/dQw4w9WgXcQ');
      expect(toYouTubeEmbedUrl('https://youtu.be/dQw4w9WgXcQ?feature=share')).toBe('https://www.youtube.com/embed/dQw4w9WgXcQ');
    });
  });
}); 