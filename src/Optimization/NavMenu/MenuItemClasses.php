<?php

namespace JazzMan\Performance\Optimization\NavMenu;

use JazzMan\Performance\Optimization\NavMenu\Placeholder\MenuItem;
use stdClass;
use WP_Error;
use WP_Post;
use WP_Post_Type;
use WP_Taxonomy;
use WP_Term;
use WP_User;

class MenuItemClasses {
    /**
     * @param MenuItem[]|stdClass[] $menuItems
     */
    public static function setMenuItemClassesByContext(array &$menuItems): void {
        global $wp_query, $wp_rewrite;

        /** @var WP_Term|WP_Post_Type|WP_Post|WP_User|null $object */
        $object = $wp_query->get_queried_object();
        $queriedObjectId = $wp_query->queried_object_id;

        $activeObject = '';
        $ancestorItemIds = [];
        $activeParentItemIds = [];
        $parentObjectIds = [];
        $taxonomyAncestors = [];
        /** @var int[] $objectParents */
        $objectParents = [];
        $homePageId = (int) get_option('page_for_posts');

        if ($wp_query->is_singular && $object instanceof WP_Post && ! is_post_type_hierarchical($object->post_type)) {
            /** @var array<string,WP_Taxonomy> $taxonomies */
            $taxonomies = get_object_taxonomies($object->post_type, 'objects');

            foreach ($taxonomies as $taxonomy => $taxonomyObject) {
                if ($taxonomyObject->hierarchical && $taxonomyObject->public) {
                    /** @var array<int,int> $termHierarchy */
                    $termHierarchy = _get_term_hierarchy($taxonomy);
                    /** @var int[]|WP_Error $terms */
                    $terms = wp_get_object_terms($queriedObjectId, $taxonomy, [ 'fields' => 'ids']);

                    if (is_array($terms)) {
                        $objectParents = array_merge($objectParents, $terms);
                        /** @var array<int,int> $termToAncestor */
                        $termToAncestor = [];

                        foreach ($termHierarchy as $anc => $descs) {
                            foreach ((array) $descs as $desc) {
                                $termToAncestor[$desc] = $anc;
                            }
                        }

                        foreach ($terms as $term) {
                            do {
                                $taxonomyAncestors[$taxonomy][] = $term;

                                if (isset($termToAncestor[$term])) {
                                    $_desc = $termToAncestor[$term];
                                    unset($termToAncestor[$term]);
                                    $term = $_desc;
                                } else {
                                    $term = 0;
                                }
                            } while ( ! empty($term));
                        }
                    }
                }
            }
        } elseif ( $object instanceof WP_Term && is_taxonomy_hierarchical($object->taxonomy)) {
            /** @var array<int,int> $termHierarchy */
            $termHierarchy = _get_term_hierarchy($object->taxonomy);
            /** @var array<int,int> $termToAncestor */
            $termToAncestor = [];

            foreach ($termHierarchy as $anc => $descs) {
                foreach ((array) $descs as $desc) {
                    $termToAncestor[$desc] = $anc;
                }
            }
            $desc = $object->term_id;

            do {
                $taxonomyAncestors[$object->taxonomy][] = $desc;

                if (isset($termToAncestor[$desc])) {
                    $_desc = $termToAncestor[$desc];
                    unset($termToAncestor[$desc]);
                    $desc = $_desc;
                } else {
                    $desc = 0;
                }
            } while ( ! empty($desc));
        }

        $objectParents = array_filter($objectParents);

        $frontPageUrl = home_url();
        $frontPageId = (int) get_option('page_on_front');
        $privacyPolicyPageId = (int) get_option('wp_page_for_privacy_policy');

        foreach ($menuItems as $key => $menuItem) {
            $menuItems[$key]->current = false;

            /** @var string[] $classes */
            $classes = (array) $menuItem->classes;
            $classes[] = 'menu-item';
            $classes[] = sprintf('menu-item-type-%s', (string) $menuItem->type);
            $classes[] = sprintf('menu-item-object-%s', (string) $menuItem->object);

            if ('post_type' === $menuItem->type) {
                if ($frontPageId === (int) $menuItem->object_id) {
                    $classes[] = 'menu-item-home';
                }

                if ($privacyPolicyPageId === (int) $menuItem->object_id) {
                    $classes[] = 'menu-item-privacy-policy';
                }
            }

            if ($wp_query->is_singular && 'taxonomy' === (string) $menuItem->type && in_array((int) $menuItem->object_id, $objectParents, true)) {
                $parentObjectIds[] = (int) $menuItem->object_id;
                $activeParentItemIds[] = (int) $menuItem->db_id;
                $activeObject = (string) $object->post_type;
            } elseif (self::isCurrentMenuItemt($menuItem, $queriedObjectId, $object, $homePageId)) {
                $classes[] = 'current-menu-item';
                $menuItems[$key]->current = true;

                if ( ! in_array((int) $menuItem->db_id, $ancestorItemIds, true)) {
                    $ancestorItemIds[] = (int) $menuItem->db_id;
                }

                if ('post_type' === (string) $menuItem->type && 'page' === (string) $menuItem->object) {
                    $classes[] = 'page_item';
                    $classes[] = sprintf('page-item-%d', (int) $menuItem->object_id);
                    $classes[] = 'current_page_item';
                }

                $activeParentItemIds[] = (int) $menuItem->menu_item_parent;
                $parentObjectIds[] = (int) $menuItem->post_parent;
                $activeObject = (string) $menuItem->object;
            } elseif ('post_type_archive' === (string) $menuItem->type && is_post_type_archive([(string) $menuItem->object])) {
                $classes[] = 'current-menu-item';
                $menuItems[$key]->current = true;

                if ( ! in_array((int) $menuItem->db_id, $ancestorItemIds, true)) {
                    $ancestorItemIds[] = (int) $menuItem->db_id;
                }
                $activeParentItemIds[] = (int) $menuItem->menu_item_parent;
            } elseif ('custom' === (string) $menuItem->object && filter_input(INPUT_SERVER, 'HTTP_HOST')) {
                $rootRelativeCurrent = app_get_current_relative_url();

                $currentUrl = app_get_current_url();

                $isUrlHash = strpos((string) $menuItem->url, '#');

                $rawItemUrl = $isUrlHash ? (string) substr( (string) $menuItem->url, 0, $isUrlHash ) : (string) $menuItem->url;
                unset($isUrlHash);

                $itemUrl = set_url_scheme(untrailingslashit($rawItemUrl));
                $indexlessCurrent = untrailingslashit(
                    (string) preg_replace('/' . preg_quote($wp_rewrite->index, '/') . '$/', '', $currentUrl)
                );

                $matches = [
                    $currentUrl,
                    urldecode($currentUrl),
                    $indexlessCurrent,
                    urldecode($indexlessCurrent),
                    $rootRelativeCurrent,
                    urldecode($rootRelativeCurrent),
                ];

                if ($rawItemUrl && in_array($itemUrl, $matches, true)) {
                    $classes[] = 'current-menu-item';
                    $menuItems[$key]->current = true;

                    if ( ! in_array((int) $menuItem->db_id, $ancestorItemIds, true)) {
                        $ancestorItemIds[] = (int) $menuItem->db_id;
                    }

                    if (in_array($frontPageUrl, [untrailingslashit($currentUrl), untrailingslashit($indexlessCurrent)], true)) {
                        // Back compat for home link to match wp_page_menu().
                        $classes[] = 'current_page_item';
                    }
                    $activeParentItemIds[] = (int) $menuItem->menu_item_parent;
                    $parentObjectIds[] = (int) $menuItem->post_parent;
                    $activeObject = (string) $menuItem->object;
                } elseif ($itemUrl === $frontPageUrl && is_front_page()) {
                    $classes[] = 'current-menu-item';
                }

                if (untrailingslashit($itemUrl) === $frontPageUrl) {
                    $classes[] = 'menu-item-home';
                }
            }

            // Back-compat with wp_page_menu(): add "current_page_parent" to static home page link for any non-page query.
            if ( ! empty($homePageId) && 'post_type' === (string) $menuItem->type
                 && empty($wp_query->is_page) && $homePageId === (int) $menuItem->object_id
            ) {
                $classes[] = 'current_page_parent';
            }

            $menuItems[$key]->classes = array_unique($classes);
        }
        $ancestorItemIds = array_filter(array_unique($ancestorItemIds));
        $activeParentItemIds = array_filter(array_unique($activeParentItemIds));
        $parentObjectIds = array_filter(array_unique($parentObjectIds));

        // Set parent's class.
        foreach ($menuItems as $key => $parentItem) {
            /** @var string[] $classes */
            $classes = (array) $parentItem->classes;
            $menuItems[$key]->current_item_ancestor = false;
            $menuItems[$key]->current_item_parent = false;

            if (self::isCurrentMenuItemtAncestor($parentItem, $object, $taxonomyAncestors)) {
                $classes[] = sprintf( 'current-%s-ancestor', $object->taxonomy ?: $object->post_type );
            }

            if (in_array((int) $parentItem->db_id, $ancestorItemIds, true)) {
                $classes[] = 'current-menu-ancestor';

                $menuItems[$key]->current_item_ancestor = true;
            }

            if (in_array((int) $parentItem->db_id, $activeParentItemIds, true)) {
                $classes[] = 'current-menu-parent';

                $menuItems[$key]->current_item_parent = true;
            }

            if (in_array((int) $parentItem->object_id, $parentObjectIds, true)) {
                $classes[] = sprintf('current-%s-parent', $activeObject);
            }

            if ('post_type' === $parentItem->type && 'page' === $parentItem->object) {
                // Back compat classes for pages to match wp_page_menu().
                if (in_array('current-menu-parent', $classes, true)) {
                    $classes[] = 'current_page_parent';
                }

                if (in_array('current-menu-ancestor', $classes, true)) {
                    $classes[] = 'current_page_ancestor';
                }
            }

            $menuItems[$key]->classes = array_unique($classes);
        }
    }

