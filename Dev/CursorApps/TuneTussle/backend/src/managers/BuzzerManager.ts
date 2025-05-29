import type { GameSession } from '../models/types';
import { HookService } from '../services/hookService';
import { RoundManagementService } from '../services/roundManagementService';
import { ANSWER_TIMER_DURATION_MS } from '../utils/gameUtils';
import { handleAttemptFailure } from '../services/gameRoundService';
import { logger } from '../utils/logger';

type VoidResult = { success: true; message?: string }
                | { success: false; message: string; errorCode?: string };

/**
 * Manages buzzer system operations.
 * 
 * Responsibilities:
 * - Player buzz-in handling
 * - Buzzer state management
 * - Answer timer coordination
 * - Score adjustments
 */
export class BuzzerManager {
  constructor(
    private readonly gameStorage: { get: (code: string) => GameSession | undefined },
    private readonly hookService: HookService,
    private readonly resourcePool: { getTimerService: (gameCode: string) => any },
    private readonly roundManagementService: RoundManagementService
  ) {}

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
   * Handle player buzzing in.
   */
  public playerBuzzIn(code: string, playerName: string): VoidResult & { message: string } {
    try {
      const game = this.ensureGame(code);
      
      if (game.gameState !== 'playing') {
        return { success: false, message: 'Game not in playing state.' };
      }
      
      const player = Array.from(game.participants.values()).find(p => p.name === playerName);
      if (!player || player.role !== 'participant') {
        return { success: false, message: 'Only participants can buzz in.' };
      }
      
      const currentRound = this.roundManagementService.getCurrentRound(game);
      if (!currentRound) {
        return { success: false, message: 'No active round.' };
      }
      
      if (!currentRound.isBuzzerOpenForNewAttempts) {
        return { success: false, message: 'Buzzer not active.' };
      }
      
      if (currentRound.buzzedInPlayer) {
        return { success: false, message: 'Another player has buzzed in.' };
      }
      
      if (currentRound.playersWhoAttempted.has(playerName)) {
        return { success: false, message: 'Already attempted this round.' };
      }

      // Execute buzz-in
      currentRound.buzzedInPlayer = playerName;
      currentRound.isBuzzerOpenForNewAttempts = false;
      
      // Notify hooks
      this.hookService.onPlayerBuzzedIn({ gameCode: code, playerName });
      this.hookService.onBuzzerStatusUpdate({ 
        gameCode: code, 
        isBuzzActive: false, 
        activePlayerName: playerName, 
        isAnswerPhase: true 
      });
      
      // Start answer timer
      const timerService = this.resourcePool.getTimerService(code);
      timerService.startAnswerTimer(
        code, 
        playerName, 
        ANSWER_TIMER_DURATION_MS, 
        () => this.handleAnswerTimerExpired(code, playerName)
      );

      return { 
        success: true, 
        message: `Player ${playerName} buzzed in! You have ${ANSWER_TIMER_DURATION_MS / 1000} seconds.` 
      };
    } catch (error: any) {
      return { success: false, message: error.message, errorCode: 'GAME_NOT_FOUND' };
    }
  }

  /**
   * Clear the buzzer (judge action).
   */
  public clearBuzzer(code: string, requestingUser: string): VoidResult & { message: string } {
    try {
      const game = this.ensureGame(code);
      
      if (game.judgeName !== requestingUser) {
        return { success: false, message: 'Only judge can clear buzzer.' };
      }
      
      if (game.gameState !== 'playing') {
        return { success: false, message: 'Game not in playing state.' };
      }
      
      const currentRound = this.roundManagementService.getCurrentRound(game);
      if (!currentRound) {
        return { success: false, message: 'No active round.' };
      }

      const previous = currentRound.buzzedInPlayer;
      const timerService = this.resourcePool.getTimerService(code);
      timerService.clearTimer(code);
      currentRound.buzzedInPlayer = null;

      let message = previous
        ? `Buzzer reset by judge. Player ${previous} removed. `
        : 'Buzzer is not currently active. ';
        
      if (currentRound.isBuzzerOpenForNewAttempts) {
        message += 'Buzzer open for new attempts.';
        this.hookService.onBuzzerEnabled({ gameCode: code });
      } else {
        message += 'Buzzer remains closed.';
      }

      this.hookService.onBuzzerStatusUpdate({ 
        gameCode: code, 
        isBuzzActive: currentRound.isBuzzerOpenForNewAttempts, 
        activePlayerName: null, 
        isAnswerPhase: false 
      });
      
      return { success: true, message };
    } catch (error: any) {
      return { success: false, message: error.message, errorCode: 'GAME_NOT_FOUND' };
    }
  }

