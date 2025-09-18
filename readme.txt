=== PKL REST API Auth ===
Contributors: palmiizkittinan
Donate link: https://github.com/PalmiizKittinan
Tags: rest api, authentication, security, privacy, restrict
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Control WordPress REST API access by requiring user authentication. Disable API access for non-logged-in or not registered users with customizable settings.

== Description ==

PKL REST API Auth is a lightweight plugin that helps secure your WordPress site by restricting access to the REST API.

- 🔒 Restrict REST API access to logged-in users only.
- 🚫 Block unauthenticated requests with customizable settings.
- ⚙️ Simple settings page to enable or disable authentication requirement.
- 🌍 Multilingual support (translation-ready).
- 🪶 Lightweight and fast, no external dependencies.

This plugin is ideal if you want to improve privacy and security by preventing unauthorized users from accessing REST API endpoints.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/pkl-rest-api-auth` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Navigate to **Settings → PKL REST API Auth** to configure the plugin.

== Frequently Asked Questions ==

= What happens when the REST API is disabled for non-logged-in users? =

Unauthenticated users will receive a `401 Unauthorized` response when they try to access the REST API.

= Can logged-in users without permissions still access the REST API? =

No. If a logged-in user does not have the `read` capability, they will receive a `403 Forbidden` response.

= Can I disable the restriction temporarily? =

Yes. You can go to **Settings → PKL REST API Auth** and uncheck the option to disable authentication requirement.

== Screenshots ==

1. Admin settings page for enabling/disabling REST API authentication.
2. Example of REST API blocked with `401 Unauthorized`.

== Changelog ==

= 1.0.0 =
* Initial release with:
  * Option to restrict REST API to logged-in users only.
  * Admin settings page.
  * Multilingual support.
* First public release. Adds REST API authentication requirement with settings page.

== Upgrade Notice ==

= 1.0.0 =
Initial release of PKL REST API Auth plugin.

= 1.1.0 =
* Add Email for API testing credential
  * Method 1: Form-data (Recommended for Postman)
  * Method 2: Header
  * Method 3: Query Parameter
