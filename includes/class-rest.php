<?php
/**
 * REST API for filtered Query Loop HTML.
 *
 * @package DynamicFilter
 */

namespace DynamicFilter;

defined( 'ABSPATH' ) || exit;

/**
 * Registers public REST routes.
 */
class Rest {

	const NAMESPACE        = 'wp-dynamic-filter/v1';
	const NAMESPACE_LEGACY = 'dynamic-filter/v1';

	/**
	 * Hook routes.
	 */
	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Route arguments shared by primary and legacy namespaces.
	 *
	 * @return array
	 */
	private static function query_args() {
		return array(
			'post_id'     => array(
				'required'          => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'instance_id' => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'taxonomy'    => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
			),
			'terms'       => array(
				'required'          => false,
				'type'              => 'array',
				'items'             => array( 'type' => 'string' ),
				'default'           => array(),
				'sanitize_callback' => array( __CLASS__, 'sanitize_terms' ),
			),
			'term'        => array(
				'required'          => false,
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => array( __CLASS__, 'sanitize_term_slug' ),
			),
			'paged'       => array(
				'required'          => false,
				'type'              => 'integer',
				'default'           => 1,
				'sanitize_callback' => 'absint',
			),
		);
	}

	/**
	 * Register /query endpoint (current + legacy namespace).
	 */
	public static function register_routes() {
		$route = array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'handle_query' ),
			'permission_callback' => array( __CLASS__, 'verify_nonce' ),
			'args'                => self::query_args(),
		);

		register_rest_route( self::NAMESPACE, '/query', $route );
		register_rest_route( self::NAMESPACE_LEGACY, '/query', $route );
	}

	/**
	 * Sanitize a list of term slugs.
	 *
	 * @param mixed $value Raw value.
	 * @return string[]
	 */
	public static function sanitize_terms( $value ) {
		return Query_Render::normalize_term_slugs( $value );
	}

	/**
	 * Sanitize term slug without the sanitize_title REST arity trap.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_term_slug( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}
		return sanitize_title( $value );
	}

	/**
	 * Require a valid wp_rest nonce.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public static function verify_nonce( \WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce ) {
			$nonce = $request->get_param( '_wpnonce' );
		}
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new \WP_Error(
				'ddf_invalid_nonce',
				__( 'Invalid security token.', 'wp-dynamic-filter' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	/**
	 * Return rendered Query Loop HTML for the requested filter.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_query( \WP_REST_Request $request ) {
		$terms = $request->get_param( 'terms' );
		if ( ! is_array( $terms ) ) {
			$terms = array();
		}
		if ( empty( $terms ) ) {
			$legacy = (string) $request->get_param( 'term' );
			if ( '' !== $legacy ) {
				$terms = Query_Render::normalize_term_slugs( $legacy );
			}
		}

		$html = Query_Render::render_filtered_html(
			(int) $request->get_param( 'post_id' ),
			(string) $request->get_param( 'instance_id' ),
			(string) $request->get_param( 'taxonomy' ),
			$terms,
			(int) $request->get_param( 'paged' )
		);

		if ( is_wp_error( $html ) ) {
			return $html;
		}

		return rest_ensure_response(
			array(
				'html'  => $html,
				'terms' => $terms,
			)
		);
	}
}
