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
if (defined('WP_DEBUG') && WP_DEBUG && is_admin()) {
    add_action('admin_footer', 'gf_reports_debug_menu');
    // Include chart debugging only when needed
    add_action('admin_init', function() {
        if (file_exists(GF_REPORTS_PLUGIN_PATH . 'debug-chart.php')) {
            include_once(GF_REPORTS_PLUGIN_PATH . 'debug-chart.php');
        }
    });
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
    // Debug: Check what hook we're on
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('GF Reports Debug - Hook: ' . $hook);
    }
    
    if ($hook !== 'forms_page_gf-reports') {
        return;
    }

    // Enqueue Chart.js from CDN
    wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js', array(), '3.9.1', true);
    
    // Enqueue custom scripts and styles
    wp_enqueue_script('gf-reports-admin', GF_REPORTS_PLUGIN_URL . 'js/admin.js', array('jquery', 'chartjs'), GF_REPORTS_VERSION, true);
    wp_enqueue_style('gf-reports-admin', GF_REPORTS_PLUGIN_URL . 'css/admin.css', array(), GF_REPORTS_VERSION);
    
    // Localize script for AJAX
    wp_localize_script('gf-reports-admin', 'gf_reports_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('gf_reports_nonce')
    ));
    
    // Debug: Log that scripts were enqueued
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('GF Reports Debug - Scripts and styles enqueued for hook: ' . $hook);
    }
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
    $selected_form = isset($_GET['form_id']) ? $_GET['form_id'] : 0;
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
                        <option value="all" <?php selected($selected_form, 'all'); ?>>All Forms</option>
                        <?php foreach ($forms as $form): ?>
                            <option value="<?php echo esc_attr($form['id']); ?>" <?php selected($selected_form, $form['id']); ?>>
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
                            <?php if ($form['id'] != $selected_form && $selected_form !== 'all'): ?>
                                <option value="<?php echo esc_attr($form['id']); ?>" <?php selected(isset($_GET['compare_form_id']) ? $_GET['compare_form_id'] : '', $form['id']); ?>>
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
                <?php if ($selected_form === 'all'): ?>
                <div class="alignleft actions">
                    <label for="chart_view" class="screen-reader-text">Chart View</label>
                    <select name="chart_view" id="chart_view">
                        <option value="individual" <?php selected(isset($_GET['chart_view']) ? $_GET['chart_view'] : 'individual', 'individual'); ?>>Individual Forms</option>
                        <option value="aggregated" <?php selected(isset($_GET['chart_view']) ? $_GET['chart_view'] : '', 'aggregated'); ?>>Aggregated Total</option>
                    </select>
                </div>
                <?php endif; ?>
                <div class="alignleft actions">
                    <label for="start_date" class="screen-reader-text">Start Date</label>
                    <input type="date" name="start" id="start_date" value="<?php echo esc_attr($start_date); ?>">
                    <label for="end_date" class="screen-reader-text">End Date</label>
                    <input type="date" name="end" id="end_date" value="<?php echo esc_attr($end_date); ?>">
                    <input type="hidden" name="page" value="gf-reports">
                    <input type="submit" class="button" value="Generate Report">
                    <?php if ($selected_form): ?>
                        <button type="button" class="button" id="export-csv">Export CSV</button>
                        <button type="button" class="button" id="export-pdf">Export PDF</button>
                    <?php endif; ?>
                </div>
                <br class="clear">
            </div>
        </form>
        <?php
        $compare_form = isset($_GET['compare_form_id']) ? $_GET['compare_form_id'] : 0;
        $show_by = isset($_GET['show_by']) ? $_GET['show_by'] : 'total';
        
        // Debug: Log submitted values
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('GF Reports Debug - Submitted values: form_id=' . $selected_form . ', compare_form_id=' . $compare_form . ', show_by=' . $show_by . ', start=' . $start_date . ', end=' . $end_date);
        }
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
                
                // Handle "All Forms" selection
                if ($selected_form === 'all') {
                    // Debug: Log that we're processing all forms
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('GF Reports Debug - Processing All Forms selection');
                    }
                    
                    // Aggregate data from all forms
                    $all_forms_data = array();
                    $total_entries_all_forms = 0;
                    $total_revenue_all_forms = 0;
                    $all_daily_entries = array();
                    
                    foreach ($forms as $form) {
                        $form_id = $form['id'];
                        $entry_count = GFAPI::count_entries($form_id, $search_criteria);
                        $entries = GFAPI::get_entries($form_id, $search_criteria, null, array('offset' => 0, 'page_size' => 1000));
                        
                        // Calculate revenue for this form
                        $form_revenue = 0;
                        $product_fields = array();
                        foreach ($form['fields'] as $field) {
                            if (isset($field['type']) && $field['type'] === 'product') {
                                $product_fields[] = $field['id'];
                            }
                        }
                        
                        if (!empty($product_fields)) {
                            foreach ($entries as $entry) {
                                foreach ($product_fields as $pid) {
                                    $val = rgar($entry, $pid);
                                    if (is_numeric($val)) {
                                        $form_revenue += floatval($val);
                                    } elseif (is_array($val) && isset($val['price'])) {
                                        $form_revenue += floatval($val['price']);
                                    } elseif (is_string($val)) {
                                        if (preg_match('/([\d\.,]+)/', $val, $matches)) {
                                            $form_revenue += floatval(str_replace(',', '', $matches[1]));
                                        }
                                    }
                                }
                            }
                        }
                        
                        // Get daily entries for this form
                        $form_daily_entries = gf_reports_get_daily_entries($form_id, $start_date, $end_date);
                        
                        // Aggregate daily entries
                        foreach ($form_daily_entries as $date => $count) {
                            if (!isset($all_daily_entries[$date])) {
                                $all_daily_entries[$date] = 0;
                            }
                            $all_daily_entries[$date] += $count;
                        }
                        
                        $total_entries_all_forms += $entry_count;
                        $total_revenue_all_forms += $form_revenue;
                        
                        $all_forms_data[] = array(
                            'form_id' => $form_id,
                            'form_title' => $form['title'],
                            'entry_count' => $entry_count,
                            'revenue' => $form_revenue,
                            'has_products' => !empty($product_fields)
                        );
                    }
                    
                    // Calculate averages
                    $days_count = count($all_daily_entries);
                    $avg_per_day = $days_count > 0 ? array_sum($all_daily_entries) / $days_count : 0;
                    
                    // Use aggregated data for chart
                    $daily_entries = $all_daily_entries;
                    $entry_count = $total_entries_all_forms;
                    $total_revenue = $total_revenue_all_forms;
                    $form = array('title' => 'All Forms');
                    $product_fields = array(); // Will be determined by checking if any form has products
                    
                    // Check if any form has product fields
                    foreach ($all_forms_data as $form_data) {
                        if ($form_data['has_products']) {
                            $product_fields = array('has_products' => true);
                            break;
                        }
                    }
                    
                    // Debug: Log aggregated data
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('GF Reports Debug - All Forms processed. Total entries: ' . $total_entries_all_forms . ', Total revenue: ' . $total_revenue_all_forms . ', Forms count: ' . count($all_forms_data));
                    }
                    
                } else {
                    // Debug: Log that we're processing single form
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('GF Reports Debug - Processing single form: ' . $selected_form);
                    }
                    
                    // Single form processing (existing code)
                    $entry_count = GFAPI::count_entries($selected_form, $search_criteria);
                    $entries = GFAPI::get_entries($selected_form, $search_criteria, null, array('offset' => 0, 'page_size' => 1000));
                    $form = GFAPI::get_form($selected_form);
                    $product_fields = array();
                    foreach ($form['fields'] as $field) {
                        if (isset($field['type']) && $field['type'] === 'product') {
                            $product_fields[] = $field['id'];
                        }
                    }
                    $total_revenue = 0;
                    if (!empty($product_fields)) {
                        foreach ($entries as $entry) {
                            foreach ($product_fields as $pid) {
                                $val = rgar($entry, $pid);
                                if (is_numeric($val)) {
                                    $total_revenue += floatval($val);
                                } elseif (is_array($val) && isset($val['price'])) {
                                    $total_revenue += floatval($val['price']);
                                } elseif (is_string($val)) {
                                    if (preg_match('/([\d\.,]+)/', $val, $matches)) {
                                        $total_revenue += floatval(str_replace(',', '', $matches[1]));
                                    }
                                }
                            }
                        }
                    }
                    // Calculate daily entries for chart and per-day summary
                    $daily_entries = gf_reports_get_daily_entries($selected_form, $start_date, $end_date);
                    $days_count = count($daily_entries);
                    $avg_per_day = $days_count > 0 ? array_sum($daily_entries) / $days_count : 0;
                }
                // Comparison form stats
                $compare_stats = null;
                if ($compare_form && $selected_form !== 'all') {
                    $compare_entry_count = GFAPI::count_entries($compare_form, $search_criteria);
                    $compare_entries = GFAPI::get_entries($compare_form, $search_criteria, null, array('offset' => 0, 'page_size' => 1000));
                    $compare_form_obj = GFAPI::get_form($compare_form);
                    $compare_product_fields = array();
                    foreach ($compare_form_obj['fields'] as $field) {
                        if (isset($field['type']) && $field['type'] === 'product') {
                            $compare_product_fields[] = $field['id'];
                        }
                    }
                    $compare_total_revenue = 0;
                    if (!empty($compare_product_fields)) {
                        foreach ($compare_entries as $entry) {
                            foreach ($compare_product_fields as $pid) {
                                $val = rgar($entry, $pid);
                                if (is_numeric($val)) {
                                    $compare_total_revenue += floatval($val);
                                } elseif (is_array($val) && isset($val['price'])) {
                                    $compare_total_revenue += floatval($val['price']);
                                } elseif (is_string($val)) {
                                    if (preg_match('/([\d\.,]+)/', $val, $matches)) {
                                        $compare_total_revenue += floatval(str_replace(',', '', $matches[1]));
                                    }
                                }
                            }
                        }
                    }
                    $compare_stats = array(
                        'entry_count' => $compare_entry_count,
                        'form_title' => $compare_form_obj['title'],
                        'total_revenue' => !empty($compare_product_fields) ? $compare_total_revenue : null
                    );
                }
                // For compare form
                $compare_daily_entries = ($compare_form && $selected_form !== 'all') ? gf_reports_get_daily_entries($compare_form, $start_date, $end_date) : [];
                $compare_days_count = count($compare_daily_entries);
                $compare_avg_per_day = $compare_days_count > 0 ? array_sum($compare_daily_entries) / $compare_days_count : 0;
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
                        <?php if ($selected_form === 'all'): ?>
                            <!-- Show breakdown for all forms -->
                            <?php foreach ($all_forms_data as $form_data): ?>
                            <tr>
                                <td><?php echo esc_html($form_data['form_title']); ?></td>
                                <?php if ($show_by === 'per_day'): ?>
                                    <td><?php echo $days_count > 0 ? number_format($form_data['entry_count'] / $days_count, 2) : '0.00'; ?></td>
                                <?php else: ?>
                                    <td><?php echo number_format($form_data['entry_count']); ?></td>
                                <?php endif; ?>
                                <td><?php echo ($start_date && $end_date) ? (date('M j, Y', strtotime($start_date)) . ' - ' . date('M j, Y', strtotime($end_date))) : '—'; ?></td>
                                <td><?php echo $form_data['has_products'] ? ('$' . number_format($form_data['revenue'], 2)) : 'N/A'; ?></td>
                                <td><?php echo $show_by === 'per_day' ? 'Per Day' : 'Total'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <!-- Show totals row -->
                            <tr style="background-color: #f9f9f9; font-weight: bold;">
                                <td><strong>Total (All Forms)</strong></td>
                                <?php if ($show_by === 'per_day'): ?>
                                    <td><strong><?php echo number_format($avg_per_day, 2); ?></strong></td>
                                <?php else: ?>
                                    <td><strong><?php echo number_format($entry_count); ?></strong></td>
                                <?php endif; ?>
                                <td><?php echo ($start_date && $end_date) ? (date('M j, Y', strtotime($start_date)) . ' - ' . date('M j, Y', strtotime($end_date))) : '—'; ?></td>
                                <td><strong><?php echo !empty($product_fields) ? ('$' . number_format($total_revenue, 2)) : 'N/A'; ?></strong></td>
                                <td><?php echo $show_by === 'per_day' ? 'Per Day' : 'Total'; ?></td>
                            </tr>
                        <?php else: ?>
                            <!-- Show single form data -->
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
                        <?php endif; ?>
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
                    <h3><?php echo $selected_form === 'all' ? 'All Forms - Entries Over Time' : 'Entries Over Time'; ?></h3>
                    <canvas id="entriesChart" width="400" height="200"></canvas>
                    <div id="chartjs-no-data" style="display:none; color:#888; text-align:center; margin-top:20px;">No data for this period.</div>
                </div>
                <?php
                // Add inline script to footer to ensure proper loading order
                $chart_labels = array_keys($daily_entries);
                $chart_data_values = array_values($show_by === 'per_day' ? $daily_entries : [array_sum($daily_entries)]);
                
                // Debug: Log chart data
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('GF Reports Debug - Chart labels: ' . print_r($chart_labels, true));
                    error_log('GF Reports Debug - Chart data: ' . print_r($chart_data_values, true));
                    error_log('GF Reports Debug - Show by: ' . $show_by);
                    error_log('GF Reports Debug - Daily entries: ' . print_r($daily_entries, true));
                }
                
                $chart_script = "
                console.log('GF Reports Debug - Inline script loaded');
                console.log('GF Reports Debug - PHP Chart labels:', " . json_encode($chart_labels) . ");
                console.log('GF Reports Debug - PHP Chart data:', " . json_encode($chart_data_values) . ");
                window.chartMode = " . json_encode($show_by) . ";
                window.chartData = {
                    labels: " . json_encode($chart_labels) . ",
                    data: " . json_encode($chart_data_values) . "
                };
                window.selectedFormLabel = " . json_encode($selected_form === 'all' ? 'All Forms' : $form['title']) . ";";
                
                // Add individual form data for "All Forms" chart
                if ($selected_form === 'all') {
                    $chart_view = isset($_GET['chart_view']) ? $_GET['chart_view'] : 'individual';
                    
                    $chart_script .= "
                window.chartView = " . json_encode($chart_view) . ";
                window.individualFormsData = [];";
                    
                    // Define colors for different forms
                    $colors = array(
                        '#2271b1', '#34c759', '#ff9500', '#ff3b30', '#af52de',
                        '#5856d6', '#007aff', '#5ac8fa', '#ffcc02', '#ff9500'
                    );
                    
                    foreach ($all_forms_data as $index => $form_data) {
                        if ($form_data['entry_count'] > 0) { // Only include forms with entries
                            $form_daily_entries = gf_reports_get_daily_entries($form_data['form_id'], $start_date, $end_date);
                            $form_data_values = array_values($show_by === 'per_day' ? $form_daily_entries : [array_sum($form_daily_entries)]);
                            $color_index = $index % count($colors);
                            
                            $chart_script .= "
                window.individualFormsData.push({
                    label: " . json_encode($form_data['form_title']) . ",
                    data: " . json_encode($form_data_values) . ",
                    borderColor: " . json_encode($colors[$color_index]) . ",
                    backgroundColor: " . json_encode(str_replace(')', ', 0.1)', $colors[$color_index])) . ",
                    borderWidth: 2,
                    fill: false,
                    tension: 0.4,
                    pointBackgroundColor: " . json_encode($colors[$color_index]) . ",
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 3,
                    pointHoverRadius: 5
                });";
                        }
                    }
                    
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('GF Reports Debug - Individual forms data prepared for chart. Chart view: ' . $chart_view);
                    }
                }
                
                if ($compare_form) {
                    $compare_data_values = array_values($show_by === 'per_day' ? $compare_daily_entries : [array_sum($compare_daily_entries)]);
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('GF Reports Debug - Compare data: ' . print_r($compare_data_values, true));
                    }
                    $chart_script .= "
                console.log('GF Reports Debug - PHP Compare data:', " . json_encode($compare_data_values) . ");
                window.compareChartData = {
                    labels: window.chartData.labels,
                    data: " . json_encode($compare_data_values) . "
                };";
                }
                
                wp_add_inline_script('gf-reports-admin', $chart_script, 'before');
                ?>
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
 * AJAX handler for CSV export
 */
