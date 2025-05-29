import { GameSession, AnswerRecord } from '../models/types';
import { HookService } from './hookService';
import { TimerService, TimerType } from './timerService';
import { GameLifecycleService } from './gameLifecycleService';
import { RoundManagementService } from './roundManagementService';
import { evaluateAnswer, handleAttemptFailure } from './gameRoundService';
import { GameRound } from '../models/types';
import { getSpotifyAccessToken, getSpotifyArtworkUrl } from './spotifyService';
import { fetchDeezerTrackWithArtwork } from './deezerService';
import env from '../utils/envConfig';

export class AnswerSubmissionService {
  constructor(
    private hookService: HookService,
    private timerService: TimerService,
    private gameLifecycleService: GameLifecycleService,
    private roundManagementService: RoundManagementService 
  ) {}

  /**
   * Fetch artwork from available music services
   */
  private async fetchArtworkUrl(songTitle: string, artist: string): Promise<string | null> {
    try {
      // Try Deezer first if it's the primary provider
      if (env.MUSIC_PROVIDER === 'deezer') {
        try {
          const deezerResult = await fetchDeezerTrackWithArtwork(songTitle, artist);
          if (deezerResult?.albumArtwork) {
            return deezerResult.albumArtwork;
          }
        } catch (error) {
          console.warn('[AnswerSubmissionService] Deezer artwork fetch failed:', error);
        }
      }

      // Try Spotify if available
      if (env.SPOTIFY_CLIENT_ID && env.SPOTIFY_CLIENT_SECRET) {
        try {
          const token = await getSpotifyAccessToken(env.SPOTIFY_CLIENT_ID, env.SPOTIFY_CLIENT_SECRET);
          const spotifyArtwork = await getSpotifyArtworkUrl(songTitle, artist, token);
          if (spotifyArtwork) {
            return spotifyArtwork;
          }
        } catch (error) {
          console.warn('[AnswerSubmissionService] Spotify artwork fetch failed:', error);
        }
      }

      return null;
    } catch (error) {
      console.error('[AnswerSubmissionService] Error fetching artwork:', error);
      return null;
    }
  }

  /**
   * Emit round complete event with all relevant data for the results screen
   */
  private async emitRoundCompleteEvent(game: GameSession, currentRound: GameRound, isLastRound: boolean): Promise<void> {
    try {
      // Fetch artwork for the results screen
      const artworkUrl = await this.fetchArtworkUrl(currentRound.songTitle, currentRound.artist);

      // Calculate points earned this round for each player
      const playersWithPoints = Array.from(game.participants.values())
        .filter(p => p.role === 'participant')
        .map(participant => {
          const currentScore = game.scores.get(participant.name) || 0;
          const playerAnswers = game.answers.get(participant.name) || [];
          const thisRoundAnswer = playerAnswers[game.currentRoundIndex];
          const pointsThisRound = thisRoundAnswer ? thisRoundAnswer.score : 0;
          
          return {
            name: participant.name,
            score: currentScore,
            pointsThisRound
          };
        });

      // Get all participants for round participants list
      const roundParticipants = Array.from(game.participants.values()).map(p => ({
        name: p.name,
        role: p.role
      }));

      const roundCompletePayload = {
        gameCode: game.code,
        roundNumber: game.currentRoundIndex + 1,
        correctAnswer: { title: currentRound.songTitle, artist: currentRound.artist },
        playersWithPoints,
        roundParticipants,
        artworkUrl,
        canAdvance: !isLastRound,
        isGameOver: isLastRound
      };

      console.log('[AnswerSubmissionService] Emitting onRoundComplete:', roundCompletePayload);
      this.hookService.onRoundComplete(roundCompletePayload);
    } catch (error) {
      console.error('[AnswerSubmissionService] Error emitting round complete event:', error);
    }
  }

