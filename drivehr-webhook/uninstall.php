<?php
/**
 * DriveHR Webhook Handler Uninstall Script
 * 
 * Handles cleanup when the plugin is deleted (not just deactivated).
 * Removes all plugin data, options, and custom post type posts.
 * 
 * @package DriveHR
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit('Direct access denied.');
}

/**
 * Clean up plugin data on uninstall
 * 
 * This function removes all traces of the plugin from the WordPress
 * installation including custom posts, metadata, and options.
 */
function drivehr_webhook_uninstall_cleanup() {
    global $wpdb;
    
    // Remove all DriveHR job posts
    $job_posts = get_posts([
        'post_type' => 'drivehr_job',
        'numberposts' => -1,
        'post_status' => 'any',
        'fields' => 'ids'
    ]);
    
    foreach ($job_posts as $post_id) {
        wp_delete_post($post_id, true); // Force delete, bypass trash
    }
    
    // Remove custom post type from database
    $wpdb->delete($wpdb->posts, ['post_type' => 'drivehr_job']);
    
    // Remove orphaned meta data
    $wpdb->query("DELETE meta FROM {$wpdb->postmeta} meta LEFT JOIN {$wpdb->posts} posts ON posts.ID = meta.post_id WHERE posts.ID IS NULL");
    
    // Remove custom taxonomies and terms
    $taxonomies = ['drivehr_department', 'drivehr_location', 'drivehr_job_type'];
    foreach ($taxonomies as $taxonomy) {
        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'fields' => 'ids'
        ]);
        
        if (!is_wp_error($terms)) {
            foreach ($terms as $term_id) {
                wp_delete_term($term_id, $taxonomy);
            }
        }
    }
    
    // Remove plugin options and transients
    delete_option('drivehr_webhook_version');
    delete_option('drivehr_webhook_settings');
    
    // Remove rate limiting transients
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_drivehr_webhook_rate_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_drivehr_webhook_rate_%'");
    
    // Remove legitimate operation transients
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_drivehr_legitimate_db_op_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_drivehr_legitimate_db_op_%'");
    
    // Remove Wordfence integration transients
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_drivehr_wordfence_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_drivehr_wordfence_%'");
    
    // Flush rewrite rules to clean up custom post type URLs
    flush_rewrite_rules();
    
    // Clear any cached data
    wp_cache_flush();
}

// Run the cleanup
drivehr_webhook_uninstall_cleanup();

// Log the uninstall for debugging
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('[DriveHR Webhook] Plugin uninstalled and data cleaned up');
}