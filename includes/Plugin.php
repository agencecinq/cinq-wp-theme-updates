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

		( new ThemeUpdater( $config, $github ) )->register();
		( new PluginUpdater( plugin_basename( CINQ_WP_THEME_UPDATES_FILE ) ) )->register();
		( new Settings( $config ) )->register();
	}
}
