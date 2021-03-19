<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Plugin Name: Posts On This Day
 * Plugin URI: https://jeremy.hu/my-plugins/posts-on-this-day/
 * Description: Widget to display a list of posts published "on this day" in years past. A good little bit of nostalgia for your blog.
 * Author: Jeremy Herve
 * Version: 1.4.1
 * Author URI: https://jeremy.hu
 * License: GPL2+
 * Text Domain: posts-on-this-day
 * Requires at least: 5.6
 * Requires PHP: 7.1
 *
 * @package jeherve/posts-on-this-day
 */

declare( strict_types=1 );

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
	 * Array of default widget settings.
	 *
	 * @return array Array of default values for the Widget's options
	 */
	private function defaults(): array {
		return array(
			'title'           => '',
			'max'             => 10,
			'back'            => 10,
			'show_thumbnails' => true,
			'group_by_year'   => true,
			'post_types'      => array( 'post' ),
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
	public function widget( array $args, array $instance ) {
		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		// Defaults.
		$instance = wp_parse_args(
			$instance,
			$this->defaults()
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

			foreach ( $posts as $year => $ids ) {
				if ( $instance['group_by_year'] ) {
					echo '<h4 class="posts_on_this_day__year">' . esc_html( $year ) . '</h4>';
					foreach ( $ids as $id ) {
						echo $this->display_post( $id, $instance ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
				} else {
					foreach ( $ids as $id ) {
						echo $this->display_post( $id, $instance ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
				}
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
	public function update( array $new_instance, array $old_instance ): array {
		$instance = $old_instance;

		$instance['title'] = wp_kses( $new_instance['title'], array() );

		/*
		 * Maximum number of posts to show.
		 * Default to 10, max 20.
		 */
		$max = (int) $new_instance['max'];
		if ( $max ) {
			$instance['max'] = min( $max, 20 );
		} else {
			$instance['max'] = 10;
		}

		/*
		 * How many years back should we go?
		 * Default to 10, max 20.
		 */
		$back = (int) $new_instance['back'];
		if ( $back ) {
			$instance['back'] = min( $back, 20 );
		} else {
			$instance['back'] = 10;
		}

		// Should we show thumbnails?
		$instance['show_thumbnails'] = isset( $new_instance['show_thumbnails'] )
			? (bool) $new_instance['show_thumbnails']
			: false;

		// Should we group posts by year?
		$instance['group_by_year'] = isset( $new_instance['group_by_year'] )
			? (bool) $new_instance['group_by_year']
			: false;

		/*
		 * Post types.
		 * Let's remove any saved post type that is not among the public post types.
		 */
		$allowed_post_types     = array_values( get_post_types( array( 'public' => true ) ) );
		$instance['post_types'] = (array) $new_instance['post_types'];
		foreach ( $new_instance['post_types'] as $key => $type ) {
			if ( ! in_array( $type, $allowed_post_types, true ) ) {
				unset( $new_instance['post_types'][ $key ] );
			}
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
	public function form( array $instance ) {
		// Defaults.
		$instance = wp_parse_args(
			(array) $instance,
			$this->defaults()
		);

		// Title.
		printf(
			'<p><label for="%1$s">%2$s<input class="widefat" id="%1$s" name="%3$s" type="text" value="%4$s" /></label></p>',
			esc_attr( $this->get_field_id( 'title' ) ),
			esc_html__( 'Widget title:', 'posts-on-this-day' ),
			esc_attr( $this->get_field_name( 'title' ) ),
			esc_attr( $instance['title'] )
		);

		// How many posts to display max?
		printf(
			'<p><label for="%1$s">%2$s</label><input class="widefat" id="%1$s" name="%3$s" type="number" min="1" value="%4$s" /></p>',
			esc_attr( $this->get_field_id( 'max' ) ),
			esc_html__( 'Maximum number of posts to display:', 'posts-on-this-day' ),
			esc_attr( $this->get_field_name( 'max' ) ),
			esc_attr( $instance['max'] )
		);

		// How far back should we go.
		printf(
			'<p><label for="%1$s">%2$s</label><input class="widefat" id="%1$s" name="%3$s" type="number" min="1" max="20" value="%4$s" /></p>',
			esc_attr( $this->get_field_id( 'back' ) ),
			esc_html__( 'How many years back do you want to look for posts?', 'posts-on-this-day' ),
			esc_attr( $this->get_field_name( 'back' ) ),
			esc_attr( $instance['back'] )
		);

		// Thumbnails.
		printf(
			'<p><input id="%1$s" name="%3$s" type="checkbox" value="1" %4$s /><label for="%1$s">%2$s</label></p>',
			esc_attr( $this->get_field_id( 'show_thumbnails' ) ),
			esc_html__( 'Show thumbnails', 'posts-on-this-day' ),
			esc_attr( $this->get_field_name( 'show_thumbnails' ) ),
			checked( $instance['show_thumbnails'], 1, false )
		);

		// Group by year.
		printf(
			'<p><input id="%1$s" name="%3$s" type="checkbox" value="1" %4$s /><label for="%1$s">%2$s</label></p>',
			esc_attr( $this->get_field_id( 'group_by_year' ) ),
			esc_html__( 'Group by year', 'posts-on-this-day' ),
			esc_attr( $this->get_field_name( 'group_by_year' ) ),
			checked( $instance['group_by_year'], 1, false )
		);

		/*
		 * Post types.
		 *
		 * First build a checkbox list of the different public post types on the site,
		 * and check each box that is saved in the widget options.
		 *
		 * Then display the actual picker.
		 */
		$allowed_post_types = array_values( get_post_types( array( 'public' => true ) ) );

		$post_type_list = '';
		foreach ( $allowed_post_types as $cpt ) {
			$is_cpt_selected = in_array( $cpt, (array) $instance['post_types'], true );

			$post_type_list .= sprintf(
				'<li><label><input value="%2$s" name="%3$s[]" id="%4$s-%2$s" type="checkbox" %5$s />%1$s</label></li>',
				esc_html( get_post_type_object( $cpt )->labels->name ),
				esc_attr( $cpt ),
				esc_attr( $this->get_field_name( 'post_types' ) ),
				esc_attr( $this->get_field_id( 'post_types' ) ),
				checked( $is_cpt_selected, true, false )
			);
		}

		printf(
			'<p><label for="%1$s">%2$s</label><ul>%3$s</ul></p>',
			esc_attr( $this->get_field_id( 'post_types' ) ),
			esc_html__( 'Pick posts from those post types:', 'posts-on-this-day' ),
			$post_type_list // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}

	/**
	 * Get posts to display.
	 *
	 * @param array $instance Saved widget options.
	 *
	 * @return array $posts Array of post IDs.
	 */
	private function get_posts( array $instance ): array {
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

	/**
	 * Display a single post.
	 *
	 * @param int   $id       Post id.
	 * @param array $instance Saved widget options.
	 *
	 * @return string $markup Markup for a single post.
	 */
	private function display_post( int $id, array $instance ): ?string {
		$title = false === $instance['group_by_year']
			? sprintf(
				/* Translators: 1: post title. 2: publication year. */
				__( '%1$s (%2$s)', 'posts-on-this-day' ),
				get_the_title( $id ),
				get_the_date( 'Y', $id )
			)
			: get_the_title( $id );

		$markup = sprintf(
			'<div class="posts_on_this_day__article"><a href="%2$s">%3$s</a><div class="posts_on_this_day__title"><a href="%2$s">%1$s</a></div></div>',
			esc_html( $title ),
			esc_url( get_permalink( $id ) ),
			( (bool) $instance['show_thumbnails'] ? get_the_post_thumbnail( $id, 'medium', array( 'class' => 'posts_on_this_day__image' ) ) : '' )
		);

		/**
		 * Allow filtering the markup of a single post inside the widget.
		 *
		 * @since 1.1.0
		 *
		 * @param string $markup   Final markup.
		 * @param int    $id       Post ID.
		 * @param array  $instance Saved widget options.
		 */
		return apply_filters( 'jeherve_posts_on_this_day_post_markup', $markup, $id, $instance );
	}
} // Class Posts_On_This_Day_Widget
