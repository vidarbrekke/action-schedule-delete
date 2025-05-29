// Utility for DRY, YAGNI-compliant test song creation
// Use this for ALL test and mock song objects to ensure required fields are always present and in sync with production logic.

import type { GameSong } from '../src/models/types';

/**
 * Create a single test GameSong with all required fields.
 */
export function makeTestSong(title: string, artist: string, overrides: Partial<GameSong> = {}): GameSong {
  return {
    title,
    artist,
    trackUrl: 'mock://audio.mp3',
    used: false,
    ...overrides,
  };
}

/**
 * Create an array of test GameSongs from arrays of titles/artists.
 */
export function makeTestSongs(songs: Array<{ title: string; artist: string }>, overrides?: Partial<GameSong>): GameSong[] {
  return songs.map(({ title, artist }) => makeTestSong(title, artist, overrides));
} 