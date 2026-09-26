<?php
/**
 * Plugin Name: DriveHR Job Sync Webhook Handler
 * Plugin URI: https://github.com/zachatkinson/drivehr-netlify-sync
 * Description: Enterprise-grade webhook handler for receiving job data from DriveHR Netlify function and storing it as WordPress custom posts. Maintains perfect parity between DriveHR and WordPress by automatically removing jobs that are no longer listed.
 * Version: 2.3.0
 * Author: DriveHR Integration Team
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Network: false
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 *
 * Security Features:
 * - HMAC-SHA256 signature over "{timestamp}.{body}" with timing-safe comparison
 * - Replay protection: 5-minute timestamp window plus seen-signature cache
 * - Rate limiting (10 requests per minute per IP, REMOTE_ADDR unless a
 *   trusted proxy header is declared via DRIVEHR_TRUSTED_PROXY_HEADER)
 * - JSON-only requests with a 2 MiB body limit
 * - Per-record validation and sanitization using WordPress functions
 * - Environment-based secret management (no hardcoded secrets)
 * - Comprehensive error handling with secure responses
 * - Database transaction safety for atomic operations
 * - Optional debug logging for development
 *
 * Recommended deployment (v2.2.0+): pull mode. Leave DRIVEHR_WEBHOOK_ENABLED
 * undefined so no public POST endpoint exists at all; the plugin fetches the
 * signed feed over HTTPS on WP-Cron instead. Define DRIVEHR_WEBHOOK_ENABLED
 * only if you still need push delivery.
 * 
 * Sync Features (NEW in v1.1.0):
 * - Automatic removal of jobs no longer in DriveHR feed
 * - Perfect parity maintenance between DriveHR and WordPress
 * - Transaction-safe job deletion with comprehensive logging
 * - Before/after deletion hooks for custom integrations
 * 
 * Manual Sync Features (NEW in v2.1.0):
 * - "Sync Jobs Now" button in WordPress admin for on-demand synchronization
 * - Triggers GitHub Actions workflow via Netlify function
 * - Allows immediate job updates without waiting for scheduled cron
 * - Useful when client updates job content and wants immediate refresh
 *
 * Installation (Regular Plugin - Recommended):
 * 1. Upload this folder to /wp-content/plugins/drivehr-webhook/
 * 2. Activate the plugin through the WordPress admin interface
 * 3. Add these constants to wp-config.php:
 *    define('DRIVEHR_WEBHOOK_SECRET', 'your-webhook-secret-here');
 *    define('DRIVEHR_WEBHOOK_ENABLED', true);
 * 4. Update your Netlify function's WP_API_URL to: /webhook/drivehr-sync
 * 5. (Optional) Enable manual sync button by adding:
 *    define('DRIVEHR_NETLIFY_TRIGGER_URL', 'https://your-site.netlify.app/.netlify/functions/manual-trigger');
 *
 * Alternative Installation (Must-Use Plugin):
 * 1. Upload this folder to /wp-content/mu-plugins/drivehr-webhook/
 * 2. Follow steps 3-4 above
 *
 * @package DriveHR
 * @version 2.1.0
 * @since 2025-01-01
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

// Define plugin constants
define('DRIVEHR_WEBHOOK_VERSION', '2.3.0');
define('DRIVEHR_WEBHOOK_PATH', __FILE__);
define('DRIVEHR_WEBHOOK_DIR', dirname(__FILE__));
define('DRIVEHR_WEBHOOK_URL', plugins_url('', __FILE__));

/**
 * Initialize DriveHR Webhook Plugin
 * 
 * Sets up the webhook handler and custom post type registration.
 * Only initializes if WordPress is fully loaded to ensure all
 * WordPress functions are available.
 */
