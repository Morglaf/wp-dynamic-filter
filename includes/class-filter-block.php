<?php
/**
 * Dynamic filter container block.
 *
 * @package DynamicFilter
 */

namespace DynamicFilter;

defined( 'ABSPATH' ) || exit;

/**
 * Registers wp-dynamic-filter/query (and legacy dynamic-filter/query).
 */
class Filter_Block {

	/**
	 * Shared block attributes (legacy registration).
	 *
	 * @return array
	 */
	private static function attributes() {
		return array(
			'instanceId'   => array( 'type' => 'string', 'default' => '' ),
			'taxonomy'     => array( 'type' => 'string', 'default' => 'category' ),
			'display'      => array( 'type' => 'string', 'default' => 'buttons' ),
			'showAll'      => array( 'type' => 'boolean', 'default' => true ),
			'allLabel'     => array( 'type' => 'string', 'default' => 'All' ),
			'multiple'     => array( 'type' => 'boolean', 'default' => false ),
			'showCounts'   => array( 'type' => 'boolean', 'default' => false ),
			'emptyMessage' => array( 'type' => 'string', 'default' => '' ),
		);
	}

	/**
	 * Register block type.
	 */
	public static function register() {
		add_action( 'init', array( __CLASS__, 'register_block' ) );
	}

	/**
	 * Register from block.json + legacy alias.
	 */
	public static function register_block() {
		$block_json = DDF_PATH . 'blocks/query';
		if ( ! file_exists( $block_json . '/block.json' ) ) {
			return;
		}

		register_block_type(
			$block_json,
			array(
				'render_callback' => array( __CLASS__, 'render' ),
				'editor_script'   => Assets::EDITOR_HANDLE,
				'editor_style'    => Assets::EDITOR_HANDLE,
			)
		);

		// Legacy block name for content saved before the rename.
		register_block_type(
			'dynamic-filter/query',
			array(
				'api_version'     => 3,
				'title'           => __( 'Dynamic Filter Query (legacy)', 'wp-dynamic-filter' ),
				'category'        => 'theme',
				'render_callback' => array( __CLASS__, 'render' ),
				'attributes'      => self::attributes(),
				'supports'        => array( 'html' => false ),
			)
		);
	}

