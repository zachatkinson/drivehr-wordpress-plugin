# DriveHR Job Sync Webhook Handler

Enterprise-grade WordPress plugin for receiving job data from DriveHR Netlify function and maintaining perfect parity between DriveHR and WordPress job listings.

## 🌟 Features

### ✅ Complete Job Synchronization
- **Automatic job creation** from DriveHR feed
- **Smart job updates** for existing listings
- **Automatic removal** of jobs no longer in DriveHR (NEW in v1.1.0!)
- **Perfect parity** between DriveHR and WordPress

### 🔐 Enterprise Security
- HMAC-SHA256 signature verification with timing-safe comparison
- Timestamp-based replay attack protection (5-minute window)
- Rate limiting (10 requests per minute per IP)
- Environment-based secret management (no hardcoded secrets)
- Comprehensive input validation and sanitization

### 🛠️ Professional WordPress Integration
- Custom post type `drivehr_job` with full WordPress features
- Custom taxonomies for departments and locations
- REST API support for headless implementations
- WordPress admin interface for manual job management
- Site Health integration for configuration monitoring

### 📊 Advanced Monitoring
- Comprehensive error handling with detailed logging
- Database transaction safety for atomic operations
- Real-time sync statistics and reporting
- Integration hooks for custom extensions

## 📦 Installation

### Method 1: Regular Plugin (Recommended)

1. **Upload the plugin:**
   ```
   /wp-content/plugins/drivehr-webhook/
   ```

2. **Activate the plugin** through the WordPress admin interface

3. **Add configuration** to `wp-config.php`:
   ```php
   define('DRIVEHR_WEBHOOK_SECRET', 'your-webhook-secret-here');
   define('DRIVEHR_WEBHOOK_ENABLED', true);
   ```

4. **Update your Netlify function** WP_API_URL to:
   ```
   https://yoursite.com/webhook/drivehr-sync
   ```

### Method 2: Must-Use Plugin (Alternative)

1. **Upload to mu-plugins:**
   ```
   /wp-content/mu-plugins/drivehr-webhook/
   ```

2. **Follow steps 3-4** from Method 1

## ⚙️ Configuration

### Required Constants

Add these to your `wp-config.php` file:

```php
// Your webhook secret (must match Netlify WEBHOOK_SECRET)
define('DRIVEHR_WEBHOOK_SECRET', 'your-secret-key-here');

// Enable the webhook handler
define('DRIVEHR_WEBHOOK_ENABLED', true);
```

### Optional Debug Logging

Enable WordPress debug logging to monitor webhook activity:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## 🔧 Usage

### Webhook Endpoint

Your Netlify function should POST to:
```
POST /webhook/drivehr-sync
```

### Expected Payload Format

```json
{
  "source": "github-actions",
  "jobs": [
    {
      "id": "job-123",
      "title": "Software Engineer",
      "description": "Join our team...",
      "department": "Engineering",
      "location": "San Francisco, CA",
      "type": "Full-time",
      "postedDate": "2024-01-15T10:00:00Z",
      "applyUrl": "https://drivehris.app/careers/company/apply/job-123"
    }
  ],
  "timestamp": "2024-01-15T10:01:00Z",
  "total_count": 1
}
```

### Response Format

```json
{
  "success": true,
  "processed": 2,
  "updated": 5,
  "removed": 3,
  "total": 7,
  "errors": [],
  "timestamp": "2024-01-15T10:01:30Z",
  "source": "drivehr-netlify-sync"
}
```

## 🏥 Monitoring & Health Checks

### WordPress Site Health

Visit **Tools > Site Health** to check DriveHR configuration status:

- ✅ **Good**: Webhook configured and working
- ⚠️ **Recommended**: Configuration issues
- ❌ **Critical**: Missing required configuration

### Admin Notices

The plugin provides helpful admin notices for:

- Missing webhook secret configuration
- Disabled webhook status
- Deactivation warnings
- Configuration guidance

### Quick Links

After installation, you'll find quick links in the plugin row:

- **View Jobs**: Manage synchronized jobs
- **Documentation**: GitHub repository
- **Site Health**: Configuration status

## 🔗 Integration Hooks

### Available Actions

```php
// Before job operations
do_action('drivehr_webhook_start');
do_action('drivehr_before_job_insert', $job_data);
do_action('drivehr_before_job_update', $job_data);
do_action('drivehr_before_job_delete', $post_id, $job_id);

// After job operations
do_action('drivehr_after_job_delete', $post_id, $job_id);
do_action('drivehr_webhook_end', $result);
```

### Custom Extensions

```php
// Example: Send email when jobs are removed
add_action('drivehr_after_job_delete', function($post_id, $job_id) {
    wp_mail(
        'admin@yoursite.com',
        'Job Removed',
        "Job {$job_id} was removed from DriveHR and WordPress."
    );
});
```

## 🐛 Troubleshooting

### Common Issues

1. **"Invalid signature" errors**
   - Verify `DRIVEHR_WEBHOOK_SECRET` matches Netlify environment
   - Check that webhook is enabled: `DRIVEHR_WEBHOOK_ENABLED = true`

2. **"Rate limit exceeded"**
   - Default: 10 requests per minute per IP
   - Check for duplicate webhook calls

3. **Jobs not appearing**
   - Verify webhook endpoint URL: `/webhook/drivehr-sync`
   - Check WordPress error logs
   - Ensure plugin is activated

### Debug Steps

1. **Enable debug logging** in `wp-config.php`
2. **Check Site Health** for configuration issues
3. **Review error logs** in `/wp-content/debug.log`
4. **Test webhook manually** using curl or Postman

## 📈 Performance

- **Optimized for scale**: Handles up to 100 jobs per request
- **Database transactions**: Atomic operations prevent data corruption
- **Efficient queries**: Minimal database impact
- **Memory management**: Safe for resource-constrained environments

## 🔄 Changelog

### v1.1.4 (Current)
- 🐛 **FIXED**: Site Health link permissions - only shows to users who can access Site Health
- 📝 **IMPROVED**: Proper capability checks for admin interface links

### v1.1.3
- 🐛 **FIXED**: WordPress permissions issue - administrators can now manage DriveHR jobs
- 📝 **IMPROVED**: Automatic capability management during plugin activation/deactivation
- ✨ **ADDED**: Support for editor role to manage DriveHR jobs

### v1.1.2
- 🐛 **FIXED**: Updated Wordfence configuration paths to match current interface
- 📝 **IMPROVED**: All Wordfence guidance now shows correct navigation paths

### v1.1.0
- ✨ **NEW**: Automatic removal of stale jobs
- ✨ **NEW**: Perfect parity maintenance between DriveHR and WordPress
- ✨ **NEW**: Enhanced admin notices and configuration guidance
- ✨ **NEW**: Site Health integration
- ✨ **NEW**: Plugin action links and meta information
- 🐛 **FIXED**: Transaction safety for job deletion
- 📝 **IMPROVED**: Comprehensive documentation

### v1.0.0
- 🎉 Initial release
- ✅ Basic job synchronization (create/update only)
- ✅ Enterprise security features
- ✅ Custom post type registration

## 📄 License

MIT License - see [LICENSE](https://opensource.org/licenses/MIT)

## 🔗 Links

- **GitHub Repository**: https://github.com/zachatkinson/drivehr-netlify-sync
- **Netlify Function**: See main repository for the complete sync system
- **Support**: Create an issue on GitHub

---

**Built with ❤️ for enterprise job synchronization**