<?php
/**
 * Plugin configuration.
 *
 * @package CinqThemeUpdateChecker
 */

namespace CinqThemeUpdateChecker;

/**
 * Resolves theme slug, GitHub repo, token and related settings.
 */
class Config {

	public const OPTION_KEY = 'cinq_theme_update_checker_settings';

	/**
	 * Cached settings array.
	 *
	 * @var array<string, string>|null
	 */
	private ?array $settings = null;

	/**
	 * Theme slug to monitor for updates.
	 */
	public function get_theme_slug(): string {
		$slug = $this->get_setting( 'theme_slug' );

		if ( '' !== $slug ) {
			return (string) apply_filters( 'cinq_theme_update_checker_slug', $slug );
		}

		$slug = get_stylesheet();

		return (string) apply_filters( 'cinq_theme_update_checker_slug', $slug );
	}

	/**
	 * GitHub repository in owner/repo format.
	 */
	public function get_repository(): string {
		$repository = $this->get_setting( 'repository' );

		if ( '' !== $repository ) {
			return (string) apply_filters( 'cinq_theme_update_checker_repo', $repository );
		}

		$repository = $this->parse_repository_from_theme_uri( $this->get_theme()->get( 'ThemeURI' ) );

		return (string) apply_filters( 'cinq_theme_update_checker_repo', $repository );
	}

	/**
	 * GitHub personal access token.
	 */
	public function get_token(): string {
		if ( defined( 'CINQ_THEME_UPDATE_GITHUB_TOKEN' ) && '' !== CINQ_THEME_UPDATE_GITHUB_TOKEN ) {
			return (string) apply_filters( 'cinq_theme_update_checker_token', $this->sanitize_token( CINQ_THEME_UPDATE_GITHUB_TOKEN ) );
		}

		return (string) apply_filters( 'cinq_theme_update_checker_token', $this->sanitize_token( $this->get_setting( 'github_token' ) ) );
	}

	/**
	 * Prefix stripped from release tags before version comparison.
	 */
	public function get_tag_prefix(): string {
		$prefix = $this->get_setting( 'tag_prefix' );

		if ( '' === $prefix ) {
			$prefix = 'v';
		}

		return (string) apply_filters( 'cinq_theme_update_checker_tag_prefix', $prefix );
	}

	/**
	 * Release asset filename, defaults to {slug}.zip.
	 */
	public function get_zip_filename(): string {
		$filename = $this->get_setting( 'zip_filename' );

		if ( '' === $filename ) {
			$filename = $this->get_theme_slug() . '.zip';
		}

		return (string) apply_filters( 'cinq_theme_update_checker_zip_filename', $filename );
	}

	/**
	 * Whether required settings are available.
	 */
	public function is_configured(): bool {
		return '' !== $this->get_repository() && '' !== $this->get_token();
	}

	/**
	 * Whether a package URL belongs to the configured GitHub repository.
	 *
	 * @param string $package Download URL.
	 */
	public function is_github_package( string $package ): bool {
		$repository = $this->get_repository();

		if ( '' === $repository ) {
			return false;
		}

		return str_contains( $package, 'github.com/' . $repository );
	}

	/**
	 * Persist plugin settings.
	 *
	 * @param array<string, string> $settings Settings to save.
	 */
	public function save_settings( array $settings ): void {
		update_option(
			self::OPTION_KEY,
			array(
				'theme_slug'    => sanitize_key( $settings['theme_slug'] ?? '' ),
				'repository'    => $this->sanitize_repository( $settings['repository'] ?? '' ),
				'github_token'  => $this->sanitize_token( $settings['github_token'] ?? '' ),
				'tag_prefix'    => sanitize_text_field( $settings['tag_prefix'] ?? 'v' ),
				'zip_filename'  => sanitize_file_name( $settings['zip_filename'] ?? '' ),
			),
			false
		);

		$this->settings = null;
	}

	/**
	 * Return all settings for the admin form.
	 *
	 * @return array<string, string>
	 */
	public function get_settings_for_display(): array {
		$stored_token = $this->get_setting( 'github_token' );

		return array(
			'theme_slug'        => $this->get_setting( 'theme_slug' ),
			'repository'        => $this->get_setting( 'repository' ),
			'github_token'      => $this->has_constant_token() ? '' : $stored_token,
			'has_stored_token'  => ! $this->has_constant_token() && '' !== $stored_token,
			'tag_prefix'        => $this->get_setting( 'tag_prefix' ) ?: 'v',
			'zip_filename'      => $this->get_setting( 'zip_filename' ),
		);
	}

	/**
	 * Whether the token is defined in wp-config.php.
	 */
	public function has_constant_token(): bool {
		return defined( 'CINQ_THEME_UPDATE_GITHUB_TOKEN' ) && '' !== CINQ_THEME_UPDATE_GITHUB_TOKEN;
	}

	/**
	 * Read a single stored setting.
	 */
	private function get_setting( string $key ): string {
		$settings = $this->get_settings();

		return $settings[ $key ] ?? '';
	}

	/**
	 * Load settings from the database.
	 *
	 * @return array<string, string>
	 */
	private function get_settings(): array {
		if ( null === $this->settings ) {
			$settings       = get_option( self::OPTION_KEY, array() );
			$this->settings = is_array( $settings ) ? $settings : array();
		}

		return $this->settings;
	}

	/**
	 * Get the configured theme object.
	 */
	private function get_theme(): \WP_Theme {
		return wp_get_theme( $this->get_theme_slug() );
	}

	/**
	 * Extract owner/repo from a GitHub theme URI.
	 *
	 * @param string $theme_uri Theme URI header value.
	 */
	private function parse_repository_from_theme_uri( string $theme_uri ): string {
		if ( preg_match( '#github\.com/([^/]+)/([^/]+?)(?:\.git)?/?$#i', $theme_uri, $matches ) ) {
			return strtolower( $matches[1] ) . '/' . preg_replace( '/\.git$/', '', $matches[2] );
		}

		return '';
	}

	/**
	 * Normalize repository input.
	 *
	 * @param string $repository Raw repository value.
	 */
	private function sanitize_repository( string $repository ): string {
		$repository = trim( $repository );
		$repository = preg_replace( '#^https?://github\.com/#i', '', $repository ) ?? $repository;
		$repository = trim( $repository, '/' );

		if ( ! preg_match( '#^[a-zA-Z0-9_.-]+/[a-zA-Z0-9_.-]+$#', $repository ) ) {
			return '';
		}

		return $repository;
	}

	/**
	 * Normalize a GitHub token before storage or use.
	 *
	 * @param string $token Raw token value.
	 */
	private function sanitize_token( string $token ): string {
		$token = trim( wp_unslash( $token ) );

		return preg_replace( '/\s+/', '', $token ) ?? '';
	}
}
