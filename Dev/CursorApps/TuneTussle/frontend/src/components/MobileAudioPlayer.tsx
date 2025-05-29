import React, { useState, useEffect, useCallback, useMemo } from 'react';

interface MobileAudioPlayerProps {
  audioRef: React.RefObject<HTMLAudioElement | null>;
  currentSongUrl: string;
  isAutoPaused: boolean;
  activePlayerName?: string | null;
  resetAutoPause: () => void;
  onLoadStart?: () => void;
  onCanPlay?: () => void;
  onError?: (error: React.SyntheticEvent<HTMLAudioElement, Event>) => void;
  onStalled?: () => void;
  providerName: 'Spotify' | 'Deezer';
}

// Memoized SVG Icons to prevent re-creation
const PlayIcon = React.memo<{ size?: number; className?: string }>(({
  size = 24,
  className = ''
}) => (
  <svg
    width={size}
    height={size}
    viewBox="0 0 24 24"
    fill="currentColor"
    className={className}
  >
    <path d="M8 5v14l11-7z" />
  </svg>
));

const PauseIcon = React.memo<{ size?: number; className?: string }>(({
  size = 24,
  className = ''
}) => (
  <svg
    width={size}
    height={size}
    viewBox="0 0 24 24"
    fill="currentColor"
    className={className}
  >
    <path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z" />
  </svg>
));

const BufferingIcon = React.memo<{ size?: number; className?: string }>(({
  size = 24,
  className = ''
}) => (
  <svg
    width={size}
    height={size}
    viewBox="0 0 24 24"
    fill="currentColor"
    className={`animate-spin ${className}`}
  >
    <path d="M12,4V2A10,10 0 0,0 2,12H4A8,8 0 0,1 12,4Z" />
  </svg>
));

// Debounce utility for time updates
const useDebounce = (value: number, delay: number) => {
  const [debouncedValue, setDebouncedValue] = useState(value);

  useEffect(() => {
    const handler = setTimeout(() => {
      setDebouncedValue(value);
    }, delay);

    return () => {
      clearTimeout(handler);
    };
  }, [value, delay]);

  return debouncedValue;
};

