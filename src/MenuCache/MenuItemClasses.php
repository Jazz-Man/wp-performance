<?php

namespace JazzMan\Performance\MenuCache;

use JazzMan\PerformanceStub\MenuItem;
use WP_Error;
use WP_Post;
use WP_Post_Type;
use WP_Query;
use WP_Rewrite;
use WP_Taxonomy;
use WP_Term;
use WP_User;

/**
 * @deprecated
 */
class MenuItemClasses {
    private WP_Rewrite $wpRewrite;

    private WP_Query $wpQuery;

    /**
     * @var null|WP_Post|WP_Post_Type|WP_Term|WP_User
     */
    private $curentQueriedObject;

    private string $frontPageUrl;

    private ?int $frontPageId;

    private ?int $homePageId;

    private int $queriedObjectId;

    /**
     * @var int[]
     */
    private array $objectParents = [];

    /**
     * @var array<string,int[]>
     */
    private $taxonomyAncestors = [];

    public function __construct() {
        global $wp_query, $wp_rewrite;

        $this->wpRewrite = $wp_rewrite;
        $this->wpQuery = $wp_query;
        $this->queriedObjectId = $this->wpQuery->get_queried_object_id();

        $this->curentQueriedObject = $this->wpQuery->get_queried_object();

        $this->frontPageId = self::getIntOption('page_on_front');
        $this->homePageId = self::getIntOption('page_for_posts');

        $this->frontPageUrl = home_url();

        $this->setTaxonomyAncestors();
    }

