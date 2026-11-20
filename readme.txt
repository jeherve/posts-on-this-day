=== Posts On This Day ===
Contributors: jeherve
Tags: widget, block, on this day
Stable tag: 2.0.0
Requires at least: 7.0
Requires PHP: 8.3
Tested up to: 7.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Display a list of posts published "on this day" in years past. A good little bit of nostalgia for your blog.

== Description ==

If you're familiar with services like Google Photos, TimeHop, or even Facebook Memories, you most likely enjoyed getting little reminders of what happened in your life in years past. This plugin brings this feature to your site.

Just like Google Photos does, it will give you a list of posts that were published at around this time (within a week) in the past years. It is available as a block for the block editor and block-based themes, as well as a legacy widget. You can choose:

* how many years back it should go, or every year since your first post.
* how many posts should be displayed at maximum.
* what post types to pick old posts from.
* whether you'd like to display thumbnails for those posts.
* whether you'd like to group your posts by year.
* whether you'd like to look for posts on the exact day, years past, or around this time (within a week).

The block is a variation of the Query Loop block, so you can lay out each post however you like: featured image, title, date, excerpt, as a list or a grid.


Banner image: [@rirri01](https://unsplash.com/@rirri01)

== Installation ==

* The usual. Go to Plugins > Add New, search, and install.
* **Block editor**: Add the "Posts On This Day" block to any page or template. You'll find it in the block inserter; searching for "on this day" or "memories" works too. Its settings are in the block sidebar, under "Posts On This Day".
* **Legacy widget**: Go to Appearance > Widgets or Appearance > Customize to set up the widget in one of your widget areas.

== FAQ ==

= How do I change what the block shows for each post? =

The block works like a Query Loop block. Select the post inside it to add, remove or move blocks such as the featured image, title, date or excerpt, and use the block's layout and style settings to switch between a list and a grid.

= I turned on "Group posts by year", but I do not see year headings in the editor =

The headings are added when the page is displayed on your site; the editor preview shows the posts without them. Year headings are only available when posts are ordered by date.

= Can I change the block's query with code? =

Yes. The block uses WordPress' `query_loop_block_query_vars` filter for its query, so you can hook into that filter too. Blocks from this plugin are the ones whose query has a `yearsBack` key. The `jeherve_posts_on_this_day_*` filters only apply to the legacy widget.

= I want to customize the look of my widget =

You have 3 ways to do so.

1. You can add custom CSS to your site, targetting the `.posts_on_this_day` container and its contents to have the widget fit your needs.
2. If you're comfortable with PHP, you can add a code snippet that hooks into the `jeherve_posts_on_this_day_post_markup` filter to customize the look of each single post in the widget. That's a good way to change the size of the images displayed for each post, for example.
3. You can also use the `jeherve_posts_on_this_day_widget_before_posts` and `jeherve_posts_on_this_day_widget_after_posts` filters to add extra markup around the list of posts in the widget.

== Screenshots ==

1. Widget settings
2. The Posts On This Day block in the editor, with its settings
3. Posts grouped by year on the site

== Changelog ==

### [2.0.0] - 2026-11-20

* New: "Posts On This Day" is now available as a block for use in the block editor and block-based themes. It can look back over every year since your first post, and group posts under year headings.
* Query: posts published on this very day in past years now appear when looking for posts within a week. They used to be left out.
* Query: on 29 February, years without a leap day show posts from 28 February. On 28 February, posts from 29 February in past leap years show too.
* Query: sticky posts are no longer added to the list whatever their date.
* General: the plugin now requires WordPress 7.0 or later.
* General: update the Jetpack Autoloader to version 6.

### [1.5.7] - 2026-02-27

* General: the plugin now requires PHP 8.3.
* Query: use the site's configured timezone instead of UTC when determining "today", fixing a bug where the widget could show yesterday's posts on sites with non-UTC timezones.
* Caching: fix cache duration calculation to use consistent timezone-aware DateTime objects, and include the current date in the cache key for more reliable daily invalidation. Props @mrclicko.

### [1.5.6] - 2025-12-16

* Widget: add 2 new filters, `jeherve_posts_on_this_day_widget_before_posts` and `jeherve_posts_on_this_day_widget_after_posts`, allowing one to add extra markup around the list of posts in the widget.

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

== Upgrade Notice ==

= 2.0.0 =
Adds a "Posts On This Day" block for the block editor and block themes. Requires WordPress 7.0 or later.