const MobileAudioPlayer: React.FC<MobileAudioPlayerProps> = ({
  audioRef,
  currentSongUrl,
  isAutoPaused,
  activePlayerName,
  resetAutoPause,
  onLoadStart,
  onCanPlay,
  onError,
  onStalled,
  providerName
}) => {
  const [isPlaying, setIsPlaying] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [duration, setDuration] = useState(0);
  const [currentTime, setCurrentTime] = useState(0);

  // Debounce currentTime updates to reduce re-renders (update every 200ms instead of ~16ms)
  const debouncedCurrentTime = useDebounce(currentTime, 200);

  // Memoize style classes to prevent recreation
  const styleClasses = useMemo(() => ({
    bgColor: providerName === 'Spotify' ? 'bg-green-50' : 'bg-purple-50',
    borderColor: providerName === 'Spotify' ? 'border-green-200' : 'border-purple-200',
    buttonColor: providerName === 'Spotify'
      ? 'bg-green-600 hover:bg-green-700 focus:ring-green-500'
      : 'bg-purple-600 hover:bg-purple-700 focus:ring-purple-500',
    progressColor: providerName === 'Spotify' ? 'bg-green-600' : 'bg-purple-600'
  }), [providerName]);

  // Track audio element events with proper cleanup
  useEffect(() => {
    const audio = audioRef.current;
    if (!audio) return;

    const handlePlay = () => setIsPlaying(true);
    const handlePause = () => setIsPlaying(false);
    const handleLoadStart = () => {
      setIsLoading(true);
      onLoadStart?.();
    };
    const handleCanPlay = () => {
      setIsLoading(false);
      onCanPlay?.();
    };
    const handleLoadedMetadata = () => setDuration(audio.duration || 0);

    // Throttled time update to prevent excessive re-renders
    let timeUpdateThrottle: number | null = null;
    const handleTimeUpdate = () => {
      if (timeUpdateThrottle) return;
      timeUpdateThrottle = window.setTimeout(() => {
        setCurrentTime(audio.currentTime || 0);
        timeUpdateThrottle = null;
      }, 100); // Update every 100ms instead of every frame
    };

    const handleError = (e: Event) => {
      if (onError) {
        // Create a proper SyntheticEvent from the native Event
        const syntheticEvent = {
          ...e,
          currentTarget: audio,
          target: audio,
          nativeEvent: e,
          bubbles: e.bubbles,
          cancelable: e.cancelable,
          defaultPrevented: e.defaultPrevented,
          eventPhase: e.eventPhase,
          isTrusted: e.isTrusted,
          timeStamp: e.timeStamp,
          type: e.type,
          preventDefault: () => e.preventDefault(),
          stopPropagation: () => e.stopPropagation(),
          persist: () => { },
          isDefaultPrevented: () => e.defaultPrevented,
          isPropagationStopped: () => false
        } as React.SyntheticEvent<HTMLAudioElement, Event>;
        onError(syntheticEvent);
      }
    };
    const handleStalled = () => onStalled?.();

    // Add all listeners
    audio.addEventListener('play', handlePlay);
    audio.addEventListener('pause', handlePause);
    audio.addEventListener('loadstart', handleLoadStart);
    audio.addEventListener('canplay', handleCanPlay);
    audio.addEventListener('loadedmetadata', handleLoadedMetadata);
    audio.addEventListener('timeupdate', handleTimeUpdate);
    audio.addEventListener('error', handleError);
    audio.addEventListener('stalled', handleStalled);

    return () => {
      // Cleanup all listeners and throttle
      if (timeUpdateThrottle) {
        clearTimeout(timeUpdateThrottle);
      }
      audio.removeEventListener('play', handlePlay);
      audio.removeEventListener('pause', handlePause);
      audio.removeEventListener('loadstart', handleLoadStart);
      audio.removeEventListener('canplay', handleCanPlay);
      audio.removeEventListener('loadedmetadata', handleLoadedMetadata);
      audio.removeEventListener('timeupdate', handleTimeUpdate);
      audio.removeEventListener('error', handleError);
      audio.removeEventListener('stalled', handleStalled);
    };
  }, [audioRef, onLoadStart, onCanPlay, onError, onStalled]);

  // Reset states when song changes
  useEffect(() => {
    setIsPlaying(false);
    setIsLoading(false);
    setCurrentTime(0);
    setDuration(0);
  }, [currentSongUrl]);

  const togglePlayPause = useCallback(() => {
    if (!audioRef.current) return;

    if (audioRef.current.paused) {
      audioRef.current.play()
        .then(() => {
          resetAutoPause(); // Reset auto-pause state when user manually plays
        })
        .catch((error) => {
          console.error('Failed to play audio:', error);
        });
    } else {
      audioRef.current.pause();
    }
  }, [audioRef, resetAutoPause]);

  const formatTime = useCallback((seconds: number): string => {
    if (!isFinite(seconds)) return '0:00';
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, '0')}`;
  }, []);

  return (
    <div className={`p-4 ${styleClasses.bgColor} border ${styleClasses.borderColor} rounded-lg`}>
      {/* Auto-pause notification */}
      {isAutoPaused && activePlayerName && (
        <div className="mb-3 p-2 bg-yellow-100 border border-yellow-300 rounded text-center">
          <p className="text-sm text-yellow-800">
            ⏸️ Audio auto-paused - <strong>{activePlayerName}</strong> buzzed in
          </p>
        </div>
      )}

      {/* Status */}
      <div className="flex items-center justify-end mb-4">
        <div className="text-xs text-gray-500">
          {formatTime(debouncedCurrentTime)} / {formatTime(duration)}
        </div>
      </div>

      {/* Main play/pause controls */}
      <div className="flex items-center justify-center space-x-4">
        <button
          onClick={togglePlayPause}
          disabled={isLoading}
          className={`w-16 h-16 rounded-full ${styleClasses.buttonColor} text-white
            shadow-lg transition-all duration-200 transform hover:scale-105
            active:scale-95 focus:outline-none focus:ring-4 focus:ring-opacity-50
            disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none
            flex items-center justify-center`}
          aria-label={isPlaying ? 'Pause audio' : 'Play audio'}
        >
          {isLoading ? (
            <BufferingIcon size={24} className="text-white" />
          ) : isPlaying ? (
            <PauseIcon size={24} />
          ) : (
            <PlayIcon size={24} />
          )}
        </button>
      </div>

      {/* Progress bar */}
      {duration > 0 && (
        <div className="mt-4">
          <div className="w-full bg-gray-200 rounded-full h-1">
            <div
              className={`h-1 rounded-full transition-all duration-100 ${styleClasses.progressColor}`}
              style={{ width: `${(debouncedCurrentTime / duration) * 100}%` }}
            />
          </div>
        </div>
      )}

      {/* Hidden audio element for screen readers */}
      <div className="sr-only">
        Audio player for {providerName}.
        {isPlaying ? 'Playing' : 'Paused'}.
        Current time: {formatTime(debouncedCurrentTime)} of {formatTime(duration)}.
      </div>
    </div>
  );
};

export default MobileAudioPlayer;
