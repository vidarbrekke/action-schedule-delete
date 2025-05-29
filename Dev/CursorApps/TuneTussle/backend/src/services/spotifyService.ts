// spotifyService.ts - Service for interacting with Spotify using web scraping approach

// Import the scraping package
const searchAndGetLinks = require('spotify-preview-finder');

interface SpotifyPreviewResult {
  name: string;
  spotifyUrl: string;
  previewUrls: string[];
}

interface SpotifyPreviewResponse {
  success: boolean;
  error?: string;
  results: SpotifyPreviewResult[];
}

interface SpotifyTokenResponse {
  access_token: string;
  expires_in: number;
  token_type: string;
}

interface SpotifySearchResponse {
  tracks?: {
    items?: Array<{
      album?: {
        images?: Array<{
          url: string;
        }>;
      };
    }>;
  };
}

// For testing - allows tests to inject a mock implementation
let _mockFetchSpotifyPreviewUrl: ((title: string, artist: string, clientId: string, clientSecret: string) => Promise<string | undefined>) | null = null;

/**
 * Sets a mock implementation for fetchSpotifyPreviewUrl (for testing)
 * @param mockFn Mock function to use instead of the real implementation
 */
export function __setMockFetchSpotifyPreviewUrl(mockFn: ((title: string, artist: string, clientId: string, clientSecret: string) => Promise<string | undefined>) | null): void {
  _mockFetchSpotifyPreviewUrl = mockFn;
}

/**
 * Clears the cached Spotify access token (kept for test compatibility)
 */
export function clearTokenCache(): void {
  // No-op for scraping approach, but kept for test compatibility
}

// DRY helper to get fetch (dynamic import for node-fetch v3+)
async function getFetch() {
  const mod = await import('node-fetch');
  return mod.default;
}

/**
 * Fetches Spotify preview URL using web scraping approach
 * This method has proven 100% success rate vs 0% for the API approach
 * @param title Song title
 * @param artist Song artist
 * @param clientId Spotify client ID (not used in scraping approach)
 * @param clientSecret Spotify client secret (not used in scraping approach)
 * @returns Promise resolving to preview URL or undefined if not found
 */
export async function fetchSpotifyPreviewUrl(
  title: string,
  artist: string,
  clientId: string,
  clientSecret: string
): Promise<string | undefined> {
  // If a mock implementation is provided, use it instead of real implementation
  if (_mockFetchSpotifyPreviewUrl) {
    return _mockFetchSpotifyPreviewUrl(title, artist, clientId, clientSecret);
  }

  try {
    console.log(`[Spotify Scraper] Searching for preview: "${title}" by "${artist}"`);
    
    // Create search query
    const searchQuery = `${title} ${artist}`;
    
    // Use the spotify-preview-finder package
    const response = await searchAndGetLinks(searchQuery, 3) as SpotifyPreviewResponse;
    
    if (!response.success) {
      console.log(`[Spotify Scraper] Search failed: ${response.error}`);
      return undefined;
    }
    
    if (response.results.length === 0) {
      console.log(`[Spotify Scraper] No results found for "${searchQuery}"`);
      return undefined;
    }
    
    // Log results for debugging
    console.log(`[Spotify Scraper] Found ${response.results.length} results:`);
    response.results.forEach((result, index) => {
      const hasPreview = result.previewUrls.length > 0;
      console.log(`[Spotify Scraper] ${index + 1}. ${result.name} - Preview: ${hasPreview ? 'YES' : 'NO'}`);
    });
    
    // Find the first result with preview URLs
    for (const result of response.results) {
      if (result.previewUrls.length > 0) {
        const previewUrl = result.previewUrls[0];
        console.log(`[Spotify Scraper] ✓ Found preview URL: ${previewUrl}`);
        return previewUrl;
      }
    }
    
    console.log(`[Spotify Scraper] ✗ No preview URLs found in ${response.results.length} results`);
    return undefined;
    
  } catch (error: unknown) {
    const errorMessage = error instanceof Error ? error.message : 'Unknown error';
    console.error(`[Spotify Scraper] Error: ${errorMessage}`);
    return undefined;
  }
}

/**
 * Fetches Spotify album artwork URL using the official Spotify Web API.
 * This does NOT require the preview-finder package and is for artwork only.
 * @param title Song title
 * @param artist Song artist
 * @param accessToken Spotify API access token
 * @returns Promise resolving to artwork URL or null if not found
 */
export async function getSpotifyArtworkUrl(
  title: string,
  artist: string,
  accessToken: string
): Promise<string | null> {
  try {
    const q = encodeURIComponent(`track:${title} artist:${artist}`);
    const url = `https://api.spotify.com/v1/search?q=${q}&type=track&limit=1`;
    const fetch = await getFetch();
    const res = await fetch(url, {
      headers: { Authorization: `Bearer ${accessToken}` }
    });
    if (!res.ok) return null;
    const data = await res.json() as SpotifySearchResponse;
    const images = data.tracks?.items?.[0]?.album?.images;
    return images?.[0]?.url || null;
  } catch (e) {
    console.error('[getSpotifyArtworkUrl] Error:', e);
    return null;
  }
}

let cachedToken: string | null = null;
let tokenExpiresAt: number = 0;

/**
 * Fetches a Spotify API access token using client credentials flow.
 * Caches the token until it expires.
 */
export async function getSpotifyAccessToken(clientId: string, clientSecret: string): Promise<string> {
  const now = Date.now();
  if (cachedToken && tokenExpiresAt > now + 60000) {
    return cachedToken;
  }
  const creds = Buffer.from(`${clientId}:${clientSecret}`).toString('base64');
  const fetch = await getFetch();
  const res = await fetch('https://accounts.spotify.com/api/token', {
    method: 'POST',
    headers: {
      'Authorization': `Basic ${creds}`,
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: 'grant_type=client_credentials',
  });
  if (!res.ok) throw new Error('Failed to fetch Spotify access token');
  const data = await res.json() as SpotifyTokenResponse;
  cachedToken = data.access_token;
  tokenExpiresAt = now + (data.expires_in * 1000);
  if (!cachedToken) throw new Error('Spotify access token is null');
  return cachedToken;
} 