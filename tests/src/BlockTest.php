<?php
/**
 * Tests for Jeherve\Posts_On_This_Day\Block.
 *
 * @package jeherve/posts-on-this-day
 */

namespace Jeherve\Posts_On_This_Day;

use Brain\Monkey;
use Brain\Monkey\Functions;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Class BlockTest
 */
#[CoversClass( Block::class )]
class BlockTest extends TestCase {
	/**
	 * Set up Brain Monkey before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	/**
	 * Tear down Brain Monkey after each test.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test that init registers expected hooks.
	 */
	public function test_init_registers_hooks(): void {
		$instance = new Block();
		$instance->init();

		$this->assertTrue(
			has_action( 'enqueue_block_editor_assets', array( $instance, 'enqueue_editor_assets' ) ) !== false
		);
		$this->assertTrue(
			has_filter( 'render_block_data', array( $instance, 'force_custom_query' ) ) !== false
		);
		$this->assertTrue(
			has_filter( 'query_loop_block_query_vars', array( $instance, 'filter_query_vars' ) ) !== false
		);
		$this->assertTrue(
			has_action( 'rest_api_init', array( $instance, 'register_rest_filters' ) ) !== false
		);
		$this->assertTrue(
			has_filter( 'render_block_core/post-template', array( $instance, 'maybe_inject_year_headings' ) ) !== false
		);
	}

	/**
	 * Test that force_custom_query sets inherit to false for our variation.
	 */
	public function test_force_custom_query_sets_inherit_false(): void {
		$instance     = new Block();
		$parsed_block = array(
			'attrs' => array(
				'namespace' => 'jeherve/posts-on-this-day',
				'query'     => array(
					'inherit' => true,
				),
			),
		);

		$result = $instance->force_custom_query( $parsed_block );

		$this->assertFalse( $result['attrs']['query']['inherit'] );
	}

	/**
	 * Malformed markup with a non-array query is left alone instead of causing a fatal error.
	 */
	public function test_force_custom_query_ignores_non_array_query(): void {
		$parsed_block = array(
			'attrs' => array(
				'namespace' => 'jeherve/posts-on-this-day',
				'query'     => 'broken',
			),
		);

		$this->assertSame( $parsed_block, ( new Block() )->force_custom_query( $parsed_block ) );
	}

	/**
	 * Test that force_custom_query does not modify other blocks.
	 */
	public function test_force_custom_query_skips_other_blocks(): void {
		$instance     = new Block();
		$parsed_block = array(
			'attrs' => array(
				'namespace' => 'core/query',
				'query'     => array(
					'inherit' => true,
				),
			),
		);

		$result = $instance->force_custom_query( $parsed_block );

		$this->assertTrue( $result['attrs']['query']['inherit'] );
	}

	/**
	 * Build a stand-in for a WP_Block with the given query context.
	 *
	 * @param array $query Query context.
	 *
	 * @return \stdClass
	 */
	private function block_with_query( array $query ): \stdClass {
		$block          = new \stdClass();
		$block->context = array( 'query' => $query );
		return $block;
	}

	/**
	 * Test that filter_query_vars adds our restrictions when yearsBack is present.
	 */
	public function test_filter_query_vars_adds_date_query(): void {
		Functions\when( 'wp_timezone' )->justReturn( new DateTimeZone( 'UTC' ) );

		$block = $this->block_with_query(
			array(
				'yearsBack'  => 5,
				'exactMatch' => false,
			)
		);

		$instance = new Block();
		$result   = $instance->filter_query_vars( array( 'post_type' => 'post' ), $block );

		$this->assertFalse( $result['has_password'] );
		$this->assertSame( 'OR', $result['date_query']['relation'] );
		// 5 years back = 5 date ranges + relation.
		$this->assertCount( 6, $result['date_query'] );
	}

	/**
	 * Private posts stay out of the block, even for logged-in users who can read them.
	 *
	 * Regression test: WP_Query adds private posts for users who can read them
	 * when no status is set, which is what the Query Loop block does.
	 */
	public function test_filter_query_vars_only_queries_published_posts(): void {
		Functions\when( 'wp_timezone' )->justReturn( new DateTimeZone( 'UTC' ) );

		$result = ( new Block() )->filter_query_vars(
			array( 'post_type' => 'post' ),
			$this->block_with_query( array( 'yearsBack' => 2 ) )
		);

		$this->assertSame( 'publish', $result['post_status'] );
	}

