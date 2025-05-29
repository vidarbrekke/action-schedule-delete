import { Socket } from 'socket.io';
import { GameSessionManager } from './gameSessionManager';
import { parseSongAndArtist } from './utils/answerParser';
import { batchEventProcessorFactory } from './services/batchEventProcessorFactory';
import { logger } from './utils/logger';

// Type-safe payload interfaces
interface JoinGameRoomPayload {
  gameCode: string;
  playerName: string;
  isJudge?: boolean;
}

interface BuzzInPayload {
  gameCode: string;
  playerName: string;
}

interface SubmitAnswerPayload {
  gameCode: string;
  playerName: string;
  answer: {
    raw?: string;
    songTitle?: string;
    artist?: string;
  };
}

interface NextRoundPayload {
  gameCode: string;
}

interface EndRoundPayload {
  gameCode: string;
}

interface GetGameStatePayload {
  gameCode: string;
}

interface AdjustScorePayload {
  gameCode: string;
  playerId: string;
  newScore: number;
}

interface CreateGamePayload {
  judgeName: string;
  gameSettings: {
    numberOfRounds: number;
    genre?: string;
    difficulty?: string;
    promptType?: string;
    customPrompt?: string;
  };
}

export function registerClientEventHandlers(socket: Socket, gameSessionManager: GameSessionManager) {
  // Get managed batch processor instance for this socket connection
  const batchProcessor = batchEventProcessorFactory.getProcessor(socket, gameSessionManager);
  socket.on('joinGameRoom', async (payload: unknown, ack?: Function) => {
    try {
      if (!payload || typeof payload !== 'object') {
        if (ack) ack({ success: false, message: 'Invalid payload: must be an object', errorCode: 'INVALID_PAYLOAD' });
        return;
      }

      const { gameCode, playerName, isJudge } = payload as JoinGameRoomPayload;
      
      if (!gameCode || typeof gameCode !== 'string') {
        if (ack) ack({ success: false, message: 'Game code is required and must be a string', errorCode: 'INVALID_GAME_CODE' });
        return;
      }
      
      if (!playerName || typeof playerName !== 'string') {
        if (ack) ack({ success: false, message: 'Player name is required and must be a string', errorCode: 'INVALID_PLAYER_NAME' });
        return;
      }

      const gameResult = gameSessionManager.getGameDetails(gameCode);
      
      if (!gameResult.success) {
        if (ack) ack({ success: false, message: 'Game not found.', errorCode: 'GAME_NOT_FOUND' });
        return;
      }
      const game = gameResult.data;
      
      const isActuallyJudge = game.judgeName === playerName;
      
      socket.join(gameCode);
      
      socket.data.playerName = playerName;
      socket.data.gameCode = gameCode;
      socket.data.isJudge = isActuallyJudge;
      
      const role = isActuallyJudge ? 'judge' : 'participant';
      
      if (isActuallyJudge) {
        gameSessionManager.setJudgeSocketId(gameCode, socket.id);
        
        // Check game state and notify judge
        if (game.gameState === 'pending') {
          socket.emit('generatingSongs', { code: gameCode });
        }
        else if (game.gameState === 'ready') {
          socket.emit('gameReady', { code: gameCode });
        }
      } else {
        // Check if player is already in the game (from HTTP join)
        const isPlayerAlreadyInGame = game.participants.has(playerName);
        
        if (!isPlayerAlreadyInGame) {
          // Player not in game yet, add them via socket join
          const joinResult = await gameSessionManager.joinGame(gameCode, playerName);
          if (!joinResult.success) {
            if (ack) ack({ success: false, message: joinResult.message, errorCode: 'JOIN_FAILED' });
            return;
          }
        }
      }
      
      const successAck = { 
        success: true, 
        role, 
        message: `Joined game ${gameCode} as ${role}`
      };
      if (ack) ack(successAck);

      // Emit 'roleAssigned' to the client with necessary data
      const participantNamesResult = gameSessionManager.listParticipants(gameCode);
      if (!participantNamesResult.success) {
        if (ack) ack({ success: false, message: 'Could not list participants.', errorCode: 'PARTICIPANT_LIST_FAILED' });
        return;
      }
      const participantNames = participantNamesResult.data;
      const gameScores = game.scores; // Get the scores map from the game object

      const participantsForFrontend = participantNames
        ? participantNames.map(nameKey => {
            const participant = game.participants.get(nameKey);
            const playerScore = gameScores.get(nameKey) || 0;
            const playerRole = nameKey === game.judgeName ? 'judge' : 'participant';

            return {
              id: nameKey,
              name: participant ? participant.name : "",
              score: playerScore,
              role: playerRole
            };
          })
        : [];

      const gameSettings = { // Construct gameSettings object based on what GameSession stores
        prompt: game.gameSettings.prompt,
        numberOfRounds: game.gameSettings.numberOfRounds,
        // Add any other relevant settings the frontend might need
      };

      // Use setImmediate to ensure ACK is processed before event emission (avoids race in tests and real clients)
      setImmediate(() => {
        socket.emit('roleAssigned', { 
          role, // The role of THIS socket/client
          participants: participantsForFrontend, // The full list of participants with their details
          gameSettings 
        });
      });

      // Emit current round to late joiners if game is in progress
      // Use setImmediate to ensure this happens after room join and avoid race conditions with hook emissions
      if (game.gameState === 'playing') {
        setImmediate(() => {
          const currentRoundResult = gameSessionManager.getCurrentRound(gameCode);
          const gameSessionResult = gameSessionManager.getGameState(gameCode);
          if (currentRoundResult.success && gameSessionResult.success) {
            const currentRoundData = currentRoundResult.data;
            const gameSession = gameSessionResult.data;
            const roundForClient = { ...currentRoundData };
            if (role !== 'judge') { // use the client's actual role
                   roundForClient.artist = ''; // Keep hiding artist for non-judges on late join if that was intended
                   // Potentially hide songTitle too for non-judges on late join to an active round if not yet revealed
                   // roundForClient.songTitle = 'Question is active'; 
            }

            const payload = {
              gameCode: gameCode, // Add missing gameCode field
              round: roundForClient, // Contains songTitle, artist (possibly blanked), youtubeLink
              currentRoundNumber: gameSession.currentRoundIndex + 1,
              totalRounds: gameSession.gameSettings.numberOfRounds,
              isLateJoin: true // Flag to distinguish late-join emissions from regular round start emissions
              // No separate top-level 'correctAnswer' string here, consistent with main emit
            };
            socket.emit('roundStarted', payload);
          }
        });
      }
    } catch (err: unknown) {
      const error = err as Error;
      console.error(`[Server Event Error] Event: joinGameRoom, Socket: ${socket.id}, Error: ${error.message}, Stack: ${error.stack}`);
      if (ack) ack({ success: false, message: `Server error processing joinGameRoom: ${error.message}`, errorCode: 'SERVER_ERROR' });
    }
  });

  socket.on('buzzIn', (payload: unknown, ack?: Function) => {
    try {
      if (!payload || typeof payload !== 'object') {
        if (ack) ack({ success: false, message: 'Invalid payload: must be an object', errorCode: 'INVALID_PAYLOAD' });
        return;
      }

      const { gameCode, playerName } = payload as BuzzInPayload;
      
      if (!gameCode || typeof gameCode !== 'string') {
        if (ack) ack({ success: false, message: 'Game code is required and must be a string', errorCode: 'INVALID_GAME_CODE' });
        return;
      }
      
      if (!playerName || typeof playerName !== 'string') {
        if (ack) ack({ success: false, message: 'Player name is required and must be a string', errorCode: 'INVALID_PLAYER_NAME' });
        return;
      }

      const result = gameSessionManager.playerBuzzIn(gameCode, playerName);
      if (ack) ack(result);
    } catch (err: unknown) {
      const error = err as Error;
      console.error(`[Server Event Error] Event: buzzIn, Socket: ${socket.id}, Error: ${error.message}, Stack: ${error.stack}`);
      if (ack) ack({ success: false, message: `Server error processing buzzIn: ${error.message}`, errorCode: 'SERVER_ERROR' });
    }
  });

  socket.on('submitAnswer', async (payload: unknown, ack?: Function) => {
    try {
      if (!payload || typeof payload !== 'object') {
        if (ack) ack({ success: false, message: 'Invalid payload: must be an object', errorCode: 'INVALID_PAYLOAD' });
        return;
      }

      const { gameCode, playerName, answer } = payload as SubmitAnswerPayload;
      
      if (!gameCode || typeof gameCode !== 'string') {
        if (ack) ack({ success: false, message: 'Game code is required and must be a string', errorCode: 'INVALID_GAME_CODE' });
        return;
      }
      
      if (!playerName || typeof playerName !== 'string') {
        if (ack) ack({ success: false, message: 'Player name is required and must be a string', errorCode: 'INVALID_PLAYER_NAME' });
        return;
      }

      if (!answer || typeof answer !== 'object') {
        if (ack) ack({ success: false, message: 'Answer is required and must be an object', errorCode: 'INVALID_ANSWER' });
        return;
      }

      let parsedAnswers: Array<{ songTitle: string; artist: string }> = [];
      
      // Get game and round details first to have correct answer available
      const gameSessionResult = gameSessionManager.getGameDetails(gameCode);
      if (!gameSessionResult.success) {
        if (ack) ack({ success: false, message: 'Game not found.', errorCode: 'INVALID_STATE' });
        return;
      }
      const game = gameSessionResult.data;
      const currentRoundResult = gameSessionManager.getCurrentRound(gameCode);
      if (!currentRoundResult.success) {
        if (ack) ack({ success: false, message: 'No active round.', errorCode: 'NO_ACTIVE_ROUND' });
        return;
      }
      const currentRound = currentRoundResult.data;
      const correctAnswerDetails = { title: currentRound.songTitle, artist: currentRound.artist };
      
      // Now parse the answer with the correct answer for intelligent matching
      if (answer && typeof answer.raw === 'string') {
        parsedAnswers = parseSongAndArtist(answer.raw, correctAnswerDetails);
      } else if (answer && typeof answer.songTitle === 'string' && typeof answer.artist === 'string') {
        parsedAnswers = [{ songTitle: answer.songTitle, artist: answer.artist }];
      } else {
        if (ack) ack({ success: false, message: 'Invalid answer format. Expected { raw: string } or { songTitle: string, artist: string }.', errorCode: 'INVALID_ANSWER_FORMAT' });
        return;
      }
      
      // Try all interpretations and pick the one with the highest score
      let bestResult = null;
      let bestScore = -1;
      let bestEvaluation = null;
      
      for (const parsed of parsedAnswers) {
        // Use the same evaluation logic as the backend
        const { evaluateAnswer } = require('./services/gameRoundService');
        const evalResult = evaluateAnswer(parsed, correctAnswerDetails);
        if (evalResult.score > bestScore) {
          bestScore = evalResult.score;
          bestResult = parsed;
          bestEvaluation = evalResult;
        }
      }
      if (!bestResult) {
        bestResult = parsedAnswers[0];
        // If no result was found, evaluate the fallback
        const { evaluateAnswer } = require('./services/gameRoundService');
        bestEvaluation = evaluateAnswer(bestResult, correctAnswerDetails);
      }
      const result = await gameSessionManager.submitAnswer(gameCode, playerName, bestResult);
      if (ack) ack(result);
    } catch (err: unknown) {
      const error = err as Error;
      console.error(`[Server Event Error] Event: submitAnswer, Socket: ${socket.id}, Error: ${error.message}, Stack: ${error.stack}`);
      if (ack) ack({ success: false, message: `Server error processing submitAnswer: ${error.message}`, errorCode: 'SERVER_ERROR' });
    }
  });

  socket.on('nextRound', (payload: unknown, ack?: Function) => {
    try {
      if (!payload || typeof payload !== 'object') {
        if (ack) ack({ success: false, message: 'Invalid payload: must be an object', errorCode: 'INVALID_PAYLOAD' });
        return;
      }

      const { gameCode } = payload as NextRoundPayload;
      
      if (!gameCode || typeof gameCode !== 'string') {
        if (ack) ack({ success: false, message: 'Game code is required and must be a string', errorCode: 'INVALID_GAME_CODE' });
        return;
      }

      const playerName = socket.data.playerName;
      const requestingGameCode = gameCode; // Rename for clarity

      if (!playerName) {
        if (ack) ack({ success: false, message: 'Player not identified for this socket connection.', errorCode: 'PLAYER_NOT_IDENTIFIED' });
        return;
      }

      const gameDetailsResult = gameSessionManager.getGameDetails(requestingGameCode);
      if (!gameDetailsResult.success) {
        if (ack) ack({ success: false, message: `Game not found: ${requestingGameCode}`, errorCode: 'GAME_NOT_FOUND' });
        return;
      }
      const gameDetails = gameDetailsResult.data;
      if (gameDetails.judgeName !== playerName) {
        if (ack) ack({ success: false, message: 'Only the judge can start the next round.', errorCode: 'ONLY_JUDGE_CAN_START_NEXT_ROUND' });
        return;
      }
      
      // Call the corrected method name from GameSessionManager
      const result = gameSessionManager.startNextRound(requestingGameCode);
      
      if (ack) ack(result);
    } catch (err: unknown) {
      const error = err as Error;
      console.error(`[Server Event Error] Event: nextRound, Socket: ${socket.id}, Error: ${error.message}, Stack: ${error.stack}`);
      if (ack) ack({ success: false, message: `Server error processing nextRound: ${error.message}`, errorCode: 'SERVER_ERROR' });
    }
  });

  socket.on('endRound', async (payload: unknown, ack?: Function) => {
    try {
      if (!payload || typeof payload !== 'object') {
        if (ack) ack({ success: false, message: 'Invalid payload: must be an object', errorCode: 'INVALID_PAYLOAD' });
        return;
      }

      const { gameCode } = payload as EndRoundPayload;
      
      if (!gameCode || typeof gameCode !== 'string') {
        if (ack) ack({ success: false, message: 'Game code is required and must be a string', errorCode: 'INVALID_GAME_CODE' });
        return;
      }

      const playerName = socket.data.playerName;

      if (!playerName) {
        if (ack) ack({ success: false, message: 'Player not identified for this socket connection.', errorCode: 'PLAYER_NOT_IDENTIFIED' });
        return;
      }

      const gameDetailsResult = gameSessionManager.getGameDetails(gameCode);
      if (!gameDetailsResult.success) {
        if (ack) ack({ success: false, message: `Game not found: ${gameCode}`, errorCode: 'GAME_NOT_FOUND' });
        return;
      }
      const gameDetails = gameDetailsResult.data;
      if (gameDetails.judgeName !== playerName) {
        if (ack) ack({ success: false, message: 'Only the judge can end the round.', errorCode: 'ONLY_JUDGE_CAN_END_ROUND' });
        return;
      }
      
      // End the current round by closing the buzzer and marking round as complete
      const result = await gameSessionManager.endCurrentRound(gameCode);
      
      if (ack) ack(result);
    } catch (err: unknown) {
      const error = err as Error;
      console.error(`[Server Event Error] Event: endRound, Socket: ${socket.id}, Error: ${error.message}, Stack: ${error.stack}`);
      if (ack) ack({ success: false, message: `Server error processing endRound: ${error.message}`, errorCode: 'SERVER_ERROR' });
    }
  });

  socket.on('getGameState', (payload: unknown, ack?: Function) => {
    try {
      if (!payload || typeof payload !== 'object') {
        if (ack) ack({ success: false, message: 'Invalid payload: must be an object', errorCode: 'INVALID_PAYLOAD' });
        return;
      }

      const { gameCode } = payload as GetGameStatePayload;
      
      if (!gameCode || typeof gameCode !== 'string') {
        if (ack) ack({ success: false, message: 'Game code is required and must be a string', errorCode: 'INVALID_GAME_CODE' });
        return;
      }

      const gameState = gameSessionManager.getGameState(gameCode);
      if (gameState) {
        if (ack) ack(gameState);
      } else {
        if (ack) ack({ error: 'Game not found', errorCode: 'GAME_NOT_FOUND' });
      }
    } catch (err: unknown) {
      const error = err as Error;
      if (ack) ack({ error: error.message, errorCode: 'SERVER_ERROR' });
    }
  });

  socket.on('adjustScore', (payload: unknown, ack?: Function) => {
    try {
      if (!payload || typeof payload !== 'object') {
        if (ack) ack({ success: false, message: 'Invalid payload: must be an object', errorCode: 'INVALID_PAYLOAD' });
        return;
      }

      const { gameCode, playerId, newScore } = payload as AdjustScorePayload;
      
      if (!gameCode || typeof gameCode !== 'string') {
        if (ack) ack({ success: false, message: 'Game code is required and must be a string', errorCode: 'INVALID_GAME_CODE' });
        return;
      }
      
      if (!playerId || typeof playerId !== 'string') {
        if (ack) ack({ success: false, message: 'Player ID is required and must be a string', errorCode: 'INVALID_PLAYER_ID' });
        return;
      }
      
      if (typeof newScore !== 'number' || newScore < 0) {
        if (ack) ack({ success: false, message: 'New score must be a non-negative number', errorCode: 'INVALID_SCORE' });
        return;
      }

      const playerName = socket.data.playerName;

      if (!playerName) {
        if (ack) ack({ success: false, message: 'Player not identified for this socket connection.', errorCode: 'PLAYER_NOT_IDENTIFIED' });
        return;
      }

      // Verify the requesting player is the judge
      const gameDetailsResult = gameSessionManager.getGameDetails(gameCode);
      if (!gameDetailsResult.success) {
        if (ack) ack({ success: false, message: `Game not found: ${gameCode}`, errorCode: 'GAME_NOT_FOUND' });
        return;
      }

      const gameDetails = gameDetailsResult.data;
      if (gameDetails.judgeName !== playerName) {
        if (ack) ack({ success: false, message: 'Only the judge can adjust scores.', errorCode: 'ONLY_JUDGE_CAN_ADJUST_SCORES' });
        return;
      }

      // Adjust the score
      const result = gameSessionManager.adjustPlayerScore(gameCode, playerId, newScore);
      
      if (ack) ack(result);
    } catch (err: unknown) {
      const error = err as Error;
      console.error(`[Server Event Error] Event: adjustScore, Socket: ${socket.id}, Error: ${error.message}, Stack: ${error.stack}`);
      if (ack) ack({ success: false, message: `Server error processing adjustScore: ${error.message}`, errorCode: 'SERVER_ERROR' });
    }
  });

  // Heartbeat endpoint for connection health monitoring
  socket.on('heartbeat', (payload: any, ack?: Function) => {
    try {
      const { gameCode, timestamp } = payload;
      
      if (!gameCode || typeof gameCode !== 'string') {
        if (ack) ack({ success: false, message: 'Invalid game code in heartbeat.' });
        return;
      }
      
      // Verify the game exists
      const gameResult = gameSessionManager.getGameDetails(gameCode);
      if (!gameResult.success) {
        if (ack) ack({ success: false, message: 'Game not found in heartbeat.' });
        return;
      }
      
      // Heartbeat successful
      if (ack) ack({ 
        success: true, 
        serverTimestamp: Date.now(),
        clientTimestamp: timestamp 
      });
    } catch (err: any) {
      console.error(`[Server Event Error] Event: heartbeat, Socket: ${socket.id}, Error: ${err.message}`);
      if (ack) ack({ success: false, message: 'Server error processing heartbeat.' });
    }
  });

  // Batched events handler for performance optimization
  socket.on('batchedEvents', (batchPayload: any) => {
    try {
      logger.debug(`Received batched events from ${socket.id}`, {
        batchId: batchPayload.batchId,
        eventCount: batchPayload.batchSize
      });
      
      batchProcessor.processBatch(socket, batchPayload);
    } catch (err: any) {
      logger.error(`Error processing batched events from ${socket.id}`, err, 'BatchHandler');
      socket.emit('batchProcessed', {
        batchId: batchPayload?.batchId || 'unknown',
        processedCount: 0,
        errorCount: batchPayload?.batchSize || 0,
        totalEvents: batchPayload?.batchSize || 0,
        results: [{ type: 'batch', success: false, message: 'Batch processing failed' }]
      });
    }
  });

  socket.on('disconnect', () => {
    const gameCode = socket.data.gameCode;
    const playerName = socket.data.playerName;
    if (gameCode && playerName) {
      const gameDetailsResult = gameSessionManager.getGameDetails(gameCode);
      if (!gameDetailsResult.success) return;
      const game = gameDetailsResult.data;
      if (game.judgeName === playerName) {
        gameSessionManager.clearJudgeSocketId(gameCode);
      }
    }
  });
} 