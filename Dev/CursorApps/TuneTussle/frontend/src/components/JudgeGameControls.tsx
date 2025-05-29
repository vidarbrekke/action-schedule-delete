import React, { useEffect, useCallback } from 'react';
import { useAudioAutoPause } from '../hooks/useAudioAutoPause';
import MobileAudioPlayer from './MobileAudioPlayer';
import AlbumArt from './AlbumArt';

interface JudgeGameControlsProps {
  onNextRound: () => void;
  onEndRound?: () => void;
  currentRound: number;
  totalRounds: number;
  correctAnswer?: string;
  currentSongAudioUrl?: string | null;
  isRoundComplete: boolean;
  canAdvance?: boolean;
  isGameOver?: boolean;
  activePlayerName?: string | null;
  currentSongTitle?: string;
  currentArtist?: string;
  artworkUrl?: string | null;
  category?: string;
}

// Utility to validate YouTube embed URLs
function isValidYouTubeEmbedUrl(url: string): boolean {
  const embedPattern = /^https:\/\/www\.youtube\.com\/embed\/[a-zA-Z0-9_-]{11}$/;
  return embedPattern.test(url);
}

// Utility to detect Spotify preview URLs
function isSpotifyPreviewUrl(url: string): boolean {
  return url.includes('p.scdn.co') || url.includes('open.spotify.com');
}

// Utility to detect Deezer preview URLs
function isDeezerPreviewUrl(url: string): boolean {
  return url.includes('deezer.com') || url.includes('dzcdn.net') || url.includes('cdnt-preview');
}

// Utility to get media type from URL
function getMediaType(url: string): 'youtube' | 'spotify' | 'deezer' | 'unknown' {
  if (isValidYouTubeEmbedUrl(url)) return 'youtube';
  if (isSpotifyPreviewUrl(url)) return 'spotify';
  if (isDeezerPreviewUrl(url)) return 'deezer';
  return 'unknown';
}

// Utility to detect mobile devices
function isMobileDevice() {
  return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
}

