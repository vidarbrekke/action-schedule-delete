import { parseSongAndArtist } from './answerParser';

describe('answerParser', () => {
  describe('parseSongAndArtist', () => {
    it('should handle comma-separated input with both interpretations', () => {
      const result = parseSongAndArtist('Nirvana, smells like teen spirit');
      
      expect(result).toHaveLength(2);
      expect(result).toEqual([
        { songTitle: 'Nirvana', artist: 'smells like teen spirit' },
        { songTitle: 'smells like teen spirit', artist: 'Nirvana' }
      ]);
    });

    it('should handle "by" separator unambiguously', () => {
      const result = parseSongAndArtist('Smells Like Teen Spirit by Nirvana');
      
      expect(result).toHaveLength(1);
      expect(result[0]).toEqual({
        songTitle: 'Smells Like Teen Spirit',
        artist: 'Nirvana'
      });
    });

    it('should handle case-insensitive "by" separator', () => {
      const result = parseSongAndArtist('Smells Like Teen Spirit BY Nirvana');
      
      expect(result).toHaveLength(1);
      expect(result[0]).toEqual({
        songTitle: 'Smells Like Teen Spirit',
        artist: 'Nirvana'
      });
    });

    it('should handle input without separators', () => {
      const result = parseSongAndArtist('Nirvana');
      
      expect(result).toHaveLength(2);
      expect(result).toEqual([
        { songTitle: 'Nirvana', artist: '' },
        { songTitle: '', artist: 'Nirvana' }
      ]);
    });

    it('should handle empty or invalid input', () => {
      expect(parseSongAndArtist('')).toEqual([{ songTitle: '', artist: '' }]);
      expect(parseSongAndArtist(null as any)).toEqual([{ songTitle: '', artist: '' }]);
      expect(parseSongAndArtist(undefined as any)).toEqual([{ songTitle: '', artist: '' }]);
    });

    it('should trim whitespace correctly', () => {
      const result = parseSongAndArtist('  Smells Like Teen Spirit  ,  Nirvana  ');
      
      expect(result).toHaveLength(2);
      expect(result).toEqual([
        { songTitle: 'Smells Like Teen Spirit', artist: 'Nirvana' },
        { songTitle: 'Nirvana', artist: 'Smells Like Teen Spirit' }
      ]);
    });
  });
}); 