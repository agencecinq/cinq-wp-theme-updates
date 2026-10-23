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
		$config        = new Config();
		$github        = new GitHubClient( $config );
		$theme_updater = new ThemeUpdater( $config, $github );

		$theme_updater->register();
		( new PluginUpdater( plugin_basename( CINQ_WP_THEME_UPDATES_FILE ) ) )->register();
		( new Settings( $config ) )->register();

		// Temporary data goes on deactivation; uninstall.php removes the settings.
		register_deactivation_hook( CINQ_WP_THEME_UPDATES_FILE, array( $theme_updater, 'forget' ) );
		register_deactivation_hook( CINQ_WP_THEME_UPDATES_FILE, array( PluginUpdater::class, 'forget' ) );
	}
}
