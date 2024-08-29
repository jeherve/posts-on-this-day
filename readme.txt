=== Posts On This Day ===
Contributors: jeherve
Tags: widget, on this day
Stable tag: 1.5.5
Requires at least: 5.6
Requires PHP: 7.1
Tested up to: 6.6
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Widget to display a list of posts published "on this day" in years past. A good little bit of nostalgia for your blog.

== Description ==

If you're familiar with services like Google Photos, TimeHop, or even Facebook Memories, you most likely enjoyed getting little reminders of what happened in your life in years past. This little widget brings this feature to your site.

This widget, just like Google Photos does, will give you a list of posts that were published at around this time (within a week) in the past years. You can choose:

* how many years back it should go.
* how many posts should be displayed at maximum.
* what post types to pick old posts from.
* whether you'd like to display thumbnails for those posts.
* whether you'd like to group your posts by year.
* whether you'd like to look for posts on the exact day, years past, or around this time (within a week).


Banner image: [@rirri01](https://unsplash.com/@rirri01)

== Installation ==

* The usual. Go to Plugins > Add New, search, and install.
* You can then go to Appearance > Widgets or Appearance > Customize to set up your widget in one of your widget areas.

== FAQ ==

= I want to customize the look of my widget =

You have 2 ways to do so.

1. You can add custom CSS to your site, targetting the `.posts_on_this_day` container and its contents to have the widget fit your needs.
2. If you're comfortable with PHP, you can add a code snippet that hooks into the `jeherve_posts_on_this_day_post_markup` filter to customize the look of each single post in the widget. That's a good way to change the size of the images displayed for each post, for example.

== Screenshots ==

1. Widget settings

== Changelog ==

### [1.5.5] - 2023-05-08

* Widget: add a new filter, `jeherve_posts_on_this_day_widget_year_heading`, allowing one to customize the heading used to display years in the widget.

### [1.5.4] - 2023-04-27

* Query: do not display private and password-protected posts in the widget.

### [1.5.3] - 2021-08-06

* Query: create new filter to allow setting a custom amount of years to fetch posts.

### [1.5.2] - 2021-04-15

* Caching: cache data until midnight of the same day, instead of caching it for 24 hours.

### [1.5.1] - 2021-04-13

* Add an option to only search for posts on the exact day, years past.
* Improve the display of each post when no post thumbnail can be found.

### [1.5.0] - 2021-03-19

* Check for types more strictly.
* The plugin now requires PHP 7.1.

### [1.4.1] - 2021-03-08

* Avoid displaying posts twice.

### [1.4.0] - 2021-03-08

* Add option to pick post types.

### [1.3.0] - 2021-03-08

* Add option to group by year.

### [1.2.0] - 2021-03-08

* Add option to display post thumbnails.

### [1.1.3] - 2021-03-07

* Update plugin URI for public release.

### [1.1.2] - 2021-03-07

* Initial public release.

