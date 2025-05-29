// deezerService.ts - Service for interacting with Deezer API

import axios from 'axios';

interface DeezerTrack {
  id: number;
  title: string;
  preview: string;
  artist: {
    name: string;
  };
  album: {
    id: number;
    cover_small?: string;
    cover_medium?: string;
    cover_big?: string;
    cover_xl?: string;
  };
}

interface DeezerSearchResponse {
  data: DeezerTrack[];
  total: number;
}

interface DeezerAlbum {
  id: number;
  title: string;
  cover_small?: string;
  cover_medium?: string;
  cover_big?: string;
  cover_xl?: string;
}

interface DeezerTrackWithArtwork {
  previewUrl: string;
  albumArtwork: string | undefined;
  albumId?: number;
}

// For testing - allows tests to inject a mock implementation
let _mockFetchDeezerPreviewUrl: ((title: string, artist: string) => Promise<string | undefined>) | null = null;

/**
 * Sets a mock implementation for fetchDeezerPreviewUrl (for testing)
 * @param mockFn Mock function or null to reset
 */
export function __setMockFetchDeezerPreviewUrl(mockFn: ((title: string, artist: string) => Promise<string | undefined>) | null): void {
  _mockFetchDeezerPreviewUrl = mockFn;
}

/**
 * Fetches a preview URL from Deezer
 * @param title Song title
 * @param artist Song artist
 * @returns Promise resolving to preview URL or undefined if not found
 */
export async function fetchDeezerPreviewUrl(title: string, artist: string): Promise<string | undefined> {
  // For testing - use mock if available
  if (_mockFetchDeezerPreviewUrl) {
    return _mockFetchDeezerPreviewUrl(title, artist);
  }

  try {
    console.log(`[Deezer] Searching for: "${title}" by "${artist}"`);
    
    const response = await axios.get<DeezerSearchResponse>('https://api.deezer.com/search', {
      params: { q: `${title} ${artist}`, limit: 10 },
      timeout: 10000,
      headers: {
        'User-Agent': 'TuneTussle/1.0'
      }
    });

    if (!response.data.data || response.data.data.length === 0) {
      console.log(`[Deezer] No results found for "${title} ${artist}"`);
      return undefined;
    }

    // Look for the best match
    for (const track of response.data.data) {
      // Check if preview is available (Deezer sometimes returns empty preview URLs)
      if (track.preview && track.preview.length > 0) {
        console.log(`[Deezer] ✓ Found preview: "${track.title}" by "${track.artist.name}"`);
        console.log(`[Deezer] Preview URL: ${track.preview}`);
        return track.preview;
      }
    }

    console.log(`[Deezer] ✗ No preview URLs available in ${response.data.data.length} results`);
    return undefined;
    
  } catch (error: unknown) {
    const errorMessage = error instanceof Error ? error.message : 'Unknown error';
    console.error(`[Deezer] Error searching for "${title} - ${artist}":`, errorMessage);
    return undefined;
  }
}

/**
 * Fetches track with artwork from Deezer
 * @param title Song title
 * @param artist Song artist
 * @returns Promise resolving to track with artwork or undefined if not found
 */
export async function fetchDeezerTrackWithArtwork(title: string, artist: string): Promise<DeezerTrackWithArtwork | undefined> {
  try {
    console.log(`[Deezer] Searching for track with artwork: "${title}" by "${artist}"`);
    
    const response = await axios.get<DeezerSearchResponse>('https://api.deezer.com/search', {
      params: { q: `${title} ${artist}`, limit: 5 },
      timeout: 10000,
      headers: {
        'User-Agent': 'TuneTussle/1.0'
      }
    });
    
    if (!response.data.data || response.data.data.length === 0) {
      console.log(`[Deezer] No results found for "${title} ${artist}"`);
      return undefined;
    }
    
    // Look for the best match with both preview and artwork
    for (const track of response.data.data) {
      if (track.preview && track.preview.length > 0) {
        const albumArtwork = track.album.cover_xl || track.album.cover_big || track.album.cover_medium || undefined;
        
        console.log(`[Deezer] ✓ Found track: "${track.title}" by "${track.artist.name}"`);
        console.log(`[Deezer] Preview URL: ${track.preview}`);
        console.log(`[Deezer] Artwork URL: ${albumArtwork || 'Not available'}`);
        
        return {
          previewUrl: track.preview,
          albumArtwork,
          albumId: track.album.id
        };
      }
    }
    
    console.log(`[Deezer] ✗ No suitable tracks found in ${response.data.data.length} results`);
    return undefined;
    
  } catch (error: unknown) {
    const errorMessage = error instanceof Error ? error.message : 'Unknown error';
    console.error(`[Deezer] Error fetching track with artwork for "${title} - ${artist}":`, errorMessage);
    return undefined;
  }
}

