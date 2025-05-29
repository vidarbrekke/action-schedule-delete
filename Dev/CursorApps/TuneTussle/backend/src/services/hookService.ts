// hookService.ts - Manages event hooks for game events

import { Hooks } from '../models/types';
import * as Events from '../models/events';
import { Server as SocketIOServer } from 'socket.io';
import { container } from '../utils/dependencyContainer';
import { RealTimeEmissionService } from './realTimeEmissionService';
import { logger } from '../utils/logger';

/**
 * Service for managing and triggering event hooks
 */
export class HookService {
  public hooks: Partial<Hooks> = {};
  private io?: SocketIOServer;
  
  constructor(io?: SocketIOServer) {
    this.io = io;
  }

  setSocketIOServer(io: SocketIOServer) {
    this.io = io;
  }
  
  /**
   * Registers hooks for game events
   * @param hooks Object containing hook functions
   */
  registerHooks(hooksToRegister: Partial<Hooks>): void {
    logger.service('Registering hooks', { hooks: Object.keys(hooksToRegister) });
    this.hooks = { ...this.hooks, ...hooksToRegister };
  }
  
  /**
   * Safely emits a registered hook
   * @param hookName Name of the hook to emit
   * @param args Arguments to pass to the hook
   */
  private emitHook(hookName: keyof Hooks, ...args: any[]): void {
    logger.debug(`Attempting to emit ${hookName}`, { registered: !!this.hooks[hookName] }, 'HookService');
    if (this.hooks && typeof this.hooks[hookName] === 'function') {
      try {
        (this.hooks[hookName] as Function)(...args);
      } catch (e) {
        logger.error(`Error executing hook ${hookName}`, e, 'HookService');
      }
    }
  }
  
  /**
   * Event: Game started
   * @param payload Event payload
   */
  public onGameStart(payload: Events.GameStartEvent): void {
    logger.gameEvent('GameStart', payload.gameCode, { registered: !!this.hooks.onGameStart });
    this.hooks.onGameStart?.(payload);
  }
  
  /**
   * Event: Round started
   * @param payload Event payload
   */
  public onRoundStart(payload: Events.RoundStartEvent): void {
    logger.gameEvent('RoundStart', payload.gameCode, { registered: !!this.hooks.onRoundStart });
    this.hooks.onRoundStart?.(payload);
  }
  
  /**
   * Event: Player buzzed in
   * @param payload Event payload
   */
  public onPlayerBuzzedIn(payload: Events.PlayerBuzzedInEvent): void {
    logger.gameEvent('PlayerBuzzedIn', payload.gameCode, { 
      player: payload.playerName, 
      registered: !!this.hooks.onPlayerBuzzedIn 
    });
    this.hooks.onPlayerBuzzedIn?.(payload);
  }
  
  /**
   * Event: Answer timer started
   * @param payload Event payload
   */
  public onAnswerTimerStarted(payload: Events.AnswerTimerStartedEvent): void {
    console.log(`[HookService onAnswerTimerStarted] Game: ${payload.gameCode}, Player: ${payload.playerName}, Duration: ${payload.duration}. Registered: ${!!this.hooks.onAnswerTimerStarted}`);
    this.hooks.onAnswerTimerStarted?.(payload);
  }
  
  /**
   * Event: Answer timer expired
   * @param payload Event payload
   */
  public onAnswerTimerExpired(payload: Events.AnswerTimerExpiredEvent): void {
    console.log(`[HookService onAnswerTimerExpired] Game: ${payload.gameCode}, Player: ${payload.playerName}. Registered: ${!!this.hooks.onAnswerTimerExpired}`);
    this.hooks.onAnswerTimerExpired?.(payload);
  }
  
  /**
   * Event: Answer submitted
   * @param payload Event payload
   */
  public onAnswerSubmitted(payload: Events.AnswerSubmittedEvent): void {
    console.log(`[HookService onAnswerSubmitted] Game: ${payload.gameCode}, Player: ${payload.playerName}. Registered: ${!!this.hooks.onAnswerSubmitted}`);
    this.hooks.onAnswerSubmitted?.(payload);
    // Use the RealTimeEmissionService for Socket.IO communications
    const realTimeEmissionService = container.get<RealTimeEmissionService>('realTimeEmissionService');
    realTimeEmissionService.emitAnswerSubmitted(payload.gameCode, payload);
  }
  
