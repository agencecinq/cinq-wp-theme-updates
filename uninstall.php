<?php
/**
 * Removes everything the plugin stored.
 *
 * Runs when the plugin is deleted from the Plugins screen. Deactivating it,
 * which comes first, already forgot the cached releases.
 *
 * @package CinqWpThemeUpdates
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'cinq_wp_theme_updates_settings' );
