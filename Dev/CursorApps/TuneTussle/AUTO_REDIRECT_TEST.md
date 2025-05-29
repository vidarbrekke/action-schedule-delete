# Auto-Redirect Feature Test Guide

This document explains how to test the new auto-redirect feature where participants are automatically redirected when their judge creates a new game.

## Feature Overview

When a judge finishes a game and creates a new one, all participants from the previous game will automatically:
1. Receive a toast notification: "{JudgeName} started a new game! Joining automatically..."
2. Be redirected to the new game URL: `/game/{newGameCode}`
3. Automatically join the new game with their stored name (no need to re-enter)

## Test Scenarios

### Scenario 1: Basic Auto-Redirect Flow

**Setup:**
1. Start the app: `./run.sh --dev`
2. Judge creates game A with prompt "rock songs"
3. Participant joins game A
4. Judge starts and finishes game A
5. Judge creates game B with prompt "jazz songs"

**Expected Results:**
- Participant automatically redirected to game B
- Toast notification shown
- Participant joins game B without entering name
- Console shows auto-redirect logs

### Scenario 2: Multiple Participants

**Setup:**
1. Judge creates game with 3+ participants
2. Play through the entire game
3. Judge creates new game

**Expected Results:**
- All participants get redirected simultaneously
- All join the new game automatically
- Lobby shows all previous participants ready

### Scenario 3: Direct URL Access with Stored Info

**Setup:**
1. User has played a game (localStorage has player info)
2. User visits direct URL: `http://localhost:5173/game/NEWCODE`

**Expected Results:**
- No join form shown
- Auto-rejoining loading screen appears
- User joins game directly with stored name

## Testing Steps

### 1. Console Verification

Look for these logs in browser console:
```
[useGameLogic] Received judgeNewGame event: {...}
[useGameLogic] Auto-redirecting {playerName} to new game {gameCode}
```

Look for these logs in backend console:
```
[GameSessionManager] Tracking {X} participants from game {oldCode} for judge {judgeName}
[GameSessionManager] Judge {judgeName} creating new game {newCode}, notifying {X} previous participants
```

### 2. Network Tab Verification

1. Open browser DevTools → Network tab
2. Watch for socket events:
   - `judgeNewGame` event emitted to all clients
   - Game state updates for new game

### 3. User Experience Verification

1. **Toast notification appears** with judge name and auto-redirect message
2. **URL changes** from old game to new game automatically
3. **No join form** - user is immediately in the new game
4. **Player name preserved** - same name as previous game

## Backend Implementation Details

### Participant Tracking
```typescript
// In GameSessionManager.finishGame()
const participants = Array.from(game.participants.values())
  .filter(p => p.role === 'participant')
  .map(p => p.name);
this.judgeLastGameParticipants.set(game.judgeName, participants);
```

### Auto-Redirect Emission
```typescript
// In GameSessionManager.createGame()
if (previousParticipants && previousParticipants.length > 0) {
  this.io.emit('judgeNewGame', {
    judgeName,
    newGameCode: result.gameSession.code,
    previousParticipants,
    gameSettings
  });
}
```

## Frontend Implementation Details

### Socket Event Handler
```typescript
// In useGameLogic.ts
const handleJudgeNewGame = (data) => {
  if (currentPlayerName && data.previousParticipants.includes(currentPlayerName)) {
    savePlayerInfo(currentPlayerName, data.newGameCode, 'participant');
    toastService.success(`${data.judgeName} started a new game! Joining automatically...`);
    window.location.href = `/game/${data.newGameCode}`;
  }
};
```

### Auto-Rejoin Logic
```typescript
// In GamePage.tsx
const hasStoredPlayerInfo = playerName && gameCode && role;
const isAutoRejoining = hasStoredPlayerInfo && !hasJoinedGame && isSocketConnected;
const shouldShowJoinForm = !hasJoinedGame && !hasStoredPlayerInfo && !(playerName && role && (currentRound > 0 || currentQuestion));
```

## Error Scenarios to Test

1. **Network disconnection** during redirect - should retry when reconnected
2. **Invalid game code** - should show error gracefully
3. **Judge leaves before creating new game** - no auto-redirect should occur
4. **Participant manually navigates away** - should not be auto-redirected

## Performance Considerations

- Auto-redirect only affects previous participants (not all connected users)
- Uses existing localStorage and socket infrastructure
- Minimal additional network traffic
- Graceful degradation if localStorage is unavailable

## Browser Support

Tested on:
- Chrome (latest)
- Safari (latest)  
- Firefox (latest)
- Mobile Safari/Chrome

## Security Notes

- Only redirects users who were actual participants in the previous game
- Uses stored player names (no sensitive data in URLs)
- Validates game codes server-side before redirect
- Toast notifications prevent silent redirects (user awareness) 