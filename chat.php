<?php
include 'db_connection.php'; // reference to the SQL database connection
session_start(); // starting a session for the current user
$userId = $_SESSION['user_id']; // using the user ID obtained from the login

function fetchChats($conn, $userId) {
    // Query to fetch all chat members' display names excluding the current user
    $query = "
        SELECT 
            c.chat_id, 
            GROUP_CONCAT(u.display_name ORDER BY u.display_name SEPARATOR ', ') AS members_names, 
            m.body AS latest_message, 
            m.timestamp AS latest_timestamp
        FROM Chat c
        JOIN Chat_Members cm ON cm.chat_id = c.chat_id
        JOIN User u ON u.user_id = cm.user_id
        LEFT JOIN Message m ON m.chat_id = c.chat_id
        WHERE cm.user_id != $userId
        AND cm.chat_id IN (SELECT chat_id FROM Chat_Members WHERE user_id = $userId)
        AND m.timestamp = (
            SELECT MAX(timestamp) 
            FROM Message 
            WHERE chat_id = c.chat_id
        )
        GROUP BY c.chat_id, m.body, m.timestamp
        ORDER BY latest_timestamp DESC;
    ";

    $result = $conn->query($query);
    $chatsHTML = ''; // Initialize HTML content for chat list

    date_default_timezone_set('UTC'); // Ensure UTC timezone for timestamp formatting
    
    while ($row = $result->fetch_assoc()) {
        $timestamp = new DateTime($row['latest_timestamp']); // Format timestamp
        $latestDate = $timestamp->format('d-m-Y'); // Format date
        $latestTime = $timestamp->format('H:i:s'); // Format time
        
        // If only one receiver, display their name, otherwise show group chat members
        $membersDisplayNames = $row['members_names']; // Concatenated member names

        $chatsHTML .= "<div class='chat-entry' data-chat-id='{$row['chat_id']}'>
                        <strong>{$membersDisplayNames}</strong><br>
                        <span>{$row['latest_message']}</span><br>
                        <small class='timestamp-time'>{$latestDate} {$latestTime}</small>
                        </div>"; // Template HTML block for each chat
    }

    return $chatsHTML; // Return the HTML content for all chats
}


function fetchMessages($conn, $userId, $chatId) {
    $query = "
        SELECT 
            u.display_name,
            m.message_id, 
            m.body, 
            m.sender_id, 
            m.timestamp
        FROM Message m
        JOIN User u ON u.user_id = m.sender_id
        JOIN Chat_Members cm ON cm.chat_id = m.chat_id
        WHERE cm.user_id = $userId AND m.chat_id = $chatId
        ORDER BY m.timestamp ASC
    "; // using SQL queries for retrieving messages belonging to a chat

    $result = $conn->query($query); // sending them over via lambda
    $messagesHTML = ''; // preparing blank HTML
    $lastDate = null;
    
    while ($row = $result->fetch_assoc()) {
        $class = $row['sender_id'] == $userId ? 'sender' : 'receiver';

        $messageTimestamp = new DateTime($row['timestamp']); // Parse the message timestamp
        $messageDate = $messageTimestamp->format('d-m-Y'); // Date for date markers
        $messageTime = $messageTimestamp->format('H:i:s'); // Time for message display

        // Add a date marker when the date changes or for the first message
        if ($lastDate !== $messageDate) {
            $messagesHTML .= "<div class='date-marker'><strong>{$messageDate}</strong></div>";
            $lastDate = $messageDate; // Update the last date to the current one
        }

        // Add the actual message
        $messagesHTML .= "
        <div class='message $class' data-message-id='{$row['message_id']}'>
            <strong>{$row['display_name']}</strong><br>
            <span>{$row['body']}</span><br>
            <small class='message-time'>{$messageTime}</small>";

        if ($row['sender_id'] == $userId) { // Add delete icon for sender messages
            $messagesHTML .= "
                <form method='POST' action='chat.php' style='display:inline;'>
                    <input type='hidden' name='message_id' value='{$row['message_id']}'>
                    <input type='hidden' name='deleteMessage' value='true'>
                    <button type='submit' class='delete-icon' aria-label='Delete message'>
                        <i class='fas fa-trash'></i>
                    </button>
                </form>";
        }

        $messagesHTML .= "</div>";
    }

    return $messagesHTML;
}

