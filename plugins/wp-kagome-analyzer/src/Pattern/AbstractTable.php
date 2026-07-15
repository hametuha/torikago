<?php
/**
 * Abstract class for database table management.
 *
 * @package Hametuha\KagomeWp
 */

namespace Hametuha\KagomeWp\Pattern;

/**
 * Abstract class for DB table and accessor.
 *
 * @property-read string $version_key Option name of stored table version.
 * @property-read string $table       Table name.
 * @property-read \wpdb  $db          wpdb instance.
 */
abstract class AbstractTable extends Singleton {

	/**
	 * Table name without prefix.
	 *
	 * @return string
	 */
	abstract protected function table_name(): string;

	/**
	 * Version number in semver.
	 *
	 * @return string
	 */
	protected function version() {
		return '0.0.0';
	}

	/**
	 * SQL query to run if version is older.
	 *
	 * @return string|string[]
	 */
	protected function build_query() {
		return '';
	}

	/**
	 * This table needs update?
	 *
	 * @return bool
	 */
	protected function needs_update() {
		$current_version = get_option( $this->version_key, '0.0.0' );
		return version_compare( $current_version, $this->version(), '<' );
	}

	/**
	 * Constructor
	 *
	 * @return void
	 */
	protected function init(): void {
		// Install or update table on admin_init or activation.
		if ( is_admin() ) {
			add_action( 'admin_init', array( $this, 'maybe_install_table' ) );
		}
		add_action( 'wp_kagome_activate', array( $this, 'maybe_install_table' ) );
	}

	/**
	 * Check and install table if needed.
	 *
	 * @return void
	 */
	public function maybe_install_table() {
		$result = $this->install_table_if_possible();
		if ( ! empty( $result ) ) {
			// Update version if installation succeeded.
			update_option( $this->version_key, $this->version() );
		}
	}

	/**
	 * Create or update table if possible.
	 *
	 * @return string[]
	 */
	protected function install_table_if_possible() {
		if ( ! $this->needs_update() ) {
			return [];
		}
		$query = $this->build_query();
		if ( empty( $query ) ) {
			return [];
		}
		// Load db delta.
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		if ( ! function_exists( 'dbDelta' ) ) {
			return [];
		}
		// Do query.
		return dbDelta( $query );
	}

	/**
	 * Getter
	 *
	 * @param string $name Variable name.
	 * @return mixed
	 */
	public function __get( $name ) {
		switch ( $name ) {
			case 'table':
				return $this->db->prefix . $this->table_name();
			case 'db':
				global $wpdb;
				return $wpdb;
			case 'version_key':
				return 'kagome_table_' . $this->table_name() . '_version';
			default:
				return null;
		}
	}
}
