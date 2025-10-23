/**
 * DriveHR Job List Block
 *
 * Gutenberg block for displaying all job postings with modern design.
 *
 * @package DriveHR_Webhook
 * @since 1.7.0
 */

import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
} from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	ToggleControl,
	RangeControl,
} from '@wordpress/components';
import { list as listIcon } from '@wordpress/icons';
import ServerSideRender from '@wordpress/server-side-render';

/**
 * Block Edit Component
 *
 * @param {Object} props Block properties
 * @return {JSX.Element} Block editor interface
 */
function Edit( props ) {
	const { attributes, setAttributes } = props;
	const { showLocation, showJobType, postsPerPage, orderBy, order } = attributes;

	const blockProps = useBlockProps( {
		className: 'drivehr-job-list-editor',
	} );

	return (
		<div { ...blockProps }>
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
						value={ postsPerPage === -1 ? 100 : postsPerPage }
						onChange={ ( value ) => setAttributes( { postsPerPage: value === 100 ? -1 : value } ) }
						min={ 1 }
						max={ 100 }
						help={ __( 'Set to 100 to show all jobs.', 'drivehr-webhook' ) }
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
