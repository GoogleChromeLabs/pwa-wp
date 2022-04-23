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

				// Change the site_icon_maskable if site_icon is not set.
				siteIconSetting.bind((newSiteIconValue) => {
					if (!newSiteIconValue) {
						siteIconMaskableSetting(false);
					}
				});

				/**
				 * Validate site icons for its presence and size.
				 */
				const validateIcon = () => {
					const attachmentId = parseInt(siteIconSetting(), 10);

					const iconMissingNotificationId = 'pwa_icon_not_set';
					const iconTooSmallNotificationId = 'pwa_icon_too_small';
					const iconNotSquareNotificationId = 'pwa_icon_not_square';
					const iconNotPngNotificationId = 'pwa_icon_not_png';

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
						siteIconControl.notifications.remove(
							iconTooSmallNotificationId
						);
						siteIconControl.notifications.remove(
							iconNotSquareNotificationId
						);
						siteIconControl.notifications.remove(
							iconNotPngNotificationId
						);
					} else {
						siteIconControl.notifications.remove(
							iconMissingNotificationId
						);

						wp.media
							.attachment(attachmentId)
							.fetch()
							.fail(addMissingIconNotification)
							.done((attachment) => {
								// Check for size.
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

								// Check for square icon per <https://github.com/GoogleChrome/lighthouse/blob/0fb3206/lighthouse-core/lib/icons.js#L63-L64>.
								if (attachment.width !== attachment.height) {
									siteIconControl.notifications.add(
										new wp.customize.Notification(
											iconNotSquareNotificationId,
											{
												type: 'warning',
												message:
													wp.customize.l10n
														.pwa_icon_not_square,
											}
										)
									);
								} else {
									siteIconControl.notifications.remove(
										iconNotSquareNotificationId
									);
								}

								// Check for PNG.
								if ('image/png' !== attachment.mime) {
									siteIconControl.notifications.add(
										new wp.customize.Notification(
											iconNotPngNotificationId,
											{
												type: 'warning',
												message:
													wp.customize.l10n
														.pwa_icon_not_png,
											}
										)
									);
								} else {
									siteIconControl.notifications.remove(
										iconNotPngNotificationId
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
