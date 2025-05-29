import { fetchYouTubeLinkByScraping } from './youtubeScrapeService';
import { fetchYouTubeLink } from './youtubeService'; // API-based service
import { fetchSpotifyPreviewUrl } from './spotifyService';
import { fetchDeezerPreviewUrl } from './deezerService';
import env from '../utils/envConfig';

export type MusicProvider = 'youtube' | 'spotify' | 'deezer';

interface ProviderConfig {
  provider: MusicProvider;
  credentials: Record<string, string | undefined>;
  fetchMethod?: string;
}

interface TrackResult {
  url?: string;
  provider: MusicProvider;
  error?: string;
}

/**
 * Validates provider configuration and credentials
 */
function validateProviderConfig(provider: MusicProvider): { isValid: boolean; error?: string } {
  switch (provider) {
    case 'youtube':
      const fetchMethod = process.env.YOUTUBE_FETCH_METHOD || 'SCRAPE';
      if (fetchMethod === 'API' && !env.YOUTUBE_API_KEY) {
        return { isValid: false, error: 'YouTube API key is required when YOUTUBE_FETCH_METHOD is set to API' };
      }
      return { isValid: true };
    
    case 'spotify':
      if (!env.SPOTIFY_CLIENT_ID || !env.SPOTIFY_CLIENT_SECRET) {
        return { isValid: false, error: 'Spotify client credentials are required' };
      }
      return { isValid: true };
    
    case 'deezer':
      // Deezer search API is public and doesn't require authentication
      return { isValid: true };
    
    default:
      return { isValid: false, error: `Music provider "${provider}" not supported` };
  }
}

/**
 * Fetches track from YouTube provider
 */
async function fetchFromYouTube(title: string, artist: string, apiKey?: string): Promise<TrackResult> {
  const fetchMethod = process.env.YOUTUBE_FETCH_METHOD || 'SCRAPE';
  const useApiMethod = fetchMethod === 'API';

  try {
    if (useApiMethod) {
      if (!apiKey?.trim()) {
        throw new Error('YouTube API key required');
      }
      const url = await fetchYouTubeLink(title, artist, apiKey);
      return { url, provider: 'youtube' };
    } else {
      const url = await fetchYouTubeLinkByScraping(title, artist);
      return { url: url || undefined, provider: 'youtube' };
    }
  } catch (error: any) {
    console.error(`[YouTube ${useApiMethod ? 'API' : 'Scrape'}] Error fetching "${title} - ${artist}":`, error.message);
    if (useApiMethod) {
      // Propagate API errors
      throw error;
    }
    // Return undefined for scraping errors (graceful degradation)
    return { provider: 'youtube', error: error.message };
  }
}

/**
 * Fetches track from Spotify provider
 */
async function fetchFromSpotify(title: string, artist: string): Promise<TrackResult> {
  try {
    const url = await fetchSpotifyPreviewUrl(title, artist, env.SPOTIFY_CLIENT_ID!, env.SPOTIFY_CLIENT_SECRET!);
    return { url, provider: 'spotify' };
  } catch (error: any) {
    console.error(`[Spotify] Error fetching "${title} - ${artist}":`, error.message);
    return { provider: 'spotify', error: error.message };
  }
}

/**
 * Fetches track from Deezer provider
 */
async function fetchFromDeezer(title: string, artist: string): Promise<TrackResult> {
  try {
    const url = await fetchDeezerPreviewUrl(title, artist);
    return { url, provider: 'deezer' };
  } catch (error: any) {
    console.error(`[Deezer] Error fetching "${title} - ${artist}":`, error.message);
    return { provider: 'deezer', error: error.message };
  }
}

/**
 * Universal function to fetch track links from any supported music provider with automatic fallback
 * @param title Song title
 * @param artist Song artist
 * @param provider Primary music provider to use
 * @param apiKey Optional API key (required for YouTube API method)
 * @returns Promise resolving to track URL or undefined if not found
 */
export async function fetchTrackLink(
  title: string,
  artist: string,
  provider: MusicProvider = 'youtube',
  apiKey?: string
): Promise<string | undefined> {
  // Validate inputs
  if (!title?.trim() || !artist?.trim()) {
    console.warn(`[Music Provider] Invalid inputs: title="${title}", artist="${artist}"`);
    return undefined;
  }

  // Validate provider configuration
  const validation = validateProviderConfig(provider);
  if (!validation.isValid) {
    console.error(`[Music Provider] Configuration error: ${validation.error}`);
    // Throw for unsupported providers or missing API configurations
    if (validation.error?.includes('not supported') || 
        (provider === 'youtube' && process.env.YOUTUBE_FETCH_METHOD === 'API')) {
      throw new Error(validation.error);
    }
    return undefined;
  }

  console.log(`[Music Provider] Fetching from ${provider}: "${title}" by "${artist}"`);

  let result: TrackResult;
  
  switch (provider) {
    case 'youtube':
      result = await fetchFromYouTube(title, artist, apiKey);
      break;
    case 'spotify':
      result = await fetchFromSpotify(title, artist);
      break;
    case 'deezer':
      result = await fetchFromDeezer(title, artist);
      break;
    default:
      throw new Error(`Music provider "${provider}" not supported`);
  }

  if (result.url) {
    console.log(`[Music Provider] ✓ Found track URL from ${result.provider}`);
    return result.url;
  } else {
    console.log(`[Music Provider] ✗ No track URL found from ${result.provider}${result.error ? `: ${result.error}` : ''}`);
    
    // Enhanced fallback system: try all other providers in order
    const allProviders: MusicProvider[] = ['deezer', 'spotify', 'youtube'];
    const fallbackProviders = allProviders.filter(p => p !== provider);
    
    // Try each fallback provider in order
    for (const fallbackProvider of fallbackProviders) {
      console.log(`[Music Provider] Attempting fallback to ${fallbackProvider} for: "${title}" by "${artist}"`);
      
      // Validate fallback provider configuration
      const fallbackValidation = validateProviderConfig(fallbackProvider);
      if (!fallbackValidation.isValid) {
        console.warn(`[Music Provider] ${fallbackProvider} fallback unavailable: ${fallbackValidation.error}`);
        continue;
      }
      
      try {
        let fallbackResult: TrackResult;
        
        switch (fallbackProvider) {
          case 'spotify':
            fallbackResult = await fetchFromSpotify(title, artist);
            break;
          case 'youtube':
            fallbackResult = await fetchFromYouTube(title, artist, apiKey);
            break;
          case 'deezer':
            fallbackResult = await fetchFromDeezer(title, artist);
            break;
          default:
            continue;
        }
        
        if (fallbackResult.url) {
          console.log(`[Music Provider] ✓ Found track URL from ${fallbackProvider} fallback`);
          return fallbackResult.url;
        } else {
          console.log(`[Music Provider] ✗ ${fallbackProvider} fallback also failed${fallbackResult.error ? `: ${fallbackResult.error}` : ''}`);
        }
      } catch (fallbackError: any) {
        console.warn(`[Music Provider] ${fallbackProvider} fallback error: ${fallbackError.message}`);
      }
    }
    
    console.log(`[Music Provider] ✗ All providers failed for: "${title}" by "${artist}"`);
    return undefined;
  }
} 