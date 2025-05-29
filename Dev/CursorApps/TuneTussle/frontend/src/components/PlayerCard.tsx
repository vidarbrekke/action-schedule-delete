import React from 'react';
import UserAvatar from './UserAvatar';

interface PlayerCardProps {
  name: string;
  role?: 'judge' | 'participant';
  score?: number;
  isOnline?: boolean;
  isCurrentPlayer?: boolean;
  className?: string;
}

const PlayerCard: React.FC<PlayerCardProps> = ({
  name,
  role = 'participant',
  score,
  isOnline = true,
  isCurrentPlayer = false,
  className = ''
}) => {
  return (
    <div 
      className={`flex items-center p-3 bg-white rounded-xl border border-gray-100 
        shadow-sm hover:shadow-md transition-shadow duration-200
        ${isCurrentPlayer ? 'ring-2 ring-indigo-500 bg-indigo-50' : ''}
        ${className}`}
    >
      <UserAvatar 
        name={name}
        role={role}
        isOnline={isOnline}
        size="md"
        showRole={true}
      />
      
      <div className="ml-3 flex-1 min-w-0">
        <div className="flex items-center justify-between">
          <h3 className="text-sm font-semibold text-gray-900 truncate">
            {name}
            {role === 'judge' && (
              <span className="ml-2 inline-flex items-center px-2 py-0.5 rounded-full 
                text-xs font-medium bg-yellow-100 text-yellow-800">
                Judge
              </span>
            )}
          </h3>
          
          {typeof score === 'number' && (
            <div className="ml-2 flex items-center">
              <span className="text-sm font-bold text-indigo-600">
                {score} pts
              </span>
            </div>
          )}
        </div>
        
        <div className="flex items-center mt-1">
          <div className={`w-2 h-2 rounded-full mr-2 ${
            isOnline ? 'bg-green-400' : 'bg-gray-300'
          }`} />
          <span className="text-xs text-gray-500">
            {isOnline ? 'Online' : 'Offline'}
          </span>
        </div>
      </div>
    </div>
  );
};

export default PlayerCard; 