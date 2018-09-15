<?php
/**
 * Plugin Name: FB GoCardless Hosted for Gravity Forms
 * Description: Provides an integration with the GoCardless redirect flows hosted payment pages.
 * Plugin URI: https://www.fatbeehive.com/
 * Author: Fat Beehive Ltd
 * Version: 1.1
 *
 * @package fatbeehive
 */

/**
 * Recommended WP Plugin security in case server is misconfigured.
 *
 * @see https://codex.wordpress.org/Writing_a_Plugin
 */
defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

/**
 * Include GoCardless Composer dependencies.
 */
require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

/**
 * Auto include php files within plugin.
 *
 * @see https://developer.wordpress.org/reference/functions/plugin_dir_path/
 */
foreach ( glob( plugin_dir_path( __FILE__ ) . 'classes/*.php' ) as $file ) {
	require_once $file;
}
