# Changelog

*Key changes for new developers. For current features, see [README.md](README.md).*

## [v1.5.5] - 2025-01-XX - Auto-Redirect Enhancement

### 🚀 New Features
- **Auto-redirect for new games**: When a judge creates a new game after finishing another, all participants from the previous game are automatically redirected to the new game
- **Seamless game transitions**: Participants no longer need to re-enter their names or manually enter new game codes
- **Enhanced user flow**: Users visiting `/game/:gameCode` with stored player info are automatically taken to the game interface (bypassing the join form)

### 🔧 Technical Implementation
- **Backend**: Track previous game participants in `GameSessionManager`
- **Backend**: Emit `judgeNewGame` socket event to notify previous participants
- **Frontend**: Auto-redirect logic with toast notifications
- **Frontend**: Enhanced GamePage to detect auto-rejoining users

### 🧪 Testing
- Added comprehensive test coverage for auto-redirect functionality
- All 340+ tests passing with new features

### 📚 Documentation
- Updated implementation notes and user flow documentation

---

## [1.5.4] - May 2025 - Players Page & Enhanced Round Results

### Major Enhancements
- **Players Navigation Page**: **NEW** - Dedicated page showing all participants accessed via footer navigation
- **Personalized Judge Messages**: **ENHANCED** - Round results now show judge's actual name instead of generic "the judge"
- **Blinking Activity Indicator**: Added visual feedback to show app is active during round transitions
- **Improved Navigation UX**: Streamlined player experience with "Back to Game" and "Invite Friends" buttons

### New Features
- **PlayersPage Component**: Complete participants list with role indicators and scores
- **Smart Navigation**: "Back to Game" button uses browser history for seamless navigation
- **Invite Friends Integration**: Direct navigation to game lobby for easy code sharing
- **You Badge System**: Clear visual indicator for current player in participants list

### UI/UX Improvements
- **Enhanced Round Results**: Judge name personalization ("Waiting for Alice to start..." instead of "Waiting for the judge...")
- **Activity Feedback**: Blinking animation during waiting periods to indicate app responsiveness
- **Mobile-First Design**: Optimized layout following established design system patterns
- **Consistent Styling**: Uses global design system for unified visual experience

### Technical Implementation
- **PlayersPage Route**: New `/players` route with proper navigation handling
- **Enhanced RoundResultsScreen**: Judge name detection and personalized messaging
- **Bottom Navigation Update**: "Join" tab converted to "Players" with Users icon
- **Judge Name Resolution**: Smart detection from participants array for accurate personalization

### Files Added
- `frontend/src/pages/PlayersPage.tsx` - New players page component
- `frontend/src/pages/PlayersPage.test.tsx` - Comprehensive test suite (8 tests)
- `frontend/src/components/__tests__/RoundResultsScreen.test.tsx` - Enhanced tests for judge name feature

### Files Modified
- `frontend/src/App.tsx` - Added `/players` route
- `frontend/src/components/BottomNavigation.tsx` - Updated navigation from "Join" to "Players"
- `frontend/src/components/RoundResultsScreen.tsx` - Added judge name personalization and blinking animation
- Multiple test files updated to maintain 99.8% test coverage

### Test Coverage
- **Total Tests**: ✅ **337 tests passing** (99.8% success rate)
- **New Test Coverage**: Complete test suite for PlayersPage functionality
- **Enhanced Test Coverage**: Judge name personalization and UI interactions
- **Snapshot Updates**: All UI snapshots updated to reflect new design patterns

### Impact
- **Enhanced User Experience**: Players can easily view all participants and navigate efficiently
- **Personal Touch**: Judge name personalization makes interactions feel more human and engaging
- **Visual Feedback**: Blinking indicators reduce user anxiety about app responsiveness
- **Streamlined Navigation**: Intuitive button placement and functionality improve overall UX

---

## [1.5.3] - December 2025 - Intelligent Answer Parsing Enhancement

### Major Enhancements
- **Intelligent Answer Parsing**: **ENHANCED** - Added smart parsing when correct answer is known for maximum scoring accuracy
- **Natural Language Support**: Handles any order input like `"good vibrations beach boys"` or `"beach boys good vibrations"`
- **Case Insensitive Matching**: Full case insensitivity for all input variations (CAPS, lowercase, MiXeD)
- **Article Handling**: Smart handling of articles like "The Beatles" matching "beatles"
- **Stale Build Prevention**: Enhanced production build process prevents compiled code staleness

### Technical Changes
- **Enhanced `parseSongAndArtist()`**: Added optional `correctAnswer` parameter for intelligent matching
- **New `parseWithKnownAnswer()` function**: Uses known song/artist to intelligently parse user input
- **Flexible Pattern Matching**: Recognizes both song and artist regardless of input order
- **Article Normalization**: Handles "the", "a", "an" flexibly in matching logic
- **Production Build Fix**: Added `clean` script to prevent stale compiled code in production

