import axios from 'axios';
import * as cheerio from 'cheerio';

const YOUTUBE_SEARCH_URL_BASE = 'https://www.youtube.com/results?search_query=';

/**
 * Fetches a YouTube video link by scraping search results.
 * Prioritizes finding videoId in script tags, then falls back to DOM selectors.
 * @param title The song title.
 * @param artist The artist's name.
 * @returns A promise that resolves to a YouTube video URL string if found, otherwise null.
 */
export async function fetchYouTubeLinkByScraping(
  title: string,
  artist: string
): Promise<string | null> {
  const searchTerm = `${title} ${artist}`;
  const searchUrl = `${YOUTUBE_SEARCH_URL_BASE}${encodeURIComponent(searchTerm)}`;
  let firstVideoLink: string | null = null;

  try {
    console.log(`Scraping YouTube for: ${searchTerm} at URL: ${searchUrl}`);

    const { data: htmlContent } = await axios.get(searchUrl, {
      headers: {
        'User-Agent':
          'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        'Accept-Language': 'en-US,en;q=0.9',
      },
    });

    const $ = cheerio.load(htmlContent);

    // Priority 1: Attempt to find videoId in script tags (e.g., ytInitialData)
    console.log(`[Scraper] Attempting to find videoId in script tags for "${searchTerm}"...`);
    $('script').each((_i, el) => {
      const scriptContent = $(el).html();
      // Look for common patterns of embedded video data. ytInitialData is a common one.
      if (scriptContent && (scriptContent.includes('ytInitialData') || scriptContent.includes('videoRenderer'))) {
        // Regex to find "videoId":"some_11_char_id". This is fragile.
        const match = scriptContent.match(/"videoId":"([a-zA-Z0-9_-]{11})"/);
        if (match && match[1]) {
          // Validate if it's a plausible video ID (11 chars, specific character set)
          // This is a basic check; YouTube might have other ID formats in edge cases.
          if (/^[a-zA-Z0-9_-]{11}$/.test(match[1])) {
            firstVideoLink = `https://www.youtube.com/watch?v=${match[1]}`;
            console.log(`[Scraper] Found videoId "${match[1]}" in script tag for "${searchTerm}". Link: ${firstVideoLink}`);
            return false; // Stop iterating script tags
          }
        }
      }
    });

    if (firstVideoLink) {
      console.log(`[Scraper] Successfully found link via script tag parsing for "${searchTerm}".`);
      return firstVideoLink;
    }
    console.log(`[Scraper] Could not find videoId in script tags for "${searchTerm}". Falling back to DOM selectors.`);

    // Priority 2: Fallback to DOM element selectors
    // Try a fairly specific selector first that often works for main video results
    console.log(`[Scraper] Attempting DOM selector 'a#video-title' for "${searchTerm}"...`);
    $('a#video-title').each((_i, el) => {
      const href = $(el).attr('href');
      if (href && href.startsWith('/watch?v=')) {
        // Ensure it's not a playlist link disguised or a channel link etc.
        if (!href.includes('&list=') && !href.includes('/channel/') && !href.includes('/user/')) {
            firstVideoLink = `https://www.youtube.com${href}`;
            console.log(`[Scraper] Found link via 'a#video-title' for "${searchTerm}": ${firstVideoLink}`);
            return false; // Stop iterating after finding the first one
        }
      }
    });

    // Fallback selector if the first one didn't find anything.
    if (!firstVideoLink) {
      console.log(`[Scraper] DOM selector 'a#video-title' failed for "${searchTerm}". Trying 'a[href^="/watch?v="]'...`);
      $('a[href^="/watch?v="]').each((_i, el) => {
        const href = $(el).attr('href');
        if (href) {
          // Basic filter to avoid playlist links and other non-video links.
          if (href.includes('&list=') || href.includes('/channel/') || href.includes('/user/') || href.includes('/playlist?')) {
            return true; // continue to next item
          }
          firstVideoLink = `https://www.youtube.com${href}`;
          console.log(`[Scraper] Found link via 'a[href^="/watch?v="]' for "${searchTerm}": ${firstVideoLink}`);
          return false; // Stop iterating
        }
      });
    }
    
    if (firstVideoLink) {
      console.log(`[Scraper] Successfully found link via DOM selectors for "${searchTerm}": ${firstVideoLink}`);
    } else {
      console.warn(`[Scraper] Could not find YouTube link for "${searchTerm}" using any method.`);
    }

    return firstVideoLink;
  } catch (error) {
    console.error(`[Scraper] Error scraping YouTube for "${searchTerm}":`, error instanceof Error ? error.message : error);
    return null;
  }
} 