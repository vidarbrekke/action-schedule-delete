<?php
/**
 * Template Manager class.
 *
 * @package UTG
 */

namespace UTG\Templates;

/**
 * Template Manager
 *
 * Provides pre-defined templates for common websites that are difficult to parse.
 */
class UTG_Template_Manager {
    /**
     * Get a template for a specific domain
     * 
     * @param string $url The URL being processed
     * @param string $title The title extracted from the page
     * @param string $content The original HTML content
     * @return array|null Template data or null if no template available
     */
    public function get_template_for_url($url, $title, $content) {
        // Extract domain from URL
        $domain = '';
        if (preg_match('/^(?:https?:\/\/)?(?:[^@\n]+@)?(?:www\.)?([^:\/\n]+)/', $url, $matches)) {
            $domain = $matches[1];
        }

        // Check common domains first
        $template = $this->get_template_by_domain($domain, $title, $url);
        if ($template) {
            return $template;
        }
        
        // If domain-based template not found, check if content is actually minimal
        if ($content) {
            // Look for indicators of real content
            $has_real_content = $this->has_meaningful_content($content);
            
            // Only consider it minimal if it's small AND doesn't have meaningful content patterns
            if (!$has_real_content && strlen($content) < 1500) { // Reduced from 3000
                return $this->get_minimal_content_template($title, $url);
            }
        } else {
            // No content at all - use template
            return $this->get_minimal_content_template($title, $url);
        }
        
        return null;
    }
    
