import type { PlayerScore, PlayerRole } from '../components/Scoreboard';

// Define interfaces for game data
interface GameSettings {
  numberOfRounds?: number;
  prompt?: string;
  [key: string]: unknown;
}

interface CurrentRound {
  songTitle?: string;
  artist?: string;
  audioUrl?: string;
  trackUrl?: string;
  [key: string]: unknown;
}

interface Answer {
  songTitle?: string;
  artist?: string;
  raw?: string;
  [key: string]: unknown;
}

interface FullGameStatePayload {
  audioUrl?: string;
  trackUrl?: string;
  currentRoundDetails?: {
    audioUrl?: string;
    trackUrl?: string;
    [key: string]: unknown;
  };
  currentSongAudioUrl?: string;
  scores?: PlayerScore[] | { [key: string]: number };
  participants?: PlayerScore[] | { [key: string]: number };
  playerName?: string;
  role?: PlayerRole;
  hasJoinedGame?: boolean;
  isLoading?: boolean;
  currentRoundNumber?: number;
  [key: string]: unknown;
}

export interface GameState {
  gameCode: string | null;
  playerName: string | null;
  hasJoinedGame: boolean;
  currentQuestion: string | null;
  category: string | null;
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
  prompt: string | null;
  lastCorrectAnswer: {
    playerName: string;
    answer: string;
    correctAnswer: string;
  } | null;
  infoMessage: string | null;
  infoFeedback?: { message: string; points: number; status: string } | null;
  songList: string[] | null;
  isGameOver: boolean;
  canAdvance?: boolean;
  showRoundResults: boolean;
  roundResults: {
    roundNumber: number;
    correctAnswer: { title: string; artist: string };
    playersWithPoints: Array<{ name: string; score: number; pointsThisRound: number }>;
    roundParticipants: Array<{ name: string; role: string }>;
    artworkUrl: string | null;
    canAdvance: boolean;
    isGameOver: boolean;
  } | null;
}

export type Action =
  | { type: 'PLAYER_NAME_SET'; payload: string }
  | { type: 'JOIN_SUCCESS'; payload: { role: PlayerRole } }
  | { type: 'ROLE_DETERMINED'; payload: PlayerRole }
  | { type: 'GAME_STATE_INIT'; payload: { participants: PlayerScore[]; gameSettings: GameSettings; currentRound?: CurrentRound } }
  | { type: 'PARTICIPANTS_UPDATED'; payload: PlayerScore[] }
  | { type: 'ROUND_STARTED'; payload: { gameCode?: string; round: { songTitle: string; artist: string; audioUrl?: string; trackUrl?: string }; currentRoundNumber: number; totalRounds: number; correctAnswer?: string } }
  | { type: 'BUZZ_IN_SUCCESS'; payload: { player: string } }
  | { type: 'ANSWER_SUBMITTED_ACK'; payload: { gameCode?: string; player: string; answer: Answer; isCorrect?: boolean; correctAnswer?: { title: string; artist: string }; message?: string; points?: number; status?: string; canAdvance?: boolean } }
  | { type: 'SCORE_UPDATE'; payload: { [key: string]: number } }
  | { type: 'GAME_OVER_EVENT'; payload: { finalScores: { [key: string]: number } } }
  | { type: 'SET_LOADING'; payload: boolean }
  | { type: 'SET_ERROR'; payload: { message: string | null; isCritical?: boolean } }
  | { type: 'SET_SOCKET_CONNECTED'; payload: boolean }
  | { type: 'NEXT_ROUND_REQUEST' }
  | { type: 'ADJUST_SCORE_REQUEST'; payload: { playerId: string; newScore: number } }
  | { type: 'SET_ROUND_COMPLETE'; payload: boolean }
  | { type: 'SET_INFO'; payload: { message: string | null } }
  | { type: 'SET_INFO_MESSAGE'; payload: { message: string | null } }
  | { type: 'SET_FULL_GAME_STATE'; payload: FullGameStatePayload }
  | { type: 'BUZZER_STATUS_UPDATE'; payload: { isBuzzActive: boolean; activePlayerName: string | null; isAnswerPhase: boolean } }
  | { type: 'SET_CAN_ADVANCE'; payload: boolean }
  | { type: 'GAME_STARTED'; payload: { gameCode: string; judgeName: string; gameSettings: GameSettings; currentRoundNumber: number; totalRounds: number; participants: PlayerScore[]; prompt: string; isSocketConnected?: boolean } }
  | { type: 'ROUND_COMPLETE'; payload: { roundNumber: number; correctAnswer: { title: string; artist: string }; playersWithPoints: Array<{ name: string; score: number; pointsThisRound: number }>; roundParticipants: Array<{ name: string; role: string }>; artworkUrl: string | null; canAdvance: boolean; isGameOver: boolean } }
  | { type: 'SHOW_ROUND_RESULTS'; payload: boolean }
  | { type: 'HIDE_ROUND_RESULTS' };

