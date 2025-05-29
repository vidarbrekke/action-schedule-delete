// timerService.ts – Manages game timers with robust multi-timer support

import { ANSWER_TIMER_DURATION_MS } from '../utils/gameUtils';
import { logger } from '../utils/logger';

type TimerId = ReturnType<typeof setTimeout>;

export enum TimerType {
  Answer = 'answer',
  Buzzer = 'buzzer',
}

/**
 * Service for scheduling and clearing game timers.
 */
export class TimerService {
  private activeTimers = new Map<string, Map<TimerType, TimerId>>();

  /**
   * Start an answer timer for a player.
   */
  startAnswerTimer(
    gameCode: string,
    player: string,
    duration: number = ANSWER_TIMER_DURATION_MS,
    onExpire: () => void
  ): void {
    const startLog = `[Timer] Starting answer timer for '${player}' in game '${gameCode}' (${duration}ms)`;
    const expireLog = `[Timer] Answer timer expired for '${player}' in game '${gameCode}'`;
    this.scheduleTimer(gameCode, TimerType.Answer, duration, startLog, expireLog, onExpire);
  }

  /**
   * Start a buzzer timer to re-enable the buzzer after a delay.
   */
  startBuzzerTimer(
    gameCode: string,
    delay: number,
    onExpire: () => void
  ): void {
    const startLog = `[Timer] Starting buzzer timer for game '${gameCode}' (${delay}ms)`;
    const expireLog = `[Timer] Buzzer timer expired for game '${gameCode}'`;
    this.scheduleTimer(gameCode, TimerType.Buzzer, delay, startLog, expireLog, onExpire);
  }

  /**
   * Internal helper to (re)start a specific timer type.
   * Clears any existing timer of the same type for that game.
   */
  private scheduleTimer(
    gameCode: string,
    type: TimerType,
    duration: number,
    startLog: string,
    expireLog: string,
    onExpire: () => void
  ): void {
    // ensure map exists
    const byType = this.activeTimers.get(gameCode) ?? new Map<TimerType, TimerId>();
    this.activeTimers.set(gameCode, byType);

    // clear existing of same type
    if (byType.has(type)) {
      clearTimeout(byType.get(type)!);
      byType.delete(type);
    }

    logger.timer(startLog);
    const timerId = setTimeout(() => {
      logger.timer(expireLog);
      byType.delete(type);
      if (byType.size === 0) this.activeTimers.delete(gameCode);
      onExpire();
    }, duration);

    byType.set(type, timerId);
  }

  /**
   * Clear a specific timer type, or all timers for one game.
   */
  clearTimer(gameCode: string, type?: TimerType): void {
    const byType = this.activeTimers.get(gameCode);
    if (!byType) return;

    if (type) {
      const id = byType.get(type);
      if (id) {
        clearTimeout(id);
        byType.delete(type);
        logger.timer(`Cleared ${type} timer for game '${gameCode}'`);
      }
    } else {
      for (const id of byType.values()) clearTimeout(id);
      this.activeTimers.delete(gameCode);
      logger.timer(`Cleared all timers for game '${gameCode}'`);
    }
  }

  /**
   * Clear every timer across all games.
   */
  clearAllTimers(): void {
    for (const [code, byType] of this.activeTimers) {
      for (const id of byType.values()) clearTimeout(id);
      logger.timer(`Cleared all timers for game '${code}'`);
    }
    this.activeTimers.clear();
  }

  /**
   * Check if a timer (or any timer) is active for a game.
   */
  hasActiveTimer(gameCode: string, type?: TimerType): boolean {
    const byType = this.activeTimers.get(gameCode);
    if (!byType) return false;
    return type ? byType.has(type) : byType.size > 0;
  }
} 