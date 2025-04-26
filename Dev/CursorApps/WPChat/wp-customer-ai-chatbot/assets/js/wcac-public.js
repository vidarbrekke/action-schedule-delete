/**
 * WCAC Public JS
 */

(function() {
    'use strict';

    // Wait for the DOM to be fully loaded
    document.addEventListener('DOMContentLoaded', function() {
        const chatContainer = document.querySelector('.wcac-chatbot-container');
        if (!chatContainer) {
            return; // Exit if chat container not found
        }

        const messagesContainer = chatContainer.querySelector('.wcac-chat-messages');
        const sendButton = chatContainer.querySelector('button');
        const inputField = chatContainer.querySelector('input[type="text"]');

        if (!messagesContainer || !sendButton || !inputField) {
            console.error('WCAC Chatbot Error: Missing essential chat elements.');
            return; // Exit if elements are missing
        }

        console.log('WCAC Chatbot JS Initialized');

        // --- Debugging: Check localized data ---
        console.log('WCAC Localized Data:', wcac_chatbot_data);
        // --- End Debugging ---

        // Function to add a message to the chat display
        function addMessage(sender, text) {
            const messageElement = document.createElement('p');
            // Convert markdown links [Title](URL) to HTML <a> tags
            const linkRegex = /\[([^\]]+)]\((https?:\/\/[^)]+)\)/g;
            let processedText = text.replace(linkRegex, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');

            // Convert simple numbered lists (1. Item\n2. Item) to HTML <ol>
            processedText = processedText.replace(/^(\d+)\.\s+(.*?)($|\n)/gm, '<li>$2</li>');
            if (processedText.includes('<li>')) {
                processedText = '<ol>' + processedText.replace(/<\/li>\n?<li>/g, '</li><li>') + '</ol>'; // Wrap in <ol> and clean up extra space
            }

            // Convert simple bullet lists (* Item\n- Item) to HTML <ul>
            processedText = processedText.replace(/^([*\\-])\s+(.*?)($|\n)/gm, '<li>$2</li>');
            if (processedText.includes('<li>') && !processedText.includes('<ol>')) { // Avoid double-wrapping if numbered list was already found
                processedText = '<ul>' + processedText.replace(/<\/li>\n?<li>/g, '</li><li>') + '</ul>'; // Wrap in <ul>
            }

            // Convert any remaining double newlines to paragraph breaks (basic)
            processedText = processedText.replace(/\n\n/g, '</p><p>');
            if (processedText.includes('<p>')) {
                processedText = '<p>' + processedText + '</p>'; // Ensure wrapped in <p>
            }

            // Convert single newlines (not already handled by lists/paragraphs) to <br>
            processedText = processedText.replace(/(?<!<br>|<\/li>|<\/p>)\n(?!<br>|<li>|<p>)/g, '<br>'); // Corrected JS regex escaping for slashes

            // Set innerHTML with the processed text containing HTML links and structure
            messageElement.innerHTML = `<strong>${sender}:</strong> ${processedText}`;

            messagesContainer.appendChild(messageElement);
            // Scroll to the bottom
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        // Function to handle sending the message
        async function sendMessage() {
            const userMessage = inputField.value.trim();
            if (userMessage === '') {
                return; // Don't send empty messages
            }

            // Display user message immediately
            addMessage('You', userMessage);
            inputField.value = ''; // Clear input field
            sendButton.disabled = true; // Disable button during request
            addMessage('Bot', 'Typing...'); // Add typing indicator

            // Prepare data for AJAX request
            const formData = new FormData();
            formData.append('action', 'wcac_send_message');
            formData.append('nonce', wcac_chatbot_data.nonce); // Access localized data
            formData.append('message', userMessage);

            try {
                const response = await fetch(wcac_chatbot_data.ajax_url, {
                    method: 'POST',
                    body: formData
                });

                // Remove typing indicator before processing response
                const typingIndicator = messagesContainer.lastChild;
                if (typingIndicator && typingIndicator.textContent.includes('Typing...')) {
                    messagesContainer.removeChild(typingIndicator);
                }

                const result = await response.json();

                if (result.success) {
                    addMessage('Bot', result.data.reply);
                } else {
                    console.error('AJAX Error:', result.data.message);
                    addMessage('System', `Error: ${result.data.message || 'Could not get reply.'}`);
                }
            } catch (error) {
                // Remove typing indicator on fetch error
                const typingIndicator = messagesContainer.lastChild;
                if (typingIndicator && typingIndicator.textContent.includes('Typing...')) {
                    messagesContainer.removeChild(typingIndicator);
                }
                console.error('Fetch/Processing Error:', error); // Log the actual error
                // Provide a more robust generic error message
                addMessage('System', 'Error: Could not process the request. Please check the console for details.');
            } finally {
                sendButton.disabled = false; // Re-enable button
            }
        }

        // Add event listener for the send button
        sendButton.addEventListener('click', sendMessage);

        // Add event listener for pressing Enter in the input field
        inputField.addEventListener('keypress', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault(); // Prevent default form submission if it were in a form
                sendMessage();
            }
        });
    });

})(); 