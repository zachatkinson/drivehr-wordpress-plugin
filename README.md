# DriveHR WordPress Plugin

Enterprise-grade WordPress plugin for receiving and displaying job postings from the DriveHR Netlify synchronization function.

## 🔗 Related Repository

This WordPress plugin works in conjunction with the DriveHR Netlify Sync function:
- **Netlify Function**: [github.com/zachatkinson/drivehr-netlify-sync](https://github.com/zachatkinson/drivehr-netlify-sync)

## 📦 Current Version

**Version**: 1.1.4

## 🚀 Installation

1. Download the latest release from the releases page
2. Upload to WordPress via Plugins → Add New → Upload Plugin
3. Configure in `wp-config.php`:
   ```php
   define('DRIVEHR_WEBHOOK_SECRET', 'your-webhook-secret');
   define('DRIVEHR_WEBHOOK_ENABLED', true);
   ```

## 🔧 Features

- **Webhook Handler**: Secure endpoint for receiving job data
- **Custom Post Type**: `drivehr_job` for job management
- **Automatic Sync**: Creates, updates, and removes jobs to maintain parity
- **Admin Interface**: Full WordPress admin integration
- **Site Health**: Integration with WordPress Site Health
- **Security**: HMAC verification, rate limiting, capability management

## 📋 Requirements

- WordPress 5.0+
- PHP 7.4+
- DriveHR Netlify Sync function (see related repository)

## 🔐 Security Features

- HMAC-SHA256 signature verification
- Replay attack protection (5-minute window)
- Rate limiting (10 requests/minute per IP)
- Full input sanitization
- Capability-based access control

## 📝 Development

This repository contains only the WordPress plugin component. For the complete system including the Netlify scraping function, see the main repository linked above.

### Local Development Setup

1. Clone this repository to your WordPress plugins directory
2. Activate the plugin in WordPress admin
3. Configure the webhook secret in `wp-config.php`
4. Set up the Netlify function from the main repository

### Code Quality & Linting

This plugin enforces WordPress Coding Standards using PHP_CodeSniffer.

**Install dependencies:**
```bash
cd drivehr-webhook
php composer.phar install
```

**Available commands:**
```bash
# Check PHP syntax
php composer.phar lint:php

# Check WordPress Coding Standards
php composer.phar lint

# Auto-fix fixable issues
php composer.phar lint:fix

# Run all checks
php composer.phar check
```

**Standards enforced:**
- ✅ WordPress-Core coding standards
- ✅ WordPress-Extra best practices
- ✅ WordPress-Docs documentation requirements
- ✅ PHP 7.4+ compatibility checks
- ✅ Security standards (nonce, sanitization, escaping)

### Building for Release

```bash
# Create a release-ready zip file
zip -r drivehr-webhook.zip drivehr-webhook/ -x "*.git*" -x "*.DS_Store" -x "*vendor*" -x "*composer.*"
```

## 📄 License

MIT License - See LICENSE file for details

## 🤝 Contributing

Please submit issues and pull requests to this repository for WordPress-specific changes. For Netlify function issues, use the main repository.

## 📞 Support

For support, please open an issue in the appropriate repository:
- **WordPress Plugin Issues**: This repository
- **Netlify Function Issues**: [Main repository](https://github.com/zachatkinson/drivehr-netlify-sync)