export const JudgeGameControls: React.FC<JudgeGameControlsProps> = ({
  onNextRound,
  onEndRound,
  currentRound,
  totalRounds,
  correctAnswer = 'Not provided',
  currentSongAudioUrl = null,
  isRoundComplete,
  canAdvance = false,
  isGameOver = false,
  activePlayerName,
  currentSongTitle,
  currentArtist,
  artworkUrl,
  category,
}) => {
  // Use the custom auto-pause hook
  const { audioRef, isAutoPaused, resetAutoPause } = useAudioAutoPause({
    activePlayerName,
    currentSongUrl: currentSongAudioUrl,
    enabled: true
  });

  // Simple audio event handlers for logging
  const handleAudioLoadStart = useCallback(() => {
    console.log('[JudgeGameControls] Audio loading started');
  }, []);

  const handleAudioCanPlay = useCallback(() => {
    console.log('[JudgeGameControls] Audio can play');
  }, []);

  const handleAudioError = useCallback((error: React.SyntheticEvent<HTMLAudioElement, Event>) => {
    console.warn('[JudgeGameControls] Audio error:', error);
  }, []);

  const handleAudioStalled = useCallback(() => {
    console.warn('[JudgeGameControls] Audio stalled - network may be slow');
  }, []);

  // Log URL changes for debugging
  useEffect(() => {
    console.log('[JudgeGameControls] currentSongAudioUrl changed:', currentSongAudioUrl);
  }, [currentSongAudioUrl]);

  // Debug: Log on every render when currentSongAudioUrl changes
  useEffect(() => {
    console.log('[JudgeGameControls] Render', { currentSongAudioUrl });
  }, [currentSongAudioUrl]);

  // Track when currentSongAudioUrl changes
  useEffect(() => {
    console.log('[JudgeGameControls] currentSongAudioUrl changed:', { 
      currentSongAudioUrl, 
      currentRound, 
      correctAnswer,
      timestamp: new Date().toISOString()
    });
  }, [currentSongAudioUrl, currentRound, correctAnswer]);

  const isLastRound = currentRound === totalRounds;

  return (
    <div className="w-full space-y-4">
      {/* Hidden audio element for actual playback */}
      {currentSongAudioUrl && (
        <audio
          ref={audioRef}
          src={currentSongAudioUrl}
          onLoadStart={handleAudioLoadStart}
          onCanPlay={handleAudioCanPlay}
          onError={handleAudioError}
          onStalled={handleAudioStalled}
          preload="auto"
          style={{ display: 'none' }}
          aria-label="Game music player"
        />
      )}
      
      {/* New integrated song info + audio player card */}
      <div className="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg p-4 border border-gray-200">
        {/* Category and Round info */}
        <div className="flex justify-between items-center mb-3">
          {category && (
            <span className="text-sm font-medium text-indigo-600">
              Category: {category}
            </span>
          )}
          <span className="text-xs bg-indigo-100 text-indigo-700 px-2 py-1 rounded-full font-semibold">
            Round {currentRound} of {totalRounds}
          </span>
        </div>
        
        {/* Song info + Audio player in horizontal layout */}
        <div className="flex items-center space-x-4">
          {/* Album artwork */}
          {artworkUrl && (
            <div className="flex-shrink-0">
              <AlbumArt artworkUrl={artworkUrl} alt="Album artwork" size="lg" />
            </div>
          )}
          
          {/* Song title and artist */}
          <div className="flex-1 min-w-0">
            {currentSongTitle && currentArtist ? (
              <>
                <h4 className="text-lg font-bold text-gray-900 truncate">
                  {currentSongTitle}
                </h4>
                <p className="text-sm text-gray-600 truncate">
                  by {currentArtist}
                </p>
              </>
            ) : (
              <div className="text-gray-500">
                <p className="text-lg font-medium">Song information not available</p>
              </div>
            )}
          </div>
          
          {/* Audio player controls */}
          <div className="flex-shrink-0">
            {currentSongAudioUrl ? (
              (() => {
                const mediaType = getMediaType(currentSongAudioUrl);
                
                if (mediaType === 'youtube') {
                  return (
                    <div className="text-center">
                      {isMobileDevice() ? (
                        <div className="flex flex-col items-center p-3 bg-black rounded-lg text-white">
                          <p className="text-xs mb-2">YouTube on mobile</p>
                          {(() => {
                            const match = currentSongAudioUrl.match(/^https:\/\/www\.youtube\.com\/embed\/([a-zA-Z0-9_-]{11})$/);
                            const videoId = match ? match[1] : null;
                            return videoId ? (
                              <a
                                href={`https://www.youtube.com/watch?v=${videoId}`}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="bg-red-600 text-white px-3 py-1 rounded text-xs hover:bg-red-700"
                              >
                                Open YouTube
                              </a>
                            ) : null;
                          })()}
                        </div>
                      ) : (
                        <div className="w-48 h-32">
                          <iframe
                            title="YouTube video player"
                            width="100%"
                            height="100%"
                            src={currentSongAudioUrl}
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowFullScreen
                            frameBorder="0"
                            className="rounded-lg"
                          />
                        </div>
                      )}
                    </div>
                  );
                } else if (mediaType === 'spotify' || mediaType === 'deezer') {
                  return (
                    <MobileAudioPlayer
                      audioRef={audioRef}
                      currentSongUrl={currentSongAudioUrl}
                      isAutoPaused={isAutoPaused}
                      activePlayerName={activePlayerName}
                      resetAutoPause={resetAutoPause}
                      onLoadStart={handleAudioLoadStart}
                      onCanPlay={handleAudioCanPlay}
                      onError={handleAudioError}
                      onStalled={handleAudioStalled}
                      providerName={mediaType === 'spotify' ? 'Spotify' : 'Deezer'}
                    />
                  );
                } else {
                  return (
                    <div className="text-red-600 text-xs text-center p-2 border border-red-300 bg-red-50 rounded">
                      Could not load audio
                    </div>
                  );
                }
              })()
            ) : (
              <div className="text-gray-500 text-xs text-center p-2 bg-gray-100 border border-gray-300 rounded">
                <p>No audio available</p>
              </div>
            )}
          </div>
        </div>
      </div>
      
      {/* Action buttons */}
      <div className="flex flex-wrap gap-2">
        {/* Show Next Round / End Game button when round is complete */}
        {isRoundComplete && (isLastRound ? (
          <div className="w-full text-center">
            <button
              className="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed disabled:bg-gray-400 transition-colors"
              onClick={onNextRound}
              disabled={!canAdvance}
            >
              End Game
            </button>
            <p className="text-sm text-gray-600 mt-2">All rounds complete. Proceed to see final results.</p>
          </div>
        ) : (
          <div className="w-full text-center">
            <button
              className="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed disabled:bg-gray-400 transition-colors"
              onClick={onNextRound}
              disabled={!canAdvance || isGameOver}
            >
              Next Song
            </button>
            <p className="text-sm text-gray-600 mt-2">Round {currentRound} of {totalRounds} complete. Ready for next round.</p>
          </div>
        ))}

        {/* Show End Round button when round is active */}
        {!isRoundComplete && !isGameOver && onEndRound && (
          <div className="w-full text-center">
            <button
              className="px-6 py-3 bg-orange-600 hover:bg-orange-700 text-white font-medium rounded-lg focus:outline-none transition-colors"
              onClick={onEndRound}
            >
              {isLastRound ? 'End Game' : 'End Round'}
            </button>
            <p className="text-sm text-gray-600 mt-2">
              {isLastRound 
                ? 'End the game and show final results.' 
                : `End round ${currentRound} and proceed to next song.`
              }
            </p>
          </div>
        )}
      </div>
    </div>
  );
};

export default JudgeGameControls; 