add_action('wp_ajax_gf_reports_export_csv', 'gf_reports_export_csv');

function gf_reports_export_csv() {
    check_ajax_referer('gf_reports_nonce', 'nonce');
    
    if (!current_user_can('gravityforms_view_entries')) {
        wp_die('Unauthorized');
    }
    
    $form_id = isset($_POST['form_id']) ? sanitize_text_field($_POST['form_id']) : '';
    $start_date = sanitize_text_field($_POST['start_date']);
    $end_date = sanitize_text_field($_POST['end_date']);
    
    $search_criteria = array('status' => 'active');
    if ($start_date) {
        $search_criteria['start_date'] = $start_date . ' 00:00:00';
    }
    if ($end_date) {
        $search_criteria['end_date'] = $end_date . ' 23:59:59';
    }

    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="gf-reports-' . $form_id . '-' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');

    // Write report summary section
    fputcsv($output, array('Report Summary'));
    fputcsv($output, array('Date Range:', date('M j, Y', strtotime($start_date)) . ' - ' . date('M j, Y', strtotime($end_date))));
    fputcsv($output, array('')); // Empty line for spacing

    if ($form_id === 'all') {
        // Export data for all forms
        $forms = GFAPI::get_forms();
        
        // Write summary headers
        fputcsv($output, array('Form', 'Total Entries', 'Average Per Day', 'Total Revenue'));
        
        $total_entries = 0;
        $total_revenue = 0;
        
        foreach ($forms as $form) {
            $entry_count = GFAPI::count_entries($form['id'], $search_criteria);
            $entries = GFAPI::get_entries($form['id'], $search_criteria);
            $daily_entries = gf_reports_get_daily_entries($form['id'], $start_date, $end_date);
            $days_count = count($daily_entries);
            $avg_per_day = $days_count > 0 ? $entry_count / $days_count : 0;
            
            // Calculate revenue
            $form_revenue = 0;
            $product_fields = array();
            foreach ($form['fields'] as $field) {
                if (isset($field['type']) && $field['type'] === 'product') {
                    $product_fields[] = $field['id'];
                }
            }
            
            if (!empty($product_fields) && !empty($entries)) {
                foreach ($entries as $entry) {
                    foreach ($product_fields as $pid) {
                        $val = rgar($entry, $pid);
                        if (is_numeric($val)) {
                            $form_revenue += floatval($val);
                        }
                    }
                }
            }
            
            fputcsv($output, array(
                $form['title'],
                $entry_count,
                number_format($avg_per_day, 2),
                !empty($product_fields) ? '$' . number_format($form_revenue, 2) : 'N/A'
            ));
            
            $total_entries += $entry_count;
            $total_revenue += $form_revenue;
        }
        
        // Write totals
        fputcsv($output, array(''));
        fputcsv($output, array(
            'TOTAL',
            $total_entries,
            number_format($total_entries / $days_count, 2),
            '$' . number_format($total_revenue, 2)
        ));
        
    } else {
        // Export data for single form
        $form = GFAPI::get_form($form_id);
        $entries = GFAPI::get_entries($form_id, $search_criteria);
        $entry_count = GFAPI::count_entries($form_id, $search_criteria);
        $daily_entries = gf_reports_get_daily_entries($form_id, $start_date, $end_date);
        $days_count = count($daily_entries);
        $avg_per_day = $days_count > 0 ? $entry_count / $days_count : 0;
        
        // Calculate revenue
        $total_revenue = 0;
        $product_fields = array();
        foreach ($form['fields'] as $field) {
            if (isset($field['type']) && $field['type'] === 'product') {
                $product_fields[] = $field['id'];
            }
        }
        
        if (!empty($product_fields) && !empty($entries)) {
            foreach ($entries as $entry) {
                foreach ($product_fields as $pid) {
                    $val = rgar($entry, $pid);
                    if (is_numeric($val)) {
                        $total_revenue += floatval($val);
                    }
                }
            }
        }

        // Write summary data
        fputcsv($output, array('Form:', $form['title']));
        fputcsv($output, array('Total Entries:', $entry_count));
        fputcsv($output, array('Average Per Day:', number_format($avg_per_day, 2)));
        fputcsv($output, array('Total Revenue:', !empty($product_fields) ? '$' . number_format($total_revenue, 2) : 'N/A'));
        fputcsv($output, array('')); // Empty line for spacing

        // Write daily breakdown
        fputcsv($output, array('Daily Breakdown'));
        fputcsv($output, array('Date', 'Entries'));
        foreach ($daily_entries as $date => $count) {
            fputcsv($output, array($date, $count));
        }
        
        fputcsv($output, array('')); // Empty line for spacing
        
        // Write entry details
        fputcsv($output, array('Entry Details'));
        
        // Get field headers
        $headers = array('Entry ID', 'Date Created');
        foreach ($form['fields'] as $field) {
            if ($field['type'] !== 'section' && $field['type'] !== 'html') {
                $headers[] = $field['label'];
            }
        }
        fputcsv($output, $headers);
        
        // Write entry data
        foreach ($entries as $entry) {
            $row = array($entry['id'], $entry['date_created']);
            foreach ($form['fields'] as $field) {
                if ($field['type'] !== 'section' && $field['type'] !== 'html') {
                    $row[] = rgar($entry, $field['id']);
                }
            }
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit;
}

/**
 * AJAX handler for PDF export
 */
add_action('wp_ajax_gf_reports_export_pdf', 'gf_reports_export_pdf');

function gf_reports_export_pdf() {
    check_ajax_referer('gf_reports_nonce', 'nonce');
    
    if (!current_user_can('gravityforms_view_entries')) {
        wp_die('Unauthorized');
    }
    
    // Check if DOMPDF is available
    if (!class_exists('DOMPDF')) {
        require_once GF_REPORTS_PLUGIN_DIR . 'vendor/dompdf/autoload.inc.php';
    }
    
    $form_id = isset($_POST['form_id']) ? sanitize_text_field($_POST['form_id']) : '';
    $start_date = sanitize_text_field($_POST['start_date']);
    $end_date = sanitize_text_field($_POST['end_date']);
    $chart_data = isset($_POST['chart_data']) ? $_POST['chart_data'] : '';
    
    // Generate HTML content
    $html = '<html><head><style>
        body { font-family: Arial, sans-serif; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 8px; border: 1px solid #ddd; }
        th { background-color: #f5f5f5; }
        h1, h2 { color: #333; }
        .chart-container { margin: 20px 0; }
    </style></head><body>';
    
    // Add report header
    $html .= '<h1>Gravity Forms Report</h1>';
    $html .= '<p>Date Range: ' . date('M j, Y', strtotime($start_date)) . ' - ' . date('M j, Y', strtotime($end_date)) . '</p>';
    
    // Add chart image if provided
    if (!empty($chart_data)) {
        $html .= '<div class="chart-container">';
        $html .= '<img src="' . $chart_data . '" style="width: 100%; max-width: 800px;">';
        $html .= '</div>';
    }
    
    // Add report data (similar to CSV export but in HTML format)
    if ($form_id === 'all') {
        // All forms summary table
        $html .= '<h2>All Forms Summary</h2>';
        $html .= '<table><tr><th>Form</th><th>Total Entries</th><th>Average Per Day</th><th>Total Revenue</th></tr>';
        
        $forms = GFAPI::get_forms();
        foreach ($forms as $form) {
            $entry_count = GFAPI::count_entries($form['id'], array('status' => 'active'));
            $daily_entries = gf_reports_get_daily_entries($form['id'], $start_date, $end_date);
            $days_count = count($daily_entries);
            $avg_per_day = $days_count > 0 ? $entry_count / $days_count : 0;
            
            $html .= sprintf(
                '<tr><td>%s</td><td>%d</td><td>%.2f</td><td>%s</td></tr>',
                esc_html($form['title']),
                $entry_count,
                $avg_per_day,
                'N/A' // Revenue calculation omitted for brevity
            );
        }
        
        $html .= '</table>';
    } else {
        // Single form details
        $form = GFAPI::get_form($form_id);
        $entries = GFAPI::get_entries($form_id, array('status' => 'active'));
        $entry_count = count($entries);
        
        $html .= '<h2>' . esc_html($form['title']) . ' - Summary</h2>';
        $html .= '<table>';
        $html .= '<tr><td>Total Entries</td><td>' . $entry_count . '</td></tr>';
        $html .= '<tr><td>Average Per Day</td><td>' . number_format($entry_count / count(gf_reports_get_daily_entries($form_id, $start_date, $end_date)), 2) . '</td></tr>';
        $html .= '</table>';
        
        // Daily breakdown
        $html .= '<h2>Daily Breakdown</h2>';
        $html .= '<table><tr><th>Date</th><th>Entries</th></tr>';
        
        $daily_entries = gf_reports_get_daily_entries($form_id, $start_date, $end_date);
        foreach ($daily_entries as $date => $count) {
            $html .= sprintf('<tr><td>%s</td><td>%d</td></tr>', $date, $count);
        }
        
        $html .= '</table>';
    }
    
    $html .= '</body></html>';
    
    // Generate PDF
    $dompdf = new \Dompdf\Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    // Output PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="gf-reports-' . $form_id . '-' . date('Y-m-d') . '.pdf"');
    echo $dompdf->output();
    exit;
}