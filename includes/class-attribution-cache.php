<?php
/**
 * Attribution Cache Class
 * 
 * Handles caching and storage of attribution data
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class GR_AttributionCache
 * 
 * Manages attribution data caching and database operations
 */
class GR_AttributionCache {
    
    /**
     * Table name for attribution cache
     */
    const TABLE_NAME = 'gr_attribution_cache';
    
    /**
     * Table name for channel costs
     */
    const COSTS_TABLE_NAME = 'gr_channel_costs';
    
    /**
     * Initialize the cache system
     */
    public static function init() {
        add_action('plugins_loaded', array(__CLASS__, 'maybe_create_tables'));
        add_action('gform_after_submission', array(__CLASS__, 'cache_entry_attribution'), 10, 2);
    }
    
    /**
     * Create database tables if they don't exist
     */
    public static function maybe_create_tables() {
        global $wpdb;
        
        $attribution_table = $wpdb->prefix . self::TABLE_NAME;
        $costs_table = $wpdb->prefix . self::COSTS_TABLE_NAME;
        
        // Check if tables exist
        $attribution_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $attribution_table));
        $costs_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $costs_table));
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Create attribution cache table
        if (!$attribution_exists) {
            $attribution_sql = "CREATE TABLE $attribution_table (
                id int(11) NOT NULL AUTO_INCREMENT,
                form_id int(11) NOT NULL,
                entry_id int(11) NOT NULL,
                channel varchar(100) DEFAULT NULL,
                source varchar(100) DEFAULT NULL,
                medium varchar(100) DEFAULT NULL,
                campaign varchar(200) DEFAULT NULL,
                term varchar(200) DEFAULT NULL,
                content varchar(200) DEFAULT NULL,
                landing_page text DEFAULT NULL,
                landing_page_group varchar(100) DEFAULT NULL,
                revenue decimal(10,2) DEFAULT 0.00,
                date_created datetime NOT NULL,
                date_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY entry_unique (entry_id),
                KEY form_channel (form_id, channel),
                KEY date_range (date_created),
                KEY channel_index (channel),
                KEY source_index (source),
                KEY campaign_index (campaign)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            
            dbDelta($attribution_sql);
        }
        
        // Create channel costs table
        if (!$costs_exists) {
            $costs_sql = "CREATE TABLE $costs_table (
                id int(11) NOT NULL AUTO_INCREMENT,
                channel varchar(100) NOT NULL,
                source varchar(100) DEFAULT NULL,
                campaign varchar(200) DEFAULT NULL,
                cost_per_acquisition decimal(10,2) DEFAULT 0.00,
                monthly_budget decimal(10,2) DEFAULT 0.00,
                notes text DEFAULT NULL,
                date_created datetime DEFAULT CURRENT_TIMESTAMP,
                date_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY channel_source_campaign (channel, source, campaign),
                KEY channel_index (channel)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            
            dbDelta($costs_sql);
        }
    }
    
    /**
     * Cache attribution data for a form entry
     * 
     * @param array $entry Gravity Forms entry
     * @param array $form Gravity Forms form
     */
    public static function cache_entry_attribution($entry, $form) {
        if (!class_exists('GR_AttributerDetector')) {
            return;
        }
        
        // Extract attribution data
        $attribution_data = GR_AttributerDetector::extract_attribution_data($entry, $form['id']);
        
        // Skip if no attribution data found
        if (empty($attribution_data) || empty(array_filter($attribution_data))) {
            return;
        }
        
        // Validate data
        $validated_data = GR_AttributerDetector::validate_attribution_data($attribution_data);
        
        // Calculate revenue from entry if available
        $revenue = self::calculate_entry_revenue($entry, $form);
        $validated_data['revenue'] = $revenue;
        
        // Store in cache
        self::store_attribution_data($validated_data);
    }
    
    /**
     * Store attribution data in the cache table
     * 
     * @param array $attribution_data Validated attribution data
     * @return bool|int Insert ID on success, false on failure
     */
    public static function store_attribution_data($attribution_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        
        $data = array(
            'form_id' => absint($attribution_data['form_id']),
            'entry_id' => absint($attribution_data['entry_id']),
            'channel' => sanitize_text_field($attribution_data['channel']),
            'source' => sanitize_text_field($attribution_data['source']),
            'medium' => sanitize_text_field($attribution_data['medium']),
            'campaign' => sanitize_text_field($attribution_data['campaign']),
            'term' => sanitize_text_field($attribution_data['term']),
            'content' => sanitize_text_field($attribution_data['content']),
            'landing_page' => esc_url_raw($attribution_data['landing_page']),
            'landing_page_group' => sanitize_text_field($attribution_data['landing_page_group']),
            'revenue' => floatval($attribution_data['revenue']),
            'date_created' => sanitize_text_field($attribution_data['date_created'])
        );
        
        $formats = array(
            '%d', // form_id
            '%d', // entry_id
            '%s', // channel
            '%s', // source
            '%s', // medium
            '%s', // campaign
            '%s', // term
            '%s', // content
            '%s', // landing_page
            '%s', // landing_page_group
            '%f', // revenue
            '%s'  // date_created
        );
        
        $result = $wpdb->insert($table_name, $data, $formats);
        
        if ($result === false) {
            error_log('GR Attribution Cache: Failed to store attribution data for entry ' . $attribution_data['entry_id']);
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get attribution data for analysis
     * 
     * @param array $args Query arguments
     * @return array Attribution data
     */
    public static function get_attribution_data($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'form_id' => null,
            'start_date' => null,
            'end_date' => null,
            'channel' => null,
            'source' => null,
            'campaign' => null,
            'group_by' => 'channel',
            'limit' => 1000,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        
        // Build WHERE clause
        $where_conditions = array('1=1');
        $where_values = array();
        
        if (!empty($args['form_id'])) {
            if (is_array($args['form_id'])) {
                $placeholders = implode(',', array_fill(0, count($args['form_id']), '%d'));
                $where_conditions[] = "form_id IN ($placeholders)";
                $where_values = array_merge($where_values, array_map('absint', $args['form_id']));
            } else {
                $where_conditions[] = 'form_id = %d';
                $where_values[] = absint($args['form_id']);
            }
        }
        
        if (!empty($args['start_date'])) {
            $where_conditions[] = 'date_created >= %s';
            $where_values[] = sanitize_text_field($args['start_date']);
        }
        
        if (!empty($args['end_date'])) {
            $where_conditions[] = 'date_created <= %s';
            $where_values[] = sanitize_text_field($args['end_date']) . ' 23:59:59';
        }
        
        if (!empty($args['channel'])) {
            $where_conditions[] = 'channel = %s';
            $where_values[] = sanitize_text_field($args['channel']);
        }
        
        if (!empty($args['source'])) {
            $where_conditions[] = 'source = %s';
            $where_values[] = sanitize_text_field($args['source']);
        }
        
        if (!empty($args['campaign'])) {
            $where_conditions[] = 'campaign = %s';
            $where_values[] = sanitize_text_field($args['campaign']);
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Build GROUP BY clause
        $group_by_field = sanitize_text_field($args['group_by']);
        $valid_group_fields = array('channel', 'source', 'medium', 'campaign', 'landing_page_group', 'date');
        
        if (!in_array($group_by_field, $valid_group_fields)) {
            $group_by_field = 'channel';
        }
        
        if ($group_by_field === 'date') {
            $select_fields = "DATE(date_created) as date_group, COUNT(*) as entries, SUM(revenue) as total_revenue";
            $group_by_clause = "GROUP BY DATE(date_created)";
        } else {
            $select_fields = "$group_by_field as group_name, COUNT(*) as entries, SUM(revenue) as total_revenue";
            $group_by_clause = "GROUP BY $group_by_field";
        }
        
        // Build final query
        $query = "SELECT $select_fields FROM $table_name WHERE $where_clause $group_by_clause ORDER BY entries DESC";
        
        if (!empty($args['limit'])) {
            $query .= $wpdb->prepare(' LIMIT %d', absint($args['limit']));
            
            if (!empty($args['offset'])) {
                $query .= $wpdb->prepare(' OFFSET %d', absint($args['offset']));
            }
        }
        
        if (!empty($where_values)) {
            $prepared_query = $wpdb->prepare($query, $where_values);
        } else {
            $prepared_query = $query;
        }
        
        return $wpdb->get_results($prepared_query, ARRAY_A);
    }
    
    /**
     * Get unique values for filters
     * 
     * @param string $field Field to get unique values for
     * @param array $args Additional filter arguments
     * @return array Unique values
     */
    public static function get_unique_values($field, $args = array()) {
        global $wpdb;
        
        $valid_fields = array('channel', 'source', 'medium', 'campaign', 'landing_page_group');
        if (!in_array($field, $valid_fields)) {
            return array();
        }
        
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        
        // Build WHERE clause for additional filters
        $where_conditions = array('1=1', "$field IS NOT NULL", "$field != ''");
        $where_values = array();
        
        if (!empty($args['form_id'])) {
            $where_conditions[] = 'form_id = %d';
            $where_values[] = absint($args['form_id']);
        }
        
        if (!empty($args['start_date'])) {
            $where_conditions[] = 'date_created >= %s';
            $where_values[] = sanitize_text_field($args['start_date']);
        }
        
        if (!empty($args['end_date'])) {
            $where_conditions[] = 'date_created <= %s';
            $where_values[] = sanitize_text_field($args['end_date']) . ' 23:59:59';
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $query = "SELECT DISTINCT $field as value, COUNT(*) as count 
                  FROM $table_name 
                  WHERE $where_clause 
                  GROUP BY $field 
                  ORDER BY count DESC, $field ASC";
        
        if (!empty($where_values)) {
            $prepared_query = $wpdb->prepare($query, $where_values);
        } else {
            $prepared_query = $query;
        }
        
        return $wpdb->get_results($prepared_query, ARRAY_A);
    }
    
    /**
     * Calculate revenue from a form entry
     * 
     * @param array $entry Gravity Forms entry
     * @param array $form Gravity Forms form
     * @return float Revenue amount
     */
    private static function calculate_entry_revenue($entry, $form) {
        if (!class_exists('GR_QuickReports')) {
            return 0.00;
        }
        
        // Use existing revenue calculation from the main plugin
        return GR_QuickReports::calculate_revenue_from_entries(array($entry));
    }
    
    /**
     * Get channel cost data
     * 
     * @param string $channel Channel name
     * @param string $source Source name (optional)
     * @param string $campaign Campaign name (optional)
     * @return array Cost data
     */
    public static function get_channel_costs($channel = null, $source = null, $campaign = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::COSTS_TABLE_NAME;
        
        $where_conditions = array('1=1');
        $where_values = array();
        
        if (!empty($channel)) {
            $where_conditions[] = 'channel = %s';
            $where_values[] = sanitize_text_field($channel);
        }
        
        if (!empty($source)) {
            $where_conditions[] = 'source = %s';
            $where_values[] = sanitize_text_field($source);
        }
        
        if (!empty($campaign)) {
            $where_conditions[] = 'campaign = %s';
            $where_values[] = sanitize_text_field($campaign);
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $query = "SELECT * FROM $table_name WHERE $where_clause ORDER BY channel, source, campaign";
        
        if (!empty($where_values)) {
            $prepared_query = $wpdb->prepare($query, $where_values);
        } else {
            $prepared_query = $query;
        }
        
        if ($channel && !$source && !$campaign) {
            return $wpdb->get_row($prepared_query, ARRAY_A);
        }
        
        return $wpdb->get_results($prepared_query, ARRAY_A);
    }
    
    /**
     * Update channel cost data
     * 
     * @param array $cost_data Cost data array
     * @return bool Success status
     */
    public static function update_channel_costs($cost_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::COSTS_TABLE_NAME;
        
        $data = array(
            'channel' => sanitize_text_field($cost_data['channel']),
            'source' => !empty($cost_data['source']) ? sanitize_text_field($cost_data['source']) : null,
            'campaign' => !empty($cost_data['campaign']) ? sanitize_text_field($cost_data['campaign']) : null,
            'cost_per_acquisition' => floatval($cost_data['cost_per_acquisition']),
            'monthly_budget' => floatval($cost_data['monthly_budget']),
            'notes' => !empty($cost_data['notes']) ? sanitize_textarea_field($cost_data['notes']) : null
        );
        
        $formats = array('%s', '%s', '%s', '%f', '%f', '%s');
        
        // Check if record exists
        $existing = self::get_channel_costs($data['channel'], $data['source'], $data['campaign']);
        
        if ($existing) {
            // Update existing record
            $where = array('channel' => $data['channel']);
            $where_formats = array('%s');
            
            if (!empty($data['source'])) {
                $where['source'] = $data['source'];
                $where_formats[] = '%s';
            }
            
            if (!empty($data['campaign'])) {
                $where['campaign'] = $data['campaign'];
                $where_formats[] = '%s';
            }
            
            return $wpdb->update($table_name, $data, $where, $formats, $where_formats);
        } else {
            // Insert new record
            return $wpdb->insert($table_name, $data, $formats);
        }
    }
    
    /**
     * Clear attribution cache for specific criteria
     * 
     * @param array $args Clear criteria
     * @return bool Success status
     */
    public static function clear_cache($args = array()) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        
        if (empty($args)) {
            // Clear all cache
            return $wpdb->query("TRUNCATE TABLE $table_name");
        }
        
        $where_conditions = array('1=1');
        $where_values = array();
        
        if (!empty($args['form_id'])) {
            $where_conditions[] = 'form_id = %d';
            $where_values[] = absint($args['form_id']);
        }
        
        if (!empty($args['entry_id'])) {
            $where_conditions[] = 'entry_id = %d';
            $where_values[] = absint($args['entry_id']);
        }
        
        if (!empty($args['before_date'])) {
            $where_conditions[] = 'date_created < %s';
            $where_values[] = sanitize_text_field($args['before_date']);
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $query = "DELETE FROM $table_name WHERE $where_clause";
        
        if (!empty($where_values)) {
            $prepared_query = $wpdb->prepare($query, $where_values);
        } else {
            $prepared_query = $query;
        }
        
        return $wpdb->query($prepared_query);
    }
}

// Initialize the attribution cache
GR_AttributionCache::init();
