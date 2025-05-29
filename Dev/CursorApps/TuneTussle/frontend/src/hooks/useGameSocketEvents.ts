import { useEffect, useRef } from 'react';
import type { Socket } from 'socket.io-client';

type EventHandler = (...args: unknown[]) => void;

type EventHandlerMap = {
  [event: string]: EventHandler;
};

export function useGameSocketEvents(socket: Socket | null, handlers: EventHandlerMap) {
  const handlersRef = useRef(handlers);

  // Update handlers ref without triggering re-registration
  handlersRef.current = handlers;

  useEffect(() => {
    if (!socket) return;

    // Create stable wrapper functions that call the current handlers
    const wrappers: { [event: string]: EventHandler } = {};

    Object.keys(handlers).forEach(event => {
      wrappers[event] = (...args: unknown[]) => {
        handlersRef.current[event]?.(...args);
      };
      socket.on(event, wrappers[event]);
    });

    return () => {
      Object.entries(wrappers).forEach(([event, wrapper]) => {
        socket.off(event, wrapper);
      });
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [socket]); // Only depend on socket, not handlers - handlers are tracked via handlersRef to prevent re-registration on every handler change
}
