/** @jest-environment node */
import { describe, it, expect, beforeEach, afterEach, jest } from '@jest/globals';
import { GameSessionManager } from '../gameSessionManager';
import { TestGameSessionManager } from '../testUtils/testGameSessionManager';
import { GameSession, GameRound, Song, GameSong } from '../models/types';
import { getLlmConfig, LlmConfig } from '../llmConfigManager'; 
import { resetAllMocksAndModules } from '../../testUtils/resetTestEnv';
import { makeTestSong, makeTestSongs } from '../../testUtils/testSongs';

// --- CORE SERVICE MOCKS ---
jest.mock('../services/gameStorageManager');
jest.mock('../services/resourcePoolManager');
jest.mock('../services/adaptivePerformanceMonitor');
jest.mock('../services/timerService');
jest.mock('../services/roundManagementService');
// --- END CORE SERVICE MOCKS ---

// Import and mock llmService
import * as llmServiceImport from '../services/llmService';
jest.mock('../services/llmService');
const mockedLlmService = llmServiceImport as jest.Mocked<typeof llmServiceImport>;

// Mock youtubeService for enrichment
import * as youtubeServiceImport from '../services/youtubeService';
jest.mock('../services/youtubeService');
const mockedYoutubeService = youtubeServiceImport as jest.Mocked<typeof youtubeServiceImport>;

// Mock llmConfigManager
jest.mock('../llmConfigManager');
const mockedGetLlmConfig = getLlmConfig as jest.MockedFunction<typeof getLlmConfig>;

// Mock answerSubmissionService
jest.mock('../services/answerSubmissionService');

// Provide a default implementation for fetch that matches the type signature
global.fetch = jest.fn(() =>
  Promise.resolve({
    ok: true,
    status: 200,
    statusText: 'OK',
    json: () => Promise.resolve({}),
    // Add other Response properties if needed by code under test, though mocks usually override this
  } as Response)
);
const mockFetch = global.fetch as jest.MockedFunction<typeof fetch>;

// Default mock LLM config
const defaultMockLlmConfig: LlmConfig = {
  apiKey: 'test-llm-api-key',
  modelId: 'test-model-id',
  apiBaseUrl: null,
  youtubeApiKey: 'test-youtube-api-key', // ensure youtube is available for enrichment path
};

// Helper to set up basic YouTube enrichment mock
const setupYouTubeEnrichmentMock = (enrichedSongsWithLinks: Array<Song & { youtubeLink?: string }>) => {
  mockedYoutubeService.enrichSongsWithYouTubeLinks.mockImplementation(async (songsToEnrich, apiKey) => {
    if (!apiKey) return songsToEnrich.map(s => ({ ...s, youtubeLink: undefined }));
    // Simulate enrichment: find matching song from enrichedSongsWithLinks and add its link
    return songsToEnrich.map(originalSong => {
      const match = enrichedSongsWithLinks.find(es => es.title === originalSong.title && es.artist === originalSong.artist);
      return { ...originalSong, youtubeLink: match?.youtubeLink };
    });
  });
};

const defaultGameSettings = {
  prompt: 'Test Prompt',
  numberOfRounds: 3,
  llmModel: 'default-test-model',
};


