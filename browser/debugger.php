<?php

namespace Syde\Debug\Browser;

use Syde\Debug\Admin\Settings;

/**
 * Modifies the HTML code to inject relevant debug modules.
 */
class Debugger {
	private $settings;

	public function __construct( array $settings ) {
		$this->settings = $settings;

		if ( $this->settings['enableDebugging'] ) {
			add_filter( 'wp_loaded', [ $this, 'init_output_buffer' ] );
		}
	}

	/**
	 * Initializes the output buffer to process the page content.
	 */
	public function init_output_buffer() {
		ob_start( [ $this, 'process_output' ] );
	}

	/**
	 * Processes the output buffer, inserting custom JS if necessary.
	 *
	 * @param string $buffer The contents of the output buffer.
	 *
	 * @return string The processed buffer contents.
	 */
	public function process_output( string $buffer ) : string {
		if ( $this->should_insert_js_code() ) {
			$buffer = $this->insert_custom_js_after_jquery( $buffer );
		}

		return $buffer;
	}

	/**
	 * Collects config values and returns a single config object with all details.
	 *
	 * @return array The configuration array.
	 */
	private function get_config() : array {
		return [
			'ignoreEvents'     => array_map( 'trim', explode( ',', $this->settings['jsEventDebuggerIgnored'] ) ),
			'watchElements'    => array_map( 'trim', explode( ',', $this->settings['domWatcherSelectors'] ) ),
			'waitOnMutation'   => (bool) $this->settings['domWatcherPause'],
			'emptyAppleWallet' => (bool) $this->settings['mockEmptyApplePayWallet'],
		];
	}

	/**
	 * Returns a URL pointing to a file in this plugin.
	 *
	 * @param string $path File path, relative to this plugin.
	 *
	 * @return string Absolute URL to an asset in this plugin.
	 */
	private function get_url( string $path ) : string {
		$plugin_path = plugin_dir_path( __FILE__ );
		$plugin_url  = plugin_dir_url( __FILE__ );
		$full_path   = $plugin_path . $path;

		if ( ! file_exists( $full_path ) ) {
			return '';
		}

		$full_url = $plugin_url . str_replace( '\\', '/', $path );

		return add_query_arg(
			[
				'v' => filemtime( $full_path ),
			],
			$full_url
		);
	}

	/**
	 * Returns an inline-script with debug configuration.
	 *
	 * @return string The script tag containing the debug configuration.
	 */
	private function get_dynamic_config_script() : string {
		return sprintf(
			'<script id="js-debugger-config">window.JS_DEBUG_CONFIG = %s;</script>',
			json_encode( $this->get_config() )
		);
	}

	/**
	 * Returns a style tag with custom CSS that helps with debugging.
	 *
	 * @return string
	 */
	private function get_custom_debugging_styles() : string {
		$rules = [];

		if ( $this->settings['mockApplePaySession'] ) {
			// Fix the Apple Pay font in non-Safari browsers.
			$rules[] = "@font-face {
			font-family: '-apple-system';
			src: local('-apple-system'),local('BlinkMacSystemFont'),local('Segoe UI'),local('Roboto'),local('Roboto-Regular'),local('Helvetica Neue'),local('Arial');
			unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+2000-206F, U+2074, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
			}";
		}

		return sprintf( '<style>%s</style>', implode( "\n", $rules ) );
	}

	/**
	 * Returns the HTML code for debugging code that should be injected at the beginning
	 * of the <head> tag.
	 *
	 * @return string Script tags to inject into the page header
	 */
	private function get_debugging_script_no_deps() : string {
		$scripts = [
			'global' => $this->get_url( 'js/global.js' ),
		];

		if ( $this->settings['domWatcher'] ) {
			$scripts['dom-watcher'] = $this->get_url( 'js/dom-watcher.js' );
		}

		if ( $this->settings['jsEventDebugger'] ) {
			$scripts['event-logs'] = $this->get_url( 'js/event-logs.js' );
		}

		if ( $this->settings['globalDOMSearch'] ) {
			$scripts['global-search'] = $this->get_url( 'js/global-search.js' );
		}

		if ( $this->settings['mockApplePaySession'] ) {
			$scripts['mock-applepaysession'] = $this->get_url( 'js/mock-applepaysession.js' );
		}

		return $this->get_dynamic_config_script() . $this->get_html_from_urls( $scripts ) . $this->get_custom_debugging_styles();
	}

	/**
	 * Generates the debugging script that should be injected right after jQuery was loaded
	 * but before any other script.
	 *
	 * @return string Script tags to inject into the page header
	 */
	private function get_debugging_script_after_jquery() : string {
		$scripts = [];

		if ( $this->settings['jsEventDebugger'] ) {
			$scripts['event-logs-jquery'] = $this->get_url( 'js/event-logs-jquery.js' );
		}

		return $this->get_html_from_urls( $scripts );
	}

	/**
	 * Generates full script tags from an array of script URLs.
	 *
	 * @param array $urls List of script URLs
	 *
	 * @return string Script tags to inject into the page header
	 */
	private function get_html_from_urls( array $urls ) : string {
		$output = [];

		foreach ( $urls as $name => $url ) {
			if ( ! $url ) {
				continue;
			}

			$output[] = sprintf(
				'<script src="%2$s" id="js-debugger-%1$s"></script>',
				esc_attr( $name ),
				esc_url( $url )
			);
		}

		return join( '', $output );
	}

	/**
	 * Injects the debug script code into the provided content (an HTML string).
	 *
	 * The debug script must be inserted after jQuery, as it overrides some core jQuery methods.
	 * We use regex patterns to detect the location of the jquery-core script and inject the debug
	 * script JS snippet directly afterward.
	 *
	 * @param string $content HTML code of the document to modify.
	 *
	 * @return string Modified content
	 */
	private function insert_custom_js_after_jquery( string $content ) : string {
		$jquery_patterns = [
			// Front-end.
			'#<script[^>]+id=[\'"]jquery-core-js[\'"][^>]*></script>#i',

			// Admin.
			'#<script[^>]+src=[\'"][^>]*?/wp-admin/load-scripts\.php\?[^>]+?jquery-core[^>]*?[\'"][^>]*></script>#i',
		];

		$early_code = $this->get_debugging_script_no_deps();
		$content    = preg_replace( '/<head[^>]*?>/', "$0\n$early_code", $content );

		foreach ( $jquery_patterns as $pattern ) {
			if ( preg_match( $pattern, $content ) ) {
				$jquery_code = $this->get_debugging_script_after_jquery();

				$content = preg_replace( $pattern, "$0\n$jquery_code", $content );
				break;
			}
		}

		return $content;
	}

	/**
	 * Checks if the current request should receive JS debugging code.
	 *
	 * @return bool True if debug script should be injected into the request, false otherwise.
	 */
	private function should_insert_js_code() : bool {
		// Check if the request accepts HTML
		$accept_header = $_SERVER['HTTP_ACCEPT'] ?? '';
		if ( strpos( $accept_header, 'text/html' ) === false ) {
			return false;
		}

		// Check if the response is HTML
		$content_type = '';
		foreach ( headers_list() as $header ) {
			if ( stripos( $header, 'Content-Type:' ) === 0 ) {
				$content_type = $header;
				break;
			}
		}
		if ( stripos( $content_type, 'text/html' ) === false ) {
			return false;
		}

		return true;
	}
}

// Initialize the debugger only if debugging is enabled
add_action( 'plugins_loaded', static function () : void {
	$settings = Settings::get_options();

	new Debugger( $settings );
} );
