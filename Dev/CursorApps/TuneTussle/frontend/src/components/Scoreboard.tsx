import React, { useState } from 'react';
import PlayerCard from './PlayerCard';
// PlayerRole type is now defined in this file

export type PlayerRole = 'judge' | 'participant' | 'admin' | 'spectator';

export type PlayerScore = {
  id: string;
  name: string;
  score: number;
  role?: PlayerRole; // Made role optional to handle data that might not have it
  isCurrentTurn?: boolean; // Optional: highlight player whose turn it is
};

interface ScoreboardProps {
  scores: PlayerScore[];
  title?: string;
  highlightWinners?: string[]; // New prop: Array of winner IDs
  compact?: boolean; // New prop for compact display
  isJudge?: boolean; // New prop to show judge controls
  onAdjustScore?: (playerId: string, newScore: number) => void; // New prop for score adjustment
  isGameOver?: boolean; // New prop to hide controls during game over
}

// Simple Plus/Minus Button Component
const ScoreAdjustButton: React.FC<{ 
  onClick: () => void; 
  children: React.ReactNode; 
  className?: string;
  disabled?: boolean;
}> = ({ onClick, children, className = '', disabled = false }) => (
  <button
    onClick={onClick}
    disabled={disabled}
    className={`w-7 h-7 flex items-center justify-center text-xs font-bold rounded-full 
      transition-colors duration-150 disabled:opacity-50 disabled:cursor-not-allowed ${className}`}
  >
    {children}
  </button>
);

