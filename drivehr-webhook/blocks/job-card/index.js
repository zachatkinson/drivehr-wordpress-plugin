/**
 * DriveHR Job Card Block
 *
 * Gutenberg block for displaying individual job postings with modern design.
 *
 * @package DriveHR_Webhook
 * @since 1.0.0
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
	TextControl,
	ButtonGroup,
	Button,
	Placeholder,
	Spinner,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useState, useEffect } from '@wordpress/element';
import { postContent as jobIcon } from '@wordpress/icons';
import apiFetch from '@wordpress/api-fetch';
import ServerSideRender from '@wordpress/server-side-render';

/**
 * Block Edit Component
 *
 * @param {Object} props Block properties
 * @return {JSX.Element} Block editor interface
 */
function Edit( props ) {
	const { attributes, setAttributes } = props;
	const { jobId, showExcerpt, showLocation, showJobType, buttonText, buttonStyle } = attributes;

	const [ jobs, setJobs ] = useState( [] );
	const [ isLoading, setIsLoading ] = useState( true );

	const blockProps = useBlockProps( {
		className: 'drivehr-job-card-editor',
	} );

	// Fetch available jobs
	useEffect( () => {
		setIsLoading( true );
		apiFetch( {
			path: '/wp/v2/drivehr-jobs?per_page=100&status=publish&_fields=id,title',
		} )
			.then( ( fetchedJobs ) => {
				const jobOptions = fetchedJobs.map( ( job ) => ( {
					label: job.title.rendered,
					value: job.id,
				} ) );
				setJobs( jobOptions );
				setIsLoading( false );
			} )
			.catch( () => {
				setJobs( [] );
				setIsLoading( false );
			} );
	}, [] );

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Job Selection', 'drivehr-webhook' ) } initialOpen={ true }>
					{ isLoading ? (
						<Placeholder>
							<Spinner />
							<p>{ __( 'Loading jobs...', 'drivehr-webhook' ) }</p>
						</Placeholder>
					) : (
						<SelectControl
							label={ __( 'Select Job', 'drivehr-webhook' ) }
							value={ jobId }
							options={ [
								{ label: __( 'Choose a job...', 'drivehr-webhook' ), value: 0 },
								...jobs,
							] }
							onChange={ ( value ) => setAttributes( { jobId: parseInt( value, 10 ) } ) }
							help={ __( 'Select which job posting to display in this card.', 'drivehr-webhook' ) }
						/>
					) }
				</PanelBody>

				<PanelBody title={ __( 'Display Options', 'drivehr-webhook' ) } initialOpen={ false }>
					<ToggleControl
						label={ __( 'Show Excerpt', 'drivehr-webhook' ) }
						checked={ showExcerpt }
						onChange={ ( value ) => setAttributes( { showExcerpt: value } ) }
						help={ __( 'Display a brief description of the job.', 'drivehr-webhook' ) }
					/>

					<ToggleControl
						label={ __( 'Show Location', 'drivehr-webhook' ) }
						checked={ showLocation }
						onChange={ ( value ) => setAttributes( { showLocation: value } ) }
						help={ __( 'Display the job location.', 'drivehr-webhook' ) }
					/>

					<ToggleControl
						label={ __( 'Show Job Type', 'drivehr-webhook' ) }
						checked={ showJobType }
						onChange={ ( value ) => setAttributes( { showJobType: value } ) }
						help={ __( 'Display the job type (Full-time, Part-time, etc.).', 'drivehr-webhook' ) }
					/>
				</PanelBody>

				<PanelBody title={ __( 'Button Settings', 'drivehr-webhook' ) } initialOpen={ false }>
					<TextControl
						label={ __( 'Button Text', 'drivehr-webhook' ) }
						value={ buttonText }
						onChange={ ( value ) => setAttributes( { buttonText: value } ) }
						help={ __( 'Customize the text displayed on the apply button.', 'drivehr-webhook' ) }
					/>

					<div className="drivehr-button-style-control">
						<label className="components-base-control__label">
							{ __( 'Button Style', 'drivehr-webhook' ) }
						</label>
						<ButtonGroup>
							<Button
								variant={ buttonStyle === 'primary' ? 'primary' : 'secondary' }
								onClick={ () => setAttributes( { buttonStyle: 'primary' } ) }
							>
								{ __( 'Primary', 'drivehr-webhook' ) }
							</Button>
							<Button
								variant={ buttonStyle === 'secondary' ? 'primary' : 'secondary' }
								onClick={ () => setAttributes( { buttonStyle: 'secondary' } ) }
							>
								{ __( 'Secondary', 'drivehr-webhook' ) }
							</Button>
							<Button
								variant={ buttonStyle === 'outline' ? 'primary' : 'secondary' }
								onClick={ () => setAttributes( { buttonStyle: 'outline' } ) }
							>
								{ __( 'Outline', 'drivehr-webhook' ) }
							</Button>
						</ButtonGroup>
					</div>
				</PanelBody>
			</InspectorControls>

			{ ! jobId && ! isLoading && (
				<Placeholder
					icon={ jobIcon }
					label={ __( 'DriveHR Job Card', 'drivehr-webhook' ) }
					instructions={ __( 'Select a job to display using the block settings in the sidebar.', 'drivehr-webhook' ) }
				>
					{ jobs.length === 0 && (
						<p className="drivehr-job-card-placeholder__no-jobs">
							{ __( 'No jobs available. Please sync jobs from DriveHR first.', 'drivehr-webhook' ) }
						</p>
					) }
				</Placeholder>
			) }

			{ jobId > 0 && (
				<ServerSideRender
					block="drivehr/job-card"
					attributes={ attributes }
					LoadingResponsePlaceholder={ () => (
						<Placeholder>
							<Spinner />
						</Placeholder>
					) }
					ErrorResponsePlaceholder={ () => (
						<Placeholder
							icon={ jobIcon }
							label={ __( 'Error Loading Job', 'drivehr-webhook' ) }
						>
							<p>{ __( 'Could not load the selected job. Please try again.', 'drivehr-webhook' ) }</p>
						</Placeholder>
					) }
				/>
			) }
		</div>
	);
}

// Register the block
registerBlockType( 'drivehr/job-card', {
	edit: Edit,
	save: () => null, // Server-side rendering
} );
