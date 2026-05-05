<?php
/**
 * Plugin Name: Fanpage Insight Sync
 * Description: Syncs fanpage insights from Google Sheets and analyzes insight images with AI.
 * Version: 1.0.0
 * Author: DPS Media
 * License: GPL2
 * Text Domain: fanpage-insight-sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define constants
define( 'FPIS_VERSION', '1.0.0' );
define( 'FPIS_PATH', plugin_dir_path( __FILE__ ) );
define( 'FPIS_URL', plugin_dir_url( __FILE__ ) );

/**
 * The core plugin class.
 */
class Fanpage_Insight_Sync {

	public function __construct() {
		$this->load_dependencies();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	private function load_dependencies() {
		require_once FPIS_PATH . 'includes/class-fpis-db.php';
		require_once FPIS_PATH . 'includes/class-fpis-sync.php';
		require_once FPIS_PATH . 'includes/class-fpis-queue.php';
		require_once FPIS_PATH . 'includes/class-fpis-ai.php';
		require_once FPIS_PATH . 'includes/class-fpis-shortcode.php';

		if ( is_admin() ) {
			require_once FPIS_PATH . 'admin/class-fpis-admin.php';
		}
	}

	private function define_admin_hooks() {
		if ( is_admin() ) {
			$admin = new FPIS_Admin();
			add_action( 'admin_menu', [ $admin, 'add_plugin_admin_menu' ] );
			add_action( 'admin_init', [ $admin, 'register_settings' ] );
		}
	}

	private function define_public_hooks() {
		$shortcode = new FPIS_Shortcode();
		add_shortcode( 'fanpage_insights', [ $shortcode, 'render_shortcode' ] );
		add_action( 'rest_api_init', [ $shortcode, 'register_routes' ] );
		
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	public function enqueue_assets() {
		wp_enqueue_style( 'fpis-frontend', FPIS_URL . 'assets/fpis-frontend.css', [], FPIS_VERSION );
		wp_enqueue_script( 'fpis-frontend', FPIS_URL . 'assets/fpis-frontend.js', [ 'jquery' ], FPIS_VERSION, true );
		
		wp_localize_script( 'fpis-frontend', 'fpisData', [
			'ajax_url'     => admin_url( 'admin-ajax.php' ),
			'rest_url'     => get_rest_url( null, 'fpis/v1' ),
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'is_logged_in' => is_user_logged_in(),
			'zalo_url'     => get_option( 'fpis_contact_zalo', '#' ),
		] );
	}
}

/**
 * Activation Hook
 */
function activate_fpis() {
	require_once FPIS_PATH . 'includes/class-fpis-activator.php';
	FPIS_Activator::activate();
}
register_activation_hook( __FILE__, 'activate_fpis' );

/**
 * Initialization
 */
function run_fpis() {
	new Fanpage_Insight_Sync();
}
add_action( 'plugins_loaded', 'run_fpis' );
