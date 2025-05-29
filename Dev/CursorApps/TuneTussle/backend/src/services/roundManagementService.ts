import type { GameSession, GameRound, Song, GameSong } from '../models/types';
import { HookService } from './hookService';
import { TimerService } from './timerService';
import { GameLifecycleService } from './gameLifecycleService';
import { prepareNextRound } from './gameRoundService';
import { ANSWER_TIMER_DURATION_MS } from '../utils/gameUtils';

export class RoundManagementService {
  constructor(
    private hookService: HookService,
    private timerService: TimerService,
    private gameLifecycleService: GameLifecycleService 
  ) {}

  /**
   * Starts the next round if the game is in a playable state and songs remain.
   * Mutates the GameSession to reflect the new round and schedules timers/hooks.
   */
  startNextRound(game: GameSession): { 
    success: boolean; 
    message: string; 
    round?: GameRound; 
    isFinalRound?: boolean; 
    gameOver?: boolean;
    errorCode?: 'INVALID_STATE' | 'NO_SONGS_AVAILABLE';
  } {
    // Validate game state
    if (!this.isPlaying(game)) {
      console.warn(`[RoundService] Cannot start round for game ${game.code}: not in playing state (current: ${game.gameState})`);
      return this.invalidStateResponse(game);
    }

    // Check for round limit
    if (this.hasReachedRoundLimit(game)) {
      this.gameLifecycleService.finishGame(game);
      return this.noSongsResponse('All rounds have been played. Game over.');
    }

    // Select next unused song
    const nextSong = this.findNextSong(game.songList);
    if (!nextSong) {
      this.gameLifecycleService.finishGame(game);
      return this.noSongsResponse('No more songs available or game not in playing state.');
    }
    nextSong.used = true;

    // Prepare and register new round
    const previousRound = this.getPreviousRound(game);
    const newRound = prepareNextRound(
      previousRound || {
        songTitle: '', // Default/dummy values as prepareNextRound ignores this
        artist: '',
        answers: [],
        buzzedInPlayer: null,
        playersWhoAttempted: new Set<string>(),
        isBuzzerOpenForNewAttempts: true,
      },
      nextSong
    );
    this.registerRound(game, newRound);

    // Notify systems and start hooks/timers
    this.resetTimers(game);
    this.logRoundStart(game.code, game.currentRoundIndex);
    this.hookService.onRoundStart({ gameCode: game.code, roundDetails: newRound, game });

    // Determine if this is the final round (after registerRound updates currentRoundIndex)
    const isFinalRound = (game.currentRoundIndex + 1) === game.gameSettings.numberOfRounds;

    // Note: Don't finish the game immediately when the final round starts
    // The game should finish when the final round is completed, not when it starts

    return { 
      success: true, 
      message: `Round ${game.currentRoundIndex + 1} started.`, 
      round: newRound, 
      isFinalRound,
      gameOver: false  // Game will be over after this round completes, but not immediately
    };
  }

  /** Returns the current GameRound or null if inactive. */
  getCurrentRound(game: GameSession): GameRound | null {
    const idx = game.currentRoundIndex;
    if (!this.isPlaying(game) || idx < 0 || !game.rounds[idx]) {
      return null;
    }
    return game.rounds[idx];
  }

  /**
   * Handles a player's buzz-in request.
   * Validates role, round status, and schedules answer timer.
   */
  playerBuzzIn(
    game: GameSession, 
    playerName: string,
    onTimerExpire: (gameCode: string, player: string, session: GameSession) => void
  ): { success: boolean; message: string } {
    // Validate participant role
    if (!this.isValidParticipant(game, playerName)) {
      return { success: false, message: 'Only participants can buzz in.' };
    }

    // Validate round and buzzer status
    const round = this.getCurrentRound(game);
    if (!round) return { success: false, message: 'No active round.' };
    if (!round.isBuzzerOpenForNewAttempts) return { success: false, message: 'Buzzer is not active.' };
    if (round.buzzedInPlayer) return { success: false, message: 'Another player has already buzzed in.' };
    if (round.playersWhoAttempted.has(playerName)) return { success: false, message: 'You have already attempted this round.' };

    // Update round state
    round.buzzedInPlayer = playerName;
    round.isBuzzerOpenForNewAttempts = false;

    // Broadcast events and start timer
    this.hookService.onPlayerBuzzedIn({ gameCode: game.code, playerName });
    this.hookService.onBuzzerStatusUpdate({
      gameCode: game.code,
      isBuzzActive: false,
      activePlayerName: playerName,
      isAnswerPhase: true,
    });
    this.timerService.startAnswerTimer(
      game.code, playerName, ANSWER_TIMER_DURATION_MS,
      () => onTimerExpire(game.code, playerName, game)
    );

    return { success: true, message: `Player ${playerName} buzzed in! You have ${ANSWER_TIMER_DURATION_MS / 1000} seconds to answer.` };
  }

