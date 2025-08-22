=== DriveHR Job Sync Webhook Handler ===
Contributors: drivehr-team
Tags: jobs, webhook, drivehr, sync, employment, careers, netlify
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.1.4
License: MIT
License URI: https://opensource.org/licenses/MIT

Enterprise-grade webhook handler for DriveHR job synchronization with automatic removal of stale jobs and perfect parity maintenance.

== Description ==

The DriveHR Job Sync Webhook Handler is a secure, enterprise-grade WordPress plugin that receives job postings from your DriveHR Netlify synchronization function and stores them as custom posts in WordPress.

**Key Features:**

* **Enterprise Security**: HMAC-SHA256 signature verification with timing-safe comparison
* **Replay Attack Protection**: Timestamp-based validation with 5-minute window
* **Rate Limiting**: 10 requests per minute per IP address protection
* **Input Sanitization**: All data sanitized using WordPress security functions
* **Database Transaction Safety**: Atomic operations with rollback support
* **Admin Interface**: Rich admin experience with custom meta boxes and list columns
* **REST API Ready**: Full REST API support for headless implementations
* **Custom Taxonomies**: Organized job management with departments, locations, and types
* **Debug Logging**: Comprehensive logging for development and troubleshooting

**Security Features:**

* No hardcoded secrets - all configuration via wp-config.php
* Comprehensive error handling without information leakage
* Proxy-aware IP detection for accurate rate limiting
* Secure headers and response sanitization
* WordPress security best practices throughout

**Perfect For:**

* Companies using DriveHR for job posting management
* Organizations requiring secure webhook integrations
* Sites needing automated job synchronization
* Teams implementing headless WordPress architectures

== Installation ==

**As Must-Use Plugin (Recommended for Security):**

1. Upload the `drivehr-webhook` folder to `/wp-content/mu-plugins/`
2. Add configuration constants to your `wp-config.php`:

```php
define('DRIVEHR_WEBHOOK_SECRET', 'your-webhook-secret-here');
define('DRIVEHR_WEBHOOK_ENABLED', true);
```

3. Configure your DriveHR Netlify function with:
   - `WP_API_URL`: `https://yoursite.com/webhook/drivehr-sync`
   - `WEBHOOK_SECRET`: Same value as DRIVEHR_WEBHOOK_SECRET

**As Regular Plugin:**

1. Upload the plugin files to `/wp-content/plugins/drivehr-webhook/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Add the same configuration constants as above

== Configuration ==

**Required wp-config.php Constants:**

```php
// Webhook secret key (must match Netlify function)
define('DRIVEHR_WEBHOOK_SECRET', 'your-secure-secret-key-here');

// Enable/disable webhook processing  
define('DRIVEHR_WEBHOOK_ENABLED', true);
```

**Optional wp-config.php Constants:**

```php
// Enable debug logging (development only)
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

**Netlify Function Environment Variables:**

* `DRIVEHR_COMPANY_ID`: Your DriveHR company UUID
* `WP_API_URL`: `https://yoursite.com/webhook/drivehr-sync`
* `WEBHOOK_SECRET`: Same as DRIVEHR_WEBHOOK_SECRET (mark as secret)

== Usage ==

Once installed and configured, the plugin automatically:

1. **Receives Webhooks**: Listens at `/webhook/drivehr-sync` endpoint
2. **Validates Security**: Verifies HMAC signatures and timestamps  
3. **Processes Jobs**: Creates/updates job posts with sanitized data
4. **Provides Admin Interface**: Rich editing experience in WordPress admin
5. **Enables Public Display**: Jobs available via standard WordPress queries

**Accessing Jobs in Templates:**

```php
// Get all jobs
$jobs = get_posts(['post_type' => 'drivehr_job']);

// Get jobs by department
$engineering_jobs = get_posts([
    'post_type' => 'drivehr_job',
    'meta_query' => [
        ['key' => 'department', 'value' => 'Engineering']
    ]
]);

// Get job metadata
$job_id = get_post_meta($post->ID, 'job_id', true);
$apply_url = get_post_meta($post->ID, 'apply_url', true);
$location = get_post_meta($post->ID, 'location', true);
```