add_action('plugins_loaded', function() {
    // Load core components
    require_once DRIVEHR_WEBHOOK_DIR . '/includes/class-job-sync.php';
    require_once DRIVEHR_WEBHOOK_DIR . '/includes/class-webhook-handler.php';
    require_once DRIVEHR_WEBHOOK_DIR . '/includes/class-feed-sync.php';
    require_once DRIVEHR_WEBHOOK_DIR . '/includes/class-post-type.php';
    require_once DRIVEHR_WEBHOOK_DIR . '/includes/class-admin.php';
    require_once DRIVEHR_WEBHOOK_DIR . '/includes/class-rest-api-cache.php';

    // Load shared rendering trait (v1.7.0+) before block classes
    require_once DRIVEHR_WEBHOOK_DIR . '/includes/trait-job-card-renderer.php';

    // Load Gutenberg blocks
    require_once DRIVEHR_WEBHOOK_DIR . '/includes/class-job-block.php';
    require_once DRIVEHR_WEBHOOK_DIR . '/includes/class-job-list-block.php';

    // Initialize components using singleton pattern (v1.6.0+)
    // This prevents duplicate hook registrations that caused 503 errors
    DriveHR_Post_Type::get_instance();
    DriveHR_Admin::get_instance();
    DriveHR_Webhook_Handler::get_instance();
    DriveHR_Feed_Sync::get_instance();
    DriveHR_REST_API_Cache::get_instance();

    // Initialize Gutenberg blocks (v1.7.0+)
    DriveHR_Job_Block::get_instance();
    DriveHR_Job_List_Block::get_instance();

    // Log plugin activation
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[DriveHR Webhook] Plugin initialized v' . DRIVEHR_WEBHOOK_VERSION);
    }
});

/**
 * Performance optimization: Disable post revisions for job posts
 *
 * Job posts are synced from external source and don't need revision history.
 * This reduces database bloat and improves update performance.
 *
 * @since 1.2.0
 */
add_filter('wp_revisions_to_keep', function($num, $post) {
    if ($post->post_type === 'drivehr_job') {
        return 0;
    }
    return $num;
}, 10, 2);

/**
 * Plugin activation hook
 * 
 * Runs when the plugin is activated to set up initial configuration
 * and flush rewrite rules for custom post types.
 */
register_activation_hook(__FILE__, function() {
    // Add capabilities for DriveHR job management
    $capabilities = [
        'edit_drivehr_job',
        'read_drivehr_job', 
        'delete_drivehr_job',
        'edit_drivehr_jobs',
        'edit_others_drivehr_jobs',
        'publish_drivehr_jobs',
        'read_private_drivehr_jobs',
        'create_drivehr_jobs',
        'delete_drivehr_jobs',
        'delete_others_drivehr_jobs',
        'delete_private_drivehr_jobs',
        'delete_published_drivehr_jobs',
    ];
    
    // Grant capabilities to administrators and editors
    $roles = ['administrator', 'editor'];
    foreach ($roles as $role_name) {
        $role = get_role($role_name);
        if ($role) {
            foreach ($capabilities as $capability) {
                $role->add_cap($capability);
            }
        }
    }
    
    // Flush rewrite rules to ensure custom post type URLs work
    flush_rewrite_rules();
    
    // Clear any deactivation warnings
    delete_option('drivehr_deactivation_warning');
    
    // Show success notice on next admin page load (if configured)
    if (defined('DRIVEHR_WEBHOOK_SECRET') && !empty(DRIVEHR_WEBHOOK_SECRET)) {
        set_transient('drivehr_show_success_notice', true, 60); // Show for 1 minute
    }
    
    // Log activation
    error_log('[DriveHR Webhook] Plugin activated with job management capabilities');
});

/**
 * Plugin deactivation hook
 * 
 * Cleanup when plugin is deactivated and warn administrators.
 */
register_deactivation_hook(__FILE__, function() {
    // Clear the feed sync cron schedule (v2.2.0)
    if (class_exists('DriveHR_Feed_Sync')) {
        DriveHR_Feed_Sync::clear_schedule();
    } else {
        wp_clear_scheduled_hook('drivehr_feed_sync');
    }

    // Remove DriveHR job capabilities from all roles
    $capabilities = [
        'edit_drivehr_job',
        'read_drivehr_job', 
        'delete_drivehr_job',
        'edit_drivehr_jobs',
        'edit_others_drivehr_jobs',
        'publish_drivehr_jobs',
        'read_private_drivehr_jobs',
        'create_drivehr_jobs',
        'delete_drivehr_jobs',
        'delete_others_drivehr_jobs',
        'delete_private_drivehr_jobs',
        'delete_published_drivehr_jobs',
    ];
    
    $roles = ['administrator', 'editor'];
    foreach ($roles as $role_name) {
        $role = get_role($role_name);
        if ($role) {
            foreach ($capabilities as $capability) {
                $role->remove_cap($capability);
            }
        }
    }
    
    // Flush rewrite rules
    flush_rewrite_rules();
    
    // Set warning flag for admin notices
    add_option('drivehr_deactivation_warning', true);
    
    // Log deactivation
    error_log('[DriveHR Webhook] Plugin deactivated - Job synchronization and capabilities removed');
});

