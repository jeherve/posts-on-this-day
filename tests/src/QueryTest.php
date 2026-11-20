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
	 * Build a date query from a fixed day, in UTC.
	 *
	 * @param string $today       Day to look back from, Y-m-d.
	 * @param int    $years_back  Years to look back.
	 * @param bool   $exact_match Exact day or week window.
	 *
	 * @return array
	 */
	private function date_query_from( string $today, int $years_back, bool $exact_match ): array {
		return Query::build_date_query(
			$years_back,
			$exact_match,
			new \DateTimeImmutable( $today . ' 15:30:00', new DateTimeZone( 'UTC' ) )
		);
	}

	/**
	 * The week window covers the 7 days ending on today's date, today included.
	 *
	 * Regression test: the window used to stop the day before, so posts published
	 * on this exact day in past years were left out.
	 */
	public function test_build_date_query_week_window_includes_today(): void {
		$result = $this->date_query_from( '2026-11-20', 2, false );

		$this->assertSame(
			array(
				array(
					'after'     => '2025-11-14',
					'before'    => '2025-11-20',
					'inclusive' => true,
				),
				array(
					'after'     => '2024-11-14',
					'before'    => '2024-11-20',
					'inclusive' => true,
				),
				'relation' => 'OR',
			),
			$result
		);
	}

	/**
	 * Exact matching uses a single inclusive day per year.
	 */
	public function test_build_date_query_exact_match(): void {
		$result = $this->date_query_from( '2026-11-20', 3, true );

		$this->assertCount( 4, $result );
		$this->assertSame( 'OR', $result['relation'] );
		$this->assertSame(
			array(
				'after'     => '2023-11-20',
				'before'    => '2023-11-20',
				'inclusive' => true,
			),
			$result[2]
		);
	}

	/**
	 * The current year is never part of the query.
	 */
	public function test_build_date_query_excludes_current_year(): void {
		$result = $this->date_query_from( '2026-01-03', 5, false );

		foreach ( array_filter( $result, 'is_array' ) as $range ) {
			$this->assertLessThan( '2026-01-01', $range['before'] );
		}
	}

	/**
	 * Early in January, the week window reaches back into December of the previous year.
	 */
	public function test_build_date_query_week_window_crosses_year_boundary(): void {
		$result = $this->date_query_from( '2026-01-03', 1, false );

		$this->assertSame( '2024-12-28', $result[0]['after'] );
		$this->assertSame( '2025-01-03', $result[0]['before'] );
	}

	/**
	 * On 29 February, years without a leap day fall back to 28 February.
	 *
	 * Regression test: DateTime arithmetic used to turn 29 February into 1 March.
	 */
	public function test_build_date_query_leap_day_in_non_leap_years(): void {
		$result = $this->date_query_from( '2028-02-29', 4, true );

		$this->assertSame( array( '2027-02-28', '2027-02-28' ), array( $result[0]['after'], $result[0]['before'] ) );
		$this->assertSame( array( '2026-02-28', '2026-02-28' ), array( $result[1]['after'], $result[1]['before'] ) );
		$this->assertSame( array( '2024-02-29', '2024-02-29' ), array( $result[3]['after'], $result[3]['before'] ) );
	}

	/**
	 * On 28 February of a non-leap year, leap years also include 29 February.
	 */
	public function test_build_date_query_includes_leap_day_on_28_february(): void {
		$result = $this->date_query_from( '2027-02-28', 3, true );

		// 2026 and 2025 have no leap day; 2024 does.
		$this->assertSame( '2026-02-28', $result[0]['before'] );
		$this->assertSame( '2025-02-28', $result[1]['before'] );
		$this->assertSame( '2024-02-28', $result[2]['after'] );
		$this->assertSame( '2024-02-29', $result[2]['before'] );
	}

	/**
	 * In a leap year, 28 February is just 28 February.
	 */
	public function test_build_date_query_28_february_in_leap_year(): void {
		$result = $this->date_query_from( '2028-02-28', 4, true );

		$this->assertSame( '2024-02-28', $result[3]['before'] );
	}

	/**
	 * Without an explicit day, "today" is computed in the site's timezone.
	 */
	public function test_build_date_query_defaults_to_today_in_site_timezone(): void {
		$tz = new DateTimeZone( 'Pacific/Auckland' );
		Functions\when( 'wp_timezone' )->justReturn( $tz );

		$result = Query::build_date_query( 1, true );

		$now = new \DateTimeImmutable( 'now', $tz );
		$this->assertStringStartsWith(
			sprintf( '%d-%s-', (int) $now->format( 'Y' ) - 1, $now->format( 'm' ) ),
			$result[0]['before']
		);
	}

	/**
	 * Zero or negative years produce no date range at all.
	 */
	public function test_build_date_query_with_no_years(): void {
		$this->assertSame( array( 'relation' => 'OR' ), $this->date_query_from( '2026-11-20', 0, false ) );
	}
}
