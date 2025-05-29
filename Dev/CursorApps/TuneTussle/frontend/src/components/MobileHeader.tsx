import React from 'react';
import { ArrowLeft, MoreVertical } from 'lucide-react';
import { useNavigate } from 'react-router-dom';

interface MobileHeaderProps {
  title: string;
  subtitle?: string;
  showBackButton?: boolean;
  onBackClick?: () => void;
  rightAction?: {
    icon?: React.ComponentType<{ size?: number; className?: string }>;
    label: string;
    onClick: () => void;
  };
  className?: string;
}

const MobileHeader: React.FC<MobileHeaderProps> = ({
  title,
  subtitle,
  showBackButton = true,
  onBackClick,
  rightAction,
  className = ''
}) => {
  const navigate = useNavigate();

  const handleBackClick = () => {
    if (onBackClick) {
      onBackClick();
    } else {
      navigate(-1);
    }
  };

  return (
    <header 
      className={`sticky top-0 z-50 bg-white border-b border-gray-200 px-4 
        h-14 flex items-center justify-between ${className}`}
    >
      {/* Left side - Back button or spacer */}
      <div className="flex items-center min-w-0 flex-1">
        {showBackButton ? (
          <button
            onClick={handleBackClick}
            className="mr-3 p-2 -ml-2 text-gray-600 hover:text-gray-900 
              hover:bg-gray-100 rounded-lg transition-colors"
            aria-label="Go back"
          >
            <ArrowLeft size={20} />
          </button>
        ) : (
          <div className="w-8" /> // Spacer when no back button
        )}
        
        {/* Title and subtitle */}
        <div className="min-w-0 flex-1">
          <h1 className="text-lg font-semibold text-gray-900 truncate">
            {title}
          </h1>
          {subtitle && (
            <p className="text-xs text-gray-500 truncate mt-0.5">
              {subtitle}
            </p>
          )}
        </div>
      </div>

      {/* Right side - Action button */}
      <div className="flex items-center ml-3">
        {rightAction ? (
          <button
            onClick={rightAction.onClick}
            className="p-2 text-gray-600 hover:text-gray-900 
              hover:bg-gray-100 rounded-lg transition-colors"
            aria-label={rightAction.label}
          >
            {rightAction.icon ? (
              <rightAction.icon size={20} />
            ) : (
              <MoreVertical size={20} />
            )}
          </button>
        ) : (
          <div className="w-8" /> // Spacer when no action
        )}
      </div>
    </header>
  );
};

export default MobileHeader; 