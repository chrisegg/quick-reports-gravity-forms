<?php
/**
 * Attribution Dashboard Template
 * 
 * Displays attribution analytics and charts
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get current filters
$selected_form = isset($_GET['form']) ? sanitize_text_field(wp_unslash($_GET['form'])) : 'all';
$start_date = isset($_GET['start']) ? sanitize_text_field(wp_unslash($_GET['start'])) : gmdate('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end']) ? sanitize_text_field(wp_unslash($_GET['end'])) : gmdate('Y-m-d');
$group_by = isset($_GET['group_by']) ? sanitize_text_field(wp_unslash($_GET['group_by'])) : 'channel';

// Get attribution data
$attribution_args = array(
    'start_date' => $start_date,
    'end_date' => $end_date,
    'group_by' => $group_by
);

if ($selected_form !== 'all') {
    $attribution_args['form_id'] = absint($selected_form);
}

$attribution_data = GR_AttributionCache::get_attribution_data($attribution_args);

// Get filter options
$filter_args = array(
    'start_date' => $start_date,
    'end_date' => $end_date
);

if ($selected_form !== 'all') {
    $filter_args['form_id'] = absint($selected_form);
}

$available_channels = GR_AttributionCache::get_unique_values('channel', $filter_args);
$available_sources = GR_AttributionCache::get_unique_values('source', $filter_args);
$available_campaigns = GR_AttributionCache::get_unique_values('campaign', $filter_args);

// Calculate totals and metrics
$total_entries = array_sum(array_column($attribution_data, 'entries'));
$total_revenue = array_sum(array_column($attribution_data, 'total_revenue'));

// Get channel costs for ROI calculations
$channel_costs = array();
foreach ($available_channels as $channel_info) {
    $cost_data = GR_AttributionCache::get_channel_costs($channel_info['value']);
    if ($cost_data) {
        $channel_costs[$channel_info['value']] = $cost_data;
    }
}
?>

<div class="gr-attribution-dashboard">
    <!-- Attribution Filters -->
    <div class="attribution-filters">
        <div class="filter-row">
            <div class="filter-group">
                <label for="attribution-form-select"><?php esc_html_e('Form:', 'gf-quickreports'); ?></label>
                <select id="attribution-form-select" name="form">
                    <option value="all" <?php selected($selected_form, 'all'); ?>><?php esc_html_e('All Forms', 'gf-quickreports'); ?></option>
                    <?php
                    $forms = GFAPI::get_forms();
                    foreach ($forms as $form) {
                        echo '<option value="' . esc_attr($form['id']) . '" ' . selected($selected_form, $form['id'], false) . '>';
                        echo esc_html($form['title']);
                        echo '</option>';
                    }
                    ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="attribution-group-by"><?php esc_html_e('Group By:', 'gf-quickreports'); ?></label>
                <select id="attribution-group-by" name="group_by">
                    <option value="channel" <?php selected($group_by, 'channel'); ?>><?php esc_html_e('Channel', 'gf-quickreports'); ?></option>
                    <option value="source" <?php selected($group_by, 'source'); ?>><?php esc_html_e('Source', 'gf-quickreports'); ?></option>
                    <option value="campaign" <?php selected($group_by, 'campaign'); ?>><?php esc_html_e('Campaign', 'gf-quickreports'); ?></option>
                    <option value="landing_page_group" <?php selected($group_by, 'landing_page_group'); ?>><?php esc_html_e('Landing Page Group', 'gf-quickreports'); ?></option>
                    <option value="date" <?php selected($group_by, 'date'); ?>><?php esc_html_e('Date', 'gf-quickreports'); ?></option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="attribution-start-date"><?php esc_html_e('Start Date:', 'gf-quickreports'); ?></label>
                <input type="date" id="attribution-start-date" name="start_date" value="<?php echo esc_attr($start_date); ?>">
            </div>
            
            <div class="filter-group">
                <label for="attribution-end-date"><?php esc_html_e('End Date:', 'gf-quickreports'); ?></label>
                <input type="date" id="attribution-end-date" name="end_date" value="<?php echo esc_attr($end_date); ?>">
            </div>
            
            <div class="filter-group">
                <button type="button" id="update-attribution-dashboard" class="button button-primary">
                    <?php esc_html_e('Update Dashboard', 'gf-quickreports'); ?>
                </button>
            </div>
        </div>
        
        <!-- Advanced Filters -->
        <div class="advanced-filters" style="display: none;">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="attribution-channel-filter"><?php esc_html_e('Channel:', 'gf-quickreports'); ?></label>
                    <select id="attribution-channel-filter" name="channel_filter">
                        <option value=""><?php esc_html_e('All Channels', 'gf-quickreports'); ?></option>
                        <?php foreach ($available_channels as $channel): ?>
                            <option value="<?php echo esc_attr($channel['value']); ?>">
                                <?php echo esc_html($channel['value']) . ' (' . esc_html($channel['count']) . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="attribution-source-filter"><?php esc_html_e('Source:', 'gf-quickreports'); ?></label>
                    <select id="attribution-source-filter" name="source_filter">
                        <option value=""><?php esc_html_e('All Sources', 'gf-quickreports'); ?></option>
                        <?php foreach ($available_sources as $source): ?>
                            <option value="<?php echo esc_attr($source['value']); ?>">
                                <?php echo esc_html($source['value']) . ' (' . esc_html($source['count']) . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="attribution-campaign-filter"><?php esc_html_e('Campaign:', 'gf-quickreports'); ?></label>
                    <select id="attribution-campaign-filter" name="campaign_filter">
                        <option value=""><?php esc_html_e('All Campaigns', 'gf-quickreports'); ?></option>
                        <?php foreach ($available_campaigns as $campaign): ?>
                            <option value="<?php echo esc_attr($campaign['value']); ?>">
                                <?php echo esc_html($campaign['value']) . ' (' . esc_html($campaign['count']) . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        
        <div class="filter-toggle">
            <button type="button" id="toggle-advanced-filters" class="button">
                <?php esc_html_e('Advanced Filters', 'gf-quickreports'); ?>
            </button>
        </div>
    </div>

    <!-- Attribution Summary Cards -->
    <div class="attribution-summary">
        <div class="summary-cards">
            <div class="summary-card">
                <div class="card-icon">📊</div>
                <div class="card-content">
                    <div class="card-title"><?php esc_html_e('Total Entries', 'gf-quickreports'); ?></div>
                    <div class="card-value"><?php echo esc_html(number_format($total_entries)); ?></div>
                </div>
            </div>
            
            <div class="summary-card">
                <div class="card-icon">💰</div>
                <div class="card-content">
                    <div class="card-title"><?php esc_html_e('Total Revenue', 'gf-quickreports'); ?></div>
                    <div class="card-value">$<?php echo esc_html(number_format($total_revenue, 2)); ?></div>
                </div>
            </div>
            
            <div class="summary-card">
                <div class="card-icon">📈</div>
                <div class="card-content">
                    <div class="card-title"><?php esc_html_e('Avg Revenue/Entry', 'gf-quickreports'); ?></div>
                    <div class="card-value">$<?php echo esc_html($total_entries > 0 ? number_format($total_revenue / $total_entries, 2) : '0.00'); ?></div>
                </div>
            </div>
            
            <div class="summary-card">
                <div class="card-icon">🎯</div>
                <div class="card-content">
                    <div class="card-title"><?php esc_html_e('Active Channels', 'gf-quickreports'); ?></div>
                    <div class="card-value"><?php echo esc_html(count($available_channels)); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Attribution Charts -->
    <div class="attribution-charts">
        <div class="chart-row">
            <!-- Channel Performance Chart -->
            <div class="chart-container">
                <div class="chart-header">
                    <h3><?php esc_html_e('Entries by', 'gf-quickreports'); ?> <?php echo esc_html(ucfirst($group_by)); ?></h3>
                    <div class="chart-controls">
                        <button type="button" class="chart-type-btn active" data-type="pie">
                            <?php esc_html_e('Pie', 'gf-quickreports'); ?>
                        </button>
                        <button type="button" class="chart-type-btn" data-type="bar">
                            <?php esc_html_e('Bar', 'gf-quickreports'); ?>
                        </button>
                    </div>
                </div>
                <canvas id="attribution-entries-chart" width="400" height="300"></canvas>
            </div>
            
            <!-- Revenue Attribution Chart -->
            <div class="chart-container">
                <div class="chart-header">
                    <h3><?php esc_html_e('Revenue by', 'gf-quickreports'); ?> <?php echo esc_html(ucfirst($group_by)); ?></h3>
                    <div class="chart-controls">
                        <button type="button" class="chart-type-btn active" data-type="pie">
                            <?php esc_html_e('Pie', 'gf-quickreports'); ?>
                        </button>
                        <button type="button" class="chart-type-btn" data-type="bar">
                            <?php esc_html_e('Bar', 'gf-quickreports'); ?>
                        </button>
                    </div>
                </div>
                <canvas id="attribution-revenue-chart" width="400" height="300"></canvas>
            </div>
        </div>
        
        <?php if ($group_by === 'date'): ?>
        <!-- Time Series Chart for Date Grouping -->
        <div class="chart-row">
            <div class="chart-container full-width">
                <div class="chart-header">
                    <h3><?php esc_html_e('Attribution Trends Over Time', 'gf-quickreports'); ?></h3>
                </div>
                <canvas id="attribution-trends-chart" width="800" height="300"></canvas>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Attribution Data Table -->
    <div class="attribution-table-container">
        <div class="table-header">
            <h3><?php esc_html_e('Attribution Performance Details', 'gf-quickreports'); ?></h3>
            <div class="table-controls">
                <button type="button" id="export-attribution-csv" class="button">
                    <?php esc_html_e('Export CSV', 'gf-quickreports'); ?>
                </button>
                <button type="button" id="export-attribution-pdf" class="button">
                    <?php esc_html_e('Export PDF', 'gf-quickreports'); ?>
                </button>
            </div>
        </div>
        
        <div class="attribution-table-wrapper">
            <table id="attribution-data-table" class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html(ucfirst($group_by)); ?></th>
                        <th><?php esc_html_e('Entries', 'gf-quickreports'); ?></th>
                        <th><?php esc_html_e('Revenue', 'gf-quickreports'); ?></th>
                        <th><?php esc_html_e('Avg Revenue/Entry', 'gf-quickreports'); ?></th>
                        <th><?php esc_html_e('Cost', 'gf-quickreports'); ?></th>
                        <th><?php esc_html_e('ROI', 'gf-quickreports'); ?></th>
                        <th><?php esc_html_e('Conversion %', 'gf-quickreports'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($attribution_data)): ?>
                        <tr>
                            <td colspan="7" class="no-data">
                                <?php esc_html_e('No attribution data found for the selected criteria.', 'gf-quickreports'); ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($attribution_data as $row): ?>
                            <?php
                            $group_name = $group_by === 'date' ? $row['date_group'] : $row['group_name'];
                            $entries = intval($row['entries']);
                            $revenue = floatval($row['total_revenue']);
                            $avg_revenue = $entries > 0 ? $revenue / $entries : 0;
                            
                            // Calculate cost and ROI
                            $cost = 0;
                            $roi = 0;
                            if ($group_by === 'channel' && isset($channel_costs[$group_name])) {
                                $cost = $channel_costs[$group_name]['cost_per_acquisition'] * $entries;
                                $roi = $cost > 0 ? (($revenue - $cost) / $cost) * 100 : 0;
                            }
                            
                            // Calculate conversion rate (placeholder - would need traffic data)
                            $conversion_rate = 0; // This would be calculated if we had traffic data
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html($group_name ?: __('(not set)', 'gf-quickreports')); ?></strong></td>
                                <td><?php echo esc_html(number_format($entries)); ?></td>
                                <td>$<?php echo esc_html(number_format($revenue, 2)); ?></td>
                                <td>$<?php echo esc_html(number_format($avg_revenue, 2)); ?></td>
                                <td>$<?php echo esc_html(number_format($cost, 2)); ?></td>
                                <td>
                                    <?php if ($roi > 0): ?>
                                        <span class="roi-positive">+<?php echo esc_html(number_format($roi, 1)); ?>%</span>
                                    <?php elseif ($roi < 0): ?>
                                        <span class="roi-negative"><?php echo esc_html(number_format($roi, 1)); ?>%</span>
                                    <?php else: ?>
                                        <span class="roi-neutral">0%</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($conversion_rate > 0): ?>
                                        <?php echo esc_html(number_format($conversion_rate, 2)); ?>%
                                    <?php else: ?>
                                        <span class="conversion-unknown">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Prepare data for JavaScript -->
<script type="text/javascript">
window.grAttributionData = {
    chartData: <?php echo wp_json_encode($attribution_data); ?>,
    groupBy: <?php echo wp_json_encode($group_by); ?>,
    channelCosts: <?php echo wp_json_encode($channel_costs); ?>,
    filters: {
        formId: <?php echo wp_json_encode($selected_form); ?>,
        startDate: <?php echo wp_json_encode($start_date); ?>,
        endDate: <?php echo wp_json_encode($end_date); ?>
    }
};
</script>

<style>
.gr-attribution-dashboard {
    margin: 20px 0;
}

.attribution-filters {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    margin-bottom: 20px;
    border-radius: 4px;
}

.filter-row {
    display: flex;
    gap: 15px;
    align-items: end;
    flex-wrap: wrap;
    margin-bottom: 15px;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.filter-group label {
    font-weight: 600;
    font-size: 13px;
    color: #23282d;
}

.filter-group select,
.filter-group input[type="date"] {
    min-width: 140px;
    height: 32px;
}

.attribution-summary {
    margin-bottom: 30px;
}

.summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.summary-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.card-icon {
    font-size: 24px;
    opacity: 0.7;
}

.card-title {
    font-size: 13px;
    color: #646970;
    margin-bottom: 5px;
}

.card-value {
    font-size: 24px;
    font-weight: 600;
    color: #1d2327;
}

.attribution-charts {
    margin-bottom: 30px;
}

.chart-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.chart-row .full-width {
    grid-column: 1 / -1;
}

.chart-container {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
}

.chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.chart-header h3 {
    margin: 0;
    font-size: 16px;
}

.chart-controls {
    display: flex;
    gap: 5px;
}

.chart-type-btn {
    background: #f0f0f1;
    border: 1px solid #c3c4c7;
    padding: 5px 10px;
    border-radius: 3px;
    cursor: pointer;
    font-size: 12px;
}

.chart-type-btn.active {
    background: #2271b1;
    color: #fff;
}

.attribution-table-container {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #ccd0d4;
}

.table-header h3 {
    margin: 0;
}

.table-controls {
    display: flex;
    gap: 10px;
}

.attribution-table-wrapper {
    overflow-x: auto;
}

#attribution-data-table {
    margin: 0;
}

#attribution-data-table th,
#attribution-data-table td {
    padding: 12px 15px;
}

.no-data {
    text-align: center;
    color: #646970;
    font-style: italic;
}

.roi-positive {
    color: #00a32a;
    font-weight: 600;
}

.roi-negative {
    color: #d63638;
    font-weight: 600;
}

.roi-neutral {
    color: #646970;
}

.conversion-unknown {
    color: #646970;
    font-style: italic;
}

.filter-toggle {
    margin-top: 10px;
}

.advanced-filters {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #ddd;
}

@media (max-width: 768px) {
    .chart-row {
        grid-template-columns: 1fr;
    }
    
    .filter-row {
        flex-direction: column;
        align-items: stretch;
    }
    
    .summary-cards {
        grid-template-columns: 1fr;
    }
}
</style>
