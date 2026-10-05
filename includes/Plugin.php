<?php
/**
 * Plugin bootstrap.
 *
 * @package CinqWpThemeUpdates
 */

namespace CinqWpThemeUpdates;

/**
 * Main plugin class.
 */
class Plugin {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function run(): void {
		$config = new Config();
		$github = new GitHubClient( $config );

		$this->maybe_clear_caches( $config, $github );

		( new ThemeUpdater( $config, $github ) )->register();
		( new Settings( $config ) )->register();
	}

	/**
	 * Clear update caches after a plugin upgrade.
	 *
	 * @param Config       $config Plugin configuration.
	 * @param GitHubClient $github GitHub API client.
	 * @return void
	 */
	private function maybe_clear_caches( Config $config, GitHubClient $github ): void {
		$stored_version = get_option( 'cinq_wp_theme_updates_version', '' );

		if ( CINQ_WP_THEME_UPDATES_VERSION === $stored_version ) {
			return;
		}

		$github->clear_cache();
		delete_site_transient( 'update_themes' );
		update_option( 'cinq_wp_theme_updates_version', CINQ_WP_THEME_UPDATES_VERSION, false );
	}
}