function fetchChatInfo($conn, $chatId, $userId) {
    $query = "
        SELECT u.display_name
        FROM Chat_Members cm
        JOIN User u ON u.user_id = cm.user_id
        WHERE cm.chat_id = $chatId
    ";

    $result = $conn->query($query);
    $members = [];
    $currentUserName = '';

    while ($row = $result->fetch_assoc()) {
        if ($row['display_name'] === $_SESSION['user_name']) { // Assuming you store the user's name in session
            $currentUserName = $row['display_name'];
        } else {
            $members[] = $row['display_name'];
        }
    }

    return json_encode([
        'membersNames' => implode(', ', $members),
        'currentUserName' => $currentUserName
    ]);
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

// Delete message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deleteMessage'])) {
    $messageId = $_POST['message_id'] ?? null;

    $stmt = $conn->prepare("DELETE FROM Message WHERE message_id = ? AND sender_id = ?");
    if (!$stmt) {
        die("Error preparing statement: " . $conn->error);
    }

    $stmt->bind_param('ii', $messageId, $userId); // Ensure the message belongs to the current user
    if (!$stmt->execute()) {
        die("Error executing statement: " . $stmt->error);
    }

    $stmt->close();
}


function deleteMessage($conn, $messageId){
    
}

// Check if an existing chat exists with a user
function getOrCreateChat($conn, $userId, $targetUserId) {
    $query = "
        SELECT c.chat_id
        FROM Chat c
        JOIN Chat_Members cm1 ON cm1.chat_id = c.chat_id
        JOIN Chat_Members cm2 ON cm2.chat_id = c.chat_id
        WHERE cm1.user_id = ? AND cm2.user_id = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $userId, $targetUserId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        return json_encode(['exists' => true, 'chatId' => $row['chat_id']]);
    }

    // No existing chat; create a new one
    $stmt = $conn->prepare("INSERT INTO Chat (creator_id) VALUES (?)");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $chatId = $conn->insert_id;

    // Add users to the chat
    $memberStmt = $conn->prepare("INSERT INTO Chat_Members (chat_id, user_id, role) VALUES (?, ?, ?)");
    $roles = [['creator', $userId], ['member', $targetUserId]];

    foreach ($roles as [$role, $user]) {
        $memberStmt->bind_param('iis', $chatId, $user, $role);
        $memberStmt->execute();
    }
    $memberStmt->close();

    return json_encode(['success' => true, 'chatId' => $chatId]);
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
} elseif ($action === 'getOrCreateChat') {
    $targetUserId = $_GET['userId'];
    echo getOrCreateChat($conn, $userId, $targetUserId);
    exit;
} elseif ($action === 'fetchChatInfo' && isset($_GET['chat_id'])) {
    $chatId = $_GET['chat_id'];
    echo fetchChatInfo($conn, $chatId);
    exit;
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bracket - Chat</title>
    <link rel="stylesheet" href="css/chat.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="chat-list" id="chatList">
        <button class="new-chat-btn" id="newChatButton">+ New Chat</button>
        <?= fetchChats($conn, $userId) ?>
    </div>

    <div class="chat-conversation" id="chatConversation">
        <div class="chat-info-overlay" id="chatInfoOverlay">
            <div class="chat-info-left">
                <span>Chatting with: <span id="chatMembersDisplay"></span></span>
            </div>
            <div class="chat-info-right">
                <span id="chatType"></span>
            </div>
        </div>
        
        <div class="messages" id="messages">
            <div id="placeholderMessage" class="placeholder-message">Select a conversation to chat</div>
            <!-- Messages will be dynamically loaded here -->
        </div>
        <div class="send-message">
            <form method="POST" action="chat.php" style="flex: 1; display: flex; gap: 0.5rem;">
                <input type="hidden" name="chat_id" id="currentChatId">
                <input type="text" id="messageInput" name="message" placeholder="Type a message..." required>
                <button type="submit" name="sendMessage" id="sendMessageButton">Send</button>
                <button type="button" name="attachment-button" id="attachmentButton" aria-label="Attach a file">
                    <i class="fas fa-paperclip"></i>
                </button>
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

    <script src="js/chat.js"></script>

</body>
</html>
