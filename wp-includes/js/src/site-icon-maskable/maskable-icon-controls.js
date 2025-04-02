/**
 * Retrieves the translation of text.
 *
 * @see https://developer.wordpress.org/block-editor/packages/packages-i18n/
 */
import { __ } from '@wordpress/i18n';

/**
 * This module allows you to create and use standalone block editors. ;)
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/
 */
import { InspectorControls } from '@wordpress/block-editor';

/**
 * This package includes a library of generic WordPress components
 * to be used for creating common UI elements shared between screens
 * and features of the WordPress dashboard.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-components/
 */
import {
	Flex,
	FlexBlock,
	FlexItem,
	PanelBody,
	ToggleControl,
} from '@wordpress/components';

/**
 * Core Data is a data module intended to
 * simplify access to and manipulation
 * of core WordPress entities.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-core-data/
 */
import { store as coreStore, useEntityProp } from '@wordpress/core-data';

/**
 * WordPress’ data module serves as a hub
 * to manage application state
 * for both plugins and WordPress itself.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-data/
 */
import { useSelect } from '@wordpress/data';

export default function MaskableControls() {
	const [siteIconMaskable, setSiteIconMaskable] = useEntityProp(
		'root',
		'site',
		'site_icon_maskable'
	);

	// mainly borrowed from ...
	const { isRequestingSiteIcon, siteIconUrl } = useSelect((select) => {
		const { getEntityRecord, isResolving } = select(coreStore);
		const siteData =
			getEntityRecord('root', '__unstableBase', undefined) || {};

		return {
			isRequestingSiteIcon: isResolving('getEntityRecord', [
				'root',
				'__unstableBase',
				undefined,
			]),
			siteIconUrl: siteData.site_icon_url,
		};
	}, []);

	if (isRequestingSiteIcon) {
		return null;
	}

	const siteIconStyle = {
		clipPath: siteIconMaskable ? 'inset(10% round 50%)' : '',
		width: '64px',
	};

	let siteIcon = <div style={siteIconStyle} />;

	if (siteIconUrl) {
		siteIcon = (
			<img
				alt={__('Site Icon')}
				className="components-site-icon"
				src={siteIconUrl}
				width={64}
				height={64}
				style={siteIconStyle}
			/>
		);
	}

	return (
		<InspectorControls>
			<PanelBody>
				<Flex align="start">
					<FlexBlock>
						<ToggleControl
							label={__('Maskable icon', 'pwa')}
							help={__(
								'Maskable icons let your Progressive Web App use adaptive icons. If you supply a maskable icon, your icon can fill up the entire shape as an app- or homescreen-icon and will look great on all devices.',
								'pwa'
							)}
							onChange={setSiteIconMaskable}
							checked={siteIconMaskable}
						/>
					</FlexBlock>
					<FlexItem>{siteIcon}</FlexItem>
				</Flex>
			</PanelBody>
		</InspectorControls>
	);
}
