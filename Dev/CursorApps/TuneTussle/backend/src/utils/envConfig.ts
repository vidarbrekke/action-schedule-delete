// envConfig.ts - Centralized environment variable configuration

import * as dotenv from 'dotenv';
import path from 'path';

// Load environment variables from .env file
const result = dotenv.config({ path: path.resolve(process.cwd(), '.env') });

if (result.error) {
  console.warn('[ENV] Warning: Could not load .env file:', result.error.message);
  console.warn('[ENV] Using existing environment variables only.');
} else {
  console.log('[ENV] Loaded .env file from:', path.resolve(process.cwd(), '.env'));
}

// Define and export all environment variables used in the application
// This creates a centralized place to document, validate, and access env vars

interface EnvConfig {
  // API Keys
  OPENROUTER_API_KEY: string | undefined;
  OPENROUTER_MODEL_ID: string | undefined;
  OPENROUTER_API_BASE_URL: string | undefined;
  YOUTUBE_API_KEY: string | undefined;
  SPOTIFY_CLIENT_ID: string | undefined;
  SPOTIFY_CLIENT_SECRET: string | undefined;

  // Music Provider Configuration
  MUSIC_PROVIDER: 'youtube' | 'spotify' | 'deezer';

  // Server Configuration
  PORT: number;
  HOST: string;
  NODE_ENV: 'development' | 'production' | 'test';
}

export const env: EnvConfig = {
  // API Keys
  OPENROUTER_API_KEY: process.env.OPENROUTER_API_KEY,
  OPENROUTER_MODEL_ID: process.env.OPENROUTER_MODEL_ID || 'openai/gpt-3.5-turbo',
  OPENROUTER_API_BASE_URL: process.env.OPENROUTER_API_BASE_URL || 'https://openrouter.ai/api/v1/chat/completions',
  YOUTUBE_API_KEY: process.env.YOUTUBE_API_KEY,
  SPOTIFY_CLIENT_ID: process.env.SPOTIFY_CLIENT_ID,
  SPOTIFY_CLIENT_SECRET: process.env.SPOTIFY_CLIENT_SECRET,

  // Music Provider Configuration
  MUSIC_PROVIDER: (process.env.MUSIC_PROVIDER as 'youtube' | 'spotify' | 'deezer') || 'youtube',

  // Server Configuration
  PORT: parseInt(process.env.PORT || '4000', 10),
  HOST: process.env.HOST || '0.0.0.0',
  NODE_ENV: (process.env.NODE_ENV as EnvConfig['NODE_ENV']) || 'development',
};

// Log key environment variable status (without exposing sensitive values)
console.log('[ENV] Environment configuration loaded:');
console.log(`[ENV] - NODE_ENV: ${env.NODE_ENV}`);
console.log(`[ENV] - PORT: ${env.PORT}`);
console.log(`[ENV] - OPENROUTER_API_KEY: ${env.OPENROUTER_API_KEY ? '✓ Set' : '✗ Not set'}`);
console.log(`[ENV] - OPENROUTER_MODEL_ID: ${env.OPENROUTER_MODEL_ID ? '✓ Set' : '✗ Not set'}`);
console.log(`[ENV] - OPENROUTER_API_BASE_URL: ${env.OPENROUTER_API_BASE_URL ? '✓ Set' : '✗ Not set'}`);
console.log(`[ENV] - YOUTUBE_API_KEY: ${env.YOUTUBE_API_KEY ? '✓ Set' : '✗ Not set'}`);
console.log(`[ENV] - SPOTIFY_CLIENT_ID: ${env.SPOTIFY_CLIENT_ID ? '✓ Set' : '✗ Not set'}`);
console.log(`[ENV] - SPOTIFY_CLIENT_SECRET: ${env.SPOTIFY_CLIENT_SECRET ? '✓ Set' : '✗ Not set'}`);
console.log(`[ENV] - MUSIC_PROVIDER: ${env.MUSIC_PROVIDER}`);
console.log(`[ENV] - HOST: ${env.HOST}`);

// Utility function to check if required environment variables are set
export function validateRequiredEnvVars(requiredVars: Array<keyof EnvConfig>): boolean {
  const missingVars = requiredVars.filter(varName => !env[varName]);
  
  if (missingVars.length > 0) {
    console.error(`[ENV] Missing required environment variables: ${missingVars.join(', ')}`);
    return false;
  }
  
  return true;
}

// Export default object for convenience
export default env;

export const allowedOrigins = env.NODE_ENV === 'production' 
  ? [
      'https://tunetussle.com',
      'https://www.tunetussle.com'
    ]
  : [
      'http://localhost:5173',
      'http://192.168.1.170:5173'
    ]; 