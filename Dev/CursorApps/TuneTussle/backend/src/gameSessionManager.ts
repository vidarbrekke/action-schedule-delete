import { Server as SocketIOServer } from 'socket.io';
import type {
  GameSession,
  GameRound,
  Hooks,
  GameSong
} from './models/types';
import {
  generateCode
} from './utils/gameUtils';
import { HookService } from './services/hookService';
import { ParticipantManagementService } from './services/participantManagementService';
import { GameLifecycleService } from './services/gameLifecycleService';
import { RoundManagementService } from './services/roundManagementService';
import { AnswerSubmissionService } from './services/answerSubmissionService';
import { GameStateManager } from './managers/GameStateManager';
import { BuzzerManager } from './managers/BuzzerManager';
import { SocketManager } from './managers/SocketManager';
import { GameStorageManager } from './services/gameStorageManager';
import { ResourcePoolManager } from './services/resourcePoolManager';
import { AdaptivePerformanceMonitor } from './services/adaptivePerformanceMonitor';
import { ENV_CONFIG } from './config/environmentConfig';
import { getSpotifyAccessToken, getSpotifyArtworkUrl } from './services/spotifyService';
import { fetchDeezerTrackWithArtwork } from './services/deezerService';
import env from './utils/envConfig';

type VoidResult = { success: true; message?: string }
                | { success: false; message: string; errorCode?: string };
type DataResult<T> = { success: true; data: T }
                   | { success: false; message: string; errorCode?: string };
type NextRoundResult = ReturnType<RoundManagementService['startNextRound']>;
type AnswerResult = Awaited<ReturnType<AnswerSubmissionService['submitAnswer']>>;

/**
 * Main game session coordinator.
 * 
 * Orchestrates specialized managers to handle different aspects of game management.
 * Maintains the core games Map and delegates operations to appropriate managers.
 */
export class GameSessionManager {
  private readonly gameStorage: GameStorageManager;
  private readonly resourcePool: ResourcePoolManager;
  private readonly performanceMonitor: AdaptivePerformanceMonitor;
  private io?: SocketIOServer;
  private readonly hookService: HookService;
  private readonly participantManagementService: ParticipantManagementService;
  private readonly gameLifecycleService: GameLifecycleService;
  private readonly roundManagementService: RoundManagementService;
  private readonly answerSubmissionService: AnswerSubmissionService;
  
  // Specialized managers
  private readonly gameStateManager: GameStateManager;
  private readonly buzzerManager: BuzzerManager;
  private readonly socketManager: SocketManager;

  // Track previous game participants for auto-redirect feature
  // Using TTL to prevent memory leaks and socket ID tracking for targeted emission
  private readonly judgeLastGameParticipants = new Map<string, {
    participants: string[],
    participantSocketIds: Set<string>,
    timestamp: number
  }>();
  
  // Clean up stale participant tracking (30 minutes TTL)
  private readonly PARTICIPANT_TRACKING_TTL = 30 * 60 * 1000;
  private participantCleanupInterval?: NodeJS.Timeout;

