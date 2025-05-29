require('dotenv').config();
const searchAndGetLinks = require('spotify-preview-finder');

const testSongs = [
  { title: 'Bohemian Rhapsody', artist: 'Queen' },
  { title: 'Blinding Lights', artist: 'The Weeknd' },
  { title: 'Watermelon Sugar', artist: 'Harry Styles' },
  { title: 'Sweet Child O Mine', artist: 'Guns N Roses' },
  { title: 'Shape of You', artist: 'Ed Sheeran' }
];

async function testMultipleSongs() {
  console.log('Testing spotify-preview-finder with multiple songs...\n');
  
  for (const song of testSongs) {
    console.log(`🎵 Testing: "${song.title}" by "${song.artist}"`);
    console.log('─'.repeat(50));
    
    try {
      const result = await searchAndGetLinks(`${song.title} ${song.artist}`, 2);
      
      if (result.success && result.results.length > 0) {
        console.log(`✅ Found ${result.results.length} results:`);
        
        result.results.forEach((track, index) => {
          const hasPreview = track.previewUrls.length > 0;
          console.log(`   ${index + 1}. ${track.name}`);
          console.log(`      Preview: ${hasPreview ? '✅ YES' : '❌ NO'}`);
          if (hasPreview) {
            console.log(`      URL: ${track.previewUrls[0]}`);
          }
        });
        
        const totalPreviews = result.results.reduce((sum, track) => sum + track.previewUrls.length, 0);
        console.log(`   📊 Total preview URLs found: ${totalPreviews}`);
      } else {
        console.log(`❌ No results found: ${result.error || 'Unknown error'}`);
      }
    } catch (error) {
      console.log(`❌ Error: ${error.message}`);
    }
    
    console.log('\n');
  }
}

testMultipleSongs(); 