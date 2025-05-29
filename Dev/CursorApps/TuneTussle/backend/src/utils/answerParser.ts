// answerParser.ts
// Utility to parse flexible answer strings into { songTitle, artist }

/**
 * Parses a flexible answer string into possible { songTitle, artist } interpretations.
 * If correctAnswer is provided, uses intelligent matching based on known song/artist.
 */
export function parseSongAndArtist(
  raw: string, 
  correctAnswer?: { title: string; artist: string }
): Array<{ songTitle: string; artist: string }> {
  if (!raw || typeof raw !== 'string') {
    return [{ songTitle: '', artist: '' }];
  }

  const trimmedInput = raw.trim();

  // If we have the correct answer, use intelligent matching
  if (correctAnswer) {
    return parseWithKnownAnswer(trimmedInput, correctAnswer);
  }

  // Fallback to basic parsing when correct answer is unknown
  return parseBasic(trimmedInput);
}

/**
 * Intelligent parsing when we know the correct song title and artist
 */
function parseWithKnownAnswer(
  input: string,
  correctAnswer: { title: string; artist: string }
): Array<{ songTitle: string; artist: string }> {
  const songTitle = correctAnswer.title.toLowerCase();
  const artistName = correctAnswer.artist.toLowerCase();
  const lowerInput = input.toLowerCase();
  
  // Remove common delimiters and normalize spaces
  const normalizedInput = lowerInput.replace(/[,\-\|\/]/g, ' ').replace(/\s+/g, ' ').trim();
  
  // Helper function to check flexible matching (handles articles like "the")
  const flexibleMatch = (text: string, target: string): boolean => {
    // Direct match
    if (text.includes(target)) {
      return true;
    }
    
    // Try removing articles from both text and target
    const removeArticles = (str: string) => 
      str.replace(/^(the|a|an)\s+/i, '').replace(/\s+(the|a|an)\s+/gi, ' ').trim();
    
    const textNoArticles = removeArticles(text);
    const targetNoArticles = removeArticles(target);
    
    return textNoArticles.includes(targetNoArticles) || targetNoArticles.includes(textNoArticles);
  };
  
  // Check if both song and artist appear in the input
  const containsSong = flexibleMatch(normalizedInput, songTitle);
  const containsArtist = flexibleMatch(normalizedInput, artistName);
  
  if (containsSong && containsArtist) {
    // Both detected - return as correct match
    return [{
      songTitle: correctAnswer.title,
      artist: correctAnswer.artist
    }];
  } else if (containsSong) {
    // Only song detected
    return [{
      songTitle: correctAnswer.title,
      artist: ''
    }];
  } else if (containsArtist) {
    // Only artist detected
    return [{
      songTitle: '',
      artist: correctAnswer.artist
    }];
  }
  
  // Neither detected clearly - fall back to basic parsing with original input
  return parseBasic(input);
}

/**
 * Basic parsing when correct answer is unknown (legacy behavior)
 */
function parseBasic(input: string): Array<{ songTitle: string; artist: string }> {
  // Try "by" (case-insensitive) - this one is unambiguous
  const byMatch = input.match(/^(.*)\s+by\s+(.*)$/i);
  if (byMatch) {
    return [{
      songTitle: byMatch[1].trim(),
      artist: byMatch[2].trim(),
    }];
  }

  // Try comma - return both possible interpretations since order is ambiguous
  const commaMatch = input.split(',');
  if (commaMatch.length === 2) {
    const left = commaMatch[0].trim();
    const right = commaMatch[1].trim();
    return [
      { songTitle: left, artist: right },    // Assume left is song, right is artist
      { songTitle: right, artist: left }     // Also try right is song, left is artist
    ];
  }

  // Fallback: return both interpretations
  return [
    { songTitle: input, artist: '' },
    { songTitle: '', artist: input }
  ];
} 