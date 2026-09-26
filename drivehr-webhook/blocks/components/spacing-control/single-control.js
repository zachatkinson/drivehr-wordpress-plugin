/**
 * Single Spacing Range Control
 *
 * Adapted from Kadence Blocks (GPL v2+)
 * Original: https://github.com/stellarwp/kadence-blocks
 *
 * Provides a single spacing input with:
 * - Visual preset dots slider (preset mode)
 * - Numeric input with units (custom mode)
 * - Toggle button to switch between modes
 *
 * @package DriveHR
 * @since 1.8.5
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Button,
	Popover,
	RangeControl,
	__experimentalNumberControl as NumberControl,
} from '@wordpress/components';
import { settings } from '@wordpress/icons';
import { SPACING_PRESETS } from './constants';
import { isCustomOption, getOptionIndex } from './utils';

/**
 * Visual icons for spacing sides (adapted from Kadence Blocks)
 */
const SpacingVisualIcons = {
	top: (
		<svg width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
			<path d="M5 5h14v3H5V5z" fill="currentColor" />
			<path d="M5 10h14v9H5v-9z" fill="none" stroke="currentColor" strokeWidth="1" />
		</svg>
	),
	right: (
		<svg width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
			<path d="M16 5h3v14h-3V5z" fill="currentColor" />
			<path d="M5 5h9v14H5V5z" fill="none" stroke="currentColor" strokeWidth="1" />
		</svg>
	),
	bottom: (
		<svg width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
			<path d="M5 16h14v3H5v-3z" fill="currentColor" />
			<path d="M5 5h14v9H5V5z" fill="none" stroke="currentColor" strokeWidth="1" />
		</svg>
	),
	left: (
		<svg width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
			<path d="M5 5h3v14H5V5z" fill="currentColor" />
			<path d="M10 5h9v14h-9V5z" fill="none" stroke="currentColor" strokeWidth="1" />
		</svg>
	),
	all: (
		<svg width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
			<path d="M5 5h14v14H5V5z" fill="currentColor" />
		</svg>
	),
};

/**
 * Single measure range control component
 *
 * @param {Object} props - Component props
 * @param {string} props.label - Label for the control
 * @param {Function} props.onChange - Callback when value changes
 * @param {string} props.value - Current value
 * @param {string} props.placeholder - Placeholder value
 * @param {string} props.className - Additional CSS class
 * @param {Array} props.options - Preset options array
 * @param {number} props.step - Step for numeric input
 * @param {number} props.max - Maximum value for numeric input
 * @param {number} props.min - Minimum value for numeric input
 * @param {string} props.defaultValue - Default value
 * @param {string} props.unit - Current unit (px, rem, em)
 * @param {Function} props.onUnit - Callback when unit changes
 * @param {Array} props.units - Available units
 * @param {boolean} props.disableCustomSizes - Disable custom mode
 * @param {boolean} props.customControl - External custom mode state
 * @param {Function} props.setCustomControl - External custom mode setter
 * @param {boolean} props.isPopover - Render in popover
 * @param {boolean} props.isSingle - Single control mode
 * @param {string} props.parentLabel - Parent label for context
 * @param {string} props.icon - Icon type (top, right, bottom, left, all)
 * @returns {JSX.Element} Single spacing control
 */
