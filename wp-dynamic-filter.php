<?php
/**
 * Plugin Name:       WP Dynamic Filter
 * Description:       AJAX taxonomy filters for Gutenberg Query Loops (container block + REST HTML).
 * Version:           1.2.0
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Author:            Morglaf
 * Author URI:        https://github.com/Morglaf
 * Plugin URI:        https://github.com/Morglaf/wp-dynamic-filter
 * Text Domain:       wp-dynamic-filter
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package DynamicFilter
 */

defined( 'ABSPATH' ) || exit;

define( 'DDF_VERSION', '1.2.0' );
define( 'DDF_FILE', __FILE__ );
define( 'DDF_PATH', plugin_dir_path( __FILE__ ) );
define( 'DDF_URL', plugin_dir_url( __FILE__ ) );
define( 'DDF_TEXTDOMAIN', 'wp-dynamic-filter' );

/**
 * Bootstrap.
 */
function ddf_bootstrap() {
	global $wp_version;

	if ( version_compare( $wp_version, '6.7', '<' ) ) {
		add_action( 'admin_notices', 'ddf_notice_wp_version' );
		return;
	}

	require_once DDF_PATH . 'includes/class-query-render.php';
	require_once DDF_PATH . 'includes/class-rest.php';
	require_once DDF_PATH . 'includes/class-assets.php';
	require_once DDF_PATH . 'includes/class-filter-block.php';
	require_once DDF_PATH . 'includes/class-plugin.php';

	\DynamicFilter\Plugin::instance()->init();
}
add_action( 'plugins_loaded', 'ddf_bootstrap', 20 );

/**
 * Load translations.
 */
function ddf_load_textdomain() {
	load_plugin_textdomain(
		DDF_TEXTDOMAIN,
		false,
		dirname( plugin_basename( DDF_FILE ) ) . '/languages'
	);
}
add_action( 'init', 'ddf_load_textdomain' );

/**
 * Admin notice: WordPress too old.
 */
function ddf_notice_wp_version() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-error"><p>';
	echo esc_html__( 'WP Dynamic Filter requires WordPress 6.7 or newer.', 'wp-dynamic-filter' );
	echo '</p></div>';
}
