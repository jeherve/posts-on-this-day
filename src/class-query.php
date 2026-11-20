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
use DateTimeImmutable;
use DateTimeInterface;
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
		$posts = array();

		$max         = ! empty( $instance['max'] ) ? (int) $instance['max'] : 10; // How many posts do we want maximum?
		$types       = implode( '-', $instance['post_types'] );
		$exact_match = ! empty( $instance['exact_match'] ) ? (bool) $instance['exact_match'] : false;

		$back = self::get_years_back( $instance );

		/*
		 * Let's attempt to cache data for a day
		 * to avoid running an expensive WP_Query
		 * that we know will return the same result for a day.
		 */
		$transient_key = sprintf(
			'jeherve_posts_on_this_day_%1$d_%2$d_%3$s_%4$s_%5$s',
			$max,
			$back,
			esc_attr( $types ),
			( true === $exact_match ? 'exact' : 'aweek' ),
			self::get_today_date()
		);

		$cached_posts = get_transient( $transient_key );
		if ( $cached_posts && is_array( $cached_posts ) ) {
			return $cached_posts;
		}

		$date_query = self::build_date_query( $back, $exact_match );

		// Make our query for posts.
		$posts = $this->query_posts( $date_query, $instance );

		set_transient( $transient_key, $posts, self::get_seconds_left_in_day() );

		return $posts;
	}

	/**
	 * How many years back the widget looks.
	 *
	 * @since 2.0.0
	 *
	 * @param array $instance Saved widget options.
	 *
	 * @return int At least 1.
	 */
	public static function get_years_back( array $instance ): int {
		$back = ! empty( $instance['back'] ) ? (int) $instance['back'] : 10;

		/**
		 * Filter the amount of years back to fetch posts from.
		 *
		 * Careful though; that can make for some really expensive queries,
		 * so could slow the first loading of the page where the widget is displayed once a day.
		 *
		 * @since 1.2.0
		 *
		 * @param int $back Amount of years back to fetch posts from.
		 */
		$back = apply_filters( 'jeherve_posts_on_this_day_query_back', $back );

		// Callbacks can return strings (from get_option(), say) or floats; keep a whole number of at least one year.
		return max( 1, (int) $back );
	}

	/**
	 * Build a date query array for fetching posts "on this day" in previous years.
	 *
	 * Each past year gets its own inclusive range: either the exact day, or the
	 * 7 days ending on that day. Dates are computed in the site's timezone, which
	 * is also the timezone of the `post_date` column the query runs against.
	 *
	 * On 29 February, years without a leap day use 28 February instead. On 28 February
	 * of a non-leap year, leap years also include 29 February, so those posts still
	 * come up once a year.
	 *
	 * @since 2.0.0
	 *
	 * @param int                    $years_back  How many years back to search.
	 * @param bool                   $exact_match Whether to match the exact day (true) or within a week (false).
	 * @param DateTimeInterface|null $today       The day to look back from. Defaults to today in the site's timezone.
	 *
	 * @return array Date query array suitable for WP_Query's date_query parameter.
	 */
	public static function build_date_query( int $years_back, bool $exact_match, ?DateTimeInterface $today = null ): array {
		$today = null === $today
			? new DateTimeImmutable( 'now', wp_timezone() )
			: DateTimeImmutable::createFromInterface( $today );

		$year  = (int) $today->format( 'Y' );
		$month = (int) $today->format( 'n' );
		$day   = (int) $today->format( 'j' );

		$date_query = array();

		for ( $i = 1; $i <= $years_back; $i++ ) {
			$past_year = $year - $i;
			$first_day = $day;
			$last_day  = $day;

			if ( 2 === $month && 29 === $day && ! self::is_leap_year( $past_year ) ) {
				$first_day = 28;
				$last_day  = 28;
			} elseif ( 2 === $month && 28 === $day && ! self::is_leap_year( $year ) && self::is_leap_year( $past_year ) ) {
				$last_day = 29;
			}

			$after  = $today->setDate( $past_year, $month, $first_day );
			$before = $today->setDate( $past_year, $month, $last_day );

			if ( ! $exact_match ) {
				$after = $after->sub( new DateInterval( 'P6D' ) );
			}

			$date_query[] = array(
				'after'     => $after->format( 'Y-m-d' ),
				'before'    => $before->format( 'Y-m-d' ),
				'inclusive' => true,
			);
		}

		// We are interested in posts for ANY of those dates.
		$date_query['relation'] = 'OR';

		return $date_query;
	}

	/**
	 * Get how many years back the oldest published post of a post type goes.
	 *
	 * The lookup is a single indexed query, cached for the request; WP_Query's own
	 * query cache also keeps it across requests when a persistent object cache is in use.
	 *
	 * @since 2.0.0
	 *
	 * @param string|string[] $post_type Post type(s) to look at.
	 *
	 * @return int Number of past years with possible posts, at least 1.
	 */
	public static function get_years_of_history( $post_type ): int {
		static $years = array();

		$key = implode( ',', (array) $post_type );
		if ( isset( $years[ $key ] ) ) {
			return $years[ $key ];
		}

		$oldest = get_posts(
			array(
				'post_type'              => $post_type,
				'post_status'            => 'publish',
				'posts_per_page'         => 1,
				'orderby'                => 'date',
				'order'                  => 'ASC',
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$this_year   = (int) ( new DateTimeImmutable( 'now', wp_timezone() ) )->format( 'Y' );
		$oldest_year = empty( $oldest ) ? $this_year : (int) substr( $oldest[0]->post_date, 0, 4 );

		$years[ $key ] = max( 1, $this_year - $oldest_year );

		return $years[ $key ];
	}

	/**
	 * Whether a year has a 29 February.
	 *
	 * @param int $year Year.
	 *
	 * @return bool
	 */
	private static function is_leap_year( int $year ): bool {
		return ( 0 === $year % 4 && 0 !== $year % 100 ) || 0 === $year % 400;
	}

	/**
	 * WP_Query arguments for the widget's posts.
	 *
	 * Sticky posts are not pulled to the top: WordPress would otherwise add every sticky
	 * post to the results whatever its date, and go past the widget's maximum.
	 *
	 * @since 2.0.0
	 *
	 * @param array $date_query WP Query date query arguments.
	 * @param array $instance   Saved widget options.
	 *
	 * @return array
	 */
	public static function get_query_args( array $date_query, array $instance ): array {
		return array(
			'post_type'           => $instance['post_types'],
			'posts_per_page'      => $instance['max'],
			'date_query'          => $date_query,
			'has_password'        => false,
			'post_status'         => 'publish',
			'ignore_sticky_posts' => true,
		);
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

		$args = self::get_query_args( $date_query, $instance );

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
		$site_tz  = wp_timezone();
		$now      = new DateTime( 'now', $site_tz );
		$midnight = new DateTime( 'tomorrow midnight', $site_tz );

		// Seconds left until midnight in the site's timezone.
		$seconds_remaining = $midnight->getTimestamp() - $now->getTimestamp();

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

	/**
	 * Get today's date in the site's timezone.
	 *
	 * @since 1.5.7
	 *
	 * @return string Today's date in Y-m-d format.
	 */
	public static function get_today_date(): string {
		return ( new DateTime( 'now', wp_timezone() ) )->format( 'Y-m-d' );
	}
}
