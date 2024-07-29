(function(API) {
	if (API._initialized.event_logger) {
		return;
	}
	API._initialized.event_logger = true;

	// Store original methods
	const originalAddEventListener = EventTarget.prototype.addEventListener;
	const originalRemoveEventListener = EventTarget.prototype.removeEventListener;

	// WeakMap to store wrapped listeners
	const listenerMap = new WeakMap();

	// Native event listener logging
	EventTarget.prototype.addEventListener = function(type, listener, options) {
		const wrappedListener = function(event) {
			API.logEvent('Trigger', 'Native', type, this, listener, event);
			return listener.apply(this, arguments);
		};

		// Store reference to wrapped listener
		if (!listenerMap.has(this)) {
			listenerMap.set(this, new Map());
		}
		listenerMap.get(this).set(listener, wrappedListener);

		API.logEvent('Add', 'Native', type, this, listener);
		return originalAddEventListener.call(this, type, wrappedListener, options);
	};

	EventTarget.prototype.removeEventListener = function(type, listener, options) {
		const wrappedListener = listenerMap.get(this)?.get(listener);

		if (wrappedListener) {
			API.logEvent('Remove', 'Native', type, this, listenerMap.get(this));
			listenerMap.get(this).delete(listener);
			return originalRemoveEventListener.call(this, type, wrappedListener, options);
		}
		return originalRemoveEventListener.call(this, type, listener, options);
	};

	console.info('Event debugging initialized. Ignored events:', API.config.ignoreEvents);
})(window.JS_DEBUG);
