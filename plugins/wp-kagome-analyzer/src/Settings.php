<?php
/**
 * Settings management class.
 *
 * @package Hametuha\KagomeWp
 */

namespace Hametuha\KagomeWp;

/**
 * Settings management class.
 */
class Settings extends Pattern\Singleton {

	/**
	 * Settings option group.
	 */
	const OPTION_GROUP = 'kagome_settings';

	/**
	 * User dictionary option name.
	 */
	const USER_DICTIONARY_OPTION = 'kagome_user_dictionary';

	/**
	 * Binary path option name.
	 */
	const BINARY_PATH_OPTION = 'kagome_binary_path';

	/**
	 * Included post types option name.
	 */
	const INCLUDED_POST_TYPES_OPTION = 'kagome_included_post_types';

	/**
	 * Excluded post types option name.
	 */
	const EXCLUDED_POST_TYPES_OPTION = 'kagome_excluded_post_types';

	/**
	 * Initialize settings.
	 */
	protected function init(): void {
		if ( is_admin() ) {
			add_action( 'admin_init', array( $this, 'register_settings' ) );
		}
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		// Register settings
		register_setting(
			self::OPTION_GROUP,
			self::USER_DICTIONARY_OPTION,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
				'default'           => '',
			)
		);

		register_setting(
			self::OPTION_GROUP,
			self::BINARY_PATH_OPTION,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		register_setting(
			self::OPTION_GROUP,
			self::INCLUDED_POST_TYPES_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_post_types' ),
				'default'           => array(),
			)
		);

