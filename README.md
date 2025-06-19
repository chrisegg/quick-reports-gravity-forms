# Quick Reports for Gravity Forms

A powerful WordPress plugin that provides advanced reporting and visualization capabilities for Gravity Forms entries.

## Features

- **Interactive Charts**: Visualize form submission data with Chart.js-powered graphs
- **Multiple Chart Modes**: View data per day or as totals
- **Form Comparison**: Compare data between different forms
- **Date Range Filtering**: Filter data by custom date ranges
- **Export Capabilities**: Export reports as CSV or PDF
- **Responsive Design**: Works seamlessly on desktop and mobile devices
- **Recent Entries Table**: View the latest form submissions
- **All Forms Overview**: Get insights across all your forms

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- Gravity Forms plugin (any license)
- Composer (for PDF export functionality)

## Installation

1. Upload the plugin files to `/wp-content/plugins/gf-quickreports/`
2. Install dependencies: `composer install`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Navigate to Forms > Quick Reports to start using

## Usage

### Basic Reporting

1. Go to **Forms > Quick Reports** in your WordPress admin
2. Select a form from the dropdown
3. Choose your date range (optional)
4. Select chart mode (Per Day or Total)
5. Click "Generate Report"

### Advanced Features

- **Form Comparison**: Select a second form to compare data
- **All Forms View**: Select "All Forms" to see combined data
- **Individual Forms View**: When viewing all forms, switch to individual view for separate charts
- **Export**: Use the export buttons to download CSV or PDF reports

### Chart Modes

- **Per Day**: Shows daily submission counts over time
- **Total**: Shows the total number of submissions for the selected period

### Date Presets

Quick access to common date ranges:
- Last 7 Days
- Last 30 Days
- Last 90 Days
- This Month
- Last Month
- This Year

## Keyboard Shortcuts

- **Ctrl/Cmd + Enter**: Submit the report form
- **Ctrl/Cmd + E**: Export as CSV

## Export Features

### CSV Export
- Includes all form fields (excluding admin-only fields)
- Contains entry ID, date, and IP address
- Compatible with Excel, Google Sheets, and other spreadsheet applications

### PDF Export
- Professional report layout
- Includes chart visualization
- Recent entries table
- Formatted for printing

## Customization

### Styling
The plugin uses CSS classes that can be customized:
- `.gf-quickreports-container`: Main container
- `.gf-quickreports-form`: Filter form
- `.gf-quickreports-results`: Results section
- `.chart-container`: Chart area
- `.recent-entries`: Entries table

### Hooks and Filters
The plugin provides various WordPress hooks for customization:
- `gf_quickreports_before_chart`: Before chart rendering
- `gf_quickreports_after_chart`: After chart rendering
- `gf_quickreports_export_data`: Modify export data

## Troubleshooting

### Common Issues

1. **Charts not displaying**: Ensure Chart.js is loading properly
2. **Export not working**: Check file permissions and PHP memory limits
3. **No data showing**: Verify form has active entries and date range is correct

### Debug Mode
Enable WordPress debug mode to see detailed error messages:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Support

For support and feature requests, please visit our support forum or contact us directly.

## Changelog

### Version 1.0.0
- Initial release
- Basic chart functionality
- CSV and PDF export
- Form comparison
- Responsive design

## License

This plugin is licensed under the GPL v2 or later.

## Credits

- Built with [Chart.js](https://www.chartjs.org/) for data visualization
- PDF generation powered by [mPDF](https://mpdf.github.io/)
- Icons from [Material Design Icons](https://materialdesignicons.com/)