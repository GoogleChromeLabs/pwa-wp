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
				 * Validate site icons for its presence and size.
				 */
				const validateIcon = () => {
					const attachmentId = parseInt(siteIconSetting(), 10);

					const iconMissingNotificationId = 'pwa_icon_not_set';
					const iconTooSmallNotificationId = 'pwa_icon_too_small';

					const addMissingIconNotification = () => {
						siteIconControl.notifications.add(
							new wp.customize.Notification(
								iconMissingNotificationId,
								{
									type: 'warning',
									message: wp.customize.l10n.pwa_icon_not_set,
								}
							)
						);
					};

					if (!attachmentId) {
						addMissingIconNotification();
					} else {
						siteIconControl.notifications.remove(
							iconMissingNotificationId
						);

						wp.media
							.attachment(attachmentId)
							.fetch()
							.fail(addMissingIconNotification)
							.done((attachment) => {
								if (
									attachment.width >= 512 &&
									attachment.height >= 512
								) {
									siteIconControl.notifications.remove(
										iconTooSmallNotificationId
									);
								} else {
									siteIconControl.notifications.add(
										new wp.customize.Notification(
											iconTooSmallNotificationId,
											{
												type: 'warning',
												message:
													wp.customize.l10n
														.pwa_icon_too_small,
											}
										)
									);
								}
							});
					}
				};

				// Set initial state.
				validateIcon();

				// Update notification when site_icon setting changes.
				siteIconSetting.bind(validateIcon);

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
