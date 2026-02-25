<?php
session_start();
include '../includes/db.php';
include '../includes/NotificationModel.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'DA') {
    header("Location: ../login.php");
    exit;
}

$da_id = $_SESSION['user_id'];
$notifications = NotificationModel::getNotificationsForUser($conn, $da_id);

function get_notification_icon($type) {
    switch ($type) {
        case 'SYSTEM_ALERT': return 'bi-exclamation-triangle-fill';
        case 'ANNOUNCEMENT': return 'bi-megaphone-fill';
        default: return 'bi-info-circle-fill';
    }
}

include '../header/headerda.php';
?>

<link rel="stylesheet" href="../css/notification.css?v=<?php echo time(); ?>">

<main class="container-fluid px-4 my-3">
  <div class="row g-3">

    <!-- Sidebar -->
    <aside class="col-12 col-md-3 col-lg-2">
      <div class="panel h-100 p-3">
        <nav class="nav flex-column">
          <a class="nav-link" href="index.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
          <a class="nav-link" href="announcements.php"><i class="bi bi-megaphone me-2"></i>Announcements</a>
        </nav>
      </div>
    </aside>

    <!-- Main Content -->
    <section class="col-12 col-md-9 col-lg-10">
      <div class="panel p-3">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <h4 class="mb-0">System Alerts & Notifications</h4>
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
                <p class="mt-2">You have no system notifications.</p>
            </div>
          <?php else: ?>
            <?php foreach ($notifications as $notif): 
                // Skip rendering NEW_MESSAGE notifications
                if ($notif['type'] === 'NEW_MESSAGE') {
                    continue;
                }
                $view_link = !empty($notif['link']) ? '../action/Notification/mark_read.php?id=' . $notif['id'] . '&redirect=' . urlencode($notif['link']) : '#';
            ?>
                <div class="notification-item <?php echo !$notif['is_read'] ? 'notification-unread' : ''; ?>" data-id="<?php echo $notif['id']; ?>">
                    <div class="notification-icon">
                        <i class="bi <?php echo get_notification_icon($notif['type']); ?>"></i>
                    </div>
                    <div class="notification-content">
                        <h6 class="mb-0"><?php echo htmlspecialchars($notif['title']); ?></h6>
                        <p class="mb-0"><?php echo htmlspecialchars($notif['body']); ?></p>
                        <span class="time"><?php echo date('M j, g:i a', strtotime($notif['created_at'])); ?></span>
                    </div>
                    <div class="notification-actions">
                        <a href="<?php echo $view_link; ?>" class="btn btn-sm btn-primary <?php echo empty($notif['link']) ? 'disabled' : ''; ?>">View</a>
                        <a href="../action/Notification/dismiss.php?id=<?php echo $notif['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Dismiss"><i class="bi bi-x"></i></a>
                    </div>
                </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

      </div>
    </section>

  </div>
</main>

<?php include '../footer/footerda.php'; ?>
