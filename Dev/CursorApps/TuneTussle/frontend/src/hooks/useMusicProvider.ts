import { useCallback } from 'react';

type MusicProvider = 'youtube' | 'spotify' | 'deezer';

export function useMusicProvider(provider: MusicProvider = 'youtube') {
  const fetchTrackLink = useCallback(async (title: string, artist: string) => {
    // This would call your backend API, passing the provider
    const params = new URLSearchParams({ title, artist, provider });
    const response = await fetch(`/api/music-link?${params.toString()}`);
    if (!response.ok) throw new Error('Failed to fetch track link');
    const { link } = await response.json();
    return link as string | undefined;
  }, [provider]);

  return { fetchTrackLink };
}
// Usage in a component:
// const { fetchTrackLink } = useMusicProvider('youtube');
// const link = await fetchTrackLink(song.title, song.artist); 