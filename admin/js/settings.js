(function(wp) {
	const {
		createElement,
		Component,
		Fragment,
	} = wp.element;
	const {
		ToggleControl,
		Button,
		TextControl,
		Panel,
		PanelBody,
		PanelRow,
		Disabled,
	} = wp.components;
	const { apiFetch } = wp;

	const settingsConfig = [
		{
			key: 'enableDebugging',
			label: 'Enable Debugging',
			type: 'toggle',
			help: 'Turn on debugging features',
		}, {
			key: 'globalDOMSearch',
			label: 'Global DOM Search',
			type: 'toggle',
			help: 'Provides the JS function globalSearch() in the browser console to search for certain attributes or values',
		}, {
			key: 'jsEventDebugger',
			label: 'JS Event Debugger',
			type: 'toggle',
			help: 'Logs all events to the browser console. Observes native- and jQuery-events',
			additionalFields: [
				{
					key: 'jsEventDebuggerIgnored',
					label: 'Ignored events (comma separated)',
					type: 'text',
					default: 'mousemove, message, keypress, keyup, keydown',
				},
			],
		}, {
			key: 'domWatcher',
			label: 'DOM Watcher',
			type: 'toggle',
			help: 'Logs all changes to the specified DOM elements to the browser; this includes creation of the element, attribute changes or updates to any child element',
			additionalFields: [
				{
					key: 'domWatcherPause',
					label: 'Pause on DOM changes',
					type: 'toggle',
					help: 'Adds a "debugger" breakpoint when a change is detected',
				}, {
					key: 'domWatcherSelectors',
					label: 'DOM Watcher Selectors (comma separated)',
					type: 'text',
				},
			],
		}, {
			key: 'mockApplePaySession',
			label: 'Mock ApplePaySession',
			type: 'toggle',
			help: 'This mock enables testing the Apple Pay button in non-Safari browsers',
			additionalFields: [
				{
					key: 'mockEmptyApplePayWallet',
					label: 'Simulate Empty Apple Wallet',
					type: 'toggle',
					help: 'Pretends that the Apple Wallet does not contain a valid payment method. Apple Pay buttons should stay hidden',
				},
			],
		},
	];

	class DebuggerSettings extends Component {
		constructor(props) {
			super(props);
			this.state = {
				...jsDebuggerSettings.options,
				isSaving: false,
				headerText: '',
			};

			this.updateSetting = this.updateSetting.bind(this);
			this.saveSettings = this.saveSettings.bind(this);
		}

		componentDidMount() {
			const h1Element = document.querySelector('.wrap > h1');
			if (h1Element) {
				this.setState({ headerText: h1Element.textContent });
				h1Element.style.display = 'none';
			}
		}

		updateSetting(setting, value) {
			this.setState({ [setting]: value });
		}

		async saveSettings() {
			this.setState({ isSaving: true });

			try {
				const dataToSave = {};
				settingsConfig.forEach(setting => {
					dataToSave[setting.key] = this.state[setting.key];
					if (setting.additionalFields) {
						setting.additionalFields.forEach(field => {
							let value;

							if ('text' === field.type) {
								value = this.state[field.key] || field.default || '';
							} else {
								value = !!this.state[field.key];
							}

							dataToSave[field.key] = value;
						});
					}
				});

				const response = await apiFetch({
					url: jsDebuggerSettings.restUrl,
					method: 'POST',
					data: dataToSave,
				});

				if (!response.success) {
					throw new Error('REST request failed');
				}
			} catch (error) {
				console.error('Error saving settings:', error);
				alert('Error saving settings. Please try again.');
			}

			this.setState({ isSaving: false });
		}

		renderField(field, isConditional = false) {
			const {
				key,
				label,
				type,
				help = '',
				default: defaultValue,
			} = field;
			let fieldComponent;

			switch (type) {
				case 'toggle':
					fieldComponent = createElement(ToggleControl, {
						label: label,
						help: help,
						checked: !!this.state[key] || defaultValue,
						onChange: (newValue) => this.updateSetting(key, newValue),
					});
					break;

				case 'text':
				default:
					fieldComponent = createElement(TextControl, {
						label: label,
						help: help,
						value: this.state[key] || defaultValue || '',
						onChange: (newValue) => this.updateSetting(key, newValue),
					});
					break;
			}

			if (isConditional) {
				fieldComponent = this.conditionallyDisable(fieldComponent);
			}

			return createElement(PanelRow, {}, fieldComponent);
		}

		renderSetting(setting, isConditional = false) {
			const {
				key,
				label,
				additionalFields,
			} = setting;
			const isEnabled = this.state[key];

			return createElement(
				PanelBody,
				{
					title: label,
					initialOpen: true,
				},
				this.renderField(setting, isConditional),
				isEnabled && additionalFields && additionalFields.map(field => this.renderField(field, isConditional)),
			);
		}

		conditionallyDisable(content) {
			const isDisabled = !this.state?.enableDebugging;

			return isDisabled ? createElement(Disabled, {}, content) : content;
		}

		render() {
			const [globalSetting, ...modulesSettings] = settingsConfig;

			return createElement(
				Panel,
				{ header: this.state.headerText },
				this.renderSetting(globalSetting),
				modulesSettings.map(setting => this.renderSetting(setting, true)),
				createElement(PanelBody, { initialOpen: true }, createElement(Button, {
					isPrimary: true,
					isBusy: this.state.isSaving,
					onClick: this.saveSettings,
				}, this.state.isSaving ? 'Saving...' : 'Save Settings')),
			);
		}
	}

	wp.domReady(function() {
		const container = document.getElementById('js-debugger-settings-container');
		if (container) {
			wp.element.render(createElement(DebuggerSettings), container);
		}
	});
})(window.wp);
