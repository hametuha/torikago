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
add_action( 'plugins_loaded', 'wp_kagome_init_plugin' );

// Register activation and deactivation hooks.
register_activation_hook( __FILE__, 'wp_kagome_activate' );
register_deactivation_hook( __FILE__, 'wp_kagome_deactivate_plugin' );

/**
 * Initialize the plugin.
 */
function wp_kagome_init_plugin() {
	// Singleton pattern automatically calls init() when getting instance
	wp_kagome_analyzer();
}

/**
 * Executed if this plugin is activated.
 *
 * @return void
 */
function wp_kagome_activate() {
	// Restore the executable bit on the bundled kagome binary. Installing a plugin
	// from a ZIP upload strips the exec bit, which would make proc_open() fail on
	// EC2 and elsewhere. Best-effort: some hosts disallow chmod, in which case the
	// admin notice from Analyzer's constructor still surfaces the problem.
	if ( class_exists( '\Hametuha\KagomeWp\Analyzer' ) ) {
		try {
			$binary_path = \Hametuha\KagomeWp\Analyzer::detectBinaryPath();
			if ( file_exists( $binary_path ) && ! is_executable( $binary_path ) ) {
				// Direct chmod is intentional: WP_Filesystem's FTP/SSH transports do
				// not reliably set the exec bit on a local file, and we need it here.
				@chmod( $binary_path, 0755 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_chmod
			}
		} catch ( \RuntimeException $e ) {
			// Unsupported OS/architecture: nothing to chmod.
			unset( $e );
		}
	}

	// Trigger activation hook for components that need it
	do_action( 'wp_kagome_activate' );
	flush_rewrite_rules();
}

/**
 * Plugin deactivation callback.
 *
 * @return void
 */
function wp_kagome_deactivate_plugin() {
	flush_rewrite_rules();
	// TODO: IF cron exists, remove them.
}
