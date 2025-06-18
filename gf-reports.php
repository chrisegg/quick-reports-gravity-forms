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

// Hook into WordPress admin menu
add_action('admin_menu', 'gf_reports_add_menu');

// Enqueue admin scripts and styles
add_action('admin_enqueue_scripts', 'gf_reports_enqueue_scripts');

/**
 * Add the Reports submenu under Forms
 */
function gf_reports_add_menu() {
    add_submenu_page(
        'gf_edit_forms',                    // Parent slug
        'Reports',                          // Page title
        'Reports',                          // Menu title
        'gravityforms_view_entries',        // Capability
        'gf-reports',                       // Menu slug
        'gf_reports_render_page'            // Callback
    );
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
 * Render the main reports page
 */
function gf_reports_render_page() {
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
        
        <!-- Report Filters -->
        <div class="gf-reports-filters">
            <form method="GET" class="gf-reports-form">
                <input type="hidden" name="page" value="gf-reports">
                
                <div class="filter-row">
                    <label for="form_id">Select Form:</label>
                    <select name="form_id" id="form_id">
                        <option value="">Select a form</option>
                        <?php foreach ($forms as $form): ?>
                            <option value="<?php echo esc_attr($form['id']); ?>" <?php selected($form['id'], $selected_form); ?>>
                                <?php echo esc_html($form['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-row">
                    <label for="start_date">Start Date:</label>
                    <input type="date" name="start" id="start_date" value="<?php echo esc_attr($start_date); ?>">
                    
                    <label for="end_date">End Date:</label>
                    <input type="date" name="end" id="end_date" value="<?php echo esc_attr($end_date); ?>">
                </div>

                <div class="filter-row">
                    <button type="submit" class="button button-primary">Generate Report</button>
                    <?php if ($selected_form): ?>
                        <button type="button" class="button button-secondary" id="export-csv">Export CSV</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

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

                $entry_count = GFAPI::count_entries($selected_form, $search_criteria);
                $entries = GFAPI::get_entries($selected_form, $search_criteria, null, array('offset' => 0, 'page_size' => 1000));
                
                // Get form details
                $form = GFAPI::get_form($selected_form);
                ?>
                
                <div class="report-summary">
                    <h2>Report Summary</h2>
                    <div class="summary-stats">
                        <div class="stat-card">
                            <h3>Total Entries</h3>
                            <div class="stat-number"><?php echo number_format($entry_count); ?></div>
                        </div>
                        
                        <?php if ($start_date && $end_date): ?>
                            <div class="stat-card">
                                <h3>Date Range</h3>
                                <div class="stat-text"><?php echo date('M j, Y', strtotime($start_date)); ?> - <?php echo date('M j, Y', strtotime($end_date)); ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="stat-card">
                            <h3>Form</h3>
                            <div class="stat-text"><?php echo esc_html($form['title']); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Chart Container -->
                <div class="chart-container">
                    <h3>Entries Over Time</h3>
                    <canvas id="entriesChart" width="400" height="200"></canvas>
                </div>

                <!-- Recent Entries Table -->
                <?php if (!empty($entries)): ?>
                    <div class="recent-entries">
                        <h3>Recent Entries (Last 10)</h3>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Entry ID</th>
                                    <?php foreach ($form['fields'] as $field): ?>
                                        <?php if ($field['type'] !== 'section' && $field['type'] !== 'html'): ?>
                                            <th><?php echo esc_html($field['label']); ?></th>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $recent_entries = array_slice($entries, 0, 10);
                                foreach ($recent_entries as $entry): 
                                ?>
                                    <tr>
                                        <td><?php echo date('M j, Y g:i A', strtotime($entry['date_created'])); ?></td>
                                        <td><?php echo $entry['id']; ?></td>
                                        <?php foreach ($form['fields'] as $field): ?>
                                            <?php if ($field['type'] !== 'section' && $field['type'] !== 'html'): ?>
                                                <td><?php echo esc_html(rgar($entry, $field['id'])); ?></td>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <script>
                // Pass data to JavaScript for chart
                var chartData = {
                    labels: <?php echo json_encode(array_keys(gf_reports_get_daily_entries($selected_form, $start_date, $end_date))); ?>,
                    data: <?php echo json_encode(array_values(gf_reports_get_daily_entries($selected_form, $start_date, $end_date))); ?>
                };
            </script>
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