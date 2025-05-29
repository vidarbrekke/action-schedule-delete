declare module 'spotify-preview-finder' {
  interface SearchResult {
    title: string;
    artist: string;
    preview_url: string;
  }

  function searchAndGetLinks(query: string): Promise<SearchResult[]>;
  export default searchAndGetLinks;
} 