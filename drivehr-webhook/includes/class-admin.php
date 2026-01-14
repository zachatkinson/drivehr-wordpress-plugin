<?php
/**
 * DriveHR Admin Interface
 * 
 * Handles admin-specific functionality including meta boxes, custom columns,
 * and admin notices for the DriveHR job synchronization system.
 * 
 * @package DriveHR
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

/**
 * DriveHR Admin Class
 *
 * Manages admin interface enhancements for DriveHR jobs.
 *
 * Uses singleton pattern to prevent duplicate hook registrations.
 *
 * @since 1.0.0
 * @since 1.6.0 Implemented singleton pattern
 */
class DriveHR_Admin {

    /**
     * Single instance of the class
     *
     * @since 1.6.0
     * @var DriveHR_Admin|null
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @since 1.6.0
     * @return DriveHR_Admin
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize admin functionality
     *
     * Private constructor prevents direct instantiation.
     * Use DriveHR_Admin::get_instance() instead.
     *
     * @since 1.0.0
     * @since 1.6.0 Changed to private constructor for singleton pattern
     */
    private function __construct() {
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_drivehr_job', [$this, 'save_job_meta_data']);
        add_filter('manage_drivehr_job_posts_columns', [$this, 'add_custom_columns']);
        add_action('manage_drivehr_job_posts_custom_column', [$this, 'populate_custom_columns'], 10, 2);
        add_filter('manage_edit-drivehr_job_sortable_columns', [$this, 'make_columns_sortable']);
        add_action('pre_get_posts', [$this, 'handle_column_sorting']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_styles']);
        add_action('admin_head', [$this, 'add_admin_styles']);

        // Manual sync functionality
        add_action('admin_notices', [$this, 'render_sync_button']);
        add_action('wp_ajax_drivehr_manual_sync', [$this, 'handle_manual_sync_ajax']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_sync_scripts']);
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
     * Add custom meta boxes for job data in admin
     * 
     * Provides admin interface for viewing and editing job-specific metadata
     * that comes from the DriveHR integration.
     */
    public function add_meta_boxes(): void {
        add_meta_box(
            'drivehr_job_details',
            __('DriveHR Job Details', 'drivehr'),
            [$this, 'render_job_details_meta_box'],
            'drivehr_job',
            'normal',
            'high'
        );
        
        add_meta_box(
            'drivehr_job_sync',
            __('Synchronization Info', 'drivehr'),
            [$this, 'render_sync_info_meta_box'],
            'drivehr_job',
            'side',
            'default'
        );
    }
    
    /**
     * Render job details meta box
     * 
     * @param WP_Post $post Current post object
     */
    public function render_job_details_meta_box($post): void {
        // Add nonce for security
        wp_nonce_field('drivehr_job_details', 'drivehr_job_details_nonce');
        
        // Get job metadata
        $job_id = get_post_meta($post->ID, 'job_id', true);
        $department = get_post_meta($post->ID, 'department', true);
        $location = get_post_meta($post->ID, 'location', true);
        $job_type = get_post_meta($post->ID, 'job_type', true);
        $employment_type = get_post_meta($post->ID, 'employment_type', true);
        $salary_range = get_post_meta($post->ID, 'salary_range', true);
        $apply_url = get_post_meta($post->ID, 'apply_url', true);
        $posted_date = get_post_meta($post->ID, 'posted_date', true);
        $expiry_date = get_post_meta($post->ID, 'expiry_date', true);
        
        ?>
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="drivehr_job_id"><?php _e('Job ID', 'drivehr'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="drivehr_job_id" name="job_id" value="<?php echo esc_attr($job_id); ?>" class="regular-text" readonly />
                        <p class="description"><?php _e('Unique identifier from DriveHR system (read-only)', 'drivehr'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="drivehr_department"><?php _e('Department', 'drivehr'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="drivehr_department" name="department" value="<?php echo esc_attr($department); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="drivehr_location"><?php _e('Location', 'drivehr'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="drivehr_location" name="location" value="<?php echo esc_attr($location); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="drivehr_job_type"><?php _e('Job Type', 'drivehr'); ?></label>
                    </th>
                    <td>
                        <select id="drivehr_job_type" name="job_type" class="regular-text">
                            <option value=""><?php _e('Select job type...', 'drivehr'); ?></option>
                            <option value="Full-time" <?php selected($job_type, 'Full-time'); ?>><?php _e('Full-time', 'drivehr'); ?></option>
                            <option value="Part-time" <?php selected($job_type, 'Part-time'); ?>><?php _e('Part-time', 'drivehr'); ?></option>
                            <option value="Contract" <?php selected($job_type, 'Contract'); ?>><?php _e('Contract', 'drivehr'); ?></option>
                            <option value="Temporary" <?php selected($job_type, 'Temporary'); ?>><?php _e('Temporary', 'drivehr'); ?></option>
                            <option value="Internship" <?php selected($job_type, 'Internship'); ?>><?php _e('Internship', 'drivehr'); ?></option>
                            <option value="Remote" <?php selected($job_type, 'Remote'); ?>><?php _e('Remote', 'drivehr'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="drivehr_employment_type"><?php _e('Employment Type', 'drivehr'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="drivehr_employment_type" name="employment_type" value="<?php echo esc_attr($employment_type); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="drivehr_salary_range"><?php _e('Salary Range', 'drivehr'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="drivehr_salary_range" name="salary_range" value="<?php echo esc_attr($salary_range); ?>" class="regular-text" />
                        <p class="description"><?php _e('e.g., $50,000 - $70,000', 'drivehr'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="drivehr_apply_url"><?php _e('Apply URL', 'drivehr'); ?></label>
                    </th>
                    <td>
                        <input type="url" id="drivehr_apply_url" name="apply_url" value="<?php echo esc_attr($apply_url); ?>" class="large-text" />
                        <?php if ($apply_url) : ?>
                            <p><a href="<?php echo esc_url($apply_url); ?>" target="_blank" class="button button-small"><?php _e('View Application Page', 'drivehr'); ?></a></p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="drivehr_posted_date"><?php _e('Posted Date', 'drivehr'); ?></label>
                    </th>
                    <td>
                        <input type="date" id="drivehr_posted_date" name="posted_date" value="<?php echo esc_attr($posted_date); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="drivehr_expiry_date"><?php _e('Expiry Date', 'drivehr'); ?></label>
                    </th>
                    <td>
                        <input type="date" id="drivehr_expiry_date" name="expiry_date" value="<?php echo esc_attr($expiry_date); ?>" class="regular-text" />
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Render synchronization info meta box
     * 
     * @param WP_Post $post Current post object
     */
    public function render_sync_info_meta_box($post): void {
        $source = get_post_meta($post->ID, 'source', true);
        $source_url = get_post_meta($post->ID, 'source_url', true);
        $last_updated = get_post_meta($post->ID, 'last_updated', true);
        $sync_version = get_post_meta($post->ID, 'sync_version', true);
        $raw_data = get_post_meta($post->ID, 'raw_data', true);
        
        ?>
        <div class="drivehr-sync-info">
            <p><strong><?php _e('Source:', 'drivehr'); ?></strong> <?php echo esc_html($source ?: 'N/A'); ?></p>
            
            <?php if ($source_url) : ?>
                <p><strong><?php _e('Source URL:', 'drivehr'); ?></strong> 
                   <a href="<?php echo esc_url($source_url); ?>" target="_blank"><?php _e('View Original', 'drivehr'); ?></a>
                </p>
            <?php endif; ?>
            
            <?php if ($last_updated) : ?>
                <p><strong><?php _e('Last Synced:', 'drivehr'); ?></strong><br>
                   <?php echo esc_html(wp_date('M j, Y g:i A', strtotime($last_updated))); ?>
                </p>
            <?php endif; ?>
            
            <?php if ($sync_version) : ?>
                <p><strong><?php _e('Sync Version:', 'drivehr'); ?></strong> <?php echo esc_html($sync_version); ?></p>
            <?php endif; ?>
            
            <?php if ($raw_data && current_user_can('manage_options')) : ?>
                <details style="margin-top: 15px;">
                    <summary style="cursor: pointer; font-weight: bold;"><?php _e('Raw Data (Admin Only)', 'drivehr'); ?></summary>
                    <pre style="background: #f1f1f1; padding: 10px; font-size: 11px; overflow-x: auto; margin-top: 10px;"><?php echo esc_html(wp_json_encode(json_decode($raw_data), JSON_PRETTY_PRINT)); ?></pre>
                </details>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Save job meta data from admin interface
     * 
     * @param int $post_id Post ID being saved
     */
    public function save_job_meta_data(int $post_id): void {
        // Verify nonce for security
        if (!isset($_POST['drivehr_job_details_nonce']) || 
            !wp_verify_nonce($_POST['drivehr_job_details_nonce'], 'drivehr_job_details')) {
            return;
        }
        
        // Check if user can edit this post
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Don't save during autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Save meta fields (excluding job_id which is read-only)
        $meta_fields = [
            'department',
            'location', 
            'job_type',
            'employment_type',
            'salary_range',
            'apply_url',
            'posted_date',
            'expiry_date'
        ];
        
        foreach ($meta_fields as $field) {
            if (isset($_POST[ $field ])) {
                $value = sanitize_text_field($_POST[ $field ]);
                if ($field === 'apply_url') {
                    $value = esc_url_raw($value);
                }
                update_post_meta($post_id, $field, $value);
            }
        }
        
        // Update last modified timestamp when manually edited
        update_post_meta($post_id, 'last_updated', current_time('mysql'));
    }
    
    /**
     * Add custom columns to jobs list table
     * 
     * @param array $columns Existing columns
     * @return array Modified columns
     */
    public function add_custom_columns(array $columns): array {
        $new_columns = [];
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = $columns['title'];
        $new_columns['drivehr_job_id'] = __('Job ID', 'drivehr');
        $new_columns['drivehr_department'] = __('Department', 'drivehr');
        $new_columns['drivehr_location'] = __('Location', 'drivehr');
        $new_columns['drivehr_job_type'] = __('Type', 'drivehr');
        $new_columns['drivehr_last_updated'] = __('Last Synced', 'drivehr');
        $new_columns['drivehr_apply_url'] = __('Apply', 'drivehr');
        $new_columns['date'] = $columns['date'];
        
        return $new_columns;
    }
    
    /**
     * Populate custom columns in jobs list table
     * 
     * @param string $column Column name
     * @param int $post_id Post ID
     */
    public function populate_custom_columns(string $column, int $post_id): void {
        switch ($column) {
            case 'drivehr_job_id':
                $job_id = get_post_meta($post_id, 'job_id', true);
                echo esc_html($job_id ?: '—');
                break;
                
            case 'drivehr_department':
                $department = get_post_meta($post_id, 'department', true);
                echo esc_html($department ?: '—');
                break;
                
            case 'drivehr_location':
                $location = get_post_meta($post_id, 'location', true);
                echo esc_html($location ?: '—');
                break;
                
            case 'drivehr_job_type':
                $job_type = get_post_meta($post_id, 'job_type', true);
                if ($job_type) {
                    echo '<span class="drivehr-job-type drivehr-job-type-' . esc_attr(strtolower(str_replace(' ', '-', $job_type))) . '">';
                    echo esc_html($job_type);
                    echo '</span>';
                } else {
                    echo '—';
                }
                break;
                
            case 'drivehr_last_updated':
                $last_updated = get_post_meta($post_id, 'last_updated', true);
                if ($last_updated) {
                    $time_diff = human_time_diff(strtotime($last_updated), current_time('timestamp'));
                    echo '<abbr title="' . esc_attr(wp_date('M j, Y g:i A', strtotime($last_updated))) . '">';
                    echo esc_html($time_diff . ' ago');
                    echo '</abbr>';
                } else {
                    echo '—';
                }
                break;
                
            case 'drivehr_apply_url':
                $apply_url = get_post_meta($post_id, 'apply_url', true);
                if ($apply_url) {
                    echo '<a href="' . esc_url($apply_url) . '" target="_blank" class="button button-small">' . esc_html__('Apply', 'drivehr') . '</a>';
                } else {
                    echo '—';
                }
                break;
        }
    }
    
    /**
     * Make columns sortable
     * 
     * @param array $columns Sortable columns
     * @return array Modified sortable columns
     */
    public function make_columns_sortable(array $columns): array {
        $columns['drivehr_department'] = 'drivehr_department';
        $columns['drivehr_location'] = 'drivehr_location';
        $columns['drivehr_job_type'] = 'drivehr_job_type';
        $columns['drivehr_last_updated'] = 'drivehr_last_updated';
        
        return $columns;
    }
    
    /**
     * Handle column sorting
     * 
     * @param WP_Query $query Main query object
     */
    public function handle_column_sorting($query): void {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }
        
        if ('drivehr_job' !== $query->get('post_type')) {
            return;
        }
        
        $orderby = $query->get('orderby');
        
        switch ($orderby) {
            case 'drivehr_department':
                $query->set('meta_key', 'department');
                $query->set('orderby', 'meta_value');
                break;
                
            case 'drivehr_location':
                $query->set('meta_key', 'location');
                $query->set('orderby', 'meta_value');
                break;
                
            case 'drivehr_job_type':
                $query->set('meta_key', 'job_type');
                $query->set('orderby', 'meta_value');
                break;
                
            case 'drivehr_last_updated':
                $query->set('meta_key', 'last_updated');
                $query->set('orderby', 'meta_value');
                break;
        }
    }
    
    /**
     * Enqueue admin styles
     * 
     * @param string $hook_suffix Current admin page
     */
    public function enqueue_admin_styles($hook_suffix): void {
        if (in_array($hook_suffix, ['edit.php', 'post.php', 'post-new.php'], true)) {
            $screen = get_current_screen();
            if ($screen && 'drivehr_job' === $screen->post_type) {
                wp_enqueue_style('drivehr-admin', false, [], DRIVEHR_WEBHOOK_VERSION);
            }
        }
    }
    
    /**
     * Add inline admin styles
     */
    public function add_admin_styles(): void {
        $screen = get_current_screen();
        if ($screen && 'drivehr_job' === $screen->post_type) {
            ?>
            <style type="text/css">
                .drivehr-job-type {
                    display: inline-block;
                    padding: 2px 8px;
                    border-radius: 3px;
                    font-size: 11px;
                    font-weight: 500;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                .drivehr-job-type-full-time { background: #d4edda; color: #155724; }
                .drivehr-job-type-part-time { background: #fff3cd; color: #856404; }
                .drivehr-job-type-contract { background: #cce5ff; color: #004085; }
                .drivehr-job-type-temporary { background: #f8d7da; color: #721c24; }
                .drivehr-job-type-internship { background: #e2e3e5; color: #383d41; }
                .drivehr-job-type-remote { background: #d1ecf1; color: #0c5460; }

                .drivehr-sync-info p {
                    margin: 8px 0;
                }

                #drivehr_job_details .form-table th {
                    width: 150px;
                }

                .column-drivehr_apply_url {
                    width: 80px;
                }

                .column-drivehr_last_updated {
                    width: 120px;
                }

                .column-drivehr_job_id {
                    width: 100px;
                    font-family: monospace;
                }

                /* Manual Sync Button Styles */
                .drivehr-sync-panel {
                    display: flex;
                    align-items: center;
                    gap: 15px;
                    padding: 12px 15px;
                    background: #f0f6fc;
                    border: 1px solid #c8d8e9;
                    border-left: 4px solid #2271b1;
                }
                .drivehr-sync-panel .button {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                }
                .drivehr-sync-panel .spinner {
                    float: none;
                    margin: 0;
                }
                .drivehr-sync-status {
                    font-size: 13px;
                    color: #50575e;
                }
                .drivehr-sync-status.success {
                    color: #00a32a;
                    font-weight: 500;
                }
                .drivehr-sync-status.error {
                    color: #d63638;
                    font-weight: 500;
                }
            </style>
            <?php
        }
    }

    /**
     * Render manual sync button on DriveHR jobs list page
     *
     * Displays a "Sync Now" button that allows administrators to manually
     * trigger a job synchronization from DriveHR without waiting for the
     * scheduled cron job.
     *
     * @since 2.1.0
     */
    public function render_sync_button(): void {
        $screen = get_current_screen();

        // Only show on DriveHR jobs list page
        if (!$screen || $screen->id !== 'edit-drivehr_job') {
            return;
        }

        // Only show to users who can manage options
        if (!current_user_can('manage_options')) {
            return;
        }

        // Check if manual sync is configured
        if (!defined('DRIVEHR_NETLIFY_TRIGGER_URL') || empty(DRIVEHR_NETLIFY_TRIGGER_URL)) {
            echo '<div class="notice notice-info drivehr-sync-panel">
                <p><strong>Manual Sync:</strong> To enable manual sync, add your Netlify trigger URL to wp-config.php:<br>
                <code>define(\'DRIVEHR_NETLIFY_TRIGGER_URL\', \'https://your-site.netlify.app/.netlify/functions/manual-trigger\');</code></p>
            </div>';
            return;
        }

        ?>
        <div class="notice drivehr-sync-panel">
            <button type="button" id="drivehr-sync-now" class="button button-primary">
                <span class="dashicons dashicons-update" style="margin-top: 3px;"></span>
                <?php _e('Sync Jobs Now', 'drivehr'); ?>
            </button>
            <span class="spinner" id="drivehr-sync-spinner"></span>
            <span class="drivehr-sync-status" id="drivehr-sync-status">
                <?php _e('Click to manually sync jobs from DriveHR', 'drivehr'); ?>
            </span>
        </div>
        <?php
    }

    /**
     * Enqueue JavaScript for manual sync functionality
     *
     * @since 2.1.0
     * @param string $hook_suffix Current admin page
     */
    public function enqueue_sync_scripts($hook_suffix): void {
        $screen = get_current_screen();

        // Only load on DriveHR jobs list page
        if (!$screen || $screen->id !== 'edit-drivehr_job') {
            return;
        }

        // Only for users who can manage options
        if (!current_user_can('manage_options')) {
            return;
        }

        // Only if manual sync is configured
        if (!defined('DRIVEHR_NETLIFY_TRIGGER_URL') || empty(DRIVEHR_NETLIFY_TRIGGER_URL)) {
            return;
        }

        // Inline script for sync functionality
        wp_add_inline_script('jquery', $this->get_sync_javascript());
    }

    /**
     * Get JavaScript for manual sync button
     *
     * @since 2.1.0
     * @return string JavaScript code
     */
    private function get_sync_javascript(): string {
        $nonce = wp_create_nonce('drivehr_manual_sync');
        $ajax_url = admin_url('admin-ajax.php');

        return "
        jQuery(document).ready(function($) {
            $('#drivehr-sync-now').on('click', function() {
                var button = $(this);
                var spinner = $('#drivehr-sync-spinner');
                var status = $('#drivehr-sync-status');

                // Disable button and show spinner
                button.prop('disabled', true);
                spinner.addClass('is-active');
                status.removeClass('success error').text('Triggering sync...');

                $.ajax({
                    url: '{$ajax_url}',
                    type: 'POST',
                    data: {
                        action: 'drivehr_manual_sync',
                        nonce: '{$nonce}',
                        force_sync: true
                    },
                    success: function(response) {
                        spinner.removeClass('is-active');
                        button.prop('disabled', false);

                        if (response.success) {
                            status.addClass('success').text(response.data.message || 'Sync triggered successfully! Jobs will update shortly.');
                            // Refresh the page after 5 seconds to show updated jobs
                            setTimeout(function() {
                                status.text('Refreshing page...');
                                location.reload();
                            }, 5000);
                        } else {
                            status.addClass('error').text(response.data.message || 'Sync failed. Please try again.');
                        }
                    },
                    error: function(xhr, textStatus, errorThrown) {
                        spinner.removeClass('is-active');
                        button.prop('disabled', false);
                        status.addClass('error').text('Connection error: ' + errorThrown);
                    }
                });
            });
        });
        ";
    }

    /**
     * Handle AJAX request for manual sync trigger
     *
     * Calls the Netlify manual-trigger function with proper HMAC authentication
     * to initiate a GitHub Actions workflow that scrapes and syncs jobs.
     *
     * @since 2.1.0
     */
    public function handle_manual_sync_ajax(): void {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'drivehr_manual_sync')) {
            wp_send_json_error(['message' => 'Security check failed']);
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
            return;
        }

        // Check configuration
        if (!defined('DRIVEHR_NETLIFY_TRIGGER_URL') || empty(DRIVEHR_NETLIFY_TRIGGER_URL)) {
            wp_send_json_error(['message' => 'Netlify trigger URL not configured']);
            return;
        }

        if (!defined('DRIVEHR_WEBHOOK_SECRET') || empty(DRIVEHR_WEBHOOK_SECRET)) {
            wp_send_json_error(['message' => 'Webhook secret not configured']);
            return;
        }

        // Prepare payload
        $payload = wp_json_encode([
            'force_sync' => isset($_POST['force_sync']) && $_POST['force_sync'],
            'reason' => 'Manual sync from WordPress admin',
            'source' => 'wordpress-admin',
        ]);

        // Generate HMAC signature
        $signature = 'sha256=' . hash_hmac('sha256', $payload, DRIVEHR_WEBHOOK_SECRET);

        // Make request to Netlify function
        $response = wp_remote_post(DRIVEHR_NETLIFY_TRIGGER_URL, [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Webhook-Signature' => $signature,
            ],
            'body' => $payload,
            'timeout' => 30,
        ]);

        // Handle response
        if (is_wp_error($response)) {
            wp_send_json_error([
                'message' => 'Failed to connect: ' . $response->get_error_message()
            ]);
            return;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code === 200 && isset($data['success']) && $data['success']) {
            // Log successful trigger
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[DriveHR] Manual sync triggered successfully by user ' . wp_get_current_user()->user_login);
            }

            wp_send_json_success([
                'message' => 'Sync triggered successfully! Jobs will update in 1-2 minutes.',
                'request_id' => $data['requestId'] ?? null,
            ]);
        } else {
            $error_message = $data['error'] ?? $data['message'] ?? 'Unknown error';

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[DriveHR] Manual sync failed: ' . $error_message);
            }

            wp_send_json_error([
                'message' => 'Sync trigger failed: ' . $error_message
            ]);
        }
    }
}