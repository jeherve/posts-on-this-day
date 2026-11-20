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
	 * Namespace identifying our variation of the Query Loop block.
	 *
	 * @var string
	 */
	const VARIATION = 'jeherve/posts-on-this-day';

	/**
	 * Upper bound for the number of years to look back.
	 *
	 * Each year adds a clause to the date query, and the value can come from a
	 * public REST request, so it must stay bounded.
	 *
	 * @var int
	 */
	const MAX_YEARS_BACK = 100;

	/**
	 * Attribute temporarily set on the first post of each year, to split the list there.
	 *
	 * @var string
	 */
	const YEAR_MARKER = 'data-posts-on-this-day-year';

	/**
	 * Initialize hooks.
	 *
	 * @since 2.0.0
	 */
	public function init(): void {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
		add_filter( 'render_block_data', array( $this, 'force_custom_query' ) );
		add_filter( 'query_loop_block_query_vars', array( $this, 'filter_query_vars' ), 10, 2 );
		add_action( 'rest_api_init', array( $this, 'register_rest_filters' ) );
		add_filter( 'render_block_core/post-template', array( $this, 'maybe_inject_year_headings' ), 10, 3 );
	}

	/**
	 * Force our variation to use a custom query instead of inheriting the global one.
	 *
	 * The core/query block defaults inherit to true, which makes the post-template
	 * use the global WP_Query (e.g. the page's own query on singular views).
	 * We override this so our date-based query always runs.
	 *
	 * @since 2.0.0
	 *
	 * @param array $parsed_block The parsed block data.
	 *
	 * @return array The modified parsed block data.
	 */
	public function force_custom_query( array $parsed_block ): array {
		if (
			isset( $parsed_block['attrs']['namespace'] )
			&& self::VARIATION === $parsed_block['attrs']['namespace']
		) {
			$parsed_block['attrs']['query']['inherit'] = false;
		}

		return $parsed_block;
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
	 * Filter the Query Loop block query vars to inject our custom date query.
	 *
	 * Core does not pass the block's namespace in context, so the `yearsBack`
	 * key in the query is what identifies our variation.
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

		if ( ! is_array( $block_query ) || ! array_key_exists( 'yearsBack', $block_query ) ) {
			return $query;
		}

		return self::add_query_args( $query, $block_query['yearsBack'], $block_query['exactMatch'] ?? false );
	}

	/**
	 * Hook the REST query filter for every post type exposed in the REST API.
	 *
	 * The editor preview fetches posts from the post type's own REST route,
	 * and each route has its own `rest_{$post_type}_query` filter.
	 *
	 * @since 2.0.0
	 */
	public function register_rest_filters(): void {
		foreach ( get_post_types( array( 'show_in_rest' => true ) ) as $post_type ) {
			add_filter( "rest_{$post_type}_query", array( $this, 'filter_rest_query' ), 10, 2 );
		}
	}

	/**
	 * Filter the REST API query for editor preview.
	 *
	 * The Query Loop editor passes any extra query attributes as request
	 * parameters, so `yearsBack` and `exactMatch` arrive as query strings.
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

		return self::add_query_args( $args, $years_back, $request->get_param( 'exactMatch' ) );
	}

	/**
	 * Add our restrictions to a set of WP_Query arguments.
	 *
	 * Matches the widget: only published posts without a password, and sticky
	 * posts are not pulled to the top regardless of their date.
	 *
	 * @since 2.0.0
	 *
	 * @param array $args        WP_Query arguments.
	 * @param mixed $years_back  Years to look back; 0 means every year with posts.
	 * @param mixed $exact_match Whether to only match the exact day. Accepts booleans and strings such as "false".
	 *
	 * @return array
	 */
	public static function add_query_args( array $args, $years_back, $exact_match ): array {
		$args['date_query']          = Query::build_date_query(
			self::sanitize_years_back( $years_back, $args['post_type'] ?? 'post' ),
			filter_var( $exact_match, FILTER_VALIDATE_BOOLEAN )
		);
		$args['has_password']        = false;
		$args['post_status']         = 'publish';
		$args['ignore_sticky_posts'] = true;

		return $args;
	}

	/**
	 * Turn a `yearsBack` value into a number of years we can query.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed           $years_back Raw value, from block attributes or a REST request.
	 * @param string|string[] $post_type  Post type(s) being queried, used when looking back over every year.
	 *
	 * @return int Between 1 and MAX_YEARS_BACK.
	 */
	public static function sanitize_years_back( $years_back, $post_type ): int {
		$years = is_numeric( $years_back ) ? (int) $years_back : 0;

		if ( $years <= 0 ) {
			$years = Query::get_years_of_history( $post_type );
		}

		return max( 1, min( self::MAX_YEARS_BACK, $years ) );
	}

	/**
	 * Group the rendered posts by year, with a heading before each group.
	 *
	 * Core renders the Post Template block as one `<ul>` with an `<li class="wp-block-post post-{ID} …">`
	 * per post. Each item's year comes from that post ID (the posts are already in the
	 * object cache), so this needs no extra query. The list is then split into one list
	 * per year, each copying the original list's attributes.
	 *
	 * Grouping only applies when posts are sorted by date; with any other order, years
	 * would repeat and the headings would not mean much.
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
		$query = $block->context['query'] ?? array();

		if (
			'' === $content
			|| ! is_array( $query )
			|| ! array_key_exists( 'yearsBack', $query )
			|| empty( $query['groupByYear'] )
			|| ( isset( $query['orderBy'] ) && 'date' !== $query['orderBy'] )
		) {
			return $content;
		}

		$processor = new \WP_HTML_Tag_Processor( $content );

		if ( ! $processor->next_tag() || 'UL' !== $processor->get_tag() ) {
			return $content;
		}

		$current_year = null;
		while ( $processor->next_tag( 'LI' ) ) {
			if ( ! $processor->has_class( 'wp-block-post' ) ) {
				continue;
			}

			$post_id = self::get_post_id_from_classes( (string) $processor->get_attribute( 'class' ) );
			if ( ! $post_id ) {
				return $content;
			}

			$year = (string) get_the_date( 'Y', $post_id );
			if ( $year !== $current_year ) {
				$processor->set_attribute( self::YEAR_MARKER, $year );
				$current_year = $year;
			}
		}

		return self::split_list_by_year(
			$processor->get_updated_html(),
			(int) ( $query['yearHeadingLevel'] ?? 3 )
		) ?? $content;
	}

	/**
	 * Split a post list at each item marked with the year it starts.
	 *
	 * @since 2.0.0
	 *
	 * @param string $html          A single `<ul>` whose items starting a new year carry the YEAR_MARKER attribute.
	 * @param int    $heading_level Heading level for the years, from 2 to 6.
	 *
	 * @return string|null The grouped markup, or null when the markup is not shaped as expected.
	 */
	public static function split_list_by_year( string $html, int $heading_level ): ?string {
		if ( ! preg_match_all( '/\s' . self::YEAR_MARKER . '="(\d+)"/', $html, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			return null;
		}

		$groups = array();
		foreach ( $matches as $match ) {
			$item_start = strrpos( substr( $html, 0, $match[0][1] ), '<li' );
			if ( false === $item_start ) {
				return null;
			}

			$groups[] = array(
				'start'  => $item_start,
				'year'   => $match[1][0],
				'marker' => $match[0][0],
			);
		}

		$list_open  = substr( $html, 0, $groups[0]['start'] );
		$list_close = strrpos( $html, '</ul>' );
		if ( false === $list_close || ! preg_match( '/^\s*<ul\b[^>]*>\s*$/', $list_open ) ) {
			return null;
		}

		// Later lists must not repeat the first list's anchor. Block wrapper attributes are always double-quoted.
		$later_list_open = preg_replace( '/\sid="[^"]*"/', '', $list_open, 1 );

		$heading_level = max( 2, min( 6, $heading_level ) );
		$output        = '';

		foreach ( $groups as $index => $group ) {
			$end   = $groups[ $index + 1 ]['start'] ?? $list_close;
			$items = substr( $html, $group['start'], $end - $group['start'] );
			$items = preg_replace( '/' . preg_quote( $group['marker'], '/' ) . '/', '', $items, 1 );

			$output .= sprintf(
				'<h%1$d class="wp-block-heading posts-on-this-day-year-heading">%2$s</h%1$d>',
				$heading_level,
				esc_html( $group['year'] )
			);
			$output .= ( 0 === $index ? $list_open : $later_list_open ) . $items . '</ul>';
		}

		return $output . substr( $html, $list_close + strlen( '</ul>' ) );
	}

	/**
	 * Get the post ID from the `post-{ID}` class core adds to each post.
	 *
	 * @param string $classes Space-separated class names.
	 *
	 * @return int Post ID, or 0 when there is none.
	 */
	private static function get_post_id_from_classes( string $classes ): int {
		return preg_match( '/(?:^|\s)post-(\d+)(?:\s|$)/', $classes, $match ) ? (int) $match[1] : 0;
	}
}
