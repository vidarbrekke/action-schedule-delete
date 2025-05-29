/**
 * Service for preloading artwork URLs to improve user experience
 * Implements intelligent caching and preloading strategies
 */

interface ArtworkCache {
  [key: string]: string; // key = "title|artist", value = artwork URL
}

interface SongInfo {
  title: string;
  artist: string;
}

/**
 * Artwork Preload Service
 * 
 * This service optimizes artwork loading in TuneTussle by:
 * 1. Caching artwork URLs to avoid repeated Spotify API calls
 * 2. Preloading artwork for upcoming rounds during current rounds
 * 3. Providing synchronous access to cached artwork
 */
class ArtworkPreloadService {
  private cache: ArtworkCache = {};

  /**
   * Generate cache key for a song
   */
  private getCacheKey(title: string, artist: string): string {
    return `${title.toLowerCase()}|${artist.toLowerCase()}`;
  }

  /**
   * Preload artwork for a specific song
   */
  async preloadArtwork(title: string, artist: string): Promise<void> {
    const cacheKey = this.getCacheKey(title, artist);
    
    // Skip if already cached
    if (this.cache[cacheKey]) {
      console.log('[ArtworkPreload] Already cached:', { title, artist });
      return;
    }

    console.log('[ArtworkPreload] Preloading artwork for:', { title, artist });

    try {
      // Dynamic import to avoid circular dependencies and enable proper testing
      const { default: apiService } = await import('../api/apiService');
      const artworkUrl = await apiService.getSpotifyArtwork(title, artist);
      
      if (artworkUrl) {
        this.cache[cacheKey] = artworkUrl;
        console.log('[ArtworkPreload] Successfully cached artwork:', { title, artist, url: artworkUrl });

        // Trigger browser to download the image
        const img = new Image();
        img.src = artworkUrl;
      }
    } catch (error) {
      console.warn('[ArtworkPreload] Failed to preload artwork:', { title, artist, error });
    }
  }

  /**
   * Preload artwork for multiple songs (game ready optimization)
   * Useful when songs are first generated to preload artwork for upcoming rounds
   */
  async preloadMultipleSongs(songs: SongInfo[], priority: 'first' | 'all' = 'first'): Promise<void> {
    if (!songs.length) return;

    console.log('[ArtworkPreload] Preloading artwork for multiple songs:', { count: songs.length, priority });

    if (priority === 'first') {
      // Only preload the first song with high priority
      const firstSong = songs[0];
      try {
        await this.preloadArtwork(firstSong.title, firstSong.artist);
        console.log('[ArtworkPreload] First song artwork preloaded:', firstSong);
      } catch (error) {
        console.warn('[ArtworkPreload] Failed to preload first song artwork:', error);
      }

      // Preload remaining songs in the background (non-blocking)
      if (songs.length > 1) {
        const remainingSongs = songs.slice(1);
        Promise.allSettled(
          remainingSongs.map(song => this.preloadArtwork(song.title, song.artist))
        ).then(results => {
          const successful = results.filter(r => r.status === 'fulfilled').length;
          console.log('[ArtworkPreload] Background preloading completed:', { 
            successful, 
            total: remainingSongs.length 
          });
        });
      }
    } else {
      // Preload all songs with equal priority
      await Promise.allSettled(
        songs.map(song => this.preloadArtwork(song.title, song.artist))
      );
    }
  }

  /**
   * Get cached artwork URL synchronously
   */
  getArtworkSync(title: string, artist: string): string | null {
    const cacheKey = this.getCacheKey(title, artist);
    return this.cache[cacheKey] || null;
  }

  /**
   * Preload artwork for current and next round
   * Returns URLs for immediate use and background preloading
   */
  async preloadForRound(currentSong: SongInfo, nextSong?: SongInfo): Promise<{
    currentArtwork: string | null;
    nextArtwork?: string | null;
  }> {
    console.log('[ArtworkPreload] Preloading for round:', { currentSong, nextSong });

    // Start preloading current artwork immediately
    const currentArtworkPromise = this.preloadArtwork(currentSong.title, currentSong.artist);

    // If we have next song info, start preloading that too
    if (nextSong) {
      console.log('[ArtworkPreload] Also preloading next round artwork:', nextSong);
      this.preloadArtwork(nextSong.title, nextSong.artist).catch(error => {
        console.warn('[ArtworkPreload] Failed to preload next round artwork:', error);
      });
    }

    // Wait for current song artwork
    await currentArtworkPromise;

    return {
      currentArtwork: this.cache[this.getCacheKey(currentSong.title, currentSong.artist)],
      nextArtwork: nextSong ? this.cache[this.getCacheKey(nextSong.title, nextSong.artist)] || null : undefined
    };
  }

  /**
   * Clear all cached artwork
   */
  clearCache(): void {
    this.cache = {};
    console.log('[ArtworkPreload] Cache cleared');
  }

  /**
   * Get cache statistics
   */
  getCacheStats(): { size: number; keys: string[] } {
    return {
      size: Object.keys(this.cache).length,
      keys: Object.keys(this.cache)
    };
  }
}

// Export singleton instance
export const artworkPreloadService = new ArtworkPreloadService();
export default artworkPreloadService; 