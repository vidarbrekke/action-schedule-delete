// llmService.ts - Service for interacting with LLM APIs

import { Song } from '../models/types';
// import { enrichSongsWithYouTubeLinks } from './youtubeService'; // Old direct call
import { toYouTubeEmbedUrl } from './youtubeService'; // For converting to embed URL
import { fetchTrackLink } from './musicProviderService'; // New centralized track link fetcher
import env from '../utils/envConfig';
import { OpenAI } from 'openai';

interface OpenAIError extends Error {
  status?: number;
  code?: string;
}

interface OpenAIResponse {
  choices: Array<{
    message: {
      content: string | null;
    };
  }>;
}

interface ChatMessage {
  role: 'system' | 'user' | 'assistant';
  content: string;
}

let openai: OpenAI | null = null;

function getOpenAI(): OpenAI {
  if (!openai) {
    const apiKey = process.env.OPENAI_API_KEY;
    if (!apiKey) {
      throw new Error('OPENAI_API_KEY is not configured');
    }
    openai = new OpenAI({ apiKey });
  }
  return openai;
}

// For testing - allows tests to inject a mock implementation
let _mockFetchSongsFromLLM: ((prompt: string, numberOfRounds: number) => Promise<Array<Song>>) | null = null;
let _mockGenerateClue: ((song: string, artist: string, difficulty: string) => Promise<string>) | null = null;

/**
 * Sets a mock implementation for fetchSongsFromLLM (for testing)
 * @param mockFn Mock function to use instead of the real implementation
 */
export function __setMockFetchSongsFromLLM(mockFn: ((prompt: string, numberOfRounds: number) => Promise<Array<Song>>) | null): void {
  _mockFetchSongsFromLLM = mockFn;
}

/**
 * Sets a mock implementation for generateClue (for testing)
 * @param mockFn Mock function to use instead of the real implementation
 */
export function __setMockGenerateClue(mockFn: ((song: string, artist: string, difficulty: string) => Promise<string>) | null): void {
  _mockGenerateClue = mockFn;
}

/**
 * Enriches a single song with track URL from the configured music provider
 */
async function enrichSongWithTrackUrl(song: Song, musicProvider: string, youtubeApiKey?: string): Promise<Song> {
  try {
    console.log(`[LLM Service] Fetching ${musicProvider} track for: "${song.title}" by "${song.artist}"`);
    
    const trackUrl = await fetchTrackLink(song.title, song.artist, musicProvider as any, youtubeApiKey);
    
    // Handle URL conversion based on the actual URL type (not just the primary provider)
    let finalUrl: string | undefined = undefined;
    if (trackUrl) {
      // Check if it's a YouTube URL (could be from fallback even if primary provider is Spotify)
      if (trackUrl.includes('youtube.com') || trackUrl.includes('youtu.be')) {
        const embedUrl = toYouTubeEmbedUrl(trackUrl);
        finalUrl = embedUrl || undefined;
      } else if (trackUrl.includes('scdn.co') || trackUrl.includes('spotify.com')) {
        // Spotify preview URLs are ready to use
        finalUrl = trackUrl;
      } else if (trackUrl.includes('deezer.com') || trackUrl.includes('dzcdn.net')) {
        // Deezer preview URLs are ready to use (30-second samples)
        finalUrl = trackUrl;
      } else {
        // Default: use URL as-is for other providers
        finalUrl = trackUrl;
      }
    }
    
    const enrichedSong = { ...song, trackUrl: finalUrl };
    console.log(`[LLM Service] ✓ Enriched: "${song.title}" - URL: ${finalUrl ? '✓' : '✗'}`);
    return enrichedSong;
  } catch (err) {
    console.error(`[LLM Service] ✗ Error enriching "${song.title}" with ${musicProvider}:`, err);
    return { ...song, trackUrl: undefined }; // Proceed without link on error
  }
}

/**
 * Fetches and validates a song list from an LLM
 * @param prompt User's prompt for song theme
 * @param numberOfRounds Number of songs to generate
 * @param llmApiKey API key for LLM service
 * @param modelId Model ID to use
 * @param llmApiBaseUrl Base URL for LLM API (optional)
 * @param youtubeApiKey YouTube API key for enriching songs (optional, used by musicProviderService if method is API)
 * @returns Promise resolving to an array of songs
 */
