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
	 *                      - 'title_color' (string) CSS color value for job title. Optional.
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
		$expiry_date  = get_post_meta( $job_id, 'expiry_date', true );
		$apply_url    = get_post_meta( $job_id, 'apply_url', true );
		$external_id  = get_post_meta( $job_id, 'job_id', true );

		// Parse options with defaults.
		$show_excerpt      = isset( $options['show_excerpt'] ) ? (bool) $options['show_excerpt'] : true;
		$show_location     = isset( $options['show_location'] ) ? (bool) $options['show_location'] : true;
		$show_job_type     = isset( $options['show_job_type'] ) ? (bool) $options['show_job_type'] : true;
		$use_wrapper       = isset( $options['use_wrapper'] ) ? (bool) $options['use_wrapper'] : true;
		$wrapper_attrs     = isset( $options['wrapper_attributes'] ) ? $options['wrapper_attributes'] : '';
		$title_color       = isset( $options['title_color'] ) ? $options['title_color'] : '';

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
				<!-- Header + Actions Container (Side by Side) -->
				<div class="drivehr-job-card__header-wrapper">
					<header class="drivehr-job-card__header">
						<h3 class="drivehr-job-card__title" itemprop="title"<?php echo ! empty( $title_color ) ? ' style="color: ' . esc_attr( $title_color ) . '"' : ''; ?>>
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

					<!-- Action Buttons (Right Side) -->
					<div class="drivehr-job-card__actions">
						<?php if ( $apply_url ) : ?>
							<button class="drivehr-job-card__button drivehr-job-card__button--apply"
									data-apply-url="<?php echo esc_url( $apply_url ); ?>"
									data-job-title="<?php echo esc_attr( $job->post_title ); ?>"
									aria-label="Apply for <?php echo esc_attr( $job->post_title ); ?>">
								<svg class="drivehr-job-card__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="currentColor" aria-hidden="true">
									<path d="M395.16 0h0q15.27 1.21 23.63 13.63 5.15 7.66 5.17 15.65.07 49.92.04 105.51c-.01 6.07-.64 10.89-6.31 13.05-6.46 2.47-11.2-2.78-11.2-2.78-2.42-2.67-2.43-6.09-2.43-9.51-.08-13.39-.06-105.16-.06-105.16 0-5.92-.53-9.58-5.17-12.52-2.05-1.3-10.41-1.3-10.41-1.3-223.58.02-355.26 0-355.26 0-5.55 0-9.08.8-11.85 5.17-1.32 2.08-1.32 9.95-1.32 9.95.02 278.58 0 443.78 0 443.78 0 5.35.79 9.07 5.1 11.75 2.2 1.37 9.7 1.37 9.7 1.37 174.43-.02 355.81-.01 355.81-.01 5.09 0 6.72-.67 12.72-3.21 6.15-2.54 6.74-7.08 6.72-14.19-.07-18.09 0-64.85 0-64.85 0-5.93.5-10.36 5.74-12.83 5.22-2.45 11.31-.1 13.47 5.23.76 1.9.78 6.83.78 6.83.07 26.92-.05 69.98-.05 69.98-.05 16.6-12.15 29.21-28.65 30.5h-366.53q-21.39-2.34-27.59-22.15c-.64-2.06-.77-4.31-1.17-6.54V28.77c1.29-15.92 12.61-27.38 28.67-28.77h366.49z"/>
									<path d="M303.93 146a91.92 91.92 0 11-183.84 0 91.92 91.92 0 11183.84 0zm-91.93-46.45c15.01 0 28.34 9.47 33.69 23.57 2.24 5.89 2.45 12.19 2.28 18.89-.29 12.25-7.14 21.36-7.14 21.36s3.75 1.69 9.89 5.57c10.86 7.3 18.57 18.34 18.57 18.34s10.21-14.68 12.62-30.06c4.04-25.74-7.95-46.98-7.95-46.98-12.8-22.7-36.41-37.08-62.88-37.07-26.47.01-50.07 14.4-62.87 37.09s-7.94 46.98-7.94 46.98c2.41 15.38 12.62 30.06 12.62 30.06s7.71-11.04 18.57-18.34c3.38-2.27 7.14-3.88 9.89-5.57s-.83-9.12-7.68-18.23c0 0-6.85-9.11-7.15-21.36-.17-6.7.04-12.99 2.28-18.88 5.34-14.1 18.67-23.57 33.68-23.58zm-16-38.8c-26.47.01-50.07 14.4-62.87 37.09s-7.94 46.98-7.94 46.98c2.41 15.38 12.62 30.06 12.62 30.06s7.71-11.04 18.57-18.34c3.38-2.27 7.14-3.88 9.89-5.57s-.83-9.12-7.68-18.23c0 0-6.85-9.11-7.15-21.36-.17-6.7.04-12.99 2.28-18.88 5.34-14.1 18.67-23.57 33.68-23.58 15.01 0 28.34 9.47 33.69 23.57 2.24 5.89 2.45 12.19 2.28 18.89-.29 12.25-7.14 21.36-7.14 21.36s3.75 1.69 9.89 5.57c10.86 7.3 18.57 18.34 18.57 18.34s10.21-14.68 12.62-30.06c4.04-25.74-7.95-46.98-7.95-46.98-12.8-22.7-36.41-37.08-62.88-37.07zm-16 38.8c0 4.81.35 6.39.35 6.39 1.69 7.49 7.94 12.81 15.65 12.81s13.96-5.32 15.64-12.81c0 0 .36-1.59.36-6.39s-.35-6.39-.35-6.39c-1.69-7.49-7.93-12.81-15.64-12.81s-13.96 5.33-15.65 12.82c0 0-.36 1.58-.36 6.38zm16 79.22c15.5 0 30.79-5.34 43.15-14.53 0 0-.87-16.37-12.92-25.85-14.34-8.52-30.88-18.01-48.7-18.01s-34.36 9.49-48.7 18.01c-12.05 9.48-12.92 25.85-12.92 25.85 12.35 9.19 27.64 14.53 43.15 14.53z"/>
									<path d="M490.97 130.35l.12.06q15.25 9.33 19.53 26.39 2.43 9.65-.1 20.57-1.37 5.87-8.93 18.67-3.42 5.79-120.1 207.98c-1.09 1.9-2.03 2.75-3.21 4.22 0 0-.53.55-1.11 1.1-3.45 2.53-60.68 44.71-60.68 44.71-4.88 3.6-8.99 6.24-14.4 3.12s-4.5-7.99-3.82-14.01c7.91-70.66 8.37-74.91 8.37-74.91s.17-.78.36-1.56c.68-1.75.95-2.99 2.05-4.89l119.74-202.15c7.3-12.94 11.7-17.06 11.7-17.06 8.19-7.66 17.76-10.38 17.76-10.38 16.92-4.82 32.62 3.72 32.72 3.79zm-49.46 42.67l37.27 21.52s8.67-16.33 8.67-16.33a22.31 22 0 00-7.9-30.32h-.01a22.31 22 0 00-30.21 8.32l-9.43 16.33s.18.67.61.48zm27.48 39.43a.42.42 0 00-.15-.57l-37.38-21.58a.42.42 0 00-.57.15l-100.56 174.18a.42.42 0 00.15.57l37.38 21.58a.42.42 0 00.57-.16l100.56-174.17zm-115.73 189.5s-.03-.5-.03-.5l-28.15-16.25s-.45.22-.45.22l-4.5 40.3s.48.28.48.28l32.65-24.05z"/>
									<circle cx="81.3" cy="280" r="9.86"/>
									<rect x="114.16" y="270.02" width="161.68" height="19.96" rx="9.85"/>
									<circle cx="81.3" cy="336" r="9.86"/>
									<rect x="114.16" y="326.02" width="161.68" height="19.96" rx="9.85"/>
									<circle cx="81.3" cy="392" r="9.86"/>
									<rect x="114.16" y="382.02" width="161.68" height="19.96" rx="9.85"/>
									<rect x="202.19" y="438.05" width="73.62" height="19.9" rx="9.68"/>
								</svg>
								<span>Apply Now</span>
							</button>
						<?php endif; ?>

						<button class="drivehr-job-card__button drivehr-job-card__button--learn-more"
								aria-expanded="false"
								aria-controls="job-details-<?php echo esc_attr( $job_id ); ?>">
							<span>Learn More</span>
							<svg class="drivehr-job-card__icon drivehr-job-card__icon--chevron" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 185.343 185.343" fill="currentColor" aria-hidden="true">
								<path d="M51.707 185.343c-2.741 0-5.493-1.044-7.593-3.149-4.194-4.194-4.194-10.981 0-15.175l74.352-74.347L44.114 18.32c-4.194-4.194-4.194-10.987 0-15.175 4.194-4.194 10.987-4.194 15.18 0l81.934 81.934c4.194 4.194 4.194 10.987 0 15.175l-81.934 81.939c-2.093 2.094-4.84 3.144-7.587 3.144z"/>
							</svg>
						</button>
					</div>
				</div>

				<!-- Job Details (Accordion Content) -->
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

			<?php if ( $use_wrapper ) : ?>
			<!-- Enhanced Schema.org JobPosting structured data -->
			<meta itemprop="datePosted" content="<?php echo esc_attr( get_the_date( 'c', $job_id ) ); ?>">
			<?php if ( $expiry_date ) : ?>
				<meta itemprop="validThrough" content="<?php echo esc_attr( gmdate( 'c', strtotime( $expiry_date ) ) ); ?>">
			<?php endif; ?>

			<div itemprop="hiringOrganization" itemscope itemtype="https://schema.org/Organization" style="display:none;">
				<meta itemprop="name" content="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
				<meta itemprop="url" content="<?php echo esc_url( home_url() ); ?>">
				<?php if ( has_custom_logo() ) : ?>
					<meta itemprop="logo" content="<?php echo esc_url( wp_get_attachment_image_url( get_theme_mod( 'custom_logo' ), 'full' ) ); ?>">
				<?php endif; ?>
			</div>

			<?php if ( $pay_type ) : ?>
				<div itemprop="baseSalary" itemscope itemtype="https://schema.org/MonetaryAmount" style="display:none;">
					<meta itemprop="value" content="<?php echo esc_attr( $pay_type ); ?>">
					<meta itemprop="currency" content="USD">
				</div>
			<?php endif; ?>
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
