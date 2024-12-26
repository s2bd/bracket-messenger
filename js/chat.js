document.addEventListener('DOMContentLoaded', () => {
    const chatList = document.getElementById('chatList');
    const messages = document.getElementById('messages');
    const messageInput = document.getElementById('messageInput');
    const sendMessageButton = document.getElementById('sendMessageButton');
    const attachmentButton = document.getElementById('attachmentButton');
    const placeholderMessage = document.getElementById('placeholderMessage');
    const currentChatIdInput = document.getElementById('currentChatId');
    const newChatButton = document.getElementById('newChatButton');
    const newChatPopup = document.getElementById('newChatPopup');
    const userSearchInput = document.getElementById('userSearchInput');
    const userSearchResults = document.getElementById('userSearchResults');
    const closePopupButton = document.getElementById('closePopupButton');

    let currentChatId = null;
    let refreshInterval = null;

    // Fetch messages for a specific chat
    function fetchMessages(chatId) {
        fetch(`chat.php?action=fetchMessages&chat_id=${chatId}`)
            .then(response => {
                if (!response.ok) throw new Error('Failed to fetch messages');
                return response.text();
            })
            .then(data => {
                // Load messages into the messages container
                messages.innerHTML = data;
                placeholderMessage.style.display = 'none'; // Hide placeholder

                // Scroll to the bottom after new messages are loaded
                scrollToBottom();
            })
            .catch(error => {
                console.error('Error fetching messages:', error);
            });
    }

    // Start auto-refresh for messages in the selected chat
    function startMessageAutoRefresh(chatId) {
        if (refreshInterval) {
            clearInterval(refreshInterval);  // Clear any existing interval
        }
        refreshInterval = setInterval(() => {
            fetchMessages(chatId);
        }, 5000); // Refresh every 5 seconds
    }

    // When a chat is clicked
    chatList.addEventListener('click', (event) => {
        const chatEntry = event.target.closest('.chat-entry');
        if (chatEntry) {
            currentChatId = chatEntry.dataset.chatId;
            currentChatIdInput.value = currentChatId;

            // Re-enable and reset opacity
            messageInput.disabled = false; 
            sendMessageButton.disabled = false;
            //attachmentButton.disabled = false; // attachment feature not ready yet!
            messageInput.style.opacity = '1';  
            sendMessageButton.style.opacity = '1';
            attachmentButton.style.opacity = '1'; 

            fetchMessages(currentChatId);  // Load messages for the selected chat
            startMessageAutoRefresh(currentChatId);  // Start auto-refreshing messages
        }
    });

    // Disable and reduce opacity
    messageInput.disabled = true;
    sendMessageButton.disabled = true;
    attachmentButton.disabled = true;
    messageInput.style.opacity = '0.5'; 
    sendMessageButton.style.opacity = '0.5';
    attachmentButton.style.opacity = '0.5'; 

    // Clear the refresh interval when leaving the page
    window.addEventListener('beforeunload', () => {
        if (refreshInterval) {
            clearInterval(refreshInterval);
        }
    });

    // Send message button functionality
    sendMessageButton.addEventListener('click', (e) => {
        e.preventDefault(); // Prevent page reload
        const chatId = currentChatIdInput.value;
        const message = messageInput.value.trim();

        if (!chatId || !message) return; // Avoid empty messages or missing chatId

        fetch('chat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                sendMessage: true,
                chat_id: chatId,
                message: message
            })
        })
            .then(response => {
                if (!response.ok) throw new Error('Failed to send message');
                return response.text();
            })
            .then(() => {
                fetchMessages(chatId);
                messageInput.value = ''; // Clear input after sending
            })
            .catch(error => {
                console.error('Error sending message:', error);
            });
    });

    // New Chat Popup logic
    newChatButton.addEventListener('click', () => {
        newChatPopup.style.display = 'block';
    });

    // Close popup logic
    closePopupButton.addEventListener('click', () => {
        newChatPopup.style.display = 'none';
        userSearchResults.innerHTML = ''; // Clear search results
        userSearchInput.value = '';       // Clear search input
    });

    // User search functionality
    userSearchInput.addEventListener('input', () => {
        const query = userSearchInput.value;
        if (query.length < 3) return;

        fetch(`search_user.php?query=${query}`)
            .then(response => response.json())
            .then(users => {
                userSearchResults.innerHTML = '';
                users.forEach(user => {
                    const div = document.createElement('div');
                    div.textContent = user.display_name;
                    div.addEventListener('click', () => {
                        fetch(`chat.php?action=checkExistingChat&userId=${user.user_id}`)
                            .then(response => response.json())
                            .then(data => {
                                if (data.exists) {
                                    alert('Chat already exists.');
                                } else {
                                    fetch(`chat.php?action=createNewChat&userId=${user.user_id}`)
                                        .then(response => response.json())
                                        .then(data => {
                                            if (data.success) {
                                                currentChatId = data.chatId;
                                                currentChatIdInput.value = currentChatId;
                                                fetchMessages(currentChatId);
                                                startMessageAutoRefresh(currentChatId);
                                                newChatPopup.style.display = 'none';
                                            }
                                        })
                                        .catch(error => console.error('Error creating chat:', error));
                                }
                            })
                            .catch(error => console.error('Error checking chat:', error));
                    });
                    userSearchResults.appendChild(div);
                });
            })
            .catch(error => console.error('Error searching users:', error));
    });
});
