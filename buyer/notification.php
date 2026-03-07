<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../includes/db.php';
include_once '../includes/NotificationModel.php';

// Authentication and Authorization Check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || strtoupper($_SESSION['role']) !== 'BUYER') {
    header("Location: ../login.php");
    exit;
}

$buyer_id = $_SESSION['user_id'];
$notifications = NotificationModel::getNotificationsForUser($conn, $buyer_id);

function get_notification_icon($type) {
    switch ($type) {
        case 'NEW_MESSAGE': return 'bi-chat-dots-fill';
        case 'INTEREST_ACCEPTED': return 'bi-check-circle-fill';
        case 'POST_UPDATE': return 'bi-arrow-up-circle-fill';
        case 'ANNOUNCEMENT': return 'bi-megaphone-fill';
        case 'SYSTEM_ALERT': return 'bi-exclamation-triangle-fill';
        default: return 'bi-info-circle-fill';
    }
}

include '../includes/universal_header.php';
?>

<link rel="stylesheet" href="../css/notification.css?v=<?php echo time(); ?>">

<main class="container-fluid px-4 my-3">
  <div class="row g-3">

    <!-- Sidebar -->
    <aside class="col-12 col-md-3 col-lg-2">
      <div class="panel h-100 p-3">
        <nav class="nav flex-column">
          <a class="nav-link" href="index.php"><i class="bi bi-shop me-2"></i>Marketplace</a>
          <a class="nav-link" href="message.php"><i class="bi bi-chat-dots me-2"></i>Messages</a>
        </nav>
      </div>
    </aside>

    <!-- Main Content -->
    <section class="col-12 col-md-9 col-lg-10">
      <div class="panel p-3">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <h4 class="mb-0">Notifications</h4>
          <div class="dropdown">
              <button class="btn btn-sm btn-light rounded-circle" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></button>
              <ul class="dropdown-menu dropdown-menu-end">
                  <li><a class="dropdown-item" href="../action/Notification/mark_all_read.php"><i class="bi bi-check-all me-2"></i>Mark all as read</a></li>
                  <li><a class="dropdown-item text-danger" href="../action/Notification/clear_all.php" onclick="return confirm('Clear all notifications?')"><i class="bi bi-trash3-fill me-2"></i>Clear all</a></li>
              </ul>
          </div>
        </div>

        <div class="notification-list">
          <?php if (empty($notifications)): ?>
            <div class="p-4 text-center text-muted">
                <i class="bi bi-bell-slash" style="font-size: 2rem; opacity: 0.2;"></i>
                <p class="mt-2">You have no notifications.</p>
            </div>
          <?php else: ?>
            <?php foreach ($notifications as $notif): 
                // Skip rendering NEW_MESSAGE notifications
                if ($notif['type'] === 'NEW_MESSAGE') {
                    continue;
                }
                $has_link = !empty($notif['link']);
                $view_link = $has_link ? '../action/Notification/mark_read.php?id=' . $notif['id'] . '&redirect=' . urlencode($notif['link']) : 'javascript:void(0)';
            ?>
                <div class="notification-item <?php echo !$notif['is_read'] ? 'notification-unread' : ''; ?> clickable" 
                     data-id="<?php echo $notif['id']; ?>"
                     data-title="<?php echo htmlspecialchars($notif['title']); ?>"
                     data-body="<?php echo htmlspecialchars($notif['body']); ?>"
                     data-link="<?php echo $has_link ? $view_link : ''; ?>">
                    <div class="notification-icon">
                        <i class="bi <?php echo get_notification_icon($notif['type']); ?>"></i>
                    </div>
                    <div class="notification-content">
                        <h6 class="mb-0"><?php echo htmlspecialchars($notif['title']); ?></h6>
                        <p class="mb-0 text-truncate" style="max-width: 500px;"><?php echo htmlspecialchars($notif['body']); ?></p>
                        <span class="time"><?php echo date('M j, g:i a', strtotime($notif['created_at'])); ?></span>
                    </div>
                    <div class="notification-actions">
                        <?php if ($has_link): ?>
                            <a href="<?php echo $view_link; ?>" class="btn btn-sm btn-primary">View</a>
                        <?php endif; ?>
                        <a href="../action/Notification/dismiss.php?id=<?php echo $notif['id']; ?>" 
                           class="btn btn-sm btn-outline-secondary" 
                           title="Dismiss"
                           onclick="event.stopPropagation();">
                           <i class="bi bi-x"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

      </div>
    </section>

  </div>
</main>

<?php include '../includes/universal_footer.php'; ?>
