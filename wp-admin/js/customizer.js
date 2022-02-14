/**
 * Add customizer controls for the PWA plugin.
 */
(function () {
	wp.customize.bind('ready', function () {
		const isIconMaskable = wp.customize('pwa_maskable_icon').get();
		const maskableInput = document.querySelector(
			'#_customize-input-pwa_maskable_icon'
		);

		/**
		 * Listens to icon update. This includes following scenarios.
		 *
		 * 1. Icon is removed.
		 * 2. Icon is updated.
		 * 3. Icon set as maskable.
		 * 4. Icon set as un-maskable.
		 */
		const iconUpdateListener = function () {
			const { isSet, makeFocused } = this;
			const iconPreview = document.querySelector('img.app-icon-preview');

			if (!isSet && !iconPreview) {
				wp.customize.control('pwa_maskable_icon').deactivate();
				return;
			}

			// At this point we are sure that icon is set, thus activate control.
			wp.customize.control('pwa_maskable_icon').activate();

			if (makeFocused) {
				wp.customize.control('pwa_maskable_icon').focus();
			}

			if (iconPreview) {
				iconPreview.style.clipPath =
					isSet && maskableInput.checked
						? 'inset(10% round 50%)'
						: '';
			}
		};

		/**
		 * Bind the icon change event.
		 */
		wp.customize(
			'site_icon',
			'pwa_maskable_icon',
			function (siteIcon, maskableIcon) {
				siteIcon.bind(function (id) {
					if (!id) {
						maskableIcon.set(false);
					}
					iconUpdateListener.call({
						isSet: id ? true : false,
						makeFocused: true,
					});
				});
			}
		);

		/**
		 * Bind the checkbox change event.
		 */
		wp.customize('pwa_maskable_icon', function (setting) {
			setting.bind(function (checked) {
				iconUpdateListener.call({
					isSet: checked ? true : false,
				});
			});
		});

		// Trigger the listener for the first time.
		iconUpdateListener.call({
			isSet: isIconMaskable ? true : false,
		});
	});
})();
