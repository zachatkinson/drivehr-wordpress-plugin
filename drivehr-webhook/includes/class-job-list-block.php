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
	 * Convert spacing preset value to CSS value
	 *
	 * Maps preset identifiers (xs, sm, md, lg, etc.) to actual pixel values.
	 * If value is already a CSS value (contains px, rem, em, etc.), returns as-is.
	 *
	 * Spacing scale adapted from Kadence Blocks (GPL v2+)
	 *
	 * @since 1.8.5
	 * @param string $value Preset identifier or CSS value
	 * @return string CSS value with unit
	 */
	private function convert_spacing_preset( string $value ): string {
		// If already a CSS value (contains unit), return as-is
		if ( preg_match( '/\d+(px|rem|em|%|vh|vw)/', $value ) ) {
			return $value;
		}

		// Preset to pixel mapping
		$presets = array(
			'0'   => '0px',
			'xs'  => '8px',
			'sm'  => '16px',
			'md'  => '24px',
			'lg'  => '32px',
			'xl'  => '48px',
			'xxl' => '64px',
		);

		return isset( $presets[ $value ] ) ? $presets[ $value ] : '0px';
	}

	/**
	 * Enqueue frontend JavaScript and CSS
	 *
	 * Job list renders job cards, so it needs both the job-card JavaScript
	 * (for accordion functionality) and CSS (for card styling).
	 *
	 * Note: As of WordPress 6.5+, block styles are loaded on-demand only for
	 * blocks present on the page. Since job-list renders markup using job-card
	 * CSS classes, we must explicitly enqueue the job-card stylesheet.
	 *
	 * @since 1.7.0
	 * @since 1.9.2 Added explicit job-card stylesheet enqueue for WP 6.5+ compatibility
	 */
	public function enqueue_frontend_assets(): void {
		// Only enqueue if we have job list blocks on the page
		if ( ! has_block( 'drivehr/job-list' ) ) {
			return;
		}

		// Enqueue job-card styles (job-list renders job cards using these CSS classes)
		// Required since WP 6.5+ only loads styles for blocks explicitly on the page
		wp_enqueue_style(
			'drivehr-job-card-style',
			plugin_dir_url( dirname( __FILE__ ) ) . 'blocks/job-card/style.css',
			array(),
			DRIVEHR_WEBHOOK_VERSION
		);

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
		$posts_per_page = isset( $attributes['postsPerPage'] ) ? absint( $attributes['postsPerPage'] ) : 50;
		$orderby = isset( $attributes['orderBy'] ) ? sanitize_text_field( $attributes['orderBy'] ) : 'date';
		$order = isset( $attributes['order'] ) ? sanitize_text_field( $attributes['order'] ) : 'DESC';

		// Extract card-specific styling attributes
		$card_bg_color = isset( $attributes['cardBackgroundColor'] ) ? $attributes['cardBackgroundColor'] : '';
		$card_text_color = isset( $attributes['cardTextColor'] ) ? $attributes['cardTextColor'] : '';
		$card_title_color = isset( $attributes['cardTitleColor'] ) ? $attributes['cardTitleColor'] : '';
		$card_border_radius = isset( $attributes['cardBorderRadius'] ) ? $attributes['cardBorderRadius'] : '8px';
		$card_border = isset( $attributes['cardBorder'] ) ? $attributes['cardBorder'] : array( 'color' => '', 'style' => 'solid', 'width' => '0px' );
		$card_shadow = isset( $attributes['cardShadow'] ) ? $attributes['cardShadow'] : '';
		$card_padding = isset( $attributes['cardPadding'] ) ? $attributes['cardPadding'] : array( 'top' => 'md', 'right' => 'md', 'bottom' => 'md', 'left' => 'md' );

		// Build card styling classes and inline styles
		$card_classes = array( 'drivehr-job-card' );
		$card_styles = array();

		// Background color - WordPress color pickers return hex/rgb values
		if ( ! empty( $card_bg_color ) ) {
			$card_styles[] = 'background-color: ' . esc_attr( $card_bg_color );
		}

		// Text color - WordPress color pickers return hex/rgb values
		if ( ! empty( $card_text_color ) ) {
			$card_styles[] = 'color: ' . esc_attr( $card_text_color );
		}

		// Border
		if ( ! empty( $card_border_radius ) ) {
			$card_styles[] = 'border-radius: ' . esc_attr( $card_border_radius );
		}
		if ( is_array( $card_border ) && ! empty( $card_border['width'] ) && $card_border['width'] !== '0px' ) {
			$card_styles[] = 'border-width: ' . esc_attr( $card_border['width'] );
			$card_styles[] = 'border-style: ' . esc_attr( $card_border['style'] ?? 'solid' );
			if ( ! empty( $card_border['color'] ) ) {
				$card_styles[] = 'border-color: ' . esc_attr( $card_border['color'] );
			}
		}

		// Shadow
		if ( ! empty( $card_shadow ) ) {
			$card_styles[] = 'box-shadow: ' . esc_attr( $card_shadow );
		}

		// Padding - convert presets to CSS values
		if ( is_array( $card_padding ) ) {
			$padding_parts = array();
			$padding_parts[] = isset( $card_padding['top'] ) ? esc_attr( $this->convert_spacing_preset( $card_padding['top'] ) ) : '0';
			$padding_parts[] = isset( $card_padding['right'] ) ? esc_attr( $this->convert_spacing_preset( $card_padding['right'] ) ) : '0';
			$padding_parts[] = isset( $card_padding['bottom'] ) ? esc_attr( $this->convert_spacing_preset( $card_padding['bottom'] ) ) : '0';
			$padding_parts[] = isset( $card_padding['left'] ) ? esc_attr( $this->convert_spacing_preset( $card_padding['left'] ) ) : '0';
			$card_styles[] = 'padding: ' . implode( ' ', $padding_parts );
		} elseif ( ! empty( $card_padding ) ) {
			// Fallback for legacy string values
			$card_styles[] = 'padding: ' . esc_attr( $card_padding );
		}

		// Build card wrapper attributes string. Block attributes are editor-
		// controlled JSON, so run the assembled declarations through the same
		// CSS filter wp_kses applies to inline styles: it drops url() values,
		// expressions and any property outside the safe allowlist.
		$card_class_attr = implode( ' ', $card_classes );
		$safe_card_styles = ! empty( $card_styles ) ? safecss_filter_attr( implode( '; ', $card_styles ) ) : '';
		$card_style_attr = '' !== $safe_card_styles ? ' style="' . esc_attr( $safe_card_styles ) . '"' : '';

		// Query jobs
		$jobs = get_posts(
			array(
				'post_type'      => 'drivehr_job',
				'posts_per_page' => $posts_per_page,
				'post_status'    => 'publish',
				'orderby'        => $orderby,
				'order'          => $order,
			)
		);

		// Simple container wrapper (no background styling - that goes on cards)
		$wrapper_attributes = 'class="drivehr-job-list"';

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

					// Create wrapper attributes for each card with styling from block settings
					$card_wrapper = sprintf(
						'class="%s" data-job-id="%s" data-external-id="%s" itemscope itemtype="https://schema.org/JobPosting"%s',
						esc_attr( $card_class_attr ),
						esc_attr( $job->ID ),
						esc_attr( $external_id ),
						$card_style_attr
					);

					echo $this->render_job_card(
						$job->ID,
						array(
							'show_location'       => $show_location,
							'show_job_type'       => $show_job_type,
							'use_wrapper'         => true,
							'wrapper_attributes'  => $card_wrapper,
							'title_color'         => $card_title_color,
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
