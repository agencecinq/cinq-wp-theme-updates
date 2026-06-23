<?php
/**
 * GitHub API client.
 *
 * @package CinqThemeUpdateChecker
 */

namespace CinqThemeUpdateChecker;

/**
 * Fetches release metadata from GitHub.
 */
class GitHubClient {

	private const CACHE_KEY = 'cinq_theme_update_checker_release';
	private const CACHE_TTL = 12 * HOUR_IN_SECONDS;

	/**
	 * Constructor.
	 *
	 * @param Config $config Plugin configuration.
	 */
	public function __construct(
		private Config $config
	) {}

	/**
	 * Fetch the latest release for the configured repository.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get_latest_release(): ?array {
		if ( ! $this->config->is_configured() ) {
			return null;
		}

		$cache_key = self::CACHE_KEY . '_' . md5( $this->config->get_repository() );
		$cached    = get_site_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$repository = $this->config->get_repository();
		$response   = wp_remote_get(
			'https://api.github.com/repos/' . $repository . '/releases/latest',
			array(
				'timeout' => 15,
				'headers' => $this->get_headers(),
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $status_code ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) || empty( $body['tag_name'] ) ) {
			return null;
		}

		$release = array(
			'tag_name'    => (string) $body['tag_name'],
			'version'     => $this->normalize_version( (string) $body['tag_name'] ),
			'url'         => (string) ( $body['html_url'] ?? '' ),
			'changelog'   => (string) ( $body['body'] ?? '' ),
			'package'     => $this->resolve_package_url( $body ),
			'published_at'=> (string) ( $body['published_at'] ?? '' ),
		);

		set_site_transient( $cache_key, $release, self::CACHE_TTL );

		return $release;
	}

	/**
	 * Clear cached release data.
	 */
	public function clear_cache(): void {
		$cache_key = self::CACHE_KEY . '_' . md5( $this->config->get_repository() );
		delete_site_transient( $cache_key );
	}

	/**
	 * Request headers for GitHub API calls.
	 *
	 * @return array<string, string>
	 */
	public function get_headers(): array {
		return array(
			'Authorization'    => 'Bearer ' . $this->config->get_token(),
			'Accept'           => 'application/vnd.github+json',
			'X-GitHub-Api-Version' => '2022-11-28',
			'User-Agent'       => 'CINQ-Theme-Update-Checker/' . CINQ_THEME_UPDATE_CHECKER_VERSION,
		);
	}

	/**
	 * Strip the configured tag prefix from a release tag.
	 *
	 * @param string $tag_name GitHub release tag.
	 */
	private function normalize_version( string $tag_name ): string {
		$prefix = $this->config->get_tag_prefix();

		if ( '' !== $prefix && str_starts_with( $tag_name, $prefix ) ) {
			return substr( $tag_name, strlen( $prefix ) );
		}

		return $tag_name;
	}

	/**
	 * Resolve the ZIP download URL from release metadata.
	 *
	 * @param array<string, mixed> $release GitHub release payload.
	 */
	private function resolve_package_url( array $release ): string {
		$zip_filename = $this->config->get_zip_filename();
		$assets       = $release['assets'] ?? array();

		if ( is_array( $assets ) ) {
			foreach ( $assets as $asset ) {
				if ( ! is_array( $asset ) ) {
					continue;
				}

				if ( ( $asset['name'] ?? '' ) === $zip_filename && ! empty( $asset['browser_download_url'] ) ) {
					return (string) $asset['browser_download_url'];
				}
			}

			foreach ( $assets as $asset ) {
				if ( ! is_array( $asset ) ) {
					continue;
				}

				$name = (string) ( $asset['name'] ?? '' );

				if ( str_ends_with( $name, '.zip' ) && ! empty( $asset['browser_download_url'] ) ) {
					return (string) $asset['browser_download_url'];
				}
			}
		}

		$repository = $this->config->get_repository();
		$tag_name   = (string) ( $release['tag_name'] ?? '' );

		return sprintf(
			'https://github.com/%s/releases/download/%s/%s',
			$repository,
			rawurlencode( $tag_name ),
			rawurlencode( $zip_filename )
		);
	}
}
