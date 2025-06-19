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
     * Handle CSV export
     */
    public function handle_csv_export() {
        // Verify nonce
        if (!check_ajax_referer('gf_quickreports_nonce', 'nonce', false)) {
            wp_send_json_error('Invalid nonce');
        }

        // Check user permissions
        if (!current_user_can('manage_options') && !current_user_can('gravityforms_view_entries')) {
            wp_send_json_error('Unauthorized access');
        }

        // Get parameters
        $form_id = isset($_POST['form_id']) ? absint($_POST['form_id']) : 0;
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';

        if (empty($form_id)) {
            wp_send_json_error('No form selected');
        }

        // Get form
        $form = GFAPI::get_form($form_id);
        if (!$form) {
            wp_send_json_error('Form not found');
        }

        // Get entries
        $search_criteria = array(
            'status' => 'active'
        );

        if (!empty($start_date)) {
            $search_criteria['start_date'] = $start_date;
        }
        if (!empty($end_date)) {
            $search_criteria['end_date'] = $end_date;
        }

        $entries = GFAPI::get_entries($form_id, $search_criteria);

        // Prepare CSV data
        $csv_data = array();
        $headers = array('Entry ID', 'Date', 'IP');

        // Add form fields to headers
        foreach ($form['fields'] as $field) {
            if (!$field['adminOnly']) {
                $headers[] = $field['label'];
            }
        }
        $csv_data[] = $headers;

        // Add entries to CSV data
        foreach ($entries as $entry) {
            $row = array(
                $entry['id'],
                $entry['date_created'],
                $entry['ip']
            );

            foreach ($form['fields'] as $field) {
                if (!$field['adminOnly']) {
                    $row[] = rgar($entry, $field['id']);
                }
            }

            $csv_data[] = $row;
        }

        // Generate CSV
        $output = fopen('php://output', 'w');
        foreach ($csv_data as $row) {
            fputcsv($output, $row);
        }
        fclose($output);

        wp_die();
    }

    /**
     * Handle PDF export
     */
    public function handle_pdf_export() {
        // Verify nonce
        if (!check_ajax_referer('gf_quickreports_nonce', 'nonce', false)) {
            wp_send_json_error('Invalid nonce');
        }

        // Check user permissions
        if (!current_user_can('manage_options') && !current_user_can('gravityforms_view_entries')) {
            wp_send_json_error('Unauthorized access');
        }

        // Get parameters
        $form_id = isset($_POST['form_id']) ? absint($_POST['form_id']) : 0;
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
        $chart_data = isset($_POST['chart_data']) ? $_POST['chart_data'] : '';

        if (empty($form_id)) {
            wp_send_json_error('No form selected');
        }

        // Get form
        $form = GFAPI::get_form($form_id);
        if (!$form) {
            wp_send_json_error('Form not found');
        }

        // Get entries
        $search_criteria = array(
            'status' => 'active'
        );

        if (!empty($start_date)) {
            $search_criteria['start_date'] = $start_date;
        }
        if (!empty($end_date)) {
            $search_criteria['end_date'] = $end_date;
        }

        $entries = GFAPI::get_entries($form_id, $search_criteria);

        // Create PDF
        require_once GF_QUICKREPORTS_PLUGIN_DIR . 'vendor/autoload.php';
        $mpdf = new \Mpdf\Mpdf();

        // Add title
        $mpdf->WriteHTML('<h1>' . esc_html($form['title']) . ' - Report</h1>');

        // Add date range
        if (!empty($start_date) || !empty($end_date)) {
            $mpdf->WriteHTML('<p>Date Range: ' . 
                (!empty($start_date) ? esc_html($start_date) : 'Start') . ' to ' . 
                (!empty($end_date) ? esc_html($end_date) : 'End') . '</p>'
            );
        }

        // Add chart if available
        if (!empty($chart_data)) {
            $chart_image = str_replace('data:image/png;base64,', '', $chart_data);
            $chart_image = base64_decode($chart_image);
            $mpdf->Image('@' . $chart_image, 10, null, 190);
        }

        // Add entries table
        $table_html = '<h2>Recent Entries</h2>';
        $table_html .= '<table border="1" cellpadding="5">';
        
        // Add headers
        $table_html .= '<tr>';
        $table_html .= '<th>Entry ID</th>';
        $table_html .= '<th>Date</th>';
        foreach ($form['fields'] as $field) {
            if (!$field['adminOnly']) {
                $table_html .= '<th>' . esc_html($field['label']) . '</th>';
            }
        }
        $table_html .= '</tr>';

        // Add entries
        foreach ($entries as $entry) {
            $table_html .= '<tr>';
            $table_html .= '<td>' . esc_html($entry['id']) . '</td>';
            $table_html .= '<td>' . esc_html($entry['date_created']) . '</td>';
            foreach ($form['fields'] as $field) {
                if (!$field['adminOnly']) {
                    $table_html .= '<td>' . esc_html(rgar($entry, $field['id'])) . '</td>';
                }
            }
            $table_html .= '</tr>';
        }
        $table_html .= '</table>';

        $mpdf->WriteHTML($table_html);

        // Output PDF
        $mpdf->Output('gf-quickreport.pdf', 'I');
        wp_die();
    }
}

// Initialize plugin
add_action('plugins_loaded', array('GF_QuickReports', 'get_instance'));