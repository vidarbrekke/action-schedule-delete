/**
 * Game Effects Hook - Manages side effects for game state changes
 *
 * Centralizes all useEffect logic from useGameLogic to improve maintainability
 * and reduce cognitive load in the main game logic hook.
 */

import { useEffect, useRef } from 'react';
import { useLocation } from 'react-router-dom';
import { gameAudioManager } from '../services/gameAudioManager';
import { useOptimizedToast } from '../services/toastService';
import type { PlayerScore } from '../components/Scoreboard';
import type { PlayerRole } from '../components/Scoreboard';

interface GameEffectsConfig {
  gameState: {
    playerName: string | null;
    gameCode: string | null;
    role: PlayerRole | null;
    currentQuestion: string | null;
    isSocketConnected: boolean;
    hasJoinedGame: boolean;
    scores: PlayerScore[];
    isGameOver: boolean;
    currentRoundNumber: number;
    infoMessage: string | null;
    error: string | null;
    infoFeedback?: { message: string; points: number; status: string } | null;
  };
  socket: {
    emit: (event: string, data?: unknown, callback?: (response: JoinGameResponse) => void) => void;
  };
  dispatch: (action: { type: string; payload?: unknown }) => void;
  setLobbyState: (updater: (prev: LobbyState) => LobbyState) => void;
  isJudge: boolean;
}

interface JoinGameResponse {
  success: boolean;
  role?: PlayerRole;
  message?: string;
  errorCode?: string;
}

interface LobbyState {
  isLoadingRole: boolean;
  [key: string]: unknown;
}

interface GameEffectsActions {
  savePlayerInfo: (playerName: string, gameCode: string, role: PlayerRole) => void;
  clearPlayerInfo: () => void;
}

// Enhanced message classification utility - now detects score information to prevent truncation
const classifyGameMessage = (message: string): {
  type: 'playerFeedback' | 'gameStatus' | 'system';
  feedbackType?: 'success' | 'warning' | 'error';
  statusType?: 'round-start' | 'round-end' | 'game-over' | 'waiting';
  hasScoreInfo?: boolean;
} => {
  const lowerMessage = message.toLowerCase();

  // Check for score information first - this is key to preventing truncation
  const hasScoreInfo = /(\d+\s*points?|score|earned|total)/i.test(message);

  // Player-specific feedback messages
  if (lowerMessage.includes('correct!') && !lowerMessage.includes('partially')) {
    return { type: 'playerFeedback', feedbackType: 'success', hasScoreInfo };
  }
  if (lowerMessage.includes('partially correct')) {
    return { type: 'playerFeedback', feedbackType: 'warning', hasScoreInfo };
  }
  if (lowerMessage.includes('incorrect') || lowerMessage.includes('wrong')) {
    return { type: 'playerFeedback', feedbackType: 'error', hasScoreInfo };
  }

  // Game status messages
  if (lowerMessage.includes('round') && lowerMessage.includes('start')) {
    return { type: 'gameStatus', statusType: 'round-start', hasScoreInfo };
  }
  if (lowerMessage.includes('round') && (lowerMessage.includes('end') || lowerMessage.includes('complete'))) {
    return { type: 'gameStatus', statusType: 'round-end', hasScoreInfo };
  }
  if (lowerMessage.includes('game over') || lowerMessage.includes('final scores')) {
    return { type: 'gameStatus', statusType: 'game-over', hasScoreInfo };
  }
  if (lowerMessage.includes('waiting') || lowerMessage.includes('lobby')) {
    return { type: 'gameStatus', statusType: 'waiting', hasScoreInfo };
  }

  return { type: 'system', hasScoreInfo };
};

