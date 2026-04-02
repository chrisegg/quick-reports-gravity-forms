<?php
/**
 * Gravity Forms Add-On Framework integration.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GF_QuickReports_Addon extends GFAddOn {

	/**
	 * @var GF_QuickReports_Addon|null
	 */
	private static $_instance = null;

	/**
	 * @return GF_QuickReports_Addon
	 */
	public static function get_instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	protected $_version                  = GF_QUICKREPORTS_VERSION;
	protected $_min_gravityforms_version = '2.5';
	protected $_slug                     = 'gfquickreports';
	protected $_path      = '';
	protected $_full_path = '';
	protected $_title     = 'Quick Reports for Gravity Forms';
	protected $_short_title              = 'Quick Reports';

	public function __construct() {
		$this->_path      = plugin_basename( GF_QUICKREPORTS_PLUGIN_DIR . 'gf-reports.php' );
		$this->_full_path = GF_QUICKREPORTS_PLUGIN_DIR . 'gf-reports.php';
		parent::__construct();
	}

	public function minimum_requirements() {
		return array(
			'php' => array(
				'version'    => '7.4',
				'extensions' => array( 'mbstring', 'dom' ),
			),
		);
	}

	public function init() {
		parent::init();
		load_plugin_textdomain( 'gf-quickreports', false, dirname( plugin_basename( $this->_full_path ) ) . '/languages' );
	}

	public function init_admin() {
		parent::init_admin();
		add_action( 'wp_ajax_gf_quickreports_export_csv', array( 'GF_QuickReports_Reports', 'handle_csv_export' ) );
		add_action( 'wp_ajax_gf_quickreports_export_pdf', array( 'GF_QuickReports_Reports', 'handle_pdf_export' ) );
		add_action( 'wp_ajax_gf_quickreports_get_compare_forms', array( 'GF_QuickReports_Reports', 'get_compare_forms' ) );
		add_action( 'wp_ajax_gf_quickreports_get_date_presets', array( 'GF_QuickReports_Reports', 'get_date_presets' ) );
	}

	public function scripts() {
		$scripts = array(
			array(
				'handle'    => 'gf-quickreports-chartjs',
				'src'       => $this->get_base_url() . 'assets/js/lib/chart.min.js',
				'version'   => '3.9.1',
				'deps'      => array(),
				'in_footer' => true,
				'enqueue'   => array(
					array( 'admin_page' => array( 'plugin_page' ) ),
				),
			),
			array(
				'handle'    => 'gf-quickreports-admin',
				'src'       => $this->get_base_url() . 'assets/js/admin.js',
				'version'   => $this->_version,
				'deps'      => array( 'jquery', 'gf-quickreports-chartjs' ),
				'in_footer' => true,
				'callback'  => array( $this, 'localize_admin_script' ),
				'enqueue'   => array(
					array( 'admin_page' => array( 'plugin_page' ) ),
				),
			),
		);
		return array_merge( parent::scripts(), $scripts );
	}

	public function styles() {
		$styles = array(
			array(
				'handle'  => 'gf-quickreports-admin',
				'src'     => $this->get_base_url() . 'assets/css/admin.css',
				'version' => $this->_version,
				'enqueue' => array(
					array( 'admin_page' => array( 'plugin_page' ) ),
				),
			),
		);
		return array_merge( parent::styles(), $styles );
	}

	/**
	 * Localize AJAX for admin.js (expects global gf_quickreports_ajax).
	 *
	 * @param array $script Script registration entry.
	 */
	public function localize_admin_script( $script ) {
		if ( empty( $script['handle'] ) || 'gf-quickreports-admin' !== $script['handle'] ) {
			return;
		}
		wp_localize_script(
			'gf-quickreports-admin',
			'gf_quickreports_ajax',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'gf_quickreports_nonce' ),
			)
		);
	}

	public function get_menu_icon() {
		return 'gform-icon--cog';
	}

	public function plugin_page() {
		if ( ! GF_QuickReports_Reports::user_can_reports() ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'gf-quickreports' ) );
		}
		require GF_QUICKREPORTS_PLUGIN_DIR . 'templates/reports-page.php';
	}
}
