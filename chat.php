<?php
include 'db_connection.php';
session_start();
$userId = $_SESSION['user_id'];

// Fetch chat list
// Fetch chat list
function fetchChats($conn, $userId) {
    $query = "
        SELECT 
            c.chat_id, 
            u.display_name AS creator_name,
            m.body AS latest_message, 
            m.timestamp AS latest_timestamp
        FROM Chat c
        JOIN Chat_Members cm ON cm.chat_id = c.chat_id
        JOIN User u ON u.user_id = c.creator_id
        LEFT JOIN Message m ON m.chat_id = c.chat_id
        WHERE cm.user_id = $userId 
        AND m.timestamp = (
            SELECT MAX(timestamp) 
            FROM Message 
            WHERE chat_id = c.chat_id
        )
        ORDER BY latest_timestamp DESC;
    ";

    $result = $conn->query($query);
    $chatsHTML = '';

    // Set the default timezone to UTC (or your preferred timezone)
    date_default_timezone_set('UTC');
    
    while ($row = $result->fetch_assoc()) {
        // Format the timestamp to a more user-friendly format
        $timestamp = new DateTime($row['latest_timestamp']);
        $latestDate = $timestamp->format('d-m-Y'); // Date format for the conversation flow
        $latestTime = $timestamp->format('H:i:s'); // Time format for individual messages

        $chatsHTML .= "<div class='chat-entry' data-chat-id='{$row['chat_id']}'>
                        <strong>{$row['creator_name']}</strong><br>
                        <span>{$row['latest_message']}</span><br>
                        <small class='timestamp-date'>{$latestDate}</small><br>
                        <small class='timestamp-time'>{$latestTime}</small>
                        </div>";
    }

    return $chatsHTML;
}


// Fetch messages for a specific chat
function fetchMessages($conn, $userId, $chatId) {
    $query = "
        SELECT 
            u.display_name, 
            m.body, 
            m.sender_id, 
            m.timestamp
        FROM Message m
        JOIN User u ON u.user_id = m.sender_id
        JOIN Chat_Members cm ON cm.chat_id = m.chat_id
        WHERE cm.user_id = $userId AND m.chat_id = $chatId
        ORDER BY m.timestamp ASC
    ";

    $result = $conn->query($query);
    $messagesHTML = '';
    while ($row = $result->fetch_assoc()) {
        $class = $row['sender_id'] == $userId ? 'sender' : 'receiver';
        // Format individual message timestamps
        $messageTimestamp = new DateTime($row['timestamp']);
        $messageTime = $messageTimestamp->format('H:i:s'); // Time format only

        $messagesHTML .= "<div class='message $class'>
                            <strong>{$row['display_name']}</strong><br>
                            <span>{$row['body']}</span><br>
                            <small class='message-time'>{$messageTime}</small>
                        </div>";

    }
    return $messagesHTML;
}

// Send message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sendMessage'])) {
    $chatId = $_POST['chat_id'] ?? null;
    $message = $_POST['message'] ?? '';

    // Validate inputs
    if (!$chatId || !$message) {
        error_log("Chat ID or message missing: chatId={$chatId}, message={$message}");
        die("Error: Missing chat ID or message.");
    }

    // Use a prepared statement
    $stmt = $conn->prepare("INSERT INTO Message (sender_id, chat_id, body, timestamp) VALUES (?, ?, ?, NOW())");
    if (!$stmt) {
        die("Error preparing statement: " . $conn->error);
    }

    $stmt->bind_param('iis', $userId, $chatId, $message);

    if (!$stmt->execute()) {
        die("Error executing statement: " . $stmt->error);
    }

    $stmt->close();
}


// Check if an existing chat exists with a user
function checkExistingChat($conn, $userId, $targetUserId) {
    $query = "
        SELECT c.chat_id
        FROM Chat c
        JOIN Chat_Members cm1 ON cm1.chat_id = c.chat_id
        JOIN Chat_Members cm2 ON cm2.chat_id = c.chat_id
        WHERE cm1.user_id = $userId AND cm2.user_id = $targetUserId
        LIMIT 1
    ";

    $result = $conn->query($query);
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return json_encode(['exists' => true, 'chatId' => $row['chat_id']]);
    } else {
        return json_encode(['exists' => false]);
    }
}