/**
 * Display admin notices for configuration and deactivation warnings
 */
add_action('admin_notices', function() {
    // Check for deactivation warning
    if (get_option('drivehr_deactivation_warning')) {
        echo '<div class="notice notice-warning is-dismissible">
            <p><strong>⚠️ DriveHR Job Sync Warning:</strong> The DriveHR webhook plugin has been deactivated.
            Job synchronization from your Netlify function will not work until the plugin is reactivated.
            <a href="' . esc_url(admin_url('plugins.php')) . '">Reactivate now</a></p>
        </div>';
        delete_option('drivehr_deactivation_warning');
    }
    
    // Check for missing webhook secret
    if (!defined('DRIVEHR_WEBHOOK_SECRET') || empty(DRIVEHR_WEBHOOK_SECRET)) {
        echo '<div class="notice notice-error">
            <p><strong>🔐 DriveHR Configuration Required:</strong> Please add your webhook secret to wp-config.php:<br>
            <code>define(\'DRIVEHR_WEBHOOK_SECRET\', \'your-secret-here\');</code><br>
            <small>This secret must match the WEBHOOK_SECRET in your Netlify environment variables.</small><br>
            <small><strong>Wordfence Users:</strong> Add <code>/webhook/drivehr-sync</code> to Wordfence > All Options > Allowlisted URLs</small></p>
        </div>';
    } elseif (get_transient('drivehr_show_success_notice')) {
        // Secret exists - show success notice on first activation only
        echo '<div class="notice notice-success is-dismissible">
            <p><strong>✅ DriveHR Webhook Configured!</strong> Your webhook secret is properly set.
            Endpoint available at: <code>/webhook/drivehr-sync</code><br>
            <small><strong>Wordfence Users:</strong> Remember to allowlist <code>/webhook/drivehr-sync</code> in Wordfence > All Options > Allowlisted URLs</small></p>
        </div>';
        delete_transient('drivehr_show_success_notice');
    }
    
    // Warn when neither delivery mode can work
    $feed_sync_enabled = class_exists('DriveHR_Feed_Sync') && DriveHR_Feed_Sync::get_instance()->is_enabled();
    $webhook_enabled = defined('DRIVEHR_WEBHOOK_ENABLED') && DRIVEHR_WEBHOOK_ENABLED === true;
    if (!$feed_sync_enabled && !$webhook_enabled) {
        echo '<div class="notice notice-warning">
            <p><strong>📡 DriveHR Sync Inactive:</strong> Neither the signed-feed pull nor the webhook is enabled.
            Pull mode (recommended) activates automatically once <code>DRIVEHR_WEBHOOK_SECRET</code> is defined.</p>
        </div>';
    }

    // Weak secrets undermine every other control
    if (defined('DRIVEHR_WEBHOOK_SECRET') && !empty(DRIVEHR_WEBHOOK_SECRET) && strlen(DRIVEHR_WEBHOOK_SECRET) < 32) {
        echo '<div class="notice notice-error">
            <p><strong>🔐 DriveHR Secret Too Short:</strong> <code>DRIVEHR_WEBHOOK_SECRET</code> should be at least 32 random characters.
            Generate one with <code>openssl rand -hex 32</code> and update it in wp-config.php, Netlify and GitHub Actions.</p>
        </div>';
    }
});

/**
 * Send hardening headers on public responses (v2.3.0)
 *
 * Applied to front-end and REST responses only when the site does not already
 * send them, so a host-level or security-plugin configuration always wins.
 * Header names are compared case-insensitively.
 *
 * @since 2.3.0
 */
add_action('send_headers', function() {
    if (is_admin() || headers_sent()) {
        return;
    }

    $wanted = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
    ];

    $already_sent = array_map(function($header) {
        return strtolower(trim(strtok($header, ':')));
    }, headers_list());

    foreach ($wanted as $name => $value) {
        if (!in_array(strtolower($name), $already_sent, true)) {
            header($name . ': ' . $value);
        }
    }
});

