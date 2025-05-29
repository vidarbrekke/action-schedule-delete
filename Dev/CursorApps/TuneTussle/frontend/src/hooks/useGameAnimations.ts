import { useState, useRef, useCallback } from 'react';

export function useGameAnimations() {
  // Confetti
  const [showConfetti, setShowConfetti] = useState(false);
  const triggerConfetti = useCallback(() => {
    setShowConfetti(true);
    setTimeout(() => setShowConfetti(false), 3000);
  }, []);

  // Shake
  const [shakeInput, setShakeInput] = useState(false);
  const shakeTimeoutRef = useRef<NodeJS.Timeout | null>(null);
  const triggerShake = useCallback(() => {
    setShakeInput(true);
    if (shakeTimeoutRef.current) clearTimeout(shakeTimeoutRef.current);
    shakeTimeoutRef.current = setTimeout(() => setShakeInput(false), 500);
  }, []);

  // Pulse (expandable for future use)
  const [pulse, setPulse] = useState(false);
  const triggerPulse = useCallback(() => {
    setPulse(true);
    setTimeout(() => setPulse(false), 500);
  }, []);

  return {
    showConfetti, triggerConfetti,
    shakeInput, triggerShake,
    pulse, triggerPulse,
  };
} 