	/**
	 * Sticky posts are not pulled to the top of the list regardless of their date.
	 *
	 * Regression test: with the Query Loop default `sticky: ""`, WP_Query prepends
	 * every sticky post to the first page.
	 */
	public function test_filter_query_vars_ignores_sticky_posts(): void {
		Functions\when( 'wp_timezone' )->justReturn( new DateTimeZone( 'UTC' ) );

		$result = ( new Block() )->filter_query_vars(
			array( 'post_type' => 'post' ),
			$this->block_with_query(
				array(
					'yearsBack' => 2,
					'sticky'    => '',
				)
			)
		);

		$this->assertTrue( $result['ignore_sticky_posts'] );
	}

	/**
	 * Test that filter_query_vars skips when yearsBack is absent.
	 */
	public function test_filter_query_vars_skips_without_years_back(): void {
		$instance = new Block();
		$original = array( 'post_type' => 'post' );
		$result   = $instance->filter_query_vars( $original, $this->block_with_query( array( 'postType' => 'post' ) ) );

		$this->assertSame( $original, $result );
	}

	/**
	 * Test that filter_query_vars with exactMatch true produces single-day ranges.
	 */
	public function test_filter_query_vars_with_exact_match(): void {
		Functions\when( 'wp_timezone' )->justReturn( new DateTimeZone( 'UTC' ) );

		$block = $this->block_with_query(
			array(
				'yearsBack'  => 3,
				'exactMatch' => true,
			)
		);

		$instance = new Block();
		$result   = $instance->filter_query_vars( array( 'post_type' => 'post' ), $block );

		$this->assertCount( 4, $result['date_query'] );

		for ( $i = 0; $i < 3; $i++ ) {
			$this->assertTrue( $result['date_query'][ $i ]['inclusive'] );
			$this->assertSame( $result['date_query'][ $i ]['before'], $result['date_query'][ $i ]['after'] );
		}
	}

	/**
	 * Test that filter_query_vars returns original query when context has no query key.
	 */
	public function test_filter_query_vars_with_empty_context(): void {
		$block          = new \stdClass();
		$block->context = array();

		$instance = new Block();
		$original = array( 'post_type' => 'post' );
		$result   = $instance->filter_query_vars( $original, $block );

		$this->assertSame( $original, $result );
	}

	/**
	 * A yearsBack of 0 looks back as far as the oldest published post.
	 */
	public function test_years_back_zero_covers_every_year_with_posts(): void {
		Functions\when( 'wp_timezone' )->justReturn( new DateTimeZone( 'UTC' ) );
		$oldest            = new \stdClass();
		$oldest->post_date = '2005-03-14 10:00:00';
		Functions\expect( 'get_posts' )->once()->andReturn( array( $oldest ) );

		$years = Block::sanitize_years_back( 0, 'potd_history_test' );

		$this->assertSame( (int) gmdate( 'Y' ) - 2005, $years );
	}

	/**
	 * On a site with no older posts, "every year" still means at least one year.
	 *
	 * An empty set of ranges would leave the date query without any condition.
	 */
	public function test_years_back_zero_without_posts_is_one_year(): void {
		Functions\when( 'wp_timezone' )->justReturn( new DateTimeZone( 'UTC' ) );
		Functions\expect( 'get_posts' )->once()->andReturn( array() );

		$this->assertSame( 1, Block::sanitize_years_back( '0', 'potd_empty_test' ) );
	}

	/**
	 * Years back is capped, so a REST request cannot ask for an unbounded date query.
	 */
	public function test_years_back_is_capped(): void {
		$this->assertSame( Block::MAX_YEARS_BACK, Block::sanitize_years_back( '1000000000', 'post' ) );
		$this->assertSame( 25, Block::sanitize_years_back( 25, 'post' ) );
		$this->assertSame( 7, Block::sanitize_years_back( '7', 'post' ) );
	}

