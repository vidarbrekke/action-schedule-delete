import React from 'react';
import MobileHeader from './MobileHeader';
import BottomNavigation from './BottomNavigation';

interface MobileAppLayoutProps {
  children: React.ReactNode;
  title: string;
  subtitle?: string;
  showHeader?: boolean;
  showBottomNav?: boolean;
  showBackButton?: boolean;
  onBackClick?: () => void;
  rightAction?: {
    icon?: React.ComponentType<{ size?: number; className?: string }>;
    label: string;
    onClick: () => void;
  };
  className?: string;
}

const MobileAppLayout: React.FC<MobileAppLayoutProps> = ({
  children,
  title,
  subtitle,
  showHeader = true,
  showBottomNav = true,
  showBackButton = true,
  onBackClick,
  rightAction,
  className = ''
}) => {
  return (
    <div className={`min-h-screen bg-gray-50 ${className}`}>
      {/* Header */}
      {showHeader && (
        <MobileHeader
          title={title}
          subtitle={subtitle}
          showBackButton={showBackButton}
          onBackClick={onBackClick}
          rightAction={rightAction}
        />
      )}

      {/* Main Content Area */}
      <main 
        className={`flex-1 ${showHeader ? 'pt-0' : 'pt-0'} ${showBottomNav ? 'pb-20' : 'pb-4'}`}
        style={{
          minHeight: showHeader 
            ? 'calc(100vh - 56px - 80px)' // Header height - bottom nav height
            : showBottomNav 
              ? 'calc(100vh - 80px)' // Just bottom nav
              : '100vh'
        }}
      >
        {children}
      </main>

      {/* Bottom Navigation */}
      {showBottomNav && <BottomNavigation />}
    </div>
  );
};

export default MobileAppLayout; 