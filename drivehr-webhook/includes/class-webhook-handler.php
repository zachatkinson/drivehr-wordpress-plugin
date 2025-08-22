<?php
/**
 * DriveHR Webhook Handler Class
 * 
 * Handles incoming webhook requests from the DriveHR Netlify function with
 * enterprise-grade security and error handling. Implements SOLID principles
 * with clear separation of concerns for webhook verification, job processing,
 * and data storage operations.
 * 
 * @package DriveHR
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

/**
 * DriveHR Webhook Handler Class
 * 
 * Handles incoming webhook requests from the DriveHR Netlify function with
 * enterprise-grade security and error handling.
 */
class DriveHR_Webhook_Handler {
    
    /**
     * Webhook endpoint path - must match Netlify function configuration
     */
    private const WEBHOOK_PATH = '/webhook/drivehr-sync';
    
    /**
     * Maximum jobs allowed per webhook request to prevent resource exhaustion
     */
    private const MAX_JOBS_PER_REQUEST = 100;
    
    /**
     * Rate limit: maximum requests per minute per IP address
     */
    private const RATE_LIMIT_MAX_REQUESTS = 10;
    
    /**
     * Rate limit time window in seconds
     */
    private const RATE_LIMIT_WINDOW = 60;
    
    /**
     * Maximum timestamp difference (seconds) to prevent replay attacks
     */
    private const MAX_TIMESTAMP_DRIFT = 300; // 5 minutes
    
    /**
     * Initialize webhook handler
     * 
     * Registers the webhook endpoint handler on WordPress init.
     * Only activates if webhook is enabled via configuration.
     */
    public function __construct() {
        add_action('init', [$this, 'handle_webhook']);
    }
    
    /**
     * Main webhook request handler
     * 
     * Processes incoming webhook requests with comprehensive security checks
     * including path validation, method verification, rate limiting, signature
     * validation, and payload processing.
     * 
     * Security flow:
     * 1. Check if webhook service is enabled
     * 2. Validate request path and method
     * 3. Apply rate limiting by IP address
     * 4. Verify HMAC signature with timestamp
     * 5. Process and store job data
     * 6. Return structured response
     * 
     * @return void Exits with JSON response
     */
    public function handle_webhook(): void {
        // Only handle requests to our webhook endpoint
        $request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        if ($request_uri !== self::WEBHOOK_PATH) {
            return;
        }
        
        // Fire webhook start action for integrations
        do_action('drivehr_webhook_start');
        
        // Check if webhook service is enabled
        if (!$this->is_webhook_enabled()) {
            $this->log_webhook_activity('Webhook disabled', ['uri' => $request_uri]);
            $this->respond(503, [
                'error' => 'Service temporarily unavailable',
                'timestamp' => current_time('c')
            ]);
        }
        
        // Only handle POST requests
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->log_webhook_activity('Invalid method', ['method' => $_SERVER['REQUEST_METHOD']]);
            $this->respond(405, [
                'error' => 'Method not allowed',
                'allowed_methods' => ['POST'],
                'timestamp' => current_time('c')
            ]);
        }
        
        // Apply rate limiting by IP
        if (!$this->check_rate_limit()) {
            $this->log_webhook_activity('Rate limit exceeded', ['ip' => $this->get_client_ip()]);
            $this->respond(429, [
                'error' => 'Rate limit exceeded',
                'retry_after' => self::RATE_LIMIT_WINDOW,
                'timestamp' => current_time('c')
            ]);
        }
        
        // Verify webhook signature and timestamp
        if (!$this->verify_signature()) {
            $this->log_webhook_activity('Invalid signature', ['ip' => $this->get_client_ip()]);
            $this->respond(401, [
                'error' => 'Unauthorized - Invalid signature',
                'timestamp' => current_time('c')
            ]);
        }
        
        // Parse and validate JSON payload
        $payload = file_get_contents('php://input');
        $data = json_decode($payload, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log_webhook_activity('Invalid JSON', ['error' => json_last_error_msg()]);
            $this->respond(400, [
                'error' => 'Invalid JSON format',
                'timestamp' => current_time('c')
            ]);
        }
        
        // Validate required data structure
        if (!$this->validate_webhook_data($data)) {
            $this->log_webhook_activity('Invalid data structure', ['data_keys' => array_keys($data ?? [])]);
            $this->respond(400, [
                'error' => 'Invalid webhook data structure',
                'expected' => ['jobs' => 'array'],
                'timestamp' => current_time('c')
            ]);
        }
        
