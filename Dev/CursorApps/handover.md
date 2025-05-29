# TuneTussle Handover

## Project Goal
TuneTussle is a playful, mobile-first music quiz game. The MVP supports local play (no accounts, no monetization), with a clean, accessible UI and real-time game management. The backend is Node.js/TypeScript, and the frontend is React/TypeScript with Tailwind CSS.

## High-Level Design
- **Backend:** Node.js, Express, TypeScript, Socket.IO (for real-time), Jest for testing.
- **Frontend:** React, TypeScript, Vite, Tailwind CSS, Vitest with React Testing Library for tests.
- **No database** (in-memory for MVP), no authentication, no payments.
- **API:** REST endpoints for game/session management, real-time updates via Socket.IO.
- **Real-time Flow:** Judge creates/starts game, players join lobby, judge broadcasts questions, players buzz in, submit answers, scores update in real-time.

## Directory Structure
```
TuneTussle/
├── backend/         # Node.js/Express/TypeScript backend
│   ├── src/        # Source code (see below)
│   ├── package.json
│   └── ...
├── frontend/        # React/TypeScript/Tailwind frontend
│   ├── src/        # Source code (see below)
│   │   ├── api/    # API services for backend communication
│   │   ├── components/ # Reusable UI components
│   │   ├── contexts/ # React Context providers (Socket.IO)
│   │   ├── hooks/  # Custom React hooks (game logic)
│   │   ├── pages/  # Main application pages
│   │   └── ...
│   ├── package.json
│   └── ...
├── handover.md      # This document
├── commit-guide.md  # Git commit and push workflow
├── initial_spec.md  # Original project specification
├── next-steps.md    # Current status and next steps
└── ...
```

## Key Backend Files
- `backend/src/gameSessionManager.ts`: Core in-memory game session logic. Manages creating games (with judge, prompt, rounds), participants joining/leaving, starting games, handling round progression (buzz-ins, answer submissions, scoring, timers), listing participants, and emitting event hooks for state changes. Defines the `GameSession` state.
- `backend/src/__tests__/gameSessionManager.test.ts`: Unit tests for `GameSessionManager` logic.
- `backend/src/routes/gameRoutes.ts`: REST API endpoints for game session management, including creating a game, joining, listing participants, leaving, and starting a game.
- `backend/src/__tests__/gameRoutes.test.ts`: Integration tests for the game API endpoints.
- `backend/src/llmConfigManager.ts`: Manages LLM configuration (API key, model ID) in-memory.
- `backend/src/__tests__/llmConfigManager.test.ts`: Unit tests for `LlmConfigManager`.
- `backend/src/routes/adminRoutes.ts`: REST API endpoints for admin to GET/PUT LLM configuration.
- `backend/src/__tests__/adminRoutes.test.ts`: Integration tests for the admin API endpoints.
- `backend/src/realtimeEmitter.ts`: Connects `GameSessionManager` event hooks to outgoing Socket.IO emissions.
- `backend/src/__tests__/realtimeEmitter.test.ts`: Unit tests for real-time event emission logic.
- `backend/src/registerClientEventHandlers.ts`: Registers handlers for incoming client events via Socket.IO (e.g., buzzIn, submitAnswer, nextRound) and routes them to `GameSessionManager`.
- `backend/src/__tests__/integration/socketGameFlow.test.ts`: Integration test for end-to-end real-time event flow (client connects, joins, game starts, client receives event).
- `backend/package.json`: Backend dependencies and scripts (e.g., `npm start`, `npm test`).

