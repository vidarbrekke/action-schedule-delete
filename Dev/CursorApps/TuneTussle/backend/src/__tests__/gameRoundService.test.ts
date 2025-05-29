import {
  evaluateAnswer,
  handleAttemptFailure,
  prepareNextRound,
  type AnswerInput,
  type CorrectAnswer,
  type EvaluateAnswerResponse,
} from '../services/gameRoundService';
import type { GameRound } from '../models/types';

/** Helper to build a mock GameRound with defaults. */
function createMockRound(
  overrides: Partial<GameRound> = {}
): GameRound {
  return {
    songTitle: overrides.songTitle ?? '',
    artist: overrides.artist ?? '',
    trackUrl: overrides.trackUrl,
    answers: overrides.answers ?? [],
    buzzedInPlayer: overrides.buzzedInPlayer ?? null,
    playersWhoAttempted: overrides.playersWhoAttempted ?? new Set<string>(),
    isBuzzerOpenForNewAttempts: overrides.isBuzzerOpenForNewAttempts ?? true,
  };
}

describe('gameRoundService', () => {
  describe('evaluateAnswer', () => {
    it('should mark correctSong true for exact title match', () => {
      const input: AnswerInput = { songTitle: 'Hello', artist: '' };
      const correct: CorrectAnswer = { title: 'hello', artist: '' };
      const result: EvaluateAnswerResponse = evaluateAnswer(input, correct);
      expect(result.correctSong).toBe(true);
      expect(result.correctArtist).toBe(false);
      expect(result.score).toBe(10);
    });

    it('should give score 0 for completely wrong answer', () => {
      const result = evaluateAnswer(
        { songTitle: 'Foo', artist: 'Bar' },
        { title: 'hello', artist: 'world' }
      );
      expect(result.correctSong).toBe(false);
      expect(result.correctArtist).toBe(false);
      expect(result.score).toBe(0);
    });

    it('should give full score when both song and artist are correct', () => {
      const result = evaluateAnswer(
        { songTitle: 'Hey Jude', artist: 'The Beatles' },
        { title: 'Hey Jude', artist: 'The Beatles' }
      );
      expect(result.correctSong).toBe(true);
      expect(result.correctArtist).toBe(true);
      expect(result.score).toBe(20);
      expect(result.feedback.status).toBe('correct');
      expect(result.feedback.message).toBe('You got both the song and artist right.');
    });

    it('should handle case-insensitive matching correctly', () => {
      const result = evaluateAnswer(
        { songTitle: 'hey jude', artist: 'the beatles' },
        { title: 'Hey Jude', artist: 'The Beatles' }
      );
      expect(result.correctSong).toBe(true);
      expect(result.correctArtist).toBe(true);
      expect(result.score).toBe(20);
    });

    it('should only give artist points when song is wrong but artist is right', () => {
      const result = evaluateAnswer(
        { songTitle: 'Wrong Song', artist: 'The Beatles' },
        { title: 'Hey Jude', artist: 'The Beatles' }
      );
      expect(result.correctSong).toBe(false);
      expect(result.correctArtist).toBe(true);
      expect(result.score).toBe(10);
      expect(result.feedback.status).toBe('partially-correct');
    });
  });

  describe('handleAttemptFailure', () => {
    it('closes buzzer when all participants have attempted', () => {
      const round = createMockRound({
        playersWhoAttempted: new Set<string>(['Bob']),
        buzzedInPlayer: 'Alice',
        isBuzzerOpenForNewAttempts: false,
      });
      const { attemptFailedMessage, allAttempted } = handleAttemptFailure(
        round,
        'Alice',
        ['Alice', 'Bob']
      );

      expect(round.playersWhoAttempted.has('Alice')).toBe(true);
      expect(round.buzzedInPlayer).toBeNull();
      expect(round.isBuzzerOpenForNewAttempts).toBe(false);
      expect(allAttempted).toBe(true);
      expect(attemptFailedMessage).toMatch(/closed/);
    }); 

    it('reopens buzzer if some participants haven\'t attempted', () => {
      const round = createMockRound({
        playersWhoAttempted: new Set<string>([]),
        buzzedInPlayer: 'Alice',
        isBuzzerOpenForNewAttempts: false,
      });
      const { allAttempted } = handleAttemptFailure(
        round,
        'Alice',
        ['Alice', 'Bob']
      );
      expect(allAttempted).toBe(false);
      expect(round.isBuzzerOpenForNewAttempts).toBe(true);
    });
  });

  describe('prepareNextRound', () => {
    it('initializes a fresh round with given song data', () => {
      const prev = createMockRound({
        songTitle: 'Old',
        artist: 'Artist',
        playersWhoAttempted: new Set(['Bob']),
        isBuzzerOpenForNewAttempts: false,
      });
      const nextSong = { title: 'New', artist: 'Artist2', trackUrl: 'link' };
      const newRound = prepareNextRound(prev, nextSong);

      expect(newRound.songTitle).toBe('New');
      expect(newRound.artist).toBe('Artist2');
      expect(newRound.trackUrl).toBe('link');
      expect(newRound.playersWhoAttempted.size).toBe(0);
      expect(newRound.isBuzzerOpenForNewAttempts).toBe(true);
    });
  });
}); 