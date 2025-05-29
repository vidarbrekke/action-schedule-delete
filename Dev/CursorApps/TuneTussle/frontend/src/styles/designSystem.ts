// Design System - Reusable styling patterns for consistent UI
// Following DRY and YAGNI principles

// Color Palette
export const colors = {
  // Primary gradient colors
  primary: {
    50: 'from-indigo-50 to-blue-50',
    100: 'from-indigo-100 to-blue-100', 
    500: 'from-indigo-500 to-blue-500',
    600: 'from-indigo-600 to-blue-600',
    700: 'from-indigo-700 to-blue-700',
  },
  
  // Hero/Results gradients
  hero: {
    primary: 'from-indigo-500 via-purple-600 to-indigo-700',
    results: 'from-purple-600 to-indigo-700',
    game: 'from-blue-100 to-indigo-200',
  },
  
  // Text colors
  text: {
    primary: 'text-gray-900',
    secondary: 'text-gray-600',
    muted: 'text-gray-500',
    light: 'text-gray-400',
    white: 'text-white',
    hero: 'text-indigo-100',
  },
  
  // Status colors
  status: {
    success: 'text-green-700 bg-green-50 border-green-200',
    warning: 'text-orange-700 bg-orange-50 border-orange-200',
    error: 'text-red-700 bg-red-50 border-red-200',
    info: 'text-blue-700 bg-blue-50 border-blue-200',
  },
  
  // Interactive elements
  interactive: {
    purple: 'text-purple-700 bg-purple-50 border-purple-200',
    indigo: 'text-indigo-700 bg-indigo-50 border-indigo-200',
  },

  // Winner/celebration colors
  winner: {
    text: 'text-yellow-300',
    bg: 'text-yellow-100',
    accent: 'text-yellow-200',
  }
};

// Typography
export const typography = {
  heading: {
    hero: 'text-6xl font-extrabold tracking-tight',
    xl: 'text-3xl font-bold',
    lg: 'text-2xl font-bold',
    md: 'text-xl font-bold',
    sm: 'text-lg font-semibold',
  },
  
  body: {
    base: 'text-base',
    sm: 'text-sm',
    xs: 'text-xs',
    lg: 'text-lg',
    xl: 'text-xl',
    '2xl': 'text-2xl',
    '3xl': 'text-3xl',
    '4xl': 'text-4xl',
  },
  
  weight: {
    extrabold: 'font-extrabold',
    bold: 'font-bold',
    semibold: 'font-semibold',
    medium: 'font-medium',
    normal: 'font-normal',
  }
};

// Layout & Spacing
export const layout = {
  container: {
    page: 'min-h-screen bg-gray-50',
    centered: 'flex flex-col justify-center min-h-full',
    card: 'w-full max-w-md',
    hero: 'min-h-screen flex flex-col items-center justify-center p-4',
    results: 'min-h-screen flex flex-col items-center justify-center p-4 text-white',
  },
  
  spacing: {
    section: 'space-y-6',
    component: 'space-y-4',
    tight: 'space-y-3',
    loose: 'space-y-8',
    hero: 'space-y-8',
  },
  
  padding: {
    page: 'p-4',
    card: 'p-6',
    compact: 'p-3',
    hero: 'px-4 py-8',
  }
};

