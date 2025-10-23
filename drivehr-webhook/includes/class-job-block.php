<?php
/**
 * DriveHR Job Card Gutenberg Block
 *
 * Registers and renders the Job Card Gutenberg block for displaying individual
 * job postings with modern, accessible design patterns.
 *
 * @package DriveHR_Webhook
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Job Card Block Handler
 *
 * Handles registration and server-side rendering of the Job Card Gutenberg block.
 * Provides dynamic content rendering with proper escaping and accessibility features.
 *
 * Uses singleton pattern to prevent duplicate block registrations and hook conflicts.
 *
 * @since 1.0.0
 * @since 1.6.0 Implemented singleton pattern to fix "Block type already registered" errors
 */
class DriveHR_Job_Block {

	/**
	 * Single instance of the class
	 *
	 * @since 1.6.0
	 * @var DriveHR_Job_Block|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * Ensures only one instance of the block handler exists per request,
	 * preventing duplicate hook registrations and block registration conflicts.
	 *
	 * @since 1.6.0
	 * @return DriveHR_Job_Block
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize the block
	 *
	 * Private constructor prevents direct instantiation.
	 * Use DriveHR_Job_Block::get_instance() instead.
	 *
	 * @since 1.0.0
	 * @since 1.6.0 Changed to private constructor for singleton pattern
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_theme_styles' ) );
	}

	/**
	 * Prevent cloning of the instance
	 *
	 * @since 1.6.0
	 */
	private function __clone() {}

	/**
	 * Prevent unserialization of the instance
	 *
	 * @since 1.6.0
	 * @throws Exception
	 */
	public function __wakeup() {
		throw new Exception( 'Cannot unserialize singleton' );
	}

	/**
	 * Enqueue frontend JavaScript and CSS
	 *
	 * @since 1.3.0
	 */
	public function enqueue_frontend_assets(): void {
		// Only enqueue if we have job card blocks on the page
		if ( ! has_block( 'drivehr/job-card' ) ) {
			return;
		}

		// Enqueue frontend JavaScript
		wp_enqueue_script(
			'drivehr-job-card-frontend',
			plugin_dir_url( dirname( __FILE__ ) ) . 'blocks/job-card/frontend.js',
			array(),
			DRIVEHR_WEBHOOK_VERSION,
			true
		);
	}

	/**
	 * Enqueue theme styles in block editor
	 *
	 * Loads active theme's stylesheet in the Gutenberg editor so ServerSideRender
	 * blocks display with proper theme styling that matches the frontend.
	 *
	 * This solves the common issue where blocks look different in editor vs frontend
	 * when using minimal block styles that rely on theme styling.
	 *
	 * @since 1.5.1
	 * @return void
	 */
	public function enqueue_editor_theme_styles(): void {
		// Get the active theme's stylesheet URL
		$theme_stylesheet = get_stylesheet_uri();

		// Enqueue the theme's main stylesheet in the editor
		// This ensures ServerSideRender blocks display with theme styles
		wp_enqueue_style(
			'drivehr-editor-theme-styles',
			$theme_stylesheet,
			array(),
			wp_get_theme()->get('Version')
		);
	}

	/**
	 * Register the Gutenberg block
	 *
	 * Includes guard against duplicate registration to prevent
	 * "Block type already registered" errors.
	 *
	 * @since 1.0.0
	 * @since 1.6.0 Added registration guard for stability
	 */
	public function register_block(): void {
		// Guard against duplicate registration
		if ( WP_Block_Type_Registry::get_instance()->is_registered( 'drivehr/job-card' ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[DriveHR Block] Block already registered, skipping duplicate registration' );
			}
			return;
		}

		// Register block assets.
		register_block_type(
			plugin_dir_path( dirname( __FILE__ ) ) . 'blocks/job-card',
			array(
				'render_callback' => array( $this, 'render_block' ),
			)
		);
	}

	/**
	 * Get all available jobs for the block editor
	 *
	 * @since 1.0.0
	 * @return array Array of job objects with id, title, and status
	 */
	public function get_available_jobs(): array {
		$jobs = get_posts(
			array(
				'post_type'      => 'drivehr_job',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		return array_map(
			function ( $job ) {
				return array(
					'id'    => $job->ID,
					'title' => $job->post_title,
				);
			},
			$jobs
		);
	}

	/**
	 * Server-side rendering of the block
	 *
	 * @since 1.0.0
	 * @since 1.6.2 Added block wrapper attributes for style support
	 * @param array $attributes Block attributes from the editor.
	 * @param string $content Block content (unused for server-side rendered blocks).
	 * @param WP_Block $block Block instance.
	 * @return string Rendered HTML output
	 */
	public function render_block( array $attributes, string $content = '', $block = null ): string {
		$job_id = isset( $attributes['jobId'] ) ? absint( $attributes['jobId'] ) : 0;

		if ( ! $job_id ) {
			return $this->render_placeholder();
		}

		$job = get_post( $job_id );

		if ( ! $job || 'drivehr_job' !== $job->post_type || 'publish' !== $job->post_status ) {
			return $this->render_not_found();
		}

		// Get job metadata (no prefix - stored directly).
		$location     = get_post_meta( $job_id, 'location', true );
		$job_type     = get_post_meta( $job_id, 'job_type', true );
		$pay_type     = get_post_meta( $job_id, 'salary_range', true );
		$posted_date  = get_post_meta( $job_id, 'posted_date', true );
		$apply_url    = get_post_meta( $job_id, 'apply_url', true );
		$external_id  = get_post_meta( $job_id, 'job_id', true );

		// Get display preferences.
		$show_excerpt = isset( $attributes['showExcerpt'] ) ? (bool) $attributes['showExcerpt'] : true;
		$show_location = isset( $attributes['showLocation'] ) ? (bool) $attributes['showLocation'] : true;
		$show_job_type = isset( $attributes['showJobType'] ) ? (bool) $attributes['showJobType'] : true;
		$button_text  = isset( $attributes['buttonText'] ) ? sanitize_text_field( $attributes['buttonText'] ) : 'Apply Now';
		$button_style = isset( $attributes['buttonStyle'] ) ? sanitize_text_field( $attributes['buttonStyle'] ) : 'primary';

		// Generate block wrapper attributes (applies background, padding, etc. from block settings).
		$wrapper_attributes = get_block_wrapper_attributes(
			array(
				'class'                => 'drivehr-job-card',
				'data-job-id'          => esc_attr( $job_id ),
				'data-external-id'     => esc_attr( $external_id ),
				'itemscope'            => true,
				'itemtype'             => 'https://schema.org/JobPosting',
			)
		);

		// Start output buffering.
		ob_start();
		?>
		<article <?php echo $wrapper_attributes; ?>>

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

			<meta itemprop="datePosted" content="<?php echo esc_attr( get_the_date( 'c', $job_id ) ); ?>">
			<meta itemprop="hiringOrganization" content="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
		</article>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render placeholder when no job is selected
	 *
	 * @since 1.0.0
	 * @return string HTML for placeholder state
	 */
	private function render_placeholder(): string {
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
	 * @since 1.0.0
	 * @return string HTML for not found state
	 */
	private function render_not_found(): string {
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

// Singleton pattern - initialization handled by main plugin file
// Do NOT instantiate here - causes double instantiation bug
