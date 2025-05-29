import React, { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { Users, Music4, Crown, Music } from 'lucide-react';
import { useGameLogic } from '../hooks/useGameLogic';
import { JudgeControls } from '../components/JudgeControls'; 
import { ParticipantControls } from '../components/ParticipantControls'; 
import PageTransition from '../components/PageTransition';
import MobileAppLayout from '../components/MobileAppLayout';
import FloatingCard from '../components/FloatingCard';
import LoadingSpinner from '../components/LoadingSpinner';
import { useOptimizedToast } from '../services/toastService';
import { components, layout, typography, colors, utils } from '../styles/designSystem';

export const LobbyPage: React.FC = () => {
  const navigate = useNavigate();
  const gameLogicOutput = useGameLogic();
  const toast = useOptimizedToast();
  const { 
    lobbyState, 
    isJudge, 
    scores,
    gameCode, 
    startGame, 
    playerName,
    isSocketConnected,
    currentQuestion
  } = gameLogicOutput;

  useEffect(() => {
    // Only navigate if all info is present
    if (currentQuestion && gameCode && playerName && (isJudge !== undefined)) {
      // Defensive: ensure localStorage is up to date
      localStorage.setItem('tt_playerName', playerName);
      localStorage.setItem('tt_gameCode', gameCode);
      localStorage.setItem('tt_role', isJudge ? 'judge' : 'participant');
      navigate(`/game/${gameCode}`);
    }
  }, [currentQuestion, gameCode, playerName, isJudge, navigate]);

  const participants = scores;

  const handleGameCodeClick = async () => {
    if (!gameCode) return;
    
    const joinUrl = `${window.location.origin}/game/${gameCode}`;
    
    try {
      await navigator.clipboard.writeText(joinUrl);
      toast.success('Game link copied to clipboard! Share it with friends or just tell them the game code.', {
        duration: 4000,
      });
    } catch (err) {
      // Fallback for older browsers
      console.error('Failed to copy to clipboard:', err);
      toast.error('Could not copy to clipboard. Please copy the game code manually.');
    }
  };

  if (!isSocketConnected) {
    return (
      <MobileAppLayout 
        title="Connecting" 
        showHeader={false}
        showBackButton={false}
        showBottomNav={true}
      >
        <div className={layout.padding.page}>
          <div className={`${layout.container.centered} ${layout.spacing.component}`}>
            <FloatingCard className={`${utils.mobile} ${components.card.floating}`}>
              <div className={`${layout.spacing.component} text-center`}>
                <div className={`${components.icon.container} ${components.icon.md} bg-gradient-to-r ${colors.primary[100]} mx-auto mb-4`}>
                  <Music4 size={24} className="text-indigo-600" />
                </div>
                <h2 className={typography.heading.md}>Connecting...</h2>
                <p className={colors.text.secondary}>Establishing connection to the game server.</p>
                <LoadingSpinner size="sm" />
              </div>
            </FloatingCard>
          </div>
        </div>
      </MobileAppLayout>
    );
  }

  if (lobbyState.isLoadingRole) {
    return (
      <MobileAppLayout 
        title="Loading" 
        showHeader={false}
        showBackButton={false}
        showBottomNav={true}
      >
        <div className={layout.padding.page}>
          <div className={`${layout.container.centered} ${layout.spacing.component}`}>
            <FloatingCard className={`${utils.mobile} ${components.card.floating}`}>
              <div className={`${layout.spacing.component} text-center`}>
                <div className={`${components.icon.container} ${components.icon.md} bg-gradient-to-r ${colors.primary[100]} mx-auto mb-4`}>
                  <Users size={24} className="text-indigo-600" />
                </div>
                <h2 className={typography.heading.md}>Loading...</h2>
                <p className={colors.text.secondary}>Getting lobby information...</p>
                <LoadingSpinner size="sm" />
              </div>
            </FloatingCard>
          </div>
        </div>
      </MobileAppLayout>
    );
  }

  return (
    <PageTransition transitionKey="lobby">
      <MobileAppLayout 
        title="Game Lobby" 
        showHeader={false}
        showBackButton={false}
        showBottomNav={true}
      >
        <div className={layout.padding.page}>
          <div className={`${layout.container.centered} ${layout.spacing.section}`}>
            <div className={`${utils.mobile} ${layout.spacing.section}`}>
              
              {/* Header Card - Updated to fix visual obstruction */}
              <FloatingCard className={`${components.card.floating} text-center`}>
                <div className={layout.spacing.component}>
                  <div className="flex items-center justify-center gap-3 mb-4">
                    <div className={`${components.icon.container} ${components.icon.heroMd} bg-indigo-100 rounded-xl`}>
                      <Music size={24} className="text-indigo-600" />
                    </div>
                    <h1 className={typography.heading.lg}>Game Lobby</h1>
                  </div>
                  
                  {/* Large, prominent game code */}
                  <div className="mt-4 mb-4">
                    <p className={`${typography.body.sm} ${colors.text.muted} mb-2`}>Share this code with players:</p>
                    <div 
                      className={`${components.card.gradient} p-6 rounded-xl border-2 border-indigo-200 cursor-pointer hover:border-indigo-300 transition-colors`}
                      onClick={handleGameCodeClick}
                    >
                      <div className="text-4xl font-bold font-mono text-indigo-700 tracking-wider select-all">
                        {gameCode}
                      </div>
                      <p className={`${typography.body.xs} ${colors.text.muted} mt-2`}>
                        Click to copy join link • Or select code to copy manually
                      </p>
                    </div>
                  </div>
                </div>
              </FloatingCard>

              {/* Controls Card - Now appears right after game code */}
              <FloatingCard className={components.card.floating}>
                <div className={layout.spacing.component}>
                  {isJudge ? (
                    <JudgeControls
                      onStartGame={startGame}
                      isStartingGame={lobbyState.isStartingGame}
                      startGameError={lobbyState.startGameError}
                      isGeneratingSongs={lobbyState.isGeneratingSongs}
                      isGameReady={lobbyState.isGameReady}
                      participants={participants}
                    />
                  ) : (
                    <ParticipantControls />
                  )}
                </div>
              </FloatingCard>

              {/* Participants Card - Now at the bottom */}
              <FloatingCard className={components.card.floating}>
                <div className={layout.spacing.component}>
                  <div className={`${utils.spaceBetween} mb-4`}>
                    <h2 className={typography.heading.sm}>Participants</h2>
                    <span className={`${components.badge.base} ${colors.status.info}`}>
                      {participants.length}
                    </span>
                  </div>
                  
                  {participants.length > 0 ? (
                    <div className={components.list.container}>
                      {participants.map((p) => (
                        <div key={p.id} className={components.list.item}>
                          <div className="flex items-center">
                            <div className="w-8 h-8 bg-gradient-to-r from-indigo-100 to-blue-100 rounded-full flex items-center justify-center mr-3">
                              {p.role === 'judge' ? (
                                <Crown size={14} className="text-indigo-600" />
                              ) : (
                                <Users size={14} className="text-indigo-600" />
                              )}
                            </div>
                            <span className={`${typography.body.base} ${colors.text.primary}`}>
                              {p.name}
                            </span>
                          </div>
                          <div className="flex gap-2">
                            {p.role === 'judge' && (
                              <span className={components.badge.role}>
                                Judge
                              </span>
                            )}
                            {p.name === playerName && (
                              <span className={`${components.badge.role} bg-blue-50 text-blue-700 border-blue-200`}>
                                You
                              </span>
                            )}
                          </div>
                        </div>
                      ))}
                    </div>
                  ) : (
                    <div className={`${components.card.gradient} p-6 text-center`}>
                      <Users size={48} className={`${colors.text.light} mx-auto mb-3`} />
                      <p className={colors.text.muted}>Waiting for participants to join...</p>
                    </div>
                  )}
                </div>
              </FloatingCard>
            </div>
          </div>
        </div>
      </MobileAppLayout>
    </PageTransition>
  );
};

export default LobbyPage; 