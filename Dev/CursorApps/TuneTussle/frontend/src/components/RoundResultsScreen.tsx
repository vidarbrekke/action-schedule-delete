import React, { useMemo } from 'react';
import { motion } from 'framer-motion';

interface RoundResultsProps {
  roundNumber: number;
  correctAnswer: { title: string; artist: string };
  playersWithPoints: Array<{ name: string; score: number; pointsThisRound: number }>;
  roundParticipants: Array<{ name: string; role: string }>;
  artworkUrl: string | null;
  canAdvance: boolean;
  isGameOver: boolean;
  isJudge: boolean;
  onNextRound: () => void;
  onEndGame?: () => void;
}

const RoundResultsScreen: React.FC<RoundResultsProps> = ({
  roundNumber,
  correctAnswer,
  playersWithPoints,
  roundParticipants,
  artworkUrl,
  canAdvance,
  isGameOver,
  isJudge,
  onNextRound,
  onEndGame
}) => {
  // Memoize expensive calculations to prevent re-computation on every render
  const { playersWhoScored, judgeName, sortedPlayers } = useMemo(() => {
    const scored = playersWithPoints.filter(p => p.pointsThisRound > 0);
    const judge = roundParticipants.find(p => p.role === 'judge');
    const sorted = playersWithPoints
      .filter(p => roundParticipants.find(rp => rp.name === p.name)?.role !== 'judge')
      .sort((a, b) => b.score - a.score);
    
    return {
      playersWhoScored: scored,
      judgeName: judge?.name || 'the judge',
      sortedPlayers: sorted
    };
  }, [playersWithPoints, roundParticipants]);

  // Optimize animation variants for better performance
  const animationVariants = useMemo(() => ({
    container: {
      initial: { opacity: 0, scale: 0.9 },
      animate: { opacity: 1, scale: 1 },
      exit: { opacity: 0, scale: 0.9 },
      transition: { duration: 0.4, ease: "easeOut" }
    },
    header: {
      initial: { y: -30, opacity: 0 },
      animate: { y: 0, opacity: 1 },
      transition: { delay: 0.1, duration: 0.3 }
    },
    content: {
      initial: { y: 20, opacity: 0 },
      animate: { y: 0, opacity: 1 },
      transition: { delay: 0.2, duration: 0.3 }
    },
    // More efficient blinking animation using CSS keyframes approach
    blinking: {
      animate: { 
        opacity: [1, 0.4, 1] 
      },
      transition: { 
        duration: 1.5, 
        repeat: Infinity, 
        ease: "easeInOut" 
      }
    }
  }), []);
  
  return (
    <motion.div
      className="fixed inset-0 bg-gradient-to-br from-indigo-50 to-purple-100 flex items-center justify-center p-4 z-50"
      {...animationVariants.container}
    >
      <div className="max-w-4xl w-full bg-white rounded-2xl shadow-2xl overflow-hidden">
        {/* Header */}
        <motion.div 
          className="bg-gradient-to-r from-indigo-600 to-purple-600 text-white p-6 text-center"
          {...animationVariants.header}
        >
          <h1 className="text-3xl font-bold mb-2">
            {isGameOver ? 'Game Complete!' : `Round ${roundNumber} Results`}
          </h1>
          <p className="text-indigo-100 text-lg">
            {isGameOver ? 'Final results for this amazing game!' : 'Here\'s how everyone did this round'}
          </p>
        </motion.div>

        <div className="p-8">
          {/* Correct Answer Section */}
          <motion.div 
            className="mb-8 text-center"
            {...animationVariants.content}
          >
            <h2 className="text-xl font-semibold text-gray-700 mb-4">The Correct Answer Was:</h2>
            <div className="flex items-center justify-center gap-6">
              {artworkUrl && (
                <motion.img
                  src={artworkUrl}
                  alt="Album artwork"
                  className="w-20 h-20 rounded-lg shadow-lg border-2 border-gray-200 object-cover"
                  initial={{ scale: 0, rotate: -90 }}
                  animate={{ scale: 1, rotate: 0 }}
                  transition={{ delay: 0.4, duration: 0.4, type: "spring", stiffness: 200 }}
                />
              )}
              <div className="text-center">
                <h3 className="text-2xl font-bold text-gray-800">{correctAnswer.title}</h3>
                <p className="text-xl text-gray-600">by {correctAnswer.artist}</p>
              </div>
            </div>
          </motion.div>

          {/* Points Earned This Round */}
          {playersWhoScored.length > 0 && (
            <motion.div 
              className="mb-8"
              initial={{ y: 20, opacity: 0 }}
              animate={{ y: 0, opacity: 1 }}
              transition={{ delay: 0.4, duration: 0.3 }}
            >
              <h3 className="text-lg font-semibold text-gray-700 mb-4 text-center">
                Points Earned This Round
              </h3>
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                {playersWhoScored.map((player, index) => (
                  <motion.div
                    key={player.name}
                    className="bg-green-50 border border-green-200 rounded-lg p-4 text-center"
                    initial={{ scale: 0, y: 10 }}
                    animate={{ scale: 1, y: 0 }}
                    transition={{ delay: 0.6 + index * 0.05, duration: 0.2 }}
                  >
                    <div className="font-semibold text-green-800">{player.name}</div>
                    <div className="text-2xl font-bold text-green-600">
                      +{player.pointsThisRound} point{player.pointsThisRound !== 1 ? 's' : ''}
                    </div>
                  </motion.div>
                ))}
              </div>
            </motion.div>
          )}

          {/* Updated Scoreboard */}
          <motion.div 
            className="mb-8"
            initial={{ y: 20, opacity: 0 }}
            animate={{ y: 0, opacity: 1 }}
            transition={{ delay: 0.6, duration: 0.3 }}
          >
            <h3 className="text-lg font-semibold text-gray-700 mb-4 text-center">
              {isGameOver ? 'Final Scores' : 'Updated Scoreboard'}
            </h3>
            <div className="bg-gray-50 rounded-lg p-6">
              <div className="space-y-3">
                {sortedPlayers.map((player, index) => (
                  <motion.div
                    key={player.name}
                    className={`flex justify-between items-center p-3 rounded-lg ${
                      isGameOver && index === 0 
                        ? 'bg-yellow-100 border-2 border-yellow-400' 
                        : 'bg-white border border-gray-200'
                    }`}
                    initial={{ x: -30, opacity: 0 }}
                    animate={{ x: 0, opacity: 1 }}
                    transition={{ delay: 0.8 + index * 0.05, duration: 0.2 }}
                  >
                    <div className="flex items-center">
                      <span className={`font-bold text-lg mr-3 ${
                        isGameOver && index === 0 ? 'text-yellow-700' : 'text-gray-600'
                      }`}>
                        #{index + 1}
                      </span>
                      <span className={`font-semibold ${
                        isGameOver && index === 0 ? 'text-yellow-800' : 'text-gray-800'
                      }`}>
                        {player.name}
                        {isGameOver && index === 0 && <span className="ml-2">🏆</span>}
                      </span>
                    </div>
                    <div className="flex items-center space-x-2">
                      {player.pointsThisRound > 0 && (
                        <span className="text-green-600 text-sm font-medium">
                          +{player.pointsThisRound}
                        </span>
                      )}
                      <span className={`font-bold text-lg ${
                        isGameOver && index === 0 ? 'text-yellow-700' : 'text-gray-700'
                      }`}>
                        {player.score}
                      </span>
                    </div>
                  </motion.div>
                ))}
              </div>
            </div>
          </motion.div>

          {/* Action Buttons */}
          {isJudge && (
            <motion.div 
              className="flex justify-center space-x-4"
              initial={{ y: 20, opacity: 0 }}
              animate={{ y: 0, opacity: 1 }}
              transition={{ delay: 0.9, duration: 0.3 }}
            >
              {isGameOver ? (
                onEndGame && (
                  <button
                    onClick={onEndGame}
                    className="px-8 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg shadow-lg transition-colors duration-200"
                  >
                    End Game
                  </button>
                )
              ) : canAdvance ? (
                <button
                  onClick={onNextRound}
                  className="px-8 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg shadow-lg transition-colors duration-200 flex items-center space-x-2"
                >
                  <span>Start Next Round</span>
                  <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                  </svg>
                </button>
              ) : (
                <div className="text-gray-600 text-center">
                  <p>Game Complete!</p>
                  <p className="text-sm">Thank you for playing TuneTussle!</p>
                </div>
              )}
            </motion.div>
          )}

          {/* Player Instructions with optimized blinking animation */}
          {!isJudge && (
            <motion.div 
              className="text-center text-gray-600 mt-6"
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              transition={{ delay: 1.0, duration: 0.3 }}
            >
              <p className="text-sm">
                {isGameOver 
                  ? 'Thank you for playing TuneTussle! 🎵' 
                  : (
                    <motion.span
                      {...animationVariants.blinking}
                    >
                      Waiting for {judgeName} to start the next round...
                    </motion.span>
                  )}
              </p>
            </motion.div>
          )}
        </div>
      </div>
    </motion.div>
  );
};

export default RoundResultsScreen; 