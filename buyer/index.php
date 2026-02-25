<?php
session_start();
include '../includes/db.php'; // Correct path to db.php

// 1. Authentication and Authorization Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'BUYER') {
    header("Location: ../login.php");
    exit;
}

$buyer_id = $_SESSION['user_id'];
$area_id = null;

// Fetch buyer's area_id for announcements
$user_stmt = $conn->prepare("SELECT area_id FROM users WHERE id = ?");
$user_stmt->bind_param("i", $buyer_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
if ($user_row = $user_result->fetch_assoc()) {
    $area_id = $user_row['area_id'];
}
$user_stmt->close();

// 2. Search and Fetch All Active Posts
$search_term = filter_input(INPUT_GET, 'search', FILTER_UNSAFE_RAW) ?? '';
$filter_produce = filter_input(INPUT_GET, 'produce_id', FILTER_VALIDATE_INT);
$filter_area = filter_input(INPUT_GET, 'area_id', FILTER_VALIDATE_INT);
$min_price = filter_input(INPUT_GET, 'min_price', FILTER_VALIDATE_FLOAT);
$max_price = filter_input(INPUT_GET, 'max_price', FILTER_VALIDATE_FLOAT);

$posts = [];
$params = [];
$types = '';

$base_query = "
    SELECT
        p.id,
        p.title,
        p.price,
        p.unit,
        pr.name AS produce_name,
        a.name AS area_name,
        u.first_name AS farmer_first_name,
        u.last_name AS farmer_last_name,
        (SELECT pi.file_path FROM post_images pi WHERE pi.post_id = p.id ORDER BY pi.id ASC LIMIT 1) AS image_path
    FROM posts p
    JOIN produce pr ON p.produce_id = pr.id
    JOIN users u ON p.farmer_id = u.id
    LEFT JOIN areas a ON p.area_id = a.id
    WHERE p.status = 'ACTIVE'
";

if (!empty($search_term)) {
    $base_query .= " AND (p.title LIKE ? OR pr.name LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR a.name LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)";
    $like_term = "%" . $search_term . "%";
    for($i=0; $i<6; $i++) { $params[] = $like_term; $types .= 's'; }
}

if ($filter_produce) {
    $base_query .= " AND p.produce_id = ?";
    $params[] = $filter_produce;
    $types .= 'i';
}

if ($filter_area) {
    $base_query .= " AND p.area_id = ?";
    $params[] = $filter_area;
    $types .= 'i';
}

if ($min_price !== false && $min_price !== null) {
    $base_query .= " AND p.price >= ?";
    $params[] = $min_price;
    $types .= 'd';
}

if ($max_price !== false && $max_price !== null) {
    $base_query .= " AND p.price <= ?";
    $params[] = $max_price;
    $types .= 'd';
}

$base_query .= " ORDER BY p.created_at DESC";
$stmt = $conn->prepare($base_query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $posts[] = $row;
}
$stmt->close();

// Fetch Produce list for filter
$produce_list = $conn->query("SELECT id, name FROM produce WHERE is_active = 1 ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
// Fetch Areas list for filter
$areas_list = $conn->query("SELECT id, name FROM areas ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

// 3. Fetch relevant announcements
$announcements = [];
$ann_query = "
    SELECT title, body, created_at FROM announcements
    WHERE area_id IS NULL OR area_id = ?
    ORDER BY created_at DESC
    LIMIT 3
";
$ann_stmt = $conn->prepare($ann_query);
$ann_stmt->bind_param("i", $area_id);
$ann_stmt->execute();
$ann_result = $ann_stmt->get_result();
while ($ann_row = $ann_result->fetch_assoc()) {
    $announcements[] = $ann_row;
}
$ann_stmt->close();


include '../header/headerbuyer.php';
?>

  <!-- Page Layout -->
  <main class="container-fluid px-4 my-3">
    <div class="row g-3">

      <!-- Sidebar -->
      <aside class="col-12 col-md-3 col-lg-3">
        <div class="card shadow-sm border-0">
          <div class="card-header bg-light border-0 py-3">
            <h6 class="mb-0 fw-bold">Market Updates</h6>
          </div>
          <div class="card-body">
              <h6 class="text-primary mb-3">Announcements</h6>
              <?php if (empty($announcements)): ?>
                  <p class="card-text text-muted small">No recent announcements.</p>
              <?php else: ?>
                  <?php foreach ($announcements as $ann): ?>
                      <div class="mb-3 border-bottom pb-2">
                          <strong><?php echo htmlspecialchars($ann['title']); ?></strong>
                          <p class="card-text text-secondary mb-1" style="font-size: 0.85rem;"><?php echo nl2br(htmlspecialchars(substr($ann['body'], 0, 80))); ?>...</p>
                          <small class="text-muted" style="font-size: 0.75rem;"><i class="bi bi-clock"></i> <?php echo date('M j, Y', strtotime($ann['created_at'])); ?></small>
                      </div>
                  <?php endforeach; ?>
              <?php endif; ?>
              <a href="#" class="btn btn-sm btn-link p-0 text-decoration-none">View All Announcements</a>
          </div>
        </div>
      </aside>

      <!-- Main Content -->
      <section class="col-12 col-md-9 col-lg-9">
        <div class="panel p-3">

          <!-- Actions Row -->
          <div class="d-flex align-items-center justify-content-between mb-3">
              <h4 class="mb-0 d-none d-md-block fw-bold">Marketplace</h4>
              <div class="d-flex align-items-center gap-2">
                  <!-- Filter Dropdown -->
                  <div class="dropdown">
                    <button class="btn btn-light rounded-circle shadow-sm position-relative" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" title="Filters" id="filterDropdownBtn">
                      <i class="bi bi-filter"></i>
                      <span id="filterBadge" class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle d-none"></span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-3 shadow-lg border-0" style="width: 280px; border-radius: 15px;">
                      <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                        <h6 class="fw-bold mb-0">Filter Products</h6>
                        <button type="button" id="resetFilters" class="btn btn-sm text-primary p-0">Reset</button>
                      </div>
                      <form id="filterForm">
                        <div class="mb-3">
                          <label class="form-label small fw-bold">Produce Category</label>
                          <select name="produce_id" id="filter_produce" class="form-select form-select-sm">
                            <option value="">All Produce</option>
                            <?php foreach ($produce_list as $pr): ?>
                              <option value="<?php echo $pr['id']; ?>"><?php echo htmlspecialchars($pr['name']); ?></option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        <div class="mb-3">
                          <label class="form-label small fw-bold">Location / Area</label>
                          <select name="area_id" id="filter_area" class="form-select form-select-sm">
                            <option value="">All Areas</option>
                            <?php foreach ($areas_list as $ar): ?>
                              <option value="<?php echo $ar['id']; ?>"><?php echo htmlspecialchars($ar['name']); ?></option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        <div class="mb-3">
                          <label class="form-label small fw-bold">Price Range (₱)</label>
                          <div class="input-group input-group-sm mb-2">
                            <span class="input-group-text">Min</span>
                            <input type="number" name="min_price" id="filter_min_price" class="form-control" placeholder="0">
                          </div>
                          <div class="input-group input-group-sm">
                            <span class="input-group-text">Max</span>
                            <input type="number" name="max_price" id="filter_max_price" class="form-control" placeholder="Any">
                          </div>
                        </div>
                      </form>
                    </div>
                  </div>

                  <!-- Live Search Box -->
                  <div class="search-wrapper">
                    <input type="text" id="liveSearch" class="search-pill" placeholder="Search products..." value="<?php echo htmlspecialchars($search_term); ?>">
                    <div class="search-btn">
                      <i class="bi bi-search"></i>
                    </div>
                  </div>
              </div>
          </div>

          <p class="text-muted small mb-4" id="resultsCount">Showing <?php echo count($posts); ?> available products.</p>

          <!-- Product Grid -->
          <div class="row g-4" id="productGrid">
            <?php if (empty($posts)): ?>
              <div class="col-12">
                <div class="alert alert-info border-0 shadow-sm">
                    No products found matching your criteria.
                </div>
              </div>
            <?php else: ?>
              <?php foreach ($posts as $post): ?>
                <div class="col-12 col-sm-6 col-md-4">
                  <div class="card product-box h-100 border-0 shadow-sm">
                    <?php
                      $image_src = !empty($post['image_path']) ? '../' . htmlspecialchars($post['image_path']) : 'https://via.placeholder.com/300x200.png?text=No+Image';
                    ?>
                    <div class="ratio ratio-4x3">
                        <img src="<?php echo $image_src; ?>" class="card-img-top object-fit-cover" alt="<?php echo htmlspecialchars($post['title']); ?>">
                    </div>
                    <div class="card-body">
                      <h6 class="card-title fw-bold mb-2"><?php echo htmlspecialchars($post['title']); ?></h6>
                      <p class="card-text mb-2"><span class="badge bg-success-subtle text-success border border-success-subtle"><?php echo htmlspecialchars($post['produce_name']); ?></span></p>
                      <h5 class="card-text text-primary fw-bold mb-3">₱ <?php echo htmlspecialchars(number_format($post['price'], 2)); ?> <small class="text-muted fw-normal">/ <?php echo htmlspecialchars($post['unit']); ?></small></h5>
                       <p class="card-text text-muted mb-0" style="font-size: 0.85rem;">
                          <i class="bi bi-person me-1"></i> <?php echo htmlspecialchars($post['farmer_first_name'] . ' ' . $post['farmer_last_name']); ?><br>
                          <i class="bi bi-geo-alt me-1"></i> <?php echo htmlspecialchars($post['area_name']); ?>
                       </p>
                    </div>
                    <div class="card-footer bg-white border-0 pt-0 pb-3">
                        <a href="view_post.php?id=<?php echo $post['id']; ?>" class="btn btn-primary btn-sm w-100 rounded-pill">View Details</a>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

        </div>
      </section>

    </div>
  </main>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
        const productGrid = document.getElementById('productGrid');
        const resultsCount = document.getElementById('resultsCount');
        const liveSearch = document.getElementById('liveSearch');
        const filterProduce = document.getElementById('filter_produce');
        const filterArea = document.getElementById('filter_area');
        const filterMinPrice = document.getElementById('filter_min_price');
        const filterMaxPrice = document.getElementById('filter_max_price');
        const filterBadge = document.getElementById('filterBadge');
        const resetBtn = document.getElementById('resetFilters');

        let debounceTimer;

        function updateFilterBadge() {
            const hasFilters = filterProduce.value || filterArea.value || filterMinPrice.value || filterMaxPrice.value;
            if (hasFilters) {
                filterBadge.classList.remove('d-none');
            } else {
                filterBadge.classList.add('d-none');
            }
        }

        function fetchPosts() {
            updateFilterBadge();
            const search = liveSearch.value;
            const produceId = filterProduce.value;
            const areaId = filterArea.value;
            const minPrice = filterMinPrice.value;
            const maxPrice = filterMaxPrice.value;

            const params = new URLSearchParams({
                search: search,
                produce_id: produceId,
                area_id: areaId,
                min_price: minPrice,
                max_price: maxPrice
            });

            fetch(`get_posts.php?${params.toString()}`)
                .then(response => response.json())
                .then(posts => {
                    updateGrid(posts);
                })
                .catch(error => console.error('Error fetching posts:', error));
        }

        function updateGrid(posts) {
            resultsCount.innerText = `Showing ${posts.length} available products.`;
            
            if (posts.length === 0) {
                productGrid.innerHTML = `
                    <div class="col-12">
                        <div class="alert alert-info border-0 shadow-sm">
                            No products found matching your criteria.
                        </div>
                    </div>
                `;
                return;
            }

            let html = '';
            posts.forEach(post => {
                const imageSrc = post.image_path ? '../' + post.image_path : 'https://via.placeholder.com/300x200.png?text=No+Image';
                const price = parseFloat(post.price).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                
                html += `
                    <div class="col-12 col-sm-6 col-md-4">
                      <div class="card product-box h-100 border-0 shadow-sm">
                        <div class="ratio ratio-4x3">
                            <img src="${imageSrc}" class="card-img-top object-fit-cover" alt="${post.title}">
                        </div>
                        <div class="card-body">
                          <h6 class="card-title fw-bold mb-2">${post.title}</h6>
                          <p class="card-text mb-2"><span class="badge bg-success-subtle text-success border border-success-subtle">${post.produce_name}</span></p>
                          <h5 class="card-text text-primary fw-bold mb-3">₱ ${price} <small class="text-muted fw-normal">/ ${post.unit}</small></h5>
                           <p class="card-text text-muted mb-0" style="font-size: 0.85rem;">
                              <i class="bi bi-person me-1"></i> ${post.farmer_first_name} ${post.farmer_last_name}<br>
                              <i class="bi bi-geo-alt me-1"></i> ${post.area_name}
                           </p>
                        </div>
                        <div class="card-footer bg-white border-0 pt-0 pb-3">
                            <a href="view_post.php?id=${post.id}" class="btn btn-primary btn-sm w-100 rounded-pill">View Details</a>
                        </div>
                      </div>
                    </div>
                `;
            });
            productGrid.innerHTML = html;
        }

        liveSearch.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(fetchPosts, 300);
        });

        [filterProduce, filterArea].forEach(el => {
            el.addEventListener('change', fetchPosts);
        });

        [filterMinPrice, filterMaxPrice].forEach(el => {
            el.addEventListener('input', () => {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(fetchPosts, 500);
            });
        });

        resetBtn.addEventListener('click', function() {
            filterProduce.value = '';
            filterArea.value = '';
            filterMinPrice.value = '';
            filterMaxPrice.value = '';
            fetchPosts();
        });
    });
  </script>

<?php include '../footer/footerbuyer.php'; ?>
