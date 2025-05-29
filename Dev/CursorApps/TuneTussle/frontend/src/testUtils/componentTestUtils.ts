/**
 * Component Test Utilities
 * Consolidates repetitive patterns across frontend component tests
 */

import { render, screen, fireEvent } from '@testing-library/react';
import { vi } from 'vitest';
import React from 'react';

/**
 * Standard test data factory for components
 */
export const componentTestDataFactory = {
  participant: (overrides = {}) => ({
    id: 'test-player',
    name: 'Test Player', 
    score: 100,
    ...overrides,
  }),

  gameState: (overrides = {}) => ({
    currentRound: 1,
    totalRounds: 10,
    isRoundComplete: false,
    isGameOver: false,
    canAdvance: true,
    correctAnswer: 'Test Answer',
    currentSongAudioUrl: null,
    ...overrides,
  }),

  lobbyState: (overrides = {}) => ({
    isGameReady: true,
    isStartingGame: false,
    isGeneratingSongs: false,
    startGameError: undefined,
    ...overrides,
  }),
};

/**
 * Standard mock factory for component handlers
 */
export const mockHandlerFactory = {
  create: (handlerNames: string[]) => {
    const mocks: Record<string, any> = {};
    handlerNames.forEach(name => {
      mocks[name] = vi.fn();
    });
    return mocks;
  },

  reset: (mocks: Record<string, any>) => {
    Object.values(mocks).forEach(mock => {
      if (vi.isMockFunction(mock)) {
        mock.mockClear();
      }
    });
  },
};

/**
 * Standard setup for component tests with common globals
 */
export function setupComponentTest() {
  beforeEach(() => {
    vi.clearAllMocks();
    global.confirm = vi.fn(() => true);
    global.alert = vi.fn();
  });

  afterEach(() => {
    vi.resetAllMocks();
  });
}

/**
 * Helper for testing button interactions
 */
export const buttonTestHelpers = {
  expectButtonExists: (buttonText: string) => {
    const button = screen.getByText(buttonText);
    expect(button).toBeInTheDocument();
    return button;
  },

  expectButtonNotExists: (buttonText: string) => {
    const button = screen.queryByText(buttonText);
    expect(button).not.toBeInTheDocument();
  },

  expectButtonDisabled: (buttonText: string) => {
    const button = screen.getByText(buttonText);
    expect(button).toBeDisabled();
  },

  expectButtonEnabled: (buttonText: string) => {
    const button = screen.getByText(buttonText);
    expect(button).not.toBeDisabled();
  },

  clickButton: (buttonText: string) => {
    const button = screen.getByText(buttonText);
    fireEvent.click(button);
    return button;
  },
};

/**
 * Helper for testing form interactions
 */
export const formTestHelpers = {
  fillInput: (placeholder: string, value: string) => {
    const input = screen.getByPlaceholderText(placeholder);
    fireEvent.change(input, { target: { value } });
    return input;
  },

  submitForm: () => {
    const form = screen.getByRole('form') || screen.getByTestId('form');
    fireEvent.submit(form);
  },

  expectValidationError: (message: string) => {
    expect(screen.getByText(message)).toBeInTheDocument();
  },
};

/**
 * Helper for testing audio-related components
 */
export const audioTestHelpers = {
  mockAudioService: () => {
    const mockPlaySound = vi.fn();
    const mockRegisterSound = vi.fn();
    
    vi.mock('../services/audioService', () => ({
      audioService: {
        playSound: mockPlaySound,
        registerSound: mockRegisterSound,
      },
    }));

    return { mockPlaySound, mockRegisterSound };
  },
};

/**
 * Creates test scenarios for component state transitions  
 */
export function createStateTransitionTests<P extends object>(
  Component: React.ComponentType<P>,
  baseProps: P,
  scenarios: Array<{
    name: string;
    props: Partial<P>;
    expectations: () => void;
  }>
) {
  scenarios.forEach(({ name, props, expectations }) => {
    it(name, () => {
      const mergedProps = { ...baseProps, ...props } as P;
      render(React.createElement(Component, mergedProps));
      expectations();
    });
  });
}

/**
 * Creates standard button interaction tests
 */
export function createButtonInteractionTests<P extends object>(
  Component: React.ComponentType<P>,
  baseProps: P,
  buttonTests: Array<{
    buttonText: string;
    mockHandler: any;
    description: string;
    additionalProps?: Partial<P>;
  }>
) {
  buttonTests.forEach(({ buttonText, mockHandler, description, additionalProps = {} }) => {
    it(description, () => {
      const mergedProps = { ...baseProps, ...additionalProps } as P;
      render(React.createElement(Component, mergedProps));
      buttonTestHelpers.clickButton(buttonText);
      expect(mockHandler).toHaveBeenCalledTimes(1);
    });
  });
}

// Re-export common testing utilities
export { vi } from 'vitest'; 