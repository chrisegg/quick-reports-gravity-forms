# Installation & Troubleshooting Guide

## Quick Installation

1. **Upload the plugin** to `/wp-content/plugins/gf-reports/`
2. **Activate** the plugin in WordPress admin
3. **Navigate** to **Forms > Reports** in your dashboard

## If the Menu Doesn't Appear

### Step 1: Check Gravity Forms
- Ensure Gravity Forms is **installed and activated**
- Verify you have a **valid Gravity Forms license**
- Check that you can see the **Forms** menu in your WordPress admin

### Step 2: Check User Permissions
- Make sure you're logged in as an **administrator**
- The plugin requires `manage_options` capability
- Try logging in as a different admin user

### Step 3: Debug Mode
Enable WordPress debug mode to see detailed information:

```php
// Add to wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Then check the debug log at `/wp-content/debug.log`

### Step 4: Test with Debug Plugin
1. Upload the `debug-menu.php` file to `/wp-content/plugins/`
2. Activate the "GF Reports Debug" plugin
3. Check the page source for debug comments
4. Look for "Test Reports" in the Forms menu

### Step 5: Check for Conflicts
1. **Deactivate all plugins** except Gravity Forms and GF Reports
2. **Switch to a default theme** (Twenty Twenty-Four)
3. **Test if the menu appears**
4. **Reactivate plugins one by one** to find the conflict

## Common Issues

### "Gravity Forms is not active" Error
- **Solution**: Install and activate Gravity Forms first
- **Check**: Verify the plugin is properly installed

### Menu Appears but Page Shows Error
- **Solution**: Check user permissions
- **Check**: Ensure you have `manage_options` capability

### Chart Not Displaying
- **Solution**: Check browser console for JavaScript errors
- **Check**: Ensure JavaScript is enabled

### CSV Export Not Working
- **Solution**: Check server memory limits
- **Check**: Verify AJAX is working on your site

## Manual Menu Check

You can manually check if the menu was registered by adding this code to your theme's `functions.php`:

```php
add_action('admin_footer', 'check_gf_reports_menu');

function check_gf_reports_menu() {
    global $submenu;
    
    if (isset($submenu['gf_edit_forms'])) {
        echo '<div style="background: #fff; padding: 10px; margin: 10px; border: 1px solid #ccc;">';
        echo '<h3>Gravity Forms Menu Items:</h3>';
        foreach ($submenu['gf_edit_forms'] as $item) {
            echo '<p>Menu: ' . $item[0] . ' | Slug: ' . $item[2] . '</p>';
        }
        echo '</div>';
    } else {
        echo '<div style="background: #fff; padding: 10px; margin: 10px; border: 1px solid #ccc;">';
        echo '<h3>Gravity Forms menu not found!</h3>';
        echo '</div>';
    }
}
```

## Support

If you're still having issues:

1. **Check the debug log** for error messages
2. **Test with the debug plugin** to isolate the issue
3. **Verify Gravity Forms version** (requires 2.5+)
4. **Check WordPress version** (requires 5.0+)

## File Structure Verification

Ensure your plugin files are in the correct location:

```
/wp-content/plugins/gf-reports/
├── gf-reports.php
├── css/
│   └── admin.css
├── js/
│   └── admin.js
└── README.md
```

All files should be readable by the web server. 