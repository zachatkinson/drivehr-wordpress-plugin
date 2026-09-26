<?php
/**
 * DriveHR Feed Sync Class
 *
 * Pull-based job synchronization: fetches the HMAC-signed job feed
 * published by the GitHub Actions scraper to the repository's `job-data`
 * branch, verifies its authenticity, and processes it through the shared
 * sync engine on a WP-Cron schedule.
 *
 * This replaces the push webhook as the primary sync mechanism. Outbound
 * requests from WordPress are never subject to host-level bot protection
 * (e.g. Imunify360 splash screens), which intermittently blocked inbound
 * webhook deliveries from shared CI runner IPs.
 *
 * Security model:
 * - The feed is signed with HMAC-SHA256 using the shared secret
 *   (DRIVEHR_WEBHOOK_SECRET). The signature covers the exact bytes of
 *   the feed file, verified with a timing-safe comparison. Even with
 *   write access to the repository, an attacker cannot forge a feed.
 * - Feeds older than the staleness window are rejected to avoid acting
 *   on abandoned data.
 * - An empty feed is refused while jobs exist locally unless explicitly
 *   allowed, preventing accidental mass deletion.
 *
 * @package DriveHR
 * @since 2.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

/**
 * DriveHR Feed Sync Class
 *
 * Schedules and executes pull-based synchronization of the signed job
 * feed. Uses singleton pattern to prevent duplicate hook registrations.
 *
 * @since 2.2.0
 */
class DriveHR_Feed_Sync {

    /**
     * Single instance of the class
     *
     * @since 2.2.0
     * @var DriveHR_Feed_Sync|null
     */
    private static $instance = null;

    /**
     * WP-Cron hook name for the scheduled feed sync
     *
     * @since 2.2.0
     */
    public const CRON_HOOK = 'drivehr_feed_sync';

    /**
     * Default feed URL on the repository's job-data branch
     *
     * Override with DRIVEHR_FEED_URL in wp-config.php.
     *
     * @since 2.2.0
     */
    private const DEFAULT_FEED_URL = 'https://raw.githubusercontent.com/zachatkinson/drivehr-netlify-sync/job-data/jobs.json';

    /**
     * HTTP timeout for feed fetches in seconds
     *
     * @since 2.2.0
     */
    private const FETCH_TIMEOUT = 15;

    /**
     * Maximum acceptable feed age in seconds (7 days)
     *
     * A feed older than this indicates the scraper pipeline is broken;
     * acting on it risks syncing abandoned data.
     *
     * @since 2.2.0
     */
    private const MAX_FEED_AGE = 604800;

    /**
     * Option name storing the hash of the last successfully applied feed
     *
     * @since 2.2.0
     */
    private const OPTION_LAST_HASH = 'drivehr_feed_last_hash';

    /**
     * Option name storing metadata about the last sync run
     *
     * @since 2.2.0
     */
    private const OPTION_LAST_SYNC = 'drivehr_feed_last_sync';

    /**
     * Maximum feed size accepted from the remote host in bytes (4 MiB)
     *
     * Passed as limit_response_size so an oversized or hostile response is
     * truncated by the HTTP API instead of being buffered in full.
     *
     * @since 2.3.0
     */
    private const MAX_FEED_BYTES = 4194304;

    /**
     * Transient that prevents overlapping sync runs (cron + manual button)
     *
     * @since 2.3.0
     */
    private const LOCK_TRANSIENT = 'drivehr_feed_sync_lock';

    /**
     * How long a sync lock is held before it is considered abandoned
     *
     * @since 2.3.0
     */
    private const LOCK_TTL = 180;

    /**
     * Get singleton instance
     *
     * @since 2.2.0
     * @return DriveHR_Feed_Sync
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize feed sync
     *
     * Registers the cron callback, schedule self-healing, and the
     * manual pull admin integration.
     *
     * @since 2.2.0
     */
    private function __construct() {
        add_action(self::CRON_HOOK, [$this, 'run_sync']);
        add_action('init', [$this, 'maybe_schedule']);
        add_action('wp_ajax_drivehr_feed_pull', [$this, 'handle_manual_pull_ajax']);
        add_action('admin_notices', [$this, 'render_pull_button']);
    }

    /**
     * Prevent cloning of the instance
     *
     * @since 2.2.0
     */
    private function __clone() {}

