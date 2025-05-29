import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Gamepad2 } from 'lucide-react';
import apiService from '../api/apiService';
import PageTransition from '../components/PageTransition';
import MobileAppLayout from '../components/MobileAppLayout';
import FloatingCard from '../components/FloatingCard';
import LoadingSpinner from '../components/LoadingSpinner';
import { components, layout, typography, colors, utils } from '../styles/designSystem';

export const CreateGamePage: React.FC = () => {
  const [judgeName, setJudgeName] = useState('');
  const [prompt, setPrompt] = useState('');
  const [numRounds, setNumRounds] = useState<number | string>(10);
  const [error, setError] = useState<string | null>(null);
  const [isCreating, setIsCreating] = useState(false);
  const navigate = useNavigate();

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    setError(null);

    if (!judgeName.trim()) {
      setError('Judge name cannot be empty.');
      return;
    }

    if (!prompt.trim()) {
      setError('Prompt cannot be empty.');
      return;
    }

    const rounds = Number(numRounds);
    if (isNaN(rounds) || rounds <= 0 || !Number.isInteger(rounds)) {
      setError('Number of rounds must be a positive integer.');
      return;
    }

    setIsCreating(true);
    try {
      const response = await apiService.createGame({
        judgeName,
        prompt,
        numberOfRounds: rounds
      });

      navigate(`/lobby/${response.code}`, { state: { playerName: judgeName, isJudge: true } });
    } catch (err: unknown) {
      // Type guard for axios-like error
      const isAxiosError = (error: unknown): error is { response?: { data?: { error?: string } } } => {
        return typeof error === 'object' && error !== null && 'response' in error;
      };

      const errorMessage = isAxiosError(err)
        ? err.response?.data?.error || 'Failed to create game. Please try again.'
        : 'Failed to create game. Please try again.';

      setError(errorMessage);
      console.error(err);
    } finally {
      setIsCreating(false);
    }
  };

  return (
    <PageTransition transitionKey="create-game">
      <MobileAppLayout
        title="Create Game"
        showHeader={false}
        showBackButton={false}
        showBottomNav={true}
      >
        <div className={layout.padding.page}>
          <div className={layout.container.centered}>
            <FloatingCard className={`${utils.mobile} ${components.card.floating}`}>
              <div className={layout.spacing.section}>
                <div className="text-center">
                  <div className="flex items-center justify-center gap-3 mb-3">
                    <div className={`${components.icon.container} ${components.icon.heroMd} bg-indigo-100 rounded-xl`}>
                      <Gamepad2 size={24} className="text-indigo-600" />
                    </div>
                    <h2 className={`${typography.heading.md} ${colors.text.primary}`}>Create New Game</h2>
                  </div>
                  <p className={`${colors.text.secondary} ${typography.body.sm}`}>Set up your music trivia game</p>
                </div>

                <form onSubmit={handleSubmit} className={layout.spacing.component}>
                  {/* Judge Name Field */}
                  <div>
                    <label htmlFor="judgeName" className={`block ${typography.body.sm} ${typography.weight.semibold} ${colors.text.primary} mb-2`}>
                      Judge Name
                    </label>
                    <input
                      type="text"
                      id="judgeName"
                      value={judgeName}
                      onChange={(e) => setJudgeName(e.target.value)}
                      placeholder="Your name as the judge"
                      className={components.input.base}
                      disabled={isCreating}
                    />
                  </div>

                  {/* Game Prompt Field */}
                  <div>
                    <label htmlFor="prompt" className={`block ${typography.body.sm} ${typography.weight.semibold} ${colors.text.primary} mb-2`}>
                      Game Prompt
                    </label>
                    <input
                      type="text"
                      id="prompt"
                      value={prompt}
                      onChange={(e) => setPrompt(e.target.value)}
                      placeholder="E.g., 90s Hip Hop, Movie Soundtracks"
                      className={components.input.base}
                      disabled={isCreating}
                    />
                    <p className={`${typography.body.xs} ${colors.text.muted} mt-1`}>Describe the theme or style of music to focus on</p>
                  </div>

                  {/* Number of Rounds Field */}
                  <div>
                    <label htmlFor="numRounds" className={`block ${typography.body.sm} ${typography.weight.semibold} ${colors.text.primary} mb-2`}>
                      Number of Rounds
                    </label>
                    <input
                      type="number"
                      id="numRounds"
                      value={numRounds}
                      onChange={(e) => setNumRounds(e.target.valueAsNumber || e.target.value)}
                      min="1"
                      max="20"
                      className={components.input.base}
                      disabled={isCreating}
                    />
                    <p className={`${typography.body.xs} ${colors.text.muted} mt-1`}>How many songs to play (1-20)</p>
                  </div>

                  {/* Error Message */}
                  {error && (
                    <div className={`${colors.status.error} border rounded-lg p-3`}>
                      <p className={typography.body.sm} data-testid="create-game-error">
                        {error}
                      </p>
                    </div>
                  )}

                  {/* Submit Button */}
                  <div className="pt-2">
                    {isCreating ? (
                      <div className={components.loading.spinner}>
                        <LoadingSpinner size="sm" message="Creating game..." />
                      </div>
                    ) : (
                      <button
                        type="submit"
                        disabled={isCreating}
                        className={`${components.button.primary} ${components.button.disabled}`}
                      >
                        Create Game
                      </button>
                    )}
                  </div>
                </form>

                {/* Helpful tip */}
                <div className={`${colors.status.info} border rounded-lg p-3`}>
                  <p className={typography.body.xs}>
                    💡 <strong>Tip:</strong> Players will join using a game code once you create the game
                  </p>
                </div>
              </div>
            </FloatingCard>
          </div>
        </div>
      </MobileAppLayout>
    </PageTransition>
  );
};

export default CreateGamePage;
