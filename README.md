# Gravity Forms Reports Add-On

A powerful WordPress plugin that adds comprehensive reporting capabilities to Gravity Forms, providing insights into form submissions with charts, analytics, and export functionality.

## Features

- **Form Selection**: Choose from any active Gravity Form
- **Date Range Filtering**: Custom date ranges with preset options
- **Visual Analytics**: Interactive charts showing entries over time
- **Summary Statistics**: Key metrics and KPIs
- **Recent Entries Table**: View the latest form submissions
- **CSV Export**: Download filtered data for external analysis
- **Responsive Design**: Works perfectly on desktop and mobile
- **Keyboard Shortcuts**: Quick actions for power users

## Requirements

- WordPress 5.0 or higher
- Gravity Forms 2.5 or higher
- PHP 7.4 or higher

## Installation

### Method 1: Manual Installation

1. Download the plugin files
2. Upload the `gf-reports` folder to your `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Navigate to **Forms > Reports** in your WordPress admin

### Method 2: ZIP Upload

1. Create a ZIP file containing all plugin files
2. Go to **Plugins > Add New > Upload Plugin** in WordPress admin
3. Upload the ZIP file and activate the plugin
4. Navigate to **Forms > Reports** in your WordPress admin

## Usage

### Basic Report Generation

1. **Select a Form**: Choose the Gravity Form you want to analyze
2. **Set Date Range**: Use the date pickers or select from presets:
   - Last 7 Days
   - Last 30 Days
   - Last 90 Days
   - This Month
   - Last Month
   - This Year
3. **Generate Report**: Click "Generate Report" to view results

### Understanding the Dashboard

#### Summary Statistics
- **Total Entries**: Number of form submissions in the selected period
- **Date Range**: The time period being analyzed
- **Form Name**: The selected form being reported on

#### Chart Visualization
- Interactive line chart showing daily entry counts
- Hover over data points for detailed information
- Responsive design that adapts to screen size

#### Recent Entries Table
- Shows the last 10 form submissions
- Displays all form fields with their values
- Formatted dates and entry IDs for easy reference

### Exporting Data

1. Generate a report with your desired filters
2. Click the "Export CSV" button
3. The file will download automatically with the format: `gf-reports-{form-id}-{date}.csv`

### Keyboard Shortcuts

- **Ctrl/Cmd + Enter**: Submit the report form
- **Ctrl/Cmd + E**: Export CSV (when available)

## Customization

### Styling

The plugin includes comprehensive CSS that can be customized. Main style classes:

- `.gf-reports-filters`: Filter section styling
- `.report-summary`: Summary statistics cards
- `.chart-container`: Chart area styling
- `.recent-entries`: Table styling

### Hooks and Filters

The plugin provides several WordPress hooks for customization:

```php
// Modify the forms list
add_filter('gf_reports_forms_list', 'my_custom_forms_filter');

// Add custom statistics
add_action('gf_reports_after_summary', 'my_custom_stats');

// Modify export data
add_filter('gf_reports_export_data', 'my_custom_export_data');
```

## Configuration

### Permissions

The plugin uses Gravity Forms' built-in permissions:
- `gravityforms_view_entries`: Required to view reports
- `gravityforms_edit_entries`: Required for advanced features

### Performance

For sites with large numbers of entries:
- Reports are limited to 1,000 recent entries for display
- CSV exports can handle up to 10,000 entries
- Consider using date filters to improve performance

## Troubleshooting

### Common Issues

**"Gravity Forms is not active" error**
- Ensure Gravity Forms is installed and activated
- Check that you have a valid Gravity Forms license

**No forms appear in dropdown**
- Verify that you have active Gravity Forms
- Check user permissions for viewing forms

**Chart not displaying**
- Ensure JavaScript is enabled in your browser
- Check browser console for any JavaScript errors

**CSV export not working**
- Verify AJAX is working on your site
- Check server memory limits for large exports

### Debug Mode

Enable WordPress debug mode to see detailed error messages:

```php
// Add to wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Advanced Features

### Custom Analytics

The plugin can be extended to include:
- Conversion rate calculations
- Field-specific analytics
- Custom chart types
- Advanced filtering options

### Integration Possibilities

- Connect with Google Analytics
- Export to Google Sheets
- Email report scheduling
- Custom dashboard widgets

## Support

For support and feature requests:
1. Check the troubleshooting section above
2. Review WordPress error logs
3. Test with default WordPress theme
4. Disable other plugins to check for conflicts

## License

This plugin is released under the GPL v2 or later license.

## Changelog

### Version 1.0
- Initial release
- Basic reporting functionality
- Chart.js integration
- CSV export feature
- Responsive design
- Date range presets
- Keyboard shortcuts

## Credits

- Built for Gravity Forms
- Uses Chart.js for visualizations
- WordPress coding standards compliant
- Accessibility focused design

---

**Note**: This plugin is designed to work with Gravity Forms and requires an active Gravity Forms license for full functionality.