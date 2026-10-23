<?php
/**
 * Plugin configuration.
 *
 * @package CinqWpThemeUpdates
 */

namespace CinqWpThemeUpdates;

/**
 * Resolves theme slug, GitHub repo, token and related settings.
 */
class Config {

	public const OPTION_KEY = 'cinq_wp_theme_updates_settings';

	/**
	 * Theme slug to monitor for updates.
	 */
	public function get_theme_slug(): string {
		$slug = $this->get_setting( 'theme_slug' );

		if ( '' === $slug ) {
			$slug = get_stylesheet();
		}

		/**
		 * Filters the slug of the theme to check for updates.
		 *
		 * @since 1.1.0
		 *
		 * @param string $slug Theme slug. Default the setting, or the active theme.
		 */
		return (string) apply_filters( 'cinq_wp_theme_updates_slug', $slug );
	}

	/**
	 * GitHub repository in owner/repo format.
	 */
	public function get_repository(): string {
		$repository = $this->get_setting( 'repository' );

		if ( '' === $repository ) {
			$repository = $this->parse_repository_from_theme_uri( $this->get_theme()->get( 'ThemeURI' ) );
		}

		/**
		 * Filters the GitHub repository the theme updates come from.
		 *
		 * @since 1.1.0
		 *
		 * @param string $repository Repository as owner/repo. Default the setting, or the repository of the theme's Theme URI.
		 */
		return (string) apply_filters( 'cinq_wp_theme_updates_repo', $repository );
	}

	/**
	 * GitHub personal access token.
	 */
	public function get_token(): string {
		$token = $this->get_setting( 'github_token' );

		/**
		 * Filters the GitHub personal access token sent with the requests.
		 *
		 * @since 1.1.0
		 *
		 * @param string $token Token. Default the setting.
		 */
		return (string) apply_filters( 'cinq_wp_theme_updates_token', $token );
	}

	/**
	 * Prefix stripped from release tags before version comparison.
	 */
	public function get_tag_prefix(): string {
		$prefix = $this->get_setting( 'tag_prefix' );

		if ( '' === $prefix ) {
			$prefix = 'v';
		}

		/**
		 * Filters the prefix removed from release tags before comparing versions.
		 *
		 * @since 1.1.0
		 *
		 * @param string $prefix Tag prefix. Default the setting, or 'v'.
		 */
		return (string) apply_filters( 'cinq_wp_theme_updates_tag_prefix', $prefix );
	}

	/**
	 * Release asset filename, defaults to {slug}.zip.
	 */
	public function get_zip_filename(): string {
		$filename = $this->get_setting( 'zip_filename' );

		if ( '' === $filename ) {
			$filename = $this->get_theme_slug() . '.zip';
		}

		/**
		 * Filters the name of the release asset to install.
		 *
		 * @since 1.1.0
		 *
		 * @param string $filename Asset file name. Default the setting, or the theme slug followed by .zip.
		 */
		return (string) apply_filters( 'cinq_wp_theme_updates_zip_filename', $filename );
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

		// Trailing slash: owner/theme must not match owner/theme-blocks.
		return str_starts_with( $package, 'https://api.github.com/repos/' . $repository . '/' );
	}

	/**
	 * Sanitize the settings submitted from the settings page, as the
	 * sanitize_callback of the setting. An empty token keeps the saved one.
	 *
	 * @param mixed $input Submitted settings, unslashed by options.php.
	 * @return array<string, string>
	 */
	public function sanitize_settings( $input ): array {
		$input = is_array( $input ) ? $input : array();
		$token = $this->sanitize_token( (string) ( $input['github_token'] ?? '' ) );

		if ( '' === $token ) {
			$token = $this->get_setting( 'github_token' );
		}

		return array(
			'theme_slug'   => sanitize_key( $input['theme_slug'] ?? '' ),
			'repository'   => $this->sanitize_repository( (string) ( $input['repository'] ?? '' ) ),
			'github_token' => $token,
			'tag_prefix'   => sanitize_text_field( $input['tag_prefix'] ?? '' ),
			'zip_filename' => sanitize_file_name( $input['zip_filename'] ?? '' ),
		);
	}

	/**
	 * Read a single saved setting, empty when unset.
	 *
	 * @param string $key Setting key.
	 */
	public function get_setting( string $key ): string {
		$settings = get_option( self::OPTION_KEY, array() );

		return is_array( $settings ) ? (string) ( $settings[ $key ] ?? '' ) : '';
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
	 * Normalize a GitHub token before storage.
	 *
	 * @param string $token Raw token value.
	 */
	private function sanitize_token( string $token ): string {
		return preg_replace( '/\s+/', '', $token ) ?? '';
	}
}
