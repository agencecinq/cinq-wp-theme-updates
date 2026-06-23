<?php
/**
 * Plugin bootstrap.
 *
 * @package CinqThemeUpdateChecker
 */

namespace CinqThemeUpdateChecker;

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
	 */
	private function maybe_clear_caches( Config $config, GitHubClient $github ): void {
		$stored_version = get_option( 'cinq_theme_update_checker_version', '' );

		if ( CINQ_THEME_UPDATE_CHECKER_VERSION === $stored_version ) {
			return;
		}

		$github->clear_cache();
		delete_site_transient( 'update_themes' );
		update_option( 'cinq_theme_update_checker_version', CINQ_THEME_UPDATE_CHECKER_VERSION, false );
	}
}
