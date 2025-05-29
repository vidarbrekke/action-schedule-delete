// Socket.IO configuration constants

export const getSocketUrl = (): string => {
  // To configure the backend Socket.IO URL, set VITE_SOCKET_IO_URL in your .env file (e.g., VITE_SOCKET_IO_URL="http://localhost:4000")
  // Use VITE_SOCKET_IO_URL for network/dev flexibility; fallback to localhost for local dev
  return import.meta.env.VITE_SOCKET_IO_URL ||
    `${window.location.protocol}//${window.location.hostname}:4000`;
};

export const SOCKET_CONFIG = {
  reconnectionAttempts: 5,
  // Consider adding withCredentials: true if you use cookies/sessions for auth with sockets
} as const; 