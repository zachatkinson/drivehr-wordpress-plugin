<?php
/**
 * REST API Caching for DriveHR Jobs
 *
 * Implements WordPress Transients API best practices for caching REST API responses.
 * Dramatically improves Gutenberg block editor performance by caching job list queries.
 *
 * Based on WordPress documentation:
 * - https://developer.wordpress.org/apis/transients/
 * - https://developer.wordpress.org/rest-api/using-the-rest-api/
 *
 * @package DriveHR_Webhook
 * @since 1.5.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

/**
 * REST API Response Caching Handler
 *
 * Provides transient-based caching for REST API responses to optimize
 * Gutenberg block editor performance. Implements automatic cache invalidation
 * when job data changes.
 *
 * Uses singleton pattern to prevent duplicate hook registrations.
 *
 * Performance Impact:
 * - First load: Same speed (cache miss)
 * - Subsequent loads: Near-instant (served from transient cache)
 * - Cache duration: 12 hours (WordPress best practice for stable data)
 *
 * @since 1.5.0
 * @since 1.6.0 Implemented singleton pattern
 */
class DriveHR_REST_API_Cache {

    /**
     * Single instance of the class
     *
     * @since 1.6.0
     * @var DriveHR_REST_API_Cache|null
     */
    private static $instance = null;

    /**
     * Cache key prefix for namespacing
     *
     * @since 1.5.0
     * @var string
     */
    private const CACHE_PREFIX = 'drivehr_jobs_rest_';

    /**
     * Cache expiration time in seconds (12 hours)
     *
     * Per WordPress best practices, 12 hours is appropriate for
     * moderately stable data that changes infrequently.
     *
     * @since 1.5.0
     * @var int
     */
    private const CACHE_EXPIRATION = 12 * HOUR_IN_SECONDS;

