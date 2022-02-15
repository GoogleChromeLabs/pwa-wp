(function () {
	wp.customize('site_icon', (siteIconSetting) => {
		// Toggle site icon maskable active state based on whether the site icon is set.
		wp.customize.control(
			'site_icon_maskable',
			(siteIconMaskableControl) => {
				const updateActive = () => {
					const siteIconValue = siteIconSetting();
					siteIconMaskableControl.active(
						typeof siteIconValue === 'number' && siteIconValue > 0
					);
				};

				// Set initial active state.
				updateActive();

				// Update active state whenever the site_icon setting changes.
				siteIconSetting.bind(updateActive);
			}
		);
	});

	wp.customize.bind('ready', function () {
		let siteIcon = wp.customize('site_icon').get();

		/**
		 * Listens to icon update. This includes following scenarios.
		 *
		 * 1. Icon is removed.
		 * 2. Icon is updated.
		 * 3. Icon set as maskable.
		 * 4. Icon set as un-maskable.
		 */
		const iconUpdateListener = function () {
			siteIcon = parseInt(this.iconId, 10);

			// Check/uncheck maskable checkbox.
			wp.customize('site_icon_maskable').set(this.checked);

			if (!siteIcon) {
				return;
			}

			const iconPreview = document.querySelector('img.app-icon-preview');

			if (iconPreview) {
				document.querySelector('img.app-icon-preview').style.clipPath =
					this.checked && siteIcon ? 'inset(10% round 50%)' : '';
			}
		};

		/**
		 * Bind to the events when icon is updated or removed.
		 */
		wp.customize('site_icon', function (value) {
			value.bind(function (id) {
				iconUpdateListener.call({
					iconId: id,
					// If image is removed or changed, uncheck maskable checkbox.
					checked:
						id && id === siteIcon
							? wp.customize('site_icon_maskable').get()
							: false,
				});
			});
		});

		/**
		 * Bind the checkbox change event.
		 */
		wp.customize('site_icon_maskable', function (value) {
			value.bind(function (checked) {
				iconUpdateListener.call({
					iconId: siteIcon,
					checked,
				});
			});
		});

		// Trigger the listener for the first time.
		iconUpdateListener.call({
			iconId: siteIcon,
			checked: wp.customize('site_icon_maskable').get(),
		});
	});
})();