export const initialGameStateBase: Omit<GameState, 'gameCode' | 'isSocketConnected'> = {
  currentQuestion: null,
  category: null,
  currentRoundNumber: 0,
  totalRounds: 0,
  scores: [],
  activePlayerName: null,
  isBuzzActive: false,
  isAnswerPhase: false,
  isLoading: false,
  error: null,
  playerName: null,
  hasJoinedGame: false,
  role: null,
  correctAnswer: null,
  currentSongAudioUrl: null,
  isRoundComplete: false,
  isGameOver: false,
  prompt: null,
  lastCorrectAnswer: null,
  infoMessage: null,
  infoFeedback: null,
  songList: null,
  canAdvance: false,
  showRoundResults: false,
  roundResults: null,
};

export function scoresObjectToArray(scoresObj: { [key: string]: number } | null | undefined): PlayerScore[] {
  if (!scoresObj) return [];
  return Object.entries(scoresObj).map(([id, score]) => ({ id, name: id, score, role: 'participant' }));
}

export function isPlayerScoreArray(val: unknown): val is PlayerScore[] {
  return Array.isArray(val) && val.every(p => typeof p === 'object' && p !== null && 'id' in p && 'score' in p);
}

// --- Helper Functions ---
function mergeScores(stateScores: PlayerScore[], payloadScores: { [key: string]: number }): PlayerScore[] {
  const updatedScores = stateScores.map(playerScore => {
    if (payloadScores[playerScore.id] !== undefined) {
      return { ...playerScore, score: payloadScores[playerScore.id] };
    }
    return playerScore;
  });
  Object.keys(payloadScores).forEach(playerId => {
    if (!updatedScores.some(p => p.id === playerId)) {
      updatedScores.push({ id: playerId, name: playerId, score: payloadScores[playerId], role: 'participant' });
    }
  });
  return updatedScores;
}

function handleRoundStarted(state: GameState, action: Action): GameState {
  if (action.type !== 'ROUND_STARTED') throw new Error('Invalid action type');

  const { round, currentRoundNumber, totalRounds } = action.payload;

  console.log('[handleRoundStarted] Processing round data:', { round, currentRoundNumber, totalRounds });

  // DRY: Prefer audioUrl or trackUrl from round, else preserve previous
  let audioUrl = state.currentSongAudioUrl;
  if (round.audioUrl || round.trackUrl) {
    audioUrl = round.audioUrl || round.trackUrl || null;
    console.log('[handleRoundStarted] Setting currentSongAudioUrl from round:', audioUrl);
  } else {
    console.log('[handleRoundStarted] No audioUrl or trackUrl found in round data, preserving existing URL:', audioUrl);
  }

  return {
    ...state,
    currentQuestion: `${round.songTitle} by ${round.artist}`,
    category: round.artist,
    currentRoundNumber,
    totalRounds,
    currentSongAudioUrl: audioUrl,
    correctAnswer: action.payload.correctAnswer || null,
    isAnswerPhase: false,
    activePlayerName: null,
    isBuzzActive: true,
    isRoundComplete: false,
    lastCorrectAnswer: null,
    infoMessage: null,
    canAdvance: false,
    // Clear round results when new round starts
    showRoundResults: false,
    roundResults: null,
  };
}

