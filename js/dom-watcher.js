(function(API) {
	function getMatchingSelectors(node, exact = false) {
		if (!(node instanceof Element)) {
			return [];
		}
		return API.watchElements.filter(selector => exact
			? node.matches(selector)
			: (node.matches(selector) || node.querySelector(selector)));
	}

	function logMutationsForNodes(nodes, action, type, getSelectors) {
		nodes.forEach(node => {
			getSelectors(node).forEach(selector => {
				API.logMutation(action, type, selector, node);

				if (API.waitOnMutation) {
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
					logMutationsForNodes([mutation.target], 'Change', 'Attr', node => getMatchingSelectors(node, true));
					break;

				case 'characterData':
					if (mutation.target.parentNode) {
						logMutationsForNodes(
							[mutation.target.parentNode],
							'Change',
							'Content',
							node => getMatchingSelectors(node, true),
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

	console.log('Debug script initialized. Watching for mutations on:', API.watchElements);
})(window.JS_DEBUG);
