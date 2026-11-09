<?php
/**
 * Locate filter block instances and re-render Query Loop HTML.
 *
 * @package DynamicFilter
 */

namespace DynamicFilter;

defined( 'ABSPATH' ) || exit;

/**
 * Parses post/template content and renders a filtered core/query.
 */
class Query_Render {

	/**
	 * Normalize term input (string, CSV, or array) into unique sanitized slugs.
	 *
	 * @param mixed $term_input Raw term(s).
	 * @return string[]
	 */
	public static function normalize_term_slugs( $term_input ) {
		$raw = array();

		if ( is_array( $term_input ) ) {
			$raw = $term_input;
		} elseif ( is_string( $term_input ) && '' !== $term_input ) {
			$raw = false !== strpos( $term_input, ',' )
				? explode( ',', $term_input )
				: array( $term_input );
		}

		$slugs = array();
		foreach ( $raw as $item ) {
			if ( ! is_string( $item ) && ! is_numeric( $item ) ) {
				continue;
			}
			$slug = sanitize_title( (string) $item );
			if ( '' !== $slug ) {
				$slugs[] = $slug;
			}
		}

		return array_values( array_unique( $slugs ) );
	}

	/**
	 * Render filtered Query Loop HTML for a filter instance.
	 *
	 * @param int          $post_id     Source post / template ID.
	 * @param string       $instance_id Filter block instanceId.
	 * @param string       $taxonomy    Taxonomy slug.
	 * @param string|array $term_input  Term slug(s); empty = all.
	 * @param int          $paged       Page number.
	 * @return string|\WP_Error
	 */
	public static function render_filtered_html( $post_id, $instance_id, $taxonomy, $term_input = '', $paged = 1 ) {
		$post_id     = (int) $post_id;
		$instance_id = (string) $instance_id;
		$taxonomy    = sanitize_key( $taxonomy );
		$term_slugs  = self::normalize_term_slugs( $term_input );
		$paged       = max( 1, (int) $paged );

		if ( $post_id <= 0 || '' === $instance_id ) {
			return new \WP_Error( 'ddf_invalid_args', __( 'Invalid filter request.', 'wp-dynamic-filter' ), array( 'status' => 400 ) );
		}

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return new \WP_Error( 'ddf_invalid_taxonomy', __( 'Taxonomy not found.', 'wp-dynamic-filter' ), array( 'status' => 400 ) );
		}

		$tax_obj = get_taxonomy( $taxonomy );
		if ( ! $tax_obj || empty( $tax_obj->public ) ) {
			return new \WP_Error( 'ddf_private_taxonomy', __( 'Taxonomy is not public.', 'wp-dynamic-filter' ), array( 'status' => 403 ) );
		}

		$term_ids = array();
		foreach ( $term_slugs as $slug ) {
			$term = get_term_by( 'slug', $slug, $taxonomy );
			if ( ! $term || is_wp_error( $term ) ) {
				return new \WP_Error( 'ddf_invalid_term', __( 'Term not found.', 'wp-dynamic-filter' ), array( 'status' => 404 ) );
			}
			$term_ids[] = (int) $term->term_id;
		}

		$content = self::get_source_content( $post_id );
		if ( is_wp_error( $content ) ) {
			return $content;
		}

		$blocks = parse_blocks( $content );
		$found  = self::find_filter_block( $blocks, $instance_id );

		if ( null === $found ) {
			return new \WP_Error( 'ddf_block_not_found', __( 'Filter block instance not found.', 'wp-dynamic-filter' ), array( 'status' => 404 ) );
		}

		$query_block = self::find_query_block( $found['innerBlocks'] );
		if ( null === $query_block ) {
			return new \WP_Error( 'ddf_query_missing', __( 'No Query Loop found inside the filter block.', 'wp-dynamic-filter' ), array( 'status' => 404 ) );
		}

		$modified = self::apply_tax_filter( $query_block, $taxonomy, $term_ids, $paged );

