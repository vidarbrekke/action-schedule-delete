import React from 'react';
import { gameAudioManager } from '../services/gameAudioManager';

interface BuzzButtonProps {
  onClick: () => void;
  disabled?: boolean;
  isActive?: boolean;
  label?: string;
}

const BuzzButton: React.FC<BuzzButtonProps> = ({
  onClick,
  disabled = false,
  isActive = false,
  label = 'BUZZ IN!',
}) => {
  const handleClick = () => {
    if (!disabled) {
      try {
        gameAudioManager.playBuzzerSound();
      } catch (error) {
        // Audio failed, but button should still work
        console.warn('Audio playback failed:', error);
      }
      onClick();
    }
  };

  if (disabled) {
    return (
      <button 
        disabled={true}
        className="w-32 h-32 rounded-full bg-gray-300 border-4 border-gray-400 
          text-gray-500 font-bold text-lg shadow-lg cursor-not-allowed 
          opacity-50 transition-all duration-200"
      >
        WAIT
      </button>
    );
  }

  if (isActive) {
    return (
      <button 
        onClick={handleClick}
        className="w-32 h-32 rounded-full bg-gradient-to-b from-green-400 to-green-600 
          border-4 border-green-300 text-white font-bold text-lg shadow-xl 
          transform scale-105 animate-pulse transition-all duration-200
          hover:from-green-500 hover:to-green-700 active:scale-95"
      >
        <div className="flex flex-col items-center">
          <span>YOU'RE</span>
          <span>IN!</span>
        </div>
      </button>
    );
  }

  return (
    <button 
      onClick={handleClick}
      disabled={disabled}
      className={`
        relative px-8 py-4 text-xl font-bold rounded-lg border-2 transition-all duration-150
        ${disabled ? 'bg-gray-400 border-gray-500 text-gray-600 cursor-not-allowed' : 
          isActive ? 'bg-red-600 border-red-700 text-white shadow-lg scale-105' : 
          'bg-red-500 border-red-600 text-white hover:bg-red-600 hover:border-red-700 hover:scale-105 shadow-md'}
      `}
    >
      {disabled ? 'WAITING...' : label}
    </button>
  );
};

export default BuzzButton; 