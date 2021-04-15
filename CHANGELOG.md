
2.2.0 / 2021-04-15
==================

  * show reusable blocks in admin panel
  * add helper file for images
  * add CACHE_GROUP
  * reset attachment cache on save
  * init AttachmentData class utils
  * add Cache utils class
  * add Cache utils class
  * installed jazzman/custom-post-type

2.1.0 / 2021-04-15
==================

  * add CHANGELOG
  * rename addScriptVersion to setScriptVersion
  * update return type for invalidateFoundPostsCache
  * update naming
  * refactor code style
  * refactor SanitizeFileName class
  * refactoring and added more phpdoc
  * update phpdoc
  * update return types
  * install jazzman/wp-app-config
  * install jazzman/wp-db-pdo
  * removed suggest section
  * removed ext-intl
  * removed wpackagist.org repo
  * used current version of jquery
  * use only dns_prefetch_link

2.0.1 / 2021-03-03
==================

  * normalize image name

2.0.0 / 2021-01-08
==================

  * added generated http links to html header
  * removed http links from header

1.9.9 / 2021-01-04
==================

  * refactoring order_by query

1.9.8 / 2020-11-08
==================

  * Speed up resource loading: preconnect, prefetch, prerender, preloading...
  * updated autoloader

1.9.7 / 2020-11-07
==================

  * add X-DNS-Prefetch-Control header
  * Update jazzman/autoload-interface requirement from ^0.1.0 to ^0.2

1.9.6 / 2020-11-07
==================

  * ignore php_cs
  * remove rest_output_link_header from template_redirect hook

1.9.5 / 2020-11-06
==================

  * add script version only if its needed
  * Update README.md

1.9.4 / 2020-09-04
==================

  * fix Svg Size Attributes
  * remove shortlink header
  * update jquery cdn url

1.9.3 / 2020-05-27
==================

  * update query_hash

1.9.2 / 2020-05-25
==================

  * wp_register_style if new url not null

1.9.1 / 2020-03-27
==================

  * remove google/recaptcha client

1.9 / 2020-03-27
================

  * remove Spamassassin client

1.8.1 / 2020-03-18
==================

  * disable recaptcha for WP_CLI

1.8 / 2020-03-18
================

  * remove jazzman/parameter-bag
  * added recaptcha
  * use PHPMailer for generating mail

1.7 / 2020-03-13
================

  * check if wpcf7_get_current_contact_form exist
  * add ContactFormSpamTester class

1.6 / 2020-03-08
================

  * add deregisterStyle method
  * remove Shortcode Parser module

1.4.6 / 2019-12-26
==================

  * Update Media.php

1.4.5 / 2019-12-16
==================

  * Update Enqueue.php

1.5 / 2020-03-05
================

  * remove Shortcode Parser module

1.4.4 / 2019-11-20
==================

  * load jquery from cdn
  * updated autoload classes array

1.4.3 / 2019-11-10
==================

  * Optimization of rand parameter via posts_clauses_request filter

1.4.2 / 2019-10-30
==================

  * Optimization of rand parameter only

1.4.1 / 2019-09-30
==================

  * check if url exist

1.4 / 2019-09-30
================

  * added svg support for cmb2
  * fix svg size attributes

1.3 / 2019-09-20
================

  * added license

performance / 2019-09-20
========================

  * fixed namespace

1.2 / 2019-09-20
================

  * added DuplicatePost class

1.1 / 2019-07-16
================

  * used transient

1.0 / 2019-05-29
================

  * rename all methods to camelcase style
  * removed unused params
  * removed Divi class

0.9.2.2 / 2019-05-28
====================

  * fix site url when et_fb_enqueue_assets is called

0.9.2.1 / 2019-05-26
====================

  * removed several divi filters

0.9.2 / 2019-05-24
==================

  * updated initialisation
  * removed xmlrpc server
  * updated performance for Divi

0.9.1 / 2019-05-23
==================

  * removed RestAPI class
  * fix wp_link_query_args

0.9 / 2019-05-20
================

  * do not load jquery from cdn

0.8.9 / 2019-05-20
==================

  * fix url host info

0.8.8 / 2019-05-20
==================

  * do not run in admin area

0.8.7 / 2019-05-16
==================

  * fix image sizes generating

0.8.6 / 2019-05-15
==================

  * fix url info

0.8.5 / 2019-05-15
==================

  * added Enqueue class
  * updated Performance for Divi

0.8.4 / 2019-05-14
==================

  * removed BulkEdit class
  * disable responsive images srcset
  * updated performance settings for divi
  * updated namespaces
  * rearrange code
  * rearrange code
  * removed class_autoload and class_autoload_cli props
  * code formatting
  * used is_importing method
  * added $permalink var

0.8.3 / 2019-04-24
==================

  * added function to generate image sizes on the fly
  * removed enable_performance_tweaks function

0.8.2 / 2019-04-16
==================

  * Fix a race condition in alloptions caching

0.8.1 / 2019-04-16
==================

  * updated php version

0.8 / 2019-04-16
================

  * added dev version
  * remove wp-cli/db-command as dependencies
  * remove dependencies

0.7.1 / 2019-04-04
==================

  * install PHP-Secure-Session

0.7 / 2019-04-04
================

  * restricts access to the REST API to logged-in users

0.6 / 2019-04-04
================

  * hotfix/interface
  * Add Codacy badge
  * used $wpdb->update
  * used jazzman/autoload-interface
  * used global $wp_query
  * installed jazzman/autoload-interface
  * added svg support
  * removed feed_links

0.5 / 2019-03-20
================

  * Update composer.json

0.4 / 2019-03-19
================

  * fix function name
  * added helper for rendering shortcode
  * removed clean_style_tag and clean_script_tag function
  * small fixes
  * added CleanUp class

0.3 / 2019-03-18
================

  * removed version from composer
  * added Sanitizer class
  * added app_autoload_classes helper

0.2 / 2019-03-18
================

  * removed version from composer file

0.1 / 2019-03-18
================

  * updated performance for Term Count
  * updated BulkEdit
  * optimise media library
  * added LastPostModified class based on https://github.com/Automattic/vip-go-mu-plugins-built/blob/master/performance/lastpostmodified.php
  * updated args for wp_link_query_args
  * added Better Shortcode Parser
  * init PostMeta Performance
  * added WP_Query_Performance class
  * added jazzman/wp-object-cache
  * Disable gravatars
  * Remove admin news dashboard widget
  * Remove some actions.
  * Disable debug emails
  * Disable WordPress from fetching available languages
  * Disable differents WP updates
  * Disable translation updates.
  * Disable automatic plugin and theme updates
  * Disable overall core updates.
  * Removes update check wp-cron
  * Time based transient checks.
  * disable Theme update API for different calls.
  * Remove bulk action for updating themes/plugins.
  * Prevent users from even trying to update plugins and themes
  * Stop wp-cron from looking out for new plugin versions
  * Checks when plugin should be enabled This offers nice compatibilty with wp-cli.
  * installed symfony/var-dumper
  * added base info about plugin
  * added test version
  * added main plugin file
  * install jazzman/wp-app-config
  * init Update class
  * init AutoloadInterface Interface
  * init App class
  * init
  * Initial commit
