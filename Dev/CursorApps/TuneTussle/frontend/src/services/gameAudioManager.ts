/**
 * Game Audio Manager - Centralized audio management for TuneTussle
 * 
 * Handles all game-related audio loading, playing, and state management.
 * Eliminates duplication and provides a clean interface for audio operations.
 */

import { audioService } from './audioService';

interface AudioTriggerConfig {
  scoreThreshold: number;
  roundTrigger: number;
}

interface GameAudioState {
  buzzerPreloaded: boolean;
  winningPreloaded: boolean;
  lastScoreCheck: number;
  gameStarted: boolean;
}

class GameAudioManager {
  private state: GameAudioState = {
    buzzerPreloaded: false,
    winningPreloaded: false,
    lastScoreCheck: 0,
    gameStarted: false
  };

  private config: AudioTriggerConfig = {
    scoreThreshold: 15, // Pre-load winning audio when any player reaches this score
    roundTrigger: 1     // Pre-load buzzer audio when this round starts
  };

  /**
   * Handle game start - pre-load buzzer audio
   */
  async onGameStart(currentRound: number): Promise<void> {
    if (this.state.gameStarted) return;
    
    this.state.gameStarted = true;
    
    if (currentRound >= this.config.roundTrigger && !this.state.buzzerPreloaded) {
      try {
        console.log('[GameAudioManager] Pre-loading buzzer audio for game start');
        await audioService.preloadBuzzerAudio();
        this.state.buzzerPreloaded = true;
      } catch (error) {
        console.warn('[GameAudioManager] Failed to pre-load buzzer audio:', error);
      }
    }
  }

  /**
   * Handle score updates - pre-load winning audio when players are close to winning
   */
  async onScoreUpdate(scores: Array<{ score: number; role: string }>): Promise<void> {
    // Filter out judges and get max score
    const participantScores = scores.filter(player => player.role !== 'judge');
    const maxScore = Math.max(...participantScores.map(p => p.score), 0);
    
    // Only check if score has increased (avoid redundant checks)
    if (maxScore <= this.state.lastScoreCheck) return;
    
    this.state.lastScoreCheck = maxScore;
    
    // Pre-load winning audio if threshold reached and not already loaded
    if (maxScore >= this.config.scoreThreshold && !this.state.winningPreloaded) {
      try {
        console.log(`[GameAudioManager] Pre-loading winning audio (max score: ${maxScore})`);
        await audioService.preloadWinningAudio();
        this.state.winningPreloaded = true;
      } catch (error) {
        console.warn('[GameAudioManager] Failed to pre-load winning audio:', error);
      }
    }
  }

  /**
   * Play winning sound for game winners
   */
  async playWinningSound(playerName: string, scores: Array<{ id: string; name: string; score: number; role: string }>): Promise<void> {
    // Filter out judges for winner calculation
    const participantScores = scores.filter(player => player.role !== 'judge');
    
    if (participantScores.length === 0) return;
    
    const maxScore = Math.max(...participantScores.map(p => p.score));
    const winners = participantScores.filter(p => p.score === maxScore);
    
    // Check if current player is a winner
    const isCurrentPlayerWinner = winners.some(winner => winner.id === playerName || winner.name === playerName);
    
    if (isCurrentPlayerWinner) {
      try {
        console.log(`[GameAudioManager] Playing winning sound for ${playerName}`);
        await audioService.playSound('winning');
      } catch (error) {
        console.warn('[GameAudioManager] Failed to play winning sound:', error);
      }
    }
  }

  /**
   * Play buzzer sound
   */
  async playBuzzerSound(): Promise<void> {
    try {
      await audioService.playSound('buzzer');
    } catch (error) {
      console.warn('[GameAudioManager] Failed to play buzzer sound:', error);
    }
  }

  /**
   * Check if audio is ready for immediate playback
   */
  isAudioReady(soundName: 'buzzer' | 'winning'): boolean {
    return audioService.isSoundReady(soundName);
  }

  /**
   * Reset audio manager state (for new games)
   */
  reset(): void {
    this.state = {
      buzzerPreloaded: false,
      winningPreloaded: false,
      lastScoreCheck: 0,
      gameStarted: false
    };
  }

  /**
   * Get current audio state for debugging
   */
  getState(): Readonly<GameAudioState> {
    return { ...this.state };
  }

  /**
   * Update configuration
   */
  updateConfig(newConfig: Partial<AudioTriggerConfig>): void {
    this.config = { ...this.config, ...newConfig };
  }
}

// Export singleton instance
export const gameAudioManager = new GameAudioManager(); 