<?php
/**
 * DriveHR Jobs Custom Post Type
 * 
 * Handles the registration and management of the drivehr_job custom post type
 * for storing synchronized job postings from DriveHR.
 * 
 * @package DriveHR
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

/**
 * DriveHR Post Type Class
 * 
 * Manages the drivehr_job custom post type registration and configuration.
 */
class DriveHR_Post_Type {
    
    /**
     * Initialize the post type
     */
    public function __construct() {
        add_action('init', [$this, 'register_post_type']);
        add_action('init', [$this, 'register_taxonomies']);
    }
    
    /**
     * Register DriveHR Jobs custom post type
     * 
     * Creates a custom post type for storing synchronized job postings from DriveHR
     * with proper labels, capabilities, and REST API support for potential future
     * headless implementations.
     */
    public function register_post_type(): void {
        $labels = [
            'name' => __('DriveHR Jobs', 'drivehr'),
            'singular_name' => __('Job', 'drivehr'),
            'add_new' => __('Add New Job', 'drivehr'),
            'add_new_item' => __('Add New Job', 'drivehr'),
            'edit_item' => __('Edit Job', 'drivehr'),
            'new_item' => __('New Job', 'drivehr'),
            'view_item' => __('View Job', 'drivehr'),
            'view_items' => __('View Jobs', 'drivehr'),
            'search_items' => __('Search Jobs', 'drivehr'),
            'not_found' => __('No jobs found', 'drivehr'),
            'not_found_in_trash' => __('No jobs found in trash', 'drivehr'),
            'parent_item_colon' => __('Parent Job:', 'drivehr'),
            'all_items' => __('All Jobs', 'drivehr'),
            'archives' => __('Job Archives', 'drivehr'),
            'attributes' => __('Job Attributes', 'drivehr'),
            'insert_into_item' => __('Insert into job', 'drivehr'),
            'uploaded_to_this_item' => __('Uploaded to this job', 'drivehr'),
            'featured_image' => __('Job Image', 'drivehr'),
            'set_featured_image' => __('Set job image', 'drivehr'),
            'remove_featured_image' => __('Remove job image', 'drivehr'),
            'use_featured_image' => __('Use as job image', 'drivehr'),
            'menu_name' => __('DriveHR Jobs', 'drivehr'),
            'filter_items_list' => __('Filter jobs list', 'drivehr'),
            'filter_by_date' => __('Filter jobs by date', 'drivehr'),
            'items_list_navigation' => __('Jobs list navigation', 'drivehr'),
            'items_list' => __('Jobs list', 'drivehr'),
            'item_published' => __('Job published', 'drivehr'),
            'item_published_privately' => __('Job published privately', 'drivehr'),
            'item_reverted_to_draft' => __('Job reverted to draft', 'drivehr'),
            'item_scheduled' => __('Job scheduled', 'drivehr'),
            'item_updated' => __('Job updated', 'drivehr'),
        ];
        
        $args = [
            'labels' => $labels,
            'description' => __('Job postings synchronized from DriveHR', 'drivehr'),
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_nav_menus' => true,
            'show_in_admin_bar' => true,
            'show_in_rest' => true,
            'rest_base' => 'drivehr-jobs',
            'rest_controller_class' => 'WP_REST_Posts_Controller',
            'menu_position' => 20,
            'menu_icon' => 'dashicons-businesswoman',
            'capability_type' => 'post',
            'capabilities' => [
                'edit_post' => 'edit_drivehr_job',
                'read_post' => 'read_drivehr_job',
                'delete_post' => 'delete_drivehr_job',
                'edit_posts' => 'edit_drivehr_jobs',
                'edit_others_posts' => 'edit_others_drivehr_jobs',
                'publish_posts' => 'publish_drivehr_jobs',
                'read_private_posts' => 'read_private_drivehr_jobs',
                'create_posts' => 'create_drivehr_jobs',
            ],
            'map_meta_cap' => true,
            'hierarchical' => false,
            'supports' => [
                'title',
                'editor',
                'excerpt',
                'custom-fields',
                'revisions',
                'page-attributes',
                'post-formats'
            ],
            'has_archive' => true,
            'rewrite' => [
                'slug' => 'jobs',
                'with_front' => false,
                'pages' => true,
                'feeds' => true,
            ],
            'query_var' => true,
            'can_export' => true,
            'delete_with_user' => false,
            'exclude_from_search' => false,
            'template' => [
                [
                    'core/heading',
                    [
                        'level' => 2,
                        'content' => __('Job Description', 'drivehr')
                    ]
                ],
                ['core/paragraph', ['placeholder' => __('Enter job description...', 'drivehr')]],
                [
                    'core/heading',
                    [
                        'level' => 3,
                        'content' => __('Requirements', 'drivehr')
                    ]
                ],
                ['core/list', ['placeholder' => __('List job requirements...', 'drivehr')]],
                [
                    'core/heading',
                    [
                        'level' => 3,
                        'content' => __('Benefits', 'drivehr')
                    ]
                ],
                ['core/list', ['placeholder' => __('List job benefits...', 'drivehr')]],
            ],
            'template_lock' => false,
        ];
        
        register_post_type('drivehr_job', $args);
    }
    
