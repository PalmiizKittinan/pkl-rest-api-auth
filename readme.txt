=== PKL RestAuth - REST API Authentication ===
Contributors: kittlam
Tags: rest-api, authentication, api-key, security
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 3.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Control WordPress REST API access by requiring user authentication with API key system.

== Description ==

PKL RestAuth provides a simple way to authenticate WordPress REST API requests using API keys. Users can generate their own API keys from their profile page and use them to make authenticated API requests.

Features:

* User-friendly API key generation from profile page
* Secure API key storage with WordPress security standards
* Easy integration with WordPress REST API
* Support for Bearer token authentication
* API key revocation capability
* Admin can manage all users' API keys
* Multiple authentication methods (Bearer Token, X-API-Key Header, Form-data, Query Parameter)

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/pkl-wpz-rest-api-auth` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Users can generate API keys from their profile page (Users > Your Profile).
4. Use the generated API key in the Authorization header: `Authorization: Bearer YOUR_API_KEY`

== Frequently Asked Questions ==

= How do I generate an API key? =

1. Go to Users > Your Profile in WordPress admin
2. Scroll down to the "REST API Access" section
3. Click "Generate New API Key"
4. Copy and save your API key securely

= How do I use the API key? =

You can use the API key in multiple ways:
- Authorization Bearer Token (Recommended): `Authorization: Bearer YOUR_API_KEY`
- X-API-Key Header: `X-API-Key: YOUR_API_KEY`
- Form-data: Include `api_key` parameter
- Query Parameter: `?api_key=YOUR_API_KEY`

= Can I revoke an API key? =

Yes, you can revoke your API key from your profile page by clicking the "Revoke API Key" button.

= Is it secure? =

Yes, the plugin follows WordPress security best practices and stores API keys securely in the database.

== Upgrade Notice ==

= 3.0.0 =
Initial release of PKL WPZ REST API Auth plugin.

= 2.5.0 =
New feature for admin page.

= 2.4.0 =
New feature: Control root REST API endpoint access. Added checkbox to disable/enable wp-json endpoint. Bug fixes included.

= 2.3.0 =
Security patch update with code improvements for WordPress.org standards. Update recommended.

= 2.2.0 =
Security patch with Bearer token support added. Update recommended for enhanced security.

= 2.1.0 =
⚠️ Major security update! OAuth endpoints removed. Please regenerate API keys through user profiles after updating.

== Changelog ==

= 3.0.0 =
* Initial release
* API key authentication support
* Multiple authentication methods
* Admin management interface

= 2.5.1 =
* Bug Fixes
** Allow only method GET for REST API endpoint (./wp-json/wp/v2/posts)
** Allow only method GET for REST API endpoint (./wp-json/wp/v2/pages)

= 2.5.0 =
* Added: Checkbox to disable/enable root REST API endpoint (./wp-json/wp/v2/posts) on admin page : Default is Enable
* Added: Checkbox to disable/enable root REST API endpoint (./wp-json/wp/v2/pages) on admin page : Default is Enable

= 2.4.0 =
* Added: Checkbox to disable/enable root REST API endpoint (./wp-json) on admin page
* Fixed: Various bug fixes for improved stability

= 2.3.3 =
* Fixed: Bug fixes

= 2.3.2 =
* Fixed: Bug fixes

= 2.3.1 =
* Fixed: Bug fixes

= 2.3.0 =
* Security: Patch security update
* Fixed: Minor bug fixes
* Improved: Recoded to meet WordPress.org standards

= 2.2.2 =
* Security: Patch security update

= 2.2.0 =
* Security: Patch security update
* Added: Bearer token authentication method

= 2.1.0 =
* **Breaking Change**: OAuth endpoints removed for security
* Added: Secure API key system
* Added: User profile API key management
* Improved: Enhanced admin interface
* Improved: Security and documentation
* Fixed: DateTime-related bugs

= 2.0.0 =
* Added: OAuth token system
* Improved: Enhanced authentication methods
* Improved: Admin interface redesign

= 1.1.0 =
* Added: Email authentication
* Initial public release

== Developer Documentation ==

For detailed API documentation and examples, visit the plugin settings page in your WordPress admin.

= Code Example =

```php
// Using Bearer token (Recommended)
$response = wp_remote_get( 'https://yoursite.com/wp-json/wp/v2/posts', [
    'headers' => [
        'Authorization' => 'Bearer YOUR_API_KEY'
    ]
]);

== Support ==

For support and feature requests, please visit our GitHub repository [@PalmiizKittinan](https://github.com/PalmiizKittinan) .