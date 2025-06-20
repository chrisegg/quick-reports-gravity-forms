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
                $labels[] = gmdate('Y-m-d', strtotime($row->date));
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
        $form_id = isset($_POST['form_id']) ? absint(wp_unslash($_POST['form_id'])) : 0;
        $compare_form_id = isset($_POST['compare_form_id']) ? absint(wp_unslash($_POST['compare_form_id'])) : 0;
        $start_date = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : '';
        $show_by = isset($_POST['show_by']) ? sanitize_text_field(wp_unslash($_POST['show_by'])) : 'total';

        // Get form data
        $form = GFAPI::get_form($form_id);
        if (!$form) {
            wp_die('Form not found');
        }

        // Get entries
        $search_criteria = array('status' => 'active');
        if (!empty($start_date)) {
            $search_criteria['start_date'] = $start_date . ' 00:00:00';
        }
        if (!empty($end_date)) {
            $search_criteria['end_date'] = $end_date . ' 23:59:59';
        }

        $entries = GFAPI::get_entries($form_id, $search_criteria, null, array('offset' => 0, 'page_size' => 1000));

        // Get product fields for revenue calculation
        $product_fields = array();
        foreach ($form['fields'] as $field) {
            if (isset($field['type']) && $field['type'] === 'product') {
                $product_fields[] = $field['id'];
            }
        }

        // Calculate revenue
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

        // Get comparison form data if provided
        $compare_data = null;
        if ($compare_form_id) {
            $compare_form = GFAPI::get_form($compare_form_id);
            if ($compare_form) {
                $compare_entries = GFAPI::get_entries($compare_form_id, $search_criteria, null, array('offset' => 0, 'page_size' => 1000));
                $compare_entry_count = count($compare_entries);
                
                // Calculate comparison revenue
                $compare_total_revenue = 0;
                $compare_product_fields = array();
                foreach ($compare_form['fields'] as $field) {
                    if (isset($field['type']) && $field['type'] === 'product') {
                        $compare_product_fields[] = $field['id'];
                    }
                }
                
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
                
                $compare_data = array(
                    'form_title' => $compare_form['title'],
                    'entry_count' => $compare_entry_count,
                    'total_revenue' => $compare_total_revenue
                );
            }
        }

        // Generate CSV content
        try {
            $filename = 'gf-quickreports-' . sanitize_title($form['title']) . '-' . gmdate('Y-m-d') . '.csv';
            
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            $output = fopen('php://output', 'w');
            
            // Write summary data
            fputcsv($output, array('Report Summary'));
            fputcsv($output, array('Form', $form['title']));
            fputcsv($output, array('Date Range', $start_date . ' to ' . $end_date));
            fputcsv($output, array('Total Entries', count($entries)));
            fputcsv($output, array('Total Revenue', '$' . number_format($total_revenue, 2)));
            
            if ($compare_data) {
                fputcsv($output, array(''));
                fputcsv($output, array('Comparison Form', $compare_data['form_title']));
                fputcsv($output, array('Comparison Entries', $compare_data['entry_count']));
                fputcsv($output, array('Comparison Revenue', '$' . number_format($compare_data['total_revenue'], 2)));
            }
            
            fputcsv($output, array(''));
            fputcsv($output, array('Detailed Entries'));
            
            // Write headers
            $headers = array('Entry ID', 'Date Created', 'IP Address');
            foreach ($form['fields'] as $field) {
                if (!isset($field['adminOnly']) || !$field['adminOnly']) {
                    $headers[] = $field['label'];
                }
            }
            fputcsv($output, $headers);
            
            // Write entry data
            foreach ($entries as $entry) {
                $row = array(
                    $entry['id'],
                    $entry['date_created'],
                    $entry['ip']
                );
                
                foreach ($form['fields'] as $field) {
                    if (!isset($field['adminOnly']) || !$field['adminOnly']) {
                        $value = rgar($entry, $field['id']);
                        if (is_array($value)) {
                            $value = implode(', ', $value);
                        }
                        $row[] = $value;
                    }
                }
                
                fputcsv($output, $row);
            }
            
            fclose($output);
            exit;
            
        } catch (Exception $e) {
            wp_die('Export failed: ' . $e->getMessage());
        }
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
        $form_id = isset($_POST['form_id']) ? absint(wp_unslash($_POST['form_id'])) : 0;
        $compare_form_id = isset($_POST['compare_form_id']) ? absint(wp_unslash($_POST['compare_form_id'])) : 0;
        $start_date = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : '';
        $show_by = isset($_POST['show_by']) ? sanitize_text_field(wp_unslash($_POST['show_by'])) : 'total';

        // Get form data
        $form = GFAPI::get_form($form_id);
        if (!$form) {
            wp_die('Form not found');
        }

        // Get entries
        $search_criteria = array('status' => 'active');
        if (!empty($start_date)) {
            $search_criteria['start_date'] = $start_date . ' 00:00:00';
        }
        if (!empty($end_date)) {
            $search_criteria['end_date'] = $end_date . ' 23:59:59';
        }

        $entries = GFAPI::get_entries($form_id, $search_criteria, null, array('offset' => 0, 'page_size' => 1000));
        $entry_count = count($entries);

        // Get product fields for revenue calculation
        $product_fields = array();
        foreach ($form['fields'] as $field) {
            if (isset($field['type']) && $field['type'] === 'product') {
                $product_fields[] = $field['id'];
            }
        }

        // Calculate revenue
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

        // Get comparison form data if provided
        $compare_data = null;
        if ($compare_form_id) {
            $compare_form = GFAPI::get_form($compare_form_id);
            if ($compare_form) {
                $compare_entries = GFAPI::get_entries($compare_form_id, $search_criteria, null, array('offset' => 0, 'page_size' => 1000));
                $compare_entry_count = count($compare_entries);
                
                // Calculate comparison revenue
                $compare_total_revenue = 0;
                $compare_product_fields = array();
                foreach ($compare_form['fields'] as $field) {
                    if (isset($field['type']) && $field['type'] === 'product') {
                        $compare_product_fields[] = $field['id'];
                    }
                }
                
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
                
                $compare_data = array(
                    'form_title' => $compare_form['title'],
                    'entry_count' => $compare_entry_count,
                    'total_revenue' => $compare_total_revenue
                );
            }
        }

        // Calculate daily entries for chart
        $daily_entries = self::get_daily_entries($form_id, $start_date, $end_date);
        $total_days = count($daily_entries);
        $avg_per_day = $total_days > 0 ? $entry_count / $total_days : 0;

        try {
            // Generate PDF using DOMPDF
            require_once GF_QUICKREPORTS_PLUGIN_DIR . 'vendor/autoload.php';
            
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->setPaper('A4', 'portrait');
            
            // Create HTML content
            $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Quick Reports - ' . esc_html($form['title']) . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .summary { margin-bottom: 30px; }
        .summary table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .summary th, .summary td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .summary th { background-color: #f2f2f2; font-weight: bold; }
        .entries { margin-top: 30px; }
        .entries table { width: 100%; border-collapse: collapse; font-size: 10px; }
        .entries th, .entries td { border: 1px solid #ddd; padding: 4px; text-align: left; }
        .entries th { background-color: #f2f2f2; font-weight: bold; }
        .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Quick Reports for Gravity Forms</h1>
        <h2>' . esc_html($form['title']) . '</h2>
        <p>Generated on ' . gmdate('F j, Y \a\t g:i A') . '</p>
    </div>
    
    <div class="summary">
        <h3>Report Summary</h3>
        <table>
            <tr><th>Form</th><td>' . esc_html($form['title']) . '</td></tr>
            <tr><th>Date Range</th><td>' . esc_html($start_date . ' to ' . $end_date) . '</td></tr>
            <tr><th>Total Entries</th><td>' . number_format($entry_count) . '</td></tr>
            <tr><th>Average Per Day</th><td>' . number_format($avg_per_day, 2) . '</td></tr>';
            
            if (!empty($product_fields)) {
                $html .= '<tr><th>Total Revenue</th><td>$' . number_format($total_revenue, 2) . '</td></tr>';
            }
            
            if ($compare_data) {
                $html .= '<tr><th>Comparison Form</th><td>' . esc_html($compare_data['form_title']) . '</td></tr>
                <tr><th>Comparison Entries</th><td>' . number_format($compare_data['entry_count']) . '</td></tr>';
                if ($compare_data['total_revenue'] > 0) {
                    $html .= '<tr><th>Comparison Revenue</th><td>$' . number_format($compare_data['total_revenue'], 2) . '</td></tr>';
                }
            }
            
            $html .= '</table>
    </div>
    
    <div class="entries">
        <h3>Recent Entries (Last 10)</h3>
        <table>
            <tr>
                <th>ID</th>
                <th>Date</th>';
            
            // Add field headers
            foreach ($form['fields'] as $field) {
                if (!isset($field['adminOnly']) || !$field['adminOnly']) {
                    $html .= '<th>' . esc_html($field['label']) . '</th>';
                }
            }
            
            $html .= '</tr>';
            
            // Add entry data (limit to 10 for PDF)
            $recent_entries = array_slice($entries, 0, 10);
            foreach ($recent_entries as $entry) {
                $html .= '<tr>
                    <td>' . esc_html($entry['id']) . '</td>
                    <td>' . esc_html($entry['date_created']) . '</td>';
                
                foreach ($form['fields'] as $field) {
                    if (!isset($field['adminOnly']) || !$field['adminOnly']) {
                        $value = rgar($entry, $field['id']);
                        if (is_array($value)) {
                            $value = implode(', ', $value);
                        }
                        $html .= '<td>' . esc_html($value) . '</td>';
                    }
                }
                
                $html .= '</tr>';
            }
            
            $html .= '</table>
    </div>
    
    <div class="footer">
        <p>Generated by Quick Reports for Gravity Forms v' . GF_QUICKREPORTS_VERSION . '</p>
    </div>
</body>
</html>';
            
            $dompdf->loadHtml($html);
            $dompdf->render();
            
            $filename = 'gf-quickreports-' . sanitize_title($form['title']) . '-' . gmdate('Y-m-d') . '.pdf';
            
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            echo $dompdf->output();
            exit;
            
        } catch (Exception $e) {
            wp_die('PDF generation failed: ' . $e->getMessage());
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

        $selected_form = isset($_POST['form_id']) ? sanitize_text_field(wp_unslash($_POST['form_id'])) : '';
        
        if (empty($selected_form) || $selected_form === 'all') {
            wp_send_json(array());
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
        
        wp_send_json($options);
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

        $presets = array(
            array('value' => 'today', 'label' => __('Today', 'gf-quickreports')),
            array('value' => 'yesterday', 'label' => __('Yesterday', 'gf-quickreports')),
            array('value' => '7days', 'label' => __('Last 7 Days', 'gf-quickreports')),
            array('value' => '30days', 'label' => __('Last 30 Days', 'gf-quickreports')),
            array('value' => '60days', 'label' => __('Last 60 Days', 'gf-quickreports')),
            array('value' => '90days', 'label' => __('Last 90 Days', 'gf-quickreports')),
            array('value' => 'year_to_date', 'label' => __('Year to Date', 'gf-quickreports')),
            array('value' => 'last_year', 'label' => __('Last Year', 'gf-quickreports'))
        );
        
        wp_send_json($presets);
    }
}

// Initialize plugin
add_action('plugins_loaded', array('GF_QuickReports', 'get_instance'));

// Global function for template compatibility
function gf_quickreports_get_daily_entries($form_id, $start_date, $end_date) {
    return GF_QuickReports::get_daily_entries($form_id, $start_date, $end_date);
}