    /**
     * Register custom taxonomies for job organization
     */
    public function register_taxonomies(): void {
        // Job Department taxonomy
        $department_labels = [
            'name' => __('Job Departments', 'drivehr'),
            'singular_name' => __('Job Department', 'drivehr'),
            'search_items' => __('Search Departments', 'drivehr'),
            'all_items' => __('All Departments', 'drivehr'),
            'parent_item' => __('Parent Department', 'drivehr'),
            'parent_item_colon' => __('Parent Department:', 'drivehr'),
            'edit_item' => __('Edit Department', 'drivehr'),
            'update_item' => __('Update Department', 'drivehr'),
            'add_new_item' => __('Add New Department', 'drivehr'),
            'new_item_name' => __('New Department Name', 'drivehr'),
            'menu_name' => __('Departments', 'drivehr'),
        ];
        
        register_taxonomy('drivehr_department', 'drivehr_job', [
            'labels' => $department_labels,
            'hierarchical' => true,
            'public' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_tagcloud' => false,
            'show_in_rest' => true,
            'rest_base' => 'drivehr-departments',
            'rewrite' => ['slug' => 'job-department'],
        ]);
        
        // Job Location taxonomy
        $location_labels = [
            'name' => __('Job Locations', 'drivehr'),
            'singular_name' => __('Job Location', 'drivehr'),
            'search_items' => __('Search Locations', 'drivehr'),
            'all_items' => __('All Locations', 'drivehr'),
            'parent_item' => __('Parent Location', 'drivehr'),
            'parent_item_colon' => __('Parent Location:', 'drivehr'),
            'edit_item' => __('Edit Location', 'drivehr'),
            'update_item' => __('Update Location', 'drivehr'),
            'add_new_item' => __('Add New Location', 'drivehr'),
            'new_item_name' => __('New Location Name', 'drivehr'),
            'menu_name' => __('Locations', 'drivehr'),
        ];
        
        register_taxonomy('drivehr_location', 'drivehr_job', [
            'labels' => $location_labels,
            'hierarchical' => true,
            'public' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_tagcloud' => false,
            'show_in_rest' => true,
            'rest_base' => 'drivehr-locations',
            'rewrite' => ['slug' => 'job-location'],
        ]);
        
        // Job Type taxonomy
        $type_labels = [
            'name' => __('Job Types', 'drivehr'),
            'singular_name' => __('Job Type', 'drivehr'),
            'search_items' => __('Search Types', 'drivehr'),
            'all_items' => __('All Types', 'drivehr'),
            'edit_item' => __('Edit Type', 'drivehr'),
            'update_item' => __('Update Type', 'drivehr'),
            'add_new_item' => __('Add New Type', 'drivehr'),
            'new_item_name' => __('New Type Name', 'drivehr'),
            'menu_name' => __('Job Types', 'drivehr'),
        ];
        
        register_taxonomy('drivehr_job_type', 'drivehr_job', [
            'labels' => $type_labels,
            'hierarchical' => false,
            'public' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_tagcloud' => true,
            'show_in_rest' => true,
            'rest_base' => 'drivehr-job-types',
            'rewrite' => ['slug' => 'job-type'],
        ]);
    }
}