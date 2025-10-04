=== PKL WPz REST API Authentication ===
Contributors: kittlam
Tags: rest-api, authentication, api-key, security
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Control WordPress REST API access by requiring user authentication with API key system.

== Description ==

PKL WPz REST API Authentication provides a simple way to authenticate WordPress REST API requests using API keys. Users can generate their own API keys from their profile page and use them to make authenticated API requests.

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

== Changelog ==

= 1.0.0 =
🚀 Plugin Launch

== Developer Documentation ==

For detailed API documentation and examples, visit the plugin settings page in your WordPress admin.

= Code Example =

Example 1: Get Posts
    <?php
    $response = wp_remote_get( 'https://yoursite.com/wp-json/wp/v2/posts', array(
        'headers' => array(
            'Authorization' => 'Bearer YOUR_API_KEY'
        )
    ) );

    if ( ! is_wp_error( $response ) ) {
        $data = json_decode( wp_remote_retrieve_body( $response ) );
    }
    ?>

Example 2: Create Post

    <?php
    $response = wp_remote_post( 'https://yoursite.com/wp-json/wp/v2/posts', array(
        'headers' => array(
            'Authorization' => 'Bearer YOUR_API_KEY',
            'Content-Type'  => 'application/json'
        ),
        'body' => json_encode( array(
            'title'   => 'My Post',
            'content' => 'Post content',
            'status'  => 'publish'
        ) )
    ) );
    ?>

== Support ==

For support and feature requests, please visit our GitHub repository [@PalmiizKittinan](https://github.com/PalmiizKittinan) .