/**
 * Spacing preset constants
 *
 * Adapted from Kadence Blocks (GPL v2+)
 * Original: https://github.com/stellarwp/kadence-blocks
 *
 * DriveHR customizations:
 * - Uses WordPress theme spacing scale
 * - Simplified preset values
 * - Matches WordPress core spacing system
 *
 * @package DriveHR
 * @since 1.8.5
 */

import { __ } from '@wordpress/i18n';

/**
 * Spacing preset options for visual range control
 *
 * Each option defines a spacing size with:
 * - value: Internal identifier
 * - label: Short display label
 * - size: Pixel size for slider positioning
 * - name: Full descriptive name
 */
export const SPACING_PRESETS = [
	{
		value: '0',
		label: __( 'None', 'drivehr-webhook' ),
		size: 0,
		name: __( 'None', 'drivehr-webhook' ),
	},
	{
		value: 'xs',
		size: 8,
		label: __( 'XS', 'drivehr-webhook' ),
		name: __( 'X Small', 'drivehr-webhook' ),
	},
	{
		value: 'sm',
		size: 16,
		label: __( 'SM', 'drivehr-webhook' ),
		name: __( 'Small', 'drivehr-webhook' ),
	},
	{
		value: 'md',
		size: 24,
		label: __( 'MD', 'drivehr-webhook' ),
		name: __( 'Medium', 'drivehr-webhook' ),
	},
	{
		value: 'lg',
		size: 32,
		label: __( 'LG', 'drivehr-webhook' ),
		name: __( 'Large', 'drivehr-webhook' ),
	},
	{
		value: 'xl',
		size: 48,
		label: __( 'XL', 'drivehr-webhook' ),
		name: __( 'X Large', 'drivehr-webhook' ),
	},
	{
		value: 'xxl',
		size: 64,
		label: __( 'XXL', 'drivehr-webhook' ),
		name: __( '2X Large', 'drivehr-webhook' ),
	},
];
