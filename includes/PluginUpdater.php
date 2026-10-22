<?php
/**
 * Plugin updates from GitHub releases.
 *
 * @package CinqWpThemeUpdates
 */

namespace CinqWpThemeUpdates;

/**
 * Offers updates from the latest GitHub release, like any other plugin, and
 * installs them on its own.
 *
 * The plugin is distributed on GitHub only. Its `Update URI` header points
 * there, which makes WordPress skip WordPress.org and ask the
 * `update_plugins_github.com` filter instead. That filter reads the latest
 * release (cached twelve hours) and hands WordPress the zip the release
 * workflow attaches to it.
 *
 * The release is read from the `update.json` the workflow attaches next to the
 * zip: a plain download, which GitHub does not rate-limit, where its API
 * allows sixty calls an hour per address and shared hosting puts many sites
 * behind one. The API stays as a fallback for releases without the file.
 *
 * The manifest carries the README.txt of the release: the "View details" modal
 * shows its sections, as WordPress.org does, and the Updates screen the
 * WordPress version it is tested up to.
 *
 * Auto-updates are on: WordPress installs a new release with its own updates,
 * twice a day. The `cinq_wp_theme_updates_auto_update` filter turns them off.
 */
class PluginUpdater {

	/**
	 * Repository, `owner/name`.
	 */
	public const REPOSITORY = 'agencecinq/cinq-wp-theme-updates';

	/**
	 * Name of the zip attached to each release.
	 */
	public const ASSET = 'cinq-wp-theme-updates.zip';

	/**
	 * Name of the release manifest attached to each release.
	 */
	public const MANIFEST = 'update.json';

	/**
	 * Transient caching the latest release.
	 */
	private const TRANSIENT = 'cinq_wp_theme_updates_latest_release';

