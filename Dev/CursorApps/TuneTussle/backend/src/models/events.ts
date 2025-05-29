import { GameSession, GameRound, Participant } from './types';

export interface GameStartEvent { gameCode: string; game: GameSession; }
export interface RoundStartEvent { gameCode: string; roundDetails: GameRound; game: GameSession; }
export interface AnswerSubmittedEvent {
  gameCode: string;
  playerName: string;
  answer: { songTitle: string; artist: string };
  isCorrect: boolean | null;
  correctAnswer?: { title: string; artist: string };
  message?: string;
  points?: number;
  status?: string;
  canAdvance?: boolean;
}
export interface AnswerTimerExpiredEvent { gameCode: string; playerName: string; }
export interface PlayerJoinedEvent { gameCode: string; playerName: string; participants: Participant[]; }
export interface PlayerLeftEvent { gameCode: string; playerName: string; participants: Participant[]; }
export interface ScoreUpdateEvent { gameCode: string; scores: Map<string, number>; }
export interface BuzzerStatusUpdateEvent {
  gameCode: string;
  isBuzzActive: boolean;
  activePlayerName: string | null;
  isAnswerPhase: boolean;
}

export interface PlayerBuzzedInEvent {
  gameCode: string;
  playerName: string;
}

export interface BuzzerEnabledEvent {
  gameCode: string;
}

export interface BuzzerDisabledEvent {
  gameCode: string;
}

export interface RoundEndedEvent {
  gameCode: string;
  // Add more fields as needed (e.g., round number, scores, etc.)
}

export interface GameOverEvent {
  gameCode: string;
  // Add more fields as needed (e.g., final scores, winner, etc.)
}

export interface AnswerTimerStartedEvent {
  gameCode: string;
  playerName: string;
  duration: number;
}

export interface AllPlayersAttemptedNoCorrectEvent {
  gameCode: string;
  message: string;
  canAdvance: boolean;
  isGameOver: boolean;
  correctAnswer: { title: string; artist: string };
}

export interface RoundCompleteEvent {
  gameCode: string;
  roundNumber: number;
  correctAnswer: { title: string; artist: string };
  playersWithPoints: Array<{ name: string; score: number; pointsThisRound: number }>;
  roundParticipants: Array<{ name: string; role: string }>;
  artworkUrl: string | null;
  canAdvance: boolean;
  isGameOver: boolean;
}

// Add more as needed for your game events 