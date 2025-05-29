import { Server as SocketIOServer } from 'socket.io';
import { GameSession } from '../models/types';

/**
 * Service for real-time emissions to clients via Socket.IO
 */
export class RealTimeEmissionService {
  private io?: SocketIOServer;

  constructor(io?: SocketIOServer) {
    this.io = io;
  }

  /**
   * Set the Socket.IO server instance
   */
  setSocketIOServer(io: SocketIOServer): void {
    this.io = io;
  }

  /**
   * Emit an answer submission event to a game room
   */
  emitAnswerSubmitted(gameCode: string, payload: any): void {
    if (!this.io) {
      console.warn('[RealTimeEmissionService] Socket.IO not initialized for emitAnswerSubmitted');
      return;
    }

    console.log(`[RealTimeEmissionService] Emitting 'answerSubmitted' to room ${gameCode}:`, payload);
    this.io.to(gameCode).emit('answerSubmitted', payload);
  }

  /**
   * Emit a buzzer status update event to a game room
   */
  emitBuzzerStatusUpdate(gameCode: string, payload: any): void {
    if (!this.io) {
      console.warn('[RealTimeEmissionService] Socket.IO not initialized for emitBuzzerStatusUpdate');
      return;
    }

    console.log(`[RealTimeEmissionService] Emitting 'buzzerStatus' to room ${gameCode}:`, payload);
    this.io.to(gameCode).emit('buzzerStatus', payload);
  }

  /**
   * Emit an event when all players attempted but none got the correct answer
   */
  emitAllPlayersAttemptedNoCorrect(
    gameCode: string,
    payload: {
      message: string;
      canAdvance: boolean;
      isGameOver: boolean;
      correctAnswer: { title: string; artist: string };
    }
  ): void {
    if (!this.io) return;
    
    console.log(`[RealTimeEmissionService] Emitting 'allPlayersAttemptedNoCorrect' to gameCode ${gameCode}:`, payload);
    this.io.to(gameCode).emit('allPlayersAttemptedNoCorrect', payload);
  }
} 