import axios from 'axios';

// Use VITE_API_BASE_URL for network/dev flexibility; fallback to localhost for local dev
const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:4000/api';

// Types
export interface CreateGameParams {
  judgeName: string;
  prompt: string;
  numberOfRounds: number;
  llmModel?: string;
}

export interface JoinGameParams {
  playerName: string;
}

export interface StartGameParams {
  requestingUser: string;
}

const apiService = {
  // Create a new game
  createGame: async (params: CreateGameParams) => {
    const response = await axios.post(`${API_BASE_URL}/games`, params);
    return response.data;
  },

  // Join an existing game
  joinGame: async (gameCode: string, params: JoinGameParams) => {
    const response = await axios.post(`${API_BASE_URL}/games/${gameCode}/join`, params);
    return response.data;
  },

  // List participants in a game
  listParticipants: async (gameCode: string) => {
    const response = await axios.get(`${API_BASE_URL}/games/${gameCode}/participants`);
    return response.data.participants;
  },

  // Leave a game
  leaveGame: async (gameCode: string, participantName: string) => {
    const response = await axios.delete(`${API_BASE_URL}/games/${gameCode}/participants/${participantName}`);
    return response.data;
  },

  // Start a game (judge only)
  startGame: async (gameCode: string, params: StartGameParams) => {
    const response = await axios.post(`${API_BASE_URL}/games/${gameCode}/start`, params);
    return response.data;
  },

  // Fetch Spotify artwork for a song
  getSpotifyArtwork: async (title: string, artist: string): Promise<string | null> => {
    const response = await axios.get(`${API_BASE_URL}/music/artwork`, { params: { title, artist } });
    return response.data.artworkUrl || null;
  }
};

export default apiService; 