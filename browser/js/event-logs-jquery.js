(function(API, $) {
	if (API._initialized.event_logger_jquery) {
		return;
	}
	API._initialized.event_logger_jquery = true;

	// WeakMap to store wrapped listeners
	const listenerMap = new WeakMap();

	const originalOn = $.fn.on;
	const originalOff = $.fn.off;

	$.fn.on = function() {
		const args = Array.prototype.slice.call(arguments);
		let events, handler;

		if (typeof args[0] === 'object') {
			events = args[0];
			$.each(events, function(eventName, eventHandler) {
				events[eventName] = wrapHandler(eventName, eventHandler);
			});
		} else {
			events = args[0];
			if (typeof args[1] === 'function') {
				handler = args[1];
			} else {
				if (typeof args[2] === 'function') {
					handler = args[2];
				} else {
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
		const args = Array.prototype.slice.call(arguments);

		API.logEvent('Remove', 'jQuery', args[0] || 'all', this);
		return originalOff.apply(this, args);
	};

	function wrapHandler(eventName, originalHandler) {
		const wrappedHandler = function(event, ...params) {
			API.logEvent('Trigger', 'jQuery', eventName, this, originalHandler, event, params);
			return originalHandler.apply(this, arguments);
		};

		// Store reference to wrapped handler
		if (!listenerMap.has(originalHandler)) {
			listenerMap.set(originalHandler, new Map());
		}
		listenerMap.get(originalHandler).set(eventName, wrappedHandler);

		API.logEvent('Add', 'jQuery', eventName, this, originalHandler);
		return wrappedHandler;
	}
})(window.JS_DEBUG, window.jQuery);
