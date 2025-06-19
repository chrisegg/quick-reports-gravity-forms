<?php
/*
Plugin Name: GF Reports
Description: Adds a simple reporting dashboard for Gravity Forms entries.
Version: 1.0
Author: Your Name
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('GF_REPORTS_VERSION', '1.0');
define('GF_REPORTS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GF_REPORTS_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Hook into WordPress admin menu with proper timing
add_action('admin_menu', 'gf_reports_add_menu', 99);

// Fallback: Try again after Gravity Forms has loaded
add_action('admin_init', 'gf_reports_add_menu_fallback');

// Debug: Check if menu was added (only in debug mode)
if (defined('WP_DEBUG') && WP_DEBUG) {
    add_action('admin_footer', 'gf_reports_debug_menu');
}

// Enqueue admin scripts and styles
add_action('admin_enqueue_scripts', 'gf_reports_enqueue_scripts');

/**
 * Add the Reports submenu under Forms
 */
function gf_reports_add_menu() {
    // Check if Gravity Forms is active
    if (!class_exists('GFFormsModel')) {
        return;
    }
    
    // Check if we're in admin
    if (!is_admin()) {
        return;
    }
    
    // Add the submenu page with a more basic capability
    add_submenu_page(
        'gf_edit_forms',                    // Parent slug
        'Reports',                          // Page title
        'Reports',                          // Menu title
        'manage_options',                   // Capability - changed to basic WordPress capability
        'gf-reports',                       // Menu slug
        'gf_reports_render_page'            // Callback
    );
}

/**
 * Fallback method to add menu if primary method fails
 */
function gf_reports_add_menu_fallback() {
    // Only run if the primary method didn't work
    global $submenu;
    
    // Check if Gravity Forms is active
    if (!class_exists('GFFormsModel')) {
        return;
    }
    
    // Check if we're in admin
    if (!is_admin()) {
        return;
    }
    
    // Check if our menu already exists
    if (isset($submenu['gf_edit_forms'])) {
        foreach ($submenu['gf_edit_forms'] as $item) {
            if ($item[2] === 'gf-reports') {
                return; // Menu already exists
            }
        }
    }
    
    // Add the submenu page as fallback with basic capability
    add_submenu_page(
        'gf_edit_forms',                    // Parent slug
        'Reports',                          // Page title
        'Reports',                          // Menu title
        'manage_options',                   // Capability - changed to basic WordPress capability
        'gf-reports',                       // Menu slug
        'gf_reports_render_page'            // Callback
    );
}

/**
 * Debug function to check if menu was added (only in debug mode)
 */
function gf_reports_debug_menu() {
    global $submenu;
    
    echo '<!-- GF Reports Debug: ';
    if (isset($submenu['gf_edit_forms'])) {
        echo 'Gravity Forms menu exists with ' . count($submenu['gf_edit_forms']) . ' items. ';
        foreach ($submenu['gf_edit_forms'] as $item) {
            echo 'Item: ' . $item[0] . ' (slug: ' . $item[2] . ') ';
        }
    } else {
        echo 'Gravity Forms menu does not exist. ';
    }
    echo '-->';
}

/**
 * Enqueue scripts and styles for the reports page
 */
function gf_reports_enqueue_scripts($hook) {
    if ($hook !== 'gravity-forms_page_gf-reports') {
        return;
    }

    // Enqueue Chart.js from CDN
    wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.9.1', true);
    
    // Enqueue custom scripts and styles
    wp_enqueue_script('gf-reports-admin', GF_REPORTS_PLUGIN_URL . 'js/admin.js', array('jquery', 'chartjs'), GF_REPORTS_VERSION, true);
    wp_enqueue_style('gf-reports-admin', GF_REPORTS_PLUGIN_URL . 'css/admin.css', array(), GF_REPORTS_VERSION);
    
    // Localize script for AJAX
    wp_localize_script('gf-reports-admin', 'gf_reports_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('gf_reports_nonce')
    ));
}

/**
 * Calculate total revenue from form entries
 */
