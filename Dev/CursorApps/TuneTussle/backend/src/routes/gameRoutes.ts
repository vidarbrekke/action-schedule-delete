import express, { Router } from 'express';
import { GameSessionManager } from '../gameSessionManager';
import { Server as SocketIOServer } from 'socket.io';
import { formatErrorResponse } from '../utils/apiUtils';
import { validateSongLink } from '../utils/gameUtils';
import { fetchAndValidateSongList } from '../services/llmService';

// Export a function that takes io AND gameManager and returns the router
export default function gameRoutes(io: SocketIOServer, gameManager: GameSessionManager) {
  const router = express.Router();

  // Middleware to check that requests have an Origin header
  // This helps prevent CSRF and ensures requests are coming from expected sources
  const ensureRequestHasOrigin = (req: express.Request, res: express.Response, next: express.NextFunction): void => {
    const origin = req.get('origin') || req.get('referer');
    
    // For local development, allow localhost and local network IPs
    const isLocal = process.env.NODE_ENV === 'development' || process.env.NODE_ENV === 'test';
    const isLocalhost = origin?.includes('localhost') || origin?.includes('127.0.0.1');
    const isLocalNetwork = origin?.includes('192.168.') || origin?.includes('10.') || origin?.includes('172.');
    
    if (!origin && !isLocal) {
      res.status(403).json({ success: false, message: 'Missing origin header' });
      return;
    }

    // Allow any origin in development/test, localhost, or local network IPs
    if (!isLocal && !isLocalhost && !isLocalNetwork) {
      // For production, check against a whitelist (implement this based on your deployment)
      const whitelist = ['tunetussle.com']; // Add your production domains
      const isAllowed = whitelist.some(domain => origin?.includes(domain));
      
      if (!isAllowed) {
        res.status(403).json({ success: false, message: 'Origin not allowed' });
        return;
      }
    }
    
    next();
  };

  // --- Utility for handling game manager results --- 
  type GameManagerResult = {
    success: boolean;
    message?: string;
    errorCode?: string;
    [key: string]: any; // To accommodate other properties like gameEnded, code, etc.
  };

  function handleGameResult(res: any, result: GameManagerResult, successStatusCode: number = 200) {
    if (!result.success) {
      let httpStatusCode = 400; // Default bad request
      if (result.message?.includes('Game not found') || result.message?.includes('not found in game')) {
        httpStatusCode = 404;
      } else if (result.message?.includes('Only the judge')) {
        httpStatusCode = 403;
      } else if (result.message?.includes('Player name already taken') || result.message?.includes('not in lobby state')) {
        // Specific 400s that are not permission or not found related
        httpStatusCode = 400;
      }
      // Add more specific error message to status code mappings here if needed
      // Prepare the error response body
      const errorBody: { error: string; errorCode?: string } = {
        error: result.message || 'An unknown error occurred.',
      };
      if (result.errorCode) {
        errorBody.errorCode = result.errorCode as string;
      }
      return res.status(httpStatusCode).json(errorBody);
    }

    // Successful operation
    const { success, message, ...dataFromManager } = result; // success is true here

    const responseBody: { success: boolean; message?: string; [key: string]: any } = {
      success: true,
      ...dataFromManager // Spread other data like gameEnded, code
    };

    if (message) { // If there was a message from the manager, include it
      responseBody.message = message;
    }
    // Examples:
    // joinGame result: { success: true } -> response: { success: true }
    // startGame result: { success: true, message: "Started" } -> response: { success: true, message: "Started" }
    // leaveGame (player) result: { success: true, message: "Left", gameEnded: false } -> response: { success: true, gameEnded: false, message: "Left" }
    // createGame (adapted): { success: true, code: "XYZ" } -> response: { success: true, code: "XYZ" }

    res.status(successStatusCode).json(responseBody);
  }

  function emitLobbyUpdate(io: SocketIOServer, code: string, gameManager: GameSessionManager) {
    const participantNamesResult = gameManager.listParticipants(code);
    if (!participantNamesResult.success) return;
    const participantNames = participantNamesResult.data;
    const gameDetailsResult = gameManager.getGameDetails(code);
    if (!gameDetailsResult.success) return;
    const gameDetails = gameDetailsResult.data;
    const participantsForFrontend = participantNames.map(name => {
      const playerScore = gameDetails.scores.get(name) || 0;
      const playerRole = name === gameDetails.judgeName ? 'judge' : 'participant';
      return { id: name, name, score: playerScore, role: playerRole };
    });
    io.to(code).emit('lobbyUpdate', { participants: participantsForFrontend });
  }

  // Register routes with types as 'any' to bypass TypeScript type checking issues
  router.post('/games', (req: express.Request, res: express.Response, next: express.NextFunction) => {
    ensureRequestHasOrigin(req, res, next);
  }, async (req: express.Request, res: express.Response) => {
    try {
      console.log('[POST /games] Incoming request body:', req.body);
      const { judgeName, prompt, numberOfRounds = 5, llmModel } = req.body;
      console.log('[POST /games] Parsed values:', { judgeName, prompt, numberOfRounds, llmModel });
      
      if (!judgeName || !prompt) {
        console.warn('[POST /games] Missing judgeName or prompt');
        res.status(400).json({
          success: false,
          error: 'judgeName, prompt, and numberOfRounds are required.'
        });
        return;
      }
      
      // Validate numberOfRounds
      const roundCount = parseInt(String(numberOfRounds));
      if (isNaN(roundCount) || roundCount < 1 || roundCount > 10) {
        console.warn('[POST /games] Invalid numberOfRounds:', numberOfRounds);
        res.status(400).json({
          success: false,
          message: 'numberOfRounds must be a number between 1 and 10'
        });
        return;
      }
      
      console.log(`[GameRoutes POST /games] Attempting to create game with judge: ${judgeName}, prompt: ${prompt}, rounds: ${roundCount}`);
      // Create the game synchronously (no songs yet)
      const result = gameManager.createGame(judgeName, {
        prompt,
        numberOfRounds: roundCount,
        llmModel
      });
      console.log('[POST /games] gameManager.createGame result:', result);
      if (!result.success || !result.data || !result.data.code) {
        console.warn('[POST /games] Game creation failed:', result);
        res.status(400).json(result);
        return;
      }
      // Respond immediately with the game code
      res.status(201).json({
        success: true,
        code: result.data.code,
        message: `Game created with code: ${result.data.code}`
      });
      // Start async song generation in the background
      (async () => {
        // Only proceed if result.data.code is a valid string
        if (!result.success || !result.data || typeof result.data.code !== 'string') return;
        try {
          // Notify the judge that song generation is starting
          const judgeSocketId = gameManager.getJudgeSocketId(result.data.code);
          if (judgeSocketId && io.sockets.sockets.get(judgeSocketId)) {
            io.to(judgeSocketId).emit('generatingSongs', { code: result.data.code });
          }
          const llmConfig = await gameManager.getLlmConfigForGame(result.data.code);
          console.log('[POST /games] LLM Config:', { 
            apiKey: llmConfig.apiKey ? '✓ Set' : '✗ Not set', 
            modelId: llmConfig.modelId,
            apiBaseUrl: llmConfig.apiBaseUrl 
          });
          const songs = await fetchAndValidateSongList(
            prompt,
            roundCount,
            llmConfig.apiKey,
            llmModel || llmConfig.modelId || 'anthropic/claude-3-opus',
            llmConfig.apiBaseUrl,
            process.env.YOUTUBE_API_KEY
          );
          // Mark game as ready and store songs
          const gameSongs = songs.map(song => ({ ...song, used: false }));
          if (typeof gameManager.setSongsAndReady === 'function') {
            gameManager.setSongsAndReady(result.data.code, gameSongs);
          }
          // Emit 'gameReady' event to judge
          const judgeSocket = gameManager.getJudgeSocketId(result.data.code);
          if (judgeSocket && io.sockets.sockets.get(judgeSocket)) {
            // Include first song info for artwork preloading
            const gameStateResult = gameManager.getGameState(result.data.code);
            let firstSong: { title: string; artist: string } | undefined;
            if (gameStateResult.success && gameStateResult.data.songList?.[0]) {
              firstSong = {
                title: gameStateResult.data.songList[0].title,
                artist: gameStateResult.data.songList[0].artist
              };
              console.log('[POST /games] Including first song for artwork preloading:', firstSong);
            }
            
            io.to(judgeSocket).emit('gameReady', { 
              code: result.data.code,
              firstSong 
            });
          }
        } catch (err) {
          console.error(`[POST /games] Song generation failed for game ${result.data.code}:`, err);
          if (typeof gameManager.setGameErrored === 'function') {
            gameManager.setGameErrored(result.data.code, err);
          }
          // Emit 'gameError' event to judge
          const judgeSocketId = gameManager.getJudgeSocketId(result.data.code);
          if (judgeSocketId && io.sockets.sockets.get(judgeSocketId)) {
            io.to(judgeSocketId).emit('gameError', { message: 'Failed to generate songs for this game. Please try again.' });
          }
        }
      })();
    } catch (error: any) {
      console.error('[POST /games] Error creating game:', error);
      formatErrorResponse(res, error, 'Error creating game');
    }
  });

  router.post('/games/:code/join', async (req: express.Request, res: express.Response) => {
    const { code } = req.params;
    const { playerName } = req.body;
    if (!playerName) {
        res.status(400).json({ success: false, error: 'playerName required' });
        return;
    }
    const result = await gameManager.joinGame(code, playerName);
    if (result.success) {
      emitLobbyUpdate(io, code, gameManager);
      res.status(200).json({ success: true });
      return;
    }
    if (result.message === 'Player name already taken in this game.') {
      res.status(400).json({ success: false, error: result.message });
      return;
    }
    if (result.message === 'Game is not joinable at this stage.') {
      res.status(400).json({ success: false, error: result.message });
      return;
    }
    if (!result.success && ('errorCode' in result) && (result.errorCode === 'GAME_NOT_FOUND' || result.message === 'Game not found.' || (result.message && result.message.includes('not found')))) {
      res.status(404).json({ success: false, error: 'Game not found.' });
      return;
    }
    res.status(400).json({ success: false, error: result.message || 'Failed to join game.' });
  });

  router.get('/games/:code/participants', (req: express.Request, res: express.Response) => {
    const { code } = req.params;
    const participantsResult = gameManager.listParticipants(code);
    if (!participantsResult.success) {
      res.status(404).json({ error: 'Game not found.' });
      return;
    }
    res.json({ participants: participantsResult.data });
  });

  /**
   * Removes a participant from a game
   */
  router.delete('/games/:code/participants/:name', (req: express.Request, res: express.Response, next: express.NextFunction) => {
    ensureRequestHasOrigin(req, res, next);
  }, async (req: express.Request, res: express.Response) => {
    try {
      const { code, name } = req.params;
      if (!code || !name) {
        res.status(400).json({ success: false, message: 'Missing required parameters: code and name' });
        return;
      }
      const result = await gameManager.leaveGame(code, name);
      if (!result.success) {
        if ('errorCode' in result && result.errorCode === 'GAME_NOT_FOUND' || result.message === 'Game not found.') {
          res.status(404).json({ success: false, error: 'Game not found.' });
        } else if (result.message?.startsWith('Participant')) {
          res.status(404).json({ success: false, error: result.message });
        } else {
          res.status(400).json({ success: false, error: result.message });
        }
        return;
      }
      if (!result.gameEnded) {
        emitLobbyUpdate(io, code, gameManager);
      }
      res.status(200).json(result);
    } catch (error: any) {
      formatErrorResponse(res, error, 'Error removing participant');
    }
  });

  router.post('/games/:code/start', async (req: express.Request, res: express.Response, next: express.NextFunction) => {
    const { code } = req.params;
    const { requestingUser } = req.body;
    
    if (!requestingUser) {
      res.status(400).json({ error: 'requestingUser is required in the request body.' });
      return;
    }
    
    try {
      const result = await gameManager.startGame(code, requestingUser);
      
      if (!result.success) {
        if (!result.success && 'errorCode' in result && result.errorCode === 'UNAUTHORIZED') {
          res.status(403).json({ error: result.message });
          return;
        }
        if (!result.success && 'errorCode' in result && result.errorCode === 'ALREADY_STARTED') {
          res.status(400).json({ error: 'Game is not in lobby state.', errorCode: 'ALREADY_STARTED' });
          return;
        }
        if (!result.success && 'errorCode' in result && (result.errorCode === 'GAME_NOT_FOUND' || result.message === 'Game not found.' || (result.message && result.message.includes('not found')))) {
          res.status(404).json({ error: 'Game not found.' });
          return;
        }
        // Include errorCode if available for proper frontend error handling
        const errorResponse: any = { error: result.message };
        if ('errorCode' in result && result.errorCode) {
          errorResponse.errorCode = result.errorCode;
        }
        res.status(400).json(errorResponse);
        return;
      }
      
      // Start the first round (roundStarted event will be emitted via hook system)
      const roundResult = gameManager.startNextRound(code);
      if (!roundResult.success) {
        console.warn(`[POST /games/${code}/start] Could not start first round:`, roundResult);
      }
      
      res.status(200).json({ success: true, message: 'Game started successfully.' });
    } catch (error) {
      console.error(`[POST /games/${code}/start] Error starting game:`, error);
      formatErrorResponse(res, error, 'Error starting game');
    }
  });

  return router;
}