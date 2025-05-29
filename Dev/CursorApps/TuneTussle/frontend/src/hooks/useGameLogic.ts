import { useEffect, useCallback, useRef, useState, useMemo, useReducer } from 'react';
import { useParams, useLocation, useNavigate } from 'react-router-dom';
import { useGameEffects } from './useGameEffects';
import { artworkPreloadService } from '../services/artworkPreloadService';
import { usePlayerRoundState } from './usePlayerRoundState';
import { useLobbyState } from './useLobbyState';
import { useGameSocketEvents } from './useGameSocketEvents';
import { useUnifiedSocketManager } from './useUnifiedSocketManager';
import { gameReducer, initialGameStateBase } from './gameStateReducer';
import apiService from '../api/apiService';
import { toastService } from '../services/toastService';
import { useSocket } from '../contexts/SocketContext';
import type { PlayerRole, PlayerScore } from '../components/Scoreboard';
import type {
  Answer,
  RoundEndedPayload
} from '../types/game';

// Define expected event payloads (mirror backend)
interface RoundStartedPayload {
  gameCode?: string;
  round: { songTitle: string; artist: string; audioUrl?: string; trackUrl?: string };
  currentRoundNumber: number;
  totalRounds: number;
  correctAnswer?: string;
  isLateJoin?: boolean;
  nextRound?: { title: string; artist: string }; // Enhanced for judges only
}
interface BuzzInPayload {
  player: string;
}
interface ScoreUpdatePayload {
  scores: { [key: string]: number };
}
interface AnswerSubmittedPayload {
  gameCode?: string;
  player: string;
  answer: Answer;
  isCorrect?: boolean;
  correctAnswer?: { title: string; artist: string };
  message?: string;
  canAdvance?: boolean; // Added: Can the judge advance to the next round?
}
interface GameOverPayload {
  finalScores: { [playerName: string]: number };
}

// ADDING type for lobbyUpdate payload from backend
interface LobbyUpdatePayload {
  participants: PlayerScore[];
  // Include other fields if the backend sends more for a lobby update
}

// Placeholder for PlayerContext or similar to get current player's name/ID

interface SocketAcknowledgment {
  success: boolean;
  message?: string;
  errorCode?: string;
  updatedScore?: number;
}

interface GameSettings {
  numberOfRounds?: number;
  prompt?: string;
  [key: string]: unknown;
}

interface GameStateData {
  success: boolean;
  data?: unknown;
}

interface RejoinAck {
  role: PlayerRole;
}

interface JudgeNewGameData {
  judgeName: string;
  newGameCode: string;
  previousParticipants: string[];
  gameSettings: GameSettings;
}

