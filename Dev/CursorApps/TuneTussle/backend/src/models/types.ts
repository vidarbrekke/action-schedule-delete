// types.ts - Defines all the core types for the game system

import * as Events from './events';

// Represents a single round in a game
export type GameRound = {
  songTitle: string;
  artist: string;
  trackUrl?: string;
  answers: Array<{
    player: string;
    songTitle: string;
    artist: string;
    correctSong: boolean;
    correctArtist: boolean;
    score: number;
  }>;
  buzzedInPlayer: string | null; // Tracks who buzzed in for this round
  playersWhoAttempted: Set<string>; // Tracks players who have made an attempt this round
  isBuzzerOpenForNewAttempts: boolean; // Is the buzzer generally open for new players to buzz in?
  answerTimer?: NodeJS.Timeout;
};

// Represents a participant in the game
export interface Participant {
  id: string;
  name: string;
  score: number;
  role: 'judge' | 'participant';
}

// Represents a single game session
export type GameSession = { 
  code: string; 
  judgeName: string; 
  participants: Map<string, Participant>;
  gameSettings: { 
    prompt: string;
    numberOfRounds: number;
    llmModel?: string; 
  };
  gameState: 'lobby' | 'pending' | 'ready' | 'playing' | 'finished' | 'error'; 
  songList: GameSong[];
  rounds: GameRound[];
  currentRoundIndex: number; 
  scores: Map<string, number>; 
  buzzOrder: string[];
  answers: Map<string, AnswerRecord[]>;
  currentRoundDetails?: GameRound;
  errorDetails?: string;
};

// Record for tracking answers
export type AnswerRecord = {
  player: string;
  songTitle: string;
  artist: string;
  correctSong: boolean;
  correctArtist: boolean;
  score: number;
};

// Interface for the hooks that GameSessionManager can call
export interface Hooks {
  onGameStart?: (payload: Events.GameStartEvent) => void;
  onRoundStart?: (payload: Events.RoundStartEvent) => void;
  onAnswerSubmitted?: (payload: Events.AnswerSubmittedEvent) => void;
  onAnswerTimerExpired?: (payload: Events.AnswerTimerExpiredEvent) => void;
  onAnswerTimerStarted?: (payload: Events.AnswerTimerStartedEvent) => void;
  onPlayerJoined?: (payload: Events.PlayerJoinedEvent) => void;
  onPlayerLeft?: (payload: Events.PlayerLeftEvent) => void;
  onScoreUpdate?: (payload: Events.ScoreUpdateEvent) => void;
  onBuzzerStatusUpdate?: (payload: Events.BuzzerStatusUpdateEvent) => void;
  onPlayerBuzzedIn?: (payload: Events.PlayerBuzzedInEvent) => void;
  onBuzzerEnabled?: (payload: Events.BuzzerEnabledEvent) => void;
  onBuzzerDisabled?: (payload: Events.BuzzerDisabledEvent) => void;
  onRoundEnded?: (payload: Events.RoundEndedEvent) => void;
  onGameOver?: (payload: Events.GameOverEvent) => void;
  onAllPlayersAttemptedNoCorrect?: (payload: Events.AllPlayersAttemptedNoCorrectEvent) => void;
  onRoundComplete?: (payload: Events.RoundCompleteEvent) => void;
  // Add more as needed
}

// Song type
export type Song = {
  title: string;
  artist: string;
  trackUrl?: string;
};

// Game song with usage tracking
export type GameSong = Song & {
  used: boolean;
}; 