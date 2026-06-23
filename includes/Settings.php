<?php
/**
 * Plugin settings page.
 *
 * @package CinqThemeUpdateChecker
 */

namespace CinqThemeUpdateChecker;

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
			__( 'CINQ Theme Updates', 'cinq-theme-update-checker' ),
			__( 'CINQ Theme Updates', 'cinq-theme-update-checker' ),
			'manage_options',
			'cinq-theme-update-checker',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Handle settings form submission.
	 */
	public function register_settings(): void {
		if ( ! isset( $_POST['cinq_theme_update_checker_nonce'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		check_admin_referer( 'cinq_theme_update_checker_save', 'cinq_theme_update_checker_nonce' );

		$stored_settings = get_option( Config::OPTION_KEY, array() );
		$stored_settings = is_array( $stored_settings ) ? $stored_settings : array();
		$github_token    = wp_unslash( $_POST['github_token'] ?? '' );

		if ( '' === $github_token && ! $this->config->has_constant_token() ) {
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
			'cinq_theme_update_checker',
			'settings_saved',
			__( 'Settings saved. Theme update cache cleared.', 'cinq-theme-update-checker' ),
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
				'error'   => __( 'Repository and GitHub token are required.', 'cinq-theme-update-checker' ),
			);
		$release    = $fetch['release'];
		$api_error  = $fetch['error'];

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'CINQ Theme Updates', 'cinq-theme-update-checker' ); ?></h1>

			<?php settings_errors( 'cinq_theme_update_checker' ); ?>

			<p>
				<?php esc_html_e( 'Configure GitHub release checks for private theme updates.', 'cinq-theme-update-checker' ); ?>
			</p>

			<form method="post">
				<?php wp_nonce_field( 'cinq_theme_update_checker_save', 'cinq_theme_update_checker_nonce' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="theme_slug"><?php esc_html_e( 'Theme slug', 'cinq-theme-update-checker' ); ?></label>
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
									esc_html__( 'Leave empty to use the active theme (%1$s, version %2$s).', 'cinq-theme-update-checker' ),
									esc_html( get_stylesheet() ),
									esc_html( wp_get_theme()->get( 'Version' ) )
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="repository"><?php esc_html_e( 'GitHub repository', 'cinq-theme-update-checker' ); ?></label>
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
									esc_html__( 'Format: owner/repo. Auto-detected from Theme URI: %s', 'cinq-theme-update-checker' ),
									esc_html( $this->config->get_repository() ?: __( 'not found', 'cinq-theme-update-checker' ) )
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="github_token"><?php esc_html_e( 'GitHub token', 'cinq-theme-update-checker' ); ?></label>
						</th>
						<td>
							<?php if ( $this->config->has_constant_token() ) : ?>
								<p>
									<code>CINQ_THEME_UPDATE_GITHUB_TOKEN</code>
									<?php esc_html_e( 'is defined in wp-config.php.', 'cinq-theme-update-checker' ); ?>
								</p>
							<?php else : ?>
								<input
									type="password"
									class="regular-text"
									id="github_token"
									name="github_token"
									value=""
									autocomplete="off"
									placeholder="<?php esc_attr_e( 'ghp_...', 'cinq-theme-update-checker' ); ?>"
								/>
								<p class="description">
									<?php
									if ( ! empty( $settings['has_stored_token'] ) ) {
										esc_html_e( 'A token is saved. Leave empty to keep the current token.', 'cinq-theme-update-checker' );
										echo ' ';
									}
									esc_html_e( 'Fine-grained token with read access to the repository contents, or a classic token with repo scope.', 'cinq-theme-update-checker' );
									?>
								</p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="tag_prefix"><?php esc_html_e( 'Tag prefix', 'cinq-theme-update-checker' ); ?></label>
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
								<?php esc_html_e( 'Prefix stripped from tags before version comparison (default: v).', 'cinq-theme-update-checker' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="zip_filename"><?php esc_html_e( 'ZIP filename', 'cinq-theme-update-checker' ); ?></label>
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
								<?php esc_html_e( 'Release asset filename. Defaults to {theme-slug}.zip.', 'cinq-theme-update-checker' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save settings', 'cinq-theme-update-checker' ) ); ?>
			</form>

			<hr>

			<h2><?php esc_html_e( 'Status', 'cinq-theme-update-checker' ); ?></h2>
			<ul>
				<li>
					<strong><?php esc_html_e( 'GitHub token', 'cinq-theme-update-checker' ); ?>:</strong>
					<?php
					if ( $this->config->has_constant_token() ) {
						esc_html_e( 'Configured via wp-config.php', 'cinq-theme-update-checker' );
					} elseif ( ! empty( $settings['has_stored_token'] ) ) {
						esc_html_e( 'Saved in plugin settings', 'cinq-theme-update-checker' );
					} else {
						esc_html_e( 'Missing', 'cinq-theme-update-checker' );
					}
					?>
				</li>
				<li>
					<strong><?php esc_html_e( 'GitHub repository', 'cinq-theme-update-checker' ); ?>:</strong>
					<?php echo esc_html( $this->config->get_repository() ?: __( 'not configured', 'cinq-theme-update-checker' ) ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Monitored theme', 'cinq-theme-update-checker' ); ?>:</strong>
					<?php echo esc_html( $theme->get( 'Name' ) . ' (' . $theme_slug . ')' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Installed version', 'cinq-theme-update-checker' ); ?>:</strong>
					<?php echo esc_html( $theme->get( 'Version' ) ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Latest GitHub release', 'cinq-theme-update-checker' ); ?>:</strong>
					<?php
					if ( null === $release ) {
						echo esc_html__( 'Unavailable.', 'cinq-theme-update-checker' );

						if ( ! empty( $api_error ) ) {
							echo ' ' . esc_html( $api_error );
						}
					} else {
						echo esc_html( $release['version'] );

						if ( version_compare( $release['version'], $theme->get( 'Version' ), '>' ) ) {
							echo ' — ' . esc_html__( 'update available', 'cinq-theme-update-checker' );
						} else {
							echo ' — ' . esc_html__( 'up to date', 'cinq-theme-update-checker' );
						}
					}
					?>
				</li>
			</ul>
		</div>
		<?php
	}
}
