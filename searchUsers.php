<?php
include 'db_connection.php';
session_start();

$query = $_GET['query'] ?? '';
$userId = $_SESSION['user_id']; // Assuming this session variable exists for the logged-in user

if ($query) {
    // Fetch users matching the search query, excluding the current user
    $stmt = $conn->prepare("SELECT user_id, display_name FROM User WHERE display_name LIKE ? AND user_id != ?");
    $likeQuery = '%' . $query . '%';
    $stmt->bind_param('si', $likeQuery, $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $usersHTML = '';
    while ($row = $result->fetch_assoc()) {
        $usersHTML .= "<li class='user-result' data-user-id='{$row['user_id']}'>
                           {$row['display_name']}
                       </li>";
    }

    echo $usersHTML ?: '<li>No users found</li>';
    $stmt->close();
} else {
    echo '<li>Please enter a search term</li>';
}

$conn->close();
?>