export async function fetchAndValidateSongList(
  prompt: string,
  numberOfRounds: number,
  llmApiKey: string,
  modelId: string,
  llmApiBaseUrl?: string | null,
  youtubeApiKey?: string | null // This key is passed to musicProviderService
): Promise<Array<Song>> {
  console.log('[LLM] fetchAndValidateSongList called with:', { prompt, numberOfRounds, modelId, llmApiBaseUrl });
  
  const parsedSongListFromLLM = await fetchSongsFromLLM(
    prompt,
    numberOfRounds,
    llmApiKey,
    modelId,
    llmApiBaseUrl
  );
  console.log('[LLM] Song list from LLM (before track enrichment):', parsedSongListFromLLM);
  
  if (numberOfRounds > 0 && parsedSongListFromLLM.length === 0) {
    console.error('[LLM] LLM returned an empty song list when songs were expected.');
    throw new Error('LLM failed to generate any songs for the game.');
  }
  
  if (parsedSongListFromLLM.length !== numberOfRounds && numberOfRounds > 0) {
    console.warn(`[LLM] LLM generated ${parsedSongListFromLLM.length} songs, but ${numberOfRounds} were requested. Using what was generated.`);
  }
  
  // Enrich songs with track URLs using the configured music provider
  const musicProvider = env.MUSIC_PROVIDER;
  console.log(`[LLM Service] Starting track enrichment using provider: ${musicProvider}`);
  
  const enrichedSongs = await Promise.all(
    parsedSongListFromLLM.map(song => enrichSongWithTrackUrl(song, musicProvider, youtubeApiKey || undefined))
  );
  
  // Ensure all enriched songs have required fields for GameSong/GameRound
  for (const song of enrichedSongs) {
    if (!song.title || !song.artist) {
      throw new Error(`[LLM Service] Song missing required fields: ${JSON.stringify(song)}`);
    }
    // Optionally, enforce trackUrl presence if required by frontend
    // if (!song.trackUrl) throw new Error(`[LLM Service] Song missing trackUrl: ${JSON.stringify(song)}`);
  }
  
  const successCount = enrichedSongs.filter(song => song.trackUrl).length;
  console.log(`[LLM] Track enrichment complete: ${successCount}/${enrichedSongs.length} songs have track URLs`);
  console.log('[LLM] Final enriched song list:', enrichedSongs);
  
  return enrichedSongs;
}

/**
 * Fetches songs from the LLM API
 * @param prompt User's prompt for song theme
 * @param numberOfRounds Number of songs to generate
 * @param llmApiKey API key for LLM service
 * @param modelId Model ID to use
 * @param llmApiBaseUrl Base URL for LLM API (optional)
 * @returns Promise resolving to an array of songs without YouTube links
 */
async function fetchSongsFromLLM(
  prompt: string,
  numberOfRounds: number,
  llmApiKey: string,
  modelId: string,
  llmApiBaseUrl?: string | null
): Promise<Array<Song>> {
  // If we have a mock implementation for testing, use it
  if (_mockFetchSongsFromLLM) {
    return _mockFetchSongsFromLLM(prompt, numberOfRounds);
  }

  const effectiveLlmApiBaseUrl = llmApiBaseUrl || 'https://openrouter.ai/api/v1/chat/completions';

  // Placeholder for site URL and title, as per OpenRouter docs (optional)
  const siteReferer = 'http://localhost:5173'; // Or your actual deployed frontend URL
  const siteTitle = 'TuneTussle';

  const systemPrompt = "You are an AI that generates a list of songs (title and artist) based on a user's theme. Respond with ONLY a valid JSON array of objects, where each object has a 'title' and 'artist' key. Do not include any other text, narration, or explanations before or after the JSON. Ensure the array contains the exact number of songs requested.";
  const userPromptContent = `Generate exactly ${numberOfRounds} songs (title and artist) related to the theme: "${prompt}". Ensure the output is ONLY a valid JSON array of objects, each with "title" and "artist" keys.`;
    
  console.log('[LLM] Sending prompt to LLM:', { systemPrompt, userPromptContent, modelId, effectiveLlmApiBaseUrl });
  const llmResponse = await fetch(effectiveLlmApiBaseUrl, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${llmApiKey}`,
      'Content-Type': 'application/json',
      'HTTP-Referer': siteReferer,
      'X-Title': siteTitle,
    },
    body: JSON.stringify({
      model: modelId,
      messages: [
        { role: 'system', content: systemPrompt },
        { role: 'user', content: userPromptContent },
      ],
    }),
  });

  if (!llmResponse.ok) {
    let errorMessage = 'Unknown LLM API error';
    try {
      const errorData = await llmResponse.json();
      if (errorData && errorData.error && typeof errorData.error.message === 'string') {
        errorMessage = errorData.error.message;
      } else if (errorData && typeof errorData.message === 'string') {
        errorMessage = errorData.message;
      }
      console.error('[LLM] LLM API error response:', errorData);
    } catch (e) {
      errorMessage = llmResponse.statusText || errorMessage;
      console.error('[LLM] LLM API error (no JSON):', errorMessage);
    }
    throw new Error(`Failed to fetch song list from LLM: ${errorMessage}`);
  }

  const completion = await llmResponse.json();
  const rawContent = completion.choices?.[0]?.message?.content;
  console.log('[LLM] Raw LLM completion:', completion);
  if (!rawContent) {
    console.error('[LLM] LLM response was empty or malformed:', completion);
    throw new Error('LLM response was empty or malformed.');
  }

  try {
    // Handle markdown-formatted JSON by stripping markdown formatting
    let jsonContent = rawContent;
    if (rawContent.includes('```json')) {
      jsonContent = rawContent.split('```json')[1].split('```')[0].trim();
    } else if (rawContent.includes('```')) {
      jsonContent = rawContent.split('```')[1].split('```')[0].trim();
    }
    
    const parsedSongList = JSON.parse(jsonContent);
    if (!Array.isArray(parsedSongList) || 
        !parsedSongList.every(song => typeof song.title === 'string' && typeof song.artist === 'string')) {
      throw new Error('LLM response is not a valid JSON array of songs (title, artist).');
    }
    console.log('[LLM] Parsed song list:', parsedSongList);
    return parsedSongList;
  } catch (parseOrValidationError: any) {
    console.error('[LLM] Failed to parse or validate song list from LLM:', parseOrValidationError.message, rawContent);
    throw new Error(`Failed to parse or validate song list from LLM: ${parseOrValidationError.message}`);
  }
}

