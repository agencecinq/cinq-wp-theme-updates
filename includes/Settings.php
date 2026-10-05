<?php
/**
 * Plugin settings page.
 *
 * @package CinqWpThemeUpdates
 */

namespace CinqWpThemeUpdates;

/**
 * Admin settings UI.
 */
class Settings {

	/**
	 * Constructor.
	 *
	 * @param Config $config Plugin configuration.
	 */
	public function __construct(
		private Config $config
	) {}

	/**
	 * Register admin hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add the settings page.
	 */
	public function register_menu(): void {
		add_options_page(
			__( 'CINQ Theme Updates', 'cinq-wp-theme-updates' ),
			__( 'CINQ Theme Updates', 'cinq-wp-theme-updates' ),
			'manage_options',
			'cinq-wp-theme-updates',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Handle settings form submission.
	 */
	public function register_settings(): void {
		if ( ! isset( $_POST['cinq_wp_theme_updates_nonce'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		check_admin_referer( 'cinq_wp_theme_updates_save', 'cinq_wp_theme_updates_nonce' );

		$stored_settings = get_option( Config::OPTION_KEY, array() );
		$stored_settings = is_array( $stored_settings ) ? $stored_settings : array();
		$github_token    = isset( $_POST['github_token'] ) ? sanitize_text_field( wp_unslash( $_POST['github_token'] ) ) : '';

		if ( '' === $github_token ) {
			$github_token = (string) ( $stored_settings['github_token'] ?? '' );
		}

		$this->config->save_settings(
			array(
				'theme_slug'   => sanitize_key( wp_unslash( $_POST['theme_slug'] ?? '' ) ),
				'repository'   => sanitize_text_field( wp_unslash( $_POST['repository'] ?? '' ) ),
				'github_token' => $github_token,
				'tag_prefix'   => sanitize_text_field( wp_unslash( $_POST['tag_prefix'] ?? 'v' ) ),
				'zip_filename' => sanitize_file_name( wp_unslash( $_POST['zip_filename'] ?? '' ) ),
			)
		);

		( new GitHubClient( $this->config ) )->clear_cache();
		delete_site_transient( 'update_themes' );

		add_settings_error(
			'cinq_wp_theme_updates',
			'settings_saved',
			__( 'Settings saved. Theme update cache cleared.', 'cinq-wp-theme-updates' ),
			'updated'
		);
	}

	/**
	 * Render the settings page.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings   = $this->config->get_settings_for_display();
		$theme_slug = $this->config->get_theme_slug();
		$theme      = wp_get_theme( $theme_slug );
		$github     = new GitHubClient( $this->config );
		$fetch      = $this->config->is_configured()
			? $github->fetch_latest_release()
			: array(
				'release' => null,
				'error'   => __( 'Repository and GitHub token are required.', 'cinq-wp-theme-updates' ),
			);
		$release    = $fetch['release'];
		$api_error  = $fetch['error'];

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'CINQ Theme Updates', 'cinq-wp-theme-updates' ); ?></h1>

			<?php settings_errors( 'cinq_wp_theme_updates' ); ?>

			<p>
				<?php esc_html_e( 'Configure GitHub release checks for private theme updates.', 'cinq-wp-theme-updates' ); ?>
			</p>

			<form method="post">
				<?php wp_nonce_field( 'cinq_wp_theme_updates_save', 'cinq_wp_theme_updates_nonce' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="theme_slug"><?php esc_html_e( 'Theme slug', 'cinq-wp-theme-updates' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								class="regular-text"
								id="theme_slug"
								name="theme_slug"
								value="<?php echo esc_attr( $settings['theme_slug'] ); ?>"
								placeholder="<?php echo esc_attr( get_stylesheet() ); ?>"
							/>
							<p class="description">
								<?php
								printf(
									/* translators: 1: active theme slug, 2: active theme version */
									esc_html__( 'Leave empty to use the active theme (%1$s, version %2$s).', 'cinq-wp-theme-updates' ),
									esc_html( get_stylesheet() ),
									esc_html( wp_get_theme()->get( 'Version' ) )
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="repository"><?php esc_html_e( 'GitHub repository', 'cinq-wp-theme-updates' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								class="regular-text"
								id="repository"
								name="repository"
								value="<?php echo esc_attr( $settings['repository'] ); ?>"
								placeholder="agencecinq/nexiode"
							/>
							<p class="description">
								<?php
								printf(
									/* translators: %s: detected repository from Theme URI */
									esc_html__( 'Format: owner/repo. Auto-detected from Theme URI: %s', 'cinq-wp-theme-updates' ),
									esc_html( '' !== $this->config->get_repository() ? $this->config->get_repository() : __( 'not found', 'cinq-wp-theme-updates' ) )
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="github_token"><?php esc_html_e( 'GitHub token', 'cinq-wp-theme-updates' ); ?></label>
						</th>
						<td>
							<input
								type="password"
								class="regular-text"
								id="github_token"
								name="github_token"
								value=""
								autocomplete="off"
								placeholder="<?php esc_attr_e( 'ghp_...', 'cinq-wp-theme-updates' ); ?>"
							/>
							<p class="description">
								<?php
								if ( ! empty( $settings['has_stored_token'] ) ) {
									esc_html_e( 'A token is saved. Leave empty to keep the current token.', 'cinq-wp-theme-updates' );
									echo ' ';
								}
								esc_html_e( 'Fine-grained token with read access to the repository contents, or a classic token with repo scope.', 'cinq-wp-theme-updates' );
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="tag_prefix"><?php esc_html_e( 'Tag prefix', 'cinq-wp-theme-updates' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								class="regular-text"
								id="tag_prefix"
								name="tag_prefix"
								value="<?php echo esc_attr( $settings['tag_prefix'] ); ?>"
							/>
							<p class="description">
								<?php esc_html_e( 'Prefix stripped from tags before version comparison (default: v).', 'cinq-wp-theme-updates' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="zip_filename"><?php esc_html_e( 'ZIP filename', 'cinq-wp-theme-updates' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								class="regular-text"
								id="zip_filename"
								name="zip_filename"
								value="<?php echo esc_attr( $settings['zip_filename'] ); ?>"
								placeholder="<?php echo esc_attr( $this->config->get_zip_filename() ); ?>"
							/>
							<p class="description">
								<?php esc_html_e( 'Release asset filename. Defaults to {theme-slug}.zip.', 'cinq-wp-theme-updates' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save settings', 'cinq-wp-theme-updates' ) ); ?>
			</form>

			<hr>

			<h2><?php esc_html_e( 'Status', 'cinq-wp-theme-updates' ); ?></h2>
			<ul>
				<li>
					<strong><?php esc_html_e( 'GitHub token', 'cinq-wp-theme-updates' ); ?>:</strong>
					<?php
					if ( ! empty( $settings['has_stored_token'] ) ) {
						esc_html_e( 'Saved in plugin settings', 'cinq-wp-theme-updates' );
					} else {
						esc_html_e( 'Missing', 'cinq-wp-theme-updates' );
					}
					?>
				</li>
				<li>
					<strong><?php esc_html_e( 'GitHub repository', 'cinq-wp-theme-updates' ); ?>:</strong>
					<?php echo esc_html( '' !== $this->config->get_repository() ? $this->config->get_repository() : __( 'not configured', 'cinq-wp-theme-updates' ) ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Monitored theme', 'cinq-wp-theme-updates' ); ?>:</strong>
					<?php echo esc_html( $theme->get( 'Name' ) . ' (' . $theme_slug . ')' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Installed version', 'cinq-wp-theme-updates' ); ?>:</strong>
					<?php echo esc_html( $theme->get( 'Version' ) ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Latest GitHub release', 'cinq-wp-theme-updates' ); ?>:</strong>
					<?php
					if ( null === $release ) {
						echo esc_html__( 'Unavailable.', 'cinq-wp-theme-updates' );

						if ( ! empty( $api_error ) ) {
							echo ' ' . esc_html( $api_error );
						}
					} else {
						echo esc_html( $release['version'] );

						if ( version_compare( $release['version'], $theme->get( 'Version' ), '>' ) ) {
							echo ' — ' . esc_html__( 'update available', 'cinq-wp-theme-updates' );
						} else {
							echo ' — ' . esc_html__( 'up to date', 'cinq-wp-theme-updates' );
						}
					}
					?>
				</li>
			</ul>
		</div>
		<?php
	}
}