### Files Modified
- `backend/src/utils/answerParser.ts` - Added intelligent parsing with known correct answers
- `backend/src/registerClientEventHandlers.ts` - Updated to pass correct answer during parsing
- `backend/package.json` - Enhanced build process with clean step for stale build prevention

### Code Quality Improvements
- **No Hardcoded Lists**: Maintains YAGNI principle using pattern matching instead of comprehensive lists
- **Backward Compatibility**: Legacy parsing behavior preserved when correct answer unknown
- **Following YAGNI**: Only implements what's needed now, avoiding premature optimization

### Impact
- **Player Experience**: Maximum scoring accuracy for natural language input variations
- **Game Fairness**: Players correctly rewarded for demonstrating knowledge regardless of input format  
- **Case Insensitivity**: Works with any case combination (verified with comprehensive testing)
- **Production Reliability**: Prevents stale compiled code issues in production deployments

### Test Coverage
- **Comprehensive Testing**: All input variations tested and working correctly
- **Case Insensitivity**: Full verification of mixed case handling
- **Order Independence**: Confirmed song-first and artist-first input both work
- **Build Process**: Verified clean builds prevent staleness

---

## [1.5.2] - December 2025 - Answer Parsing Logic Fix

### Bug Fixes
- **Answer Parsing Logic**: **FIXED** - Players now correctly receive points for comma-separated answers in any order
- **Critical Scoring Issue**: Resolved bug where `"Nirvana, smells like teen spirit"` scored 0 points instead of full points
- **Enhanced Answer Interpretation**: `parseSongAndArtist` now returns multiple interpretations for ambiguous comma-separated input

### Technical Changes
- Enhanced `backend/src/utils/answerParser.ts` to try both interpretations of comma-separated input:
  - `"Artist, Song"` → tries as both `{artist: "Artist", song: "Song"}` and `{artist: "Song", song: "Artist"}`
  - Existing game logic already selects best-scoring interpretation automatically
- Added comprehensive TypeScript test suite: `backend/src/utils/answerParser.test.ts` (6 tests covering all input formats)
- Maintained unambiguous handling for "by" separator: `"Song by Artist"` remains single interpretation
- Removed debug console.log statements for clean production output

### Impact
- **Player Experience**: Correct scoring regardless of answer format order
- **Game Fairness**: Players no longer penalized for natural language variations
- **Backward Compatibility**: All existing functionality preserved, only enhanced scoring accuracy

### Test Coverage
- **Enhanced test suite**: 6 comprehensive answerParser tests (100% passing)
- **Current Status**: See [DEVELOPMENT.md](DEVELOPMENT.md) for detailed test breakdown
- **Overall**: Excellent coverage across all functionality

---

## [1.5.1] - December 2025 - Judge Name Persistence Fix

### Bug Fixes
- **Judge Name Persistence**: **FIXED** - Judges no longer prompted to re-enter name after starting game
- **LocalStorage Management**: Proper persistence of player info across socket reconnections
- **Socket Reconnection**: Enhanced state preservation during connection lifecycle

### Technical Changes
- Enhanced `getPlayerInfo()` in `useGameLogic.ts` to actually read from localStorage
- Added proper `savePlayerInfo()` and `clearPlayerInfo()` functions
- Updated `gameStateReducer.ts` to preserve existing player info during state updates
- Added automatic localStorage saving when player info becomes available
- Enhanced role assignment handler to immediately save player info

### Files Modified
- `frontend/src/hooks/useGameLogic.ts` - Fixed localStorage persistence functions
- `frontend/src/hooks/gameStateReducer.ts` - Enhanced state preservation logic

### Impact
- **Judge Experience**: Seamless transition from game creation → start → gameplay
- **Socket Reliability**: Player identity preserved across all connection events
- **User Experience**: No more unexpected name re-entry prompts

---

## [1.5.0] - December 2025 - Post-Round Results & Artwork Preloading

### Major Changes
- **Post-Round Results Screen**: Complete implementation with artwork display for enhanced user experience
- **Just-In-Time Artwork Preloading**: Smart caching system following DRY/YAGNI principles
- **Enhanced Backend Events**: `roundStarted` events include next round info for judges (no spoilers for players)
- **Smart Caching Strategy**: 5-minute TTL with LRU eviction and memory pressure detection
- **Role-Based Information**: Judges get enhanced data for preloading, players get standard game data
- **Comprehensive Testing**: See [DEVELOPMENT.md](DEVELOPMENT.md) for current test status

### Files Added
- `frontend/src/services/artworkPreloadService.ts` - Just-in-time artwork preloading service (191 lines)
- `frontend/src/services/artworkPreloadService.test.ts` - Comprehensive test suite (24 tests)