/**
 * Generates a creative clue for a song using OpenAI's GPT
 * @param song Song title
 * @param artist Artist name
 * @param difficulty Difficulty level for the clue
 * @returns Promise resolving to the generated clue
 */
export async function generateClue(song: string, artist: string, difficulty: string): Promise<string> {
  // If a mock implementation is provided, use it instead of real implementation
  if (_mockGenerateClue) {
    return _mockGenerateClue(song, artist, difficulty);
  }

  try {
    const client = getOpenAI();
    
    const prompt = `Generate a creative clue for the song "${song}" by ${artist}. The clue should be ${difficulty} difficulty. Make it engaging and fun, but not too obvious. Don't mention the song title or artist name directly.`;
    
    const messages: ChatMessage[] = [
      {
        role: 'system',
        content: 'You are a creative music trivia host. Generate engaging clues that are fun but appropriately challenging.'
      },
      {
        role: 'user',
        content: prompt
      }
    ];
    
    const response = await client.chat.completions.create({
      model: 'gpt-3.5-turbo',
      messages,
      max_tokens: 150,
      temperature: 0.8,
    }) as OpenAIResponse;
    
    const clue = response.choices[0]?.message?.content?.trim();
    
    if (!clue) {
      console.warn('[LLM] Generated clue was empty, using fallback');
      return generateFallbackClue(song, artist, difficulty);
    }
    
    console.log(`[LLM] Generated clue for "${song}" by ${artist}: ${clue}`);
    return clue;
    
  } catch (error: unknown) {
    const openAIError = error as OpenAIError;
    console.error('[LLM] Error generating clue:', openAIError.message);
    console.error('[LLM] Error details:', { 
      status: openAIError.status, 
      code: openAIError.code 
    });
    
    // Fallback to a generic clue
    return generateFallbackClue(song, artist, difficulty);
  }
}

/**
 * Generates a fallback clue when OpenAI fails
 * @param song Song title
 * @param artist Artist name
 * @param difficulty Difficulty level
 * @returns Fallback clue string
 */
function generateFallbackClue(song: string, artist: string, difficulty: string): string {
  const templates = {
    easy: [
      `This popular track has become a signature song for many artists.`,
      `You've probably heard this one on the radio countless times.`,
      `This song is considered a classic by many music fans.`
    ],
    medium: [
      `This track features memorable lyrics that tell an interesting story.`,
      `The melody of this song has a distinctive hook that's hard to forget.`,
      `This piece showcases the artist's unique musical style and talent.`
    ],
    hard: [
      `This composition demonstrates sophisticated musical arrangements.`,
      `The production techniques used in this recording were innovative for its time.`,
      `This track represents a significant moment in the artist's musical evolution.`
    ]
  };
  
  const difficultyTemplates = templates[difficulty as keyof typeof templates] || templates.medium;
  const randomTemplate = difficultyTemplates[Math.floor(Math.random() * difficultyTemplates.length)];
  
  console.log(`[LLM] Using fallback clue template for "${song}" by ${artist}`);
  return randomTemplate;
} 