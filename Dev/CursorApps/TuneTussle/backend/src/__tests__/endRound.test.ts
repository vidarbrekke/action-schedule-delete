import { GameSessionManager } from '../gameSessionManager';
import { HookService } from '../services/hookService';
import { ParticipantManagementService } from '../services/participantManagementService';
import { GameLifecycleService } from '../services/gameLifecycleService';
import { RoundManagementService } from '../services/roundManagementService';
import { AnswerSubmissionService } from '../services/answerSubmissionService';
import { TimerService } from '../services/timerService';

describe('EndRound Functionality', () => {
  let gameSessionManager: GameSessionManager;
  let hookService: HookService;
  let participantManagementService: ParticipantManagementService;
  let gameLifecycleService: GameLifecycleService;
  let roundManagementService: RoundManagementService;
  let answerSubmissionService: AnswerSubmissionService;
  let timerService: TimerService;

  beforeEach(() => {
    // Create service instances
    hookService = new HookService();
    timerService = new TimerService();
    participantManagementService = new ParticipantManagementService(hookService);
    gameLifecycleService = new GameLifecycleService(hookService);
    roundManagementService = new RoundManagementService(
      hookService,
      timerService,
      gameLifecycleService
    );
    answerSubmissionService = new AnswerSubmissionService(
      hookService,
      timerService,
      gameLifecycleService,
      roundManagementService
    );

    // Create GameSessionManager instance
    gameSessionManager = new GameSessionManager(
      hookService,
      participantManagementService,
      gameLifecycleService,
      roundManagementService,
      answerSubmissionService
    );
  });

  afterEach(() => {
    gameSessionManager.DEBUG_resetGames();
    // gameSessionManager.destroy(); // Clean up intervals to prevent Jest hanging
  });

  describe('endCurrentRound', () => {
    it('should successfully end a round in progress', async () => {
      // Create a game
      const judgeName = 'TestJudge';
      const gameSettings = { prompt: 'Test Music', numberOfRounds: 3 };
      const createResult = gameSessionManager.createGame(judgeName, gameSettings);
      expect(createResult.success).toBe(true);
      if (!createResult.success) throw new Error(createResult.message);
      const gameCode = createResult.data.code;

      // Add a player
      const playerName = 'TestPlayer';
      gameSessionManager.joinGame(gameCode, playerName);

      // Set up songs and start the game
      const mockSongs = [
        { title: 'Song 1', artist: 'Artist 1', trackUrl: 'https://youtube.com/watch?v=test1', used: false },
        { title: 'Song 2', artist: 'Artist 2', trackUrl: 'https://youtube.com/watch?v=test2', used: false },
        { title: 'Song 3', artist: 'Artist 3', trackUrl: 'https://youtube.com/watch?v=test3', used: false }
      ];
      gameSessionManager.setSongsAndReady(gameCode, mockSongs);
      await gameSessionManager.startGame(gameCode, judgeName);

      // Start the first round
      const nextRoundResult = gameSessionManager.startNextRound(gameCode);
      expect(nextRoundResult.success).toBe(true);

      // End the current round
      const endRoundResult = await gameSessionManager.endCurrentRound(gameCode);

      // Verify the result
      expect(endRoundResult.success).toBe(true);
      expect(endRoundResult.message).toContain('Round ended');
      expect(endRoundResult.message).toContain('Song 1 by Artist 1');
      expect(endRoundResult.canAdvance).toBe(true);
      expect(endRoundResult.isGameOver).toBe(false);
    });

    it('should end the game when ending the final round', async () => {
      // Create a game with only 1 round
      const judgeName = 'TestJudge';
      const gameSettings = { prompt: 'Test Music', numberOfRounds: 1 };
      const createResult = gameSessionManager.createGame(judgeName, gameSettings);
      expect(createResult.success).toBe(true);
      if (!createResult.success) throw new Error(createResult.message);
      const gameCode = createResult.data.code;

      // Add a player
      const playerName = 'TestPlayer';
      gameSessionManager.joinGame(gameCode, playerName);

      // Set up songs and start the game
      const mockSongs = [
        { title: 'Final Song', artist: 'Final Artist', trackUrl: 'https://youtube.com/watch?v=final', used: false }
      ];
      gameSessionManager.setSongsAndReady(gameCode, mockSongs);
      await gameSessionManager.startGame(gameCode, judgeName);

      // Start the first (and only) round
      const nextRoundResult = gameSessionManager.startNextRound(gameCode);
      expect(nextRoundResult.success).toBe(true);

      // End the current round (which is the final round)
      const endRoundResult = await gameSessionManager.endCurrentRound(gameCode);

      // Verify the result
      expect(endRoundResult.success).toBe(true);
      expect(endRoundResult.message).toContain('Round ended');
      expect(endRoundResult.message).toContain('Final Song by Final Artist');
      expect(endRoundResult.canAdvance).toBe(false);
      expect(endRoundResult.isGameOver).toBe(true);

      // Verify game state is finished
      const gameStateResult = gameSessionManager.getGameState(gameCode);
      if (!gameStateResult.success) throw new Error(gameStateResult.message);
      expect(gameStateResult.data.gameState).toBe('finished');
    });

    it('should fail when game is not in playing state', async () => {
      // Create a game but don't start it
      const judgeName = 'TestJudge';
      const gameSettings = { prompt: 'Test Music', numberOfRounds: 2 };
      const createResult = gameSessionManager.createGame(judgeName, gameSettings);
      expect(createResult.success).toBe(true);
      if (!createResult.success) throw new Error(createResult.message);
      const gameCode = createResult.data.code;

      // Try to end round without starting the game
      const endRoundResult = await gameSessionManager.endCurrentRound(gameCode);

      // Verify it fails
      expect(endRoundResult.success).toBe(false);
      expect(endRoundResult.message).toBe('Game is not in playing state.');
    });

    it('should fail when no active round exists', async () => {
      // Create and start a game
      const judgeName = 'TestJudge';
      const gameSettings = { prompt: 'Test Music', numberOfRounds: 2 };
      const createResult = gameSessionManager.createGame(judgeName, gameSettings);
      expect(createResult.success).toBe(true);
      if (!createResult.success) throw new Error(createResult.message);
      const gameCode = createResult.data.code;

      // Add a player
      const playerName = 'TestPlayer';
      gameSessionManager.joinGame(gameCode, playerName);

      // Set up songs and start the game
      const mockSongs = [
        { title: 'Song 1', artist: 'Artist 1', trackUrl: 'https://youtube.com/watch?v=test1', used: false },
        { title: 'Song 2', artist: 'Artist 2', trackUrl: 'https://youtube.com/watch?v=test2', used: false }
      ];
      gameSessionManager.setSongsAndReady(gameCode, mockSongs);
      await gameSessionManager.startGame(gameCode, judgeName);

      // Try to end round without starting any round
      const endRoundResult = await gameSessionManager.endCurrentRound(gameCode);

      // Verify it fails
      expect(endRoundResult.success).toBe(false);
      expect(endRoundResult.message).toBe('No active round to end.');
    });

    it('should fail when game does not exist', async () => {
      const nonExistentGameCode = 'INVALID';
      const endRoundResult = await gameSessionManager.endCurrentRound(nonExistentGameCode);

      expect(endRoundResult.success).toBe(false);
      expect(endRoundResult.message).toBe('Game not found: INVALID');
    });

    it('should close buzzer and clear active player when ending round', async () => {
      // Create a game
      const judgeName = 'TestJudge';
      const gameSettings = { prompt: 'Test Music', numberOfRounds: 2 };
      const createResult = gameSessionManager.createGame(judgeName, gameSettings);
      expect(createResult.success).toBe(true);
      if (!createResult.success) throw new Error(createResult.message);
      const gameCode = createResult.data.code;

      // Add a player
      const playerName = 'TestPlayer';
      gameSessionManager.joinGame(gameCode, playerName);

      // Set up songs and start the game
      const mockSongs = [
        { title: 'Song 1', artist: 'Artist 1', trackUrl: 'https://youtube.com/watch?v=test1', used: false },
        { title: 'Song 2', artist: 'Artist 2', trackUrl: 'https://youtube.com/watch?v=test2', used: false }
      ];
      gameSessionManager.setSongsAndReady(gameCode, mockSongs);
      await gameSessionManager.startGame(gameCode, judgeName);

      // Start the first round
      const nextRoundResult = gameSessionManager.startNextRound(gameCode);
      expect(nextRoundResult.success).toBe(true);

      // Player buzzes in
      const buzzResult = gameSessionManager.playerBuzzIn(gameCode, playerName);
      expect(buzzResult.success).toBe(true);

      // Verify player is active
      const currentRoundBefore = gameSessionManager.getCurrentRound(gameCode);
      if (currentRoundBefore.success) {
        expect(currentRoundBefore.data.buzzedInPlayer).toBe(playerName);
        expect(currentRoundBefore.data.isBuzzerOpenForNewAttempts).toBe(false);
      } else {
        throw new Error(currentRoundBefore.message);
      }

      // End the current round
      const endRoundResult = await gameSessionManager.endCurrentRound(gameCode);
      expect(endRoundResult.success).toBe(true);

      // Verify buzzer is closed and player is cleared
      const currentRoundAfter = gameSessionManager.getCurrentRound(gameCode);
      if (currentRoundAfter.success) {
        expect(currentRoundAfter.data.buzzedInPlayer).toBe(null);
        expect(currentRoundAfter.data.isBuzzerOpenForNewAttempts).toBe(false);
      } else {
        throw new Error(currentRoundAfter.message);
      }
    });
  });
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