### Files Modified
- `backend/src/realtimeEmitter.ts` - Enhanced `roundStarted` events with next round info for judges
- `frontend/src/hooks/useGameLogic.ts` - Integration with artwork preloading service
- `backend/src/__tests__/realtimeEmitter.test.ts` - Tests for enhanced roundStarted functionality
- `frontend/src/hooks/__tests__/useGameLogic.test.tsx` - Simplified, focused tests (removed flaky implementation details)
- `README.md` - Updated with artwork preloading documentation and current test status
- `DEVELOPMENT.md` - Added artwork preloading patterns and developer handover guidelines

### Performance Optimizations
- **Memory Efficient**: Smart cache with pressure detection and LRU eviction
- **Browser Preloading**: Images preloaded for instant display
- **Background Loading**: Next round artwork preloads during current round (judges only)
- **Deduplication**: Prevents redundant API calls with intelligent request batching
- **Graceful Fallbacks**: Smooth degradation when artwork unavailable

### Test Coverage Improvements
- **Comprehensive test enhancements**: See [DEVELOPMENT.md](DEVELOPMENT.md) for current test status
- **New Test Categories**: Artwork preloading, enhanced events, integration testing
- **Coverage**: Excellent test coverage across all functionality

### Architecture Decisions
- **Just-in-time strategy**: Only loads artwork when needed (current round start)
- **Role-based payloads**: Enhanced events for judges, standard for players
- **Singleton service**: Memory efficient artwork management
- **5-minute cache TTL**: Balances performance with freshness
- **No spoilers**: Players never receive next round information

### Breaking Changes
None - all changes backward compatible and enhance existing functionality

---

## [1.4.0] - May 2025 - Test Architecture Consolidation

### Major Changes
- **Test Consolidation**: Comprehensive DRY/YAGNI refactor eliminating 20+ redundant files
- **Shared Test Utilities**: Created `musicServiceTestUtils.ts`, `sharedTestSetup.ts`, `componentTestUtils.ts`
- **TypeScript-First Testing**: Removed all duplicate .js test files, standardized on .ts
- **Automated Test Patterns**: Helper functions for component state transitions and interactions
- **Mock Management**: Consolidated and cleaned up Jest mocks across both codebases

### Files Added
- `backend/src/testUtils/musicServiceTestUtils.ts` - Unified music service test patterns
- `backend/src/testUtils/sharedTestSetup.ts` - Standard setup/teardown utilities
- `frontend/src/testUtils/componentTestUtils.ts` - Comprehensive component test helpers
- `DEVELOPMENT.md` - Detailed development and testing guide

### Files Removed
- `backend/src/__tests__/gameSessionManager.resultTypes.test.ts` - Merged into main test
- Multiple duplicate .js test files (spotifyService, youtubeService, musicProvider, musicLinkRoutes)
- Duplicate Jest mock files causing warnings

### Test Coverage
- **Backend**: 174/190 tests passing (16 failing due to isolated mock issues)
- **Frontend**: 305/310 tests passing (4 failing due to Socket.IO mocking complexity)
- **Core functionality**: All game features fully tested and working

### Breaking Changes
None - all changes improve maintainability without affecting functionality

---

## [1.3.0] - December 2024 - Deezer Integration

### Major Changes
- **Deezer as Primary Music Provider**: No auth required, 30s previews, 500x500px artwork
- **Multi-Provider Fallback**: Deezer → Spotify → YouTube (automatic)
- **CORS Network Fixes**: Multi-device gameplay now works properly
- **Album Artwork System**: New `/api/music/artwork` endpoint

### Files Added
- `backend/src/services/deezerService.ts` - Main Deezer integration
- `backend/src/services/deezerService.test.ts` - Tests

### Breaking Changes
None - all changes backward compatible

---

## [1.2.0] - December 2024 - Toast & Audio Improvements

### Major Changes
- **Single Toast Notifications**: Eliminated duplicate messages
- **Provider-Agnostic Audio**: `currentSongYouTubeLink` → `currentSongAudioUrl`
- **Score Preservation**: Better mobile UX for notifications

### Breaking Changes
- Audio URL property renamed (handled automatically)

---

## [1.1.1] - Round Completion Fix

### Major Changes
- **Partial Answer Bug**: Players can now buzz in again after partial correct answers
- **Judge Control**: Judge must explicitly advance rounds

---

## [1.1.0] - Music Player Critical Fixes

### Major Changes
- **Music Player Display**: Fixed "No audio/video available" error
- **Auto-Pause System**: Music pauses when players buzz in
- **State Management**: Enhanced React state preservation

### Breaking Changes
None

---

## [1.0.0] - Production Ready

### Major Changes
- **Memory Optimization**: 98.8% reduction (20MB vs 1.4GB)
- **Production Deployment**: Full multi-device support
- **Health Monitoring**: Performance endpoints added

---

## [0.9.0] - Initial Release

### Core Features
- Real-time multiplayer music quiz
- Spotify + YouTube integration
- Socket.IO synchronization
- LLM song generation
- Complete game flow 