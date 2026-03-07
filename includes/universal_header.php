<?php
// universal_header.php - Consolidated header for all roles
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Determine base path for assets and links
// If we are in a subdirectory (farmer, buyer, da, profile), we need '../'
// If we are in the root, we don't need it.
$current_dir = basename(dirname($_SERVER['SCRIPT_NAME']));
$is_subdir = in_array($current_dir, ['farmer', 'buyer', 'da', 'profile', 'action', 'includes']);
$base = $is_subdir ? '../' : '';

// Define configuration based on role
$role = $_SESSION['role'] ?? 'GUEST';
$config = [
    'FARMER' => [
        'primary_color' => '#28a745',
        'secondary_color' => '#218838',
        'title' => 'Farmer Dashboard',
        'brand' => 'DFPS Farmer',
        'menu_header' => 'Farmer Menu',
        'links' => [
            ['url' => $base . 'farmer/index.php', 'icon' => 'bi-speedometer2', 'label' => 'Dashboard'],
            ['url' => $base . 'profile/index.php', 'icon' => 'bi-person-circle', 'label' => 'My Profile'],
            ['url' => $base . 'farmer/add_post.php', 'icon' => 'bi-plus-square', 'label' => 'Add New Post'],
            ['url' => $base . 'farmer/message.php', 'icon' => 'bi-chat-dots', 'label' => 'Messages'],
            ['url' => $base . 'farmer/notification.php', 'icon' => 'bi-bell', 'label' => 'Notifications'],
        ]
    ],
    'DA' => [
        'primary_color' => '#1b5e20',
        'secondary_color' => '#2e7d32',
        'title' => 'DA Portal | Department of Agriculture',
        'brand' => 'DA Portal',
        'menu_header' => 'DA Menu',
        'links' => [
            ['url' => $base . 'da/index.php', 'icon' => 'bi-speedometer2', 'label' => 'Dashboard'],
            ['url' => $base . 'profile/index.php', 'icon' => 'bi-person-circle', 'label' => 'My Profile'],
            ['url' => $base . 'da/users.php', 'icon' => 'bi-people', 'label' => 'Users Management'],
            ['url' => $base . 'da/listings.php', 'icon' => 'bi-card-list', 'label' => 'Listings Overview'],
            ['url' => $base . 'da/produce.php', 'icon' => 'bi-egg-fried', 'label' => 'Produce Master List'],
            ['url' => $base . 'da/announcements.php', 'icon' => 'bi-megaphone', 'label' => 'Announcements'],
            ['url' => $base . 'da/send_notification.php', 'icon' => 'bi-broadcast', 'label' => 'Broadcast Alert'],
        ]
    ],
    'BUYER' => [
        'primary_color' => '#007bff',
        'secondary_color' => '#0056b3',
        'title' => 'Buyer Dashboard',
        'brand' => 'DFPS',
        'menu_header' => 'Buyer Menu',
        'links' => [
            ['url' => $base . 'buyer/index.php', 'icon' => 'bi-shop', 'label' => 'Marketplace'],
            ['url' => $base . 'profile/index.php', 'icon' => 'bi-person-circle', 'label' => 'My Profile'],
            ['url' => $base . 'buyer/message.php', 'icon' => 'bi-chat-dots', 'label' => 'Messages'],
            ['url' => $base . 'buyer/notification.php', 'icon' => 'bi-bell', 'label' => 'Notifications'],
        ]
    ],
    'GUEST' => [
        'primary_color' => '#6c757d',
        'secondary_color' => '#5a6268',
        'title' => 'DFPS',
        'brand' => 'DFPS',
        'menu_header' => 'Menu',
        'links' => [
            ['url' => $base . 'index.php', 'icon' => 'bi-house', 'label' => 'Home'],
        ]
    ]
];

$current_config = $config[$role] ?? $config['GUEST'];

