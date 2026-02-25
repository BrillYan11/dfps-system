<!DOCTYPE html>
<html lang="en" class="<?php echo $body_class ?? ''; ?>">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Buyer Dashboard</title>

  <!-- Bootstrap CSS (local) -->
  <link rel="stylesheet" href="../bootstrap/css/bootstrap.css">

  <!-- Your CSS -->
  <link rel="stylesheet" href="../css/style.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="../css/message.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="../css/notification.css?v=<?php echo time(); ?>">

  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

  <!-- Bootstrap JS (local) -->
  <script src="../bootstrap/js/bootstrap.bundle.min.js" defer></script>
  <!-- Real-time updates -->
  <script src="../js/realtime.js" defer></script>
  <style>
    /* Add the DA-specific styling if needed, or make it generic for Farmer/Buyer */
    :root {
      --primary-color: #007bff; /* Buyer specific color */
    }
    .app-header { background-color: var(--primary-color) !important; }
    .btn-primary { background-color: var(--primary-color); border-color: var(--primary-color); }
    .btn-primary:hover { background-color: #0056b3; border-color: #0056b3; }
    .badge.bg-primary { background-color: var(--primary-color) !important; }
    .text-primary { color: var(--primary-color) !important; }


    /* Sidebar Styles (Moved from DA header, made generic) */
    .app-sidebar {
      position: fixed;
      top: 0;
      left: -280px;
      width: 280px;
      height: 100%;
      background: #fff;
      z-index: 1050;
      transition: all 0.3s ease;
      box-shadow: 4px 0 15px rgba(0,0,0,0.1);
      display: flex;
      flex-direction: column;
    }
    .app-sidebar.active {
      left: 0 !important;
    }
    .sidebar-header {
      padding: 20px;
      background: var(--primary-color); /* Use generic primary color */
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .sidebar-content {
      padding: 20px 0;
      flex-grow: 1;
      overflow-y: auto;
    }
    .sidebar-link {
      display: flex;
      align-items: center;
      padding: 12px 25px;
      color: #333;
      text-decoration: none !important;
      transition: all 0.2s;
      gap: 15px;
      font-weight: 500;
    }
    .sidebar-link:hover {
      background: #f0f7f0; /* Light background for hover */
      color: var(--primary-color);
    }
    .sidebar-link i {
      font-size: 1.2rem;
    }
    .sidebar-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.5);
      z-index: 1040;
      display: none;
    }
    .sidebar-overlay.active {
      display: block;
    }
  </style>
</head>
<body class="<?php echo $body_class ?? ''; ?>">

<?php
  // Fetch counts
  $unread_notif_count = 0;
  $unread_msg_count = 0;
  if (isset($_SESSION['user_id'])) {
      include_once '../includes/NotificationModel.php';
      $unread_notif_count = NotificationModel::countUnread($conn, $_SESSION['user_id']);
      
      // Fetch unread messages
      $msg_count_sql = "SELECT COUNT(id) as c FROM messages m JOIN conversation_participants cp ON m.conversation_id = cp.conversation_id WHERE cp.user_id = ? AND m.sender_id != ? AND m.read_at IS NULL AND m.is_deleted = 0";
      $msg_count_stmt = $conn->prepare($msg_count_sql);
      $msg_count_stmt->bind_param("ii", $_SESSION['user_id'], $_SESSION['user_id']);
      $msg_count_stmt->execute();
      $unread_msg_count = $msg_count_stmt->get_result()->fetch_assoc()['c'] ?? 0;
      $msg_count_stmt->close();
  }
?>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- App Sidebar (Generic) -->
<aside class="app-sidebar" id="appSidebar">
  <div class="sidebar-header">
    <h5 class="mb-0 fw-bold">Buyer Menu</h5>
    <button type="button" class="btn-close btn-close-white" id="closeSidebar"></button>
  </div>
  <div class="sidebar-content">
    <a href="index.php" class="sidebar-link">
      <i class="bi bi-shop"></i>
      <span>Marketplace</span>
    </a>
    <a href="message.php" class="sidebar-link">
      <i class="bi bi-chat-dots"></i>
      <span>Messages</span>
    </a>
    <a href="notification.php" class="sidebar-link">
      <i class="bi bi-bell"></i>
      <span>Notifications</span>
    </a>
    <hr>
    <a href="../logout.php" class="sidebar-link text-danger">
      <i class="bi bi-box-arrow-right"></i>
      <span>Logout</span>
    </a>
  </div>
</aside>

<header class="app-header">
  <div class="header-left">
    <button type="button" class="hamburger-btn" id="menuBtn" aria-label="Open menu">
      <i class="bi bi-list"></i>
    </button>

    <a href="index.php" class="app-title-link">
      <span class="app-title d-none d-lg-inline">DFPS</span>
    </a>
  </div>

  <div class="header-right">
    <a href="../buyer/message.php" class="header-item">
      <i class="bi bi-chat-dots"></i>
      <span class="d-none d-md-block">Message</span>
      <?php if ($unread_msg_count > 0): ?>
          <span class="badge rounded-pill bg-danger">
              <?php echo $unread_msg_count > 99 ? '99+' : $unread_msg_count; ?>
          </span>
      <?php endif; ?>
    </a>

    <a href="../buyer/notification.php" class="header-item">
      <i class="bi bi-bell"></i>
      <span class="d-none d-md-block">Notification</span>
      <?php if ($unread_notif_count > 0): ?>
          <span class="badge rounded-pill bg-danger">
              <?php echo $unread_notif_count > 99 ? '99+' : $unread_notif_count; ?>
          </span>
      <?php endif; ?>
    </a>

    <a href="../logout.php" class="header-item">
      <i class="bi bi-box-arrow-right"></i>
      <span class="d-none d-md-block">Logout</span>
    </a>
  </div>
</header>

<!-- System Alert Modal -->
<div class="modal fade" id="systemAlertModal" tabindex="-1" aria-labelledby="systemAlertModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4">
      <div class="modal-header bg-danger text-white rounded-top-4">
        <h5 class="modal-title fw-bold" id="systemAlertModalLabel">
          <i class="bi bi-exclamation-triangle-fill me-2"></i> System Alert
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <h5 id="alertTitle" class="fw-bold mb-3"></h5>
        <div id="alertBody" class="text-secondary mb-0"></div>
      </div>
      <div class="modal-footer border-0 p-3">
        <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
        <a id="alertLink" href="#" class="btn btn-primary rounded-pill px-4">View Details</a>
      </div>
    </div>
  </div>
</div>

<script>
  // Sidebar Toggle Logic
  const menuBtn = document.getElementById('menuBtn');
  const closeSidebar = document.getElementById('closeSidebar');
  const appSidebar = document.getElementById('appSidebar');
  const sidebarOverlay = document.getElementById('sidebarOverlay');

  function toggleSidebar() {
    appSidebar.classList.toggle('active');
    sidebarOverlay.classList.toggle('active');
  }

  if(menuBtn) menuBtn.addEventListener('click', toggleSidebar);
  if(closeSidebar) closeSidebar.addEventListener('click', toggleSidebar);
  if(sidebarOverlay) sidebarOverlay.addEventListener('click', toggleSidebar);
</script>