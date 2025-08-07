<?php
/**
 * REST API endpoint for search performance testing.
 *
 * @package Hametuha\KagomeWp
 */

namespace Hametuha\KagomeWp\Rest;

use Hametuha\KagomeWp\Pattern\AbstractRestApiPattern;

/**
 * Performance endpoint: POST /kagome/v1/performance
 */
class Performance extends AbstractRestApiPattern {

	/**
	 * Get route.
	 *
	 * @return string
	 */
	protected function route(): string {
		return '/performance';
	}

	/**
	 * Handle POST request for performance testing.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_post( \WP_REST_Request $request ) {
		$search_term = $request->get_param( 'search' );
		$limit = intval( $request->get_param( 'limit' ) ?: 10 );

		if ( empty( $search_term ) ) {
			return new \WP_Error(
				'missing_search_term',
				__( 'Search term is required.', 'kagome' ),
				array( 'status' => 400 )
			);
		}

		$results = array(
			'search_term' => $search_term,
			'limit'       => $limit,
			'tests'       => array(),
		);

		// Test 1: Kagome optimized search
		$results['tests']['kagome_optimized'] = $this->measure_search_performance( $search_term, $limit, false );

		// Test 2: WordPress default search
		$results['tests']['wordpress_default'] = $this->measure_search_performance( $search_term, $limit, true );

		// Calculate improvement
		$kagome_time = $results['tests']['kagome_optimized']['execution_time'];
		$wp_time = $results['tests']['wordpress_default']['execution_time'];

		if ( $wp_time > 0 ) {
			$results['improvement'] = array(
				'speed_ratio'    => round( $wp_time / $kagome_time, 2 ),
				'time_saved_ms'  => round( ( $wp_time - $kagome_time ) * 1000, 2 ),
				'percentage'     => round( ( ( $wp_time - $kagome_time ) / $wp_time ) * 100, 1 ),
			);
		} else {
			$results['improvement'] = null;
		}

		return rest_ensure_response( $results );
	}

	/**
	 * Measure search performance.
	 *
	 * @param string $search_term  Search term.
	 * @param int    $limit        Result limit.
	 * @param bool   $exact_match  Use WordPress default search.
	 * @return array Performance results.
	 */
	private function measure_search_performance( $search_term, $limit, $exact_match = false ) {
		$start_time = microtime( true );
		$start_memory = memory_get_usage();

		// Create query args
		$args = array(
			's'              => $search_term,
			'posts_per_page' => $limit,
			'post_status'    => 'publish',
			'fields'         => 'ids', // Only get IDs for performance
		);

		if ( $exact_match ) {
			$args['exact_match'] = 1;
		}

		// Execute search
		$query = new \WP_Query( $args );
		$posts = $query->posts;

		$end_time = microtime( true );
		$end_memory = memory_get_usage();

		// Get database query count
		global $wpdb;
		$query_count = $wpdb->num_queries;

		// Calculate metrics
		$execution_time = $end_time - $start_time;
		$memory_used = $end_memory - $start_memory;

		return array(
			'method'         => $exact_match ? 'WordPress Default' : 'Kagome Optimized',
			'execution_time' => $execution_time,
			'execution_time_ms' => round( $execution_time * 1000, 2 ),
			'memory_used'    => $memory_used,
			'memory_used_kb' => round( $memory_used / 1024, 2 ),
			'found_posts'    => $query->found_posts,
			'returned_posts' => count( $posts ),
			'db_queries'     => $query_count,
			'optimized'      => ! $exact_match,
		);
	}

	/**
	 * Get arguments for POST endpoint.
	 *
	 * @param string $http_method HTTP method.
	 * @return array REST API arguments.
	 */
	protected function get_args( $http_method ): array {
		if ( 'POST' !== $http_method ) {
			return array();
		}

		return array(
			'search' => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function( $value ) {
					return ! empty( trim( $value ) );
				},
				'description'       => __( 'The search term to test.', 'kagome' ),
			),
			'limit'  => array(
				'required'    => false,
				'type'        => 'integer',
				'default'     => 10,
				'minimum'     => 1,
				'maximum'     => 100,
				'description' => __( 'Maximum number of posts to retrieve.', 'kagome' ),
			),
		);
	}

	/**
	 * Permission callback for performance endpoint.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function permission_callback( \WP_REST_Request $request ): bool {
		return current_user_can( 'manage_options' );
	}
}