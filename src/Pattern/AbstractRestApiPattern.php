<?php

namespace Hametuha\KagomeWp\Pattern;


use Hametuha\KagomeWp\Plugin;

/**
 * Abstract class for REST API implementation.
 *
 * One endpoint for 1 class. Extend this class and implement required methods.
 * If public methods `handle_(get|post|put|delete)` exists,
 * endpoints for the method will be registered.
 *
 * @property-read Plugin $plugin Plugin instance.
 * @method \WP_Error|\WP_REST_Response handle_get(\WP_REST_Request $request );
 * @method \WP_Error|\WP_REST_Response handle_post( \WP_REST_Request $request );
 * @method \WP_Error|\WP_REST_Response handle_put( \WP_REST_Request $request );
 * @method \WP_Error|\WP_REST_Response handle_delete( \WP_REST_Request $request );
 */
abstract class AbstractRestApiPattern extends Singleton {

	/**
	 * Namespace
	 *
	 * @return string
	 */
	protected function ns():string {
		return 'kagome/v1';
	}

	/**
	 * Should return route
	 *
	 * @return string
	 */
	abstract protected function route():string;

	/**
	 * Register REST API hook.
	 *
	 * @return void
	 */
	protected function init():void {
		add_action( 'rest_api_init', [ $this, 'rest_api_init' ] );
	}

	/**
	 * Register REST API.
	 *
	 * @return void
	 */
	public function rest_api_init():void {
		$methods = [];
		foreach ( [ 'get', 'post', 'put', 'delete' ] as $method ) {
			$method_name = 'handle_' . $method;
			if ( ! method_exists( $this, $method_name ) ) {
				continue;
			}
			$method = strtoupper( $method );
			$methods[] = [
				'methods'  => $method,
				'callback' => [ $this, $method_name ],
				'args'     => $this->get_args( $method ),
				'permission_callback' => [ $this, 'permission_callback' ],
			];
		}
		if ( ! empty( $methods ) ) {
			register_rest_route( $this->ns(), $this->route(), $methods );
		}
	}

	/**
	 * Get arguments for endpoint.
	 *
	 * @param string $http_method 'GET', 'PUT', 'POST', 'DELETE'
	 * @return array REST API's arguments.
	 */
	protected function get_args( $http_method ):array {
		return [];
	}

	/**
	 * Default is true(means public API)
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function permission_callback( \WP_REST_Request $request ):bool {
		return true;
	}

	/**
	 * Getter
	 *
	 * @param string $name Variable name.
	 * @return mixed
	 */
	public function __get( $name ) {
		if ( 'plugin' === $name ) {
			return Plugin::get_instance();
		} else {
			return null;
		}
	}
}
