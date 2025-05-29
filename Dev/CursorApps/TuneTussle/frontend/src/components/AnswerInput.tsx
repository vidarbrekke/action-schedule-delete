import React, { useState } from 'react';
import { motion } from 'framer-motion';

interface AnswerInputProps {
  onSubmit: (answer: { raw: string }) => void;
  disabled?: boolean;
  placeholder?: string;
  activePlayerName?: string | null; // Name of the player who should be answering
  shake?: boolean; // New prop: triggers shake animation
}

const AnswerInput: React.FC<AnswerInputProps> = ({
  onSubmit,
  disabled = false,
  placeholder = "Type song and artist in any order (e.g. 'Madonna Like a Virgin', 'Like a Virgin, Madonna', 'Like a Virgin by Madonna')",
  activePlayerName = null,
  shake = false,
}) => {
  const [input, setInput] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [lastSubmittedAnswer, setLastSubmittedAnswer] = useState<string | null>(null);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!disabled && input.trim() && !isSubmitting) {
      const answer = input.trim();
      setIsSubmitting(true);
      setLastSubmittedAnswer(answer);
      
      try {
        onSubmit({ raw: answer });
        setInput('');
        
        // Show submission feedback for 2 seconds
        setTimeout(() => {
          setIsSubmitting(false);
          setLastSubmittedAnswer(null);
        }, 2000);
      } catch (error) {
        console.error('Error submitting answer:', error);
        setIsSubmitting(false);
        setLastSubmittedAnswer(null);
      }
    }
  };

  return (
    <form onSubmit={handleSubmit} className="w-full max-w-md space-y-3">
      {activePlayerName && (
        <p className="text-center text-lg text-indigo-600">
          <span className="font-semibold">{activePlayerName}</span>, it's your turn!
        </p>
      )}
      
      {/* Submission feedback */}
      {isSubmitting && lastSubmittedAnswer && (
        <div className="p-3 bg-blue-100 border border-blue-300 rounded-lg text-center">
          <div className="flex items-center justify-center space-x-2">
            <div className="w-4 h-4 border-2 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
            <span className="text-blue-800 text-sm font-medium">
              Submitted: "{lastSubmittedAnswer}"
            </span>
          </div>
          <p className="text-blue-600 text-xs mt-1">Processing your answer...</p>
        </div>
      )}
      
      <motion.div
        className="flex flex-col space-y-2"
        animate={shake ? { x: [0, -10, 10, -8, 8, -4, 4, 0] } : false}
        transition={{ duration: 0.5, type: 'tween' }}
      >
        <input
          type="text"
          value={input}
          onChange={(e) => setInput(e.target.value)}
          disabled={disabled || isSubmitting}
          placeholder={placeholder}
          className="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-shadow disabled:bg-gray-100 disabled:cursor-not-allowed"
        />
        <button
          type="submit"
          disabled={disabled || !input.trim() || isSubmitting}
          className="w-full px-6 py-3 bg-green-500 text-white font-semibold rounded-lg hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-400 focus:ring-opacity-50 disabled:bg-gray-400 disabled:cursor-not-allowed transition-colors relative"
        >
          {isSubmitting ? (
            <div className="flex items-center justify-center space-x-2">
              <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
              <span>Submitting...</span>
            </div>
          ) : (
            'Submit'
          )}
        </button>
      </motion.div>
    </form>
  );
};

export default AnswerInput; 