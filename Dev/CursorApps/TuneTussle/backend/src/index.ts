import express from 'express';
import http from 'http';
import { Server } from 'socket.io';
import cors from 'cors';
import env, { validateRequiredEnvVars, allowedOrigins as envAllowedOrigins } from './utils/envConfig';
import gameRoutes from './routes/gameRoutes';
import adminRoutes from './routes/adminRoutes';
import performanceRoutes from './routes/performanceRoutes';
import musicLinkRoutes from './routes/musicLinkRoutes';
// import testSpotifyRoutes from './routes/testSpotifyRoutes';
import { GameSessionManager } from './gameSessionManager';
import { registerRealtimeHooks } from './realtimeEmitter';
import { registerClientEventHandlers } from './registerClientEventHandlers';
import { container } from './utils/dependencyContainer';
import { logger } from './utils/logger';

// Configure logger for development/production
logger.configure({
  level: process.env.LOG_LEVEL as any || (process.env.NODE_ENV === 'production' ? 'error' : 'debug')
});

logger.info("Tune Tussle Server Starting");

const youtubeMethod = process.env.YOUTUBE_FETCH_METHOD || 'SCRAPE';
logger.debug(`YouTube method: ${youtubeMethod} (${youtubeMethod === 'API' ? (env.YOUTUBE_API_KEY ? 'API key provided' : 'API key missing!') : 'scraping mode'})`);

// Environment variables are loaded by the envConfig utility

// Validate required environment variables
// This is informational only for now, since we can still start without some APIs
validateRequiredEnvVars(['PORT']);

// Initialize Express app and HTTP server
const app = express();
const server = http.createServer(app);

// Utility to get the local network IP address
function getLocalNetworkIp() {
  const networkInterfaces = require('os').networkInterfaces();
  for (const name of Object.keys(networkInterfaces)) {
    for (const net of networkInterfaces[name]!) {
      if (net.family === 'IPv4' && !net.internal) {
        return net.address;
      }
    }
  }
  return 'localhost';
}

const LOCAL_IP = getLocalNetworkIp();
const FRONTEND_PORT = 5173;
const FRONTEND_ORIGIN = [
  `http://localhost:${FRONTEND_PORT}`,
  `http://${LOCAL_IP}:${FRONTEND_PORT}`
];

// Initialize Socket.IO
const io = new Server(server, {
  cors: {
    origin: envAllowedOrigins,
    methods: ['GET', 'POST'],
    credentials: true
  }
});

// Middleware
app.use(cors({
  origin: envAllowedOrigins,
  credentials: true // if you use cookies/auth
}));
app.use(express.json());

logger.info("Setting up TuneTussle backend");

// Register Socket.IO with the dependency container
container.registerSocketIO(io);

// Get GameSessionManager from dependency container
const gameSessionManager = container.get<GameSessionManager>('gameSessionManager');
registerRealtimeHooks(io, gameSessionManager);

// Set up Socket.IO connection handler
io.on('connection', (socket) => {
  logger.socket(`Client connected: ${socket.id}`);
  
  // Register event handlers for this client
  registerClientEventHandlers(socket, gameSessionManager);
  
  socket.on('disconnect', () => {
    logger.socket(`Client disconnected: ${socket.id}`);
  });
});

// Register API routes
app.use('/api', gameRoutes(io, gameSessionManager));
app.use('/api/admin', adminRoutes);
app.use('/api/performance', performanceRoutes);
app.use('/api', musicLinkRoutes);
// app.use('/api', testSpotifyRoutes);

const PORT = env.PORT;
// const HOST = '192.168.1.170'; // Explicitly bind to local network IP
const HOST = '0.0.0.0'; // Listen on all available network interfaces
server.listen(PORT, HOST, () => {
  logger.info(`Tune Tussle backend listening on http://localhost:${PORT}`);
  if (LOCAL_IP !== 'localhost') {
    logger.info(`Also available on your network at: http://${LOCAL_IP}:${PORT}`);
    logger.info(`Frontend clients can connect from: http://${LOCAL_IP}:${FRONTEND_PORT}`);
  }
});
