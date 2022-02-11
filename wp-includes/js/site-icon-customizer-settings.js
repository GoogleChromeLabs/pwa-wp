(() => {
	wp.customize.bind('ready', () => {
		/*  eslint-disable no-undef */
		const maskableIconChecked = pwa_maskable_icon_data.maskable_icon;
		/* eslint-enable no-undef */
		const isMaskableIcon = document.getElementById(
			'_customize-input-pwa_maskable_icon'
		);
		let siteIcon = document.querySelector('img.app-icon-preview');

		wp.customize('site_icon', function (value) {
			value.bind(function (to) {
				if (!to) {
					isMaskableIcon.checked = false;
					isMaskableIcon.disabled = true;
					isMaskableIcon.value = false;
				} else {
					isMaskableIcon.disabled = false;
					isMaskableIcon.value = true;
					isMaskableIcon.checked = true;
					siteIcon = document.querySelector('img.app-icon-preview');
				}
			});
		});

		wp.customize('pwa_maskable_icon', function (value) {
			if (!siteIcon) {
				isMaskableIcon.checked = false;
				isMaskableIcon.disabled = true;
				isMaskableIcon.value = false;
			}
			if (maskableIconChecked && siteIcon) {
				siteIcon.style.clipPath = 'inset(10% round 50%)';
			}
			value.bind(function (to) {
				if (siteIcon) {
					isMaskableIcon.addEventListener('change', function () {
						if (to) {
							siteIcon.style.clipPath = 'inset(10% round 50%)';
						} else {
							siteIcon.style.clipPath = '';
						}
					});
				}
			});
		});
	});
})();
