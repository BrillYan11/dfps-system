<?php
session_start();
include '../../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['unread_count' => 0]);
    exit;
}

$user_id = $_SESSION['user_id'];

// Count unread messages in conversations where user is an ACTIVE participant
// We join with conversation_participants for THIS user to ensure they still "see" it.
$sql = "
    SELECT COUNT(m.id) as unread_count
    FROM messages m
    JOIN conversation_participants cp ON m.conversation_id = cp.conversation_id
    WHERE cp.user_id = ? 
    AND m.sender_id != ? 
    AND m.read_at IS NULL
    AND m.is_deleted = 0
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

$count = intval($row['unread_count'] ?? 0);

echo json_encode(['unread_count' => $count]);
?>
