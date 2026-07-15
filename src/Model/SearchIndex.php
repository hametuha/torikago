<?php
/**
 * Search index table for Kagome analysis results.
 *
 * @package Hametuha\KagomeWp
 */

namespace Hametuha\KagomeWp\Model;

use Hametuha\KagomeWp\Pattern\AbstractTable;

/**
 * Search index table class.
 */
class SearchIndex extends AbstractTable {

	/**
	 * Table name without prefix.
	 *
	 * @return string
	 */
	protected function table_name(): string {
		return 'kagome_search_index';
	}

	/**
	 * Version number in semver.
	 *
	 * @return string
	 */
	protected function version() {
		return '1.0.0';
	}

	/**
	 * SQL query to create/update table.
	 *
	 * @return string
	 */
	protected function build_query() {
		$charset_collate = $this->db->get_charset_collate();
		$table = $this->table;

		return "CREATE TABLE {$table} (
			index_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			post_type varchar(20) NOT NULL DEFAULT 'post',
			tokens text NOT NULL,
			reading text,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (index_id),
			KEY post_id (post_id),
			KEY post_type (post_type),
			KEY updated_at (updated_at),
			FULLTEXT KEY tokens_fulltext (tokens)
		) {$charset_collate};";
	}

	/**
	 * Update search index for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return bool Success or failure.
	 */
	public function update_index( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			error_log( "SearchIndex: Post not found: {$post_id}" );
			return false;
		}

		// Get content to analyze.
		$content = $this->get_analyzable_content( $post );
		if ( empty( $content ) ) {
			error_log( "SearchIndex: Empty content for post {$post_id}" );
			// Delete index if content is empty.
			return $this->delete_index( $post_id );
		}

		// Analyze content using Kagome.
		$tokens = $this->analyze_content( $content );
		if ( empty( $tokens ) ) {
			error_log( "SearchIndex: Analysis failed or returned empty tokens for post {$post_id}" );
			return false;
		}

		// Check if we actually have surfaces to index
		if ( empty( $tokens['surfaces'] ) ) {
			error_log( "SearchIndex: No surface forms found for post {$post_id}" );
			return false;
		}

		// Prepare data for insert/update.
		$data = array(
			'post_id'    => $post_id,
			'post_type'  => $post->post_type,
			'tokens'     => implode( ' ', $tokens['surfaces'] ),
			'reading'    => implode( ' ', $tokens['readings'] ),
			'updated_at' => current_time( 'mysql' ),
		);

		// Check if index exists.
		$existing = $this->db->get_var(
			$this->db->prepare(
				"SELECT index_id FROM {$this->table} WHERE post_id = %d",
				$post_id
			)
		);

		if ( $existing ) {
			// Update existing index.
			$result = $this->db->update(
				$this->table,
				$data,
				array( 'post_id' => $post_id ),
				array( '%d', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
			if ( false === $result ) {
				error_log( "SearchIndex: Failed to update index for post {$post_id}: " . $this->db->last_error );
			}
		} else {
			// Insert new index.
			$result = $this->db->insert(
				$this->table,
				$data,
				array( '%d', '%s', '%s', '%s', '%s' )
			);
			if ( false === $result ) {
				error_log( "SearchIndex: Failed to insert index for post {$post_id}: " . $this->db->last_error );
			}
		}

		$success = false !== $result;
		if ( $success ) {
			error_log( "SearchIndex: Successfully indexed post {$post_id} with " . count( $tokens['surfaces'] ) . " surface forms" );
		}
		
		return $success;
	}

	/**
	 * Delete search index for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return bool Success or failure.
	 */
	public function delete_index( $post_id ) {
		$result = $this->db->delete(
			$this->table,
			array( 'post_id' => $post_id ),
			array( '%d' )
		);
		return false !== $result;
	}

	/**
	 * Get analyzable content from post.
	 *
	 * @param \WP_Post $post Post object.
	 * @return string Combined content for analysis.
	 */
	private function get_analyzable_content( $post ) {
		$content_parts = array();

		// Add post title.
		if ( ! empty( $post->post_title ) ) {
			$content_parts[] = $post->post_title;
		}

		// Add post content.
		if ( ! empty( $post->post_content ) ) {
			// Strip shortcodes and HTML tags.
			$content = strip_shortcodes( $post->post_content );
			$content = wp_strip_all_tags( $content );
			if ( ! empty( $content ) ) {
				$content_parts[] = $content;
			}
		}

		// Add post excerpt.
		if ( ! empty( $post->post_excerpt ) ) {
			$content_parts[] = wp_strip_all_tags( $post->post_excerpt );
		}

		/**
		 * Filter the content parts for search indexing.
		 *
		 * @param array    $content_parts Array of content strings.
		 * @param \WP_Post $post          Post object.
		 */
		$content_parts = apply_filters( 'kagome_search_index_content', $content_parts, $post );

		return implode( ' ', $content_parts );
	}

	/**
	 * Analyze content using Kagome.
	 *
	 * @param string $content Content to analyze.
	 * @return array Array with 'surfaces' and 'readings' keys.
	 */
	private function analyze_content( $content ) {
		$plugin = \Hametuha\KagomeWp\Plugin::get_instance();
		$analyzer = $plugin->get_analyzer();

		if ( ! $analyzer ) {
			return array();
		}

		// Get analysis options including user dictionary.
		$options = $plugin->get_analysis_options();

		try {
			// Log content being analyzed for debugging
			if ( strlen( $content ) > 1000 ) {
				error_log( 'SearchIndex: Analyzing long content (' . strlen( $content ) . ' chars): ' . substr( $content, 0, 200 ) . '...' );
			}
			
			$tokens = $analyzer->tokenize( $content, $options );
			
			$surfaces = array();
			$readings = array();

			foreach ( $tokens as $token ) {
				// Filter out particles and symbols.
				if ( isset( $token['pos'] ) && is_array( $token['pos'] ) ) {
					$pos = $token['pos'][0];
					// Skip particles, symbols, and auxiliary verbs.
					if ( in_array( $pos, array( '助詞', '記号', '助動詞' ), true ) ) {
						continue;
					}
				}

				// Add surface form.
				if ( ! empty( $token['surface'] ) ) {
					$surfaces[] = $token['surface'];
				}

				// Add reading if available.
				if ( ! empty( $token['reading'] ) ) {
					$readings[] = $token['reading'];
				} elseif ( isset( $token['class'] ) && 'USER' === $token['class'] && isset( $token['features'][2] ) ) {
					// For user dictionary entries.
					$readings[] = $token['features'][2];
				}
			}

			return array(
				'surfaces' => array_unique( $surfaces ),
				'readings' => array_unique( $readings ),
			);
		} catch ( \Exception $e ) {
			error_log( 'Kagome analysis failed: ' . $e->getMessage() );
			return array();
		}
	}

	/**
	 * Search posts by keyword.
	 *
	 * @param string $keyword    Search keyword.
	 * @param array  $args       Additional query arguments.
	 * @return array Array of post IDs.
	 */
	public function search( $keyword, $args = array() ) {
		// Analyze the search keyword.
		$tokens = $this->analyze_content( $keyword );
		if ( empty( $tokens['surfaces'] ) ) {
			return array();
		}

		// Build search query.
		$search_terms = implode( ' ', $tokens['surfaces'] );
		
		$sql = "SELECT DISTINCT post_id 
				FROM {$this->table} 
				WHERE MATCH(tokens) AGAINST(%s IN BOOLEAN MODE)";

		$query_args = array( $search_terms );

		// Add post type filter if specified.
		if ( ! empty( $args['post_type'] ) ) {
			if ( is_array( $args['post_type'] ) ) {
				// Multiple post types
				$placeholders = array_fill( 0, count( $args['post_type'] ), '%s' );
				$sql .= " AND post_type IN (" . implode( ',', $placeholders ) . ")";
				$query_args = array_merge( $query_args, $args['post_type'] );
			} else {
				// Single post type
				$sql .= " AND post_type = %s";
				$query_args[] = $args['post_type'];
			}
		}

		// Add limit if specified.
		if ( ! empty( $args['limit'] ) ) {
			$sql .= " LIMIT %d";
			$query_args[] = intval( $args['limit'] );
		}

		// Execute query.
		$results = $this->db->get_col(
			$this->db->prepare( $sql, $query_args )
		);

		return array_map( 'intval', $results );
	}

	/**
	 * Get index statistics.
	 *
	 * @return array Statistics array.
	 */
	public function get_stats() {
		$total = $this->db->get_var( "SELECT COUNT(*) FROM {$this->table}" );
		
		$by_post_type = $this->db->get_results(
			"SELECT post_type, COUNT(*) as count 
			FROM {$this->table} 
			GROUP BY post_type",
			ARRAY_A
		);

		return array(
			'total'        => intval( $total ),
			'by_post_type' => $by_post_type,
			'table_name'   => $this->table,
		);
	}
}