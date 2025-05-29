import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { User } from 'lucide-react';
import QuestionDisplay from '../components/QuestionDisplay';
import BuzzButton from '../components/BuzzButton';
import AnswerInput from '../components/AnswerInput';
import Scoreboard from '../components/Scoreboard';
import JudgeGameControls from '../components/JudgeGameControls';
import RoundResultsScreen from '../components/RoundResultsScreen';
import MobileAppLayout from '../components/MobileAppLayout';
import FloatingCard from '../components/FloatingCard';
import { useGameLogic } from '../hooks/useGameLogic';
import LoadingSpinner from '../components/LoadingSpinner';
import PageTransition from '../components/PageTransition';
import { useGameAnimations } from '../hooks/useGameAnimations';
import { components, layout, typography, colors, utils } from '../styles/designSystem';

const GamePage: React.FC = () => {
  // All hooks at the top!
  const navigate = useNavigate();
  const {
    gameCode,
    playerName,
    hasJoinedGame,
    currentQuestion,
    currentRound,
    totalRounds,
    scores,
    activePlayerName,
    isLoading,
    error,
    isBuzzButtonDisabled,
    isAnswerInputDisabled,
    isJudge,
    correctAnswer,
    isRoundComplete,
    handleBuzzIn,
    handleSubmitAnswer,
    handleNextRound,
    handleEndRound,
    handleAdjustScore,
    setPlayerNameAndInitiateJoin,
    currentSongAudioUrl,
    prompt,
    infoMessage,
    isGameOver,
    canAdvance,
    role,
    isSocketConnected,
    artworkUrl,
    infoFeedback,
    showRoundResults,
    roundResults
  } = useGameLogic();
  const [nameInputValue, setNameInputValue] = useState('');
  const { shakeInput } = useGameAnimations();
  
  // All useEffect hooks must be called before any conditional returns
  useEffect(() => {
    console.log('[GamePage] Nav Effect Check. isGameOver:', isGameOver, 'gameCode:', gameCode, 'Scores present:', !!scores, 'Navigate fn present:', !!navigate, 'isJudge:', isJudge, 'playerName:', playerName);
    if (isGameOver) {
      if (gameCode) {
        console.log('[GamePage] Game is over AND gameCode is present, attempting to navigate to results.', { gameCode, scores, judgeName: isJudge ? playerName : undefined });
        navigate(`/results/${gameCode}`, { state: { scores, gameCode, judgeName: isJudge ? playerName : undefined } });
      } else {
        console.warn('[GamePage] Game is over, but gameCode is MISSING. Cannot navigate to results. Current state:', { isGameOver, gameCode, scoresLength: scores?.length, isJudge, playerName });
      }
    }
  }, [isGameOver, gameCode, scores, navigate, isJudge, playerName]);
  
  // NOTE: Toast handling for infoMessage is now centralized in useGameEffects.ts
  // This prevents duplicate toasts from appearing

  // NOTE: Error handling is now also centralized in useGameEffects.ts
  // This prevents duplicate error toasts from appearing

  // Add state synchronization debugging
  useEffect(() => {
    console.log('[GamePage] State Debug:', {
      hasJoinedGame,
      playerName,
      isLoading,
      role,
      gameCode,
      isSocketConnected
    });
  }, [hasJoinedGame, playerName, isLoading, role, gameCode, isSocketConnected]);

  const handleNameSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (nameInputValue.trim()) {
      setPlayerNameAndInitiateJoin(nameInputValue.trim());
    } else {
      console.warn('Player name cannot be empty');
    }
  };
  const handlePlayerSubmitAnswer = (answer: { raw: string }) => {
    if (isRoundComplete) return;
    // Send the raw answer directly - let backend handle intelligent parsing
    handleSubmitAnswer(answer);
  };
  // Now, do your conditional returns
  if (isGameOver) {
    // Filter out judges for winner calculation only
    const participantScores = scores?.filter?.(player => player.role !== 'judge') || [];
    // Find the highest score(s) for highlightWinners
    let highlightWinners: string[] = [];
    if (participantScores.length > 0) {
      const maxScore = Math.max(...participantScores.map(p => p.score));
      highlightWinners = participantScores.filter(p => p.score === maxScore).map(p => p.id);
    }
    return (
      <main className={`${utils.centerContent} ${layout.container.page} ${utils.bgGradientHero} ${colors.hero.game}`}>
        <section className={`${utils.mobile} mt-8 mb-4`}>
          <h1 className={`${typography.heading.xl} text-center text-indigo-800 mb-2 drop-shadow-lg`}>
            Game Over
          </h1>
          <Scoreboard scores={scores} highlightWinners={highlightWinners} />
        </section>
      </main>
    );
  }

  // ENHANCED: Check if we should show the join form or the game interface
  // Allow bypass if user has a valid role and game state (fixes state corruption issues)
  // ALSO: If user has stored player info and is auto-rejoining, don't show join form
  const hasStoredPlayerInfo = playerName && gameCode && role;
  const isAutoRejoining = hasStoredPlayerInfo && !hasJoinedGame && isSocketConnected;
  
  // Enhanced error detection: if we have an error about game not found, don't auto-rejoin
  const hasGameNotFoundError = error && (error.toLowerCase().includes('game not found') || error.toLowerCase().includes('failed to join'));
  const shouldShowJoinForm = !hasJoinedGame && !hasStoredPlayerInfo && !(playerName && role && (currentRound > 0 || currentQuestion || (role === 'judge' && hasJoinedGame)));
  
  if (shouldShowJoinForm || hasGameNotFoundError) {
    console.log('[GamePage] Showing join form', { isLoading, playerName, hasJoinedGame, role, currentRound, currentSongAudioUrl });
    if (isLoading && playerName) {
      return (
        <div className="min-h-screen flex flex-col items-center justify-center bg-gray-50 p-4">
          <LoadingSpinner message={`Joining game as ${playerName}...`} size="lg" />
        </div>
      );
    }

    return (
      <MobileAppLayout 
        title="Join Game" 
        subtitle={gameCode ? `Code: ${gameCode}` : undefined}
        showHeader={false}
        showBackButton={false}
        showBottomNav={true}
      >
        <div className={`${layout.padding.page} flex flex-col justify-center min-h-full`}>
          <div className="flex-1 flex items-center justify-center">
            <FloatingCard className={`${utils.mobile} ${components.card.floating}`}>
              <form onSubmit={handleNameSubmit} className={layout.spacing.section}>
                <div className="text-center">
                  <div className="flex items-center justify-center gap-3 mb-3">
                    <div className={`${components.icon.container} ${components.icon.heroMd} bg-indigo-100 rounded-xl`}>
                      <User size={24} className="text-indigo-600" />
                    </div>
                    <h2 className={`${typography.body.lg} ${typography.weight.semibold} ${colors.text.primary}`}>Enter Your Name</h2>
                  </div>
                  <p className={`${colors.text.secondary} ${typography.body.sm}`}>Ready to join the music challenge?</p>
                </div>
                
                <div>
                  <label htmlFor="playerName" className={`block ${typography.body.sm} ${typography.weight.medium} ${colors.text.primary} mb-2`}>
                    Player Name
                  </label>
                  <input 
                    type="text"
                    id="playerName"
                    value={nameInputValue}
                    onChange={(e) => setNameInputValue(e.target.value)}
                    className={components.input.base}
                    placeholder="Enter your name"
                    required
                  />
                  {error && (
                    <p className={`${colors.status.error.split(' ')[0]} ${typography.body.sm} mt-1`}>{error}</p>
                  )}
                </div>
                
                {isLoading && !playerName && (
                  <div className={components.loading.spinner}>
                    <LoadingSpinner size="sm" message="Connecting..." />
                  </div>
                )}
                
                {!error ? (
                  <button 
                    type="submit" 
                    disabled={isLoading}
                    className={`${components.button.primary} ${components.button.disabled}`}
                  >
                    {isLoading && playerName ? 'Joining...' : (isLoading ? 'Connecting...' : 'Join Game')}
                  </button>
                ) : (
                  <div className={layout.spacing.tight}>
                    <button
                      type="button"
                      onClick={() => navigate('/')}
                      className={`${components.button.primary} ${components.button.disabled}`}
                    >
                      Join Game
                    </button>
                    <button
                      type="button"
                      onClick={() => navigate('/create-game')}
                      className={components.button.secondary}
                    >
                      Create New Game
                    </button>
                  </div>
                )}
              </form>
            </FloatingCard>
          </div>
        </div>
      </MobileAppLayout>
    );
  }

  // Show auto-rejoining loading screen for users with stored player info
  if (isAutoRejoining) {
    console.log('[GamePage] Auto-rejoining with stored player info', { playerName, gameCode, role });
    
    // Don't show auto-rejoin spinner if we have a game not found error
    if (hasGameNotFoundError) {
      console.log('[GamePage] Game not found error detected, skipping auto-rejoin spinner');
      return (
        <MobileAppLayout 
          title="Game Not Found"
          showHeader={false}
          showBackButton={false}
          showBottomNav={true}
        >
          <div className={`${utils.centerContent} min-h-full`}>
            <FloatingCard className={`${utils.mobile} text-center`}>
              <div className={layout.spacing.component}>
                <div className={typography.heading.hero}>🎵</div>
                <h1 className={`${typography.heading.md} ${colors.text.primary}`}>Game Not Available</h1>
                <p className={colors.text.secondary}>The game you're trying to join doesn't exist or has ended.</p>
                <p className={`${colors.text.muted} ${typography.body.sm}`}>Games are automatically cleaned up after 30 minutes of inactivity.</p>
                
                <div className={`${layout.spacing.tight} pt-4`}>
                  <button
                    onClick={() => navigate('/')}
                    className={`${components.button.primary} ${components.button.disabled}`}
                  >
                    Join Another Game
                  </button>
                  <button
                    onClick={() => navigate('/create-game')}
                    className={components.button.secondary}
                  >
                    Create New Game
                  </button>
                </div>
              </div>
            </FloatingCard>
          </div>
        </MobileAppLayout>
      );
    }
    
    return (
      <MobileAppLayout 
        title="Rejoining Game"
        showHeader={false}
        showBackButton={false}
        showBottomNav={true}
      >
        <div className={`${utils.centerContent} min-h-full`}>
          <LoadingSpinner message={`Rejoining game as ${playerName}...`} size="lg" />
        </div>
      </MobileAppLayout>
    );
  }

  if (isLoading) {
    console.log('[GamePage] Loading');
    return (
      <MobileAppLayout 
        title="Loading"
        showHeader={false}
        showBackButton={false}
        showBottomNav={true}
      >
        <div className={`${utils.centerContent} min-h-full`}>
          <LoadingSpinner message="Loading game content..." size="lg" />
        </div>
      </MobileAppLayout>
    );
  }

  if (error) {
    console.log('[GamePage] Error', error);
    
    return (
      <MobileAppLayout 
        title="Connection Error"
        showHeader={false}
        showBackButton={false}
        showBottomNav={true}
      >
        <div className={`${layout.padding.page} flex flex-col justify-center min-h-full`}>
          <div className="flex-1 flex items-center justify-center">
            <FloatingCard className={`${utils.mobile} text-center`}>
              <div className={layout.spacing.component}>
                <div className={typography.heading.hero}>😔</div>
                <h1 className={`${typography.heading.md} ${colors.text.primary}`}>Oops! Something went wrong.</h1>
                <p className={colors.text.secondary}>We encountered an error: {error}</p>
                <p className={`${colors.text.muted} ${typography.body.sm}`}>Please try again or create a new game.</p>
                
                <div className={`${layout.spacing.tight} pt-4`}>
                  <button
                    onClick={() => navigate('/')}
                    className={`${components.button.primary} ${components.button.disabled}`}
                  >
                    Join Game
                  </button>
                  <button
                    onClick={() => navigate('/create-game')}
                    className={components.button.secondary}
                  >
                    Create New Game
                  </button>
                </div>
              </div>
            </FloatingCard>
          </div>
        </div>
      </MobileAppLayout>
    );
  }

  // Show round results screen if it should be displayed
  if (showRoundResults && roundResults) {
    return (
      <RoundResultsScreen
        roundNumber={roundResults.roundNumber}
        correctAnswer={roundResults.correctAnswer}
        playersWithPoints={roundResults.playersWithPoints}
        roundParticipants={roundResults.roundParticipants}
        artworkUrl={roundResults.artworkUrl}
        canAdvance={roundResults.canAdvance}
        isGameOver={roundResults.isGameOver}
        isJudge={isJudge}
        onNextRound={handleNextRound}
        onEndGame={() => {
          // Handle game end - could navigate to results or clear state
          console.log('[GamePage] Judge ended game from results screen');
        }}
      />
    );
  }

  console.log('[GamePage] Rendering main game interface', { hasJoinedGame, isJudge, currentSongAudioUrl, currentRound, isGameOver, currentQuestion });

  // Debug: Log the conditions for showing different UI components
  console.log('[GamePage] UI conditions:', {
    showRoundResults,
    hasRoundResults: !!roundResults,
    isRoundComplete,
    isGameOver,
    isJudge,
    canAdvance
  });
  
  return (
    <PageTransition transitionKey="game">
      <MobileAppLayout 
        title={isJudge ? "Judge Controls" : "TuneTussle"}
        subtitle={gameCode ? `${gameCode} • Round ${currentRound}/${totalRounds}` : undefined}
        showHeader={false}
        showBackButton={false}
        showBottomNav={false} // Hide during game for more space
      >
        <div className={`${layout.spacing.tight} pb-4`}>
          {/* Progress indicator - slim bar as suggested */}
          <div className={`${layout.padding.page} pt-2`}>
            <div className="w-full bg-gray-200 rounded-full h-1">
              <div 
                className="bg-indigo-600 h-1 rounded-full transition-all duration-300"
                style={{ width: `${(currentRound / totalRounds) * 100}%` }}
              />
            </div>
            <div className={`${utils.spaceBetween} ${typography.body.xs} ${colors.text.muted} mt-1`}>
              <span>Round {currentRound}</span>
              <span>{totalRounds} Total</span>
            </div>
          </div>
          
          {/* Question Display Card - Only for players, not judges */}
          {!isJudge && (
            <div className={layout.padding.page}>
              <FloatingCard className={`border-0 shadow-lg ${components.card.floating}`}>
                <QuestionDisplay 
                  questionText={currentQuestion ? '?' : 'Waiting for question...'}
                  currentRound={currentRound}
                  totalRounds={totalRounds}
                  category={prompt || undefined}
                  isRoundComplete={isRoundComplete}
                  correctAnswerText={correctAnswer}
                  partiallyCorrect={infoMessage?.includes('Partially correct') || false}
                  lastCorrectPlayerName={isRoundComplete ? activePlayerName : null}
                  isJudge={false}
                  isMusicPlaying={!!currentSongAudioUrl && !isRoundComplete}
                  showTurntable={!!currentSongAudioUrl && !isRoundComplete}
                  artworkUrl={null}
                  submissionFeedback={infoFeedback}
                />
              </FloatingCard>
            </div>
          )}

          {/* Judge Controls */}
          {!isGameOver && isJudge && (
            <div className={layout.padding.page}>
              <FloatingCard className={`border-0 shadow-md ${components.card.gradient}`}>
                <JudgeGameControls
                  onNextRound={handleNextRound}
                  onEndRound={handleEndRound}
                  currentRound={currentRound}
                  totalRounds={totalRounds}
                  correctAnswer={correctAnswer || undefined}
                  currentSongAudioUrl={currentSongAudioUrl}
                  isRoundComplete={isRoundComplete}
                  canAdvance={canAdvance}
                  isGameOver={isGameOver}
                  activePlayerName={activePlayerName}
                  currentSongTitle={currentQuestion ? currentQuestion.split(' by ')[0] : undefined}
                  currentArtist={currentQuestion ? currentQuestion.split(' by ')[1] : undefined}
                  artworkUrl={artworkUrl}
                  category={prompt || undefined}
                />
              </FloatingCard>
            </div>
          )}
          
          {/* Player Controls */}
          {!isGameOver && !isJudge && (
            <>
              {/* Buzz Button */}
              <div className={`${utils.centerContent} ${layout.padding.page}`}>
                <BuzzButton 
                  onClick={handleBuzzIn} 
                  disabled={isBuzzButtonDisabled}
                  isActive={!!activePlayerName && !isAnswerInputDisabled}
                />
              </div>
              
              {/* Answer Input */}
              <div className={layout.padding.page}>
                <FloatingCard className={`border-0 shadow-md ${components.card.floating}`}>
                  <AnswerInput 
                    onSubmit={handlePlayerSubmitAnswer} 
                    disabled={isAnswerInputDisabled} 
                    activePlayerName={isRoundComplete ? null : activePlayerName}
                    placeholder={isAnswerInputDisabled && !activePlayerName ? 'Waiting for buzz...' : (activePlayerName && !isRoundComplete ? `Your answer, ${activePlayerName}...` : 'Your answer...')}
                    shake={shakeInput}
                  />
                  <p className={`text-center ${typography.body.xs} ${colors.text.muted} mt-2`}>
                    Enter song title, artist, or both!
                  </p>
                </FloatingCard>
              </div>
            </>
          )}

          {/* Compact Scoreboard */}
          <div className={layout.padding.page}>
            <FloatingCard className={`border-0 shadow-md ${components.card.gradient}`}>
              <Scoreboard 
                scores={scores} 
                compact={true} 
                isJudge={isJudge}
                onAdjustScore={isJudge ? handleAdjustScore : undefined}
                isGameOver={isGameOver}
              />
            </FloatingCard>
          </div>
        </div>
      </MobileAppLayout>
    </PageTransition>
  );
};

export default GamePage; 