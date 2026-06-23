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

		( new ThemeUpdater( $config, new GitHubClient( $config ) ) )->register();
		( new Settings( $config ) )->register();
	}
}