  constructor(
    hookService: HookService,
    participantManagementService: ParticipantManagementService,
    gameLifecycleService: GameLifecycleService,
    roundManagementService: RoundManagementService,
    answerSubmissionService: AnswerSubmissionService,
    io?: SocketIOServer
  ) {
    this.hookService = hookService;
    this.participantManagementService = participantManagementService;
    this.gameLifecycleService = gameLifecycleService;
    this.roundManagementService = roundManagementService;
    this.answerSubmissionService = answerSubmissionService;
    
    // Initialize scaling optimizations using centralized configuration
    this.gameStorage = new GameStorageManager({
      maxGames: ENV_CONFIG.gameSettings.maxGames,
      idleTimeoutMs: ENV_CONFIG.gameSettings.idleTimeoutMs,
      cleanupIntervalMs: ENV_CONFIG.gameSettings.cleanupIntervalMs,
      maxMemoryMB: ENV_CONFIG.memoryLimits.gameStorageMaxMB
    });
    
    this.resourcePool = ResourcePoolManager.getInstance({
      maxTimerPools: ENV_CONFIG.resourcePools.maxTimerPools,
      maxBatchProcessors: ENV_CONFIG.resourcePools.maxBatchProcessors,
      resourceCleanupIntervalMs: ENV_CONFIG.resourcePools.resourceCleanupIntervalMs
    });
    
    // Configure performance monitor using centralized thresholds
    this.performanceMonitor = AdaptivePerformanceMonitor.getInstance({
      baseIntervalMs: ENV_CONFIG.monitoring.baseIntervalMs,
      adaptiveScaling: ENV_CONFIG.monitoring.adaptiveScaling,
      alertingEnabled: ENV_CONFIG.monitoring.alertingEnabled
    }, {
      memoryWarningMB: ENV_CONFIG.memoryLimits.performanceWarningMB,
      memoryCriticalMB: ENV_CONFIG.memoryLimits.performanceCriticalMB,
      cpuWarning: 60,
      cpuCritical: 80,
      responseTimeWarningMs: 1000,
      responseTimeCriticalMs: 3000
    });
    
    // Connect performance monitor to managers
    this.performanceMonitor.setManagers(this.gameStorage, this.resourcePool);
    
    // Initialize specialized managers with optimized storage
    this.gameStateManager = new GameStateManager(this.gameStorage);
    this.buzzerManager = new BuzzerManager(this.gameStorage, hookService, this.resourcePool, roundManagementService);
    this.socketManager = new SocketManager(hookService, io);
    
    if (io) {
      this.setSocketIOServer(io);
    }
    
    // Only start interval cleanup in production, not in tests
    if (process.env.NODE_ENV !== 'test') {
      this.startParticipantCleanup();
    }
  }

  /** Allows injecting or updating the Socket.IO instance */
  public setSocketIOServer(io: SocketIOServer): void {
    this.io = io;
    this.hookService.setSocketIOServer(io);
    this.socketManager.setSocketIOServer(io);
  }

  /** Register hooks for game events */
  public registerHooks(hooks: Partial<Hooks>): void {
    this.socketManager.registerHooks(hooks);
  }

  /** Helper: throws if game not found */
  private ensureGame(code: string): GameSession {
    const gameSessionResult = this.gameStateManager.getGameSession(code);
    if (!gameSessionResult) {
      throw new Error(`Game not found: ${code}`);
    }
    return gameSessionResult;
  }

  /**
   * Fetch artwork from available music services
   */
  private async fetchArtworkUrl(songTitle: string, artist: string): Promise<string | null> {
    try {
      // Try Deezer first if it's the primary provider
      if (env.MUSIC_PROVIDER === 'deezer') {
        try {
          const deezerResult = await fetchDeezerTrackWithArtwork(songTitle, artist);
          if (deezerResult?.albumArtwork) {
            return deezerResult.albumArtwork;
          }
        } catch (error) {
          console.warn('[GameSessionManager] Deezer artwork fetch failed:', error);
        }
      }

      // Try Spotify if available
      if (env.SPOTIFY_CLIENT_ID && env.SPOTIFY_CLIENT_SECRET) {
        try {
          const token = await getSpotifyAccessToken(env.SPOTIFY_CLIENT_ID, env.SPOTIFY_CLIENT_SECRET);
          const spotifyArtwork = await getSpotifyArtworkUrl(songTitle, artist, token);
          if (spotifyArtwork) {
            return spotifyArtwork;
          }
        } catch (error) {
          console.warn('[GameSessionManager] Spotify artwork fetch failed:', error);
        }
      }

      return null;
    } catch (error) {
      console.error('[GameSessionManager] Error fetching artwork:', error);
      return null;
    }
  }

