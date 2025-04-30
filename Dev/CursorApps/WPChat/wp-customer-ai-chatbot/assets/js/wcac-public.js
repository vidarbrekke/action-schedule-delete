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

        // Conversation history array
        var conversation = [];

        // Make sure required elements exist
        if (!chatForm.length || !messageInput.length || !chatMessages.length || !sendButton.length) {
            console.error('WCAC: Required chat elements not found');
            return;
        }

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
            }
        }

        // Show typing indicator while waiting for response
        function showTypingIndicator() {
            chatMessages.append('<div id="wcac-typing-indicator" class="wcac-assistant-message"><div class="wcac-message-content"><span class="wcac-dot"></span><span class="wcac-dot"></span><span class="wcac-dot"></span></div></div>');
            chatMessages.scrollTop(chatMessages[0].scrollHeight);
        }

        // Remove typing indicator
        function removeTypingIndicator() {
            $('#wcac-typing-indicator').remove();
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
                data: {
                    action: 'wcac_send_message',
                    message: message,
                    conversation: JSON.stringify(conversation),
                    nonce: wcac_params.nonce
                },
                success: function(response) {
                    removeTypingIndicator();
                    sendButton.prop('disabled', false);
                    
                    if (response.success && response.data && response.data.message) {
                        addMessage('assistant', response.data.message);
                        
                        // Update nonce if provided
                        if (response.data.new_nonce) {
                            wcac_params.nonce = response.data.new_nonce;
                        }
                    } else if (!response.success && response.data && response.data.message) {
                        addMessage('assistant', response.data.message, true);
                    } else {
                        addMessage('assistant', 'Sorry, I encountered an error. Please try again.', true);
                        console.error('WCAC: Invalid response format', response);
                    }
                },
                error: function(xhr, status, error) {
                    removeTypingIndicator();
                    sendButton.prop('disabled', false);
                    
                    console.error('WCAC: Error sending message:', status, error);
                    addMessage('assistant', 'Sorry, there was an error communicating with the server. Please try again.', true);
                    
                    // If it's a nonce error, try to refresh the page
                    if (xhr.status === 403) {
                        setTimeout(function() {
                            addMessage('assistant', 'Your session may have expired. The page will refresh in 3 seconds...', true);
                            setTimeout(function() {
                                window.location.reload();
                            }, 3000);
                        }, 1000);
                    }
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

        // Display initial greeting if configured
        if (initialGreeting) {
            addMessage('assistant', initialGreeting);
        }
    }

    // Initialize when document is ready
    initChatbot();
}); 