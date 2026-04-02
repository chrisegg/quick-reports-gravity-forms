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

// Include required classes
require_once plugin_dir_path(__FILE__) . 'includes/class-attributer-detector.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-attribution-cache.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-attribution-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-attribution-calculator.php';

// Define plugin constants
define('GR_QUICKREPORTS_VERSION', '1.0.0');
define('GR_QUICKREPORTS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GR_QUICKREPORTS_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main plugin class
 */
class GR_QuickReports {
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
        add_action('wp_ajax_gr_quickreports_export_csv', array($this, 'handle_csv_export'));
        add_action('wp_ajax_gr_quickreports_export_pdf', array($this, 'handle_pdf_export'));
        add_action('wp_ajax_gr_quickreports_get_compare_forms', array($this, 'get_compare_forms'));
        add_action('wp_ajax_gr_quickreports_get_date_presets', array($this, 'get_date_presets'));
        
        // Attribution AJAX handlers
        add_action('wp_ajax_gr_get_attribution_data', array($this, 'handle_get_attribution_data'));
        add_action('wp_ajax_gr_get_attribution_filters', array($this, 'handle_get_attribution_filters'));
        add_action('wp_ajax_gr_update_channel_costs', array($this, 'handle_update_channel_costs'));
        add_action('wp_ajax_gr_detect_attributer_fields', array($this, 'handle_detect_attributer_fields'));
        add_action('wp_ajax_gr_export_attribution_csv', array($this, 'handle_attribution_csv_export'));
        add_action('wp_ajax_gr_export_attribution_pdf', array($this, 'handle_attribution_pdf_export'));
    }

    /**
     * Initialize plugin
     */
    public function init() {
        load_plugin_textdomain('gf-quickreports', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Initialize attribution system if enabled
        $this->maybe_init_attribution();
    }
    
    /**
     * Maybe initialize attribution system
     */
    private function maybe_init_attribution() {
        $settings = get_option(GR_AttributionSettings::SETTINGS_OPTION, GR_AttributionSettings::get_default_settings());
        
        if (!empty($settings['enable_attribution'])) {
            // Show admin notice about attribution status
            add_action('admin_notices', array($this, 'show_attribution_status'));
        }
    }
    
    /**
     * Show attribution system status
     */
    public function show_attribution_status() {
        $current_screen = get_current_screen();
        
        // Only show on our plugin pages
        if (!$current_screen || !in_array($current_screen->id, array('forms_page_gr_quickreports', 'forms_page_gr_attribution_settings'))) {
            return;
        }
        
        $forms_with_attribution = GR_AttributerDetector::get_forms_with_attribution();
        
        if (empty($forms_with_attribution)) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p>' . esc_html__('Attribution tracking is enabled but no forms with Attributer fields were detected.', 'gf-quickreports') . '</p>';
            echo '</div>';
        } else {
            $form_count = count($forms_with_attribution);
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>' . sprintf(
                esc_html(_n(
                    'Attribution tracking is active. Found %d form with Attributer fields.',
                    'Attribution tracking is active. Found %d forms with Attributer fields.',
                    $form_count,
                    'gf-quickreports'
                )),
                $form_count
            ) . '</p>';
            echo '</div>';
        }
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
            'gr_quickreports',
            array($this, 'render_reports_page')
        );
    }

    /**
     * Enqueue assets
     */
    public function enqueue_assets($hook) {
        if ($hook !== 'forms_page_gr_quickreports') {
            return;
        }

        // Enqueue Chart.js
        wp_enqueue_script(
            'chartjs',
            GR_QUICKREPORTS_PLUGIN_URL . 'assets/js/lib/chart.min.js',
            array(),
            '3.9.1',
            true
        );

        // Enqueue plugin scripts
        wp_enqueue_script(
            'gr-quickreports-admin',
            GR_QUICKREPORTS_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'chartjs'),
            GR_QUICKREPORTS_VERSION,
            true
        );
        
        // Enqueue attribution scripts if enabled
        $attribution_settings = get_option(GR_AttributionSettings::SETTINGS_OPTION, GR_AttributionSettings::get_default_settings());
        if (!empty($attribution_settings['enable_attribution'])) {
            wp_enqueue_script(
                'gr-attribution-admin',
                GR_QUICKREPORTS_PLUGIN_URL . 'assets/js/attribution.js',
                array('jquery', 'chartjs', 'gr-quickreports-admin'),
                GR_QUICKREPORTS_VERSION,
                true
            );
        }

        // Enqueue plugin styles
        wp_enqueue_style(
            'gr-quickreports-admin',
            GR_QUICKREPORTS_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            GR_QUICKREPORTS_VERSION
        );

        // Localize script
        wp_localize_script('gr-quickreports-admin', 'gr_quickreports_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gr_quickreports_nonce')
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
        $chart_data = $this->get_chart_data($form_id, $start_date, $end_date, $chart_mode);
        $compare_chart_data = $compare_form_id ? $this->get_chart_data($compare_form_id, $start_date, $end_date, $chart_mode) : null;

        // Get chart data for revenue
        $revenue_chart_data = $this->get_chart_data($form_id, $start_date, $end_date, $chart_mode, 'revenue');
        $compare_revenue_chart_data = $compare_form_id ? $this->get_chart_data($compare_form_id, $start_date, $end_date, $chart_mode, 'revenue') : null;

        // Get individual forms data for "All Forms" view (entries)
        $individual_forms_data = array();
        $individual_revenue_data = array();
        if ($form_id === 0) {
            foreach ($forms as $form) {
                $form_data = $this->get_chart_data($form['id'], $start_date, $end_date, $chart_mode);
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

        // Pass data to the template
        include_once plugin_dir_path(__FILE__) . 'templates/reports-page.php';
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

        $table_name = GFFormsModel::get_entry_table_name();
        $where_parts = array();
        $where_parts[] = $wpdb->prepare("status = %s", "active");

        if ($form_id !== 'all') {
            $where_parts[] = $wpdb->prepare("form_id = %d", $form_id);
        }

        if (!empty($start_date)) {
            $where_parts[] = $wpdb->prepare("date_created >= %s", $start_date . ' 00:00:00');
        }
        if (!empty($end_date)) {
            $where_parts[] = $wpdb->prepare("date_created <= %s", $end_date . ' 23:59:59');
        }

        $where = implode(' AND ', $where_parts);

        if ($mode === 'total') {
            // For entry totals
            $query = "SELECT COUNT(*) as count FROM {$table_name} WHERE {$where}";
            $result = $wpdb->get_var($query);
            return array('labels' => array('Total'), 'data' => array((int)$result));
        }
        
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

    /**
     * Calculate revenue from entry IDs. Made public and more robust.
     */
    public function calculate_revenue_from_entries($entry_ids) {
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
            
            // Use Gravity Forms built-in functions for robust product/price extraction
            $products = GFCommon::get_product_fields($form, $entry);
            
            if (!empty($products['products'])) {
                foreach ($products['products'] as $product) {
                    $price = GFCommon::to_number($product['price']);
                    if (is_numeric($price)) {
                        $total_revenue += $price * $product['quantity'];
                    }
                }
            }
        }
        
        return round($total_revenue, 2);
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
     * Get daily revenue for chart.
     */
    public static function get_daily_revenue($form_id, $start_date, $end_date) {
        $daily_revenue = array();

        if (!$start_date || !$end_date) {
            return $daily_revenue;
        }

        $instance = self::get_instance();
        
        $form_ids = [];
        if ($form_id === 'all') {
            // Get all form IDs that have product fields to avoid unnecessary processing
            $all_forms = GFAPI::get_forms();
            foreach ($all_forms as $form) {
                $product_fields = GFCommon::get_fields_by_type($form, ['product']);
                if (!empty($product_fields)) {
                    $form_ids[] = $form['id'];
                }
            }
        } elseif (is_numeric($form_id) && $form_id > 0) {
            // Use the single form ID provided
            $form_ids = [$form_id];
        }

        // Initialize date range array to ensure all days are present
        $current_ts = strtotime($start_date);
        $end_ts = strtotime($end_date);
        while ($current_ts <= $end_ts) {
            $daily_revenue[gmdate('M j', $current_ts)] = 0;
            $current_ts = strtotime('+1 day', $current_ts);
        }

        if (empty($form_ids)) {
            return $daily_revenue;
        }

        $search_criteria = [
            'status' => 'active',
            'start_date' => $start_date . ' 00:00:00',
            'end_date' => $end_date . ' 23:59:59',
        ];

        // Fetch all entries in the date range at once for efficiency
        $entries = GFAPI::get_entries($form_ids, $search_criteria, null, ['page_size' => 2000]);

        // Process entries and group revenue by day
        foreach ($entries as $entry) {
            $entry_date = gmdate('M j', strtotime($entry['date_created']));
            if (isset($daily_revenue[$entry_date])) {
                $revenue = $instance->calculate_revenue_from_entries(array($entry['id']));
                $daily_revenue[$entry_date] += $revenue;
            }
        }

        return $daily_revenue;
    }

    /**
     * Handle CSV export
     */
    public function handle_csv_export() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'gr_quickreports_nonce')) {
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
        if (!wp_verify_nonce($_POST['nonce'], 'gr_quickreports_nonce')) {
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
            require_once GR_QUICKREPORTS_PLUGIN_DIR . 'vendor/autoload.php';
            
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
        if (!wp_verify_nonce($_POST['nonce'], 'gr_quickreports_nonce')) {
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
        if (!wp_verify_nonce($_POST['nonce'], 'gr_quickreports_nonce')) {
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
    
    /**
     * Handle get attribution data AJAX request
     */
    public function handle_get_attribution_data() {
        if (!wp_verify_nonce($_POST['nonce'], 'gr_quickreports_nonce')) {
            wp_die(esc_html__('Security check failed', 'gf-quickreports'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'gf-quickreports'));
        }
        
        $form_id = !empty($_POST['form_id']) ? wp_unslash($_POST['form_id']) : '';
        $start_date = !empty($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '';
        $end_date = !empty($_POST['end_date']) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : '';
        $group_by = !empty($_POST['group_by']) ? sanitize_text_field(wp_unslash($_POST['group_by'])) : 'channel';
        
        $args = array(
            'start_date' => $start_date,
            'end_date' => $end_date,
            'group_by' => $group_by
        );
        
        if ($form_id !== 'all') {
            $args['form_id'] = absint($form_id);
        }
        
        $attribution_data = GR_AttributionCache::get_attribution_data($args);
        
        wp_send_json_success($attribution_data);
    }
    
    /**
     * Handle get attribution filters AJAX request
     */
    public function handle_get_attribution_filters() {
        if (!wp_verify_nonce($_POST['nonce'], 'gr_quickreports_nonce')) {
            wp_die(esc_html__('Security check failed', 'gf-quickreports'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'gf-quickreports'));
        }
        
        $form_id = !empty($_POST['form_id']) ? wp_unslash($_POST['form_id']) : '';
        $start_date = !empty($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '';
        $end_date = !empty($_POST['end_date']) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : '';
        
        $args = array(
            'start_date' => $start_date,
            'end_date' => $end_date
        );
        
        if ($form_id !== 'all') {
            $args['form_id'] = absint($form_id);
        }
        
        $filters = array(
            'channels' => GR_AttributionCache::get_unique_values('channel', $args),
            'sources' => GR_AttributionCache::get_unique_values('source', $args),
            'campaigns' => GR_AttributionCache::get_unique_values('campaign', $args),
            'landing_page_groups' => GR_AttributionCache::get_unique_values('landing_page_group', $args)
        );
        
        wp_send_json_success($filters);
    }
    
    /**
     * Handle update channel costs AJAX request
     */
    public function handle_update_channel_costs() {
        if (!wp_verify_nonce($_POST['nonce'], 'gr_quickreports_nonce')) {
            wp_die(esc_html__('Security check failed', 'gf-quickreports'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'gf-quickreports'));
        }
        
        $cost_data = array(
            'channel' => !empty($_POST['channel']) ? sanitize_text_field(wp_unslash($_POST['channel'])) : '',
            'source' => !empty($_POST['source']) ? sanitize_text_field(wp_unslash($_POST['source'])) : '',
            'campaign' => !empty($_POST['campaign']) ? sanitize_text_field(wp_unslash($_POST['campaign'])) : '',
            'cost_per_acquisition' => !empty($_POST['cost_per_acquisition']) ? floatval($_POST['cost_per_acquisition']) : 0,
            'monthly_budget' => !empty($_POST['monthly_budget']) ? floatval($_POST['monthly_budget']) : 0,
            'notes' => !empty($_POST['notes']) ? sanitize_textarea_field(wp_unslash($_POST['notes'])) : ''
        );
        
        if (empty($cost_data['channel'])) {
            wp_send_json_error(esc_html__('Channel name is required', 'gf-quickreports'));
            return;
        }
        
        $result = GR_AttributionCache::update_channel_costs($cost_data);
        
        if ($result) {
            wp_send_json_success(esc_html__('Channel costs updated successfully', 'gf-quickreports'));
        } else {
            wp_send_json_error(esc_html__('Failed to update channel costs', 'gf-quickreports'));
        }
    }
    
    /**
     * Handle detect Attributer fields AJAX request
     */
    public function handle_detect_attributer_fields() {
        if (!wp_verify_nonce($_POST['nonce'], 'gr_quickreports_nonce')) {
            wp_die(esc_html__('Security check failed', 'gf-quickreports'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'gf-quickreports'));
        }
        
        $form_id = !empty($_POST['form_id']) ? absint($_POST['form_id']) : 0;
        
        if (!$form_id) {
            wp_send_json_error(esc_html__('Form ID is required', 'gf-quickreports'));
            return;
        }
        
        $detected_fields = GR_AttributerDetector::detect_attributer_fields($form_id);
        $forms_with_attribution = GR_AttributerDetector::get_forms_with_attribution();
        
        wp_send_json_success(array(
            'detected_fields' => $detected_fields,
            'forms_with_attribution' => $forms_with_attribution
        ));
    }
    
    /**
     * Handle attribution CSV export
     */
    public function handle_attribution_csv_export() {
        if (!wp_verify_nonce($_POST['nonce'], 'gr_quickreports_nonce')) {
            wp_die(esc_html__('Security check failed', 'gf-quickreports'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'gf-quickreports'));
        }
        
        $form_id = !empty($_POST['form_id']) ? wp_unslash($_POST['form_id']) : 'all';
        $group_by = !empty($_POST['group_by']) ? sanitize_text_field(wp_unslash($_POST['group_by'])) : 'channel';
        $start_date = !empty($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '';
        $end_date = !empty($_POST['end_date']) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : '';
        
        // Get attribution data
        $args = array(
            'start_date' => $start_date,
            'end_date' => $end_date,
            'group_by' => $group_by
        );
        
        if ($form_id !== 'all') {
            $args['form_id'] = absint($form_id);
        }
        
        $attribution_data = GR_AttributionCache::get_attribution_data($args);
        
        // Get cost data for ROI calculations
        $channel_costs = array();
        $unique_channels = array_unique(array_column($attribution_data, 'group_name'));
        foreach ($unique_channels as $channel) {
            $cost_data = GR_AttributionCache::get_channel_costs($channel);
            if ($cost_data) {
                $channel_costs[$channel] = $cost_data;
            }
        }
        
        // Calculate ROI and other metrics
        $calculated_data = GR_AttributionCalculator::calculate_roi($attribution_data, $channel_costs);
        
        // Set headers for CSV download
        $filename = 'attribution-report-' . gmdate('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Create CSV content
        $output = fopen('php://output', 'w');
        
        // CSV Headers
        $headers = array(
            ucfirst($group_by),
            'Entries',
            'Revenue',
            'Avg Revenue/Entry',
            'Cost',
            'ROI (%)',
            'Profit'
        );
        
        fputcsv($output, $headers);
        
        // CSV Data
        foreach ($calculated_data as $row) {
            $group_name = $row['group_name'] ?? $row['date_group'] ?? '(not set)';
            $entries = intval($row['entries']);
            $revenue = floatval($row['total_revenue']);
            $avg_revenue = $entries > 0 ? $revenue / $entries : 0;
            $cost = isset($row['total_cost']) ? floatval($row['total_cost']) : 0;
            $roi = isset($row['roi']) ? floatval($row['roi']) : 0;
            $profit = isset($row['profit']) ? floatval($row['profit']) : $revenue - $cost;
            
            $csv_row = array(
                $group_name,
                $entries,
                '$' . number_format($revenue, 2),
                '$' . number_format($avg_revenue, 2),
                '$' . number_format($cost, 2),
                number_format($roi, 2) . '%',
                '$' . number_format($profit, 2)
            );
            
            fputcsv($output, $csv_row);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Handle attribution PDF export
     */
    public function handle_attribution_pdf_export() {
        if (!wp_verify_nonce($_POST['nonce'], 'gr_quickreports_nonce')) {
            wp_die(esc_html__('Security check failed', 'gf-quickreports'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'gf-quickreports'));
        }
        
        $form_id = !empty($_POST['form_id']) ? wp_unslash($_POST['form_id']) : 'all';
        $group_by = !empty($_POST['group_by']) ? sanitize_text_field(wp_unslash($_POST['group_by'])) : 'channel';
        $start_date = !empty($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '';
        $end_date = !empty($_POST['end_date']) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : '';
        
        // Get chart images
        $entries_chart = !empty($_POST['entries_chart']) ? wp_unslash($_POST['entries_chart']) : '';
        $revenue_chart = !empty($_POST['revenue_chart']) ? wp_unslash($_POST['revenue_chart']) : '';
        
        try {
            require_once GR_QUICKREPORTS_PLUGIN_DIR . 'vendor/autoload.php';
            
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->setPaper('A4', 'portrait');
            
            // Get attribution data
            $args = array(
                'start_date' => $start_date,
                'end_date' => $end_date,
                'group_by' => $group_by
            );
            
            if ($form_id !== 'all') {
                $args['form_id'] = absint($form_id);
            }
            
            $attribution_data = GR_AttributionCache::get_attribution_data($args);
            
            // Get cost data for ROI calculations
            $channel_costs = array();
            $unique_channels = array_unique(array_column($attribution_data, 'group_name'));
            foreach ($unique_channels as $channel) {
                $cost_data = GR_AttributionCache::get_channel_costs($channel);
                if ($cost_data) {
                    $channel_costs[$channel] = $cost_data;
                }
            }
            
            // Calculate metrics
            $calculated_data = GR_AttributionCalculator::calculate_roi($attribution_data, $channel_costs);
            $performance_metrics = GR_AttributionCalculator::calculate_performance_metrics($calculated_data);
            
            // Generate PDF HTML
            $html = $this->generate_attribution_pdf_html($calculated_data, $performance_metrics, $group_by, $start_date, $end_date, $entries_chart, $revenue_chart);
            
            $dompdf->loadHtml($html);
            $dompdf->render();
            
            $filename = 'attribution-report-' . gmdate('Y-m-d') . '.pdf';
            $dompdf->stream($filename, array('Attachment' => true));
            
        } catch (Exception $e) {
            wp_die(esc_html__('PDF generation failed: ', 'gf-quickreports') . esc_html($e->getMessage()));
        }
        
        exit;
    }
    
    /**
     * Generate PDF HTML for attribution report
     */
    private function generate_attribution_pdf_html($data, $metrics, $group_by, $start_date, $end_date, $entries_chart, $revenue_chart) {
        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Attribution Report</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 10px; }
                .summary { margin-bottom: 30px; }
                .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px; }
                .summary-card { background: #f8f9fa; padding: 15px; border-radius: 5px; text-align: center; }
                .card-value { font-size: 24px; font-weight: bold; color: #333; }
                .card-label { font-size: 12px; color: #666; margin-top: 5px; }
                .charts { margin-bottom: 30px; }
                .chart { margin-bottom: 20px; text-align: center; }
                .chart img { max-width: 100%; height: auto; }
                .data-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                .data-table th, .data-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                .data-table th { background: #f2f2f2; font-weight: bold; }
                .positive { color: #28a745; }
                .negative { color: #dc3545; }
                .neutral { color: #6c757d; }
                .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Attribution Analytics Report</h1>
                <p>Period: ' . esc_html($start_date) . ' to ' . esc_html($end_date) . '</p>
                <p>Grouped by: ' . esc_html(ucfirst($group_by)) . '</p>
                <p>Generated: ' . esc_html(gmdate('Y-m-d H:i:s')) . ' UTC</p>
            </div>
            
            <div class="summary">
                <h2>Performance Summary</h2>
                <div class="summary-grid">
                    <div class="summary-card">
                        <div class="card-value">' . esc_html(number_format($metrics['total_entries'])) . '</div>
                        <div class="card-label">Total Entries</div>
                    </div>
                    <div class="summary-card">
                        <div class="card-value">$' . esc_html(number_format($metrics['total_revenue'], 2)) . '</div>
                        <div class="card-label">Total Revenue</div>
                    </div>
                    <div class="summary-card">
                        <div class="card-value">$' . esc_html(number_format($metrics['avg_revenue_per_entry'], 2)) . '</div>
                        <div class="card-label">Avg Revenue/Entry</div>
                    </div>
                    <div class="summary-card">
                        <div class="card-value">' . esc_html(number_format($metrics['overall_roi'], 1)) . '%</div>
                        <div class="card-label">Overall ROI</div>
                    </div>
                </div>
            </div>';
        
        // Add charts if available
        if (!empty($entries_chart) || !empty($revenue_chart)) {
            $html .= '<div class="charts">
                <h2>Performance Charts</h2>';
            
            if (!empty($entries_chart)) {
                $html .= '<div class="chart">
                    <h3>Entries by ' . esc_html(ucfirst($group_by)) . '</h3>
                    <img src="' . $entries_chart . '" alt="Entries Chart">
                </div>';
            }
            
            if (!empty($revenue_chart)) {
                $html .= '<div class="chart">
                    <h3>Revenue by ' . esc_html(ucfirst($group_by)) . '</h3>
                    <img src="' . $revenue_chart . '" alt="Revenue Chart">
                </div>';
            }
            
            $html .= '</div>';
        }
        
        // Add data table
        $html .= '<div class="data">
            <h2>Detailed Performance Data</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>' . esc_html(ucfirst($group_by)) . '</th>
                        <th>Entries</th>
                        <th>Revenue</th>
                        <th>Avg Revenue/Entry</th>
                        <th>Cost</th>
                        <th>ROI</th>
                        <th>Profit</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($data as $row) {
            $group_name = $row['group_name'] ?? $row['date_group'] ?? '(not set)';
            $entries = intval($row['entries']);
            $revenue = floatval($row['total_revenue']);
            $avg_revenue = $entries > 0 ? $revenue / $entries : 0;
            $cost = isset($row['total_cost']) ? floatval($row['total_cost']) : 0;
            $roi = isset($row['roi']) ? floatval($row['roi']) : 0;
            $profit = isset($row['profit']) ? floatval($row['profit']) : $revenue - $cost;
            
            $roi_class = 'neutral';
            if ($roi > 0) $roi_class = 'positive';
            elseif ($roi < 0) $roi_class = 'negative';
            
            $html .= '<tr>
                <td><strong>' . esc_html($group_name) . '</strong></td>
                <td>' . esc_html(number_format($entries)) . '</td>
                <td>$' . esc_html(number_format($revenue, 2)) . '</td>
                <td>$' . esc_html(number_format($avg_revenue, 2)) . '</td>
                <td>$' . esc_html(number_format($cost, 2)) . '</td>
                <td class="' . $roi_class . '">' . esc_html(number_format($roi, 1)) . '%</td>
                <td class="' . ($profit >= 0 ? 'positive' : 'negative') . '">$' . esc_html(number_format($profit, 2)) . '</td>
            </tr>';
        }
        
        $html .= '</tbody>
            </table>
        </div>
        
        <div class="footer">
            <p>Generated by Quick Reports for Gravity Forms</p>
        </div>
        
        </body>
        </html>';
        
        return $html;
    }
}

// Initialize plugin
add_action('plugins_loaded', array('GR_QuickReports', 'get_instance'));

// Global function for template compatibility
function gr_quickreports_get_daily_entries($form_id, $start_date, $end_date) {
    return GR_QuickReports::get_daily_entries($form_id, $start_date, $end_date);
}

/**
 * Wrapper for getting daily revenue data.
 */
function gr_quickreports_get_daily_revenue($form_id, $start_date, $end_date) {
    return GR_QuickReports::get_daily_revenue($form_id, $start_date, $end_date);
}