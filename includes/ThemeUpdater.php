<?php
/**
 * WordPress theme update integration.
 *
 * @package CinqThemeUpdateChecker
 */

namespace CinqThemeUpdateChecker;

/**
 * Injects GitHub release data into the WordPress theme update API.
 */
class ThemeUpdater {

	/**
	 * Constructor.
	 *
	 * @param Config       $config Plugin configuration.
	 * @param GitHubClient $github GitHub API client.
	 */
	public function __construct(
		private Config $config,
		private GitHubClient $github
	) {}

	/**
	 * Register WordPress hooks.
	 */
	public function register(): void {
		add_filter( 'pre_set_site_transient_update_themes', array( $this, 'inject_update' ) );
		add_filter( 'themes_api', array( $this, 'theme_details' ), 10, 3 );
		add_filter( 'upgrader_pre_download', array( $this, 'authenticate_download' ), 10, 4 );
	}

	/**
	 * Add remote update data to the themes update transient.
	 *
	 * @param object|false $transient Themes update transient.
	 * @return object|false
	 */
	public function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$theme_slug = $this->config->get_theme_slug();

		if ( '' === $theme_slug || ! isset( $transient->checked[ $theme_slug ] ) ) {
			return $transient;
		}

		$release = $this->github->get_latest_release();

		if ( null === $release || empty( $release['version'] ) || empty( $release['package'] ) ) {
			return $transient;
		}

		$local_version = (string) $transient->checked[ $theme_slug ];

		if ( version_compare( $release['version'], $local_version, '<=' ) ) {
			return $transient;
		}

		$transient->response[ $theme_slug ] = array(
			'theme'       => $theme_slug,
			'new_version' => $release['version'],
			'url'         => $release['url'],
			'package'     => $release['package'],
		);

		return $transient;
	}

	/**
	 * Provide changelog/details in the theme thickbox.
	 *
	 * @param false|object|array $result API result.
	 * @param string             $action Requested action.
	 * @param object             $args   Request arguments.
	 * @return false|object|array
	 */
	public function theme_details( $result, string $action, $args ) {
		if ( 'theme_information' !== $action || ! is_object( $args ) ) {
			return $result;
		}

		$theme_slug = $this->config->get_theme_slug();

		if ( ( $args->slug ?? '' ) !== $theme_slug ) {
			return $result;
		}

		$release = $this->github->get_latest_release();
		$theme   = wp_get_theme( $theme_slug );

		if ( null === $release ) {
			return $result;
		}

		return (object) array(
			'name'          => $theme->get( 'Name' ),
			'slug'          => $theme_slug,
			'version'       => $release['version'],
			'author'        => $theme->get( 'Author' ),
			'homepage'      => $release['url'] ?: $theme->get( 'ThemeURI' ),
			'download_link' => $release['package'],
			'sections'      => array(
				'description' => $theme->get( 'Description' ),
				'changelog'   => $release['changelog'] ?: __( 'No changelog provided for this release.', 'cinq-theme-update-checker' ),
			),
			'last_updated'  => $release['published_at'],
		);
	}

	/**
	 * Download private GitHub release assets with authentication.
	 *
	 * @param bool|\WP_Error $reply      Download response.
	 * @param string         $package    Package URL.
	 * @param \WP_Upgrader   $upgrader   Upgrader instance.
	 * @param array<mixed>   $hook_extra Extra arguments.
	 * @return bool|\WP_Error|string
	 */
	public function authenticate_download( $reply, string $package, $upgrader, array $hook_extra ) {
		if ( false !== $reply || ! $this->config->is_github_package( $package ) ) {
			return $reply;
		}

		$token = $this->config->get_token();

		if ( '' === $token ) {
			return new \WP_Error(
				'cinq_theme_update_checker_missing_token',
				__( 'A GitHub token is required to download theme updates.', 'cinq-theme-update-checker' )
			);
		}

		$response = wp_remote_get(
			$package,
			array(
				'timeout'  => 300,
				'headers'  => array(
					'Authorization' => 'Bearer ' . $token,
					'Accept'        => 'application/octet-stream',
					'User-Agent'    => 'CINQ-Theme-Update-Checker/' . CINQ_THEME_UPDATE_CHECKER_VERSION,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $status_code ) {
			return new \WP_Error(
				'cinq_theme_update_checker_download_failed',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'Theme download failed with HTTP status %d.', 'cinq-theme-update-checker' ),
					$status_code
				)
			);
		}

		$filename = wp_tempnam( $package );

		if ( ! $filename ) {
			return new \WP_Error(
				'cinq_theme_update_checker_temp_file',
				__( 'Could not create a temporary file for the theme download.', 'cinq-theme-update-checker' )
			);
		}

		$written = file_put_contents( $filename, wp_remote_retrieve_body( $response ) );

		if ( false === $written ) {
			return new \WP_Error(
				'cinq_theme_update_checker_write_failed',
				__( 'Could not write the downloaded theme archive.', 'cinq-theme-update-checker' )
			);
		}

		return $filename;
	}
}
