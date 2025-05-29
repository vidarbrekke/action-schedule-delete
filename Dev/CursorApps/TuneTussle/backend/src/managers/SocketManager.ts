import type { Server as SocketIOServer } from 'socket.io';
import type { Hooks } from '../models/types';
import { HookService } from '../services/hookService';
import { getLlmConfig } from '../llmConfigManager';

/**
 * Manages Socket.IO operations and judge socket tracking.
 * 
 * Responsibilities:
 * - Socket.IO server management
 * - Hook registration and management
 * - Judge socket ID tracking
 * - LLM configuration access
 */
export class SocketManager {
  private readonly judgeSocketIds = new Map<string, string>();

  constructor(
    private readonly hookService: HookService,
    private io?: SocketIOServer
  ) {}

  /**
   * Set or update the Socket.IO server instance.
   */
  public setSocketIOServer(io: SocketIOServer): void {
    this.io = io;
    this.hookService.setSocketIOServer(io);
  }

  /**
   * Register hooks for game events.
   */
  public registerHooks(hooks: Partial<Hooks>): void {
    this.hookService.registerHooks(hooks);
  }

  /**
   * Set the socket ID for a judge in a specific game.
   */
  public setJudgeSocketId(code: string, socketId: string): void {
    this.judgeSocketIds.set(code, socketId);
  }

  /**
   * Clear the socket ID for a judge in a specific game.
   */
  public clearJudgeSocketId(code: string): void {
    this.judgeSocketIds.delete(code);
  }

  /**
   * Get the socket ID for a judge in a specific game.
   */
  public getJudgeSocketId(code: string): string | null {
    return this.judgeSocketIds.get(code) ?? null;
  }

  /**
   * Get LLM configuration for a game (async operation).
   */
  public async getLlmConfigForGame(code: string): Promise<any> {
    return getLlmConfig();
  }

  /**
   * Get all judge socket mappings (for debugging).
   */
  public getAllJudgeSocketIds(): Map<string, string> {
    return new Map(this.judgeSocketIds);
  }

  /**
   * Clear all judge socket IDs (for testing/cleanup).
   */
  public clearAllJudgeSocketIds(): void {
    this.judgeSocketIds.clear();
  }

  /**
   * Check if a judge socket is registered for a game.
   */
  public hasJudgeSocket(code: string): boolean {
    return this.judgeSocketIds.has(code);
  }
} 