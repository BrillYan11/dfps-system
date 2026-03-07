<?php
session_start();
include '../includes/db.php';
include '../includes/NotificationModel.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'DA') {
    header("Location: ../login.php");
    exit;
}

$da_id = $_SESSION['user_id'];
$selected_conv_id = filter_input(INPUT_GET, 'conv_id', FILTER_VALIDATE_INT);
$receiver_id = filter_input(INPUT_GET, 'receiver_id', FILTER_VALIDATE_INT);
$view = filter_input(INPUT_GET, 'view', FILTER_UNSAFE_RAW) ?: 'active'; // 'active' or 'archived'

// --- Mark selected conversation as read ---
if ($selected_conv_id) {
    $read_stmt = $conn->prepare("UPDATE messages SET read_at = CURRENT_TIMESTAMP WHERE conversation_id = ? AND sender_id != ? AND read_at IS NULL");
    $read_stmt->bind_param("ii", $selected_conv_id, $da_id);
    $read_stmt->execute();
    $read_stmt->close();
}

// --- Handle receiver_id to find or create conversation ---
if ($receiver_id && !$selected_conv_id) {
    $conv_lookup_stmt = $conn->prepare("
        SELECT cp1.conversation_id
        FROM conversation_participants AS cp1
        JOIN conversation_participants AS cp2 ON cp1.conversation_id = cp2.conversation_id
        WHERE cp1.user_id = ? AND cp2.user_id = ?
    ");
    $conv_lookup_stmt->bind_param("ii", $da_id, $receiver_id);
    $conv_lookup_stmt->execute();
    $conv_lookup_result = $conv_lookup_stmt->get_result();
    if ($conv_row = $conv_lookup_result->fetch_assoc()) {
        $selected_conv_id = $conv_row['conversation_id'];
        $conn->query("UPDATE conversation_participants SET is_archived = 0 WHERE conversation_id = $selected_conv_id AND user_id = $da_id");
    } else {
        $conn->begin_transaction();
        try {
            $conn->query("INSERT INTO conversations () VALUES ()");
            $selected_conv_id = $conn->insert_id;
            $part_stmt = $conn->prepare("INSERT INTO conversation_participants (conversation_id, user_id) VALUES (?, ?), (?, ?)");
            $part_stmt->bind_param("iiii", $selected_conv_id, $da_id, $selected_conv_id, $receiver_id);
            $part_stmt->execute();
            $part_stmt->close();
            $conn->commit();
        } catch (Exception $e) { $conn->rollback(); $selected_conv_id = null; }
    }
    $conv_lookup_stmt->close();
}

// --- Handle Sending a New Message ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty(trim($_POST['message_body'])) && $selected_conv_id) {
    $message_body = trim($_POST['message_body']);
    $conn->begin_transaction();
    try {
        $verify_stmt = $conn->prepare("SELECT user_id FROM conversation_participants WHERE conversation_id = ? AND user_id != ?");
        $verify_stmt->bind_param("ii", $selected_conv_id, $da_id);
        $verify_stmt->execute();
        $result = $verify_stmt->get_result();
        if ($result->num_rows > 0) {
            $actual_receiver_id = $result->fetch_assoc()['user_id'];
            $msg_stmt = $conn->prepare("INSERT INTO messages (conversation_id, sender_id, body) VALUES (?, ?, ?)");
            $msg_stmt->bind_param("iis", $selected_conv_id, $da_id, $message_body);
            $msg_stmt->execute();
            $msg_stmt->close();

            // Notify Receiver
            $notif_title = "New Message from DA Portal";
            $notif_body = "Sent you a message.";
            $notif_link = ($_SESSION['role'] === 'FARMER' ? "../farmer/message.php" : "../buyer/message.php") . "?conv_id=" . $selected_conv_id;
            
            // We need to know the receiver's role for the correct link
            $r_stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
            $r_stmt->bind_param("i", $actual_receiver_id);
            $r_stmt->execute();
            $r_role = $r_stmt->get_result()->fetch_assoc()['role'];
            $r_stmt->close();
            
            $notif_link = ($r_role === 'FARMER' ? "../farmer/message.php" : "../buyer/message.php") . "?conv_id=" . $selected_conv_id;
            NotificationModel::createNotification($conn, $actual_receiver_id, 'NEW_MESSAGE', $notif_title, $notif_body, $notif_link);

            $conn->query("UPDATE conversation_participants SET is_archived = 0 WHERE conversation_id = $selected_conv_id");
            
            $conn->commit();
            header("Location: message.php?conv_id=$selected_conv_id&view=$view");
            exit;
        }
    } catch (Exception $e) { $conn->rollback(); }
}

