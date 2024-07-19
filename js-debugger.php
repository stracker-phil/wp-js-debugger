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

$optional_config = __DIR__ . '/config.local.php';
if ( file_exists( $optional_config ) ) {
	require_once $optional_config;
}
unset( $optional_config );

/**
 * Returns the value of the requested constant, or a default value
 *
 * @param string $const_name
 * @param mixed  $default
 *
 * @return mixed
 */
function get_config_val( string $const_name, $default = null ) {
	if ( defined( $const_name ) ) {
		return constant( $const_name );
	}

	return $default;
}

/**
 * Returns values for a single config item, from a const or a default value.
 *
 * @param string $const_name
 * @param array  $default
 *
 * @return string[] Config values
 */
function get_config_list( string $const_name, array $default = [] ) : array {
	$value = get_config_val( $const_name, $default );

	if ( is_string( $value ) ) {
		$value = explode( ',', $value );
	} elseif ( ! is_array( $value ) ) {
		$value = [];
	}

	return array_map( 'trim', $value );
}

/**
 * Collects config values and returns a single config object with all details.
 *
 * @return array
 */
function get_config() : array {
	return [
		'ignore_events'    => get_config_list( 'JS_DEBUG_IGNORE_EVENTS', [
			'mousemove',
			'message',
			'keypress',
			'keyup',
			'keydown',
		] ),
		'watch_elements'   => get_config_list( 'JS_DEBUG_WATCH_ELEMENTS' ),
		'wait_on_mutation' => (bool) get_config_val( 'JS_DEBUG_WAIT_ON_MUTATION', false ),
	];
}

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
 * Returns an inline-script with debug configuration.
 *
 * @return string
 */
function get_dynamic_config_script() : string {
	$config = get_config();

	return sprintf( '
		<script id="js-debugger-config">(function(API){
			API.ignoreEvents = %1$s;
			API.watchElements = %2$s;
			API.waitOnMutation = %3$s;
		})(window.JS_DEBUG = {});</script>',
		json_encode( $config['ignore_events'] ),
		json_encode( $config['watch_elements'] ),
		json_encode( $config['wait_on_mutation'] )
	);
}

/**
 * Returns the HTML code for debugging code that should be injected at the beginning
 * of the <head> tag.
 *
 * @return string Script tags to inject into the page header
 */
function get_debugging_script_no_deps() : string {
	return get_dynamic_config_script() . get_html_from_urls( [
			'global'        => get_url( 'js/global.js' ),
			'dom-watcher'   => get_url( 'js/dom-watcher.js' ),
			'event-logs'    => get_url( 'js/event-logs.js' ),
			'global-search' => get_url( 'js/global-search.js' ),
		] );
}

/**
 * Generates the debugging script that should be injected right after jQuery was loaded
 * but before any other script.
 *
 * @return string Script tags to inject into the page header
 */
function get_debugging_script_after_jquery() : string {
	return get_html_from_urls( [
		'event-logs-jquery' => get_url( 'js/event-logs-jquery.js' ),
	] );
}

/**
 * Generates full script tags from an array of script URLs.
 *
 * @param array $urls List of script URLs
 *
 * @return string Script tags to inject into the page header
 */
function get_html_from_urls( array $urls ) : string {
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
function insert_custom_js_after_jquery( string $content ) : string {
	$jquery_patterns = [
		// Front-end.
		'#<script[^>]+id=[\'"]jquery-core-js[\'"][^>]*></script>#i',

		// Admin.
		'#<script[^>]+src=[\'"][^>]*?/wp-admin/load-scripts\.php\?[^>]+?jquery-core[^>]*?[\'"][^>]*></script>#i',
	];

	$early_code = get_debugging_script_no_deps();
	$content    = preg_replace( '/<head[^>]*?>/', "$0\n$early_code", $content );

	foreach ( $jquery_patterns as $pattern ) {
		if ( preg_match( $pattern, $content ) ) {
			$jquery_code = get_debugging_script_after_jquery();

			$content = preg_replace( $pattern, "$0\n$jquery_code", $content );
			break;
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
