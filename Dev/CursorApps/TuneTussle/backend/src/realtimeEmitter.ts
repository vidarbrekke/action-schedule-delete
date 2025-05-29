import { Server as SocketIOServer } from 'socket.io';
import { GameSessionManager } from './gameSessionManager';
import { GameSession, GameRound, AnswerRecord, Participant } from './models/types';
import { container } from './utils/dependencyContainer';
import { RealTimeEmissionService } from './services/realTimeEmissionService';

// Utility to build a serializable game state snapshot for the frontend
function buildGameStateSnapshot(game: GameSession) {
  const currentRound = game.rounds[game.currentRoundIndex];
  
  // Build participants with role information and current scores
  const participantsWithRoles = Array.from(game.participants.values()).map(participant => ({
    ...participant,
    role: participant.name === game.judgeName ? 'judge' : 'participant',
    score: game.scores.get(participant.name) || 0  // Include current score from the scores Map
  }));
  
  return {
    code: game.code,
    judgeName: game.judgeName,
    participants: participantsWithRoles,
    scores: Object.fromEntries(game.scores),
    gameSettings: game.gameSettings,
    gameState: game.gameState,
    currentRoundIndex: game.currentRoundIndex,
    rounds: game.rounds.map((r: GameRound) => ({
      ...r,
      playersWhoAttempted: Array.from(r.playersWhoAttempted),
    })),
    currentRoundDetails: game.currentRoundDetails ? {
      ...game.currentRoundDetails,
      playersWhoAttempted: Array.from(game.currentRoundDetails.playersWhoAttempted),
      audioUrl: game.currentRoundDetails.trackUrl, // Map trackUrl to audioUrl for frontend compatibility
    } : null,
    buzzOrder: game.buzzOrder,
    answers: Object.fromEntries(Array.from(game.answers.entries()) as [string, AnswerRecord[]][]),
    activePlayerName: currentRound && currentRound.buzzedInPlayer ? currentRound.buzzedInPlayer : null,
    isBuzzActive: currentRound ? currentRound.isBuzzerOpenForNewAttempts : false,
  };
}

