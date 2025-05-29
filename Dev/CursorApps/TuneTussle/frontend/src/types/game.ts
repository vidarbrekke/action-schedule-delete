// Core game interfaces
export interface Player {
    id: string;
    name: string;
    isJudge: boolean;
    isActive: boolean;
}

export interface PlayerScore {
    id: string;
    name: string;
    score: number;
    role: 'participant' | 'judge';
}

export interface Song {
    title: string;
    artist: string;
    url?: string;
    previewUrl?: string;
    duration?: number;
    albumArt?: string;
}

export interface Answer {
    title: string;
    artist: string;
}

export interface GameSettings {
    numberOfRounds: number;
    roundDuration?: number;
    buzzerEnabled?: boolean;
    autoAdvance?: boolean;
    prompt?: string;
    [key: string]: unknown; // Allow additional settings
}

export interface CurrentRound {
    number: number;
    song?: Song;
    startTime?: string;
    endTime?: string;
    correctAnswer?: Answer;
}

export interface GameState {
    gameCode: string;
    phase: 'lobby' | 'playing' | 'judging' | 'scoring' | 'finished';
    currentRound: number;
    totalRounds: number;
    players: PlayerScore[];
    judgeId?: string;
    judgeName?: string;
    currentSong?: Song;
    settings: GameSettings;
    lastUpdated?: string;
    roundData?: CurrentRound;
}

// Socket event payload interfaces
export interface JoinGamePayload {
    gameCode: string;
    playerName: string;
}

export interface GameCreatedPayload {
    gameCode: string;
    judgeId: string;
    judgeName: string;
}

export interface RoleAssignedPayload {
    role: 'judge' | 'participant';
    participants?: PlayerScore[];
    gameSettings?: GameSettings;
    judgeName?: string;
}

export interface GameStartedPayload {
    gameState: GameState;
    settings?: GameSettings;
}

export interface RoundStartedPayload {
    round: number;
    song: Song;
    gameState?: GameState;
}

export interface PlayerBuzzedPayload {
    playerId: string;
    playerName: string;
    timestamp: string;
    gameCode?: string;
}

export interface AnswerSubmittedPayload {
    playerId: string;
    playerName?: string;
    answer: Answer | string;
    isCorrect?: boolean;
    correctAnswer?: Answer;
    message?: string;
    canAdvance?: boolean;
    gameCode?: string;
}

export interface RoundEndedPayload {
    scores: PlayerScore[];
    correctAnswer?: Answer;
    gameState?: GameState;
    round?: number;
}

export interface GameEndedPayload {
    finalScores: PlayerScore[];
    winner?: Player;
    gameState?: GameState;
}

export interface ErrorPayload {
    message: string;
    code?: string;
    type?: 'validation' | 'game' | 'network';
}

export interface GameNotFoundPayload {
    gameCode: string;
    message?: string;
}

// Socket event map for type-safe event handling
export interface SocketEventMap {
    // Game management
    'join-game': (data: JoinGamePayload) => void;
    'game-created': (data: GameCreatedPayload) => void;
    'game-state': (data: GameState) => void;
    'role-assigned': (data: RoleAssignedPayload) => void;

    // Game flow
    'game-started': (data: GameStartedPayload) => void;
    'round-started': (data: RoundStartedPayload) => void;
    'player-buzzed': (data: PlayerBuzzedPayload) => void;
    'answer-submitted': (data: AnswerSubmittedPayload) => void;
    'round-ended': (data: RoundEndedPayload) => void;
    'game-ended': (data: GameEndedPayload) => void;

    // Error handling
    'error': (data: ErrorPayload) => void;
    'game-not-found': (data: GameNotFoundPayload) => void;
    'invalid-action': (data: ErrorPayload) => void;

    // Connection management
    'connect': () => void;
    'disconnect': (reason: string) => void;
    'reconnect': () => void;
}

