import request from 'supertest';
import express from 'express';
import gameRoutes from '../routes/gameRoutes'; // gameRoutes imports GameSessionManager, which now imports llmConfigManager
import { getLlmConfig } from '../llmConfigManager'; // For mock typing
import { TestGameSessionManager } from '../testUtils/testGameSessionManager'; // For type if needed
import * as llmService from '../services/llmService'; // <-- Add this import
import * as realtimeEmitter from '../realtimeEmitter'; // <-- Import realtimeEmitter
import { makeTestSong, makeTestSongs } from '../../testUtils/testSongs';

// --- Mocks --- 
jest.mock('../llmConfigManager'); // Must be mocked before gameRoutes (and thus GameSessionManager) is imported
global.fetch = jest.fn();         // Mock global fetch
// --- End Mocks ---

// Set up a test app instance
const app = express();
app.use(express.json());

// Provide a mock Socket.IO object for the router
const mockIo: any = { // Added 'any' type for simplicity in mock
  to: jest.fn(() => ({ emit: jest.fn() })), // Ensure methods are jest.fn for spying if needed
  emit: jest.fn(),
};

// Instantiate GameSessionManager and pass it to gameRoutes
const testGameManager = TestGameSessionManager.createForTesting();
// Initialize realtimeEmitter with the mock IO and test game manager
realtimeEmitter.registerRealtimeHooks(mockIo, testGameManager);
app.use('/api', gameRoutes(mockIo, testGameManager)); // Pass testGameManager

beforeAll(() => {
  // Patch getLlmConfig to always return a valid config
  (getLlmConfig as jest.Mock).mockResolvedValue({ apiKey: 'test-key', modelId: 'test-model', apiBaseUrl: 'http://mock' });
  // Patch fetchAndValidateSongList to always return a valid song list
  jest.spyOn(llmService, 'fetchAndValidateSongList').mockResolvedValue(makeTestSongs([
    makeTestSong('Song 1', 'Artist 1'),
    makeTestSong('Song 2', 'Artist 2'),
    makeTestSong('Song 3', 'Artist 3')
  ]));
});

