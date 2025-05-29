import toast from 'react-hot-toast';
import { useMemo, useEffect, useState } from 'react';

// === TYPE DEFINITIONS ===
interface ToastOptions {
  duration?: number;
  position?: 'top-center' | 'top-right' | 'top-left' | 'bottom-center' | 'bottom-right' | 'bottom-left';
  style?: React.CSSProperties;
  id?: string;
  hasScoreInfo?: boolean;
}

interface GameToastTypes {
  playerFeedback: 'success' | 'warning' | 'error';
  gameStatus: 'round-start' | 'round-end' | 'game-over' | 'waiting';
  connection: 'lost' | 'restored' | 'reconnecting';
  score: 'update' | 'adjustment';
}

interface ToastConfig {
  mobile: {
    breakpoint: number;
    fontSize: string;
    padding: string;
    maxWidth: string;
    hapticEnabled: boolean;
  };
  desktop: {
    fontSize: string;
    padding: string;
    maxWidth: string;
  };
  durations: {
    success: number;
    error: number;
    warning: number;
    info: number;
    loading: number;
  };
  hapticPatterns: {
    success: number[];
    error: number[];
    warning: number[];
  };
  deduplication: {
    windowMs: number;
    maxActive: number;
  };
}

interface ToastState {
  isMobile: boolean;
  activeToasts: Set<string>;
  recentMessages: Map<string, number>;
}

// === CONFIGURATION ===
const TOAST_CONFIG: ToastConfig = {
  mobile: {
    breakpoint: 768,
    fontSize: '16px',
    padding: '16px 20px',
    maxWidth: '90vw',
    hapticEnabled: true,
  },
  desktop: {
    fontSize: '14px',
    padding: '12px 16px',
    maxWidth: '420px',
  },
  durations: {
    success: 3000,
    error: 5000,
    warning: 4000,
    info: 3000,
    loading: Infinity,
  },
  hapticPatterns: {
    success: [50],
    error: [100, 50, 100],
    warning: [75],
  },
  deduplication: {
    windowMs: 2000,
    maxActive: 5,
  },
};

// === UTILITY FUNCTIONS ===
const detectMobile = (): boolean => {
  return (
    window.innerWidth <= TOAST_CONFIG.mobile.breakpoint ||
    /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)
  );
};

const truncateMessage = (message: string, isMobile: boolean, maxLength?: number): string => {
  if (!isMobile) return message;
  
  const limit = maxLength || (isMobile ? 120 : 200);
  if (message.length <= limit) return message;
  
  return `${message.slice(0, limit - 3)}...`;
};

const generateToastKey = (content: string, type: string): string => {
  return `${type}:${content.slice(0, 50)}`; // Use first 50 chars for key
};

// === HOOK FOR TOAST STATE MANAGEMENT ===
export const useToastState = (): ToastState => {
  const [isMobile, setIsMobile] = useState(detectMobile);
  const [activeToasts] = useState(() => new Set<string>());
  const [recentMessages] = useState(() => new Map<string, number>());

  // Update mobile state on window resize
  useEffect(() => {
    const handleResize = () => {
      const newIsMobile = detectMobile();
      if (newIsMobile !== isMobile) {
        setIsMobile(newIsMobile);
      }
    };

    window.addEventListener('resize', handleResize);
    return () => window.removeEventListener('resize', handleResize);
  }, [isMobile]);

  // Cleanup old recent messages
  useEffect(() => {
    const cleanupInterval = setInterval(() => {
      const now = Date.now();
      const cutoff = now - TOAST_CONFIG.deduplication.windowMs;
      
      for (const [key, timestamp] of recentMessages.entries()) {
        if (timestamp < cutoff) {
          recentMessages.delete(key);
        }
      }
    }, TOAST_CONFIG.deduplication.windowMs);

    return () => clearInterval(cleanupInterval);
  }, [recentMessages]);

  return { isMobile, activeToasts, recentMessages };
};

// === OPTIMIZED TOAST SERVICE ===
class OptimizedToastService {
  private styleCache = new Map<string, React.CSSProperties>();
  private throttledHaptic: ((type: keyof typeof TOAST_CONFIG.hapticPatterns) => void) | null = null;

  constructor() {
    // Initialize throttled haptic feedback
    this.throttledHaptic = this.createThrottledHaptic();
  }

  private createThrottledHaptic() {
    let lastHaptic = 0;
    const throttleMs = 100;

    return (type: keyof typeof TOAST_CONFIG.hapticPatterns) => {
      const now = Date.now();
      if (now - lastHaptic < throttleMs) return;
      
      lastHaptic = now;
      this.triggerHapticFeedback(type);
    };
  }

  private triggerHapticFeedback(type: keyof typeof TOAST_CONFIG.hapticPatterns) {
    if (!TOAST_CONFIG.mobile.hapticEnabled || !('vibrate' in navigator)) return;
    
    const pattern = TOAST_CONFIG.hapticPatterns[type];
    navigator.vibrate(pattern);
  }

