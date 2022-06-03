=== WPML to Polylang ===
Contributors: Chouby
Donate link: https://polylang.pro
Tags: Polylang, WPML, importer, multilingual, bilingual
Requires at least: 4.9
Tested up to: 5.8
Stable tag: 0.4
License: GPLv3 or later

Import multilingual data from WPML into Polylang.

== Description ==

= Features =

* Imports languages and WPML options (when the same options exist in Polylang).
* Imports posts and terms languages as well as translations (including for custom post types and custom taxonomies).
* Imports multilingual nav menus.
* Imports strings translations.
* Does not delete WPML data.

= Important =

Although WPML data should not be corrupted, as Polylang data are created without deleting anything, **make a database backup before proceeding**.

= How to proceed? =

* De-activate WPML
* Activate [Polylang](https://polylang.pro) and WPML to Polylang. Do **not** create any language with Polylang (they will be imported).
* Go to Tools -> WPML Importer.
* If all checks are passed, you can click on 'Import'.
* De-activate WPML to Polylang (You can even delete it).
* Setup a language switcher either as a widget or in nav menus.
* Check that everything is OK.
* If something went wrong and you want to revert to WPML, you can delete Polylang using the red link in the Plugins table. If you want to delete all data created for Polylang, Please read [how](https://polylang.pro/doc/how-to-uninstall-polylang/) **before** deleting Polylang. Finally you can re-activate WPML.

= Constants =

These are provided constants you can use to optimize your import process. 

These are entirely optional and not required but may be necessary depending on the size of the data needing to be imported.

| Constants | Default | Description |
|-----------|---------|-------------|
| `WPML_TO_POLYLANG_SCRIPT_TIMEOUT_IN_SECONDS` | (int) `1800` | The time allotted for the script to run (`1800` is 30 mins). Never use `0` as this is a bad practice. |
| `WPML_TO_POLYLANG_QUERY_BATCH_SIZE` | (int) `25000` | The size of the data to send to the database at a time. Increasing this will improve performance but at the risk of needing to increase your `max_packet_size` in MySQL. |
| `WPML_TO_POLYLANG_LEGACY_SUBMISSION` | (bool) `false` | Disables AJAX submissions and uses the form submission instead (you will gateway timeout issues if behind a proxy/load balancer if enabled) |

Memory issues are the most common problem you will experience. To solve this, you can set the WordPress constant `WP_MAX_MEMORY_LIMIT` to a higher value. This value depends on the amount of data you are needing to import.

= Notes =

This plugin does not include error management. It has however been tested successfully to migrate a site with about 9,000 posts and media.
As everything is processed in one unique step, big sites may require to tweak the PHP memory limit and MySQL buffer size.
It has been tested with WPML 4.4.8. It has also tested on multisite.

= Credits =

The banner and icon were designed by [Alex Lopez](http://www.alexlopez.rocks/)

== Changelog ==

= 0.4 (2021-01-19) =

* Min Polylang version is now 2.8
* Fix languages incorrectly imported with Polylang 2.8+
* Fix media translation option incorrectly imported #7

= 0.3.1 (2019-12-26) =

* Fix PHP notices

= 0.3 (2019-06-27) =

* Min Polylang version is now 2.6 #3
* Fix the front page label not displayed for translations in the pages list table #4
* Fix deprecated notice related to the screen icon

= 0.2.5 (2018-08-22) =

* Fix flags and rtl property not correctly imported

= 0.2.4 (2017-10-03) =

* Fix incompatibility with WP 4.8.2 (placeholder %1$s in prepare)

= 0.2.3 (2017-08-16) =

* Assign the default language to objects without language in the 'icl_translations' table
* Fix term languages not correctly imported
* Fix unprepared SQL query (thanks to @grapplerulrich)

= 0.2.2 (2016-09-12) =

* Fix: Don't import the empty strings translations as it breaks Polylang

= 0.2.1 (2016-05-06) =

* Allow plugin localization from translate.wordpress.org

= 0.2 (2015-11-19) =

* Ready for Polylang 1.8, min Polylang version is 1.5, min WP version is 3.5
* Adopt WordPress coding standards
* Fix: database error when importing categories translations

= 0.1.4 (2014-06-24) =

* Fix: after import, updating a page deletes all translation relationships

= 0.1.3 (2014-06-15) =

* Fix: strings translations are not saved

= 0.1.2 (2014-06-06) =

* Add: "success" message
* Fix: incompatibility with Polylang 1.5
* Fix: taxonomy terms translations incorrectly mapped as WPML uses term_taxonomy_id whereas Polylang uses term_id
* Fix: bug with custom post types and custom taxonomies

= 0.1.1 (2014-05-01) =

* Bug fixes
