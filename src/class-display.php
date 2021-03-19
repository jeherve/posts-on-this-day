<?php
/**
 * Frontend elements for the plugin.
 *
 * @package jeherve/posts-on-this-day
 */

declare( strict_types=1 );

namespace Jeherve\Posts_On_This_Day;

/**
 * Handling the display of elements inside the widget.
 */
class Display {
	/**
	 * Display a single post.
	 *
	 * @param int   $id       Post id.
	 * @param array $instance Saved widget options.
	 *
	 * @return string $markup Markup for a single post.
	 */
	public function display_post( int $id, array $instance ): ?string {
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
}
