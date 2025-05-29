import { useState } from 'react';
import { useMusicProvider } from '../hooks/useMusicProvider';

export function TrackLinkButton({ title, artist }: { title: string; artist: string }) {
  const { fetchTrackLink } = useMusicProvider('youtube'); // Can be configured to use any provider
  const [link, setLink] = useState<string | undefined>();
  const [error, setError] = useState<string | undefined>();

  const handleFetch = async () => {
    setError(undefined);
    try {
      const result = await fetchTrackLink(title, artist);
      setLink(result);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'An error occurred');
    }
  };

  return (
    <div>
      <button onClick={handleFetch}>Get Track Link</button>
      {link && <a href={link} target="_blank" rel="noopener noreferrer">Listen</a>}
      {error && <span style={{ color: 'red' }}>{error}</span>}
    </div>
  );
} 