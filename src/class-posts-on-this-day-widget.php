<?php
/**
 * Posts on This Day widget class.
 *
 * @package jeherve/posts-on-this-day
 */

declare( strict_types=1 );

namespace Jeherve\Posts_On_This_Day;

use WP_Widget;

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
			'exact_match'     => false,
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

		// Defaults.
		$instance = wp_parse_args(
			$instance,
			$this->defaults()
		);

		// Display posts.
		$posts   = ( new Query() )->get_posts( $instance );
		$display = new Display();
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
					/**
					 * Filters the heading level for the year heading in the Posts On This Day widget.
					 *
					 * @since 1.5.5
					 *
					 * @param string $year_heading Heading level. Default to h4.
					 */
					$year_heading = apply_filters(
						'jeherve_posts_on_this_day_widget_year_heading',
						'h4'
					);
					printf(
						'<%1$s class="posts_on_this_day__year">%2$s</%1$s>',
						esc_attr( $year_heading ),
						esc_html( $year ),
					);

					foreach ( $ids as $id ) {
						echo $display->display_post( $id, $instance ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
				} else {
					foreach ( $ids as $id ) {
						echo $display->display_post( $id, $instance ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
	public function update( $new_instance, $old_instance ): array {
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

		// Should we only look for matching posts on the exact date years back?
		$instance['exact_match'] = isset( $new_instance['exact_match'] )
			? (bool) $new_instance['exact_match']
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
	public function form( $instance ) {
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

		// Should we look for posts on the exact date, or around that date years past.
		printf(
			'<p><input id="%1$s" name="%3$s" type="checkbox" value="1" %4$s /><label for="%1$s">%2$s</label><em>%5$s</em></p>',
			esc_attr( $this->get_field_id( 'exact_match' ) ),
			esc_html__( 'Are you only interested in posts that were published on that exact day?', 'posts-on-this-day' ),
			esc_attr( $this->get_field_name( 'exact_match' ) ),
			checked( $instance['exact_match'], 1, false ),
			esc_html__( 'By default, the widget will look for posts that were published around that day (within a week) in years past.', 'posts-on-this-day' )
		);
	}
} // Class Posts_On_This_Day_Widget
