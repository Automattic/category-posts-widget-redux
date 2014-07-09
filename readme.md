# WordPress Category Posts Widget Redux
* Contributors: James Lao, Automattic, Jeff Bowen
* Author URL: http://jameslao.com/
* Fork URL: http://vip.wordpress.com/plugins/category-posts-widget/
* Tags: category, posts, widget
* Requires at least: 3.8
* Tested up to: 3.9.1
* Stable tag: 3.3p-a8c1

## Description

Adds a widget that can configurably display posts via category.

Category Posts Widget is a light widget designed to do one thing and do it well: display the most recent posts from a certain category.

### Features:

* [NEW] Option to change ordering of posts.
* Support for displaying thumbnail images via WP 2.9's new post thumbnail feature.
* Set how many posts to show.
* Set which category the posts should come form.
* Option to show the post excerpt and how long the excerpt should be.
* Option to show the post date.
* Option to show the comment count.
* Option to make the widget title link to the category page.
* Multiple widgets.

## Installation

1. Download the plugin.
2. Upload it to the plugins folder of your blog.
3. Goto the Plugins section of the WordPress admin and activate the plugin.
4. Goto the Widget tab of the Presentation section and configure the widget.

## Upgrade Notice

Note that version 3.0 drops support for [Simple Post Thumbnails plugin](http://wordpress.org/extend/plugins/simple-post-thumbnails/) in favor of WP 2.9's built in post thumbnail functionality.

## Screenshots

1. The widget configuration dialog.

## Changelog
* 3.3p-a8c1
  * Use class member functions instead of create_function calls
  * Configurable object caching of the widget markup
  * Filterable comment number display
  * Add in escaping / sanitization
  * Improve adherence to WordPress style guidelines ( braces, whitespace, etc. )
  * Use HTML5 input type “number” on appropriate fields in the config.
  * Fix some translation strings

* 3.3
  * Sort by slug uses 'rand' instead of 'random' -- see https://plugins.trac.wordpress.org/changeset/427984/category-posts/trunk/cat-posts.php

* 3.2
  * Added option to change ordering of posts. Defaults to showing newest posts first.

* 3.1
  * Fixed a bug in the thumbnail size registration routine.

* 3.0
  * Added support for WP 2.9's post thumbnail feature.
  * Removed support for Simple Post Thumbnails plugin.
  * Added option to show the post date.
  * Added option to set the excerpt length.
  * Added option to show the number of comments.

* 2.3
  * Really tried to fix bug where wp_query global was getting over written by manually instantiating a WP_Query object

* 2.1
  * Fixed bug where wp_query global was getting over written.

* 2.0
  * Updated to use the WP 2.8 widget API.
  * Added support for [Simple Post Thumbnails plugin](http://wordpress.org/extend/plugins/simple-post-thumbnails/).
