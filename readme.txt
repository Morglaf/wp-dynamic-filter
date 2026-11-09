=== WP Dynamic Filter ===
Contributors: morglaf
Tags: gutenberg, query loop, filter, taxonomy, ajax
Requires at least: 6.7
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AJAX taxonomy filters for Gutenberg Query Loops, without page reload.

== Description ==

WP Dynamic Filter adds a Gutenberg container block around a Query Loop. Visitors can filter posts by taxonomy terms (categories, tags, or custom taxonomies) using buttons or a dropdown. Results reload via the WordPress REST API as rendered HTML, so complex Query Loop templates (grids, block bindings, etc.) keep working.

Features:

* Container block wrapping a native Query Loop
* Taxonomy picker (public taxonomies)
* Buttons or dropdown UI
* Optional “All” button with custom label
* Optional multiple selection (OR logic)
* Optional term post counts
* URL persistence with the History API (`ddf_id`, `ddf_tax`, `ddf_term`)
* Assets loaded only when the block is present
* No jQuery dependency
* Translations: English (source), French, Spanish, German

Development source and build tools: https://github.com/Morglaf/wp-dynamic-filter
(The `src/` directory is also included in the plugin package.)

== Installation ==

1. Upload the `wp-dynamic-filter` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins screen
3. In the block editor, insert **Dynamic Filter Query**, or transform an existing Query Loop
4. Configure taxonomy and display options in the sidebar

Development build (optional):

`npm install && npm run build`

== Frequently Asked Questions ==

= Does it work with custom post types? =

Yes. Configure the inner Query Loop for your post type; the filter only applies a taxonomy query on top.

= Will renaming break my existing pages? =

Content saved with the previous block name `dynamic-filter/query` still renders. New inserts use `wp-dynamic-filter/query`.

= Is the Block Directory supported? =

This plugin includes meaningful PHP/REST code and targets the main Plugin Directory, not the Block Directory.

== Changelog ==

= 1.2.0 =
* Rename slug / text domain to wp-dynamic-filter
* Legacy block and REST namespace compatibility
* English source strings + FR / ES / DE translations
* WordPress.org packaging (readme.txt, LICENSE)

= 1.1.0 =
* Multiple selection
* Custom “All” button label

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.2.0 =
Rename to wp-dynamic-filter. Redeploy assets; legacy content remains supported.