function handleGameOverEvent(state: GameState, action: Action): GameState {
  // Defensive guard: Ensure finalScores is present
  if (!('payload' in action) || !action.payload) {
    console.warn('GAME_OVER_EVENT action missing or invalid finalScores:', action);
    return state;
  }
  const { finalScores } = action.payload;
  if (!finalScores || (typeof finalScores !== 'object' && !Array.isArray(finalScores))) {
    console.warn('GAME_OVER_EVENT action missing or invalid finalScores:', action.payload);
    return state;
  }
  let finalScoresArrayWithRoles: PlayerScore[];
  if (Array.isArray(finalScores)) {
    finalScoresArrayWithRoles = finalScores;
  } else {
    finalScoresArrayWithRoles = state.scores.map(player => ({
      ...player,
      score: finalScores && finalScores[player.id] !== undefined ? finalScores[player.id] : player.score,
    }));
    if (finalScores && typeof finalScores === 'object') {
      Object.keys(finalScores).forEach(playerId => {
        if (!finalScoresArrayWithRoles.some(p => p.id === playerId)) {
          finalScoresArrayWithRoles.push({
            id: playerId,
            name: playerId,
            score: finalScores[playerId],
            role: 'participant'
          });
        }
      });
    }
  }
  try {
    localStorage.setItem('tt_finalScores', JSON.stringify(finalScoresArrayWithRoles));
    localStorage.setItem('tt_gameEnded', 'true');
  } catch (e) {
    // Not critical, just log
    console.error('Failed to store final scores in localStorage', e);
  }
  return {
    ...state,
    scores: finalScoresArrayWithRoles,
    currentQuestion: 'Game Over! Thank you for playing.',
    isGameOver: true,
    isRoundComplete: true,
    isBuzzActive: false,
    isAnswerPhase: false,
    activePlayerName: null,
    currentSongAudioUrl: null,
    infoMessage: 'The game has ended!',
  };
}

function handleSetFullGameState(state: GameState, action: Action): GameState {
  if (action.type !== 'SET_FULL_GAME_STATE') throw new Error('Invalid action type');

  const payloadData = action.payload;
  console.log('[handleSetFullGameState] Processing full game state update:', payloadData);

  // DRY: Extract audio URL from all possible locations in order of priority
  let extractedAudioUrl =
    payloadData.audioUrl ||
    payloadData.trackUrl ||
    (payloadData.currentRoundDetails && (payloadData.currentRoundDetails.audioUrl || payloadData.currentRoundDetails.trackUrl)) ||
    null;

  // Use existing URL if no new URL found and preserve it
  if (!extractedAudioUrl) {
    extractedAudioUrl = state.currentSongAudioUrl;
  }
  payloadData.currentSongAudioUrl = extractedAudioUrl;
  console.log('[handleSetFullGameState] Setting currentSongAudioUrl to:', extractedAudioUrl);
  console.log('[handleSetFullGameState] Previous URL was:', state.currentSongAudioUrl);
  console.log('[handleSetFullGameState] URL changed:', state.currentSongAudioUrl !== extractedAudioUrl);

  // Handle scores normalization
  let normalizedScores = state.scores;
  if (payloadData.scores) {
    if (isPlayerScoreArray(payloadData.scores)) {
      normalizedScores = payloadData.scores;
    } else {
      normalizedScores = scoresObjectToArray(payloadData.scores);
    }
  } else if (payloadData.participants && isPlayerScoreArray(payloadData.participants)) {
    normalizedScores = payloadData.participants;
  }

  // PRESERVE EXISTING PLAYER INFO: Don't reset playerName, role, or hasJoinedGame if they're already set
  // This prevents the judge from being asked to enter their name again after game starts
  let playerName = state.playerName;
  let role = state.role;
  let hasJoinedGame = state.hasJoinedGame;

  // Only update player info if it's explicitly provided in payload AND we don't have it already
  if (payloadData.playerName !== undefined && !state.playerName) {
    playerName = payloadData.playerName;
  }
  if (payloadData.role !== undefined && !state.role) {
    role = payloadData.role;
  }

  // Determine hasJoinedGame status - preserve if already true
  if (!hasJoinedGame) {
    // If we have game data (like scores or round info), assume we've joined
    if ((normalizedScores.length > 0 || payloadData.currentRoundNumber > 0) && playerName && state.gameCode) {
      hasJoinedGame = true;
    }

    // Explicit hasJoinedGame in payload takes precedence
    if (payloadData.hasJoinedGame !== undefined) {
      hasJoinedGame = payloadData.hasJoinedGame;
    }
  }

  // Build result state - explicitly preserve critical player info
  const result = {
    ...state,
    ...payloadData,
    // Explicitly preserve these critical fields
    playerName,
    role,
    hasJoinedGame,
    scores: normalizedScores,
    currentSongAudioUrl: extractedAudioUrl,
    // Preserve loading state logic
    isLoading: payloadData.isLoading !== undefined ? payloadData.isLoading :
      (hasJoinedGame && (normalizedScores.length > 0 || payloadData.currentRoundNumber > 0)) ? false : state.isLoading
  };

  console.log('[handleSetFullGameState] Final result - hasJoinedGame:', result.hasJoinedGame, 'playerName:', result.playerName, 'role:', result.role, 'currentSongAudioUrl:', result.currentSongAudioUrl, 'currentRoundNumber:', result.currentRoundNumber);

  if (result.isRoundComplete && result.canAdvance === undefined) {
    result.canAdvance = result.currentRoundNumber < result.totalRounds;
  }
  return result;
}

