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

		$max   = ! empty( $instance['max'] ) ? (int) $instance['max'] : 10; // How many posts do we want maximum?
		$back  = ! empty( $instance['back'] ) ? (int) $instance['back'] : 10; // How many years back to we want to go back?
		$types = implode( '-', $instance['post_types'] );

		/*
		 * Let's attempt to cache data for a day
		 * to avoid running an expensive WP_Query
		 * that we know will return the same result for a day.
		 */
		$transient_key = sprintf(
			'jeherve_posts_on_this_day_%1$d_%2$d_%3$s',
			$max,
			$back,
			esc_attr( $types )
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
			$date_query[]  = array(
				'before' => $today->sub( new DateInterval( $date_interval ) )->format( 'Y-m-d' ),
				'after'  => $today->sub( new DateInterval( 'P7D' ) )->format( 'Y-m-d' ),
			);

			$i++;
		}

		// We are interested in posts for ANY of those dates.
		$date_query['relation'] = 'OR';

		// Make our query for posts.
		$posts = $this->query_posts( $date_query, $instance );

		set_transient( $transient_key, $posts, DAY_IN_SECONDS );

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
}
