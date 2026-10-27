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
	 * Avoid outputting duplicate wp_localize_script for the same handle in one request.
	 *
	 * @var bool
	 */
	private static $admin_script_localized = false;

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

	/**
	 * Plugin version plus file mtime so browsers do not keep stale admin.js after updates.
	 *
	 * @param string $relative Path under the plugin directory, e.g. assets/js/admin.js.
	 * @return string
	 */
	private function asset_version( $relative ) {
		$path = GF_QUICKREPORTS_PLUGIN_DIR . ltrim( $relative, '/' );
		if ( is_readable( $path ) ) {
			return $this->_version . '.' . (string) filemtime( $path );
		}
		return $this->_version;
	}

	public function init() {
		parent::init();
		load_plugin_textdomain( 'gf-quickreports', false, dirname( plugin_basename( $this->_full_path ) ) . '/languages' );
	}

	public function init_admin() {
		parent::init_admin();
		add_filter( 'gform_addon_navigation', array( $this, 'move_plugin_menu_to_bottom' ), 99999 );
		add_action( 'admin_menu', array( $this, 'move_reports_submenu_to_bottom' ), 99999 );
		add_filter( 'gform_noconflict_scripts', array( $this, 'register_noconflict_scripts' ) );
		add_filter( 'gform_noconflict_styles', array( $this, 'register_noconflict_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'maybe_force_enqueue_reports_assets' ), 1000 );
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
				'version'   => $this->asset_version( 'assets/js/lib/chart.min.js' ),
				'deps'      => array(),
				'in_footer' => true,
				'enqueue'   => array(
					array( 'admin_page' => array( 'plugin_page' ) ),
				),
			),
			array(
				'handle'    => 'gf-quickreports-admin',
				'src'       => $this->get_base_url() . 'assets/js/admin.js',
				'version'   => $this->asset_version( 'assets/js/admin.js' ),
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
				'version' => $this->asset_version( 'assets/css/admin.css' ),
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
	 * @param array|null $script Script registration entry from GF, or null when called manually.
	 */
	public function localize_admin_script( $script = null ) {
		if ( null !== $script && ( empty( $script['handle'] ) || 'gf-quickreports-admin' !== $script['handle'] ) ) {
			return;
		}
		$this->localize_gf_quickreports_admin_script();
	}

	/**
	 * Outputs wp_localize_script once per request for gf-quickreports-admin.
	 */
	private function localize_gf_quickreports_admin_script() {
		if ( self::$admin_script_localized ) {
			return;
		}
		if ( ! wp_script_is( 'gf-quickreports-admin', 'registered' ) ) {
			return;
		}
		$admin_js = GF_QUICKREPORTS_PLUGIN_DIR . 'assets/js/admin.js';
		$build    = is_readable( $admin_js ) ? (string) filemtime( $admin_js ) : '0';

		wp_localize_script(
			'gf-quickreports-admin',
			'gf_quickreports_ajax',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'gf_quickreports_nonce' ),
				'debug'    => ( defined( 'WP_DEBUG' ) && WP_DEBUG ),
				'build'    => $build,
			)
		);
		self::$admin_script_localized = true;
	}

	/**
	 * Gravity Forms no-conflict mode strips unregistered scripts; whitelist ours.
	 *
	 * @param array $scripts Script handles.
	 * @return array
	 */
	public function register_noconflict_scripts( $scripts ) {
		if ( ! is_array( $scripts ) ) {
			$scripts = array();
		}
		$scripts[] = 'gf-quickreports-chartjs';
		$scripts[] = 'gf-quickreports-admin';
		return array_values( array_unique( $scripts ) );
	}

	/**
	 * @param array $styles Style handles.
	 * @return array
	 */
	public function register_noconflict_styles( $styles ) {
		if ( ! is_array( $styles ) ) {
			$styles = array();
		}
		$styles[] = 'gf-quickreports-admin';
		return array_values( array_unique( $styles ) );
	}

	/**
	 * True when the Quick Reports add-on screen is being requested.
	 *
	 * @return bool
	 */
	private function is_quick_reports_gf_screen() {
		if ( empty( $_GET['page'] ) || 'gf_edit_forms' !== $_GET['page'] ) {
			return false;
		}
		if ( empty( $_GET['subview'] ) ) {
			return false;
		}
		$sub  = strtolower( sanitize_text_field( wp_unslash( $_GET['subview'] ) ) );
		$want = strtolower( (string) $this->get_slug() );
		return ( $sub === $want );
	}

	/**
	 * If the add-on framework did not enqueue our assets, register and enqueue them here.
	 * Without this, charts/exports never run when no-conflict or enqueue rules fail to match.
	 */
	public function maybe_force_enqueue_reports_assets() {
		if ( ! is_admin() || ! $this->is_quick_reports_gf_screen() ) {
			return;
		}
		if ( ! GF_QuickReports_Reports::user_can_reports() ) {
			return;
		}
		if ( wp_script_is( 'gf-quickreports-admin', 'enqueued' ) ) {
			if ( ! wp_script_is( 'gf-quickreports-chartjs', 'enqueued' ) && wp_script_is( 'gf-quickreports-chartjs', 'registered' ) ) {
				wp_enqueue_script( 'gf-quickreports-chartjs' );
			}
			$this->localize_gf_quickreports_admin_script();
			if ( ! wp_style_is( 'gf-quickreports-admin', 'enqueued' ) ) {
				$this->register_and_enqueue_reports_style();
			}
			return;
		}

		$chart_ver = $this->asset_version( 'assets/js/lib/chart.min.js' );
		$admin_ver = $this->asset_version( 'assets/js/admin.js' );

		if ( ! wp_script_is( 'gf-quickreports-chartjs', 'registered' ) ) {
			wp_register_script(
				'gf-quickreports-chartjs',
				$this->get_base_url() . 'assets/js/lib/chart.min.js',
				array(),
				$chart_ver,
				true
			);
		}
		if ( ! wp_script_is( 'gf-quickreports-admin', 'registered' ) ) {
			wp_register_script(
				'gf-quickreports-admin',
				$this->get_base_url() . 'assets/js/admin.js',
				array( 'jquery', 'gf-quickreports-chartjs' ),
				$admin_ver,
				true
			);
		}

		wp_enqueue_script( 'gf-quickreports-chartjs' );
		wp_enqueue_script( 'gf-quickreports-admin' );
		$this->localize_gf_quickreports_admin_script();
		$this->register_and_enqueue_reports_style();
	}

	/**
	 * Enqueue reports admin CSS if not already queued.
	 */
	private function register_and_enqueue_reports_style() {
		if ( wp_style_is( 'gf-quickreports-admin', 'enqueued' ) ) {
			return;
		}
		if ( ! wp_style_is( 'gf-quickreports-admin', 'registered' ) ) {
			wp_register_style(
				'gf-quickreports-admin',
				$this->get_base_url() . 'assets/css/admin.css',
				array(),
				$this->asset_version( 'assets/css/admin.css' )
			);
		}
		wp_enqueue_style( 'gf-quickreports-admin' );
	}

	/**
	 * Move the Quick Reports entry to the end of the Forms submenu (WP menu structure).
	 */
	public function move_reports_submenu_to_bottom() {
		global $submenu;
		if ( ! is_array( $submenu ) ) {
			return;
		}
		$needle = strtolower( (string) $this->get_slug() );
		foreach ( $submenu as $parent => $items ) {
			if ( ! is_array( $items ) ) {
				continue;
			}
			$found_key = null;
			$found     = null;
			foreach ( $items as $index => $item ) {
				if ( empty( $item[2] ) || ! is_string( $item[2] ) ) {
					continue;
				}
				$slug = strtolower( $item[2] );
				if ( $slug === $needle || ( strlen( $needle ) > 0 && strpos( $slug, $needle ) !== false ) ) {
					$found_key = $index;
					$found     = $item;
					break;
				}
			}
			if ( null === $found_key || null === $found ) {
				continue;
			}
			unset( $submenu[ $parent ][ $found_key ] );
			$submenu[ $parent ]   = array_values( $submenu[ $parent ] );
			$submenu[ $parent ][] = $found;
		}
	}

	public function get_menu_icon() {
		return 'gform-icon--cog';
	}

	/**
	 * Place this add-on’s Forms submenu entry last (with other late add-ons, e.g. Form Locator, Feed Queue).
	 *
	 * @param array $menu_items Items from gform_addon_navigation.
	 * @return array
	 */
	public function move_plugin_menu_to_bottom( $menu_items ) {
		if ( ! is_array( $menu_items ) || empty( $menu_items ) ) {
			return $menu_items;
		}
		$slug = is_callable( array( $this, 'get_slug' ) ) ? $this->get_slug() : $this->_slug;
		$ours = null;
		foreach ( $menu_items as $i => $item ) {
			if ( isset( $item['name'] ) && (string) $item['name'] === (string) $slug ) {
				$ours = $item;
				unset( $menu_items[ $i ] );
				break;
			}
		}
		if ( null === $ours ) {
			foreach ( $menu_items as $i => $item ) {
				if ( empty( $item['callback'] ) || ! is_array( $item['callback'] ) ) {
					continue;
				}
				if ( isset( $item['callback'][0] ) && $item['callback'][0] === $this ) {
					$ours = $item;
					unset( $menu_items[ $i ] );
					break;
				}
			}
		}
		if ( null === $ours ) {
			return $menu_items;
		}
		$menu_items   = array_values( $menu_items );
		$menu_items[] = $ours;
		return $menu_items;
	}

	public function plugin_page() {
		if ( ! GF_QuickReports_Reports::user_can_reports() ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'gf-quickreports' ) );
		}
		require GF_QUICKREPORTS_PLUGIN_DIR . 'templates/reports-page.php';
	}
}