// --- Main Reducer ---
export const gameReducer = (state: GameState, action: Action): GameState => {
  let scores = state.scores;
  if (!isPlayerScoreArray(scores)) {
    if (scores && typeof scores === 'object') {
      scores = scoresObjectToArray(scores as { [key: string]: number });
    } else {
      throw new Error('gameReducer: state.scores is not an array or object');
    }
  }
  let nextState: GameState;
  switch (action.type) {
    case 'PLAYER_NAME_SET':
      nextState = { ...state, playerName: action.payload, isLoading: true, scores };
      break;
    case 'JOIN_SUCCESS': {
      const playerExistsInScores = scores.some(p => p.id === state.playerName);
      let updatedScoresOnJoin = scores;
      if (state.playerName && !playerExistsInScores) {
        updatedScoresOnJoin = [
          ...scores,
          { id: state.playerName, name: state.playerName, score: 0, role: action.payload.role }
        ];
      }
      nextState = {
        ...state,
        hasJoinedGame: true,
        isLoading: false,
        error: null,
        role: action.payload.role,
        scores: updatedScoresOnJoin
      };
      break;
    }
    case 'ROLE_DETERMINED':
      nextState = {
        ...state,
        role: action.payload,
      };
      break;
    case 'GAME_STATE_INIT':
      nextState = {
        ...state,
        scores: isPlayerScoreArray(action.payload.participants) ? action.payload.participants.map(p => ({ ...p })) : scoresObjectToArray(action.payload.participants as { [key: string]: number }),
        totalRounds: action.payload.gameSettings?.numberOfRounds || state.totalRounds,
        prompt: action.payload.gameSettings?.prompt || state.prompt,
        isLoading: false,
        error: null
      };
      break;
    case 'PARTICIPANTS_UPDATED':
      nextState = {
        ...state,
        scores: isPlayerScoreArray(action.payload) ? action.payload.map(p => ({ ...p })) : scoresObjectToArray(action.payload as { [key: string]: number })
      };
      break;
    case 'ROUND_STARTED':
      return handleRoundStarted(state, action);
    case 'BUZZ_IN_SUCCESS':
      nextState = {
        ...state,
        activePlayerName: action.payload.player,
        isBuzzActive: false,
        isAnswerPhase: true,
      };
      break;
    case 'SCORE_UPDATE': {
      if (!('payload' in action) || !action.payload) {
        nextState = state;
        break;
      }
      let updatedScoresFromUpdate = state.scores;
      if (action.payload && typeof action.payload === 'object' && !Array.isArray(action.payload)) {
        updatedScoresFromUpdate = mergeScores(state.scores, action.payload);
      } else if (Array.isArray(action.payload)) {
        updatedScoresFromUpdate = action.payload;
      } else {
        console.warn('SCORE_UPDATE received unexpected payload structure:', action.payload);
        const scoreMap = action.payload as { [key: string]: number };
        updatedScoresFromUpdate = state.scores.map(ps => ({
          ...ps,
          score: scoreMap[ps.id] !== undefined ? scoreMap[ps.id] : ps.score,
        }));
      }
      nextState = { ...state, scores: updatedScoresFromUpdate };
      break;
    }
    case 'ANSWER_SUBMITTED_ACK': {
      let lastCorrectAnswer = state.lastCorrectAnswer;
      let newInfoMessage = null;
      let newInfoFeedback: { message: string; points: number; status: string } | null = null;
      let newError = state.error;
      let newCorrectAnswer = state.correctAnswer;
      let canAdvance = action.payload.canAdvance;
      // Default canAdvance to false if undefined, except for last round
      if (canAdvance === undefined) {
        canAdvance = state.currentRoundNumber < state.totalRounds;
      }
      if (action.payload.isCorrect && action.payload.correctAnswer) {
        lastCorrectAnswer = {
          playerName: action.payload.player,
          answer: typeof action.payload.answer === 'object' ?
            `${action.payload.answer.songTitle} by ${action.payload.answer.artist}` :
            String(action.payload.answer),
          correctAnswer: `${action.payload.correctAnswer.title} by ${action.payload.correctAnswer.artist}`
        };
        newInfoMessage = action.payload.message ||
          `Correct! ${action.payload.player} got it. The answer was "${action.payload.correctAnswer.title}" by "${action.payload.correctAnswer.artist}".`;
        newInfoFeedback = {
          message: action.payload.message ?? '',
          points: action.payload.points ?? 0,
          status: action.payload.status ?? '',
        };
        newCorrectAnswer = `${action.payload.correctAnswer.title} by ${action.payload.correctAnswer.artist}`;
        newError = null;
      } else if (action.payload.correctAnswer) {
        newCorrectAnswer = `${action.payload.correctAnswer.title} by ${action.payload.correctAnswer.artist}`;
        if (action.payload.message && action.payload.message.includes('Partially correct')) {
          newInfoMessage = action.payload.message;
          newInfoFeedback = {
            message: action.payload.message ?? '',
            points: action.payload.points ?? 0,
            status: action.payload.status ?? '',
          };
        }
      }
      nextState = {
        ...state,
        lastCorrectAnswer,
        infoMessage: newInfoMessage,
        infoFeedback: newInfoFeedback,
        error: newError,
        correctAnswer: newCorrectAnswer,
        canAdvance,
      };
      break;
    }
    case 'GAME_OVER_EVENT':
      if (!('payload' in action) || !action.payload) {
        return state;
      }
      return handleGameOverEvent(state, action);
    case 'SET_LOADING':
      nextState = { ...state, isLoading: action.payload };
      break;
    case 'SET_ERROR': {
      const unrecoverableErrors = [
        'Game not found',
        'You have been removed from the game',
        'Failed to join game room',
        'Failed to rejoin game',
        'Game has ended',
        'Game is over',
      ];
      const isUnrecoverable = action.payload.isCritical === true &&
        action.payload.message &&
        unrecoverableErrors.some(msg => action.payload.message?.includes(msg));
      if (isUnrecoverable) {
        console.warn('[gameReducer] Setting hasJoinedGame to false due to unrecoverable error:', action.payload.message);
      }
      nextState = {
        ...state,
        error: action.payload.message,
        isLoading: false,
        hasJoinedGame: isUnrecoverable ? false : state.hasJoinedGame,
        infoMessage: null,
      };
      break;
    }
    case 'SET_SOCKET_CONNECTED':
      nextState = { ...state, isSocketConnected: action.payload };
      break;
    case 'SET_ROUND_COMPLETE':
      console.log('[gameStateReducer] SET_ROUND_COMPLETE:', action.payload, 'Previous isRoundComplete:', state.isRoundComplete);
      nextState = { ...state, isRoundComplete: action.payload };
      break;
    case 'NEXT_ROUND_REQUEST':
      return handleNextRoundRequest(state);
    case 'ADJUST_SCORE_REQUEST':
      nextState = state;
      break;
    case 'SET_INFO':
      nextState = { ...state, infoMessage: action.payload.message };
      break;
    case 'SET_INFO_MESSAGE':
      nextState = { ...state, infoMessage: action.payload.message };
      break;
    case 'SET_FULL_GAME_STATE':
      return handleSetFullGameState(state, action);
    case 'BUZZER_STATUS_UPDATE':
      nextState = {
        ...state,
        isBuzzActive: action.payload.isBuzzActive,
        activePlayerName: action.payload.activePlayerName,
        isAnswerPhase: action.payload.isAnswerPhase,
        error: action.payload.isBuzzActive ? null : state.error,
        infoMessage: action.payload.isBuzzActive ? null : state.infoMessage,
      };
      break;
    case 'SET_CAN_ADVANCE':
      console.log('[gameStateReducer] SET_CAN_ADVANCE:', action.payload, 'Previous canAdvance:', state.canAdvance);
      nextState = { ...state, canAdvance: action.payload };
      break;
    case 'GAME_STARTED':
      nextState = {
        ...initialGameStateBase,
        gameCode: action.payload.gameCode,
        playerName: state.playerName,
        role: state.playerName === action.payload.judgeName ? 'judge' : state.role,
        hasJoinedGame: state.hasJoinedGame,
        isSocketConnected: action.payload.isSocketConnected !== undefined ? action.payload.isSocketConnected : state.isSocketConnected,
        scores: isPlayerScoreArray(action.payload.participants) ? action.payload.participants.map(p => ({ ...p })) : scoresObjectToArray(action.payload.participants as { [key: string]: number }),
        totalRounds: action.payload.gameSettings?.numberOfRounds || 0,
        prompt: action.payload.prompt || null,
        currentRoundNumber: action.payload.currentRoundNumber || 0,
        isRoundComplete: false,
        canAdvance: false,
        isGameOver: false,
        activePlayerName: null,
        isBuzzActive: false,
        isAnswerPhase: false,
        error: null,
        lastCorrectAnswer: null,
        infoMessage: null,
        currentSongAudioUrl: null,
        currentQuestion: null,
        category: null,
      };
      break;
    case 'ROUND_COMPLETE':
      nextState = {
        ...state,
        roundResults: {
          roundNumber: action.payload.roundNumber,
          correctAnswer: action.payload.correctAnswer,
          playersWithPoints: action.payload.playersWithPoints,
          roundParticipants: action.payload.roundParticipants,
          artworkUrl: action.payload.artworkUrl,
          canAdvance: action.payload.canAdvance,
          isGameOver: action.payload.isGameOver,
        },
        showRoundResults: true,
        isRoundComplete: true,
        canAdvance: action.payload.canAdvance,
      };
      break;
    case 'SHOW_ROUND_RESULTS':
      nextState = { ...state, showRoundResults: action.payload };
      break;
    case 'HIDE_ROUND_RESULTS':
      nextState = { ...state, showRoundResults: false };
      break;
    default:
      nextState = state;
      break;
  }
  if (nextState.hasJoinedGame !== state.hasJoinedGame) {
    console.log('[useGameLogic] hasJoinedGame changed:', nextState.hasJoinedGame, 'Action:', action.type, action);
  }
  return nextState ?? state;
};

function handleNextRoundRequest(state: GameState): GameState {
  console.log('[handleNextRoundRequest] Judge requested next round');
  return {
    ...state,
    isLoading: true,
    isRoundComplete: false,
    showRoundResults: false,
    roundResults: null,
    activePlayerName: null,
    isBuzzActive: false,
    isAnswerPhase: false,
    correctAnswer: null,
    currentSongAudioUrl: null,
    infoMessage: null,
    canAdvance: false,
  };
}
