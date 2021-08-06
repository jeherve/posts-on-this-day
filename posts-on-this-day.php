<?php
/**
 * Plugin Name: Posts On This Day
 * Plugin URI: https://jeremy.hu/my-plugins/posts-on-this-day/
 * Description: Widget to display a list of posts published "on this day" in years past. A good little bit of nostalgia for your blog.
 * Author: Jeremy Herve
 * Version: 1.5.3
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

$posts_on_this_day_autoloader = plugin_dir_path( __FILE__ ) . 'vendor/autoload_packages.php';
if ( is_readable( $posts_on_this_day_autoloader ) ) {
	require $posts_on_this_day_autoloader;
}

// Register widget.
add_action(
	'widgets_init',
	function () {
		register_widget( __NAMESPACE__ . '\Posts_On_This_Day_Widget' );
	}
);