const Scoreboard: React.FC<ScoreboardProps> = ({
  scores,
  title = 'Scoreboard',
  highlightWinners = [], // Default to empty array
  compact = false,
  isJudge = false,
  onAdjustScore,
  isGameOver = false
}) => {
  const [editingPlayerId, setEditingPlayerId] = useState<string | null>(null);
  const [tempScore, setTempScore] = useState<string>('');

  // Filter out the judge from scores - handle cases where role might be undefined
  const playerScores = scores.filter(score => score.role !== 'judge');
  
  const sortedScores = [...playerScores].sort((a, b) => b.score - a.score);

  const handleQuickAdjust = (playerId: string, delta: number) => {
    if (!onAdjustScore) return;
    const player = sortedScores.find(p => p.id === playerId);
    if (player) {
      const newScore = Math.max(0, player.score + delta); // Don't allow negative scores
      onAdjustScore(playerId, newScore);
    }
  };

  const handleStartEdit = (playerId: string, currentScore: number) => {
    setEditingPlayerId(playerId);
    setTempScore(currentScore.toString());
  };

  const handleSaveEdit = () => {
    if (!editingPlayerId || !onAdjustScore) return;
    const newScore = parseFloat(tempScore);
    if (!isNaN(newScore) && newScore >= 0) {
      onAdjustScore(editingPlayerId, newScore);
    }
    setEditingPlayerId(null);
    setTempScore('');
  };

  const handleCancelEdit = () => {
    setEditingPlayerId(null);
    setTempScore('');
  };

  if (compact) {
    // Enhanced compact version with judge controls
    return (
      <div className="w-full">
        <h3 className="text-lg font-semibold text-gray-900 mb-3">{title}</h3>
        {sortedScores.length === 0 ? (
          <p className="text-center text-gray-500 py-4">No scores yet. The game is about to start!</p>
        ) : (
          <div className="space-y-2">
            {sortedScores.map((player, index) => {
              const isWinner = highlightWinners.includes(player.id);
              const isEditing = editingPlayerId === player.id;
              const showControls = isJudge && onAdjustScore && !isGameOver;
              
              return (
                <div key={player.id} className="flex items-center space-x-2">
                  {/* Rank Badge */}
                  <div className={`flex-shrink-0 w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold
                    ${isWinner ? 'bg-yellow-400 text-yellow-900' : 'bg-gray-200 text-gray-700'}`}>
                    {index + 1}
                  </div>
                  
                  {/* Player Card - takes most space */}
                  <div className="flex-1 min-w-0">
                    <PlayerCard
                      name={player.name}
                      role={player.role === 'judge' || player.role === 'participant' ? player.role : undefined}
                      score={player.score}
                      isCurrentPlayer={player.isCurrentTurn || isWinner}
                      className="w-full"
                    />
                  </div>
                  
                  {/* Judge Controls - compact horizontal layout */}
                  {showControls && !isEditing && (
                    <div className="flex-shrink-0 flex items-center space-x-1">
                      {/* Quick adjust buttons */}
                      <ScoreAdjustButton
                        onClick={() => handleQuickAdjust(player.id, -5)}
                        className="bg-red-100 hover:bg-red-200 text-red-700"
                      >
                        -5
                      </ScoreAdjustButton>
                      <ScoreAdjustButton
                        onClick={() => handleQuickAdjust(player.id, 5)}
                        className="bg-green-100 hover:bg-green-200 text-green-700"
                      >
                        +5
                      </ScoreAdjustButton>
                      {/* Edit button */}
                      <ScoreAdjustButton
                        onClick={() => handleStartEdit(player.id, player.score)}
                        className="bg-blue-100 hover:bg-blue-200 text-blue-700"
                      >
                        ✏️
                      </ScoreAdjustButton>
                    </div>
                  )}

                  {/* Inline edit mode */}
                  {showControls && isEditing && (
                    <div className="flex-shrink-0 flex items-center space-x-1">
                      <input
                        type="number"
                        value={tempScore}
                        onChange={(e) => setTempScore(e.target.value)}
                        className="w-16 px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:border-blue-500"
                        min="0"
                        step="1"
                        autoFocus
                        onKeyDown={(e) => {
                          if (e.key === 'Enter') handleSaveEdit();
                          if (e.key === 'Escape') handleCancelEdit();
                        }}
                      />
                      <ScoreAdjustButton
                        onClick={handleSaveEdit}
                        className="bg-green-100 hover:bg-green-200 text-green-700"
                      >
                        ✓
                      </ScoreAdjustButton>
                      <ScoreAdjustButton
                        onClick={handleCancelEdit}
                        className="bg-red-100 hover:bg-red-200 text-red-700"
                      >
                        ✗
                      </ScoreAdjustButton>
                    </div>
                  )}
                  
                  {/* Winner Trophy */}
                  {!showControls && isWinner && (
                    <div className="flex-shrink-0 text-lg">🏆</div>
                  )}
                </div>
              );
            })}
          </div>
        )}
        
        {/* Instructions for judges */}
        {isJudge && onAdjustScore && !isGameOver && sortedScores.length > 0 && (
          <p className="text-xs text-gray-500 mt-3 text-center">
            Quick adjust: ±5 buttons • Edit: ✏️ button • Enter to save, Esc to cancel
          </p>
        )}
      </div>
    );
  }

  // Original full version for game-over screen
  return (
    <div className="w-full max-w-lg p-6 bg-white border border-gray-200 rounded-lg shadow-md">
      <h3 className="text-2xl font-bold text-center text-gray-800 mb-4">{title}</h3>
      {sortedScores.length === 0 ? (
        <p className="text-center text-gray-500">No scores yet. The game is about to start!</p>
      ) : (
        <ul className="space-y-3">
          {sortedScores.map((player, index) => {
            const isWinner = highlightWinners.includes(player.id);
            let playerClass = 'bg-gray-50';
            if (isWinner) {
              playerClass = 'bg-yellow-200 ring-2 ring-yellow-500';
            } else if (player.isCurrentTurn) {
              playerClass = 'bg-blue-100 ring-2 ring-blue-500';
            }

            return (
              <li
                key={player.id}
                className={`flex justify-between items-center p-3 rounded-md ${playerClass}`}
              >
                <div className="flex items-center">
                  <span className={`font-semibold text-lg ${isWinner ? 'text-yellow-800' : player.isCurrentTurn ? 'text-blue-700' : 'text-gray-700'}`}>
                    {index + 1}. {player.name} {isWinner ? '🏆' : ''}
                  </span>
                </div>
                <span className={`font-bold text-xl ${isWinner ? 'text-yellow-800' : player.isCurrentTurn ? 'text-blue-700' : 'text-gray-900'}`}>
                  {player.score}
                </span>
              </li>
            );
          })}
        </ul>
      )}
    </div>
  );
};

export default Scoreboard; 