  async submitAnswer(
    game: GameSession,
    playerName: string,
    answerInput: { songTitle: string; artist: string }
  ): Promise<{ 
    success: boolean; 
    correctSong?: boolean; 
    correctArtist?: boolean; 
    score?: number; 
    message: string; 
    points?: number; 
    status?: string; 
    allAttempted?: boolean; 
    canAdvance?: boolean;
    errorCode?: 'INVALID_STATE' | 'PLAYER_NOT_FOUND' | 'JUDGE_CANNOT_ANSWER' | 'NO_ACTIVE_ROUND' | 'NOT_BUZZED_IN';
  }> {
    console.log(`[AnswerSubmissionService] Received answer submission from ${playerName} in game ${game.code}: songTitle="${answerInput.songTitle}", artist="${answerInput.artist}"`);
    
    if (game.gameState !== 'playing') {
      console.log(`[AnswerSubmissionService] Rejecting submission - game not in playing state: ${game.gameState}`);
      return { success: false, message: 'Game is not in playing state.', errorCode: 'INVALID_STATE' };
    }
    if (!game.participants.has(playerName)) {
      console.log(`[AnswerSubmissionService] Rejecting submission - player ${playerName} not found in game`);
      return { success: false, message: 'Player not found in game.', errorCode: 'PLAYER_NOT_FOUND' };
    }
    if (playerName === game.judgeName) {
      console.log(`[AnswerSubmissionService] Rejecting submission - ${playerName} is the judge`);
      return { success: false, message: 'Judge cannot submit answers.', errorCode: 'JUDGE_CANNOT_ANSWER' };
    }

    const currentRound = this.roundManagementService.getCurrentRound(game);
    if (!currentRound) {
      console.log(`[AnswerSubmissionService] Rejecting submission - no active round`);
      return { success: false, message: 'No active round.', errorCode: 'NO_ACTIVE_ROUND' };
    }
    if (currentRound.buzzedInPlayer !== playerName) {
      console.log(`[AnswerSubmissionService] Rejecting submission - ${playerName} not buzzed in (current: ${currentRound.buzzedInPlayer})`);
      return { success: false, message: 'Buzzer is not active for you, or it is not your turn.', errorCode: 'NOT_BUZZED_IN' };
    }

    console.log(`[AnswerSubmissionService] Processing valid answer submission from ${playerName}`);
    this.timerService.clearTimer(game.code, TimerType.Answer);

    const correctAnswerDetails = { title: currentRound.songTitle, artist: currentRound.artist };
    const { correctSong, correctArtist, score, feedback } = evaluateAnswer(answerInput, correctAnswerDetails);

    console.log(`[AnswerSubmissionService] Answer evaluation result for ${playerName}: correctSong=${correctSong}, correctArtist=${correctArtist}, score=${score}`);

    currentRound.playersWhoAttempted.add(playerName);
    const answerRecord: AnswerRecord = { 
      player: playerName, 
      songTitle: answerInput.songTitle, 
      artist: answerInput.artist, 
      correctSong, 
      correctArtist, 
      score 
    };
    if (!game.answers.has(playerName)) {
      game.answers.set(playerName, []);
    }
    game.answers.get(playerName)!.push(answerRecord);

    if (score > 0) { // Correct Answer
      const currentScore = game.scores.get(playerName) || 0;
      game.scores.set(playerName, currentScore + score);
      
      // Check if this was the final round
      const isLastRound = game.currentRoundIndex >= game.gameSettings.numberOfRounds - 1;
      const canAdvanceAfterCorrect = !isLastRound; // Can advance to next round if not the last round

      console.log('[AnswerSubmissionService] Emitting onAnswerSubmitted (correct):', {
        gameCode: game.code,
        playerName,
        answer: answerInput,
        isCorrect: true,
        correctAnswer: correctAnswerDetails,
        message: feedback.message,
        points: feedback.points,
        status: feedback.status,
        canAdvance: canAdvanceAfterCorrect,
        isGameOver: isLastRound,
      });
      this.hookService.onAnswerSubmitted({
        gameCode: game.code,
        playerName,
        answer: answerInput,
        isCorrect: true,
        correctAnswer: correctAnswerDetails,
        message: feedback.message,
        points: feedback.points,
        status: feedback.status,
        canAdvance: canAdvanceAfterCorrect,
      });
      this.hookService.onScoreUpdate({
        gameCode: game.code,
        scores: game.scores
      });
      
      currentRound.isBuzzerOpenForNewAttempts = false; 
      currentRound.buzzedInPlayer = null; 
      
      // Emit round complete event for results screen
      await this.emitRoundCompleteEvent(game, currentRound, isLastRound);
      
      // If this was the final round, finish the game
      if (isLastRound) {
        this.gameLifecycleService.finishGame(game);
      }
      
      return { success: true, correctSong, correctArtist, score, message: feedback.message, points: feedback.points, status: feedback.status, canAdvance: canAdvanceAfterCorrect };
    } else { // Incorrect or partially correct
      const nonJudgeParticipants = Array.from(game.participants.values()).filter(p => p.role !== 'judge').map(p => p.name);
      const { attemptFailedMessage, allAttempted } = handleAttemptFailure(currentRound, playerName, nonJudgeParticipants);
      
      if (!allAttempted) {
        // Only emit AnswerSubmitted if the round is continuing for other players
        console.log('[AnswerSubmissionService] Emitting onAnswerSubmitted (incorrect, not all attempted):', {
          gameCode: game.code,
          playerName,
          answer: answerInput,
          isCorrect: false,
          correctAnswer: correctAnswerDetails,
          message: attemptFailedMessage,
          canAdvance: false,
        });
        this.hookService.onAnswerSubmitted({
          gameCode: game.code,
          playerName,
          answer: answerInput,
          isCorrect: false,
          correctAnswer: correctAnswerDetails,
          message: attemptFailedMessage,
          canAdvance: false, // Always include canAdvance
        });

        this.hookService.onBuzzerStatusUpdate({
          gameCode: game.code,
          isBuzzActive: true,
          activePlayerName: null,
          isAnswerPhase: false
        }); 
      } else {
        // All players have attempted, or this was the last attempt
        currentRound.isBuzzerOpenForNewAttempts = false;
        currentRound.buzzedInPlayer = null;
        
        this.hookService.onBuzzerStatusUpdate({
          gameCode: game.code,
          isBuzzActive: false,
          activePlayerName: null,
          isAnswerPhase: false
        });
        
        const isLastRound = game.currentRoundIndex >= game.gameSettings.numberOfRounds - 1;
        const canAdvanceAfterAllAttempted = !isLastRound;
        
        const hookPayload = {
          gameCode: game.code,
          message: attemptFailedMessage,
          canAdvance: canAdvanceAfterAllAttempted,
          isGameOver: isLastRound,
          correctAnswer: correctAnswerDetails
        };

        console.log('[AnswerSubmissionService] Emitting onAllPlayersAttemptedNoCorrect hook:', hookPayload);
        this.hookService.onAllPlayersAttemptedNoCorrect(hookPayload);
        
        // Emit round complete event for results screen
        await this.emitRoundCompleteEvent(game, currentRound, isLastRound);
        
        if (isLastRound) {
          this.gameLifecycleService.finishGame(game);
        }
      }
      this.hookService.onScoreUpdate({
        gameCode: game.code,
        scores: game.scores
      });
      return { 
        success: true, 
        correctSong: false, 
        correctArtist: false, 
        score: 0, 
        message: attemptFailedMessage, 
        points: feedback.points, 
        status: feedback.status, 
        allAttempted,
        canAdvance: false // Always include canAdvance in return value for consistency
      };
    }
  }
} 