  /**
   * Adjust a player's score (judge action).
   */
  public adjustPlayerScore(code: string, playerId: string, newScore: number): VoidResult & { updatedScore?: number } {
    try {
      const game = this.ensureGame(code);
      
      // Verify the player exists in the game
      const player = game.participants.get(playerId);
      if (!player) {
        return { 
          success: false, 
          message: `Player with ID '${playerId}' not found in game.`, 
          errorCode: 'PLAYER_NOT_FOUND' 
        };
      }

      // Don't allow adjusting judge's score
      if (player.role === 'judge') {
        return { 
          success: false, 
          message: 'Cannot adjust judge score.', 
          errorCode: 'CANNOT_ADJUST_JUDGE_SCORE' 
        };
      }

      // Update the score
      const oldScore = game.scores.get(playerId) || 0;
      game.scores.set(playerId, newScore);

      logger.game(`Score adjusted for player ${playerId} (${player.name}) from ${oldScore} to ${newScore} in game ${code}`);

      // Emit score update event to all clients in the game
      this.hookService.onScoreUpdate({ gameCode: code, scores: game.scores });

      return { 
        success: true, 
        message: `Score for ${player.name} updated from ${oldScore} to ${newScore}.`,
        updatedScore: newScore
      };
    } catch (error: any) {
      return { success: false, message: error.message, errorCode: 'GAME_NOT_FOUND' };
    }
  }

  /**
   * Handle answer timer expiration.
   */
  private handleAnswerTimerExpired(gameCode: string, playerName: string): void {
    try {
      console.log(`[BuzzerManager] Answer timer expired for player ${playerName} in game ${gameCode}`);
      const game = this.ensureGame(gameCode);
      const currentRound = this.roundManagementService.getCurrentRound(game);
      if (!currentRound || currentRound.buzzedInPlayer !== playerName) {
        console.log(`[BuzzerManager] Timer expired but player ${playerName} no longer buzzed in or no active round`);
        return;
      }

      console.log(`[BuzzerManager] Processing timer expiration - marking ${playerName} as attempted`);
      currentRound.playersWhoAttempted.add(playerName);
      currentRound.buzzedInPlayer = null;
      
      const nonJudge = Array.from(game.participants.values())
        .filter(p => p.role !== 'judge')
        .map(p => p.name);
        
      const updated = handleAttemptFailure(currentRound, playerName, nonJudge);
      console.log(`[BuzzerManager] Timer expiration result - allAttempted: ${updated.allAttempted}`);
      
      this.hookService.onAnswerTimerExpired({ gameCode, playerName });
      this.hookService.onBuzzerStatusUpdate({ 
        gameCode, 
        isBuzzActive: currentRound.isBuzzerOpenForNewAttempts, 
        activePlayerName: null, 
        isAnswerPhase: false 
      });
      
      console.log(`[BuzzerManager] Timer expiration complete for ${playerName}, buzzer reopened: ${currentRound.isBuzzerOpenForNewAttempts}`);
    } catch (error) {
      logger.error(`Error handling timer expiration for ${playerName} in game ${gameCode}`, error);
    }
  }
} 