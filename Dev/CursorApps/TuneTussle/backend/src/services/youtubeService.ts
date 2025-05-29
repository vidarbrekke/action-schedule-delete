// youtubeService.ts - Service for interacting with YouTube API

import { google, youtube_v3 } from 'googleapis';
import { Song } from '../models/types';

// For testing - allows tests to inject a mock implementation
let _mockFetchYouTubeLink: ((title: string, artist: string, apiKey: string) => Promise<string | undefined>) | null = null;
let _mockEnrichSongsWithYouTubeLinks: ((songs: Array<Song>, apiKey: string | undefined | null) => Promise<Array<Song>>) | null = null;

/**
 * Sets a mock implementation for fetchYouTubeLink (for testing)
 * @param mockFn Mock function to use instead of the real implementation
 */
export function __setMockFetchYouTubeLink(mockFn: ((title: string, artist: string, apiKey: string) => Promise<string | undefined>) | null): void {
  _mockFetchYouTubeLink = mockFn;
}

/**
 * Sets a mock implementation for enrichSongsWithYouTubeLinks (for testing)
 * @param mockFn Mock function to use instead of the real implementation
 */
export function __setMockEnrichSongsWithYouTubeLinks(mockFn: ((songs: Array<Song>, apiKey: string | undefined | null) => Promise<Array<Song>>) | null): void {
  _mockEnrichSongsWithYouTubeLinks = mockFn
    ? (songs, apiKey) => mockFn(songs, apiKey).then(result => result.map(song => ({ ...song, trackUrl: song.trackUrl, used: false })))
    : null;
}

/**
 * Converts a YouTube URL to an embed URL, or returns null if invalid.
 */
export function toYouTubeEmbedUrl(url: string): string | null {
  if (!url) return null;
  let hostname = '';
  try {
    const parsed = new URL(url);
    hostname = parsed.hostname.replace(/^www\./, '');
    if (hostname !== 'youtube.com' && hostname !== 'youtu.be') {
      return null;
    }
  } catch {
    // If not a valid URL, fall through to regex (legacy support)
    // But if it doesn't match, will return null below
  }
  // Regex to extract YouTube video ID from various URL formats
  const youtubeRegex = /^.*(?:(?:youtu\.be\/|v\/|vi\/|u\/\\w\/|embed\/|watch\?v(?:i)?=|\&v(?:i)?=))([^#\&\?]*).*/;
  const match = url.match(youtubeRegex);
  if (match && match[1] && match[1].length === 11) {
    return `https://www.youtube.com/embed/${match[1]}`;
  }
  // Fallback for direct embed links
  try {
    const videoUrl = new URL(url);
    if ((videoUrl.hostname === 'www.youtube.com' || videoUrl.hostname === 'youtube.com') &&
        videoUrl.pathname.startsWith('/embed/')) {
      const videoId = videoUrl.pathname.substring('/embed/'.length).split('/')[0];
      if (videoId && videoId.length === 11) {
        return `https://www.youtube.com/embed/${videoId}`;
      }
    }
  } catch { /* ignore */ }
  return null;
}

/**
 * DRY: Returns songs with { ...song, trackUrl, used: false } for compatibility with all consumers and tests.
 * Enriches an array of songs with YouTube links (as embed URLs)
 * @param songs Array of songs to enrich
 * @param apiKey YouTube API key
 * @returns Promise resolving to enriched songs
 */
export async function enrichSongsWithYouTubeLinks(songs: Array<Song>, apiKey: string | undefined | null): Promise<Array<Song>> {
  // If a mock implementation is provided, use it instead of real implementation
  if (_mockEnrichSongsWithYouTubeLinks) {
    return _mockEnrichSongsWithYouTubeLinks(songs, apiKey);
  }

  // If no API key or empty songs list, just return the songs as-is
  if (!apiKey || songs.length === 0) {
    console.warn('[YouTube] YouTube API key is not configured or song list is empty. Proceeding without YouTube links.');
    return songs.map(song => ({ ...song, trackUrl: undefined, used: false }));
  }

  // Process each song to add a YouTube embed link
  const enrichedSongs = await Promise.all(
    songs.map(async song => {
      try {
        const youtubeLink = await fetchYouTubeLink(song.title, song.artist, apiKey!);
        const embedUrl = youtubeLink ? toYouTubeEmbedUrl(youtubeLink) : null;
        return { ...song, trackUrl: embedUrl || undefined, used: false };
      } catch (err) {
        console.error(`[YouTube] Error enriching song: ${song.title} by ${song.artist}`, err);
        return { ...song, trackUrl: undefined, used: false };
      }
    })
  );

  return enrichedSongs;
}

/**
 * Fetches a YouTube link for a song
 * @param title Song title
 * @param artist Song artist
 * @param apiKey YouTube API key
 * @returns Promise resolving to YouTube link or undefined if not found
 */
export async function fetchYouTubeLink(title: string, artist: string, apiKey: string): Promise<string | undefined> {
  // If a mock implementation is provided, use it instead of real implementation
  if (_mockFetchYouTubeLink) {
    return _mockFetchYouTubeLink(title, artist, apiKey);
  }

  if (!apiKey) {
    console.error('[YouTube] Cannot fetch YouTube link: API key is not provided');
    return undefined;
  }

  // Create search query
  const searchQuery = `${title} ${artist} official audio`;
  
  try {
    // Import googleapis dynamically
    const { google } = await import('googleapis');
    
    // Create YouTube client
    const youtube = google.youtube({
      version: 'v3',
      auth: apiKey
    });
    
    // Search for videos
    const response = await youtube.search.list({
      part: ['snippet'],
      q: searchQuery,
      type: ['video'],
      videoCategoryId: '10', // Music category
      maxResults: 1
    });
    
    // Extract video ID from results
    if (response.data.items && response.data.items.length > 0 && response.data.items[0].id?.videoId) {
      const foundUrl = `https://www.youtube.com/watch?v=${response.data.items[0].id.videoId}`;
      return foundUrl;
    }
    return undefined;
  } catch (error: any) {
    console.error(`[YouTube] Error searching for video "${searchQuery}":`, error.message);
    return undefined;
  }
} 