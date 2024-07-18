<?php
/**
 * @formatter:off
 * Plugin Name: JS Event Debugger
 * Plugin URI:  https://github.com/stracker-phil/wp-js-debugger
 * Description: While enabled, an additional JS snippet is injected into the page to log all events to the console. Observes native JS events and jQuery events.
 * Author:      Philipp Stracker (Syde)
 * Version:     1.0.2
 * @formatter:on
 */

namespace Syde\Debug;

/**
 * Returns a URL pointing to a file in this plugin.
 *
 * @param string $path File path, relative to this plugin.
 *
 * @return string Absolute URL to an asset in this plugin.
 */
function get_url( string $path ) : string {
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
 * Generates the debugging script that will be injected into the document.
 *
 * @return string Script tags to inject into the page header
 */
function get_debugging_script() : string {
	$output      = [];
	$script_urls = [];

	$script_urls['event-logs']    = get_url( 'js/event-logs.js' );
	$script_urls['global-search'] = get_url( 'js/global-search.js' );

	foreach ( $script_urls as $name => $url ) {
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
function insert_custom_js_after_jquery( string $content ) : string {
	$patterns = [
		// Front-end.
		'#<script[^>]+id=[\'"]jquery-core-js[\'"][^>]*></script>#i',

		// Admin.
		'#<script[^>]+src=[\'"][^>]*?/wp-admin/load-scripts\.php\?[^>]+?jquery-core[^>]*?[\'"][^>]*></script>#i',
	];

	foreach ( $patterns as $pattern ) {
		if ( preg_match( $pattern, $content ) ) {
			$custom_js = get_debugging_script();

			return preg_replace( $pattern, "$0\n$custom_js", $content );
		}
	}

	return $content;
}

/**
 * Checks, if the current request should receive JS debugging code.
 *
 * @return bool True, if debug script should be injected into the request.
 */
function should_insert_js_code() : bool {
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

add_filter( 'wp_loaded', function () {
	ob_start( function ( $buffer ) {
		if ( should_insert_js_code() ) {
			$buffer = insert_custom_js_after_jquery( $buffer );
		}

		return $buffer;
	} );
} );
