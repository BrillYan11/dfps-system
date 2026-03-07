<?php
session_start();
include '../includes/db.php';
include '../includes/NotificationModel.php';

// Authentication and Authorization Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'DA') {
    header("Location: ../login.php");
    exit;
}

$da_id = $_SESSION['user_id'];

// --- ANALYTICS DATA FETCHING ---
// 1. User Stats
$user_counts = $conn->query("SELECT role, COUNT(*) as count FROM users GROUP BY role")->fetch_all(MYSQLI_ASSOC);
$stats_users = ['FARMER' => 0, 'BUYER' => 0, 'DA' => 0];
foreach($user_counts as $uc) { $stats_users[$uc['role']] = $uc['count']; }

// 2. Post Stats
$post_stats = $conn->query("SELECT status, COUNT(*) as count FROM posts GROUP BY status")->fetch_all(MYSQLI_ASSOC);
$stats_posts = ['ACTIVE' => 0, 'SOLD' => 0];
foreach($post_stats as $ps) { if(isset($stats_posts[$ps['status']])) $stats_posts[$ps['status']] = $ps['count']; }

// 3. Price Analysis (Avg price per Produce)
$price_analysis = $conn->query("
    SELECT pr.name, AVG(p.price) as avg_price, p.unit, pr.srp
    FROM posts p 
    JOIN produce pr ON p.produce_id = pr.id 
    WHERE p.status = 'ACTIVE' 
    GROUP BY pr.name, p.unit, pr.srp
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// 4. Recent Activity (Latest Posts) with SRP comparison
$recent_posts = $conn->query("
    SELECT p.id, p.title, p.price, p.unit, u.first_name, u.last_name, p.created_at, pr.srp as produce_srp
    FROM posts p
    JOIN users u ON p.farmer_id = u.id
    JOIN produce pr ON p.produce_id = pr.id
    ORDER BY p.created_at DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

include '../includes/universal_header.php';
?>

<style>
    .featured-card {
        border: none;
        border-radius: 15px;
        background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%);
        color: #fff;
        padding: 25px;
        margin-bottom: 25px;
        box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }
    .stat-box {
        background: #fff;
        border-radius: 12px;
        padding: 20px;
        border: 1px solid #eef0f2;
        transition: transform 0.2s ease;
    }
    .stat-box:hover { transform: translateY(-5px); }
    .stat-icon {
        width: 48px; height: 48px;
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.5rem; margin-bottom: 15px;
    }
</style>

<main class="container-fluid px-4 my-4">
    <!-- Featured Header -->
    <div class="featured-card">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="display-5 fw-bold mb-2">DA Command Center</h1>
                <p class="lead opacity-75">Ensuring fair market pricing and supporting our local agricultural community.</p>
                <div class="d-flex gap-2 mt-4">
                    <a href="announcements.php" class="btn btn-light rounded-pill px-4">Post Announcement</a>
                    <a href="produce.php" class="btn btn-outline-light rounded-pill px-4">Update SRP</a>
                </div>
            </div>
            <div class="col-md-4 text-end d-none d-md-block">
                <i class="bi bi-graph-up-arrow" style="font-size: 8rem; opacity: 0.2;"></i>
            </div>
        </div>
    </div>

    <!-- Analytics Overview -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <a href="users.php?role=FARMER" class="text-decoration-none">
                <div class="stat-box h-100">
                    <div class="stat-icon bg-primary text-white"><i class="bi bi-people-fill"></i></div>
                    <h6 class="text-muted mb-1">Total Farmers</h6>
                    <h3 class="mb-0 text-dark"><?php echo number_format($stats_users['FARMER']); ?></h3>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="listings.php?status=ACTIVE" class="text-decoration-none">
                <div class="stat-box h-100">
                    <div class="stat-icon bg-success text-white"><i class="bi bi-cart-check-fill"></i></div>
                    <h6 class="text-muted mb-1">Active Listings</h6>
                    <h3 class="mb-0 text-dark"><?php echo number_format($stats_posts['ACTIVE']); ?></h3>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="users.php?role=BUYER" class="text-decoration-none">
                <div class="stat-box h-100">
                    <div class="stat-icon bg-warning text-white"><i class="bi bi-cash-stack"></i></div>
                    <h6 class="text-muted mb-1">Total Buyers</h6>
                    <h3 class="mb-0 text-dark"><?php echo number_format($stats_users['BUYER']); ?></h3>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="listings.php?status=SOLD" class="text-decoration-none">
                <div class="stat-box h-100">
                    <div class="stat-icon bg-info text-white"><i class="bi bi-check-circle-fill"></i></div>
                    <h6 class="text-muted mb-1">Sold Products</h6>
                    <h3 class="mb-0 text-dark"><?php echo number_format($stats_posts['SOLD']); ?></h3>
                </div>
            </a>
        </div>
    </div>

    <div class="row g-4">
        <!-- Price Analysis Table -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-transparent py-3 border-0">
                    <h5 class="mb-0 fw-bold">Market Price Analysis <small class="text-muted fw-normal">(vs SRP)</small></h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">Produce Name</th>
                                    <th>Avg. Price</th>
                                    <th>SRP (Goal)</th>
                                    <th class="text-end pe-4">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($price_analysis)): ?>
                                    <tr><td colspan="4" class="text-center py-4">No data available</td></tr>
                                <?php else: ?>
                                    <?php foreach($price_analysis as $pa): 
                                        $diff = $pa['avg_price'] - $pa['srp'];
                                        $is_over = ($diff > 0);
                                    ?>
                                        <tr>
                                            <td class="ps-4 fw-semibold"><?php echo htmlspecialchars($pa['name']); ?></td>
                                            <td class="fw-bold <?php echo $is_over ? 'text-danger' : 'text-success'; ?>">₱<?php echo number_format($pa['avg_price'], 2); ?></td>
                                            <td>₱<?php echo number_format($pa['srp'], 2); ?></td>
                                            <td class="text-end pe-4">
                                                <?php if($is_over): ?>
                                                    <span class="badge bg-danger">Over SRP</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Below SRP</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-transparent py-3 border-0">
                    <h5 class="mb-0 fw-bold">Recent Market Activity</h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <?php if(empty($recent_posts)): ?>
                            <p class="text-center text-muted">No recent activity.</p>
                        <?php else: ?>
                            <?php foreach($recent_posts as $rp): 
                                $is_over_srp = ($rp['price'] > $rp['produce_srp']);
                            ?>
                                <div class="list-group-item px-0 py-3 border-0">
                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($rp['title']); ?></h6>
                                            <small class="text-muted d-block mb-1">By <?php echo htmlspecialchars($rp['first_name'].' '.$rp['last_name']); ?></small>
                                            <span class="badge bg-light text-dark border">SRP: ₱<?php echo number_format($rp['produce_srp'], 2); ?></span>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-bold <?php echo $is_over_srp ? 'text-danger' : 'text-primary'; ?>">
                                                ₱<?php echo number_format($rp['price'], 2); ?>
                                                <?php if($is_over_srp): ?><i class="bi bi-arrow-up-circle-fill ms-1"></i><?php endif; ?>
                                            </div>
                                            <small class="text-muted"><?php echo date('h:i A', strtotime($rp['created_at'])); ?></small>
                                            <div class="mt-2">
                                                <a href="../buyer/view_post.php?id=<?php echo $rp['id']; ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill py-0 px-2" style="font-size: 0.75rem;">View</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <a href="listings.php" class="btn btn-outline-primary w-100 mt-3 rounded-pill">Manage All Listings</a>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/universal_footer.php'; ?>
