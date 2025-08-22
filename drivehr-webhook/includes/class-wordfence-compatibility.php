<?php
/**
 * Wordfence Compatibility Layer
 * 
 * Ensures DriveHR webhook operations are properly identified to Wordfence
 * and don't trigger false positive security alerts.
 * 
 * @package DriveHR
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

/**
 * DriveHR Wordfence Compatibility Class
 * 
 * Handles integration with Wordfence security plugin to prevent
 * false positive alerts during legitimate webhook operations.
 */
class DriveHR_Wordfence_Compatibility {
    
    /**
     * Initialize Wordfence compatibility
     */
    public function __construct() {
        // Hook into Wordfence events if plugin is active
        if ($this->is_wordfence_active()) {
            add_action('init', [$this, 'setup_wordfence_integration'], 5);
            add_filter('wordfence_ls_require_auth', [$this, 'bypass_login_security_for_webhook'], 10, 2);
            add_filter('wordfence_api_request_filter', [$this, 'identify_webhook_requests'], 10, 3);
        }
        
        // Add Wordfence-specific logging
        add_action('drivehr_webhook_start', [$this, 'log_webhook_start']);
        add_action('drivehr_webhook_end', [$this, 'log_webhook_end']);
    }
    
    /**
     * Check if Wordfence is active
     * 
     * @return bool True if Wordfence is active and loaded
     */
    private function is_wordfence_active(): bool {
        return defined('WORDFENCE_VERSION') && class_exists('wordfence');
    }
    
    /**
     * Setup Wordfence integration
     */
    public function setup_wordfence_integration(): void {
        // Register our webhook endpoint as legitimate
        $this->register_legitimate_endpoint();
        
        // Set up database monitoring exemptions
        $this->setup_database_exemptions();
        
        // Configure firewall exemptions if possible
        $this->suggest_firewall_rules();
    }
    
    /**
     * Register webhook endpoint as legitimate to Wordfence
     */
    private function register_legitimate_endpoint(): void {
        if (class_exists('wfConfig')) {
            // Add our endpoint to Wordfence's allowlist if method exists
            if (method_exists('wfConfig', 'addToAllowlist')) {
                wfConfig::addToAllowlist('webhook/drivehr-sync', 'DriveHR Job Sync Endpoint');
            }
        }
    }
    
    /**
     * Setup database operation exemptions
     */
    private function setup_database_exemptions(): void {
        // Mark our operations as legitimate for Wordfence monitoring
        add_action('drivehr_before_job_insert', function($job_data) {
            $this->mark_legitimate_database_operation('insert', 'drivehr_job', $job_data);
        });
        
        add_action('drivehr_before_job_update', function($job_data) {
            $this->mark_legitimate_database_operation('update', 'drivehr_job', $job_data);
        });
    }
    
