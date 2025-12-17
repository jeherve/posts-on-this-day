<?php
/**
 * Tests for Jeherve\Posts_On_This_Day\Query;
 *
 * @package jeherve/posts-on-this-day
 */

namespace Jeherve\Posts_On_This_Day;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Class QueryTest
 */
#[CoversClass( Query::class )]
class QueryTest extends TestCase {
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
	 * Test the output of the get_seconds_left_in_day method.
	 */
	public function test_get_seconds_left_in_day(): void {
		// Mock current_time to return a specific timestamp (noon).
		Functions\when( 'current_time' )->justReturn( strtotime( 'today 12:00' ) );

		// Mock apply_filters to return the second argument (the value being filtered).
		Functions\when( 'apply_filters' )->returnArg( 2 );

		$seconds_remaining = Query::get_seconds_left_in_day();

		$this->assertGreaterThan( 0, $seconds_remaining );
		$this->assertLessThanOrEqual( DAY_IN_SECONDS, $seconds_remaining );
	}

	/**
	 * Test the fallback when get_seconds_left_in_day returns an invalid value.
	 */
	public function test_get_seconds_left_in_day_fallback(): void {
		// Mock current_time to return a timestamp that would result in negative seconds.
		Functions\when( 'current_time' )->justReturn( strtotime( 'tomorrow 12:00' ) );

		// Mock apply_filters to return the second argument (the value being filtered).
		Functions\when( 'apply_filters' )->returnArg( 2 );

		$seconds_remaining = Query::get_seconds_left_in_day();

		// Should fall back to DAY_IN_SECONDS.
		$this->assertEquals( DAY_IN_SECONDS, $seconds_remaining );
	}
}
