import { fetchDeezerPreviewUrl, __setMockFetchDeezerPreviewUrl, getDeezerTrackDetails, extractDeezerTrackId, getDeezerAlbumArtwork, fetchDeezerTrackWithArtwork } from './deezerService';
import axios from 'axios';
import { 
  createMusicServiceTestSuite, 
  testMusicServiceInputValidation,
  testMusicServiceErrorHandling,
  testMusicServiceSuccessScenarios
} from '../testUtils/musicServiceTestUtils';

// Mock axios
jest.mock('axios');
const mockedAxios = axios as jest.Mocked<typeof axios>;

describe('deezerService', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    // Reset any mock implementations
    __setMockFetchDeezerPreviewUrl(null);
  });

  describe('fetchDeezerPreviewUrl', () => {
    const deezerConfig = {
      fetchFunction: fetchDeezerPreviewUrl,
      mockSetup: () => {
        jest.clearAllMocks();
        __setMockFetchDeezerPreviewUrl(null);
      },
      mockImplementation: (mockFn: jest.MockedFunction<any>) => {
        __setMockFetchDeezerPreviewUrl(mockFn);
      },
      expectedUrl: 'https://cdns-preview-d.dzcdn.net/stream/test-preview.mp3'
    };

    // Apply shared test patterns
    testMusicServiceInputValidation(deezerConfig);
    testMusicServiceErrorHandling({ ...deezerConfig, serviceName: 'Deezer' });
    testMusicServiceSuccessScenarios({ ...deezerConfig, serviceName: 'Deezer' });

    // Deezer-specific tests that add unique value
    it('should return preview URL for a found track', async () => {
      const validResponse = {
        data: {
          data: [
            {
              id: 3135556,
              title: 'Harder Better Faster Stronger',
              title_short: 'Harder Better Faster Stronger',
              duration: 225,
              preview: deezerConfig.expectedUrl,
              artist: { id: 27, name: 'Daft Punk' },
              album: { id: 302127, title: 'Discovery' }
            }
          ],
          total: 1
        }
      };
      mockedAxios.get.mockResolvedValue(validResponse);

      const result = await fetchDeezerPreviewUrl('Harder Better Faster Stronger', 'Daft Punk');

      expect(result).toBe(deezerConfig.expectedUrl);
      expect(mockedAxios.get).toHaveBeenCalledWith('https://api.deezer.com/search', {
        params: { q: 'Harder Better Faster Stronger Daft Punk', limit: 10 },
        timeout: 10000,
        headers: { 'User-Agent': 'TuneTussle/1.0' }
      });
    });

    it('should return undefined when no results found', async () => {
      const emptyResponse = { data: { data: [], total: 0 } };
      mockedAxios.get.mockResolvedValue(emptyResponse);
      const result = await fetchDeezerPreviewUrl('Unknown Song', 'Unknown Artist');
      expect(result).toBeUndefined();
    });

    it('should return undefined when no tracks have preview URLs', async () => {
      const noPreviewResponse = {
        data: {
          data: [
            {
              id: 12345,
              title: 'Some Song',
              title_short: 'Some Song',
              duration: 180,
              preview: '', // No preview URL
              artist: { id: 123, name: 'Some Artist' },
              album: { id: 456, title: 'Some Album' }
            }
          ],
          total: 1
        }
      };

      mockedAxios.get.mockResolvedValue(noPreviewResponse);
      const result = await fetchDeezerPreviewUrl('Some Song', 'Some Artist');
      expect(result).toBeUndefined();
    });

    it('should find first track with valid preview URL', async () => {
      const multipleTracksResponse = {
        data: {
          data: [
            {
              id: 1,
              title: 'Song 1',
              preview: '', // No preview
              artist: { id: 1, name: 'Artist' },
              album: { id: 1, title: 'Album' }
            },
            {
              id: 2,
              title: 'Song 2',
              preview: 'https://cdns-preview-d.dzcdn.net/stream/valid-preview.mp3', // Valid preview
              artist: { id: 1, name: 'Artist' },
              album: { id: 1, title: 'Album' }
            }
          ],
          total: 2
        }
      };

      mockedAxios.get.mockResolvedValue(multipleTracksResponse);
      const result = await fetchDeezerPreviewUrl('Test Song', 'Test Artist');
      expect(result).toBe('https://cdns-preview-d.dzcdn.net/stream/valid-preview.mp3');
    });
  });

  describe('getDeezerTrackDetails', () => {
    it('should return track details for valid track ID', async () => {
      const mockTrack = {
        id: 3135556,
        title: 'Harder Better Faster Stronger',
        title_short: 'Harder Better Faster Stronger',
        duration: 225,
        preview: 'https://cdns-preview-d.dzcdn.net/stream/preview.mp3',
        artist: { id: 27, name: 'Daft Punk' },
        album: { id: 302127, title: 'Discovery' }
      };

      mockedAxios.get.mockResolvedValue({ data: mockTrack });

      const result = await getDeezerTrackDetails(3135556);

      expect(result).toEqual(mockTrack);
      expect(mockedAxios.get).toHaveBeenCalledWith('https://api.deezer.com/track/3135556', {
        timeout: 5000,
        headers: { 'User-Agent': 'TuneTussle/1.0' }
      });
    });

    it('should return undefined for invalid track ID', async () => {
      mockedAxios.get.mockRejectedValue(new Error('Track not found'));
      const result = await getDeezerTrackDetails(999999999);
      expect(result).toBeUndefined();
    });
  });

  describe('extractDeezerTrackId', () => {
    it('should extract track ID from various Deezer URL formats', () => {
      expect(extractDeezerTrackId('https://www.deezer.com/track/3135556')).toBe(3135556);
      expect(extractDeezerTrackId('https://deezer.com/track/123456789')).toBe(123456789);
      expect(extractDeezerTrackId('http://deezer.com/track/987654321')).toBe(987654321); // HTTP variant
      expect(extractDeezerTrackId('https://www.deezer.com/en/track/987654321')).toBeUndefined(); // This format isn't supported by current regex
      expect(extractDeezerTrackId('invalid-url')).toBeUndefined();
      expect(extractDeezerTrackId('')).toBeUndefined();
    });
  });

  describe('getDeezerAlbumArtwork', () => {
    it('should return artwork URL for valid album ID', async () => {
      const mockAlbum = {
        id: 302127,
        title: 'Discovery',
        cover_big: 'https://cdn-images.dzcdn.net/images/cover/5718f7c81c27e0b2417e2a4c45224f8a/500x500-000000-80-0-0.jpg'
      };

      mockedAxios.get.mockResolvedValue({ data: mockAlbum });

      const result = await getDeezerAlbumArtwork(302127);

      expect(result).toBe(mockAlbum.cover_big);
      expect(mockedAxios.get).toHaveBeenCalledWith('https://api.deezer.com/album/302127', {
        timeout: 5000,
        headers: { 'User-Agent': 'TuneTussle/1.0' }
      });
    });

    it('should return undefined for invalid album ID', async () => {
      mockedAxios.get.mockRejectedValue(new Error('Album not found'));
      const result = await getDeezerAlbumArtwork(999999999);
      expect(result).toBeUndefined();
    });
  });

  describe('fetchDeezerTrackWithArtwork', () => {
    it('should return track with album artwork', async () => {
      const mockSearchResponse = {
        data: {
          data: [
            {
              id: 3135556,
              title: 'Harder Better Faster Stronger',
              title_short: 'Harder Better Faster Stronger',
              duration: 225,
              preview: 'https://cdns-preview-d.dzcdn.net/stream/preview.mp3',
              artist: { id: 27, name: 'Daft Punk' },
              album: { id: 302127, title: 'Discovery', cover_big: 'https://artwork-url.jpg' }
            }
          ],
          total: 1
        }
      };

      mockedAxios.get.mockResolvedValue(mockSearchResponse);

      const result = await fetchDeezerTrackWithArtwork('Test Song', 'Test Artist');

      expect(result).toEqual({
        previewUrl: 'https://cdns-preview-d.dzcdn.net/stream/preview.mp3',
        albumArtwork: 'https://artwork-url.jpg',
        albumId: 302127
      });
    });

    it('should return undefined when no tracks found', async () => {
      mockedAxios.get.mockResolvedValue({ data: { data: [], total: 0 } });
      const result = await fetchDeezerTrackWithArtwork('Unknown Song', 'Unknown Artist');
      expect(result).toBeUndefined();
    });

    it('should handle missing album artwork gracefully', async () => {
      const mockResponseNoArtwork = {
        data: {
          data: [
            {
              id: 3135556,
              title: 'Test Song',
              title_short: 'Test Song',
              duration: 225,
              preview: 'https://cdns-preview-d.dzcdn.net/stream/preview.mp3',
              artist: { id: 27, name: 'Test Artist' },
              album: { id: 302127, title: 'Test Album' }
            }
          ],
          total: 1
        }
      };

      mockedAxios.get.mockResolvedValue(mockResponseNoArtwork);
      const result = await fetchDeezerTrackWithArtwork('Test Song', 'Test Artist');
      expect(result?.albumArtwork).toBeUndefined();
    });
  });
}); 