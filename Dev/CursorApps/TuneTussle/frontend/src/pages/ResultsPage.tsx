import React, { useEffect, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import type { PlayerScore } from '../components/Scoreboard'; // Already correct
import PageTransition from '../components/PageTransition';
import Confetti from 'react-confetti';
import { useGameAnimations } from '../hooks/useGameAnimations';
import { components, layout, typography, colors, effects, utils } from '../styles/designSystem';

interface ResultsLocationState {
  scores: PlayerScore[];
  gameCode?: string;
}

const ResultsPage: React.FC = () => {
  const navigate = useNavigate();
  const location = useLocation();
  const { showConfetti, triggerConfetti } = useGameAnimations();
  const [finalScores, setFinalScores] = useState<PlayerScore[]>([]);
  const [gameCode, setGameCode] = useState<string | undefined>(undefined);
  
  // Initialize scores and gameCode from location state or localStorage
  useEffect(() => {
    // Try to get values from location state first
    const locState = location.state as ResultsLocationState | null;
    let scoreData = locState?.scores || [];
    let code = locState?.gameCode;
    
    // If no scores in location state, try localStorage
    if (scoreData.length === 0) {
      try {
        const storedScores = localStorage.getItem('tt_finalScores');
        if (storedScores) {
          scoreData = JSON.parse(storedScores);
          // Filter out judge from scores
          scoreData = scoreData.filter(player => player.role !== 'judge');
        }
        
        // Also check for gameCode in localStorage
        const storedGameCode = localStorage.getItem('tt_gameCode');
        if (storedGameCode && !code) {
          code = storedGameCode;
        }
      } catch (e) {
        console.error('Error retrieving scores from localStorage', e);
      }
    }
    
    setFinalScores(scoreData);
    setGameCode(code);
  }, [location.state]);

  const getWinners = (playerScores: PlayerScore[]): PlayerScore[] => {
    if (!playerScores || playerScores.length === 0) return [];
    const filteredScores = playerScores.filter(player => player.role !== 'judge');
    const numericScores = filteredScores.map(s => ({ ...s, score: Number(s.score) }));
    const maxScore = Math.max(...numericScores.map(s => s.score));
    // Handle case where all scores are 0 or no one scored (no winner)
    if (maxScore === 0 && numericScores.every(s => s.score === 0)) {
      return [];
    }
    return numericScores.filter(s => s.score === maxScore);
  };

  const winners = getWinners(finalScores);
  const sortedScores = [...finalScores]
    .filter(player => player.role !== 'judge')
    .sort((a, b) => Number(b.score) - Number(a.score));

  useEffect(() => {
    if (winners.length > 0) triggerConfetti();
    // eslint-disable-next-line
  }, [winners]);

  // Clean up localStorage when leaving results page
  useEffect(() => {
    return () => {
      localStorage.removeItem('tt_finalScores');
      localStorage.removeItem('tt_gameEnded');
    };
  }, []);

  return (
    <PageTransition transitionKey="results">
      {showConfetti && <Confetti width={window.innerWidth} height={window.innerHeight} numberOfPieces={250} recycle={false} gravity={0.25} />}
      <div className={`${layout.container.results} ${utils.bgGradientHero} ${colors.hero.results} ${layout.spacing.hero}`}>
        <header className={components.results.header}>
          <h1 className={`${typography.heading.hero} ${colors.text.white}`}>Game Over!</h1>
          {gameCode && <p className={`${typography.body.lg} ${colors.text.hero}`}>Game Code: {gameCode}</p>}
        </header>

        {winners.length > 0 && (
          <section className={`${components.card.glass} ${components.results.winners}`}>
            <h2 className={`${typography.body['3xl']} ${typography.weight.bold} ${colors.winner.text} mb-4`}>
              {winners.length > 1 ? '🏆 Winners! 🏆' : '🎉 Winner! 🎉'}
            </h2>
            {winners.map((winner) => (
              <p key={winner.id} className={`${typography.body['4xl']} ${typography.weight.bold} ${colors.text.white} ${effects.animation.pulse}`}>
                {winner.name}
              </p>
            ))}
            <p className={`${typography.body.xl} mt-2 ${colors.winner.accent}`}>Score: {winners[0].score}</p>
          </section>
        )}

        {winners.length === 0 && sortedScores.length > 0 && (
          <section className={`${components.card.glassMuted} ${components.results.winners}`}>
            <p className={`${typography.body['2xl']} ${typography.weight.semibold} ${colors.text.white}`}>It's a draw, or no one scored any points!</p>
            <p className={`${typography.body.lg} ${colors.text.hero}`}>Well played everyone!</p>
          </section>
        )}
        
        {sortedScores.length === 0 && (
           <section className={`${components.card.glassMuted} ${components.results.winners}`}>
            <p className={`${typography.body['2xl']} ${typography.weight.semibold} ${colors.text.white}`}>The game has ended.</p>
            <p className={`${typography.body.lg} ${colors.text.hero}`}>No scores to display.</p>
          </section>
        )}

        <section className={`${components.card.glassMuted} ${components.results.scores}`}>
          <h3 className={`${typography.body['2xl']} ${typography.weight.semibold} text-center mb-4 ${colors.text.white}`}>Final Scores</h3>
          {sortedScores.length > 0 ? (
            <ul className={components.list.results}>
              {sortedScores.map((player, index) => (
                <li 
                  key={player.id} 
                  className={`${components.list.resultsItem} ${
                    winners.some(w => w.id === player.id) 
                      ? components.list.resultsWinner
                      : components.list.resultsNormal
                  }`}
                >
                  <span>{index + 1}. {player.name}</span>
                  <span>{player.score} pts</span>
                </li>
              ))}
            </ul>
          ) : (
            <p className={`text-center ${colors.text.hero}`}>No scores recorded.</p>
          )}
        </section>

        <footer className={components.results.footer}>
          <button
            onClick={() => navigate('/')}
            className={`${components.button.results} ${components.button.resultsGreen}`}
          >
            Play Another Game
          </button>
          <button
            onClick={() => navigate('/create-game')}
            className={`${components.button.results} ${components.button.resultsBlue}`}
          >
            Create New Game
          </button>
        </footer>
      </div>
    </PageTransition>
  );
};

export default ResultsPage; 