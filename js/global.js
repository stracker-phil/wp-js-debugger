(function(API) {
	console.clear();

	const isDarkMode = window?.matchMedia('(prefers-color-scheme: dark)')?.matches;

	API.theme = {
		event: {
			action: {
				Add: isDarkMode ? '#8a8' : '#0a0',
				Remove: isDarkMode ? '#a88' : '#a00',
				Trigger: isDarkMode ? '#88a' : '#00a',
			},
			type: {
				jQuery: isDarkMode ? '#00eeee' : '#008b8b',
				Native: isDarkMode ? '#e0e' : '#a0a',
			},
		},
		mutation: {
			action: {
				Add: isDarkMode ? '#8a8' : '#0a0',
				Remove: isDarkMode ? '#a88' : '#a00',
				Change: isDarkMode ? '#88a' : '#00a',
			},
			type: {
				Child: isDarkMode ? '#efb500' : '#b78700',
				Attr: isDarkMode ? '#df14c3' : '#a3008f',
				Content: isDarkMode ? '#00eeee' : '#008b8b',
			},
		},
	};

	API.logEvent = function logEvent(action, type, eventName, target, handler, event) {
		if (API.ignoreEvents.includes(eventName)) {
			return;
		}

		const details = {
			target,
			handler,
		};

		let template = `[%c${action} %c${type} %cEvent: %c${eventName}%c] %o`;
		const templateArgs = [
			`color:${API.theme.event.action[action]}`,
			`color:${API.theme.event.type[type]}`,
			'',
			'font-weight:bold;',
			'',
			details,
		];

		if (event) {
			template += '%o';
			templateArgs.push({ event });
		}

		console.log(template, ...templateArgs);
	};

	API.logMutation = function logMutation(action, type, selector, node, details) {
		if (!node) {
			return;
		}

		let template = `[%c${action} %c${type} %cMutation: %c${selector}%c] %o`;
		const templateArgs = [
			`color:${API.theme.mutation.action[action]}`,
			`color:${API.theme.mutation.type[type]}`,
			'',
			'font-weight:bold;',
			'',
			node,
		];

		if (details) {
			template += '%o';
			templateArgs.push(details);
		}

		console.log(template, ...templateArgs);

	};
})(window.JS_DEBUG = window.JS_DEBUG || {});