// Components
export const components = {
  // Cards and containers
  card: {
    base: 'bg-white rounded-lg border border-gray-200 shadow-lg',
    floating: 'border-0 shadow-lg bg-white',
    gradient: 'bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg border border-gray-200',
    glass: 'bg-white/20 backdrop-blur-md rounded-xl shadow-2xl',
    glassMuted: 'bg-white/10 backdrop-blur-sm rounded-xl shadow-lg',
  },
  
  // Form elements
  input: {
    base: `w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none 
          focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-base
          disabled:bg-gray-100 disabled:text-gray-500 transition-colors`,
    hero: `w-full px-4 py-4 border-2 border-gray-200 rounded-xl focus:outline-none 
           focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-lg
           bg-gray-50 transition-all duration-200`,
    heroCode: `w-full px-4 py-4 border-2 border-gray-200 rounded-xl focus:outline-none 
               focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-lg font-medium
               bg-gray-50 transition-all duration-200`,
  },
  
  // Buttons
  button: {
    primary: `w-full py-3 px-4 bg-gradient-to-r from-indigo-600 to-blue-600 
             hover:from-indigo-700 hover:to-blue-700 disabled:from-gray-400 disabled:to-gray-400
             text-white font-semibold rounded-lg focus:outline-none focus:ring-2 
             focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200
             shadow-lg hover:shadow-xl transform hover:-translate-y-0.5`,
    
    hero: `w-full py-4 px-6 bg-indigo-600 hover:bg-indigo-700 disabled:bg-gray-400
           text-white font-bold text-lg rounded-xl focus:outline-none focus:ring-2 
           focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200
           shadow-lg hover:shadow-xl transform hover:scale-[1.02] active:scale-[0.98]`,
    
    secondary: `px-4 py-2 bg-white border border-gray-300 text-gray-700 font-medium 
               rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 
               focus:ring-indigo-500 transition-colors`,
    
    results: `flex-1 font-semibold py-3 px-6 rounded-lg shadow-md hover:shadow-lg 
              transition-all duration-150 ease-in-out transform hover:scale-105 
              focus:outline-none focus:ring-2 focus:ring-offset-2`,
    
    resultsGreen: `bg-green-500 hover:bg-green-600 text-white focus:ring-green-400`,
    resultsBlue: `bg-blue-500 hover:bg-blue-600 text-white focus:ring-blue-400`,
    
    disabled: 'disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none',
  },
  
  // Badges and tags
  badge: {
    base: 'inline-flex items-center px-3 py-1 rounded-full text-sm font-medium',
    code: 'px-3 py-1 text-sm font-mono bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-lg',
    role: 'px-2 py-1 text-xs font-medium bg-purple-50 text-purple-700 border border-purple-200 rounded-md',
    status: 'px-3 py-1 text-sm font-medium bg-green-50 text-green-700 border border-green-200 rounded-full',
  },
  
  // Icons and avatars
  icon: {
    container: 'inline-flex items-center justify-center rounded-xl',
    hero: 'inline-flex items-center justify-center rounded-full',
    sm: 'w-12 h-12',
    md: 'w-16 h-16',
    lg: 'w-20 h-20',
    heroSm: 'w-10 h-10',
    heroMd: 'w-12 h-12',
    heroLg: 'w-16 h-16',
  },
  
  // Lists and grids
  list: {
    container: 'border border-gray-200 rounded-lg divide-y divide-gray-200 bg-white',
    item: 'p-4 flex items-center justify-between hover:bg-gray-50 transition-colors',
    results: 'space-y-2',
    resultsItem: 'flex justify-between items-center p-3 rounded-lg text-lg',
    resultsWinner: 'bg-yellow-400/30 text-yellow-100 font-bold',
    resultsNormal: 'bg-black/20',
  },
  
  // Loading states
  loading: {
    spinner: 'flex justify-center py-4',
    text: 'text-center space-y-2',
    hero: 'flex items-center justify-center',
    heroSpinner: 'animate-spin rounded-full h-5 w-5 border-b-2 border-white mr-2',
  },

  // Hero sections
  hero: {
    container: 'text-center text-white',
    section: 'text-center mb-6',
    iconBg: 'bg-white/20',
    cardBorder: 'border-2 border-dashed border-gray-200 bg-gray-50/50',
    cardIcon: 'bg-green-100',
  },

  // Results page specific
  results: {
    background: 'bg-gradient-to-br',
    header: 'text-center space-y-2',
    winners: 'text-center w-full max-w-md',
    scores: 'w-full max-w-md space-y-3',
    footer: 'w-full max-w-md flex flex-col sm:flex-row gap-4 mt-6',
  }
};

// Animations & Effects
export const effects = {
  animation: {
    pulse: 'animate-pulse',
    confetti: 'numberOfPieces={250} recycle={false} gravity={0.25}',
  },
  
  transform: {
    hover: 'hover:-translate-y-0.5',
    scale: 'hover:scale-105',
    scaleHero: 'hover:scale-[1.02] active:scale-[0.98]',
  },
  
  backdrop: {
    blur: 'backdrop-blur-md',
    blurSm: 'backdrop-blur-sm',
  }
};

// Utilities
export const utils = {
  // Transitions and animations
  transition: 'transition-all duration-200',
  transitionFast: 'transition-all duration-150',
  
  // Common patterns
  centerContent: 'flex items-center justify-center',
  spaceBetween: 'flex items-center justify-between',
  
  // Responsive
  mobile: 'w-full max-w-md mx-auto',
  
  // Background patterns
  bgGradientHero: 'bg-gradient-to-br',
  bgGradientCard: 'bg-gradient-to-r',
} 