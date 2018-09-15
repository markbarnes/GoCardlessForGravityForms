=== Plugin Name ===
Contributors: fatbeehive, seuser
Tags: payments, payment gateway, gocardless, gravityforms, directdebit
Requires at least: 4.9.2
Tested up to: 4.9.5
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

GoCardless add on for Gravity Forms.


== Description ==
This plugin provides the functionality to set up direct debit payments through [GoCardless](https://gocardless.com). It requires [Gravity Forms](https://www.gravityforms.com/) and a GoCardless account.

This uses GoCardless Hosted Payment Page for reduced PCI Compliance requirements.

== Installation ==

1. Upload `fb-gforms-gocardless-hosted` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Add the GoCardless access token in your wp-config.php file with:
    `define( 'FB_GF_GOCARDLESS_HOSTED_READWRITE_TOKEN', 'insert-token-here' );`
4. Set the GoCardless connection live in your wp-config.php file with:
    `define( 'FB_GF_GOCARDLESS_HOSTED_ENVIRONMENT', 'live' );`
3. Edit the Gravity Form you would like to collect the direct debit through
4. Add a GoCardless feed from Settings > GoCardless Hosted

== Changelog ==
= 1.0 =
* Initial release