// Create a new chat
function createNewChat($conn, $userId, $targetUserId) {
    if (!$targetUserId || !is_numeric($targetUserId)) {
        return json_encode(['error' => 'Invalid user ID']);
    }

    $stmt = $conn->prepare("INSERT INTO Chat (creator_id) VALUES (?)");
    $stmt->bind_param('i', $userId);

    if ($stmt->execute()) {
        $chatId = $conn->insert_id;

        // Add users to the chat
        $memberStmt = $conn->prepare("INSERT INTO Chat_Members (chat_id, user_id, role) VALUES (?, ?, ?)");
        $roles = [['creator', $userId], ['member', $targetUserId]];

        foreach ($roles as [$role, $user]) {
            $memberStmt->bind_param('iis', $chatId, $user, $role);
            if (!$memberStmt->execute()) {
                return json_encode(['error' => 'Failed to add members to the chat']);
            }
        }
        $memberStmt->close();

        return json_encode(['success' => true, 'chatId' => $chatId]);
    } else {
        return json_encode(['error' => 'Failed to create a new chat']);
    }
}

// Action handling based on GET request
$action = $_GET['action'] ?? null;
if ($action === 'fetchChats') {
    echo fetchChats($conn, $userId);
    exit;
} elseif ($action === 'fetchMessages') {
    $chatId = $_GET['chat_id'];
    echo fetchMessages($conn, $userId, $chatId);
    exit;
} elseif ($action === 'checkExistingChat') {
    $targetUserId = $_GET['userId'];
    echo checkExistingChat($conn, $userId, $targetUserId);
    exit;
} elseif ($action === 'createNewChat') {
    $targetUserId = $_GET['userId'];
    echo createNewChat($conn, $userId, $targetUserId);
    exit;
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Screen</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="chat-list" id="chatList">
        <button class="new-chat-btn" id="newChatButton">+ New Chat</button>
        <?= fetchChats($conn, $userId) ?>
    </div>

    <div class="chat-conversation" id="chatConversation">
        <div class="messages" id="messages">
            <!-- Messages will be dynamically loaded here -->
        </div>
        <div class="send-message">
            <form method="POST" action="chat.php">
                <input type="hidden" name="chat_id" id="currentChatId">
                <input type="text" id="messageInput" name="message" placeholder="Type a message...">
                <button type="submit" name="sendMessage" id="sendMessageButton">Send</button>
            </form>
        </div>
    </div>

    <div class="popup" id="newChatPopup">
        <input type="text" id="userSearchInput" placeholder="Search for users...">
        <ul id="userSearchResults">
            <!-- Search results will be dynamically populated -->
        </ul>
        <button class="close-btn" id="closePopupButton">Close</button>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
    const chatList = document.getElementById('chatList');
    const messages = document.getElementById('messages');
    const messageInput = document.getElementById('messageInput');
    const sendMessageButton = document.getElementById('sendMessageButton');
    const newChatButton = document.getElementById('newChatButton');
    const newChatPopup = document.getElementById('newChatPopup');
    const userSearchInput = document.getElementById('userSearchInput');
    const userSearchResults = document.getElementById('userSearchResults');
    const closePopupButton = document.getElementById('closePopupButton');
    const currentChatIdInput = document.getElementById('currentChatId');

    let currentChatId = null;
    let refreshInterval = null;

    function fetchMessages(chatId) {
        fetch(`chat.php?action=fetchMessages&chat_id=${chatId}`)
            .then(response => {
                if (!response.ok) throw new Error('Failed to fetch messages');
                return response.text();
            })
            .then(data => {
                messages.innerHTML = data;
            })
            .catch(error => {
                console.error('Error fetching messages:', error);
            });
    }

    function startMessageAutoRefresh(chatId) {
        if (refreshInterval) {
            clearInterval(refreshInterval);
        }
        refreshInterval = setInterval(() => {
            fetchMessages(chatId);
        }, 5000); // Refresh every few seconds
    }

    chatList.addEventListener('click', (e) => {
        const target = e.target.closest('.chat-entry');
        if (target) {
            currentChatId = target.dataset.chatId;
            currentChatIdInput.value = currentChatId;
            fetchMessages(currentChatId);
            startMessageAutoRefresh(currentChatId);
        }
    });

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

    closePopupButton.addEventListener('click', () => {
        newChatPopup.style.display = 'none';
        userSearchResults.innerHTML = ''; // Clear search results
        userSearchInput.value = '';       // Clear search input
    });

    newChatButton.addEventListener('click', () => {
        newChatPopup.style.display = 'block';
    });

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

    window.addEventListener('beforeunload', () => {
        if (refreshInterval) {
            clearInterval(refreshInterval);
        }
    });
});



</script>

</body>
</html>
