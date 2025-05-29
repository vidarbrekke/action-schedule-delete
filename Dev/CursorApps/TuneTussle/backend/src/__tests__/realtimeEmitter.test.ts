import { registerRealtimeHooks } from '../realtimeEmitter';
import { GameSession, GameRound } from '../models/types';
import { TestGameSessionManager } from '../testUtils/testGameSessionManager';
import { HookService } from '../services/hookService';
import { resetAllMocksAndModulesAsync } from '../../testUtils/resetTestEnv';

describe('registerRealtimeHooks', () => {
  let io: any;
  let manager: TestGameSessionManager;
  let emitted: { event: string; room: string; payload: any; except?: string }[];

  beforeEach(async () => {
    await resetAllMocksAndModulesAsync();
    emitted = [];
    io = {
      to: (room: string) => ({
        emit: (event: string, payload: any) => {
          emitted.push({ event, room, payload });
        },
        except: (socketId: string) => ({
          emit: (event: string, payload: any) => {
            emitted.push({ event, room, payload, except: socketId });
          }
        })
      }),
      emit: (event: string, payload: any) => {
        emitted.push({ event, room: '<broadcast>', payload });
      },
      sockets: {
        sockets: new Map()
      }
    };
    manager = TestGameSessionManager.createForTesting(io);
    
    // Mock getJudgeSocketId method
    manager.getJudgeSocketId = jest.fn().mockReturnValue('judge-socket-123');
    
    registerRealtimeHooks(io, manager as any);
  });

  it('emits gameState on onGameStart', () => {
    const createResult = manager.createGame('Judge', { prompt: 'Test', numberOfRounds: 1 });
    expect(createResult.success).toBe(true);
    const gameCode = createResult.success ? createResult.data.code : 'ROOM1';
    
    emitted.length = 0;
    
    manager.getHookService().onGameStart({ gameCode, game: manager.getGameSession(gameCode)! });
    
    expect(emitted).toEqual(
      expect.arrayContaining([
        { event: 'gameState', room: gameCode, payload: expect.objectContaining({ code: gameCode, gameState: 'lobby' }) },
        { event: 'gameStarted', room: gameCode, payload: expect.objectContaining({ gameCode, judgeName: 'Judge', gameSettings: { prompt: 'Test', numberOfRounds: 1 } }) }
      ])
    );
    expect(emitted.length).toBe(2);
  });

  it('emits gameState on onRoundStart', () => {
    const createResult = manager.createGame('Judge', { prompt: 'Test', numberOfRounds: 3 });
    expect(createResult.success).toBe(true);
    const gameCode = createResult.success ? createResult.data.code : 'ROOM2';
    
    emitted.length = 0;
    
    const round = { songTitle: 'Song', artist: 'Artist', answers: [], buzzedInPlayer: null, playersWhoAttempted: new Set(), isBuzzerOpenForNewAttempts: true };
    const game = manager.getGameSession(gameCode)!;
    
    manager.getHookService().onRoundStart({ gameCode, roundDetails: round as any, game });
    expect(emitted).toEqual(
      expect.arrayContaining([
        { event: 'gameState', room: gameCode, payload: expect.objectContaining({ code: gameCode, gameState: 'lobby' }) },
        { event: 'roundStarted', room: gameCode, payload: expect.objectContaining({ gameCode, currentRoundNumber: 0, round: expect.any(Object), totalRounds: 3 }) }
      ])
    );
  });

  describe('Enhanced roundStarted events with next round info', () => {
    it('should send enhanced payload to judge when next round exists', () => {
      const createResult = manager.createGame('Judge', { prompt: 'Test', numberOfRounds: 3 });
      expect(createResult.success).toBe(true);
      const gameCode = createResult.success ? createResult.data.code : 'ROOM_JUDGE';
      
      // Setup game with multiple rounds and songList
      const game = manager.getGameSession(gameCode)!;
      game.songList = [
        { title: 'Song 1', artist: 'Artist 1', used: false },
        { title: 'Song 2', artist: 'Artist 2', used: false }
      ];
      game.currentRoundIndex = 0; // Starting first round
      
      emitted.length = 0;
      
      const round = { 
        songTitle: 'Song 1', 
        artist: 'Artist 1', 
        trackUrl: 'http://example.com/song1.mp3',
        answers: [], 
        buzzedInPlayer: null, 
        playersWhoAttempted: new Set(), 
        isBuzzerOpenForNewAttempts: true 
      };
      
      manager.getHookService().onRoundStart({ gameCode, roundDetails: round as any, game });
      
      // Should have two roundStarted events: enhanced for judge, standard for others
      const roundStartedEvents = emitted.filter(e => e.event === 'roundStarted');
      expect(roundStartedEvents).toHaveLength(2);
      
      // Find the enhanced judge payload
      const judgeEvent = roundStartedEvents.find(e => e.room === 'judge-socket-123');
      expect(judgeEvent).toBeDefined();
      expect(judgeEvent!.payload.nextRound).toEqual({
        title: 'Song 2',
        artist: 'Artist 2'
      });
      
      // Find the standard payload for other players
      const playersEvent = roundStartedEvents.find(e => e.except === 'judge-socket-123');
      expect(playersEvent).toBeDefined();
      expect(playersEvent!.payload.nextRound).toBeUndefined();
    });

    it('should send standard payload to all when no next round exists', () => {
      const createResult = manager.createGame('Judge', { prompt: 'Test', numberOfRounds: 1 });
      expect(createResult.success).toBe(true);
      const gameCode = createResult.success ? createResult.data.code : 'ROOM_FINAL';
      
      // Setup game for final round
      const game = manager.getGameSession(gameCode)!;
      game.songList = [{ title: 'Final Song', artist: 'Final Artist', used: false }];
      game.currentRoundIndex = 0; // This is the last round
      
      emitted.length = 0;
      
      const round = { 
        songTitle: 'Final Song', 
        artist: 'Final Artist', 
        trackUrl: 'http://example.com/final.mp3',
        answers: [], 
        buzzedInPlayer: null, 
        playersWhoAttempted: new Set(), 
        isBuzzerOpenForNewAttempts: true 
      };
      
      manager.getHookService().onRoundStart({ gameCode, roundDetails: round as any, game });
      
      // Should have only one roundStarted event to all players (no next round to preload)
      const roundStartedEvents = emitted.filter(e => e.event === 'roundStarted');
      expect(roundStartedEvents).toHaveLength(1);
      expect(roundStartedEvents[0].room).toBe(gameCode);
      expect(roundStartedEvents[0].except).toBeUndefined();
      expect(roundStartedEvents[0].payload.nextRound).toBeUndefined();
    });

    it('should send standard payload when judge socket ID not available', () => {
      // Mock getJudgeSocketId to return null (judge not connected)
      manager.getJudgeSocketId = jest.fn().mockReturnValue(null);
      
      const createResult = manager.createGame('Judge', { prompt: 'Test', numberOfRounds: 2 });
      expect(createResult.success).toBe(true);
      const gameCode = createResult.success ? createResult.data.code : 'ROOM_NO_JUDGE';
      
      const game = manager.getGameSession(gameCode)!;
      game.songList = [
        { title: 'Song 1', artist: 'Artist 1' },
        { title: 'Song 2', artist: 'Artist 2' }
      ];
      game.currentRoundIndex = 0;
      
      emitted.length = 0;
      
      const round = { 
        songTitle: 'Song 1', 
        artist: 'Artist 1', 
        trackUrl: 'http://example.com/song1.mp3',
        answers: [], 
        buzzedInPlayer: null, 
        playersWhoAttempted: new Set(), 
        isBuzzerOpenForNewAttempts: true 
      };
      
      manager.getHookService().onRoundStart({ gameCode, roundDetails: round as any, game });
      
      // Should send standard payload to all when judge socket unavailable
      const roundStartedEvents = emitted.filter(e => e.event === 'roundStarted');
      expect(roundStartedEvents).toHaveLength(1);
      expect(roundStartedEvents[0].room).toBe(gameCode);
      expect(roundStartedEvents[0].payload.nextRound).toBeUndefined();
    });

    it('should handle missing songList gracefully', () => {
      const createResult = manager.createGame('Judge', { prompt: 'Test', numberOfRounds: 2 });
      expect(createResult.success).toBe(true);
      const gameCode = createResult.success ? createResult.data.code : 'ROOM_NO_SONGS';
      
      const game = manager.getGameSession(gameCode)!;
      // songList is undefined/missing
      game.currentRoundIndex = 0;
      
      emitted.length = 0;
      
      const round = { 
        songTitle: 'Song 1', 
        artist: 'Artist 1', 
        trackUrl: 'http://example.com/song1.mp3',
        answers: [], 
        buzzedInPlayer: null, 
        playersWhoAttempted: new Set(), 
        isBuzzerOpenForNewAttempts: true 
      };
      
      manager.getHookService().onRoundStart({ gameCode, roundDetails: round as any, game });
      
      // Should send standard payload when songList missing
      const roundStartedEvents = emitted.filter(e => e.event === 'roundStarted');
      expect(roundStartedEvents).toHaveLength(1);
      expect(roundStartedEvents[0].room).toBe(gameCode);
      expect(roundStartedEvents[0].payload.nextRound).toBeUndefined();
    });
  });

  it('emits gameState on onBuzzIn', () => {
    const createResult = manager.createGame('Judge', { prompt: 'Test', numberOfRounds: 1 });
    expect(createResult.success).toBe(true);
    const gameCode = createResult.success ? createResult.data.code : 'ROOM3';
    
    emitted.length = 0;
    
    manager.getHookService().onPlayerBuzzedIn({ gameCode, playerName: 'Player1' });
    
    expect(emitted).toEqual([
      { event: 'gameState', room: gameCode, payload: expect.objectContaining({ code: gameCode, gameState: 'lobby' }) }
    ]);
  });

  it('emits answerSubmitted on onAnswer', () => {
    const createResult = manager.createGame('Judge', { prompt: 'Test', numberOfRounds: 1 });
    expect(createResult.success).toBe(true);
    const gameCode = createResult.success ? createResult.data.code : 'ROOM4';
    
    emitted.length = 0;
    
    const answer = { songTitle: 'S', artist: 'A' };
    const isCorrect = true;
    manager.getHookService().onAnswerSubmitted({
      gameCode,
      playerName: 'Player2',
      answer,
      isCorrect,
    });
    expect(emitted).toEqual(expect.arrayContaining([
      { event: 'answerSubmitted', room: gameCode, payload: expect.objectContaining({ playerName: 'Player2', answer, isCorrect }) }
    ]));
  });

  it('emits gameState on onScoreUpdate', () => {
    const createResult = manager.createGame('Judge', { prompt: 'Test', numberOfRounds: 1 });
    expect(createResult.success).toBe(true);
    const gameCode = createResult.success ? createResult.data.code : 'ROOM5';
    
    emitted.length = 0;
    
    const game = manager.getGameSession(gameCode)!;
    game.scores.set('P1', 10);
    game.scores.set('P2', 5);
    
    manager.getHookService().onScoreUpdate({ gameCode, scores: game.scores });
    expect(emitted).toEqual(expect.arrayContaining([
      { event: 'gameState', room: gameCode, payload: expect.objectContaining({ code: gameCode, gameState: 'lobby' }) },
      { event: 'scoreUpdate', room: gameCode, payload: { scores: { P1: 10, P2: 5 } } }
    ]));
  });

  it('emits gameState on onGameOver', () => {
    const createResult = manager.createGame('Judge', { prompt: 'Test', numberOfRounds: 1 });
    expect(createResult.success).toBe(true);
    const gameCode = createResult.success ? createResult.data.code : 'ROOM6';
    
    emitted.length = 0;
    
    const game = manager.getGameSession(gameCode)!;
    game.gameState = 'finished';
    game.scores.set('P1', 10);
    game.scores.set('P2', 5);
    
    manager.getHookService().onGameOver({ gameCode });
    expect(emitted).toEqual(
      expect.arrayContaining([
        { event: 'gameState', room: gameCode, payload: expect.objectContaining({ code: gameCode, gameState: 'finished' }) },
        { event: 'gameOver', room: gameCode, payload: expect.objectContaining({ gameCode }) }
      ])
    );
  });

  it('emits roundComplete with artwork URL', () => {
    const createResult = manager.createGame('Judge', { prompt: 'Test', numberOfRounds: 1 });
    expect(createResult.success).toBe(true);
    const gameCode = createResult.success ? createResult.data.code : 'ROOM_ROUND_COMPLETE';
    
    emitted.length = 0;
    
    const roundCompletePayload = {
      gameCode,
      roundNumber: 1,
      correctAnswer: { title: 'Test Song', artist: 'Test Artist' },
      playersWithPoints: [{ name: 'Player1', score: 100, pointsThisRound: 25 }],
      roundParticipants: [{ name: 'Player1', role: 'participant' }, { name: 'Judge', role: 'judge' }],
      artworkUrl: 'https://example.com/artwork.jpg',
      canAdvance: false,
      isGameOver: true
    };
    
    manager.getHookService().onRoundComplete(roundCompletePayload);
    
    const roundCompleteEvents = emitted.filter(e => e.event === 'roundComplete');
    expect(roundCompleteEvents).toHaveLength(1);
    expect(roundCompleteEvents[0].payload).toEqual(expect.objectContaining({
      roundNumber: 1,
      correctAnswer: { title: 'Test Song', artist: 'Test Artist' },
      artworkUrl: 'https://example.com/artwork.jpg',
      canAdvance: false,
      isGameOver: true
    }));
  });

  afterEach(async () => {
    await resetAllMocksAndModulesAsync();
  });

  afterAll(() => {
    // Clean up singleton instances for test isolation
    const { GameStorageManager } = require('../services/gameStorageManager');
    GameStorageManager.cleanupAllInstances();

    const { ResourcePoolManager } = require('../services/resourcePoolManager');
    ResourcePoolManager.resetInstance();

    const { AdaptivePerformanceMonitor } = require('../services/adaptivePerformanceMonitor');
    AdaptivePerformanceMonitor.resetInstance();
  });
}); 