export function useGameEffects(config: GameEffectsConfig, actions: GameEffectsActions) {
  const location = useLocation();
  const { gameState, socket, dispatch, setLobbyState, isJudge } = config;
  const { savePlayerInfo, clearPlayerInfo } = actions;

  // Use optimized toast hook
  const toast = useOptimizedToast();

  // Effect: Set player name from location state (e.g., for judge after creating game)
  useEffect(() => {
    const locationState = location.state as { playerName?: string; isJudge?: boolean } | null;
    if (locationState?.playerName && !gameState.playerName) {
      console.log('[useGameEffects] Setting playerName from location.state:', locationState.playerName);
      dispatch({ type: 'PLAYER_NAME_SET', payload: locationState.playerName });
      if (locationState.isJudge) {
        dispatch({ type: 'ROLE_DETERMINED', payload: 'judge' });
      }
    }
  }, [location, gameState.playerName, dispatch]);

  // Effect: Persist player info to localStorage on join
  useEffect(() => {
    if (gameState.playerName && gameState.gameCode && gameState.role) {
      savePlayerInfo(gameState.playerName, gameState.gameCode, gameState.role);
    }
  }, [gameState.playerName, gameState.gameCode, gameState.role, savePlayerInfo]);

  // Effect: Clear player info on game over
  useEffect(() => {
    if (gameState.currentQuestion === 'Game Over! Thank you for playing.') {
      clearPlayerInfo();
    }
  }, [gameState.currentQuestion, clearPlayerInfo]);

  // Effect: Sync global socket connection status to local state
  useEffect(() => {
    dispatch({ type: 'SET_SOCKET_CONNECTED', payload: gameState.isSocketConnected });
  }, [gameState.isSocketConnected, dispatch]);

  // Effect: Join game room when conditions are met
  useEffect(() => {
    if (socket && gameState.isSocketConnected && gameState.gameCode && gameState.playerName && !gameState.hasJoinedGame) {
      socket.emit('joinGameRoom', { gameCode: gameState.gameCode, playerName: gameState.playerName }, (ack: JoinGameResponse) => {
        if (ack && ack.success && ack.role) {
          dispatch({ type: 'JOIN_SUCCESS', payload: { role: ack.role } });
        } else {
          const errorMessage = ack?.message || 'Unknown error during join.';

          // Check if this is a "Game not found" error
          if (ack?.errorCode === 'GAME_NOT_FOUND' || errorMessage.toLowerCase().includes('game not found')) {
            // Clear player info for games that don't exist
            clearPlayerInfo();

            // Show error toast and redirect to home page
            toast.error(`Game not found. The game may have expired or ended.`, {
              duration: 5000,
            });

            // Navigate back to home page after a brief delay
            setTimeout(() => {
              window.location.href = '/';
            }, 2000);

            return;
          }

          dispatch({ type: 'SET_ERROR', payload: { message: `Failed to join game room: ${errorMessage}`, isCritical: true } });
          setLobbyState((prev: LobbyState) => ({ ...prev, isLoadingRole: false }));
        }
      });
    }
  }, [socket, gameState.isSocketConnected, gameState.gameCode, gameState.playerName, gameState.hasJoinedGame, dispatch, setLobbyState, clearPlayerInfo, toast]);

  // Effect: Handle audio pre-loading on game start
  useEffect(() => {
    if (gameState.currentRoundNumber > 0) {
      gameAudioManager.onGameStart(gameState.currentRoundNumber);
    }
  }, [gameState.currentRoundNumber]);

  // Effect: Handle audio pre-loading on score updates
  useEffect(() => {
    if (gameState.scores.length > 0 && !gameState.isGameOver) {
      // Filter and map scores to match gameAudioManager interface
      const scoresForAudio = gameState.scores
        .filter(player => player.role !== undefined) // Only include players with defined roles
        .map(player => ({
          score: player.score,
          role: player.role as string // Safe cast since we filtered for defined roles
        }));

      if (scoresForAudio.length > 0) {
        gameAudioManager.onScoreUpdate(scoresForAudio);
      }
    }
  }, [gameState.scores, gameState.isGameOver]);

  // Effect: Play winning sound for winners when game ends
  useEffect(() => {
    if (gameState.isGameOver && gameState.playerName && gameState.scores.length > 0) {
      // Filter and map scores to match gameAudioManager interface
      const scoresForAudio = gameState.scores
        .filter(player => player.role !== undefined) // Only include players with defined roles
        .map(player => ({
          id: player.id,
          name: player.name,
          score: player.score,
          role: player.role as string // Safe cast since we filtered for defined roles
        }));

      if (scoresForAudio.length > 0) {
        gameAudioManager.playWinningSound(gameState.playerName, scoresForAudio);
      }
    }
  }, [gameState.isGameOver, gameState.playerName, gameState.scores]);

  // Effect: Show info messages as optimized toasts with enhanced classification
  const lastInfoMessageRef = useRef<string | null>(null);
  useEffect(() => {
    if (!gameState.infoMessage) return;
    if (lastInfoMessageRef.current === gameState.infoMessage) return; // Prevent duplicate toasts
    lastInfoMessageRef.current = gameState.infoMessage;
    const classification = classifyGameMessage(gameState.infoMessage);

    // Enhanced: Show player-specific feedback as toasts for immediate acknowledgment
    // Only suppress if it's also displayed in the QuestionDisplay component
    if (classification.type === 'playerFeedback') {
      // Show as toast for immediate feedback, but check if it's about the current player
      const currentPlayer = gameState.playerName;
      const messageIncludesCurrentPlayer = currentPlayer && gameState.infoMessage.toLowerCase().includes(currentPlayer.toLowerCase());

      // Show toast for player feedback, especially for the current player's submissions
      if (messageIncludesCurrentPlayer || classification.feedbackType === 'error') {
        const toastType = classification.feedbackType || 'success';
        toast.quickFeedback(gameState.infoMessage, toastType, { hasScoreInfo: classification.hasScoreInfo });
      }
      return;
    }

    // Route to appropriate toast method based on classification
    switch (classification.type) {
      case 'gameStatus':
        if (classification.statusType) {
          toast.gameStatus(gameState.infoMessage, classification.statusType, { hasScoreInfo: classification.hasScoreInfo });
        }
        break;
      case 'system':
      default:
        toast.success(gameState.infoMessage, {
          id: 'game-info',
          hasScoreInfo: classification.hasScoreInfo
        });
        break;
    }
  }, [gameState.infoMessage, gameState.currentRoundNumber, gameState.infoFeedback, gameState.role, isJudge, toast, gameState.playerName]);

  // Effect: Show error messages as toasts
  useEffect(() => {
    if (gameState.error) {
      toast.error(gameState.error, { id: 'game-error' });
    }
  }, [gameState.error, toast]);

  // Effect: Reset audio manager on new games
  useEffect(() => {
    if (gameState.gameCode) {
      // Reset audio manager when starting a new game
      gameAudioManager.reset();
    }
  }, [gameState.gameCode]);
}
