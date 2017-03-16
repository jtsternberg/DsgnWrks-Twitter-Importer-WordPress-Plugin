=== Plugin Name ===
DsgnWrks Twitter Importer

Contributors: jtsternberg
Plugin Name: DsgnWrks Twitter Importer
Plugin URI: http://dsgnwrks.pro/plugins/dsgnwrks-twitter-importer
Tags: twitter, tweets, import, backup, importer
Author URI: http://about.me/jtsternberg
Author: Jtsternberg
Donate link: http://j.ustin.co/rYL89n
Requires at least: 3.1
Tested up to: 4.7.1
Version: 1.1.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Backup your tweets & display your twitter archive. Supports importing to custom post-types & adding custom taxonomies.

Now supports the Twitter 1.1 API

== Description ==

In the spirit of WordPress and "owning your data," this plugin will allow you to import and backup your tweets to your WordPress site. Includes robust options to allow you to control the imported posts formatting including built-in support for WordPress custom post-types, custom taxonomies, post-formats. Add an unlimited number of user accounts for backup and importing.

Plugin is built with developers in mind and has many filters to manipulate the imported posts.

1.1.0: Updated to work with the Twitter 1.1 API. Uses the [TwitterWP](https://github.com/jtsternberg/TwitterWP) library.

Like this plugin? Checkout the [DsgnWrks Instagram Importer](http://j.ustin.co/QbG3mQ). Feel free to [fork this plugin on github](http://j.ustin.co/QbQQ0a).

== Installation ==

1. Upload the `dsgnwrks-twitter-importer` directory to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Visit the plugin settings page (`/wp-admin/tools.php?page=dw-twitter-importer-settings`) to add your twitter usernames and import your tweets.

== Frequently Asked Questions ==

= ?? =
If you run into a problem or have a question, contact me ([contact form](http://j.ustin.co/scbo43) or [@jtsternberg on twitter](http://j.ustin.co/wUfBD3)). I'll add them here.


== Screenshots ==

1. Welcome Panel.
2. After adding a user, this is the options panel you'll be presented with. If you select a custom post-type in the post-type selector, the options may change based on the post-type's supports, as well as any custom taxonomies.

== Changelog ==

= 1.1.3 =
* Add cron/auto-import feature.
* Use TwitterText lib to parse tweent text and add links, replace urls with tweet entities etc.
* Use twitter text for post-title/slug instead of just timestamp.
* Allow setting to post-format even if theme doesn't support.
* Only set timezone during import if we have one set in WP settings

= 1.1.2 =
* Update the TwitterWP library to fix url encoding issues.

= 1.1.1 =
* Add `dw_twitter_api_get_tweets` filter for overriding the TwitterWP method for getting tweets. This enables importing from favorites/lists, etc.

= 1.1.0 =
* Updated to work with the Twitter 1.1 API. Uses the [TwitterWP](https://github.com/jtsternberg/TwitterWP) library.

= 1.0.2 =
* Fixed incorrectly named function

= 1.0.1 =
* Fixed a bug where imported tweet times could be set to the future

= 1.0 =
* Launch.


== Upgrade Notice ==

= 1.1.3 =
* Add cron/auto-import feature.
* Use TwitterText lib to parse tweent text and add links, replace urls with tweet entities etc.
* Use twitter text for post-title/slug instead of just timestamp.
* Allow setting to post-format even if theme doesn't support.
* Only set timezone during import if we have one set in WP settings

= 1.1.2 =
* Update the TwitterWP library to fix url encoding issues.

= 1.1.1 =
* Add `dw_twitter_api_get_tweets` filter for overriding the TwitterWP method for getting tweets. This enables importing from favorites/lists, etc.

= 1.1.0 =
* Updated to work with the Twitter 1.1 API. Uses the [TwitterWP](https://github.com/jtsternberg/TwitterWP) library.

= 1.0.2 =
* Fixed incorrectly named function

= 1.0.1 =
* Fixed a bug where imported tweet times could be set to the future

= 1.0 =
* Launch
