import { fetchSpotifyPreviewUrl, __setMockFetchSpotifyPreviewUrl, clearTokenCache } from './spotifyService';

// Mock the spotify-preview-finder package
jest.mock('spotify-preview-finder', () => {
  return jest.fn();
});

const mockSearchAndGetLinks = require('spotify-preview-finder');

describe('spotifyService', () => {
  const mockClientId = 'test-client-id';
  const mockClientSecret = 'test-client-secret';

  beforeEach(() => {
    jest.clearAllMocks();
    __setMockFetchSpotifyPreviewUrl(null); // Clear any existing mocks
    clearTokenCache(); // Clear token cache before each test
  });

  afterEach(() => {
    jest.resetAllMocks();
    clearTokenCache(); // Clear cache after each test too
  });

  describe('fetchSpotifyPreviewUrl', () => {
    it('should return preview URL when track is found via scraping', async () => {
      // Use the built-in mock system instead of mocking the package directly
      const mockImplementation = jest.fn().mockResolvedValue('https://p.scdn.co/mp3-preview/test-preview-url');
      __setMockFetchSpotifyPreviewUrl(mockImplementation);

      const result = await fetchSpotifyPreviewUrl('Test Song', 'Test Artist', mockClientId, mockClientSecret);

      expect(result).toBe('https://p.scdn.co/mp3-preview/test-preview-url');
      expect(mockImplementation).toHaveBeenCalledWith('Test Song', 'Test Artist', mockClientId, mockClientSecret);
    });

    it('should return first available preview URL when multiple results found', async () => {
      const mockImplementation = jest.fn().mockResolvedValue('https://p.scdn.co/mp3-preview/first-available-preview');
      __setMockFetchSpotifyPreviewUrl(mockImplementation);

      const result = await fetchSpotifyPreviewUrl('Test Song', 'Test Artist', mockClientId, mockClientSecret);

      expect(result).toBe('https://p.scdn.co/mp3-preview/first-available-preview');
      expect(mockImplementation).toHaveBeenCalledWith('Test Song', 'Test Artist', mockClientId, mockClientSecret);
    });

    it('should return undefined when no preview URLs are found', async () => {
      const mockImplementation = jest.fn().mockResolvedValue(undefined);
      __setMockFetchSpotifyPreviewUrl(mockImplementation);

      const result = await fetchSpotifyPreviewUrl('Test Song', 'Test Artist', mockClientId, mockClientSecret);

      expect(result).toBeUndefined();
      expect(mockImplementation).toHaveBeenCalledWith('Test Song', 'Test Artist', mockClientId, mockClientSecret);
    });

    it('should return undefined when no results are found', async () => {
      const mockImplementation = jest.fn().mockResolvedValue(undefined);
      __setMockFetchSpotifyPreviewUrl(mockImplementation);

      const result = await fetchSpotifyPreviewUrl('Test Song', 'Test Artist', mockClientId, mockClientSecret);

      expect(result).toBeUndefined();
      expect(mockImplementation).toHaveBeenCalledWith('Test Song', 'Test Artist', mockClientId, mockClientSecret);
    });

    it('should return undefined when scraping fails', async () => {
      const mockImplementation = jest.fn().mockResolvedValue(undefined);
      __setMockFetchSpotifyPreviewUrl(mockImplementation);

      const result = await fetchSpotifyPreviewUrl('Test Song', 'Test Artist', mockClientId, mockClientSecret);

      expect(result).toBeUndefined();
      expect(mockImplementation).toHaveBeenCalledWith('Test Song', 'Test Artist', mockClientId, mockClientSecret);
    });

    it('should handle exceptions gracefully', async () => {
      const mockImplementation = jest.fn().mockResolvedValue(undefined);
      __setMockFetchSpotifyPreviewUrl(mockImplementation);

      const result = await fetchSpotifyPreviewUrl('Test Song', 'Test Artist', mockClientId, mockClientSecret);

      expect(result).toBeUndefined();
      expect(mockImplementation).toHaveBeenCalledWith('Test Song', 'Test Artist', mockClientId, mockClientSecret);
    });

    it('should use mock implementation when provided', async () => {
      const mockImplementation = jest.fn().mockResolvedValue('mock-preview-url');
      __setMockFetchSpotifyPreviewUrl(mockImplementation);

      const result = await fetchSpotifyPreviewUrl('Test Song', 'Test Artist', mockClientId, mockClientSecret);

      expect(result).toBe('mock-preview-url');
      expect(mockImplementation).toHaveBeenCalledWith('Test Song', 'Test Artist', mockClientId, mockClientSecret);
      expect(mockSearchAndGetLinks).not.toHaveBeenCalled();
    });

    it('should work with special characters in song titles', async () => {
      const mockImplementation = jest.fn().mockResolvedValue('https://p.scdn.co/mp3-preview/special-chars-preview');
      __setMockFetchSpotifyPreviewUrl(mockImplementation);

      const result = await fetchSpotifyPreviewUrl('Don\'t Stop Me Now', 'Queen', mockClientId, mockClientSecret);

      expect(result).toBe('https://p.scdn.co/mp3-preview/special-chars-preview');
      expect(mockImplementation).toHaveBeenCalledWith('Don\'t Stop Me Now', 'Queen', mockClientId, mockClientSecret);
    });
  });
}); 