export function registerRealtimeHooks(io: SocketIOServer, manager: GameSessionManager) {
  // Register io with the dependency container
  container.registerSocketIO(io);
  
  // Get the realTimeEmissionService and set the Socket.IO server
  const realTimeEmissionService = container.get<RealTimeEmissionService>('realTimeEmissionService');
  realTimeEmissionService.setSocketIOServer(io);
  
  manager.registerHooks({
    onGameStart: (payload) => {
      const gameResult = manager.getGameState(payload.gameCode);
      if (gameResult && gameResult.success) {
        const snapshot = buildGameStateSnapshot(gameResult.data);
        console.log(`[RealtimeEmitter] Emitting 'gameState' to room ${payload.gameCode}:`, snapshot);
        io.to(payload.gameCode).emit('gameState', snapshot);
        // Emit 'gameStarted' event to the judge and the room for compatibility
        const judge = gameResult.data.judgeName;
        const judgeParticipant = Array.from(gameResult.data.participants.values()).find(p => p.name === judge);
        const judgeSocketId = manager.getJudgeSocketId ? manager.getJudgeSocketId(payload.gameCode) : null;
        const eventPayload = { gameCode: gameResult.data.code, judgeName: gameResult.data.judgeName, gameSettings: gameResult.data.gameSettings };
        if (judgeSocketId && io.sockets && io.sockets.sockets && io.sockets.sockets.get(judgeSocketId)) {
          console.log(`[RealtimeEmitter] Emitting 'gameStarted' to judge socket ${judgeSocketId}:`, eventPayload);
          io.to(judgeSocketId).emit('gameStarted', eventPayload);
        }
        // Also emit to the room for compatibility with tests
        console.log(`[RealtimeEmitter] Emitting 'gameStarted' to room ${payload.gameCode}:`, eventPayload);
        io.to(payload.gameCode).emit('gameStarted', eventPayload);
      }
    },
    onRoundStart: (payload) => {
      const gameResult = manager.getGameState(payload.gameCode);
      if (gameResult && gameResult.success) {
        const snapshot = buildGameStateSnapshot(gameResult.data);
        console.log(`[RealtimeEmitter] Emitting 'gameState' to room ${payload.gameCode}:`, snapshot);
        io.to(payload.gameCode).emit('gameState', snapshot);
        
        // Prepare round for client: convert Set to Array, map trackUrl to youtubeLink
        const round = payload.roundDetails;
        if (!round) throw new Error('RealtimeEmitter: roundDetails missing in onRoundStart payload');
        const roundForClient = {
          ...round,
          playersWhoAttempted: Array.isArray(round.playersWhoAttempted)
            ? round.playersWhoAttempted
            : Array.from(round.playersWhoAttempted),
          audioUrl: round.trackUrl, // For frontend compatibility
        };

        // Get next round info for judges (for artwork preloading)
        let nextRoundInfo: { title: string; artist: string } | undefined;
        const game = gameResult.data;
        const nextRoundIndex = game.currentRoundIndex + 1;
        if (nextRoundIndex < game.gameSettings.numberOfRounds && game.songList && game.songList[nextRoundIndex]) {
          const nextSong = game.songList[nextRoundIndex];
          nextRoundInfo = {
            title: nextSong.title,
            artist: nextSong.artist
          };
          console.log(`[RealtimeEmitter] Next round info for preloading:`, nextRoundInfo);
        }

        const baseRoundPayload = {
          gameCode: gameResult.data.code,
          currentRoundNumber: gameResult.data.currentRoundIndex + 1,
          round: roundForClient,
          totalRounds: gameResult.data.gameSettings?.numberOfRounds || 0
        };

        // Send different payloads to judge vs players
        const judgeSocketId = manager.getJudgeSocketId ? manager.getJudgeSocketId(payload.gameCode) : null;
        
        if (judgeSocketId && nextRoundInfo) {
          // Send enhanced payload to judge with next round info
          const judgePayload = {
            ...baseRoundPayload,
            nextRound: nextRoundInfo // Only judges get next round info to avoid spoilers
          };
          console.log(`[RealtimeEmitter] Emitting enhanced 'roundStarted' to judge:`, judgePayload);
          io.to(judgeSocketId).emit('roundStarted', judgePayload);
          
          // Send standard payload to all other players in the room
          console.log(`[RealtimeEmitter] Emitting standard 'roundStarted' to room (excluding judge):`, baseRoundPayload);
          io.to(payload.gameCode).except(judgeSocketId).emit('roundStarted', baseRoundPayload);
        } else {
          // No judge socket or next round info available, send standard payload to all
          console.log(`[RealtimeEmitter] Emitting standard 'roundStarted' to room:`, baseRoundPayload);
          io.to(payload.gameCode).emit('roundStarted', baseRoundPayload);
        }
      }
    },
    onAnswerSubmitted: (payload) => {
      const gameResult = manager.getGameState(payload.gameCode);
      if (gameResult && gameResult.success) {
        // Emit the full game state first for immediate UI updates
        const snapshot = buildGameStateSnapshot(gameResult.data);
        console.log(`[RealtimeEmitter] Emitting 'gameState' to room ${payload.gameCode} after answer submission:`, snapshot);
        io.to(payload.gameCode).emit('gameState', snapshot);
        
        // Also emit the specific answerSubmitted event for targeted handling
        const answerPayload = { 
          player: payload.playerName, 
          answer: payload.answer, 
          isCorrect: payload.isCorrect,
          correctAnswer: payload.correctAnswer,
          message: payload.message,
          points: payload.points,
          status: payload.status,
          canAdvance: payload.canAdvance
        };
        console.log(`[RealtimeEmitter] Emitting 'answerSubmitted' to room ${payload.gameCode}:`, answerPayload);
        io.to(payload.gameCode).emit('answerSubmitted', answerPayload);
      }
    },
    onScoreUpdate: (payload) => {
      const gameResult = manager.getGameState(payload.gameCode);
      if (gameResult && gameResult.success) {
        const snapshot = buildGameStateSnapshot(gameResult.data);
        console.log(`[RealtimeEmitter] Emitting 'gameState' to room ${payload.gameCode}:`, snapshot);
        io.to(payload.gameCode).emit('gameState', snapshot);
        
        // Also emit the specific scoreUpdate event for targeted handling
        const scoreUpdatePayload = {
          scores: Object.fromEntries(payload.scores)
        };
        console.log(`[RealtimeEmitter] Emitting 'scoreUpdate' to room ${payload.gameCode}:`, scoreUpdatePayload);
        io.to(payload.gameCode).emit('scoreUpdate', scoreUpdatePayload);
      }
    },
    onGameOver: (payload) => {
      const gameResult = manager.getGameState(payload.gameCode);
      if (gameResult && gameResult.success) {
        const snapshot = buildGameStateSnapshot(gameResult.data);
        console.log(`[RealtimeEmitter] Emitting 'gameState' to room ${payload.gameCode}:`, snapshot);
        io.to(payload.gameCode).emit('gameState', snapshot);
        // Emit gameOver with expected payload for integration tests
        const gameOverPayload = {
          gameCode: gameResult.data.code,
          finalScores: Object.fromEntries(gameResult.data.scores)
        };
        console.log(`[RealtimeEmitter] Emitting 'gameOver' to room ${payload.gameCode}:`, gameOverPayload);
        io.to(payload.gameCode).emit('gameOver', gameOverPayload);
      }
    },
    onPlayerJoined: (payload) => {
      console.log(`[RealtimeEmitter] Hook: onPlayerJoined - Game: ${payload.gameCode}, Player: ${payload.playerName}, Participants Count: ${payload.participants.length}`);
      const gameResult = manager.getGameState(payload.gameCode);
      if (gameResult && gameResult.success) io.to(payload.gameCode).emit('gameState', buildGameStateSnapshot(gameResult.data));
    },
    onPlayerLeft: (payload) => {
      console.log(`[RealtimeEmitter] Hook: onPlayerLeft - Game: ${payload.gameCode}, Player: ${payload.playerName}, Participants Count: ${payload.participants.length}`);
      const gameResult = manager.getGameState(payload.gameCode);
      if (gameResult && gameResult.success) io.to(payload.gameCode).emit('gameState', buildGameStateSnapshot(gameResult.data));
    },
    onAnswerTimerExpired: (payload) => {
      const gameResult = manager.getGameState(payload.gameCode);
      if (gameResult && gameResult.success) io.to(payload.gameCode).emit('gameState', buildGameStateSnapshot(gameResult.data));
    },
    onPlayerBuzzedIn: (payload) => {
      const gameResult = manager.getGameState(payload.gameCode);
      if (gameResult && gameResult.success) io.to(payload.gameCode).emit('gameState', buildGameStateSnapshot(gameResult.data));
    },
    onAllPlayersAttemptedNoCorrect: (payload) => {
      const gameResult = manager.getGameState(payload.gameCode);
      if (gameResult && gameResult.success) {
        // Emit updated game state first
        const snapshot = buildGameStateSnapshot(gameResult.data);
        console.log(`[RealtimeEmitter] Emitting 'gameState' to room ${payload.gameCode} after all players attempted:`, snapshot);
        io.to(payload.gameCode).emit('gameState', snapshot);
        
        // Emit the specific event for all players attempted scenario
        const allAttemptedPayload = {
          message: payload.message,
          canAdvance: payload.canAdvance,
          isGameOver: payload.isGameOver,
          correctAnswer: payload.correctAnswer
        };
        console.log(`[RealtimeEmitter] Emitting 'allPlayersAttemptedNoCorrect' to room ${payload.gameCode}:`, allAttemptedPayload);
        io.to(payload.gameCode).emit('allPlayersAttemptedNoCorrect', allAttemptedPayload);
      }
    },
    onRoundComplete: (payload) => {
      const gameResult = manager.getGameState(payload.gameCode);
      if (gameResult && gameResult.success) {
        // Emit updated game state first
        const snapshot = buildGameStateSnapshot(gameResult.data);
        console.log(`[RealtimeEmitter] Emitting 'gameState' to room ${payload.gameCode} after round complete:`, snapshot);
        io.to(payload.gameCode).emit('gameState', snapshot);
        
        // Emit the round complete event with all the data for the results screen
        const roundCompletePayload = {
          roundNumber: payload.roundNumber,
          correctAnswer: payload.correctAnswer,
          playersWithPoints: payload.playersWithPoints,
          roundParticipants: payload.roundParticipants,
          artworkUrl: payload.artworkUrl,
          canAdvance: payload.canAdvance,
          isGameOver: payload.isGameOver
        };
        console.log(`[RealtimeEmitter] Emitting 'roundComplete' to room ${payload.gameCode}:`, roundCompletePayload);
        io.to(payload.gameCode).emit('roundComplete', roundCompletePayload);
      }
    }
  });
} 