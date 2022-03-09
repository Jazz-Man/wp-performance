
3.0.0 / 2022-03-09
==================

  * update baseline config
  * fix code style
  * fix problem with generating $objectParents array
  * move NavMenuCache to new namespace
  * update baseline
  * refactor(used static methods)
  * removed phpcs comments
  * removed extra phpdoc
  * refactor(used static methods)
  * added phpdoc
  * refactor CleanUp class to use static methods
  * update attributes generation
  * refactor to use static methods
  * regenerate baseline
  * ingnore MissingFile error
  * refactor to use static methods
  * fix phpdoc format
  * added type for newPostArgs
  * reduced extra type definition
  * autoload wp constants file
  * used get_posts function for app_get_wp_block
  * added ResourceHints class to autoload list
  * rename class
  * update type casting
  * moved to Utils namespace
  * removed anonymous functions
  * removed anonymous functions
  * disable EncapsedStringsToSprintfRector rule
  * moved to Utils namespace
  * moved to Utils namespace
  * reduce complexity
  * ignore some rector rules
  * fix code style
  * updated require-dev section
  * init new WPResourceHints class
  * update phpdoc
  * installed my own php-cs-fixer rules
  * update phpdoc
  * used static methods for jsToFooter and jqueryFromCdn
  * fix code style
  * regenerate base line
  * used jquery-ui from jsdelivr
  * fix code style
  * used jquery-ui from jsdelivr
  * Merge branch 'feature/reviewdog' into develop
  * test Psalm run
  * test Psalm run
  * removed allowPhpStormGenerics option
  * refactor setMenuItemClassesByContext method
  * update psalm annotations style
  * enable phpmd cmd
  * update exclude paths config
  * regenerate baseline files
  * disable DEFLUENT rules
  * ignore some files
  * updated composer scripts
  * update phpmd baseline
  * ignore rector.php config file
  * moved cache file to cache dir
  * autoload stubs
  * update phpstan config
  * removed WP_CLI class
  * rebuild baseline
  * init phive config
  * removed Cli commands
  * update psalm config
  * ignore vimeo/psalm
  * removed edgedesign/phpqa
  * renamed: .github/workflows/phpmd.yml -> .github/workflows/reviewdog.yml
  * renamed: .github/workflows/phpmd.yml -> .github/workflows/reviewdog.yml
  * update format for psalm
  * update format for psalm
  * update format for psalm
  * update format for psalm
  * set level for reviewdog
  * init reviewdog config
  * enable Psalm
  * ignore src/Cli dir
  * removed phpstan/phpstan-deprecation-rules
  * rebuild base line
  * removed humanmade/psalm-plugin-wordpress
  * suppress some minor warnings
  * update types for metadata
  * update baseline
  * convert array to object in removePluginUpdates method
  * added more phpdoc and types
  * disable Latitude query builder
  * removed unnecessary var annotation
  * fix MixedAssignment
  * fix MixedArgumentTypeCoercion
  * fix InvalidPropertyAssignmentValue
  * upda6te type for countedTerms prop
  * update phpdoc
  * install humanmade/psalm-plugin-wordpress
  * rebuild base line
  * php-cs-fix
  * encapsed strings to sprintf
  * add more strict types
  * Applied rules:  * DateTimeToDateTimeInterfaceRector  * NarrowUnionTypeDocRector  * ChangeAndIfToEarlyReturnRector  * AddArrayParamDocTypeRector  * AddArrayReturnDocTypeRector  * ReturnTypeFromStrictTypedCallRector
  * Applied rules:  * EncapsedStringsToSprintfRector  * RenameForeachValueVariableToMatchExprVariableRector  * AddArrayReturnDocTypeRector
  * switch negated ternary
  * update type in Cache class
  * removed is_wp_error function
  * added more phpdoc
  * added more phpdoc
  * regenerate baseline
  * rename check to cs-check
  * run php-cs-fixer
  * refactor types and phpdoc
  * copy all post meta data to new post
  * removed extra type cast
  * update rector/rector
  * update types
  * update menu placeholder types
  * update menu placeholder types
  * removed RedundantCastGivenDocblockType
  * added type for liner functions
  * disable no_superfluous_phpdoc_tags rule
  * refactor var names
  * update phpmd command
  * Merge branch 'feature/rector' into develop
  * php-cs-fixer
  * enable SetList::DEFLUENT
  * rebuild baseline
  * check if app_get_taxonomy_ancestors return an array
  * add type to privat props
  * Rector SetList::CODING_STYLE
  * php-cs-fixer fix
  * used Rector SetList::TYPE_DECLARATION_STRICT
  * refactor var names
  * run php-cs-fixer
  * used Rector SetList::EARLY_RETURN
  * php-cs-fixer fix
  * Rector fix
  * set type for wp_version
  * install rector/rector
  * install rector/rector
  * rebuild base line
  * moved cache dir to build
  * Merge branch 'feature/psalm-fix' into develop
  * added php-cs-fixer config
  * used menu placeholder classes
  * set string type for size param
  * update baseline
  * added globals vars list
  * SuppressWarnings(PHPMD)
  * extend by stdClass
  * removed phpstan/phpstan-strict-rules
  * set menu_item_parent to int type
  * added NavMenuArgs Placeholder class
  * added MenuItem Placeholder class
  * set string[] types for $publicPostTypes
  * rebuild baseline
  * rebuild baseline
  * set strict rules
  * rebuild phpdoc for LastPostModified
  * install phpstan/extension-installer
  * install phpstan/extension-installer
  * fix types in phpdoc
  * fix UnusedForeachValue
  * set max level of error detection
  * update psalm composer scripts
  * update phpdoc type for esc_attr params
  * update phpdoc type for pdo params
  * rebuild baseline
  * update composer scripts for psalm
  * fix InvalidReturnStatement
  * refactor type declaration
  * move  to top
  * set correct scalar type
  * suppress TooManyArguments for apply_filters
  * check array offset
  * update base line
  * updated composer scripts
  * fix return type and phpdoc
  * update phpdoc: set specific return type
  * set php version to 7.4
  * fix global var scope
  * bump jazzman/autoload-interface
  * move global  in to right position
  * install roots/wordpress as dev dependencies
  * psalm --alter --issues=MissingReturnType
  * regenerate base line
  * fix problems in src/Optimization/DuplicatePost.php
  * removed humanmade/psalm-plugin-wordpress
  * replace is_wp_error => WP_Error
  * update phpqa config
  * update phpdoc type for ancestors
  * enable wordpress extension
  * rebuild baseline for phpmd
  * added script to start php server
  * init psalm-baseline file
  * removed roots/wordpress as dev dependencies
  * updated jazzman/wp-db-pdo
  * removed "replace" config
  * removed woocommerce_install_skip_create_files hook
  * disable security-checker and parallel-lint
  * generate baseline files and add to repo
  * generate psalm baseline file
  * refactor Cyclomatic Complexity
  * update config for phpstan
  * disabled UnusedLocalVariable rule
  * removed SuppressWarnings phpdoc comments
  * removed UnusedFormalParameter phpmd inspection
  * removed comments
  * added php script
  * removed PhpUnusedParameterInspection phpdoc comment
  * update phpdoc
  * fix Cyclomatic Complexity
  * update phpdoc
  * set types for private props
  * php.yml -> phpmd.yml
  * removed macfja/phpqa-extensions and qossmic/deptrac-shim
  * update php pipeline
  * removed rskuipers/php-assumptions
  * removed enlightn/security-checker,povils/phpmnd, symfony/var-dumper and add composer scripts
  * ignore phpmd baseline and local phpstan config
  * phpstan.neon -> phpstan.neon.dist
  * disable MissingImport rule
  * update docker image
  * update phpqa action
  * Create php_test.yml
  * update phpqa action
  * update phpqa image
  * Merge branch 'develop'
  * rename phpqa to php
  * add workflows
  * Delete codacy-analysis.yml
  * delete workflows
  * Create php.yml
  * Merge branch 'develop' of github.com:Jazz-Man/wp-performance into develop
  * delete codacy-analysis workflows
  * Update phpqa.yml
  * not ignore .github dir
  * decompose AttachmentData class
  * update phpdoc
  * used composer script to run commands
  * disable parallel execution
  * init phpqa workflow
  * update ignored Dirs for phpqa
  * removed replace section
  * update php version
  * update phpdoc
  * decompose NavMenuCache class
  * move to JazzMan\Performance\Optimization\NavMenu namespace
  * refactor phpdoc and types
  * ignore php cs fixer cache
  * decompose wrapId method
  * decompose setupNavMenuItem method
  * updated analisis config
  * ignore cache dir
  * decompose app_get_term_link function
  * update dev
  * removed wp-cli/wp-cli and install php-stubs/wp-cli-stubs
  * update phpdoc and return type
  * update phpdoc and return type
  * removed curly braces
  * refactor naming and phpdoc
  * install phpstan and psalm plugin for wordpress
  * ignore build dir
  * move all static functions to helper.php
  * refactor phpdoc and return types
  * refactor try catch and phpdoc types
  * added app_make_link_relative function
  * refactor naming and phpdoc
  * init psalm config
  * enable Superglobals rule
  * init base config for phpstan
  * init base config for phpqa
  * refactoring app_get_term_link function
  * decompose setScriptVersion method
  * decompose duplicatePostAsDraft method
  * disabled ob_start in header
  * moved all remove_action for wp_head to separate method
  * disable body_class filter
  * update phpdoc and returns type
  * removed Options.php class (https://core.trac.wordpress.org/ticket/31245)
  * update return type
  * refactor naming
  * refactor naming
  * fix code style
  * move props to local vars
  * suppress inspection warnings
  * removed extra public methods in class
  * removed extra params
  * fix CamelCaseVariableName rules
  * fix phpmd rules
  * fix CamelCaseVariableName rule
  * fix CamelCaseVariableName rule
  * disable UndefinedVariable rule
  * remove global function import
  * fix CamelCaseVariableName rule
  * suppress phpmd warnings
  * decompose class methods
  * init privat props
  * force array type
  * fix CamelCase rule
  * fix ElseExpression rule
  * fix CamelCaseClassName rule
  * disable StaticAccess rule and enable BooleanGetMethodName
  * update phpdoc
  * removed extra public methods
  * fix CamelCaseParameterName errors in WPQuery::class
  * disable error suppressing
  * add phpqa extensions
  * install edgedesign/phpqa
  * install phpmd/phpmd end add rules
  * replace wp_get_attachment_image_url to app_get_attachment_image_url
  * added branches list
  * Merge branch 'master' of github.com:Jazz-Man/wp-performance
  * fix menu item url
  * update wp-cli/wp-cli version
  * Create codacy-analysis.yml
  * Merge tag '2.2.7' into develop

2.2.5 / 2021-04-16
==================

  * install WP_CLI as dev dependency

2.2.4 / 2021-04-16
==================

  * update CHANGELOG.md
  * check if metadata not empty

2.2.3 / 2021-04-16
==================

  * update CHANGELOG.md
  * add NavMenuCache class
  * update phpdoc
  * update list of helpers functions
  * fix sql join for imageAlt and refactor naming
  * update wp-cli commands
  * join meta table if _wp_attachment_image_alt is exist
  * fix image urls
  * fix post guid
  * fix media library Months data

2.2.0 / 2021-04-15
==================

  * update CHANGELOG
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
