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
	 * @var \Hametuha\KagomeWp\Analyzer
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

		// Initialize Kagome analyzer. The class is bundled with the plugin, so the
		// class_exists guard mainly protects against a broken autoloader; the
		// try/catch is what matters (Analyzer throws when the binary is missing or
		// not executable).
		if ( class_exists( '\Hametuha\KagomeWp\Analyzer' ) ) {
			try {
				$binary_path = $this->get_binary_path();
				$this->analyzer = new \Hametuha\KagomeWp\Analyzer( $binary_path );
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
		Rest\Performance::get_instance();

		// Initialize settings management
		Settings::get_instance();

		// Initialize search index table
		Model\SearchIndex::get_instance();

		// Initialize search query optimizer
		Search\QueryOptimizer::get_instance();

		// Add admin menu if user has proper capabilities.
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		}

		// Hook for post save to update search index
		add_action( 'save_post', array( $this, 'update_search_index' ), 10, 2 );
		add_action( 'delete_post', array( $this, 'delete_search_index' ) );
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
									$settings = Settings::get_instance();
									settings_fields( Settings::OPTION_GROUP );
									$settings->add_settings_fields( 'kagome-analyzer' );
									do_settings_sections( 'kagome-analyzer' );
									submit_button();
									?>
								</form>
							</div>
						</div>

						<!-- Search Performance Test -->
						<div class="postbox">
							<h2 class="hndle"><?php esc_html_e( 'Search Performance Test', 'kagome' ); ?></h2>
							<div class="inside">
								<table class="form-table">
									<tr>
										<th scope="row">
											<label for="performance_search_term"><?php esc_html_e( 'Search Term', 'kagome' ); ?></label>
										</th>
										<td>
											<input 
												type="text" 
												id="performance_search_term" 
												class="regular-text"
												placeholder="<?php esc_attr_e( 'Enter Japanese text to search...', 'kagome' ); ?>"
												value="破滅派" />
										</td>
									</tr>
									<tr>
										<th scope="row">
											<label for="performance_limit"><?php esc_html_e( 'Result Limit', 'kagome' ); ?></label>
										</th>
										<td>
											<select id="performance_limit">
												<option value="10">10</option>
												<option value="50" selected>50</option>
												<option value="100">100</option>
											</select>
										</td>
									</tr>
								</table>
								<p class="submit">
									<button type="button" id="run_performance_test" class="button button-primary">
										<?php esc_html_e( 'Run Performance Test', 'kagome' ); ?>
									</button>
								</p>
								<div id="performance_results" style="display: none;">
									<h3><?php esc_html_e( 'Performance Results', 'kagome' ); ?></h3>
									<div id="performance_output"></div>
								</div>
								<div id="performance_loading" style="display: none;">
									<p><?php esc_html_e( 'Running performance tests...', 'kagome' ); ?></p>
								</div>
							</div>
						</div>

						<!-- Text Analysis Test -->
						<div class="postbox">
							<h2 class="hndle"><?php esc_html_e( 'Text Analysis Test', 'kagome' ); ?></h2>
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
								<code><?php echo esc_html( Settings::get_instance()->get_binary_path() ); ?></code></p>
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
	 * Get analyzer instance.
	 *
	 * @return \Hametuha\KagomeWp\Analyzer|null
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
		return Settings::get_instance()->get_analysis_options( $options );
	}

	/**
	 * Get binary path with filter support.
	 *
	 * @return string|null
	 */
	private function get_binary_path() {
		return Settings::get_instance()->get_binary_path();
	}

	/**
	 * Update search index when post is saved.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function update_search_index( $post_id, $post ) {
		// Skip auto-saves and revisions.
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Skip if post status is not published.
		if ( 'publish' !== $post->post_status ) {
			return;
		}

		// Check if post type is indexable
		$indexable_types = wp_kagome_indexable_post_types();
		if ( ! in_array( $post->post_type, $indexable_types, true ) ) {
			return;
		}

		// Update search index.
		$search_index = Model\SearchIndex::get_instance();
		$search_index->update_index( $post_id );
	}

	/**
	 * Delete search index when post is deleted.
	 *
	 * @param int $post_id Post ID.
	 */
	public function delete_search_index( $post_id ) {
		$search_index = Model\SearchIndex::get_instance();
		$search_index->delete_index( $post_id );
	}


}