        // Process jobs with error handling
        try {
            $result = $this->process_jobs($data['jobs']);
            
            // Remove stale jobs that are no longer in DriveHR
            $current_job_ids = array_column($data['jobs'], 'id');
            $removal_result = $this->remove_stale_jobs($current_job_ids);
            $result['removed'] = $removal_result['removed'];
            
            $this->log_webhook_activity('Jobs processed successfully', $result);
            
            // Fire webhook end action for integrations
            do_action('drivehr_webhook_end', $result);
            
            $this->respond(200, $result);
        } catch (Exception $e) {
            $this->log_webhook_activity('Processing failed', ['error' => $e->getMessage()]);
            
            // Fire webhook end action with error
            do_action('drivehr_webhook_end', ['error' => $e->getMessage()]);
            
            $this->respond(500, [
                'error' => 'Internal server error',
                'timestamp' => current_time('c')
            ]);
        }
    }
    
    /**
     * Check if webhook service is enabled
     * 
     * @return bool True if webhook is enabled via configuration
     */
    private function is_webhook_enabled(): bool {
        return defined('DRIVEHR_WEBHOOK_ENABLED') && DRIVEHR_WEBHOOK_ENABLED === true;
    }
    
    /**
     * Get webhook secret from environment configuration
     * 
     * @return string Webhook secret or empty string if not configured
     */
    private function get_webhook_secret(): string {
        if (!defined('DRIVEHR_WEBHOOK_SECRET') || empty(DRIVEHR_WEBHOOK_SECRET)) {
            return '';
        }
        return DRIVEHR_WEBHOOK_SECRET;
    }
    
    /**
     * Get client IP address with proxy support
     * 
     * @return string Client IP address
     */
    private function get_client_ip(): string {
        // Check for IP from various headers (proxy-aware)
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Standard proxy header
            'HTTP_X_FORWARDED',          // Alternative proxy header
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster environments
            'HTTP_FORWARDED_FOR',        // RFC 7239
            'HTTP_FORWARDED',            // RFC 7239
            'REMOTE_ADDR'                // Direct connection
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = trim(explode(',', $_SERVER[$header])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Rate limiting check by IP address
     * 
     * Uses WordPress transients to track request counts per IP address
     * within the configured time window.
     * 
     * @return bool True if request is within rate limits
     */
    private function check_rate_limit(): bool {
        $ip = $this->get_client_ip();
        $transient_key = 'drivehr_webhook_rate_' . md5($ip);
        
        $requests = get_transient($transient_key);
        if ($requests === false) {
            // First request in window
            set_transient($transient_key, 1, self::RATE_LIMIT_WINDOW);
            return true;
        }
        
        if ($requests >= self::RATE_LIMIT_MAX_REQUESTS) {
            return false;
        }
        
        // Increment request count
        set_transient($transient_key, $requests + 1, self::RATE_LIMIT_WINDOW);
        return true;
    }
    
    /**
     * Verify HMAC signature with timestamp validation
     * 
     * Implements secure webhook verification using HMAC-SHA256 with
     * timing-safe comparison and replay attack protection via timestamp
     * validation.
     * 
     * Expected headers from Netlify function:
     * - X-Webhook-Signature: sha256=<hmac_hash>
     * - X-Webhook-Timestamp: <unix_timestamp>
     * 
     * @return bool True if signature and timestamp are valid
     */
    private function verify_signature(): bool {
        // Get signature and timestamp from headers
        $signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';
        $timestamp = $_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] ?? '';
        
        // Validate timestamp to prevent replay attacks
        if (empty($timestamp) || !is_numeric($timestamp)) {
            return false;
        }
        
        $timestamp_diff = abs(time() - intval($timestamp));
        if ($timestamp_diff > self::MAX_TIMESTAMP_DRIFT) {
            return false;
        }
        
        // Get webhook secret
        $secret = $this->get_webhook_secret();
        if (empty($secret)) {
            return false;
        }
        
        // Validate signature format
        if (empty($signature) || substr($signature, 0, 7) !== 'sha256=') {
            return false;
        }
        
        // Calculate expected signature
        $payload = file_get_contents('php://input');
        $expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);
        
        // Use timing-safe comparison to prevent timing attacks
        return hash_equals($signature, $expected);
    }
    
    /**
     * Validate webhook data structure
     * 
     * @param mixed $data Decoded JSON payload
     * @return bool True if data structure is valid
     */
    private function validate_webhook_data($data): bool {
        if (!is_array($data)) {
            return false;
        }
        
        if (!isset($data['jobs']) || !is_array($data['jobs'])) {
            return false;
        }
        
        // Check job count limits
        if (count($data['jobs']) > self::MAX_JOBS_PER_REQUEST) {
            return false;
        }
        
        // Validate job structure (at least one job should have required fields)
        if (!empty($data['jobs'])) {
            $first_job = $data['jobs'][0];
            if (!isset($first_job['id'], $first_job['title'])) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Process array of jobs from webhook payload
     * 
     * Iterates through jobs array and stores each job as a WordPress post
     * with proper error handling and transaction safety.
     * 
     * @param array $jobs Array of job data from webhook
     * @return array Processing results with counts and errors
     * @throws Exception If critical processing error occurs
     */
    private function process_jobs(array $jobs): array {
        $processed = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        
        foreach ($jobs as $index => $job) {
            if (!is_array($job)) {
                $errors[] = "Job at index {$index}: Invalid job data format";
                continue;
            }
            
            try {
                $result = $this->store_job($job);
                if ($result['action'] === 'created') {
                    $processed++;
                } elseif ($result['action'] === 'updated') {
                    $updated++;
                } else {
                    $skipped++;
                }
            } catch (Exception $e) {
                $job_id = isset($job['id']) ? $job['id'] : 'unknown';
                $errors[] = "Job '{$job_id}': " . $e->getMessage();
            }
        }
        
        return [
            'success' => true,
            'processed' => $processed,
            'updated' => $updated,
            'skipped' => $skipped,
            'total' => count($jobs),
            'errors' => $errors,
            'timestamp' => current_time('c'),
            'source' => 'drivehr-netlify-sync'
        ];
    }
    
    /**
     * Store or update a single job in WordPress
     * 
     * Handles both new job creation and existing job updates with proper
     * data sanitization, duplicate detection, and transaction safety.
     * 
     * @param array $job Job data from webhook payload
     * @return array Result with action taken and post ID
     * @throws Exception If job storage fails
     */
    private function store_job(array $job): array {
        // Validate required fields
        if (empty($job['id']) || empty($job['title'])) {
            throw new Exception('Missing required fields: id and title are required');
        }
        
        global $wpdb;
        
        // Start database transaction for atomicity
        $wpdb->query('START TRANSACTION');
        
        try {
            // Check if job already exists
            $existing_posts = get_posts([
                'post_type' => 'drivehr_job',
                'meta_query' => [
                    [
                        'key' => 'job_id',
                        'value' => sanitize_text_field($job['id']),
                        'compare' => '='
                    ]
                ],
                'posts_per_page' => 1,
                'fields' => 'ids'
            ]);
            
            // Prepare sanitized job data
            $post_data = [
                'post_type' => 'drivehr_job',
                'post_title' => sanitize_text_field($job['title']),
                'post_content' => wp_kses_post($job['description'] ?? ''),
                'post_excerpt' => sanitize_textarea_field($job['summary'] ?? ''),
                'post_status' => 'publish',
                'post_date' => $this->parse_date($job['postedDate'] ?? $job['posted_date'] ?? ''),
                'meta_input' => [
                    'job_id' => sanitize_text_field($job['id']),
                    'department' => sanitize_text_field($job['department'] ?? ''),
                    'location' => sanitize_text_field($job['location'] ?? ''),
                    'job_type' => sanitize_text_field($job['type'] ?? $job['jobType'] ?? ''),
                    'employment_type' => sanitize_text_field($job['employmentType'] ?? ''),
                    'salary_range' => sanitize_text_field($job['salaryRange'] ?? $job['salary_range'] ?? ''),
                    'apply_url' => esc_url_raw($job['applyUrl'] ?? $job['apply_url'] ?? ''),
                    'posted_date' => sanitize_text_field($job['postedDate'] ?? $job['posted_date'] ?? ''),
                    'expiry_date' => sanitize_text_field($job['expiryDate'] ?? $job['expiry_date'] ?? ''),
                    'source' => 'drivehr',
                    'source_url' => esc_url_raw($job['sourceUrl'] ?? ''),
                    'raw_data' => wp_json_encode($job, JSON_UNESCAPED_UNICODE),
                    'last_updated' => current_time('mysql'),
                    'sync_version' => DRIVEHR_WEBHOOK_VERSION
                ]
            ];
            
            $action = 'created';
            
            if (!empty($existing_posts)) {
                // Fire before update action
                do_action('drivehr_before_job_update', $job);
                
                // Update existing job
                $post_data['ID'] = $existing_posts[0];
                $result = wp_update_post($post_data, true);
                $action = 'updated';
            } else {
                // Fire before insert action  
                do_action('drivehr_before_job_insert', $job);
                
                // Create new job
                $result = wp_insert_post($post_data, true);
                $action = 'created';
            }
            
            // Check for WordPress errors
            if (is_wp_error($result)) {
                throw new Exception('WordPress error: ' . $result->get_error_message());
            }
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
            return [
                'action' => $action,
                'post_id' => $result,
                'job_id' => $job['id']
            ];
            
        } catch (Exception $e) {
            // Rollback transaction on any error
            $wpdb->query('ROLLBACK');
            throw $e;
        }
    }
    
    /**
     * Remove stale jobs that are no longer in DriveHR
     * 
     * Compares current job IDs from DriveHR with existing jobs in WordPress
     * and removes any jobs that are no longer present in the DriveHR feed.
     * This maintains perfect parity between DriveHR and WordPress job listings.
     * 
     * @param array $current_job_ids Array of job IDs currently in DriveHR
     * @return array Result with count of removed jobs
     * @throws Exception If job removal fails
     */
    private function remove_stale_jobs(array $current_job_ids): array {
        global $wpdb;
        
        // Start database transaction for atomicity
        $wpdb->query('START TRANSACTION');
        
        try {
            // Get all existing DriveHR jobs in WordPress
            $existing_posts = get_posts([
                'post_type' => 'drivehr_job',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'meta_query' => [
                    [
                        'key' => 'job_id',
                        'compare' => 'EXISTS'
                    ]
                ]
            ]);
            
            $removed = 0;
            $removed_job_ids = [];
            
            foreach ($existing_posts as $post_id) {
                $job_id = get_post_meta($post_id, 'job_id', true);
                
                // If this job is not in the current DriveHR list, remove it
                if (!in_array($job_id, $current_job_ids)) {
                    // Fire before delete action for integrations
                    do_action('drivehr_before_job_delete', $post_id, $job_id);
                    
                    // Permanently delete the job (bypass trash)
                    $delete_result = wp_delete_post($post_id, true);
                    
                    if ($delete_result) {
                        $removed++;
                        $removed_job_ids[] = $job_id;
                        
                        // Fire after delete action for integrations
                        do_action('drivehr_after_job_delete', $post_id, $job_id);
                    }
                }
            }
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
            // Log removal activity
            if ($removed > 0) {
                $this->log_webhook_activity(
                    "Removed {$removed} stale jobs", 
                    ['removed_job_ids' => $removed_job_ids]
                );
            }
            
            return [
                'removed' => $removed,
                'removed_job_ids' => $removed_job_ids
            ];
            
        } catch (Exception $e) {
            // Rollback transaction on any error
            $wpdb->query('ROLLBACK');
            throw new Exception('Failed to remove stale jobs: ' . $e->getMessage());
        }
    }
    
    /**
     * Parse date string to WordPress format
     * 
     * @param string $date_string Date in various formats
     * @return string WordPress-compatible date string
     */
    private function parse_date(string $date_string): string {
        if (empty($date_string)) {
            return current_time('mysql');
        }
        
        // Try to parse the date
        $timestamp = strtotime($date_string);
        if ($timestamp === false) {
            // If parsing fails, use current time
            return current_time('mysql');
        }
        
        return date('Y-m-d H:i:s', $timestamp);
    }
    
    /**
     * Log webhook activity for debugging
     * 
     * Only logs when WP_DEBUG is enabled to avoid performance impact
     * in production environments.
     * 
     * @param string $message Log message
     * @param mixed $data Additional data to log
     * @return void
     */
    private function log_webhook_activity(string $message, $data = null): void {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        $log_entry = '[DriveHR Webhook] ' . $message;
        if ($data !== null) {
            $log_entry .= ' | Data: ' . wp_json_encode($data, JSON_UNESCAPED_UNICODE);
        }
        
        error_log($log_entry);
    }
    
    /**
     * Send JSON response and exit
     * 
     * Sends appropriate HTTP status code and JSON-formatted response
     * with proper security headers.
     * 
     * @param int $status_code HTTP status code
     * @param array $data Response data to JSON encode
     * @return void Exits script execution
     */
    private function respond(int $status_code, array $data): void {
        // Set HTTP status
        status_header($status_code);
        
        // Set security headers
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet');
        
        // Add cache headers for error responses
        if ($status_code >= 400) {
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
        
        // Ensure data has consistent structure
        if (!isset($data['timestamp'])) {
            $data['timestamp'] = current_time('c');
        }
        
        // Output JSON response
        echo wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}