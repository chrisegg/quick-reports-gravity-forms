# Quick Reports for Gravity Forms

A powerful WordPress plugin that provides advanced reporting and visualization capabilities for Gravity Forms entries.

![quickreports-img1](https://github.com/user-attachments/assets/04db21b5-0986-494c-85e7-ce23d4262744)


## Features

- **Interactive Charts**: Visualize form submission data with Chart.js-powered graphs
- **Multiple Chart Modes**: View data per day or as totals
- **Form Comparison**: Compare data between different forms
- **Date Range Filtering**: Filter data by custom date ranges or use preset options
- **Export Capabilities**: Export reports as CSV or PDF
- **Responsive Design**: Works seamlessly on desktop and mobile devices
- **All Forms Overview**: Get insights across all your forms with aggregated data
- **Revenue Tracking**: Automatically calculate revenue from product fields

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
2. Select a form from the dropdown (or choose "All Forms" for aggregated data)
3. Choose your date range using the date picker or preset options
4. Select chart mode (Per Day or Total)
5. Click "Generate Report"

### Advanced Features

- **Form Comparison**: Select a second form to compare data side-by-side
- **All Forms View**: Select "All Forms" to see combined data from all forms
- **Individual Forms View**: When viewing all forms, switch between individual and aggregated chart views
- **Export**: Use the export buttons to download CSV or PDF reports

### Chart Modes

- **Per Day**: Shows daily submission counts over time
- **Total**: Shows the total number of submissions for the selected period

### Date Presets

Quick access to common date ranges:
- Today
- Yesterday
- Last 7 Days
- Last 30 Days
- Last 60 Days
- Last 90 Days
- Year to Date
- Last Year
- Custom Range (manual date selection)

## Export Features

### CSV Export
- Includes summary data with form statistics
- Compatible with Excel, Google Sheets, and other spreadsheet applications

![quickreports-csv](https://github.com/user-attachments/assets/f997f5e4-feab-47a1-929c-7310c81eb941)

### PDF Export
- Professional report layout with proper formatting
- Includes summary statistics and comparison data
- Clean, print-ready design
- Generated using DOMPDF library

![quickreports-pdf](https://github.com/user-attachments/assets/ba5de4c7-557e-420e-ab18-dc4c516efae6)

## Customization

### Styling
The plugin uses CSS classes that can be customized:
- `.gf-quickreports-results`: Main results container
- `.report-summary-table`: Summary statistics table
- `.chart-container`: Chart area
- `.recent-entries`: Entries table

### Hooks and Filters
The plugin provides various WordPress hooks for customization:
- `gf_quickreports_before_chart`: Before chart rendering
- `gf_quickreports_after_chart`: After chart rendering
- `gf_quickreports_export_data`: Modify export data

## Troubleshooting

### Common Issues

1. **Charts not displaying**: Ensure Chart.js is loading properly (now served locally)
2. **Export not working**: Check file permissions and PHP memory limits
3. **No data showing**: Verify form has active entries and date range is correct
4. **PDF export fails**: Ensure Composer dependencies are installed

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
- Interactive chart functionality with Chart.js
- CSV and PDF export capabilities
- Form comparison features
- All forms aggregation
- Date preset filtering
- Revenue calculation from product fields
- Responsive design
- Local Chart.js library (no external dependencies)

## License

This plugin is licensed under the GPL v2 or later.

## Credits

- Built with [Chart.js](https://www.chartjs.org/) for data visualization
- PDF generation powered by [DOMPDF](https://github.com/dompdf/dompdf)
- Icons from [Material Design Icons](https://materialdesignicons.com/)