export const useGameLogic = () => {
  // Get params from URL
  const { gameCode: urlGameCode } = useParams<{ gameCode: string }>();
  const location = useLocation();
  const navigate = useNavigate();

  // Get socketIO instance from context
  const { socket, isConnected: socketIsConnectedGlobal } = useSocket();

  // --- LocalStorage persistence helpers ---
  function getPlayerInfo(): { playerName: string | null; gameCode: string | null; role: PlayerRole | null } {
    try {
      const playerName = localStorage.getItem('tt_playerName');
      const gameCode = localStorage.getItem('tt_gameCode');
      const role = localStorage.getItem('tt_role') as PlayerRole | null;
      return { playerName, gameCode, role };
    } catch (error) {
      console.warn('[useGameLogic] Failed to read player info from localStorage:', error);
      return { playerName: null, gameCode: null, role: null };
    }
  }

  function savePlayerInfo(playerName: string, gameCode: string, role: PlayerRole) {
    try {
      localStorage.setItem('tt_playerName', playerName);
      localStorage.setItem('tt_gameCode', gameCode);
      localStorage.setItem('tt_role', role);
    } catch (error) {
      console.warn('[useGameLogic] Failed to save player info to localStorage:', error);
    }
  }

  function clearPlayerInfo() {
    try {
      localStorage.removeItem('tt_playerName');
      localStorage.removeItem('tt_gameCode');
      localStorage.removeItem('tt_role');
    } catch (error) {
      console.warn('[useGameLogic] Failed to clear player info from localStorage:', error);
    }
  }

  // Extract initial values from URL params and localStorage
  let initialPlayerName = null;
  let initialRole: PlayerRole | null = null;
  let initialGameCode = urlGameCode || null;
  const locationState = location.state as { playerName?: string; isJudge?: boolean } | null;
  if (locationState?.playerName) {
    initialPlayerName = locationState.playerName;
    initialRole = locationState.isJudge ? 'judge' : 'participant';
    if (urlGameCode) initialGameCode = urlGameCode;
  } else {
    const { playerName, gameCode, role } = getPlayerInfo();
    if (playerName) initialPlayerName = playerName;
    if (gameCode) initialGameCode = gameCode;
    if (role) initialRole = role;
  }

  // State management
  const [gameState, dispatch] = useReducer(gameReducer, {
    ...initialGameStateBase, // Fix: Remove function call since it's already an object
    gameCode: initialGameCode,
    isSocketConnected: socketIsConnectedGlobal,
    playerName: initialPlayerName,
    role: initialRole,
    // isLoading is true from initialGameStateBase by default
  });
  const [gameStartedOrReady, setGameStartedOrReady] = useState(false);
  const [artworkUrl, setArtworkUrl] = useState<string | null>(null);

  // Refs for tracking and preventing duplicate processing
  const processedEventsRef = useRef<Set<string>>(new Set());
  const lastRoundStartedEventRef = useRef<RoundStartedPayload | null>(null);

  const [lobbyState, setLobbyState] = useLobbyState(gameState, null); // Pass null for initialState

  // --- Player round state (modularized) ---
  const { playerState, setPlayerState } = usePlayerRoundState();

  // Use the shared game effects logic
  useGameEffects(
    {
      gameState: {
        ...gameState,
        role: gameState.role,
        currentQuestion: gameState.currentQuestion,
        isSocketConnected: socketIsConnectedGlobal,
        hasJoinedGame: gameState.hasJoinedGame,
        scores: gameState.scores,
        playerName: gameState.playerName,
        isGameOver: gameState.isGameOver,
        currentRoundNumber: gameState.currentRoundNumber,
        infoMessage: gameState.infoMessage,
        error: gameState.error,
        infoFeedback: gameState.infoFeedback,
      },
      socket,
      dispatch,
      setLobbyState,
      isJudge: gameState.role === 'judge',
    },
    {
      savePlayerInfo,
      clearPlayerInfo,
    }
  );

  // --- Unified Socket Manager for all connection lifecycle management ---
  const { forceStateRefresh, forceRejoin } = useUnifiedSocketManager({
    socket,
    isConnected: gameState.isSocketConnected,
    gameCode: gameState.gameCode,
    playerName: gameState.playerName,
    hasJoinedGame: gameState.hasJoinedGame,
    onStateRefresh: (gameStateData: GameStateData) => {
      if (gameStateData && gameStateData.success) {
        dispatch({ type: 'SET_FULL_GAME_STATE', payload: gameStateData.data });
      }
    },
    onConnectionLost: () => {
      console.log('[useGameLogic] Unified socket manager detected connection loss');
      toastService.error('Connection lost. Attempting to reconnect...', { id: 'connection-lost' });
      dispatch({ type: 'SET_ERROR', payload: { message: 'Connection lost. Attempting to reconnect...', isCritical: false } });
    },
    onConnectionRestored: () => {
      console.log('[useGameLogic] Unified socket manager detected connection restored');
      toastService.success('Connection restored!', { id: 'connection-restored', duration: 2000 });
      dispatch({ type: 'SET_ERROR', payload: { message: null, isCritical: false } });
    },
    onRejoinSuccess: (ack: RejoinAck) => {
      console.log('[useGameLogic] Rejoin successful, role confirmed:', ack.role);
      // Don't dispatch JOIN_SUCCESS here as it's already handled by useGameEffects
      // Just ensure the role is correct if needed
      if (gameState.role !== ack.role) {
        dispatch({ type: 'ROLE_DETERMINED', payload: ack.role });
      }
    },
    onRejoinError: (error: string) => {
      dispatch({
        type: 'SET_ERROR',
        payload: {
          message: `Failed to rejoin game: ${error}`,
          isCritical: true
        }
      });
    },
  });

  // Clear processed events when game code changes (new game)
  useEffect(() => {
    if (gameState.gameCode) {
      processedEventsRef.current.clear();
    }
  }, [gameState.gameCode]);

  // Save player info to localStorage when available
  useEffect(() => {
    if (gameState.playerName && gameState.gameCode && gameState.role) {
      console.log('[useGameLogic] Saving player info to localStorage:', {
        playerName: gameState.playerName,
        gameCode: gameState.gameCode,
        role: gameState.role
      });
      savePlayerInfo(gameState.playerName, gameState.gameCode, gameState.role);
    }
  }, [gameState.playerName, gameState.gameCode, gameState.role]);

  // Define event handlers at the component level
  const handleRoleAssigned = useCallback((data: { role: PlayerRole; participants: PlayerScore[]; gameSettings: GameSettings; judgeName?: string }) => {
    console.log('[useGameLogic] Received roleAssigned event:', data);
    dispatch({ type: 'ROLE_DETERMINED', payload: data.role });
    dispatch({
      type: 'GAME_STATE_INIT',
      payload: {
        participants: data.participants,
        gameSettings: data.gameSettings
      }
    });
    setLobbyState((prev: LobbyStateType) => ({ ...prev, isLoadingRole: false }));
    if (data.gameSettings?.numberOfRounds) {
      setLobbyState((prev: LobbyStateType) => ({ ...prev, expectedSongCount: data.gameSettings.numberOfRounds }));
    }

    // Save player info immediately when role is assigned
    if (gameState.playerName && gameState.gameCode) {
      console.log('[useGameLogic] Saving player info after role assignment:', {
        playerName: gameState.playerName,
        gameCode: gameState.gameCode,
        role: data.role
      });
      savePlayerInfo(gameState.playerName, gameState.gameCode, data.role);
    }
  }, [dispatch, setLobbyState, gameState.playerName, gameState.gameCode]);

  const handleGameError = useCallback((data: { message: string; isCritical?: boolean }) => {
    console.error('[useGameLogic] Received gameError:', data.message);
    dispatch({ type: 'SET_ERROR', payload: { message: data.message, isCritical: data.isCritical } });
  }, [dispatch]);

  const handleLobbyUpdate = useCallback((data: LobbyUpdatePayload) => {
    console.log('[useGameLogic] Received lobbyUpdate:', data);
    dispatch({ type: 'PARTICIPANTS_UPDATED', payload: data.participants });
  }, [dispatch]);

  const handleRoundStarted = useCallback((data: RoundStartedPayload) => {
    console.log('[useGameLogic] Processing roundStarted event:', data);

    // Store the event data for artwork preloading
    lastRoundStartedEventRef.current = data;

    // Create unique event ID for deduplication
    const eventId = `roundStarted-${data.gameCode}-${data.currentRoundNumber}-${data.isLateJoin ? 'late' : 'normal'}`;

    // Skip if we've already processed this exact event
    if (processedEventsRef.current.has(eventId)) {
      console.log('[useGameLogic] Skipping duplicate roundStarted event:', eventId);
      return;
    }

    // Skip processing if this is a late-join duplicate event
    if (data.isLateJoin && gameState.currentRoundNumber === data.currentRoundNumber) {
      console.log('[useGameLogic] Skipping duplicate late-join roundStarted event');
      return;
    }

    // Mark event as processed
    processedEventsRef.current.add(eventId);

    dispatch({ type: 'SET_LOADING', payload: false });
    setGameStartedOrReady(true);
    dispatch({ type: 'ROUND_STARTED', payload: data });
    dispatch({ type: 'SET_ERROR', payload: { message: null, isCritical: false } });
    console.log('[useGameLogic] Resetting player state to IDLE for new round');
    setPlayerState({ state: 'IDLE' });
  }, [dispatch, gameState.currentRoundNumber, setGameStartedOrReady, setPlayerState]);

  const handleGameReady = useCallback(async (data: { code: string; firstSong?: { title: string; artist: string } }) => {
    console.log('[useGameLogic] Received gameReady event:', data);
    setLobbyState((prev: LobbyStateType) => ({
      ...prev,
      isGameReady: true,
      isGeneratingSongs: false,
      isStartingGame: false,
      startGameError: null,
    }));
    dispatch({ type: 'SET_LOADING', payload: false });
    setGameStartedOrReady(true);

    // NEW: Preload artwork for the first round as soon as songs are ready
    if (gameState.role === 'judge' && data.firstSong) {
      try {
        console.log('[useGameLogic] Preloading artwork for first round:', data.firstSong);

        // Start preloading the first song's artwork in the background
        // This ensures the artwork shows immediately when the round starts
        const preloadPromise = artworkPreloadService.preloadArtwork(data.firstSong.title, data.firstSong.artist);

        // Also trigger audio preloading for the first song to improve initial load time
        // Audio will be preloaded when the round actually starts, but we can prepare the system
        console.log('[useGameLogic] Preparing for first-round audio preloading');

        await preloadPromise;
        console.log('[useGameLogic] First-round artwork preloaded successfully');
      } catch (error) {
        console.warn('[useGameLogic] Error preloading first-round content:', error);
      }
    }
  }, [gameState.role, setLobbyState, dispatch, setGameStartedOrReady]);

  // Add a handler for generatingSongs event
  const handleGeneratingSongs = useCallback((data: { code: string }) => {
    console.log('[useGameLogic] Received generatingSongs event:', data);
    setLobbyState((prev: LobbyStateType) => ({
      ...prev,
      isGeneratingSongs: true,
      isGameReady: false,
      startGameError: null
    }));
  }, [setLobbyState]);

  // Define additional event handlers for the consolidated socket events
  const handleBuzzIn = useCallback((data: BuzzInPayload) => {
    dispatch({ type: 'BUZZ_IN_SUCCESS', payload: data });
    dispatch({ type: 'SET_ERROR', payload: { message: null, isCritical: false } });
  }, [dispatch]);

  const handleScoreUpdate = useCallback((data: ScoreUpdatePayload) => {
    const normalizedScores = scoresObjectToArray(data.scores);
    dispatch({ type: 'PARTICIPANTS_UPDATED', payload: normalizedScores });
  }, [dispatch]);

  const handleAnswerSubmitted = useCallback((data: AnswerSubmittedPayload) => {
    const { player, isCorrect, message, canAdvance } = data;
    dispatch({
      type: 'ANSWER_SUBMITTED_ACK',
      payload: data
    });
    if (canAdvance !== undefined) {
      dispatch({ type: 'SET_CAN_ADVANCE', payload: canAdvance });
    }

    if (player === gameState.playerName) {
      setPlayerState({ state: 'ANSWERED' });
      if (!isCorrect) {
        dispatch({
          type: 'SET_INFO_MESSAGE',
          payload: {
            message: message || "Sorry, that's incorrect.",
          }
        });
      }
    }
  }, [dispatch, gameState.playerName, setPlayerState]);

  const handleGameOver = useCallback((data: GameOverPayload) => {
    const normalizedScores = scoresObjectToArray(data.finalScores);
    dispatch({ type: 'PARTICIPANTS_UPDATED', payload: normalizedScores });
    dispatch({ type: 'GAME_OVER_EVENT', payload: data });
  }, [dispatch]);

  const handleAnswerTimerExpired = useCallback((data: { gameCode: string; playerName: string }) => {
    if (data.playerName === gameState.playerName) {
      setPlayerState({ state: 'TIMED_OUT' });
      dispatch({ type: 'SET_ERROR', payload: { message: "Time's up! You didn't answer in time.", isCritical: false } });
    }
  }, [gameState.playerName, setPlayerState, dispatch]);

  const handleAllPlayersAttemptedNoCorrect = useCallback((data: { message: string; canAdvance: boolean; isGameOver: boolean; correctAnswer: { title: string; artist: string } }) => {
    dispatch({ type: 'SET_INFO_MESSAGE', payload: { message: data.message } });
    dispatch({ type: 'SET_CAN_ADVANCE', payload: data.canAdvance });

    const correctAnswerString = `${data.correctAnswer.title} by ${data.correctAnswer.artist}`;
    dispatch({
      type: 'SET_FULL_GAME_STATE',
      payload: {
        correctAnswer: correctAnswerString,
      }
    });

    if (data.isGameOver) {
      const finalScores = gameState.scores.reduce((acc, scoreObj) => {
        acc[scoreObj.id] = scoreObj.score;
        return acc;
      }, {} as { [key: string]: number });
      dispatch({ type: 'GAME_OVER_EVENT', payload: { finalScores } });
    }
  }, [dispatch, gameState.scores]);

  const handleBuzzerStatusUpdate = useCallback((data: { isBuzzActive: boolean; activePlayerName: string | null; isAnswerPhase: boolean }) => {
    dispatch({ type: 'BUZZER_STATUS_UPDATE', payload: data });
  }, [dispatch]);

  const handleRoundComplete = useCallback((data: RoundEndedPayload) => {
    dispatch({ type: 'ROUND_COMPLETE', payload: data });
  }, [dispatch]);

  // Consolidated socket event handling - all events in one place
  useGameSocketEvents(socket, {
    roleAssigned: handleRoleAssigned,
    gameError: handleGameError,
    lobbyUpdate: handleLobbyUpdate,
    roundStarted: handleRoundStarted,
    gameReady: handleGameReady,
    generatingSongs: handleGeneratingSongs,
    buzzIn: handleBuzzIn,
    scoreUpdate: handleScoreUpdate,
    answerSubmitted: handleAnswerSubmitted,
    gameOver: handleGameOver,
    answerTimerExpired: handleAnswerTimerExpired,
    allPlayersAttemptedNoCorrect: handleAllPlayersAttemptedNoCorrect,
    buzzerStatusUpdate: handleBuzzerStatusUpdate,
    roundComplete: handleRoundComplete,
  });



  const setPlayerNameAndInitiateJoin = useCallback((name: string) => {
    if (!name.trim()) {
      dispatch({ type: 'SET_ERROR', payload: { message: 'Player name cannot be empty.', isCritical: true } });
      return;
    }
    dispatch({ type: 'PLAYER_NAME_SET', payload: name });
  }, [dispatch]);

  const handlePlayerBuzzIn = useCallback(() => {
    if (playerState.state !== 'IDLE') return;
    setPlayerState({ state: 'BUZZED_IN' });
    socket.emit('buzzIn', { gameCode: gameState.gameCode, playerName: gameState.playerName });
  }, [gameState.gameCode, gameState.playerName, playerState.state, socket, setPlayerState]); // Added setPlayerState

  const handleSubmitAnswer = useCallback((answer: Answer) => {
    if (playerState.state !== 'BUZZED_IN') {
      dispatch({ type: 'SET_ERROR', payload: { message: "You can't submit an answer right now.", isCritical: false } });
      return;
    }
    setPlayerState({ state: 'ANSWERING', answer });
    socket.emit('submitAnswer', {
      gameCode: gameState.gameCode,
      playerName: gameState.playerName,
      answer
    }, (ack: SocketAcknowledgment) => {
      if (!ack || !ack.success) {
        if (ack?.errorCode === 'TIME_EXPIRED') {
          setPlayerState({ state: 'TIMED_OUT' });
          dispatch({ type: 'SET_ERROR', payload: { message: "Time's up! You didn't answer in time.", isCritical: false } });
        } else {
          dispatch({
            type: 'SET_ERROR',
            payload: {
              message: `Failed to submit answer: ${ack?.message || 'Unknown error'}`,
              isCritical: false
            }
          });
          setPlayerState({ state: 'IDLE' });
        }
      }
    });
  }, [gameState.gameCode, gameState.playerName, playerState.state, socket, dispatch, setPlayerState]);

  // Judge-specific functions
  const handleNextRoundCallback = useCallback(() => {
    if (!socket || !gameState.isSocketConnected || !gameState.gameCode || gameState.role !== 'judge') {
      console.log('[useGameLogic] Cannot start next round: conditions not met', {
        socketExists: !!socket,
        isConnected: gameState.isSocketConnected,
        gameCode: gameState.gameCode,
        role: gameState.role
      });
      return;
    }

    console.log(`[useGameLogic] Judge requesting next round for game: ${gameState.gameCode}`);
    socket.emit('nextRound', { gameCode: gameState.gameCode });
    dispatch({ type: 'NEXT_ROUND_REQUEST' }); // Reducer does nothing with this, mostly for logging/tracing
  }, [socket, gameState.isSocketConnected, gameState.gameCode, gameState.role, dispatch]);

  const handleEndRound = useCallback(() => {
    if (!socket || !gameState.isSocketConnected || !gameState.gameCode || gameState.role !== 'judge') {
      console.log('[useGameLogic] Cannot end round: conditions not met', {
        socketExists: !!socket,
        isConnected: gameState.isSocketConnected,
        gameCode: gameState.gameCode,
        role: gameState.role
      });
      return;
    }

    console.log(`[useGameLogic] Judge ending current round for game: ${gameState.gameCode}`);
    socket.emit('endRound', { gameCode: gameState.gameCode }, (ack: SocketAcknowledgment) => {
      if (ack && ack.success) {
        console.log(`[useGameLogic] Round ended successfully: ${ack.message}`);
      } else {
        console.error(`[useGameLogic] Failed to end round: ${ack?.message || 'Unknown error'}`);
        dispatch({
          type: 'SET_ERROR',
          payload: {
            message: `Failed to end round: ${ack?.message || 'Unknown error'}`,
            isCritical: false
          }
        });
      }
    });
  }, [socket, gameState.isSocketConnected, gameState.gameCode, gameState.role, dispatch]);

  const handleAdjustScore = useCallback((playerId: string, newScore: number) => {
    if (!socket || !gameState.isSocketConnected || !gameState.gameCode || gameState.role !== 'judge') {
      console.warn('[useGameLogic] Adjust score conditions not met.');
      return;
    }
    console.log(`[useGameLogic] Judge adjusting score for player ${playerId} to ${newScore} in game: ${gameState.gameCode}`);

    // Find the player name for better feedback
    const player = gameState.scores.find(p => p.id === playerId);
    const playerName = player?.name || playerId;

    // Emit event and handle acknowledgement from server
    socket.emit(
      'adjustScore',
      { gameCode: gameState.gameCode, playerId, newScore },
      (ack: { success: boolean; message?: string; updatedScore?: number }) => {
        console.log('[useGameLogic] Adjust score acknowledgment received:', ack);
        if (ack && ack.success) {
          console.log(`[useGameLogic] Score adjustment for ${playerId} to ${ack.updatedScore !== undefined ? ack.updatedScore : newScore} acknowledged by server.`);

          const finalScore = ack.updatedScore !== undefined ? ack.updatedScore : newScore;

          // Use toast service to show success message
          toastService.success(`${playerName}'s score updated to ${finalScore}`, {
            duration: 3000,
            id: `score-adjust-${playerId}-${finalScore}`
          });

          if (gameState.error && gameState.error.startsWith('Score adjustment failed:')) {
            dispatch({ type: 'SET_ERROR', payload: { message: null, isCritical: false } });
          }
        } else {
          console.error(`[useGameLogic] Score adjustment failed or was not acknowledged: ${ack?.message || 'No details provided'}`);
          const errorMessage = `Score adjustment failed: ${ack?.message || 'Server error'}`;
          dispatch({ type: 'SET_ERROR', payload: { message: errorMessage, isCritical: false } });
        }
      }
    );
    dispatch({ type: 'ADJUST_SCORE_REQUEST', payload: { playerId, newScore } });
  }, [socket, gameState.isSocketConnected, gameState.gameCode, gameState.role, gameState.scores, dispatch, gameState.error]);

  // Update the startGame function to handle state transitions better
  const startGame = useCallback(async () => {
    if (!socket || !gameState.isSocketConnected || !gameState.gameCode || !gameState.playerName || gameState.role !== 'judge') {
      setLobbyState((prev: LobbyStateType) => ({
        ...prev,
        startGameError: 'Only the judge can start the game.'
      }));
      return;
    }

    console.log('[useGameLogic:startGame] Current lobby state:', lobbyState);

    // Prevent multiple concurrent start game calls
    if (lobbyState.isStartingGame) {
      console.log('[useGameLogic:startGame] Already starting game, ignoring duplicate call');
      return;
    }

    if (!lobbyState.isGameReady) {
      console.log('[useGameLogic:startGame] Preventing start - game is not ready yet');
      setLobbyState((prev: LobbyStateType) => ({
        ...prev,
        startGameError: 'Game is not ready yet. Please wait for songs to be generated.'
      }));
      return;
    }

    setLobbyState((prev: LobbyStateType) => ({
      ...prev,
      isStartingGame: true,
      startGameError: null
    }));

    setGameStartedOrReady(false);
    dispatch({ type: 'SET_LOADING', payload: true });
    dispatch({ type: 'SET_ERROR', payload: { message: null, isCritical: false } });

    try {
      const payload = {
        requestingUser: gameState.playerName
      };
      console.log('[useGameLogic] Sending startGame payload:', payload, 'for gameCode:', gameState.gameCode);
      await apiService.startGame(gameState.gameCode, payload);
      console.log(`[useGameLogic] Successfully started game ${gameState.gameCode}`);

      const timeoutId = setTimeout(() => {
        if (!gameStartedOrReady) {
          console.log('[useGameLogic] Game start timeout - resetting state');
          setLobbyState((prev: LobbyStateType) => ({
            ...prev,
            isStartingGame: false,
            startGameError: 'Game failed to start in time. Please try again.'
          }));
          dispatch({ type: 'SET_LOADING', payload: false });
        }
      }, 30000);
      return () => clearTimeout(timeoutId);
    } catch (err: unknown) {
      console.error('[useGameLogic] Start game error:', err);

      // Type guard for axios-like error
      const isAxiosError = (error: unknown): error is { response?: { data?: { errorCode?: string; error?: string } } } => {
        return typeof error === 'object' && error !== null && 'response' in error;
      };

      if (isAxiosError(err) && err.response) {
        console.error('[useGameLogic] Backend error response:', err.response.data);
      }

      let displayMessage = 'Failed to start the game. Please try again.';
      const errorCode = isAxiosError(err) ? err.response?.data?.errorCode : undefined;
      const backendMessage = isAxiosError(err) ? err.response?.data?.error : undefined;

      if (errorCode) {
        switch (errorCode) {
          case 'LLM_CONFIG_ERROR':
            displayMessage = 'There is a configuration problem with the music suggestion service. Please contact the admin.';
            break;
          case 'LLM_ERROR':
            displayMessage = 'Could not fetch songs from the music suggestion service. The service might be temporarily unavailable or the prompt is too restrictive. Please try a different prompt or try again later.';
            break;
          case 'NO_SONGS_GENERATED':
            displayMessage = 'No songs could be generated for your prompt. Please try a different, perhaps broader, prompt.';
            break;
          case 'ALREADY_STARTED':
            displayMessage = 'The game has already been started. Please check the game screen to continue playing.';
            break;
          default:
            if (backendMessage) {
              displayMessage = backendMessage;
            }
            break;
        }
      } else if (backendMessage) {
        displayMessage = backendMessage;
      }

      setLobbyState((prev: LobbyStateType) => ({
        ...prev,
        startGameError: displayMessage
      }));
      dispatch({ type: 'SET_LOADING', payload: false });
    } finally {
      setLobbyState((prev: LobbyStateType) => ({
        ...prev,
        isStartingGame: false
      }));
    }
  }, [
    socket,
    gameState.isSocketConnected,
    gameState.gameCode,
    gameState.playerName,
    gameState.role,
    lobbyState, // include lobbyState as a whole due to access of isGameReady
    gameStartedOrReady,
    dispatch,
    setLobbyState
  ]);

  // In useEffect, listen for gameState event
  useEffect(() => {
    if (!socket) return;
    const handleGameState = (data: GameStateData) => {
      console.log('[useGameLogic] Received gameState event:', data);
      console.log('[useGameLogic] Current hasJoinedGame before gameState processing:', gameState.hasJoinedGame);

      const normalizedData = { ...data };
      if (data.scores && typeof data.scores === 'object' && !Array.isArray(data.scores)) {
        normalizedData.scores = scoresObjectToArray(data.scores);
      }

      // NOTE: Score update toasts are now handled in useGameEffects.ts via infoMessage
      // to prevent duplicate toasts. The backend includes score information in the answer feedback message.

      // Log what audio URL we're getting from the backend
      if (normalizedData.currentRoundDetails?.audioUrl) {
        console.log('[useGameLogic] gameState event contains audioUrl:', normalizedData.currentRoundDetails.audioUrl);
      }

      dispatch({ type: 'SET_FULL_GAME_STATE', payload: normalizedData });
    };

    // Handle judge creating a new game - auto-redirect previous participants
    const handleJudgeNewGame = (data: JudgeNewGameData) => {
      console.log('[useGameLogic] Received judgeNewGame event:', data);

      // Check if current user was a participant in the judge's previous game
      const currentPlayerName = gameState.playerName;
      if (currentPlayerName && data.previousParticipants.includes(currentPlayerName)) {
        console.log(`[useGameLogic] Auto-redirecting ${currentPlayerName} to new game ${data.newGameCode} created by judge ${data.judgeName}`);

        try {
          // Save the new game info but keep the existing player name and set as participant
          savePlayerInfo(currentPlayerName, data.newGameCode, 'participant');

          // Show a toast notification about the auto-redirect
          toastService.success(
            `${data.judgeName} started a new game! Joining automatically...`,
            { duration: 3000 }
          );

          // Use React Router navigation instead of window.location.href
          console.log(`[useGameLogic] Navigating to new game: /game/${data.newGameCode}`);
          navigate(`/game/${data.newGameCode}`, { replace: true });
        } catch (error) {
          console.error('[useGameLogic] Error during auto-redirect:', error);
          toastService.error('Failed to join the new game automatically. Please try manually.');

          // Fallback to manual URL change if React Router fails
          try {
            window.location.href = `/game/${data.newGameCode}`;
          } catch (fallbackError) {
            console.error('[useGameLogic] Fallback navigation also failed:', fallbackError);
          }
        }
      } else {
        console.log(`[useGameLogic] Not auto-redirecting - current player "${currentPlayerName}" not in previous participants:`, data.previousParticipants);
      }
    };

    socket.on('gameState', handleGameState);
    socket.on('judgeNewGame', handleJudgeNewGame);

    return () => {
      socket.off('gameState', handleGameState);
      socket.off('judgeNewGame', handleJudgeNewGame);
    };
  }, [socket, dispatch, gameState.scores, gameState.playerName, gameState.hasJoinedGame, navigate]);



  const isAnswerInputDisabled = useMemo(() => {
    if (gameState.activePlayerName !== gameState.playerName) {
      return true;
    }
    if (gameState.isRoundComplete) {
      return true;
    }
    if (!gameState.activePlayerName) {
      return true;
    }
    return false;
  }, [gameState.activePlayerName, gameState.playerName, gameState.isRoundComplete]);

  // Enhanced artwork preloading with next round support
  useEffect(() => {
    const preloadArtworkForCurrentAndNext = async () => {
      // Priority 1: Use artwork from round results if available and matches current song
      if (gameState.roundResults?.artworkUrl) {
        const roundSong = `${gameState.roundResults.correctAnswer.title} by ${gameState.roundResults.correctAnswer.artist}`;
        const currentSong = gameState.currentQuestion;

        if (currentSong && roundSong.includes(currentSong.split(' by ')[0])) {
          console.log('[Artwork Preload] Using cached artwork from round results');
          setArtworkUrl(gameState.roundResults.artworkUrl);
          return;
        }
      }

      // Priority 2: Preload artwork for current and next round
      const formattedQuestion = gameState.currentQuestion;
      const artist = gameState.category;

      if (gameState.currentSongAudioUrl && formattedQuestion && artist) {
        // Extract song title from formatted question (remove ' by Artist' suffix)
        const songTitle = formattedQuestion.replace(` by ${artist}`, '');

        // Parse current song info
        const currentSong = { title: songTitle, artist };

        // Get next song info from lastRoundStartedEvent (judges only)
        let nextSong: { title: string; artist: string } | undefined;
        if (gameState.role === 'judge' && lastRoundStartedEventRef.current?.nextRound) {
          nextSong = lastRoundStartedEventRef.current.nextRound;
          console.log('[Artwork Preload] Next round info from backend:', nextSong);
        }

        try {
          const result = await artworkPreloadService.preloadForRound(currentSong, nextSong);
          console.log('[Artwork Preload] Preload result:', result);

          if (result.currentArtwork) {
            setArtworkUrl(result.currentArtwork);
          } else {
            // Fallback to sync cache check
            const cachedArtwork = artworkPreloadService.getArtworkSync(currentSong.title, currentSong.artist);
            setArtworkUrl(cachedArtwork);
          }
        } catch (error) {
          console.warn('[Artwork Preload] Error preloading artwork:', error);
          setArtworkUrl(null);
        }
      }
    };

    preloadArtworkForCurrentAndNext();
  }, [gameState.currentSongAudioUrl, gameState.currentQuestion, gameState.currentRoundNumber, gameState.role, gameState.category, gameState.roundResults, navigate]);

  // ==== Public API of the hook ====
  return {
    ...gameState,
    lobbyState,
    currentRound: gameState.currentRoundNumber,
    isBuzzButtonDisabled:
      !gameState.isBuzzActive ||
      gameState.isAnswerPhase ||
      !!gameState.activePlayerName ||
      !gameState.isSocketConnected ||
      gameState.isLoading ||
      !gameState.hasJoinedGame ||
      gameState.isRoundComplete ||
      playerState.state === 'ANSWERED' ||
      playerState.state === 'TIMED_OUT',
    isAnswerInputDisabled,
    isJudge: gameState.role === 'judge',
    handleBuzzIn: handlePlayerBuzzIn,
    handleSubmitAnswer,
    handleNextRound: handleNextRoundCallback,
    handleEndRound,
    handleAdjustScore,
    setPlayerNameAndInitiateJoin,
    startGame,
    setError: (message: string | null) => dispatch({ type: 'SET_ERROR', payload: { message, isCritical: true } }),
    setRoundComplete: (isComplete: boolean) => dispatch({ type: 'SET_ROUND_COMPLETE', payload: isComplete }),
    prompt: gameState.prompt || '',
    infoMessage: gameState.infoMessage || '',
    currentSongAudioUrl: gameState.currentSongAudioUrl,
    correctAnswer: gameState.correctAnswer || '',
    totalRounds: gameState.totalRounds || 1,
    artworkUrl,
    forceStateRefresh, // Expose the force sync function for manual use if needed
    forceRejoin, // Expose the force rejoin function for manual use if needed
  };
};