    /**
     * Mark database operation as legitimate
     * 
     * @param string $operation Type of operation (insert, update, delete)
     * @param string $table Table being modified
     * @param array $data Operation data
     */
    private function mark_legitimate_database_operation(string $operation, string $table, array $data): void {
        // Log the operation for Wordfence context
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[DriveHR-Wordfence] Legitimate %s operation on %s: %s',
                $operation,
                $table,
                wp_json_encode($data, JSON_UNESCAPED_UNICODE)
            ));
        }
        
        // Set a transient to identify this as a legitimate operation
        set_transient(
            'drivehr_legitimate_db_op_' . md5(serialize($data)),
            [
                'operation' => $operation,
                'table' => $table,
                'timestamp' => time(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'DriveHR-Webhook',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
            ],
            300 // 5 minutes
        );
    }
    
    /**
     * Bypass Wordfence login security for webhook endpoint
     * 
     * @param bool $require_auth Whether to require authentication
     * @param string $action Action being performed
     * @return bool Modified authentication requirement
     */
    public function bypass_login_security_for_webhook(bool $require_auth, string $action): bool {
        // Check if this is our webhook request
        if ($this->is_webhook_request()) {
            return false; // Don't require Wordfence login security
        }
        
        return $require_auth;
    }
    
    /**
     * Identify webhook requests to Wordfence
     * 
     * @param bool $is_api_request Whether this is an API request
     * @param string $request_uri Request URI
     * @param array $context Request context
     * @return bool Modified API request status
     */
    public function identify_webhook_requests(bool $is_api_request, string $request_uri, array $context): bool {
        if ($this->is_webhook_request()) {
            // Mark as legitimate API request
            return true;
        }
        
        return $is_api_request;
    }
    
    /**
     * Check if current request is our webhook
     * 
     * @return bool True if this is a webhook request
     */
    private function is_webhook_request(): bool {
        $request_uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        return $request_uri === '/webhook/drivehr-sync';
    }
    
    /**
     * Suggest firewall rules to admin
     */
    private function suggest_firewall_rules(): void {
        // Only show to admins and only once
        if (current_user_can('manage_options') && !get_transient('drivehr_wordfence_rules_suggested')) {
            add_action('admin_notices', [$this, 'show_wordfence_configuration_notice']);
            set_transient('drivehr_wordfence_rules_suggested', true, WEEK_IN_SECONDS);
        }
    }
    
    /**
     * Show Wordfence configuration notice
     */
    public function show_wordfence_configuration_notice(): void {
        if (!$this->is_wordfence_active()) {
            return;
        }
        
        ?>
        <div class="notice notice-info is-dismissible">
            <h3><?php _e('DriveHR + Wordfence Configuration', 'drivehr'); ?></h3>
            <p><?php _e('To ensure DriveHR webhooks work properly with Wordfence, please add these configurations:', 'drivehr'); ?></p>
            
            <h4><?php _e('Wordfence > All Options > Allowlisted URLs:', 'drivehr'); ?></h4>
            <code>/webhook/drivehr-sync</code>
            
            <h4><?php _e('Wordfence > All Options > Rate Limiting:', 'drivehr'); ?></h4>
            <p><?php _e('Add IP whitelist for your Netlify deployment, or create rate limiting exception for webhook endpoint.', 'drivehr'); ?></p>
            
            <h4><?php _e('Optional - Wordfence > Login Security:', 'drivehr'); ?></h4>
            <p><?php _e('Add webhook endpoint to login security bypasses if you experience authentication issues.', 'drivehr'); ?></p>
            
            <p>
                <a href="<?php echo admin_url('admin.php?page=WordfenceWAF'); ?>" class="button button-primary">
                    <?php _e('Configure Wordfence Now', 'drivehr'); ?>
                </a>
                <a href="#" class="button" onclick="this.closest('.notice').style.display='none'">
                    <?php _e('Dismiss', 'drivehr'); ?>
                </a>
            </p>
        </div>
        <?php
    }
    
    /**
     * Log webhook start for Wordfence context
     */
    public function log_webhook_start(): void {
        if ($this->is_wordfence_active()) {
            error_log('[DriveHR-Wordfence] Webhook processing started - legitimate operation');
        }
    }
    
    /**
     * Log webhook end for Wordfence context  
     */
    public function log_webhook_end(): void {
        if ($this->is_wordfence_active()) {
            error_log('[DriveHR-Wordfence] Webhook processing completed - legitimate operation');
        }
    }
    
    /**
     * Get Wordfence whitelist recommendations
     * 
     * @return array Array of recommendations
     */
    public function get_whitelist_recommendations(): array {
        return [
            'urls' => [
                '/webhook/drivehr-sync'
            ],
            'ips' => [
                '// Add your Netlify deployment IPs here',
                '// You can find these in your Netlify dashboard'
            ],
            'user_agents' => [
                'DriveHR-Webhook',
                'Netlify-Function'
            ]
        ];
    }
}

// Initialize Wordfence compatibility
new DriveHR_Wordfence_Compatibility();