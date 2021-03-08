=== Posts On This Day ===
Contributors: jeherve
Tags: widget, on this day
Stable tag: 1.3.0
Requires at least: 5.6
Requires PHP: 5.6
Tested up to: 5.7
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Widget to display a list of posts published "on this day" in years past. A good little bit of nostalgia for your blog.

== Description ==

If you're familiar with services like Google Photos, TimeHop, or even Facebook Memories, you most likely enjoyed getting little reminders of what happened in your life in years past. This little widget brings this feature to your site.

This widget, just like Google Photos does, will give you a list of posts that were published at around this time (within a week) in the past years. You can choose:
- how many years back it should go.
- how many posts should be displayed at maximum.
- whether you'd like to display thumbnails for those posts.
- whether you'd like to group your posts by year.

== Installation ==

* The usual. Go to Plugins > Add New, search, and install.
* You can then go to Appearance > Widgets or Appearance > Customize to set up your widget in one of your widget areas.

== Frequently Asked Questions ==

= I want to customize the look of my widget =

You have 2 ways to do so.

1. You can add custom CSS to your site, targetting the `.posts_on_this_day` container and its contents to have the widget fit your needs.
2. If you're comfortable with PHP, you can add a code snippet that hooks into the `jeherve_posts_on_this_day_post_markup` filter to customize the look of each single post in the widget. That's a good way to change the size of the images displayed for each post, for example.

== Screenshots ==

1. Widget settings

== Changelog ==

## [1.3.0] - 2021-03-08

* Add option to group by year.

## [1.2.0] - 2021-03-08

* Add option to display post thumbnails.

## [1.1.3] - 2021-03-07

* Update plugin URI for public release.

## [1.1.2] - 2021-03-07

* Initial public release.