// --- Fetch Conversations based on view ---
$conversations = [];
$is_archived_filter = ($view === 'archived') ? 1 : 0;
$conv_query = "
    SELECT
        c.id as conversation_id,
        other_user.id as participant_id,
        other_user.first_name,
        other_user.last_name,
        other_user.role as participant_role,
        other_user.profile_picture as participant_profile_picture,
        cp_me.is_archived,
        (SELECT body FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message,
        (SELECT created_at FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message_time,
        (SELECT is_deleted FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message_deleted,
        (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND sender_id != ? AND read_at IS NULL AND is_deleted = 0) as unread_count
    FROM conversations c
    JOIN conversation_participants cp_me ON c.id = cp_me.conversation_id AND cp_me.user_id = ?
    JOIN conversation_participants cp_other ON c.id = cp_other.conversation_id AND cp_other.user_id != ?
    JOIN users other_user ON cp_other.user_id = other_user.id
    WHERE cp_me.is_archived = ?
    ORDER BY last_message_time DESC
";
$conv_stmt = $conn->prepare($conv_query);
$conv_stmt->bind_param("iiii", $da_id, $da_id, $da_id, $is_archived_filter);
$conv_stmt->execute();
$conv_result = $conv_stmt->get_result();
while ($row = $conv_result->fetch_assoc()) { $conversations[] = $row; }
$conv_stmt->close();

// --- Fetch selected message details ---
$messages = [];
$selected_participant = null;
if ($selected_conv_id) {
    $p_stmt = $conn->prepare("
        SELECT other_user.id as participant_id, other_user.first_name, other_user.last_name, other_user.role as participant_role, other_user.profile_picture as participant_profile_picture, cp_me.is_archived
        FROM users other_user
        JOIN conversation_participants cp_other ON other_user.id = cp_other.user_id AND cp_other.conversation_id = ?
        JOIN conversation_participants cp_me ON cp_me.conversation_id = cp_other.conversation_id AND cp_me.user_id = ?
        WHERE other_user.id != ?
    ");
    $p_stmt->bind_param("iii", $selected_conv_id, $da_id, $da_id);
    $p_stmt->execute();
    $selected_participant = $p_stmt->get_result()->fetch_assoc();
    $p_stmt->close();

    if ($selected_participant) {
        $msg_stmt = $conn->prepare("SELECT id, sender_id, body, created_at, is_deleted FROM messages WHERE conversation_id = ? ORDER BY created_at ASC");
        $msg_stmt->bind_param("i", $selected_conv_id);
        $msg_stmt->execute();
        $messages = $msg_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $msg_stmt->close();
    }
}

$body_class = 'messaging-page';
include '../includes/universal_header.php';
?>
<link rel="stylesheet" href="../css/message.css?v=<?php echo time(); ?>">

<div class="messaging-wrapper">
  <div class="messaging-layout">
    
    <!-- Left Pane -->
    <aside class="conversations-list-panel">
        <div class="conversations-header d-flex align-items-center justify-content-between">
            <h4>DA Messages</h4>
            <div class="dropdown">
                <button class="btn btn-sm btn-light rounded-circle" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="message.php?view=active"><i class="bi bi-chat-fill me-2"></i>Active</a></li>
                    <li><a class="dropdown-item" href="message.php?view=archived"><i class="bi bi-archive-fill me-2"></i>Archived</a></li>
                </ul>
            </div>
        </div>
        <div class="conversations-scroll">
            <?php if (empty($conversations)): ?>
                <div class="text-center text-muted p-4"><small>No conversations found.</small></div>
            <?php else: ?>
                <?php foreach ($conversations as $conv):
                    $isActive = ($selected_conv_id == $conv['conversation_id']);
                    $disp_msg = $conv['last_message_deleted'] ? 'Message unsent' : ($conv['last_message'] ?: 'No messages yet');
                ?>
                    <a href="message.php?conv_id=<?php echo $conv['conversation_id']; ?>&view=<?php echo $view; ?>" class="conv-item <?php echo $isActive ? 'active' : ''; ?>">
                        <div class="conv-avatar overflow-hidden">
                            <?php if (!empty($conv['participant_profile_picture'])): ?>
                                <img src="../<?php echo $conv['participant_profile_picture']; ?>" class="w-100 h-100" style="object-fit: cover;">
                            <?php else: ?>
                                <i class="bi bi-person-circle" style="font-size: 1.5rem;"></i>
                            <?php endif; ?>
                        </div>
                        <div class="conv-info">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="conv-name text-truncate"><?php echo htmlspecialchars($conv['first_name'] . ' ' . $conv['last_name']); ?></div>
                                <?php if ($conv['unread_count'] > 0): ?>
                                    <span class="badge rounded-pill bg-danger" style="font-size: 0.65rem;"><?php echo $conv['unread_count']; ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="conv-last-msg <?php echo $conv['unread_count'] > 0 ? 'fw-bold text-dark' : ''; ?>">
                                <small class="text-primary fw-bold"><?php echo $conv['participant_role']; ?></small>: 
                                <?php echo htmlspecialchars($disp_msg); ?>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>

    <!-- Center Pane -->
    <section class="message-viewer-panel">
        <?php if (!$selected_conv_id): ?>
            <div class="d-flex h-100 flex-column justify-content-center align-items-center text-muted p-5 text-center">
                <i class="bi bi-chat-dots" style="font-size: 5rem; opacity: 0.15; color: var(--primary-color);"></i>
                <h5 class="mt-3">Select a conversation to start messaging users</h5>
                <p>You can start a chat by visiting the <a href="users.php">User Management</a> section.</p>
            </div>
        <?php else: ?>
            <div class="chat-header justify-content-between">
                <div class="d-flex align-items-center">
                    <div class="conv-avatar overflow-hidden" style="width: 40px; height: 40px; margin-right: 12px;">
                        <?php if (!empty($selected_participant['participant_profile_picture'])): ?>
                            <img src="../<?php echo $selected_participant['participant_profile_picture']; ?>" class="w-100 h-100" style="object-fit: cover;">
                        <?php else: ?>
                            <i class="bi bi-person-circle" style="font-size: 1.5rem;"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h6 class="mb-0"><?php echo htmlspecialchars($selected_participant['first_name'] . ' ' . $selected_participant['last_name']); ?></h6>
                        <small class="text-primary fw-bold"><?php echo $selected_participant['participant_role']; ?></small>
                    </div>
                </div>
            </div>

            <div class="message-container" id="message-container">
                <?php foreach ($messages as $message):
                    $isSent = ($message['sender_id'] == $da_id);
                ?>
                    <div class="message-row <?php echo $isSent ? 'sent' : 'received'; ?>">
                        <?php if (!$isSent): ?>
                            <div class="message-avatar overflow-hidden">
                                <?php if (!empty($selected_participant['participant_profile_picture'])): ?>
                                    <img src="../<?php echo $selected_participant['participant_profile_picture']; ?>" class="w-100 h-100" style="object-fit: cover;">
                                <?php else: ?>
                                    <i class="bi bi-person-circle" style="font-size: 1.2rem;"></i>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <div class="message <?php echo $isSent ? 'sent' : 'received'; ?>">
                            <div class="message-body <?php echo $message['is_deleted'] ? 'message-deleted' : ''; ?>">
                                <?php echo $message['is_deleted'] ? ($isSent ? "You unsent a message" : "Message unsent") : nl2br(htmlspecialchars($message['body'])); ?>
                            </div>
                            <div class="message-time"><?php echo date('g:i a', strtotime($message['created_at'])); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="chat-footer">
                <form method="POST" action="message.php?conv_id=<?php echo $selected_conv_id; ?>&view=<?php echo $view; ?>">
                    <div class="message-input-wrapper">
                        <textarea name="message_body" class="message-input" rows="1" placeholder="Type a message to the <?php echo strtolower($selected_participant['participant_role']); ?>..." required id="message-input"></textarea>
                        <button class="send-btn" type="submit"><i class="bi bi-send-fill"></i></button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </section>
  </div>
</div>

<script>
    var messageContainer = document.getElementById('message-container');
    if(messageContainer) { messageContainer.scrollTop = messageContainer.scrollHeight; }

    var tx = document.getElementById('message-input');
    if(tx) {
        tx.addEventListener("input", function() { this.style.height = "auto"; this.style.height = (this.scrollHeight) + "px"; }, false);
        tx.addEventListener('keydown', function(e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); this.form.submit(); } });
    }
</script>
<?php include '../includes/universal_footer.php'; ?>
