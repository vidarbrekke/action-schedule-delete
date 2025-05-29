import { useMemo } from 'react';
import type { PlayerScore } from '../components/Scoreboard';
import type { PlayerRole } from '../components/Scoreboard';

interface GameRound {
  number: number;
  song?: {
    title: string;
    artist: string;
    audioUrl?: string;
  };
  isComplete?: boolean;
  correctAnswer?: string;
}

interface GameState {
  gameCode: string | null;
  playerName: string | null;
  hasJoinedGame: boolean;
  currentRoundNumber: number;
  totalRounds: number;
  scores: PlayerScore[];
  activePlayerName: string | null;
  isBuzzActive: boolean;
  isAnswerPhase: boolean;
  isLoading: boolean;
  error: string | null;
  isSocketConnected: boolean;
  role: PlayerRole | null;
  correctAnswer: string | null;
  currentSongAudioUrl: string | null;
  isRoundComplete: boolean;
  isGameOver: boolean;
  prompt: string | null;
  infoMessage: string | null;
  rounds: GameRound[];
  currentRoundIndex: number;
}

interface OptimizedGameSelectors {
  // UI State Selectors
  isBuzzButtonDisabled: boolean;
  isAnswerInputDisabled: boolean;
  canStartGame: boolean;
  canAdvanceRound: boolean;

  // Game Progress Selectors
  currentRoundData: GameRound | null;
  gameProgress: {
    current: number;
    total: number;
    percentage: number;
  };

  // Player State Selectors
  isJudge: boolean;
  isActivePlayer: boolean;
  playerScore: number;
  playerRank: number;

  // Connection State Selectors
  connectionStatus: 'connected' | 'disconnected' | 'reconnecting';
  canPerformActions: boolean;
}

/**
 * Performance-optimized hook that provides memoized selectors for game state.
 * Prevents unnecessary re-renders by only recalculating when relevant dependencies change.
 */
export function useOptimizedGameState(gameState: GameState): OptimizedGameSelectors {
  // UI State Selectors - Memoized to prevent re-renders
  const isBuzzButtonDisabled = useMemo(() => {
    return !gameState.isBuzzActive ||
      gameState.isAnswerPhase ||
      !!gameState.activePlayerName ||
      !gameState.isSocketConnected ||
      gameState.isLoading ||
      !gameState.hasJoinedGame ||
      gameState.isRoundComplete;
  }, [
    gameState.isBuzzActive,
    gameState.isAnswerPhase,
    gameState.activePlayerName,
    gameState.isSocketConnected,
    gameState.isLoading,
    gameState.hasJoinedGame,
    gameState.isRoundComplete
  ]);

  const isAnswerInputDisabled = useMemo(() => {
    return gameState.activePlayerName !== gameState.playerName ||
      gameState.isRoundComplete ||
      !gameState.activePlayerName;
  }, [
    gameState.activePlayerName,
    gameState.playerName,
    gameState.isRoundComplete
  ]);

  const canStartGame = useMemo(() => {
    return gameState.role === 'judge' &&
      gameState.isSocketConnected &&
      !gameState.isLoading &&
      gameState.hasJoinedGame;
  }, [
    gameState.role,
    gameState.isSocketConnected,
    gameState.isLoading,
    gameState.hasJoinedGame
  ]);

  const canAdvanceRound = useMemo(() => {
    return gameState.role === 'judge' &&
      gameState.isRoundComplete &&
      !gameState.isGameOver &&
      gameState.isSocketConnected;
  }, [
    gameState.role,
    gameState.isRoundComplete,
    gameState.isGameOver,
    gameState.isSocketConnected
  ]);

  // Game Progress Selectors
  const currentRoundData = useMemo(() => {
    if (gameState.currentRoundIndex >= 0 && gameState.rounds.length > gameState.currentRoundIndex) {
      return gameState.rounds[gameState.currentRoundIndex];
    }
    return null;
  }, [gameState.currentRoundIndex, gameState.rounds]);

  const gameProgress = useMemo(() => {
    const current = Math.max(0, gameState.currentRoundNumber);
    const total = Math.max(1, gameState.totalRounds);
    const percentage = Math.round((current / total) * 100);

    return { current, total, percentage };
  }, [gameState.currentRoundNumber, gameState.totalRounds]);

  // Player State Selectors
  const isJudge = useMemo(() => {
    return gameState.role === 'judge';
  }, [gameState.role]);

  const isActivePlayer = useMemo(() => {
    return gameState.activePlayerName === gameState.playerName;
  }, [gameState.activePlayerName, gameState.playerName]);

  const playerScore = useMemo(() => {
    const player = gameState.scores.find(p => p.name === gameState.playerName);
    return player?.score || 0;
  }, [gameState.scores, gameState.playerName]);

  const playerRank = useMemo(() => {
    if (!gameState.playerName) return 0;

    const sortedScores = [...gameState.scores]
      .filter(p => p.role !== 'judge')
      .sort((a, b) => b.score - a.score);

    const rank = sortedScores.findIndex(p => p.name === gameState.playerName) + 1;
    return rank || sortedScores.length + 1;
  }, [gameState.scores, gameState.playerName]);

  // Connection State Selectors
  const connectionStatus = useMemo((): 'connected' | 'disconnected' | 'reconnecting' => {
    if (gameState.isSocketConnected) {
      return 'connected';
    } else if (gameState.isLoading) {
      return 'reconnecting';
    } else {
      return 'disconnected';
    }
  }, [gameState.isSocketConnected, gameState.isLoading]);

  const canPerformActions = useMemo(() => {
    return gameState.isSocketConnected &&
      !gameState.isLoading &&
      gameState.hasJoinedGame &&
      !gameState.error;
  }, [
    gameState.isSocketConnected,
    gameState.isLoading,
    gameState.hasJoinedGame,
    gameState.error
  ]);

  return {
    // UI State
    isBuzzButtonDisabled,
    isAnswerInputDisabled,
    canStartGame,
    canAdvanceRound,

    // Game Progress
    currentRoundData,
    gameProgress,

    // Player State
    isJudge,
    isActivePlayer,
    playerScore,
    playerRank,

    // Connection State
    connectionStatus,
    canPerformActions,
  };
}

/**
 * Performance monitoring hook to track re-renders and optimization effectiveness
 */
export function usePerformanceMonitor(componentName: string) {
  const renderCount = useMemo(() => {
    let count = 0;
    return () => {
      count++;
      if (count > 50) {
        console.warn(`[Performance] High render count detected in ${componentName}: ${count}`);
      }
      return count;
    };
  }, [componentName]);

  // Track render in development
  if (process.env.NODE_ENV === 'development') {
    renderCount();
  }

  return {
    renderCount: renderCount(),
  };
}
