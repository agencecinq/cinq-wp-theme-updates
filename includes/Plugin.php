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

		add_action( 'init', array( $this, 'load_textdomain' ) );

		$theme_updater->register();
		( new PluginUpdater( plugin_basename( CINQ_WP_THEME_UPDATES_FILE ) ) )->register();
		( new Settings( $config ) )->register();

		// Temporary data goes on deactivation; uninstall.php removes the settings.
		register_deactivation_hook( CINQ_WP_THEME_UPDATES_FILE, array( $theme_updater, 'forget' ) );
		register_deactivation_hook( CINQ_WP_THEME_UPDATES_FILE, array( PluginUpdater::class, 'forget' ) );
	}

	/**
	 * Loads the translations of languages/: WordPress only finds the ones
	 * of plugins hosted on WordPress.org by itself.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'cinq-wp-theme-updates', false, dirname( plugin_basename( CINQ_WP_THEME_UPDATES_FILE ) ) . '/languages' );
	}
}
