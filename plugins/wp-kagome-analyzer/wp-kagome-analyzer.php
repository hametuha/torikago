<?php
/**
 * Plugin Name: Kagome Analyzer
 * Plugin URI: https://github.com/fumikito/php-kagome
 * Description: Japanese text morphological analysis plugin powered by Kagome analyzer. Provides APIs and tools for analyzing Japanese text structure.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: Fumiki Takahashi
 * Author URI: https://github.com/fumikito
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: kagome
 * Domain Path: /languages
 * Network: false
 *
 * @package KagomeWordPress
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'WP_KAGOME_VERSION', '1.0.0' );
define( 'WP_KAGOME_PATH', plugin_dir_path( __FILE__ ) );
define( 'WP_KAGOME_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_KAGOME_BASENAME', plugin_basename( __FILE__ ) );

// Autoload Composer dependencies.
$autoload_file = WP_KAGOME_PATH . 'vendor/autoload.php';
if ( file_exists( $autoload_file ) ) {
	require_once $autoload_file;
}

// Load helper functions.
require_once WP_KAGOME_PATH . 'src/functions.php';

// Initialize plugin on WordPress init.
add_action( 'init', 'wp_kagome_init_plugin' );
add_action( 'rest_api_init', 'wp_kagome_init_rest_api' );

// Register activation and deactivation hooks.
register_activation_hook( __FILE__, 'wp_kagome_activate_plugin' );
register_deactivation_hook( __FILE__, 'wp_kagome_deactivate_plugin' );

/**
 * Initialize the plugin.
 */
function wp_kagome_init_plugin() {
	// Singleton pattern automatically calls init() when getting instance
	wp_kagome_analyzer();
}

/**
 * Initialize REST API.
 */
function wp_kagome_init_rest_api() {
	// REST API endpoints are now initialized automatically via Plugin::init()
	// No manual initialization needed.
}

/**
 * Plugin activation callback.
 */
function wp_kagome_activate_plugin() {
	$plugin = wp_kagome_analyzer();
	$plugin->activate();
}

/**
 * Plugin deactivation callback.
 */
function wp_kagome_deactivate_plugin() {
	$plugin = wp_kagome_analyzer();
	$plugin->deactivate();
}
