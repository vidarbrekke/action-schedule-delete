import type { GameSession } from '../../models/types';

export class AnswerSubmissionService {
  constructor(
    hookService: any,
    timerService: any,
    gameLifecycleService: any,
    roundManagementService: any
  ) {}

  submitAnswer(game: GameSession, player: string, answer: { songTitle: string; artist: string }) {
    return { success: true, score: 1 };
  }

  // Stub other methods as needed
} 