import type { GameSession } from '../../models/types'; // Adjusted path

/**
 * Mock of GameStorageManager for tests.
 * All instances will share the same static game store.
 */
export class GameStorageManager {
  private static mockGames = new Map<string, GameSession>();

  // Constructor is a no-op as state is static.
  constructor(config?: any) {}

  public static clearAllGames(): void {
    GameStorageManager.mockGames.clear();
  }

  set(code: string, game: GameSession): void {
    GameStorageManager.mockGames.set(code, game);
  }

  get(code: string): GameSession | undefined {
    return GameStorageManager.mockGames.get(code);
  }

  has(code: string): boolean {
    return GameStorageManager.mockGames.has(code);
  }

  delete(code: string): boolean {
    return GameStorageManager.mockGames.delete(code);
  }

  keys(): IterableIterator<string> {
    return GameStorageManager.mockGames.keys();
  }

  getStats(): { totalGames: number; activeGames: number; memoryUsageMB: number } {
    return {
      totalGames: GameStorageManager.mockGames.size,
      activeGames: GameStorageManager.mockGames.size, // Simplified for mock
      memoryUsageMB: 0 
    };
  }

  // Add other methods that the real GameStorageManager has, if they are called by GameSessionManager
  // For example, if performCleanup or destroy are called, add them as no-ops.
  performCleanup(): number {
    return 0;
  }

  destroy(): void {
    GameStorageManager.mockGames.clear(); // Or be a no-op if clearAllGames is the sole reset point
  }

  performEmergencyCleanup(): void {}

  protected startCleanupProcess(): void {}

  protected cleanupGameResources(game: GameSession): void {}
} 