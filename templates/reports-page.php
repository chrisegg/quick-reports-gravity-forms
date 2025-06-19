<?php
/**
 * Quick Reports for Gravity Forms - Reports Page Template
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Quick Reports for Gravity Forms', 'gf-quickreports'); ?></h1>
    
    <div class="gf-quickreports-container">
        <!-- Filter Form -->
        <form method="get" class="gf-quickreports-form">
            <input type="hidden" name="page" value="gf_quickreports">
            
            <div class="filter-row">
                <label for="form_id"><?php _e('Form:', 'gf-quickreports'); ?></label>
                <select name="form_id" id="form_id">
                    <option value="0"><?php _e('All Forms', 'gf-quickreports'); ?></option>
                    <?php foreach ($forms as $form): ?>
                        <option value="<?php echo esc_attr($form['id']); ?>" <?php selected($form_id, $form['id']); ?>>
                            <?php echo esc_html($form['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <label for="start_date"><?php _e('Start Date:', 'gf-quickreports'); ?></label>
                <input type="date" name="start_date" id="start_date" value="<?php echo esc_attr($start_date); ?>">
                
                <label for="end_date"><?php _e('End Date:', 'gf-quickreports'); ?></label>
                <input type="date" name="end_date" id="end_date" value="<?php echo esc_attr($end_date); ?>">
                
                <label for="chart_mode"><?php _e('Chart Mode:', 'gf-quickreports'); ?></label>
                <select name="chart_mode" id="chart_mode">
                    <option value="per_day" <?php selected($chart_mode, 'per_day'); ?>><?php _e('Per Day', 'gf-quickreports'); ?></option>
                    <option value="total" <?php selected($chart_mode, 'total'); ?>><?php _e('Total', 'gf-quickreports'); ?></option>
                </select>
                
                <?php if ($form_id === 0): ?>
                    <label for="chart_view"><?php _e('Chart View:', 'gf-quickreports'); ?></label>
                    <select name="chart_view" id="chart_view">
                        <option value="combined" <?php selected($chart_view, 'combined'); ?>><?php _e('Combined', 'gf-quickreports'); ?></option>
                        <option value="individual" <?php selected($chart_view, 'individual'); ?>><?php _e('Individual Forms', 'gf-quickreports'); ?></option>
                    </select>
                <?php endif; ?>
                
                <button type="submit" class="button button-primary"><?php _e('Generate Report', 'gf-quickreports'); ?></button>
            </div>
            
            <?php if ($form_id > 0): ?>
                <div class="filter-row">
                    <label for="compare_form_id"><?php _e('Compare with:', 'gf-quickreports'); ?></label>
                    <select name="compare_form_id" id="compare_form_id">
                        <option value="0"><?php _e('No comparison', 'gf-quickreports'); ?></option>
                        <?php foreach ($forms as $form): ?>
                            <?php if ($form['id'] != $form_id): ?>
                                <option value="<?php echo esc_attr($form['id']); ?>" <?php selected($compare_form_id, $form['id']); ?>>
                                    <?php echo esc_html($form['title']); ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
        </form>
        
        <?php if ($form_id > 0 || !empty($chart_data['data'])): ?>
            <div class="gf-quickreports-results">
                <!-- Export Buttons -->
                <div class="export-buttons">
                    <button type="button" id="export-csv" class="button">
                        <svg viewBox="0 0 24 24">
                            <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                        </svg>
                        <?php _e('Export CSV', 'gf-quickreports'); ?>
                    </button>
                    <button type="button" id="export-pdf" class="button">
                        <svg viewBox="0 0 24 24">
                            <path d="M20,2H8A2,2 0 0,0 6,4V16A2,2 0 0,0 8,18H20A2,2 0 0,0 22,16V4A2,2 0 0,0 20,2M20,16H8V4H20V16M16,20V22H4A2,2 0 0,1 2,20V7H4V20H16Z"/>
                        </svg>
                        <?php _e('Export PDF', 'gf-quickreports'); ?>
                    </button>
                </div>
                
                <!-- Chart Container -->
                <div class="chart-container">
                    <canvas id="entriesChart"></canvas>
                    <div id="chartjs-no-data"><?php _e('No data available for the selected criteria.', 'gf-quickreports'); ?></div>
                </div>
                
                <!-- Recent Entries -->
                <?php if (!empty($recent_entries)): ?>
                    <div class="recent-entries">
                        <h3><?php _e('Recent Entries', 'gf-quickreports'); ?></h3>
                        <table>
                            <thead>
                                <tr>
                                    <th><?php _e('Entry ID', 'gf-quickreports'); ?></th>
                                    <th><?php _e('Date', 'gf-quickreports'); ?></th>
                                    <th><?php _e('IP', 'gf-quickreports'); ?></th>
                                    <?php if ($form_id > 0): ?>
                                        <?php 
                                        $form = GFAPI::get_form($form_id);
                                        if ($form) {
                                            foreach ($form['fields'] as $field) {
                                                if (!$field['adminOnly']) {
                                                    echo '<th>' . esc_html($field['label']) . '</th>';
                                                }
                                            }
                                        }
                                        ?>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_entries as $entry): ?>
                                    <tr>
                                        <td><?php echo esc_html($entry['id']); ?></td>
                                        <td><?php echo esc_html($entry['date_created']); ?></td>
                                        <td><?php echo esc_html($entry['ip']); ?></td>
                                        <?php if ($form_id > 0 && isset($form)): ?>
                                            <?php foreach ($form['fields'] as $field): ?>
                                                <?php if (!$field['adminOnly']): ?>
                                                    <td><?php echo esc_html(rgar($entry, $field['id'])); ?></td>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Pass data to JavaScript
window.chartData = <?php echo json_encode($chart_data); ?>;
window.compareChartData = <?php echo json_encode($compare_chart_data); ?>;
window.chartMode = <?php echo json_encode($chart_mode); ?>;
window.chartView = <?php echo json_encode($chart_view); ?>;
<?php if ($form_id > 0): ?>
window.selectedFormLabel = <?php echo json_encode(GFAPI::get_form($form_id)['title']); ?>;
<?php endif; ?>
<?php if (!empty($individual_forms_data)): ?>
window.individualFormsData = <?php echo json_encode($individual_forms_data); ?>;
<?php endif; ?>
</script> 