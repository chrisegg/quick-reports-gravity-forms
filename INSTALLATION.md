# Quick Reports for Gravity Forms - Installation Guide

## Prerequisites

Before installing the plugin, ensure you have:

- WordPress 5.0 or higher
- PHP 7.2 or higher
- Gravity Forms plugin (any license)
- Composer (for PDF export functionality)

## Installation Steps

### Step 1: Download and Extract

1. Download the plugin files
2. Extract the ZIP file to your local machine
3. Ensure the folder structure looks like this:
   ```
   gf-quickreports/
   ├── gf-reports.php
   ├── composer.json
   ├── css/
   │   └── admin.css
   ├── js/
   │   └── admin.js
   ├── templates/
   │   └── reports-page.php
   └── vendor/ (will be created after composer install)
   ```

### Step 2: Install Dependencies

1. Open terminal/command prompt
2. Navigate to the plugin directory:
   ```bash
   cd /path/to/wp-content/plugins/gf-quickreports
   ```
3. Install PHP dependencies:
   ```bash
   composer install
   ```

### Step 3: Upload to WordPress

1. Upload the entire `gf-quickreports` folder to your `/wp-content/plugins/` directory
2. Ensure the plugin folder has proper permissions (755 for directories, 644 for files)

### Step 4: Activate the Plugin

1. Log in to your WordPress admin dashboard
2. Go to **Plugins > Installed Plugins**
3. Find "Quick Reports for Gravity Forms" in the list
4. Click **Activate**

### Step 5: Verify Installation

1. Navigate to **Forms > Quick Reports** in your WordPress admin
2. You should see the Quick Reports interface
3. Select a form and generate a report to test functionality

## Configuration

### File Permissions

Ensure proper file permissions:
```bash
find /path/to/wp-content/plugins/gf-quickreports -type d -exec chmod 755 {} \;
find /path/to/wp-content/plugins/gf-quickreports -type f -exec chmod 644 {} \;
```

### PHP Requirements

The plugin requires:
- PHP 7.2 or higher
- GD extension (for chart image generation)
- mbstring extension (for PDF generation)

### Memory Limits

For large exports, you may need to increase PHP memory limits:
```php
// Add to wp-config.php
define('WP_MEMORY_LIMIT', '256M');
```

## Troubleshooting

### Common Installation Issues

1. **Composer not found**
   - Install Composer from https://getcomposer.org/
   - Ensure it's in your system PATH

2. **Permission denied errors**
   - Check file and directory permissions
   - Ensure web server can read plugin files

3. **Plugin not appearing in admin**
   - Check for PHP errors in error logs
   - Verify Gravity Forms is active
   - Ensure plugin files are in correct location

4. **Charts not displaying**
   - Check browser console for JavaScript errors
   - Verify Chart.js is loading from CDN
   - Ensure no JavaScript conflicts

5. **Export functionality not working**
   - Check AJAX is working on your site
   - Verify nonce verification is passing
   - Check server memory limits

### Debug Mode

Enable WordPress debug mode for detailed error messages:
```php
// Add to wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Testing

After installation, test the following:
1. Form selection dropdown populates
2. Date range filtering works
3. Charts display correctly
4. CSV export downloads properly
5. PDF export generates files
6. Responsive design works on mobile

## Updating

To update the plugin:

1. Deactivate the plugin
2. Replace plugin files with new version
3. Run `composer install` to update dependencies
4. Reactivate the plugin

## Uninstallation

To completely remove the plugin:

1. Deactivate the plugin in WordPress admin
2. Delete the plugin folder from `/wp-content/plugins/gf-quickreports/`
3. The plugin will automatically clean up any database entries

## Support

If you encounter issues during installation:

1. Check the troubleshooting section above
2. Review WordPress error logs
3. Test with a default WordPress theme
4. Disable other plugins to check for conflicts
5. Contact support with detailed error information

## Security Notes

- Keep the plugin updated to the latest version
- Use strong passwords for WordPress admin
- Regularly backup your site
- Monitor error logs for suspicious activity 