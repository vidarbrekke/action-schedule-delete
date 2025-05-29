/**
 * WCAC Public JS
 */

jQuery(document).ready(function($) {
    'use strict';

    // Initialize the chatbot functionality
    function initChatbot() {
        var chatForm = $('#wcac-chat-form');
        var messageInput = $('#wcac-message');
        var chatMessages = $('#wcac-chat-messages');
        var sendButton = $('#wcac-send-button');
        var chatContainer = $('#wcac-chatbot-container');
        var initialGreeting = chatContainer.data('greeting');

        // Persistent conversation history key
        var STORAGE_KEY = 'wcac_chat_history';
        // Conversation history array
        var conversation = [];

        // Restore conversation from localStorage if available
        function restoreConversation() {
            var stored = localStorage.getItem(STORAGE_KEY);
            if (stored) {
                try {
                    var parsed = JSON.parse(stored);
                    if (Array.isArray(parsed)) {
                        conversation = parsed;
                        // Render all messages
                        chatMessages.empty();
                        parsed.forEach(function(turn) {
                            if (turn.role === 'user') {
                                addMessage('user', turn.content);
                            } else if (turn.role === 'assistant') {
                                addMessage('assistant', turn.content);
                            }
                        });
                    }
                } catch (e) {
                    console.warn('WCAC: Failed to parse stored chat history', e);
                }
            }
        }

        // Save conversation to localStorage
        function saveConversation() {
            try {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(conversation));
            } catch (e) {
                console.warn('WCAC: Failed to save chat history', e);
            }
        }

        // Make sure required elements exist
        if (!chatForm.length || !messageInput.length || !chatMessages.length || !sendButton.length) {
            console.error('WCAC: Required chat elements not found');
            return;
        }

        // Restore chat on load
        restoreConversation();

        // Verify AJAX connectivity on initialization
        verifyAjaxConnectivity();

        // Format message with markdown-style formatting
        function formatMessage(message) {
            if (!message) return '';
            
            // Convert bullet points (now checking for '-') to proper HTML lists
            if (message.indexOf('- ') !== -1) {
                var lines = message.split('\n');
                var inList = false;
                var formattedLines = [];

                for (var i = 0; i < lines.length; i++) {
                    var line = lines[i];
                    
                    // Match lines starting with hyphen-space for bullet points
                    if (line.trim().startsWith('- ')) {
                        if (!inList) {
                            formattedLines.push('<ul>');
                            inList = true;
                        }
                        // Extract list item content and convert link within it
                        var listItemContent = line.trim().substring(2);
                        
                        // DEBUG: Log content before and after replace for first 3 items
                        if (i < 3) { 
                            console.log(`WCAC JS DEBUG [${i}] Before Replace:`, listItemContent);
                        }
                        
                        var linkedContent = listItemContent.replace(/\[(.+?)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');
                        
                        if (i < 3) {
                            console.log(`WCAC JS DEBUG [${i}] After Replace:`, linkedContent);
                        }
                        
                        formattedLines.push('<li>' + linkedContent + '</li>');
                    } else {
                        if (inList) {
                            formattedLines.push('</ul>');
                            inList = false;
                        }
                        formattedLines.push(line);
                    }
                }
                
                if (inList) {
                    formattedLines.push('</ul>');
                }
                
                message = formattedLines.join('\n');
            }
            
            // Handle line breaks
            // Convert any remaining markdown links (e.g., outside lists)
            message = message.replace(/\[(.+?)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');
            
            message = message.replace(/\n/g, '<br>');
            
            return message;
        }

        // Add a message to the chat history and to the conversation array
        function addMessage(sender, message, isError) {
            var messageClass = 'wcac-' + sender + '-message';
            if (isError) messageClass += ' wcac-error-message';
            
            var formattedMessage = formatMessage(message);
            chatMessages.append('<div class="' + messageClass + '"><div class="wcac-message-content">' + formattedMessage + '</div></div>');
            
            // Scroll to the bottom of the chat
            chatMessages.scrollTop(chatMessages[0].scrollHeight);
            
            // Add to conversation array (except for errors)
            if (!isError && (sender === 'user' || sender === 'assistant')) {
                conversation.push({
                    role: sender === 'user' ? 'user' : 'assistant',
                    content: message
                });
                saveConversation(); // Save after each new message
            }
        }

        // Show typing indicator while waiting for response
        function showTypingIndicator() {
            // Always remove any existing indicator first
            $('#wcac-typing-indicator').remove();
            chatMessages.append('<div id="wcac-typing-indicator" class="wcac-assistant-message"><div class="wcac-message-content"><span class="wcac-dot"></span><span class="wcac-dot"></span><span class="wcac-dot"></span></div></div>');
            chatMessages.scrollTop(chatMessages[0].scrollHeight);
            console.log('WCAC: showTypingIndicator called. Count now:', $('#wcac-typing-indicator').length);
        }

        // Remove typing indicator
        function removeTypingIndicator() {
            var count = $('#wcac-typing-indicator').length;
            $('#wcac-typing-indicator').remove();
            console.log('WCAC: removeTypingIndicator called. Removed count:', count, 'Remaining:', $('#wcac-typing-indicator').length);
        }

        // Verify AJAX connectivity and nonce
        function verifyAjaxConnectivity() {
            console.log('WCAC: Verifying AJAX connectivity...');
            
            // Check if we have the required parameters
            if (!wcac_params || !wcac_params.ajax_url || !wcac_params.nonce) {
                console.error('WCAC: Missing required parameters for AJAX verification');
                console.log('WCAC params:', wcac_params);
                return;
            }
            
            // Make a test request to verify nonce
            $.ajax({
                url: wcac_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'wcac_debug_nonce',
                    nonce: wcac_params.nonce
                },
                success: function(response) {
                    if (response.success) {
                        console.log('WCAC: Nonce verification:', response.data.verification_result);
                        
                        // Always update the nonce with the new one
                        if (response.data.new_nonce) {
                            wcac_params.nonce = response.data.new_nonce;
                            console.log('WCAC: Nonce updated');
                        }
                        
                        // If this is the first load and we have a greeting, show it
                        if (initialGreeting && chatMessages.children().length === 0) {
                            addMessage('assistant', initialGreeting);
                        }
                    } else {
                        console.error('WCAC: Nonce verification failed:', response);
                        addMessage('assistant', 'There was an error connecting to the server. Please reload the page and try again.', true);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('WCAC: AJAX connectivity test failed', status, error);
                    console.log('Status code:', xhr.status);
                    console.log('Response text:', xhr.responseText);
                    
                    // Add error message to chat if visible
                    if (chatContainer.is(':visible')) {
                        addMessage('assistant', 'There seems to be a connection issue. Please reload the page and try again.', true);
                    }
                }
            });
        }

        // --- Add this function for automatic hyperlinking ---
        function autoLinkTitles(text, products) {
            if (!Array.isArray(products) || products.length === 0 || !text) return text;
            // Build a map of lowercased titles to URLs
            var titleToUrl = {};
            products.forEach(function(item) {
                var name = item.title || item.name;
                var url = item.url;
                if (name && url) {
                    titleToUrl[name.toLowerCase()] = url;
                }
            });
            // Sort titles by length descending to avoid partial matches
            var titles = Object.keys(titleToUrl).sort(function(a, b) { return b.length - a.length; });
            // Replace each title with a link, only if not already inside an <a> tag
            titles.forEach(function(title) {
                // Regex: match whole word, case-insensitive, not inside HTML tag
                var regex = new RegExp('(?<![\w>])(' + title.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')(?![\w<])', 'gi');
                text = text.replace(regex, function(match) {
                    // Avoid double-linking if already inside an <a> tag
                    if (/<a [^>]*?>.*?/.test(match)) return match;
                    return '<a href="' + titleToUrl[title] + '" target="_blank" rel="noopener noreferrer">' + match + '</a>';
                });
            });
            return text;
        }

        // Handle sending a message to the server
        function sendMessage(message) {
            if (!message.trim()) return;
            
            // Display user message
            addMessage('user', message);
            
            // Clear input field
            messageInput.val('');
            
            // Show typing indicator
            showTypingIndicator();
            
            // Disable send button while processing
            sendButton.prop('disabled', true);
            
            // Send AJAX request to server with conversation history
            $.ajax({
                url: wcac_params.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'wcac_send_message',
                    message: message,
                    conversation: JSON.stringify(conversation),
                    nonce: wcac_params.nonce
                },
                beforeSend: function() {
                    showTypingIndicator();
                },
                success: function(response, status, xhr) {
                    console.log('WCAC: Raw AJAX Success response string:', xhr.responseText);
                    sendButton.prop('disabled', false);

                    if (typeof response === 'string') {
                        try {
                            response = JSON.parse(response);
                        } catch (e) {
                            removeTypingIndicator();
                            console.error('WCAC: Failed to parse JSON response', response);
                            addMessage('assistant', 'Sorry, I encountered an error. Please try again.', true);
                            return;
                        }
                    }

                    console.log('WCAC: Parsed AJAX Success response:', response);

                    // --- Unified flat response handling ---
                    if (response && response.success) {
                        var data = response.data || response; // Some plugins use data, some put keys at top level
                        var msg = data.response || data.message || '';
                        var products = Array.isArray(data.products) ? data.products : [];
                        // Deduplicate products by title or name (already done backend, but keep for safety)
                        var seenTitles = new Set();
                        var dedupedProducts = [];
                        products.forEach(function(item) {
                            var name = item.title || item.name || '';
                            if (!seenTitles.has(name.toLowerCase())) {
                                seenTitles.add(name.toLowerCase());
                                dedupedProducts.push(item);
                            }
                        });
                        // Auto-link product/page titles in the LLM response
                        msg = autoLinkTitles(msg, dedupedProducts);
                        // Only show the LLM's answer (with auto-linking)
                        addMessage('assistant', msg, false);
                        saveConversation(); // Save after assistant reply
                        return;
                    }
                    // --- End unified flat response handling ---

                    // Fallback for invalid format
                    removeTypingIndicator();
                    console.error('WCAC: Invalid response format', response);
                    addMessage('assistant', 'Sorry, I encountered an error. Please try again.', true);
                },
                error: function(xhr, status, error) {
                    removeTypingIndicator();
                    sendButton.prop('disabled', false);
                    
                    console.error('WCAC: Error sending message:', status, error);
                    removeTypingIndicator();
                    addMessage('assistant', 'Sorry, there was an error communicating with the server. Please try again.', true);
                    
                    // If it's a nonce error, try to refresh the page
                    if (xhr.status === 403) {
                        setTimeout(function() {
                            removeTypingIndicator();
                            addMessage('assistant', 'Your session may have expired. The page will refresh in 3 seconds...', true);
                            setTimeout(function() {
                                window.location.reload();
                            }, 3000);
                        }, 1000);
                    }
                    
                    console.error('WCAC: Raw AJAX response:', xhr.responseText); // Log raw response
                    console.error('WCAC: AJAX status:', status);
                    console.error('WCAC: AJAX error:', error);
                }
            });
        }

        // Handle form submission
        chatForm.on('submit', function(e) {
            e.preventDefault();
            var message = messageInput.val();
            sendMessage(message);
        });

        // Handle enter key press (but allow shift+enter for new lines)
        messageInput.on('keydown', function(e) {
            if (e.keyCode === 13 && !e.shiftKey) {
                e.preventDefault();
                chatForm.submit();
            }
        });

        // Display initial greeting if configured and not already restored
        if (initialGreeting && conversation.length === 0) {
            addMessage('assistant', initialGreeting);
        }

        // Add Clear Chat button to the chat container
        var clearButton = $('<button type="button" id="wcac-clear-chat" class="button" style="margin-left:8px; margin-bottom:8px;">Clear Chat</button>');
        chatContainer.prepend(clearButton);

        clearButton.on('click', function() {
            if (confirm('Clear all chat history?')) {
                localStorage.removeItem(STORAGE_KEY);
                conversation = [];
                chatMessages.empty();
                if (initialGreeting) {
                    addMessage('assistant', initialGreeting);
                }
            }
        });
    }

    // Initialize when document is ready
    initChatbot();
}); 