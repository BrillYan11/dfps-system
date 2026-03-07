<?php
/**
 * Renders a Bootstrap 5 pagination component.
 * 
 * @param int $current_page Current active page
 * @param int $total_pages Total number of pages
 * @param array $extra_params Optional additional query parameters to include in links
 */
function renderPagination($current_page, $total_pages, $extra_params = []) {
    if ($total_pages <= 1) return;

    // Get current GET parameters
    $params = $_GET;
    // Remove existing 'page' to re-add it correctly for each link
    unset($params['page']);
    // Merge with any specific extra parameters provided for this call
    $params = array_merge($params, $extra_params);
    
    // Helper to build URL for a specific page
    $buildUrl = function($p) use ($params) {
        $params['page'] = $p;
        return '?' . http_build_query($params);
    };
    ?>
    <nav aria-label="Page navigation">
        <ul class="pagination pagination-sm justify-content-center mb-0">
            <!-- Previous Button -->
            <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                <a class="page-link rounded-pill px-3 me-2" href="<?php echo $buildUrl($current_page - 1); ?>" <?php echo ($current_page <= 1) ? 'tabindex="-1" aria-disabled="true"' : ''; ?>>Previous</a>
            </li>

            <!-- Page Number Links -->
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php echo ($current_page == $i) ? 'active' : ''; ?>">
                    <a class="page-link rounded-circle mx-1" href="<?php echo $buildUrl($i); ?>" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>

            <!-- Next Button -->
            <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                <a class="page-link rounded-pill px-3 ms-2" href="<?php echo $buildUrl($current_page + 1); ?>" <?php echo ($current_page >= $total_pages) ? 'tabindex="-1" aria-disabled="true"' : ''; ?>>Next</a>
            </li>
        </ul>
    </nav>
    <?php
}
?>