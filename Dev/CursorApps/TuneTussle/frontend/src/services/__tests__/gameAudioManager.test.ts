import { describe, it, expect, beforeEach, vi } from 'vitest';
import { gameAudioManager } from '../gameAudioManager';
import { audioService } from '../audioService';

// Mock the audioService
vi.mock('../audioService', () => ({
  audioService: {
    preloadBuzzerAudio: vi.fn(),
    preloadWinningAudio: vi.fn(),
    playSound: vi.fn(),
    isSoundReady: vi.fn(),
  }
}));

describe('GameAudioManager', () => {
  beforeEach(() => {
    // Reset the manager state before each test
    gameAudioManager.reset();
    vi.clearAllMocks();
  });

  describe('onGameStart', () => {
    it('should pre-load buzzer audio when game starts on round 1', async () => {
      await gameAudioManager.onGameStart(1);

      expect(audioService.preloadBuzzerAudio).toHaveBeenCalledOnce();
      expect(gameAudioManager.getState().buzzerPreloaded).toBe(true);
      expect(gameAudioManager.getState().gameStarted).toBe(true);
    });

    it('should not pre-load buzzer audio multiple times', async () => {
      await gameAudioManager.onGameStart(1);
      await gameAudioManager.onGameStart(2);

      expect(audioService.preloadBuzzerAudio).toHaveBeenCalledOnce();
    });

    it('should handle pre-loading errors gracefully', async () => {
      const consoleSpy = vi.spyOn(console, 'warn').mockImplementation(() => {});
      (audioService.preloadBuzzerAudio as any).mockRejectedValue(new Error('Load failed'));

      await gameAudioManager.onGameStart(1);

      expect(consoleSpy).toHaveBeenCalledWith(
        '[GameAudioManager] Failed to pre-load buzzer audio:',
        expect.any(Error)
      );
      consoleSpy.mockRestore();
    });

    it('should not start game multiple times', async () => {
      await gameAudioManager.onGameStart(1);
      vi.clearAllMocks();
      
      await gameAudioManager.onGameStart(2);

      expect(audioService.preloadBuzzerAudio).not.toHaveBeenCalled();
    });
  });

  describe('onScoreUpdate', () => {
    it('should pre-load winning audio when a player reaches threshold', async () => {
      const scores = [
        { score: 15, role: 'participant' },
        { score: 5, role: 'participant' },
        { score: 0, role: 'judge' }
      ];

      await gameAudioManager.onScoreUpdate(scores);

      expect(audioService.preloadWinningAudio).toHaveBeenCalledOnce();
      expect(gameAudioManager.getState().winningPreloaded).toBe(true);
    });

    it('should not pre-load winning audio below threshold', async () => {
      const scores = [
        { score: 14, role: 'participant' },
        { score: 5, role: 'participant' },
        { score: 0, role: 'judge' }
      ];

      await gameAudioManager.onScoreUpdate(scores);

      expect(audioService.preloadWinningAudio).not.toHaveBeenCalled();
      expect(gameAudioManager.getState().winningPreloaded).toBe(false);
    });

    it('should ignore judge scores when calculating threshold', async () => {
      const scores = [
        { score: 10, role: 'participant' },
        { score: 20, role: 'judge' } // Judge score should be ignored
      ];

      await gameAudioManager.onScoreUpdate(scores);

      expect(audioService.preloadWinningAudio).not.toHaveBeenCalled();
    });

    it('should not pre-load winning audio multiple times', async () => {
      const scores1 = [{ score: 15, role: 'participant' }];
      const scores2 = [{ score: 18, role: 'participant' }];

      await gameAudioManager.onScoreUpdate(scores1);
      await gameAudioManager.onScoreUpdate(scores2);

      expect(audioService.preloadWinningAudio).toHaveBeenCalledOnce();
    });

    it('should only check when scores increase', async () => {
      const scores1 = [{ score: 15, role: 'participant' }];
      const scores2 = [{ score: 12, role: 'participant' }]; // Lower score

      await gameAudioManager.onScoreUpdate(scores1);
      vi.clearAllMocks();
      
      await gameAudioManager.onScoreUpdate(scores2);

      expect(audioService.preloadWinningAudio).not.toHaveBeenCalled();
    });

    it('should handle pre-loading errors gracefully', async () => {
      const consoleSpy = vi.spyOn(console, 'warn').mockImplementation(() => {});
      (audioService.preloadWinningAudio as any).mockRejectedValue(new Error('Load failed'));

      const scores = [{ score: 15, role: 'participant' }];
      await gameAudioManager.onScoreUpdate(scores);

      expect(consoleSpy).toHaveBeenCalledWith(
        '[GameAudioManager] Failed to pre-load winning audio:',
        expect.any(Error)
      );
      consoleSpy.mockRestore();
    });
  });

  describe('playWinningSound', () => {
    it('should play winning sound for winners', async () => {
      const playerName = 'Player1';
      const scores = [
        { id: 'Player1', name: 'Player1', score: 20, role: 'participant' },
        { id: 'Player2', name: 'Player2', score: 15, role: 'participant' },
        { id: 'Judge', name: 'Judge', score: 0, role: 'judge' }
      ];

      await gameAudioManager.playWinningSound(playerName, scores);

      expect(audioService.playSound).toHaveBeenCalledWith('winning');
    });

    it('should not play winning sound for non-winners', async () => {
      const playerName = 'Player2';
      const scores = [
        { id: 'Player1', name: 'Player1', score: 20, role: 'participant' },
        { id: 'Player2', name: 'Player2', score: 15, role: 'participant' },
        { id: 'Judge', name: 'Judge', score: 0, role: 'judge' }
      ];

      await gameAudioManager.playWinningSound(playerName, scores);

      expect(audioService.playSound).not.toHaveBeenCalled();
    });

    it('should handle tied winners correctly', async () => {
      const playerName = 'Player2';
      const scores = [
        { id: 'Player1', name: 'Player1', score: 20, role: 'participant' },
        { id: 'Player2', name: 'Player2', score: 20, role: 'participant' }, // Tied winner
        { id: 'Judge', name: 'Judge', score: 0, role: 'judge' }
      ];

      await gameAudioManager.playWinningSound(playerName, scores);

      expect(audioService.playSound).toHaveBeenCalledWith('winning');
    });

    it('should work with name-based matching', async () => {
      const playerName = 'Player1';
      const scores = [
        { id: 'p1', name: 'Player1', score: 20, role: 'participant' },
        { id: 'p2', name: 'Player2', score: 15, role: 'participant' }
      ];

      await gameAudioManager.playWinningSound(playerName, scores);

      expect(audioService.playSound).toHaveBeenCalledWith('winning');
    });

    it('should handle empty participant scores', async () => {
      const playerName = 'Player1';
      const scores = [
        { id: 'Judge', name: 'Judge', score: 0, role: 'judge' }
      ];

      await gameAudioManager.playWinningSound(playerName, scores);

      expect(audioService.playSound).not.toHaveBeenCalled();
    });

    it('should handle play errors gracefully', async () => {
      const consoleSpy = vi.spyOn(console, 'warn').mockImplementation(() => {});
      (audioService.playSound as any).mockRejectedValue(new Error('Play failed'));

      const playerName = 'Player1';
      const scores = [{ id: 'Player1', name: 'Player1', score: 20, role: 'participant' }];

      await gameAudioManager.playWinningSound(playerName, scores);

      expect(consoleSpy).toHaveBeenCalledWith(
        '[GameAudioManager] Failed to play winning sound:',
        expect.any(Error)
      );
      consoleSpy.mockRestore();
    });
  });

  describe('playBuzzerSound', () => {
    it('should play buzzer sound', async () => {
      await gameAudioManager.playBuzzerSound();

      expect(audioService.playSound).toHaveBeenCalledWith('buzzer');
    });

    it('should handle play errors gracefully', async () => {
      const consoleSpy = vi.spyOn(console, 'warn').mockImplementation(() => {});
      (audioService.playSound as any).mockRejectedValue(new Error('Play failed'));

      await gameAudioManager.playBuzzerSound();

      expect(consoleSpy).toHaveBeenCalledWith(
        '[GameAudioManager] Failed to play buzzer sound:',
        expect.any(Error)
      );
      consoleSpy.mockRestore();
    });
  });

  describe('isAudioReady', () => {
    it('should check if buzzer audio is ready', () => {
      (audioService.isSoundReady as any).mockReturnValue(true);

      const result = gameAudioManager.isAudioReady('buzzer');

      expect(audioService.isSoundReady).toHaveBeenCalledWith('buzzer');
      expect(result).toBe(true);
    });

    it('should check if winning audio is ready', () => {
      (audioService.isSoundReady as any).mockReturnValue(false);

      const result = gameAudioManager.isAudioReady('winning');

      expect(audioService.isSoundReady).toHaveBeenCalledWith('winning');
      expect(result).toBe(false);
    });
  });

  describe('reset', () => {
    it('should reset all state to initial values', async () => {
      // Mock successful audio loading
      (audioService.preloadBuzzerAudio as any).mockResolvedValue(undefined);
      (audioService.preloadWinningAudio as any).mockResolvedValue(undefined);
      
      // Set some state first
      await gameAudioManager.onGameStart(1);
      await gameAudioManager.onScoreUpdate([{ score: 15, role: 'participant' }]);

      expect(gameAudioManager.getState().gameStarted).toBe(true);
      expect(gameAudioManager.getState().buzzerPreloaded).toBe(true);

      gameAudioManager.reset();

      const state = gameAudioManager.getState();
      expect(state.gameStarted).toBe(false);
      expect(state.buzzerPreloaded).toBe(false);
      expect(state.winningPreloaded).toBe(false);
      expect(state.lastScoreCheck).toBe(0);
    });
  });

  describe('updateConfig', () => {
    it('should update score threshold', async () => {
      gameAudioManager.updateConfig({ scoreThreshold: 10 });

      const scores = [{ score: 10, role: 'participant' }];
      await gameAudioManager.onScoreUpdate(scores);

      expect(audioService.preloadWinningAudio).toHaveBeenCalledOnce();
    });

    it('should update round trigger', async () => {
      // Reset the manager to clear any previous game state
      gameAudioManager.reset();
      gameAudioManager.updateConfig({ roundTrigger: 2 });

      await gameAudioManager.onGameStart(1);
      expect(audioService.preloadBuzzerAudio).not.toHaveBeenCalled();

      // Reset the manager again to allow a new game start
      gameAudioManager.reset();
      await gameAudioManager.onGameStart(2);
      expect(audioService.preloadBuzzerAudio).toHaveBeenCalledOnce();
    });
  });

  describe('getState', () => {
    it('should return readonly state copy', () => {
      const state = gameAudioManager.getState();
      
      expect(state).toEqual({
        buzzerPreloaded: false,
        winningPreloaded: false,
        lastScoreCheck: 0,
        gameStarted: false
      });

      // Should not be able to modify the returned state
      expect(() => {
        (state as any).gameStarted = true;
      }).not.toThrow(); // Assignment works but doesn't affect internal state
      
      expect(gameAudioManager.getState().gameStarted).toBe(false);
    });
  });
}); 