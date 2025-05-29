// Utility to normalize song titles and artist names for comparison
export function normalizeSongOrArtist(str: string): string {
  if (!str) return ''; // Handle null or undefined input gracefully

  let normalized = str.toLowerCase();

  // Remove common articles (whole words)
  normalized = normalized.replace(/\b(the|a|an)\b/g, '');

  // Remove "feat.", "ft." (whole words or followed by space/punctuation)
  // Consider variants like "featuring"
  normalized = normalized.replace(/\b(featuring|feat|ft)\b[.\s]?/g, '');
  
  // Replace "&" with "and" for consistency.
  normalized = normalized.replace(/&/g, 'and');

  // Remove specified punctuation. 
  // Consider if '-' should be kept or replaced with space, e.g. "Spider-Man" vs "Spider Man"
  // For now, removing it along with other punctuation.
  normalized = normalized.replace(/[.,/#!$%^;:{}=_`~()\-]/g, ''); 
                                  // Note: '&' was removed from this regex as it's handled above.

  // Collapse multiple whitespace characters (including those left by replacements) to a single space
  // and trim leading/trailing spaces that might result from replacements at string ends.
  normalized = normalized.replace(/\s+/g, ' ').trim();

  return normalized;
} 