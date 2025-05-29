import type { GameSession, GameRound, GameSong } from '../models/types';
import { logger } from '../utils/logger';

type DataResult<T> = { success: true; data: T }
                   | { success: false; message: string; errorCode?: string };

/**
 * Manages game state operations and queries.
 * 
 * Responsibilities:
 * - Game state retrieval and validation
 * - Game session data access
 * - Round information management
 * - State consistency checks
 */
export class GameStateManager {
  constructor(private readonly gameStorage: { get: (code: string) => GameSession | undefined; keys: () => IterableIterator<string> }) {}

  /**
   * Ensures a game exists and returns it, throwing if not found.
   */
  private ensureGame(code: string): GameSession {
    const game = this.gameStorage.get(code);
    if (!game) {
      const err: any = new Error('Game not found.');
      err.code = 'GAME_NOT_FOUND';
      throw err;
    }
    return game;
  }

  /**
   * Get basic game details (excluding sensitive data like songList and rounds).
   */
  public getGameDetails(code: string): DataResult<Omit<GameSession, 'songList' | 'rounds'>> {
    try {
      const game = this.ensureGame(code);
      const { songList, rounds, ...details } = game;
      return { success: true, data: details };
    } catch (error: any) {
      logger.debug(`Game with code ${code} not found for getGameDetails`);
      return { success: false, message: error.message, errorCode: 'GAME_NOT_FOUND' };
    }
  }

  /**
   * Get complete game state (for internal use).
   */
  public getGameState(code: string): DataResult<GameSession> {
    try {
      const game = this.ensureGame(code);
      return { success: true, data: game };
    } catch (error: any) {
      return { success: false, message: error.message, errorCode: 'GAME_NOT_FOUND' };
    }
  }

  /**
   * Get the current active round for a game.
   */
  public getCurrentRound(code: string): DataResult<GameRound> {
    try {
      const game = this.ensureGame(code);
      
      if (!game.rounds || game.currentRoundIndex < 0 || game.currentRoundIndex >= game.rounds.length) {
        return { success: false, message: 'No active round.', errorCode: 'NO_ACTIVE_ROUND' };
      }
      
      const round = game.rounds[game.currentRoundIndex];
      return { success: true, data: round };
    } catch (error: any) {
      return { success: false, message: error.message, errorCode: 'GAME_NOT_FOUND' };
    }
  }

  /**
   * Get raw game session (for testing and internal use).
   */
  public getGameSession(code: string): GameSession | undefined {
    return this.gameStorage.get(code);
  }

  /**
   * Set songs and mark game as ready.
   */
  public setSongsAndReady(code: string, songs: GameSong[]): void {
    const game = this.ensureGame(code);
    game.songList = songs;
    game.gameSettings.numberOfRounds = songs.length;
    game.gameState = 'ready';
  }

  /**
   * Mark game as errored with error details.
   */
  public setGameErrored(code: string, error: any): void {
    const game = this.ensureGame(code);
    game.gameState = 'error';
    (game as any).error = error?.message || String(error);
  }

  /**
   * Check if a game exists.
   */
  public gameExists(code: string): boolean {
    return this.gameStorage.get(code) !== undefined;
  }

  /**
   * Get all active game codes (for debugging).
   */
  public getAllGameCodes(): string[] {
    return Array.from(this.gameStorage.keys());
  }

  /**
   * Validate game state for specific operations.
   */
  public validateGameState(code: string, expectedStates: string[]): { valid: boolean; message?: string } {
    try {
      const game = this.ensureGame(code);
      if (!expectedStates.includes(game.gameState)) {
        return {
          valid: false,
          message: `Game is in '${game.gameState}' state, expected one of: ${expectedStates.join(', ')}`
        };
      }
      return { valid: true };
    } catch (error: any) {
      return { valid: false, message: error.message };
    }
  }
} 