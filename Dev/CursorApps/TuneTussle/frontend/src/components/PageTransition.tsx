import React from 'react';
import { motion, AnimatePresence } from 'framer-motion';

interface PageTransitionProps {
  children: React.ReactNode;
  /**
   * Optionally provide a unique key for AnimatePresence to trigger exit/enter animations.
   * If not provided, the parent should handle keying.
   */
  transitionKey?: string | number;
}

const variants = {
  initial: {
    opacity: 0,
    y: 24,
  },
  animate: {
    opacity: 1,
    y: 0,
    transition: { duration: 0.35, ease: 'easeOut' },
  },
  exit: {
    opacity: 0,
    y: -24,
    transition: { duration: 0.25, ease: 'easeIn' },
  },
};

const PageTransition: React.FC<PageTransitionProps> = ({ children, transitionKey }) => (
  <AnimatePresence mode="wait">
    <motion.div
      key={transitionKey}
      variants={variants}
      initial="initial"
      animate="animate"
      exit="exit"
      style={{ minHeight: '100vh' }}
    >
      {children}
    </motion.div>
  </AnimatePresence>
);

export default PageTransition; 