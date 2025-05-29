import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { LogIn, Plus, Gamepad2 } from 'lucide-react';
import FloatingCard from '../components/FloatingCard';
import MobileAppLayout from '../components/MobileAppLayout';
import { useSocket } from '../contexts/SocketContext';
import PageTransition from '../components/PageTransition';
import apiService from '../api/apiService';
import { components, layout, typography, colors, utils } from '../styles/designSystem';

const HomePage: React.FC = () => {
  const navigate = useNavigate();
  const { joinRoom } = useSocket();
  const [gameCode, setGameCode] = useState('');
  const [playerName, setPlayerName] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(false);

  const handleCreateGame = () => {
    navigate('/create-game');
  };

  const handleJoinGame = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!gameCode.trim() || !playerName.trim()) {
      setError('Game Code and Player Name are required.');
      return;
    }
    setIsLoading(true);
    setError(null);

    try {
      await apiService.joinGame(gameCode, { playerName });

      console.log(`Successfully joined game ${gameCode} via API`);

      joinRoom(gameCode, playerName);

      navigate(`/lobby/${gameCode}`, { state: { playerName, isJudge: false } });

    } catch (err: unknown) {
      console.error('Join game error:', err);

      // Type guard for axios-like error
      const isAxiosError = (error: unknown): error is { response?: { data?: { error?: string } } } => {
        return typeof error === 'object' && error !== null && 'response' in error;
      };

      const errorMessage = isAxiosError(err)
        ? err.response?.data?.error || 'An unknown error occurred.'
        : 'An unknown error occurred.';

      setError(errorMessage);
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <PageTransition transitionKey="home">
      <MobileAppLayout
        title="TuneTussle"
        showHeader={false}
        showBackButton={false}
        showBottomNav={true}
      >
        {/* Hero Section with Gradient Background */}
        <div className={`${utils.bgGradientHero} ${colors.hero.primary} ${layout.padding.hero}`}>
          <div className={components.hero.container}>
            <div className={`${components.icon.hero} ${components.icon.heroLg} ${components.hero.iconBg} mb-4`}>
              <Gamepad2 size={32} className={colors.text.white} />
            </div>
            <h1 className={`${typography.body['2xl']} ${typography.weight.bold} mb-2 ${colors.text.white}`}>Ready to Play?</h1>
            <p className={`${typography.body.sm} ${colors.text.hero}`}>Test your music knowledge with friends</p>
          </div>
        </div>

        <div className={`${layout.padding.page} ${layout.spacing.section} -mt-4`}>
          {/* Join Game Card - Primary Action */}
          <FloatingCard className="shadow-xl border-0">
            <form onSubmit={handleJoinGame} className={layout.spacing.component}>
              <div className={components.hero.section}>
                <div className="flex items-center justify-center gap-3 mb-3">
                  <div className={`${components.icon.container} ${components.icon.heroMd} bg-indigo-500 rounded-xl`}>
                    <LogIn size={24} className={colors.text.white} />
                  </div>
                  <h2 className={`${typography.heading.md} ${colors.text.primary}`}>Join a Game</h2>
                </div>
                <p className={`${colors.text.secondary} ${typography.body.sm}`}>Enter your game code and name</p>
              </div>

              <div className={layout.spacing.component}>
                <div>
                  <label htmlFor="gameCode" className={`block ${typography.body.sm} ${typography.weight.semibold} ${colors.text.primary} mb-2`}>
                    Game Code
                  </label>
                  <input
                    type="text"
                    id="gameCode"
                    value={gameCode}
                    onChange={(e) => setGameCode(e.target.value.toUpperCase())}
                    className={components.input.heroCode}
                    placeholder="ABC123"
                    maxLength={6}
                    required={process.env.NODE_ENV !== 'test'}
                    disabled={isLoading}
                  />
                  {error && gameCode && (
                    <p className={`${colors.status.error.split(' ')[0]} ${typography.body.sm} mt-2 ${typography.weight.medium}`}>{error}</p>
                  )}
                </div>

                <div>
                  <label htmlFor="playerName" className={`block ${typography.body.sm} ${typography.weight.semibold} ${colors.text.primary} mb-2`}>
                    Your Name
                  </label>
                  <input
                    type="text"
                    id="playerName"
                    value={playerName}
                    onChange={(e) => setPlayerName(e.target.value)}
                    className={components.input.hero}
                    placeholder="Your name"
                    required={process.env.NODE_ENV !== 'test'}
                    disabled={isLoading}
                  />
                </div>
              </div>

              {/* Primary Button - Full Width, High Contrast */}
              <button
                type="submit"
                disabled={isLoading}
                className={`${components.button.hero} ${components.button.disabled}`}
              >
                {isLoading ? (
                  <div className={components.loading.hero}>
                    <div className={components.loading.heroSpinner}></div>
                    Joining...
                  </div>
                ) : (
                  'Join Game'
                )}
              </button>

              {/* General error message */}
              {error && !gameCode && (
                <p
                  className={`${colors.status.error.split(' ')[0]} ${typography.body.sm} text-center ${typography.weight.medium}`}
                  data-testid="join-error-message"
                >
                  {error}
                </p>
              )}
            </form>
          </FloatingCard>

          {/* Secondary Action Card - Create Game */}
          <FloatingCard className={components.hero.cardBorder}>
            <div className="text-center py-4">
              <div className="flex items-center justify-center gap-3 mb-3">
                <div className={`${components.icon.hero} ${components.icon.heroSm} ${components.hero.cardIcon} rounded-full`}>
                  <Plus size={20} className="text-green-600" />
                </div>
                <h3 className={`${typography.body.lg} ${typography.weight.semibold} ${colors.text.primary}`}>Host a New Game</h3>
              </div>
              <p className={`${colors.text.secondary} ${typography.body.sm} mb-4`}>Create your own game and invite friends</p>

              <button
                type="button"
                onClick={handleCreateGame}
                className={`text-green-600 hover:text-green-700 ${typography.weight.semibold} ${typography.body.sm}
                  hover:underline ${utils.transition}`}
              >
                Start Creating →
              </button>
            </div>
          </FloatingCard>
        </div>
      </MobileAppLayout>
    </PageTransition>
  );
};

export default HomePage;
