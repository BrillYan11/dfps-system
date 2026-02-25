<?php
// includes/locations_api.php
header('Content-Type: application/json');

// Base URL for PSGC API
// Example: https://psgc.gitlab.io/api/regions/
$base_url = "https://psgc.gitlab.io/api";

$action = $_GET['action'] ?? '';

function fetch_json($url) {
    // Suppress warnings for HTTP errors (e.g. 404)
    $json = @file_get_contents($url);
    if ($json === false) {
        return json_encode(['error' => 'Failed to fetch data from PSGC API.']);
    }
    return $json;
}

if ($action === 'regions') {
    echo fetch_json("$base_url/regions/");
} elseif ($action === 'provinces' && isset($_GET['region_id'])) {
    $region_id = urlencode($_GET['region_id']);
    echo fetch_json("$base_url/regions/$region_id/provinces/");
} elseif ($action === 'cities' && isset($_GET['province_id'])) {
    $province_id = urlencode($_GET['province_id']);
    echo fetch_json("$base_url/provinces/$province_id/cities-municipalities/");
} else {
    echo json_encode(['error' => 'Invalid action or missing parameters']);
}
?>