    /**
     * Get singleton instance
     *
     * @since 1.6.0
     * @return DriveHR_REST_API_Cache
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize the caching system
     *
     * Private constructor prevents direct instantiation.
     * Use DriveHR_REST_API_Cache::get_instance() instead.
     *
     * @since 1.5.0
     * @since 1.6.0 Changed to private constructor for singleton pattern
     */
    private function __construct() {
        // Cache REST API responses
        add_filter('rest_pre_dispatch', array($this, 'maybe_serve_cached_response'), 10, 3);
        add_filter('rest_post_dispatch', array($this, 'maybe_cache_response'), 10, 3);

        // Invalidate cache on data changes
        add_action('save_post_drivehr_job', array($this, 'clear_cache'));
        add_action('delete_post', array($this, 'clear_cache_on_delete'), 10, 1);
        add_action('drivehr_after_job_sync', array($this, 'clear_cache'));

        // Optimize REST queries
        add_filter('rest_drivehr_job_query', array($this, 'optimize_rest_query'), 10, 2);

        // Add debug logging if WP_DEBUG is enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_action('drivehr_cache_hit', array($this, 'log_cache_hit'));
            add_action('drivehr_cache_miss', array($this, 'log_cache_miss'));
            add_action('drivehr_cache_cleared', array($this, 'log_cache_clear'));
        }
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
        throw new Exception('Cannot unserialize singleton');
    }

    /**
     * Check if request should be cached
     *
     * Only cache GET requests to the drivehr-jobs endpoint with
     * limited field selection (id,title).
     *
     * @since 1.5.0
     * @param WP_REST_Request $request Current request object
     * @return bool Whether request should be cached
     */
    private function should_cache_request($request): bool {
        // Only cache GET requests
        if ($request->get_method() !== 'GET') {
            return false;
        }

        // Only cache list requests (not individual jobs)
        $route = $request->get_route();
        if (strpos($route, '/wp/v2/drivehr-jobs') === false) {
            return false;
        }

        // Don't cache individual job requests (route ends with /\d+)
        if (preg_match('/\/\d+$/', $route)) {
            return false;
        }

        return true;
    }

    /**
     * Generate cache key from request parameters
     *
     * Creates a unique cache key based on request parameters to ensure
     * different queries get different cache entries.
     *
     * @since 1.5.0
     * @param WP_REST_Request $request Current request object
     * @return string Cache key
     */
    private function get_cache_key($request): string {
        $params = $request->get_query_params();

        // Remove unnecessary parameters that don't affect response
        unset($params['_locale']);
        unset($params['_wpnonce']);

        // Sort parameters for consistent cache keys
        ksort($params);

        return self::CACHE_PREFIX . md5(serialize($params));
    }

    /**
     * Try to serve cached response
     *
     * Checks if a valid cached response exists for the current request.
     * If found, returns it immediately to bypass WordPress query overhead.
     *
     * @since 1.5.0
     * @param mixed $result Response to replace the requested version with
     * @param WP_REST_Server $server Server instance
     * @param WP_REST_Request $request Request used to generate the response
     * @return mixed Original result or cached response
     */
    public function maybe_serve_cached_response($result, $server, $request) {
        if (!$this->should_cache_request($request)) {
            return $result;
        }

        $cache_key = $this->get_cache_key($request);
        $cached = get_transient($cache_key);

        if (false !== $cached) {
            do_action('drivehr_cache_hit', $cache_key);
            return rest_ensure_response($cached);
        }

        do_action('drivehr_cache_miss', $cache_key);
        return $result;
    }

    /**
     * Cache successful responses
     *
     * Stores successful REST API responses in transient cache for
     * fast retrieval on subsequent requests.
     *
     * @since 1.5.0
     * @param WP_HTTP_Response $result Result to send to the client
     * @param WP_REST_Server $server Server instance
     * @param WP_REST_Request $request Request used to generate the response
     * @return WP_HTTP_Response Unmodified result
     */
    public function maybe_cache_response($result, $server, $request) {
        if (!$this->should_cache_request($request)) {
            return $result;
        }

        // Only cache successful responses (200 status)
        if ($result->get_status() !== 200) {
            return $result;
        }

        $cache_key = $this->get_cache_key($request);
        $data = $result->get_data();

        set_transient($cache_key, $data, self::CACHE_EXPIRATION);

        return $result;
    }

    /**
     * Optimize REST API queries for job listings
     *
     * When only id and title are requested, skip loading unnecessary
     * data like post meta and term caches.
     *
     * @since 1.5.0
     * @param array $args Query arguments
     * @param WP_REST_Request $request Current request
     * @return array Modified query arguments
     */
    public function optimize_rest_query($args, $request): array {
        $fields = $request->get_param('_fields');

        // Optimize when only id/title requested (common for dropdowns)
        if ($fields && strpos($fields, 'id,title') !== false) {
            $args['no_found_rows'] = true;           // Skip pagination count query
            $args['update_post_meta_cache'] = false; // Skip meta cache
            $args['update_post_term_cache'] = false; // Skip taxonomy cache
        }

        return $args;
    }

    /**
     * Clear all cached REST API responses
     *
     * Deletes all transients matching the DriveHR jobs REST API cache pattern.
     * Called when jobs are created, updated, or deleted to ensure fresh data.
     *
     * Per WordPress best practices, uses direct database query for pattern matching
     * since WordPress doesn't provide a native way to delete multiple transients.
     *
     * @since 1.5.0
     * @return void
     */
    public function clear_cache(): void {
        global $wpdb;

        // Delete all transients and their timeout values
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options}
                 WHERE option_name LIKE %s
                 OR option_name LIKE %s",
                '_transient_' . self::CACHE_PREFIX . '%',
                '_transient_timeout_' . self::CACHE_PREFIX . '%'
            )
        );

        do_action('drivehr_cache_cleared');
    }

    /**
     * Clear cache when a post is deleted
     *
     * Only clears cache if the deleted post is a DriveHR job.
     *
     * @since 1.5.0
     * @param int $post_id Post ID being deleted
     * @return void
     */
    public function clear_cache_on_delete($post_id): void {
        if (get_post_type($post_id) === 'drivehr_job') {
            $this->clear_cache();
        }
    }

    /**
     * Log cache hit for debugging
     *
     * @since 1.5.0
     * @param string $cache_key Cache key that was hit
     * @return void
     */
    public function log_cache_hit($cache_key): void {
        error_log("[DriveHR Cache] HIT: {$cache_key}");
    }

    /**
     * Log cache miss for debugging
     *
     * @since 1.5.0
     * @param string $cache_key Cache key that was missed
     * @return void
     */
    public function log_cache_miss($cache_key): void {
        error_log("[DriveHR Cache] MISS: {$cache_key}");
    }

    /**
     * Log cache clear for debugging
     *
     * @since 1.5.0
     * @return void
     */
    public function log_cache_clear(): void {
        error_log('[DriveHR Cache] CLEARED: All job REST API caches invalidated');
    }
}
