/* global PWA_Customizer_Data, console, document, wp */

(function() {

	wp.customize.bind('ready', function() {
		let siteIcon = PWA_Customizer_Data.siteIcon;
		// Checkbox input.
		const isMaskableIcon = document.getElementById( '_customize-input-pwa_maskable_icon' );

		/**
		 * Listens to icon update. This includes following scenarios.
		 *
		 * 1. Icon is removed.
		 * 2. Icon is updated.
		 * 3. Icon set as maskable.
		 * 4. Icon set as un-maskable.
		 */
		const iconUpdateListener = function() {
			siteIcon = this.iconId;
			isMaskableIcon.checked = this.checked;
			isMaskableIcon.value = this.checked;

			if( ! this.iconId ) {
				wp.customize.control( 'pwa_maskable_icon' ).deactivate();
				return;
			}

			document.querySelector('img.app-icon-preview').style.clipPath = this.checked && siteIcon ? 'inset(10% round 50%)' : '';
		};

		/**
		 * Bind to the events when icon is updated or removed.
		 */
		wp.customize( 'site_icon', function (value) {
			value.bind(function ( id ) {
				iconUpdateListener.call({
					iconId: id,
					checked: false,
				});
			});
		});

		/**
		 * Bind the checkbox change event.
		 */
		wp.customize( 'pwa_maskable_icon', function (value) {
			value.bind(function ( checked ) {
				iconUpdateListener.call({
					iconId: siteIcon,
					checked: checked,
				});
			});
		});

		// Trigger the listener for the first time.
		iconUpdateListener.call({
			iconId: siteIcon,
			checked: isMaskableIcon.checked,
		});
	});
})();