**REST API Access:**

Jobs are available via WordPress REST API at:
- `/wp-json/wp/v2/drivehr-jobs` - All jobs
- `/wp-json/wp/v2/drivehr-jobs/{id}` - Single job

== Frequently Asked Questions ==

= Is this plugin secure? =

Yes. This plugin implements enterprise-grade security including HMAC signature verification, replay attack protection, rate limiting, and comprehensive input sanitization. All secrets are stored in wp-config.php, never in the database.

= What happens if my webhook secret is compromised? =

Simply update the DRIVEHR_WEBHOOK_SECRET in wp-config.php and the corresponding WEBHOOK_SECRET in your Netlify function. All future webhook requests will be validated against the new secret.

= Can I customize the job post type? =

Yes. The plugin creates a standard WordPress custom post type that supports all WordPress features including custom fields, taxonomies, templates, and hooks.

= Does this work with page builders? =

Yes. DriveHR jobs are standard WordPress posts and work with all page builders, themes, and plugins that support custom post types.

= How do I troubleshoot webhook issues? =

Enable WP_DEBUG and WP_DEBUG_LOG in wp-config.php to see detailed webhook processing logs. Check your error_log file for DriveHR-related entries.

== Screenshots ==

1. Job listing in WordPress admin with custom columns
2. Job editing interface with DriveHR-specific meta boxes
3. Synchronization info showing webhook data
4. Job taxonomies for organization (departments, locations, types)

== Changelog ==

= 1.1.4 =
* **FIXED**: Site Health link permissions - only shows to users who can access Site Health
* **IMPROVED**: Proper capability checks for admin interface links

= 1.1.3 =
* **FIXED**: WordPress permissions issue - administrators can now manage DriveHR jobs
* **IMPROVED**: Automatic capability management during plugin activation/deactivation
* **ADDED**: Support for editor role to manage DriveHR jobs

= 1.1.2 =
* **FIXED**: Updated Wordfence configuration paths to match current interface
* **IMPROVED**: All Wordfence guidance now shows correct navigation paths

= 1.1.0 =
* **NEW**: Automatic removal of stale jobs no longer in DriveHR feed
* **NEW**: Perfect parity maintenance between DriveHR and WordPress
* **NEW**: Enhanced admin notices for configuration guidance
* **NEW**: Site Health integration for webhook monitoring
* **NEW**: Plugin action links for easy access to jobs and documentation
* **NEW**: Comprehensive deactivation warnings
* **IMPROVED**: Transaction safety for job deletion operations
* **IMPROVED**: Enhanced logging for job removal activities
* **IMPROVED**: Professional admin interface enhancements

= 1.0.0 =
* Initial release
* Enterprise-grade webhook security implementation
* Custom post type with full admin interface
* REST API support
* Custom taxonomies for job organization
* Comprehensive error handling and logging
* Rate limiting and replay attack protection
* Database transaction safety
* WordPress security best practices throughout

== Upgrade Notice ==

= 1.1.0 =
Major update adds automatic removal of stale jobs and perfect parity maintenance. Now maintains 100% sync between DriveHR and WordPress. Recommended for all users.

= 1.0.0 =
Initial release of the DriveHR Job Sync Webhook Handler.

== Security ==

This plugin has been designed with security as the primary concern:

* **No Database Secrets**: All sensitive configuration stored in wp-config.php
* **HMAC Verification**: All webhooks verified with cryptographic signatures
* **Timing Attack Protection**: Uses hash_equals() for secure comparisons  
* **Input Sanitization**: All data sanitized using WordPress security functions
* **Rate Limiting**: Protection against abuse and DOS attacks
* **Replay Protection**: Timestamp validation prevents replay attacks
* **Error Handling**: Secure error responses without information leakage
* **Transaction Safety**: Database operations wrapped in transactions

For security concerns, please contact the development team.

== Support ==

For support and bug reports:
* GitHub: https://github.com/zachatkinson/drivehr-netlify-sync
* Documentation: See included code comments and examples

== License ==

This plugin is licensed under the MIT License. See LICENSE file for details.