  /**
   * Allows the judge to clear the current buzz, optionally reopening it.
   */
  clearBuzzerByJudge(game: GameSession, requestingUser: string): { success: boolean; message?: string } {
    // Only judge can clear
    if (requestingUser !== game.judgeName) {
      return { success: false, message: 'Only the judge can clear the buzzer.' };
    }
    const round = this.getCurrentRound(game);
    if (!round) {
      return { success: false, message: 'No active round.' };
    }

    // Reset buzz state
    const prevPlayer = round.buzzedInPlayer;
    this.timerService.clearTimer(game.code);
    round.buzzedInPlayer = null;

    // Determine eligibility and update flag
    round.isBuzzerOpenForNewAttempts = this.hasEligiblePlayers(game, round);
    this.broadcastBuzzerReset(game, prevPlayer, round);

    return { success: true, message: this.buildClearBuzzerMessage(prevPlayer, round) };
  }

  // --- Private Helpers ---

  private isPlaying(game: GameSession): boolean {
    return game.gameState === 'playing';
  }

  private invalidStateResponse(game: GameSession) {
    return {
      success: false,
      message: 'Game is not in playing state. Cannot start next round.',
      gameOver: game.gameState === 'finished',
      errorCode: 'INVALID_STATE' as const,
    };
  }

  private hasReachedRoundLimit(game: GameSession): boolean {
    // Only end the game if the next round index would exceed the number of rounds
    return (game.currentRoundIndex + 1) >= game.gameSettings.numberOfRounds;
  }

  private noSongsResponse(msg: string) {
    return {
      success: false,
      message: msg,
      gameOver: true,
      errorCode: 'NO_SONGS_AVAILABLE' as const,
    };
  }

  private findNextSong(songList: readonly GameSong[]): GameSong | undefined {
    return songList.find(song => !song.used);
  }

  private getPreviousRound(game: GameSession): GameRound | null {
    return game.rounds[game.currentRoundIndex] || null;
  }

  private registerRound(game: GameSession, round: GameRound): void {
    game.rounds.push(round);
    game.currentRoundIndex = game.rounds.length - 1;
    game.currentRoundDetails = round;
  }

  private resetTimers(game: GameSession): void {
    this.timerService.clearTimer(game.code);
  }

  private logRoundStart(gameCode: string, index: number): void {
    console.log(`[RoundService startNextRound] Round ${index + 1} for game ${gameCode}`);
  }

  private isValidParticipant(game: GameSession, playerName: string): boolean {
    const part = Array.from(game.participants.values()).find(p => p.name === playerName);
    return Boolean(part && part.role === 'participant');
  }

  private hasEligiblePlayers(game: GameSession, round: GameRound): boolean {
    return Array.from(game.participants.values())
      .some(p => p.role === 'participant' && !round.playersWhoAttempted.has(p.name));
  }

  private broadcastBuzzerReset(
    game: GameSession,
    prevPlayer: string | null,
    round: GameRound
  ): void {
    if (prevPlayer && round.isBuzzerOpenForNewAttempts) {
      this.hookService.onBuzzerEnabled({ gameCode: game.code });
    }
    this.hookService.onBuzzerStatusUpdate({
      gameCode: game.code,
      isBuzzActive: round.isBuzzerOpenForNewAttempts,
      activePlayerName: null,
      isAnswerPhase: false,
    });
  }

  private buildClearBuzzerMessage(
    prevPlayer: string | null,
    round: GameRound
  ): string {
    const base = prevPlayer
      ? `${prevPlayer} no longer buzzed. `
      : '';
    const status = round.isBuzzerOpenForNewAttempts
      ? 'Buzzer is open.'
      : 'Buzzer remains closed.';
    return `Buzzer reset. ${base}${status}`;
  }
} 