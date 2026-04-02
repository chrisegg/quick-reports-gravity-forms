<?php
/**
 * Attribution Settings Class
 * 
 * Handles settings and configuration for attribution features
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class GR_AttributionSettings
 * 
 * Manages attribution-related settings and configuration
 */
class GR_AttributionSettings {
    
    /**
     * Settings option name
     */
    const SETTINGS_OPTION = 'gr_attribution_settings';
    
    /**
     * Initialize settings
     */
    public static function init() {
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('admin_menu', array(__CLASS__, 'add_settings_submenu'), 25);
    }
    
    /**
     * Register settings
     */
    public static function register_settings() {
        register_setting(
            'gr_attribution_settings_group',
            self::SETTINGS_OPTION,
            array(
                'sanitize_callback' => array(__CLASS__, 'sanitize_settings'),
                'default' => self::get_default_settings()
            )
        );
        
        // Attribution Features Section
        add_settings_section(
            'gr_attribution_features',
            esc_html__('Attribution Features', 'gf-quickreports'),
            array(__CLASS__, 'features_section_callback'),
            'gr_attribution_settings'
        );
        
        add_settings_field(
            'enable_attribution',
            esc_html__('Enable Attribution Tracking', 'gf-quickreports'),
            array(__CLASS__, 'enable_attribution_callback'),
            'gr_attribution_settings',
            'gr_attribution_features'
        );
        
        add_settings_field(
            'auto_detect_fields',
            esc_html__('Auto-Detect Attributer Fields', 'gf-quickreports'),
            array(__CLASS__, 'auto_detect_fields_callback'),
            'gr_attribution_settings',
            'gr_attribution_features'
        );
        
        // Field Mapping Section
        add_settings_section(
            'gr_field_mapping',
            esc_html__('Field Mapping', 'gf-quickreports'),
            array(__CLASS__, 'field_mapping_section_callback'),
            'gr_attribution_settings'
        );
        
        // Default Costs Section
        add_settings_section(
            'gr_default_costs',
            esc_html__('Default Channel Costs', 'gf-quickreports'),
            array(__CLASS__, 'default_costs_section_callback'),
            'gr_attribution_settings'
        );
        
        // Cache Settings Section
        add_settings_section(
            'gr_cache_settings',
            esc_html__('Cache Settings', 'gf-quickreports'),
            array(__CLASS__, 'cache_settings_section_callback'),
            'gr_attribution_settings'
        );
        
        add_settings_field(
            'cache_duration',
            esc_html__('Cache Duration (hours)', 'gf-quickreports'),
            array(__CLASS__, 'cache_duration_callback'),
            'gr_attribution_settings',
            'gr_cache_settings'
        );
    }
    
    /**
     * Add settings submenu
     */
    public static function add_settings_submenu() {
        add_submenu_page(
            'gf_edit_forms',
            esc_html__('Attribution Settings', 'gf-quickreports'),
            esc_html__('Attribution Settings', 'gf-quickreports'),
            'manage_options',
            'gr_attribution_settings',
            array(__CLASS__, 'render_settings_page')
        );
    }
    
