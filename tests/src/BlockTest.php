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
	 * IDs of the posts whose date was looked up during a test.
	 *
	 * @var int[]
	 */
	private array $date_lookups = array();

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
			has_filter( 'the_posts', array( $instance, 'capture_grouped_posts' ) ) !== false
		);
		$this->assertTrue(
			has_filter( 'render_block_core/null', array( $instance, 'mark_grouped_item' ) ) !== false
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
	 * A post object with a given year, as returned by WP_Query.
	 *
	 * @param int    $id   Post ID.
	 * @param string $year Publication year.
	 *
	 * @return \stdClass
	 */
	private function post( int $id, string $year ): \stdClass {
		$post            = new \stdClass();
		$post->ID        = $id;
		$post->post_date = "{$year}-11-18 10:00:00";
		return $post;
	}

	/**
	 * Render a grouping Post Template block the way core does, through the plugin's hooks.
	 *
	 * Mirrors render_block_core_post_template(): build the query vars, run the query,
	 * render each post's inner blocks as `core/null`, wrap each in `<li>`, then apply
	 * the block's render filter.
	 *
	 * @param Block             $instance Plugin instance.
	 * @param array             $query    Query attributes in the block context.
	 * @param \stdClass[]       $posts    Posts the query returns.
	 * @param array<int,string> $contents Rendered inner content of each post, by index.
	 *
	 * @return string
	 */
	private function render_post_template( Block $instance, array $query, array $posts, array $contents = array() ): string {
		Functions\when( 'wp_timezone' )->justReturn( new DateTimeZone( 'UTC' ) );
		$this->date_lookups = array();
		Functions\when( 'get_the_date' )->alias(
			function ( $format, $post ) {
				$this->date_lookups[] = is_object( $post ) ? $post->ID : (int) $post;
				return is_object( $post ) ? substr( $post->post_date, 0, 4 ) : '1999';
			}
		);
		Functions\stubEscapeFunctions();

		$block               = new \WP_Block();
		$block->name         = 'core/post-template';
		$block->context      = array( 'query' => $query );
		$block->parsed_block = $this->template_block();

		$query_vars           = $instance->filter_query_vars( array( 'post_type' => 'post' ), $block );
		$wp_query             = new \stdClass();
		$wp_query->query_vars = $query_vars;
		$posts                = $instance->capture_grouped_posts( $posts, $wp_query );

		$html = '<ul class="wp-block-post-template is-layout-grid">';
		foreach ( $posts as $index => $post ) {
			$inner = $instance->mark_grouped_item( $contents[ $index ] ?? "Post {$post->ID}", $this->item_block() );
			$html .= sprintf( '<li class="wp-block-post post-%d post type-post">%s</li>', $post->ID, $inner );
		}
		$html .= '</ul>';

		return $instance->maybe_inject_year_headings( $html, array(), $block );
	}

	/**
	 * Parsed Post Template block, as in the block's markup.
	 *
	 * @return array
	 */
	private function template_block(): array {
		return array(
			'blockName'   => 'core/post-template',
			'attrs'       => array(),
			'innerBlocks' => array(
				array(
					'blockName'   => 'core/post-title',
					'attrs'       => array( 'isLink' => true ),
					'innerBlocks' => array(),
				),
			),
		);
	}

	/**
	 * The `core/null` copy core renders for each post of the Post Template block.
	 *
	 * @return array
	 */
	private function item_block(): array {
		return array_merge( $this->template_block(), array( 'blockName' => 'core/null' ) );
	}

	/**
	 * Expected markup for a heading.
	 *
	 * @param string $year  Year.
	 * @param int    $level Heading level.
	 *
	 * @return string
	 */
	private function heading( string $year, int $level = 3 ): string {
		return sprintf( '<h%1$d class="wp-block-heading posts-on-this-day-year-heading">%2$s</h%1$d>', $level, $year );
	}

	/**
	 * Posts are split into one list per year, each after a heading, with no markers left behind.
	 */
	public function test_year_headings_group_posts(): void {
		$result = $this->render_post_template(
			new Block(),
			array(
				'yearsBack'   => 5,
				'groupByYear' => true,
			),
			array( $this->post( 1, '2024' ), $this->post( 2, '2024' ), $this->post( 3, '2020' ) )
		);

		$list = '<ul class="wp-block-post-template is-layout-grid">';
		$this->assertSame(
			$this->heading( '2024' ) . $list
			. '<li class="wp-block-post post-1 post type-post">Post 1</li>'
			. '<li class="wp-block-post post-2 post type-post">Post 2</li></ul>'
			. $this->heading( '2020' ) . $list
			. '<li class="wp-block-post post-3 post type-post">Post 3</li></ul>',
			$result
		);
	}

	/**
	 * Post content imitating a post item cannot add a heading or reveal another post's year.
	 *
	 * Regression test: headings used to take post IDs from `li.wp-block-post` classes anywhere
	 * in the markup, so content with `<li class="wp-block-post post-123">` made the plugin look
	 * up and print the year of any post, private ones included.
	 */
	public function test_year_headings_ignore_fake_post_items_in_content(): void {
		$fake = '<ul><li class="wp-block-post post-999 post">Fake</li></ul><p>Text</p>';

		$result = $this->render_post_template(
			new Block(),
			array(
				'yearsBack'   => 5,
				'groupByYear' => true,
			),
			array( $this->post( 1, '2024' ), $this->post( 2, '2023' ) ),
			array( 0 => $fake )
		);

		$this->assertSame( array( 1, 2 ), $this->date_lookups );
		$this->assertSame( 2, substr_count( $result, 'posts-on-this-day-year-heading' ) );
		$this->assertStringContainsString( $this->heading( '2024' ), $result );
		$this->assertStringContainsString( $this->heading( '2023' ), $result );
		$this->assertStringContainsString( '<li class="wp-block-post post-1 post type-post">' . $fake . '</li></ul>' . $this->heading( '2023' ), $result );
	}

	/**
	 * Post content imitating the plugin's markers cannot move or add headings.
	 *
	 * Regression test: the split points used to be found again with a regex over the whole
	 * markup, so content carrying the same marker could choose where lists were split.
	 */
	public function test_year_headings_ignore_fake_markers_in_content(): void {
		$fake = '<li data-posts-on-this-day-year="1999">x</li><!--posts-on-this-day:00000000000000000000000000000000:1-->';

		$result = $this->render_post_template(
			new Block(),
			array(
				'yearsBack'   => 5,
				'groupByYear' => true,
			),
			array( $this->post( 1, '2024' ), $this->post( 2, '2024' ) ),
			array( 0 => $fake )
		);

		$this->assertSame( array( 1, 2 ), $this->date_lookups );
		$this->assertSame( 1, substr_count( $result, 'posts-on-this-day-year-heading' ) );
		$this->assertStringStartsWith( $this->heading( '2024' ), $result );
		$this->assertStringNotContainsString( '1999</h', $result );
		$this->assertStringContainsString( $fake, $result );
	}

	/**
	 * Content that closes its item and opens a fake top-level `li.wp-block-post` does not
	 * create a heading or a lookup.
	 */
	public function test_year_headings_ignore_fake_top_level_items_in_content(): void {
		$fake = 'Start</li><li class="wp-block-post post-123 post">Fake item';

		$result = $this->render_post_template(
			new Block(),
			array(
				'yearsBack'   => 5,
				'groupByYear' => true,
			),
			array( $this->post( 1, '2024' ), $this->post( 2, '2023' ) ),
			array( 0 => $fake )
		);

		$this->assertSame( array( 1, 2 ), $this->date_lookups );
		$this->assertSame( 2, substr_count( $result, 'posts-on-this-day-year-heading' ) );
		$this->assertStringContainsString( $fake . '</li></ul>' . $this->heading( '2023' ), $result );
	}

	/**
	 * A private or password-protected post referenced from content never has its date looked up.
	 *
	 * The mocked lookup answers 1999 for any post that is not one of the query's results.
	 */
	public function test_year_headings_never_look_up_posts_referenced_from_content(): void {
		$private_post_id = 555;
		$fake            = sprintf(
			'<ul><li class="wp-block-post post-%1$d status-private">x</li></ul></li><li class="wp-block-post post-%1$d post-password-required">y',
			$private_post_id
		);

		$result = $this->render_post_template(
			new Block(),
			array(
				'yearsBack'   => 5,
				'groupByYear' => true,
			),
			array( $this->post( 1, '2024' ), $this->post( 2, '2024' ) ),
			array( 1 => $fake )
		);

		$this->assertNotContains( $private_post_id, $this->date_lookups );
		$this->assertSame( array( 1, 2 ), $this->date_lookups );
		$this->assertStringNotContainsString( '1999', $result );
		$this->assertSame( 1, substr_count( $result, 'posts-on-this-day-year-heading' ) );
	}

	/**
	 * Items of a loop nested inside a post are not taken for the block's own items.
	 *
	 * Their `core/null` blocks render before the outer post's, so marking them would split
	 * the list inside that post.
	 */
	public function test_year_headings_ignore_items_of_nested_loops(): void {
		$instance = new Block();
		$block    = new \WP_Block();

		$block->name         = 'core/post-template';
		$block->parsed_block = $this->template_block();
		$block->context      = array(
			'query' => array(
				'yearsBack'   => 5,
				'groupByYear' => true,
			),
		);

		Functions\when( 'wp_timezone' )->justReturn( new DateTimeZone( 'UTC' ) );
		Functions\when( 'get_the_date' )->alias( static fn( $format, $post ) => substr( $post->post_date, 0, 4 ) );
		Functions\stubEscapeFunctions();

		$wp_query             = new \stdClass();
		$wp_query->query_vars = $instance->filter_query_vars( array( 'post_type' => 'post' ), $block );
		$instance->capture_grouped_posts( array( $this->post( 1, '2024' ), $this->post( 2, '2023' ) ), $wp_query );

		$nested_block = array(
			'blockName'   => 'core/null',
			'attrs'       => array(),
			'innerBlocks' => array( array( 'blockName' => 'core/post-excerpt' ) ),
		);
		$nested       = $instance->mark_grouped_item( 'Nested', $nested_block );
		$first        = $instance->mark_grouped_item( 'One<ul><li>' . $nested . '</li></ul>', $this->item_block() );
		$second       = $instance->mark_grouped_item( 'Two', $this->item_block() );
		$html         = '<ul><li class="wp-block-post post-1">' . $first . '</li><li class="wp-block-post post-2">' . $second . '</li></ul>';

		$this->assertSame(
			$this->heading( '2024' ) . '<ul><li class="wp-block-post post-1">One<ul><li>Nested</li></ul></li></ul>'
			. $this->heading( '2023' ) . '<ul><li class="wp-block-post post-2">Two</li></ul>',
			$instance->maybe_inject_year_headings( $html, array(), $block )
		);
	}

	/**
	 * More markers than posts leaves the list alone.
	 */
	public function test_split_list_by_year_rejects_extra_items(): void {
		$token = str_repeat( 'c', 32 );
		$html  = '<ul><li class="wp-block-post post-1"><!--posts-on-this-day:' . $token . ':0-->One'
			. '<ul><li><!--posts-on-this-day:' . $token . ':1-->Nested</li></ul></li></ul>';

		$this->assertNull( Block::split_list_by_year( $html, $token, array( '2024' ), 3 ) );
	}

	/**
	 * Only the first list keeps an HTML anchor, so IDs stay unique, and heading levels are clamped.
	 */
	public function test_split_list_by_year_keeps_id_on_first_list_and_clamps_level(): void {
		Functions\stubEscapeFunctions();

		$token = str_repeat( 'a', 32 );
		$html  = '<ul id="memories" class="list">'
			. '<li class="wp-block-post post-1"><!--posts-on-this-day:' . $token . ':0-->One</li>'
			. '<li class="wp-block-post post-2"><!--posts-on-this-day:' . $token . ':1-->Two</li></ul>';

		$result = Block::split_list_by_year( $html, $token, array( '2024', '2023' ), 9 );

		$this->assertSame( 1, substr_count( $result, 'id="memories"' ) );
		$this->assertStringStartsWith( '<h6 ', $result );
		$this->assertStringContainsString( '</ul><h6 class="wp-block-heading posts-on-this-day-year-heading">2023</h6><ul class="list">', $result );
		$this->assertStringNotContainsString( $token, $result );
	}

	/**
	 * A marker that does not directly follow its item's opening tag leaves the list alone.
	 */
	public function test_split_list_by_year_rejects_misplaced_markers(): void {
		$token = str_repeat( 'b', 32 );
		$html  = '<ul><li class="wp-block-post post-1"><p>Intro</p><!--posts-on-this-day:' . $token . ':0-->One</li></ul>';

		$this->assertNull( Block::split_list_by_year( $html, $token, array( '2024' ), 3 ) );
	}

	/**
	 * Headings are only added when the block asks for them, sorted by date.
	 */
	public function test_year_headings_only_when_requested(): void {
		$cases = array(
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

		foreach ( $cases as $label => $query ) {
			$result = $this->render_post_template( new Block(), $query, array( $this->post( 1, '2024' ) ) );
			$this->assertSame( '<ul class="wp-block-post-template is-layout-grid"><li class="wp-block-post post-1 post type-post">Post 1</li></ul>', $result, $label );
		}
	}

	/**
	 * Other Query Loop blocks never get markers or headings.
	 */
	public function test_other_post_templates_are_left_alone(): void {
		$instance = new Block();

		$this->assertSame( 'Content', $instance->mark_grouped_item( 'Content', $this->item_block() ) );

		$block          = new \WP_Block();
		$block->name    = 'core/post-template';
		$block->context = array( 'query' => array( 'postType' => 'post' ) );

		$this->assertSame( '<ul></ul>', $instance->maybe_inject_year_headings( '<ul></ul>', array(), $block ) );
	}
}
