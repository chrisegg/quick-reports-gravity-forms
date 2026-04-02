<?php
/**
 * Attributer Field Detection Class
 * 
 * Detects and maps Attributer.io fields in Gravity Forms
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class GR_AttributerDetector
 * 
 * Handles detection and mapping of Attributer fields in Gravity Forms
 */
class GR_AttributerDetector {
    
    /**
     * Standard Attributer field patterns
     */
    const ATTRIBUTER_FIELDS = array(
        'channel' => array(
            'patterns' => array('attributer_channel', 'channel', 'traffic_channel'),
            'label_patterns' => array('channel', 'traffic channel', 'marketing channel'),
            'type' => 'channel'
        ),
        'source' => array(
            'patterns' => array('attributer_source', 'attributer_channel_drilldown_1', 'source', 'traffic_source'),
            'label_patterns' => array('source', 'traffic source', 'channel drilldown 1'),
            'type' => 'source'
        ),
        'medium' => array(
            'patterns' => array('attributer_medium', 'attributer_channel_drilldown_2', 'medium', 'traffic_medium'),
            'label_patterns' => array('medium', 'traffic medium', 'channel drilldown 2'),
            'type' => 'medium'
        ),
        'campaign' => array(
            'patterns' => array('attributer_campaign', 'attributer_channel_drilldown_3', 'campaign', 'utm_campaign'),
            'label_patterns' => array('campaign', 'campaign name', 'channel drilldown 3'),
            'type' => 'campaign'
        ),
        'term' => array(
            'patterns' => array('attributer_term', 'utm_term', 'keyword', 'search_term'),
            'label_patterns' => array('term', 'keyword', 'search term'),
            'type' => 'term'
        ),
        'content' => array(
            'patterns' => array('attributer_content', 'utm_content', 'ad_content'),
            'label_patterns' => array('content', 'ad content', 'utm content'),
            'type' => 'content'
        ),
        'landing_page' => array(
            'patterns' => array('attributer_landing_page', 'landing_page', 'first_page'),
            'label_patterns' => array('landing page', 'first page', 'entry page'),
            'type' => 'landing_page'
        ),
        'landing_page_group' => array(
            'patterns' => array('attributer_landing_page_group', 'landing_page_group', 'page_group'),
            'label_patterns' => array('landing page group', 'page group', 'page category'),
            'type' => 'landing_page_group'
        )
    );
    
    /**
     * Detect Attributer fields in a specific form
     * 
     * @param int $form_id The Gravity Forms form ID
     * @return array Mapping of field IDs to attribution types
     */
    public static function detect_attributer_fields($form_id) {
        $form = GFAPI::get_form($form_id);
        
        if (!$form || !isset($form['fields'])) {
            return array();
        }
        
        $detected_fields = array();
        
        foreach ($form['fields'] as $field) {
            $attribution_type = self::identify_attribution_field($field);
            if ($attribution_type) {
                $detected_fields[$field->id] = array(
                    'type' => $attribution_type,
                    'label' => $field->label,
                    'admin_label' => $field->adminLabel,
                    'css_class' => $field->cssClass,
                    'field_type' => $field->type
                );
            }
        }
        
        return $detected_fields;
    }
    
