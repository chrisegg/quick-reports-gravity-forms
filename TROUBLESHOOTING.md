# GF Reports - Chart Troubleshooting Guide

## Chart Not Rendering Issue

If the chart is not displaying on your GF Reports page, follow these troubleshooting steps:

### Step 1: Enable WordPress Debug Mode

Add these lines to your `wp-config.php` file:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Step 2: Check Browser Console

1. Open your browser's Developer Tools (F12)
2. Go to the Console tab
3. Navigate to the GF Reports page
4. Look for debug messages starting with "GF Reports Debug"

### Step 3: Common Issues and Solutions

#### Issue: "Chart.js failed to load"
**Solution:** 
- Check your internet connection
- Verify CDN access to `https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js`
- Try refreshing the page

#### Issue: "Canvas element not found"
**Solution:**
- Ensure you have selected a form and generated a report
- Check if there are any PHP errors in the WordPress error log

#### Issue: "No data for this period"
**Solution:**
- Verify that your selected form has entries in the specified date range
- Check the date range - ensure end date is after start date
- Confirm form has active entries (not spam/trash)

### Step 4: Check WordPress Error Log

Look for PHP errors in your WordPress error log:
- Usually located at `/wp-content/debug.log`
- Look for "GF Reports Debug" entries

### Step 5: Test Chart.js Independently

1. Open `test-chart.html` in your browser (if available in plugin directory)
2. If the test chart works, the issue is with WordPress integration
3. If the test chart doesn't work, it's a Chart.js loading issue

### Step 6: Verify Plugin Requirements

Ensure you have:
- WordPress 5.0+
- Gravity Forms 2.0+
- PHP 7.4+
- Modern browser with JavaScript enabled

### Step 7: Manual Debug Steps

If the above doesn't help, try these manual checks:

1. **Check Hook Priority:**
   - The plugin uses priority 99 for admin_menu
   - If still not working, try changing to priority 999

2. **Check Script Dependencies:**
   - Verify Chart.js loads before admin.js
   - Check browser Network tab for failed script loads

3. **Check Canvas Dimensions:**
   - Canvas should have width/height attributes
   - CSS should set proper dimensions

### Step 8: Get Help

If none of the above resolves the issue:

1. Copy all console debug messages
2. Check WordPress error log for PHP errors
3. Note your WordPress version, Gravity Forms version, and browser
4. Provide screenshots of the issue

## Quick Fixes

### Fix 1: Clear Browser Cache
Sometimes old cached JavaScript files can cause issues.

### Fix 2: Deactivate/Reactivate Plugin
This ensures all hooks are properly registered.

### Fix 3: Check for Plugin Conflicts
Temporarily deactivate other plugins to check for conflicts.

### Fix 4: Switch to Default Theme
Test with a default WordPress theme to rule out theme conflicts.

## Technical Details

The chart rendering process:
1. PHP generates chart data from Gravity Forms entries
2. Data is passed to JavaScript via `wp_add_inline_script()`
3. Chart.js library is loaded from CDN
4. JavaScript initializes Chart.js with the data
5. Chart renders in the canvas element

Common failure points:
- CDN loading (network issues)
- Script loading order (dependencies)
- Data format issues (PHP to JS conversion)
- Canvas element availability (DOM ready)
- Browser compatibility (older browsers) 