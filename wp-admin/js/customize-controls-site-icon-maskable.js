wp.customize(
	'site_icon',
	'site_icon_maskable',
	(siteIconSetting, siteIconMaskableSetting) => {
		wp.customize.control(
			'site_icon',
			'site_icon_maskable',
			(siteIconControl, siteIconMaskableControl) => {
				/**
				 * Determine whether the site_icon setting has been set.
				 *
				 * @return {boolean} Whether set.
				 */
				const hasSiteIcon = () => {
					const siteIconValue = siteIconSetting();
					return (
						typeof siteIconValue === 'number' && siteIconValue > 0
					);
				};

				/**
				 * Toggle site icon maskable active state based on whether the site icon is set.
				 */
				const updateActive = () => {
					siteIconMaskableControl.active(hasSiteIcon());
				};

				// Set initial active state.
				updateActive();

				// Update active state whenever the site_icon setting changes.
				siteIconSetting.bind(updateActive);

				/**
				 * Update the icon styling based on whether the site icon maskable is enabled.
				 */
				const updateIconStyle = () => {
					siteIconControl.container
						.find('img.app-icon-preview')
						.css(
							'clipPath',
							siteIconMaskableSetting()
								? 'inset(10% round 50%)'
								: ''
						);
				};

				// Set initial style.
				updateIconStyle();

				// Update style whenever the site_icon or the site_icon_maskable changes.
				siteIconSetting.bind(updateIconStyle);
				siteIconMaskableSetting.bind(updateIconStyle);
			}
		);
	}
);
