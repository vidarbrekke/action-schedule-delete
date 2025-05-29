import { renderHook } from '@testing-library/react';
import { useGameSocketEvents } from './useGameSocketEvents';
import { vi } from 'vitest';

describe('useGameSocketEvents', () => {
  it('registers and cleans up event handlers', () => {
    const socket = {
      on: vi.fn(),
      off: vi.fn(),
    };
    const handlerA = vi.fn();
    const handlerB = vi.fn();

    const { unmount } = renderHook(() =>
      useGameSocketEvents(socket, { eventA: handlerA, eventB: handlerB })
    );

    // Check that socket.on was called with the correct event names
    // The handlers are now wrapped, so we check for function type instead of exact match
    expect(socket.on).toHaveBeenCalledWith('eventA', expect.any(Function));
    expect(socket.on).toHaveBeenCalledWith('eventB', expect.any(Function));

    // Test that the wrapper functions actually call the original handlers
    const eventAWrapper = socket.on.mock.calls.find(call => call[0] === 'eventA')?.[1];
    const eventBWrapper = socket.on.mock.calls.find(call => call[0] === 'eventB')?.[1];
    
    eventAWrapper('test data A');
    eventBWrapper('test data B');
    
    expect(handlerA).toHaveBeenCalledWith('test data A');
    expect(handlerB).toHaveBeenCalledWith('test data B');

    unmount();
    
    // Check that socket.off was called with the wrapper functions
    expect(socket.off).toHaveBeenCalledWith('eventA', expect.any(Function));
    expect(socket.off).toHaveBeenCalledWith('eventB', expect.any(Function));
  });
}); 