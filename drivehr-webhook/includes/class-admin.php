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
 */
class DriveHR_Admin {
    
    /**
     * Initialize admin functionality
     */
    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_drivehr_job', [$this, 'save_job_meta_data']);
        add_filter('manage_drivehr_job_posts_columns', [$this, 'add_custom_columns']);
        add_action('manage_drivehr_job_posts_custom_column', [$this, 'populate_custom_columns'], 10, 2);
        add_filter('manage_edit-drivehr_job_sortable_columns', [$this, 'make_columns_sortable']);
        add_action('pre_get_posts', [$this, 'handle_column_sorting']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_styles']);
        add_action('admin_head', [$this, 'add_admin_styles']);
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
                        <?php if ($apply_url): ?>
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
            
            <?php if ($source_url): ?>
                <p><strong><?php _e('Source URL:', 'drivehr'); ?></strong> 
                   <a href="<?php echo esc_url($source_url); ?>" target="_blank"><?php _e('View Original', 'drivehr'); ?></a>
                </p>
            <?php endif; ?>
            
            <?php if ($last_updated): ?>
                <p><strong><?php _e('Last Synced:', 'drivehr'); ?></strong><br>
                   <?php echo esc_html(date('M j, Y g:i A', strtotime($last_updated))); ?>
                </p>
            <?php endif; ?>
            
            <?php if ($sync_version): ?>
                <p><strong><?php _e('Sync Version:', 'drivehr'); ?></strong> <?php echo esc_html($sync_version); ?></p>
            <?php endif; ?>
            
            <?php if ($raw_data && current_user_can('manage_options')): ?>
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
            if (isset($_POST[$field])) {
                $value = sanitize_text_field($_POST[$field]);
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
                    $time_diff = human_time_diff(strtotime($last_updated));
                    echo '<abbr title="' . esc_attr(date('M j, Y g:i A', strtotime($last_updated))) . '">';
                    echo esc_html($time_diff . ' ago');
                    echo '</abbr>';
                } else {
                    echo '—';
                }
                break;
                
            case 'drivehr_apply_url':
                $apply_url = get_post_meta($post_id, 'apply_url', true);
                if ($apply_url) {
                    echo '<a href="' . esc_url($apply_url) . '" target="_blank" class="button button-small">' . __('Apply', 'drivehr') . '</a>';
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
            </style>
            <?php
        }
    }
}