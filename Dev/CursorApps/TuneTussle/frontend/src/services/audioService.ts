/**
 * Smart Audio Service for TuneTussle
 *
 * Implements conditional loading strategy:
 * - Buzzer audio: Pre-loaded when judge starts the game
 * - Winning audio: Pre-loaded when any player reaches 15+ points (close to winning)
 * - On-demand fallback: Load audio immediately if needed but not pre-loaded
 */

interface AudioConfig {
  volume?: number;
  preload?: boolean;
}

interface SoundDefinition {
  url: string;
  config?: AudioConfig;
}

interface LoadingState {
  isLoading: boolean;
  isLoaded: boolean;
  loadPromise?: Promise<void>;
}

// Extend HTMLAudioElement with our custom property
interface ExtendedHTMLAudioElement extends HTMLAudioElement {
  _soundUrl?: string;
}

class AudioService {
  private sounds: Map<string, ExtendedHTMLAudioElement> = new Map();
  private loadingStates: Map<string, LoadingState> = new Map();
  private defaultVolume = 0.7;
  private audioEnabled = false;
  private userInteractionReceived = false;

  constructor() {
    // Add event listeners for user interaction to enable audio
    this.initializeAudioOnUserInteraction();
  }

  /**
   * Initialize audio playback after first user interaction
   */
  private initializeAudioOnUserInteraction(): void {
    const enableAudio = () => {
      if (this.userInteractionReceived) return;

      this.userInteractionReceived = true;
      this.audioEnabled = true;
      console.log('[AudioService] Audio enabled after user interaction');

      // Remove event listeners after first interaction
      document.removeEventListener('click', enableAudio);
      document.removeEventListener('keydown', enableAudio);
      document.removeEventListener('touchstart', enableAudio);
    };

    // Listen for various user interaction events
    document.addEventListener('click', enableAudio, { once: true });
    document.addEventListener('keydown', enableAudio, { once: true });
    document.addEventListener('touchstart', enableAudio, { once: true });
  }

  /**
   * Register a sound for later playback (without immediate loading)
   */
  registerSound(name: string, definition: SoundDefinition): void {
    const audio = new Audio() as ExtendedHTMLAudioElement;
    audio.volume = definition.config?.volume ?? this.defaultVolume;

    this.sounds.set(name, audio);
    this.loadingStates.set(name, { isLoading: false, isLoaded: false });

    // Store the URL for later loading
    audio._soundUrl = definition.url;

    // Only preload if explicitly requested (for backwards compatibility)
    if (definition.config?.preload) {
      this.preloadSound(name);
    }
  }

  /**
   * Pre-load a sound file
   */
  async preloadSound(name: string): Promise<void> {
    const audio = this.sounds.get(name);
    const loadingState = this.loadingStates.get(name);

    if (!audio || !loadingState) {
      console.warn(`[AudioService] Sound '${name}' not registered`);
      return;
    }

    if (loadingState.isLoaded || loadingState.isLoading) {
      return loadingState.loadPromise || Promise.resolve();
    }

    loadingState.isLoading = true;
    loadingState.loadPromise = new Promise((resolve, reject) => {
      const handleLoad = () => {
        loadingState.isLoading = false;
        loadingState.isLoaded = true;
        audio.removeEventListener('canplaythrough', handleLoad);
        audio.removeEventListener('error', handleError);
        console.log(`[AudioService] Pre-loaded sound '${name}'`);
        resolve();
      };

      const handleError = (error: Event | Error) => {
        loadingState.isLoading = false;
        audio.removeEventListener('canplaythrough', handleLoad);
        audio.removeEventListener('error', handleError);
        console.warn(`[AudioService] Failed to pre-load sound '${name}':`, error);
        reject(error);
      };

      audio.addEventListener('canplaythrough', handleLoad);
      audio.addEventListener('error', handleError);

      // Set the source to start loading
      audio.src = audio._soundUrl || '';
      audio.preload = 'auto';
    });

    return loadingState.loadPromise;
  }

