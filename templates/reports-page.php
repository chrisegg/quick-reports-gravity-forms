<?php
/**
 * Quick Reports for Gravity Forms - Reports Page Template
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check user capabilities
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'gf-quickreports'));
}

if (!class_exists('GFFormsModel')) {
    echo '<div class="notice notice-error"><p>Gravity Forms is not active. Please install and activate Gravity Forms to use this add-on.</p></div>';
    return;
}

$forms = GFAPI::get_forms();
$selected_form = isset($_GET['form_id']) ? $_GET['form_id'] : 0;
$date_preset = isset($_GET['date_preset']) ? sanitize_text_field(wp_unslash($_GET['date_preset'])) : 'custom';

// Handle date preset logic
if ($date_preset && $date_preset !== 'custom') {
    switch($date_preset) {
        case 'today':
            $start_date = gmdate('Y-m-d');
            $end_date = gmdate('Y-m-d');
            break;
        case 'yesterday':
            $start_date = gmdate('Y-m-d', strtotime('-1 day'));
            $end_date = gmdate('Y-m-d', strtotime('-1 day'));
            break;
        case '7days':
            $start_date = gmdate('Y-m-d', strtotime('-7 days'));
            $end_date = gmdate('Y-m-d');
            break;
        case '30days':
            $start_date = gmdate('Y-m-d', strtotime('-30 days'));
            $end_date = gmdate('Y-m-d');
            break;
        case '60days':
            $start_date = gmdate('Y-m-d', strtotime('-60 days'));
            $end_date = gmdate('Y-m-d');
            break;
        case '90days':
            $start_date = gmdate('Y-m-d', strtotime('-90 days'));
            $end_date = gmdate('Y-m-d');
            break;
        case 'year_to_date':
            $start_date = gmdate('Y-01-01');
            $end_date = gmdate('Y-m-d');
            break;
        case 'last_year':
            $start_date = gmdate('Y-01-01', strtotime('-1 year'));
            $end_date = gmdate('Y-12-31', strtotime('-1 year'));
            break;
        default:
            $start_date = isset($_GET['start']) ? sanitize_text_field(wp_unslash($_GET['start'])) : gmdate('Y-m-d', strtotime('-30 days'));
            $end_date = isset($_GET['end']) ? sanitize_text_field(wp_unslash($_GET['end'])) : gmdate('Y-m-d');
            break;
    }
} else {
    $start_date = isset($_GET['start']) ? sanitize_text_field(wp_unslash($_GET['start'])) : gmdate('Y-m-d', strtotime('-30 days'));
    $end_date = isset($_GET['end']) ? sanitize_text_field(wp_unslash($_GET['end'])) : gmdate('Y-m-d');
}

?>
<div class="wrap">
    <h1><?php esc_html_e('Quick Reports for Gravity Forms', 'gf-quickreports'); ?></h1>
    <!-- WP Admin TableNav Filters Bar -->
    <form method="GET">
        <div class="tablenav top">
            <div class="alignleft actions">
                <label for="form_id" class="screen-reader-text"><?php esc_html_e('Select form', 'gf-quickreports'); ?></label>
                <select name="form_id" id="form_id">
                    <option value=""><?php esc_html_e('Select a form', 'gf-quickreports'); ?></option>
                    <option value="all" <?php selected($selected_form, 'all'); ?>><?php esc_html_e('All Forms', 'gf-quickreports'); ?></option>
                    <?php foreach ($forms as $form): ?>
                        <option value="<?php echo esc_attr($form['id']); ?>" <?php selected($selected_form, $form['id']); ?>>
                            <?php echo esc_html($form['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="alignleft actions">
                <label for="compare_form_id" class="screen-reader-text"><?php esc_html_e('Compare With', 'gf-quickreports'); ?></label>
                <select name="compare_form_id" id="compare_form_id" <?php echo ($selected_form === 'all' || !$selected_form) ? 'disabled' : ''; ?>>
                    <option value=""><?php esc_html_e('Compare With...', 'gf-quickreports'); ?></option>
                    <?php 
                    // Add the currently selected comparison form as an option if it exists
                    $current_compare_form_id = isset($_GET['compare_form_id']) ? absint(wp_unslash($_GET['compare_form_id'])) : '';
                    if ($current_compare_form_id && $selected_form && $selected_form !== 'all') {
                        $compare_form_obj = GFAPI::get_form($current_compare_form_id);
                        if ($compare_form_obj) {
                            echo '<option value="' . esc_attr($current_compare_form_id) . '" selected>' . esc_html($compare_form_obj['title']) . '</option>';
                        }
                    }
                    ?>
                </select>
            </div>
            <div class="alignleft actions">
                <label for="show_by" class="screen-reader-text"><?php esc_html_e('Show by', 'gf-quickreports'); ?></label>
                <select name="show_by" id="show_by">
                    <option value="total" <?php selected(isset($_GET['show_by']) ? sanitize_text_field(wp_unslash($_GET['show_by'])) : 'total', 'total'); ?>><?php esc_html_e('Total', 'gf-quickreports'); ?></option>
                    <option value="per_day" <?php selected(isset($_GET['show_by']) ? sanitize_text_field(wp_unslash($_GET['show_by'])) : '', 'per_day'); ?>><?php esc_html_e('Per Day', 'gf-quickreports'); ?></option>
                </select>
            </div>
            <div class="alignleft actions">
                <label for="date_preset" class="screen-reader-text"><?php esc_html_e('Date Preset', 'gf-quickreports'); ?></label>
                <select name="date_preset" id="date_preset">
                    <option value="custom" <?php selected($date_preset, 'custom'); ?>><?php esc_html_e('Custom Range', 'gf-quickreports'); ?></option>
                    <option value="today" <?php selected($date_preset, 'today'); ?>><?php esc_html_e('Today', 'gf-quickreports'); ?></option>
                    <option value="yesterday" <?php selected($date_preset, 'yesterday'); ?>><?php esc_html_e('Yesterday', 'gf-quickreports'); ?></option>
                    <option value="7days" <?php selected($date_preset, '7days'); ?>><?php esc_html_e('Last 7 Days', 'gf-quickreports'); ?></option>
                    <option value="30days" <?php selected($date_preset, '30days'); ?>><?php esc_html_e('Last 30 Days', 'gf-quickreports'); ?></option>
                    <option value="60days" <?php selected($date_preset, '60days'); ?>><?php esc_html_e('Last 60 Days', 'gf-quickreports'); ?></option>
                    <option value="90days" <?php selected($date_preset, '90days'); ?>><?php esc_html_e('Last 90 Days', 'gf-quickreports'); ?></option>
                    <option value="year_to_date" <?php selected($date_preset, 'year_to_date'); ?>><?php esc_html_e('Year to Date', 'gf-quickreports'); ?></option>
                    <option value="last_year" <?php selected($date_preset, 'last_year'); ?>><?php esc_html_e('Last Year', 'gf-quickreports'); ?></option>
                </select>
            </div>
            <?php if ($selected_form === 'all'): ?>
            <div class="alignleft actions">
                <label for="chart_view" class="screen-reader-text"><?php esc_html_e('Chart View', 'gf-quickreports'); ?></label>
                <select name="chart_view" id="chart_view">
                    <option value="individual" <?php selected(isset($_GET['chart_view']) ? sanitize_text_field(wp_unslash($_GET['chart_view'])) : 'individual', 'individual'); ?>><?php esc_html_e('Individual Forms', 'gf-quickreports'); ?></option>
                    <option value="aggregated" <?php selected(isset($_GET['chart_view']) ? sanitize_text_field(wp_unslash($_GET['chart_view'])) : '', 'aggregated'); ?>><?php esc_html_e('Aggregated Total', 'gf-quickreports'); ?></option>
                </select>
            </div>
            <?php endif; ?>
            <div class="alignleft actions date-range-container">
                <label for="start_date" class="screen-reader-text"><?php esc_html_e('Start Date', 'gf-quickreports'); ?></label>
                <input type="date" name="start" id="start_date" value="<?php echo esc_attr($start_date); ?>">
                <label for="end_date" class="screen-reader-text"><?php esc_html_e('End Date', 'gf-quickreports'); ?></label>
                <input type="date" name="end" id="end_date" value="<?php echo esc_attr($end_date); ?>">
            </div>
            <div class="alignleft actions">
                <input type="hidden" name="page" value="gf_quickreports">
                <input type="hidden" id="current_compare_form_id" value="<?php echo isset($_GET['compare_form_id']) ? esc_attr(wp_unslash($_GET['compare_form_id'])) : ''; ?>">
                <input type="submit" class="button" value="<?php esc_attr_e('Generate Report', 'gf-quickreports'); ?>">
                <?php if ($selected_form): ?>
                    <button type="button" class="button" id="export-csv"><?php esc_html_e('Export CSV', 'gf-quickreports'); ?></button>
                    <button type="button" class="button" id="export-pdf"><?php esc_html_e('Export PDF', 'gf-quickreports'); ?></button>
                <?php endif; ?>
            </div>
            <br class="clear">
        </div>
    </form>
    <?php
    $compare_form = isset($_GET['compare_form_id']) ? absint(wp_unslash($_GET['compare_form_id'])) : 0;
    $show_by = isset($_GET['show_by']) ? sanitize_text_field(wp_unslash($_GET['show_by'])) : 'total';
    ?>
    <?php if ($selected_form): ?>
        <hr>
        <!-- Report Results -->
        <div class="gf-quickreports-results">
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
                    $form_daily_entries = gf_quickreports_get_daily_entries($form_id, $start_date, $end_date);
                    
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
                
            } else {
                // Single form processing
                $form_id = is_numeric($selected_form) ? absint($selected_form) : 0;
                $entry_count = GFAPI::count_entries($form_id, $search_criteria);
                $entries = GFAPI::get_entries($form_id, $search_criteria, null, array('offset' => 0, 'page_size' => 1000));
                $form = GFAPI::get_form($form_id);
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
                $daily_entries = gf_quickreports_get_daily_entries($form_id, $start_date, $end_date);
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
            $compare_daily_entries = ($compare_form && $selected_form !== 'all') ? gf_quickreports_get_daily_entries($compare_form, $start_date, $end_date) : [];
            $compare_days_count = count($compare_daily_entries);
            $compare_avg_per_day = $compare_days_count > 0 ? array_sum($compare_daily_entries) / $compare_days_count : 0;
            ?>
            <table class="wp-list-table widefat fixed striped report-summary-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Form', 'gf-quickreports'); ?></th>
                        <?php if ($show_by === 'per_day'): ?>
                            <th><?php esc_html_e('Entries Per Day', 'gf-quickreports'); ?></th>
                        <?php else: ?>
                            <th><?php esc_html_e('Total Entries', 'gf-quickreports'); ?></th>
                        <?php endif; ?>
                        <th><?php esc_html_e('Date Range', 'gf-quickreports'); ?></th>
                        <th><?php esc_html_e('Total Revenue', 'gf-quickreports'); ?></th>
                        <th><?php esc_html_e('Show By', 'gf-quickreports'); ?></th>
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
                            <td><?php echo ($start_date && $end_date) ? (gmdate('M j, Y', strtotime($start_date)) . ' - ' . gmdate('M j, Y', strtotime($end_date))) : '—'; ?></td>
                            <td><?php echo $form_data['has_products'] ? ('$' . number_format($form_data['revenue'], 2)) : 'N/A'; ?></td>
                            <td><?php echo $show_by === 'per_day' ? esc_html__('Per Day', 'gf-quickreports') : esc_html__('Total', 'gf-quickreports'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <!-- Show totals row -->
                        <tr style="background-color: #f9f9f9; font-weight: bold;">
                            <td><strong><?php esc_html_e('Total (All Forms)', 'gf-quickreports'); ?></strong></td>
                            <?php if ($show_by === 'per_day'): ?>
                                <td><strong><?php echo number_format($avg_per_day, 2); ?></strong></td>
                            <?php else: ?>
                                <td><strong><?php echo number_format($entry_count); ?></strong></td>
                            <?php endif; ?>
                            <td><?php echo ($start_date && $end_date) ? (gmdate('M j, Y', strtotime($start_date)) . ' - ' . gmdate('M j, Y', strtotime($end_date))) : '—'; ?></td>
                            <td><strong><?php echo !empty($product_fields) ? ('$' . number_format($total_revenue, 2)) : 'N/A'; ?></strong></td>
                            <td><?php echo $show_by === 'per_day' ? esc_html__('Per Day', 'gf-quickreports') : esc_html__('Total', 'gf-quickreports'); ?></td>
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
                            <td><?php echo ($start_date && $end_date) ? (gmdate('M j, Y', strtotime($start_date)) . ' - ' . gmdate('M j, Y', strtotime($end_date))) : '—'; ?></td>
                            <td><?php echo !empty($product_fields) ? ('$' . number_format($total_revenue, 2)) : 'N/A'; ?></td>
                            <td><?php echo $show_by === 'per_day' ? esc_html__('Per Day', 'gf-quickreports') : esc_html__('Total', 'gf-quickreports'); ?></td>
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
                        <td><?php echo ($start_date && $end_date) ? (gmdate('M j, Y', strtotime($start_date)) . ' - ' . gmdate('M j, Y', strtotime($end_date))) : '—'; ?></td>
                        <td><?php echo ($compare_stats['total_revenue'] !== null) ? ('$' . number_format($compare_stats['total_revenue'], 2)) : 'N/A'; ?></td>
                        <td><?php echo $show_by === 'per_day' ? esc_html__('Per Day', 'gf-quickreports') : esc_html__('Total', 'gf-quickreports'); ?></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <!-- Chart Container -->
            <div class="chart-container">
                <h3><?php echo $selected_form === 'all' ? esc_html__('All Forms - Entries Over Time', 'gf-quickreports') : esc_html__('Entries Over Time', 'gf-quickreports'); ?></h3>
                <canvas id="entriesChart" width="400" height="200"></canvas>
                <div id="chartjs-no-data" style="display:none; color:#888; text-align:center; margin-top:20px;"><?php esc_html_e('No data for this period.', 'gf-quickreports'); ?></div>
            </div>
            <?php
            // Add inline script to footer to ensure proper loading order
            $chart_labels = array_keys($daily_entries);
            $chart_data_values = array_values($show_by === 'per_day' ? $daily_entries : [array_sum($daily_entries)]);
            
            $chart_script = "
            window.chartMode = " . json_encode($show_by) . ";
            window.chartData = {
                labels: " . json_encode($chart_labels) . ",
                data: " . json_encode($chart_data_values) . "
            };
            window.selectedFormLabel = " . json_encode($selected_form === 'all' ? 'All Forms' : $form['title']) . ";";
            
            // Add individual form data for "All Forms" chart
            if ($selected_form === 'all') {
                $chart_view = isset($_GET['chart_view']) ? sanitize_text_field(wp_unslash($_GET['chart_view'])) : 'individual';
                
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
                        $form_daily_entries = gf_quickreports_get_daily_entries($form_data['form_id'], $start_date, $end_date);
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
            }
            
            if ($compare_form) {
                $compare_data_values = array_values($show_by === 'per_day' ? $compare_daily_entries : [array_sum($compare_daily_entries)]);
                $chart_script .= "
            window.compareChartData = {
                labels: window.chartData.labels,
                data: " . json_encode($compare_data_values) . "
            };";
            }
            
            wp_add_inline_script('gf-quickreports-admin', $chart_script, 'before');
            ?>
        </div>
    <?php endif; ?>
</div> 