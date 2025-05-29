# TuneTussle Dependency Injection System

This document describes the dependency injection (DI) system implemented in the TuneTussle backend.

## Overview

The DI system provides several benefits:
- Decouples service creation from service usage
- Makes the code more modular and testable
- Centralizes service instantiation
- Handles circular dependencies gracefully
- Makes it easier to mock services in tests

## Key Components

### `DependencyContainer` Class (`dependencyContainer.ts`)

The main container that manages service instantiation and dependency resolution. Features:

- Service factory registration - each service has a factory function that knows how to create it
- Lazy instantiation - services are only created when first requested
- Instance caching - services are singletons within the container's lifecycle
- Socket.IO registration - handles updating Socket.IO dependencies in services

### Usage

#### Getting a Service

```typescript
import { container } from './utils/dependencyContainer';
import { HookService } from './services/hookService';

// Get a service instance
const hookService = container.get<HookService>('hookService');
```

#### Registering Socket.IO

```typescript
import { container } from './utils/dependencyContainer';
import { Server as SocketIOServer } from 'socket.io';

// Create Socket.IO server
const io = new SocketIOServer(server);

// Register with the container
container.registerSocketIO(io);
```

#### Resetting the Container (for Testing)

```typescript
import { container } from './utils/dependencyContainer';

// Reset all service instances (useful in tests)
container.reset();
```

## Handled Services

The following services are managed by the DI container:

- `HookService` - Event hook management
- `TimerService` - Timer management
- `ParticipantManagementService` - Player joining/leaving
- `GameLifecycleService` - Game state transitions
- `RoundManagementService` - Round progression
- `AnswerSubmissionService` - Player answer handling
- `RealTimeEmissionService` - Socket.IO event emission

## Real-Time Emission Service

As part of the DI implementation, a new `RealTimeEmissionService` was created to centralize all direct Socket.IO communications. This enforces separation of concerns:

- Game logic services focus on game state and rules
- `RealTimeEmissionService` handles all Socket.IO event emission
- `HookService` manages event hooks but delegates actual Socket.IO communication

## Testing Patterns

When writing tests for services that use the dependency container:

- **Test Isolation:** Always call `container.reset()` in your test `beforeEach` hook to ensure each test gets a fresh set of service instances. This prevents state leakage between tests due to the singleton nature of the container.

```typescript
beforeEach(() => {
  container.reset();
});
```

- **Mocking Services:**
  - For isolated unit tests, you can mock the container's `get` method to return test doubles or mocks for specific services.
  - Alternatively, consider refactoring the container to allow custom factory registration for test scenarios (see 'Future Improvements').

```typescript
jest.spyOn(container, 'get').mockImplementation((serviceName: string) => {
  if (serviceName === 'hookService') return mockHookService;
  // ... other mocks ...
  throw new Error('Mock not implemented for ' + serviceName);
});
```

- **Singleton Implications:**
  - The container is a singleton, so all code (including tests) shares the same instance unless you explicitly create a new one.
  - This is why resetting the container is critical for test reliability.

## Future Improvements

1. Add an option to register custom service factories for testing
2. Support more dependency injection patterns (property injection, method injection)
3. Add scoped lifetime management (request scoped, transient, etc.) 