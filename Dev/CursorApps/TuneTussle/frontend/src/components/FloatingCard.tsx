import React from 'react';

interface FloatingCardProps {
  children: React.ReactNode;
  className?: string;
  padding?: 'sm' | 'md' | 'lg';
  shadow?: 'sm' | 'md' | 'lg' | 'xl';
}

const FloatingCard: React.FC<FloatingCardProps> = ({
  children,
  className = '',
  padding = 'md',
  shadow = 'md'
}) => {
  const paddingClasses = {
    sm: 'p-4',
    md: 'p-6',
    lg: 'p-8'
  };

  const shadowClasses = {
    sm: 'shadow-sm',
    md: 'shadow-lg',
    lg: 'shadow-xl',
    xl: 'shadow-2xl'
  };

  return (
    <div 
      className={`bg-white rounded-2xl border border-gray-100 ${paddingClasses[padding]} 
        ${shadowClasses[shadow]} backdrop-blur-sm ${className}`}
      style={{
        // Enhanced native app-like shadows
        boxShadow: shadow === 'xl' 
          ? '0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04)'
          : shadow === 'lg'
          ? '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05)'
          : shadow === 'md'
          ? '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06)'
          : '0 1px 2px 0 rgba(0, 0, 0, 0.05)',
      }}
    >
      {children}
    </div>
  );
};

export default FloatingCard; 