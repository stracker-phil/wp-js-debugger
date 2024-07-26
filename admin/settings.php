<?php
/**
 * Provides the WordPress settings page for this plugin
 */

namespace Syde\Debug\Admin;

use WP_REST_Response;
use WP_REST_Request;

/**
 * Manages the settings page.
 */
class Settings {
	/**
	 * Constructor: Sets up WordPress hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'rest_api_init', [ $this, 'register_rest_route' ] );
	}

	/**
	 * Adds the JS Debugger menu item to the WordPress admin menu.
	 */
	public function add_admin_menu() {
		add_menu_page(
			'JS Debugger Settings',
			'JS Debugger',
			'manage_options',
			'js-debugger-settings',
			[ $this, 'render_settings_page' ],
			'dashicons-flag',
			100
		);
	}

	/**
	 * Enqueues necessary scripts and styles for the settings page.
	 *
	 * @param string $hook The current admin page.
	 */
	public function enqueue_scripts( $hook ) {
		if ( $hook !== 'toplevel_page_js-debugger-settings' ) {
			return;
		}

		wp_enqueue_script( 'wp-element' );
		wp_enqueue_script( 'wp-components' );
		wp_enqueue_script( 'wp-api-fetch' );
		wp_enqueue_style( 'wp-components' );

		$script_url  = plugin_dir_url( __FILE__ ) . 'js/settings.js';
		$script_path = plugin_dir_path( __FILE__ ) . 'js/settings.js';

		wp_enqueue_script(
			'js-debugger-settings',
			$script_url,
			[ 'wp-element', 'wp-components', 'wp-api-fetch' ],
			filemtime( $script_path ),
			true
		);

		// Localize the script with new data
		$js_debug_options = self::get_options();

		wp_localize_script( 'js-debugger-settings', 'jsDebuggerSettings', [
			'options' => $js_debug_options,
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'restUrl' => esc_url_raw( rest_url( 'js-debugger/v1/save-settings' ) ),
		] );
	}

	/**
	 * Registers the settings.
	 */
	public function register_settings() {
		register_setting( 'js_debugger', 'js_debug_options' );
	}

	/**
	 * Registers the REST API route for saving options.
	 */
	public function register_rest_route() {
		register_rest_route( 'js-debugger/v1', '/save-settings', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'save_settings' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		] );
	}

	/**
	 * Saves the settings via the REST API.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return WP_REST_Response The REST response object.
	 */
	public function save_settings( WP_REST_Request $request ) : WP_REST_Response {
		$options = $request->get_json_params();
		update_option( 'js_debug_options', $options );

		return new WP_REST_Response( [ 'success' => true ] );
	}

	/**
	 * Renders the settings page HTML.
	 */
	public function render_settings_page() {
		?>
		<div class="wrap">
			<h1>JS Debugger Settings</h1>
			<div id="js-debugger-settings-container"></div>
		</div>
		<?php
	}

	/**
	 * Static getter to access the saved settings from other PHP modules.
	 *
	 * @return array The saved options or default values if not set.
	 */
	public static function get_options() : array {
		return get_option( 'js_debug_options', [
			'enableDebugging'         => false,
			'globalDOMSearch'         => false,
			'jsEventDebugger'         => false,
			'jsEventDebuggerIgnored'  => 'mousemove, message, keypress, keyup, keydown',
			'domWatcher'              => false,
			'domWatcherSelectors'     => '',
			'mockApplePaySession'     => false,
			'mockEmptyApplePayWallet' => false,
		] );
	}
}

// Initialize the settings page
new Settings();
