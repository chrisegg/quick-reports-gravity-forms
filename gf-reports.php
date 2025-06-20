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
        add_action('admin_menu', array($this, 'add_menu_page'), 20);
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
            esc_html__('Quick Reports', 'gf-quickreports'),
            esc_html__('Quick Reports', 'gf-quickreports'),
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
            GF_QUICKREPORTS_PLUGIN_URL . 'assets/js/lib/chart.min.js',
            array(),
            '3.9.1',
            true
        );

        // Enqueue plugin scripts
        wp_enqueue_script(
            'gf-quickreports-admin',
            GF_QUICKREPORTS_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'chartjs'),
            GF_QUICKREPORTS_VERSION,
            true
        );

        // Enqueue plugin styles
        wp_enqueue_style(
            'gf-quickreports-admin',
            GF_QUICKREPORTS_PLUGIN_URL . 'assets/css/admin.css',
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

        // Get selected form with proper sanitization
        $form_id = isset($_GET['form_id']) ? absint(wp_unslash($_GET['form_id'])) : 0;
        $compare_form_id = isset($_GET['compare_form_id']) ? absint(wp_unslash($_GET['compare_form_id'])) : 0;
        $start_date = isset($_GET['start_date']) ? sanitize_text_field(wp_unslash($_GET['start_date'])) : '';
        $end_date = isset($_GET['end_date']) ? sanitize_text_field(wp_unslash($_GET['end_date'])) : '';
        $chart_mode = isset($_GET['chart_mode']) ? sanitize_text_field(wp_unslash($_GET['chart_mode'])) : 'per_day';
        $chart_view = isset($_GET['chart_view']) ? sanitize_text_field(wp_unslash($_GET['chart_view'])) : 'combined';

        // Get chart data for entries
        $chart_data = $this->get_chart_data($form_id, $start_date, $end_date, $chart_mode, 'entries');
        $compare_chart_data = $compare_form_id ? $this->get_chart_data($compare_form_id, $start_date, $end_date, $chart_mode, 'entries') : null;

        // Get chart data for revenue
        $revenue_chart_data = $this->get_chart_data($form_id, $start_date, $end_date, $chart_mode, 'revenue');
        $compare_revenue_chart_data = $compare_form_id ? $this->get_chart_data($compare_form_id, $start_date, $end_date, $chart_mode, 'revenue') : null;

        // Get individual forms data for "All Forms" view (entries)
        $individual_forms_data = array();
        $individual_revenue_data = array();
        if ($form_id === 0) {
            foreach ($forms as $form) {
                $form_data = $this->get_chart_data($form['id'], $start_date, $end_date, $chart_mode, 'entries');
                if (!empty($form_data['data'])) {
                    $individual_forms_data[] = array(
                        'label' => $form['title'],
                        'data' => $form_data['data'],
                        'borderColor' => sprintf('#%06X', wp_rand(0, 0xFFFFFF)),
                        'backgroundColor' => 'rgba(' . wp_rand(0, 255) . ',' . wp_rand(0, 255) . ',' . wp_rand(0, 255) . ',0.1)',
                        'borderWidth' => 2,
                        'fill' => true,
                        'tension' => 0.4
                    );
                }
                
                // Get revenue data for individual forms
                $form_revenue_data = $this->get_chart_data($form['id'], $start_date, $end_date, $chart_mode, 'revenue');
                if (!empty($form_revenue_data['data'])) {
                    $individual_revenue_data[] = array(
                        'label' => $form['title'],
                        'data' => $form_revenue_data['data'],
                        'borderColor' => sprintf('#%06X', wp_rand(0, 0xFFFFFF)),
                        'backgroundColor' => 'rgba(' . wp_rand(0, 255) . ',' . wp_rand(0, 255) . ',' . wp_rand(0, 255) . ',0.1)',
                        'borderWidth' => 2,
                        'fill' => true,
                        'tension' => 0.4
                    );
                }
            }
        }

        // Get recent entries
        $recent_entries = $this->get_recent_entries($form_id, 10);

        // Debug: Check if revenue data is being generated
        error_log('GF QuickReports Debug - Form ID: ' . $form_id);
        error_log('GF QuickReports Debug - Revenue Chart Data: ' . print_r($revenue_chart_data, true));
        error_log('GF QuickReports Debug - Individual Revenue Data: ' . print_r($individual_revenue_data, true));

        // Include template
        include GF_QUICKREPORTS_PLUGIN_DIR . 'templates/reports-page.php';
    }

    /**
     * Get chart data
     */
    private function get_chart_data($form_id, $start_date = '', $end_date = '', $mode = 'per_day', $type = 'entries') {
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
            if ($type === 'revenue') {
                // For revenue totals, we need to calculate from entries
                $query = "SELECT id FROM {$table_name} WHERE {$where}";
                $entry_ids = $wpdb->get_col($query);
                $total_revenue = $this->calculate_revenue_from_entries($entry_ids);
                return array('labels' => array('Total'), 'data' => array($total_revenue));
            } else {
                // For entry totals
                $query = "SELECT COUNT(*) as count FROM {$table_name} WHERE {$where}";
                $result = $wpdb->get_var($query);
                return array('labels' => array('Total'), 'data' => array((int)$result));
            }
        }

        if ($type === 'revenue') {
            // Get daily revenue data
            $query = "SELECT DATE(date_created) as date, id FROM {$table_name} WHERE {$where} ORDER BY date ASC";
            $results = $wpdb->get_results($query);
            
            $daily_revenue = array();
            if (!empty($results)) {
                foreach ($results as $row) {
                    $date = gmdate('Y-m-d', strtotime($row->date));
                    if (!isset($daily_revenue[$date])) {
                        $daily_revenue[$date] = 0;
                    }
                    $daily_revenue[$date] += $this->calculate_revenue_from_entries(array($row->id));
                }
            }
            
            $labels = array();
            $data = array();
            
            if (!empty($daily_revenue)) {
                foreach ($daily_revenue as $date => $revenue) {
                    $labels[] = $date;
                    $data[] = round($revenue, 2);
                }
            }
            
            return array('labels' => $labels, 'data' => $data);
        } else {
            // Get daily entry counts (existing logic)
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
                    $labels[] = gmdate('Y-m-d', strtotime($row->date));
                    $data[] = (int)$row->count;
                }
            }

            return array('labels' => $labels, 'data' => $data);
        }
    }

    /**
     * Calculate revenue from entry IDs
     */
    private function calculate_revenue_from_entries($entry_ids) {
        if (empty($entry_ids)) {
            return 0;
        }

        $total_revenue = 0;
        
        foreach ($entry_ids as $entry_id) {
            $entry = GFAPI::get_entry($entry_id);
            if (!$entry || $entry['status'] !== 'active') {
                continue;
            }
            
            $form = GFAPI::get_form($entry['form_id']);
            if (!$form) {
                continue;
            }
            
            // Find product fields
            $product_fields = array();
            foreach ($form['fields'] as $field) {
                if (isset($field['type']) && $field['type'] === 'product') {
                    $product_fields[] = $field['id'];
                }
            }
            
            // Calculate revenue for this entry
            if (!empty($product_fields)) {
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
        
        return $total_revenue;
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
            $daily_entries[gmdate('M j', strtotime($current_date))] = $count;
            
            $current_date = gmdate('Y-m-d', strtotime($current_date . ' +1 day'));
        }
        
        return $daily_entries;
    }

    /**
     * Handle CSV export
     */
    public function handle_csv_export() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'gf_quickreports_nonce')) {
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        // Get form data
        $form_id = isset($_POST['form_id']) ? sanitize_text_field(wp_unslash($_POST['form_id'])) : '';
        $compare_form_id = isset($_POST['compare_form_id']) ? sanitize_text_field(wp_unslash($_POST['compare_form_id'])) : '';
        $start_date = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : '';

        // Get search criteria
        $search_criteria = array('status' => 'active');
        if (!empty($start_date)) {
            $search_criteria['start_date'] = $start_date . ' 00:00:00';
        }
        if (!empty($end_date)) {
            $search_criteria['end_date'] = $end_date . ' 23:59:59';
        }

        // Clear any previous output and disable compression
        if (ob_get_length()) ob_clean();
        if (ini_get('zlib.output_compression')) ini_set('zlib.output_compression', 'Off');
        
        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="gf-quickreports-' . $form_id . '-' . gmdate('Y-m-d') . '.csv"');
        
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
                $compare_form = GFAPI::get_form($compare_form_id);
                $compare_entries = GFAPI::get_entries($compare_form_id, $search_criteria);
                $compare_entry_count = count($compare_entries);
                $compare_daily_entries = self::get_daily_entries($compare_form_id, $start_date, $end_date);
                $compare_days_count = count($compare_daily_entries);
                $compare_avg_per_day = $compare_days_count > 0 ? $compare_entry_count / $compare_days_count : 0;
                
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
                
                // Write comparison form data
                fputcsv($output, array(
                    $compare_form['title'],
                    $compare_entry_count,
                    number_format($compare_avg_per_day, 2),
                    !empty($compare_product_fields) ? '$' . number_format($compare_total_revenue, 2) : 'N/A'
                ));
            }
        }
        
        fclose($output);
        exit;
    }

    /**
     * Handle PDF export
     */
    public function handle_pdf_export() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'gf_quickreports_nonce')) {
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        // Get form data
        $form_id = isset($_POST['form_id']) ? sanitize_text_field(wp_unslash($_POST['form_id'])) : '';
        $compare_form_id = isset($_POST['compare_form_id']) ? sanitize_text_field(wp_unslash($_POST['compare_form_id'])) : '';
        $start_date = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : '';
        $chart_data = isset($_POST['chart_data']) ? $_POST['chart_data'] : '';
        $revenue_chart_data = isset($_POST['revenue_chart_data']) ? $_POST['revenue_chart_data'] : '';

        // Get search criteria
        $search_criteria = array('status' => 'active');
        if (!empty($start_date)) {
            $search_criteria['start_date'] = $start_date . ' 00:00:00';
        }
        if (!empty($end_date)) {
            $search_criteria['end_date'] = $end_date . ' 23:59:59';
        }

        try {
            // Generate PDF using DOMPDF
            require_once GF_QUICKREPORTS_PLUGIN_DIR . 'vendor/autoload.php';
            
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->setPaper('A4', 'portrait');
            
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
            $html .= '<p>Date Range: ' . gmdate('M j, Y', strtotime($start_date)) . ' - ' . gmdate('M j, Y', strtotime($end_date)) . '</p>';
            
            // Add chart image if provided
            if (!empty($chart_data)) {
                $html .= '<div class="chart-container">';
                $html .= '<h3>Entries Over Time</h3>';
                $html .= '<img src="' . $chart_data . '">';
                $html .= '</div>';
            }
            
            // Add revenue chart image if provided
            if (!empty($revenue_chart_data)) {
                $html .= '<div class="chart-container">';
                $html .= '<h3>Revenue Over Time</h3>';
                $html .= '<img src="' . $revenue_chart_data . '">';
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
            header('Content-Disposition: attachment; filename="gf-quickreports-' . $form_id . '-' . gmdate('Y-m-d') . '.pdf"');
            header('Content-Length: ' . $content_length);
            header('Cache-Control: private, no-store, no-cache, must-revalidate');
            header('Pragma: no-cache');
            
            // Output PDF content
            echo $pdf_content;
            exit;
            
        } catch (Exception $e) {
            wp_die('Error generating PDF: ' . esc_html($e->getMessage()));
        }
    }

    /**
     * Get compare form options via AJAX
     */
    public function get_compare_forms() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'gf_quickreports_nonce')) {
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $selected_form = isset($_POST['selected_form']) ? sanitize_text_field(wp_unslash($_POST['selected_form'])) : '';
        
        if (empty($selected_form) || $selected_form === 'all') {
            wp_send_json_success(array('options' => array()));
        }

        $forms = GFAPI::get_forms();
        $options = array();
        
        foreach ($forms as $form) {
            if ($form['id'] != $selected_form) {
                $options[] = array(
                    'value' => $form['id'],
                    'label' => $form['title']
                );
            }
        }
        
        wp_send_json_success(array('options' => $options));
    }

    /**
     * Get date preset values via AJAX
     */
    public function get_date_presets() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'gf_quickreports_nonce')) {
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $preset = isset($_POST['preset']) ? sanitize_text_field(wp_unslash($_POST['preset'])) : '';
        $dates = array();
        
        switch($preset) {
            case 'today':
                $dates['start_date'] = gmdate('Y-m-d');
                $dates['end_date'] = gmdate('Y-m-d');
                break;
            case 'yesterday':
                $dates['start_date'] = gmdate('Y-m-d', strtotime('-1 day'));
                $dates['end_date'] = gmdate('Y-m-d', strtotime('-1 day'));
                break;
            case '7days':
                $dates['start_date'] = gmdate('Y-m-d', strtotime('-7 days'));
                $dates['end_date'] = gmdate('Y-m-d');
                break;
            case '30days':
                $dates['start_date'] = gmdate('Y-m-d', strtotime('-30 days'));
                $dates['end_date'] = gmdate('Y-m-d');
                break;
            case '60days':
                $dates['start_date'] = gmdate('Y-m-d', strtotime('-60 days'));
                $dates['end_date'] = gmdate('Y-m-d');
                break;
            case '90days':
                $dates['start_date'] = gmdate('Y-m-d', strtotime('-90 days'));
                $dates['end_date'] = gmdate('Y-m-d');
                break;
            case 'year_to_date':
                $dates['start_date'] = gmdate('Y-01-01');
                $dates['end_date'] = gmdate('Y-m-d');
                break;
            case 'last_year':
                $dates['start_date'] = gmdate('Y-01-01', strtotime('-1 year'));
                $dates['end_date'] = gmdate('Y-12-31', strtotime('-1 year'));
                break;
            case 'custom':
            default:
                $dates['start_date'] = '';
                $dates['end_date'] = '';
                break;
        }
        
        wp_send_json_success($dates);
    }
}

// Initialize plugin
add_action('plugins_loaded', array('GF_QuickReports', 'get_instance'));

// Global function for template compatibility
function gf_quickreports_get_daily_entries($form_id, $start_date, $end_date) {
    return GF_QuickReports::get_daily_entries($form_id, $start_date, $end_date);
}