	/**
	 * Server render: filter UI + wrapped Query Loop HTML.
	 *
	 * @param array     $attributes Block attributes.
	 * @param string    $content    Inner blocks HTML.
	 * @param \WP_Block $block      Block instance.
	 * @return string
	 */
	public static function render( $attributes, $content, $block ) {
		$instance_id = isset( $attributes['instanceId'] ) ? (string) $attributes['instanceId'] : '';
		$taxonomy    = isset( $attributes['taxonomy'] ) ? sanitize_key( $attributes['taxonomy'] ) : 'category';
		$display     = isset( $attributes['display'] ) ? (string) $attributes['display'] : 'buttons';
		$show_all    = ! isset( $attributes['showAll'] ) || (bool) $attributes['showAll'];
		$all_label   = isset( $attributes['allLabel'] ) ? (string) $attributes['allLabel'] : '';
		$multiple    = ! empty( $attributes['multiple'] );
		$show_counts = ! empty( $attributes['showCounts'] );
		$empty_msg   = isset( $attributes['emptyMessage'] ) ? (string) $attributes['emptyMessage'] : '';

		if ( '' === trim( $all_label ) ) {
			$all_label = __( 'All', 'wp-dynamic-filter' );
		}

		if ( ! taxonomy_exists( $taxonomy ) ) {
			$taxonomy = 'category';
		}

		$post_id = (int) get_the_ID();
		if ( ! $post_id && isset( $block->context['postId'] ) ) {
			$post_id = (int) $block->context['postId'];
		}

		$active_terms = array();
		if ( isset( $_GET['ddf_id'], $_GET['ddf_term'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			&& (string) wp_unslash( $_GET['ddf_id'] ) === $instance_id ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$active_terms = Query_Render::normalize_term_slugs(
				wp_unslash( $_GET['ddf_term'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			);
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
			)
		);

		if ( is_wp_error( $terms ) ) {
			$terms = array();
		}

		$filter_html = self::render_filter_ui(
			array(
				'taxonomy'     => $taxonomy,
				'display'      => $display,
				'show_all'     => $show_all,
				'all_label'    => $all_label,
				'multiple'     => $multiple,
				'show_counts'  => $show_counts,
				'terms'        => $terms,
				'active_terms' => $active_terms,
			)
		);

		$wrapper_attrs = get_block_wrapper_attributes(
			array(
				'class'             => 'ddf-query' . ( $multiple ? ' ddf-query--multiple' : '' ),
				'data-ddf-instance' => $instance_id,
				'data-ddf-taxonomy' => $taxonomy,
				'data-ddf-post-id'  => (string) $post_id,
				'data-ddf-display'  => $display,
				'data-ddf-multiple' => $multiple ? '1' : '0',
			)
		);

		$results = $content;

		if ( ! empty( $active_terms ) && $post_id > 0 ) {
			$filtered = Query_Render::render_filtered_html( $post_id, $instance_id, $taxonomy, $active_terms, 1 );
			if ( ! is_wp_error( $filtered ) ) {
				$results = $filtered;
			}
		}

		if ( '' === trim( wp_strip_all_tags( $results ) ) && '' !== $empty_msg ) {
			$results = '<p class="ddf-query__empty">' . esc_html( $empty_msg ) . '</p>';
		}

		ob_start();
		?>
		<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php echo $filter_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<div class="ddf-query__results" data-ddf-results>
				<?php echo $results; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Build filter buttons or select markup.
	 *
	 * @param array $args Render args.
	 * @return string
	 */
	public static function render_filter_ui( array $args ) {
		$display      = $args['display'];
		$show_all     = $args['show_all'];
		$all_label    = $args['all_label'];
		$multiple     = ! empty( $args['multiple'] );
		$show_counts  = $args['show_counts'];
		$terms        = $args['terms'];
		$active_terms = isset( $args['active_terms'] ) && is_array( $args['active_terms'] ) ? $args['active_terms'] : array();
		$is_all       = empty( $active_terms );

		ob_start();

		if ( 'select' === $display ) {
			?>
			<nav class="ddf-filter ddf-filter--select" aria-label="<?php echo esc_attr__( 'Filter by term', 'wp-dynamic-filter' ); ?>">
				<select
					class="ddf-filter__select"
					data-ddf-filter-control
					<?php echo $multiple ? 'multiple' : ''; ?>
					<?php echo $multiple ? ' aria-multiselectable="true"' : ''; ?>
				>
					<?php if ( $show_all && ! $multiple ) : ?>
						<option value="" <?php selected( $is_all ); ?>><?php echo esc_html( $all_label ); ?></option>
					<?php endif; ?>
					<?php foreach ( $terms as $term ) : ?>
						<?php
						$label = $term->name;
						if ( $show_counts ) {
							$label .= ' (' . (int) $term->count . ')';
						}
						$selected = in_array( $term->slug, $active_terms, true );
						?>
						<option
							value="<?php echo esc_attr( $term->slug ); ?>"
							<?php selected( $selected ); ?>
						><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php if ( $multiple && $show_all ) : ?>
					<button type="button" class="ddf-filter__clear" data-ddf-term="" data-ddf-all>
						<?php echo esc_html( $all_label ); ?>
					</button>
				<?php endif; ?>
			</nav>
			<?php
			return (string) ob_get_clean();
		}

		?>
		<nav class="ddf-filter ddf-filter--buttons" aria-label="<?php echo esc_attr__( 'Filter by term', 'wp-dynamic-filter' ); ?>">
			<ul class="ddf-filter__list">
				<?php if ( $show_all ) : ?>
					<li class="ddf-filter__item">
						<button
							type="button"
							class="ddf-filter__btn<?php echo $is_all ? ' is-active' : ''; ?>"
							data-ddf-term=""
							data-ddf-all
							aria-pressed="<?php echo $is_all ? 'true' : 'false'; ?>"
						><?php echo esc_html( $all_label ); ?></button>
					</li>
				<?php endif; ?>
				<?php foreach ( $terms as $term ) : ?>
					<?php
					$is_active = in_array( $term->slug, $active_terms, true );
					$label     = $term->name;
					if ( $show_counts ) {
						$label .= ' (' . (int) $term->count . ')';
					}
					?>
					<li class="ddf-filter__item">
						<button
							type="button"
							class="ddf-filter__btn<?php echo $is_active ? ' is-active' : ''; ?>"
							data-ddf-term="<?php echo esc_attr( $term->slug ); ?>"
							aria-pressed="<?php echo $is_active ? 'true' : 'false'; ?>"
						><?php echo esc_html( $label ); ?></button>
					</li>
				<?php endforeach; ?>
			</ul>
		</nav>
		<?php
		return (string) ob_get_clean();
	}
}
