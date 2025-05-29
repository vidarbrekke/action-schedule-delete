import { BrowserRouter as Router, Routes, Route } from 'react-router-dom';
import HomePage from './pages/HomePage';
import LobbyPage from './pages/LobbyPage'; // Assuming LobbyPage will be created
// import CreateGamePage from './pages/CreateGamePage'; // Placeholder
import CreateGamePage from './pages/CreateGamePage';
import GamePage from './pages/GamePage'; // Placeholder
import ResultsPage from './pages/ResultsPage'; // Import the new ResultsPage
import PlayersPage from './pages/PlayersPage'; // Import the new PlayersPage
import ErrorBoundary from './components/ErrorBoundary';

function App() {
  return (
    <Router>
      <Routes>
        <Route path="/" element={<HomePage />} />
        <Route path="/create-game" element={<CreateGamePage />} />
        <Route path="/lobby/:gameCode" element={<LobbyPage />} />
        <Route path="/game/:gameCode" element={<ErrorBoundary><GamePage /></ErrorBoundary>} />
        <Route path="/results/:gameCode" element={<ResultsPage />} /> {/* Add route for ResultsPage */}
        <Route path="/players" element={<PlayersPage />} /> {/* Add route for PlayersPage */}
      </Routes>
    </Router>
  );
}

export default App;