// Redux-style action types for game state reducer
export type GameAction =
    | { type: 'GAME_INIT'; payload: GameState }
    | { type: 'GAME_STATE_INIT'; payload: GameState }
    | { type: 'ROLE_DETERMINED'; payload: 'judge' | 'participant' }
    | { type: 'PLAYER_JOINED'; payload: { player: Player } }
    | { type: 'GAME_STARTED'; payload: { settings: GameSettings; gameState?: GameState } }
    | { type: 'ROUND_STARTED'; payload: { round: number; song: Song; gameState?: GameState } }
    | { type: 'PLAYER_BUZZED'; payload: PlayerBuzzedPayload }
    | { type: 'ANSWER_SUBMITTED'; payload: AnswerSubmittedPayload }
    | { type: 'ROUND_ENDED'; payload: RoundEndedPayload }
    | { type: 'GAME_ENDED'; payload: GameEndedPayload }
    | { type: 'ERROR_OCCURRED'; payload: ErrorPayload }
    | { type: 'PARTICIPANTS_UPDATED'; payload: { participants: PlayerScore[] } }
    | { type: 'SETTINGS_UPDATED'; payload: { gameSettings: GameSettings } };

// Hook return types
export interface UseGameLogicReturn {
    // Game state
    gameState: GameState | null;
    isLoading: boolean;
    error: string | null;

    // Player info
    currentPlayer: Player | null;
    isJudge: boolean;
    canBuzz: boolean;

    // Game actions
    joinGame: (gameCode: string, playerName: string) => Promise<void>;
    startGame: (settings: GameSettings) => Promise<void>;
    buzzIn: () => void;
    submitAnswer: (answer: string | Answer) => Promise<void>;
    advanceRound: () => Promise<void>;
    endGame: () => Promise<void>;
}

export interface UseLobbyStateReturn {
    // Lobby state
    participants: PlayerScore[];
    gameSettings: GameSettings | null;
    judgeName: string | null;
    isLoading: boolean;
    error: string | null;

    // Lobby actions
    updateSettings: (settings: Partial<GameSettings>) => void;
    removePlayer: (playerId: string) => void;
}

export interface UseSocketEventsReturn {
    // Connection state
    isConnected: boolean;
    connectionError: string | null;

    // Event subscription
    subscribe: <K extends keyof SocketEventMap>(
        event: K,
        handler: SocketEventMap[K]
    ) => () => void;

    // Event emission
    emit: <K extends keyof SocketEventMap>(
        event: K,
        data?: Parameters<SocketEventMap[K]>[0]
    ) => void;
}

// Utility types for common patterns
export type PlayerRole = 'judge' | 'participant';
export type GamePhase = GameState['phase'];
export type SocketEventName = keyof SocketEventMap;

// Type guards for runtime type checking
export const isGameState = (obj: unknown): obj is GameState => {
    return (
        typeof obj === 'object' &&
        obj !== null &&
        'gameCode' in obj &&
        'phase' in obj &&
        'players' in obj
    );
};

export const isPlayerScore = (obj: unknown): obj is PlayerScore => {
    return (
        typeof obj === 'object' &&
        obj !== null &&
        'id' in obj &&
        'name' in obj &&
        'score' in obj &&
        'role' in obj
    );
};

export const isAnswer = (obj: unknown): obj is Answer => {
    return (
        typeof obj === 'object' &&
        obj !== null &&
        'title' in obj &&
        'artist' in obj
    );
};

// Default/initial values
export const createEmptyGameState = (): Partial<GameState> => ({
    gameCode: '',
    phase: 'lobby',
    currentRound: 0,
    totalRounds: 0,
    players: [],
    settings: {
        numberOfRounds: 5,
        buzzerEnabled: true,
    },
});

export const createEmptyGameSettings = (): GameSettings => ({
    numberOfRounds: 5,
    roundDuration: 30,
    buzzerEnabled: true,
    autoAdvance: false,
    prompt: 'Name the song and artist',
});
