/**
 * Spacing control utility functions
 *
 * Adapted from Kadence Blocks (GPL v2+)
 * Original: https://github.com/stellarwp/kadence-blocks
 *
 * @package DriveHR
 * @since 1.8.5
 */

/**
 * Check if a value is a custom size (not in preset options)
 *
 * @param {Array} optionsArray - Array of preset options
 * @param {string} value - Value to check
 * @returns {boolean} True if value is custom (not a preset)
 */
export function isCustomOption( optionsArray, value ) {
	if ( ! value ) {
		return false;
	}
	if ( ! optionsArray ) {
		return false;
	}
	return ! optionsArray.find( ( option ) => option.value === value );
}

/**
 * Get the index of a preset option by its value
 *
 * @param {Array} optionsArray - Array of preset options
 * @param {string} value - Preset value to find
 * @returns {number|undefined} Index of the option, or undefined if not found
 */
export function getOptionIndex( optionsArray, value ) {
	if ( ! value ) {
		return;
	}
	if ( ! optionsArray ) {
		return;
	}
	if ( value === '0' ) {
		return 0;
	}
	const found = optionsArray.findIndex( ( option ) => option.value === value );
	if ( found === -1 ) {
		return;
	}
	return found;
}

/**
 * Convert preset value to pixel size
 *
 * @param {Array} optionsArray - Array of preset options
 * @param {string} value - Preset value
 * @param {string} unit - Unit (px, rem, em, etc.)
 * @returns {string} Pixel size with unit
 */
export function getOptionSize( optionsArray, value, unit ) {
	if ( ! value ) {
		return '';
	}
	const option = optionsArray.find( ( opt ) => opt.value === value );
	if ( ! option ) {
		return value; // Return as-is if not a preset
	}
	return option.size + ( unit || 'px' );
}

/**
 * Convert pixel size to preset value
 *
 * @param {Array} optionsArray - Array of preset options
 * @param {string} value - Pixel value (e.g., "16px")
 * @param {string} unit - Unit to match
 * @returns {string} Preset value or original value if no match
 */
export function getOptionFromSize( optionsArray, value, unit ) {
	if ( ! value ) {
		return '';
	}
	// Extract numeric part
	const numericValue = parseFloat( value );
	if ( isNaN( numericValue ) ) {
		return value;
	}

	// Find closest preset
	const option = optionsArray.find( ( opt ) => opt.size === numericValue );
	if ( option ) {
		return option.value;
	}

	return value; // Return as-is if no preset match
}
