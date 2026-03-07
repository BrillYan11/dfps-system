<?php
session_start();
include 'includes/universal_header.php';
?>

<div class="container mt-5">
    <div class="p-5 mb-4 bg-light rounded-3">
        <div class="container-fluid py-5">
            <h1 class="display-5 fw-bold">Welcome to DFPS</h1>
            <p class="col-md-8 fs-4">Digital Farming Platform System. Your one-stop solution for connecting farmers and buyers directly.</p>

            <?php if (isset($_SESSION['user_id'])): ?>
                <p>You are logged in. Go to your dashboard or logout.</p>
                <?php
                    // Determine dashboard link based on role
                    $dashboard_link = 'index.php'; // Default fallback
                    if (isset($_SESSION['role'])) {
                        if ($_SESSION['role'] == 'FARMER') {
                            $dashboard_link = 'farmer/index.php';
                        } elseif ($_SESSION['role'] == 'BUYER') {
                            $dashboard_link = 'buyer/index.php';
                        }
                    }
                ?>
                <a href="<?php echo $dashboard_link; ?>" class="btn btn-primary btn-lg">My Dashboard</a>
                <a href="logout.php" class="btn btn-secondary btn-lg">Logout</a>
            <?php else: ?>
                <p>Join our platform to start buying or selling fresh produce.</p>
                <a href="login.php" class="btn btn-primary btn-lg">Login</a>
                <a href="register.php" class="btn btn-outline-secondary btn-lg">Register</a>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php include 'includes/universal_footer.php'; ?>
