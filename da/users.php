<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'DA') {
    header("Location: ../login.php");
    exit;
}

$role_filter = filter_input(INPUT_GET, 'role', FILTER_UNSAFE_RAW);
$users = [];

// 1. Analytics for the header
$total_farmers = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'FARMER'")->fetch_row()[0];
$total_buyers = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'BUYER'")->fetch_row()[0];
$active_accounts = $conn->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetch_row()[0];

// 2. Data fetching for the list
$query = "
    SELECT u.*, a.name as area_name, 
           (SELECT COUNT(*) FROM posts WHERE farmer_id = u.id) as post_count
    FROM users u 
    LEFT JOIN areas a ON u.area_id = a.id 
    WHERE 1=1
";
$params = [];
$types = "";

if ($role_filter) {
    $query .= " AND u.role = ?";
    $params[] = $role_filter;
    $types .= "s";
}

$query .= " ORDER BY u.created_at DESC";
$stmt = $conn->prepare($query);
if ($role_filter) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

include '../includes/universal_header.php';
?>

<style>
    .user-featured-header {
        background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%);
        color: #fff;
        border-radius: 15px;
        padding: 30px;
        margin-bottom: 30px;
    }
    .user-card {
        border: none;
        border-radius: 12px;
        transition: transform 0.2s;
        border: 1px solid #eef0f2;
    }
    .user-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.05);
    }
</style>

<main class="container-fluid px-4 my-4">
    <!-- Featured Analytics Header -->
    <div class="user-featured-header">
        <div class="row g-4 align-items-center">
            <div class="col-md-6">
                <h2 class="fw-bold mb-1">User Ecosystem Management</h2>
                <p class="opacity-75 mb-0">Overseeing all farmers and buyers to ensure a secure and stable marketplace.</p>
            </div>
            <div class="col-md-6">
                <div class="row g-3">
                    <div class="col-4">
                        <div class="bg-white bg-opacity-10 p-3 rounded-4 text-center">
                            <div class="h3 fw-bold mb-0"><?php echo number_format($total_farmers); ?></div>
                            <small class="opacity-75">Farmers</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="bg-white bg-opacity-10 p-3 rounded-4 text-center">
                            <div class="h3 fw-bold mb-0"><?php echo number_format($total_buyers); ?></div>
                            <small class="opacity-75">Buyers</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="bg-white bg-opacity-10 p-3 rounded-4 text-center">
                            <div class="h3 fw-bold mb-0 text-warning"><?php echo number_format($active_accounts); ?></div>
                            <small class="opacity-75">Active</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- User Management Table -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold"><?php echo $role_filter ? ucfirst(strtolower($role_filter)) . 's' : 'All Marketplace Participants'; ?></h5>
            <?php
                $isAll = empty($role_filter);
                $isFarmer = ($role_filter === 'FARMER');
                $isBuyer  = ($role_filter === 'BUYER');

                $allClass    = $isAll    ? 'btn-secondary' : 'btn-outline-secondary';
                $farmerClass = $isFarmer ? 'btn-primary'   : 'btn-outline-primary';
                $buyerClass  = $isBuyer  ? 'btn-success'   : 'btn-outline-success';
            ?>
            <div class="d-flex gap-2">
                <a href="users.php" class="btn btn-sm <?php echo $allClass; ?> rounded-pill px-3">All</a>
                <a href="users.php?role=FARMER" class="btn btn-sm <?php echo $farmerClass; ?> rounded-pill px-3">Farmers</a>
                <a href="users.php?role=BUYER" class="btn btn-sm <?php echo $buyerClass; ?> rounded-pill px-3">Buyers</a>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted">
                        <tr>
                            <th class="ps-4">Profile & Activity</th>
                            <th>Account Info</th>
                            <th>Location</th>
                            <th>Account Status</th>
                            <th class="text-end pe-4">Management Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr><td colspan="5" class="text-center py-5 text-muted">No users found matching your criteria.</td></tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): 
                                $initials = strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1));
                            ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center overflow-hidden" style="width: 48px; height: 48px; border: 2px solid #eef0f2;">
                                                <?php if (!empty($user['profile_picture'])): ?>
                                                    <img src="../<?php echo $user['profile_picture']; ?>" class="w-100 h-100" style="object-fit: cover;">
                                                <?php else: ?>
                                                    <i class="bi bi-person-circle text-secondary" style="font-size: 32px;"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                                                <div class="d-flex gap-2 mt-1">
                                                    <span class="badge bg-light text-dark border" style="font-size: 0.7rem;"><?php echo $user['role']; ?></span>
                                                    <?php if($user['role'] === 'FARMER'): ?>
                                                        <span class="badge bg-success bg-opacity-10 text-success" style="font-size: 0.7rem;"><?php echo $user['post_count']; ?> Posts</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="text-dark small"><i class="bi bi-envelope me-1"></i> <?php echo htmlspecialchars($user['email']); ?></div>
                                        <div class="text-muted small mt-1"><i class="bi bi-telephone me-1"></i> <?php echo htmlspecialchars($user['phone']); ?></div>
                                    </td>
                                    <td>
                                        <div class="small"><i class="bi bi-geo-alt-fill text-danger me-1"></i> <?php echo htmlspecialchars($user['area_name'] ?: 'Not set'); ?></div>
                                        <div class="text-muted" style="font-size: 0.7rem;">Member since <?php echo date('M Y', strtotime($user['created_at'])); ?></div>
                                    </td>
                                    <td>
                                        <?php if($user['is_active']): ?>
                                            <span class="badge rounded-pill bg-success-subtle text-success border border-success px-3">Active</span>
                                        <?php else: ?>
                                            <span class="badge rounded-pill bg-danger-subtle text-danger border border-danger px-3">Deactivated</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="btn-group shadow-sm rounded-pill overflow-hidden">
                                            <a href="message.php?receiver_id=<?php echo $user['id']; ?>" class="btn btn-sm btn-white border" title="Message User"><i class="bi bi-chat-dots"></i></a>
                                            <?php if($user['role'] === 'FARMER'): ?>
                                                <a href="listings.php?farmer_id=<?php echo $user['id']; ?>" class="btn btn-sm btn-white border" title="View Listings"><i class="bi bi-grid-3x3"></i></a>
                                            <?php endif; ?>
                                            
                                            <?php if($user['is_active']): ?>
                                                <a href="../action/DA/toggle_user.php?id=<?php echo $user['id']; ?>&status=0&role=<?php echo $role_filter; ?>" 
                                                   class="btn btn-sm btn-outline-danger border" 
                                                   onclick="return confirm('Deactivate this user? They will not be able to login.')" 
                                                   title="Deactivate Account">
                                                    <i class="bi bi-person-x-fill"></i> Deactivate
                                                </a>
                                            <?php else: ?>
                                                <a href="../action/DA/toggle_user.php?id=<?php echo $user['id']; ?>&status=1&role=<?php echo $role_filter; ?>" 
                                                   class="btn btn-sm btn-outline-success border" 
                                                   title="Activate Account">
                                                    <i class="bi bi-person-check-fill"></i> Activate
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/universal_footer.php'; ?>
