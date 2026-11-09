<?php
/**
 * Editor and front assets.
 *
 * @package DynamicFilter
 */

namespace DynamicFilter;

defined( 'ABSPATH' ) || exit;

/**
 * Register and enqueue scripts / styles.
 */
class Assets {

	const EDITOR_HANDLE = 'dynamic-filter-editor';
	const FRONT_HANDLE  = 'dynamic-filter-front';

	/**
	 * Hook enqueue.
	 */
	public static function register() {
		add_action( 'init', array( __CLASS__, 'register_editor_script' ), 5 );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_front' ) );
		add_filter( 'render_block_dynamic-filter/query', array( __CLASS__, 'mark_front_needed' ), 5, 1 );
	}

	/**
	 * Register editor bundle early so block.json can reference the handle.
	 */
	public static function register_editor_script() {
		$asset_file = DDF_PATH . 'build/index.asset.php';
		$script     = DDF_PATH . 'build/index.js';

		if ( ! file_exists( $script ) ) {
			return;
		}

		$asset = file_exists( $asset_file )
			? include $asset_file
			: array(
				'dependencies' => array(
					'wp-blocks',
					'wp-block-editor',
					'wp-components',
					'wp-data',
					'wp-element',
					'wp-i18n',
					'wp-api-fetch',
				),
				'version'      => DDF_VERSION,
			);

		wp_register_script(
			self::EDITOR_HANDLE,
			DDF_URL . 'build/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		$style = DDF_PATH . 'build/index.css';
		if ( file_exists( $style ) ) {
			wp_register_style(
				self::EDITOR_HANDLE,
				DDF_URL . 'build/index.css',
				array(),
				$asset['version']
			);
		}

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( self::EDITOR_HANDLE, 'dynamic-filter' );
		}
	}

	/**
	 * Enqueue editor assets + localize.
	 */
	public static function enqueue_editor() {
		if ( ! wp_script_is( self::EDITOR_HANDLE, 'registered' ) ) {
			self::register_editor_script();
		}
		if ( ! wp_script_is( self::EDITOR_HANDLE, 'registered' ) ) {
			return;
		}

		wp_enqueue_script( self::EDITOR_HANDLE );
		if ( wp_style_is( self::EDITOR_HANDLE, 'registered' ) ) {
			wp_enqueue_style( self::EDITOR_HANDLE );
		}

		$post_id = get_the_ID();
		if ( ! $post_id && isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$post_id = (int) $_GET['post']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		wp_localize_script(
			self::EDITOR_HANDLE,
			'ddfSettings',
			array(
				'restUrl'   => esc_url_raw( rest_url( Rest::NAMESPACE ) ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'postId'    => (int) $post_id,
				'pluginUrl' => DDF_URL,
			)
		);
	}

	/**
	 * Enqueue front assets when our block renders (covers FSE templates).
	 *
	 * @param string $block_content Block HTML.
	 * @return string
	 */
	public static function mark_front_needed( $block_content ) {
		self::enqueue_front();
		return $block_content;
	}

	/**
	 * Enqueue front assets if the block is present in content.
	 */
	public static function maybe_enqueue_front() {
		if ( is_singular() && has_block( 'dynamic-filter/query' ) ) {
			self::enqueue_front();
		}
	}

	/**
	 * Register + enqueue front vanilla assets once.
	 */
	public static function enqueue_front() {
		if ( wp_script_is( self::FRONT_HANDLE, 'enqueued' ) ) {
			return;
		}

		$js  = DDF_PATH . 'assets/front/filter.js';
		$css = DDF_PATH . 'assets/front/filter.css';

		if ( ! file_exists( $js ) ) {
			return;
		}

		wp_register_script(
			self::FRONT_HANDLE,
			DDF_URL . 'assets/front/filter.js',
			array(),
			DDF_VERSION,
			true
		);

		if ( file_exists( $css ) ) {
			wp_register_style(
				self::FRONT_HANDLE,
				DDF_URL . 'assets/front/filter.css',
				array(),
				DDF_VERSION
			);
			wp_enqueue_style( self::FRONT_HANDLE );
		}

		wp_localize_script(
			self::FRONT_HANDLE,
			'ddfSettings',
			array(
				'restUrl' => esc_url_raw( rest_url( Rest::NAMESPACE . '/query' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			)
		);

		wp_enqueue_script( self::FRONT_HANDLE );
	}
}
