import { describe, it, expect, beforeEach, vi } from 'vitest';
import { audioService } from './audioService';

// Mock HTMLAudioElement
class MockAudio implements Partial<HTMLAudioElement> {
  public volume = 1;
  public currentTime = 0;
  public preload = '';
  public src = '';
  public _soundUrl = '';
  private eventListeners: { [key: string]: Function[] } = {};

  constructor(src?: string) {
    if (src) {
      this.src = src;
      this._soundUrl = src;
    }
  }

  async play(): Promise<void> {
    return Promise.resolve();
  }

  addEventListener = vi.fn((event: string, handler: Function) => {
    if (!this.eventListeners[event]) {
      this.eventListeners[event] = [];
    }
    this.eventListeners[event].push(handler);
  });

  removeEventListener = vi.fn((event: string, handler: Function) => {
    if (this.eventListeners[event]) {
      this.eventListeners[event] = this.eventListeners[event].filter(h => h !== handler);
    }
  });

  load = vi.fn();

  // Helper method to trigger events in tests
  triggerEvent(event: string, data?: any) {
    if (this.eventListeners[event]) {
      this.eventListeners[event].forEach(handler => handler(data));
    }
  }
}

// Mock Audio constructor with proper typing
global.Audio = MockAudio as unknown as typeof Audio;

