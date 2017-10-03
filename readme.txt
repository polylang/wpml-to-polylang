=== WPML to Polylang ===
Contributors: Chouby
Donate link: https://polylang.pro
Tags: Polylang, WPML, importer, multilingual, bilingual
Requires at least: 3.5
Tested up to: 4.8
Stable tag: 0.2.4
License: GPLv2 or later

Import multilingual data from WPML into Polylang.

== Description ==

= Features =

* Imports languages and WPML options (when the same options exist in Polylang)
* Imports posts and terms languages as well as translations (including for custom post types and custom taxonomies)
* Imports multilingual nav menus
* Imports strings translations
* Does not delete WPML data

= Important =

Although WPML data should not be corrupted, as Polylang data are created without deleting anything, **make a database backup before proceeding**.

= How to proceed? =

* De-activate WPML
* Activate [Polylang](https://polylang.pro) and WPML to Polylang. Do *not* create any language with Polylang.
* Go in Tools -> WPML Importer
* If all checks are passed, you can click on 'Import'
* De-activate WPML to Polylang (you can even delete it)
* Setup a language switcher either as a widget or in nav menus
* Check that everything is OK
* If something went wrong and you want to revert to WPML, you can delete Polylang using the red link in the Plugins table (You can delete all data created for Polylang by checking the relevant option in Settings > Languages> Settings > Tools before deleting Polylang) and then re-activate WPML

= Notes =

This plugin is still experimental and does not include error management. I however tested it successfully to migrate a site with about 9,000 posts and media from WPML 3.7.
As everything is processed in one unique step, big sites may require to tweak the PHP memory limit and MySQL buffer size.
Multisite has not been tested.

= Credits =

The banner and icon were designed by [Alex Lopez](http://www.alexlopez.rocks/)

== Changelog ==

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
