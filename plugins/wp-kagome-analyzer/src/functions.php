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