	/**
	 * Test that register_rest_filters hooks every REST-enabled post type.
	 */
	public function test_register_rest_filters_covers_all_rest_post_types(): void {
		Functions\when( 'get_post_types' )->justReturn(
			array(
				'post' => 'post',
				'page' => 'page',
			)
		);

		$instance = new Block();
		$instance->register_rest_filters();

		$this->assertNotFalse( has_filter( 'rest_post_query', array( $instance, 'filter_rest_query' ) ) );
		$this->assertNotFalse( has_filter( 'rest_page_query', array( $instance, 'filter_rest_query' ) ) );
	}

	/**
	 * Test that filter_rest_query adds date_query when yearsBack param is present.
	 */
	public function test_filter_rest_query_with_years_back(): void {
		Functions\when( 'wp_timezone' )->justReturn( new DateTimeZone( 'UTC' ) );

		$request = new \WP_REST_Request(
			array(
				'yearsBack'  => '3',
				'exactMatch' => '0',
			)
		);

		$instance = new Block();
		$result   = $instance->filter_rest_query( array( 'post_type' => 'post' ), $request );

		$this->assertCount( 4, $result['date_query'] );
		$this->assertFalse( $result['has_password'] );
		$this->assertSame( 'publish', $result['post_status'] );
		$this->assertTrue( $result['ignore_sticky_posts'] );
	}

	/**
	 * The editor sends `exactMatch=false` as a string, which must not count as true.
	 *
	 * Regression test: casting the string "false" to bool gave true, so the editor
	 * preview showed exact-day matches while the site showed the whole week.
	 */
	public function test_filter_rest_query_reads_string_false_as_false(): void {
		Functions\when( 'wp_timezone' )->justReturn( new DateTimeZone( 'UTC' ) );

		$request = new \WP_REST_Request(
			array(
				'yearsBack'  => '1',
				'exactMatch' => 'false',
			)
		);

		$result = ( new Block() )->filter_rest_query( array( 'post_type' => 'post' ), $request );

		$this->assertNotSame( $result['date_query'][0]['after'], $result['date_query'][0]['before'] );
	}

	/**
	 * Test that filter_rest_query skips when yearsBack param is absent.
	 */
	public function test_filter_rest_query_without_years_back(): void {
		$request = new \WP_REST_Request( array() );

		$instance = new Block();
		$original = array( 'post_type' => 'post' );
		$result   = $instance->filter_rest_query( $original, $request );

		$this->assertSame( $original, $result );
	}

	/**
	 * Test that filter_rest_query with exactMatch produces single-day ranges.
	 */
	public function test_filter_rest_query_with_exact_match(): void {
		Functions\when( 'wp_timezone' )->justReturn( new DateTimeZone( 'UTC' ) );

		$request = new \WP_REST_Request(
			array(
				'yearsBack'  => 2,
				'exactMatch' => 'true',
			)
		);

		$instance = new Block();
		$result   = $instance->filter_rest_query( array( 'post_type' => 'post' ), $request );

		$this->assertCount( 3, $result['date_query'] );

		for ( $i = 0; $i < 2; $i++ ) {
			$this->assertSame( $result['date_query'][ $i ]['before'], $result['date_query'][ $i ]['after'] );
		}
	}

	/**
	 * Build an item as core's Post Template renders it.
	 *
	 * @param int    $id     Post ID.
	 * @param string $year   Year to mark the item with, or '' for none.
	 * @param string $inside Item content.
	 *
	 * @return string
	 */
	private function item( int $id, string $year = '', string $inside = '' ): string {
		$marker = '' === $year ? '' : sprintf( ' %s="%s"', Block::YEAR_MARKER, $year );
		return sprintf( '<li%1$s class="wp-block-post post-%2$d post type-post">%3$s</li>', $marker, $id, '' === $inside ? "Post {$id}" : $inside );
	}

