/**
 * DriveHR Spacing Control
 *
 * Adapted from Kadence Blocks (GPL v2+)
 * Original: https://github.com/stellarwp/kadence-blocks
 *
 * Four-sided spacing control with:
 * - Visual preset dots (like WordPress native controls)
 * - Linked/individual mode toggle
 * - Custom size mode with numeric inputs
 * - Reset button
 *
 * @package DriveHR
 * @since 1.8.5
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { link, linkOff, settings } from '@wordpress/icons';
import { isEqual } from 'lodash';
import SingleSpacingControl from './single-control';
import { SPACING_PRESETS } from './constants';
import { isCustomOption, getOptionSize, getOptionFromSize } from './utils';
import './editor.scss';

/**
 * Spacing control component for padding/margin
 *
 * @param {Object} props - Component props
 * @param {string} props.label - Label for the control group
 * @param {Function} props.onChange - Callback when values change
 * @param {Object} props.value - Current values {top, right, bottom, left}
 * @param {string} props.className - Additional CSS class
 * @param {Array} props.options - Preset options
 * @param {number} props.step - Step for numeric inputs
 * @param {number} props.max - Maximum value
 * @param {number} props.min - Minimum value
 * @param {Object} props.placeholder - Placeholder values
 * @param {Object} props.defaultValue - Default values
 * @param {string} props.control - 'individual' or 'linked'
 * @param {string} props.unit - Current unit
 * @param {Function} props.onUnit - Unit change callback
 * @param {Array} props.units - Available units
 * @param {boolean} props.disableCustomSizes - Disable custom mode
 * @param {Function} props.reset - Reset callback
 * @returns {JSX.Element} Spacing control component
 */