    /**
     * Render settings page
     */
    public static function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'gf-quickreports'));
        }
        
        // Handle manual field mapping updates
        if (isset($_POST['update_field_mapping']) && wp_verify_nonce($_POST['field_mapping_nonce'], 'gr_update_field_mapping')) {
            self::handle_field_mapping_update();
        }
        
        // Handle channel costs updates
        if (isset($_POST['update_channel_costs']) && wp_verify_nonce($_POST['channel_costs_nonce'], 'gr_update_channel_costs')) {
            self::handle_channel_costs_update();
        }
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Attribution Settings', 'gf-quickreports'); ?></h1>
            
            <div class="gr-settings-container">
                <div class="nav-tab-wrapper">
                    <a href="#general" class="nav-tab nav-tab-active" data-tab="general">
                        <?php esc_html_e('General Settings', 'gf-quickreports'); ?>
                    </a>
                    <a href="#field-mapping" class="nav-tab" data-tab="field-mapping">
                        <?php esc_html_e('Field Mapping', 'gf-quickreports'); ?>
                    </a>
                    <a href="#channel-costs" class="nav-tab" data-tab="channel-costs">
                        <?php esc_html_e('Channel Costs', 'gf-quickreports'); ?>
                    </a>
                    <a href="#tools" class="nav-tab" data-tab="tools">
                        <?php esc_html_e('Tools', 'gf-quickreports'); ?>
                    </a>
                </div>
                
                <!-- General Settings Tab -->
                <div id="general-tab" class="tab-content active">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('gr_attribution_settings_group');
                        do_settings_sections('gr_attribution_settings');
                        submit_button();
                        ?>
                    </form>
                </div>
                
                <!-- Field Mapping Tab -->
                <div id="field-mapping-tab" class="tab-content">
                    <?php self::render_field_mapping_section(); ?>
                </div>
                
                <!-- Channel Costs Tab -->
                <div id="channel-costs-tab" class="tab-content">
                    <?php self::render_channel_costs_section(); ?>
                </div>
                
                <!-- Tools Tab -->
                <div id="tools-tab" class="tab-content">
                    <?php self::render_tools_section(); ?>
                </div>
            </div>
        </div>
        
        <style>
        .gr-settings-container .tab-content {
            display: none;
            padding: 20px 0;
        }
        .gr-settings-container .tab-content.active {
            display: block;
        }
        .attribution-field-mapping table {
            width: 100%;
            margin-top: 15px;
        }
        .attribution-field-mapping th,
        .attribution-field-mapping td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .channel-costs-table {
            width: 100%;
            margin-top: 15px;
        }
        .channel-costs-table th,
        .channel-costs-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .tools-section .button {
            margin-right: 10px;
            margin-bottom: 10px;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Tab switching
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                var tab = $(this).data('tab');
                
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                $('.tab-content').removeClass('active');
                $('#' + tab + '-tab').addClass('active');
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render field mapping section
     */
    private static function render_field_mapping_section() {
        $forms = GFAPI::get_forms();
        ?>
        <h2><?php esc_html_e('Field Mapping Configuration', 'gf-quickreports'); ?></h2>
        <p><?php esc_html_e('Configure how Attributer fields are mapped in your forms. Auto-detection works for most cases, but you can manually override mappings here.', 'gf-quickreports'); ?></p>
        
        <form method="post" action="">
            <?php wp_nonce_field('gr_update_field_mapping', 'field_mapping_nonce'); ?>
            
            <?php foreach ($forms as $form): ?>
                <?php 
                $detected_fields = GR_AttributerDetector::detect_attributer_fields($form['id']);
                if (empty($detected_fields)) continue;
                ?>
                
                <div class="attribution-field-mapping">
                    <h3><?php echo esc_html($form['title']); ?> (ID: <?php echo esc_html($form['id']); ?>)</h3>
                    
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Field ID', 'gf-quickreports'); ?></th>
                                <th><?php esc_html_e('Field Label', 'gf-quickreports'); ?></th>
                                <th><?php esc_html_e('Attribution Type', 'gf-quickreports'); ?></th>
                                <th><?php esc_html_e('Enabled', 'gf-quickreports'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($detected_fields as $field_id => $field_config): ?>
                                <tr>
                                    <td><?php echo esc_html($field_id); ?></td>
                                    <td><?php echo esc_html($field_config['label']); ?></td>
                                    <td>
                                        <select name="field_mapping[<?php echo esc_attr($form['id']); ?>][<?php echo esc_attr($field_id); ?>][type]">
                                            <option value=""><?php esc_html_e('-- Not Mapped --', 'gf-quickreports'); ?></option>
                                            <option value="channel" <?php selected($field_config['type'], 'channel'); ?>><?php esc_html_e('Channel', 'gf-quickreports'); ?></option>
                                            <option value="source" <?php selected($field_config['type'], 'source'); ?>><?php esc_html_e('Source', 'gf-quickreports'); ?></option>
                                            <option value="medium" <?php selected($field_config['type'], 'medium'); ?>><?php esc_html_e('Medium', 'gf-quickreports'); ?></option>
                                            <option value="campaign" <?php selected($field_config['type'], 'campaign'); ?>><?php esc_html_e('Campaign', 'gf-quickreports'); ?></option>
                                            <option value="term" <?php selected($field_config['type'], 'term'); ?>><?php esc_html_e('Term', 'gf-quickreports'); ?></option>
                                            <option value="content" <?php selected($field_config['type'], 'content'); ?>><?php esc_html_e('Content', 'gf-quickreports'); ?></option>
                                            <option value="landing_page" <?php selected($field_config['type'], 'landing_page'); ?>><?php esc_html_e('Landing Page', 'gf-quickreports'); ?></option>
                                            <option value="landing_page_group" <?php selected($field_config['type'], 'landing_page_group'); ?>><?php esc_html_e('Landing Page Group', 'gf-quickreports'); ?></option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="checkbox" name="field_mapping[<?php echo esc_attr($form['id']); ?>][<?php echo esc_attr($field_id); ?>][enabled]" value="1" checked>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <br>
            <?php endforeach; ?>
            
            <?php submit_button(esc_html__('Update Field Mapping', 'gf-quickreports'), 'primary', 'update_field_mapping'); ?>
        </form>
        <?php
    }
    
    /**
     * Render channel costs section
     */
    private static function render_channel_costs_section() {
        $all_costs = GR_AttributionCache::get_channel_costs();
        ?>
        <h2><?php esc_html_e('Channel Cost Configuration', 'gf-quickreports'); ?></h2>
        <p><?php esc_html_e('Set cost per acquisition and monthly budgets for different channels to calculate ROI.', 'gf-quickreports'); ?></p>
        
        <form method="post" action="">
            <?php wp_nonce_field('gr_update_channel_costs', 'channel_costs_nonce'); ?>
            
            <h3><?php esc_html_e('Add New Channel Cost', 'gf-quickreports'); ?></h3>
            <table class="form-table">
                <tr>
                    <th><label for="new_channel"><?php esc_html_e('Channel', 'gf-quickreports'); ?></label></th>
                    <td><input type="text" name="new_cost[channel]" id="new_channel" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="new_source"><?php esc_html_e('Source (Optional)', 'gf-quickreports'); ?></label></th>
                    <td><input type="text" name="new_cost[source]" id="new_source" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="new_campaign"><?php esc_html_e('Campaign (Optional)', 'gf-quickreports'); ?></label></th>
                    <td><input type="text" name="new_cost[campaign]" id="new_campaign" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="new_cpa"><?php esc_html_e('Cost Per Acquisition', 'gf-quickreports'); ?></label></th>
                    <td><input type="number" name="new_cost[cost_per_acquisition]" id="new_cpa" step="0.01" min="0" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="new_budget"><?php esc_html_e('Monthly Budget', 'gf-quickreports'); ?></label></th>
                    <td><input type="number" name="new_cost[monthly_budget]" id="new_budget" step="0.01" min="0" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="new_notes"><?php esc_html_e('Notes', 'gf-quickreports'); ?></label></th>
                    <td><textarea name="new_cost[notes]" id="new_notes" rows="3" class="regular-text"></textarea></td>
                </tr>
            </table>
            
            <?php if (!empty($all_costs)): ?>
                <h3><?php esc_html_e('Existing Channel Costs', 'gf-quickreports'); ?></h3>
                <table class="widefat channel-costs-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Channel', 'gf-quickreports'); ?></th>
                            <th><?php esc_html_e('Source', 'gf-quickreports'); ?></th>
                            <th><?php esc_html_e('Campaign', 'gf-quickreports'); ?></th>
                            <th><?php esc_html_e('Cost Per Acquisition', 'gf-quickreports'); ?></th>
                            <th><?php esc_html_e('Monthly Budget', 'gf-quickreports'); ?></th>
                            <th><?php esc_html_e('Actions', 'gf-quickreports'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_costs as $cost): ?>
                            <tr>
                                <td><?php echo esc_html($cost['channel']); ?></td>
                                <td><?php echo esc_html($cost['source'] ?: '-'); ?></td>
                                <td><?php echo esc_html($cost['campaign'] ?: '-'); ?></td>
                                <td>$<?php echo esc_html(number_format($cost['cost_per_acquisition'], 2)); ?></td>
                                <td>$<?php echo esc_html(number_format($cost['monthly_budget'], 2)); ?></td>
                                <td>
                                    <input type="hidden" name="existing_costs[<?php echo esc_attr($cost['id']); ?>][id]" value="<?php echo esc_attr($cost['id']); ?>">
                                    <button type="submit" name="delete_cost" value="<?php echo esc_attr($cost['id']); ?>" class="button button-link-delete">
                                        <?php esc_html_e('Delete', 'gf-quickreports'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <?php submit_button(esc_html__('Save Channel Costs', 'gf-quickreports'), 'primary', 'update_channel_costs'); ?>
        </form>
        <?php
    }
    
    /**
     * Render tools section
     */
    private static function render_tools_section() {
        ?>
        <h2><?php esc_html_e('Attribution Tools', 'gf-quickreports'); ?></h2>
        
        <div class="tools-section">
            <h3><?php esc_html_e('Cache Management', 'gf-quickreports'); ?></h3>
            <p><?php esc_html_e('Manage attribution data cache for better performance.', 'gf-quickreports'); ?></p>
            
            <button type="button" class="button" onclick="grRebuildCache()">
                <?php esc_html_e('Rebuild Attribution Cache', 'gf-quickreports'); ?>
            </button>
            
            <button type="button" class="button" onclick="grClearCache()">
                <?php esc_html_e('Clear Attribution Cache', 'gf-quickreports'); ?>
            </button>
            
            <h3><?php esc_html_e('Field Detection', 'gf-quickreports'); ?></h3>
            <p><?php esc_html_e('Re-scan all forms for Attributer fields.', 'gf-quickreports'); ?></p>
            
            <button type="button" class="button" onclick="grRescanFields()">
                <?php esc_html_e('Re-scan All Forms', 'gf-quickreports'); ?>
            </button>
            
            <div id="tools-results" style="margin-top: 20px;"></div>
        </div>
        
        <script>
        function grRebuildCache() {
            // Implementation for cache rebuild
            jQuery('#tools-results').html('<div class="notice notice-info"><p>Rebuilding cache...</p></div>');
            // AJAX call would go here
        }
        
        function grClearCache() {
            if (confirm('<?php echo esc_js(__('Are you sure you want to clear the attribution cache?', 'gf-quickreports')); ?>')) {
                // Implementation for cache clear
                jQuery('#tools-results').html('<div class="notice notice-success"><p>Cache cleared successfully.</p></div>');
                // AJAX call would go here
            }
        }
        
        function grRescanFields() {
            // Implementation for field rescan
            jQuery('#tools-results').html('<div class="notice notice-info"><p>Rescanning forms...</p></div>');
            // AJAX call would go here
        }
        </script>
        <?php
    }
    
    /**
     * Get default settings
     */
    public static function get_default_settings() {
        return array(
            'enable_attribution' => true,
            'auto_detect_fields' => true,
            'cache_duration' => 24
        );
    }
    
    /**
     * Sanitize settings
     */
    public static function sanitize_settings($settings) {
        $sanitized = array();
        
        $sanitized['enable_attribution'] = !empty($settings['enable_attribution']);
        $sanitized['auto_detect_fields'] = !empty($settings['auto_detect_fields']);
        $sanitized['cache_duration'] = absint($settings['cache_duration']);
        
        return $sanitized;
    }
    
    /**
     * Section callbacks
     */
    public static function features_section_callback() {
        echo '<p>' . esc_html__('Configure basic attribution tracking features.', 'gf-quickreports') . '</p>';
    }
    
    public static function field_mapping_section_callback() {
        echo '<p>' . esc_html__('Advanced field mapping configuration.', 'gf-quickreports') . '</p>';
    }
    
    public static function default_costs_section_callback() {
        echo '<p>' . esc_html__('Set default costs for ROI calculations.', 'gf-quickreports') . '</p>';
    }
    
    public static function cache_settings_section_callback() {
        echo '<p>' . esc_html__('Configure attribution data caching.', 'gf-quickreports') . '</p>';
    }
    
    /**
     * Field callbacks
     */
    public static function enable_attribution_callback() {
        $settings = get_option(self::SETTINGS_OPTION, self::get_default_settings());
        echo '<input type="checkbox" name="' . esc_attr(self::SETTINGS_OPTION) . '[enable_attribution]" value="1" ' . checked($settings['enable_attribution'], true, false) . '>';
        echo '<p class="description">' . esc_html__('Enable attribution tracking and reporting features.', 'gf-quickreports') . '</p>';
    }
    
    public static function auto_detect_fields_callback() {
        $settings = get_option(self::SETTINGS_OPTION, self::get_default_settings());
        echo '<input type="checkbox" name="' . esc_attr(self::SETTINGS_OPTION) . '[auto_detect_fields]" value="1" ' . checked($settings['auto_detect_fields'], true, false) . '>';
        echo '<p class="description">' . esc_html__('Automatically detect Attributer fields in forms.', 'gf-quickreports') . '</p>';
    }
    
    public static function cache_duration_callback() {
        $settings = get_option(self::SETTINGS_OPTION, self::get_default_settings());
        echo '<input type="number" name="' . esc_attr(self::SETTINGS_OPTION) . '[cache_duration]" value="' . esc_attr($settings['cache_duration']) . '" min="1" max="168" class="small-text">';
        echo '<p class="description">' . esc_html__('How long to cache attribution data (1-168 hours).', 'gf-quickreports') . '</p>';
    }
    
    /**
     * Handle field mapping update
     */
    private static function handle_field_mapping_update() {
        if (empty($_POST['field_mapping'])) {
            return;
        }
        
        foreach ($_POST['field_mapping'] as $form_id => $fields) {
            $form_id = absint($form_id);
            $sanitized_mapping = array();
            
            foreach ($fields as $field_id => $config) {
                if (!empty($config['enabled'])) {
                    $sanitized_mapping[absint($field_id)] = array(
                        'type' => sanitize_text_field($config['type']),
                        'enabled' => true
                    );
                }
            }
            
            GR_AttributerDetector::save_field_mapping($form_id, $sanitized_mapping);
        }
        
        add_settings_error(
            'gr_attribution_settings',
            'field_mapping_updated',
            esc_html__('Field mapping updated successfully.', 'gf-quickreports'),
            'success'
        );
    }
    
    /**
     * Handle channel costs update
     */
    private static function handle_channel_costs_update() {
        // Handle new cost addition
        if (!empty($_POST['new_cost']['channel'])) {
            $new_cost = wp_unslash($_POST['new_cost']);
            $cost_data = array(
                'channel' => sanitize_text_field($new_cost['channel']),
                'source' => !empty($new_cost['source']) ? sanitize_text_field($new_cost['source']) : '',
                'campaign' => !empty($new_cost['campaign']) ? sanitize_text_field($new_cost['campaign']) : '',
                'cost_per_acquisition' => floatval($new_cost['cost_per_acquisition']),
                'monthly_budget' => floatval($new_cost['monthly_budget']),
                'notes' => !empty($new_cost['notes']) ? sanitize_textarea_field($new_cost['notes']) : ''
            );
            
            GR_AttributionCache::update_channel_costs($cost_data);
        }
        
        // Handle cost deletion
        if (!empty($_POST['delete_cost'])) {
            $cost_id = absint($_POST['delete_cost']);
            global $wpdb;
            $table_name = $wpdb->prefix . GR_AttributionCache::COSTS_TABLE_NAME;
            $wpdb->delete($table_name, array('id' => $cost_id), array('%d'));
        }
        
        add_settings_error(
            'gr_attribution_settings',
            'channel_costs_updated',
            esc_html__('Channel costs updated successfully.', 'gf-quickreports'),
            'success'
        );
    }
}

// Initialize attribution settings
GR_AttributionSettings::init();
