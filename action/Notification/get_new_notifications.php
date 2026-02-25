<?php
session_start();
include '../../includes/db.php';
include '../../includes/NotificationModel.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$last_id = filter_input(INPUT_GET, 'last_id', FILTER_VALIDATE_INT) ?: 0;

// Fetch new notifications
$sql = "SELECT id, type, title, body, link, is_read, created_at FROM notifications WHERE user_id = ? AND id > ? ORDER BY created_at ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $user_id, $last_id);
$stmt->execute();
$result = $stmt->get_result();
$notifications = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode(['notifications' => $notifications]);
?>
