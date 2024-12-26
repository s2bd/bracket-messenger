<?php
include 'db_connection.php'; // reference to the SQL database connection
session_start(); // starting a session for the current user
$userId = $_SESSION['user_id']; // using the user ID obtained from the login

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
    "; // using SQL statements to retrieve the chats that the user is part of

    $result = $conn->query($query); // using a lambda function to send the query via the PHP-SQL connection
    $chatsHTML = ''; // preparing a blank HTML code for adding all dynamic content later on

    date_default_timezone_set('UTC'); // setting the timezone of the server as UTC
    
    while ($row = $result->fetch_assoc()) {
        $timestamp = new DateTime($row['latest_timestamp']); // formatting the timestamp to be more user-friendly
        $latestDate = $timestamp->format('d-m-Y'); // date format for the conversation flow
        $latestTime = $timestamp->format('H:i:s'); // time format for the individual messages
        $chatsHTML .= "<div class='chat-entry' data-chat-id='{$row['chat_id']}'>
                        <strong>{$row['creator_name']}</strong><br>
                        <span>{$row['latest_message']}</span><br>
                        <small class='timestamp-time'>{$latestDate} {$latestTime}</small>
                        </div>"; // template HTML block for each chat
    }
    return $chatsHTML; // add all the chats to the collection of HTML code and return
}

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
