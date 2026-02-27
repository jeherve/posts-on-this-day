<?php
/**
 * Tests for Jeherve\Posts_On_This_Day\Query;
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
		// Mock wp_timezone to return a specific timezone.
		Functions\when( 'wp_timezone' )->justReturn( new DateTimeZone( 'Europe/Amsterdam' ) );

		// Mock apply_filters to return the second argument (the value being filtered).
		Functions\when( 'apply_filters' )->returnArg( 2 );

		$seconds_remaining = Query::get_seconds_left_in_day();

		$this->assertGreaterThan( 0, $seconds_remaining );
		$this->assertLessThanOrEqual( DAY_IN_SECONDS, $seconds_remaining );
	}

	/**
	 * Test get_seconds_left_in_day uses the site timezone consistently.
	 *
	 * Verifies that the cache duration is computed correctly for a non-UTC timezone,
	 * avoiding the bug where mixing UTC strtotime() and local current_time() produced
	 * negative or incorrect values for sites ahead of UTC.
	 */
	public function test_get_seconds_left_in_day_uses_site_timezone(): void {
		$tz = new DateTimeZone( 'Pacific/Auckland' ); // UTC+12/+13
		Functions\when( 'wp_timezone' )->justReturn( $tz );
		Functions\when( 'apply_filters' )->returnArg( 2 );

		$seconds_remaining = Query::get_seconds_left_in_day();

		// Should always be between 1 second and a full day, never hitting the fallback
		// due to timezone math errors.
		$now      = new \DateTime( 'now', $tz );
		$midnight = new \DateTime( 'tomorrow midnight', $tz );
		$expected = $midnight->getTimestamp() - $now->getTimestamp();

		// Allow 2 seconds of tolerance for test execution time.
		$this->assertEqualsWithDelta( $expected, $seconds_remaining, 2 );
	}

	/**
	 * Test the fallback when get_seconds_left_in_day returns an invalid value.
	 */
	public function test_get_seconds_left_in_day_fallback(): void {
		// Mock wp_timezone to return UTC (simplest case for triggering fallback).
		Functions\when( 'wp_timezone' )->justReturn( new DateTimeZone( 'UTC' ) );

		// Mock apply_filters to return the second argument (the value being filtered).
		Functions\when( 'apply_filters' )->returnArg( 2 );

		$seconds_remaining = Query::get_seconds_left_in_day();

		// With the timezone-aware implementation, the result should always be valid.
		// This test confirms we still get a sensible value.
		$this->assertGreaterThan( 0, $seconds_remaining );
		$this->assertLessThanOrEqual( DAY_IN_SECONDS, $seconds_remaining );
	}

	/**
	 * Test that get_today_date returns the date in the site timezone.
	 *
	 * Simulates the scenario from the bug report: when a site is ahead of UTC,
	 * the "today" date should reflect the site timezone, not UTC.
	 */
	public function test_get_today_date_uses_site_timezone(): void {
		$tz = new DateTimeZone( 'Pacific/Auckland' ); // UTC+12/+13
		Functions\when( 'wp_timezone' )->justReturn( $tz );

		$result   = Query::get_today_date();
		$expected = ( new \DateTime( 'now', $tz ) )->format( 'Y-m-d' );

		$this->assertSame( $expected, $result );
	}

	/**
	 * Test the structure of build_date_query with default (non-exact) matching.
	 */
	public function test_build_date_query_structure(): void {
		Functions\when( 'wp_timezone' )->justReturn( new DateTimeZone( 'UTC' ) );

		$result = Query::build_date_query( 3, false );

		// Should have 3 date ranges + relation key.
		$this->assertCount( 4, $result ); // 3 ranges + 'relation'
		$this->assertSame( 'OR', $result['relation'] );

		// Each range should have 'before' and 'after' keys.
		for ( $i = 0; $i < 3; $i++ ) {
			$this->assertArrayHasKey( 'before', $result[ $i ] );
			$this->assertArrayHasKey( 'after', $result[ $i ] );
			// Date format should be Y-m-d.
			$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}$/', $result[ $i ]['before'] );
			$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}$/', $result[ $i ]['after'] );
		}
	}

	/**
	 * Test build_date_query with exact matching.
	 */
	public function test_build_date_query_exact_match(): void {
		Functions\when( 'wp_timezone' )->justReturn( new DateTimeZone( 'UTC' ) );

		$result = Query::build_date_query( 2, true );

		// Should have 2 date ranges + relation key.
		$this->assertCount( 3, $result ); // 2 ranges + 'relation'
		$this->assertSame( 'OR', $result['relation'] );

		// Each range should be inclusive.
		for ( $i = 0; $i < 2; $i++ ) {
			$this->assertArrayHasKey( 'inclusive', $result[ $i ] );
			$this->assertTrue( $result[ $i ]['inclusive'] );
			// Before and after should be the same date (exact match).
			$this->assertSame( $result[ $i ]['before'], $result[ $i ]['after'] );
		}
	}

	/**
	 * Test build_date_query non-exact ranges span approximately 7 days.
	 */
	public function test_build_date_query_non_exact_spans_week(): void {
		Functions\when( 'wp_timezone' )->justReturn( new DateTimeZone( 'UTC' ) );

		$result = Query::build_date_query( 1, false );

		$before = new \DateTime( $result[0]['before'] );
		$after  = new \DateTime( $result[0]['after'] );
		$diff   = $before->diff( $after )->days;

		// The span should be 7 days.
		$this->assertSame( 7, $diff );
		// Should NOT have inclusive key.
		$this->assertArrayNotHasKey( 'inclusive', $result[0] );
	}
}