  /**
   * Emit round complete event with all relevant data for the results screen
   */
  private async emitRoundCompleteEvent(game: GameSession, currentRound: GameRound, isLastRound: boolean): Promise<void> {
    try {
      // Fetch artwork for the results screen
      const artworkUrl = await this.fetchArtworkUrl(currentRound.songTitle, currentRound.artist);

      // Calculate points earned this round for each player
      const playersWithPoints = Array.from(game.participants.values())
        .filter(p => p.role === 'participant')
        .map(participant => {
          const currentScore = game.scores.get(participant.name) || 0;
          const playerAnswers = game.answers.get(participant.name) || [];
          const thisRoundAnswer = playerAnswers[game.currentRoundIndex];
          const pointsThisRound = thisRoundAnswer ? thisRoundAnswer.score : 0;
          
          return {
            name: participant.name,
            score: currentScore,
            pointsThisRound
          };
        });

      // Get all participants for round participants list
      const roundParticipants = Array.from(game.participants.values()).map(p => ({
        name: p.name,
        role: p.role
      }));

      const roundCompletePayload = {
        gameCode: game.code,
        roundNumber: game.currentRoundIndex + 1,
        correctAnswer: { title: currentRound.songTitle, artist: currentRound.artist },
        playersWithPoints,
        roundParticipants,
        artworkUrl,
        canAdvance: !isLastRound,
        isGameOver: isLastRound
      };

      console.log('[GameSessionManager] Emitting onRoundComplete:', roundCompletePayload);
      this.hookService.onRoundComplete(roundCompletePayload);
    } catch (error) {
      console.error('[GameSessionManager] Error emitting round complete event:', error);
    }
  }

  /** Generates a unique game code */
  private generateUniqueCodeFrom(maxAttempts = 10): string {
    for (let i = 0; i < maxAttempts; i++) {
      const code = generateCode();
      if (!this.gameStorage.has(code)) {
        return code;
      }
    }
    throw new Error('Could not generate unique game code');
  }

  /** Creates a new game session */
  public createGame(
    judgeName: string,
    gameSettings: { prompt: string; numberOfRounds: number; llmModel?: string }
  ): DataResult<{ code: string }> {
    try {
      const result = this.gameLifecycleService.createInitialSession(
        judgeName,
        gameSettings,
        () => this.generateUniqueCodeFrom()
      );
      if (!result.success || !result.gameSession) {
        return {
          success: false,
          message: result.message || 'Failed to create game session',
          errorCode: 'CREATE_FAILED'
        };
      }
      this.gameStorage.set(result.gameSession.code, result.gameSession);
      
      // Check if this judge has previous game participants to notify
      const participantData = this.judgeLastGameParticipants.get(judgeName);
      if (participantData && this.io) {
        // Check TTL and clean up stale entries
        const isExpired = Date.now() - participantData.timestamp > this.PARTICIPANT_TRACKING_TTL;
        if (isExpired) {
          console.log(`[GameSessionManager] Cleaning up expired participant tracking for judge ${judgeName}`);
          this.judgeLastGameParticipants.delete(judgeName);
        } else if (participantData.participants.length > 0) {
          console.log(`[GameSessionManager] Judge ${judgeName} creating new game ${result.gameSession.code}, notifying ${participantData.participants.length} previous participants`);
          
          // Use targeted emission to specific sockets instead of global broadcast
          const notificationPayload = {
            judgeName,
            newGameCode: result.gameSession.code,
            previousParticipants: participantData.participants,
            gameSettings: {
              prompt: gameSettings.prompt,
              numberOfRounds: gameSettings.numberOfRounds
            }
          };
          
          // Emit to all connected clients (we'll filter on frontend)
          // TODO: Track participant socket IDs for more targeted emission
          this.io.emit('judgeNewGame', notificationPayload);
        }
      }
      
      // Track performance
      this.performanceMonitor.trackEvent();
      return { success: true, data: { code: result.gameSession.code } };
    } catch (error: any) {
      return { success: false, message: error.message, errorCode: 'CODE_GENERATION_FAILED' };
    }
  }