/**
 * Fetches album artwork URL from Deezer for a given track
 * @param title Song title
 * @param artist Song artist
 * @returns Promise resolving to artwork URL or undefined if not found
 */
export async function getDeezerArtworkUrl(title: string, artist: string): Promise<string | undefined> {
  try {
    console.log(`[Deezer] Searching for artwork: "${title}" by "${artist}"`);
    
    const response = await axios.get<DeezerSearchResponse>('https://api.deezer.com/search', {
      params: { q: `${title} ${artist}`, limit: 3 },
      timeout: 10000,
      headers: {
        'User-Agent': 'TuneTussle/1.0'
      }
    });
    
    if (!response.data.data || response.data.data.length === 0) {
      console.log(`[Deezer] No results found for artwork: "${title} ${artist}"`);
      return undefined;
    }
    
    // Return the highest quality artwork available
    const track = response.data.data[0];
    const artworkUrl = track.album.cover_xl || track.album.cover_big || track.album.cover_medium;
    
    if (artworkUrl) {
      console.log(`[Deezer] ✓ Found artwork: ${artworkUrl}`);
      return artworkUrl;
    }
    
    console.log(`[Deezer] ✗ No artwork available for track`);
    return undefined;
    
  } catch (error: unknown) {
    const errorMessage = error instanceof Error ? error.message : 'Unknown error';
    console.error(`[Deezer] Error fetching artwork for "${title} - ${artist}":`, errorMessage);
    return undefined;
  }
}

/**
 * Gets detailed track information from Deezer by track ID
 * @param trackId Deezer track ID
 * @returns Promise resolving to track details or undefined if not found
 */
export async function getDeezerTrackDetails(trackId: number): Promise<DeezerTrack | undefined> {
  try {
    const response = await axios.get<DeezerTrack>(`https://api.deezer.com/track/${trackId}`, {
      timeout: 5000,
      headers: {
        'User-Agent': 'TuneTussle/1.0'
      }
    });

    return response.data;
  } catch (error: any) {
    console.error(`[Deezer] Error fetching track ${trackId}:`, error.message);
    return undefined;
  }
}

/**
 * Utility function to extract track ID from Deezer URL
 * @param url Deezer track URL
 * @returns Track ID or undefined if not found
 */
export function extractDeezerTrackId(url: string): number | undefined {
  const match = url.match(/deezer\.com\/track\/(\d+)/);
  return match ? parseInt(match[1], 10) : undefined;
}

/**
 * Gets album artwork URL from Deezer
 * @param albumId Deezer album ID
 * @param size Album cover size ('small', 'medium', 'big', 'xl')
 * @returns Promise resolving to artwork URL or undefined if not found
 */
export async function getDeezerAlbumArtwork(albumId: number, size: 'small' | 'medium' | 'big' | 'xl' = 'big'): Promise<string | undefined> {
  try {
    const response = await axios.get<DeezerAlbum>(`https://api.deezer.com/album/${albumId}`, {
      timeout: 5000,
      headers: {
        'User-Agent': 'TuneTussle/1.0'
      }
    });

    const album = response.data;
    
    // Return the appropriate size artwork URL
    switch (size) {
      case 'small': return album.cover_small;
      case 'medium': return album.cover_medium;
      case 'big': return album.cover_big;
      case 'xl': return album.cover_xl;
      default: return album.cover_big;
    }
  } catch (error: any) {
    console.error(`[Deezer] Error fetching album artwork for album ${albumId}:`, error.message);
    return undefined;
  }
} 