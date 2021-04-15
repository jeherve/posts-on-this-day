<?php
/**
 * Tests for Jeherve\Posts_On_This_Day\Query;
 *
 * @package jeherve/posts-on-this-day
 */

namespace Jeherve\Posts_On_This_Day;

use WorDBless\BaseTestCase;

/**
 * Class Test_Query
 */
class Test_Query extends BaseTestCase {
	/**
	 * Test the output of the get_seconds_left_in_day method.
	 *
	 * @covers Jeherve\Posts_On_This_Day\Query::get_seconds_left_in_day
	 */
	public function test_get_seconds_left_in_day() {

		$seconds_remaining = Query::get_seconds_left_in_day();

		$this->assertGreaterThan( 0, $seconds_remaining );
		$this->assertLessThanOrEqual( DAY_IN_SECONDS, $seconds_remaining );
	}
}