  /** Lists all active game codes (for debug) */
  public DEBUG_listAllGameCodes(): string[] {
    return this.gameStateManager.getAllGameCodes();
  }

  public async joinGame(code: string, playerName: string): Promise<VoidResult> {
    try {
      const game = this.ensureGame(code);
      const result = await this.participantManagementService.joinGame(game, playerName);
      if (result.success) {
        return { success: true };
      } else {
        return { success: false, message: result.message || 'Failed to join game.', errorCode: 'GAME_NOT_FOUND' };
      }
    } catch (error: any) {
      return { success: false, message: error.message, errorCode: 'GAME_NOT_FOUND' };
    }
  }

  public async leaveGame(code: string, playerName: string): Promise<VoidResult & { gameEnded?: boolean }> {
    try {
      const game = this.ensureGame(code);
      const result = await this.participantManagementService.leaveGame(game, playerName);

      if (result.success && result.judgeLeft) {
        this.resourcePool.releaseTimerService(code);
        if (game.gameState === 'lobby' || game.gameState === 'ready') {
          this.gameStorage.delete(code);
          this.gameLifecycleService.finishGame(game);
          return { success: true, message: result.message || 'Judge left lobby; game deleted as it cannot proceed.', gameEnded: true };
        } else {
          this.gameLifecycleService.finishGame(game);
          return { success: true, message: 'Judge left; game set to finished.', gameEnded: false };
        }
      }

      if (result.success && result.gameEnded && !result.judgeLeft) {
        this.resourcePool.releaseTimerService(code);
        this.gameLifecycleService.finishGame(game);
        return { success: true, message: result.message || '', gameEnded: true };
      }

      if (!result.success) {
        const errorResult: { success: false; message: string; errorCode?: string } = {
          success: false,
          message: result.message || 'Failed to leave game.'
        };
        return errorResult;
      }

      return { success: true, message: result.message || '', gameEnded: result.gameEnded };
    } catch (error: any) {
      return { success: false, message: error.message, errorCode: 'GAME_NOT_FOUND' };
    }
  }

  public listParticipants(code: string): DataResult<string[]> {
    try {
      const game = this.ensureGame(code);
      return { success: true, data: this.participantManagementService.listParticipants(game) };
    } catch (error: any) {
      return { success: false, message: error.message, errorCode: 'GAME_NOT_FOUND' };
    }
  }

  // Delegate to GameStateManager
  public getGameDetails(code: string): DataResult<Omit<GameSession, 'songList' | 'rounds'>> {
    return this.gameStateManager.getGameDetails(code);
  }

  public getGameState(code: string): DataResult<GameSession> {
    return this.gameStateManager.getGameState(code);
  }

  public getCurrentRound(code: string): DataResult<GameRound> {
    return this.gameStateManager.getCurrentRound(code);
  }

  public getGameSession(code: string): GameSession | undefined {
    return this.gameStateManager.getGameSession(code);
  }

  public setSongsAndReady(code: string, songs: GameSong[]): void {
    this.gameStateManager.setSongsAndReady(code, songs);
  }

  public setGameErrored(code: string, error: any): void {
    this.gameStateManager.setGameErrored(code, error);
  }

  public async startGame(code: string, requestingUser: string): Promise<VoidResult> {
    try {
      const game = this.ensureGame(code);
      if (game.judgeName !== requestingUser) {
        return { success: false, message: 'Only the judge can start the game.', errorCode: 'UNAUTHORIZED' };
      }
      if (game.gameState !== 'lobby' && game.gameState !== 'ready') {
        return { success: false, message: 'Game not in a startable state.', errorCode: 'ALREADY_STARTED' };
      }
      if (!Array.isArray(game.songList) || game.songList.length === 0) {
        return { success: false, message: 'No songs generated.', errorCode: 'NO_SONGS_GENERATED' };
      }
      const result = this.gameLifecycleService.startGame(game);
      if (result.success) {
        return { success: true, message: result.message };
      } else {
        return { success: false, message: result.message || 'Failed to start game.', errorCode: result.errorCode };
      }
    } catch (error: any) {
      return { success: false, message: error.message, errorCode: 'GAME_NOT_FOUND' };
    }
  }