		register_setting(
			self::OPTION_GROUP,
			self::EXCLUDED_POST_TYPES_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_post_types' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Add settings sections and fields.
	 *
	 * @param string $page_slug The page slug to add fields to.
	 */
	public function add_settings_fields( $page_slug ) {
		// Add settings section
		add_settings_section(
			'kagome_settings_section',
			__( 'Kagome Settings', 'kagome' ),
			array( $this, 'settings_section_callback' ),
			$page_slug
		);

		// Add settings fields
		add_settings_field(
			self::USER_DICTIONARY_OPTION,
			__( 'User Dictionary', 'kagome' ),
			array( $this, 'user_dictionary_callback' ),
			$page_slug,
			'kagome_settings_section'
		);

		add_settings_field(
			self::BINARY_PATH_OPTION,
			__( 'Kagome Binary Path', 'kagome' ),
			array( $this, 'binary_path_callback' ),
			$page_slug,
			'kagome_settings_section'
		);

		add_settings_field(
			'kagome_post_types',
			__( 'Indexable Post Types', 'kagome' ),
			array( $this, 'post_types_callback' ),
			$page_slug,
			'kagome_settings_section'
		);
	}

	/**
	 * Settings section callback.
	 */
	public function settings_section_callback() {
		echo '<p>' . esc_html__( 'Configure Kagome analyzer settings below.', 'kagome' ) . '</p>';
	}

	/**
	 * User dictionary field callback.
	 */
	public function user_dictionary_callback() {
		$value = $this->get_user_dictionary_content();
		?>
		<textarea 
			name="<?php echo esc_attr( self::USER_DICTIONARY_OPTION ); ?>" 
			id="<?php echo esc_attr( self::USER_DICTIONARY_OPTION ); ?>" 
			rows="10" 
			cols="80" 
			class="large-text code"
			placeholder="<?php esc_attr_e( 'Enter user dictionary entries, one per line in format: surface,tokens,readings,part-of-speech', 'kagome' ); ?>"
		><?php echo esc_textarea( $value ); ?></textarea>
		<p class="description">
			<?php esc_html_e( 'Format: surface,tokens,readings,part-of-speech (e.g., テスト単語,テスト 単語,テスト タンゴ,名詞)', 'kagome' ); ?>
		</p>
		<?php
	}

	/**
	 * Binary path field callback.
	 */
	public function binary_path_callback() {
		$value = $this->get_custom_binary_path();
		$default_path = $this->get_default_binary_path();
		?>
		<input 
			type="text" 
			name="<?php echo esc_attr( self::BINARY_PATH_OPTION ); ?>" 
			id="<?php echo esc_attr( self::BINARY_PATH_OPTION ); ?>" 
			value="<?php echo esc_attr( $value ); ?>" 
			class="regular-text code"
			placeholder="<?php echo esc_attr( $default_path ); ?>"
		/>
		<p class="description">
			<?php 
			printf(
				/* translators: %s: current binary path */
				esc_html__( 'Leave empty to use the default path: %s', 'kagome' ),
				'<code>' . esc_html( $default_path ) . '</code>'
			); 
			?>
		</p>
		<?php
	}

	/**
	 * Get user dictionary content.
	 *
	 * @return string User dictionary content.
	 */
	public function get_user_dictionary_content() {
		return get_option( self::USER_DICTIONARY_OPTION, '' );
	}

	/**
	 * Get user dictionary entries as array.
	 *
	 * @return array User dictionary entries.
	 */
	public function get_user_dictionary_entries() {
		$content = $this->get_user_dictionary_content();
		$entries = array();

		if ( ! empty( $content ) ) {
			$lines = array_filter( explode( "\n", $content ) );
			foreach ( $lines as $line ) {
				$line = trim( $line );
				if ( empty( $line ) || strpos( $line, '#' ) === 0 ) {
					continue; // Skip empty lines and comments
				}
				$parts = str_getcsv( $line );
				if ( count( $parts ) >= 4 ) {
					$entries[] = array(
						'surface' => $parts[0],
						'tokens'  => $parts[1],
						'reading' => $parts[2],
						'pos'     => $parts[3],
					);
				}
			}
		}

		return $entries;
	}

	/**
	 * Check if user dictionary is enabled (has content).
	 *
	 * @return bool True if user dictionary has content.
	 */
	public function is_user_dictionary_enabled() {
		return ! empty( $this->get_user_dictionary_content() );
	}

	/**
	 * Get custom binary path set by user.
	 *
	 * @return string Custom binary path.
	 */
	public function get_custom_binary_path() {
		return get_option( self::BINARY_PATH_OPTION, '' );
	}

	/**
	 * Get the effective binary path (custom or default).
	 *
	 * @return string|null Binary path.
	 */
	public function get_binary_path() {
		// Check if user has set a custom binary path
		$custom_path = $this->get_custom_binary_path();
		if ( ! empty( $custom_path ) && file_exists( $custom_path ) ) {
			return $custom_path;
		}

		$default_path = $this->get_default_binary_path();

		/**
		 * Filter the Kagome binary path.
		 *
		 * Allows users to specify custom binary locations.
		 *
		 * @param string|null $binary_path The path to the Kagome binary.
		 */
		return apply_filters( 'wp_kagome_binary_path', $default_path );
	}

	/**
	 * Get default binary path.
	 *
	 * @return string|null Default binary path.
	 */
	private function get_default_binary_path() {
		$default_path = null;

		// Check wp-env mapped directory first.
		if ( defined( 'WP_CONTENT_DIR' ) && file_exists( WP_CONTENT_DIR . '/kagome/kagome-cli-linux' ) ) {
			$default_path = WP_CONTENT_DIR . '/kagome/kagome-cli-linux';
		}

		return $default_path;
	}

	/**
	 * Create temporary user dictionary file.
	 *
	 * @return string|false Temporary file path or false on failure.
	 */
	public function create_temp_user_dict_file() {
		$dict_content = $this->get_user_dictionary_content();
		
		if ( empty( $dict_content ) ) {
			return false;
		}

		$upload_dir = wp_upload_dir();
		$temp_dir = $upload_dir['basedir'] . '/kagome-temp/';

		if ( ! wp_mkdir_p( $temp_dir ) ) {
			return false;
		}

		$temp_file = $temp_dir . 'user_dict_' . md5( $dict_content ) . '.txt';

		// Only create if content has changed
		if ( ! file_exists( $temp_file ) || md5_file( $temp_file ) !== md5( $dict_content ) ) {
			if ( file_put_contents( $temp_file, $dict_content ) === false ) {
				return false;
			}
		}

		return $temp_file;
	}

	/**
	 * Get analysis options including user dictionary.
	 *
	 * @param array $options Additional options to merge.
	 * @return array Analysis options.
	 */
	public function get_analysis_options( $options = array() ) {
		$default_options = array();

		// Add user dictionary if configured
		if ( $this->is_user_dictionary_enabled() ) {
			$temp_dict_path = $this->create_temp_user_dict_file();
			if ( $temp_dict_path ) {
				$default_options['udict'] = $temp_dict_path;
			}
		}

		return array_merge( $default_options, $options );
	}

	/**
	 * Post types field callback.
	 */
	public function post_types_callback() {
		$all_post_types = get_post_types( array( 'public' => true ), 'objects' );
		$default_types = wp_kagome_indexable_post_types( false );
		$included_types = $this->get_included_post_types();
		$excluded_types = $this->get_excluded_post_types();
		?>
		<div class="kagome-post-types-settings">
			<div style="margin-bottom: 20px;">
				<h4><?php esc_html_e( 'Default Indexable Post Types:', 'kagome' ); ?></h4>
				<p class="description">
					<?php echo esc_html( implode( ', ', $default_types ) ); ?>
				</p>
			</div>

			<div style="display: flex; gap: 30px;">
				<div style="flex: 1;">
					<h4><?php esc_html_e( 'Force Include:', 'kagome' ); ?></h4>
					<p class="description"><?php esc_html_e( 'Check to always include these post types', 'kagome' ); ?></p>
					<?php foreach ( $all_post_types as $post_type ) : ?>
						<?php if ( ! in_array( $post_type->name, $default_types, true ) ) : ?>
							<label style="display: block; margin: 5px 0;">
								<input type="checkbox" 
									   name="<?php echo esc_attr( self::INCLUDED_POST_TYPES_OPTION ); ?>[]" 
									   value="<?php echo esc_attr( $post_type->name ); ?>"
									   <?php checked( in_array( $post_type->name, $included_types, true ) ); ?> />
								<?php echo esc_html( $post_type->label ); ?> (<?php echo esc_html( $post_type->name ); ?>)
							</label>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>

				<div style="flex: 1;">
					<h4><?php esc_html_e( 'Force Exclude:', 'kagome' ); ?></h4>
					<p class="description"><?php esc_html_e( 'Check to always exclude these post types', 'kagome' ); ?></p>
					<?php foreach ( $all_post_types as $post_type ) : ?>
						<?php if ( in_array( $post_type->name, $default_types, true ) ) : ?>
							<label style="display: block; margin: 5px 0;">
								<input type="checkbox" 
									   name="<?php echo esc_attr( self::EXCLUDED_POST_TYPES_OPTION ); ?>[]" 
									   value="<?php echo esc_attr( $post_type->name ); ?>"
									   <?php checked( in_array( $post_type->name, $excluded_types, true ) ); ?> />
								<?php echo esc_html( $post_type->label ); ?> (<?php echo esc_html( $post_type->name ); ?>)
							</label>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Sanitize post types array.
	 *
	 * @param mixed $value Input value.
	 * @return array Sanitized array.
	 */
	public function sanitize_post_types( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		// Validate each post type exists
		$valid_types = array();
		$all_types = get_post_types();

		foreach ( $value as $post_type ) {
			if ( in_array( $post_type, $all_types, true ) ) {
				$valid_types[] = $post_type;
			}
		}

		return $valid_types;
	}

	/**
	 * Get included post types.
	 *
	 * @return array Array of post type names to include.
	 */
	public function get_included_post_types() {
		return get_option( self::INCLUDED_POST_TYPES_OPTION, array() );
	}

	/**
	 * Get excluded post types.
	 *
	 * @return array Array of post type names to exclude.
	 */
	public function get_excluded_post_types() {
		return get_option( self::EXCLUDED_POST_TYPES_OPTION, array() );
	}
}