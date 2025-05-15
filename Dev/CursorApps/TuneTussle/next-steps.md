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
    - [ ] Find playable YouTube links for each song (manual playback for MVP).
    - [X] Store song list and correct answers in session.

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
    - [X] All backend tests (Jest), including integration tests like `socketGameFlow.test.ts`, are passing.
    - [X] All frontend tests (Vitest), including `useGameLogic.test.tsx` and `CreateGamePage.test.tsx`, are passing.
    - [X] Codebase is significantly more stable with comprehensive test coverage.

---

## Immediate Next Steps

### 1. Test Failures Resolved
- ✅ **Backend Socket Integration Tests (`socketGameFlow.test.ts`)**: All pass.
    - Previous timing issues and event waiting problems have been resolved.
- ✅ **Backend Game Logic Tests (`gameSessionManager.test.ts`)**: All pass.
    - `submitAnswer` / `allAttempted` flag logic fixed.
    - Timer test for buzzer closing fixed.
- ✅ **Frontend Unit Tests**: All pass.
    - Socket emit call expectations in `useGameLogic.test.tsx` fixed.
    - `CreateGamePage.test.tsx` navigation URL test fixed.
    - Mocks updated to reflect actual implementations.

### 2. Complete YouTube Integration (Backend & Frontend)
- **Backend: YouTube Link Fetching**:
    - Integrate with the YouTube Data API to search for song links based on title and artist.
        - Research and select appropriate YouTube Data API client library or direct API calls.
        - Implement API key management for YouTube Data API (via environment variables - see item #3).
    - Modify `_fetchAndValidateSongList` (or a new dedicated service) in `gameSessionManager.ts` to call the YouTube API after getting song titles/artists from the LLM.
    - Add error handling for cases where YouTube links are not found or the API fails.
    - Update `GameSession`'s `songList` to store the fetched `youtubeLink`.
    - Ensure the `roundStarted` socket event payload includes the `youtubeLink` for the current song.
- **Frontend: YouTube Player**:
    - [X] The existing embedded player in `JudgeGameControls.tsx` is ready to receive valid links.
    - Verify playback and error states once backend provides links (e.g., if a link is invalid or video is unavailable).
- **Testing**:
    - Add backend tests for YouTube API integration (mocking the API calls).
    - Test frontend player with various link scenarios once backend integration is complete.

### 3. Configuration Management: Environment Variables
- **Implement Robust `.env` File Usage (Backend)**:
    - [X] `.env.example` file should be created in the `backend` directory.
    - [X] `backend/.gitignore` should include `.env`.
    - Update `llmConfigManager.ts` (and any new modules needing API keys like YouTube) to properly load all keys from `process.env`.
    - Add `dotenv` package to backend dependencies and ensure it's configured early in the application lifecycle (e.g., in `server.ts`).
    - Provide clear instructions in `README.md` or `handover.md` on setting up the `.env` file with all required variables (OpenRouter API Key, YouTube Data API Key).

### 4. Enhanced Error Handling & User Feedback (Continuous Improvement)
- **Backend Error Propagation**:
    - Review critical backend flows (game creation, start, round progression, API calls) for consistent error handling.
    - Ensure errors are converted to user-friendly messages where appropriate before sending to frontend.
- **Frontend Error Display**:
    - [X] Non-critical errors are now handled with `react-hot-toast`.
    - [X] Critical errors in `GamePage.tsx` have improved display.
    - Continue to specifically improve feedback for LLM/YouTube API failures (e.g., "Could not fetch songs, please try a different prompt" or "Could not load video for this song").
- **Socket Reconnection & State Recovery**:
    - Investigate and improve handling of socket disconnections and reconnections, aiming to restore game state where possible.
    - [X] Fixed socket connection port mismatch between frontend and backend (updated from 3000 to 4000).

### 5. Testing & Code Refinement
- **Targeted Backend Refactoring**:
    - Plan and execute refactoring of `gameSessionManager.ts` to improve modularity and reduce its size. Consider extracting LLM interaction, timer management, or round lifecycle logic into separate modules/services.
- **Judge-Specific Frontend Tests**:
    - [X] Frontend tests for `JudgeGameControls.tsx`, `QuestionDisplay.tsx`, `Scoreboard.tsx`, and `GamePage.tsx` have been updated.
    - Continue to ensure comprehensive coverage for all judge interactions.
- **Cross-Browser/Device Testing**:
    - Perform manual testing on different browsers and screen sizes to identify and fix any UI inconsistencies for the MVP.
- **End-to-End Testing**:
    - [X] All unit and integration tests for frontend and backend are passing.
    - [ ] Begin manual end-to-end testing with browser to verify full application functionality.

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
- ✅ Frontend YouTube player display (awaiting backend links)
- ✅ Frontend toast notifications for errors/feedback
- ✅ Fixed socket connection port mismatch between frontend and backend
- ✅ Fixed GameSessionManager TypeScript linter errors
- ✅ Fixed submitAnswer test for allAttempted flag
- ✅ Fixed timer test for closing buzzer when all players time out consecutively
- ✅ All gameSessionManager.test.ts tests now passing
- ✅ Fixed blank participant name visibility in lobby.
- ✅ All frontend tests fixed and passing.

### In Progress
- 🔄 Manual browser end-to-end testing
- 🔄 YouTube integration for song playback (Backend link fetching remaining)
- 🔄 Robust environment variable loading in backend

### Pending
- ⏳ Enhanced error handling for LLM/YouTube API failures (frontend message improvements).
- ⏳ Socket reconnection and state recovery.
- ⏳ Testing of the completed refactoring of `gameSessionManager.ts`.
- ⏳ Mobile optimization & Cross-browser/device testing (manual).

### Next Steps: Music Providers

- To add a new provider, implement its fetch logic and add a case to the provider switch in `musicProviderService.ts`.
- Update the frontend to allow provider selection if/when needed.
- Add tests for new providers in both backend and frontend.

---

This document will be regularly updated as development progresses. 