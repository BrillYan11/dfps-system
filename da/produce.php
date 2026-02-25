<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'DA') {
    header("Location: ../login.php");
    exit;
}

$success_msg = '';
$error_msg = '';

// Handle Adding New Produce
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_produce'])) {
    $name = trim($_POST['name']);
    $unit = trim($_POST['unit']) ?: 'kg';
    $srp = filter_input(INPUT_POST, 'srp', FILTER_VALIDATE_FLOAT) ?: 0.00;

    if (empty($name)) {
        $error_msg = "Produce name is required.";
    } else {
        $stmt = $conn->prepare("INSERT INTO produce (name, unit, srp) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE srp = VALUES(srp), unit = VALUES(unit)");
        $stmt->bind_param("ssd", $name, $unit, $srp);
        if ($stmt->execute()) {
            $success_msg = "Produce updated in the master list!";
        } else {
            $error_msg = "Error: " . $conn->error;
        }
        $stmt->close();
    }
}

// Fetch all produce
$produce_list = $conn->query("SELECT * FROM produce ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

include '../header/headerda.php';
?>

<main class="container-fluid px-4 my-4">
    <div class="row g-4">
        <!-- Add/Edit Produce Form -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-primary text-white py-3 border-0 rounded-top-4">
                    <h5 class="mb-0 fw-bold">Manage Produce & SRP</h5>
                </div>
                <div class="card-body">
                    <?php if ($success_msg): ?>
                        <div class="alert alert-success d-flex align-items-center"><i class="bi bi-check-circle-fill me-2"></i><?php echo $success_msg; ?></div>
                    <?php endif; ?>
                    <?php if ($error_msg): ?>
                        <div class="alert alert-danger d-flex align-items-center"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error_msg; ?></div>
                    <?php endif; ?>

                    <form method="POST" action="produce.php">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Produce Name</label>
                            <input type="text" name="name" id="produce_name" class="form-control rounded-3 shadow-none border-2" placeholder="e.g. Potato, Rice, Corn" required>
                            <small class="text-muted">Duplicates will update SRP/Unit.</small>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Default Unit</label>
                                <input type="text" name="unit" id="produce_unit" class="form-control rounded-3 shadow-none border-2" placeholder="kg, sack" value="kg">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">SRP (₱)</label>
                                <input type="number" step="0.01" name="srp" id="produce_srp" class="form-control rounded-3 shadow-none border-2" placeholder="0.00" required>
                            </div>
                        </div>
                        <button type="submit" name="add_produce" class="btn btn-primary w-100 py-2 rounded-pill fw-bold">
                            <i class="bi bi-save me-2"></i> Save Changes
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Produce List Table -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="mb-0 fw-bold">Master Produce List</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light text-muted">
                                <tr>
                                    <th class="ps-4">Produce Name</th>
                                    <th>Unit</th>
                                    <th>SRP (Suggested)</th>
                                    <th>Status</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($produce_list)): ?>
                                    <tr><td colspan="5" class="text-center py-4">No produce types registered.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($produce_list as $item): ?>
                                        <tr>
                                            <td class="ps-4 fw-semibold text-dark"><?php echo htmlspecialchars($item['name']); ?></td>
                                            <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($item['unit']); ?></span></td>
                                            <td class="fw-bold text-primary">₱<?php echo number_format($item['srp'], 2); ?></td>
                                            <td>
                                                <span class="badge <?php echo $item['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                                                    <?php echo $item['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td class="text-end pe-4">
                                                <button class="btn btn-sm btn-outline-primary rounded-pill edit-btn" 
                                                        data-name="<?php echo htmlspecialchars($item['name']); ?>" 
                                                        data-unit="<?php echo htmlspecialchars($item['unit']); ?>" 
                                                        data-srp="<?php echo $item['srp']; ?>">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </button>
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
    </div>
</main>

<script>
    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('produce_name').value = this.dataset.name;
            document.getElementById('produce_unit').value = this.dataset.unit;
            document.getElementById('produce_srp').value = this.dataset.srp;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });
</script>

<?php include '../footer/footerda.php'; ?>
