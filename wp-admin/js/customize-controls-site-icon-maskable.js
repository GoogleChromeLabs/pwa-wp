/*globals PWA_IconMessages*/
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

				/**
				 * Validate site icons for its presence and size.
				 */
				const iconValidation = () => {
					const iconData = siteIconControl.container.find(
						'img.app-icon-preview'
					);
					const baseNotificationProps = {
						dismissible: true,
						message: '',
						type: 'warning',
						code: null,
					};
					const notifications = [];

					if (!iconData.length) {
						notifications.push(
							new wp.customize.Notification('pwa_icon_not_set', {
								...baseNotificationProps,
								message: PWA_IconMessages.pwa_icon_not_set,
							})
						);
					}

					if (
						iconData.length &&
						(iconData[0].naturalHeight < 512 ||
							iconData[0].naturalHeight < 512)
					) {
						notifications.push(
							new wp.customize.Notification(
								'pwa_icon_too_small',
								{
									...baseNotificationProps,
									message:
										PWA_IconMessages.pwa_icon_too_small,
								}
							)
						);
					}

					notifications.map((notification) => {
						wp.customize
							.section('title_tagline')
							.notifications.add(notification);

						return notification;
					});
				};

				// Update active state whenever the site_icon setting changes.
				// Update notification when site_icon setting changes.
				siteIconSetting.bind(updateActive).bind(iconValidation);

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
