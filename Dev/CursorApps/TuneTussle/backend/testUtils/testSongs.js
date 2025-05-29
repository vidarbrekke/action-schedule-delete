"use strict";
// Utility for DRY, YAGNI-compliant test song creation
// Use this for ALL test and mock song objects to ensure required fields are always present and in sync with production logic.
Object.defineProperty(exports, "__esModule", { value: true });
exports.makeTestSong = makeTestSong;
exports.makeTestSongs = makeTestSongs;
/**
 * Create a single test GameSong with all required fields.
 */
function makeTestSong(title, artist, overrides = {}) {
    return Object.assign({ title,
        artist, trackUrl: 'mock://audio.mp3', used: false }, overrides);
}
/**
 * Create an array of test GameSongs from arrays of titles/artists.
 */
function makeTestSongs(songs, overrides) {
    return songs.map(({ title, artist }) => makeTestSong(title, artist, overrides));
}
