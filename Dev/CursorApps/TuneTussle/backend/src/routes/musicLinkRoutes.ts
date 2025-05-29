import express from 'express';
import { fetchTrackLink, MusicProvider } from '../services/musicProviderService';
import env from '../utils/envConfig';
import { getSpotifyArtworkUrl, getSpotifyAccessToken } from '../services/spotifyService';
import { fetchDeezerTrackWithArtwork } from '../services/deezerService';

const router = express.Router();

router.get('/music-link', async (req, res): Promise<void> => {
  const { title, artist, provider = env.MUSIC_PROVIDER } = req.query;
  const apiKey = env.YOUTUBE_API_KEY;

  // Validate required parameters
  if (!title || !artist) {
    res.status(400).json({ error: 'Missing title or artist' });
    return;
  }

  // Validate provider type
  const validProviders: MusicProvider[] = ['youtube', 'spotify', 'deezer'];
  if (!validProviders.includes(provider as MusicProvider)) {
    res.status(400).json({ error: `Invalid provider. Must be one of: ${validProviders.join(', ')}` });
    return;
  }

  // Check for API method requiring API key
  if (process.env.YOUTUBE_FETCH_METHOD === 'API' && !apiKey) {
    res.status(503).json({ error: 'API key required for API method' });
    return;
  }

  try {
    const link = await fetchTrackLink(
      String(title),
      String(artist),
      provider as MusicProvider,
      apiKey
    );
    res.json({ link });
  } catch (err: any) {
    console.error('[API] Error fetching music link:', err.message);
    
    // Handle specific error types with appropriate status codes and messages
    if (err.message.includes('API key required') || err.message.includes('credentials required')) {
      res.status(503).json({ error: `API key required for API method` });
    } else if (err.message.includes('not supported')) {
      res.status(500).json({ error: err.message });
    } else if (err.message.includes('Provider error')) {
      res.status(500).json({ error: err.message });
    } else {
      res.status(500).json({ error: 'Internal server error while fetching music link' });
    }
  }
});

router.get('/music/artwork', async (req, res): Promise<void> => {
  const { title, artist } = req.query;
  console.log('[API] /music/artwork called with:', { title, artist });
  
  if (!title || !artist) {
    res.status(400).json({ error: 'Missing title or artist' });
    return;
  }

  try {
    let artworkUrl: string | undefined;

    // Try Deezer first if it's the primary provider
    if (env.MUSIC_PROVIDER === 'deezer') {
      console.log('[API] /music/artwork trying Deezer first...');
      try {
        const deezerResult = await fetchDeezerTrackWithArtwork(String(title), String(artist));
        if (deezerResult?.albumArtwork) {
          artworkUrl = deezerResult.albumArtwork;
          console.log('[API] /music/artwork found Deezer artwork:', { artworkUrl });
        }
      } catch (deezerError: any) {
        console.warn('[API] /music/artwork Deezer failed:', deezerError.message);
      }
    }

    // Fallback to Spotify if Deezer didn't work or isn't primary
    if (!artworkUrl && env.SPOTIFY_CLIENT_ID && env.SPOTIFY_CLIENT_SECRET) {
      console.log('[API] /music/artwork trying Spotify fallback...');
      try {
        const token = await getSpotifyAccessToken(env.SPOTIFY_CLIENT_ID, env.SPOTIFY_CLIENT_SECRET);
        const spotifyArtwork = await getSpotifyArtworkUrl(String(title), String(artist), token);
        artworkUrl = spotifyArtwork || undefined;
        console.log('[API] /music/artwork found Spotify artwork:', { artworkUrl });
      } catch (spotifyError: any) {
        console.warn('[API] /music/artwork Spotify failed:', spotifyError.message);
      }
    }

    // Try all providers in fallback order if primary provider isn't configured or failed
    if (!artworkUrl) {
      console.log('[API] /music/artwork trying all providers...');
      
      // Try providers in order: Deezer, Spotify (YouTube doesn't provide album artwork)
      const artworkProviders = [
        { name: 'deezer', available: true },
        { name: 'spotify', available: !!(env.SPOTIFY_CLIENT_ID && env.SPOTIFY_CLIENT_SECRET) }
      ];

      for (const provider of artworkProviders) {
        if (!provider.available || (provider.name === env.MUSIC_PROVIDER)) continue; // Skip if unavailable or already tried
        
        try {
          if (provider.name === 'deezer') {
            const deezerResult = await fetchDeezerTrackWithArtwork(String(title), String(artist));
            if (deezerResult?.albumArtwork) {
              artworkUrl = deezerResult.albumArtwork;
              console.log(`[API] /music/artwork found ${provider.name} artwork:`, { artworkUrl });
              break;
            }
          } else if (provider.name === 'spotify') {
            const token = await getSpotifyAccessToken(env.SPOTIFY_CLIENT_ID!, env.SPOTIFY_CLIENT_SECRET!);
            const spotifyArtwork = await getSpotifyArtworkUrl(String(title), String(artist), token);
            if (spotifyArtwork) {
              artworkUrl = spotifyArtwork;
              console.log(`[API] /music/artwork found ${provider.name} artwork:`, { artworkUrl });
              break;
            }
          }
        } catch (providerError: any) {
          console.warn(`[API] /music/artwork ${provider.name} failed:`, providerError.message);
        }
      }
    }

    console.log('[API] /music/artwork result:', { artworkUrl });
    res.json({ artworkUrl });
  } catch (err) {
    console.error('[API] /music/artwork error:', err);
    res.status(500).json({ error: 'Failed to fetch artwork' });
  }
});

export default router; 