<?php
/**
 * Block editor support for the Posts On This Day block.
 *
 * @package jeherve/posts-on-this-day
 */

declare( strict_types=1 );

namespace Jeherve\Posts_On_This_Day;

use WP_Block;

/**
 * Registers the Query Loop block variation and handles
 * server-side query filtering and year heading injection.
 */
class Block {
	/**
	 * Whether the query_loop_block_query_vars filter has already been added.
	 *
	 * @var bool
	 */
	private $query_filter_added = false;

	/**
	 * Initialize hooks.
	 *
	 * @since 2.0.0
	 */
	public function init(): void {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
		add_filter( 'pre_render_block', array( $this, 'maybe_add_query_filter' ), 10, 2 );
		add_filter( 'rest_post_query', array( $this, 'filter_rest_query' ), 10, 2 );
		add_filter( 'render_block_core/post-template', array( $this, 'maybe_inject_year_headings' ), 10, 3 );
	}

	/**
	 * Enqueue the block variation JavaScript in the editor.
	 *
	 * @since 2.0.0
	 */
	public function enqueue_editor_assets(): void {
		$asset_file = plugin_dir_path( __DIR__ ) . 'build/index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = include $asset_file;

		wp_enqueue_script(
			'posts-on-this-day-editor',
			plugins_url( 'build/index.js', __DIR__ ),
			$asset['dependencies'],
			$asset['version'],
			false
		);

		wp_set_script_translations(
			'posts-on-this-day-editor',
			'posts-on-this-day'
		);
	}

	/**
	 * Conditionally add the query_loop_block_query_vars filter
	 * when a Posts On This Day block variation is being rendered.
	 *
	 * Hooked to pre_render_block to inspect the parsed block before rendering.
	 *
	 * @since 2.0.0
	 *
	 * @param string|null $pre_render  The pre-rendered content. Default null.
	 * @param array       $parsed_block The block being rendered.
	 *
	 * @return string|null The unmodified pre-render value.
	 */
	public function maybe_add_query_filter( $pre_render, array $parsed_block ) {
		if (
			! $this->query_filter_added
			&& isset( $parsed_block['attrs']['namespace'] )
			&& 'jeherve/posts-on-this-day' === $parsed_block['attrs']['namespace']
		) {
			add_filter(
				'query_loop_block_query_vars',
				array( $this, 'filter_query_vars' ),
				10,
				2
			);
			$this->query_filter_added = true;
		}

		return $pre_render;
	}

	/**
	 * Filter the Query Loop block query vars to inject our custom date query.
	 *
	 * @since 2.0.0
	 *
	 * @param array    $query The query vars for the Query Loop block.
	 * @param WP_Block $block The block instance, including context.
	 *
	 * @return array Modified query vars with date_query added when applicable.
	 */
	public function filter_query_vars( array $query, $block ): array {
		$block_query = $block->context['query'] ?? array();

		if ( ! isset( $block_query['yearsBack'] ) ) {
			return $query;
		}

		$years_back  = (int) ( $block_query['yearsBack'] ?? 10 );
		$exact_match = (bool) ( $block_query['exactMatch'] ?? false );

		$query['date_query']   = Query::build_date_query( $years_back, $exact_match );
		$query['has_password'] = false;

		return $query;
	}

	/**
	 * Filter the REST API query for editor preview.
	 *
	 * When the editor fetches posts for preview, check for our custom
	 * parameters and inject the date query if present.
	 *
	 * @since 2.0.0
	 *
	 * @param array            $args    WP_Query arguments for the REST query.
	 * @param \WP_REST_Request $request The REST request.
	 *
	 * @return array Modified query arguments.
	 */
	public function filter_rest_query( array $args, \WP_REST_Request $request ): array {
		$years_back = $request->get_param( 'yearsBack' );

		if ( null === $years_back ) {
			return $args;
		}

		$exact_match = (bool) $request->get_param( 'exactMatch' );
		$years_back  = (int) $years_back;

		$args['date_query']   = Query::build_date_query( $years_back, $exact_match );
		$args['has_password'] = false;

		return $args;
	}

	/**
	 * Inject year headings into the post-template output when groupByYear is enabled.
	 *
	 * Post-processes the rendered HTML from core/post-template to split the post list
	 * into year-grouped sections with heading elements between them.
	 *
	 * @since 2.0.0
	 *
	 * @param string   $content      The rendered block content.
	 * @param array    $parsed_block The parsed block data.
	 * @param WP_Block $block        The block instance.
	 *
	 * @return string Modified content with year headings.
	 */
	public function maybe_inject_year_headings( string $content, array $parsed_block, WP_Block $block ): string {
		$query_context = $block->context['query'] ?? array();

		if ( empty( $query_context['yearsBack'] ) || empty( $query_context['groupByYear'] ) ) {
			return $content;
		}

		$heading_level = (int) ( $query_context['yearHeadingLevel'] ?? 3 );
		$heading_level = max( 2, min( 6, $heading_level ) );

		// Re-build the query to determine post years in display order.
		$page        = absint( $block->context['page'] ?? 1 );
		$query_args  = build_query_vars_from_query_block( $block, $page );
		$posts_query = new \WP_Query( $query_args );

		if ( ! $posts_query->have_posts() ) {
			return $content;
		}

		// Map each post position to its year.
		$year_map = array();
		while ( $posts_query->have_posts() ) {
			$posts_query->the_post();
			$year_map[] = get_the_date( 'Y' );
		}
		wp_reset_postdata();

		// Extract the <ul> wrapper attributes.
		preg_match( '/<ul([^>]*)>/', $content, $ul_match );
		$ul_attrs = $ul_match[1] ?? '';

		// Extract top-level <li>…</li> items by tracking nesting depth,
		// so nested lists inside post content/excerpts are handled correctly.
		$items      = array();
		$offset     = 0;
		$depth      = 0;
		$item_start = 0;

		while ( preg_match( '/<(\/?)li\b[^>]*>/si', $content, $match, PREG_OFFSET_CAPTURE, $offset ) ) {
			$tag        = $match[0][0];
			$pos        = $match[0][1];
			$is_closing = '/' === $match[1][0];

			if ( ! $is_closing ) {
				if ( 0 === $depth ) {
					$item_start = $pos;
				}
				++$depth;
			} else {
				--$depth;
				if ( 0 === $depth ) {
					$items[] = substr( $content, $item_start, $pos + strlen( $tag ) - $item_start );
				}
			}

			$offset = $pos + strlen( $tag );
		}

		if ( empty( $items ) ) {
			return $content;
		}

		// Rebuild the output with year headings between groups.
		$output       = '';
		$current_year = '';
		$group_items  = array();

		foreach ( $items as $index => $item ) {
			$year = $year_map[ $index ] ?? '';

			if ( $year !== $current_year && '' !== $year ) {
				// Close the previous year group.
				if ( ! empty( $group_items ) ) {
					$output .= '<ul' . $ul_attrs . '>' . implode( '', $group_items ) . '</ul>';
				}

				// Add the year heading.
				$output .= sprintf(
					'<%1$s class="posts-on-this-day-year-heading">%2$s</%1$s>',
					'h' . $heading_level,
					esc_html( $year )
				);

				$current_year = $year;
				$group_items  = array();
			}

			$group_items[] = $item;
		}

		// Close the last year group.
		if ( ! empty( $group_items ) ) {
			$output .= '<ul' . $ul_attrs . '>' . implode( '', $group_items ) . '</ul>';
		}

		return $output;
	}
}
