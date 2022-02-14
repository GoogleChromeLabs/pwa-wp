(function () {
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
			wp.customize('pwa_maskable_icon').set(this.checked);

			if (!siteIcon) {
				//alert( wp.customize.control( 'pwa_maskable_icon' ) );
				wp.customize.control('pwa_maskable_icon').deactivate();
				return;
			}

			// At this point we are sure that icon is set, thus activate control.
			wp.customize.control('pwa_maskable_icon').activate();

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
							? wp.customize('pwa_maskable_icon').get()
							: false,
				});
			});
		});

		/**
		 * Bind the checkbox change event.
		 */
		wp.customize('pwa_maskable_icon', function (value) {
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
			checked: wp.customize('pwa_maskable_icon').get(),
		});
	});
})();
