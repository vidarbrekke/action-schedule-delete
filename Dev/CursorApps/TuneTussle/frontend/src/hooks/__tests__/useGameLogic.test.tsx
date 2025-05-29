import React from 'react';
import { renderHook, act } from '@testing-library/react';
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { useGameLogic } from '../useGameLogic';
import { SocketProvider } from '../../contexts/SocketContext';

// Mock the socket context
vi.mock('../../contexts/SocketContext', async () => {
  const actual = await vi.importActual('../../contexts/SocketContext');
  return {
    ...actual,
    useSocket: vi.fn().mockReturnValue({
      socket: {
        on: vi.fn(),
        off: vi.fn(),
        emit: vi.fn(),
        disconnect: vi.fn(),
      },
      isConnected: true,
    }),
  };
});

// Mock the params hook
vi.mock('react-router-dom', () => ({
  useParams: () => ({ gameCode: 'TEST123' }),
  useNavigate: () => vi.fn(),
  useLocation: () => ({ state: null }),
}));

// Mock other dependencies
vi.mock('../useGameSocketEvents', () => ({
  useGameSocketEvents: vi.fn(),
}));

vi.mock('../useLobbyState', () => ({
  useLobbyState: () => [
    {
      isLoadingRole: false,
      isStartingGame: false,
      startGameError: null,
      isGeneratingSongs: false,
      isGameReady: true,
      expectedSongCount: null,
    },
    vi.fn(),
  ],
}));

vi.mock('../usePlayerRoundState', () => ({
  usePlayerRoundState: () => ({
    playerState: { state: 'IDLE' },
    setPlayerState: vi.fn(),
  }),
}));

vi.mock('../useUnifiedSocketManager', () => ({
  useUnifiedSocketManager: () => ({
    forceStateRefresh: vi.fn(),
    forceRejoin: vi.fn(),
  }),
}));

vi.mock('../useGameEffects', () => ({
  useGameEffects: vi.fn(),
}));

vi.mock('../../api/apiService', () => ({
  default: {
    startGame: vi.fn(),
  },
}));

vi.mock('../../services/gameToastService', () => ({
  gameToastService: {
    systemStatus: vi.fn(),
    playerAction: vi.fn(),
    judgeControl: vi.fn(),
    roundTransition: vi.fn(),
    updateContext: vi.fn(),
    dismiss: vi.fn(),
    dismissAll: vi.fn(),
  },
}));

vi.mock('../../services/gameAudioManager', () => ({
  gameAudioManager: {},
}));

describe('useGameLogic - Core Functionality Tests', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  const wrapper = ({ children }: { children: React.ReactNode }) => (
    <SocketProvider>
      {children}
    </SocketProvider>
  );

  describe('Basic Hook Functionality', () => {
    it('should provide expected functions and initial state', () => {
      const { result } = renderHook(() => useGameLogic(), { wrapper });
      
      // Check that essential functions are provided (only test what's actually available)
      expect(typeof result.current.setPlayerNameAndInitiateJoin).toBe('function');
      expect(typeof result.current.handleEndRound).toBe('function');
      expect(typeof result.current.setRoundComplete).toBe('function');
      
      // Check initial state
      expect(result.current.isRoundComplete).toBe(false);
      expect(result.current.canAdvance).toBe(false);
      expect(result.current.playerName).toBeNull();
      expect(result.current.hasJoinedGame).toBe(false);
    });

    it('should update player name when setPlayerNameAndInitiateJoin is called', () => {
      const { result } = renderHook(() => useGameLogic(), { wrapper });
      
      act(() => {
        result.current.setPlayerNameAndInitiateJoin('TestPlayer');
      });
      
      expect(result.current.playerName).toBe('TestPlayer');
    });

    it('should update round completion state', () => {
      const { result } = renderHook(() => useGameLogic(), { wrapper });
      
      act(() => {
        result.current.setRoundComplete(true);
      });
      
      expect(result.current.isRoundComplete).toBe(true);
      
      act(() => {
        result.current.setRoundComplete(false);
      });
      
      expect(result.current.isRoundComplete).toBe(false);
    });
  });

  describe('State Management', () => {
    it('should maintain state consistency across multiple operations', () => {
      const { result } = renderHook(() => useGameLogic(), { wrapper });

      // Initial state
      expect(result.current.isRoundComplete).toBe(false);
      expect(result.current.canAdvance).toBe(false);

      // Set round complete
      act(() => {
        result.current.setRoundComplete(true);
      });

      expect(result.current.isRoundComplete).toBe(true);
      expect(result.current.canAdvance).toBe(false); // Should remain independent

      // Reset
      act(() => {
        result.current.setRoundComplete(false);
      });

      expect(result.current.isRoundComplete).toBe(false);
      expect(result.current.canAdvance).toBe(false);
    });

    it('should handle round completion logic correctly', () => {
      const { result } = renderHook(() => useGameLogic(), { wrapper });

      // Initially not complete and cannot advance
      expect(result.current.isRoundComplete).toBe(false);
      expect(result.current.canAdvance).toBe(false);
    });
  });

  describe('Function Availability', () => {
    it('should provide handleEndRound function in the hook return', () => {
      const { result } = renderHook(() => useGameLogic(), { wrapper });
      
      expect(result.current.handleEndRound).toBeDefined();
      expect(typeof result.current.handleEndRound).toBe('function');
    });

    it('should maintain handleEndRound function reference across re-renders', () => {
      const { result, rerender } = renderHook(() => useGameLogic(), { wrapper });
      
      const firstReference = result.current.handleEndRound;
      
      rerender();
      
      const secondReference = result.current.handleEndRound;
      expect(firstReference).toBe(secondReference);
    });

    it('should provide core functions', () => {
      const { result } = renderHook(() => useGameLogic(), { wrapper });
      
      const expectedFunctions = [
        'setPlayerNameAndInitiateJoin',
        'handleEndRound',
        'setRoundComplete'
      ];
      
      expectedFunctions.forEach(funcName => {
        expect((result.current as Record<string, unknown>)[funcName]).toBeDefined();
        expect(typeof (result.current as Record<string, unknown>)[funcName]).toBe('function');
      });
    });
  });

  describe('Game State Properties', () => {
    it('should expose core game state properties', () => {
      const { result } = renderHook(() => useGameLogic(), { wrapper });
      
      const coreProperties = [
        'playerName',
        'hasJoinedGame',
        'isLoading', 
        'isRoundComplete',
        'canAdvance',
        'gameCode',
        'currentQuestion',
        'currentRound',
        'isGameOver',
        'error'
      ];
      
      coreProperties.forEach(prop => {
        expect(result.current).toHaveProperty(prop);
      });
    });

    it('should have reasonable initial values', () => {
      const { result } = renderHook(() => useGameLogic(), { wrapper });
      
      expect(result.current.playerName).toBeNull();
      expect(result.current.hasJoinedGame).toBe(false);
      expect(result.current.isLoading).toBe(false);
      expect(result.current.isRoundComplete).toBe(false);
      expect(result.current.canAdvance).toBe(false);
      expect(result.current.gameCode).toBe('TEST123');
    });
  });
}); 