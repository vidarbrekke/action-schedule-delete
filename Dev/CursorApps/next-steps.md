# Next Steps for TuneTussle MVP

This document tracks the current state and next actions for the TuneTussle MVP, aligned with the original [initial_spec.md](./initial_spec.md).

---

## I. Backend Development (Core Game Logic & Real-time Communication)

1. **Game Session Setup & Management**
    - [X] Judge can create a game session (prompt, rounds).
    - [X] Unique game codes generated and managed.
    - [X] Participants can join/leave sessions.
    - [X] Judge can start the game, changing state.
    - [X] Session state: participants, settings, round index, game state, scores.
    - [X] TDD with Jest for all logic.

2. **LLM Integration & Song List Generation**
    - [X] Integrate with OpenRouter.ai for song list (title/artist).
    - [X] Find playable YouTube links for each song (manual playback for MVP; backend fetches and provides links to the judge).
    - [X] Store song list and correct answers in session.
    - [X] System is provider-agnostic and ready for future music providers (Spotify, Apple Music, etc.).

3. **Core Game Round Logic**
    - [X] Data models for questions/rounds.
    - [X] Full round lifecycle: start, buzz-in, answer timer, answer submission, scoring, next round/game end.
    - [X] Answer verification: exact match (fuzzy/LLM future).
    - [X] Manual score override by Judge (UI implemented, backend support needed). (Backend E2E Implemented & Tested)
    - [X] TDD with Jest.

4. **Socket.IO Real-time Game Events**
    - [X] Server-side events: lobby updates (join/leave).
    - [X] Server-side events: game start, new round, buzz-in, answer submission, score updates, round summary, game over.
    - [X] Handle incoming client events: buzz-in, answer submission, next round.
    - [X] Test Socket.IO interactions.

---

## II. Frontend Development (Game Interface & Real-time Updates)

1. **Game Setup & Lobby UI**
    - [X] HomePage: join game form, error handling, navigation.
    - [X] LobbyPage: real-time participant list, connection status.
    - [X] CreateGamePage: UI for judge to create a game.
    - [X] Judge/participant role distinction in lobby (Judge has "Start Game" button).
    - [X] Vitest tests for HomePage, LobbyPage.

2. **Game Play Page/Components**
    - [X] GamePage: main gameplay interface (participant view substantially complete).
    - [X] QuestionDisplay: show current song/round state. (Enhanced to show correct answer)
    - [X] AnswerInput: for buzzed-in participant.
    - [X] Buzz-in button, real-time state.
    - [X] Scoreboard: real-time scores. (Enhanced to highlight winners)
    - [X] Consistent Tailwind styling for existing components.
    - [X] Vitest tests for new components and core game logic hook (`useGameLogic`).
    - [X] Judge-specific controls/views on GamePage (see correct answer, manual next round, score override UI). (Score override confirmation modal added)
    - [X] UI for displaying correct answer to all players after a round.
    - [X] UI for displaying final game results/winner. (Dedicated ResultsPage.tsx)

3. **Socket.IO Client Integration**
    - [X] Socket.IO client context (`SocketContext.tsx`) for connection management.
    - [X] `useGameLogic` hook handles joining game room, player name input, and processing most game state updates from server.
    - [X] Listen for: game start, new round, buzz-in success, score updates, game over (via `useGameLogic`).
    - [X] Emit: player name for join, buzz-in, answer submission (via `useGameLogic`).

4. **Client-Side Game Logic & State**
    - [X] `useGameLogic` custom hook manages core game state (current question, scores, active player, buzz/answer phase, loading/error states).
    - [X] Player name input and join flow implemented.
    - [X] UI state for most participant game phases handled.
    - [X] Judge-specific state and controls implemented.

5. **API Integration**
    - [X] Centralized API service for backend communication.
    - [X] Game creation (create new game by judge).
    - [X] Game joining (join existing game by participants).
    - [X] Game start (judge initiates game).
    - [X] Score adjustments (UI implemented, integration needed). (Backend E2E Implemented & Tested)

6. **Routing**
    - [X] Client-side routing for Home, Lobby, GamePage, CreateGamePage.
    - [X] Add route for ResultsPage (or handle on GamePage). (`/results/:gameCode` added)

---

## III. General & Best Practices

1. **Iterative Development:** Continue building/test features in small increments.
2. **API Design:** REST endpoints for game creation, session management.
3. **Continuous Testing:** Run Vitest/Jest regularly.
4. **Code Quality & Refinement:**
    - Adhere to DRY, YAGNI, single responsibility principles.
    - Conduct regular code reviews, especially for complex modules like `gameSessionManager.ts`.
    - Proactively refactor and optimize code for clarity and maintainability.
5. **Documentation:** Keep `handover.md`, `next-steps.md`, and event contracts up to date.
6. **Configuration Management**:
    - Prioritize use of environment variables (`.env` files) for API keys (e.g., OpenRouter, YouTube Data API) and other sensitive configurations.
    - Ensure `.gitignore` correctly excludes `.env` files.
    - Implement robust loading of environment variables in the backend. (Basic `.env` file present, loading needs full implementation)
    - (Future Consideration): An admin UI for managing API credentials could be explored post-MVP.
- **Testing**:
    - [X] All backend tests (Jest), including integration tests like `socketGameFlow.test.ts`, are passing (including new edge cases for music provider and routes).
    - [X] All frontend tests (Vitest), including `useGameLogic.test.tsx` and `CreateGamePage.test.tsx`, are passing.
    - [X] Codebase is significantly more stable with comprehensive test coverage.

