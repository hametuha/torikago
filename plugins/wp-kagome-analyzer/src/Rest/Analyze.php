<?php
/**
 * REST API endpoint for text analysis.
 *
 * @package Hametuha\KagomeWp
 */

namespace Hametuha\KagomeWp\Rest;

use Hametuha\KagomeWp\Pattern\AbstractRestApiPattern;

/**
 * Analyze endpoint: POST /kagome/v1/analyze
 */
class Analyze extends AbstractRestApiPattern {

	/**
	 * Get route.
	 *
	 * @return string
	 */
	protected function route(): string {
		return '/analyze';
	}

	/**
	 * Handle POST request for text analysis.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_post( \WP_REST_Request $request ) {
		$analyzer = $this->plugin->get_analyzer();
		if ( ! $analyzer ) {
			return new \WP_Error(
				'kagome_not_available',
				__( 'Kagome analyzer is not available.', 'kagome' ),
				array( 'status' => 503 )
			);
		}

		$text    = $request->get_param( 'text' );
		$options = $request->get_param( 'options' ) ?: array();

		// Merge with plugin settings (including user dictionary)
		$options = $this->plugin->get_analysis_options( $options );

		try {
			$result = $analyzer->analyze( $text, $options );
			return rest_ensure_response(
				array(
					'text'    => $text,
					'tokens'  => $result,
					'options' => $options,
				)
			);
		} catch ( \Exception $e ) {
			return new \WP_Error(
				'analysis_failed',
				sprintf(
					/* translators: %s: error message */
					__( 'Text analysis failed: %s', 'kagome' ),
					$e->getMessage()
				),
				array( 'status' => 500 )
			);
		}
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
			'text'    => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
				'validate_callback' => array( $this, 'validate_text_input' ),
				'description'       => __( 'The text to analyze.', 'kagome' ),
			),
			'options' => array(
				'required'    => false,
				'type'        => 'object',
				'default'     => array(),
				'description' => __( 'Analysis options (mode, user dictionary, etc.).', 'kagome' ),
			),
		);
	}

	/**
	 * Permission callback for analyze endpoint.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function permission_callback( \WP_REST_Request $request ): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Validate text input.
	 *
	 * @param mixed $value Input value.
	 * @return bool
	 */
	public function validate_text_input( $value ) {
		return is_string( $value ) && ! empty( trim( $value ) );
	}
}