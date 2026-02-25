<?php
session_start();
include '../../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$conv_id = filter_input(INPUT_GET, 'conv_id', FILTER_VALIDATE_INT);
$last_id = filter_input(INPUT_GET, 'last_id', FILTER_VALIDATE_INT) ?: 0;
$update_read = filter_input(INPUT_GET, 'update_read', FILTER_VALIDATE_BOOLEAN);

if (!$conv_id) {
    echo json_encode(['error' => 'Invalid conversation ID']);
    exit;
}

// Verify user is part of the conversation
$verify_stmt = $conn->prepare("SELECT 1 FROM conversation_participants WHERE conversation_id = ? AND user_id = ?");
$verify_stmt->bind_param("ii", $conv_id, $user_id);
$verify_stmt->execute();
if ($verify_stmt->get_result()->num_rows === 0) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Fetch new messages
$msg_stmt = $conn->prepare("SELECT id, sender_id, body, created_at, is_deleted FROM messages WHERE conversation_id = ? AND id > ? ORDER BY created_at ASC");
$msg_stmt->bind_param("ii", $conv_id, $last_id);
$msg_stmt->execute();
$messages = $msg_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$msg_stmt->close();

// Fetch ALL deleted message IDs for this conversation to sync UI
$del_stmt = $conn->prepare("SELECT id FROM messages WHERE conversation_id = ? AND is_deleted = 1");
$del_stmt->bind_param("i", $conv_id);
$del_stmt->execute();
$deleted_ids = $del_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$del_stmt->close();

$deleted_ids_flat = array_column($deleted_ids, 'id');

if ($update_read && !empty($messages)) {
    // Mark these specific messages as read if we are the receiver
    $update_stmt = $conn->prepare("UPDATE messages SET read_at = CURRENT_TIMESTAMP WHERE conversation_id = ? AND sender_id != ? AND read_at IS NULL AND id > ?");
    $update_stmt->bind_param("iii", $conv_id, $user_id, $last_id);
    $update_stmt->execute();
}

echo json_encode([
    'messages' => $messages,
    'deleted_ids' => $deleted_ids_flat
]);
?>
