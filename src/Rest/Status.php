<?php
/**
 * REST API endpoint for status information.
 *
 * @package Hametuha\KagomeWp
 */

namespace Hametuha\KagomeWp\Rest;

use Hametuha\KagomeWp\Pattern\AbstractRestApiPattern;

/**
 * Status endpoint: GET /kagome/v1/status
 */
class Status extends AbstractRestApiPattern {

	/**
	 * Get route.
	 *
	 * @return string
	 */
	protected function route(): string {
		return '/status';
	}

	/**
	 * Handle GET request for status information.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function handle_get( \WP_REST_Request $request ) {
		$analyzer = $this->plugin->get_analyzer();

		return rest_ensure_response(
			array(
				'available'   => ! ! $analyzer,
				'version'     => WP_KAGOME_VERSION,
				'binary_path' => $analyzer ? 'Available' : 'Not found',
				'plugin_info' => array(
					'name'    => 'Kagome Analyzer',
					'version' => WP_KAGOME_VERSION,
				),
			)
		);
	}

	/**
	 * Permission callback for status endpoint.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function permission_callback( \WP_REST_Request $request ): bool {
		return current_user_can( 'read' );
	}
}