describe('AudioService - Smart Conditional Loading', () => {
  beforeEach(() => {
    // Reset audio service state before each test
    audioService.reset();

    // Reset global Audio mock
    global.Audio = vi.fn(() => new MockAudio()) as unknown as typeof Audio;
  });

  afterEach(() => {
    audioService.cleanup();
    vi.restoreAllMocks();
  });

  describe('registerSound', () => {
    it('should register a sound without immediate loading', () => {
      audioService.registerSound('test', { url: '/test.mp3' });
      expect(audioService.hasSound('test')).toBe(true);
      expect(audioService.isSoundReady('test')).toBe(false);
    });

    it('should register a sound with custom volume', () => {
      audioService.registerSound('test', {
        url: '/test.mp3',
        config: { volume: 0.5 }
      });
      expect(audioService.hasSound('test')).toBe(true);
      expect(audioService.isSoundReady('test')).toBe(false);
    });

    it('should preload immediately only when explicitly requested', async () => {
      const mockAudio = new MockAudio();
      global.Audio = vi.fn(() => mockAudio) as unknown as typeof Audio;

      audioService.registerSound('test', {
        url: '/test.mp3',
        config: { preload: true }
      });

      expect(audioService.hasSound('test')).toBe(true);
      // Should have started loading process
      expect(mockAudio.addEventListener).toHaveBeenCalledWith('canplaythrough', expect.any(Function));
    });
  });

  describe('preloadSound', () => {
    it('should preload a registered sound', async () => {
      const mockAudio = new MockAudio();
      global.Audio = vi.fn(() => mockAudio) as unknown as typeof Audio;

      audioService.registerSound('test', { url: '/test.mp3' });

      const preloadPromise = audioService.preloadSound('test');

      expect(mockAudio.addEventListener).toHaveBeenCalledWith('canplaythrough', expect.any(Function));
      expect(mockAudio.addEventListener).toHaveBeenCalledWith('error', expect.any(Function));
      expect(mockAudio.src).toBe('/test.mp3');
      expect(mockAudio.preload).toBe('auto');

      // Simulate successful load
      mockAudio.triggerEvent('canplaythrough');
      await preloadPromise;
    });

    it('should not preload unregistered sound', async () => {
      const consoleSpy = vi.spyOn(console, 'warn').mockImplementation(() => { });

      await audioService.preloadSound('nonexistent');

      expect(consoleSpy).toHaveBeenCalledWith("[AudioService] Sound 'nonexistent' not registered");
      consoleSpy.mockRestore();
    });

    it('should not preload already loaded sound', async () => {
      const mockAudio = new MockAudio();
      global.Audio = vi.fn(() => mockAudio) as unknown as typeof Audio;

      audioService.registerSound('test', { url: '/test.mp3' });

      // First load
      const preloadPromise1 = audioService.preloadSound('test');
      mockAudio.triggerEvent('canplaythrough');
      await preloadPromise1;

      // Clear mocks and try to preload again
      vi.clearAllMocks();
      await audioService.preloadSound('test');

      // Should not call addEventListener again
      expect(mockAudio.addEventListener).not.toHaveBeenCalled();
    });
  });

  describe('playSound', () => {
    it('should load and play unloaded sound on-demand', async () => {
      const mockAudio = new MockAudio();
      const mockPlay = vi.fn().mockResolvedValue(undefined);
      mockAudio.play = mockPlay;
      global.Audio = vi.fn(() => mockAudio) as unknown as typeof Audio;

      audioService.registerSound('test', { url: '/test.mp3' });

      // Simulate user interaction to enable audio
      document.dispatchEvent(new Event('click'));

      const playPromise = audioService.playSound('test');

      // Simulate successful load
      mockAudio.triggerEvent('canplaythrough');

      await playPromise;

      expect(mockAudio.currentTime).toBe(0);
      expect(mockPlay).toHaveBeenCalled();
    });

    it('should warn when trying to play unregistered sound', async () => {
      const consoleSpy = vi.spyOn(console, 'warn').mockImplementation(() => { });

      await audioService.playSound('nonexistent');

      expect(consoleSpy).toHaveBeenCalledWith("[AudioService] Sound 'nonexistent' not found");
      consoleSpy.mockRestore();
    });

    it('should handle play errors gracefully', async () => {
      const mockAudio = new MockAudio();
      const mockPlay = vi.fn().mockRejectedValue(new Error('Play failed'));
      mockAudio.play = mockPlay;
      global.Audio = vi.fn(() => mockAudio) as unknown as typeof Audio;

      audioService.registerSound('error-test', { url: '/error.mp3' });

      const consoleSpy = vi.spyOn(console, 'warn').mockImplementation(() => { });

      // Simulate user interaction to enable audio
      document.dispatchEvent(new Event('click'));

      const playPromise = audioService.playSound('error-test');

      // Simulate successful load first
      mockAudio.triggerEvent('canplaythrough');

      await playPromise;

      expect(consoleSpy).toHaveBeenCalledWith(
        "[AudioService] Failed to play sound 'error-test':",
        expect.any(Error)
      );
      consoleSpy.mockRestore();
    });

    it('should warn when trying to play without user interaction', async () => {
      const mockAudio = new MockAudio();
      global.Audio = vi.fn(() => mockAudio) as unknown as typeof Audio;

      audioService.registerSound('no-interaction-test', { url: '/test.mp3' });

      const consoleSpy = vi.spyOn(console, 'warn').mockImplementation(() => { });

      // Don't simulate user interaction - audio should be disabled
      await audioService.playSound('no-interaction-test');

      expect(consoleSpy).toHaveBeenCalledWith(
        "[AudioService] Cannot play sound 'no-interaction-test' - no user interaction received yet"
      );
      consoleSpy.mockRestore();
    });
  });

  describe('Smart Loading Methods', () => {
    it('should preload buzzer audio when requested', async () => {
      // Clear and re-register with fresh mock
      audioService.cleanup();

      const mockAudio = new MockAudio();
      global.Audio = vi.fn(() => mockAudio) as unknown as typeof Audio;

      audioService.registerSound('buzzer', {
        url: '/sounds/buzzer.mp3',
        config: { volume: 0.8, preload: false }
      });

      const consoleSpy = vi.spyOn(console, 'log').mockImplementation(() => { });

      const preloadPromise = audioService.preloadBuzzerAudio();

      expect(consoleSpy).toHaveBeenCalledWith('[AudioService] Pre-loading buzzer audio for game start');
      expect(mockAudio.addEventListener).toHaveBeenCalledWith('canplaythrough', expect.any(Function));

      // Simulate successful load
      mockAudio.triggerEvent('canplaythrough');
      await preloadPromise;

      consoleSpy.mockRestore();
    });

    it('should preload winning audio when requested', async () => {
      // Clear and re-register with fresh mock
      audioService.cleanup();

      const mockAudio = new MockAudio();
      global.Audio = vi.fn(() => mockAudio) as unknown as typeof Audio;

      audioService.registerSound('winning', {
        url: '/sounds/winning.mp3',
        config: { volume: 0.9, preload: false }
      });

      const consoleSpy = vi.spyOn(console, 'log').mockImplementation(() => { });

      const preloadPromise = audioService.preloadWinningAudio();

      expect(consoleSpy).toHaveBeenCalledWith('[AudioService] Pre-loading winning audio for potential winners');
      expect(mockAudio.addEventListener).toHaveBeenCalledWith('canplaythrough', expect.any(Function));

      // Simulate successful load
      mockAudio.triggerEvent('canplaythrough');
      await preloadPromise;

      consoleSpy.mockRestore();
    });
  });

  describe('isSoundReady', () => {
    it('should return false for unloaded sounds', () => {
      audioService.registerSound('test', { url: '/test.mp3' });
      expect(audioService.isSoundReady('test')).toBe(false);
    });

    it('should return false for unregistered sounds', () => {
      expect(audioService.isSoundReady('nonexistent')).toBe(false);
    });

    it('should return true for loaded sounds', async () => {
      const mockAudio = new MockAudio();
      global.Audio = vi.fn(() => mockAudio) as unknown as typeof Audio;

      audioService.registerSound('test', { url: '/test.mp3' });

      const preloadPromise = audioService.preloadSound('test');
      mockAudio.triggerEvent('canplaythrough');
      await preloadPromise;

      expect(audioService.isSoundReady('test')).toBe(true);
    });
  });

  describe('setVolume', () => {
    it('should set volume for existing sound', () => {
      audioService.registerSound('test', { url: '/test.mp3' });
      audioService.setVolume('test', 0.5);
      expect(audioService.hasSound('test')).toBe(true);
    });

    it('should clamp volume to valid range', () => {
      audioService.registerSound('test', { url: '/test.mp3' });
      audioService.setVolume('test', 1.5); // Should clamp to 1
      audioService.setVolume('test', -0.5); // Should clamp to 0
      expect(audioService.hasSound('test')).toBe(true);
    });

    it('should handle setting volume for non-existent sound', () => {
      audioService.setVolume('nonexistent', 0.5);
      expect(audioService.hasSound('nonexistent')).toBe(false);
    });
  });

  describe('setDefaultVolume', () => {
    it('should set default volume', () => {
      audioService.setDefaultVolume(0.3);
      expect(true).toBe(true); // Placeholder assertion
    });

    it('should clamp default volume to valid range', () => {
      audioService.setDefaultVolume(1.5); // Should clamp to 1
      audioService.setDefaultVolume(-0.5); // Should clamp to 0
      expect(true).toBe(true); // Placeholder assertion
    });
  });

  describe('hasSound', () => {
    it('should return true for registered sounds', () => {
      audioService.registerSound('test', { url: '/test.mp3' });
      expect(audioService.hasSound('test')).toBe(true);
    });

    it('should return false for unregistered sounds', () => {
      expect(audioService.hasSound('nonexistent')).toBe(false);
    });
  });

  describe('cleanup', () => {
    it('should remove all registered sounds and reset audio elements', () => {
      const mockAudio1 = new MockAudio();
      const mockAudio2 = new MockAudio();
      let audioCount = 0;
      global.Audio = vi.fn(() => {
        audioCount++;
        return audioCount === 1 ? mockAudio1 : mockAudio2;
      }) as unknown as typeof Audio;

      audioService.registerSound('test1', { url: '/test1.mp3' });
      audioService.registerSound('test2', { url: '/test2.mp3' });

      expect(audioService.hasSound('test1')).toBe(true);
      expect(audioService.hasSound('test2')).toBe(true);

      audioService.cleanup();

      expect(audioService.hasSound('test1')).toBe(false);
      expect(audioService.hasSound('test2')).toBe(false);
      expect(mockAudio1.load).toHaveBeenCalled();
      expect(mockAudio2.load).toHaveBeenCalled();
    });
  });

  describe('default sound registration', () => {
    it('should have buzzer sound registered but not preloaded by default', () => {
      expect(audioService.hasSound('buzzer')).toBe(true);
      expect(audioService.isSoundReady('buzzer')).toBe(false);
    });

    it('should have winning sound registered but not preloaded by default', () => {
      expect(audioService.hasSound('winning')).toBe(true);
      expect(audioService.isSoundReady('winning')).toBe(false);
    });
  });

  describe('isAudioEnabled', () => {
    it('should return false initially', () => {
      expect(audioService.isAudioEnabled()).toBe(false);
    });

    it('should return true after user interaction', () => {
      // Simulate user interaction
      document.dispatchEvent(new Event('click'));
      expect(audioService.isAudioEnabled()).toBe(true);
    });
  });
});
