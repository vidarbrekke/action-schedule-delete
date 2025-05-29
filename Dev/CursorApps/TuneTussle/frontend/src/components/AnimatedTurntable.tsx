import React, { useMemo } from 'react';
import { motion } from 'framer-motion';

interface AnimatedTurntableProps {
  size?: number;           // diameter in px
  speed?: number;          // vinyl spin speed in seconds per rotation
  tonearmDelay?: number;   // delay before tonearm drop (s)
  isPlaying?: boolean;     // whether music is currently playing
  className?: string;      // additional CSS classes
}

// Configuration constants
const TURNTABLE_CONFIG = {
  viewBox: { width: 280, height: 200 },
  aspectRatio: 1.4,
  slate: { x: 30, y: 10, width: 208, height: 192, rx: 8 },
  record: { cx: 120, cy: 100, radius: 65 },
  tonearm: {
    base: { x: 216, y: 106 },
    length: 56.4,
    angles: { lifted: 0, playing: -120 }
  },
  reflection: {
    path: "M 84 65 A 50 50 0 0 1 139 55 A 35 35 0 0 0 84 65 Z",
    opacity: { min: 0.15, max: 0.4, base: 0.25 },
    wiggle: { amplitude: 0.5, duration: 3 }
  }
} as const;

// Custom hook for tonearm calculations
const useTonearmAnimation = (isPlaying: boolean, tonearmDelay: number) => {
  const positions = useMemo(() => {
    const { base, length, angles } = TURNTABLE_CONFIG.tonearm;
    
    const liftedEndpoint = {
      x: base.x,
      y: base.y - length
    };
    
    const playingAngle = angles.playing * (Math.PI / 180);
    const playingEndpoint = {
      x: base.x + length * Math.sin(playingAngle),
      y: base.y + length * Math.cos(playingAngle)
    };
    
    return { lifted: liftedEndpoint, playing: playingEndpoint };
  }, []);

  const animationProps = useMemo(() => ({
    initial: { x2: positions.lifted.x, y2: positions.lifted.y },
    animate: {
      x2: isPlaying ? positions.playing.x : positions.lifted.x,
      y2: isPlaying ? positions.playing.y : positions.lifted.y
    },
    transition: {
      duration: isPlaying ? 1 : 0.8,
      delay: isPlaying ? tonearmDelay : 0,
      ease: "easeInOut" as const
    }
  }), [isPlaying, tonearmDelay, positions]);

  return { positions, animationProps };
};

// Custom hook for vinyl animation variants
const useVinylAnimation = (speed: number) => {
  return useMemo(() => ({
    spinning: {
      rotate: 360,
      transition: {
        duration: speed,
        repeat: Infinity,
        ease: "linear" as const
      }
    },
    stopped: {
      rotate: 0,
      transition: {
        duration: 0.5,
        ease: "easeOut" as const
      }
    }
  }), [speed]);
};

// Custom hook for reflection animation
const useReflectionAnimation = () => {
  return useMemo(() => {
    const { opacity, wiggle } = TURNTABLE_CONFIG.reflection;
    return {
      animate: {
        opacity: [opacity.base, opacity.min, opacity.max, opacity.base],
        x: [0, wiggle.amplitude, -wiggle.amplitude, 0]
      },
      transition: {
        duration: wiggle.duration,
        repeat: Infinity,
        ease: "easeInOut" as const
      }
    };
  }, []);
};

