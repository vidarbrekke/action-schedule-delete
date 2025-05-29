import React from 'react';
import { motion } from 'framer-motion';
import AnimatedTurntable from './AnimatedTurntable';
import AlbumArt from './AlbumArt';

interface QuestionDisplayProps {
  questionText?: string;
  currentRound?: number;
  totalRounds?: number;
  category?: string; // Optional: e.g., Music Genre, Artist Trivia
  isRoundComplete?: boolean; // Indicates if the round is over
  correctAnswerText?: string | null; // The correct answer to display
  partiallyCorrect?: boolean; // Was the answer partially correct
  lastCorrectPlayerName?: string | null; // Who got it right, if anyone
  isJudge?: boolean; // Whether the current user is a judge
  submissionFeedback?: { message: string; points: number; status: string } | null; // Feedback object
  isMusicPlaying?: boolean; // Whether music is currently playing (for turntable animation)
  showTurntable?: boolean; // Whether to show the animated turntable
  artworkUrl?: string | null; // <-- Add artworkUrl prop
}

const QuestionDisplay: React.FC<QuestionDisplayProps> = ({
  questionText,
  currentRound,
  totalRounds,
  category,
  isRoundComplete = false,
  correctAnswerText = null,
  partiallyCorrect = false,
  lastCorrectPlayerName = null,
  isJudge = false,
  submissionFeedback = null,
  isMusicPlaying = false,
  showTurntable = false,
  artworkUrl = null // <-- Add default
}) => {
  return (
    <div className="w-full p-6 bg-white border border-gray-200 rounded-lg shadow-lg text-center relative">
      {currentRound && totalRounds && (
        <div className="absolute top-2 right-2 bg-indigo-100 text-indigo-700 text-xs font-semibold px-2 py-1 rounded-full">
          Round {currentRound} / {totalRounds}
        </div>
      )}
      {/* Only show category if round is not complete */}
      {!isRoundComplete && category && (
        <p className="text-sm text-indigo-500 font-medium mb-2">Category: {category}</p>
      )}
      {!isRoundComplete ? (
        <div className="min-h-[6rem] flex flex-col items-center justify-center">
          {/* Show turntable for players during music playback */}
          {showTurntable && !isJudge && (
            <div className="mb-4">
              <AnimatedTurntable 
                size={120} 
                speed={2} 
                tonearmDelay={0.5} 
                isPlaying={isMusicPlaying}
                className="mx-auto"
              />
            </div>
          )}
          <div className="flex items-center justify-center w-full">
            {artworkUrl && isJudge && (
              <div className="mr-4">
                <AlbumArt artworkUrl={artworkUrl} alt="Album artwork" size="md" />
              </div>
            )}
            <h2 className="text-2xl md:text-3xl font-bold text-gray-800 text-center">
              {questionText || 'Waiting for the next question...'}
            </h2>
          </div>
        </div>
      ) : (
        <motion.div
          className={`py-4 px-2 rounded-lg border min-h-[6rem] flex flex-col items-center justify-center
            ${isJudge 
              ? (partiallyCorrect ? 'bg-yellow-50 border-yellow-300' : 'bg-green-50 border-green-300')
              : 'bg-blue-50 border-blue-200'
            }`}
          initial={{ scale: 0.95, opacity: 0.7 }}
          animate={{ scale: 1.05, opacity: 1 }}
          transition={{ type: 'spring', stiffness: 300, damping: 18, duration: 0.5 }}
        >
          {/* Show different message for judge vs player */}
          {isJudge ? (
            <>
              <p className={`text-sm font-medium ${partiallyCorrect ? 'text-yellow-700' : 'text-green-700'}`}>
                {partiallyCorrect ? 'Partially correct answer! The full answer was:' : 'The correct answer was:'}
              </p>
              <h3 className={`text-xl md:text-2xl font-bold mt-1 break-words ${partiallyCorrect ? 'text-yellow-800' : 'text-green-800'}`}>
                {correctAnswerText || 'Answer not available'}
              </h3>
              {lastCorrectPlayerName && (
                <p className={`text-sm mt-2 ${partiallyCorrect ? 'text-yellow-600' : 'text-green-600'}`}>
                  {partiallyCorrect ? 
                    `${lastCorrectPlayerName} got part of the answer right.` : 
                    `${lastCorrectPlayerName} got it right.`}
                </p>
              )}
              <p className="text-sm mt-4 font-medium text-blue-600">
                Round complete. Start the next round when you are ready!
              </p>
            </>
          ) : (
            <>
              <p className="text-sm font-medium text-blue-700">
                Round Complete
              </p>
              {submissionFeedback && (
                <div className="mt-2 p-3 rounded-md bg-blue-100 border border-blue-200 mb-2 max-w-md">
                  <p className="text-sm text-blue-800">{submissionFeedback.message}</p>
                  {submissionFeedback.points > 0 && (
                    <span className="block text-xs text-blue-600 mt-1">You earned {submissionFeedback.points} point{submissionFeedback.points === 1 ? '' : 's'}!</span>
                  )}
                </div>
              )}
              <h3 className="text-xl md:text-2xl font-bold mt-1 break-words text-blue-800">
                {correctAnswerText
                  ? `The correct answer was: ${correctAnswerText}`
                  : 'Correct answer not available'}
              </h3>
            </>
          )}
        </motion.div>
      )}
      
      {/* Placeholder for timer or other elements if needed later */}
    </div>
  );
};

export default QuestionDisplay; 