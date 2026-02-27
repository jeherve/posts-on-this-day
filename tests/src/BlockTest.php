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
			has_filter( 'query_loop_block_query_vars', array( $instance, 'filter_query_vars' ) ) !== false
		);
		$this->assertTrue(
			has_filter( 'rest_post_query', array( $instance, 'filter_rest_query' ) ) !== false
		);
		$this->assertTrue(
			has_filter( 'render_block_core/post-template', array( $instance, 'maybe_inject_year_headings' ) ) !== false
		);
	}

	/**
	 * Test that filter_query_vars adds date_query when yearsBack is present.
	 */
	public function test_filter_query_vars_adds_date_query(): void {
		Functions\when( 'wp_timezone' )->justReturn( new DateTimeZone( 'UTC' ) );

		$block          = new \stdClass();
		$block->context = array(
			'query' => array(
				'yearsBack'  => 5,
				'exactMatch' => false,
			),
		);

		$instance = new Block();
		$result   = $instance->filter_query_vars( array( 'post_type' => 'post' ), $block );

		$this->assertArrayHasKey( 'date_query', $result );
		$this->assertArrayHasKey( 'has_password', $result );
		$this->assertFalse( $result['has_password'] );
		$this->assertSame( 'OR', $result['date_query']['relation'] );
		// 5 years back = 5 date ranges + relation.
		$this->assertCount( 6, $result['date_query'] );
	}

	/**
	 * Test that filter_query_vars skips when yearsBack is absent.
	 */
	public function test_filter_query_vars_skips_without_years_back(): void {
		$block          = new \stdClass();
		$block->context = array(
			'query' => array(
				'postType' => 'post',
			),
		);

		$instance = new Block();
		$original = array( 'post_type' => 'post' );
		$result   = $instance->filter_query_vars( $original, $block );

		$this->assertSame( $original, $result );
	}

	/**
	 * Test that filter_query_vars with exactMatch true produces inclusive date ranges.
	 */
	public function test_filter_query_vars_with_exact_match(): void {
		Functions\when( 'wp_timezone' )->justReturn( new DateTimeZone( 'UTC' ) );

		$block          = new \stdClass();
		$block->context = array(
			'query' => array(
				'yearsBack'  => 3,
				'exactMatch' => true,
			),
		);

		$instance = new Block();
		$result   = $instance->filter_query_vars( array( 'post_type' => 'post' ), $block );

		$this->assertArrayHasKey( 'date_query', $result );
		$this->assertSame( 'OR', $result['date_query']['relation'] );
		// 3 years back = 3 date ranges + relation.
		$this->assertCount( 4, $result['date_query'] );

		// Each date range should be inclusive with matching before/after dates.
		for ( $i = 0; $i < 3; $i++ ) {
			$this->assertArrayHasKey( 'inclusive', $result['date_query'][ $i ] );
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
	 * Test that filter_rest_query adds date_query when yearsBack param is present.
	 */
	public function test_filter_rest_query_with_years_back(): void {
		Functions\when( 'wp_timezone' )->justReturn( new DateTimeZone( 'UTC' ) );

		$request = new \WP_REST_Request(
			array(
				'yearsBack'  => 3,
				'exactMatch' => false,
			)
		);

		$instance = new Block();
		$result   = $instance->filter_rest_query( array( 'post_type' => 'post' ), $request );

		$this->assertArrayHasKey( 'date_query', $result );
		$this->assertFalse( $result['has_password'] );
		$this->assertSame( 'OR', $result['date_query']['relation'] );
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
	 * Test that filter_rest_query with exactMatch produces inclusive date ranges.
	 */
	public function test_filter_rest_query_with_exact_match(): void {
		Functions\when( 'wp_timezone' )->justReturn( new DateTimeZone( 'UTC' ) );

		$request = new \WP_REST_Request(
			array(
				'yearsBack'  => 2,
				'exactMatch' => true,
			)
		);

		$instance = new Block();
		$result   = $instance->filter_rest_query( array( 'post_type' => 'post' ), $request );

		$this->assertArrayHasKey( 'date_query', $result );
		$this->assertFalse( $result['has_password'] );
		$this->assertSame( 'OR', $result['date_query']['relation'] );
		// 2 years back = 2 date ranges + relation.
		$this->assertCount( 3, $result['date_query'] );

		// Each date range should be inclusive with matching before/after dates.
		for ( $i = 0; $i < 2; $i++ ) {
			$this->assertArrayHasKey( 'inclusive', $result['date_query'][ $i ] );
			$this->assertTrue( $result['date_query'][ $i ]['inclusive'] );
			$this->assertSame( $result['date_query'][ $i ]['before'], $result['date_query'][ $i ]['after'] );
		}
	}
}