    /**
     * Prevent unserialization of the instance
     *
     * @since 2.2.0
     * @throws Exception
     */
    public function __wakeup() {
        throw new Exception('Cannot unserialize singleton');
    }

    /**
     * Check whether feed sync is enabled
     *
     * Enabled by default whenever the shared secret is configured.
     * Disable explicitly with: define('DRIVEHR_FEED_SYNC_ENABLED', false);
     *
     * @since 2.2.0
     * @return bool True when feed sync should run
     */
    public function is_enabled(): bool {
        if (!defined('DRIVEHR_WEBHOOK_SECRET') || empty(DRIVEHR_WEBHOOK_SECRET)) {
            return false;
        }
        if (defined('DRIVEHR_FEED_SYNC_ENABLED') && DRIVEHR_FEED_SYNC_ENABLED === false) {
            return false;
        }
        return true;
    }

    /**
     * Get the configured feed URL
     *
     * @since 2.2.0
     * @return string Feed URL (jobs.json); the signature URL is derived
     *                by appending `.sig`
     */
    public function get_feed_url(): string {
        if (defined('DRIVEHR_FEED_URL') && !empty(DRIVEHR_FEED_URL)) {
            return DRIVEHR_FEED_URL;
        }
        return self::DEFAULT_FEED_URL;
    }

    /**
     * Ensure the cron schedule matches the enabled state
     *
     * Self-healing scheduler: schedules the hourly event when enabled and
     * missing, clears it when disabled. Runs on init so no activation
     * hook ordering issues can leave the schedule stale.
     *
     * @since 2.2.0
     * @return void
     */
    public function maybe_schedule(): void {
        $scheduled = wp_next_scheduled(self::CRON_HOOK);

        if ($this->is_enabled() && !$scheduled) {
            wp_schedule_event(time(), 'hourly', self::CRON_HOOK);
        } elseif (!$this->is_enabled() && $scheduled) {
            wp_unschedule_event($scheduled, self::CRON_HOOK);
        }
    }

