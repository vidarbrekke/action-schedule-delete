import React, { useMemo, useCallback } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import { Home, Users, Gamepad2, Trophy } from 'lucide-react';

interface NavItem {
  id: string;
  label: string;
  icon: React.ComponentType<{ size?: number; className?: string }>;
  path: string;
  badge?: number;
}

interface BottomNavigationProps {
  className?: string;
}

const BottomNavigation: React.FC<BottomNavigationProps> = ({ className = '' }) => {
  const navigate = useNavigate();
  const location = useLocation();

  // Memoize navigation items to prevent recreation on every render
  const navItems: NavItem[] = useMemo(() => [
    { id: 'home', label: 'Home', icon: Home, path: '/' },
    { id: 'players', label: 'Players', icon: Users, path: '/players' },
    { id: 'game', label: 'Game', icon: Gamepad2, path: '/game' },
    { id: 'results', label: 'Results', icon: Trophy, path: '/results' },
  ], []);

  // Memoize route checking function
  const isActiveRoute = useCallback((path: string) => {
    if (path === '/') {
      return location.pathname === '/';
    }
    return location.pathname.startsWith(path);
  }, [location.pathname]);

  // Memoize navigation handler
  const handleNavigate = useCallback((path: string) => {
    navigate(path);
  }, [navigate]);

  // Memoize active states to prevent recalculation
  const activeStates = useMemo(() => {
    return navItems.reduce((acc, item) => {
      acc[item.id] = isActiveRoute(item.path);
      return acc;
    }, {} as Record<string, boolean>);
  }, [navItems, isActiveRoute]);

  return (
    <nav 
      className={`fixed bottom-0 left-0 right-0 z-50 bg-white border-t border-gray-200 
        safe-area-inset-bottom ${className}`}
      style={{
        paddingBottom: 'max(env(safe-area-inset-bottom), 8px)',
      }}
    >
      <div className="flex">
        {navItems.map((item) => {
          const isActive = activeStates[item.id];
          
          return (
            <NavigationButton
              key={item.id}
              item={item}
              isActive={isActive}
              onNavigate={handleNavigate}
            />
          );
        })}
      </div>
    </nav>
  );
};

// Memoized navigation button component to prevent unnecessary re-renders
const NavigationButton = React.memo<{
  item: NavItem;
  isActive: boolean;
  onNavigate: (path: string) => void;
}>(({ item, isActive, onNavigate }) => {
  const handleClick = useCallback(() => {
    onNavigate(item.path);
  }, [item.path, onNavigate]);

  return (
    <button
      onClick={handleClick}
      className={`flex-1 flex flex-col items-center justify-center py-2 px-1 
        transition-colors duration-200 relative
        ${isActive 
          ? 'text-indigo-600' 
          : 'text-gray-400 hover:text-gray-600 active:text-gray-800'
        }`}
      style={{
        minHeight: '60px',
        WebkitTapHighlightColor: 'transparent',
      }}
      aria-label={`Navigate to ${item.label}`}
    >
      {/* Icon */}
      <div className="relative">
        <item.icon 
          size={24} 
          className={`transition-all duration-200 ${
            isActive ? 'scale-110' : 'scale-100'
          }`}
        />
        
        {/* Badge */}
        {item.badge && item.badge > 0 && (
          <div className="absolute -top-2 -right-2 bg-red-500 text-white 
            text-xs rounded-full h-5 w-5 flex items-center justify-center 
            font-medium">
            {item.badge > 99 ? '99+' : item.badge}
          </div>
        )}
        
        {/* Active indicator */}
        {isActive && (
          <div className="absolute -bottom-1 left-1/2 transform -translate-x-1/2 
            w-1 h-1 bg-indigo-600 rounded-full" />
        )}
      </div>
      
      {/* Label */}
      <span className={`text-xs font-medium mt-1 transition-colors duration-200 ${
        isActive ? 'text-indigo-600' : 'text-gray-500'
      }`}>
        {item.label}
      </span>
    </button>
  );
});

NavigationButton.displayName = 'NavigationButton';

export default BottomNavigation; 