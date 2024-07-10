<?php
/**
 * Plugin Name: JS Event Debugger
 * Plugin URI:  https://github.com/stracker-phil/wp-js-debugger
 * Description: While enabled, an additional JS snippet is injected into the page to log all events to the console. Observes native JS events and jQuery events.
 * Author:      Philipp Stracker (Syde)
 * Version:     1.0.0
 */

namespace Syde\Debug;

function get_debugging_script() : string {
	return <<<EOD
<script>
(function() {
    window.ignoreEvents = ['mousemove', 'message', 'keypress', 'keyup', 'keydown'];

    function logEvent(action, source, eventName, target, handler, event, args) {
        if (window.ignoreEvents.includes(eventName)) {
            return;
        }

		const isDarkMode = window?.matchMedia('(prefers-color-scheme: dark)')?.matches;
		const colors = {
			source: {
				jQuery: isDarkMode ? '#0ee' : '#0aa',
				Native: isDarkMode ? '#e0e' : '#a0a',
			},
			action: {
				Add: isDarkMode ? '#8a8' : '#0a0',
				Remove: isDarkMode ? '#a88' : '#a00',
				Trigger: isDarkMode ? '#88a' : '#00a',
			},
		}

		const details = {
			target,
			handler,
			event,
		}
		if (args) {
			details.args = args;
		}

        console.log(
			`[%c\${action} %c\${source} %cEvent: %c\${eventName}%c] %o`,
			`color:\${colors.action[action]}`,
			`color:\${colors.source[source]}`,
			'',
			'font-weight:bold;',
			'',
			details
		);
    }

    // Store original methods
    var originalAddEventListener = EventTarget.prototype.addEventListener;
    var originalRemoveEventListener = EventTarget.prototype.removeEventListener;

    // WeakMap to store wrapped listeners
    var listenerMap = new WeakMap();

    // jQuery event logging
    (function($) {
        var originalOn = $.fn.on;
        var originalOff = $.fn.off;

        $.fn.on = function() {
            var args = Array.prototype.slice.call(arguments);
            var events, selector, data, handler;
            if (typeof args[0] === 'object') {
                events = args[0];
                selector = args[1];
                data = args[2];
                $.each(events, function(eventName, eventHandler) {
                    events[eventName] = wrapHandler(eventName, eventHandler);
                });
            } else {
                events = args[0];
                if (typeof args[1] === 'function') {
                    handler = args[1];
                } else {
                    selector = args[1];
                    if (typeof args[2] === 'function') {
                        handler = args[2];
                    } else {
                        data = args[2];
                        handler = args[3];
                    }
                }
                if (handler) {
                    args[args.length - 1] = wrapHandler(events, handler);
                }
            }
            return originalOn.apply(this, args);
        };

        $.fn.off = function() {
            var args = Array.prototype.slice.call(arguments);
            logEvent('Remove', 'jQuery', args[0] || 'all', this, null, args);
            return originalOff.apply(this, args);
        };

        function wrapHandler(eventName, originalHandler) {
            var wrappedHandler = function(event, ...params) {
                logEvent('Trigger', 'jQuery', eventName, this, originalHandler, event, params);
                return originalHandler.apply(this, arguments);
            };

            // Store reference to wrapped handler
            if (!listenerMap.has(originalHandler)) {
                listenerMap.set(originalHandler, new Map());
            }
            listenerMap.get(originalHandler).set(eventName, wrappedHandler);

			logEvent('Add', 'jQuery', eventName, this, originalHandler);
            return wrappedHandler;
        }
    })(jQuery);

    // Native event listener logging
    EventTarget.prototype.addEventListener = function(type, listener, options) {
        var wrappedListener = function(event) {
            logEvent('Trigger', 'Native', type, this, listener, event);
            return listener.apply(this, arguments);
        };

        // Store reference to wrapped listener
        if (!listenerMap.has(this)) {
            listenerMap.set(this, new Map());
        }
        listenerMap.get(this).set(listener, wrappedListener);

        logEvent('Add', 'Native', type, this, listener);
        return originalAddEventListener.call(this, type, wrappedListener, options);
    };

    EventTarget.prototype.removeEventListener = function(type, listener, options) {
        var wrappedListener = listenerMap.get(this)?.get(listener);
        if (wrappedListener) {
            logEvent('Remove', 'Native', type, this, listenerMap.get(this));
            listenerMap.get(this).delete(listener);
            return originalRemoveEventListener.call(this, type, wrappedListener, options);
        }
        return originalRemoveEventListener.call(this, type, listener, options);
    };

    console.log('Event debugging initialized. Ignored events:', window.ignoreEvents);
})();
</script>
EOD;
}

function insert_custom_js_after_jquery( $content ) {
	$custom_js = get_debugging_script();
	$pattern   = '/<script[^>]+id="jquery-core-js"[^>]*><\/script>/i';

	return preg_replace( $pattern, "$0\n$custom_js", $content );
}

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

add_filter( 'wp', function () {
	ob_start( function ( $buffer ) {
		if ( should_insert_js_code() ) {
			$buffer = insert_custom_js_after_jquery( $buffer );
		}

		return $buffer;
	} );
} );