	/**
	 * Constructor.
	 *
	 * @param string $plugin_file Plugin basename, e.g. cinq-wp-theme-updates/cinq-wp-theme-updates.php.
	 */
	public function __construct(
		private string $plugin_file
	) {}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'update_plugins_github.com', array( $this, 'check' ), 10, 3 );
		add_filter( 'plugins_api', array( $this, 'details' ), 20, 3 );
		add_filter( 'auto_update_plugin', array( $this, 'auto_update' ), 10, 2 );
		add_filter( 'upgrader_source_selection', array( $this, 'keep_folder' ), 10, 4 );
		add_action( 'upgrader_process_complete', array( self::class, 'forget' ) );
		add_action( 'load-update-core.php', array( $this, 'force_check' ), 9 );
	}

	/**
	 * Turns auto-updates on for this plugin.
	 *
	 * @param bool|null $update Whether to update, as decided so far.
	 * @param object    $item   Update offer.
	 * @return bool|null
	 */
	public function auto_update( $update, $item ) {
		if ( ! is_object( $item ) || ( $item->plugin ?? '' ) !== $this->plugin_file ) {
			return $update;
		}

		/**
		 * Filters whether WordPress installs new releases of the plugin on its own.
		 *
		 * @since 1.4.0
		 *
		 * @param bool $enabled Default true.
		 */
		return (bool) apply_filters( 'cinq_wp_theme_updates_auto_update', true );
	}

	/**
	 * Unpacks an update into the folder the plugin already lives in.
	 *
	 * The zip unpacks into `cinq-wp-theme-updates/`. A copy installed under
	 * another name (a renamed folder) would otherwise come back under that one,
	 * and WordPress would deactivate the plugin it can no longer find.
	 *
	 * @param string|\WP_Error     $source        Unpacked folder.
	 * @param string               $remote_source Folder it was unpacked in.
	 * @param mixed                $upgrader      Upgrader.
	 * @param array<string, mixed> $hook_extra    Context; `plugin` on updates.
	 * @return string|\WP_Error
	 */
	public function keep_folder( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		global $wp_filesystem;

		if ( ! is_string( $source ) || ( $hook_extra['plugin'] ?? '' ) !== $this->plugin_file ) {
			return $source;
		}

		$folder = dirname( $this->plugin_file );
		$target = trailingslashit( $remote_source ) . $folder . '/';

		if ( '.' === $folder || untrailingslashit( $source ) === untrailingslashit( $target ) || ! is_object( $wp_filesystem ) ) {
			return $source;
		}

		return $wp_filesystem->move( $source, $target, true ) ? $target : $source;
	}

	/**
	 * Forgets the cached release, so the next check asks GitHub again.
	 *
	 * @return void
	 */
	public static function forget(): void {
		delete_transient( self::TRANSIENT );
	}

	/**
	 * Reads GitHub again when "Check again" is clicked on the Updates screen.
	 *
	 * Priority 9: core checks for plugin updates on the same hook at 10, and
	 * must find the cache already empty.
	 *
	 * @return void
	 */
	public function force_check(): void {
		if ( isset( $_GET['force-check'] ) && current_user_can( 'update_plugins' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- core's own link; only drops a cache.
			self::forget();
		}
	}

	/**
	 * Answers WordPress's update check for this plugin.
	 *
	 * @param array<string, mixed>|false $update      Update found so far.
	 * @param array<string, mixed>       $plugin_data Plugin headers.
	 * @param string                     $plugin_file Plugin basename.
	 * @return array<string, mixed>|false
	 */
	public function check( $update, $plugin_data, $plugin_file ) {
		if ( $this->plugin_file !== $plugin_file ) {
			return $update;
		}

		// Updating replaces the plugin folder: never offer it over a Git working copy.
		if ( is_dir( CINQ_WP_THEME_UPDATES_PATH . '.git' ) ) {
			return $update;
		}

		$release = self::latest();

		if ( null === $release ) {
			return $update;
		}

		return array(
			'id'           => 'github.com/' . self::REPOSITORY,
			'slug'         => dirname( $plugin_file ),
			'plugin'       => $plugin_file,
			'version'      => $release['version'],
			'url'          => $release['url'],
			'package'      => $release['package'],
			'requires'     => $release['requires'],
			'requires_php' => $release['requires_php'],
			'tested'       => self::tested( $release['readme'] ),
			'icons'        => array( 'svg' => plugins_url( 'assets/icon.svg', CINQ_WP_THEME_UPDATES_FILE ) ),
		);
	}

	/**
	 * Fills the "View details" modal with the readme of the release, or else
	 * its notes.
	 *
	 * @param false|object|array<string, mixed> $result Result so far.
	 * @param string                            $action API action.
	 * @param object                            $args   Request arguments.
	 * @return false|object|array<string, mixed>
	 */
	public function details( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || dirname( $this->plugin_file ) !== ( $args->slug ?? '' ) ) {
			return $result;
		}

		$release = self::latest();

		if ( null === $release ) {
			return $result;
		}

		$sections = Readme::sections( $release['readme'] );

		return (object) array(
			'name'          => 'CINQ Theme Updates',
			'slug'          => dirname( $this->plugin_file ),
			'version'       => $release['version'],
			'author'        => '<a href="https://agencecinq.com">CINQ</a>',
			'homepage'      => 'https://github.com/' . self::REPOSITORY,
			'download_link' => $release['package'],
			'requires'      => $release['requires'],
			'tested'        => self::tested( $release['readme'] ),
			'requires_php'  => $release['requires_php'],
			'last_updated'  => $release['last_updated'],
			'banners'       => array( 'high' => plugins_url( 'assets/banner.svg', CINQ_WP_THEME_UPDATES_FILE ) ),
			'sections'      => $sections ? $sections : array(
				'changelog' => '' === $release['notes']
					? sprintf( '<p><a href="%s">%s</a></p>', esc_url( $release['url'] ), esc_html__( 'Release notes on GitHub', 'cinq-wp-theme-updates' ) )
					: wpautop( esc_html( $release['notes'] ) ),
			),
		);
	}

	/**
	 * WordPress version the release is tested up to.
	 *
	 * As on WordPress.org, "Tested up to: 7.1" covers every 7.1.x release:
	 * WordPress compares full versions, and would warn a 7.1.3 site that the
	 * plugin is not tested with it.
	 *
	 * @param string $readme Readme of the release.
	 */
	private static function tested( string $readme ): string {
		$tested     = Readme::header( $readme, 'Tested up to' );
		$wp_version = get_bloginfo( 'version' );

		return '' !== $tested && str_starts_with( $wp_version . '.', $tested . '.' ) ? $wp_version : $tested;
	}

	/**
	 * Returns the latest release, from cache or from GitHub.
	 *
	 * @return array{version: string, url: string, package: string, notes: string, readme: string, requires: string, requires_php: string, last_updated: string}|null
	 */
	public static function latest(): ?array {
		$cached = get_transient( self::TRANSIENT );

		if ( is_array( $cached ) ) {
			return empty( $cached['version'] ) ? null : $cached;
		}

		$release = self::parse_manifest( self::fetch( 'https://github.com/' . self::REPOSITORY . '/releases/latest/download/' . self::MANIFEST ) )
			?? self::parse( self::fetch( 'https://api.github.com/repos/' . self::REPOSITORY . '/releases/latest' ) );

		// Remember failures too, for an hour, so a GitHub outage does not slow
		// every admin page down.
		set_transient( self::TRANSIENT, $release ?? array(), null === $release ? HOUR_IN_SECONDS : 12 * HOUR_IN_SECONDS );

		return $release;
	}

	/**
	 * Downloads a URL, following GitHub's redirects to its file storage.
	 *
	 * @param string $url URL.
	 * @return string Body, empty on any failure.
	 */
	private static function fetch( string $url ): string {
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json, application/json',
					'User-Agent' => 'cinq-wp-theme-updates/' . CINQ_WP_THEME_UPDATES_VERSION,
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return '';
		}

		return (string) wp_remote_retrieve_body( $response );
	}

	/**
	 * Reads the `update.json` attached to a release.
	 *
	 * @param string $body File contents.
	 * @return array{version: string, url: string, package: string, notes: string, readme: string, requires: string, requires_php: string, last_updated: string}|null
	 */
	public static function parse_manifest( string $body ): ?array {
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || empty( $data['version'] ) || empty( $data['package'] ) ) {
			return null;
		}

		$package = (string) $data['package'];

		// Only ever install what this repository's releases serve.
		if ( ! str_starts_with( $package, 'https://github.com/' . self::REPOSITORY . '/releases/download/' ) ) {
			return null;
		}

		return array(
			'version'      => ltrim( (string) $data['version'], 'vV' ),
			'url'          => (string) ( $data['url'] ?? 'https://github.com/' . self::REPOSITORY . '/releases' ),
			'package'      => $package,
			'notes'        => (string) ( $data['notes'] ?? '' ),
			'readme'       => (string) ( $data['readme'] ?? '' ),
			'requires'     => (string) ( $data['requires'] ?? '' ),
			'requires_php' => (string) ( $data['requires_php'] ?? '8.1' ),
			'last_updated' => (string) ( $data['last_updated'] ?? '' ),
		);
	}

	/**
	 * Reads a GitHub "latest release" API response.
	 *
	 * @param string $body Response body.
	 * @return array{version: string, url: string, package: string, notes: string, readme: string, requires: string, requires_php: string, last_updated: string}|null
	 */
	public static function parse( string $body ): ?array {
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || empty( $data['tag_name'] ) || ! empty( $data['draft'] ) || ! empty( $data['prerelease'] ) ) {
			return null;
		}

		$package = '';

		foreach ( (array) ( $data['assets'] ?? array() ) as $asset ) {
			if ( self::ASSET === ( $asset['name'] ?? '' ) && ! empty( $asset['browser_download_url'] ) ) {
				$package = (string) $asset['browser_download_url'];
			}
		}

		// The source archive GitHub builds unpacks into "cinq-wp-theme-updates-1.4.0/",
		// which WordPress would install as a second plugin: no asset, no update.
		if ( '' === $package ) {
			return null;
		}

		return array(
			'version'      => ltrim( (string) $data['tag_name'], 'vV' ),
			'url'          => (string) ( $data['html_url'] ?? 'https://github.com/' . self::REPOSITORY ),
			'package'      => $package,
			'notes'        => (string) ( $data['body'] ?? '' ),
			'readme'       => '',
			'requires'     => '',
			'requires_php' => '8.1',
			'last_updated' => (string) ( $data['published_at'] ?? '' ),
		);
	}
}