describe('GameSessionManager', () => {
  jest.setTimeout(30000);

  beforeEach(() => {
    // resetAllMocksAndModules(); // REMOVED from global
    jest.clearAllMocks(); // Keep this for resetting mock call counts etc.
    jest.useFakeTimers();
  });

  afterEach(() => {
    jest.useRealTimers();
    // resetAllMocksAndModules(); // REMOVED from global
    // If GameStorageManager holds state across tests, it might need manual clearing here
    // For now, let's see the impact of not resetting modules globally.
    const GsmMock = require('../services/__mocks__/gameStorageManager').GameStorageManager;
    if (GsmMock && typeof GsmMock.clearAllGames === 'function') {
      GsmMock.clearAllGames(); // Ensure GameStorageManager mock is cleared
    }
  });

  describe('startGame scenarios', () => {
    let gameCodeForBlock: string;
    const judgeNameForBlock = 'TestJudge';
    let isolatedManager: TestGameSessionManager;
    let isolatedMockedLlmService: jest.Mocked<typeof llmServiceImport>;
    let isolatedMockedGetLlmConfig: jest.MockedFunction<typeof getLlmConfig>;
    
    beforeEach(() => {
      // This block already uses jest.isolateModules, which handles module freshness for its scope.
      // We still need to clear mocks for this specific scope if not handled globally.
      jest.clearAllMocks(); 
      
      jest.isolateModules(() => {
        const { GameStorageManager: IsolatedGSMStorageMock } = require('../services/gameStorageManager');
        if (IsolatedGSMStorageMock && typeof IsolatedGSMStorageMock.clearAllGames === 'function') {
            IsolatedGSMStorageMock.clearAllGames(); // Clear storage for this isolated context
        }

        const { TestGameSessionManager: IsolatedTestGSM } = require('../testUtils/testGameSessionManager');
        isolatedMockedLlmService = require('../services/llmService') as jest.Mocked<typeof llmServiceImport>;
        const llmConfigModule = require('../llmConfigManager') as { getLlmConfig: jest.MockedFunction<typeof getLlmConfig> };
        isolatedMockedGetLlmConfig = llmConfigModule.getLlmConfig;
        
        isolatedManager = IsolatedTestGSM.createForTesting(); 
        
        const currentDefaultMockLlmConfig: LlmConfig = { apiKey: 'test-llm-api-key', modelId: 'test-model-id', apiBaseUrl: null, youtubeApiKey: 'test-youtube-api-key' };
        const mockDefaultSongs: Song[] = [{ title: 'S1', artist: 'A1' }]; 
        
        isolatedMockedGetLlmConfig.mockReturnValue(currentDefaultMockLlmConfig);
        isolatedMockedLlmService.fetchAndValidateSongList.mockResolvedValue(JSON.parse(JSON.stringify(mockDefaultSongs)));
        
        const createGameBlockResult = isolatedManager.createGame(judgeNameForBlock, { prompt: 'Test Prompt', numberOfRounds: 3, llmModel: 'default-test-model' });
        if (!createGameBlockResult.success) {
          throw new Error('Game creation failed in test setup for startGame scenarios');
        }
        gameCodeForBlock = createGameBlockResult.data.code; 
      });
    });

    it('when LLM provides fewer songs than requested › should adjust gameSettings.numberOfRounds and proceed', async () => {
      const localJudgeName = 'TestJudgeFewer';
      const localGameSettings = { prompt: 'Fewer Songs Test', numberOfRounds: 5, llmModel: 'test-model' };
      const fewerSongsFromLlm: Song[] = [ 
        { title: 'S1', artist: 'A1' }, 
        { title: 'S2', artist: 'A2' }
      ];

      isolatedMockedGetLlmConfig.mockReturnValue(defaultMockLlmConfig);
      isolatedMockedLlmService.fetchAndValidateSongList.mockResolvedValue(JSON.parse(JSON.stringify(fewerSongsFromLlm)));
      setupYouTubeEnrichmentMock(fewerSongsFromLlm.map(s => ({...s, youtubeLink: `mock://youtube/${s.title}`})));

      const createResultOp = isolatedManager.createGame(localJudgeName, localGameSettings);
      expect(createResultOp.success).toBe(true);
      if (!createResultOp.success) return; 
      const currentTestGameCode = createResultOp.data.code;

      const fetchedSongs: Song[] = await isolatedMockedLlmService.fetchAndValidateSongList(
        localGameSettings.prompt, localGameSettings.numberOfRounds,
        defaultMockLlmConfig.modelId, defaultMockLlmConfig.apiKey, defaultMockLlmConfig.apiBaseUrl 
      );
      
      const gameSongsToSet: GameSong[] = fetchedSongs.map(s => makeTestSong(s.title, s.artist, { trackUrl: s.trackUrl }));
      await isolatedManager.setSongsAndReady(currentTestGameCode, gameSongsToSet);

      const gameBeforeStart = isolatedManager.getGameSession(currentTestGameCode);
      expect(gameBeforeStart).toBeDefined();
      expect(gameBeforeStart?.gameSettings.numberOfRounds).toBe(fewerSongsFromLlm.length); 
      expect(gameBeforeStart?.songList.length).toBe(fewerSongsFromLlm.length);

      const result = await isolatedManager.startGame(currentTestGameCode, localJudgeName);
      expect(result.success).toBe(true);
      expect(result.message).toBe('Game started successfully.');
      
      const gameAfterStart = isolatedManager.getGameSession(currentTestGameCode);
      expect(gameAfterStart?.gameState).toBe('playing');
      expect(gameAfterStart?.gameSettings.numberOfRounds).toBe(fewerSongsFromLlm.length);
      expect(gameAfterStart?.songList.every(s => s.used === false)).toBe(true);
    });

    it('when LLM provides no songs › should adjust rounds to 0 and fail to start', async () => {
        const localJudgeName = 'TestJudgeNoSongs';
        const localGameSettings = { prompt: 'No Songs Test', numberOfRounds: 3, llmModel: 'test-model' };

        isolatedMockedGetLlmConfig.mockReturnValue(defaultMockLlmConfig);
        isolatedMockedLlmService.fetchAndValidateSongList.mockResolvedValueOnce([]);

        const createResultOp = isolatedManager.createGame(localJudgeName, localGameSettings);
        expect(createResultOp.success).toBe(true);
        if (!createResultOp.success) return;
        const currentTestGameCode = createResultOp.data.code;

        const fetchedSongs: Song[] = await isolatedMockedLlmService.fetchAndValidateSongList(
            localGameSettings.prompt, localGameSettings.numberOfRounds, defaultMockLlmConfig.modelId,
            defaultMockLlmConfig.apiKey, defaultMockLlmConfig.apiBaseUrl
        );
        expect(fetchedSongs).toEqual([]);
        
        const gameSongsToSet: GameSong[] = fetchedSongs.map(s => makeTestSong(s.title, s.artist, { trackUrl: s.trackUrl }));
        await isolatedManager.setSongsAndReady(currentTestGameCode, gameSongsToSet);
        
        const gameAfterSetReady = isolatedManager.getGameSession(currentTestGameCode);
        expect(gameAfterSetReady!.songList.length).toBe(0);
        expect(gameAfterSetReady!.gameSettings.numberOfRounds).toBe(0); 

        const startResult = await isolatedManager.startGame(currentTestGameCode, localJudgeName);
        expect(startResult.success).toBe(false);
        if (!startResult.success) {
            expect((startResult as { success: false; message: string; errorCode?: string }).errorCode).toBe('NO_SONGS_GENERATED');
        }
    });

    it('when LLM API call fails (fetch error in fetchAndValidateSongList) › startGame should report error', async () => {
        const localJudgeName = 'TestJudgeLLMFail';
        const localGameSettings = { prompt: 'LLM Fail Test', numberOfRounds: 3 };
        isolatedMockedGetLlmConfig.mockReturnValue(defaultMockLlmConfig);
        isolatedMockedLlmService.fetchAndValidateSongList.mockRejectedValueOnce(new Error('LLM API Down'));

        const createResultOp = isolatedManager.createGame(localJudgeName, localGameSettings);
        expect(createResultOp.success).toBe(true);
        if(!createResultOp.success) return;
        const currentTestGameCode = createResultOp.data.code;
        
        try {
            await isolatedMockedLlmService.fetchAndValidateSongList(
                localGameSettings.prompt, localGameSettings.numberOfRounds, defaultMockLlmConfig.modelId,
                defaultMockLlmConfig.apiKey, defaultMockLlmConfig.apiBaseUrl
            );
        } catch (e) { /* Expected error */ }
        await isolatedManager.setSongsAndReady(currentTestGameCode, []);
        
        const startResult = await isolatedManager.startGame(currentTestGameCode, localJudgeName);
        expect(startResult.success).toBe(false);
        if (!startResult.success) {
            expect((startResult as { success: false; message: string; errorCode?: string }).errorCode).toBe('NO_SONGS_GENERATED'); 
        }
    });

    it('when LLM configuration is missing (API Key) › startGame should report error', async () => {
        const localJudgeName = 'TestJudgeNoKey';
        const localGameSettings = { prompt: 'No Key Test', numberOfRounds: 3 };
        isolatedMockedGetLlmConfig.mockReturnValue({ ...defaultMockLlmConfig, apiKey: '' });

        const createResultOp = isolatedManager.createGame(localJudgeName, localGameSettings);
        expect(createResultOp.success).toBe(true);
        if(!createResultOp.success) return;
        const currentTestGameCode = createResultOp.data.code;

        isolatedMockedLlmService.fetchAndValidateSongList.mockImplementation(async (prompt, numRounds, model, apiKey) => {
            if (!apiKey) { throw new Error("Missing API Key for LLM"); }
            return []; 
        });

        try {
            await isolatedMockedLlmService.fetchAndValidateSongList(localGameSettings.prompt, localGameSettings.numberOfRounds, defaultMockLlmConfig.modelId, '', defaultMockLlmConfig.apiBaseUrl);
        } catch (e) { /* Expected to throw */ }
        await isolatedManager.setSongsAndReady(currentTestGameCode, []);

        const startResult = await isolatedManager.startGame(currentTestGameCode, localJudgeName);
        expect(startResult.success).toBe(false);
        if (!startResult.success) {
            expect((startResult as { success: false; message: string; errorCode?: string }).errorCode).toBe('NO_SONGS_GENERATED');
        }
    });
  });

  describe('startGame behavior direct check', () => {
    it('should correctly initialize rounds and currentRoundIndex when startGame is called (minimal)', async () => {
      jest.clearAllMocks();
      const GsmMock = require('../services/__mocks__/gameStorageManager').GameStorageManager;
      if (GsmMock && typeof GsmMock.clearAllGames === 'function') {
        GsmMock.clearAllGames();
      }

      const testManager = TestGameSessionManager.createForTesting();
      const judgeName = 'DirectCheckJudgeMin';
      const gameSettings = { prompt: 'Direct Check Min', numberOfRounds: 1, llmModel: 'test-llm' };
      const songsToSet: GameSong[] = [makeTestSong('S1', 'A1', { trackUrl: 'url1' })]; 

      mockedGetLlmConfig.mockReturnValue(defaultMockLlmConfig);

      const createResult = testManager.createGame(judgeName, gameSettings);
      expect(createResult.success).toBe(true);
      if (!createResult.success) throw new Error('Create game failed');
      const gameCode = createResult.data.code;

      await testManager.setSongsAndReady(gameCode, songsToSet);
      
      const gameBeforeStart = testManager.getGameSession(gameCode);
      expect(gameBeforeStart?.songList.length).toBe(1);
      expect(gameBeforeStart?.songList[0].title).toBe('S1');
      expect(gameBeforeStart?.gameState).toBe('ready');

      // Log songList and used status before startGame
      console.log('[SONGLIST DEBUG] songList before start:', gameBeforeStart?.songList);

      // Log the constructor name of the roundManagementService instance
      // @ts-ignore
      console.log('[SERVICE DEBUG] roundManagementService constructor:', testManager.roundManagementService?.constructor?.name);

      // --- Diagnostic: capture references before startGame ---
      const gameBeforeStartRef = gameBeforeStart;
      const roundsBeforeStartRef = gameBeforeStart?.rounds;

      const startGameResult = await testManager.startGame(gameCode, judgeName);
      console.log('[REF DEBUG] startGameResult:', JSON.stringify(startGameResult, null, 2));

      // Call startNextRound to actually create the first round (matches real system)
      testManager.startNextRound(gameCode);

      const gameAfterStart = testManager.getGameSession(gameCode);
      const roundsAfterStartRef = gameAfterStart?.rounds;

      console.log('[REF DEBUG] Same game object?', gameBeforeStartRef === gameAfterStart);
      console.log('[REF DEBUG] Same rounds array?', roundsBeforeStartRef === roundsAfterStartRef);
      console.log('[REF DEBUG] Rounds after start:', gameAfterStart?.rounds);
      console.log('[REF DEBUG] Current index after start:', gameAfterStart?.currentRoundIndex);

      expect(gameAfterStart).toBeDefined();
      if (!gameAfterStart) throw new Error('gameAfterStart is undefined');
      
      expect(gameAfterStart.rounds.length).toBe(1);
      expect(gameAfterStart.currentRoundIndex).toBe(0);
      expect(gameAfterStart.gameState).toBe('playing');
    });
  });

  describe('submitAnswer', () => {
    it('should correctly process a correct answer', async () => {
      const testManager = TestGameSessionManager.createForTesting(); 
      const judgeName = 'JudgeSubmit';
      const playerName = 'Player1Answer';
      const gameSettings = { prompt: 'Answer Test', numberOfRounds: 1, llmModel: 'test-llm' };
      const songsForRound: GameSong[] = [makeTestSong('Song 1', 'Artist 1', { trackUrl: 'url1' })];

      mockedGetLlmConfig.mockReturnValue(defaultMockLlmConfig);
      const initialSongsForEnrichment: Song[] = [makeTestSong('Song 1', 'Artist 1', {})];
      setupYouTubeEnrichmentMock(initialSongsForEnrichment.map(s => ({...s, youtubeLink: 'link1'})));

      const createResultOp = testManager.createGame(judgeName, gameSettings);
      expect(createResultOp.success).toBe(true);
      if (!createResultOp.success) return;
      const gameCode = createResultOp.data.code;

      await testManager.joinGame(gameCode, playerName);
      await testManager.setSongsAndReady(gameCode, songsForRound);

      const gameBeforeStart = testManager.getGameSession(gameCode);
      console.log('[TEST DEBUG] submitAnswer - Game before start - participants:', gameBeforeStart ? JSON.stringify(Object.fromEntries(gameBeforeStart.participants)) : 'undefined');
      console.log('[TEST DEBUG] submitAnswer - Game before start - full:', JSON.stringify(gameBeforeStart, null, 2));

      await testManager.startGame(gameCode, judgeName);
      // Call startNextRound to create the first round
      testManager.startNextRound(gameCode);

      const gameAfterStart = testManager.getGameSession(gameCode);
      console.log('[TEST DEBUG] submitAnswer - Game after start - rounds JSON:', JSON.stringify(gameAfterStart?.rounds));
      console.log('[TEST DEBUG] submitAnswer - Game after start - rounds length:', gameAfterStart?.rounds.length);
      console.log('[TEST DEBUG] submitAnswer - Game after start - currentIndex:', gameAfterStart?.currentRoundIndex);

      const currentRound = testManager.getCurrentRound(gameCode);
      expect(currentRound.success).toBe(true);
      if(!currentRound.success) return;
      expect(currentRound.data.songTitle).toBe('Song 1');

      testManager.playerBuzzIn(gameCode, playerName); 
      const answerResult = await testManager.submitAnswer(gameCode, playerName, 'Song 1', 'Artist 1');

      expect(answerResult.success).toBe(true);
      expect(answerResult.score).toBeGreaterThan(0);
    });
  });

  describe('leaveGame', () => {
    it('should clear timer if judge leaves and game is deleted (lobby or last player)', async () => {
      const testManager = TestGameSessionManager.createForTesting(); 
      const createResultOp = testManager.createGame('JudgeLeave', { prompt: 'Test', numberOfRounds: 1 });
      expect(createResultOp.success).toBe(true);
      if (!createResultOp.success) return;
      const gameCode = createResultOp.data.code;

      const leaveResult = await testManager.leaveGame(gameCode, 'JudgeLeave');
      expect(leaveResult.success).toBe(true);
      expect(leaveResult.gameEnded).toBe(true);

      const resourcePoolMockInstance = require('../services/resourcePoolManager').ResourcePoolManager.getInstance();
      expect(resourcePoolMockInstance.releaseTimerService).toHaveBeenCalledWith(gameCode);

      const gameAfterLeave = testManager.getGameSession(gameCode);
      expect(gameAfterLeave).toBeUndefined();
    });
  });
  
  describe('Tests for timer in buzzIn', () => {
    it('ORIGINAL - should set up a single timer when player buzzes in', async () => {
      const testManager = TestGameSessionManager.createForTesting();
      const judgeName = 'BuzzJudge';
      const playerName = 'BuzzPlayer';
      const gameSettings = { prompt: 'Buzz Test', numberOfRounds: 1, llmModel: 'test-llm' };
      const songs: GameSong[] = [makeTestSong('Buzz Song', 'Buzz Artist', { trackUrl: 'urlBuzz' })];

      mockedGetLlmConfig.mockReturnValue(defaultMockLlmConfig);
      const createResult = testManager.createGame(judgeName, gameSettings);
      expect(createResult.success).toBe(true);
      if (!createResult.success) return;
      const gameCode = createResult.data.code;

      await testManager.joinGame(gameCode, playerName);
      await testManager.setSongsAndReady(gameCode, songs);

      const gameBeforeStart = testManager.getGameSession(gameCode);
      console.log('[TEST DEBUG] buzzIn - Game before start - participants:', gameBeforeStart ? JSON.stringify(Object.fromEntries(gameBeforeStart.participants)) : 'undefined');

      await testManager.startGame(gameCode, judgeName);
      // Call startNextRound to create the first round
      testManager.startNextRound(gameCode);

      const gameAfterStart = testManager.getGameSession(gameCode);
      console.log('[TEST DEBUG] buzzIn - Game after start - rounds JSON:', JSON.stringify(gameAfterStart?.rounds));
      console.log('[TEST DEBUG] buzzIn - Game after start - rounds length:', gameAfterStart?.rounds.length);
      console.log('[TEST DEBUG] buzzIn - Game after start - currentIndex:', gameAfterStart?.currentRoundIndex);

      const roundState = testManager.getCurrentRound(gameCode);
      expect(roundState.success).toBe(true); 

      const buzzResult = testManager.playerBuzzIn(gameCode, playerName);
      expect(buzzResult.success).toBe(true);

      const resourcePoolMockInstance = require('../services/resourcePoolManager').ResourcePoolManager.getInstance();
      const timerServiceMockFromPool = resourcePoolMockInstance.getTimerService();
      // BuzzerManager uses timerService.startAnswerTimer
      expect(timerServiceMockFromPool.startAnswerTimer).toHaveBeenCalled(); 
    });
  });

  // Consolidated result types tests (previously in gameSessionManager.resultTypes.test.ts)
  describe('Result types and error handling', () => {
    let manager: TestGameSessionManager;
    
    beforeEach(() => {
      manager = TestGameSessionManager.createForTesting();
    });

    it('returns { success: false, message } for missing game', () => {
      const res = manager.getGameDetails('FAKE');
      expect(res).toEqual(expect.objectContaining({ success: false, message: expect.any(String) }));
    });

    it('returns { success: true, data } for created game', () => {
      const createResult = manager.createGame('Judge', { prompt: 'Test', numberOfRounds: 1 });
      expect(createResult.success).toBe(true);
      if (createResult.success) {
        const res = manager.getGameDetails(createResult.data.code);
        expect(res).toEqual(expect.objectContaining({ success: true, data: expect.any(Object) }));
      }
    });

    it('getGameState returns full session', () => {
      const createResult = manager.createGame('Judge', { prompt: 'Test', numberOfRounds: 1 });
      expect(createResult.success).toBe(true);
      if (createResult.success) {
        const res = manager.getGameState(createResult.data.code);
        expect(res).toEqual(expect.objectContaining({ success: true, data: expect.any(Object) }));
        expect(res.success && res.data).toBeTruthy();
        if (res.success) {
          expect(res.data).toHaveProperty('code');
          expect(res.data).toHaveProperty('participants');
          expect(res.data).toHaveProperty('rounds');
        }
      }
    });

    it('getCurrentRound returns error if no round', () => {
      const createResult = manager.createGame('Judge', { prompt: 'Test', numberOfRounds: 1 });
      expect(createResult.success).toBe(true);
      if (createResult.success) {
        const res = manager.getCurrentRound(createResult.data.code);
        expect(res.success).toBe(false);
        expect(res).toHaveProperty('errorCode', 'NO_ACTIVE_ROUND');
      }
    });

    it('joinGame returns error for missing game', async () => {
      const res = await manager.joinGame('FAKE', 'Player');
      expect(res.success).toBe(false);
      expect(res).toHaveProperty('errorCode', 'GAME_NOT_FOUND');
    });

    it('startGame returns error for missing game', async () => {
      const res = await manager.startGame('FAKE', 'Judge');
      expect(res.success).toBe(false);
      expect(res).toHaveProperty('errorCode', 'GAME_NOT_FOUND');
    });
  });
}); 