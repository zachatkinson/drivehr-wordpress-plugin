<?php
/**
 * DriveHR Webhook Plugin Uninstall
 *
 * Removes every piece of data this plugin created and nothing else:
 * job posts (and their meta via wp_delete_post), taxonomy terms, options,
 * transients, the feed-sync cron event and the custom capabilities.
 *
 * Deliberately does NOT touch orphaned postmeta belonging to other post
 * types or flush the persistent object cache; those belong to the site,
 * not to this plugin.
 *
 * @package DriveHR
 * @since 1.0.0
 * @since 2.3.0 Scoped cleanup to plugin-owned data; added cron, caps and feed options
 */

// Prevent direct access
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit('Direct access denied.');
}

/**
 * Clean up plugin data on uninstall
 *
 * @return void
 */
function drivehr_webhook_uninstall_cleanup(): void {
    global $wpdb;

    // Remove all DriveHR job posts; wp_delete_post() with force also removes
    // the post's meta and term relationships.
    $job_posts = get_posts([
        'post_type' => 'drivehr_job',
        'numberposts' => -1,
        'post_status' => 'any',
        'fields' => 'ids',
    ]);

    foreach ($job_posts as $post_id) {
        wp_delete_post($post_id, true);
    }

    // Remove custom taxonomy terms
    $taxonomies = ['drivehr_department', 'drivehr_location', 'drivehr_job_type'];
    foreach ($taxonomies as $taxonomy) {
        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'fields' => 'ids',
        ]);

        if (!is_wp_error($terms)) {
            foreach ($terms as $term_id) {
                wp_delete_term($term_id, $taxonomy);
            }
        }
    }

    // Remove the feed-sync cron event
    wp_clear_scheduled_hook('drivehr_feed_sync');

    // Remove plugin options
    foreach (['drivehr_feed_last_hash', 'drivehr_feed_last_sync', 'drivehr_deactivation_warning'] as $option) {
        delete_option($option);
    }

    // Remove every transient this plugin creates (rate limits, seen
    // signatures, REST cache, sync lock, notices). Both the value and its
    // timeout row are matched by the shared prefix.
    $like = $wpdb->esc_like('_transient_') . 'drivehr_%';
    $timeout_like = $wpdb->esc_like('_transient_timeout_') . 'drivehr_%';
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $like,
            $timeout_like
        )
    );

    // Remove the custom capabilities granted on activation
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
    foreach (wp_roles()->role_objects as $role) {
        foreach ($capabilities as $capability) {
            if ($role->has_cap($capability)) {
                $role->remove_cap($capability);
            }
        }
    }

    // Flush rewrite rules to drop the custom post type URLs
    flush_rewrite_rules();
}

drivehr_webhook_uninstall_cleanup();

if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('[DriveHR Webhook] Plugin uninstalled and data cleaned up');
}
