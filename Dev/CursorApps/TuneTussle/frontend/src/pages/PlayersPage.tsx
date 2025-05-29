import React from 'react';
import { useNavigate } from 'react-router-dom';
import { Users, Crown, ArrowLeft, UserPlus } from 'lucide-react';
import { useGameLogic } from '../hooks/useGameLogic';
import PageTransition from '../components/PageTransition';
import MobileAppLayout from '../components/MobileAppLayout';
import FloatingCard from '../components/FloatingCard';
import LoadingSpinner from '../components/LoadingSpinner';
import { components, layout, typography, colors, utils } from '../styles/designSystem';

export const PlayersPage: React.FC = () => {
  const navigate = useNavigate();
  const { scores, playerName, isSocketConnected, gameCode } = useGameLogic();

  const participants = scores || [];

  const handleGoBack = () => {
    // Use browser history to go back to the previous page
    navigate(-1);
  };

  const handleInviteFriends = () => {
    if (gameCode) {
      navigate(`/lobby/${gameCode}`);
    }
  };

  if (!isSocketConnected) {
    return (
      <MobileAppLayout 
        title="Players" 
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

  return (
    <PageTransition transitionKey="players">
      <MobileAppLayout 
        title="Players" 
        showHeader={false}
        showBackButton={false}
        showBottomNav={true}
      >
        <div className={layout.padding.page}>
          <div className={`${layout.container.centered} ${layout.spacing.section}`}>
            <div className={`${utils.mobile} ${layout.spacing.section}`}>
              
              {/* Header Card */}
              <FloatingCard className={`${components.card.floating} text-center`}>
                <div className={layout.spacing.component}>
                  <div className="flex items-center justify-center gap-3 mb-4">
                    <div className={`${components.icon.container} ${components.icon.heroMd} bg-indigo-100 rounded-xl`}>
                      <Users size={24} className="text-indigo-600" />
                    </div>
                    <h1 className={typography.heading.lg}>All Players</h1>
                  </div>
                  
                  {gameCode && (
                    <div className="mt-2">
                      <p className={`${typography.body.sm} ${colors.text.muted}`}>Game: {gameCode}</p>
                    </div>
                  )}
                </div>
              </FloatingCard>

              {/* Action Buttons Card */}
              <FloatingCard className={components.card.floating}>
                <div className={layout.spacing.component}>
                  <div className="grid grid-cols-2 gap-3">
                    <button
                      onClick={handleGoBack}
                      className={`${components.button.secondary} flex items-center justify-center gap-2 text-sm`}
                    >
                      <ArrowLeft size={18} />
                      Back to Game
                    </button>
                    <button
                      onClick={handleInviteFriends}
                      className={`${components.button.primary} flex items-center justify-center gap-2 text-sm`}
                    >
                      <UserPlus size={18} />
                      Invite Friends
                    </button>
                  </div>
                </div>
              </FloatingCard>

              {/* Players List Card */}
              <FloatingCard className={components.card.floating}>
                <div className={layout.spacing.component}>
                  <div className={`${utils.spaceBetween} mb-4`}>
                    <h2 className={typography.heading.sm}>All Participants</h2>
                    <span className={`${components.badge.base} ${colors.status.info}`}>
                      {participants.length}
                    </span>
                  </div>
                  
                  {participants.length > 0 ? (
                    <div className={components.list.container}>
                      {participants.map((p) => (
                        <div key={p.id} className={components.list.item}>
                          <div className="flex items-center">
                            <div className="w-10 h-10 bg-gradient-to-r from-indigo-100 to-blue-100 rounded-full flex items-center justify-center mr-4">
                              {p.role === 'judge' ? (
                                <Crown size={16} className="text-indigo-600" />
                              ) : (
                                <Users size={16} className="text-indigo-600" />
                              )}
                            </div>
                            <div className="flex-1">
                              <span className={`${typography.body.lg} ${colors.text.primary} font-medium`}>
                                {p.name}
                              </span>
                              <div className="flex items-center gap-2 mt-1">
                                <span className={`${typography.body.sm} ${colors.text.muted}`}>
                                  Score: {p.score} pts
                                </span>
                              </div>
                            </div>
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
                      <p className={colors.text.muted}>No participants found...</p>
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

export default PlayersPage; 