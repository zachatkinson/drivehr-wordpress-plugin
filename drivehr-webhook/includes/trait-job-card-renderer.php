<?php
/**
 * DriveHR Job Card Renderer Trait
 *
 * Provides shared job card rendering logic for both single job card
 * and job list blocks, following DRY principles.
 *
 * @package DriveHR_Webhook
 * @since 1.7.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Job Card Renderer Trait
 *
 * Shared rendering methods for job card HTML across multiple block types.
 * Ensures consistent markup, accessibility, and Schema.org structure.
 *
 * @since 1.7.0
 */
trait DriveHR_Job_Card_Renderer_Trait {

	/**
	 * Render a single job card
	 *
	 * Generates consistent job card HTML with proper escaping, accessibility,
	 * and Schema.org markup. Used by both single job and job list blocks.
	 *
	 * @since 1.7.0
	 * @param int $job_id WordPress post ID for the job.
	 * @param array $options Optional rendering options.
	 *                      - 'show_excerpt' (bool) Whether to show excerpt. Default true.
	 *                      - 'show_location' (bool) Whether to show location. Default true.
	 *                      - 'show_job_type' (bool) Whether to show job type. Default true.
	 *                      - 'wrapper_attributes' (string) Custom wrapper attributes. Optional.
	 *                      - 'use_wrapper' (bool) Whether to include article wrapper. Default true.
	 * @return string Rendered job card HTML
	 */
	protected function render_job_card( int $job_id, array $options = array() ): string {
		$job = get_post( $job_id );

		if ( ! $job || 'drivehr_job' !== $job->post_type || 'publish' !== $job->post_status ) {
			return '';
		}

		// Get job metadata.
		$location     = get_post_meta( $job_id, 'location', true );
		$job_type     = get_post_meta( $job_id, 'job_type', true );
		$pay_type     = get_post_meta( $job_id, 'salary_range', true );
		$posted_date  = get_post_meta( $job_id, 'posted_date', true );
		$apply_url    = get_post_meta( $job_id, 'apply_url', true );
		$external_id  = get_post_meta( $job_id, 'job_id', true );

		// Parse options with defaults.
		$show_excerpt      = isset( $options['show_excerpt'] ) ? (bool) $options['show_excerpt'] : true;
		$show_location     = isset( $options['show_location'] ) ? (bool) $options['show_location'] : true;
		$show_job_type     = isset( $options['show_job_type'] ) ? (bool) $options['show_job_type'] : true;
		$use_wrapper       = isset( $options['use_wrapper'] ) ? (bool) $options['use_wrapper'] : true;
		$wrapper_attrs     = isset( $options['wrapper_attributes'] ) ? $options['wrapper_attributes'] : '';

		// Generate wrapper attributes if not provided.
		if ( $use_wrapper && empty( $wrapper_attrs ) ) {
			$wrapper_attrs = get_block_wrapper_attributes(
				array(
					'class'            => 'drivehr-job-card',
					'data-job-id'      => esc_attr( $job_id ),
					'data-external-id' => esc_attr( $external_id ),
					'itemscope'        => true,
					'itemtype'         => 'https://schema.org/JobPosting',
				)
			);
		}

		// Start output buffering.
		ob_start();
		?>
		<?php if ( $use_wrapper ) : ?>
		<article <?php echo $wrapper_attrs; ?>>
		<?php else : ?>
		<div class="drivehr-job-card__content-wrapper">
		<?php endif; ?>

			<div class="drivehr-job-card__content">
				<header class="drivehr-job-card__header">
					<h3 class="drivehr-job-card__title" itemprop="title">
						<?php echo esc_html( $job->post_title ); ?>
					</h3>

					<div class="drivehr-job-card__meta">
						<?php if ( $show_location && $location ) : ?>
							<span class="drivehr-job-card__location" itemprop="jobLocation" itemscope itemtype="https://schema.org/Place">
								<svg class="drivehr-job-card__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
									<path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd" />
								</svg>
								<span itemprop="address"><?php echo esc_html( $location ); ?></span>
							</span>
						<?php endif; ?>

						<?php if ( $show_job_type && $job_type ) : ?>
							<span class="drivehr-job-card__work-type" itemprop="employmentType">
								<svg class="drivehr-job-card__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
									<path fill-rule="evenodd" d="M6 6V5a3 3 0 013-3h2a3 3 0 013 3v1h2a2 2 0 012 2v3.57A22.952 22.952 0 0110 13a22.95 22.95 0 01-8-1.43V8a2 2 0 012-2h2zm2-1a1 1 0 011-1h2a1 1 0 011 1v1H8V5zm1 5a1 1 0 011-1h.01a1 1 0 110 2H10a1 1 0 01-1-1z" clip-rule="evenodd" />
									<path d="M2 13.692V16a2 2 0 002 2h12a2 2 0 002-2v-2.308A24.974 24.974 0 0110 15c-2.796 0-5.487-.46-8-1.308z" />
								</svg>
								<?php echo esc_html( $job_type ); ?>
							</span>
						<?php endif; ?>

						<?php if ( $pay_type ) : ?>
							<span class="drivehr-job-card__pay-type">
								<svg class="drivehr-job-card__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
									<path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z" />
									<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd" />
								</svg>
								<?php echo esc_html( $pay_type ); ?>
							</span>
						<?php endif; ?>
					</div>
				</header>

				<!-- Learn More Accordion -->
				<div class="drivehr-job-card__learn-more">
					<button class="drivehr-job-card__learn-more-toggle"
							aria-expanded="false"
							aria-controls="job-details-<?php echo esc_attr( $job_id ); ?>">
						<span>Learn More</span>
						<svg class="drivehr-job-card__toggle-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
							<path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
						</svg>
					</button>

					<div id="job-details-<?php echo esc_attr( $job_id ); ?>"
						 class="drivehr-job-card__details"
						 hidden
						 itemprop="description">
						<?php if ( $job->post_content ) : ?>
							<?php echo wp_kses_post( wpautop( $job->post_content ) ); ?>
						<?php else : ?>
							<p>No additional details available for this position.</p>
						<?php endif; ?>
					</div>
				</div>

				<!-- Action Buttons -->
				<?php if ( $apply_url ) : ?>
					<footer class="drivehr-job-card__footer">
						<button class="drivehr-job-card__button drivehr-job-card__button--apply"
								data-apply-url="<?php echo esc_url( $apply_url ); ?>"
								data-job-title="<?php echo esc_attr( $job->post_title ); ?>"
								aria-label="Apply for <?php echo esc_attr( $job->post_title ); ?>">
							Apply Now
							<svg class="drivehr-job-card__button-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
								<path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd" />
							</svg>
						</button>
					</footer>
				<?php endif; ?>
			</div>

			<?php if ( $use_wrapper ) : ?>
			<meta itemprop="datePosted" content="<?php echo esc_attr( get_the_date( 'c', $job_id ) ); ?>">
			<meta itemprop="hiringOrganization" content="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
			<?php endif; ?>

		<?php if ( $use_wrapper ) : ?>
		</article>
		<?php else : ?>
		</div>
		<?php endif; ?>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render placeholder when no job is selected
	 *
	 * @since 1.7.0
	 * @return string HTML for placeholder state
	 */
	protected function render_placeholder(): string {
		return '<div class="drivehr-job-card drivehr-job-card--placeholder">
			<div class="drivehr-job-card__content">
				<p class="drivehr-job-card__placeholder-text">
					<svg class="drivehr-job-card__placeholder-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
					</svg>
					Select a job to display in the block settings.
				</p>
			</div>
		</div>';
	}

	/**
	 * Render not found message
	 *
	 * @since 1.7.0
	 * @return string HTML for not found state
	 */
	protected function render_not_found(): string {
		return '<div class="drivehr-job-card drivehr-job-card--not-found">
			<div class="drivehr-job-card__content">
				<p class="drivehr-job-card__error-text">
					<svg class="drivehr-job-card__error-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
					</svg>
					The selected job could not be found or is no longer available.
				</p>
			</div>
		</div>';
	}
}