  public startNextRound(code: string): NextRoundResult {
    try {
      const game = this.ensureGame(code);
      return this.roundManagementService.startNextRound(game);
    } catch (error: any) {
      return { success: false, message: error.message, errorCode: 'INVALID_STATE' } as NextRoundResult;
    }
  }

  public async endCurrentRound(code: string): Promise<VoidResult & { message: string; canAdvance?: boolean; isGameOver?: boolean }> {
    try {
      const game = this.ensureGame(code);
      
      if (game.gameState !== 'playing') {
        return { success: false, message: 'Game is not in playing state.' };
      }
      
      const currentRound = this.roundManagementService.getCurrentRound(game);
      if (!currentRound) {
        return { success: false, message: 'No active round to end.' };
      }
      
      // Clear any active timers
      const timerService = this.resourcePool.getTimerService(code);
      timerService.clearTimer(code);
      
      // Close the buzzer and clear any active player
      currentRound.buzzedInPlayer = null;
      currentRound.isBuzzerOpenForNewAttempts = false;
      
      // Determine if this is the last round
      const isLastRound = game.currentRoundIndex >= game.gameSettings.numberOfRounds - 1;
      const canAdvance = !isLastRound;
      
      // Broadcast buzzer status update
      this.hookService.onBuzzerStatusUpdate({
        gameCode: code,
        isBuzzActive: false,
        activePlayerName: null,
        isAnswerPhase: false
      });
      
      // Emit round ended event with correct answer
      const correctAnswer = { title: currentRound.songTitle, artist: currentRound.artist };
      this.hookService.onAllPlayersAttemptedNoCorrect({
        gameCode: code,
        message: `Round ended by judge. The correct answer was: ${currentRound.songTitle} by ${currentRound.artist}`,
        canAdvance,
        isGameOver: isLastRound,
        correctAnswer
      });
      
      // Emit round complete event for results screen
      await this.emitRoundCompleteEvent(game, currentRound, isLastRound);
      
      // If this was the last round, finish the game
      if (isLastRound) {
        this.finishGame(code);
      }
      
      return { 
        success: true, 
        message: `Round ended. The correct answer was: ${currentRound.songTitle} by ${currentRound.artist}`,
        canAdvance,
        isGameOver: isLastRound
      };
    } catch (error: any) {
      return { success: false, message: error.message, errorCode: 'GAME_NOT_FOUND' };
    }
  }

  public async submitAnswer(
    code: string,
    player: string,
    answerOrTitle: string | { songTitle: string; artist: string },
    maybeArtist?: string
  ): Promise<AnswerResult> {
    try {
      const game = this.ensureGame(code);
      const answerInput = typeof answerOrTitle === 'object'
        ? answerOrTitle
        : { songTitle: answerOrTitle.trim(), artist: maybeArtist?.trim() || answerOrTitle.trim() };
      return await this.answerSubmissionService.submitAnswer(game, player, answerInput);
    } catch (error: any) {
      return { success: false, message: error.message, errorCode: 'INVALID_STATE' } as AnswerResult;
    }
  }

  // Delegate to BuzzerManager
  public playerBuzzIn(code: string, playerName: string): VoidResult & { message: string } {
    return this.buzzerManager.playerBuzzIn(code, playerName);
  }

  public clearBuzzer(code: string, requestingUser: string): VoidResult & { message: string } {
    return this.buzzerManager.clearBuzzer(code, requestingUser);
  }

