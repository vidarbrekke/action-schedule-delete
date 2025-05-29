import React from 'react';
import { Music } from 'lucide-react';

interface AlbumArtProps {
  artworkUrl?: string;
  alt?: string;
  size?: 'sm' | 'md' | 'lg';
  className?: string;
}

const AlbumArt: React.FC<AlbumArtProps> = ({
  artworkUrl,
  alt = 'Album artwork',
  size = 'md',
  className = ''
}) => {
  const sizeClasses = {
    sm: 'w-12 h-12',
    md: 'w-16 h-16', // 64x64px as suggested
    lg: 'w-24 h-24'
  };

  const iconSizes = {
    sm: 20,
    md: 24,
    lg: 32
  };

  if (!artworkUrl) {
    return (
      <div 
        className={`${sizeClasses[size]} bg-gray-100 rounded-lg border border-gray-200 
          flex items-center justify-center ${className}`}
      >
        <Music size={iconSizes[size]} className="text-gray-400" />
      </div>
    );
  }

  return (
    <div className={`${sizeClasses[size]} ${className}`}>
      <img 
        src={artworkUrl}
        alt={alt}
        className={`${sizeClasses[size]} object-cover rounded-lg border border-gray-200 
          shadow-sm`}
        loading="lazy"
        onError={(e) => {
          // Fallback to placeholder if image fails to load
          e.currentTarget.style.display = 'none';
          const placeholder = e.currentTarget.nextElementSibling as HTMLElement;
          if (placeholder) {
            placeholder.style.display = 'flex';
          }
        }}
      />
      {/* Fallback placeholder (hidden by default) */}
      <div 
        className={`${sizeClasses[size]} bg-gray-100 rounded-lg border border-gray-200 
          flex items-center justify-center`}
        style={{ display: 'none' }}
      >
        <Music size={iconSizes[size]} className="text-gray-400" />
      </div>
    </div>
  );
};

export default AlbumArt; 