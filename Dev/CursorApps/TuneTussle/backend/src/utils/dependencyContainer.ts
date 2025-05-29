import { Server as SocketIOServer } from 'socket.io';
import { HookService } from '../services/hookService';
import { ParticipantManagementService } from '../services/participantManagementService';
import { GameLifecycleService } from '../services/gameLifecycleService';
import { RoundManagementService } from '../services/roundManagementService';
import { AnswerSubmissionService } from '../services/answerSubmissionService';
import { RealTimeEmissionService } from '../services/realTimeEmissionService';
import { GameSessionManager } from '../gameSessionManager';

type ServiceFactory<T> = (container: DependencyContainer) => T;

/**
 * Simple dependency injection container for TuneTussle services.
 *
 * - Manages service instantiation and dependency resolution.
 * - Supports lazy instantiation and singleton services.
 * - Handles circular dependencies via provider pattern.
 * - Allows registration of Socket.IO server for real-time services.
 * - Provides reset functionality for test isolation.
 */
export class DependencyContainer {
  private instances: Map<string, any> = new Map();
  private factories: Map<string, ServiceFactory<any>> = new Map();
  private io?: SocketIOServer;

  constructor() {
    this.registerFactories();
  }

  /**
   * Register Socket.IO server with the container.
   *
   * @param io - The Socket.IO server instance to register.
   *
   * This will update any already-instantiated services that depend on Socket.IO.
   */
  registerSocketIO(io: SocketIOServer): void {
    this.io = io;
    
    // Set Socket.IO in any already-instantiated services that need it
    if (this.instances.has('hookService')) {
      (this.instances.get('hookService') as HookService).setSocketIOServer(io);
    }
    
    if (this.instances.has('realTimeEmissionService')) {
      (this.instances.get('realTimeEmissionService') as RealTimeEmissionService).setSocketIOServer(io);
    }
    
    if (this.instances.has('gameSessionManager')) {
      (this.instances.get('gameSessionManager') as GameSessionManager).setSocketIOServer(io);
    }
    
    if (this.instances.has('socketManager')) {
      const socketManager = this.instances.get('socketManager');
      if (socketManager && typeof socketManager.setSocketIOServer === 'function') {
        socketManager.setSocketIOServer(io);
      }
    }
  }

  /**
   * Register all service factories.
   *
   * This method sets up the factory functions for each service managed by the container.
   * Factories are responsible for resolving dependencies via the container.
   */
  private registerFactories() {
    this.factories.set('realTimeEmissionService', container => {
      return new RealTimeEmissionService(this.io);
    });
    
    this.factories.set('hookService', container => {
      return new HookService(this.io);
    });
    
    // Add timerService factory for testing compatibility
    this.factories.set('timerService', container => {
      const { TimerService } = require('../services/timerService');
      return new TimerService();
    });
    
    this.factories.set('participantManagementService', container => {
      return new ParticipantManagementService(
        container.get<HookService>('hookService')
      );
    });
    
    this.factories.set('gameLifecycleService', container => {
      return new GameLifecycleService(
        container.get<HookService>('hookService')
      );
    });
    
    this.factories.set('roundManagementService', container => {
      // Use require here (not at module scope) so Jest can mock this
      const { RoundManagementService } = require('../services/roundManagementService');
      const { TimerService } = require('../services/timerService');
      return new RoundManagementService(
        container.get<HookService>('hookService'),
        new TimerService(),
        container.get<GameLifecycleService>('gameLifecycleService')
      );
    });
    
    this.factories.set('answerSubmissionService', container => {
      // Use require here (not at module scope) so Jest can mock this
      const { AnswerSubmissionService } = require('../services/answerSubmissionService');
      const { TimerService } = require('../services/timerService');
      return new AnswerSubmissionService(
        container.get<HookService>('hookService'),
        new TimerService(),
        container.get<GameLifecycleService>('gameLifecycleService'),
        container.get<RoundManagementService>('roundManagementService')
      );
    });
    
    this.factories.set('socketManager', container => {
      const { SocketManager } = require('../managers/SocketManager');
      return new SocketManager(
        container.get<HookService>('hookService'),
        this.io
      );
    });
    
    // Note: GameStateManager and BuzzerManager are created directly in GameSessionManager
    // with optimized storage and resource managers for scaling performance
    
    this.factories.set('gameSessionManager', container => {
      return new GameSessionManager(
        container.get<HookService>('hookService'),
        container.get<ParticipantManagementService>('participantManagementService'),
        container.get<GameLifecycleService>('gameLifecycleService'),
        container.get<RoundManagementService>('roundManagementService'),
        container.get<AnswerSubmissionService>('answerSubmissionService'),
        this.io
      );
    });
  }

  /**
   * Get a service instance, instantiating it if needed.
   *
   * @param serviceName - The name of the service to retrieve.
   * @returns The singleton instance of the requested service.
   * @throws Error if no factory is registered for the requested service.
   */
  get<T>(serviceName: string): T {
    // Return existing instance if available
    if (this.instances.has(serviceName)) {
      return this.instances.get(serviceName) as T;
    }
    
    // Check if a factory is registered for this service
    const factory = this.factories.get(serviceName);
    if (!factory) {
      throw new Error(`No factory registered for service: ${serviceName}`);
    }
    
    // Create and cache the instance
    const instance = factory(this);
    this.instances.set(serviceName, instance);
    return instance as T;
  }

  /**
   * Reset all services (useful for testing).
   *
   * Clears all cached service instances, forcing re-creation on next get().
   */
  reset(): void {
    this.instances.clear();
  }
}

/**
 * Singleton instance of the dependency container.
 *
 * Use this instance throughout the application to retrieve services.
 */
export const container = new DependencyContainer(); 