  public adjustPlayerScore(code: string, playerId: string, newScore: number): VoidResult & { updatedScore?: number } {
    return this.buzzerManager.adjustPlayerScore(code, playerId, newScore);
  }

  public finishGame(code: string): VoidResult {
    try {
      const game = this.ensureGame(code);
      
      // Track participants from this game for auto-redirect feature
      // Only track participants (not the judge) for notification when the judge creates a new game
      const participants = Array.from(game.participants.values())
        .filter(p => p.role === 'participant')
        .map(p => p.name);
      
      if (participants.length > 0) {
        console.log(`[GameSessionManager] Tracking ${participants.length} participants from game ${code} for judge ${game.judgeName}: ${participants.join(', ')}`);
        this.judgeLastGameParticipants.set(game.judgeName, {
          participants,
          participantSocketIds: new Set(),
          timestamp: Date.now()
        });
      }
      
      this.gameLifecycleService.finishGame(game);
      return { success: true };
    } catch (error: any) {
      return { success: false, message: error.message, errorCode: 'GAME_NOT_FOUND' };
    }
  }

  // Delegate to SocketManager
  public setJudgeSocketId(code: string, socketId: string): void {
    this.socketManager.setJudgeSocketId(code, socketId);
  }

  public clearJudgeSocketId(code: string): void {
    this.socketManager.clearJudgeSocketId(code);
  }

  public getJudgeSocketId(code: string): string | null {
    return this.socketManager.getJudgeSocketId(code);
  }

  /** Clean up participant tracking for a specific judge */
  public cleanupJudgeParticipantTracking(judgeName: string): void {
    const deleted = this.judgeLastGameParticipants.delete(judgeName);
    if (deleted) {
      console.log(`[GameSessionManager] Cleaned up participant tracking for judge ${judgeName}`);
    }
  }

  /** Clean up expired participant tracking entries */
  public cleanupExpiredParticipantTracking(): void {
    const now = Date.now();
    let cleanedCount = 0;
    
    for (const [judgeName, data] of this.judgeLastGameParticipants.entries()) {
      if (now - data.timestamp > this.PARTICIPANT_TRACKING_TTL) {
        this.judgeLastGameParticipants.delete(judgeName);
        cleanedCount++;
      }
    }
    
    if (cleanedCount > 0) {
      console.log(`[GameSessionManager] Cleaned up ${cleanedCount} expired participant tracking entries`);
    }
  }

  public async getLlmConfigForGame(code: string): Promise<any> {
    return this.socketManager.getLlmConfigForGame(code);
  }

  /** Test-only: reset all games and judge socket IDs (for test isolation) */
  public DEBUG_resetGames(): void {
    // Clean up all games through storage manager
    for (const code of this.gameStorage.keys()) {
      this.gameStorage.delete(code);
      this.resourcePool.releaseTimerService(code);
    }
    this.socketManager.clearAllJudgeSocketIds();
    // Clean up participant tracking
    this.judgeLastGameParticipants.clear();
    console.log('[GameSessionManager] DEBUG: Reset all games, socket IDs, and participant tracking');
  }

  /** Clean up resources (for graceful shutdown) */
  public destroy(): void {
    if (this.participantCleanupInterval) {
      clearInterval(this.participantCleanupInterval);
      this.participantCleanupInterval = undefined;
    }
    this.judgeLastGameParticipants.clear();
    console.log('[GameSessionManager] Destroyed and cleaned up all resources');
  }

  /** Get scaling performance statistics */
  public getScalingStats(): {
    storage: any;
    resources: any;
    performance: any;
  } {
    return {
      storage: this.gameStorage.getStats(),
      resources: this.resourcePool.getResourceStats(),
      performance: this.performanceMonitor.getPerformanceStats()
    };
  }

  // Start periodic cleanup for participant tracking
  private startParticipantCleanup(): void {
    this.participantCleanupInterval = setInterval(() => this.cleanupExpiredParticipantTracking(), this.PARTICIPANT_TRACKING_TTL);
  }
}
