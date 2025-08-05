<?php
/**
 * Main plugin class.
 *
 * @package Hametuha\KagomeWp
 */

namespace Hametuha\KagomeWp;

// WordPress and PHP standard classes use backslash prefix

/**
 * Main plugin class.
 */
class Plugin extends Pattern\Singleton {

	/**
	 * Kagome analyzer instance.
	 *
	 * @var \Hametuha\Kagome\Analyzer
	 */
	private $analyzer = null;

	/**
	 * Initialize the plugin.
	 */
	protected function init():void {
		// Load text domain for internationalization.
		load_plugin_textdomain(
			'wp-kagome-analyzer',
			false,
			dirname( WP_KAGOME_BASENAME ) . '/languages'
		);

		// Initialize Kagome analyzer if available.
		if ( class_exists( '\Hametuha\Kagome\Analyzer' ) ) {
			try {
				$binary_path = $this->get_binary_path();
				$this->analyzer = new \Hametuha\Kagome\Analyzer( $binary_path );
			} catch ( \Exception $e ) {
				add_action( 'admin_notices', array( $this, 'show_kagome_error' ) );
			}
		} else {
			add_action( 'admin_notices', array( $this, 'show_missing_dependency_notice' ) );
		}

		// Register WP-CLI command if available.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'kagome', Command::class );
		}

		// Initialize REST API endpoints.
		Rest\Analyze::get_instance();
		Rest\Tokenize::get_instance();
		Rest\Status::get_instance();

