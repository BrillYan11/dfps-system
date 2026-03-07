<?php
session_start();
include '../includes/db.php'; // Correct path to db.php

// 1. Authentication and Authorization Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'FARMER') {
    header("Location: ../login.php");
    exit;
}

$farmer_id = $_SESSION['user_id'];
$area_id = null;

// Fetch farmer's area_id for announcements
$user_stmt = $conn->prepare("SELECT area_id FROM users WHERE id = ?");
$user_stmt->bind_param("i", $farmer_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
if ($user_row = $user_result->fetch_assoc()) {
    $area_id = $user_row['area_id'];
}
$user_stmt->close();

// 2. Fetch Initial Posts (for SSR)
$posts = [];
$posts_query = "
    SELECT
        p.id,
        p.title,
        p.description,
        p.price,
        p.quantity,
        p.unit,
        pr.name AS produce_name,
        a.name AS area_name,
        (SELECT pi.file_path FROM post_images pi WHERE pi.post_id = p.id ORDER BY pi.id ASC LIMIT 1) AS image_path
    FROM posts p
    JOIN produce pr ON p.produce_id = pr.id
    LEFT JOIN areas a ON p.area_id = a.id
    WHERE p.farmer_id = ?
    ORDER BY p.created_at DESC
";
$stmt = $conn->prepare($posts_query);
$stmt->bind_param("i", $farmer_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) { $posts[] = $row; }
$stmt->close();

// Fetch lists for filter
$produce_list = $conn->query("SELECT id, name FROM produce WHERE is_active = 1 ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
$areas_list = $conn->query("SELECT id, name FROM areas ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

// 3. Fetch announcements
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
while ($ann_row = $ann_result->fetch_assoc()) { $announcements[] = $ann_row; }
$ann_stmt->close();

include '../includes/universal_header.php';
?>

  <main class="container-fluid px-4 my-3">
    <div class="row g-3">

      <!-- Sidebar -->
      <aside class="col-12 col-md-3 col-lg-2">
        <div class="card shadow-sm border-0">
          <div class="card-header bg-light border-0 py-3">
            <h6 class="mb-0 fw-bold">Farmer Updates</h6>
          </div>
          <div class="card-body">
            <h6 class="text-success mb-3">Announcements</h6>
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
            <a href="#" class="btn btn-sm btn-link p-0 text-decoration-none">View All</a>
          </div>
        </div>
      </aside>

      <!-- Main Content -->
      <section class="col-12 col-md-9 col-lg-10">
        <div class="panel p-3">

          <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <a href="add_post.php" class="btn btn-success rounded-pill px-4 shadow-sm"><i class="bi bi-plus-circle me-1"></i> Create New Post</a>

            <div class="d-flex align-items-center gap-2">
                <!-- Filter Dropdown -->
                <div class="dropdown">
                  <button class="btn btn-light rounded-circle shadow-sm position-relative" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" title="Filters" id="filterDropdownBtn">
                    <i class="bi bi-filter"></i>
                    <span id="filterBadge" class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle d-none"></span>
                  </button>
                  <div class="dropdown-menu dropdown-menu-end p-3 shadow-lg border-0" style="width: 280px; border-radius: 15px;">
                    <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                      <h6 class="fw-bold mb-0">Filter My Posts</h6>
                      <button type="button" id="resetFilters" class="btn btn-sm text-success p-0">Reset</button>
                    </div>
                    <form id="filterForm">
                      <div class="mb-3">
                        <label class="form-label small fw-bold">Produce Category</label>
                        <select id="filter_produce" class="form-select form-select-sm">
                          <option value="">All Produce</option>
                          <?php foreach ($produce_list as $pr): ?>
                            <option value="<?php echo $pr['id']; ?>"><?php echo htmlspecialchars($pr['name']); ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div class="mb-3">
                        <label class="form-label small fw-bold">Location / Area</label>
                        <select id="filter_area" class="form-select form-select-sm">
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
                          <input type="number" id="filter_min_price" class="form-control" placeholder="0">
                        </div>
                        <div class="input-group input-group-sm">
                          <span class="input-group-text">Max</span>
                          <input type="number" id="filter_max_price" class="form-control" placeholder="Any">
                        </div>
                      </div>
                    </form>
                  </div>
                </div>

                <div class="search-wrapper">
                  <input type="text" id="liveSearch" class="search-pill" placeholder="Search my posts...">
                  <div class="search-btn">
                    <i class="bi bi-search"></i>
                  </div>
                </div>
            </div>
          </div>

          <h4 class="mb-3 fw-bold">My Product Listings</h4>
          <p class="text-muted small mb-4" id="resultsCount">Showing <?php echo count($posts); ?> listings.</p>

          <div class="row g-4" id="productGrid">
            <?php if (empty($posts)): ?>
              <div class="col-12">
                <div class="alert alert-info border-0 shadow-sm">
                    You have no active listings matching your criteria.
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
                      <p class="card-text mb-2"><span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle"><?php echo htmlspecialchars($post['produce_name']); ?></span></p>
                      <h5 class="card-text text-success fw-bold mb-3">₱ <?php echo htmlspecialchars(number_format($post['price'], 2)); ?> <small class="text-muted fw-normal">/ <?php echo htmlspecialchars($post['unit']); ?></small></h5>
                       <p class="card-text text-muted mb-0 small">
                          <i class="bi bi-box-seam me-1"></i> Stock: <?php echo htmlspecialchars($post['quantity']); ?> <?php echo htmlspecialchars($post['unit']); ?><br>
                          <i class="bi bi-geo-alt me-1"></i> <?php echo htmlspecialchars($post['area_name']); ?>
                       </p>
                    </div>
                    <div class="card-footer bg-white border-0 pt-0 pb-3">
                        <a href="edit_post.php?id=<?php echo $post['id']; ?>" class="btn btn-outline-success btn-sm w-100 rounded-pill">Edit Details</a>
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
            resultsCount.innerText = `Showing ${posts.length} listings.`;
            
            if (posts.length === 0) {
                productGrid.innerHTML = `
                    <div class="col-12">
                        <div class="alert alert-info border-0 shadow-sm">
                            You have no active listings matching your criteria.
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
                          <p class="card-text mb-2"><span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">${post.produce_name}</span></p>
                          <h5 class="card-text text-success fw-bold mb-3">₱ ${price} <small class="text-muted fw-normal">/ ${post.unit}</small></h5>
                           <p class="card-text text-muted mb-0 small">
                              <i class="bi bi-box-seam me-1"></i> Stock: ${post.quantity} ${post.unit}<br>
                              <i class="bi bi-geo-alt me-1"></i> ${post.area_name}
                           </p>
                        </div>
                        <div class="card-footer bg-white border-0 pt-0 pb-3">
                            <a href="edit_post.php?id=${post.id}" class="btn btn-outline-success btn-sm w-100 rounded-pill">Edit Details</a>
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

<?php include '../includes/universal_footer.php'; ?>