		return render_block( $modified );
	}

	/**
	 * Load block content from a post or FSE template.
	 *
	 * @param int $post_id Post ID.
	 * @return string|\WP_Error
	 */
	public static function get_source_content( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new \WP_Error( 'ddf_post_missing', __( 'Source content not found.', 'wp-dynamic-filter' ), array( 'status' => 404 ) );
		}

		if ( ! self::can_read_post( $post ) ) {
			return new \WP_Error( 'ddf_forbidden', __( 'You cannot view this content.', 'wp-dynamic-filter' ), array( 'status' => 403 ) );
		}

		if ( in_array( $post->post_type, array( 'wp_template', 'wp_template_part' ), true ) ) {
			$template = get_block_template( get_stylesheet() . '//' . $post->post_name, $post->post_type );
			if ( $template && ! empty( $template->content ) ) {
				return (string) $template->content;
			}
		}

		return (string) $post->post_content;
	}

	/**
	 * Whether the current user / public can read the source post.
	 *
	 * @param \WP_Post $post Post object.
	 * @return bool
	 */
	public static function can_read_post( \WP_Post $post ) {
		if ( in_array( $post->post_type, array( 'wp_template', 'wp_template_part' ), true ) ) {
			return true;
		}

		if ( is_post_publicly_viewable( $post ) ) {
			return true;
		}

		return current_user_can( 'read_post', $post->ID );
	}

	/**
	 * Recursively find a filter block by instanceId (current + legacy names).
	 *
	 * @param array  $blocks      Parsed blocks.
	 * @param string $instance_id Target instance.
	 * @return array|null
	 */
	public static function find_filter_block( array $blocks, $instance_id ) {
		$our_blocks = array( 'wp-dynamic-filter/query', 'dynamic-filter/query' );

		foreach ( $blocks as $block ) {
			if ( empty( $block['blockName'] ) ) {
				continue;
			}

			if ( in_array( $block['blockName'], $our_blocks, true ) ) {
				$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
				$id    = isset( $attrs['instanceId'] ) ? (string) $attrs['instanceId'] : '';
				if ( $id === $instance_id ) {
					return $block;
				}
			}

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$found = self::find_filter_block( $block['innerBlocks'], $instance_id );
				if ( null !== $found ) {
					return $found;
				}
			}
		}

		return null;
	}

	/**
	 * Find the first core/query among inner blocks.
	 *
	 * @param array $blocks Inner blocks.
	 * @return array|null
	 */
	public static function find_query_block( array $blocks ) {
		foreach ( $blocks as $block ) {
			if ( empty( $block['blockName'] ) ) {
				continue;
			}
			if ( 'core/query' === $block['blockName'] ) {
				return $block;
			}
			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$found = self::find_query_block( $block['innerBlocks'] );
				if ( null !== $found ) {
					return $found;
				}
			}
		}
		return null;
	}

	/**
	 * Merge taxonomy filter into core/query attributes.
	 *
	 * @param array  $query_block Parsed core/query block.
	 * @param string $taxonomy    Taxonomy.
	 * @param int[]  $term_ids    Term IDs (empty = all / no tax filter).
	 * @param int    $paged       Page.
	 * @return array
	 */
	public static function apply_tax_filter( array $query_block, $taxonomy, array $term_ids, $paged = 1 ) {
		$attrs = isset( $query_block['attrs'] ) && is_array( $query_block['attrs'] ) ? $query_block['attrs'] : array();
		$query = isset( $attrs['query'] ) && is_array( $attrs['query'] ) ? $attrs['query'] : array();

		// AJAX cannot rely on the main query inherit context.
		$query['inherit'] = false;

		if ( ! isset( $query['perPage'] ) ) {
			$query['perPage'] = 10;
		}
		if ( ! isset( $query['postType'] ) ) {
			$query['postType'] = 'post';
		}

		$query['offset'] = 0;
		$query['pages']  = 0;

		// Gutenberg stores taxQuery as { taxonomySlug: [ termId, ... ] } (OR / IN).
		$tax_query = isset( $query['taxQuery'] ) && is_array( $query['taxQuery'] ) ? $query['taxQuery'] : array();

		if ( empty( $term_ids ) ) {
			unset( $tax_query[ $taxonomy ] );
		} else {
			$tax_query[ $taxonomy ] = array_values( array_map( 'intval', $term_ids ) );
		}

		if ( empty( $tax_query ) ) {
			unset( $query['taxQuery'] );
		} else {
			$query['taxQuery'] = $tax_query;
		}

		$attrs['query']       = $query;
		$query_block['attrs'] = $attrs;

		if ( $paged > 1 ) {
			$query_block = self::inject_paged_context( $query_block, $paged );
		}

		return $query_block;
	}

	/**
	 * Best-effort page injection for query blocks that read queryId + page.
	 *
	 * @param array $query_block Block.
	 * @param int   $paged       Page number.
	 * @return array
	 */
	private static function inject_paged_context( array $query_block, $paged ) {
		$attrs    = isset( $query_block['attrs'] ) ? $query_block['attrs'] : array();
		$query_id = isset( $attrs['queryId'] ) ? (int) $attrs['queryId'] : 0;

		if ( $query_id > 0 ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$_GET[ 'query-' . $query_id . '-page' ] = (string) $paged;
		} else {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$_GET['query-page'] = (string) $paged;
		}

		return $query_block;
	}
}
