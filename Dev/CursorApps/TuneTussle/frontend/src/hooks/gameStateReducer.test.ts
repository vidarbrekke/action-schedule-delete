import { gameReducer, initialGameStateBase } from './gameStateReducer';
import type { GameState, Action } from './gameStateReducer';

describe('gameReducer', () => {
  let baseState: GameState;
  beforeEach(() => {
    baseState = {
      ...initialGameStateBase,
      gameCode: 'TEST123',
      isSocketConnected: true,
    } as GameState;
  });

  it('handles PLAYER_NAME_SET', () => {
    const action: Action = { type: 'PLAYER_NAME_SET', payload: 'Alice' };
    const state = gameReducer(baseState, action);
    expect(state.playerName).toBe('Alice');
    expect(state.isLoading).toBe(true);
  });

  it('handles JOIN_SUCCESS', () => {
    const action: Action = { type: 'JOIN_SUCCESS', payload: { role: 'participant' } };
    const state = gameReducer({ ...baseState, playerName: 'Bob' }, action);
    expect(state.hasJoinedGame).toBe(true);
    expect(state.isLoading).toBe(false);
    expect(state.scores.some(s => s.id === 'Bob')).toBe(true);
  });

  it('handles ROLE_DETERMINED', () => {
    const action: Action = { type: 'ROLE_DETERMINED', payload: 'judge' };
    const state = gameReducer(baseState, action);
    expect(state.role).toBe('judge');
  });

  it('handles GAME_STATE_INIT', () => {
    const action: Action = {
      type: 'GAME_STATE_INIT',
      payload: {
        participants: [{ id: 'p1', name: 'P1', score: 0, role: 'participant' }],
        gameSettings: { numberOfRounds: 5, prompt: 'Test prompt' },
      },
    };
    const state = gameReducer(baseState, action);
    expect(state.scores.length).toBe(1);
    expect(state.totalRounds).toBe(5);
    expect(state.prompt).toBe('Test prompt');
  });

  it('handles PARTICIPANTS_UPDATED', () => {
    const action: Action = {
      type: 'PARTICIPANTS_UPDATED',
      payload: [{ id: 'p2', name: 'P2', score: 10, role: 'participant' }],
    };
    const state = gameReducer(baseState, action);
    expect(state.scores.length).toBe(1);
    expect(state.scores[0].id).toBe('p2');
  });

  it('handles ROUND_STARTED', () => {
    const action: Action = {
      type: 'ROUND_STARTED',
      payload: {
        round: { songTitle: 'Song', artist: 'Artist', audioUrl: 'yt' },
        currentRoundNumber: 2,
        totalRounds: 5,
      },
    };
    const state = gameReducer(baseState, action);
    expect(state.currentQuestion).toBe('Song by Artist');
    expect(state.category).toBe('Artist');
    expect(state.currentSongAudioUrl).toBe('yt');
    expect(state.currentRoundNumber).toBe(2);
    expect(state.totalRounds).toBe(5);
    expect(state.isBuzzActive).toBe(true);
    expect(state.isRoundComplete).toBe(false);
  });

  it('handles BUZZ_IN_SUCCESS', () => {
    const action: Action = { type: 'BUZZ_IN_SUCCESS', payload: { player: 'Alice' } };
    const state = gameReducer(baseState, action);
    expect(state.activePlayerName).toBe('Alice');
    expect(state.isBuzzActive).toBe(false);
    expect(state.isAnswerPhase).toBe(true);
  });

  it('handles ANSWER_SUBMITTED_ACK (correct with canAdvance)', () => {
    const action: Action = {
      type: 'ANSWER_SUBMITTED_ACK',
      payload: {
        player: 'Alice',
        answer: { songTitle: 'Song', artist: 'Artist' },
        isCorrect: true,
        correctAnswer: { title: 'Song', artist: 'Artist' },
        canAdvance: true,
      },
    };
    const state = gameReducer(baseState, action);
    expect(state.isRoundComplete).toBe(false); // Round completion is now handled by ROUND_COMPLETE event
    expect(state.lastCorrectAnswer?.playerName).toBe('Alice');
    expect(state.infoMessage).toMatch(/Correct!/);
    expect(state.error).toBeNull();
    expect(state.canAdvance).toBe(true);
  });

  it('handles ANSWER_SUBMITTED_ACK (correct but cannot advance)', () => {
    const action: Action = {
      type: 'ANSWER_SUBMITTED_ACK',
      payload: {
        player: 'Alice',
        answer: { songTitle: 'Song', artist: 'Artist' },
        isCorrect: true,
        correctAnswer: { title: 'Song', artist: 'Artist' },
        canAdvance: false, // Round should NOT complete
      },
    };
    const state = gameReducer(baseState, action);
    expect(state.isRoundComplete).toBe(false); // Should NOT complete round
    expect(state.lastCorrectAnswer?.playerName).toBe('Alice');
    expect(state.infoMessage).toMatch(/Correct!/);
    expect(state.error).toBeNull();
  });

  it('handles ANSWER_SUBMITTED_ACK (partially correct)', () => {
    const action: Action = {
      type: 'ANSWER_SUBMITTED_ACK',
      payload: {
        player: 'Bob',
        answer: { songTitle: 'Song', artist: 'Wrong Artist' },
        isCorrect: false,
        correctAnswer: { title: 'Song', artist: 'Artist' },
        message: 'Partially correct! You got the song title right but missed the artist. +10 points!',
        canAdvance: false, // Partially correct should not advance
      },
    };
    const state = gameReducer(baseState, action);
    expect(state.isRoundComplete).toBe(false); // Should NOT complete round
    expect(state.lastCorrectAnswer).toBeNull(); // No lastCorrectAnswer for partial
    expect(state.infoMessage).toMatch(/Partially correct!/);
    expect(state.correctAnswer).toBe('Song by Artist');
  });

  it('handles GAME_OVER_EVENT', () => {
    const action: Action = {
      type: 'GAME_OVER_EVENT',
      payload: { finalScores: { p1: 100, p2: 50 } },
    };
    const state = gameReducer(baseState, action);
    expect(state.isRoundComplete).toBe(true);
    expect(state.currentQuestion).toMatch(/Game Over/);
    expect(state.scores.some(s => s.id === 'p1')).toBe(true);
  });

  it('handles SET_ERROR (unrecoverable)', () => {
    const action: Action = {
      type: 'SET_ERROR',
      payload: { message: 'Game not found', isCritical: true },
    };
    const state = gameReducer(baseState, action);
    expect(state.hasJoinedGame).toBe(false);
    expect(state.error).toBe('Game not found');
  });

  it('handles SET_SOCKET_CONNECTED', () => {
    const action: Action = { type: 'SET_SOCKET_CONNECTED', payload: false };
    const state = gameReducer(baseState, action);
    expect(state.isSocketConnected).toBe(false);
  });

  it('handles SET_ROUND_COMPLETE', () => {
    const action: Action = { type: 'SET_ROUND_COMPLETE', payload: true };
    const state = gameReducer(baseState, action);
    expect(state.isRoundComplete).toBe(true);
  });

  it('handles BUZZER_STATUS_UPDATE', () => {
    const action: Action = {
      type: 'BUZZER_STATUS_UPDATE',
      payload: { isBuzzActive: true, activePlayerName: 'Bob', isAnswerPhase: false },
    };
    const state = gameReducer(baseState, action);
    expect(state.isBuzzActive).toBe(true);
    expect(state.activePlayerName).toBe('Bob');
    expect(state.isAnswerPhase).toBe(false);
  });

  it('handles SET_CAN_ADVANCE', () => {
    const action: Action = { type: 'SET_CAN_ADVANCE', payload: true };
    const state = gameReducer(baseState, action);
    expect(state.canAdvance).toBe(true);
  });

  it('handles ROUND_COMPLETE', () => {
    const action: Action = {
      type: 'ROUND_COMPLETE',
      payload: {
        roundNumber: 3,
        correctAnswer: { title: 'Test Song', artist: 'Test Artist' },
        playersWithPoints: [
          { name: 'Alice', score: 100, pointsThisRound: 25 },
          { name: 'Bob', score: 50, pointsThisRound: 0 }
        ],
        roundParticipants: [
          { name: 'Alice', role: 'participant' },
          { name: 'Bob', role: 'participant' },
          { name: 'Judge1', role: 'judge' }
        ],
        artworkUrl: 'https://example.com/artwork.jpg',
        canAdvance: true,
        isGameOver: false
      }
    };
    const state = gameReducer(baseState, action);
    expect(state.showRoundResults).toBe(true);
    expect(state.isRoundComplete).toBe(true);
    expect(state.canAdvance).toBe(true);
    expect(state.roundResults?.roundNumber).toBe(3);
    expect(state.roundResults?.correctAnswer.title).toBe('Test Song');
    expect(state.roundResults?.artworkUrl).toBe('https://example.com/artwork.jpg');
    expect(state.roundResults?.playersWithPoints.length).toBe(2);
  });

  it('handles SHOW_ROUND_RESULTS', () => {
    const action: Action = { type: 'SHOW_ROUND_RESULTS', payload: true };
    const state = gameReducer(baseState, action);
    expect(state.showRoundResults).toBe(true);
  });

  it('handles HIDE_ROUND_RESULTS', () => {
    const action: Action = { type: 'HIDE_ROUND_RESULTS' };
    const state = gameReducer(baseState, action);
    expect(state.showRoundResults).toBe(false);
  });
}); 