	/**
	 * Posts are split into one list per year, each after a heading.
	 */
	public function test_split_list_by_year_groups_items(): void {
		Functions\stubEscapeFunctions();

		$html = '<ul class="wp-block-post-template is-layout-grid">'
			. $this->item( 1, '2024' ) . $this->item( 2 )
			. $this->item( 3, '2020' )
			. '</ul>';

		$this->assertSame(
			'<h3 class="wp-block-heading posts-on-this-day-year-heading">2024</h3>'
			. '<ul class="wp-block-post-template is-layout-grid">' . $this->item( 1 ) . $this->item( 2 ) . '</ul>'
			. '<h3 class="wp-block-heading posts-on-this-day-year-heading">2020</h3>'
			. '<ul class="wp-block-post-template is-layout-grid">' . $this->item( 3 ) . '</ul>',
			Block::split_list_by_year( $html, 3 )
		);
	}

	/**
	 * Lists nested in a post's content do not confuse the split.
	 *
	 * The previous implementation parsed `<li>` tags with regular expressions and
	 * depended on every nested list being well formed.
	 */
	public function test_split_list_by_year_keeps_nested_lists_intact(): void {
		Functions\stubEscapeFunctions();

		$nested = '<ul><li>One<li>Two</ul><p title="a > b">Text</p>';
		$html   = '<ul class="list">' . $this->item( 1, '2024', $nested ) . $this->item( 2, '2023' ) . '</ul>';

		$result = Block::split_list_by_year( $html, 2 );

		$this->assertStringContainsString( $this->item( 1, '', $nested ) . '</ul><h2', $result );
		$this->assertStringEndsWith( '<ul class="list">' . $this->item( 2 ) . '</ul>', $result );
		$this->assertStringNotContainsString( Block::YEAR_MARKER, $result );
	}

	/**
	 * Only the first list keeps an HTML anchor, so IDs stay unique.
	 */
	public function test_split_list_by_year_keeps_id_on_first_list_only(): void {
		Functions\stubEscapeFunctions();

		$html   = '<ul id="memories" class="list">' . $this->item( 1, '2024' ) . $this->item( 2, '2023' ) . '</ul>';
		$result = Block::split_list_by_year( $html, 3 );

		$this->assertSame( 1, substr_count( $result, 'id="memories"' ) );
		$this->assertSame( 2, substr_count( $result, '<ul class="list">' ) + substr_count( $result, '<ul id="memories" class="list">' ) );
	}

	/**
	 * Heading levels outside of h2 to h6 are clamped.
	 */
	public function test_split_list_by_year_clamps_heading_level(): void {
		Functions\stubEscapeFunctions();

		$html = '<ul>' . $this->item( 1, '2024' ) . '</ul>';

		$this->assertStringStartsWith( '<h2 ', Block::split_list_by_year( $html, 1 ) );
		$this->assertStringStartsWith( '<h6 ', Block::split_list_by_year( $html, 9 ) );
	}

	/**
	 * Unexpected markup is left alone.
	 */
	public function test_split_list_by_year_bails_on_unexpected_markup(): void {
		$this->assertNull( Block::split_list_by_year( '<ul>' . $this->item( 1 ) . '</ul>', 3 ) );
		$this->assertNull( Block::split_list_by_year( '<div><p>Intro</p>' . $this->item( 1, '2024' ) . '</div>', 3 ) );
	}

	/**
	 * Headings are only added when the block asks for them.
	 */
	public function test_maybe_inject_year_headings_skips_when_not_grouping(): void {
		$content = '<ul>' . $this->item( 1 ) . '</ul>';
		$block   = new \WP_Block();

		$cases = array(
			'not our variation'  => array( 'groupByYear' => true ),
			'grouping off'       => array(
				'yearsBack'   => 5,
				'groupByYear' => false,
			),
			'not sorted by date' => array(
				'yearsBack'   => 5,
				'groupByYear' => true,
				'orderBy'     => 'title',
			),
		);

		$cases['marker already in content'] = array(
			'yearsBack'   => 5,
			'groupByYear' => true,
		);
		$content_with_marker                = '<ul>' . $this->item( 1, '1999' ) . '</ul>';

		foreach ( $cases as $label => $query ) {
			$html           = 'marker already in content' === $label ? $content_with_marker : $content;
			$block->context = array( 'query' => $query );
			$this->assertSame( $html, ( new Block() )->maybe_inject_year_headings( $html, array(), $block ), $label );
		}
	}
}
