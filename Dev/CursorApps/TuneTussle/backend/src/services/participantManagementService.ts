import { GameSession, Participant } from '../models/types';
import { HookService } from './hookService';

export class ParticipantManagementService {
  constructor(private hookService: HookService) {}

  async joinGame(
    game: GameSession,
    playerName: string
  ): Promise<{ success: boolean; message?: string }> {
    if (game.participants.has(playerName)) {
      return { success: false, message: 'Player name already taken in this game.' };
    }
    if (game.gameState !== 'lobby' && game.gameState !== 'ready' && game.gameState !== 'playing') {
      return { success: false, message: 'Game is not joinable at this stage.' };
    }

    const participant: Participant = { id: playerName, name: playerName, score: 0, role: 'participant' };
    game.participants.set(playerName, participant);
    game.scores.set(playerName, 0);

    // Pass the full game session to the hook as per HookService's existing onPlayerJoined signature
    this.hookService.onPlayerJoined({
      gameCode: game.code,
      playerName,
      participants: Array.from(game.participants.values())
    });
    return { success: true };
  }

  async leaveGame(
    game: GameSession,
    playerName: string,
  ): Promise<{ success: boolean; message?: string; gameEnded?: boolean; judgeLeft?: boolean }> {
    const participant = game.participants.get(playerName);
    if (!participant) {
      return { success: false, message: `Participant ${playerName} not found in game.` };
    }

    game.participants.delete(playerName);
    game.scores.delete(playerName);

    // Pass the full game session to the hook as per HookService's existing onPlayerLeft signature
    this.hookService.onPlayerLeft({
      gameCode: game.code,
      playerName,
      participants: Array.from(game.participants.values())
    });

    if (playerName === game.judgeName) {
      // Signal that the judge left and whether the game might have ended as a result
      if (game.gameState === 'lobby' || game.gameState === 'ready') {
        return { success: true, message: 'Judge left lobby; game deleted as it cannot proceed.', gameEnded: true, judgeLeft: true };
      } else {
        return { success: true, message: 'Judge left an active game; game has been set to finished.', gameEnded: false, judgeLeft: true };
      }
    }

    const nonJudgeParticipants = Array.from(game.participants.values()).filter(p => p.role === 'participant');
    if (nonJudgeParticipants.length === 0 && game.gameState === 'playing') {
      // Signal that the game ended because no participants remain
      return { success: true, message: `Game ${game.code} ended because no participants remain.`, gameEnded: true, judgeLeft: false };
    }
    
    let message = `Player ${playerName} left game ${game.code}.`;
    if (game.gameState === 'lobby' || game.gameState === 'ready') {
        message = `Player ${playerName} left the lobby.`;
    }
    return { success: true, message, gameEnded: false, judgeLeft: false };
  }

  listParticipants(game: GameSession): string[] {
    return Array.from(game.participants.keys());
  }

  getParticipants(game: GameSession): Participant[] {
    return Array.from(game.participants.values());
  }
} 