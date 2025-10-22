<?php
/**
 * Plugin Name: DriveHR Job Sync Webhook Handler
 * Plugin URI: https://github.com/zachatkinson/drivehr-netlify-sync
 * Description: Enterprise-grade webhook handler for receiving job data from DriveHR Netlify function and storing it as WordPress custom posts. Maintains perfect parity between DriveHR and WordPress by automatically removing jobs that are no longer listed.
 * Version: 1.1.4
 * Author: DriveHR Integration Team
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Network: false
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * 
 * Security Features:
 * - HMAC-SHA256 signature verification with timing-safe comparison
 * - Timestamp-based replay attack protection (5-minute window)
 * - Rate limiting (10 requests per minute per IP)
 * - Input validation and sanitization using WordPress functions
 * - Environment-based secret management (no hardcoded secrets)
 * - Comprehensive error handling with secure responses
 * - Database transaction safety for atomic operations
 * - Optional debug logging for development
 * 
 * Sync Features (NEW in v1.1.0):
 * - Automatic removal of jobs no longer in DriveHR feed
 * - Perfect parity maintenance between DriveHR and WordPress
 * - Transaction-safe job deletion with comprehensive logging
 * - Before/after deletion hooks for custom integrations
 * 
 * Installation (Regular Plugin - Recommended):
 * 1. Upload this folder to /wp-content/plugins/drivehr-webhook/
 * 2. Activate the plugin through the WordPress admin interface
 * 3. Add these constants to wp-config.php:
 *    define('DRIVEHR_WEBHOOK_SECRET', 'your-webhook-secret-here');
 *    define('DRIVEHR_WEBHOOK_ENABLED', true);
 * 4. Update your Netlify function's WP_API_URL to: /webhook/drivehr-sync
 * 
 * Alternative Installation (Must-Use Plugin):
 * 1. Upload this folder to /wp-content/mu-plugins/drivehr-webhook/
 * 2. Follow steps 3-4 above
 * 
 * @package DriveHR
 * @version 1.1.4
 * @since 2025-01-01
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

// Define plugin constants
define('DRIVEHR_WEBHOOK_VERSION', '1.1.4');
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
    // Load the webhook handler
    require_once DRIVEHR_WEBHOOK_DIR . '/includes/class-webhook-handler.php';
    require_once DRIVEHR_WEBHOOK_DIR . '/includes/class-post-type.php';
    require_once DRIVEHR_WEBHOOK_DIR . '/includes/class-admin.php';
    require_once DRIVEHR_WEBHOOK_DIR . '/includes/class-wordfence-compatibility.php';
    
    // Initialize components
    new DriveHR_Post_Type();
    new DriveHR_Admin();
    new DriveHR_Webhook_Handler();
    new DriveHR_Wordfence_Compatibility();
    
    // Log plugin activation
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[DriveHR Webhook] Plugin initialized v' . DRIVEHR_WEBHOOK_VERSION);
    }
});

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
    
    // Check if webhook is disabled
    if (!defined('DRIVEHR_WEBHOOK_ENABLED') || DRIVEHR_WEBHOOK_ENABLED !== true) {
        echo '<div class="notice notice-warning">
            <p><strong>📡 DriveHR Webhook Disabled:</strong> Please enable the webhook in wp-config.php:<br>
            <code>define(\'DRIVEHR_WEBHOOK_ENABLED\', true);</code></p>
        </div>';
    }
});

/**
 * Add plugin action links for easy access to documentation
 */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
    $settings_links = [
        '<a href="' . admin_url('edit.php?post_type=drivehr_job') . '">View Jobs</a>',
        '<a href="https://github.com/zachatkinson/drivehr-netlify-sync" target="_blank">Documentation</a>',
    ];
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

    // Secret exists - show configuration summary
    $secret_length = strlen(DRIVEHR_WEBHOOK_SECRET);
    $masked_secret = str_repeat('•', min($secret_length - 4, 20)) . substr(DRIVEHR_WEBHOOK_SECRET, -4);

    // Check if webhook is enabled
    if (!defined('DRIVEHR_WEBHOOK_ENABLED') || DRIVEHR_WEBHOOK_ENABLED !== true) {
        $result['status'] = 'recommended';
        $result['description'] = '<p>Webhook is configured but disabled. Enable it in wp-config.php:<br><code>define(\'DRIVEHR_WEBHOOK_ENABLED\', true);</code></p>';
        $result['badge']['color'] = 'orange';
        return $result;
    }

    // Check if we have any synced jobs
    $job_count = wp_count_posts('drivehr_job')->publish ?? 0;
    $webhook_endpoint = home_url('/webhook/drivehr-sync');
    
    if ($job_count > 0) {
        $result['description'] = sprintf(
            '<p>✅ <strong>DriveHR webhook is active and working!</strong><br>• Secret configured: <code>%s</code><br>• Currently managing: <strong>%d job(s)</strong><br>• Endpoint: <code>%s</code><br><br>🔗 <a href="%s">View Jobs</a> | 📋 <strong>Wordfence users:</strong> Ensure <code>/webhook/drivehr-sync</code> is allowlisted</p>',
            $masked_secret,
            $job_count,
            $webhook_endpoint,
            admin_url('edit.php?post_type=drivehr_job')
        );
    } else {
        $result['status'] = 'recommended';
        $result['description'] = sprintf(
            '<p>⚠️ <strong>DriveHR webhook is configured but no jobs have been synced yet.</strong><br>• Secret configured: <code>%s</code><br>• Endpoint: <code>%s</code><br><br>This is normal for new installations. Trigger your Netlify function to test the connection.<br><br>📋 <strong>Wordfence users:</strong> Ensure <code>/webhook/drivehr-sync</code> is allowlisted in Wordfence > All Options > Allowlisted URLs</p>',
            $masked_secret,
            $webhook_endpoint
        );
        $result['badge']['color'] = 'orange';
    }

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