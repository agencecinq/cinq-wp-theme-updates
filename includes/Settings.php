<?php
/**
 * Plugin settings page.
 *
 * @package CinqWpThemeUpdates
 */

namespace CinqWpThemeUpdates;

/**
 * Admin settings UI, saved by options.php through the Settings API.
 */
class Settings {

	/**
	 * Slug of the settings page, also its option group.
	 */
	private const PAGE = 'cinq-wp-theme-updates';

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
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register the setting and its fields.
	 */
	public function register_settings(): void {
		register_setting(
			self::PAGE,
			Config::OPTION_KEY,
			array( 'sanitize_callback' => array( $this->config, 'sanitize_settings' ) )
		);

		add_settings_section( 'default', '', '', self::PAGE );

		$fields = array(
			'theme_slug'   => __( 'Theme slug', 'cinq-wp-theme-updates' ),
			'repository'   => __( 'GitHub repository', 'cinq-wp-theme-updates' ),
			'github_token' => __( 'GitHub token', 'cinq-wp-theme-updates' ),
			'tag_prefix'   => __( 'Tag prefix', 'cinq-wp-theme-updates' ),
			'zip_filename' => __( 'ZIP filename', 'cinq-wp-theme-updates' ),
		);

		foreach ( $fields as $key => $label ) {
			add_settings_field( $key, $label, array( $this, 'render_field' ), self::PAGE, 'default', array( 'label_for' => $key ) );
		}
	}

	/**
	 * Render a field with its description.
	 *
	 * The token is never printed back: an empty field keeps the saved one.
	 *
	 * @param array{label_for: string} $args Field arguments.
	 */
	public function render_field( array $args ): void {
		$key            = $args['label_for'];
		$is_token       = 'github_token' === $key;
		$type           = $is_token ? 'password' : 'text';
		$autocomplete   = $is_token ? 'new-password' : 'on';
		$name           = Config::OPTION_KEY . '[' . $key . ']';
		$value          = $is_token ? '' : $this->config->get_setting( $key );
		$placeholder    = $this->placeholder( $key );
		$description    = $this->description( $key );
		$description_id = $key . '-description';

		?>
		<input
			type="<?php echo esc_attr( $type ); ?>"
			class="regular-text"
			id="<?php echo esc_attr( $key ); ?>"
			name="<?php echo esc_attr( $name ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			placeholder="<?php echo esc_attr( $placeholder ); ?>"
			autocomplete="<?php echo esc_attr( $autocomplete ); ?>"
			aria-describedby="<?php echo esc_attr( $description_id ); ?>"
		/>
		<p class="description" id="<?php echo esc_attr( $description_id ); ?>">
			<?php echo esc_html( $description ); ?>
		</p>
		<?php
	}

	/**
	 * Render the settings page.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$theme_slug = $this->config->get_theme_slug();
		$theme      = wp_get_theme( $theme_slug );
		$repository = $this->config->get_repository();
		$fetch      = ( new GitHubClient( $this->config ) )->fetch_latest_release();
		$release    = $fetch['release'];
		$api_error  = $fetch['error'];

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<p>
				<?php esc_html_e( 'Configure GitHub release checks for private theme updates.', 'cinq-wp-theme-updates' ); ?>
			</p>

			<form action="options.php" method="post">
				<?php
				settings_fields( self::PAGE );
				do_settings_sections( self::PAGE );
				submit_button( __( 'Save settings', 'cinq-wp-theme-updates' ) );
				?>
			</form>

			<hr>

			<h2><?php esc_html_e( 'Status', 'cinq-wp-theme-updates' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'GitHub token', 'cinq-wp-theme-updates' ); ?></th>
					<td>
						<?php
						if ( '' !== $this->config->get_setting( 'github_token' ) ) {
							esc_html_e( 'Saved in plugin settings', 'cinq-wp-theme-updates' );
						} else {
							esc_html_e( 'Missing', 'cinq-wp-theme-updates' );
						}
						?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'GitHub repository', 'cinq-wp-theme-updates' ); ?></th>
					<td><?php echo esc_html( '' !== $repository ? $repository : __( 'not configured', 'cinq-wp-theme-updates' ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Monitored theme', 'cinq-wp-theme-updates' ); ?></th>
					<td><?php echo esc_html( $theme->get( 'Name' ) . ' (' . $theme_slug . ')' ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Installed version', 'cinq-wp-theme-updates' ); ?></th>
					<td><?php echo esc_html( $theme->get( 'Version' ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Latest GitHub release', 'cinq-wp-theme-updates' ); ?></th>
					<td>
						<?php
						if ( null === $release ) {
							echo esc_html__( 'Unavailable.', 'cinq-wp-theme-updates' );

							if ( ! empty( $api_error ) ) {
								echo ' ' . esc_html( $api_error );
							}
						} else {
							printf(
								'<a href="%1$s">%2$s</a>',
								esc_url( $release['url'] ),
								esc_html( $release['name'] )
							);

							if ( version_compare( $release['version'], $theme->get( 'Version' ), '<=' ) ) {
								echo ' — ' . esc_html__( 'up to date', 'cinq-wp-theme-updates' );
							} elseif ( '' === $release['package'] ) {
								echo ' — ' . esc_html__( 'no ZIP attached to the release', 'cinq-wp-theme-updates' );
							} else {
								echo ' — ' . esc_html__( 'update available', 'cinq-wp-theme-updates' );
							}
						}
						?>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * Placeholder of a field.
	 *
	 * @param string $key Setting key.
	 */
	private function placeholder( string $key ): string {
		return match ( $key ) {
			'theme_slug'   => get_stylesheet(),
			'repository'   => 'owner/my-theme',
			'github_token' => 'ghp_...',
			'tag_prefix'   => 'v',
			'zip_filename' => $this->config->get_zip_filename(),
		};
	}

	/**
	 * Description printed under a field.
	 *
	 * @param string $key Setting key.
	 */
	private function description( string $key ): string {
		return match ( $key ) {
			'theme_slug'   => sprintf(
				/* translators: 1: active theme slug, 2: active theme version */
				__( 'Leave empty to use the active theme (%1$s, version %2$s).', 'cinq-wp-theme-updates' ),
				get_stylesheet(),
				wp_get_theme()->get( 'Version' )
			),
			'repository'   => sprintf(
				/* translators: %s: detected repository from Theme URI */
				__( 'Format: owner/repo. Auto-detected from Theme URI: %s', 'cinq-wp-theme-updates' ),
				'' !== $this->config->get_repository() ? $this->config->get_repository() : __( 'not found', 'cinq-wp-theme-updates' )
			),
			'github_token' => __( 'Fine-grained token with read access to the repository contents, or a classic token with repo scope. Leave empty to keep the saved token.', 'cinq-wp-theme-updates' ),
			'tag_prefix'   => __( 'Prefix stripped from tags before version comparison (default: v).', 'cinq-wp-theme-updates' ),
			'zip_filename' => __( 'Release asset filename. Defaults to {theme-slug}.zip.', 'cinq-wp-theme-updates' ),
		};
	}
}