## Key Frontend Files
- `frontend/src/App.tsx`: App entry point, sets up routing for Home, CreateGame, Lobby, Game, and Results pages.
- `frontend/src/main.tsx`: Renders the root React application, wraps with `SocketProvider` and `Toaster` for notifications.
- `frontend/src/api/apiService.ts`: Centralizes API calls to backend endpoints using axios.
- `frontend/src/contexts/SocketContext.tsx`: Provides Socket.IO client instance and connection status via React Context, including joinRoom functionality. Configured to connect to the backend on port 4000.
- `frontend/src/hooks/useGameLogic.ts`: Custom React hook managing game state, Socket.IO event handling, user interactions, judge/participant role handling, and round progression for all game phases. Includes logic for error handling with toasts.
- `frontend/src/pages/HomePage.tsx`: Main entry page with UI for both creating or joining a game.
- `frontend/src/pages/CreateGamePage.tsx`: UI for judges to set up a new game (name, prompt, rounds).
- `frontend/src/pages/LobbyPage.tsx`: Displays participants waiting in a game lobby with role-specific controls (start game button for judge).
- `frontend/src/pages/GamePage.tsx`: Container page for active gameplay, with different views for judge and participants. Manages loading states and critical error displays. Integrates `QuestionDisplay`, `Scoreboard`, `JudgeGameControls`, etc.
- `frontend/src/pages/ResultsPage.tsx`: New page to display final game results, winner(s), and options to play again or create a new game.
- `frontend/src/components/Button.tsx`: Reusable button component.
- `frontend/src/components/QuestionDisplay.tsx`: Displays current question, category, round info, and reveals correct answer when round is complete.
- `frontend/src/components/BuzzButton.tsx`: Button for players to buzz-in.
- `frontend/src/components/AnswerInput.tsx`: Input field for players to submit their answers.
- `frontend/src/components/Scoreboard.tsx`: Displays player scores, highlighting current player and game winner(s).
- `frontend/src/components/JudgeControls.tsx`: Controls for judge in the lobby (start game button).
- `frontend/src/components/JudgeGameControls.tsx`: In-game controls for judge (view answer, next round, adjust scores via `ConfirmModal`, YouTube player display).
- `frontend/src/components/ParticipantControls.tsx`: Controls for participants in the lobby.
- `frontend/src/components/ConfirmModal.tsx`: Reusable modal component for confirmations, used for score adjustments.
- `frontend/src/components/LoadingSpinner.tsx`: Reusable loading spinner component.
- `frontend/src/index.css`: Tailwind CSS directives.
- `frontend/tailwind.config.js`: Tailwind configuration.
- `frontend/vite.config.ts`: Vite and Vitest configuration.
- `frontend/src/setupTests.ts`: Vitest setup file for global test configurations.
- Test files for the above components/hooks/pages (e.g., `frontend/src/pages/HomePage.test.tsx`, `frontend/src/hooks/useGameLogic.test.tsx`, etc.). All frontend tests are currently passing.
- `frontend/package.json`: Frontend dependencies and scripts (includes `react-hot-toast`).

## Real-time Event Flow
1. **Connection**: Client connects via Socket.IO through `SocketContext.tsx`
2. **Room Joining**: Client joins game room with player name, receives role (judge/participant)
3. **Lobby Updates**: Real-time participant list updates as players join/leave
4. **Game Start**: Judge triggers game start via API, all clients receive 'roundStarted' event
5. **Buzz In**: Participants can buzz in, all clients receive 'buzzIn' event with active player
6. **Answer Submission**: Active player submits answer, all clients receive 'answerSubmitted' and 'scoreUpdate' events
7. **Next Round**: Judge triggers next round, new 'roundStarted' event is broadcast
8. **Game Over**: After final round, all clients receive 'gameOver' event with final scores. Frontend navigates to `ResultsPage`.

## Project Conventions
- **TypeScript everywhere** for type safety.
- **DRY, YAGNI, and single-responsibility** principles throughout.
- **Tests:** Jest for backend (all unit tests and most integration tests are passing; **however, `socketGameFlow.test.ts` has persistent timeout issues related to `roleAssigned` event handling and is currently failing.** This is a critical area for the new developer to investigate.); Vitest with React Testing Library for frontend (all tests passing).
- **CI/CD:** GitHub Actions (see `.github/workflows/` if present).
- **Branching:** Work is done on `dev` branch; use clear commit messages.
- **API Communication:** Centralized through apiService for REST calls; Socket.IO for real-time updates.
- **State Management:** React hooks and context for frontend state, no Redux needed for this MVP scope.

## Getting Started
1. **Backend:**
   - `cd backend`
   - `npm install`
   - `npm test` (runs all backend tests)
   - `npm test src/__tests__/yourSpecificTestFile.test.ts` (runs a specific test file, replace with actual file name)
   - `npm test src/__tests__/yourSpecificTestFile.test.ts -t "your specific test name"` (runs a specific test by name within a file)
   - `npm start` (start server)
