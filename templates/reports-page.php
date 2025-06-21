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
$selected_form = isset($_GET['form_id']) ? sanitize_text_field(wp_unslash($_GET['form_id'])) : 0;
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
                    
                    // Use new helper for revenue
                    $daily_revenue = gf_quickreports_get_daily_revenue($form_id, $start_date, $end_date);
                    $form_revenue = array_sum($daily_revenue);

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
                        'has_products' => $form_revenue > 0
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
                $product_fields = array('has_products' => $total_revenue > 0);
                
            } else {
                // Single form processing
                $form_id = is_numeric($selected_form) ? absint($selected_form) : 0;
                $entry_count = GFAPI::count_entries($form_id, $search_criteria);
                $form = GFAPI::get_form($form_id);

                // Calculate daily entries for chart and per-day summary
                $daily_entries = gf_quickreports_get_daily_entries($form_id, $start_date, $end_date);
                $days_count = count($daily_entries);
                $avg_per_day = $days_count > 0 ? array_sum($daily_entries) / $days_count : 0;

                // Use new helper for revenue
                $daily_revenue = gf_quickreports_get_daily_revenue($form_id, $start_date, $end_date);
                $total_revenue = array_sum($daily_revenue);
                $product_fields = array('has_products' => $total_revenue > 0);
            }
            // Comparison form stats
            $compare_stats = null;
            if ($compare_form && $selected_form !== 'all') {
                $compare_entry_count = GFAPI::count_entries($compare_form, $search_criteria);
                $compare_form_obj = GFAPI::get_form($compare_form);
                $compare_daily_revenue = gf_quickreports_get_daily_revenue($compare_form, $start_date, $end_date);
                $compare_total_revenue = array_sum($compare_daily_revenue);

                $compare_stats = array(
                    'entry_count' => $compare_entry_count,
                    'form_title' => $compare_form_obj['title'],
                    'total_revenue' => $compare_total_revenue > 0 ? $compare_total_revenue : null
                );
                 $compare_daily_entries = gf_quickreports_get_daily_entries($compare_form, $start_date, $end_date);
                 $compare_days_count = count($compare_daily_entries);
                 $compare_avg_per_day = $compare_days_count > 0 ? array_sum($compare_daily_entries) / $compare_days_count : 0;
            }
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
                            <?php if ($show_by === 'per_day'): 
                                $form_daily_entries = gf_quickreports_get_daily_entries($form_data['form_id'], $start_date, $end_date);
                                $days_count = count($form_daily_entries);
                                $form_avg_per_day = $days_count > 0 ? $form_data['entry_count'] / $days_count : 0;
                            ?>
                                <td><?php echo number_format($form_avg_per_day, 2); ?></td>
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
                            <td><?php echo !empty($product_fields['has_products']) ? ('$' . number_format($total_revenue, 2)) : 'N/A'; ?></td>
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
            
            <!-- Revenue Chart Container -->
            <div class="chart-container">
                <h3><?php echo $selected_form === 'all' ? esc_html__('All Forms - Revenue Over Time', 'gf-quickreports') : esc_html__('Revenue Over Time', 'gf-quickreports'); ?></h3>
                <canvas id="revenueChart" width="400" height="200"></canvas>
                <div id="revenue-chartjs-no-data" style="display:none; color:#888; text-align:center; margin-top:20px;"><?php esc_html_e('No revenue data for this period.', 'gf-quickreports'); ?></div>
            </div>
        </div>
    <?php
    // Prepare data for charts

    // Entry Data
    $chart_labels = !empty($daily_entries) ? array_keys($daily_entries) : [];
    $chart_data_values = !empty($daily_entries) ? array_values($daily_entries) : [];

    // Revenue Data - using the new helper function
    $daily_revenue = gf_quickreports_get_daily_revenue($selected_form, $start_date, $end_date);
    $revenue_chart_labels = !empty($daily_revenue) ? array_keys($daily_revenue) : $chart_labels;
    $revenue_chart_data_values = !empty($daily_revenue) ? array_values($daily_revenue) : array_fill(0, count($chart_labels), 0);

    // Comparison Data
    $compare_chart_data_values = ($compare_form && $selected_form !== 'all') ? array_values(gf_quickreports_get_daily_entries($compare_form, $start_date, $end_date)) : [];
    $compare_revenue_data_values = ($compare_form && $selected_form !== 'all') ? array_values(gf_quickreports_get_daily_revenue($compare_form, $start_date, $end_date)) : [];
    
    // Data for "All Forms" individual lines
    $individual_forms_data = [];
    $individual_revenue_data = [];
    if ($selected_form === 'all') {
        $chart_view = isset($_GET['chart_view']) ? sanitize_text_field(wp_unslash($_GET['chart_view'])) : 'individual';
        $colors = ['#2271b1', '#34c759', '#ff9500', '#ff3b30', '#af52de', '#5856d6', '#007aff', '#5ac8fa', '#ffcc02', '#ff9500'];
        foreach ($all_forms_data as $index => $form_data) {
            // Entry data for each form
            $form_daily_entries = gf_quickreports_get_daily_entries($form_data['form_id'], $start_date, $end_date);
            $individual_forms_data[] = [
                'label' => $form_data['form_title'],
                'data' => array_values($form_daily_entries),
                'borderColor' => $colors[$index % count($colors)],
                'backgroundColor' => str_replace(')', ', 0.1)', $colors[$index % count($colors)]),
                'borderWidth' => 2,
                'fill' => false,
                'tension' => 0.4,
            ];

            // Revenue data for each form
            if ($form_data['has_products']) {
                $form_daily_revenue = gf_quickreports_get_daily_revenue($form_data['form_id'], $start_date, $end_date);
                 if (array_sum($form_daily_revenue) > 0) {
                    $individual_revenue_data[] = [
                        'label' => $form_data['form_title'],
                        'data' => array_values($form_daily_revenue),
                        'borderColor' => $colors[$index % count($colors)],
                        'backgroundColor' => str_replace(')', ', 0.1)', $colors[$index % count($colors)]),
                        'borderWidth' => 2,
                        'fill' => false,
                        'tension' => 0.4,
                    ];
                }
            }
        }
    }

    $chart_script = "
    window.chartView = " . json_encode($chart_view ?? 'aggregated') . ";
    window.chartMode = " . json_encode($show_by) . ";
    window.selectedFormLabel = " . json_encode($selected_form === 'all' ? 'All Forms' : ($form['title'] ?? '')) . ";

    window.chartData = {
        labels: " . json_encode($chart_labels) . ",
        data: " . json_encode($chart_data_values) . "
    };
    window.revenueChartData = {
        labels: " . json_encode($revenue_chart_labels) . ",
        data: " . json_encode($revenue_chart_data_values) . "
    };
    
    window.compareChartData = {
        data: " . json_encode($compare_chart_data_values) . "
    };
    window.compareRevenueChartData = {
        data: " . json_encode($compare_revenue_data_values) . "
    };

    window.individualFormsData = " . json_encode($individual_forms_data) . ";
    window.individualRevenueData = " . json_encode($individual_revenue_data) . ";
    ";
    
    wp_add_inline_script('gf-quickreports-admin', $chart_script, 'before');
    ?>
<?php endif; ?>
</div> <!-- .wrap --> 