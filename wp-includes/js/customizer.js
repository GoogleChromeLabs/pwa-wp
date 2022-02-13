/* global PWA_Customizer_Data */

(function () {
	wp.customize.bind('ready', function () {
		let siteIcon = PWA_Customizer_Data.siteIcon;
		const isIconMaskable = PWA_Customizer_Data.maskableIcon;
		// Checkbox input.
		const maskableInput = document.getElementById(
			'_customize-input-pwa_maskable_icon'
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
			siteIcon = parseInt(this.iconId, 10);
			maskableInput.value = this.checked;
			maskableInput.checked = this.checked;

			if (!siteIcon) {
				wp.customize.control('pwa_maskable_icon').deactivate();
				return;
			}

			// At this point we are sure that icon is set, thus activate control.
			wp.customize.control('pwa_maskable_icon').activate();

			const iconPreview = document.querySelector('img.app-icon-preview');
			if (iconPreview) {
				iconPreview.style.clipPath =
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
						id && id === siteIcon ? isIconMaskable.checked : false,
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
			checked: isIconMaskable,
		});
	});
})();