/**
 * Hide plugin-generated version strings from anonymous visitors (v2.3.0)
 *
 * Only affects this plugin's own asset handles; other plugins and core keep
 * their own behaviour.
 *
 * @since 2.3.0
 */
add_filter('script_loader_src', 'drivehr_strip_plugin_asset_version', 20);
add_filter('style_loader_src', 'drivehr_strip_plugin_asset_version', 20);
function drivehr_strip_plugin_asset_version($src) {
    if (is_string($src) && strpos($src, '/plugins/drivehr-webhook/') !== false && !is_user_logged_in()) {
        $src = remove_query_arg('ver', $src);
    }
    return $src;
}

/**
 * Add plugin action links for easy access to documentation
 */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
    $settings_links = [
        '<a href="' . admin_url('edit.php?post_type=drivehr_job') . '">View Jobs</a>',
        '<a href="https://github.com/zachatkinson/drivehr-netlify-sync" target="_blank">Documentation</a>',
    ];
    // Show sync link if configured
    if (defined('DRIVEHR_NETLIFY_TRIGGER_URL') && !empty(DRIVEHR_NETLIFY_TRIGGER_URL)) {
        array_unshift($settings_links, '<a href="' . admin_url('edit.php?post_type=drivehr_job') . '"><strong>Sync Now</strong></a>');
    }
    return array_merge($settings_links, $links);
});

/**
 * Add meta information to plugin row
 */
add_filter('plugin_row_meta', function($links, $file) {
    if ($file === plugin_basename(__FILE__)) {
        $additional_links = [
            '<a href="' . admin_url('edit.php?post_type=drivehr_job') . '">📋 Manage Jobs</a>',
        ];
        
        // Only show Site Health link to users who can access it
        if (current_user_can('view_site_health_checks') || current_user_can('manage_options')) {
            $additional_links[] = '<a href="' . admin_url('site-health.php') . '">🏥 Site Health</a>';
        }
        
        return array_merge($links, $additional_links);
    }
    return $links;
}, 10, 2);

/**
 * Add Site Health check for DriveHR configuration
 */
add_filter('site_status_tests', function($tests) {
    $tests['direct']['drivehr_webhook_config'] = [
        'label' => __('DriveHR Webhook Configuration', 'drivehr'),
        'test'  => 'drivehr_webhook_health_check'
    ];
    return $tests;
});

/**
 * Site Health check function
 */