    /**
     * Identify if a field is an attribution field
     * 
     * @param object $field Gravity Forms field object
     * @return string|false Attribution type or false if not attribution field
     */
    private static function identify_attribution_field($field) {
        // Only check hidden fields and some text fields
        if (!in_array($field->type, array('hidden', 'text', 'select'))) {
            return false;
        }
        
        // Check field properties for Attributer patterns
        $field_identifiers = array(
            'label' => strtolower($field->label),
            'admin_label' => strtolower($field->adminLabel),
            'css_class' => strtolower($field->cssClass),
            'parameter_name' => isset($field->inputName) ? strtolower($field->inputName) : '',
            'default_value' => isset($field->defaultValue) ? strtolower($field->defaultValue) : ''
        );
        
        foreach (self::ATTRIBUTER_FIELDS as $attribution_type => $config) {
            // Check against field name patterns
            foreach ($config['patterns'] as $pattern) {
                foreach ($field_identifiers as $identifier) {
                    if (strpos($identifier, $pattern) !== false) {
                        return $attribution_type;
                    }
                }
            }
            
            // Check against label patterns
            foreach ($config['label_patterns'] as $label_pattern) {
                if (strpos($field_identifiers['label'], $label_pattern) !== false ||
                    strpos($field_identifiers['admin_label'], $label_pattern) !== false) {
                    return $attribution_type;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Get all forms that have Attributer fields
     * 
     * @return array Array of form IDs with their detected attribution fields
     */
    public static function get_forms_with_attribution() {
        $forms = GFAPI::get_forms();
        $forms_with_attribution = array();
        
        foreach ($forms as $form) {
            $detected_fields = self::detect_attributer_fields($form['id']);
            if (!empty($detected_fields)) {
                $forms_with_attribution[$form['id']] = array(
                    'form_title' => $form['title'],
                    'attribution_fields' => $detected_fields
                );
            }
        }
        
        return $forms_with_attribution;
    }
    
    /**
     * Extract attribution data from a form entry
     * 
     * @param array $entry Gravity Forms entry
     * @param int $form_id Form ID
     * @return array Attribution data
     */
    public static function extract_attribution_data($entry, $form_id) {
        $detected_fields = self::detect_attributer_fields($form_id);
        $attribution_data = array();
        
        foreach ($detected_fields as $field_id => $field_config) {
            $value = isset($entry[$field_id]) ? sanitize_text_field($entry[$field_id]) : '';
            $attribution_data[$field_config['type']] = $value;
        }
        
        // Add entry metadata
        $attribution_data['entry_id'] = $entry['id'];
        $attribution_data['form_id'] = $form_id;
        $attribution_data['date_created'] = $entry['date_created'];
        
        return $attribution_data;
    }
    
    /**
     * Validate attribution data
     * 
     * @param array $attribution_data
     * @return array Validated data with any corrections
     */
    public static function validate_attribution_data($attribution_data) {
        $validated = array();
        
        // Define validation rules
        $validation_rules = array(
            'channel' => array('max_length' => 100, 'required' => false),
            'source' => array('max_length' => 100, 'required' => false),
            'medium' => array('max_length' => 100, 'required' => false),
            'campaign' => array('max_length' => 200, 'required' => false),
            'term' => array('max_length' => 200, 'required' => false),
            'content' => array('max_length' => 200, 'required' => false),
            'landing_page' => array('max_length' => 500, 'required' => false, 'type' => 'url'),
            'landing_page_group' => array('max_length' => 100, 'required' => false)
        );
        
        foreach ($validation_rules as $field => $rules) {
            if (isset($attribution_data[$field])) {
                $value = $attribution_data[$field];
                
                // Sanitize
                $value = sanitize_text_field($value);
                
                // Check max length
                if (isset($rules['max_length']) && strlen($value) > $rules['max_length']) {
                    $value = substr($value, 0, $rules['max_length']);
                }
                
                // URL validation for landing page
                if (isset($rules['type']) && $rules['type'] === 'url' && !empty($value)) {
                    $value = esc_url_raw($value);
                }
                
                $validated[$field] = $value;
            } else {
                $validated[$field] = '';
            }
        }
        
        // Preserve metadata
        $metadata_fields = array('entry_id', 'form_id', 'date_created');
        foreach ($metadata_fields as $meta_field) {
            if (isset($attribution_data[$meta_field])) {
                $validated[$meta_field] = $attribution_data[$meta_field];
            }
        }
        
        return $validated;
    }
    
    /**
     * Get attribution field mapping for a form (with manual overrides)
     * 
     * @param int $form_id Form ID
     * @return array Field mapping including manual overrides
     */
    public static function get_field_mapping($form_id) {
        // Get auto-detected fields
        $auto_detected = self::detect_attributer_fields($form_id);
        
        // Get manual overrides from settings
        $manual_overrides = get_option('gr_attribution_field_mapping_' . $form_id, array());
        
        // Merge with manual overrides taking precedence
        return wp_parse_args($manual_overrides, $auto_detected);
    }
    
    /**
     * Save manual field mapping for a form
     * 
     * @param int $form_id Form ID
     * @param array $mapping Field mapping
     * @return bool Success status
     */
    public static function save_field_mapping($form_id, $mapping) {
        $sanitized_mapping = array();
        
        foreach ($mapping as $field_id => $config) {
            $sanitized_mapping[absint($field_id)] = array(
                'type' => sanitize_text_field($config['type']),
                'label' => sanitize_text_field($config['label']),
                'enabled' => !empty($config['enabled'])
            );
        }
        
        return update_option('gr_attribution_field_mapping_' . absint($form_id), $sanitized_mapping);
    }
}