export default function SingleSpacingControl( {
	label,
	onChange,
	value = '',
	placeholder = '',
	className = '',
	options = SPACING_PRESETS,
	step = 1,
	max = 200,
	min = 0,
	defaultValue = 0,
	unit = 'px',
	onUnit,
	units = [ 'px', 'rem', 'em' ],
	disableCustomSizes = false,
	customControl = false,
	setCustomControl = null,
	isPopover = false,
	isSingle = false,
	parentLabel = null,
	icon = null,
} ) {
	const [ isCustom, setIsCustom ] = useState( false );
	const [ isOpen, setIsOpen ] = useState( false );

	useEffect( () => {
		setIsCustom( isCustomOption( options, value ) );
	}, [] );

	const realIsCustomControl = setCustomControl ? customControl : isCustom;
	const realSetIsCustom = setCustomControl ? setCustomControl : setIsCustom;

	function toggle() {
		setIsOpen( ! isOpen );
	}

	function close() {
		setIsOpen( false );
	}

	/**
	 * Convert slider index to preset value
	 */
	const getNewPresetValue = ( newSize ) => {
		if ( undefined === newSize ) {
			return '';
		}
		const size = parseInt( newSize, 10 );
		if ( size === 0 ) {
			return '0';
		}
		return `${ options[ newSize ]?.value }`;
	};

	/**
	 * Handle custom numeric input change
	 */
	const onChangeCustom = ( newSize ) => {
		const isNumeric = ! isNaN( parseFloat( newSize ) );
		const nextValue = isNumeric ? parseFloat( newSize ) : undefined;
		onChange( nextValue );
	};

	// Create marks for visual dots on slider
	const marks = options.map( ( newValue, index ) => ( {
		value: index,
		label: undefined,
	} ) );

	const controlUnits = units.map( ( unitItem ) => ( {
		value: unitItem,
		label: unitItem,
	} ) );

	const currentValue = ! realIsCustomControl
		? getOptionIndex( options, value )
		: Number( value );
	const currentPlaceholder = ! realIsCustomControl
		? getOptionIndex( options, placeholder )
		: Number( placeholder );

	const customTooltipContent = ( newValue ) => {
		return options[ newValue ]?.label;
	};

	const currentValueLabel = options[ currentValue ]?.label
		? options[ currentValue ]?.label
		: __( 'Unset', 'drivehr-webhook' );
	const currentValueName = options[ currentValue ]?.name
		? options[ currentValue ]?.name + ' ' + options[ currentValue ]?.size + 'px'
		: __( 'Unset', 'drivehr-webhook' );
	const addParent = parentLabel ? parentLabel + ' ' : '';
	let rangeLabel = label;
	if ( isSingle ) {
		rangeLabel = currentValueName;
	} else if ( label && addParent ) {
		rangeLabel = addParent + label + ' ' + currentValueLabel;
	}

	// Preset mode: Range control with visual dots
	const presetRangeControl = (
		<>
			<RangeControl
				label={ rangeLabel ? rangeLabel : undefined }
				className="drivehr-spacing-range-control"
				value={ currentValue }
				onChange={ ( newVal ) => {
					if ( undefined === newVal ) {
						onChange( defaultValue );
					} else {
						onChange( getNewPresetValue( newVal ) );
					}
				} }
				min={ 0 }
				max={ options.length - 1 }
				marks={ marks }
				step={ 1 }
				withInputField={ false }
				renderTooltipContent={ customTooltipContent }
				allowReset={ isSingle ? true : false }
				hideLabelFromVision={ isPopover || isSingle ? false : true }
			/>
			{ ! disableCustomSizes && (
				<Button
					className="drivehr-spacing-custom-toggle"
					label={ __( 'Set custom size', 'drivehr-webhook' ) }
					icon={ settings }
					onClick={ () => realSetIsCustom( true ) }
					isPressed={ false }
					size="small"
				/>
			) }
		</>
	);

	// Custom mode: Range slider + numeric input (like Kadence)
	const customSizeControl = (
		<div className="drivehr-spacing-custom-controls">
			<RangeControl
				label={ rangeLabel ? rangeLabel : undefined }
				className="drivehr-spacing-range-control"
				value={ parseFloat( value ) || 0 }
				onChange={ ( newVal ) => onChange( newVal ) }
				min={ min }
				max={ max }
				step={ step }
				allowReset={ false }
				hideLabelFromVision={ isPopover || isSingle ? false : true }
			/>
			<NumberControl
				className="drivehr-spacing-number-input"
				value={ value || '' }
				onChange={ ( newVal ) => onChangeCustom( newVal ) }
				min={ min }
				max={ max }
				step={ step }
				placeholder={ placeholder }
			/>
			{ ! disableCustomSizes && (
				<Button
					className="drivehr-spacing-custom-toggle"
					label={ __( 'Use size preset', 'drivehr-webhook' ) }
					icon={ settings }
					onClick={ () => realSetIsCustom( false ) }
					isPressed={ true }
					size="small"
				/>
			) }
		</div>
	);

	return (
		<div
			className={ `drivehr-single-spacing-control${ className ? ' ' + className : '' }` }
		>
			{ icon && SpacingVisualIcons[ icon ] && (
				<div className="drivehr-spacing-icon">
					{ SpacingVisualIcons[ icon ] }
				</div>
			) }
			<div className="drivehr-spacing-control-wrapper">
				{ ! realIsCustomControl && (
					<div className="drivehr-spacing-preset-mode">
						{ isPopover && (
							<>
								<Button
									className="drivehr-spacing-preset-btn"
									onClick={ toggle }
								>
									{ parentLabel && label && (
										<span className="drivehr-spacing-label">{ label }</span>
									) }
									<span className="drivehr-spacing-value">
										{ options[ currentValue ]?.label }
										{ ! options[ currentValue ]?.label && (
											<span className="drivehr-spacing-placeholder">
												{ options?.[ currentPlaceholder ]?.label
													? options?.[ currentPlaceholder ]?.label
													: placeholder }
											</span>
										) }
									</span>
								</Button>
								{ isOpen && (
									<Popover onClose={ close } className="drivehr-spacing-popover">
										<div className="drivehr-spacing-popover-inner">
											{ presetRangeControl }
											<Button
												variant="secondary"
												size="small"
												text={ __( 'Reset', 'drivehr-webhook' ) }
												onClick={ () => onChange( '' ) }
											/>
										</div>
									</Popover>
								) }
							</>
						) }
						{ ! isPopover && presetRangeControl }
					</div>
				) }
				{ realIsCustomControl && (
					<div className="drivehr-spacing-custom-mode">
						{ customSizeControl }
					</div>
				) }
			</div>
		</div>
	);
}
