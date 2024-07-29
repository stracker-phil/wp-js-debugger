(function(API) {
	if (API._initialized.dom_watcher) {
		return;
	}
	API._initialized.dom_watcher = true;

	function getMatchingSelectors(node, exact = false) {
		if (!(node instanceof Element)) {
			return [];
		}
		return API.config.watchElements.filter(selector => {
			if (exact) {
				return node.matches(selector);
			} else {
				if (node.matches(selector)) {
					return true;
				}
				if (node.querySelector(selector)) {
					return true;
				}
				let parent = node.parentElement;
				while (parent) {
					if (parent.matches(selector)) {
						return true;
					}
					parent = parent.parentElement;
				}
				return false;
			}
		});
	}

	function logMutationsForNodes(nodes, action, type, getSelectors, changeInfo = null) {
		nodes.forEach(node => {
			getSelectors(node).forEach(selector => {
				API.logMutation(action, type, selector, node, changeInfo);

				if (API.config.waitOnMutation) {
					debugger;
				}
			});
		});
	}

	const observer = new MutationObserver((mutations) => {
		mutations.forEach(mutation => {
			switch (mutation.type) {
				case 'childList':
					logMutationsForNodes(mutation.addedNodes, 'Add', 'Child', node => getMatchingSelectors(node));
					logMutationsForNodes(mutation.removedNodes, 'Remove', 'Child', node => getMatchingSelectors(node));
					break;

				case 'attributes':
					logMutationsForNodes([mutation.target],
						'Change',
						'Attr',
						node => getMatchingSelectors(node, true),
						{
							[mutation.attributeName]: mutation.target.getAttribute(mutation.attributeName),
						},
					);
					break;

				case 'characterData':
					if (mutation.target.parentNode) {
						logMutationsForNodes([mutation.target.parentNode],
							'Change',
							'Content',
							node => getMatchingSelectors(node, true),
							{ newContent: mutation.target.textContent },
						);
					}
					break;
			}
		});
	});

	observer.observe(document.documentElement, {
		childList: true,
		subtree: true,
		attributes: true,
		characterData: true,
	});

	console.info('Debug script initialized. Watching for mutations on:', API.config.watchElements);
})(window.JS_DEBUG);
