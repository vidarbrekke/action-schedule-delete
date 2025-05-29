import { renderHook } from '@testing-library/react';
import { useSocketReconnectionEffect } from './useSocketReconnectionEffect';
import { vi } from 'vitest';

describe('useSocketReconnectionEffect', () => {
  it('registers and cleans up reconnect/disconnect handlers', () => {
    const socket = { on: vi.fn(), off: vi.fn(), emit: vi.fn(), connect: vi.fn() };
    const gameState = { gameCode: 'ABC', playerName: 'Alice', hasJoinedGame: true };
    const dispatch = vi.fn();
    const { unmount } = renderHook(() => useSocketReconnectionEffect(socket, gameState, dispatch));
    expect(socket.on).toHaveBeenCalledWith('reconnect', expect.any(Function));
    expect(socket.on).toHaveBeenCalledWith('disconnect', expect.any(Function));
    unmount();
    expect(socket.off).toHaveBeenCalledWith('reconnect', expect.any(Function));
    expect(socket.off).toHaveBeenCalledWith('disconnect', expect.any(Function));
  });

  it('attempts to rejoin on reconnect', () => {
    const socket = { on: vi.fn(), off: vi.fn(), emit: vi.fn((_event, _payload, cb) => cb({ success: true, role: 'participant' })), connect: vi.fn() };
    const gameState = { gameCode: 'ABC', playerName: 'Alice', hasJoinedGame: true };
    const dispatch = vi.fn();
    renderHook(() => useSocketReconnectionEffect(socket, gameState, dispatch));
    // Simulate reconnect
    const handler = socket.on.mock.calls.find(([event]) => event === 'reconnect')?.[1];
    handler();
    expect(socket.emit).toHaveBeenCalledWith('joinGameRoom', expect.any(Object), expect.any(Function));
    expect(dispatch).toHaveBeenCalledWith({ type: 'JOIN_SUCCESS', payload: { role: 'participant' } });
  });

  it('attempts to reconnect on io server disconnect', () => {
    const socket = { on: vi.fn(), off: vi.fn(), emit: vi.fn(), connect: vi.fn() };
    const gameState = { gameCode: 'ABC', playerName: 'Alice', hasJoinedGame: true };
    const dispatch = vi.fn();
    renderHook(() => useSocketReconnectionEffect(socket, gameState, dispatch));
    // Simulate disconnect
    const handler = socket.on.mock.calls.find(([event]) => event === 'disconnect')?.[1];
    handler('io server disconnect');
    expect(socket.connect).toHaveBeenCalled();
  });
}); 