<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Plugin Name: Posts On This Day
 * Plugin URI: https://jeremy.hu/my-plugins/posts-on-this-day/
 * Description: Widget to display a list of posts published "on this day" in years past. A good little bit of nostalgia for your blog.
 * Author: Jeremy Herve
 * Version: 1.1.3
 * Author URI: https://jeremy.hu
 * License: GPL2+
 * Text Domain: posts-on-this-day
 * Requires at least: 5.6
 * Requires PHP: 5.6
 *
 * @package jeherve/posts-on-this-day
 */

namespace Jeherve\Posts_On_This_Day;

use DateTime;
use DateInterval;
use WP_Query;
use WP_Widget;

/**
* Register the widget for use in Appearance -> Widgets
*/
add_action(
	'widgets_init',
	function() {
		register_widget( '\Jeherve\Posts_On_This_Day\Posts_On_This_Day_Widget' );
	}
);

/**
 * My Widget
 */
class Posts_On_This_Day_Widget extends WP_Widget {
	/**
	 * Register widget with WordPress.
	 */
	public function __construct() {
		parent::__construct(
			'posts_on_this_day',
			esc_html__( 'Posts On This Day', 'posts-on-this-day' ),
			array(
				'classname'                   => 'widget_posts_on_this_day',
				'description'                 => __( 'Display a list of posts from years past', 'posts-on-this-day' ),
				'customize_selective_refresh' => true,
			)
		);
	}

	/**
	 * Front-end display of widget.
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved widget options.
	 */
	public function widget( $args, $instance ) {
		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		$instance = wp_parse_args(
			$instance,
			array(
				'title' => '',
				'max'   => 10,
				'back'  => 10,
			)
		);

		// Display posts.
		$posts = $this->get_posts( $instance );
		if ( ! empty( $posts ) ) {
			/** This filter is documented in core/src/wp-includes/default-widgets.php */
			$title = apply_filters( 'widget_title', $instance['title'] );

			// Display title.
			if ( '' !== $title ) {
				echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			// Open markup.
			echo '<div class="posts_on_this_day">';

			foreach ( $posts as $id ) {
				echo $this->display_post( $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}

			// Close markup.
			echo '</div>';
		}

		echo "\n" . $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @see WP_Widget::update()
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 *
	 * @return array Updated safe values to be saved.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = $old_instance;

		$instance['title'] = wp_kses( $new_instance['title'], array() );

		$max = (int) $new_instance['max'];
		if ( $max ) {
			$instance['max'] = min( $max, 20 );
		} else {
			$instance['max'] = 10;
		}

		$back = (int) $new_instance['back'];
		if ( $back ) {
			$instance['back'] = min( $back, 20 );
		} else {
			$instance['back'] = 10;
		}

		return $instance;
	}

	/**
	 * Back end widget form.
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Previously saved values from database.
	 */
	public function form( $instance ) {
		// Defaults.
		$instance = wp_parse_args(
			(array) $instance,
			array(
				'title' => '',
				'max'   => 10,
				'back'  => 10,
			)
		);

		printf(
			'<p><label for="%1$s">%2$s<input class="widefat" id="%1$s" name="%3$s" type="text" value="%4$s" /></label></p>',
			esc_attr( $this->get_field_id( 'title' ) ),
			esc_html__( 'Widget title:', 'posts-on-this-day' ),
			esc_attr( $this->get_field_name( 'title' ) ),
			esc_attr( $instance['title'] )
		);

		printf(
			'<p><label for="%1$s">%2$s</label><input class="widefat" id="%1$s" name="%3$s" type="number" min="1" value="%4$s" /></p>',
			esc_attr( $this->get_field_id( 'max' ) ),
			esc_html__( 'Maximum number of posts to display:', 'posts-on-this-day' ),
			esc_attr( $this->get_field_name( 'max' ) ),
			esc_attr( $instance['max'] )
		);

		printf(
			'<p><label for="%1$s">%2$s</label><input class="widefat" id="%1$s" name="%3$s" type="number" min="1" max="20" value="%4$s" /></p>',
			esc_attr( $this->get_field_id( 'back' ) ),
			esc_html__( 'How many years back do you want to look for posts?', 'posts-on-this-day' ),
			esc_attr( $this->get_field_name( 'back' ) ),
			esc_attr( $instance['back'] )
		);
	}

	/**
	 * Get posts to display.
	 *
	 * @param array $instance Saved widget options.
	 *
	 * @return array $posts Array of post IDs.
	 */
	private function get_posts( $instance ) {
		$posts      = array();
		$date_query = array();

		$max  = ! empty( $instance['max'] ) ? (int) $instance['max'] : 10; // How many posts do we want maximum?
		$back = ! empty( $instance['back'] ) ? (int) $instance['back'] : 10; // How many years back to we want to go back?

		/*
		 * Let's attempt to cache data for a day
		 * to avoid running an expensive WP_Query
		 * that we know will return the same result for a day.
		 */
		$transient_key = sprintf(
			'jeherve_posts_on_this_day_%1$d_%2$d',
			$max,
			$back
		);

		$cached_posts = get_transient( $transient_key );
		if ( $cached_posts ) {
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
		$posts = $this->query_posts( $date_query, $max );

		// Make sure we never return more posts than set.
		$posts = array_slice( $posts, 0, $max );

		set_transient( $transient_key, $posts, DAY_IN_SECONDS );

		return $posts;
	}

	/**
	 * Query for posts matching our date query.
	 *
	 * @param array $date_query WP Query date query arguments.
	 * @param int   $max        Maximum number of posts to look for.
	 *
	 * @return array $posts Array of post IDs.
	 */
	private function query_posts( $date_query, $max ) {
		$posts = array();

		$args = array(
			'post_type'      => 'post',
			'posts_per_page' => $max,
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

				$posts[] = $query->post->ID;
			}
			wp_reset_postdata();
		}

		return $posts;
	}

	/**
	 * Display a single post.
	 *
	 * @param int $id Post id.
	 *
	 * @return string $markup Markup for a single post.
	 */
	private function display_post( $id ) {
		$markup = sprintf(
			'<div class="posts_on_this_day__article"><a href="%2$s">%4$s</a><div class="posts_on_this_day__title"><a href="%2$s">%3$s (%1$s)</a></div></div>',
			esc_html( get_the_date( 'Y', $id ) ),
			esc_url( get_permalink( $id ) ),
			esc_html( get_the_title( $id ) ),
			get_the_post_thumbnail( $id, 'medium', array( 'class' => 'posts_on_this_day__image' ) )
		);

		/**
		 * Allow filtering the markup of a single post inside the widget.
		 *
		 * @since 1.1.0
		 *
		 * @param string $markup Final markup.
		 * @param int    $id     Post ID.
		 */
		return apply_filters( 'jeherve_posts_on_this_day_post_markup', $markup, $id );
	}
} // Class Posts_On_This_Day_Widget
