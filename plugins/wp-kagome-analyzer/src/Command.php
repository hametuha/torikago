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

		// Get plugin instance and merge with user dictionary options
		$plugin = Plugin::get_instance();
		$options = $plugin->get_analysis_options( $options );

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
					// For user dictionary entries, extract reading from features
					$reading = $token['reading'] ?? '';
					$base = $token['base_form'] ?? '';
					
					if ( isset( $token['class'] ) && 'USER' === $token['class'] && isset( $token['features'] ) ) {
						// User dictionary entries have reading in features[2]
						if ( empty( $reading ) && isset( $token['features'][2] ) ) {
							$reading = $token['features'][2];
						}
						// User dictionary entries typically use surface as base form
						if ( empty( $base ) ) {
							$base = $token['surface'];
						}
					}
					
					$items[] = array(
						'surface' => $token['surface'],
						'pos'     => $token['pos'][0] ?? '',
						'reading' => $reading,
						'base'    => $base,
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
	 * Display user dictionary information and entries.
	 *
	 * ## OPTIONS
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
	 * [--test=<text>]
	 * : Test text analysis with user dictionary.
	 *
	 * ## EXAMPLES
	 *
	 *     # Show user dictionary entries
	 *     $ wp kagome user-dic
	 *
	 *     # Show in JSON format
	 *     $ wp kagome user-dic --format=json
	 *
	 *     # Test analysis with user dictionary
	 *     $ wp kagome user-dic --test="カスタム語を含むテスト文"
	 *
	 * @subcommand user-dic
	 * @synopsis [--format=<format>] [--test=<text>]
	 * @when after_wp_load
	 */
	public function user_dic( $args, $assoc_args ) {
		$settings = Settings::get_instance();
		$user_dict_content = $settings->get_user_dictionary_content();
		$entries = $settings->get_user_dictionary_entries();

		\WP_CLI::line( 'User Dictionary Information:' );
		\WP_CLI::line( '----------------------------' );

		if ( $settings->is_user_dictionary_enabled() ) {
			\WP_CLI::success( 'User dictionary is configured' );
		} else {
			\WP_CLI::warning( 'User dictionary is empty' );
		}

		\WP_CLI::line( 'Total entries: ' . count( $entries ) );

		if ( empty( $entries ) ) {
			if ( ! empty( $user_dict_content ) ) {
				\WP_CLI::warning( 'User dictionary content found but no valid entries parsed' );
				\WP_CLI::line( 'Raw content:' );
				\WP_CLI::line( $user_dict_content );
			} else {
				\WP_CLI::warning( 'No user dictionary entries found' );
			}
			return;
		}

		// Show entries.
		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

		if ( 'json' === $format ) {
			\WP_CLI::line( wp_json_encode( $entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
		} else {
			\WP_CLI\Utils\format_items( $format, $entries, array( 'surface', 'tokens', 'reading', 'pos' ) );
		}

		// Test analysis if requested.
		if ( isset( $assoc_args['test'] ) ) {
			$test_text = $assoc_args['test'];
			\WP_CLI::line( '' );
			\WP_CLI::line( 'Testing analysis with user dictionary:' );
			\WP_CLI::line( 'Text: ' . $test_text );
			\WP_CLI::line( '---' );

			$analyzer = $this->get_analyzer();
			if ( ! $analyzer ) {
				\WP_CLI::error( 'Analyzer not available' );
			}

			try {
				// Get plugin options including user dictionary
				$plugin = Plugin::get_instance();
				$options = $plugin->get_analysis_options();
				$result = $analyzer->analyze( $test_text, $options );
				$items = array();
				foreach ( $result as $token ) {
					// For user dictionary entries, extract reading from features
					$reading = $token['reading'] ?? '';
					$base = $token['base_form'] ?? '';
					
					if ( isset( $token['class'] ) && 'USER' === $token['class'] && isset( $token['features'] ) ) {
						// User dictionary entries have reading in features[2]
						if ( empty( $reading ) && isset( $token['features'][2] ) ) {
							$reading = $token['features'][2];
						}
						// User dictionary entries typically use surface as base form
						if ( empty( $base ) ) {
							$base = $token['surface'];
						}
					}
					
					$items[] = array(
						'surface' => $token['surface'],
						'pos'     => $token['pos'][0] ?? '',
						'reading' => $reading,
						'base'    => $base,
					);
				}
				\WP_CLI\Utils\format_items( 'table', $items, array( 'surface', 'pos', 'reading', 'base' ) );
			} catch ( \Exception $e ) {
				\WP_CLI::error( 'Analysis failed: ' . $e->getMessage() );
			}
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
		$binary_path = Settings::get_instance()->get_binary_path();

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

}
