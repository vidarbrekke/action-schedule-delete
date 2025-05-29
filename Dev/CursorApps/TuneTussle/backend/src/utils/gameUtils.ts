// gameUtils.ts - Refactored game utility functions

import { randomInt } from 'crypto';
import { GameRound, GameSession, Song } from '../models/types';

// Constants
const DEFAULT_CODE_LENGTH = 6;
const CODE_CHARSET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

// Precompiled regex patterns
const NON_WORD_REGEX = /[^\w\s]+/g;
const MULTI_SPACE_REGEX = /\s+/g;

// Whitelist of valid music service hostnames
const VALID_HOSTS = [
  'youtube.com',
  'youtu.be',
  'spotify.com',
  'open.spotify.com',
  'music.apple.com',
  'soundcloud.com',
] as const;

type ValidHost = typeof VALID_HOSTS[number];

/** Default durations and counts */
export const ANSWER_TIMER_DURATION_MS = 30_000;
export const ROUND_DURATION_MS = 60_000;
export const DEFAULT_ROUND_COUNT = 5;

/**
 * Generate a random alphanumeric code.
 * Uses crypto.randomInt for uniform randomness.
 */
export function generateCode(length = DEFAULT_CODE_LENGTH, charset = CODE_CHARSET): string {
  return Array.from({ length }, () => charset.charAt(randomInt(0, charset.length))).join('');
}

/**
 * Normalize text: lowercase, trim, remove special chars, collapse spaces.
 */
export function normalizeText(text: string): string {
  return text
    .toLowerCase()
    .trim()
    .replace(NON_WORD_REGEX, '')
    .replace(MULTI_SPACE_REGEX, ' ');
}

/**
 * Get message based on correct song/artist flags.
 */
export function getCorrectAnswerMessage(correctSong: boolean, correctArtist: boolean): string {
  if (correctSong && correctArtist) {
    return 'Correct! You got both the song and artist right. +20 points! Buzzer closed for this round.';
  }
  if (correctSong) {
    return 'Partially correct! You got the song title right but missed the artist. +10 points! Buzzer closed for this round.';
  }
  if (correctArtist) {
    return 'Partially correct! You got the artist right but missed the song title. +10 points! Buzzer closed for this round.';
  }
  return "Sorry, that's incorrect. The buzzer is open again for other players.";
}

/**
 * Validate if link hostname matches known music services.
 */
export function validateSongLink(link: string): boolean {
  try {
    const { hostname } = new URL(link);
    return VALID_HOSTS.some(host => hostname.includes(host));
  } catch {
    return false;
  }
}

/**
 * Compute Levenshtein distance with two rolling arrays for memory efficiency.
 */
function levenshteinDistance(a: string, b: string): number {
  const s = normalizeText(a);
  const t = normalizeText(b);
  const m = s.length, n = t.length;
  if (m === 0) return n;
  if (n === 0) return m;

  let prev = Array.from({ length: n + 1 }, (_, i) => i);
  let curr = new Array<number>(n + 1);

  for (let i = 1; i <= m; i++) {
    curr[0] = i;
    for (let j = 1; j <= n; j++) {
      const cost = s[i - 1] === t[j - 1] ? 0 : 1;
      curr[j] = Math.min(prev[j] + 1, curr[j - 1] + 1, prev[j - 1] + cost);
    }
    [prev, curr] = [curr, prev];
  }

  return prev[n];
}

/**
 * Calculate similarity score between two strings (0-1).
 */
export function calculateStringSimilarity(a: string, b: string): number {
  const maxLen = Math.max(a.length, b.length);
  if (maxLen === 0) return 1;
  if (maxLen < 3) return normalizeText(a) === normalizeText(b) ? 1 : 0;
  const distance = levenshteinDistance(a, b);
  return 1 - distance / maxLen;
}

/**
 * Find best string match above threshold.
 */
export function findBestStringMatch(
  query: string,
  possibleMatches: string[],
  threshold = 0.7
): { match: string; similarity: number } | null {
  if (!query || possibleMatches.length === 0) return null;

  const best = possibleMatches.reduce<{ match: string; similarity: number } | null>(
    (bestSoFar, candidate) => {
      const sim = calculateStringSimilarity(query, candidate);
      if (!bestSoFar || sim > bestSoFar.similarity) {
        return { match: candidate, similarity: sim };
      }
      return bestSoFar;
    },
    null
  );

  return best && best.similarity >= threshold ? best : null;
}

export { normalizeSongOrArtist } from './normalize'; 