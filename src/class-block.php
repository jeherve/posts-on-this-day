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
	 * Query var flagging the queries of blocks that group posts by year.
	 *
	 * @var string
	 */
	const GROUP_QUERY_FLAG = 'posts_on_this_day_group_by_year';

	/**
	 * Post Template blocks currently rendering with year headings, keyed by block instance.
	 *
	 * Each entry holds a random token for its item markers, the block's inner blocks
	 * (to recognise its own items), the year of each post the block's query returned
	 * (in order), and how many items were marked so far.
	 *
	 * @var array<int, array{token: string, inner_blocks: array, years: string[]|null, marked: int}>
	 */
	private array $groupings = array();

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
		// Last, so the recorded order is the one the posts are rendered in, after any plugin reorders them.
		add_filter( 'the_posts', array( $this, 'capture_grouped_posts' ), PHP_INT_MAX, 2 );
		add_filter( 'render_block_core/null', array( $this, 'mark_grouped_item' ), 10, 2 );
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
			&& is_array( $parsed_block['attrs']['query'] ?? array() )
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

		$query = self::add_query_args( $query, $block_query['yearsBack'], $block_query['exactMatch'] ?? false );

		if ( self::groups_by_year( $block_query ) && $block instanceof WP_Block && 'core/post-template' === $block->name ) {
			$this->groupings[ spl_object_id( $block ) ] = array(
				'token'        => bin2hex( random_bytes( 16 ) ),
				'inner_blocks' => $block->parsed_block['innerBlocks'] ?? array(),
				'years'        => null,
				'marked'       => 0,
			);
			$query[ self::GROUP_QUERY_FLAG ]            = true;
		}

		return $query;
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
	 * Whether a block's query asks for year headings.
	 *
	 * Grouping only applies when posts are sorted by date; with any other order, years
	 * would repeat and the headings would not mean much.
	 *
	 * @param array $block_query The block's query attributes.
	 *
	 * @return bool
	 */
	private static function groups_by_year( array $block_query ): bool {
		return ! empty( $block_query['groupByYear'] )
			&& ( ! isset( $block_query['orderBy'] ) || 'date' === $block_query['orderBy'] );
	}

	/**
	 * Record the year of each post a grouping block's query returned, in order.
	 *
	 * This is the only source of years for the headings: nothing is read back from the
	 * rendered markup, which includes post content.
	 *
	 * @since 2.0.0
	 *
	 * @param array  $posts Posts returned by the query.
	 * @param object $query The WP_Query instance.
	 *
	 * @return array The posts, unchanged.
	 */
	public function capture_grouped_posts( $posts, $query ) {
		$key = array_key_last( $this->groupings );

		if (
			null === $key
			|| null !== $this->groupings[ $key ]['years']
			|| empty( $query->query_vars[ self::GROUP_QUERY_FLAG ] )
			|| ! is_array( $posts )
		) {
			return $posts;
		}

		$this->groupings[ $key ]['years'] = array_map(
			static fn( $post ) => (string) get_the_date( 'Y', $post ),
			$posts
		);

		return $posts;
	}

	/**
	 * Mark the start of each post rendered by a grouping Post Template block.
	 *
	 * Core renders each post's inner blocks as a `core/null` copy of the Post Template
	 * block, then wraps the result in `<li …>`. Loops nested inside a post render their
	 * own `core/null` items first; comparing inner blocks keeps those unmarked. The marker
	 * carries a random token created for this render, so post content cannot contain or
	 * imitate it.
	 *
	 * @since 2.0.0
	 *
	 * @param string $content      The rendered content of one post.
	 * @param array  $parsed_block The `core/null` copy of the Post Template block.
	 *
	 * @return string
	 */
	public function mark_grouped_item( $content, $parsed_block = array() ) {
		$key = array_key_last( $this->groupings );

		if (
			null === $key
			|| null === $this->groupings[ $key ]['years']
			|| ( $parsed_block['innerBlocks'] ?? null ) !== $this->groupings[ $key ]['inner_blocks']
		) {
			return $content;
		}

		$state = &$this->groupings[ $key ];
		if ( $state['marked'] >= count( $state['years'] ) ) {
			return $content;
		}

		$marker = self::item_marker( $state['token'], $state['marked'] );
		++$state['marked'];

		return $marker . $content;
	}

	/**
	 * Group the rendered posts by year, with a heading before each group.
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
		$key = spl_object_id( $block );

		if ( ! isset( $this->groupings[ $key ] ) ) {
			return $content;
		}

		$state = $this->groupings[ $key ];
		unset( $this->groupings[ $key ] );

		if ( '' === $content || empty( $state['years'] ) ) {
			return self::remove_item_markers( $content, $state['token'] );
		}

		$query = $block->context['query'] ?? array();

		return self::split_list_by_year(
			$content,
			$state['token'],
			$state['years'],
			(int) ( $query['yearHeadingLevel'] ?? 3 )
		) ?? self::remove_item_markers( $content, $state['token'] );
	}

	/**
	 * Split a rendered post list into one list per year, each after a heading.
	 *
	 * Every item must carry its marker exactly once, in order, right after its own `<li …>`
	 * opening tag, and there must be one item per post the query returned. Anything else
	 * (a nested loop inside a post, unexpected markup) leaves the list as it is.
	 *
	 * @since 2.0.0
	 *
	 * @param string   $html          The Post Template block's markup: one `<ul>` of marked items.
	 * @param string   $token         Marker token for this render.
	 * @param string[] $years         Year of each post, in the order they were rendered.
	 * @param int      $heading_level Heading level for the years, from 2 to 6.
	 *
	 * @return string|null The grouped markup, or null when the markup is not shaped as expected.
	 */
	public static function split_list_by_year( string $html, string $token, array $years, int $heading_level ): ?string {
		$count = count( $years );

		// One marker per post: more means items from a loop nested inside a post.
		if ( 0 === $count || substr_count( $html, self::marker_prefix( $token ) ) !== $count ) {
			return null;
		}

		$starts   = array();
		$previous = 0;
		for ( $index = 0; $index < $count; $index++ ) {
			$marker   = self::item_marker( $token, $index );
			$position = strpos( $html, $marker, $previous );
			if ( false === $position ) {
				return null;
			}

			// Core's item tag is `<li …>` with escaped attributes, directly followed by the marker.
			$item_start = strrpos( substr( $html, 0, $position ), '<li' );
			if (
				false === $item_start
				|| $item_start < $previous
				|| ! preg_match( '/^<li\b[^<>]*>$/', substr( $html, $item_start, $position - $item_start ) )
			) {
				return null;
			}

			$starts[] = $item_start;
			$previous = $position + strlen( $marker );
		}

		$list_open  = substr( $html, 0, $starts[0] );
		$list_close = strrpos( $html, '</ul>' );
		if (
			false === $list_close
			|| $list_close < $previous
			|| ! preg_match( '/^\s*<ul\b[^<>]*>\s*$/', $list_open )
			|| '' !== trim( substr( $html, $list_close + strlen( '</ul>' ) ) )
		) {
			return null;
		}

		// Later lists must not repeat the first list's anchor. Block wrapper attributes are always double-quoted.
		$later_list_open = preg_replace( '/\sid="[^"]*"/', '', $list_open, 1 );
		$heading_level   = max( 2, min( 6, $heading_level ) );
		$output          = '';

		for ( $index = 0; $index < $count; $index++ ) {
			$end  = $starts[ $index + 1 ] ?? $list_close;
			$item = str_replace( self::item_marker( $token, $index ), '', substr( $html, $starts[ $index ], $end - $starts[ $index ] ) );

			if ( 0 === $index || $years[ $index ] !== $years[ $index - 1 ] ) {
				if ( 0 !== $index ) {
					$output .= '</ul>';
				}
				$output .= sprintf(
					'<h%1$d class="wp-block-heading posts-on-this-day-year-heading">%2$s</h%1$d>',
					$heading_level,
					esc_html( $years[ $index ] )
				);
				$output .= 0 === $index ? $list_open : $later_list_open;
			}

			$output .= $item;
		}

		return $output . '</ul>';
	}

	/**
	 * Build the marker placed at the start of a post's rendered content.
	 *
	 * @param string $token Marker token for this render.
	 * @param int    $index Position of the post in the list.
	 *
	 * @return string
	 */
	private static function item_marker( string $token, int $index ): string {
		return self::marker_prefix( $token ) . $index . '-->';
	}

	/**
	 * Build the part of the item markers shared by every post in one render.
	 *
	 * @param string $token Marker token for this render.
	 *
	 * @return string
	 */
	private static function marker_prefix( string $token ): string {
		return '<!--posts-on-this-day:' . $token . ':';
	}

	/**
	 * Remove this render's item markers.
	 *
	 * @param string $html  Markup.
	 * @param string $token Marker token for this render.
	 *
	 * @return string
	 */
	private static function remove_item_markers( string $html, string $token ): string {
		return (string) preg_replace( '/' . preg_quote( self::marker_prefix( $token ), '/' ) . '\d+-->/', '', $html );
	}
}
