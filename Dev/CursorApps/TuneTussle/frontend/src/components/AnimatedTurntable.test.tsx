import { render } from '@testing-library/react';
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import AnimatedTurntable from './AnimatedTurntable';

// Mock framer-motion to avoid animation issues in tests
vi.mock('framer-motion', () => ({
  motion: {
    div: ({ children, ...props }: { children?: React.ReactNode; [key: string]: unknown }) => <div {...props}>{children}</div>,
    g: ({ children, ...props }: { children?: React.ReactNode; [key: string]: unknown }) => <g {...props}>{children}</g>,
    line: ({ children, ...props }: { children?: React.ReactNode; [key: string]: unknown }) => <line {...props}>{children}</line>,
    circle: ({ children, ...props }: { children?: React.ReactNode; [key: string]: unknown }) => <circle {...props}>{children}</circle>,
    path: ({ children, ...props }: { children?: React.ReactNode; [key: string]: unknown }) => <path {...props}>{children}</path>,
  },
}));

describe('AnimatedTurntable', () => {
  beforeEach(() => {
    // Reset any mocks before each test
  });

  afterEach(() => {
    // Clean up after each test
  });

  describe('Rendering', () => {
    it('renders without crashing', () => {
      const { container } = render(<AnimatedTurntable />);
      expect(container.querySelector('svg')).toBeInTheDocument();
    });

    it('renders with default props', () => {
      const { container } = render(<AnimatedTurntable />);
      const turntableContainer = container.firstChild as HTMLElement;
      
      expect(turntableContainer).toHaveStyle({
        width: '280px', // 200 * 1.4
        height: '200px'
      });
    });

    it('renders with custom size', () => {
      const customSize = 300;
      const { container } = render(<AnimatedTurntable size={customSize} />);
      const turntableContainer = container.firstChild as HTMLElement;
      
      expect(turntableContainer).toHaveStyle({
        width: '420px', // 300 * 1.4
        height: '300px'
      });
    });

    it('applies custom className', () => {
      const customClass = 'custom-turntable-class';
      const { container } = render(<AnimatedTurntable className={customClass} />);
      const turntableContainer = container.firstChild as HTMLElement;
      
      expect(turntableContainer).toHaveClass(customClass);
    });
  });

  describe('SVG Elements', () => {
    it('renders all required SVG elements', () => {
      const { container } = render(<AnimatedTurntable />);
      
      // Check for SVG elements
      const svgElements = container.querySelectorAll('svg');
      expect(svgElements.length).toBeGreaterThan(0);
      
      // Check for vinyl record elements
      const circles = container.querySelectorAll('circle');
      expect(circles.length).toBeGreaterThan(0);
      
      // Check for tonearm elements
      const lines = container.querySelectorAll('line');
      expect(lines.length).toBeGreaterThan(0);
      
      // Check for text elements (TUNE TUSSLE label)
      const textElements = container.querySelectorAll('text');
      expect(textElements.length).toBe(2); // Only one set now
    });

    it('displays TUNE TUSSLE branding', () => {
      const { container } = render(<AnimatedTurntable />);
      const textElements = container.querySelectorAll('text');
      
      // Should have TUNE and TUSSLE text in the center label
      const textContent = Array.from(textElements).map(el => el.textContent);
      expect(textContent).toContain('TUNE');
      expect(textContent).toContain('TUSSLE');
    });

    it('renders power indicator with correct color when not playing', () => {
      const { container } = render(<AnimatedTurntable isPlaying={false} />);
      const powerIndicators = container.querySelectorAll('.power-indicator');
      
      powerIndicators.forEach(indicator => {
        expect(indicator).toHaveAttribute('fill', '#a0aec0'); // Gray when not playing
      });
    });

    it('renders power indicator with correct color when playing', () => {
      const { container } = render(<AnimatedTurntable isPlaying={true} />);
      const powerIndicators = container.querySelectorAll('.power-indicator');
      
      powerIndicators.forEach(indicator => {
        expect(indicator).toHaveAttribute('fill', '#48bb78'); // Green when playing
      });
    });
  });

  describe('Animation States', () => {
    it('sets up vinyl animation for spinning state', () => {
      const { container } = render(<AnimatedTurntable isPlaying={true} />);
      const vinylRecord = container.querySelector('.vinyl-record');
      
      expect(vinylRecord).toBeInTheDocument();
    });

    it('sets up vinyl animation for stopped state', () => {
      const { container } = render(<AnimatedTurntable isPlaying={false} />);
      const vinylRecord = container.querySelector('.vinyl-record');
      
      expect(vinylRecord).toBeInTheDocument();
    });

    it('sets up tonearm animation for playing state', () => {
      const { container } = render(<AnimatedTurntable isPlaying={true} />);
      const tonearm = container.querySelector('.tonearm');
      
      expect(tonearm).toBeInTheDocument();
    });

    it('sets up tonearm animation for lifted state', () => {
      const { container } = render(<AnimatedTurntable isPlaying={false} />);
      const tonearm = container.querySelector('.tonearm');
      
      expect(tonearm).toBeInTheDocument();
    });
  });

  describe('Props Handling', () => {
    it('handles speed prop correctly', () => {
      const customSpeed = 3;
      const { container } = render(<AnimatedTurntable speed={customSpeed} isPlaying={true} />);
      
      // Component should render without errors with custom speed
      expect(container.querySelector('svg')).toBeInTheDocument();
    });

    it('handles tonearmDelay prop correctly', () => {
      const customDelay = 2;
      const { container } = render(<AnimatedTurntable tonearmDelay={customDelay} isPlaying={true} />);
      
      // Component should render without errors with custom delay
      expect(container.querySelector('svg')).toBeInTheDocument();
    });

    it('handles all props together', () => {
      const props = {
        size: 250,
        speed: 1.5,
        tonearmDelay: 0.5,
        isPlaying: true,
        className: 'test-class'
      };
      
      const { container } = render(<AnimatedTurntable {...props} />);
      const turntableContainer = container.firstChild as HTMLElement;
      
      expect(turntableContainer).toHaveStyle({
        width: '350px', // 250 * 1.4
        height: '250px'
      });
      expect(turntableContainer).toHaveClass('test-class');
    });
  });

  describe('Accessibility', () => {
    it('provides appropriate structure for screen readers', () => {
      const { container } = render(<AnimatedTurntable />);
      
      // SVG elements should be present and properly structured
      const svgElements = container.querySelectorAll('svg');
      expect(svgElements.length).toBeGreaterThan(0);
      
      // Text elements should be readable
      const textElements = container.querySelectorAll('text');
      expect(textElements.length).toBeGreaterThan(0);
    });
  });

  describe('Performance', () => {
    it('renders efficiently with multiple instances', () => {
      const { container } = render(
        <div>
          <AnimatedTurntable size={100} />
          <AnimatedTurntable size={150} />
          <AnimatedTurntable size={200} />
        </div>
      );
      
      // Should render all instances without issues
      const turntables = container.querySelectorAll('svg');
      expect(turntables.length).toBe(3); // 3 turntables × 1 SVG each
    });
  });
}); 