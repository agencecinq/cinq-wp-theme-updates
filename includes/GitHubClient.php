<?php
/**
 * GitHub API client.
 *
 * @package CinqWpThemeUpdates
 */

namespace CinqWpThemeUpdates;

/**
 * Fetches release metadata from GitHub.
 */
class GitHubClient {

	private const CACHE_KEY = 'cinq_wp_theme_updates_release_v2';
	private const ERROR_KEY = 'cinq_wp_theme_updates_last_error';
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
		$result = $this->fetch_latest_release();

		return $result['release'];
	}

	/**
	 * Fetch the latest release and capture API errors.
	 *
	 * @return array{release: array<string, mixed>|null, error: string|null}
	 */
	public function fetch_latest_release(): array {
		if ( ! $this->config->is_configured() ) {
			$error = __( 'Repository and GitHub token are required.', 'cinq-wp-theme-updates' );

			$this->store_error( $error );

			return array(
				'release' => null,
				'error'   => $error,
			);
		}

		$cache_key = self::CACHE_KEY . '_' . md5( $this->config->get_repository() );
		$cached    = get_site_transient( $cache_key );

		if ( is_array( $cached ) ) {
			$this->store_error( null );

			return array(
				'release' => $cached,
				'error'   => null,
			);
		}

		$repository = $this->config->get_repository();
		$response   = $this->request(
			'https://api.github.com/repos/' . $repository . '/releases/latest'
		);

		if ( null !== $response['error'] && 404 === $response['status_code'] ) {
			$response = $this->request(
				'https://api.github.com/repos/' . $repository . '/releases?per_page=1'
			);

			if ( null === $response['error'] && is_array( $response['body'] ) && ! empty( $response['body'][0] ) ) {
				$response['body'] = $response['body'][0];
			} else {
				$response['body'] = null;
			}
		}

		if ( null !== $response['error'] ) {
			$this->store_error( $response['error'] );

			return array(
				'release' => null,
				'error'   => $response['error'],
			);
		}

		$body = $response['body'];

		if ( ! is_array( $body ) || empty( $body['tag_name'] ) ) {
			$error = __( 'GitHub returned an unexpected release response.', 'cinq-wp-theme-updates' );

			$this->store_error( $error );

			return array(
				'release' => null,
				'error'   => $error,
			);
		}

		$release = array(
			'tag_name'     => (string) $body['tag_name'],
			'version'      => $this->normalize_version( (string) $body['tag_name'] ),
			'url'          => (string) ( $body['html_url'] ?? '' ),
			'changelog'    => (string) ( $body['body'] ?? '' ),
			'package'      => $this->resolve_package_url( $body ),
			'asset_id'     => $this->resolve_asset_id( $body ),
			'published_at' => (string) ( $body['published_at'] ?? '' ),
		);

		set_site_transient( $cache_key, $release, self::CACHE_TTL );
		$this->store_error( null );

		return array(
			'release' => $release,
			'error'   => null,
		);
	}

	/**
	 * Return the last API error message, if any.
	 */
	public function get_last_error(): ?string {
		$error = get_site_transient( self::ERROR_KEY );

		return is_string( $error ) && '' !== $error ? $error : null;
	}

	/**
	 * Clear cached release data.
	 */
	public function clear_cache(): void {
		$cache_key = self::CACHE_KEY . '_' . md5( $this->config->get_repository() );
		delete_site_transient( $cache_key );
		delete_site_transient( self::ERROR_KEY );
	}

	/**
	 * Request headers for GitHub API calls.
	 *
	 * @return array<string, string>
	 */
	public function get_headers(): array {
		return array(
			'Authorization'        => $this->get_authorization_header(),
			'Accept'               => 'application/vnd.github+json',
			'X-GitHub-Api-Version' => '2022-11-28',
			'User-Agent'           => 'CINQ-Theme-Updates/' . CINQ_WP_THEME_UPDATES_VERSION,
		);
	}

	/**
	 * Build the Authorization header for GitHub personal access tokens.
	 */
	public function get_authorization_header(): string {
		$token = $this->config->get_token();

		if ( str_starts_with( $token, 'ghp_' ) || str_starts_with( $token, 'gho_' ) ) {
			return 'token ' . $token;
		}

		return 'Bearer ' . $token;
	}

	/**
	 * Perform a GitHub API request.
	 *
	 * @param string $url Request URL.
	 * @return array{status_code: int, body: mixed, error: string|null}
	 */
	private function request( string $url ): array {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'   => 15,
				'headers'   => $this->get_headers(),
				'sslverify' => (bool) apply_filters( 'cinq_wp_theme_updates_sslverify', true ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'status_code' => 0,
				'body'        => null,
				'error'       => $response->get_error_message(),
			);
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $status_code ) {
			return array(
				'status_code' => $status_code,
				'body'        => $body,
				'error'       => $this->format_api_error( $status_code, $body ),
			);
		}

		return array(
			'status_code' => $status_code,
			'body'        => $body,
			'error'       => null,
		);
	}

	/**
	 * Format a GitHub API error for display.
	 *
	 * @param int   $status_code HTTP status code.
	 * @param mixed $body        Response body.
	 */
	private function format_api_error( int $status_code, mixed $body ): string {
		$message = '';

		if ( is_array( $body ) && ! empty( $body['message'] ) ) {
			$message = (string) $body['message'];
		}

		if ( '' === $message ) {
			$message = __( 'GitHub API request failed.', 'cinq-wp-theme-updates' );
		}

		if ( 404 === $status_code ) {
			$message .= ' ' . __( 'Check that the repository exists and the token can access it.', 'cinq-wp-theme-updates' );
		}

		if ( 401 === $status_code ) {
			$message .= ' ' . __( 'The token is invalid or expired.', 'cinq-wp-theme-updates' );
		}

		if ( 403 === $status_code && is_array( $body ) && str_contains( (string) ( $body['message'] ?? '' ), 'SAML' ) ) {
			$message .= ' ' . __( 'Authorize the token for your GitHub organization SSO.', 'cinq-wp-theme-updates' );
		}

		return sprintf(
			/* translators: 1: HTTP status code, 2: error message */
			__( 'GitHub API error %1$d: %2$s', 'cinq-wp-theme-updates' ),
			$status_code,
			$message
		);
	}

	/**
	 * Persist the latest API error for the settings screen.
	 *
	 * @param string|null $error Error message.
	 */
	private function store_error( ?string $error ): void {
		if ( null === $error || '' === $error ) {
			delete_site_transient( self::ERROR_KEY );
			return;
		}

		set_site_transient( self::ERROR_KEY, $error, self::CACHE_TTL );
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
	 * Download a release asset to a temporary file.
	 *
	 * @param string $package Package or asset API URL.
	 * @return string|\WP_Error Path to the downloaded archive.
	 */
	public function download_release_asset( string $package ) {
		$download_url = $this->resolve_download_url( $package );

		$response = wp_remote_get(
			$download_url,
			array(
				'timeout'     => 300,
				'redirection' => 5,
				'headers'     => array(
					'Authorization'        => $this->get_authorization_header(),
					'Accept'               => 'application/octet-stream',
					'X-GitHub-Api-Version' => '2022-11-28',
					'User-Agent'           => 'CINQ-Theme-Updates/' . CINQ_WP_THEME_UPDATES_VERSION,
				),
				'sslverify'   => (bool) apply_filters( 'cinq_wp_theme_updates_sslverify', true ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $status_code ) {
			return new \WP_Error(
				'cinq_wp_theme_updates_download_failed',
				sprintf(
					/* translators: 1: HTTP status code, 2: download URL */
					__( 'Theme download failed with HTTP status %1$d (%2$s).', 'cinq-wp-theme-updates' ),
					$status_code,
					$download_url
				)
			);
		}

		$filename = wp_tempnam( $download_url );

		if ( ! $filename ) {
			return new \WP_Error(
				'cinq_wp_theme_updates_temp_file',
				__( 'Could not create a temporary file for the theme download.', 'cinq-wp-theme-updates' )
			);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Temporary file created by wp_tempnam, outside the WordPress filesystem.
		$written = file_put_contents( $filename, wp_remote_retrieve_body( $response ) );

		if ( false === $written ) {
			return new \WP_Error(
				'cinq_wp_theme_updates_write_failed',
				__( 'Could not write the downloaded theme archive.', 'cinq-wp-theme-updates' )
			);
		}

		return $filename;
	}

	/**
	 * Resolve the best download URL for a release asset.
	 *
	 * @param string $package Stored package URL.
	 */
	public function resolve_download_url( string $package ): string {
		if ( str_contains( $package, 'api.github.com/repos/' ) && str_contains( $package, '/releases/assets/' ) ) {
			return $package;
		}

		$release = $this->get_latest_release();

		if ( is_array( $release ) && ! empty( $release['asset_id'] ) ) {
			return sprintf(
				'https://api.github.com/repos/%s/releases/assets/%d',
				$this->config->get_repository(),
				(int) $release['asset_id']
			);
		}

		return $package;
	}

	/**
	 * Resolve the ZIP download URL from release metadata.
	 *
	 * @param array<string, mixed> $release GitHub release payload.
	 */
	private function resolve_package_url( array $release ): string {
		$asset_id = $this->resolve_asset_id( $release );

		if ( null !== $asset_id ) {
			return sprintf(
				'https://api.github.com/repos/%s/releases/assets/%d',
				$this->config->get_repository(),
				$asset_id
			);
		}

		$zip_filename = $this->config->get_zip_filename();
		$repository   = $this->config->get_repository();
		$tag_name     = (string) ( $release['tag_name'] ?? '' );

		return sprintf(
			'https://github.com/%s/releases/download/%s/%s',
			$repository,
			rawurlencode( $tag_name ),
			rawurlencode( $zip_filename )
		);
	}

	/**
	 * Find the release asset ID for the configured ZIP filename.
	 *
	 * @param array<string, mixed> $release GitHub release payload.
	 */
	private function resolve_asset_id( array $release ): ?int {
		$zip_filename = $this->config->get_zip_filename();
		$assets       = $release['assets'] ?? array();

		if ( ! is_array( $assets ) ) {
			return null;
		}

		foreach ( $assets as $asset ) {
			if ( ! is_array( $asset ) || empty( $asset['id'] ) ) {
				continue;
			}

			if ( ( $asset['name'] ?? '' ) === $zip_filename ) {
				return (int) $asset['id'];
			}
		}

		foreach ( $assets as $asset ) {
			if ( ! is_array( $asset ) || empty( $asset['id'] ) ) {
				continue;
			}

			$name = (string) ( $asset['name'] ?? '' );

			if ( str_ends_with( $name, '.zip' ) ) {
				return (int) $asset['id'];
			}
		}

		return null;
	}
}
