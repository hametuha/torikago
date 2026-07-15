<?php
/**
 * Helper functions for the plugin.
 *
 * @package Hametuha\KagomeWp
 */

use Hametuha\KagomeWp\Plugin;

/**
 * Helper function to get plugin instance.
 *
 * @return Plugin
 */
function wp_kagome_analyzer() {
	return Plugin::get_instance();
}

/**
 * Helper function to analyze Japanese text.
 *
 * @param string $text    Text to analyze.
 * @param array  $options Analysis options.
 * @return array|false Analysis result or false on failure.
 */
function wp_kagome_analyze( $text, $options = array() ) {
	$analyzer = wp_kagome_analyzer()->get_analyzer();
	if ( ! $analyzer ) {
		return false;
	}

	try {
		return $analyzer->analyze( $text, $options );
	} catch ( \Exception $e ) {
		return false;
	}
}

/**
 * Helper function to tokenize Japanese text.
 *
 * @param string $text    Text to tokenize.
 * @param array  $options Tokenization options.
 * @return array|false Tokenization result or false on failure.
 */
function wp_kagome_tokenize( $text, $options = array() ) {
	$analyzer = wp_kagome_analyzer()->get_analyzer();
	if ( ! $analyzer ) {
		return false;
	}

	try {
		return $analyzer->tokenize( $text, $options );
	} catch ( \Exception $e ) {
		return false;
	}
}

/**
 * Get indexable post types for Kagome search.
 *
 * @param bool $include_user_settings Whether to include user settings.
 * @return array Array of post type names.
 */
function wp_kagome_indexable_post_types( $include_user_settings = true ) {
	// Get default indexable post types
	$default_post_types = get_post_types( array(
		'public'              => true,
		'exclude_from_search' => false,
		'publicly_queryable'  => true,
	) );
	
	// Remove some internal post types that shouldn't be indexed
	$excluded_internal = array(
		'wp_block',              // Reusable blocks
		'wp_template',           // Block templates
		'wp_template_part',      // Template parts
		'wp_navigation',         // Navigation menus
		'wp_global_styles',      // Global styles
		'custom_css',            // Custom CSS
		'customize_changeset',   // Customizer changesets
		'oembed_cache',          // oEmbed cache
		'user_request',          // User requests (GDPR)
	);
	
	$default_post_types = array_diff( $default_post_types, $excluded_internal );
	
	// Apply user settings if requested
	if ( $include_user_settings ) {
		$settings = \Hametuha\KagomeWp\Settings::get_instance();
		
		// Get explicitly included post types
		$include_types = $settings->get_included_post_types();
		if ( ! empty( $include_types ) ) {
			$default_post_types = array_merge( $default_post_types, $include_types );
		}
		
		// Remove explicitly excluded post types
		$exclude_types = $settings->get_excluded_post_types();
		if ( ! empty( $exclude_types ) ) {
			$default_post_types = array_diff( $default_post_types, $exclude_types );
		}
	}
	
	// Remove duplicates and re-index
	$default_post_types = array_unique( $default_post_types );
	$default_post_types = array_values( $default_post_types );
	
	/**
	 * Filter indexable post types.
	 *
	 * @param array $post_types Array of post type names.
	 */
	return apply_filters( 'wp_kagome_indexable_post_types', $default_post_types );
}

