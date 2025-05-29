// gameRoundService.ts - Manages game round functionality like buzzer and answer handling

import type { GameRound } from '../models/types';
import {
  normalizeSongOrArtist,
  getCorrectAnswerMessage,
  calculateStringSimilarity,
} from '../utils/gameUtils';

/** Similarity threshold levels for fuzzy matching. */
type SimilarityLevel =
  | 'EXACT_MATCH'
  | 'CLOSE_MATCH'
  | 'PARTIAL_MATCH'
  | 'ARTIST_NAME_MATCH';

/** Map of similarity thresholds used during answer evaluation. */
const SIMILARITY_THRESHOLDS: Record<SimilarityLevel, number> = {
  EXACT_MATCH: 0.9,          // Very high similarity (almost exact)
  CLOSE_MATCH: 0.75,         // Forgiving for small typos
  PARTIAL_MATCH: 0.7,        // Partial match
  ARTIST_NAME_MATCH: 0.65,   // Looser match for artist names
};

/** Input shape for a player's answer attempt. */
export interface AnswerInput {
  songTitle: string;
  artist: string;
}

/** Shape of the correct answer. */
export interface CorrectAnswer {
  title: string;
  artist: string;
}

/** Result returned from answer evaluation. */
export interface EvaluateAnswerResponse {
  correctSong: boolean;
  correctArtist: boolean;
  score: number;
  feedback: {
    message: string;
    points: number;
    status: 'correct' | 'partially-correct' | 'incorrect';
  };
}

// --- Logic ---
/**
 * Handles a player's failed buzz-in attempt.
 * Mutates the round state by recording the attempt and updating buzzer flags.
 * @param round - The current game round object.
 * @param player - The ID of the player who failed.
 * @param nonJudgeParticipants - List of participant IDs allowed to buzz.
 * @returns A message for the UI and whether everyone has now attempted.
 */
export function handleAttemptFailure(
  round: GameRound,
  player: string,
  nonJudgeParticipants: readonly string[]
): { attemptFailedMessage: string; allAttempted: boolean } {
  round.playersWhoAttempted.add(player);
  const allAttempted =
    nonJudgeParticipants.length > 0 &&
    nonJudgeParticipants.every((p) => round.playersWhoAttempted.has(p));

  // Reset buzz state
  round.buzzedInPlayer = null;
  round.isBuzzerOpenForNewAttempts = !allAttempted;

  const attemptFailedMessage = allAttempted
    ? `Player ${player} was incorrect. All players have now attempted. The buzzer is now closed for this round.`
    : `Player ${player} was incorrect. The buzzer is open again for other players.`;

  return { attemptFailedMessage, allAttempted };
}

function appendPointsToMessage(message: string, score: number): string {
  if (score > 0) {
    return `${message} You earned ${score} point${score === 1 ? '' : 's'}!`;
  }
  return message;
}

/**
 * Evaluates a player's song/artist answer with fuzzy matching.
 * @param answer - The user's attempted songTitle and artist.
 * @param correctAnswer - The true song title and artist.
 * @returns Flags for correctSong and correctArtist, total score, and feedback message.
 */
export function evaluateAnswer(
  answer: AnswerInput,
  correctAnswer: CorrectAnswer
): EvaluateAnswerResponse {
  // Normalize inputs for comparison
  const ansTitle = normalizeSongOrArtist(answer.songTitle);
  const ansArtist = normalizeSongOrArtist(answer.artist);
  const corrTitle = normalizeSongOrArtist(correctAnswer.title);
  const corrArtist = normalizeSongOrArtist(correctAnswer.artist);

  // Split combined entries if user included both song and artist in one field
  const titleParts = ansTitle.split(',').map((s) => s.trim());
  const artistParts = ansArtist.split(',').map((s) => s.trim());

  // Base similarity scores
  let titleSim = ansTitle ? calculateStringSimilarity(ansTitle, corrTitle) : 0;
  let artistSim = ansArtist ? calculateStringSimilarity(ansArtist, corrArtist) : 0;
  const crossTitleSim = ansTitle ? calculateStringSimilarity(ansTitle, corrArtist) : 0;
  const crossArtistSim = ansArtist ? calculateStringSimilarity(ansArtist, corrTitle) : 0;

  // Check each split part to find a higher similarity
  for (const part of [...titleParts, ...artistParts]) {
    if (!part) continue;
    titleSim = Math.max(
      titleSim,
      calculateStringSimilarity(part, corrTitle),
      crossArtistSim
    );
    artistSim = Math.max(
      artistSim,
      calculateStringSimilarity(part, corrArtist),
      crossTitleSim
    );
  }

  // Determine correctness based on thresholds
  const songMatch = titleSim >= SIMILARITY_THRESHOLDS.CLOSE_MATCH;
  const artistMatch = artistSim >= SIMILARITY_THRESHOLDS.ARTIST_NAME_MATCH;

  const correctSong = Boolean(ansTitle) && songMatch;
  const correctArtist = Boolean(ansArtist) && artistMatch;

  // Score: 10 points per correct field
  const score = (correctSong ? 10 : 0) + (correctArtist ? 10 : 0);
  let message = '';
  let status: 'correct' | 'partially-correct' | 'incorrect';
  if (correctSong && correctArtist) {
    message = 'You got both the song and artist right.';
    status = 'correct';
  } else if (correctSong) {
    message = 'You got the song title right but missed the artist.';
    status = 'partially-correct';
  } else if (correctArtist) {
    message = 'You got the artist right but missed the song title.';
    status = 'partially-correct';
  } else {
    message = 'Sorry, that\'s incorrect.';
    status = 'incorrect';
  }
  
  return {
    correctSong,
    correctArtist,
    score,
    feedback: {
      message,
      points: score,
      status,
    },
  };
}

/**
 * Prepares a fresh GameRound for the next song.
 * Ensures all required fields are present for the frontend contract.
 * Throws if required fields are missing.
 */
export function prepareNextRound(
  _currentRound: GameRound, // Parameter is kept for API compatibility, but not used.
  nextSong: { title: string; artist: string; trackUrl?: string }
): GameRound {
  if (!nextSong.title || !nextSong.artist) {
    throw new Error('prepareNextRound: Song must have title and artist');
  }
  if (!nextSong.trackUrl) {
    throw new Error('prepareNextRound: Song must have trackUrl (audio URL)');
  }
  return {
    songTitle: nextSong.title,
    artist: nextSong.artist,
    trackUrl: nextSong.trackUrl,
    answers: [],
    buzzedInPlayer: null,
    playersWhoAttempted: new Set<string>(),
    isBuzzerOpenForNewAttempts: true,
    // Add any new required fields here as needed
  };
} 