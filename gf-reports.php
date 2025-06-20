<?php
/**
 * Plugin Name: Quick Reports for Gravity Forms
 * Plugin URI: https://gravityranger.com/plugins/gravity-forms-quick-reports-addon
 * Description: Advanced reporting and visualization for Gravity Forms entries
 * Version: 1.0.0
 * Author: Chris Eggleston
 * Author URI: https://gravityranger.com
 * Text Domain: gf-quickreports
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('GF_QUICKREPORTS_VERSION', '1.0.0');
define('GF_QUICKREPORTS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GF_QUICKREPORTS_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main plugin class
 */
class GF_QuickReports {
    /**
     * Plugin instance
     */
    private static $instance = null;

    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_menu_page'), 999);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_gf_quickreports_export_csv', array($this, 'handle_csv_export'));
        add_action('wp_ajax_gf_quickreports_export_pdf', array($this, 'handle_pdf_export'));
        add_action('wp_ajax_gf_quickreports_get_compare_forms', array($this, 'get_compare_forms'));
        add_action('wp_ajax_gf_quickreports_get_date_presets', array($this, 'get_date_presets'));
    }

    /**
     * Initialize plugin
     */
    public function init() {
        load_plugin_textdomain('gf-quickreports', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /**
     * Add menu page
     */
    public function add_menu_page() {
        add_submenu_page(
            'gf_edit_forms',
            __('Quick Reports', 'gf-quickreports'),
            __('Quick Reports', 'gf-quickreports'),
            'manage_options',
            'gf_quickreports',
            array($this, 'render_reports_page')
        );
    }

    /**
     * Enqueue assets
     */
    public function enqueue_assets($hook) {
        if ($hook !== 'forms_page_gf_quickreports') {
            return;
        }

        // Enqueue Chart.js
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
            array(),
            '3.9.1',
            true
        );

        // Enqueue plugin scripts
        wp_enqueue_script(
            'gf-quickreports-admin',
            GF_QUICKREPORTS_PLUGIN_URL . 'js/admin.js',
            array('jquery', 'chartjs'),
            GF_QUICKREPORTS_VERSION,
            true
        );

        // Enqueue plugin styles
        wp_enqueue_style(
            'gf-quickreports-admin',
            GF_QUICKREPORTS_PLUGIN_URL . 'css/admin.css',
            array(),
            GF_QUICKREPORTS_VERSION
        );

        // Localize script
        wp_localize_script('gf-quickreports-admin', 'gf_quickreports_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gf_quickreports_nonce')
        ));
    }

    /**
     * Render reports page
     */
    public function render_reports_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'gf-quickreports'));
        }

        // Get forms
        $forms = GFAPI::get_forms();

        // Get selected form
        $form_id = isset($_GET['form_id']) ? absint($_GET['form_id']) : 0;
        $compare_form_id = isset($_GET['compare_form_id']) ? absint($_GET['compare_form_id']) : 0;
        $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
        $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';
        $chart_mode = isset($_GET['chart_mode']) ? sanitize_text_field($_GET['chart_mode']) : 'per_day';
        $chart_view = isset($_GET['chart_view']) ? sanitize_text_field($_GET['chart_view']) : 'combined';

        // Get chart data
        $chart_data = $this->get_chart_data($form_id, $start_date, $end_date, $chart_mode);
        $compare_chart_data = $compare_form_id ? $this->get_chart_data($compare_form_id, $start_date, $end_date, $chart_mode) : null;

        // Get individual forms data for "All Forms" view
        $individual_forms_data = array();
        if ($form_id === 0) {
            foreach ($forms as $form) {
                $form_data = $this->get_chart_data($form['id'], $start_date, $end_date, $chart_mode);
                if (!empty($form_data['data'])) {
                    $individual_forms_data[] = array(
                        'label' => $form['title'],
                        'data' => $form_data['data'],
                        'borderColor' => sprintf('#%06X', mt_rand(0, 0xFFFFFF)),
                        'backgroundColor' => 'rgba(' . mt_rand(0, 255) . ',' . mt_rand(0, 255) . ',' . mt_rand(0, 255) . ',0.1)',
                        'borderWidth' => 2,
                        'fill' => true,
                        'tension' => 0.4
                    );
                }
            }
        }

        // Get recent entries
        $recent_entries = $this->get_recent_entries($form_id, 10);

        // Include template
        include GF_QUICKREPORTS_PLUGIN_DIR . 'templates/reports-page.php';
    }

    /**
     * Get chart data
     */
    private function get_chart_data($form_id, $start_date = '', $end_date = '', $mode = 'per_day') {
        global $wpdb;

        // Return empty data if no form selected
        if (empty($form_id)) {
            return array('labels' => array(), 'data' => array());
        }

        // Prepare date range
        $where_clauses = array("status = 'active'");
        if (!empty($start_date)) {
            $where_clauses[] = $wpdb->prepare("date_created >= %s", $start_date . ' 00:00:00');
        }
        if (!empty($end_date)) {
            $where_clauses[] = $wpdb->prepare("date_created <= %s", $end_date . ' 23:59:59');
        }

        // Add form ID clause
        if ($form_id > 0) {
            $where_clauses[] = $wpdb->prepare("form_id = %d", $form_id);
        }

        // Build query
        $where = implode(' AND ', $where_clauses);
        $table_name = GFFormsModel::get_entry_table_name();

        if ($mode === 'total') {
            $query = "SELECT COUNT(*) as count FROM {$table_name} WHERE {$where}";
            $result = $wpdb->get_var($query);
            return array('labels' => array('Total'), 'data' => array((int)$result));
        }

        $query = "SELECT DATE(date_created) as date, COUNT(*) as count 
                 FROM {$table_name} 
                 WHERE {$where} 
                 GROUP BY DATE(date_created) 
                 ORDER BY date ASC";

        $results = $wpdb->get_results($query);

        $labels = array();
        $data = array();

        if (!empty($results)) {
            foreach ($results as $row) {
                $labels[] = date('Y-m-d', strtotime($row->date));
                $data[] = (int)$row->count;
            }
        }

        return array('labels' => $labels, 'data' => $data);
    }

    /**
     * Get recent entries
     */
    private function get_recent_entries($form_id, $limit = 10) {
        if (empty($form_id)) {
            return array();
        }

        $search_criteria = array(
            'status' => 'active'
        );

        $sorting = array(
            'key' => 'date_created',
            'direction' => 'DESC'
        );

        $paging = array(
            'offset' => 0,
            'page_size' => $limit
        );

        return GFAPI::get_entries($form_id, $search_criteria, $sorting, $paging);
    }

    /**
     * Get daily entry counts for chart
     */
    public static function get_daily_entries($form_id, $start_date, $end_date) {
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
     * Handle CSV export
     */
    public function handle_csv_export() {
        try {
            // Debug logging
            error_log('GF QuickReports: CSV export called');
            error_log('GF QuickReports: POST data: ' . print_r($_POST, true));
            
            check_ajax_referer('gf_quickreports_nonce', 'nonce');
            
            $current_user = wp_get_current_user();
            
            // Check if user is an admin or has GF permissions
            if (!current_user_can('administrator') && !current_user_can('gravityforms_view_entries')) {
                wp_die('Unauthorized');
            }
            
            $form_id = isset($_POST['form_id']) ? sanitize_text_field($_POST['form_id']) : '';
            $start_date = sanitize_text_field($_POST['start_date']);
            $end_date = sanitize_text_field($_POST['end_date']);
            $compare_form_id = isset($_POST['compare_form_id']) ? sanitize_text_field($_POST['compare_form_id']) : '';
            
            error_log('GF QuickReports: Form ID: ' . $form_id);
            error_log('GF QuickReports: Compare Form ID: ' . $compare_form_id);
            error_log('GF QuickReports: Start Date: ' . $start_date);
            error_log('GF QuickReports: End Date: ' . $end_date);
            
            $search_criteria = array('status' => 'active');
            if ($start_date) {
                $search_criteria['start_date'] = $start_date . ' 00:00:00';
            }
            if ($end_date) {
                $search_criteria['end_date'] = $end_date . ' 23:59:59';
            }

            // Clear any previous output and disable compression
            if (ob_get_length()) ob_clean();
            if (ini_get('zlib.output_compression')) ini_set('zlib.output_compression', 'Off');
            
            // Set headers for CSV download
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="gf-quickreports-' . $form_id . '-' . date('Y-m-d') . '.csv"');
            
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // Add UTF-8 BOM

            if ($form_id === 'all') {
                // Export data for all forms
                $forms = GFAPI::get_forms();
                
                // Write summary headers
                fputcsv($output, array('Form Name', 'Total Entries', 'Average Per Day', 'Total Revenue'));
                
                $total_entries = 0;
                $total_revenue = 0;
                $total_days = 0;
                
                foreach ($forms as $form) {
                    $entry_count = GFAPI::count_entries($form['id'], $search_criteria);
                    $entries = GFAPI::get_entries($form['id'], $search_criteria);
                    $daily_entries = self::get_daily_entries($form['id'], $start_date, $end_date);
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
                    
                    fputcsv($output, array(
                        $form['title'],
                        $entry_count,
                        number_format($avg_per_day, 2),
                        !empty($product_fields) ? '$' . number_format($form_revenue, 2) : 'N/A'
                    ));
                    
                    $total_entries += $entry_count;
                    $total_revenue += $form_revenue;
                    $total_days = max($total_days, $days_count);
                }
                
                // Write totals
                fputcsv($output, array(''));
                fputcsv($output, array(
                    'TOTAL',
                    $total_entries,
                    $total_days > 0 ? number_format($total_entries / $total_days, 2) : '0.00',
                    '$' . number_format($total_revenue, 2)
                ));
                
            } else {
                // Export data for single form
                $form = GFAPI::get_form($form_id);
                $entries = GFAPI::get_entries($form_id, $search_criteria);
                $entry_count = count($entries);
                $daily_entries = self::get_daily_entries($form_id, $start_date, $end_date);
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

                // Write summary data for main form
                fputcsv($output, array('Form Name', 'Total Entries', 'Average Per Day', 'Total Revenue'));
                fputcsv($output, array(
                    $form['title'],
                    $entry_count,
                    number_format($avg_per_day, 2),
                    !empty($product_fields) ? '$' . number_format($total_revenue, 2) : 'N/A'
                ));
                
                // Add comparison form data if selected
                if ($compare_form_id) {
                    error_log('GF QuickReports: Processing comparison form data for form ID: ' . $compare_form_id);
                    $compare_form = GFAPI::get_form($compare_form_id);
                    $compare_entries = GFAPI::get_entries($compare_form_id, $search_criteria);
                    $compare_entry_count = count($compare_entries);
                    $compare_daily_entries = self::get_daily_entries($compare_form_id, $start_date, $end_date);
                    $compare_days_count = count($compare_daily_entries);
                    $compare_avg_per_day = $compare_days_count > 0 ? $compare_entry_count / $compare_days_count : 0;
                    
                    error_log('GF QuickReports: Comparison form: ' . $compare_form['title']);
                    error_log('GF QuickReports: Comparison entries: ' . $compare_entry_count);
                    
                    // Calculate comparison form revenue
                    $compare_total_revenue = 0;
                    $compare_product_fields = array();
                    foreach ($compare_form['fields'] as $field) {
                        if (isset($field['type']) && $field['type'] === 'product') {
                            $compare_product_fields[] = $field['id'];
                        }
                    }
                    
                    if (!empty($compare_product_fields) && !empty($compare_entries)) {
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
                    
                    error_log('GF QuickReports: Comparison revenue: ' . $compare_total_revenue);
                    
                    // Write comparison form data (always write, regardless of product fields)
                    fputcsv($output, array(
                        $compare_form['title'],
                        $compare_entry_count,
                        number_format($compare_avg_per_day, 2),
                        !empty($compare_product_fields) ? '$' . number_format($compare_total_revenue, 2) : 'N/A'
                    ));
                } else {
                    error_log('GF QuickReports: No comparison form ID provided');
                }
            }
            
            fclose($output);
            exit;
            
        } catch (Exception $e) {
            wp_die('Error generating CSV: ' . $e->getMessage());
        }
    }

    /**
     * Handle PDF export
     */
    public function handle_pdf_export() {
        try {
            // Debug logging
            error_log('GF QuickReports: PDF export called');
            error_log('GF QuickReports: POST data: ' . print_r($_POST, true));
            
            check_ajax_referer('gf_quickreports_nonce', 'nonce');
            
            $current_user = wp_get_current_user();
            
            // Check if user is an admin or has GF permissions
            if (!current_user_can('administrator') && !current_user_can('gravityforms_view_entries')) {
                wp_die('Unauthorized');
            }
            
            // Check if DOMPDF is available
            if (!class_exists('Dompdf\\Dompdf')) {
                // Try to load it from vendor directory
                $autoload_path = __DIR__ . '/vendor/autoload.php';
                
                if (file_exists($autoload_path)) {
                    require_once $autoload_path;
                } else {
                    wp_die('PDF generation library not available - autoload.php not found');
                }
                
                if (!class_exists('Dompdf\\Dompdf')) {
                    wp_die('PDF generation library not available - DOMPDF class not found');
                }
            }
            
            $form_id = isset($_POST['form_id']) ? sanitize_text_field($_POST['form_id']) : '';
            $start_date = sanitize_text_field($_POST['start_date']);
            $end_date = sanitize_text_field($_POST['end_date']);
            $compare_form_id = isset($_POST['compare_form_id']) ? sanitize_text_field($_POST['compare_form_id']) : '';
            $chart_data = isset($_POST['chart_data']) ? $_POST['chart_data'] : '';
            
            error_log('GF QuickReports: Form ID: ' . $form_id);
            error_log('GF QuickReports: Compare Form ID: ' . $compare_form_id);
            error_log('GF QuickReports: Start Date: ' . $start_date);
            error_log('GF QuickReports: End Date: ' . $end_date);
            
            // Generate HTML content
            $html = '<html><head><style>
                body { font-family: DejaVu Sans, sans-serif; margin: 20px; }
                table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                th, td { padding: 8px; border: 1px solid #ddd; }
                th { background-color: #f5f5f5; }
                h1, h2 { color: #333; }
                .chart-container { margin: 20px 0; text-align: center; }
                img { max-width: 100%; height: auto; }
            </style></head><body>';
            
            // Add report header
            $html .= '<h1>Gravity Forms Report</h1>';
            $html .= '<p>Date Range: ' . date('M j, Y', strtotime($start_date)) . ' - ' . date('M j, Y', strtotime($end_date)) . '</p>';
            
            // Add chart image if provided
            if (!empty($chart_data)) {
                $html .= '<div class="chart-container">';
                $html .= '<img src="' . $chart_data . '">';
                $html .= '</div>';
            }
            
            // Add report data
            if ($form_id === 'all') {
                // All forms summary table
                $html .= '<h2>All Forms Summary</h2>';
                $html .= '<table><tr><th>Form</th><th>Total Entries</th><th>Average Per Day</th><th>Total Revenue</th></tr>';
                
                $forms = GFAPI::get_forms();
                $total_entries = 0;
                $total_revenue = 0;
                $total_days = 0;
                
                foreach ($forms as $form) {
                    $search_criteria = array(
                        'status' => 'active',
                        'start_date' => $start_date . ' 00:00:00',
                        'end_date' => $end_date . ' 23:59:59'
                    );
                    
                    $entry_count = GFAPI::count_entries($form['id'], $search_criteria);
                    $daily_entries = self::get_daily_entries($form['id'], $start_date, $end_date);
                    $days_count = count($daily_entries);
                    $avg_per_day = $days_count > 0 ? $entry_count / $days_count : 0;
                    
                    // Calculate revenue
                    $entries = GFAPI::get_entries($form['id'], $search_criteria);
                    $form_revenue = 0;
                    $product_fields = array();
                    foreach ($form['fields'] as $field) {
                        if (isset($field['type']) && $field['type'] === 'product') {
                            $product_fields[] = $field['id'];
                        }
                    }
                    
                    error_log('GF QuickReports: PDF - Form: ' . $form['title'] . ', Product fields: ' . print_r($product_fields, true));
                    
                    if (!empty($product_fields) && !empty($entries)) {
                        foreach ($entries as $entry) {
                            foreach ($product_fields as $pid) {
                                $val = rgar($entry, $pid);
                                error_log('GF QuickReports: PDF - Entry ' . $entry['id'] . ', Field ' . $pid . ', Value: ' . print_r($val, true));
                                if (is_numeric($val)) {
                                    $form_revenue += floatval($val);
                                    error_log('GF QuickReports: PDF - Added numeric value: ' . $val);
                                } elseif (is_array($val) && isset($val['price'])) {
                                    $form_revenue += floatval($val['price']);
                                    error_log('GF QuickReports: PDF - Added array price: ' . $val['price']);
                                } elseif (is_string($val)) {
                                    if (preg_match('/([\d\.,]+)/', $val, $matches)) {
                                        $form_revenue += floatval(str_replace(',', '', $matches[1]));
                                        error_log('GF QuickReports: PDF - Added string value: ' . $matches[1]);
                                    }
                                }
                            }
                        }
                    }
                    
                    error_log('GF QuickReports: PDF - Form ' . $form['title'] . ' revenue: ' . $form_revenue);
                    
                    $html .= sprintf(
                        '<tr><td>%s</td><td>%d</td><td>%.2f</td><td>%s</td></tr>',
                        esc_html($form['title']),
                        $entry_count,
                        $avg_per_day,
                        !empty($product_fields) ? '$' . number_format($form_revenue, 2) : 'N/A'
                    );
                    
                    $total_entries += $entry_count;
                    $total_revenue += $form_revenue;
                    $total_days = max($total_days, $days_count);
                }
                
                error_log('GF QuickReports: PDF - Total revenue: ' . $total_revenue);
                error_log('GF QuickReports: PDF - Total days: ' . $total_days);
                
                // Add totals row
                $html .= sprintf(
                    '<tr style="font-weight: bold;"><td>TOTAL</td><td>%d</td><td>%.2f</td><td>$%s</td></tr>',
                    $total_entries,
                    $total_days > 0 ? $total_entries / $total_days : 0,
                    number_format($total_revenue, 2)
                );
                
                $html .= '</table>';
                
                // Add comparison form data if selected
                if ($compare_form_id) {
                    $compare_form = GFAPI::get_form($compare_form_id);
                    $compare_entries = GFAPI::get_entries($compare_form_id, $search_criteria);
                    $compare_entry_count = count($compare_entries);
                    $compare_daily_entries = self::get_daily_entries($compare_form_id, $start_date, $end_date);
                    $compare_days_count = count($compare_daily_entries);
                    $compare_avg_per_day = $compare_days_count > 0 ? $compare_entry_count / $compare_days_count : 0;
                    
                    $html .= '<h2>' . esc_html($compare_form['title']) . ' - Summary</h2>';
                    $html .= '<table>';
                    $html .= sprintf('<tr><td>Total Entries</td><td>%d</td></tr>', $compare_entry_count);
                    $html .= sprintf('<tr><td>Average Per Day</td><td>%.2f</td></tr>', 
                        count($compare_daily_entries) > 0 ? $compare_entry_count / count($compare_daily_entries) : 0
                    );
                    
                    // Calculate comparison form revenue if applicable
                    $compare_total_revenue = 0;
                    $compare_product_fields = array();
                    foreach ($compare_form['fields'] as $field) {
                        if (isset($field['type']) && $field['type'] === 'product') {
                            $compare_product_fields[] = $field['id'];
                        }
                    }
                    
                    if (!empty($compare_product_fields) && !empty($compare_entries)) {
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
                        $html .= sprintf('<tr><td>Total Revenue</td><td>$%s</td></tr>', number_format($compare_total_revenue, 2));
                    }
                    
                    $html .= '</table>';
                }
            } else {
                // Single form details
                $form = GFAPI::get_form($form_id);
                $search_criteria = array(
                    'status' => 'active',
                    'start_date' => $start_date . ' 00:00:00',
                    'end_date' => $end_date . ' 23:59:59'
                );
                
                $entries = GFAPI::get_entries($form_id, $search_criteria);
                $entry_count = count($entries);
                $daily_entries = self::get_daily_entries($form_id, $start_date, $end_date);
                
                $html .= '<h2>' . esc_html($form['title']) . ' - Summary</h2>';
                $html .= '<table>';
                $html .= sprintf('<tr><td>Total Entries</td><td>%d</td></tr>', $entry_count);
                $html .= sprintf('<tr><td>Average Per Day</td><td>%.2f</td></tr>', 
                    count($daily_entries) > 0 ? $entry_count / count($daily_entries) : 0
                );
                
                // Calculate revenue if applicable
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
                            } elseif (is_array($val) && isset($val['price'])) {
                                $total_revenue += floatval($val['price']);
                            } elseif (is_string($val)) {
                                if (preg_match('/([\d\.,]+)/', $val, $matches)) {
                                    $total_revenue += floatval(str_replace(',', '', $matches[1]));
                                }
                            }
                        }
                    }
                    $html .= sprintf('<tr><td>Total Revenue</td><td>$%s</td></tr>', number_format($total_revenue, 2));
                }
                
                $html .= '</table>';
                
                // Add comparison form data if selected
                if ($compare_form_id) {
                    $compare_form = GFAPI::get_form($compare_form_id);
                    $compare_entries = GFAPI::get_entries($compare_form_id, $search_criteria);
                    $compare_entry_count = count($compare_entries);
                    $compare_daily_entries = self::get_daily_entries($compare_form_id, $start_date, $end_date);
                    $compare_days_count = count($compare_daily_entries);
                    $compare_avg_per_day = $compare_days_count > 0 ? $compare_entry_count / $compare_days_count : 0;
                    
                    $html .= '<h2>' . esc_html($compare_form['title']) . ' - Summary</h2>';
                    $html .= '<table>';
                    $html .= sprintf('<tr><td>Total Entries</td><td>%d</td></tr>', $compare_entry_count);
                    $html .= sprintf('<tr><td>Average Per Day</td><td>%.2f</td></tr>', 
                        count($compare_daily_entries) > 0 ? $compare_entry_count / count($compare_daily_entries) : 0
                    );
                    
                    // Calculate comparison form revenue if applicable
                    $compare_total_revenue = 0;
                    $compare_product_fields = array();
                    foreach ($compare_form['fields'] as $field) {
                        if (isset($field['type']) && $field['type'] === 'product') {
                            $compare_product_fields[] = $field['id'];
                        }
                    }
                    
                    if (!empty($compare_product_fields) && !empty($compare_entries)) {
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
                        $html .= sprintf('<tr><td>Total Revenue</td><td>$%s</td></tr>', number_format($compare_total_revenue, 2));
                    }
                    
                    $html .= '</table>';
                }
            }
            
            $html .= '</body></html>';
            
            // Initialize DOMPDF with options
            $options = new \Dompdf\Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isPhpEnabled', true);
            $options->set('isRemoteEnabled', true);
            
            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->loadHtml($html);
            $dompdf->render();
            
            // Get the PDF content
            $pdf_content = $dompdf->output();
            $content_length = strlen($pdf_content);
            
            // Clear any previous output and disable compression
            if (ob_get_length()) ob_clean();
            if (ini_get('zlib.output_compression')) ini_set('zlib.output_compression', 'Off');
            
            // Set headers for PDF download
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="gf-quickreports-' . $form_id . '-' . date('Y-m-d') . '.pdf"');
            header('Content-Length: ' . $content_length);
            header('Cache-Control: private, no-store, no-cache, must-revalidate');
            header('Pragma: no-cache');
            
            // Output PDF content
            echo $pdf_content;
            exit;
            
        } catch (Exception $e) {
            wp_die('Error generating PDF: ' . $e->getMessage());
        }
    }

    /**
     * Get compare form options via AJAX
     */
    public function get_compare_forms() {
        try {
            // Debug logging
            error_log('GF QuickReports: get_compare_forms called');
            error_log('GF QuickReports: POST data: ' . print_r($_POST, true));
            
            check_ajax_referer('gf_quickreports_nonce', 'nonce');
            
            // Check if user is an admin or has GF permissions
            if (!current_user_can('administrator') && !current_user_can('gravityforms_view_entries')) {
                error_log('GF QuickReports: Unauthorized access attempt');
                wp_die('Unauthorized');
            }
            
            $selected_form = isset($_POST['selected_form']) ? sanitize_text_field($_POST['selected_form']) : '';
            error_log('GF QuickReports: Selected form: ' . $selected_form);
            
            if (!$selected_form || $selected_form === 'all') {
                error_log('GF QuickReports: No valid form selected, returning empty options');
                wp_send_json_success(array('options' => array()));
            }
            
            $forms = GFAPI::get_forms();
            error_log('GF QuickReports: Total forms found: ' . count($forms));
            
            $options = array();
            
            foreach ($forms as $form) {
                if ($form['id'] != $selected_form) {
                    $options[] = array(
                        'value' => $form['id'],
                        'label' => $form['title']
                    );
                }
            }
            
            error_log('GF QuickReports: Options to return: ' . print_r($options, true));
            wp_send_json_success(array('options' => $options));
            
        } catch (Exception $e) {
            error_log('GF QuickReports: Error in get_compare_forms: ' . $e->getMessage());
            wp_send_json_error('Error getting compare forms: ' . $e->getMessage());
        }
    }

    /**
     * Get date preset values via AJAX
     */
    public function get_date_presets() {
        try {
            check_ajax_referer('gf_quickreports_nonce', 'nonce');
            
            // Check if user is an admin or has GF permissions
            if (!current_user_can('administrator') && !current_user_can('gravityforms_view_entries')) {
                wp_die('Unauthorized');
            }
            
            $preset = isset($_POST['preset']) ? sanitize_text_field($_POST['preset']) : '';
            $dates = array();
            
            switch($preset) {
                case 'today':
                    $dates['start_date'] = date('Y-m-d');
                    $dates['end_date'] = date('Y-m-d');
                    break;
                case 'yesterday':
                    $dates['start_date'] = date('Y-m-d', strtotime('-1 day'));
                    $dates['end_date'] = date('Y-m-d', strtotime('-1 day'));
                    break;
                case '7days':
                    $dates['start_date'] = date('Y-m-d', strtotime('-7 days'));
                    $dates['end_date'] = date('Y-m-d');
                    break;
                case '30days':
                    $dates['start_date'] = date('Y-m-d', strtotime('-30 days'));
                    $dates['end_date'] = date('Y-m-d');
                    break;
                case '60days':
                    $dates['start_date'] = date('Y-m-d', strtotime('-60 days'));
                    $dates['end_date'] = date('Y-m-d');
                    break;
                case '90days':
                    $dates['start_date'] = date('Y-m-d', strtotime('-90 days'));
                    $dates['end_date'] = date('Y-m-d');
                    break;
                case 'year_to_date':
                    $dates['start_date'] = date('Y-01-01');
                    $dates['end_date'] = date('Y-m-d');
                    break;
                case 'last_year':
                    $dates['start_date'] = date('Y-01-01', strtotime('-1 year'));
                    $dates['end_date'] = date('Y-12-31', strtotime('-1 year'));
                    break;
                case 'custom':
                default:
                    $dates['start_date'] = '';
                    $dates['end_date'] = '';
                    break;
            }
            
            wp_send_json_success($dates);
            
        } catch (Exception $e) {
            wp_send_json_error('Error getting date presets: ' . $e->getMessage());
        }
    }
}

// Initialize plugin
add_action('plugins_loaded', array('GF_QuickReports', 'get_instance'));

// Global function for template compatibility
function gf_quickreports_get_daily_entries($form_id, $start_date, $end_date) {
    return GF_QuickReports::get_daily_entries($form_id, $start_date, $end_date);
}