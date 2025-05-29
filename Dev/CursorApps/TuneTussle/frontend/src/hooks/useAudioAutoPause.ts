import { useEffect, useRef, useState, useCallback } from 'react';

interface UseAudioAutoPauseOptions {
  activePlayerName?: string | null;
  currentSongUrl?: string | null;
  enabled?: boolean;
}

interface UseAudioAutoPauseReturn {
  audioRef: React.RefObject<HTMLAudioElement | null>;
  isAutoPaused: boolean;
  resetAutoPause: () => void;
}

/**
 * Custom hook for handling audio auto-pause functionality
 * 
 * Automatically pauses audio when a player buzzes in and provides
 * state management for the auto-pause feature.
 * 
 * @param options Configuration options for auto-pause behavior
 * @returns Audio ref, auto-pause state, and control functions
 */
export function useAudioAutoPause({
  activePlayerName,
  currentSongUrl,
  enabled = true
}: UseAudioAutoPauseOptions): UseAudioAutoPauseReturn {
  const audioRef = useRef<HTMLAudioElement>(null);
  const [isAutoPaused, setIsAutoPaused] = useState(false);

  // Auto-pause audio when someone buzzes in
  useEffect(() => {
    if (!enabled || !audioRef.current) return;

    if (activePlayerName && !audioRef.current.paused) {
      console.log(`[useAudioAutoPause] Auto-pausing audio - ${activePlayerName} buzzed in`);
      audioRef.current.pause();
      setIsAutoPaused(true);
    } else if (!activePlayerName) {
      // Reset auto-pause state when no one is buzzed in
      setIsAutoPaused(false);
    }
  }, [activePlayerName, enabled]);

  // Reset auto-pause state when song changes
  useEffect(() => {
    setIsAutoPaused(false);
  }, [currentSongUrl]);

  // Manual reset function
  const resetAutoPause = useCallback(() => {
    setIsAutoPaused(false);
  }, []);

  // Cleanup on unmount
  useEffect(() => {
    const audioElement = audioRef.current;
    return () => {
      if (audioElement) {
        audioElement.pause();
      }
    };
  }, []);

  return {
    audioRef,
    isAutoPaused,
    resetAutoPause
  };
} 