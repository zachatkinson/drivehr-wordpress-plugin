/**
 * DriveHR Job List Block
 *
 * Gutenberg block for displaying all job postings with modern design.
 *
 * Features:
 * - Visual preset spacing controls (adapted from Kadence Blocks GPL v2+)
 * - Theme color integration
 * - Server-side rendering
 *
 * @package DriveHR_Webhook
 * @since 1.7.0
 */

import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	__experimentalPanelColorGradientSettings as PanelColorGradientSettings,
} from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	ToggleControl,
	RangeControl,
	BorderControl,
	__experimentalUnitControl as UnitControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { list as listIcon } from '@wordpress/icons';
import ServerSideRender from '@wordpress/server-side-render';
import DriveHRSpacingControl from '../components/spacing-control';
import DriveHRShadowPicker from '../components/shadow-picker';

/**
 * Block Edit Component
 *
 * @param {Object} props Block properties
 * @return {JSX.Element} Block editor interface
 */
function Edit( props ) {
	const { attributes, setAttributes } = props;
	const {
		showLocation,
		showJobType,
		postsPerPage,
		orderBy,
		order,
		cardBackgroundColor,
		cardTextColor,
		cardTitleColor,
		cardBorder,
		cardBorderRadius,
		cardShadow,
		cardPadding
	} = attributes;

	// State for spacing unit
	const [ paddingUnit, setPaddingUnit ] = useState( 'rem' );

	// Get theme colors for color pickers
	const { colors } = useSelect( ( select ) => {
		const settings = select( 'core/block-editor' ).getSettings();
		return {
			colors: settings?.colors || [],
		};
	}, [] );

	const blockProps = useBlockProps( {
		className: 'drivehr-job-list-editor',
	} );

	return (
		<div { ...blockProps }>
			{/* Settings Tab - Functional controls */}
			<InspectorControls>
				<PanelBody title={ __( 'Display Settings', 'drivehr-webhook' ) } initialOpen={ true }>
					<ToggleControl
						label={ __( 'Show Location', 'drivehr-webhook' ) }
						checked={ showLocation }
						onChange={ ( value ) => setAttributes( { showLocation: value } ) }
						help={ __( 'Display the job location for each listing.', 'drivehr-webhook' ) }
					/>

					<ToggleControl
						label={ __( 'Show Job Type', 'drivehr-webhook' ) }
						checked={ showJobType }
						onChange={ ( value ) => setAttributes( { showJobType: value } ) }
						help={ __( 'Display the job type (Full-time, Part-time, etc.).', 'drivehr-webhook' ) }
					/>
				</PanelBody>

				<PanelBody title={ __( 'Query Settings', 'drivehr-webhook' ) } initialOpen={ false }>
					<RangeControl
						label={ __( 'Number of Jobs', 'drivehr-webhook' ) }
						value={ postsPerPage }
						onChange={ ( value ) => setAttributes( { postsPerPage: value } ) }
						min={ 1 }
						max={ 100 }
						help={ __( 'Maximum number of jobs to display. Default: 50', 'drivehr-webhook' ) }
					/>

					<SelectControl
						label={ __( 'Order By', 'drivehr-webhook' ) }
						value={ orderBy }
						options={ [
							{ label: __( 'Date Posted', 'drivehr-webhook' ), value: 'date' },
							{ label: __( 'Job Title', 'drivehr-webhook' ), value: 'title' },
							{ label: __( 'Last Modified', 'drivehr-webhook' ), value: 'modified' },
						] }
						onChange={ ( value ) => setAttributes( { orderBy: value } ) }
					/>

					<SelectControl
						label={ __( 'Order', 'drivehr-webhook' ) }
						value={ order }
						options={ [
							{ label: __( 'Descending', 'drivehr-webhook' ), value: 'DESC' },
							{ label: __( 'Ascending', 'drivehr-webhook' ), value: 'ASC' },
						] }
						onChange={ ( value ) => setAttributes( { order: value } ) }
					/>
				</PanelBody>
			</InspectorControls>

			{/* Styles Tab - Visual styling controls */}
			<InspectorControls group="styles">
				<PanelColorGradientSettings
					__experimentalIsRenderedInSidebar
					title={ __( 'Card Colors', 'drivehr-webhook' ) }
					enableAlpha
					settings={ [
						{
							label: __( 'Background', 'drivehr-webhook' ),
							colorValue: cardBackgroundColor,
							onColorChange: ( value ) => setAttributes( { cardBackgroundColor: value } ),
							clearable: true,
						},
						{
							label: __( 'Text', 'drivehr-webhook' ),
							colorValue: cardTextColor,
							onColorChange: ( value ) => setAttributes( { cardTextColor: value } ),
							clearable: true,
						},
						{
							label: __( 'Title', 'drivehr-webhook' ),
							colorValue: cardTitleColor,
							onColorChange: ( value ) => setAttributes( { cardTitleColor: value } ),
							clearable: true,
						},
					] }
				/>

				<PanelBody title={ __( 'Card Border', 'drivehr-webhook' ) } initialOpen={ false }>
					<BorderControl
						__next40pxDefaultSize
						colors={ colors }
						label={ __( 'Border', 'drivehr-webhook' ) }
						onChange={ ( value ) => setAttributes( { cardBorder: value } ) }
						value={ cardBorder }
						withSlider
					/>

					<UnitControl
						label={ __( 'Border Radius', 'drivehr-webhook' ) }
						value={ cardBorderRadius }
						onChange={ ( value ) => setAttributes( { cardBorderRadius: value } ) }
						units={ [
							{ value: 'px', label: 'px' },
							{ value: 'rem', label: 'rem' },
							{ value: '%', label: '%' },
						] }
					/>
				</PanelBody>

				<PanelBody title={ __( 'Card Spacing', 'drivehr-webhook' ) } initialOpen={ false }>
					<DriveHRSpacingControl
						label={ __( 'Padding', 'drivehr-webhook' ) }
						value={ cardPadding }
						onChange={ ( value ) => setAttributes( { cardPadding: value } ) }
						unit={ paddingUnit }
						onUnit={ setPaddingUnit }
						units={ [ 'px', 'rem', 'em' ] }
						defaultValue={ {
							top: 'md',
							right: 'md',
							bottom: 'md',
							left: 'md',
						} }
						reset={ () => setAttributes( {
							cardPadding: {
								top: 'md',
								right: 'md',
								bottom: 'md',
								left: 'md',
							}
						} ) }
					/>
				</PanelBody>

				<PanelBody title={ __( 'Card Shadow', 'drivehr-webhook' ) } initialOpen={ false }>
					<DriveHRShadowPicker
						label={ __( 'Shadow Preset', 'drivehr-webhook' ) }
						value={ cardShadow }
						onChange={ ( value ) => setAttributes( { cardShadow: value } ) }
					/>
				</PanelBody>
			</InspectorControls>

			<ServerSideRender
				block="drivehr/job-list"
				attributes={ attributes }
			/>
		</div>
	);
}

// Register the block
registerBlockType( 'drivehr/job-list', {
	edit: Edit,
	save: () => null, // Server-side rendering
} );