> **⚠️ Backend Restart Warning:**
> TuneTussle's backend is in-memory only for the MVP. **All game sessions are lost when the server restarts.** After a backend restart, you must create a new game and use the new code. Attempting to join with an old code will result in a "Game not found for code: ..." error in the backend logs and a join failure for users.

---

## Immediate Next Steps

### 1. Test Failures Resolved
- ✅ **Backend Socket Integration Tests (`socketGameFlow.test.ts`)**: All tests are now passing and stable. The persistent timeout and event delivery issues have been fully resolved.
- ✅ **Backend Game Logic Tests (`gameSessionManager.test.ts`)**: All pass.
    - `submitAnswer` / `allAttempted` flag logic fixed.
    - Timer test for buzzer closing fixed.
- ✅ **Frontend Unit Tests**: All pass.
    - Socket emit call expectations in `useGameLogic.test.tsx` fixed.
    - `CreateGamePage.test.tsx` navigation URL test fixed.
    - Mocks updated to reflect actual implementations.

### 2. YouTube Integration (Backend & Frontend) Complete
- **Backend: YouTube Link Fetching**:
    - The backend now fetches YouTube links for each song and provides them to the judge for playback.
    - The system is provider-agnostic and ready for future music providers (Spotify, Apple Music, etc.).
    - Robust error handling for missing/invalid links.
    - All related backend tests (including edge cases) are passing.
- **Frontend: YouTube Player**:
    - The embedded player in `JudgeGameControls.tsx` displays the YouTube link if available.
    - Handles error states if a link is invalid or missing.
    - All related frontend tests are passing.

### 3. Configuration Management: Environment Variables ✅
- **Implement Robust `.env` File Usage (Backend)**:
    - [X] `.env.example` file should be created in the `backend` directory.
    - [X] `backend/.gitignore` should include `.env`.
    - ✅ All environment variables properly loaded in server startup.
    - ✅ Clear instructions in `handover.md` for setting up the `.env` file with all required variables.

### 4. Enhanced Error Handling & User Feedback ✅
- **Backend Error Propagation**:
    - ✅ All critical backend flows (game creation, start, round progression, API calls) now have consistent error handling with standardized errorCode and actionable messages.
    - ✅ Errors are converted to user-friendly messages before sending to frontend.
- **Frontend Error Display**:
    - [X] Non-critical errors are now handled with `react-hot-toast`.
    - [X] Critical errors in `GamePage.tsx` have improved display.
    - ✅ Improved feedback for LLM/YouTube API failures with specific, actionable messages.
- **Socket Reconnection & State Recovery**:
    - ✅ Robust handling of socket disconnections and reconnections with full state restoration.
    - [X] Fixed socket connection port mismatch between frontend and backend (updated from 3000 to 4000).

### 5. Testing & Code Refinement
- **Targeted Backend Refactoring**:
    - ✅ `gameSessionManager.ts` has been refactored to extract state transition logic to `GameStateService`.
    - ⏳ Testing of the completed refactoring.
- **Judge-Specific Frontend Tests**:
    - [X] Frontend tests for `JudgeGameControls.tsx`, `QuestionDisplay.tsx`, `Scoreboard.tsx`, and `GamePage.tsx` have been updated.
    - Continue to ensure comprehensive coverage for all judge interactions.
- **Cross-Browser/Device Testing**:
    - ⏳ Perform manual testing on different browsers and screen sizes to identify and fix any UI inconsistencies for the MVP.
- **End-to-End Testing**:
    - [X] All unit and integration tests for frontend and backend are passing.
    - ⏳ Begin manual end-to-end testing with browser to verify full application functionality.

### 6. Deployment Readiness ✅
- **Documentation**:
    - ✅ Deployment instructions added to `handover.md` covering environment setup, build, and running production build.
    - ✅ Updated all documentation to reflect current project status.

**Troubleshooting:**
- If you see `[Server joinGameRoom Error] Game not found for code: ...` in the backend logs, or users cannot join a game, ensure that:
  - The backend has not been restarted since the game was created.
  - You are using a code from a game created after the most recent backend start.
  - If in doubt, create a new game and share the new code with participants.

---

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
- ✅ Fixed submitAnswer test for allAttempted flag
- ✅ Fixed timer test for closing buzzer when all players time out consecutively
- ✅ All gameSessionManager.test.ts tests now passing
- ✅ Fixed blank participant name visibility in lobby.
- ✅ All frontend tests fixed and passing.
- ✅ Backend and frontend are provider-agnostic and ready for additional music providers.
- ✅ All backend tests including socketGameFlow.test.ts are now passing and stable.
- ✅ Enhanced error handling for LLM/YouTube API failures with standardized error codes and actionable messages.
- ✅ Socket reconnection and state recovery with automatic reconnection and state restoration.
- ✅ Refactored gameSessionManager.ts to extract state transitions to GameStateService.
- ✅ Deployment documentation with clear instructions for environment setup and production build.

### In Progress
- 🔄 Testing of the completed gameSessionManager.ts refactoring.
- 🔄 Manual browser end-to-end testing.

### Pending
- ⏳ Mobile optimization & Cross-browser/device testing (manual).

### Next Steps: Music Providers

- To add a new provider, implement its fetch logic and add a case to the provider switch in `musicProviderService.ts`.
- Update the frontend to allow provider selection if/when needed.
- Add tests for new providers in both backend and frontend.

---

This document will be regularly updated as development progresses. 

- ✅ Enhanced error handling for LLM/YouTube API failures (frontend message improvements, actionable error codes/messages everywhere).
- ✅ Socket reconnection and state recovery are robust and production-ready.
- ✅ Deployment instructions are now included in handover.md. 