<?php
/**
 * DriveHR Job List Gutenberg Block
 *
 * Registers and renders the Job List Gutenberg block for displaying all
 * available job postings in a modern, accessible list format.
 *
 * @package DriveHR_Webhook
 * @since 1.7.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Job List Block Handler
 *
 * Handles registration and server-side rendering of the Job List Gutenberg block.
 * Queries all published job postings and renders them using shared job card markup.
 *
 * Uses singleton pattern to prevent duplicate block registrations and hook conflicts.
 * Uses shared rendering trait for DRY principle with job-card block.
 *
 * @since 1.7.0
 */
class DriveHR_Job_List_Block {
	use DriveHR_Job_Card_Renderer_Trait;

	/**
	 * Single instance of the class
	 *
	 * @since 1.7.0
	 * @var DriveHR_Job_List_Block|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * Ensures only one instance of the block handler exists per request,
	 * preventing duplicate hook registrations and block registration conflicts.
	 *
	 * @since 1.7.0
	 * @return DriveHR_Job_List_Block
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
	 * Use DriveHR_Job_List_Block::get_instance() instead.
	 *
	 * @since 1.7.0
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_theme_styles' ) );
	}

	/**
	 * Prevent cloning of the instance
	 *
	 * @since 1.7.0
	 */
	private function __clone() {}

	/**
	 * Prevent unserialization of the instance
	 *
	 * @since 1.7.0
	 * @throws Exception
	 */
	public function __wakeup() {
		throw new Exception( 'Cannot unserialize singleton' );
	}

	/**
	 * Enqueue frontend JavaScript and CSS
	 *
	 * @since 1.7.0
	 */
	public function enqueue_frontend_assets(): void {
		// Only enqueue if we have job list blocks on the page
		if ( ! has_block( 'drivehr/job-list' ) ) {
			return;
		}

		// Enqueue frontend JavaScript (shares same accordion logic as job-card)
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
	 * @since 1.7.0
	 * @return void
	 */
	public function enqueue_editor_theme_styles(): void {
		// Get the active theme's stylesheet URL
		$theme_stylesheet = get_stylesheet_uri();

		// Enqueue the theme's main stylesheet in the editor
		wp_enqueue_style(
			'drivehr-editor-theme-styles',
			$theme_stylesheet,
			array(),
			wp_get_theme()->get( 'Version' )
		);
	}

	/**
	 * Register the Gutenberg block
	 *
	 * Includes guard against duplicate registration to prevent
	 * "Block type already registered" errors.
	 *
	 * @since 1.7.0
	 */
	public function register_block(): void {
		// Guard against duplicate registration
		if ( WP_Block_Type_Registry::get_instance()->is_registered( 'drivehr/job-list' ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[DriveHR Block] Job list block already registered, skipping duplicate registration' );
			}
			return;
		}

		// Register block assets.
		register_block_type(
			plugin_dir_path( dirname( __FILE__ ) ) . 'blocks/job-list',
			array(
				'render_callback' => array( $this, 'render_block' ),
			)
		);
	}

	/**
	 * Server-side rendering of the block
	 *
	 * Queries all published jobs and renders them using shared job card markup.
	 *
	 * @since 1.7.0
	 * @param array $attributes Block attributes from the editor.
	 * @param string $content Block content (unused for server-side rendered blocks).
	 * @param WP_Block $block Block instance.
	 * @return string Rendered HTML output
	 */
	public function render_block( array $attributes, string $content = '', $block = null ): string {
		// Get display preferences
		$show_location = isset( $attributes['showLocation'] ) ? (bool) $attributes['showLocation'] : true;
		$show_job_type = isset( $attributes['showJobType'] ) ? (bool) $attributes['showJobType'] : true;
		$posts_per_page = isset( $attributes['postsPerPage'] ) ? absint( $attributes['postsPerPage'] ) : -1;
		$orderby = isset( $attributes['orderBy'] ) ? sanitize_text_field( $attributes['orderBy'] ) : 'date';
		$order = isset( $attributes['order'] ) ? sanitize_text_field( $attributes['order'] ) : 'DESC';

		// Query jobs
		$jobs = get_posts(
			array(
				'post_type'      => 'drivehr_job',
				'posts_per_page' => $posts_per_page === -1 ? -1 : $posts_per_page,
				'post_status'    => 'publish',
				'orderby'        => $orderby,
				'order'          => $order,
			)
		);

		// Generate block wrapper attributes (applies background, padding, etc. from block settings).
		$wrapper_attributes = get_block_wrapper_attributes(
			array(
				'class' => 'drivehr-job-list',
			)
		);

		// Start output buffering.
		ob_start();
		?>
		<div <?php echo $wrapper_attributes; ?>>
			<?php if ( empty( $jobs ) ) : ?>
				<?php echo $this->render_empty_state(); ?>
			<?php else : ?>
				<?php foreach ( $jobs as $job ) : ?>
					<?php
					// Get job metadata for wrapper attributes
					$external_id = get_post_meta( $job->ID, 'job_id', true );

					// Create wrapper attributes for each card (NOT using get_block_wrapper_attributes)
					$card_wrapper = sprintf(
						'class="drivehr-job-card" data-job-id="%s" data-external-id="%s" itemscope itemtype="https://schema.org/JobPosting"',
						esc_attr( $job->ID ),
						esc_attr( $external_id )
					);

					echo $this->render_job_card(
						$job->ID,
						array(
							'show_location'       => $show_location,
							'show_job_type'       => $show_job_type,
							'use_wrapper'         => true,
							'wrapper_attributes'  => $card_wrapper,
						)
					);
					?>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<?php
		// Clean up post data
		wp_reset_postdata();

		return ob_get_clean();
	}

	/**
	 * Render empty state when no jobs are available
	 *
	 * @since 1.7.0
	 * @return string HTML for empty state
	 */
	private function render_empty_state(): string {
		return '<div class="drivehr-job-list__empty">
			<svg class="drivehr-job-list__empty-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
			</svg>
			<p class="drivehr-job-list__empty-text">No job openings are currently available. Please check back soon!</p>
		</div>';
	}
}

// Singleton pattern - initialization handled by main plugin file
// Do NOT instantiate here - causes double instantiation bug
