<?php
/**
 * Search query optimizer class.
 *
 * @package Hametuha\KagomeWp
 */

namespace Hametuha\KagomeWp\Search;

use Hametuha\KagomeWp\Pattern\Singleton;
use Hametuha\KagomeWp\Model\SearchIndex;

/**
 * Query optimizer for Kagome search.
 */
class QueryOptimizer extends Singleton {

	/**
	 * Initialize the optimizer.
	 */
	protected function init(): void {
		// Hook for search optimization
		add_action( 'pre_get_posts', array( $this, 'optimize_search_query' ) );
	}

	/**
	 * Optimize search queries using Kagome search index.
	 *
	 * @param \WP_Query $query Query object.
	 */
	public function optimize_search_query( $query ) {
		// Skip if not main query or admin
		if ( ! $query->is_main_query() || is_admin() ) {
			return;
		}

		// Skip if not a search query
		if ( ! $query->is_search() ) {
			return;
		}

		// Get search keyword
		$search_term = $query->get( 's' );
		if ( empty( $search_term ) ) {
			return;
		}

		// Check for exact_match parameter to disable optimization
		$exact_match = $query->get( 'exact_match' );
		if ( ! empty( $exact_match ) ) {
			return;
		}

		// Skip optimization if not available or insufficient data
		if ( ! $this->is_optimization_available() ) {
			return;
		}

		// Check if search optimization is enabled
		$optimization_enabled = apply_filters( 'wp_kagome_search_optimization_enabled', true, $query );
		if ( ! $optimization_enabled ) {
			return;
		}

		// Get post types to search
		$post_types = $this->get_query_post_types( $query );
		if ( empty( $post_types ) ) {
			return;
		}

		// Use search index to find matching posts
		$matching_post_ids = $this->search_with_kagome_index( $search_term, $post_types );

		// Handle search results
		$this->apply_search_results( $query, $matching_post_ids, $search_term );
	}

	/**
	 * Get post types for the query.
	 *
	 * @param \WP_Query $query Query object.
	 * @return array Array of post type names.
	 */
	private function get_query_post_types( $query ) {
		$post_types = $query->get( 'post_type' );
		
		if ( empty( $post_types ) ) {
			// If no post type specified, use indexable post types
			$post_types = wp_kagome_indexable_post_types();
		} elseif ( is_string( $post_types ) ) {
			$post_types = array( $post_types );
		}

		// Filter to only indexable post types
		$indexable_types = wp_kagome_indexable_post_types();
		$post_types = array_intersect( $post_types, $indexable_types );

		return $post_types;
	}

	/**
	 * Search using Kagome search index.
	 *
	 * @param string $search_term Search term.
	 * @param array  $post_types  Post types to search.
	 * @return array Array of post IDs.
	 */
	private function search_with_kagome_index( $search_term, $post_types ) {
		$search_index = SearchIndex::get_instance();
		
		return $search_index->search( $search_term, array(
			'post_type' => $post_types,
			'limit'     => 1000, // Max results to process
		) );
	}

	/**
	 * Apply search results to query.
	 *
	 * @param \WP_Query $query             Query object.
	 * @param array     $matching_post_ids Array of matching post IDs.
	 * @param string    $search_term       Original search term.
	 */
	private function apply_search_results( $query, $matching_post_ids, $search_term ) {
		// If no results found, return empty result
		if ( empty( $matching_post_ids ) ) {
			// Force empty result
			$query->set( 'post__in', array( 0 ) );
			// Clear search term to prevent default MySQL search
			$query->set( 's', '' );
			return;
		}

		// Use matching post IDs for query
		$query->set( 'post__in', $matching_post_ids );
		
		// Clear search term to prevent default MySQL LIKE search
		$query->set( 's', '' );
		
		// Order by relevance (order in array)
		$query->set( 'orderby', 'post__in' );

		// Add flag to indicate optimized search
		$query->set( 'kagome_optimized', true );

		/**
		 * Action after Kagome search optimization.
		 *
		 * @param \WP_Query $query           Query object.
		 * @param array     $matching_post_ids Array of matching post IDs.
		 * @param string    $search_term     Search term.
		 */
		do_action( 'wp_kagome_after_search_optimization', $query, $matching_post_ids, $search_term );
	}

	/**
	 * Check if search optimization is available.
	 *
	 * @return bool True if optimization is available.
	 */
	public function is_optimization_available() {
		// Check if Kagome analyzer is available
		$plugin = \Hametuha\KagomeWp\Plugin::get_instance();
		$analyzer = $plugin->get_analyzer();
		
		if ( ! $analyzer ) {
			return false;
		}

		// Check if search index table exists and has sufficient data
		$search_index = SearchIndex::get_instance();
		$stats = $search_index->get_stats();
		
		// Require at least 50 indexed posts for optimization to be worthwhile
		return $stats['total'] >= 50;
	}

	/**
	 * Get optimization statistics.
	 *
	 * @return array Statistics array.
	 */
	public function get_optimization_stats() {
		$search_index = SearchIndex::get_instance();
		$stats = $search_index->get_stats();
		
		return array(
			'indexed_posts'    => $stats['total'],
			'indexed_by_type'  => $stats['by_post_type'],
			'optimization_enabled' => $this->is_optimization_available(),
			'indexable_types'  => wp_kagome_indexable_post_types(),
		);
	}
}