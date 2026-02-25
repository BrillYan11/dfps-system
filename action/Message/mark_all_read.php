<?php
session_start();
include '../../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Mark all messages as read across all conversations for this user
// Messages where I am the recipient (sender_id != me) and I am a participant
$sql = "
    UPDATE messages m
    JOIN conversation_participants cp ON m.conversation_id = cp.conversation_id
    SET m.read_at = CURRENT_TIMESTAMP
    WHERE cp.user_id = ?
    AND m.sender_id != ?
    AND m.read_at IS NULL
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$stmt->close();

$redirect_path = ($role === 'FARMER') ? '../../farmer/message.php' : '../../buyer/message.php';
header("Location: " . $redirect_path);
exit;
?>
