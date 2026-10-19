<?php
/**
 * Plugin updates from GitHub releases.
 *
 * @package CinqWpThemeUpdates
 */

namespace CinqWpThemeUpdates;

/**
 * Offers the latest GitHub release of this plugin as a plugin update.
 *
 * WordPress calls update_plugins_{hostname} for plugins whose Update URI header
 * points to that host, then compares the returned version with the installed one.
 */
class PluginUpdater {

	/**
	 * Site transient holding the latest release.
	 */
	private const CACHE_KEY = 'cinq_wp_theme_updates_plugin_release';

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
	 */
	public function register(): void {
		add_filter( 'update_plugins_github.com', array( $this, 'check_update' ), 10, 3 );
		add_filter( 'plugins_api', array( $this, 'plugin_details' ), 20, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'rename_source' ), 10, 4 );
	}

	/**
	 * Return the latest release as update data.
	 *
	 * @param array|false $update      Update data, false when no other filter answered.
	 * @param array       $plugin_data Plugin headers.
	 * @param string      $plugin_file Plugin basename.
	 * @return array|false
	 */
	public function check_update( $update, array $plugin_data, string $plugin_file ) {
		unset( $plugin_data );

		if ( $this->plugin_file !== $plugin_file ) {
			return $update;
		}

		// Updating replaces the plugin folder: never offer it over a Git working copy.
		if ( is_dir( CINQ_WP_THEME_UPDATES_PATH . '.git' ) ) {
			return $update;
		}

		$release = $this->get_release();

		if ( null === $release ) {
			return $update;
		}

		return array(
			'slug'    => $this->slug(),
			'version' => $release['version'],
			'url'     => $release['url'],
			'package' => $release['package'],
		);
	}

	/**
	 * Fill the "View details" modal with the plugin description and the release notes.
	 *
	 * @param false|object|array $result Plugin information, false by default.
	 * @param string             $action API action.
	 * @param object             $args   Request arguments.
	 * @return false|object|array
	 */
	public function plugin_details( $result, string $action, $args ) {
		if ( 'plugin_information' !== $action || ! is_object( $args ) || ( $args->slug ?? '' ) !== $this->slug() ) {
			return $result;
		}

		$release = $this->get_release();

		if ( null === $release ) {
			return $result;
		}

		$plugin_data = get_plugin_data( CINQ_WP_THEME_UPDATES_FILE, false, false );

		return (object) array(
			'name'          => $plugin_data['Name'],
			'slug'          => $this->slug(),
			'version'       => $release['version'],
			'author'        => $plugin_data['Author'],
			'homepage'      => $plugin_data['PluginURI'],
			'requires'      => $plugin_data['RequiresWP'],
			'requires_php'  => $plugin_data['RequiresPHP'],
			'last_updated'  => $release['date'],
			'download_link' => $release['package'],
			'sections'      => array(
				'description' => wpautop( esc_html( $plugin_data['Description'] ) ),
				'changelog'   => wpautop( esc_html( $release['notes'] ) ),
			),
		);
	}

	/**
	 * Rename the extracted archive folder (owner-repo-sha) to the plugin slug.
	 *
	 * @param string|\WP_Error $source        Path of the extracted folder.
	 * @param string           $remote_source Parent folder of the extraction.
	 * @param \WP_Upgrader     $upgrader      Upgrader instance.
	 * @param array            $hook_extra    Extra arguments, with the plugin being updated.
	 * @return string|\WP_Error
	 */
	public function rename_source( $source, string $remote_source, $upgrader, array $hook_extra ) {
		unset( $upgrader );

		if ( is_wp_error( $source ) || ( $hook_extra['plugin'] ?? '' ) !== $this->plugin_file ) {
			return $source;
		}

		global $wp_filesystem;

		$destination = trailingslashit( $remote_source ) . $this->slug() . '/';

		if ( trailingslashit( $source ) === $destination ) {
			return $source;
		}

		if ( ! $wp_filesystem->move( $source, $destination ) ) {
			return new \WP_Error(
				'cinq_wp_theme_updates_rename_failed',
				__( 'Could not rename the update folder.', 'cinq-wp-theme-updates' )
			);
		}

		return $destination;
	}

	/**
	 * Latest GitHub release, cached to stay under the API rate limit.
	 *
	 * @return array{version: string, package: string, url: string, notes: string, date: string}|null
	 */
	private function get_release(): ?array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- "Check again" button on the updates screen.
		$force_check = isset( $_GET['force-check'] );
		$cached      = $force_check ? false : get_site_transient( self::CACHE_KEY );

		if ( false !== $cached ) {
			return is_array( $cached ) ? $cached : null;
		}

		$url      = 'https://api.github.com/repos/' . $this->repository() . '/releases/latest';
		$response = wp_remote_get( $url, array( 'headers' => array( 'Accept' => 'application/vnd.github+json' ) ) );
		$body     = json_decode( wp_remote_retrieve_body( $response ), true );
		$release  = null;

		if ( 200 === wp_remote_retrieve_response_code( $response ) && isset( $body['tag_name'], $body['zipball_url'] ) ) {
			$release = array(
				'version' => ltrim( $body['tag_name'], 'v' ),
				'package' => $body['zipball_url'],
				'url'     => $body['html_url'],
				'notes'   => (string) $body['body'],
				'date'    => $body['published_at'],
			);
		}

		// A failed request is cached too, for a shorter time, as an empty string.
		$cached     = $release ?? '';
		$expiration = $release ? 6 * HOUR_IN_SECONDS : HOUR_IN_SECONDS;

		set_site_transient( self::CACHE_KEY, $cached, $expiration );

		return $release;
	}

	/**
	 * GitHub repository (owner/name), read from the Update URI header.
	 */
	private function repository(): string {
		$update_uri = get_file_data( CINQ_WP_THEME_UPDATES_FILE, array( 'update_uri' => 'Update URI' ) )['update_uri'];

		return trim( (string) wp_parse_url( $update_uri, PHP_URL_PATH ), '/' );
	}

	/**
	 * Plugin slug, i.e. its folder name.
	 */
	private function slug(): string {
		return dirname( $this->plugin_file );
	}
}
