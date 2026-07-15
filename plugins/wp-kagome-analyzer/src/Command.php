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
	 * Import Livedoor news corpus into WordPress posts.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<number>]
	 * : Limit the number of posts to import.
	 * ---
	 * default: 100
	 * ---
	 *
	 * [--category=<category>]
	 * : Import only from specific category directory.
	 *
	 * [--dry-run]
	 * : Show what would be imported without actually importing.
	 *
	 * ## EXAMPLES
	 *
	 *     # Import 50 news articles
	 *     $ wp kagome import-news --limit=50
	 *
	 *     # Import from specific category
	 *     $ wp kagome import-news --category=dokujo-tsushin --limit=20
	 *
	 *     # Dry run to see what would be imported
	 *     $ wp kagome import-news --dry-run --limit=10
	 *
	 * @when after_wp_load
	 */
	public function import_news( $args, $assoc_args ) {
		$limit = isset( $assoc_args['limit'] ) ? intval( $assoc_args['limit'] ) : 100;
		$category_filter = isset( $assoc_args['category'] ) ? $assoc_args['category'] : null;
		$dry_run = isset( $assoc_args['dry-run'] );
		
		$corpus_dir = WP_KAGOME_PATH . 'tmp/text';
		
		if ( ! is_dir( $corpus_dir ) ) {
			\WP_CLI::error( 'Corpus directory not found: ' . $corpus_dir );
		}

		\WP_CLI::line( 'Scanning Livedoor News Corpus...' );
		\WP_CLI::line( '--------------------------------' );
		
		$files = $this->scan_news_files( $corpus_dir, $category_filter );
		$total_files = count( $files );
		
		if ( $total_files === 0 ) {
			\WP_CLI::warning( 'No news files found.' );
			return;
		}
		
		\WP_CLI::line( "Found {$total_files} news files." );
		
		if ( $limit > 0 && $total_files > $limit ) {
			$files = array_slice( $files, 0, $limit );
			\WP_CLI::line( "Limiting import to {$limit} files." );
		}
		
		if ( $dry_run ) {
			\WP_CLI::line( '' );
			\WP_CLI::line( 'DRY RUN - Files that would be imported:' );
			foreach ( array_slice( $files, 0, 10 ) as $file ) {
				$news_data = $this->parse_news_file( $file['path'] );
				if ( $news_data ) {
					\WP_CLI::line( "- [{$file['category']}] {$news_data['title']}" );
				}
			}
			if ( count( $files ) > 10 ) {
				\WP_CLI::line( "... and " . ( count( $files ) - 10 ) . " more files." );
			}
			return;
		}
		
		\WP_CLI::line( '' );
		\WP_CLI::line( 'Starting import...' );
		
		$progress = \WP_CLI\Utils\make_progress_bar( 'Importing news articles', count( $files ) );
		$imported = 0;
		$skipped = 0;
		
		foreach ( $files as $file ) {
			$news_data = $this->parse_news_file( $file['path'] );
			
			if ( ! $news_data ) {
				$skipped++;
				$progress->tick();
				continue;
			}
			
			// Check if post already exists (by title)
			$existing = get_page_by_title( $news_data['title'], OBJECT, 'post' );
			if ( $existing ) {
				$skipped++;
				$progress->tick();
				continue;
			}
			
			// Create post
			$post_data = array(
				'post_title'   => $news_data['title'],
				'post_content' => $news_data['content'],
				'post_status'  => 'publish',
				'post_type'    => 'post',
				'post_date'    => $news_data['date'],
			);
			
			$post_id = wp_insert_post( $post_data );
			
			if ( is_wp_error( $post_id ) ) {
				$skipped++;
				$progress->tick();
				continue;
			}
			
			// Set category
			$category = $this->get_or_create_category( $file['category'] );
			if ( $category ) {
				wp_set_post_categories( $post_id, array( $category->term_id ) );
			}
			
			// Add original URL as meta
			add_post_meta( $post_id, '_livedoor_url', $news_data['url'] );
			add_post_meta( $post_id, '_livedoor_category', $file['category'] );
			
			$imported++;
			$progress->tick();
		}
		
		$progress->finish();
		
		\WP_CLI::success( "Import completed! Imported: {$imported}, Skipped: {$skipped}" );
		
		// Show search index update
		\WP_CLI::line( '' );
		\WP_CLI::line( 'Search index will be updated automatically for new posts.' );
	}

	/**
	 * Display search index information.
	 *
	 * ## EXAMPLES
	 *
	 *     # Show search index statistics
	 *     $ wp kagome search-index
	 *
	 * @when after_wp_load
	 */
	public function search_index( $args, $assoc_args ) {
		$search_index = \Hametuha\KagomeWp\Model\SearchIndex::get_instance();
		$stats = $search_index->get_stats();

		\WP_CLI::line( 'Search Index Information:' );
		\WP_CLI::line( '------------------------' );
		\WP_CLI::line( 'Table: ' . $stats['table_name'] );
		\WP_CLI::line( 'Total entries: ' . $stats['total'] );

		if ( ! empty( $stats['by_post_type'] ) ) {
			\WP_CLI::line( '' );
			\WP_CLI::line( 'By post type:' );
			foreach ( $stats['by_post_type'] as $type_stat ) {
				\WP_CLI::line( '  ' . $type_stat['post_type'] . ': ' . $type_stat['count'] );
			}
		}
	}

	/**
	 * Rebuild search index for all published posts.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<number>]
	 * : Limit the number of posts to process.
	 *
	 * [--post-id=<id>]
	 * : Process only specific post ID.
	 *
	 * ## EXAMPLES
	 *
	 *     # Rebuild index for all posts
	 *     $ wp kagome rebuild-index
	 *
	 *     # Rebuild index for specific post
	 *     $ wp kagome rebuild-index --post-id=123
	 *
	 *     # Process limited number of posts
	 *     $ wp kagome rebuild-index --limit=100
	 *
	 * @when after_wp_load
	 */
	public function rebuild_index( $args, $assoc_args ) {
		$search_index = \Hametuha\KagomeWp\Model\SearchIndex::get_instance();
		
		// Handle specific post ID
		if ( isset( $assoc_args['post-id'] ) ) {
			$post_id = intval( $assoc_args['post-id'] );
			$post = get_post( $post_id );
			
			if ( ! $post ) {
				\WP_CLI::error( "Post with ID {$post_id} not found." );
			}
			
			\WP_CLI::line( "Rebuilding index for post: {$post->post_title}" );
			$result = $search_index->update_index( $post_id );
			
			if ( $result ) {
				\WP_CLI::success( 'Index updated successfully.' );
			} else {
				\WP_CLI::error( 'Failed to update index.' );
			}
			return;
		}
		
		// Handle bulk rebuild
		$limit = isset( $assoc_args['limit'] ) ? intval( $assoc_args['limit'] ) : -1;
		
		$query_args = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'fields'         => 'ids',
		);
		
		$posts = get_posts( $query_args );
		
		if ( empty( $posts ) ) {
			\WP_CLI::warning( 'No published posts found.' );
			return;
		}
		
		\WP_CLI::line( 'Rebuilding search index...' );
		$progress = \WP_CLI\Utils\make_progress_bar( 'Processing posts', count( $posts ) );
		
		$updated = 0;
		$failed = 0;
		
		foreach ( $posts as $post_id ) {
			$result = $search_index->update_index( $post_id );
			if ( $result ) {
				$updated++;
			} else {
				$failed++;
			}
			$progress->tick();
		}
		
		$progress->finish();
		
		\WP_CLI::success( "Index rebuild completed! Updated: {$updated}, Failed: {$failed}" );
	}

	/**
	 * Scan news files in corpus directory.
	 *
	 * @param string      $corpus_dir      Corpus directory path.
	 * @param string|null $category_filter Category filter.
	 * @return array Array of file information.
	 */
	private function scan_news_files( $corpus_dir, $category_filter = null ) {
		$files = array();
		
		$categories = scandir( $corpus_dir );
		foreach ( $categories as $category ) {
			if ( $category === '.' || $category === '..' || ! is_dir( $corpus_dir . '/' . $category ) ) {
				continue;
			}
			
			// Skip non-txt files and system files
			if ( in_array( $category, array( 'CHANGES.txt', 'README.txt' ), true ) ) {
				continue;
			}
			
			// Apply category filter
			if ( $category_filter && $category !== $category_filter ) {
				continue;
			}
			
			$category_dir = $corpus_dir . '/' . $category;
			$news_files = glob( $category_dir . '/*.txt' );
			
			foreach ( $news_files as $file_path ) {
				$filename = basename( $file_path );
				// Skip LICENSE files
				if ( $filename === 'LICENSE.txt' ) {
					continue;
				}
				
				$files[] = array(
					'path'     => $file_path,
					'category' => $category,
					'filename' => $filename,
				);
			}
		}
		
		return $files;
	}

	/**
	 * Parse news file content.
	 *
	 * @param string $file_path Path to news file.
	 * @return array|false Parsed news data or false on failure.
	 */
	private function parse_news_file( $file_path ) {
		$content = file_get_contents( $file_path );
		if ( false === $content ) {
			return false;
		}
		
		$lines = explode( "\n", $content );
		if ( count( $lines ) < 4 ) {
			return false;
		}
		
		$url = trim( $lines[0] );
		$date_string = trim( $lines[1] );
		$title = trim( $lines[2] );
		
		// Join remaining lines as content
		$content_lines = array_slice( $lines, 3 );
		$article_content = trim( implode( "\n", $content_lines ) );
		
		// Convert date format
		$date = date( 'Y-m-d H:i:s', strtotime( $date_string ) );
		
		return array(
			'url'     => $url,
			'date'    => $date,
			'title'   => $title,
			'content' => $article_content,
		);
	}

	/**
	 * Get or create category by name.
	 *
	 * @param string $category_name Category name.
	 * @return \WP_Term|false Category term or false on failure.
	 */
	private function get_or_create_category( $category_name ) {
		// Convert directory name to readable category name
		$readable_name = $this->format_category_name( $category_name );
		
		$term = get_term_by( 'slug', $category_name, 'category' );
		if ( $term ) {
			return $term;
		}
		
		// Create new category
		$result = wp_insert_term( $readable_name, 'category', array( 'slug' => $category_name ) );
		if ( is_wp_error( $result ) ) {
			return false;
		}
		
		return get_term( $result['term_id'], 'category' );
	}

	/**
	 * Format category name for display.
	 *
	 * @param string $category_name Directory-based category name.
	 * @return string Formatted category name.
	 */
	private function format_category_name( $category_name ) {
		$mapping = array(
			'dokujo-tsushin'   => '独女通信',
			'it-life-hack'     => 'ITライフハック',
			'kaden-channel'    => '家電チャンネル',
			'livedoor-homme'   => 'livedoorオム',
			'movie-enter'      => '映画エンター',
			'peachy'           => 'Peachy',
			'smax'             => 'S-MAX',
			'sports-watch'     => 'スポーツウォッチ',
			'topic-news'       => 'トピックニュース',
		);
		
		return isset( $mapping[ $category_name ] ) ? $mapping[ $category_name ] : ucfirst( $category_name );
	}

	/**
	 * Get analyzer instance.
	 *
	 * @return \Hametuha\KagomeWp\Analyzer|null
	 */
	private function get_analyzer() {
		$plugin = Plugin::get_instance();
		return $plugin->get_analyzer();
	}

}