function gf_reports_calculate_revenue($entries, $form) {
    $total_revenue = 0;
    $product_fields = array();
    
    // Get all product fields from the form
    foreach ($form['fields'] as $field) {
        if (isset($field['type']) && ($field['type'] === 'product' || $field['type'] === 'total')) {
            $product_fields[] = $field['id'];
        }
    }
    
    if (empty($product_fields)) {
        return null; // Return null if no product fields found
    }
    
    foreach ($entries as $entry) {
        foreach ($product_fields as $pid) {
            $val = rgar($entry, $pid);
            
            // Handle different value formats
            if (is_numeric($val)) {
                $total_revenue += floatval($val);
            } elseif (is_array($val) && isset($val['price'])) {
                $total_revenue += floatval($val['price']);
            } elseif (is_string($val)) {
                // Extract numeric value from string (e.g. "$100.00" -> 100.00)
                if (preg_match('/[\d,.]+/', str_replace(['$', ','], '', $val), $matches)) {
                    $total_revenue += floatval($matches[0]);
                }
            }
        }
    }
    
    return $total_revenue;
}

/**
 * Render the main reports page
 */
function gf_reports_render_page() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    
    if (!class_exists('GFFormsModel')) {
        echo '<div class="notice notice-error"><p>Gravity Forms is not active. Please install and activate Gravity Forms to use this add-on.</p></div>';
        return;
    }

    $forms = GFAPI::get_forms();
    $selected_form = isset($_GET['form_id']) ? intval($_GET['form_id']) : 0;
    $start_date = isset($_GET['start']) ? sanitize_text_field($_GET['start']) : date('Y-m-d', strtotime('-30 days'));
    $end_date = isset($_GET['end']) ? sanitize_text_field($_GET['end']) : date('Y-m-d');

    ?>
    <div class="wrap">
        <h1>Gravity Forms Reports</h1>
        <!-- WP Admin TableNav Filters Bar -->
        <form method="GET">
            <div class="tablenav top">
                <div class="alignleft actions">
                    <label for="form_id" class="screen-reader-text">Select form</label>
                    <select name="form_id" id="form_id">
                        <option value="">Select a form</option>
                        <?php foreach ($forms as $form): ?>
                            <option value="<?php echo esc_attr($form['id']); ?>" <?php selected($form['id'], $selected_form); ?>>
                                <?php echo esc_html($form['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="alignleft actions">
                    <label for="compare_form_id" class="screen-reader-text">Compare With</label>
                    <select name="compare_form_id" id="compare_form_id">
                        <option value="">Compare With...</option>
                        <?php foreach ($forms as $form): ?>
                            <?php if ($form['id'] != $selected_form): ?>
                                <option value="<?php echo esc_attr($form['id']); ?>" <?php selected(isset($_GET['compare_form_id']) ? intval($_GET['compare_form_id']) : '', $form['id']); ?>>
                                    <?php echo esc_html($form['title']); ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="alignleft actions">
                    <label for="show_by" class="screen-reader-text">Show by</label>
                    <select name="show_by" id="show_by">
                        <option value="total" <?php selected(isset($_GET['show_by']) ? $_GET['show_by'] : 'total', 'total'); ?>>Total</option>
                        <option value="per_day" <?php selected(isset($_GET['show_by']) ? $_GET['show_by'] : '', 'per_day'); ?>>Per Day</option>
                    </select>
                </div>
                <div class="alignleft actions">
                    <label for="start_date" class="screen-reader-text">Start Date</label>
                    <input type="date" name="start" id="start_date" value="<?php echo esc_attr($start_date); ?>">
                    <label for="end_date" class="screen-reader-text">End Date</label>
                    <input type="date" name="end" id="end_date" value="<?php echo esc_attr($end_date); ?>">
                    <input type="hidden" name="page" value="gf-reports">
                    <input type="submit" class="button" value="Generate Report">
                    <?php if ($selected_form): ?>
                        <button type="button" class="button" id="export-csv">Export CSV</button>
                    <?php endif; ?>
                </div>
                <br class="clear">
            </div>
        </form>
        <?php
        $compare_form = isset($_GET['compare_form_id']) ? intval($_GET['compare_form_id']) : 0;
        $show_by = isset($_GET['show_by']) ? $_GET['show_by'] : 'total';
        ?>
        <?php if ($selected_form): ?>
            <hr>
            <!-- Report Results -->
            <div class="gf-reports-results">
                <?php
                $search_criteria = array('status' => 'active');
                if ($start_date) {
                    $search_criteria['start_date'] = $start_date . ' 00:00:00';
                }
                if ($end_date) {
                    $search_criteria['end_date'] = $end_date . ' 23:59:59';
                }
                // Primary form stats
                $entry_count = GFAPI::count_entries($selected_form, $search_criteria);
                $entries = GFAPI::get_entries($selected_form, $search_criteria, null, array('offset' => 0, 'page_size' => 1000));
                $form = GFAPI::get_form($selected_form);
                $total_revenue = gf_reports_calculate_revenue($entries, $form);
                
                // Comparison form stats
                $compare_stats = null;
                if ($compare_form) {
                    $compare_entry_count = GFAPI::count_entries($compare_form, $search_criteria);
                    $compare_entries = GFAPI::get_entries($compare_form, $search_criteria, null, array('offset' => 0, 'page_size' => 1000));
                    $compare_form_obj = GFAPI::get_form($compare_form);
                    $compare_total_revenue = gf_reports_calculate_revenue($compare_entries, $compare_form_obj);
                    
                    $compare_stats = array(
                        'entry_count' => $compare_entry_count,
                        'form_title' => $compare_form_obj['title'],
                        'total_revenue' => $compare_total_revenue
                    );
                }
                // Calculate daily entries for chart and per-day summary
                $daily_entries = gf_reports_get_daily_entries($selected_form, $start_date, $end_date);
                $days_count = count($daily_entries);
                $avg_per_day = $days_count > 0 ? array_sum($daily_entries) / $days_count : 0;
                // For compare form
                $compare_daily_entries = $compare_form ? gf_reports_get_daily_entries($compare_form, $start_date, $end_date) : [];
                $compare_days_count = count($compare_daily_entries);
                $compare_avg_per_day = $compare_days_count > 0 ? array_sum($compare_daily_entries) / $compare_days_count : 0;
                
                // Calculate revenue per day
                $daily_revenue = array();
                $daily_compare_revenue = array();
                
                if ($total_revenue !== null) {
                    $daily_revenue = gf_reports_get_daily_revenue($selected_form, $start_date, $end_date);
                }
                
                if ($compare_form && $compare_total_revenue !== null) {
                    $daily_compare_revenue = gf_reports_get_daily_revenue($compare_form, $start_date, $end_date);
                }
                
                ?>
                <table class="wp-list-table widefat fixed striped report-summary-table">
                    <thead>
                        <tr>
                            <th>Form</th>
                            <?php if ($show_by === 'per_day'): ?>
                                <th>Entries Per Day</th>
                            <?php else: ?>
                                <th>Total Entries</th>
                            <?php endif; ?>
                            <th>Date Range</th>
                            <th>Total Revenue</th>
                            <th>Show By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php echo esc_html($form['title']); ?></td>
                            <?php if ($show_by === 'per_day'): ?>
                                <td><?php echo number_format($avg_per_day, 2); ?></td>
                            <?php else: ?>
                                <td><?php echo number_format($entry_count); ?></td>
                            <?php endif; ?>
                            <td><?php echo ($start_date && $end_date) ? (date('M j, Y', strtotime($start_date)) . ' - ' . date('M j, Y', strtotime($end_date))) : '—'; ?></td>
                            <td><?php echo !empty($product_fields) ? ('$' . number_format($total_revenue, 2)) : 'N/A'; ?></td>
                            <td><?php echo $show_by === 'per_day' ? 'Per Day' : 'Total'; ?></td>
                        </tr>
                        <?php if ($compare_stats): ?>
                        <tr>
                            <td><?php echo esc_html($compare_stats['form_title']); ?></td>
                            <?php if ($show_by === 'per_day'): ?>
                                <td><?php echo number_format($compare_avg_per_day, 2); ?></td>
                            <?php else: ?>
                                <td><?php echo number_format($compare_stats['entry_count']); ?></td>
                            <?php endif; ?>
                            <td><?php echo ($start_date && $end_date) ? (date('M j, Y', strtotime($start_date)) . ' - ' . date('M j, Y', strtotime($end_date))) : '—'; ?></td>
                            <td><?php echo ($compare_stats['total_revenue'] !== null) ? ('$' . number_format($compare_stats['total_revenue'], 2)) : 'N/A'; ?></td>
                            <td><?php echo $show_by === 'per_day' ? 'Per Day' : 'Total'; ?></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <!-- Chart Container -->
                <div class="chart-container">
                    <div class="chart-header">
                        <h3>Entries Over Time</h3>
                        <?php if ($total_revenue !== null): ?>
                        <div class="chart-toggle">
                            <label>
                                <input type="radio" name="chart_type" value="entries" checked> Entries
                            </label>
                            <label>
                                <input type="radio" name="chart_type" value="revenue"> Revenue
                            </label>
                        </div>
                        <?php endif; ?>
                    </div>
                    <canvas id="entriesChart" width="400" height="200"></canvas>
                    <canvas id="revenueChart" width="400" height="200" style="display: none;"></canvas>
                    <div id="chartjs-no-data" style="display:none; color:#888; text-align:center; margin-top:20px;">No data for this period.</div>
                </div>
                <script>
                // Pass data to JavaScript for charts
                var chartMode = <?php echo json_encode($show_by); ?>;
                var chartData = {
                    labels: <?php echo json_encode(array_keys($daily_entries)); ?>,
                    entries: <?php echo json_encode(array_values($show_by === 'per_day' ? $daily_entries : [array_sum($daily_entries)])); ?>,
                    revenue: <?php echo json_encode(array_values($show_by === 'per_day' ? $daily_revenue : [$total_revenue])); ?>
                };
                <?php if ($compare_form): ?>
                var compareChartData = {
                    labels: chartData.labels,
                    entries: <?php echo json_encode(array_values($show_by === 'per_day' ? $compare_daily_entries : [array_sum($compare_daily_entries)])); ?>,
                    revenue: <?php echo json_encode(array_values($show_by === 'per_day' ? $daily_compare_revenue : [$compare_total_revenue])); ?>
                };
                <?php endif; ?>
                </script>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Get daily entry counts for chart
 */
function gf_reports_get_daily_entries($form_id, $start_date, $end_date) {
    $daily_entries = array();
    
    if (!$start_date || !$end_date) {
        return $daily_entries;
    }

    $current_date = $start_date;
    while (strtotime($current_date) <= strtotime($end_date)) {
        $search_criteria = array(
            'status' => 'active',
            'start_date' => $current_date . ' 00:00:00',
            'end_date' => $current_date . ' 23:59:59'
        );
        
        $count = GFAPI::count_entries($form_id, $search_criteria);
        $daily_entries[date('M j', strtotime($current_date))] = $count;
        
        $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
    }
    
    return $daily_entries;
}

/**
 * Get daily revenue for chart
 */
function gf_reports_get_daily_revenue($form_id, $start_date, $end_date) {
    $daily_revenue = array();
    
    if (!$start_date || !$end_date) {
        return $daily_revenue;
    }

    $current_date = $start_date;
    $form = GFAPI::get_form($form_id);
    
    while (strtotime($current_date) <= strtotime($end_date)) {
        $search_criteria = array(
            'status' => 'active',
            'start_date' => $current_date . ' 00:00:00',
            'end_date' => $current_date . ' 23:59:59'
        );
        
        $entries = GFAPI::get_entries($form_id, $search_criteria);
        $revenue = gf_reports_calculate_revenue($entries, $form);
        $daily_revenue[date('M j', strtotime($current_date))] = $revenue ?: 0;
        
        $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
    }
    
    return $daily_revenue;
}

/**
 * AJAX handler for CSV export
 */
add_action('wp_ajax_gf_reports_export_csv', 'gf_reports_export_csv');

function gf_reports_export_csv() {
    check_ajax_referer('gf_reports_nonce', 'nonce');
    
    if (!current_user_can('gravityforms_view_entries')) {
        wp_die('Unauthorized');
    }
    
    $form_id = intval($_POST['form_id']);
    $start_date = sanitize_text_field($_POST['start_date']);
    $end_date = sanitize_text_field($_POST['end_date']);
    
    $search_criteria = array('status' => 'active');
    if ($start_date) {
        $search_criteria['start_date'] = $start_date . ' 00:00:00';
    }
    if ($end_date) {
        $search_criteria['end_date'] = $end_date . ' 23:59:59';
    }
    
    $entries = GFAPI::get_entries($form_id, $search_criteria, null, array('offset' => 0, 'page_size' => 10000));
    $form = GFAPI::get_form($form_id);
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="gf-reports-' . $form_id . '-' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Write headers
    $headers = array('Entry ID', 'Date Created');
    foreach ($form['fields'] as $field) {
        if ($field['type'] !== 'section' && $field['type'] !== 'html') {
            $headers[] = $field['label'];
        }
    }
    fputcsv($output, $headers);
    
    // Write data
    foreach ($entries as $entry) {
        $row = array($entry['id'], $entry['date_created']);
        foreach ($form['fields'] as $field) {
            if ($field['type'] !== 'section' && $field['type'] !== 'html') {
                $row[] = rgar($entry, $field['id']);
            }
        }
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
} 