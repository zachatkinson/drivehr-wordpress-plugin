/**
 * DriveHR Shadow Picker Component
 *
 * Uses WordPress theme shadow presets for consistent styling.
 * Inspired by native Gutenberg shadow support but for custom attributes.
 *
 * @package DriveHR
 * @since 1.9.0
 */

import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import './editor.scss';

/**
 * Shadow Picker Component
 *
 * @param {Object} props - Component props
 * @param {string} props.value - Current shadow value
 * @param {Function} props.onChange - Callback when shadow changes
 * @param {string} props.label - Label for the control
 * @returns {JSX.Element} Shadow picker component
 */
export default function DriveHRShadowPicker( { value = '', onChange, label } ) {
	// Get theme shadow presets from block editor settings
	const { shadowPresets } = useSelect( ( select ) => {
		const settings = select( 'core/block-editor' ).getSettings();
		return {
			shadowPresets: settings?.__experimentalFeatures?.shadow?.presets?.theme || [
				{
					name: __( 'None', 'drivehr-webhook' ),
					slug: 'none',
					shadow: '',
				},
				{
					name: __( 'Natural', 'drivehr-webhook' ),
					slug: 'natural',
					shadow: '6px 6px 9px rgba(0, 0, 0, 0.2)',
				},
				{
					name: __( 'Deep', 'drivehr-webhook' ),
					slug: 'deep',
					shadow: '12px 12px 50px rgba(0, 0, 0, 0.4)',
				},
				{
					name: __( 'Sharp', 'drivehr-webhook' ),
					slug: 'sharp',
					shadow: '6px 6px 0px rgba(0, 0, 0, 0.2)',
				},
				{
					name: __( 'Outlined', 'drivehr-webhook' ),
					slug: 'outlined',
					shadow: '0 0 0 1px rgba(0, 0, 0, 0.1)',
				},
				{
					name: __( 'Crisp', 'drivehr-webhook' ),
					slug: 'crisp',
					shadow: '6px 6px 0px rgba(0, 0, 0, 1)',
				},
			],
		};
	}, [] );

	// Additional common shadow presets
	const commonShadows = [
		{
			name: __( 'Small', 'drivehr-webhook' ),
			slug: 'small',
			shadow: '0 1px 3px rgba(0, 0, 0, 0.12), 0 1px 2px rgba(0, 0, 0, 0.24)',
		},
		{
			name: __( 'Medium', 'drivehr-webhook' ),
			slug: 'medium',
			shadow: '0 3px 6px rgba(0, 0, 0, 0.15), 0 2px 4px rgba(0, 0, 0, 0.12)',
		},
		{
			name: __( 'Large', 'drivehr-webhook' ),
			slug: 'large',
			shadow: '0 10px 20px rgba(0, 0, 0, 0.15), 0 3px 6px rgba(0, 0, 0, 0.10)',
		},
		{
			name: __( 'Extra Large', 'drivehr-webhook' ),
			slug: 'xlarge',
			shadow: '0 15px 25px rgba(0, 0, 0, 0.15), 0 5px 10px rgba(0, 0, 0, 0.05)',
		},
	];

	// Combine theme presets with common presets
	const allShadows = [ ...shadowPresets, ...commonShadows ];

	// Find current shadow preset
	const currentShadow = allShadows.find( ( s ) => s.shadow === value );

	return (
		<div className="drivehr-shadow-picker">
			{ label && <div className="drivehr-shadow-picker__label">{ label }</div> }
			<div className="drivehr-shadow-picker__presets">
				{ allShadows.map( ( shadowPreset ) => {
					const isActive = shadowPreset.shadow === value;
					return (
						<Button
							key={ shadowPreset.slug }
							className={ `drivehr-shadow-preset-button${ isActive ? ' is-active' : '' }` }
							onClick={ () => onChange( shadowPreset.shadow ) }
						>
							<div className="drivehr-shadow-preset-content">
								<div className="drivehr-shadow-preset-name">
									{ shadowPreset.name }
								</div>
								<div
									className="drivehr-shadow-preset-preview"
									style={ {
										boxShadow: shadowPreset.shadow || 'none',
									} }
								/>
							</div>
						</Button>
					);
				} ) }
			</div>
			{ ! currentShadow && value && (
				<div className="drivehr-shadow-picker__custom">
					<div className="drivehr-shadow-custom-label">
						{ __( 'Custom shadow:', 'drivehr-webhook' ) }
					</div>
					<code className="drivehr-shadow-custom-value">{ value }</code>
				</div>
			) }
		</div>
	);
}