  /**
   * Load a sound on-demand if not already loaded
   */
  private async ensureSoundLoaded(name: string): Promise<void> {
    const loadingState = this.loadingStates.get(name);

    if (!loadingState) {
      console.warn(`[AudioService] Sound '${name}' not registered`);
      return;
    }

    if (loadingState.isLoaded) {
      return;
    }

    if (loadingState.isLoading && loadingState.loadPromise) {
      return loadingState.loadPromise;
    }

    // Load on-demand
    console.log(`[AudioService] Loading sound '${name}' on-demand`);
    return this.preloadSound(name);
  }

  /**
   * Play a registered sound (with on-demand loading fallback)
   */
  async playSound(name: string): Promise<void> {
    const audio = this.sounds.get(name);
    if (!audio) {
      console.warn(`[AudioService] Sound '${name}' not found`);
      return;
    }

    // Check if audio is enabled (user interaction received)
    if (!this.audioEnabled) {
      console.warn(`[AudioService] Cannot play sound '${name}' - no user interaction received yet`);
      return;
    }

    try {
      // Ensure the sound is loaded before playing
      await this.ensureSoundLoaded(name);

      // Reset to beginning in case it was played before
      audio.currentTime = 0;
      await audio.play();
    } catch (error) {
      console.warn(`[AudioService] Failed to play sound '${name}':`, error);
    }
  }

  /**
   * Pre-load buzzer audio when game starts
   */
  async preloadBuzzerAudio(): Promise<void> {
    console.log('[AudioService] Pre-loading buzzer audio for game start');
    return this.preloadSound('buzzer');
  }

  /**
   * Pre-load winning audio when players are close to winning
   */
  async preloadWinningAudio(): Promise<void> {
    console.log('[AudioService] Pre-loading winning audio for potential winners');
    return this.preloadSound('winning');
  }

  /**
   * Check if audio is enabled (user interaction received)
   */
  isAudioEnabled(): boolean {
    return this.audioEnabled;
  }

  /**
   * Check if a sound is loaded and ready to play
   */
  isSoundReady(name: string): boolean {
    const loadingState = this.loadingStates.get(name);
    return loadingState?.isLoaded || false;
  }

  /**
   * Set volume for a specific sound
   */
  setVolume(name: string, volume: number): void {
    const audio = this.sounds.get(name);
    if (audio) {
      audio.volume = Math.max(0, Math.min(1, volume));
    }
  }

  /**
   * Set default volume for new sounds
   */
  setDefaultVolume(volume: number): void {
    this.defaultVolume = Math.max(0, Math.min(1, volume));
  }

  /**
   * Check if a sound is registered
   */
  hasSound(name: string): boolean {
    return this.sounds.has(name);
  }

  /**
   * Reset audio manager state (for new games)
   */
  reset(): void {
    this.audioEnabled = false;
    this.userInteractionReceived = false;
    this.cleanup();
    // Re-initialize user interaction listeners
    this.initializeAudioOnUserInteraction();

    // Re-register default sounds
    this.registerSound('buzzer', {
      url: '/sounds/buzzer.mp3',
      config: {
        volume: 0.8,
        preload: false
      }
    });

    this.registerSound('winning', {
      url: '/sounds/winning.mp3',
      config: {
        volume: 0.9,
        preload: false
      }
    });
  }

  /**
   * Cleanup - remove all sounds and cancel loading
   */
  cleanup(): void {
    this.sounds.forEach(audio => {
      audio.src = '';
      audio.load(); // Reset the audio element
    });
    this.sounds.clear();
    this.loadingStates.clear();
  }
}

// Create singleton instance
export const audioService = new AudioService();

// Register game sounds (without immediate loading)
audioService.registerSound('buzzer', {
  url: '/sounds/buzzer.mp3',
  config: {
    volume: 0.8,
    preload: false // Changed: Don't pre-load immediately
  }
});

audioService.registerSound('winning', {
  url: '/sounds/winning.mp3',
  config: {
    volume: 0.9,
    preload: false // Changed: Don't pre-load immediately
  }
});

export default audioService;
