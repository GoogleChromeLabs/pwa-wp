/**
 * The 'icon maskable' control allows to set the 'site_icon_maskable' option.
 *
 * It also provides a small preview of the image used as Site-Logo,
 * using a cropping-preview to illustrate the safe space an logo needs
 * to be an adaptive image.
 */
import MaskableControls from './maskable-icon-controls';

/**
 * The compose package is a collection
 * of handy Hooks and Higher Order Components (HOCs)
 * you can use to wrap your WordPress components
 * and provide some basic features like: state, instance id, pure…
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-compose/
 */
import { createHigherOrderComponent } from '@wordpress/compose';

const withMaskableControls = createHigherOrderComponent((BlockEdit) => {
	return (props) => {
		if (props.name !== 'core/site-logo') {
			return <BlockEdit {...props} />;
		}

		return (
			<>
				<BlockEdit {...props} />
				<MaskableControls />
			</>
		);
	};
}, 'withMaskableControls');

/**
 * To modify the behavior of existing blocks,
 * WordPress exposes several APIs.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/filters/block-filters/
 */
wp.hooks.addFilter(
	'editor.BlockEdit',
	'pwa/with-maskable-icon-controls',
	withMaskableControls
);