function drivehr_webhook_health_check() {
    $result = [
        'label'       => __('DriveHR Webhook Configuration', 'drivehr'),
        'status'      => 'good',
        'badge'       => [
            'label' => __('DriveHR', 'drivehr'),
            'color' => 'blue',
        ],
        'description' => sprintf(
            '<p>%s</p>',
            __('Your DriveHR webhook is properly configured and ready to receive job data.', 'drivehr')
        ),
        'actions'     => '',
        'test'        => 'drivehr_webhook_config',
    ];

    // Check webhook secret
    if (!defined('DRIVEHR_WEBHOOK_SECRET') || empty(DRIVEHR_WEBHOOK_SECRET)) {
        $result['status'] = 'critical';
        $result['description'] = '<p><strong>❌ Missing webhook secret configuration.</strong><br>Add this to wp-config.php:<br><code>define(\'DRIVEHR_WEBHOOK_SECRET\', \'your-secret-here\');</code><br><br><strong>📋 Additional Setup:</strong><br>• <strong>Wordfence users:</strong> Add <code>/webhook/drivehr-sync</code> to Wordfence > All Options > Allowlisted URLs<br>• Update your Netlify function\'s WP_API_URL to include the webhook endpoint</p>';
        $result['badge']['color'] = 'red';
        return $result;
    }

    // Secret strength: never echo any part of the secret, only its length class
    $secret_length = strlen(DRIVEHR_WEBHOOK_SECRET);
    if ($secret_length < 32) {
        $result['status'] = 'critical';
        $result['description'] = '<p><strong>❌ Webhook secret is too short.</strong><br>Use at least 32 random characters (<code>openssl rand -hex 32</code>) and update it in wp-config.php, Netlify and GitHub Actions at the same time.</p>';
        $result['badge']['color'] = 'red';
        return $result;
    }

    $webhook_enabled = defined('DRIVEHR_WEBHOOK_ENABLED') && DRIVEHR_WEBHOOK_ENABLED === true;
    $feed_sync_enabled = class_exists('DriveHR_Feed_Sync') && DriveHR_Feed_Sync::get_instance()->is_enabled();

    if (!$webhook_enabled && !$feed_sync_enabled) {
        $result['status'] = 'critical';
        $result['description'] = '<p>Secret is set but both delivery modes are disabled. Remove <code>DRIVEHR_FEED_SYNC_ENABLED</code> (or set it to true) to use pull mode.</p>';
        $result['badge']['color'] = 'red';
        return $result;
    }

    $job_count = wp_count_posts('drivehr_job')->publish ?? 0;
    $mode_lines = [];
    if ($feed_sync_enabled) {
        $last_sync = get_option('drivehr_feed_last_sync');
        $last_text = is_array($last_sync) && isset($last_sync['time'])
            ? sprintf('last pull %s ago, %s', human_time_diff((int) $last_sync['time']), !empty($last_sync['success']) ? 'succeeded' : 'failed')
            : 'no pull recorded yet';
        $mode_lines[] = '✅ Pull mode active (signed feed over HTTPS, hourly WP-Cron; ' . esc_html($last_text) . ')';
    }
    if ($webhook_enabled) {
        $trusted_proxy = defined('DRIVEHR_TRUSTED_PROXY_HEADER') && !empty(DRIVEHR_TRUSTED_PROXY_HEADER);
        $mode_lines[] = sprintf(
            '⚠️ Push webhook enabled at <code>%s</code> (public POST endpoint). Prefer pull mode and remove <code>DRIVEHR_WEBHOOK_ENABLED</code> once the feed sync is confirmed working. Rate limiting keys on %s.',
            esc_html(home_url('/webhook/drivehr-sync')),
            $trusted_proxy ? 'the declared proxy header' : 'REMOTE_ADDR (define DRIVEHR_TRUSTED_PROXY_HEADER if you are behind a CDN)'
        );
        if (!$feed_sync_enabled) {
            $result['status'] = 'recommended';
            $result['badge']['color'] = 'orange';
        }
    }
    $mode_lines[] = defined('DRIVEHR_NETLIFY_TRIGGER_URL') && !empty(DRIVEHR_NETLIFY_TRIGGER_URL)
        ? '✅ Manual sync button enabled'
        : '⚪ Manual sync button not configured';

    if ($job_count === 0) {
        $result['status'] = 'recommended';
        $result['badge']['color'] = 'orange';
        $mode_lines[] = '⚠️ No jobs have been synced yet. This is normal for a new installation.';
    } else {
        $mode_lines[] = sprintf('Currently managing <strong>%d job(s)</strong>', $job_count);
    }

    $result['description'] = sprintf(
        '<p>%s<br><br>🔗 <a href="%s">View Jobs</a></p>',
        implode('<br>• ', array_merge([''], $mode_lines)),
        esc_url(admin_url('edit.php?post_type=drivehr_job'))
    );

    return $result;
}

/**
 * Display plugin information in admin footer on DriveHR pages
 */
add_filter('admin_footer_text', function($footer_text) {
    $screen = get_current_screen();
    if ($screen && $screen->post_type === 'drivehr_job') {
        $job_count = wp_count_posts('drivehr_job')->publish ?? 0;
        return sprintf(
            'Managing %d DriveHR job(s) • <a href="%s">DriveHR Sync v%s</a>',
            $job_count,
            'https://github.com/zachatkinson/drivehr-netlify-sync',
            DRIVEHR_WEBHOOK_VERSION
        );
    }
    return $footer_text;
});