export default function DriveHRSpacingControl( {
	label,
	onChange,
	value = { top: '', right: '', bottom: '', left: '' },
	className = '',
	options = SPACING_PRESETS,
	step = 1,
	max = 200,
	min = 0,
	placeholder = { top: '', right: '', bottom: '', left: '' },
	defaultValue = { top: '', right: '', bottom: '', left: '' },
	control = 'individual',
	unit = 'px',
	onUnit,
	units = [ 'px', 'rem', 'em' ],
	disableCustomSizes = false,
	reset,
} ) {
	const [ isCustom, setIsCustom ] = useState( false );
	const [ theControl, setTheControl ] = useState( control );

	useEffect( () => {
		// Check if ANY of the values are custom (not preset values)
		const anyCustom = isCustomOption( options, value.top ) ||
			isCustomOption( options, value.right ) ||
			isCustomOption( options, value.bottom ) ||
			isCustomOption( options, value.left );
		setIsCustom( anyCustom );
	}, [] );

	const realIsCustomControl = isCustom;
	const realSetIsCustom = setIsCustom;

	const onReset = () => {
		if ( typeof reset === 'function' ) {
			reset();
		} else {
			onChange( defaultValue );
		}
	};

	const onSetIsCustom = () => {
		if ( ! realIsCustomControl ) {
			// Converting to custom mode
			const newValue = {
				top: getOptionSize( options, value.top || '', unit ),
				right: getOptionSize( options, value.right || '', unit ),
				bottom: getOptionSize( options, value.bottom || '', unit ),
				left: getOptionSize( options, value.left || '', unit ),
			};
			onChange( newValue );
		} else {
			// Converting to preset mode
			const newValue = {
				top: getOptionFromSize( options, value.top || '', unit ),
				right: getOptionFromSize( options, value.right || '', unit ),
				bottom: getOptionFromSize( options, value.bottom || '', unit ),
				left: getOptionFromSize( options, value.left || '', unit ),
			};
			onChange( newValue );
		}
		realSetIsCustom( ! realIsCustomControl );
	};

	const realControl = theControl;
	const realSetOnControl = setTheControl;

	return (
		<div
			className={ `drivehr-spacing-control${ className ? ' ' + className : '' }` }
		>
			{ label && (
				<div className="drivehr-spacing-header">
					<div className="drivehr-spacing-title">
						<label className="components-base-control__label">{ label }</label>
						{ reset && (
							<Button
								className="drivehr-spacing-reset"
								label="reset"
								size="small"
								disabled={ isEqual( defaultValue, value ) }
								onClick={ () => onReset() }
							>
								↺
							</Button>
						) }
					</div>
					{ ! disableCustomSizes && (
						<Button
							className="drivehr-spacing-custom-toggle"
							label={
								! realIsCustomControl
									? __( 'Set custom size', 'drivehr-webhook' )
									: __( 'Use size preset', 'drivehr-webhook' )
							}
							icon={ settings }
							size="small"
							onClick={ onSetIsCustom }
							isPressed={ realIsCustomControl ? true : false }
						/>
					) }
					{ realSetOnControl && (
						<Button
							size="small"
							className="drivehr-spacing-link-toggle"
							label={
								realControl !== 'individual'
									? __( 'Individual', 'drivehr-webhook' )
									: __( 'Linked', 'drivehr-webhook' )
							}
							icon={ realControl !== 'individual' ? linkOff : link }
							onClick={ () =>
								realSetOnControl( realControl !== 'individual' ? 'individual' : 'linked' )
							}
							isPressed={ realControl !== 'individual' ? true : false }
						/>
					) }
				</div>
			) }
			<div className="drivehr-spacing-controls">
				{ realControl !== 'individual' && (
					<SingleSpacingControl
						value={ value.top || '' }
						onChange={ ( newVal ) =>
							onChange( { top: newVal, right: newVal, bottom: newVal, left: newVal } )
						}
						className="drivehr-spacing-all"
						min={ min }
						max={ max }
						options={ options }
						step={ step }
						unit={ unit }
						units={ units }
						onUnit={ onUnit }
						defaultValue={ defaultValue.top }
						placeholder={ placeholder?.top || '' }
						disableCustomSizes={ true }
						setCustomControl={ realSetIsCustom }
						customControl={ realIsCustomControl }
						isPopover={ false }
						isSingle={ true }
						icon="all"
					/>
				) }
				{ realControl === 'individual' && (
					<>
						<SingleSpacingControl
							parentLabel={ label }
							label={ __( 'Top', 'drivehr-webhook' ) }
							className="drivehr-spacing-top"
							value={ value.top || '' }
							onChange={ ( newVal ) => onChange( { ...value, top: newVal } ) }
							min={ min }
							max={ max }
							options={ options }
							step={ step }
							unit={ unit }
							units={ units }
							onUnit={ onUnit }
							defaultValue={ defaultValue.top }
							placeholder={ placeholder?.top || '' }
							disableCustomSizes={ true }
							setCustomControl={ realSetIsCustom }
							customControl={ realIsCustomControl }
							isPopover={ false }
							icon="top"
						/>
						<SingleSpacingControl
							parentLabel={ label }
							label={ __( 'Right', 'drivehr-webhook' ) }
							className="drivehr-spacing-right"
							value={ value.right || '' }
							onChange={ ( newVal ) => onChange( { ...value, right: newVal } ) }
							min={ min }
							max={ max }
							options={ options }
							step={ step }
							unit={ unit }
							units={ units }
							onUnit={ onUnit }
							defaultValue={ defaultValue.right }
							placeholder={ placeholder?.right || '' }
							disableCustomSizes={ true }
							setCustomControl={ realSetIsCustom }
							customControl={ realIsCustomControl }
							isPopover={ false }
							icon="right"
						/>
						<SingleSpacingControl
							parentLabel={ label }
							label={ __( 'Bottom', 'drivehr-webhook' ) }
							className="drivehr-spacing-bottom"
							value={ value.bottom || '' }
							onChange={ ( newVal ) => onChange( { ...value, bottom: newVal } ) }
							min={ min }
							max={ max }
							options={ options }
							step={ step }
							unit={ unit }
							units={ units }
							onUnit={ onUnit }
							defaultValue={ defaultValue.bottom }
							placeholder={ placeholder?.bottom || '' }
							disableCustomSizes={ true }
							setCustomControl={ realSetIsCustom }
							customControl={ realIsCustomControl }
							isPopover={ false }
							icon="bottom"
						/>
						<SingleSpacingControl
							parentLabel={ label }
							label={ __( 'Left', 'drivehr-webhook' ) }
							className="drivehr-spacing-left"
							value={ value.left || '' }
							onChange={ ( newVal ) => onChange( { ...value, left: newVal } ) }
							min={ min }
							max={ max }
							options={ options }
							step={ step }
							unit={ unit }
							units={ units }
							onUnit={ onUnit }
							defaultValue={ defaultValue.left }
							placeholder={ placeholder?.left || '' }
							disableCustomSizes={ true }
							setCustomControl={ realSetIsCustom }
							customControl={ realIsCustomControl }
							isPopover={ false }
							icon="left"
						/>
						{ realIsCustomControl && (
							<div className="drivehr-spacing-unit-select">
								<select
									className="drivehr-unit-select"
									onChange={ ( event ) => {
										onUnit( event.target.value );
									} }
									value={ unit }
									disabled={ units.length === 1 }
								>
									{ units.map( ( option ) => (
										<option value={ option } key={ option }>
											{ option }
										</option>
									) ) }
								</select>
							</div>
						) }
					</>
				) }
			</div>
		</div>
	);
}