// Fetch unread counts
$unread_notif_count = 0;
$unread_msg_count = 0;
if (isset($_SESSION['user_id']) && isset($conn)) {
    include_once __DIR__ . '/NotificationModel.php';
    $unread_notif_count = NotificationModel::countUnread($conn, $_SESSION['user_id']);
    
    // Message count only for Farmer/Buyer
    if ($role === 'FARMER' || $role === 'BUYER') {
        $msg_count_sql = "SELECT COUNT(id) as c FROM messages m JOIN conversation_participants cp ON m.conversation_id = cp.conversation_id WHERE cp.user_id = ? AND m.sender_id != ? AND m.read_at IS NULL AND m.is_deleted = 0";
        $msg_count_stmt = $conn->prepare($msg_count_sql);
        $msg_count_stmt->bind_param("ii", $_SESSION['user_id'], $_SESSION['user_id']);
        $msg_count_stmt->execute();
        $unread_msg_count = $msg_count_stmt->get_result()->fetch_assoc()['c'] ?? 0;
        $msg_count_stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="<?php echo $body_class ?? ''; ?>">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo $current_config['title']; ?></title>

  <!-- Bootstrap CSS (local) -->
  <link rel="stylesheet" href="<?php echo $base; ?>bootstrap/css/bootstrap.css">

  <!-- Your CSS -->
  <link rel="stylesheet" href="<?php echo $base; ?>css/style.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="<?php echo $base; ?>css/header.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="<?php echo $base; ?>css/message.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="<?php echo $base; ?>css/notification.css?v=<?php echo time(); ?>">

  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

  <!-- Bootstrap JS (local) -->
  <script src="<?php echo $base; ?>bootstrap/js/bootstrap.bundle.min.js" defer></script>
  <!-- Real-time updates -->
  <script src="<?php echo $base; ?>js/realtime.js?v=<?php echo time(); ?>" defer></script>
  <style>
    :root {
      --primary-color: <?php echo $current_config['primary_color']; ?>;
      --secondary-color: <?php echo $current_config['secondary_color']; ?>;
    }
  </style>
</head>
<body class="<?php echo $body_class ?? ''; ?>">

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- App Sidebar -->
<aside class="app-sidebar" id="appSidebar">
  <div class="sidebar-header">
    <h5 class="mb-0 fw-bold"><?php echo $current_config['menu_header']; ?></h5>
    <button type="button" class="btn-close btn-close-white" id="closeSidebar"></button>
  </div>
  <div class="sidebar-content">
    <?php foreach ($current_config['links'] as $link): ?>
    <a href="<?php echo $link['url']; ?>" class="sidebar-link">
      <i class="bi <?php echo $link['icon']; ?>"></i>
      <span><?php echo $link['label']; ?></span>
    </a>
    <?php endforeach; ?>
    <hr>
    <?php if (isset($_SESSION['user_id'])): ?>
    <a href="<?php echo $base; ?>logout.php" class="sidebar-link text-danger">
      <i class="bi bi-box-arrow-right"></i>
      <span>Logout</span>
    </a>
    <?php else: ?>
    <a href="<?php echo $base; ?>login.php" class="sidebar-link text-primary">
      <i class="bi bi-box-arrow-in-right"></i>
      <span>Login</span>
    </a>
    <?php endif; ?>
  </div>
</aside>

<header class="app-header">
  <div class="header-left">
    <button type="button" class="hamburger-btn" id="menuBtn" aria-label="Open menu">
      <i class="bi bi-list"></i>
    </button>
    <a href="<?php echo $base; ?>index.php" class="app-title-link">
      <span class="app-title"><?php echo $current_config['brand']; ?></span>
    </a>
  </div>

  <div class="header-right">
    <?php if ($role === 'FARMER' || $role === 'BUYER'): ?>
    <?php $msg_url = ($role === 'FARMER') ? $base . 'farmer/message.php' : $base . 'buyer/message.php'; ?>
    <a href="<?php echo $msg_url; ?>" class="header-item">
      <i class="bi bi-chat-dots"></i>
      <span class="d-none d-md-block">Message</span>
      <?php if ($unread_msg_count > 0): ?>
          <span class="badge rounded-pill bg-danger">
              <?php echo $unread_msg_count > 99 ? '99+' : $unread_msg_count; ?>
          </span>
      <?php endif; ?>
    </a>
    <?php endif; ?>

    <?php 
      $notif_url = '#';
      if ($role === 'FARMER') $notif_url = $base . 'farmer/notification.php';
      elseif ($role === 'BUYER') $notif_url = $base . 'buyer/notification.php';
      elseif ($role === 'DA') $notif_url = $base . 'da/notification.php';
    ?>
    <?php if ($role !== 'GUEST'): ?>
    <a href="<?php echo $notif_url; ?>" class="header-item">
      <i class="bi bi-bell"></i>
      <span class="d-none d-md-block"><?php echo ($role === 'DA') ? 'Alerts' : 'Notification'; ?></span>
      <?php if ($unread_notif_count > 0): ?>
          <span class="badge rounded-pill bg-danger">
              <?php echo $unread_notif_count > 99 ? '99+' : $unread_notif_count; ?>
          </span>
      <?php endif; ?>
    </a>
    <?php endif; ?>

    <?php if ($role === 'GUEST'): ?>
    <a href="<?php echo $base; ?>login.php" class="header-item">
      <i class="bi bi-box-arrow-in-right"></i>
      <span class="d-none d-md-block">Login</span>
    </a>
    <a href="<?php echo $base; ?>register.php" class="header-item">
      <i class="bi bi-person-plus"></i>
      <span class="d-none d-md-block">Register</span>
    </a>
    <?php endif; ?>

    <?php if (isset($_SESSION['user_id'])): ?>
    <a href="<?php echo $base; ?>logout.php" class="header-item">
      <i class="bi bi-box-arrow-right"></i>
      <span class="d-none d-md-block">Logout</span>
    </a>
    <?php endif; ?>
  </div>
</header>

<script>
  // Sidebar Toggle Logic
  document.addEventListener('DOMContentLoaded', function() {
    const menuBtn = document.getElementById('menuBtn');
    const closeSidebar = document.getElementById('closeSidebar');
    const appSidebar = document.getElementById('appSidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    function toggleSidebar() {
      if (appSidebar && sidebarOverlay) {
        appSidebar.classList.toggle('active');
        sidebarOverlay.classList.toggle('active');
      }
    }

    if(menuBtn) menuBtn.addEventListener('click', toggleSidebar);
    if(closeSidebar) closeSidebar.addEventListener('click', toggleSidebar);
    if(sidebarOverlay) sidebarOverlay.addEventListener('click', toggleSidebar);
  });
</script>