    /**
     * @param MenuItem|stdClass                         $menuItem
     * @param int                                       $queriedObjectId
     * @param WP_Post|WP_Post_Type|WP_Term|WP_User|null $queriedObject
     * @param int|null                                  $homePageId
     *
     * @return bool
     */
    private static function isCurrentMenuItemt(stdClass $menuItem, int $queriedObjectId, $queriedObject, ?int $homePageId = null): bool {
        global $wp_query;

        if ((int) $menuItem->object_id === $queriedObjectId) {
            return true;
        }

        if ((string) $menuItem->type == 'post_type') {
            if ( ! empty($homePageId) && $wp_query->is_home && $homePageId === (int) $menuItem->object_id) {
                return true;
            }

            if ($wp_query->is_singular) {
                return true;
            }
        } elseif ((string) $menuItem->type == 'taxonomy' && $queriedObject instanceof WP_Term) {
            $isCategory = $wp_query->is_category || $wp_query->is_tag || $wp_query->is_tax;

            return  $isCategory && $queriedObject->taxonomy === (string) $menuItem->object;
        }

        return false;
    }

    /**
     * @param MenuItem|stdClass                         $parent
     * @param WP_Post|WP_Post_Type|WP_Term|WP_User|null $object
     * @param array<string,array<int,int>>              $ancestors
     *
     * @return bool
     */
    private static function isCurrentMenuItemtAncestor(stdClass $parent, $object, array $ancestors): bool {
        if (empty($parent->type)) {
            return false;
        }

        if ((string) $parent->type == 'post_type' && $object instanceof WP_Post) {
            $isHierarchical = ! empty($object->post_type) && is_post_type_hierarchical($object->post_type);

            return $isHierarchical && in_array((int) $parent->object_id, $object->ancestors, true) && $parent->object !== $object->ID;
        }

        if ((string) $parent->type != 'taxonomy') {
            return false;
        }

        if (!$object instanceof WP_Term) {
            return false;
        }

        return isset($ancestors[(string) $parent->object]) && in_array((int) $parent->object_id, $ancestors[(string) $parent->object], true) && $parent->object_id !== $object->term_id;
    }
}
