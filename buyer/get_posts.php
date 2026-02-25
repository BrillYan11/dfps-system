<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'BUYER') {
    exit;
}

$search_term = filter_input(INPUT_GET, 'search', FILTER_UNSAFE_RAW) ?? '';
$filter_produce = filter_input(INPUT_GET, 'produce_id', FILTER_VALIDATE_INT);
$filter_area = filter_input(INPUT_GET, 'area_id', FILTER_VALIDATE_INT);
$min_price = filter_input(INPUT_GET, 'min_price', FILTER_VALIDATE_FLOAT);
$max_price = filter_input(INPUT_GET, 'max_price', FILTER_VALIDATE_FLOAT);

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
$posts = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

header('Content-Type: application/json');
echo json_encode($posts);