    /**
     * @param MenuItem[]|\stdClass[] $menuItems
     */
    public function setMenuItemClassesByContext(array &$menuItems): void {
        $activeObject = '';
        $ancestorItemIds = [];
        $activeParentItemIds = [];
        $parentObjectIds = [];

        $privacyPolicyPageId = self::getIntOption('wp_page_for_privacy_policy');

        foreach ($menuItems as $key => $menuItem) {
            $menuItems[$key]->current = false;

            /** @var string[] $classes */
            $classes = (array) $menuItem->classes;
            $classes[] = 'menu-item';
            $classes[] = sprintf('menu-item-type-%s', (string) $menuItem->type);
            $classes[] = sprintf('menu-item-object-%s', (string) $menuItem->object);

            if ('post_type' === $menuItem->type) {
                if (!empty($this->frontPageId) && $this->frontPageId === (int) $menuItem->object_id) {
                    $classes[] = 'menu-item-home';
                }

                if (!empty($privacyPolicyPageId) && $privacyPolicyPageId === (int) $menuItem->object_id) {
                    $classes[] = 'menu-item-privacy-policy';
                }
            }

            if ($this->wpQuery->is_singular && 'taxonomy' === (string) $menuItem->type && \in_array((int) $menuItem->object_id, $this->objectParents, true)) {
                $parentObjectIds[] = (int) $menuItem->object_id;
                $activeParentItemIds[] = (int) $menuItem->db_id;

                if ($this->curentQueriedObject instanceof WP_Post) {
                    $activeObject = $this->curentQueriedObject->post_type;
                }
            } elseif ($this->isCurrentMenuItemt($menuItem)) {
                $classes[] = 'current-menu-item';
                $menuItems[$key]->current = true;

                if (!\in_array((int) $menuItem->db_id, $ancestorItemIds, true)) {
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
            } elseif ('post_type_archive' === (string) $menuItem->type && $this->wpQuery->is_post_type_archive((string) $menuItem->object)) {
                $classes[] = 'current-menu-item';
                $menuItems[$key]->current = true;

                if (!\in_array((int) $menuItem->db_id, $ancestorItemIds, true)) {
                    $ancestorItemIds[] = (int) $menuItem->db_id;
                }
                $activeParentItemIds[] = (int) $menuItem->menu_item_parent;
            } elseif ('custom' === (string) $menuItem->object && filter_input(INPUT_SERVER, 'HTTP_HOST')) {
                $rootRelativeCurrent = app_get_current_relative_url();

                $currentUrl = app_get_current_url();

                $isUrlHash = strpos((string) $menuItem->url, '#');

                $rawItemUrl = $isUrlHash ? (string) substr((string) $menuItem->url, 0, $isUrlHash) : (string) $menuItem->url;
                unset($isUrlHash);

                $itemUrl = set_url_scheme(untrailingslashit($rawItemUrl));
                $indexlessCurrent = untrailingslashit(
                    (string) preg_replace('/'.preg_quote($this->wpRewrite->index, '/').'$/', '', $currentUrl)
                );

                $matches = [
                    $currentUrl,
                    urldecode($currentUrl),
                    $indexlessCurrent,
                    urldecode($indexlessCurrent),
                    $rootRelativeCurrent,
                    urldecode($rootRelativeCurrent),
                ];

                if ($rawItemUrl && \in_array($itemUrl, $matches, true)) {
                    $classes[] = 'current-menu-item';
                    $menuItems[$key]->current = true;

                    if (!\in_array((int) $menuItem->db_id, $ancestorItemIds, true)) {
                        $ancestorItemIds[] = (int) $menuItem->db_id;
                    }

                    if (\in_array(
                        $this->frontPageUrl,
                        [
                            untrailingslashit($currentUrl),
                            untrailingslashit($indexlessCurrent),
                        ],
                        true
                    )) {
                        // Back compat for home link to match wp_page_menu().
                        $classes[] = 'current_page_item';
                    }
                    $activeParentItemIds[] = (int) $menuItem->menu_item_parent;
                    $parentObjectIds[] = (int) $menuItem->post_parent;
                    $activeObject = (string) $menuItem->object;
                } elseif ($itemUrl === $this->frontPageUrl && $this->wpQuery->is_front_page()) {
                    $classes[] = 'current-menu-item';
                }

                if (untrailingslashit($itemUrl) === $this->frontPageUrl) {
                    $classes[] = 'menu-item-home';
                }
            }

            // Back-compat with wp_page_menu(): add "current_page_parent" to static home page link for any non-page query.
            if (
                'post_type' === (string) $menuItem->type
                && empty($this->wpQuery->is_page)
                && (!empty($this->homePageId) && $this->homePageId === (int) $menuItem->object_id)
            ) {
                $classes[] = 'current_page_parent';
            }

            /** @var string[] $classes */
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

            if ($this->isCurrentMenuItemtAncestor($parentItem)) {
                $ancestorType = false;

                if ($this->curentQueriedObject instanceof WP_Term) {
                    $ancestorType = $this->curentQueriedObject->taxonomy;
                }

                if ($this->curentQueriedObject instanceof WP_Post) {
                    $ancestorType = $this->curentQueriedObject->post_type;
                }

                if (!empty($ancestorType)) {
                    $classes[] = sprintf('current-%s-ancestor', $ancestorType);
                }

                unset($ancestorType);
            }

            if (\in_array((int) $parentItem->db_id, $ancestorItemIds, true)) {
                $classes[] = 'current-menu-ancestor';

                $menuItems[$key]->current_item_ancestor = true;
            }

            if (\in_array((int) $parentItem->db_id, $activeParentItemIds, true)) {
                $classes[] = 'current-menu-parent';

                $menuItems[$key]->current_item_parent = true;
            }

            if (\in_array((int) $parentItem->object_id, $parentObjectIds, true)) {
                $classes[] = sprintf('current-%s-parent', $activeObject);
            }

            if ('post_type' === $parentItem->type && 'page' === $parentItem->object) {
                // Back compat classes for pages to match wp_page_menu().
                if (\in_array('current-menu-parent', $classes, true)) {
                    $classes[] = 'current_page_parent';
                }

                if (\in_array('current-menu-ancestor', $classes, true)) {
                    $classes[] = 'current_page_ancestor';
                }
            }

            $menuItems[$key]->classes = array_unique($classes);
        }
    }

    private function setTaxonomyAncestors(): void {
        if ($this->wpQuery->is_singular && $this->curentQueriedObject instanceof WP_Post && !is_post_type_hierarchical($this->curentQueriedObject->post_type)) {
            /** @var array<string,WP_Taxonomy> $taxonomies */
            $taxonomies = get_object_taxonomies($this->curentQueriedObject->post_type, 'objects');

            foreach ($taxonomies as $taxonomy => $taxonomyObject) {
                if ($taxonomyObject->hierarchical && $taxonomyObject->public) {
                    /** @var array<int,int> $termHierarchy */
                    $termHierarchy = _get_term_hierarchy($taxonomy);

                    /** @var int[]|WP_Error $terms */
                    $terms = wp_get_object_terms($this->queriedObjectId, $taxonomy, ['fields' => 'ids']);

                    if (\is_array($terms)) {
                        $this->objectParents = array_merge($this->objectParents, $terms);

                        /** @var array<int,int> $termToAncestor */
                        $termToAncestor = [];

                        foreach ($termHierarchy as $anc => $descs) {
                            foreach ((array) $descs as $desc) {
                                $termToAncestor[$desc] = $anc;
                            }
                        }

                        foreach ($terms as $term) {
                            do {
                                $this->taxonomyAncestors[$taxonomy][] = $term;

                                if (isset($termToAncestor[$term])) {
                                    $_desc = $termToAncestor[$term];
                                    unset($termToAncestor[$term]);
                                    $term = $_desc;
                                } else {
                                    $term = 0;
                                }
                            } while (!empty($term));
                        }
                    }
                }
            }
        } elseif ($this->curentQueriedObject instanceof WP_Term) {
            /** @var array<int,int> $termHierarchy */
            $termHierarchy = _get_term_hierarchy($this->curentQueriedObject->taxonomy);

            /** @var array<int,int> $termToAncestor */
            $termToAncestor = [];

            foreach ($termHierarchy as $anc => $descs) {
                foreach ((array) $descs as $desc) {
                    $termToAncestor[$desc] = $anc;
                }
            }

            $desc = $this->curentQueriedObject->term_id;

            do {
                $this->taxonomyAncestors[$this->curentQueriedObject->taxonomy][] = $desc;

                if (isset($termToAncestor[$desc])) {
                    $_desc = $termToAncestor[$desc];
                    unset($termToAncestor[$desc]);
                    $desc = $_desc;
                } else {
                    $desc = 0;
                }
            } while (!empty($desc));
        }

        $this->objectParents = array_map('absint', array_filter($this->objectParents));
    }

    /**
     * @param MenuItem|\stdClass $menuItem
     */
    private function isCurrentMenuItemt($menuItem): bool {
        if ((int) $menuItem->object_id === $this->queriedObjectId) {
            return true;
        }

        if ('post_type' == (string) $menuItem->type) {
            if (
                $this->wpQuery->is_home
                && (!empty($this->homePageId) && $this->homePageId === (int) $menuItem->object_id)
            ) {
                return true;
            }

            if ($this->wpQuery->is_singular) {
                return true;
            }
        } elseif ('taxonomy' == (string) $menuItem->type && $this->curentQueriedObject instanceof WP_Term) {
            $isCategory = $this->wpQuery->is_category || $this->wpQuery->is_tag || $this->wpQuery->is_tax;

            return $isCategory && $this->curentQueriedObject->taxonomy === (string) $menuItem->object;
        }

        return false;
    }

    /**
     * @param MenuItem|\stdClass $parent
     */
    private function isCurrentMenuItemtAncestor($parent): bool {
        if (empty($parent->type)) {
            return false;
        }

        if ('post_type' === $parent->type && $this->curentQueriedObject instanceof WP_Post) {
            if (!is_post_type_hierarchical($this->curentQueriedObject->post_type)) {
                return false;
            }

            if (!\in_array((int) $parent->object_id, $this->curentQueriedObject->ancestors, true)) {
                return false;
            }

            return !empty($parent->object) && $parent->object_id !== $this->curentQueriedObject->ID;
        }

        if ('taxonomy' !== $parent->type) {
            return false;
        }

        if (!$this->curentQueriedObject instanceof WP_Term) {
            return false;
        }

        if (!isset($this->taxonomyAncestors[(string) $parent->object])) {
            return false;
        }

        return \in_array((int) $parent->object_id, $this->taxonomyAncestors[(string) $parent->object], true)
               && (int) $parent->object_id !== $this->curentQueriedObject->term_id;
    }

    private static function getIntOption(string $optionName): ?int {
        /** @var false|int $option */
        $option = get_option($optionName);

        return !empty($option) ? $option : null;
    }
}
