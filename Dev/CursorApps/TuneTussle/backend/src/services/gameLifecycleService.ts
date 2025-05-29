import { GameSession, Song, GameSong } from '../models/types';
import { generateCode } from '../utils/gameUtils';
import { HookService } from './hookService';

export class GameLifecycleService {
  constructor(private hookService: HookService) {}

  createInitialSession(
    judgeName: string,
    gameSettings: { prompt: string; numberOfRounds: number; llmModel?: string },
    generateUniqueCodeCb: () => string // Callback to generate a unique code
  ): { success: boolean; gameSession?: GameSession; message?: string } {
    if (!judgeName || judgeName.trim().length === 0) {
      return { success: false, message: 'Judge name cannot be empty.' };
    }
    if (!gameSettings || !gameSettings.prompt || gameSettings.prompt.trim().length === 0) {
      return { success: false, message: 'Game prompt cannot be empty.' };
    }
    if (typeof gameSettings.numberOfRounds !== 'number' || gameSettings.numberOfRounds <= 0 || !Number.isInteger(gameSettings.numberOfRounds)) {
      return { success: false, message: 'Number of rounds must be a positive integer.' };
    }

    const code = generateUniqueCodeCb();
    if (!code) {
        return { success: false, message: 'Failed to generate a unique game code.' };
    }

    const newGame: GameSession = {
      code,
      judgeName,
      participants: new Map([[judgeName, { id: judgeName, name: judgeName, score: 0, role: 'judge' }]]),
      gameSettings: { ...gameSettings },
      gameState: 'lobby',
      songList: [],
      rounds: [],
      currentRoundIndex: -1,
      scores: new Map(), // Judge should not be in scores map - only players
      buzzOrder: [],
      answers: new Map(),
    };
    return { success: true, gameSession: newGame };
  }

  startGame(
    game: GameSession
  ): { success: boolean; message?: string; errorCode?: 'ALREADY_STARTED' | 'NO_SONGS_GENERATED' } {
    // Allow starting if songs are ready (even if still in lobby) or explicitly in 'ready' state
    if (game.gameState !== 'lobby' && game.gameState !== 'ready') {
      return { success: false, message: 'Game is not in a startable state (must be lobby or ready).', errorCode: 'ALREADY_STARTED' };
    }
    if (!Array.isArray(game.songList) || game.songList.length === 0) {
      return { success: false, message: 'No songs were generated for this game.', errorCode: 'NO_SONGS_GENERATED' };
    }

    game.songList = game.songList.map(song => ({ ...song, used: false }));
    game.gameState = 'playing';
    game.rounds = [];
    game.currentRoundIndex = -1;

    this.hookService.onGameStart({
      gameCode: game.code,
      game
    });
    return { success: true, message: 'Game started successfully.' };
  }

  setSongsAndReady(game: GameSession, songs: GameSong[]): void {
    game.songList = songs.map(s => ({ ...s, used: false }));
    game.gameSettings.numberOfRounds = songs.length; 
    if (game.gameState === 'lobby' || game.gameState === 'pending') {
        game.gameState = 'ready'; 
    }

    // For now, GameSessionManager can emit a custom socket event to the judge if needed.
  }

  setGameErrored(game: GameSession, error: any): void {
    game.gameState = 'error';
    game.errorDetails = error?.message || 'Unknown error during game setup.';

    // For now, GameSessionManager can emit a custom socket event to the judge.
  }
  
  finishGame(game: GameSession): void {
    if (game.gameState !== 'finished') {
        game.gameState = 'finished';
        this.hookService.onGameOver({
          gameCode: game.code
          // Add more fields if needed (e.g., scores)
        });
    }
  }
} 