    /**
     * Check if content appears to have meaningful elements
     * 
     * @param string $content The HTML content to check
     * @return bool True if content appears to have useful elements
     */
    private function has_meaningful_content($content) {
        // Check for paragraph with substantial content
        $has_paragraphs = preg_match_all('/<p[^>]*>(.+?)<\/p>/is', $content, $matches);
        if ($has_paragraphs) {
            foreach ($matches[1] as $paragraph) {
                $text = strip_tags($paragraph);
                if (strlen($text) > 30) {
                    return true;
                }
            }
        }
        
        // Check for headings (h1-h6)
        $has_headings = preg_match('/<h[1-6][^>]*>.+?<\/h[1-6]>/is', $content);
        if ($has_headings) {
            return true;
        }
        
        // Check for images
        $has_images = preg_match('/<img[^>]+src=["\'][^"\']+["\'][^>]*>/is', $content);
        if ($has_images) {
            return true;
        }
        
        // Check for WordPress block structure
        $has_wp_blocks = strpos($content, '<!-- wp:') !== false;
        if ($has_wp_blocks) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get a template for a specific domain
     * 
     * @param string $domain The domain name
     * @param string $title The page title
     * @param string $url The full URL
     * @return array|null Template data or null if no template for this domain
     */
    private function get_template_by_domain($domain, $title, $url) {
        // Google search results template
        if (strpos($domain, 'google.com') !== false && preg_match('/\?q=([^&]+)/', $url, $matches)) {
            $search_term = urldecode($matches[1]);
            return $this->create_google_search_template($title, $search_term);
        }
        
        // Facebook template
        if (strpos($domain, 'facebook.com') !== false) {
            return $this->create_facebook_template($title);
        }
        
        // Twitter/X template
        if (strpos($domain, 'twitter.com') !== false || strpos($domain, 'x.com') !== false) {
            return $this->create_twitter_template($title);
        }
        
        // YouTube template
        if (strpos($domain, 'youtube.com') !== false) {
            return $this->create_youtube_template($title, $url);
        }
        
        return null;
    }
    
    /**
     * Create a template for Google search results
     * 
     * @param string $title The page title
     * @param string $search_term The search term
     * @return array Template data
     */
    private function create_google_search_template($title, $search_term) {
        return [
            'title' => $title ?: 'Google Search Results: ' . $search_term,
            'blocks' => [
                [
                    'type' => 'core/image',
                    'attributes' => [
                        'url' => '', // Will be replaced with Google logo by post generator
                        'alt' => 'Google Logo',
                        'align' => 'center'
                    ]
                ],
                [
                    'type' => 'core/search',
                    'attributes' => [
                        'label' => 'Search',
                        'placeholder' => $search_term,
                        'buttonText' => 'Search'
                    ]
                ],
                [
                    'type' => 'core/heading',
                    'attributes' => [
                        'content' => 'Search Results for: ' . $search_term,
                        'level' => 2
                    ]
                ],
                [
                    'type' => 'core/paragraph',
                    'attributes' => [
                        'content' => 'This is a template-generated representation of Google search results. The actual search results cannot be displayed due to JavaScript rendering limitations.'
                    ]
                ]
            ],
            'images' => [
                [
                    'alt_text' => 'Google Logo',
                    'description' => 'Google company logo',
                    'source_url' => 'https://www.google.com/images/branding/googlelogo/1x/googlelogo_color_272x92dp.png'
                ]
            ]
        ];
    }
    
    /**
     * Create a template for Facebook
     * 
     * @param string $title The page title
     * @return array Template data
     */
    private function create_facebook_template($title) {
        return [
            'title' => $title ?: 'Facebook',
            'blocks' => [
                [
                    'type' => 'core/columns',
                    'attributes' => [
                        'verticalAlignment' => 'top'
                    ],
                    'innerBlocks' => [
                        [
                            'type' => 'core/column',
                            'attributes' => [
                                'width' => 25
                            ],
                            'innerBlocks' => [
                                [
                                    'type' => 'core/heading',
                                    'attributes' => [
                                        'content' => 'Navigation',
                                        'level' => 3
                                    ]
                                ],
                                [
                                    'type' => 'core/list',
                                    'attributes' => [
                                        'values' => '<li>Home</li><li>Friends</li><li>Groups</li><li>Marketplace</li><li>Watch</li>'
                                    ]
                                ]
                            ]
                        ],
                        [
                            'type' => 'core/column',
                            'attributes' => [
                                'width' => 50
                            ],
                            'innerBlocks' => [
                                [
                                    'type' => 'core/paragraph',
                                    'attributes' => [
                                        'content' => 'This is a template-generated representation of a Facebook page. The actual content cannot be displayed due to JavaScript rendering limitations and Facebook\'s authentication requirements.'
                                    ]
                                ]
                            ]
                        ],
                        [
                            'type' => 'core/column',
                            'attributes' => [
                                'width' => 25
                            ],
                            'innerBlocks' => [
                                [
                                    'type' => 'core/heading',
                                    'attributes' => [
                                        'content' => 'Contacts',
                                        'level' => 3
                                    ]
                                ],
                                [
                                    'type' => 'core/paragraph',
                                    'attributes' => [
                                        'content' => 'Contact list would appear here'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'images' => []
        ];
    }
    
    /**
     * Create a template for Twitter/X
     * 
     * @param string $title The page title
     * @return array Template data
     */
    private function create_twitter_template($title) {
        return [
            'title' => $title ?: 'Twitter/X',
            'blocks' => [
                [
                    'type' => 'core/columns',
                    'attributes' => [
                        'verticalAlignment' => 'top'
                    ],
                    'innerBlocks' => [
                        [
                            'type' => 'core/column',
                            'attributes' => [
                                'width' => 25
                            ],
                            'innerBlocks' => [
                                [
                                    'type' => 'core/heading',
                                    'attributes' => [
                                        'content' => 'Navigation',
                                        'level' => 3
                                    ]
                                ],
                                [
                                    'type' => 'core/list',
                                    'attributes' => [
                                        'values' => '<li>Home</li><li>Explore</li><li>Notifications</li><li>Messages</li><li>Bookmarks</li>'
                                    ]
                                ]
                            ]
                        ],
                        [
                            'type' => 'core/column',
                            'attributes' => [
                                'width' => 50
                            ],
                            'innerBlocks' => [
                                [
                                    'type' => 'core/paragraph',
                                    'attributes' => [
                                        'content' => 'This is a template-generated representation of a Twitter/X page. The actual tweets cannot be displayed due to JavaScript rendering limitations and authentication requirements.'
                                    ]
                                ]
                            ]
                        ],
                        [
                            'type' => 'core/column',
                            'attributes' => [
                                'width' => 25
                            ],
                            'innerBlocks' => [
                                [
                                    'type' => 'core/heading',
                                    'attributes' => [
                                        'content' => 'Trending',
                                        'level' => 3
                                    ]
                                ],
                                [
                                    'type' => 'core/paragraph',
                                    'attributes' => [
                                        'content' => 'Trending topics would appear here'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'images' => []
        ];
    }
    
    /**
     * Create a template for YouTube
     * 
     * @param string $title The page title
     * @param string $url The YouTube URL
     * @return array Template data
     */
    private function create_youtube_template($title, $url) {
        // Try to extract video ID
        $video_id = '';
        if (preg_match('/[?&]v=([^&]+)/', $url, $matches)) {
            $video_id = $matches[1];
        }
        
        return [
            'title' => $title ?: 'YouTube Video',
            'blocks' => [
                [
                    'type' => 'core/embed',
                    'attributes' => [
                        'url' => 'https://www.youtube.com/watch?v=' . $video_id,
                        'type' => 'video',
                        'providerNameSlug' => 'youtube',
                        'responsive' => true,
                        'className' => 'wp-embed-aspect-16-9 wp-has-aspect-ratio'
                    ]
                ],
                [
                    'type' => 'core/paragraph',
                    'attributes' => [
                        'content' => 'This is a template-generated representation of a YouTube video page. The video player above should work if the video ID was correctly extracted from the URL.'
                    ]
                ],
                [
                    'type' => 'core/columns',
                    'attributes' => [],
                    'innerBlocks' => [
                        [
                            'type' => 'core/column',
                            'attributes' => [
                                'width' => 70
                            ],
                            'innerBlocks' => [
                                [
                                    'type' => 'core/heading',
                                    'attributes' => [
                                        'content' => $title ?: 'YouTube Video',
                                        'level' => 2
                                    ]
                                ],
                                [
                                    'type' => 'core/paragraph',
                                    'attributes' => [
                                        'content' => 'Video description would appear here.'
                                    ]
                                ]
                            ]
                        ],
                        [
                            'type' => 'core/column',
                            'attributes' => [
                                'width' => 30
                            ],
                            'innerBlocks' => [
                                [
                                    'type' => 'core/heading',
                                    'attributes' => [
                                        'content' => 'Related Videos',
                                        'level' => 3
                                    ]
                                ],
                                [
                                    'type' => 'core/paragraph',
                                    'attributes' => [
                                        'content' => 'Related videos would appear here.'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'images' => []
        ];
    }
    
    /**
     * Get a generic template for minimal content pages
     * 
     * @param string $title The page title
     * @param string $url The URL
     * @return array Template data
     */
    private function get_minimal_content_template($title, $url) {
        // Extract domain for better heading
        $domain = '';
        if (preg_match('/^(?:https?:\/\/)?(?:[^@\n]+@)?(?:www\.)?([^:\/\n]+)/', $url, $matches)) {
            $domain = $matches[1];
        }
        
        // Create a meaningful heading from the title or URL
        $heading_content = $title ? $title : ($domain ? 'Content from ' . $domain : 'Web Page Content');
        
        return [
            'title' => $title ?: 'Page Content',
            'blocks' => [
                [
                    'type' => 'core/heading',
                    'attributes' => [
                        'content' => $heading_content,
                        'level' => 2
                    ]
                ],
                [
                    'type' => 'core/paragraph',
                    'attributes' => [
                        'content' => 'This page appears to be using JavaScript to load its content, and only minimal HTML was available for extraction. The representation below is a template-generated version based on the page title and URL.'
                    ]
                ],
                [
                    'type' => 'core/heading',
                    'attributes' => [
                        'content' => 'Source Information',
                        'level' => 3
                    ]
                ],
                [
                    'type' => 'core/paragraph',
                    'attributes' => [
                        'content' => 'Source URL: <a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '</a>'
                    ]
                ],
                [
                    'type' => 'core/paragraph',
                    'attributes' => [
                        'content' => 'To view the complete and interactive content of this page, please visit the URL directly using your web browser.'
                    ]
                ],
                [
                    'type' => 'core/separator',
                    'attributes' => []
                ],
                [
                    'type' => 'core/heading',
                    'attributes' => [
                        'content' => 'About This Content',
                        'level' => 3
                    ]
                ],
                [
                    'type' => 'core/paragraph',
                    'attributes' => [
                        'content' => 'This content was generated automatically by the URL to Gutenberg plugin. The plugin attempted to extract content from the provided URL but encountered limitations with JavaScript-rendered content or access restrictions.'
                    ]
                ]
            ],
            'images' => []
        ];
    }
} 