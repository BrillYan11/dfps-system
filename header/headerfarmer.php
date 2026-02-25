<!DOCTYPE html>
<html lang="en" class="<?php echo $body_class ?? ''; ?>">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Farmer Dashboard</title>

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
      --primary-color: #28a745; /* Farmer specific color */
    }
    .app-header { background-color: var(--primary-color) !important; }
    .btn-primary { background-color: var(--primary-color); border-color: var(--primary-color); }
    .btn-primary:hover { background-color: #218838; border-color: #1e7e34; }
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

    /* Existing styles */
    .header-item .badge {
        position: absolute !important;
        top: 4px !important;
        right: 12px !important;
        transform: translate(50%, -50%) !important;
        z-index: 10;
        display: inline-block !important;
    }
    .header-item {
        position: relative !important;
        display: flex !important;
        flex-direction: column !important;
        align-items: center !important;
        justify-content: center !important;
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
    <h5 class="mb-0 fw-bold">Farmer Menu</h5>
    <button type="button" class="btn-close btn-close-white" id="closeSidebar"></button>
  </div>
  <div class="sidebar-content">
    <a href="index.php" class="sidebar-link">
      <i class="bi bi-speedometer2"></i>
      <span>Dashboard</span>
    </a>
    <a href="add_post.php" class="sidebar-link">
      <i class="bi bi-plus-square"></i>
      <span>Add New Post</span>
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
      <span class="app-title d-none d-lg-inline">DFPS Farmer</span>
    </a>
  </div>

  <div class="header-right">
    <a href="../farmer/message.php" class="header-item">
      <i class="bi bi-chat-dots"></i>
      <span class="d-none d-md-block">Message</span>
      <?php if ($unread_msg_count > 0): ?>
          <span class="badge rounded-pill bg-danger">
              <?php echo $unread_msg_count > 99 ? '99+' : $unread_msg_count; ?>
          </span>
      <?php endif; ?>
    </a>

    <a href="../farmer/notification.php" class="header-item">
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

<script>
  // Sidebar Toggle Logic
  const menuBtn = document.getElementById('menuBtn');
  const closeSidebar = document.getElementById('closeSidebar');
  const appSidebar = document.getElementById('appSidebar'); // Changed from daSidebar to appSidebar
  const sidebarOverlay = document.getElementById('sidebarOverlay');

  function toggleSidebar() {
    appSidebar.classList.toggle('active');
    sidebarOverlay.classList.toggle('active');
  }

  if(menuBtn) menuBtn.addEventListener('click', toggleSidebar);
  if(closeSidebar) closeSidebar.addEventListener('click', toggleSidebar);
  if(sidebarOverlay) sidebarOverlay.addEventListener('click', toggleSidebar);
</script>
