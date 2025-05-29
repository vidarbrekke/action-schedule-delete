/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  theme: {
    screens: {
      'xs': '475px',
      'sm': '640px',
      'md': '768px',
      'lg': '1024px',
      'xl': '1280px',
      '2xl': '1536px',
    },
    extend: {
      spacing: {
        'safe-top': 'env(safe-area-inset-top)',
        'safe-bottom': 'env(safe-area-inset-bottom)',
        'safe-left': 'env(safe-area-inset-left)',
        'safe-right': 'env(safe-area-inset-right)',
      },
      fontSize: {
        'toast-mobile': ['16px', '1.4'],
        'toast-desktop': ['14px', '1.5'],
      },
      backdropBlur: {
        'xs': '2px',
      },
      animation: {
        'toast-in-mobile': 'slideInFromTop 0.3s ease-out',
        'toast-out-mobile': 'slideOutToTop 0.2s ease-in',
        'toast-in-desktop': 'slideInFromBottom 0.3s ease-out',
      },
      transitionProperty: {
        'toast': 'transform, opacity, scale',
      },
    },
  },
  plugins: [],
};

