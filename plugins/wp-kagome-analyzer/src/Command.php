<?php
/**
 * WP-CLI Command for Kagome Analyzer.
 *
 * @package Hametuha\KagomeWp
 */

namespace Hametuha\KagomeWp;

// WordPress classes use backslash prefix

/**
 * Analyze Japanese text using Kagome morphological analyzer.
 */
class Command extends \WP_CLI_Command {

	/**
	 * Analyze Japanese text and return word segmentation.
	 *
	 * ## OPTIONS
	 *
	 * <text>
	 * : The Japanese text to analyze.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 * ---
	 *
	 * [--mode=<mode>]
	 * : Analysis mode.
	 * ---
	 * default: normal
	 * options:
	 *   - normal
	 *   - search
	 *   - extended
	 * ---
	 *
	 * [--simple]
	 * : Show only segmented words (space-separated).
	 *
	 * ## EXAMPLES
	 *
	 *     # Basic analysis
	 *     $ wp kagome analyze "すもももももももものうち"
	 *
	 *     # Simple word segmentation
	 *     $ wp kagome analyze "東京タワーに行きました" --simple
	 *
	 *     # JSON output
	 *     $ wp kagome analyze "日本語のテキスト" --format=json
	 *
	 * @when after_wp_load
	 */
	public function analyze( $args, $assoc_args ) {
		$text = $args[0];

		// Get analyzer instance.
		$analyzer = $this->get_analyzer();
		if ( ! $analyzer ) {
			\WP_CLI::error( __( 'Kagome analyzer is not available. Please check if the binary is properly installed.', 'kagome' ) );
		}

		// Prepare options.
		$options = array();
		if ( isset( $assoc_args['mode'] ) ) {
			$options['mode'] = $assoc_args['mode'];
		}

		try {
			// Simple mode - just show segmented words.
			if ( isset( $assoc_args['simple'] ) ) {
				$tokens = $analyzer->tokenize( $text, $options );
				$words = array_column( $tokens, 'surface' );
				\WP_CLI::line( implode( ' ', $words ) );
				return;
			}

			// Full analysis.
			$result = $analyzer->analyze( $text, $options );

			// Format output.
			$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

			if ( 'json' === $format ) {
				\WP_CLI::line( wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
			} elseif ( 'table' === $format ) {
				$items = array();
				foreach ( $result as $token ) {
					$items[] = array(
						'surface' => $token['surface'],
						'pos'     => $token['pos'][0] ?? '',
						'reading' => $token['reading'] ?? '',
						'base'    => $token['base_form'] ?? '',
					);
				}
				\WP_CLI\Utils\format_items( $format, $items, array( 'surface', 'pos', 'reading', 'base' ) );
			} else {
				// Other formats (csv, yaml).
				\WP_CLI\Utils\format_items( $format, $result, array_keys( $result[0] ) );
			}
		} catch ( \Exception $e ) {
			\WP_CLI::error( 'Analysis failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Show Kagome binary information.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp kagome info
	 *
	 * @when after_wp_load
	 */
	public function info() {
		$binary_path = $this->get_binary_path();

		\WP_CLI::line( 'Kagome Analyzer Information:' );
		\WP_CLI::line( '----------------------------' );

		if ( $binary_path && file_exists( $binary_path ) ) {
			\WP_CLI::success( 'Binary found: ' . $binary_path );

			// Check if executable.
			if ( is_executable( $binary_path ) ) {
				\WP_CLI::line( 'Status: Executable' );
			} else {
				\WP_CLI::warning( 'Binary is not executable' );
			}

			// Show file info.
			$size = filesize( $binary_path );
			\WP_CLI::line( 'Size: ' . size_format( $size ) );
			\WP_CLI::line( 'Modified: ' . date( 'Y-m-d H:i:s', filemtime( $binary_path ) ) );
		} else {
			\WP_CLI::error( 'Binary not found' );
		}

		// Check if analyzer can be initialized.
		$analyzer = $this->get_analyzer();
		if ( $analyzer ) {
			\WP_CLI::success( 'Analyzer is ready' );
		} else {
			\WP_CLI::warning( 'Analyzer could not be initialized' );
		}
	}

	/**
	 * Get analyzer instance.
	 *
	 * @return \Hametuha\Kagome\Analyzer|null
	 */
	private function get_analyzer() {
		$plugin = Plugin::get_instance();
		return $plugin->get_analyzer();
	}

	/**
	 * Get binary path with filter support.
	 *
	 * @return string|null
	 */
	private function get_binary_path() {
		$default_path = null;

		// Check wp-env mapped directory first.
		if ( defined( 'WP_CONTENT_DIR' ) && file_exists( WP_CONTENT_DIR . '/kagome/kagome-cli-linux' ) ) {
			$default_path = WP_CONTENT_DIR . '/kagome/kagome-cli-linux';
		}

		/**
		 * Filter the Kagome binary path.
		 *
		 * @param string|null $binary_path The path to the Kagome binary.
		 */
		return apply_filters( 'wp_kagome_binary_path', $default_path );
	}
}
