<?php
/**
 * Plugin Name: CINQ Theme Update Checker
 * Plugin URI: https://agencecinq.com/
 * Description: Check for theme updates from private GitHub releases.
 * Version: 1.0.1
 * Author: CINQ
 * Author URI: https://agencecinq.com/
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Text Domain: cinq-theme-update-checker
 *
 * @package CinqThemeUpdateChecker
 */

defined( 'ABSPATH' ) || exit;

define( 'CINQ_THEME_UPDATE_CHECKER_VERSION', '1.0.1' );
define( 'CINQ_THEME_UPDATE_CHECKER_FILE', __FILE__ );
define( 'CINQ_THEME_UPDATE_CHECKER_PATH', plugin_dir_path( __FILE__ ) );

require_once CINQ_THEME_UPDATE_CHECKER_PATH . 'includes/Plugin.php';
require_once CINQ_THEME_UPDATE_CHECKER_PATH . 'includes/Config.php';
require_once CINQ_THEME_UPDATE_CHECKER_PATH . 'includes/GitHubClient.php';
require_once CINQ_THEME_UPDATE_CHECKER_PATH . 'includes/ThemeUpdater.php';
require_once CINQ_THEME_UPDATE_CHECKER_PATH . 'includes/Settings.php';

( new CinqThemeUpdateChecker\Plugin() )->run();