  private getToastStyle(type: 'success' | 'error' | 'warning' | 'info', isMobile: boolean): React.CSSProperties {
    const cacheKey = `${type}-${isMobile}`;
    
    if (this.styleCache.has(cacheKey)) {
      return this.styleCache.get(cacheKey)!;
    }

    const config = isMobile ? TOAST_CONFIG.mobile : TOAST_CONFIG.desktop;
    
    const baseStyle: React.CSSProperties = {
      fontSize: config.fontSize,
      padding: config.padding,
      maxWidth: config.maxWidth,
      lineHeight: '1.4',
      borderRadius: '12px',
      wordBreak: 'break-word',
      fontWeight: '500',
      // Mobile optimizations
      ...(isMobile && {
        transform: 'translateZ(0)',
        backfaceVisibility: 'hidden',
        willChange: 'transform, opacity',
      }),
    };

    const typeStyles = {
      success: {
        background: '#065f46',
        color: '#ecfdf5',
        border: '1px solid #10b981',
      },
      error: {
        background: '#7f1d1d',
        color: '#fef2f2',
        border: '1px solid #ef4444',
      },
      warning: {
        background: '#f59e0b',
        color: '#ffffff',
        border: '1px solid #d97706',
      },
      info: {
        background: '#1e40af',
        color: '#dbeafe',
        border: '1px solid #3b82f6',
      },
    };

    const style = { ...baseStyle, ...typeStyles[type] };
    this.styleCache.set(cacheKey, style);
    
    return style;
  }

  private isDuplicate(content: string, type: string, recentMessages: Map<string, number>): boolean {
    const key = generateToastKey(content, type);
    const lastTime = recentMessages.get(key);
    
    if (lastTime && Date.now() - lastTime < TOAST_CONFIG.deduplication.windowMs) {
      return true;
    }
    
    recentMessages.set(key, Date.now());
    return false;
  }

  private limitActiveToasts(activeToasts: Set<string>) {
    if (activeToasts.size >= TOAST_CONFIG.deduplication.maxActive) {
      const oldestId = activeToasts.values().next().value;
      toast.dismiss(oldestId);
      if (typeof oldestId === 'string') {
        activeToasts.delete(oldestId);
      }
    }
  }

  // === PUBLIC API ===
  success(message: string, options: ToastOptions = {}, toastState?: ToastState): string | null {
    const { isMobile, activeToasts, recentMessages } = toastState || { 
      isMobile: detectMobile(), 
      activeToasts: new Set(), 
      recentMessages: new Map() 
    };
    
    const truncatedMessage = truncateMessage(message, isMobile);
    
    if (this.isDuplicate(truncatedMessage, 'success', recentMessages)) {
      return null;
    }

    this.limitActiveToasts(activeToasts);
    this.throttledHaptic?.('success');
    
    const toastId = toast.success(truncatedMessage, {
      duration: isMobile ? TOAST_CONFIG.durations.success + 1000 : TOAST_CONFIG.durations.success,
      style: { ...this.getToastStyle('success', isMobile), ...options.style },
      ...options,
    });

    activeToasts.add(toastId);
    return toastId;
  }

  error(message: string, options: ToastOptions = {}, toastState?: ToastState): string | null {
    const { isMobile, activeToasts, recentMessages } = toastState || { 
      isMobile: detectMobile(), 
      activeToasts: new Set(), 
      recentMessages: new Map() 
    };
    
    const truncatedMessage = truncateMessage(message, isMobile, 150);
    
    if (this.isDuplicate(truncatedMessage, 'error', recentMessages)) {
      return null;
    }

    this.limitActiveToasts(activeToasts);
    this.throttledHaptic?.('error');
    
    const toastId = toast.error(truncatedMessage, {
      duration: isMobile ? TOAST_CONFIG.durations.error + 1000 : TOAST_CONFIG.durations.error,
      style: { ...this.getToastStyle('error', isMobile), ...options.style },
      ...options,
    });

    activeToasts.add(toastId);
    return toastId;
  }

  loading(message: string, options: ToastOptions = {}, toastState?: ToastState): string {
    const { isMobile, activeToasts } = toastState || { 
      isMobile: detectMobile(), 
      activeToasts: new Set(), 
      recentMessages: new Map() 
    };
    
    const truncatedMessage = truncateMessage(message, isMobile);
    
    this.limitActiveToasts(activeToasts);
    
    const toastId = toast.loading(truncatedMessage, {
      style: { ...this.getToastStyle('info', isMobile), ...options.style },
      ...options,
    });

    activeToasts.add(toastId);
    return toastId;
  }

