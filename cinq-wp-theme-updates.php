<?php
/**
 * Plugin Name: CINQ Theme Updates
 * Plugin URI: https://github.com/agencecinq/cinq-wp-theme-updates
 * Description: Installs theme updates from private GitHub releases. Settings screen, no front-end markup.
 * Version: 1.2.0
 * Author: CINQ
 * Author URI: https://agencecinq.com/
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Text Domain: cinq-wp-theme-updates
 *
 * @package CinqWpThemeUpdates
 */

defined( 'ABSPATH' ) || exit;

define( 'CINQ_WP_THEME_UPDATES_VERSION', '1.2.0' );
define( 'CINQ_WP_THEME_UPDATES_FILE', __FILE__ );
define( 'CINQ_WP_THEME_UPDATES_PATH', plugin_dir_path( __FILE__ ) );

require_once CINQ_WP_THEME_UPDATES_PATH . 'includes/Plugin.php';
require_once CINQ_WP_THEME_UPDATES_PATH . 'includes/Config.php';
require_once CINQ_WP_THEME_UPDATES_PATH . 'includes/GitHubClient.php';
require_once CINQ_WP_THEME_UPDATES_PATH . 'includes/ThemeUpdater.php';
require_once CINQ_WP_THEME_UPDATES_PATH . 'includes/Settings.php';

( new CinqWpThemeUpdates\Plugin() )->run();