		// Add admin menu if user has proper capabilities.
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
			add_action( 'admin_init', array( $this, 'admin_init' ) );
		}
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_management_page(
			__( 'Japanese Text Analysis', 'kagome' ),
			__( 'Japanese Text Analysis', 'kagome' ),
			'manage_options',
			'kagome-analyzer',
			array( $this, 'admin_page' )
		);
	}

	/**
	 * Initialize admin settings.
	 */
	public function admin_init() {
		// Register settings
		register_setting(
			'kagome_settings',
			'kagome_user_dictionary',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
				'default'           => '',
			)
		);

		register_setting(
			'kagome_settings',
			'kagome_binary_path',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		// Add settings section
		add_settings_section(
			'kagome_settings_section',
			__( 'Kagome Settings', 'kagome' ),
			array( $this, 'settings_section_callback' ),
			'kagome-analyzer'
		);

		// Add settings fields
		add_settings_field(
			'kagome_user_dictionary',
			__( 'User Dictionary', 'kagome' ),
			array( $this, 'user_dictionary_callback' ),
			'kagome-analyzer',
			'kagome_settings_section'
		);

		add_settings_field(
			'kagome_binary_path',
			__( 'Kagome Binary Path', 'kagome' ),
			array( $this, 'binary_path_callback' ),
			'kagome-analyzer',
			'kagome_settings_section'
		);
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook_suffix Admin page hook suffix.
	 */
	public function enqueue_admin_scripts( $hook_suffix ) {
		if ( 'tools_page_kagome-analyzer' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_script(
			'wp-kagome-analyzer-admin',
			WP_KAGOME_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-api-fetch' ),
			WP_KAGOME_VERSION,
			true
		);

		wp_localize_script(
			'wp-kagome-analyzer-admin',
			'wpKagomeAdmin',
			array(
				'apiUrl'      => rest_url( 'kagome/v1/' ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'isAvailable' => ! ! $this->analyzer,
				'strings'     => array(
					'noText'        => __( 'Please enter text to analyze.', 'kagome' ),
					'analysisError' => __( 'Analysis failed', 'kagome' ),
					'analyzing'     => __( 'Analyzing...', 'kagome' ),
				),
			)
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
		$value = get_option( 'kagome_user_dictionary', '' );
		?>
		<textarea 
			name="kagome_user_dictionary" 
			id="kagome_user_dictionary" 
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
		$value = get_option( 'kagome_binary_path', '' );
		$current_path = $this->get_binary_path();
		?>
		<input 
			type="text" 
			name="kagome_binary_path" 
			id="kagome_binary_path" 
			value="<?php echo esc_attr( $value ); ?>" 
			class="regular-text code"
			placeholder="<?php echo esc_attr( $current_path ); ?>"
		/>
		<p class="description">
			<?php 
			printf(
				/* translators: %s: current binary path */
				esc_html__( 'Leave empty to use the default path: %s', 'kagome' ),
				'<code>' . esc_html( $current_path ) . '</code>'
			); 
			?>
		</p>
		<?php
	}

	/**
	 * Admin page content.
	 */
	public function admin_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Japanese Text Analysis', 'kagome' ); ?></h1>
			
			<?php settings_errors(); ?>
			
			<div id="poststuff">
				<div id="post-body" class="metabox-holder columns-2">
					<div id="post-body-content">
						<!-- Settings Form -->
						<div class="postbox">
							<h2 class="hndle"><?php esc_html_e( 'Settings', 'kagome' ); ?></h2>
							<div class="inside">
								<form method="post" action="options.php">
									<?php
									settings_fields( 'kagome_settings' );
									do_settings_sections( 'kagome-analyzer' );
									submit_button();
									?>
								</form>
							</div>
						</div>
						
						<!-- Text Analysis Test -->
						<div class="postbox">
							<h2 class="hndle"><?php esc_html_e( 'Test Analysis', 'kagome' ); ?></h2>
							<div class="inside">
								<table class="form-table">
									<tr>
										<th scope="row">
											<label for="test_text"><?php esc_html_e( 'Test Text', 'kagome' ); ?></label>
										</th>
										<td>
											<textarea 
												id="test_text" 
												rows="3" 
												cols="50" 
												class="large-text"
												placeholder="<?php esc_attr_e( 'Enter Japanese text to analyze...', 'kagome' ); ?>"
											></textarea>
										</td>
									</tr>
									<tr>
										<th scope="row">
											<label for="analysis_mode"><?php esc_html_e( 'Analysis Mode', 'kagome' ); ?></label>
										</th>
										<td>
											<select id="analysis_mode">
												<option value="normal"><?php esc_html_e( 'Normal', 'kagome' ); ?></option>
												<option value="search"><?php esc_html_e( 'Search', 'kagome' ); ?></option>
												<option value="extended"><?php esc_html_e( 'Extended', 'kagome' ); ?></option>
											</select>
										</td>
									</tr>
								</table>
								<p class="submit">
									<button type="button" id="analyze_button" class="button button-primary">
										<?php esc_html_e( 'Analyze', 'kagome' ); ?>
									</button>
									<button type="button" id="tokenize_button" class="button">
										<?php esc_html_e( 'Tokenize Only', 'kagome' ); ?>
									</button>
								</p>
								<div id="analysis_results" style="display: none;">
									<h3><?php esc_html_e( 'Results', 'kagome' ); ?></h3>
									<pre id="results_output" style="background: #f1f1f1; padding: 10px; overflow-x: auto;"></pre>
								</div>
								<div id="analysis_loading" style="display: none;">
									<p><?php esc_html_e( 'Analyzing...', 'kagome' ); ?></p>
								</div>
							</div>
						</div>
					</div>
					
					<div id="postbox-container-1" class="postbox-container">
						<div class="postbox">
							<h2 class="hndle"><?php esc_html_e( 'Status', 'kagome' ); ?></h2>
							<div class="inside">
								<?php if ( $this->analyzer ) : ?>
									<p><span class="dashicons dashicons-yes-alt" style="color: green;"></span> <?php esc_html_e( 'Kagome analyzer is ready', 'kagome' ); ?></p>
								<?php else : ?>
									<p><span class="dashicons dashicons-warning" style="color: orange;"></span> <?php esc_html_e( 'Kagome analyzer is not available', 'kagome' ); ?></p>
								<?php endif; ?>
								<p><strong><?php esc_html_e( 'Binary Path:', 'kagome' ); ?></strong><br>
								<code><?php echo esc_html( $this->get_binary_path() ); ?></code></p>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Show Kagome error notice.
	 */
	public function show_kagome_error() {
		?>
		<div class="notice notice-error">
			<p>
				<?php esc_html_e( 'Kagome Analyzer: Unable to initialize Kagome binary. Please check if the binary is available and executable.', 'wp-kagome-analyzer' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Show missing dependency notice.
	 */
	public function show_missing_dependency_notice() {
		?>
		<div class="notice notice-error">
			<p>
				<?php
				echo wp_kses_post(
					sprintf(
						/* translators: %s: composer install command */
						__( 'Kagome Analyzer: Required dependency is missing. Please run %s in the plugin directory.', 'wp-kagome-analyzer' ),
						'<code>composer install --no-dev</code>'
					)
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Plugin activation.
	 */
	public function activate() {
		// Add activation logic here if needed.
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation.
	 */
	public function deactivate() {
		// Add deactivation logic here if needed.
		flush_rewrite_rules();
	}

	/**
	 * Get analyzer instance.
	 *
	 * @return \Hametuha\Kagome\Analyzer|null
	 */
	public function get_analyzer() {
		return $this->analyzer;
	}

	/**
	 * Get analysis options including user dictionary.
	 *
	 * @param array $options Additional options to merge.
	 * @return array
	 */
	public function get_analysis_options( $options = array() ) {
		$default_options = array();

		// Add user dictionary if configured
		$user_dict = get_option( 'kagome_user_dictionary', '' );
		if ( ! empty( $user_dict ) ) {
			// Create temporary user dictionary file
			$temp_dict_path = $this->create_temp_user_dict( $user_dict );
			if ( $temp_dict_path ) {
				$default_options['udict'] = $temp_dict_path;
			}
		}

		return array_merge( $default_options, $options );
	}

	/**
	 * Create temporary user dictionary file.
	 *
	 * @param string $dict_content Dictionary content.
	 * @return string|false Temporary file path or false on failure.
	 */
	private function create_temp_user_dict( $dict_content ) {
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
	 * Get binary path with filter support.
	 *
	 * @return string|null
	 */
	private function get_binary_path() {
		// Check if user has set a custom binary path
		$custom_path = get_option( 'kagome_binary_path', '' );
		if ( ! empty( $custom_path ) && file_exists( $custom_path ) ) {
			return $custom_path;
		}

		$default_path = null;

		// Check wp-env mapped directory first.
		if ( defined( 'WP_CONTENT_DIR' ) && file_exists( WP_CONTENT_DIR . '/kagome/kagome-cli-linux' ) ) {
			$default_path = WP_CONTENT_DIR . '/kagome/kagome-cli-linux';
		}

		/**
		 * Filter the Kagome binary path.
		 *
		 * Allows users to specify custom binary locations.
		 *
		 * @param string|null $binary_path The path to the Kagome binary.
		 */
		return apply_filters( 'wp_kagome_binary_path', $default_path );
	}
}
