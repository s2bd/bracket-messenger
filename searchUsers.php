<?php
include 'db_connection.php';
session_start();

$query = $_GET['query'] ?? '';
$userId = $_SESSION['user_id'] ?? null;

// Response array for easier debugging
$response = [
    'status' => 'ok',
    'users' => [],
    'query' => $query,
    'userId' => $userId,
    'error' => null,
];

if ($query) {
    if ($userId !== null) {
        try {
            $stmt = $conn->prepare("
                SELECT user_id, display_name 
                FROM User 
                WHERE (LOWER(display_name) LIKE LOWER(?) OR LOWER(username) LIKE LOWER(?)) 
                AND user_id != ?
            ");
            $likeQuery = '%' . $query . '%';
            $stmt->bind_param('ssi', $likeQuery, $likeQuery, $userId);

            if ($stmt->execute()) {
                $result = $stmt->get_result();

                // Fetch results
                while ($row = $result->fetch_assoc()) {
                    $response['users'][] = $row;
                }
            } else {
                $response['status'] = 'error';
                $response['error'] = 'Failed to execute query: ' . $stmt->error;
            }
        } catch (Exception $e) {
            $response['status'] = 'error';
            $response['error'] = 'Exception: ' . $e->getMessage();
        } finally {
            if (isset($stmt)) {
                $stmt->close();
            }
        }
    } else {
        $response['status'] = 'error';
        $response['error'] = 'User ID not found in session';
    }
} else {
    $response['status'] = 'error';
    $response['error'] = 'No search query provided';
}

// Close the database connection
$conn->close();

// Send response as JSON
header('Content-Type: application/json');
echo json_encode($response);
?>
