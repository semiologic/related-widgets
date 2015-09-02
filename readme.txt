=== Related Widgets ===
Contributors: Denis-de-Bernardy & Mike_Koepke
Donate link: https://www.semiologic.com/donate/
Tags: semiologic
Requires at least: 3.1
Tested up to: 4.3
Stable tag: trunk

A collection of widgets to list related posts and pages.


== Description ==

The Related Widgets plugin for WordPress introduces multi-use widgets that allow you to list related posts or pages.

To use the plugin, browse Appearance / Widgets, insert a Related Widget where you want it to be, and configure it as appropriate.

You can optionally filter the results by category or section.

= On post and page tags =

The plugin builds on your tags to generate lists of related posts and pages. For this reason, it allows to you add tags to your pages, even though they're not otherwise used by WP.

Note that the plugin manages page tags in a manner that does not disrupt WP. As a result, page tags will only display, when using the Semiologic theme, when at least one post also has that tag. The tags are definitely used, however, when scanning for related posts and pages.

That the plugin's algorithm is smart enough to spot related tags. In other words, if post A shares tags with posts B and C, but not post D; and B and C share a tag with D; then the plugin may decide that A is related to D.

At the other end of the spectrum, keep noise tags in mind. Be it signal processing, SEO, or anything else, information comes from differences, not from similarity -- it's difficult to detect a dark gray dot on a black board, whereas it's easy to spot a white dot. If all of your posts share a small set of tags, there is no information to extract and everything becomes noise. And these noise tags end up ignored.

= This post/page in widgets =

This plugin shares options with a couple of other plugins from Semiologic. They're available when editing your posts and pages, in meta boxes called "This post in widgets" and "This page in widgets."

These options allow you to configure a title and a description that are then used by Fuzzy Widgets, Random Widgets, Related Widgets, Nav Menu Widgets, Silo Widgets, and so on. They additionally allow you to exclude a post or page from all of them in one go.

= Help Me! =

The [Semiologic Support Page](https://www.semiologic.com/support/) is the best place to report issues.


== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress

== Change Log ==

= 3.6 =

- Updated to use PHP5 constructors as WP deprecated PHP4 constructor type in 4.3.
- WP 4.3 compat
- Tested against PHP 5.6

= 3.5 =

- WP 4.0 compat

= 3.4.1 =

- Use more full proof WP version check to alter plugin behavior instead of relying on $wp_version constant.

= 3.4 =

- Clear caches on WP upgrade
- Code refactoring
- WP 3.9 compat

= 3.3.1 =

- Further tweaks around the widget context caching

= 3.3 =

- Improved context caching to work better with page revisions and auto-saves.
- WP 3.8 compat

= 3.2.1 =

- Fix PHP Warning 'Object of class related_widget could not be converted to string' in /wp-content/plugins/sem-cache/object-cache.php on line 133

= 3.2 =

- WP 3.6 compat
- PHP 5.4 compact

= 3.1.1 =

- Fix caching issue with "This Page in Widgets" not refreshing on title or description updates

= 3.1 =

- WP 3.5 compat
- Recoded for removed _get_post_ancestors function

= 3.0.5 =

- WP 3.0 compat

= 3.0.4 =

- Remove php5-specific code
- Further cache improvements (fix priority)

= 3.0.3 =

- Slight algorithm improvement
- Improve caching and memcached support
- Apply filters to permalinks

= 3.0.2 =

- WP 2.9 compat
- Fix hard-coded DB tables

= 3.0.1 =

- Fix an occasional warning

= 3.0 =

- Complete rewrite
- WP_Widget class
- Localization
- Code enhancements and optimizations