  quickFeedback(message: string, type: GameToastTypes['playerFeedback'] = 'success', options?: { hasScoreInfo?: boolean }): string {
    const isMobile = detectMobile();
    
    // Enhanced: Don't truncate messages containing score information
    const shouldPreserveMessage = options?.hasScoreInfo || /(\d+\s*points?|score|earned|total)/i.test(message);
    const charLimit = shouldPreserveMessage ? 200 : (type === 'warning' ? 150 : 80);
    const truncatedMessage = truncateMessage(message, isMobile, charLimit);
    
    if (type !== 'error') {
      this.throttledHaptic?.(type === 'warning' ? 'warning' : 'success');
    }
    
    const icons = { success: '✅', warning: '⚠️', error: '❌' };
    
    // Use longer duration for messages with score info or warnings
    const baseDuration = (shouldPreserveMessage || type === 'warning') ? TOAST_CONFIG.durations.warning : TOAST_CONFIG.durations.info;
    const duration = isMobile ? baseDuration + 1000 : baseDuration;
    
    const toastId = toast(truncatedMessage, {
      duration,
      style: this.getToastStyle(type === 'error' ? 'error' : type === 'warning' ? 'warning' : 'success', isMobile),
      icon: icons[type],
    });

    return toastId;
  }

  scoreUpdate(playerName: string, oldScore: number, newScore: number, toastState?: ToastState): string | null {
    const { isMobile, activeToasts, recentMessages } = toastState || { 
      isMobile: detectMobile(), 
      activeToasts: new Set(), 
      recentMessages: new Map() 
    };
    
    const scoreDiff = newScore - oldScore;
    const sign = scoreDiff > 0 ? '+' : '';
    
    const message = isMobile 
      ? `${playerName}: ${oldScore} → ${newScore} (${sign}${scoreDiff})`
      : `${playerName}'s score: ${oldScore} → ${newScore} (${sign}${scoreDiff})`;
    
    if (this.isDuplicate(message, 'score', recentMessages)) {
      return null;
    }

    this.limitActiveToasts(activeToasts);
    this.throttledHaptic?.('success');

    const toastId = toast.success(message, {
      duration: isMobile ? TOAST_CONFIG.durations.success + 1000 : TOAST_CONFIG.durations.success,
      style: { 
        ...this.getToastStyle('success', isMobile),
        fontWeight: '600',
      },
      icon: '🎯',
    });

    activeToasts.add(toastId);
    return toastId;
  }

  gameStatus(message: string, type: GameToastTypes['gameStatus'] = 'waiting', options?: { hasScoreInfo?: boolean }): string {
    const isMobile = detectMobile();
    
    // Enhanced: Don't truncate messages containing score information
    const shouldPreserveMessage = options?.hasScoreInfo || /(\d+\s*points?|score|earned|total)/i.test(message);
    const charLimit = shouldPreserveMessage ? 200 : 100;
    const truncatedMessage = truncateMessage(message, isMobile, charLimit);
    
    const config = {
      'round-start': { icon: '🎵', haptic: 'warning' as const },
      'round-end': { icon: '⏹️', haptic: 'success' as const },
      'game-over': { icon: '🏁', haptic: 'success' as const },
      'waiting': { icon: '⏳', haptic: null },
    };
    
    const { icon, haptic } = config[type];
    
    if (haptic) {
      this.throttledHaptic?.(haptic);
    }
    
    const toastId = toast(truncatedMessage, {
      duration: shouldPreserveMessage ? TOAST_CONFIG.durations.warning : TOAST_CONFIG.durations.info,
      icon,
      style: this.getToastStyle('info', isMobile),
    });

    return toastId;
  }

  dismiss(toastId: string, activeToasts?: Set<string>) {
    toast.dismiss(toastId);
    activeToasts?.delete(toastId);
  }

  dismissAll(activeToasts?: Set<string>) {
    toast.dismiss();
    activeToasts?.clear();
  }

  // Cleanup method
  destroy() {
    this.styleCache.clear();
    this.throttledHaptic = null;
  }
}

// === SINGLETON INSTANCE ===
export const toastService = new OptimizedToastService();

// === HOOK FOR COMPONENT USAGE ===
export const useOptimizedToast = () => {
  const toastState = useToastState();
  
  const optimizedToast = useMemo(() => ({
    success: (message: string, options?: ToastOptions) => 
      toastService.success(message, options, toastState),
    error: (message: string, options?: ToastOptions) => 
      toastService.error(message, options, toastState),
    loading: (message: string, options?: ToastOptions) => 
      toastService.loading(message, options, toastState),
    quickFeedback: (message: string, type?: GameToastTypes['playerFeedback'], options?: { hasScoreInfo?: boolean }) => 
      toastService.quickFeedback(message, type, options),
    scoreUpdate: (playerName: string, oldScore: number, newScore: number) => 
      toastService.scoreUpdate(playerName, oldScore, newScore, toastState),
    gameStatus: (message: string, type?: GameToastTypes['gameStatus'], options?: { hasScoreInfo?: boolean }) => 
      toastService.gameStatus(message, type, options),
    dismiss: (toastId: string) => toastService.dismiss(toastId, toastState.activeToasts),
    dismissAll: () => toastService.dismissAll(toastState.activeToasts),
  }), [toastState]);

  return { 
    ...optimizedToast, 
    toastState,
    isMobile: toastState.isMobile,
    activeCount: toastState.activeToasts.size,
  };
}; 