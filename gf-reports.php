<?php
/**
 * Plugin Name: Quick Reports for Gravity Forms
 * Plugin URI: https://gravityranger.com/plugins/quick-reports-gravity-forms
 * Description: Advanced reporting and visualization for Gravity Forms entries
 * Version: 1.0.1
 * Author: Chris Eggleston
 * Author URI: https://gravityranger.com
 * Text Domain: gf-quickreports
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.0
 * Requires PHP: 7.4
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GF_QUICKREPORTS_VERSION', '1.0.1' );
define( 'GF_QUICKREPORTS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GF_QUICKREPORTS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Load add-on after Gravity Forms and register with the add-on framework.
 */
function gf_quickreports_load_addon() {
	if ( class_exists( 'GF_QuickReports_Addon', false ) ) {
		return;
	}
	if ( ! method_exists( 'GFForms', 'include_addon_framework' ) ) {
		return;
	}

	GFForms::include_addon_framework();

	require_once GF_QUICKREPORTS_PLUGIN_DIR . 'includes/class-gf-quickreports-reports.php';
	require_once GF_QUICKREPORTS_PLUGIN_DIR . 'includes/class-gf-quickreports-addon.php';

	GFAddOn::register( 'GF_QuickReports_Addon' );
}

add_action( 'gform_loaded', 'gf_quickreports_load_addon', 5 );

/**
 * Admin notice when Gravity Forms is not active.
 */
function gf_quickreports_admin_notice_missing_gf() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	if ( class_exists( 'GFForms', false ) ) {
		return;
	}
	echo '<div class="notice notice-warning"><p>';
	echo esc_html__( 'Quick Reports for Gravity Forms requires Gravity Forms to be installed and active.', 'gf-quickreports' );
	echo '</p></div>';
}

add_action( 'admin_notices', 'gf_quickreports_admin_notice_missing_gf' );

/**
 * Global helpers for templates and legacy callers.
 *
 * @param int|string $form_id Form ID or 'all'.
 * @param string     $start_date Y-m-d.
 * @param string     $end_date Y-m-d.
 * @return array
 */
function gf_quickreports_get_daily_entries( $form_id, $start_date, $end_date ) {
	return GF_QuickReports_Reports::get_daily_entries( $form_id, $start_date, $end_date );
}

/**
 * @param int|string $form_id Form ID or 'all'.
 * @param string     $start_date Y-m-d.
 * @param string     $end_date Y-m-d.
 * @return array
 */
function gf_quickreports_get_daily_revenue( $form_id, $start_date, $end_date ) {
	return GF_QuickReports_Reports::get_daily_revenue( $form_id, $start_date, $end_date );
}

/**
 * @return GF_QuickReports_Addon|null
 */
function gf_quickreports_addon() {
	if ( class_exists( 'GF_QuickReports_Addon' ) ) {
		return GF_QuickReports_Addon::get_instance();
	}
	return null;
}