const AnimatedTurntable: React.FC<AnimatedTurntableProps> = ({
  size = 200,
  speed = 2,
  tonearmDelay = 1,
  isPlaying = false,
  className = ''
}) => {
  const { positions, animationProps } = useTonearmAnimation(isPlaying, tonearmDelay);
  const vinylVariants = useVinylAnimation(speed);
  const reflectionAnimation = useReflectionAnimation();

  const containerStyle = useMemo(() => ({
    width: size * TURNTABLE_CONFIG.aspectRatio,
    height: size
  }), [size]);

  const svgDimensions = useMemo(() => ({
    width: size * TURNTABLE_CONFIG.aspectRatio,
    height: size,
    viewBox: `0 0 ${TURNTABLE_CONFIG.viewBox.width} ${TURNTABLE_CONFIG.viewBox.height}`
  }), [size]);

  return (
    <div
      className={`inline-block ${className}`}
      style={containerStyle}
      role="img"
      aria-label="Animated turntable"
    >
      <div className="relative">
        <svg
          {...svgDimensions}
          xmlns="http://www.w3.org/2000/svg"
          className="turntable"
        >
          {/* Gray slate base */}
          <rect
            {...TURNTABLE_CONFIG.slate}
            fill="#4a5568"
            stroke="#2d3748"
            strokeWidth="2"
          />
          
          {/* Turntable base */}
          <circle
            {...TURNTABLE_CONFIG.record}
            r="75"
            fill="#2d3748"
            stroke="#1a202c"
            strokeWidth="2"
          />
          
          {/* Animated vinyl record */}
          <motion.g
            variants={vinylVariants}
            animate={isPlaying ? "spinning" : "stopped"}
            style={{ transformOrigin: `${TURNTABLE_CONFIG.record.cx}px ${TURNTABLE_CONFIG.record.cy}px` }}
            className="vinyl-record"
          >
            {/* Main record */}
            <circle {...TURNTABLE_CONFIG.record} r="65" fill="#1a202c" />
            
            {/* Record grooves */}
            {[55, 45, 35, 25].map(radius => (
              <circle
                key={radius}
                {...TURNTABLE_CONFIG.record}
                r={radius}
                fill="none"
                stroke="#2d3748"
                strokeWidth="0.5"
              />
            ))}
            
            {/* Center label */}
            <circle {...TURNTABLE_CONFIG.record} r="20" fill="#e53e3e" />
            <circle {...TURNTABLE_CONFIG.record} r="16" fill="#c53030" />
            <text
              x={TURNTABLE_CONFIG.record.cx}
              y={TURNTABLE_CONFIG.record.cy + 3}
              textAnchor="middle"
              fill="white"
              fontSize="7"
              fontWeight="bold"
            >
              TUNE
            </text>
            <text
              x={TURNTABLE_CONFIG.record.cx}
              y={TURNTABLE_CONFIG.record.cy + 12}
              textAnchor="middle"
              fill="white"
              fontSize="5"
            >
              TUSSLE
            </text>
            
            {/* Center hole */}
            <circle {...TURNTABLE_CONFIG.record} r="3" fill="#1a202c" />
          </motion.g>
          
          {/* Tonearm base */}
          <circle 
            cx={TURNTABLE_CONFIG.tonearm.base.x} 
            cy={TURNTABLE_CONFIG.tonearm.base.y} 
            r="8" 
            fill="#4a5568" 
          />
          <circle 
            cx={TURNTABLE_CONFIG.tonearm.base.x} 
            cy={TURNTABLE_CONFIG.tonearm.base.y} 
            r="5" 
            fill="#718096" 
          />
          
          {/* Animated tonearm */}
          <g className="tonearm">
            <motion.line
              x1={TURNTABLE_CONFIG.tonearm.base.x}
              y1={TURNTABLE_CONFIG.tonearm.base.y}
              {...animationProps}
              stroke="#718096"
              strokeWidth="3"
              strokeLinecap="round"
            />
            
            <motion.circle
              initial={{ cx: positions.lifted.x, cy: positions.lifted.y }}
              animate={{
                cx: isPlaying ? positions.playing.x : positions.lifted.x,
                cy: isPlaying ? positions.playing.y : positions.lifted.y
              }}
              transition={animationProps.transition}
              r="3"
              fill="#e53e3e"
            />
          </g>
          
          {/* Static reflection highlight */}
          <motion.path
            d={TURNTABLE_CONFIG.reflection.path}
            fill="#ffffff"
            transform="rotate(10 120 100)"
            {...reflectionAnimation}
          />
        </svg>
      </div>
    </div>
  );
};

export default AnimatedTurntable; 