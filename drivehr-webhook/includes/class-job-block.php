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
 * @since 1.7.0 Uses shared job card rendering trait for DRY principle
 */
class DriveHR_Job_Block {
	use DriveHR_Job_Card_Renderer_Trait;

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
	 * @since 1.7.0 Refactored to use shared trait for DRY principle
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

		// Get external ID for wrapper attributes.
		$external_id = get_post_meta( $job_id, 'job_id', true );

		// Get display preferences.
		$show_excerpt  = isset( $attributes['showExcerpt'] ) ? (bool) $attributes['showExcerpt'] : true;
		$show_location = isset( $attributes['showLocation'] ) ? (bool) $attributes['showLocation'] : true;
		$show_job_type = isset( $attributes['showJobType'] ) ? (bool) $attributes['showJobType'] : true;

		// Generate block wrapper attributes (applies background, padding, etc. from block settings).
		$wrapper_attributes = get_block_wrapper_attributes(
			array(
				'class'            => 'drivehr-job-card',
				'data-job-id'      => esc_attr( $job_id ),
				'data-external-id' => esc_attr( $external_id ),
				'itemscope'        => true,
				'itemtype'         => 'https://schema.org/JobPosting',
			)
		);

		// Use shared trait method for rendering.
		return $this->render_job_card(
			$job_id,
			array(
				'show_excerpt'        => $show_excerpt,
				'show_location'       => $show_location,
				'show_job_type'       => $show_job_type,
				'wrapper_attributes'  => $wrapper_attributes,
				'use_wrapper'         => true,
			)
		);
	}
}

// Singleton pattern - initialization handled by main plugin file
// Do NOT instantiate here - causes double instantiation bug
