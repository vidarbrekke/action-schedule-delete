/**
 * Mock of TimerService for tests.
 */
export class TimerService {
  createTimer = jest.fn();
  clearTimer = jest.fn();
  hasTimer = jest.fn(() => false);
  startAnswerTimer = jest.fn((gameCode, playerName, duration, onTimerExpire) => {
    // In tests, we might want to call onTimerExpire immediately or control it via test utils
    // For a basic mock, just track the call.
    return `${gameCode}-${playerName}-answerTimer`; // Return a dummy timer ID
  });
  getActiveTimersCount = jest.fn(() => 0);

  constructor() {
    // No-op constructor for the mock
  }

  // Add any other methods that the real TimerService has and might be called.
  // For example, if there are methods to get all active timers, etc.
  clearAllTimersForGame(gameCode: string): void {
    // This is a common pattern, ensure it's mocked if used.
    // For a simple mock, just be a no-op or a jest.fn()
    jest.fn()(gameCode);
  }
} 