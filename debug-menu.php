<?php
/*
Plugin Name: GF Reports Debug
Description: Debug version to test menu registration
Version: 1.0
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Test menu registration
add_action('admin_menu', 'test_menu_registration', 99);

function test_menu_registration() {
    echo '<!-- Testing menu registration -->';
    
    // Check if Gravity Forms is active
    if (class_exists('GFFormsModel')) {
        echo '<!-- Gravity Forms is active -->';
        
        // Try to add the menu
        add_submenu_page(
            'gf_edit_forms',
            'Test Reports',
            'Test Reports',
            'manage_options',
            'test-reports',
            'test_reports_page'
        );
        
        echo '<!-- Menu registration attempted -->';
    } else {
        echo '<!-- Gravity Forms is NOT active -->';
    }
}

function test_reports_page() {
    echo '<div class="wrap">';
    echo '<h1>Test Reports Page</h1>';
    echo '<p>If you can see this, the menu registration is working!</p>';
    echo '</div>';
}

// Debug function to show menu structure
add_action('admin_footer', 'debug_menu_structure');

function debug_menu_structure() {
    global $submenu;
    
    echo '<!-- Menu Debug: ';
    if (isset($submenu['gf_edit_forms'])) {
        echo 'Gravity Forms menu exists with ' . count($submenu['gf_edit_forms']) . ' items. ';
        foreach ($submenu['gf_edit_forms'] as $item) {
            echo 'Item: ' . $item[0] . ' (slug: ' . $item[2] . ') ';
        }
    } else {
        echo 'Gravity Forms menu does not exist. ';
    }
    echo '-->';
} 