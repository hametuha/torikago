<?php
/**
 * Singleton base class for WordPress plugins.
 *
 * @package Hametuha\KagomeWp
 */

namespace Hametuha\KagomeWp\Pattern;

/**
 * Abstract singleton base class.
 */
abstract class Singleton {

	/**
	 * Instance storage for all singleton classes.
	 *
	 * @var array
	 */
	private static $instances = array();

	/**
	 * Get singleton instance.
	 *
	 * @return static
	 */
	final public static function get_instance() {
		$class = static::class;
		if ( ! isset( self::$instances[ $class ] ) ) {
			self::$instances[ $class ] = new static();
		}
		return self::$instances[ $class ];
	}

	/**
	 * Constructor.
	 */
	final protected function __construct() {
		$this->init();
	}

	/**
	 * Initialize the class.
	 *
	 * This method should be overridden by child classes to implement
	 * their initialization logic.
	 */
	abstract protected function init():void;

	/**
	 * Prevent cloning.
	 */
	final protected function __clone() {}

	/**
	 * Prevent unserialization.
	 */
	final public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton' );
	}
}
