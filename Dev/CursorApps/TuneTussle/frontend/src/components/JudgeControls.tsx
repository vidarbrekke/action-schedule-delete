import React from 'react';
import { Clock, Users, AlertCircle } from 'lucide-react';
import { components, typography, colors, layout } from '../styles/designSystem';
import LoadingSpinner from './LoadingSpinner';

interface JudgeControlsProps {
  onStartGame: () => void;
  isStartingGame: boolean;
  startGameError: string | null;
  isGeneratingSongs?: boolean;
  isGameReady?: boolean;
  participants?: Array<{ id: string; name: string; role?: string }>;
}

export const JudgeControls: React.FC<JudgeControlsProps> = ({
  onStartGame,
  isStartingGame,
  startGameError,
  isGeneratingSongs = false,
  isGameReady = false,
  participants = [],
}) => {
  // Count non-judge participants
  const playerCount = participants.filter(p => p.role === 'participant').length;
  const hasPlayers = playerCount > 0;
  
  // Button should be disabled if starting, no players, or game not ready
  const isButtonDisabled = isStartingGame || !hasPlayers || !isGameReady;
  
  return (
    <div className={`${layout.spacing.component} text-center`}>
      <h3 className={`${typography.heading.sm} mb-4`}>Game Controls</h3>
      
      {/* Status Messages */}
      <div className={layout.spacing.tight}>
        {!hasPlayers && !isStartingGame && (
          <div className={`${components.card.gradient} p-3 rounded-lg`}>
            <Users size={20} className={`${colors.text.muted} mx-auto mb-2`} />
            <p className={`${typography.body.sm} ${colors.text.muted}`}>
              Waiting for players to join...
            </p>
          </div>
        )}
        
        {startGameError && (
          <div className={`${colors.status.error} border rounded-lg p-3`} data-testid="start-game-error">
            <AlertCircle size={20} className="mx-auto mb-2" />
            <p className={typography.body.sm}>Error: {startGameError}</p>
          </div>
        )}
        
        {isGeneratingSongs && !startGameError && (
          <div className={`${colors.status.info} border rounded-lg p-3`}>
            <div className="flex items-center justify-center mb-2">
              <LoadingSpinner size="sm" />
            </div>
            <p className={typography.body.sm}>
              Generating songs for your game. Please wait...
            </p>
          </div>
        )}
        
        {!isGameReady && !isGeneratingSongs && !startGameError && hasPlayers && (
          <div className={`${colors.status.warning} border rounded-lg p-3`}>
            <Clock size={20} className="mx-auto mb-2" />
            <p className={typography.body.sm}>
              Waiting for playlist to be ready...
            </p>
          </div>
        )}
      </div>
      
      {/* Start Game Button */}
      <div className="mt-6">
        {isStartingGame ? (
          <div className={components.loading.spinner}>
            <LoadingSpinner size="sm" message="Starting game..." />
          </div>
        ) : (
          <button
            className={`${components.button.primary} ${components.button.disabled}`}
            data-testid="start-game-btn"
            onClick={!isButtonDisabled ? onStartGame : undefined}
            disabled={isButtonDisabled}
          >
            Start Game
          </button>
        )}
      </div>
    </div>
  );
}; 