describe('Game Routes API', () => {
  const defaultGamePayload = {
    judgeName: 'Judge Dread',
    prompt: 'Reggae Hits',
    numberOfRounds: 5,
  };

  afterEach(() => {
    // Clean up after each test to prevent state leakage
    testGameManager.DEBUG_resetGames();
  });

  it('should create a new game and return a code', async () => {
    const res = await request(app)
      .post('/api/games')
      .send(defaultGamePayload);
    expect(res.status).toBe(201);
    expect(res.body.code).toBeDefined();
  });

  it('should return 400 if create game payload is missing required fields', async () => {
    const res = await request(app)
      .post('/api/games')
      .send({ judgeName: 'Judge incomplete' }); // Missing prompt and numberOfRounds
    expect(res.status).toBe(400);
    expect(res.body.error).toBe('judgeName, prompt, and numberOfRounds are required.');
  });

  it('should allow a participant to join a game', async () => {
    const createRes = await request(app)
      .post('/api/games')
      .send(defaultGamePayload);
    const code = createRes.body.code;
    expect(createRes.status).toBe(201); // Ensure game was created

    const joinRes = await request(app)
      .post(`/api/games/${code}/join`)
      .send({ playerName: 'Player1' });
    expect(joinRes.status).toBe(200);
    expect(joinRes.body.success).toBe(true);
  });

  it('should list all participants in a game', async () => {
    const createRes = await request(app)
      .post('/api/games')
      .send(defaultGamePayload);
    const code = createRes.body.code;
    expect(createRes.status).toBe(201);

    await request(app)
      .post(`/api/games/${code}/join`)
      .send({ playerName: 'Player1' });
    await request(app)
      .post(`/api/games/${code}/join`)
      .send({ playerName: 'Player2' });

    const listRes = await request(app)
      .get(`/api/games/${code}/participants`);
    expect(listRes.status).toBe(200);
    // Judge is also a participant by default now
    expect(listRes.body.participants).toEqual(
      expect.arrayContaining([defaultGamePayload.judgeName, 'Player1', 'Player2'])
    );
    expect(listRes.body.participants.length).toBe(3);
  });

  it('should return 404 for non-existent game on join', async () => {
    const res = await request(app)
      .post('/api/games/FAKECODE/join')
      .send({ playerName: 'Player1' });
    expect(res.status).toBe(404); // Expecting 404 due to result.message check in route
  });

  it('should return 404 for non-existent game on list', async () => {
    const res = await request(app)
      .get('/api/games/FAKECODE/participants');
    expect(res.status).toBe(404);
  });

  // Add a test for attempting to join with a duplicate name (expecting 400)
  it('should return 400 if participant name is taken', async () => {
    const createRes = await request(app)
      .post('/api/games')
      .send(defaultGamePayload);
    const code = createRes.body.code;
    expect(createRes.status).toBe(201);

    await request(app)
      .post(`/api/games/${code}/join`)
      .send({ playerName: 'PlayerUnique' }); // First join is fine
    
    const joinResDuplicate = await request(app)
      .post(`/api/games/${code}/join`)
      .send({ playerName: 'PlayerUnique' }); // Second join with same name
    expect(joinResDuplicate.status).toBe(400);
    expect(joinResDuplicate.body.error).toBe('Player name already taken in this game.');
  });

  describe('Leave Game Route', () => {
    it('should allow a participant to leave a game', async () => {
      const createRes = await request(app)
        .post('/api/games')
        .send(defaultGamePayload);
      const code = createRes.body.code;
      await request(app).post(`/api/games/${code}/join`).send({ playerName: 'PlayerToLeave' });

      const leaveRes = await request(app)
        .delete(`/api/games/${code}/participants/PlayerToLeave`);
      expect(leaveRes.status).toBe(200);
      expect(leaveRes.body.success).toBe(true);
      expect(leaveRes.body.message).toBe('Player PlayerToLeave left the lobby.');
      expect(leaveRes.body.gameEnded).toBe(false);

      // Verify participant is gone
      const listRes = await request(app).get(`/api/games/${code}/participants`);
      expect(listRes.body.participants).not.toContain('PlayerToLeave');
      expect(listRes.body.participants).toContain(defaultGamePayload.judgeName); // Judge should still be there
    });

    it('should return 404 if participant to remove is not in the game', async () => {
      const createRes = await request(app)
        .post('/api/games')
        .send(defaultGamePayload);
      const code = createRes.body.code;

      const leaveRes = await request(app)
        .delete(`/api/games/${code}/participants/NonExistentPlayer`);
      expect(leaveRes.status).toBe(404);
      expect(leaveRes.body.error).toBe('Participant NonExistentPlayer not found in game.');
    });

    it('should return 404 when trying to leave a non-existent game', async () => {
      const leaveRes = await request(app)
        .delete('/api/games/FAKECODE/participants/AnyPlayer');
      expect(leaveRes.status).toBe(404);
      expect(leaveRes.body.error).toBe('Game not found.');
    });

    it('should allow the judge to leave, and delete the game if judge is last participant', async () => {
      const createRes = await request(app)
        .post('/api/games')
        .send(defaultGamePayload);
      const code = createRes.body.code;

      const leaveRes = await request(app)
        .delete(`/api/games/${code}/participants/${defaultGamePayload.judgeName}`);
      expect(leaveRes.status).toBe(200);
      expect(leaveRes.body.success).toBe(true);
      expect(leaveRes.body.message).toBe('Judge left lobby; game deleted as it cannot proceed.');
      expect(leaveRes.body.gameEnded).toBe(true);

      const listRes = await request(app).get(`/api/games/${code}/participants`);
      expect(listRes.status).toBe(404);
    });

    it('should allow the judge to leave a lobby game with other participants, deleting the game', async () => {
      const createRes = await request(app).post('/api/games').send(defaultGamePayload);
      const code = createRes.body.code;
      expect(createRes.status).toBe(201);
      await request(app).post(`/api/games/${code}/join`).send({ playerName: 'Player1' });

      const leaveRes = await request(app)
        .delete(`/api/games/${code}/participants/${defaultGamePayload.judgeName}`);
      expect(leaveRes.status).toBe(200);
      expect(leaveRes.body.success).toBe(true);
      expect(leaveRes.body.message).toBe('Judge left lobby; game deleted as it cannot proceed.');
      expect(leaveRes.body.gameEnded).toBe(true);

      const listRes = await request(app).get(`/api/games/${code}/participants`);
      expect(listRes.status).toBe(404);
    });

    // Test for judge leaving an ACTIVE game and it becomes FINISHED is hard via API without /start.
    // This specific state transition (playing -> finished on judge leave) is tested in gameSessionManager.test.ts.
    // However, we can add a test to ensure if the GameSessionManager *did* return that state, the route passes it through.
    // This requires mocking gameManager.leaveGame for one specific test case.
  });

  describe('Start Game Route', () => {
    let gameCodeForStart: string;
    const judgeWhoCreated = 'JudgeToStart';
    const defaultGamePayload = { // Reusable payload for game creation in these tests
      judgeName: judgeWhoCreated,
      prompt: 'Test Start Game Prompt',
      numberOfRounds: 5,
    };

    beforeEach(async () => {
      (getLlmConfig as jest.Mock).mockReset();
      (getLlmConfig as jest.Mock).mockResolvedValue({ apiKey: 'test-key', modelId: 'test-model', apiBaseUrl: 'http://mock' });
      (global.fetch as jest.Mock).mockReset();
      jest.spyOn(llmService, 'fetchAndValidateSongList').mockReset().mockResolvedValue(makeTestSongs([
        makeTestSong('Song 1', 'Artist 1'),
        makeTestSong('Song 2', 'Artist 2'),
        makeTestSong('Song 3', 'Artist 3')
      ]));

      // Create a game to be used in start tests
      const createRes = await request(app)
        .post('/api/games')
        .send(defaultGamePayload);
      expect(createRes.status).toBe(201); // Ensure game creation was successful
      gameCodeForStart = createRes.body.code;
    });

    afterEach(() => {
      jest.spyOn(llmService, 'fetchAndValidateSongList').mockReset();
    });

    it('should allow the judge to start a game in lobby state', async () => {
      // Setup mocks for a successful startGame call
      (getLlmConfig as jest.Mock).mockReturnValue({ apiKey: 'testkey', modelId: 'testmodel' });
      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        json: async () => ({ choices: [{ message: { content: JSON.stringify([{title:'S1', artist:'A1'}]) } }] }),
      } as Response);

      const startRes = await request(app)
        .post(`/api/games/${gameCodeForStart}/start`)
        .send({ requestingUser: judgeWhoCreated });
      
      expect(startRes.status).toBe(200);
      expect(startRes.body.success).toBe(true);
      expect(startRes.body.message).toBe('Game started successfully.');

      // Optionally, verify game state via another endpoint if available, or trust gameManager tests
    });

    it('should return 403 if a non-judge tries to start the game', async () => {
      // Non-judge attempt, LLM calls should not happen, but mocks prevent unhandled promises if they did.
      (getLlmConfig as jest.Mock).mockReturnValue({ apiKey: 'testkey', modelId: 'testmodel' }); // Prevent LLM config error
      // No fetch mock needed as it shouldn't be reached if judge check fails first.

      const startRes = await request(app)
        .post(`/api/games/${gameCodeForStart}/start`)
        .send({ requestingUser: 'NonJudge' });
      
      expect(startRes.status).toBe(403);
      expect(startRes.body.error).toBe('Only the judge can start the game.');
    });

    it('should return 404 when trying to start a non-existent game', async () => {
      // No mocks for LLM needed as game not found error should occur first.
      const startRes = await request(app)
        .post('/api/games/FAKECODE/start')
        .send({ requestingUser: 'AnyUser' });
      expect(startRes.status).toBe(404);
      expect(startRes.body.error).toBe('Game not found.');
    });

    it('should return 400 if trying to start a game not in lobby state', async () => {
      // First successful start
      (getLlmConfig as jest.Mock).mockReturnValue({ apiKey: 'testkey', modelId: 'testmodel' });
      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        json: async () => ({ choices: [{ message: { content: JSON.stringify([{title:'S1', artist:'A1'}]) } }] }),
      } as Response);
      await request(app).post(`/api/games/${gameCodeForStart}/start`).send({ requestingUser: judgeWhoCreated });

      // Attempt to start again (mocks for LLM not strictly needed as state check is first, but good for safety)
      (getLlmConfig as jest.Mock).mockReturnValue({ apiKey: 'testkey', modelId: 'testmodel' });
      const secondStartRes = await request(app)
        .post(`/api/games/${gameCodeForStart}/start`)
        .send({ requestingUser: judgeWhoCreated });
      
      expect(secondStartRes.status).toBe(400);
      expect(secondStartRes.body.error).toBe('Game is not in lobby state.');
    });

    it('should return 400 if requestingUser is missing from the body', async () => {
      const startRes = await request(app)
        .post(`/api/games/${gameCodeForStart}/start`)
        .send({}); // Empty body
      
      expect(startRes.status).toBe(400);
      expect(startRes.body.error).toBe('requestingUser is required in the request body.');
    });
  });
});

afterAll(() => {
  // Clean up GameSessionManager interval
  testGameManager.destroy();
  
  // Clean up singleton instances for test isolation
  const { GameStorageManager } = require('../services/gameStorageManager');
  GameStorageManager.cleanupAllInstances();

  const { ResourcePoolManager } = require('../services/resourcePoolManager');
  ResourcePoolManager.resetInstance();

  const { AdaptivePerformanceMonitor } = require('../services/adaptivePerformanceMonitor');
  AdaptivePerformanceMonitor.resetInstance();
}); 