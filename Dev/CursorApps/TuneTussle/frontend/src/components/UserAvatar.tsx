import React from 'react';
import { Crown } from 'lucide-react';

interface UserAvatarProps {
  name: string;
  role?: 'judge' | 'participant';
  isOnline?: boolean;
  size?: 'sm' | 'md' | 'lg';
  showRole?: boolean;
  className?: string;
}

const UserAvatar: React.FC<UserAvatarProps> = ({
  name,
  role = 'participant',
  isOnline = true,
  size = 'md',
  showRole = true,
  className = ''
}) => {
  const sizeClasses = {
    sm: 'w-8 h-8',
    md: 'w-12 h-12',
    lg: 'w-16 h-16'
  };

  const getInitials = (name: string) => {
    return name
      .split(' ')
      .map(word => word.charAt(0))
      .join('')
      .toUpperCase()
      .slice(0, 2);
  };

  const getBgColor = (name: string) => {
    // Generate consistent color based on name
    const colors = [
      'bg-blue-500',
      'bg-green-500', 
      'bg-purple-500',
      'bg-pink-500',
      'bg-yellow-500',
      'bg-indigo-500',
      'bg-red-500',
      'bg-teal-500'
    ];
    
    const index = name.split('').reduce((acc, char) => acc + char.charCodeAt(0), 0) % colors.length;
    return colors[index];
  };

  return (
    <div className={`relative inline-flex items-center ${className}`}>
      {/* Avatar Circle */}
      <div 
        className={`${sizeClasses[size]} ${getBgColor(name)} 
          rounded-full flex items-center justify-center text-white font-semibold
          border-2 border-white shadow-sm relative`}
      >
        <span className={`text-${size === 'sm' ? 'xs' : size === 'md' ? 'sm' : 'base'}`}>
          {getInitials(name)}
        </span>
        
        {/* Online Status Indicator */}
        {isOnline && (
          <div className="absolute -bottom-0 -right-0 w-3 h-3 bg-green-400 
            border-2 border-white rounded-full"></div>
        )}
      </div>

      {/* Role Badge */}
      {showRole && role === 'judge' && (
        <div className="absolute -top-1 -right-1 bg-yellow-400 text-yellow-900 
          rounded-full p-1 border-2 border-white shadow-sm">
          <Crown size={12} />
        </div>
      )}
    </div>
  );
};

export default UserAvatar; 