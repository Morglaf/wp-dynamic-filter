<?php
/**
 * Plugin Name:       Dynamic Filter
 * Description:       Filtre AJAX des Query Loop Gutenberg par taxonomie (bloc conteneur + REST HTML).
 * Version:           1.1.0
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Author:            Morglaf
 * Text Domain:       dynamic-filter
 * License:           GPL-2.0-or-later
 *
 * @package DynamicFilter
 */

defined( 'ABSPATH' ) || exit;

define( 'DDF_VERSION', '1.1.0' );
define( 'DDF_FILE', __FILE__ );
define( 'DDF_PATH', plugin_dir_path( __FILE__ ) );
define( 'DDF_URL', plugin_dir_url( __FILE__ ) );

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
 * Admin notice: WordPress too old.
 */
function ddf_notice_wp_version() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-error"><p>';
	echo esc_html__( 'Dynamic Filter requires WordPress 6.7 or newer.', 'dynamic-filter' );
	echo '</p></div>';
}
