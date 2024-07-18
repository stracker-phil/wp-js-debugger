(function () {
	window.ignoreEvents = ['mousemove', 'message', 'keypress', 'keyup', 'keydown'];

	function logEvent(action, source, eventName, target, handler, event) {
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
			handler
		}

		let template = `[%c${action} %c${source} %cEvent: %c${eventName}%c] %o`;
		const templateArgs = [
			`color:${colors.action[action]}`,
			`color:${colors.source[source]}`,
			'',
			'font-weight:bold;',
			'',
			details
		];

		if (event) {
			template += '%o';
			templateArgs.push({event})
		}

		console.log(template, ...templateArgs);
	}

	// Store original methods
	const originalAddEventListener = EventTarget.prototype.addEventListener;
	const originalRemoveEventListener = EventTarget.prototype.removeEventListener;

	// WeakMap to store wrapped listeners
	const listenerMap = new WeakMap();

	// jQuery event logging
	(function ($) {
		const originalOn = $.fn.on;
		const originalOff = $.fn.off;

		$.fn.on = function () {
			const args = Array.prototype.slice.call(arguments);
			let events, handler;

			if (typeof args[0] === 'object') {
				events = args[0];
				$.each(events, function (eventName, eventHandler) {
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

		$.fn.off = function () {
			const args = Array.prototype.slice.call(arguments);

			logEvent('Remove', 'jQuery', args[0] || 'all', this);
			return originalOff.apply(this, args);
		};

		function wrapHandler(eventName, originalHandler) {
			const wrappedHandler = function (event, ...params) {
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
	})(window.jQuery);

	// Native event listener logging
	EventTarget.prototype.addEventListener = function (type, listener, options) {
		const wrappedListener = function (event) {
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

	EventTarget.prototype.removeEventListener = function (type, listener, options) {
		const wrappedListener = listenerMap.get(this)?.get(listener);

		if (wrappedListener) {
			logEvent('Remove', 'Native', type, this, listenerMap.get(this));
			listenerMap.get(this).delete(listener);
			return originalRemoveEventListener.call(this, type, wrappedListener, options);
		}
		return originalRemoveEventListener.call(this, type, listener, options);
	};

	console.log('Event debugging initialized. Ignored events:', window.ignoreEvents);
})();