    /**
     * Clear the cron schedule
     *
     * Called from the plugin deactivation hook.
     *
     * @since 2.2.0
     * @return void
     */
    public static function clear_schedule(): void {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    /**
     * Fetch, verify, and apply the signed job feed
     *
     * Complete pull-sync flow:
     * 1. Fetch feed and detached signature over HTTPS
     * 2. Verify HMAC-SHA256 signature over the exact feed bytes
     * 3. Skip if the feed is unchanged since the last applied sync
     * 4. Validate payload structure, freshness, and size limits
     * 5. Guard against unexpected empty feeds
     * 6. Process through the shared sync engine (create/update/remove)
     *
     * @param bool $force Process even when the feed hash is unchanged
     * @return array Result array with 'success', 'message', and counts
     * @since 2.2.0
     */
    public function run_sync(bool $force = false): array {
        if (!$this->is_enabled()) {
            return $this->finish_sync(false, 'Feed sync is not enabled (missing secret or explicitly disabled)');
        }

        // Serialize runs: WP-Cron and the admin button can otherwise overlap
        // and race each other's delete-stale pass.
        if (get_transient(self::LOCK_TRANSIENT) !== false) {
            return $this->finish_sync(false, 'A feed sync is already running; try again shortly');
        }
        set_transient(self::LOCK_TRANSIENT, time(), self::LOCK_TTL);

        try {
            return $this->run_sync_locked($force);
        } finally {
            delete_transient(self::LOCK_TRANSIENT);
        }
    }

    /**
     * Sync implementation, executed while the run lock is held
     *
     * @since 2.3.0
     * @param bool $force Bypass the unchanged-feed shortcut
     * @return array Result with success, message and timestamp keys
     */
    private function run_sync_locked(bool $force): array {
        $feed_url = $this->get_feed_url();

        // The feed and its signature must travel over TLS; the HMAC protects
        // integrity but not confidentiality of the job data.
        if (wp_parse_url($feed_url, PHP_URL_SCHEME) !== 'https' || !wp_http_validate_url($feed_url)) {
            return $this->finish_sync(false, 'Feed URL must be a valid https:// URL');
        }

        // Fetch feed and signature
        $feed_body = $this->fetch_url($feed_url);
        if ($feed_body === null) {
            return $this->finish_sync(false, 'Failed to fetch feed from ' . esc_url_raw($feed_url));
        }

        $signature = $this->fetch_url($feed_url . '.sig');
        if ($signature === null) {
            return $this->finish_sync(false, 'Failed to fetch feed signature');
        }

        // Verify HMAC over the exact feed bytes (timing-safe)
        $expected = 'sha256=' . hash_hmac('sha256', $feed_body, DRIVEHR_WEBHOOK_SECRET);
        if (!hash_equals($expected, trim($signature))) {
            return $this->finish_sync(false, 'Feed signature verification failed');
        }

        // Skip when the feed is unchanged since the last applied sync
        $feed_hash = hash('sha256', $feed_body);
        if (!$force && get_option(self::OPTION_LAST_HASH) === $feed_hash) {
            return $this->finish_sync(true, 'Feed unchanged since last sync; skipped', ['skipped' => true]);
        }

        // Decode and validate payload structure
        $data = json_decode($feed_body, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return $this->finish_sync(false, 'Feed contains invalid JSON');
        }

        if (!isset($data['jobs']) || !is_array($data['jobs'])) {
            return $this->finish_sync(false, 'Feed payload missing jobs array');
        }

        if (count($data['jobs']) > DriveHR_Job_Sync::MAX_JOBS_PER_SYNC) {
            return $this->finish_sync(false, 'Feed exceeds maximum job count');
        }

        // Reject stale feeds - a dead pipeline should not drive syncs
        $feed_time = isset($data['timestamp']) ? strtotime($data['timestamp']) : false;
        if ($feed_time === false || (time() - $feed_time) > self::MAX_FEED_AGE) {
            return $this->finish_sync(false, 'Feed is stale or missing a valid timestamp; refusing to sync');
        }

        // Guard against accidental mass deletion: an empty feed while jobs
        // exist locally is refused unless explicitly allowed.
        if (empty($data['jobs'])) {
            $existing = wp_count_posts('drivehr_job')->publish ?? 0;
            $allow_empty = defined('DRIVEHR_FEED_ALLOW_EMPTY') && DRIVEHR_FEED_ALLOW_EMPTY === true;
            if ($existing > 0 && !$allow_empty) {
                return $this->finish_sync(
                    false,
                    'Empty feed refused while jobs exist. Define DRIVEHR_FEED_ALLOW_EMPTY as true to allow.'
                );
            }
        }

        // Apply through the shared sync engine
        try {
            $sync_engine = new DriveHR_Job_Sync();
            $result = $sync_engine->sync($data['jobs']);

            update_option(self::OPTION_LAST_HASH, $feed_hash, false);

            $summary = sprintf(
                'Synced %d jobs (%d created, %d updated, %d removed)',
                (int) ($result['total'] ?? 0),
                (int) ($result['processed'] ?? 0),
                (int) ($result['updated'] ?? 0),
                (int) ($result['removed'] ?? 0)
            );

            return $this->finish_sync(true, $summary, $result);
        } catch (Exception $e) {
            return $this->finish_sync(false, 'Sync engine error: ' . $e->getMessage());
        }
    }

    /**
     * Handle AJAX request for a manual feed pull
     *
     * Allows administrators to trigger an immediate pull from the jobs
     * list screen instead of waiting for the next cron run.
     *
     * @since 2.2.0
     * @return void Sends JSON response and exits
     */
    public function handle_manual_pull_ajax(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied'], 403);
            return;
        }

        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'drivehr_feed_pull')) {
            wp_send_json_error(['message' => 'Security check failed'], 403);
            return;
        }

        $result = $this->run_sync(true);

        if ($result['success']) {
            wp_send_json_success(['message' => $result['message']]);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
    }

    /**
     * Render the manual "Pull Feed Now" button on the jobs list screen
     *
     * Self-contained panel with inline JavaScript so no changes to the
     * admin class are required (single responsibility).
     *
     * @since 2.2.0
     * @return void
     */
    public function render_pull_button(): void {
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'edit-drivehr_job') {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        if (!$this->is_enabled()) {
            return;
        }

        $last_sync = get_option(self::OPTION_LAST_SYNC);
        $last_message = is_array($last_sync) && isset($last_sync['message'], $last_sync['time'])
            ? sprintf(
                /* translators: 1: last sync result message, 2: human-readable time difference */
                __('Last pull: %1$s (%2$s ago)', 'drivehr'),
                $last_sync['message'],
                human_time_diff((int) $last_sync['time'])
            )
            : __('Click to pull the latest job feed now', 'drivehr');

        $nonce = wp_create_nonce('drivehr_feed_pull');
        $ajax_url = admin_url('admin-ajax.php');
        ?>
        <div class="notice drivehr-sync-panel">
            <button type="button" id="drivehr-feed-pull" class="button button-secondary">
                <span class="dashicons dashicons-download" style="margin-top: 3px;"></span>
                <?php _e('Pull Feed Now', 'drivehr'); ?>
            </button>
            <span class="spinner" id="drivehr-feed-pull-spinner"></span>
            <span class="drivehr-sync-status" id="drivehr-feed-pull-status"><?php echo esc_html($last_message); ?></span>
        </div>
        <script>
        jQuery(document).ready(function($) {
            $('#drivehr-feed-pull').on('click', function() {
                var button = $(this);
                var spinner = $('#drivehr-feed-pull-spinner');
                var status = $('#drivehr-feed-pull-status');

                button.prop('disabled', true);
                spinner.addClass('is-active');
                status.removeClass('success error').text('<?php echo esc_js(__('Pulling feed...', 'drivehr')); ?>');

                $.post('<?php echo esc_js($ajax_url); ?>', {
                    action: 'drivehr_feed_pull',
                    nonce: '<?php echo esc_js($nonce); ?>'
                }).done(function(response) {
                    spinner.removeClass('is-active');
                    button.prop('disabled', false);
                    if (response.success) {
                        status.addClass('success').text(response.data.message);
                        setTimeout(function() { location.reload(); }, 3000);
                    } else {
                        status.addClass('error').text(response.data.message);
                    }
                }).fail(function(xhr, textStatus, errorThrown) {
                    spinner.removeClass('is-active');
                    button.prop('disabled', false);
                    status.addClass('error').text('<?php echo esc_js(__('Connection error:', 'drivehr')); ?> ' + errorThrown);
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Fetch a URL and return the response body
     *
     * @param string $url URL to fetch
     * @return string|null Response body, or null on any failure
     * @since 2.2.0
     */
    private function fetch_url(string $url): ?string {
        $response = wp_remote_get($url, [
            'timeout' => self::FETCH_TIMEOUT,
            'redirection' => 3,
            'sslverify' => true,
            'reject_unsafe_urls' => true,
            'limit_response_size' => self::MAX_FEED_BYTES,
            'user-agent' => 'DriveHR-Feed-Sync/' . DRIVEHR_WEBHOOK_VERSION . '; ' . home_url(),
            'headers' => [
                // raw.githubusercontent.com caches ~5 minutes; ask for fresh content
                'Cache-Control' => 'no-cache',
            ],
        ]);

        if (is_wp_error($response)) {
            $this->log_feed_activity('Fetch failed', ['url' => $url, 'error' => $response->get_error_message()]);
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            $this->log_feed_activity('Fetch returned non-200', ['url' => $url, 'code' => $code]);
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        if ($body === '' || strlen($body) >= self::MAX_FEED_BYTES) {
            $this->log_feed_activity('Fetch body empty or truncated at size limit', ['url' => $url]);
            return null;
        }

        return $body;
    }

    /**
     * Record the sync outcome and build the result array
     *
     * Persists the last-sync metadata for the admin panel and logs the
     * outcome when debugging is enabled.
     *
     * @param bool $success Whether the sync succeeded
     * @param string $message Human-readable outcome message
     * @param array $extra Additional result data to merge
     * @return array Structured result for callers
     * @since 2.2.0
     */
    private function finish_sync(bool $success, string $message, array $extra = []): array {
        $result = array_merge([
            'success' => $success,
            'message' => $message,
            'timestamp' => current_time('c'),
        ], $extra);

        update_option(self::OPTION_LAST_SYNC, [
            'success' => $success,
            'message' => $message,
            'time' => time(),
        ], false);

        $this->log_feed_activity($success ? 'Sync completed' : 'Sync failed', ['message' => $message]);

        return $result;
    }

    /**
     * Log feed sync activity for debugging
     *
     * Only logs when WP_DEBUG is enabled to avoid performance impact
     * in production environments.
     *
     * @param string $message Log message
     * @param mixed $data Additional data to log
     * @return void
     * @since 2.2.0
     */
    private function log_feed_activity(string $message, $data = null): void {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $log_entry = '[DriveHR Feed Sync] ' . $message;
        if ($data !== null) {
            $log_entry .= ' | Data: ' . wp_json_encode($data, JSON_UNESCAPED_UNICODE);
        }

        error_log($log_entry);
    }
}
