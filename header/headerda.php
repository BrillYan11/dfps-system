<!DOCTYPE html>
<html lang="en" class="<?php echo $body_class ?? ''; ?>">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>DA Portal | Department of Agriculture</title>

  <!-- Bootstrap CSS (local) -->
  <link rel="stylesheet" href="../bootstrap/css/bootstrap.css">

  <!-- Your CSS -->
  <link rel="stylesheet" href="../css/style.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="../css/notification.css?v=<?php echo time(); ?>">

  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  
  <!-- Real-time updates -->
  <script src="../js/realtime.js" defer></script>

  <style>
    :root {
      --da-primary: #1b5e20;
      --da-secondary: #2e7d32;
    }
    .app-header { background-color: var(--da-primary) !important; }
    .btn-primary { background-color: var(--da-primary); border-color: var(--da-primary); }
    .btn-primary:hover { background-color: var(--da-secondary); border-color: var(--da-secondary); }
    .badge.bg-primary { background-color: var(--da-primary) !important; }
    .text-primary { color: var(--da-primary) !important; }

    /* Sidebar Styles */
    .da-sidebar {
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
    .da-sidebar.active {
      left: 0;
    }
    .sidebar-header {
      padding: 20px;
      background: var(--da-primary);
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
      background: #f0f7f0;
      color: var(--da-primary);
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
      
      // DA usually doesn't have messages, but for consistency in realtime.js
      $unread_msg_count = 0; 
  }
?>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- DA Sidebar -->
<aside class="da-sidebar" id="daSidebar">
  <div class="sidebar-header">
    <h5 class="mb-0 fw-bold">DA Menu</h5>
    <button type="button" class="btn-close btn-close-white" id="closeSidebar"></button>
  </div>
  <div class="sidebar-content">
    <a href="index.php" class="sidebar-link">
      <i class="bi bi-speedometer2"></i>
      <span>Dashboard</span>
    </a>
    <a href="users.php" class="sidebar-link">
      <i class="bi bi-people"></i>
      <span>Users Management</span>
    </a>
    <a href="listings.php" class="sidebar-link">
      <i class="bi bi-card-list"></i>
      <span>Listings Overview</span>
    </a>
    <a href="produce.php" class="sidebar-link">
      <i class="bi bi-egg-fried"></i>
      <span>Produce Master List</span>
    </a>
    <a href="announcements.php" class="sidebar-link">
      <i class="bi bi-megaphone"></i>
      <span>Announcements</span>
    </a>
    <a href="send_notification.php" class="sidebar-link">
      <i class="bi bi-broadcast"></i>
      <span>Broadcast Alert</span>
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
      <span class="app-title d-none d-lg-inline">DA Portal</span>
    </a>
  </div>

  <div class="header-right">
    <!-- Keeping direct icons for high-frequency actions -->
    <a href="notification.php" class="header-item">
      <i class="bi bi-bell"></i>
      <span class="d-none d-md-block">Alerts</span>
      <?php if ($unread_notif_count > 0): ?>
          <span class="badge rounded-pill bg-danger">
              <?php echo $unread_notif_count; ?>
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
  const daSidebar = document.getElementById('daSidebar');
  const sidebarOverlay = document.getElementById('sidebarOverlay');

  function toggleSidebar() {
    daSidebar.classList.toggle('active');
    sidebarOverlay.classList.toggle('active');
  }

  if(menuBtn) menuBtn.addEventListener('click', toggleSidebar);
  if(closeSidebar) closeSidebar.addEventListener('click', toggleSidebar);
  if(sidebarOverlay) sidebarOverlay.addEventListener('click', toggleSidebar);
</script>
