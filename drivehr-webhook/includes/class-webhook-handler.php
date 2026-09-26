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
 *
 * Uses singleton pattern to prevent duplicate hook registrations.
 *
 * @since 1.0.0
 * @since 1.6.0 Implemented singleton pattern
 */
class DriveHR_Webhook_Handler {

    /**
     * Single instance of the class
     *
     * @since 1.6.0
     * @var DriveHR_Webhook_Handler|null
     */
    private static $instance = null;

    /**
     * Webhook endpoint path - must match Netlify function configuration
     */
    private const WEBHOOK_PATH = '/webhook/drivehr-sync';

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
     * Maximum accepted request body size in bytes (2 MiB)
     *
     * A hundred jobs with rich descriptions is well under 1 MiB; anything
     * larger is either a bug or an attempt to exhaust memory.
     *
     * @since 2.3.0
     */
    private const MAX_PAYLOAD_BYTES = 2097152;

    /**
     * Server variable that carries the timestamp-bound HMAC (v2 scheme)
     *
     * The v2 signature is computed over "{timestamp}.{raw_body}" so a captured
     * request cannot be replayed with a fresh timestamp. The legacy
     * X-Webhook-Signature header (HMAC over the body alone) is ignored.
     *
     * @since 2.3.0
     */
    private const SIGNATURE_SERVER_KEY = 'HTTP_X_WEBHOOK_SIGNATURE_V2';

