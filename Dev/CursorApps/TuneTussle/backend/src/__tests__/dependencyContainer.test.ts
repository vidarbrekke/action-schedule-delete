import { DependencyContainer } from '../utils/dependencyContainer';
import { HookService } from '../services/hookService';
import { TimerService } from '../services/timerService';
import { RealTimeEmissionService } from '../services/realTimeEmissionService';
import { Server as SocketIOServer } from 'socket.io';

describe('DependencyContainer', () => {
  let container: DependencyContainer;

  beforeEach(() => {
    container = new DependencyContainer();
  });

  it('should instantiate and cache singleton services', () => {
    const hook1 = container.get<HookService>('hookService');
    const hook2 = container.get<HookService>('hookService');
    expect(hook1).toBeInstanceOf(HookService);
    expect(hook1).toBe(hook2);
  });

  it('should reset and re-instantiate services', () => {
    const hook1 = container.get<HookService>('hookService');
    container.reset();
    const hook2 = container.get<HookService>('hookService');
    expect(hook2).toBeInstanceOf(HookService);
    expect(hook1).not.toBe(hook2);
  });

  it('should throw for unknown service', () => {
    expect(() => container.get<any>('notAService')).toThrow('No factory registered for service: notAService');
  });

  it('should register Socket.IO and update services', () => {
    const io = { fake: 'io' } as unknown as SocketIOServer;
    const hook = container.get<HookService>('hookService');
    const realTime = container.get<RealTimeEmissionService>('realTimeEmissionService');
    // Patch setSocketIOServer to test invocation
    const hookSet = jest.spyOn(hook, 'setSocketIOServer');
    const realTimeSet = jest.spyOn(realTime, 'setSocketIOServer');
    container.registerSocketIO(io);
    expect(hookSet).toHaveBeenCalledWith(io);
    expect(realTimeSet).toHaveBeenCalledWith(io);
  });

  it('should resolve dependencies between services', () => {
    const timer = container.get<TimerService>('timerService');
    expect(timer).toBeInstanceOf(TimerService);
    const hook = container.get<HookService>('hookService');
    expect(hook).toBeInstanceOf(HookService);
    // Should not throw
    expect(() => container.get<RealTimeEmissionService>('realTimeEmissionService')).not.toThrow();
  });
}); 