require('dotenv').config();
const searchAndGetLinks = require('spotify-preview-finder');

async function testSpotifyFinder() {
  console.log('Testing spotify-preview-finder package...');
  console.log('Environment check:');
  console.log('- SPOTIFY_CLIENT_ID:', process.env.SPOTIFY_CLIENT_ID ? '✓ Set' : '✗ Missing');
  console.log('- SPOTIFY_CLIENT_SECRET:', process.env.SPOTIFY_CLIENT_SECRET ? '✓ Set' : '✗ Missing');
  
  try {
    console.log('\nSearching for "Bohemian Rhapsody Queen"...');
    const result = await searchAndGetLinks('Bohemian Rhapsody Queen', 3);
    
    console.log('\nResult:', JSON.stringify(result, null, 2));
    
    if (result.success && result.results.length > 0) {
      console.log('\n=== DETAILED RESULTS ===');
      result.results.forEach((track, index) => {
        console.log(`\n${index + 1}. ${track.name}`);
        console.log(`   Spotify URL: ${track.spotifyUrl}`);
        console.log(`   Preview URLs: ${track.previewUrls.length > 0 ? track.previewUrls.join(', ') : 'None'}`);
      });
    }
  } catch (error) {
    console.error('Error:', error.message);
  }
}

testSpotifyFinder(); 