    /**
     * Get singleton instance
     *
     * Ensures only one webhook handler exists per request,
     * preventing duplicate hook registrations.
     *
     * @since 1.6.0
     * @return DriveHR_Webhook_Handler
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize webhook handler
     *
     * Private constructor prevents direct instantiation.
     * Use DriveHR_Webhook_Handler::get_instance() instead.
     *
     * Registers the webhook endpoint handler on WordPress init.
     * Only activates if webhook is enabled via configuration.
     *
     * @since 1.0.0
     * @since 1.6.0 Changed to private constructor for singleton pattern
     */
    private function __construct() {
        add_action('init', [$this, 'handle_webhook']);
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
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        if ($method !== 'POST') {
            $this->log_webhook_activity('Invalid method', ['method' => $method]);
            $this->respond(405, [
                'error' => 'Method not allowed',
                'allowed_methods' => ['POST'],
                'timestamp' => current_time('c')
            ]);
        }

        // Only accept JSON bodies; form-encoded and multipart requests are
        // never legitimate here and are a common WAF-evasion shape.
        if (!$this->has_json_content_type()) {
            $this->log_webhook_activity('Unsupported content type');
            $this->respond(415, [
                'error' => 'Unsupported media type',
                'expected' => 'application/json',
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

        // Read the body once, refusing oversized requests before buffering them
        $payload = $this->read_request_body();
        if ($payload === null) {
            $this->log_webhook_activity('Payload too large');
            $this->respond(413, [
                'error' => 'Payload too large',
                'max_bytes' => self::MAX_PAYLOAD_BYTES,
                'timestamp' => current_time('c')
            ]);
        }

        // Verify timestamp-bound webhook signature
        if (!$this->verify_signature($payload)) {
            $this->log_webhook_activity('Invalid signature', ['ip' => $this->get_client_ip()]);
            $this->respond(401, [
                'error' => 'Unauthorized - Invalid signature',
                'timestamp' => current_time('c')
            ]);
        }

        // Reject a signed request that has already been processed
        if (!$this->register_signature_nonce()) {
            $this->log_webhook_activity('Replayed request', ['ip' => $this->get_client_ip()]);
            $this->respond(401, [
                'error' => 'Unauthorized - Duplicate request',
                'timestamp' => current_time('c')
            ]);
        }

        // Parse and validate JSON payload
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
        
        // Process jobs with error handling (delegated to shared sync engine, v2.2.0)
        try {
            $sync_engine = new DriveHR_Job_Sync();
            $result = $sync_engine->sync($data['jobs']);

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
     * Get client IP address
     *
     * Uses REMOTE_ADDR by default. Forwarding headers such as X-Forwarded-For
     * are attacker-controlled unless a trusted proxy/CDN sets them, so they are
     * only honoured when the site owner names the header explicitly:
     *
     *   define('DRIVEHR_TRUSTED_PROXY_HEADER', 'CF-Connecting-IP');
     *
     * Trusting arbitrary forwarding headers previously allowed the per-IP rate
     * limit to be bypassed by sending a fresh spoofed header on every request.
     *
     * @since 2.3.0 Stopped trusting undeclared forwarding headers
     * @return string Client IP address, or 0.0.0.0 if none is valid
     */
    private function get_client_ip(): string {
        if (defined('DRIVEHR_TRUSTED_PROXY_HEADER')
            && is_string(DRIVEHR_TRUSTED_PROXY_HEADER)
            && DRIVEHR_TRUSTED_PROXY_HEADER !== ''
        ) {
            $server_key = 'HTTP_' . strtoupper(str_replace('-', '_', DRIVEHR_TRUSTED_PROXY_HEADER));
            $forwarded = trim(explode(',', (string) ($_SERVER[ $server_key ] ?? ''))[0]);
            if ($forwarded !== '' && filter_var($forwarded, FILTER_VALIDATE_IP)) {
                return $forwarded;
            }
        }

        $remote_addr = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        return filter_var($remote_addr, FILTER_VALIDATE_IP) ? $remote_addr : '0.0.0.0';
    }

    /**
     * Check that the request declares a JSON body
     *
     * @since 2.3.0
     * @return bool True when Content-Type starts with application/json
     */
    private function has_json_content_type(): bool {
        $content_type = (string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '');
        return strpos(strtolower(trim($content_type)), 'application/json') === 0;
    }

    /**
     * Read the raw request body, enforcing the size limit
     *
     * Checks the declared Content-Length first, then reads at most one byte
     * past the limit so chunked or mis-declared bodies are still caught
     * without buffering an unbounded stream.
     *
     * @since 2.3.0
     * @return string|null Raw body, or null when it exceeds MAX_PAYLOAD_BYTES
     */
    private function read_request_body(): ?string {
        $declared_length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($declared_length > self::MAX_PAYLOAD_BYTES) {
            return null;
        }

        $payload = file_get_contents('php://input', false, null, 0, self::MAX_PAYLOAD_BYTES + 1);
        if ($payload === false || strlen($payload) > self::MAX_PAYLOAD_BYTES) {
            return null;
        }

        return $payload;
    }

    /**
     * Record the request signature so the same signed request cannot be replayed
     *
     * The signature covers the timestamp, so every legitimate request is unique.
     * Seen signatures are remembered for twice the timestamp drift window, which
     * is the longest a replay could otherwise still pass the timestamp check.
     *
     * @since 2.3.0
     * @return bool True if this signature has not been seen before
     */
    private function register_signature_nonce(): bool {
        $signature = (string) ($_SERVER[ self::SIGNATURE_SERVER_KEY ] ?? '');
        $transient_key = 'drivehr_webhook_seen_' . hash('sha256', $signature);

        if (get_transient($transient_key) !== false) {
            return false;
        }

        set_transient($transient_key, time(), self::MAX_TIMESTAMP_DRIFT * 2);
        return true;
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
     * Verify the timestamp-bound HMAC signature
     *
     * Expected headers from the Netlify function:
     * - X-Webhook-Timestamp: <unix_timestamp> (must be within MAX_TIMESTAMP_DRIFT)
     * - X-Webhook-Signature-V2: sha256=HMAC_SHA256("{timestamp}.{raw_body}", secret)
     *
     * Binding the timestamp into the signed message is what makes the drift
     * window meaningful: before 2.3.0 the timestamp was checked but not signed,
     * so a captured body + signature could be replayed indefinitely with a new
     * timestamp header.
     *
     * @since 2.3.0 Signature now covers the timestamp; legacy header ignored
     * @param string $payload Raw request body (already size-checked)
     * @return bool True if signature and timestamp are valid
     */
    private function verify_signature(string $payload): bool {
        $signature = (string) ($_SERVER[ self::SIGNATURE_SERVER_KEY ] ?? '');
        $timestamp = (string) ($_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] ?? '');

        // Timestamp must be a plain unsigned integer within the drift window
        if ($timestamp === '' || !ctype_digit($timestamp) || strlen($timestamp) > 12) {
            return false;
        }
        if (abs(time() - (int) $timestamp) > self::MAX_TIMESTAMP_DRIFT) {
            return false;
        }

        $secret = $this->get_webhook_secret();
        if ($secret === '') {
            return false;
        }

        // "sha256=" + 64 hex characters
        if (strlen($signature) !== 71 || substr($signature, 0, 7) !== 'sha256=') {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

        // Timing-safe comparison; known value first per PHP documentation
        return hash_equals($expected, $signature);
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
        if (count($data['jobs']) > DriveHR_Job_Sync::MAX_JOBS_PER_SYNC) {
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
        
        // Set security headers; responses are machine-to-machine and never cacheable
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet');
        header('Referrer-Policy: no-referrer');
        header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
        header('Cache-Control: no-store, max-age=0');
        
        // Ensure data has consistent structure
        if (!isset($data['timestamp'])) {
            $data['timestamp'] = current_time('c');
        }
        
        // Output JSON response
        echo wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}