  /**
   * Event: Round ended
   * @param payload Event payload
   */
  public onRoundEnded(payload: Events.RoundEndedEvent): void {
    console.log(`[HookService onRoundEnded] Game: ${payload.gameCode}. Registered: ${!!this.hooks.onRoundEnded}`);
    this.hooks.onRoundEnded?.(payload);
  }
  
  /**
   * Event: Game over
   * @param payload Event payload
   */
  public onGameOver(payload: Events.GameOverEvent): void {
    console.log(`[HookService onGameOver] Game: ${payload.gameCode}. Registered: ${!!this.hooks.onGameOver}`);
    this.hooks.onGameOver?.(payload);
  }
  
  /**
   * Event: Player joined
   * @param payload Event payload
   */
  public onPlayerJoined(payload: Events.PlayerJoinedEvent): void {
    console.log(`[HookService onPlayerJoined] Game: ${payload.gameCode}, Player: ${payload.playerName}. Registered: ${!!this.hooks.onPlayerJoined}`);
    this.hooks.onPlayerJoined?.(payload);
  }
  
  /**
   * Event: Player left
   * @param payload Event payload
   */
  public onPlayerLeft(payload: Events.PlayerLeftEvent): void {
    console.log(`[HookService onPlayerLeft] Game: ${payload.gameCode}, Player: ${payload.playerName}. Registered: ${!!this.hooks.onPlayerLeft}`);
    this.hooks.onPlayerLeft?.(payload);
  }
  
  /**
   * Event: Score update
   * @param payload Event payload
   */
  public onScoreUpdate(payload: Events.ScoreUpdateEvent): void {
    console.log(`[HookService onScoreUpdate] Game: ${payload.gameCode}. Registered: ${!!this.hooks.onScoreUpdate}`);
    this.hooks.onScoreUpdate?.(payload);
  }
  
  /**
   * Event: Buzzer enabled
   * @param payload Event payload
   */
  public onBuzzerEnabled(payload: Events.BuzzerEnabledEvent): void {
    console.log(`[HookService onBuzzerEnabled] Game: ${payload.gameCode}. Registered: ${!!this.hooks.onBuzzerEnabled}`);
    this.hooks.onBuzzerEnabled?.(payload);
  }
  
  /**
   * Event: Buzzer disabled
   * @param payload Event payload
   */
  public onBuzzerDisabled(payload: Events.BuzzerDisabledEvent): void {
    console.log(`[HookService onBuzzerDisabled] Game: ${payload.gameCode}. Registered: ${!!this.hooks.onBuzzerDisabled}`);
    this.hooks.onBuzzerDisabled?.(payload);
  }

  public onBuzzerStatusUpdate(payload: Events.BuzzerStatusUpdateEvent): void {
    console.log(`[HookService onBuzzerStatusUpdate] Game: ${payload.gameCode}. Registered: ${!!this.hooks.onBuzzerStatusUpdate}`);
    this.hooks.onBuzzerStatusUpdate?.(payload);
    // Use the RealTimeEmissionService for Socket.IO communications
    const realTimeEmissionService = container.get<RealTimeEmissionService>('realTimeEmissionService');
    const { gameCode, ...rest } = payload;
    realTimeEmissionService.emitBuzzerStatusUpdate(gameCode, rest);
  }

  /**
   * Event: All players attempted a round with no correct answer.
   * @param payload Event payload
   */
  public onAllPlayersAttemptedNoCorrect(payload: Events.AllPlayersAttemptedNoCorrectEvent): void {
    console.log(`[HookService onAllPlayersAttemptedNoCorrect] Game: ${payload.gameCode}. Registered: ${!!this.hooks.onAllPlayersAttemptedNoCorrect}`);
    this.hooks.onAllPlayersAttemptedNoCorrect?.(payload);
  }

  /**
   * Event: Round complete with all player data for results screen
   * @param payload Event payload
   */
  public onRoundComplete(payload: Events.RoundCompleteEvent): void {
    console.log(`[HookService onRoundComplete] Game: ${payload.gameCode}, Round: ${payload.roundNumber}. Registered: ${!!this.hooks.onRoundComplete}`);
    this.hooks.onRoundComplete?.(payload);
  }

  public clearHooks(): void {
    this.hooks = {};
  }
} 