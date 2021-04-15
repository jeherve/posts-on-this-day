<?php
/**
 * Build and run WP Queries to fetch posts.
 *
 * @package jeherve/posts-on-this-day
 */

declare( strict_types=1 );

namespace Jeherve\Posts_On_This_Day;

use DateInterval;
use DateTime;
use WP_Query;

/**
 * Build and run WP Queries to fetch posts.
 */
class Query {
	/**
	 * Get posts to display.
	 *
	 * @param array $instance Saved widget options.
	 *
	 * @return array $posts Array of post IDs.
	 */
	public function get_posts( array $instance ): array {
		$posts      = array();
		$date_query = array();

		$max         = ! empty( $instance['max'] ) ? (int) $instance['max'] : 10; // How many posts do we want maximum?
		$back        = ! empty( $instance['back'] ) ? (int) $instance['back'] : 10; // How many years back to we want to go back?
		$types       = implode( '-', $instance['post_types'] );
		$exact_match = ! empty( $instance['exact_match'] ) ? (bool) $instance['exact_match'] : false;

		/*
		 * Let's attempt to cache data for a day
		 * to avoid running an expensive WP_Query
		 * that we know will return the same result for a day.
		 */
		$transient_key = sprintf(
			'jeherve_posts_on_this_day_%1$d_%2$d_%3$s_%4$s',
			$max,
			$back,
			esc_attr( $types ),
			( true === $exact_match ? 'exact' : 'aweek' )
		);

		$cached_posts = get_transient( $transient_key );
		if ( $cached_posts && is_array( $cached_posts ) ) {
			return $cached_posts;
		}

		// Loop to create an array of date ranges where we want to search for posts.
		$i = 1;
		while ( $back >= $i ) {
			$today         = new DateTime();
			$date_interval = sprintf( 'P%dY', $i );

			// This is either a week before, or the same day if you've chosen exact matching.
			$after_interval = true === $exact_match
				? 'P0D'
				: 'P7D';

			// Build the query for year iteration $i.
			$this_year_query = array(
				'before' => $today->sub( new DateInterval( $date_interval ) )->format( 'Y-m-d' ),
				'after'  => $today->sub( new DateInterval( $after_interval ) )->format( 'Y-m-d' ),
			);

			// If we're doing an exact match, we need to be inclusive since we'll be looking for posts before and after the same date.
			if ( true === $exact_match ) {
				$this_year_query['inclusive'] = true;
			}

			// Add that year to the over date query args.
			$date_query[] = $this_year_query;

			$i++;
		}

		// We are interested in posts for ANY of those dates.
		$date_query['relation'] = 'OR';

		// Make our query for posts.
		$posts = $this->query_posts( $date_query, $instance );

		set_transient( $transient_key, $posts, self::get_seconds_left_in_day() );

		return $posts;
	}

	/**
	 * Query for posts matching our date query.
	 *
	 * @param array $date_query WP Query date query arguments.
	 * @param array $instance   Saved widget options.
	 *
	 * @return array $posts Multidimensional array of post IDs per year.
	 */
	private function query_posts( array $date_query, array $instance ): array {
		$posts = array();

		$args = array(
			'post_type'      => $instance['post_types'],
			'posts_per_page' => $instance['max'],
			'date_query'     => $date_query,
		);

		/**
		 * Allow adjusting the query for a subset of posts.
		 *
		 * @since 1.1.0
		 *
		 * @param array $args WP_Query arguments.
		 */
		$args = apply_filters( 'jeherve_posts_on_this_day_query_args', $args );

		$query = new WP_Query( $args );

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();

				$post_year             = get_the_date( 'Y' );
				$posts[ $post_year ][] = $query->post->ID;
			}
			wp_reset_postdata();
		}

		return $posts;
	}

	/**
	 * Get the number of seconds left in the day.
	 *
	 * We want to create a transient that will expire at midnight on our day,
	 * so let's generate how many seconds are left betwwen the time the transient is generated
	 * and midnight, in the timezone of the site.
	 *
	 * @return int $seconds Number of seconds left until midnight.
	 */
	public static function get_seconds_left_in_day(): int {
		$time_tonight = (int) strtotime( 'today 24:00' );
		$time_now     = (int) current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- we specifically want the current timestamp.

		// Seconds left until midnight.
		$seconds_remaining = $time_tonight - $time_now;

		/*
		 * Set a default fallback in case we get weird values from above.
		 * This should not happen, but ¯\_(ツ)_/¯
		 */
		if ( 0 >= $seconds_remaining || 86400 < $seconds_remaining ) {
			$seconds_remaining = DAY_IN_SECONDS; // Default: a full day, i.e. 86400 seconds.
		}

		/**
		 * Allow filtering the time the data is cached.
		 *
		 * @param int $seconds_remaining Number of seconds.
		 */
		return (int) apply_filters( 'jeherve_posts_on_this_day_cache_duration', $seconds_remaining );
	}
}