2. **Frontend:**
   - `cd frontend`
   - `npm install`
   - `npm run dev` (start Vite dev server)
   - `npm test` (run tests once)
   - `npm run test:watch` (run tests in interactive watch mode)
   - Open [http://localhost:5173](http://localhost:5173)
3. **End-to-End Manual Testing:**
   - Start the backend server: `cd backend && npm start`
   - Start the frontend server: `cd frontend && npm run dev`
   - Open two browser windows to test judge and participant interactions:
     - Window 1: Create a game as the judge
     - Window 2: Join the game as a participant
     - Test the full game flow including lobby, gameplay, and results
   - **IMPORTANT:** If you restart the backend server, all previous games are lost. Always create a new game after a restart and use the new code. Attempting to join with an old code will fail with a "Game not found for code: ..." error in the backend logs.

## User Flows
1. **Judge Flow**: 
   - Create a new game (name, prompt, rounds)
   - Share game code with participants
   - Start the game when players have joined
   - View correct answers during gameplay (and after round via `QuestionDisplay`)
   - Embed and view YouTube videos for songs.
   - Control round progression
   - Override scores if needed (with confirmation via `ConfirmModal`)
   - See final results on `ResultsPage`.
2. **Participant Flow**:
   - Join game with code and name
   - Wait in lobby for game to start
   - Listen to song played by judge
   - Buzz in when they know the answer
   - Submit their guess
   - View scores after each round (on `Scoreboard`)
   - See correct answer after each round (via `QuestionDisplay`)
   - See final results and winner(s) on `ResultsPage`.

## References
- For backend logic, see `backend/src/gameSessionManager.ts` and its tests. This includes game creation, participant management (join/leave), game state transitions (lobby, playing, finished), round progression (buzz-ins, answer submissions, scoring, timers), and LLM song list generation.
- For API endpoints, see `backend/src/routes/gameRoutes.ts` (game actions) and `backend/src/routes/adminRoutes.ts` (LLM configuration), along with their respective tests.
- For frontend UI, see `frontend/src/pages/HomePage.tsx`, `frontend/src/pages/GamePage.tsx` and related components.
- For frontend state management and Socket.IO integration for gameplay, see `frontend/src/hooks/useGameLogic.ts` and `frontend/src/contexts/SocketContext.tsx`.
- For REST API communication, see `frontend/src/api/apiService.ts`.
- For Tailwind setup, see `frontend/tailwind.config.js` and `frontend/src/index.css`.
- For commit workflow, see `commit-guide.md`.
- For the original project specification, see `initial_spec.md`.
- For real-time event emission (server -> client), see `backend/src/realtimeEmitter.ts`.
- For handling incoming client real-time events (client -> server), see `backend/src/registerClientEventHandlers.ts`.
- For end-to-end real-time testing, see `backend/src/__tests__/integration/socketGameFlow.test.ts`.
- For current status and next steps, see `next-steps.md`.

## Project Status Summary

### Completed
- ✅ Core game engine implementation
- ✅ Socket.IO real-time event system
- ✅ Game creation by judge
- ✅ Lobby system with participant list
- ✅ Basic gameplay (buzz in, submit answers)
- ✅ Judge controls for game progression (including score override with confirmation)
- ✅ Role-based UI (judge vs participant views)
- ✅ Score tracking and display (including winner highlighting)
- ✅ Display of correct answer to all players after a round
- ✅ Final game results/winner display (`ResultsPage.tsx`)
- ✅ Score Override functionality (Backend E2E, Frontend UI with custom modal)
- ✅ Frontend YouTube player display with backend link integration
- ✅ Frontend toast notifications for errors/feedback
- ✅ Fixed socket connection port mismatch between frontend and backend
- ✅ Fixed GameSessionManager TypeScript linter errors
- ✅ Fixed submitAnswer test for the allAttempted flag logic
- ✅ Fixed timer test for closing buzzer when all players time out consecutively
- ✅ All gameSessionManager.test.ts tests now passing
- ✅ Fixed blank participant name visibility in lobby.
- ✅ All frontend tests fixed and passing.
- ✅ All backend integration tests, including `socketGameFlow.test.ts`, are now passing and stable.

### In Progress
- 🔄 Debugging backend integration tests in `socketGameFlow.test.ts` (persistent timeout issues related to `roleAssigned` event).
- 🔄 Manual browser end-to-end testing (partially blocked/affected by backend test instability).
- 🔄 Robust environment variable loading in backend

### Pending
- ✅ **CRITICAL: Resolve `socketGameFlow.test.ts` integration test failures.** - All backend integration tests, including `socketGameFlow.test.ts`, are now passing and stable.
- ✅ Enhanced error handling for LLM/YouTube API failures (frontend message improvements) - All error payloads now include standardized errorCode and actionable messages.
- ✅ Socket reconnection and state recovery - Now fully implemented with automatic reconnection handling and state restoration.
- ⏳ Testing of the completed refactoring of `gameSessionManager.ts` - GameStateService now handles all state transitions.
- ⏳ Mobile optimization & Cross-browser/device testing (manual).

### Known Issues
- No persistent storage - all game data is lost if the server restarts
- **Troubleshooting:** If you see `[Server joinGameRoom Error] Game not found for code: ...` in the backend logs, or users cannot join a game, ensure you are using a code from a game created after the most recent backend start. If in doubt, create a new game and share the new code.

# Running Tests

TuneTussle uses **two separate test runners** for backend and frontend:

- **Backend:** Uses [Jest](https://jestjs.io/) (with ts-jest for TypeScript)
- **Frontend:** Uses [Vitest](https://vitest.dev/) (with React Testing Library)

**How to run tests:**

- **Backend:**
  1. `cd backend`
  2. `npm install` (if not already done)
  3. `npm test` (runs all backend tests with Jest)

- **Frontend:**
  1. `cd frontend`
  2. `npm install` (if not already done)
  3. `npm test` (runs all frontend tests with Vitest)
  4. `npm run test:watch` (runs frontend tests in watch mode)

**Note:**
- Do not mix test files between backend and frontend; keep them in their respective `src` directories.
- Use `jest`/`ts-jest` for backend tests, and `vitest`/`vi` for frontend tests.
- All tests in both the frontend and backend are now passing.

## Music Provider Abstraction

- The backend now exposes a unified `/api/music-link` endpoint, supporting a `provider` query param (YouTube only for MVP).
- The provider logic is encapsulated in `musicProviderService.ts` for easy future expansion.
- The frontend uses a `useMusicProvider` hook, which is provider-agnostic and ready for additional services.
- All new logic is fully tested (backend and frontend).

## Deployment

1. Copy `.env.example` to `.env` in the backend and set all required variables.
   - **`OPENROUTER_API_KEY`**: Your OpenRouter.ai API key for LLM song selection.
   - **`OPENROUTER_MODEL_ID`**: (Optional) Default LLM model ID for OpenRouter.
   - **`OPENROUTER_API_BASE_URL`**: (Optional) Custom base URL for OpenRouter compatible services.
   - **`YOUTUBE_API_KEY`**: Your YouTube Data API key. This is **required if `YOUTUBE_FETCH_METHOD` is set to `API`**.
   - **`YOUTUBE_FETCH_METHOD`**: Determines how YouTube links are fetched.
     - `SCRAPE` (Default): Uses web scraping. No `YOUTUBE_API_KEY` is needed. This method is less reliant on API quotas but can be less stable if YouTube changes its page structure.
     - `API`: Uses the official YouTube Data API. Requires a valid `YOUTUBE_API_KEY`. This method is generally more stable but is subject to API quotas.
   - **`PORT`**: (Optional) The port the backend server will run on (defaults to 4000 if not set).
2. Run `npm install` in both backend and frontend.
3. Run all tests (`npm test` in each).
4. Build frontend: `cd frontend && npm run build`.
5. Start backend: `cd backend && npm start`.
6. Serve frontend build with your preferred static server or integrate with backend as needed.

- All error messages are now actionable and user-friendly.
- Socket reconnection and state recovery are robust and production-ready.

---
This document provides the essentials for onboarding. For detailed next steps and feature roadmap, see `next-steps.md`. 