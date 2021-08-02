<?php

namespace JazzMan\Performance\Optimization;

use Exception;
use JazzMan\AutoloadInterface\AutoloadInterface;
use JazzMan\Performance\Utils\Cache;
use function Latitude\QueryBuilder\alias;
use function Latitude\QueryBuilder\field;
use function Latitude\QueryBuilder\on;
use Latitude\QueryBuilder\QueryFactory;
use PDO;
use stdClass;
use WP_Post;
use WP_Post_Type;
use WP_Taxonomy;
use WP_Term;
use WP_User;

class NavMenuCache implements AutoloadInterface {
    public function load() {
        add_filter('wp_nav_menu_args', [$this, 'setMenuFallbackParams']);
        add_filter('pre_wp_nav_menu', [$this, 'buildWpNavMenu'], 10, 2);
    }

    public static function setupNavMenuItem(stdClass $menuItem): stdClass {
        if ( ! empty($menuItem->post_type)) {
            if ('nav_menu_item' === $menuItem->post_type) {
                $menuItem->db_id = (int) $menuItem->ID;
                $menuItem->menu_item_parent = (int) $menuItem->menu_item_parent;
                $menuItem->object_id = (int) $menuItem->object_id;

                $menuItem = self::setupNavMenuItemByType($menuItem);

                $menuItem->attr_title = ! empty($menuItem->attr_title) ? $menuItem->attr_title : apply_filters('nav_menu_attr_title', $menuItem->post_excerpt);

                if ( ! isset($menuItem->description)) {
                    $menuItem->description = apply_filters('nav_menu_description', wp_trim_words($menuItem->post_content, 200));
                }

                $menuItem->classes = (array) maybe_unserialize($menuItem->classes);

                return $menuItem;
            }

            $menuItem->db_id = 0;
            $menuItem->menu_item_parent = 0;
            $menuItem->object_id = (int) $menuItem->ID;
            $menuItem->type = 'post_type';

            $object = get_post_type_object($menuItem->post_type);

            $isPostType = $object instanceof WP_Post_Type;

            $menuItem->object = $isPostType ? $object->name : '';
            $menuItem->type_label = $isPostType ? $object->labels->singular_name : '';
            $menuItem->post_title = '' === $menuItem->post_title ? sprintf(__('#%d (no title)'), $menuItem->ID) : $menuItem->post_title;

            $menuItem->title = $menuItem->post_title;
            $menuItem->url = get_permalink($menuItem->ID);
            $menuItem->target = '';
            $menuItem->attr_title = apply_filters('nav_menu_attr_title', '');
            $menuItem->description = apply_filters('nav_menu_description', '');
            $menuItem->classes = [];
            $menuItem->xfn = '';

            return $menuItem;
        }

        if ( ! empty($menuItem->taxonomy)) {
            $menuItem->ID = $menuItem->term_id;
            $menuItem->db_id = 0;
            $menuItem->menu_item_parent = 0;
            $menuItem->object_id = (int) $menuItem->term_id;
            $menuItem->post_parent = (int) $menuItem->parent;
            $menuItem->type = 'taxonomy';

            /** @var WP_Taxonomy $object */
            $object = get_taxonomy($menuItem->taxonomy);

            $isTaxonomy = $object instanceof WP_Taxonomy;

            $menuItem->object = $isTaxonomy ? $object->name : '';
            $menuItem->type_label = $isTaxonomy ? $object->labels->singular_name : '';

            $menuItem->title = $menuItem->name;
            $menuItem->url = app_get_term_link((int) $menuItem->term_id, (string) $menuItem->taxonomy);
            $menuItem->target = '';
            $menuItem->attr_title = '';
            $menuItem->description = get_term_field('description', $menuItem->term_id, $menuItem->taxonomy);
            $menuItem->classes = [];
            $menuItem->xfn = '';
        }

        return $menuItem;
    }

    private static function setupNavMenuItemByType(stdClass $menuItem): stdClass {
        switch ($menuItem->type) {
            case 'post_type':
                $postTypeObject = get_post_type_object($menuItem->object);

                $originalTerm = get_post($menuItem->object_id);
                $isPost = $originalTerm instanceof WP_Post;

                $isPostType = $postTypeObject instanceof WP_Post_Type;
                $isInTrash = 'trash' === get_post_status($menuItem->object_id);

                $menuItem->type_label = $isPostType ? $postTypeObject->labels->singular_name : $menuItem->object;

                if ($isPostType && function_exists('get_post_states')) {
                    /** @var WP_Post $menuPost */
                    $menuPost = get_post($menuItem->object_id);
                    $postStates = get_post_states($menuPost);

                    if ( ! empty($postStates)) {
                        $menuItem->type_label = wp_strip_all_tags(implode(', ', $postStates));
                    }
                }

                $menuItem->_invalid = ! $isPostType || $isInTrash;

                $menuItem->url = $isPost ? get_permalink($originalTerm->ID) : '';

                $originalTitle = $isPost ?
                    apply_filters('the_title', $originalTerm->post_title, $originalTerm->ID) :
                    sprintf(__('#%d (no title)'), $menuItem->object_id);

                $menuItem->title = $menuItem->post_title ?: $originalTitle;

                $menuItem->_invalid = ! $isPost;

                break;

            case 'post_type_archive':
                $postTypeObject = get_post_type_object($menuItem->object);

                $isPostType = $postTypeObject instanceof WP_Post_Type;

                $menuItem->title = $menuItem->post_title ?: ($isPostType ? $postTypeObject->labels->archives : '');

                $menuItem->_invalid = ! $isPostType;

                $menuItem->type_label = __('Post Type Archive');
                $menuItem->url = get_post_type_archive_link($menuItem->object);

                break;

            case 'taxonomy':
                $postTypeObject = get_taxonomy($menuItem->object);
                $isTaxonomy = $postTypeObject instanceof WP_Taxonomy;
                $originalTerm = get_term($menuItem->object_id, $menuItem->object);

                $isTerm = $originalTerm instanceof WP_Term;

                $menuItem->type_label = $isTaxonomy ? $postTypeObject->labels->singular_name : $menuItem->object;

                $menuItem->url = $isTerm ? app_get_term_link($menuItem->object_id, $menuItem->object) : '';

                $originalTitle = $isTerm ? $originalTerm->name : sprintf(__('#%d (no title)'), $menuItem->object_id);

                $menuItem->_invalid = ! $isTerm || ! $isTaxonomy;

                $menuItem->title = $menuItem->post_title ?: $originalTitle;

                break;

            default:
                $menuItem->type_label = __('Custom Link');
                $menuItem->title = $menuItem->post_title;

                break;
        }

        return $menuItem;
    }

    /**
     * @param mixed $output
     *
     * @return string|false
     */
    public function buildWpNavMenu($output, stdClass $args) {
        $menu = wp_get_nav_menu_object($args->menu);

        if (empty($menu)) {
            return $output;
        }

        $menuItems = self::getMenuItems($menu);

        if ( ! is_admin()) {
            $menuItems = array_filter($menuItems, '_is_valid_nav_menu_item');
        }

        if ((empty($menuItems) && ! $args->theme_location) && isset($args->fallback_cb) && $args->fallback_cb && is_callable($args->fallback_cb)) {
            return call_user_func($args->fallback_cb, (array) $args);
        }

        $navMenu = '';
        $items = '';

        $this->setMenuItemClassesByContext($menuItems);

        $sortedMenuItems = [];
        $menuWithChildren = [];

        foreach ($menuItems as $menuItem) {
            $sortedMenuItems[$menuItem->menu_order] = $menuItem;

            if ($menuItem->menu_item_parent) {
                $menuWithChildren[$menuItem->menu_item_parent] = true;
            }
        }

        // Add the menu-item-has-children class where applicable.
        if ($menuWithChildren) {
            foreach ($sortedMenuItems as &$menuItem) {
                if (isset($menuWithChildren[$menuItem->ID])) {
                    $menuItem->classes[] = 'menu-item-has-children';
                }
            }
        }

        unset($menuItems, $menuItem);

        $sortedMenuItems = apply_filters('wp_nav_menu_objects', $sortedMenuItems, $args);

        $items .= walk_nav_menu_tree($sortedMenuItems, $args->depth, $args);
        unset($sortedMenuItems);

        $wrapId = $this->getMenuWrapId($menu, $args);

        $wrapClass = $args->menu_class ?: '';

        $items = apply_filters('wp_nav_menu_items', $items, $args);

        $items = apply_filters("wp_nav_menu_{$menu->slug}_items", $items, $args);

        if (empty($items)) {
            return false;
        }

        $navMenu .= sprintf($args->items_wrap, esc_attr($wrapId), esc_attr($wrapClass), $items);
        unset($items);

        $navMenu = $this->wrapToContainer($args, $menu, $navMenu);

        return apply_filters('wp_nav_menu', $navMenu, $args);
    }

    private function getMenuWrapId(WP_Term $menu, stdClass $args): string {
        static $menuIdSlugs = [];

        // Attributes.
        if ( ! empty($args->menu_id)) {
            return (string) $args->menu_id;
        }

        $wrapId = "menu-$menu->slug";

        while (in_array($wrapId, $menuIdSlugs, true)) {
            $pattern = '#-(\d+)$#';
            preg_match($pattern, (string) $wrapId, $matches);

            $wrapId = ! empty($matches) ? (string) preg_replace($pattern, '-' . ++$matches[1], $wrapId) : $wrapId . '-1';
        }

        $menuIdSlugs[] = $wrapId;

        return $wrapId;
    }

    private function wrapToContainer(stdClass $args, WP_Term $menu, string $navMenu): string {
        $allowedTags = apply_filters('wp_nav_menu_container_allowedtags', ['div', 'nav']);

        if (($args->container && is_string($args->container)) && in_array($args->container, $allowedTags, true)) {
            $attributes = [
                'class' => $args->container_class ?: sprintf('menu-%s-container', $menu->slug),
            ];

            if ($args->container_id) {
                $attributes['id'] = $args->container_id;
            }

            if ('nav' === $args->container && ! empty($args->container_aria_label)) {
                $attributes['aria-label'] = $args->container_aria_label;
            }

            return sprintf(
                '<%1$s %2$s>%3$s</%1$s>',
                $args->container,
                app_add_attr_to_el($attributes),
                $navMenu
            );
        }

        return $navMenu;
    }

    /**
     * @return array|bool|mixed
     */
    public static function getMenuItems(WP_Term $menuObject) {
        $cacheKey = Cache::getMenuItemCacheKey($menuObject);

        $menuItems = wp_cache_get($cacheKey, 'menu_items');

        if (false === $menuItems) {
            global $wpdb;

            try {
                $pdo = app_db_pdo();

                $sql = (new QueryFactory())
                    ->select(
                        'm.ID',
                        'm.post_title',
                        'm.post_name',
                        'm.post_parent',
                        'm.menu_order',
                        'm.post_type',
                        'm.post_content',
                        'm.post_excerpt',
                        alias('classes.meta_value', 'classes'),
                        alias('menu_item_parent.meta_value', 'menu_item_parent'),
                        alias('object.meta_value', 'object'),
                        alias('object_id.meta_value', 'object_id'),
                        alias('target.meta_value', 'target'),
                        alias('type.meta_value', 'type'),
                        alias('url.meta_value', 'url'),
                        alias('xfn.meta_value', 'xfn'),
                        alias('hide_link.meta_value', 'hide_link'),
                        alias('image_link.meta_value', 'image_link')
                    )
                    ->from(alias($wpdb->posts, 'm'))
                    ->leftJoin(alias($wpdb->term_relationships, 'tr'), on('m.ID', 'tr.object_id'))
                    ->leftJoin(
                        alias($wpdb->postmeta, 'classes'),
                        on('m.ID', 'classes.post_id')
                            ->and(field('classes.meta_key')->eq('_menu_item_classes'))
                    )
                    ->leftJoin(
                        alias($wpdb->postmeta, 'menu_item_parent'),
                        on('m.ID', 'menu_item_parent.post_id')
                            ->and(field('menu_item_parent.meta_key')->eq('_menu_item_menu_item_parent'))
                    )
                    ->leftJoin(
                        alias($wpdb->postmeta, 'object'),
                        on('m.ID', 'object.post_id')
                            ->and(field('object.meta_key')->eq('_menu_item_object'))
                    )
                    ->leftJoin(
                        alias($wpdb->postmeta, 'object_id'),
                        on('m.ID', 'object_id.post_id')
                            ->and(field('object_id.meta_key')->eq('_menu_item_object_id'))
                    )
                    ->leftJoin(
                        alias($wpdb->postmeta, 'target'),
                        on('m.ID', 'target.post_id')
                            ->and(field('target.meta_key')->eq('_menu_item_target'))
                    )
                    ->leftJoin(
                        alias($wpdb->postmeta, 'type'),
                        on('m.ID', 'type.post_id')
                            ->and(field('type.meta_key')->eq('_menu_item_type'))
                    )
                    ->leftJoin(
                        alias($wpdb->postmeta, 'url'),
                        on('m.ID', 'url.post_id')
                            ->and(field('url.meta_key')->eq('_menu_item_url'))
                    )
                    ->leftJoin(
                        alias($wpdb->postmeta, 'xfn'),
                        on('m.ID', 'xfn.post_id')
                            ->and(field('xfn.meta_key')->eq('_menu_item_xfn'))
                    )
                    ->leftJoin(
                        alias($wpdb->postmeta, 'hide_link'),
                        on('m.ID', 'hide_link.post_id')
                            ->and(field('hide_link.meta_key')->eq('menu-item-mm-hide-link'))
                    )
                    ->leftJoin(
                        alias($wpdb->postmeta, 'image_link'),
                        on('m.ID', 'image_link.post_id')
                            ->and(field('image_link.meta_key')->eq('menu-item-mm-image-link'))
                    )
                    ->where(
                        field('tr.term_taxonomy_id')
                            ->eq($menuObject->term_taxonomy_id)
                            ->and(field('m.post_type')->eq('nav_menu_item'))
                            ->and(field('m.post_status')->eq('publish'))
                    )
                    ->groupBy('m.ID', 'm.menu_order')
                    ->orderBy('m.menu_order', 'asc')
                    ->compile()
                ;

                $navStatement = $pdo->prepare($sql->sql());

                $navStatement->execute($sql->params());

                $menuItems = $navStatement->fetchAll(PDO::FETCH_OBJ);

                $menuItems = apply_filters('app_nav_menu_cache_items', $menuItems, $menuObject);

                $menuItems = array_map([__CLASS__, 'setupNavMenuItem'], $menuItems);

                wp_cache_set($cacheKey, $menuItems, 'menu_items');
            } catch (Exception $exception) {
                $item = new stdClass();
                $item->_invalid = true;

                $menuItems = [];
                $menuItems[] = $item;

                app_error_log($exception, __METHOD__);
            }
        }

        return $menuItems;
    }

    /**
     * @param stdClass[] $menuItems
     *
     * @SuppressWarnings (PHPMD.CamelCaseVariableName)
     */
    private function setMenuItemClassesByContext(array &$menuItems): void {
        global $wp_query, $wp_rewrite;

        $queriedObject = $wp_query->get_queried_object();
        $queriedObjectId = (int) $wp_query->queried_object_id;

        $activeObject = '';
        $ancestorItemIds = [];
        $activeParentItemIds = [];
        $parentObjectIds = [];
        $taxonomyAncestors = [];
        $objectParents = [];
        $homePageId = (int) get_option('page_for_posts');

        if ($wp_query->is_singular
            && ! empty($queriedObject->post_type)
            && ! is_post_type_hierarchical($queriedObject->post_type)) {
            /** @var WP_Taxonomy[] $taxonomies */
            $taxonomies = get_object_taxonomies($queriedObject->post_type, 'objects');

            foreach ($taxonomies as $taxonomy => $taxonomyObject) {
                if ($taxonomyObject->hierarchical && $taxonomyObject->public) {
                    $termHierarchy = _get_term_hierarchy($taxonomy);
                    $terms = wp_get_object_terms($queriedObjectId, $taxonomy, ['fields' => 'ids']);

                    if (is_array($terms)) {
                        $objectParents = array_merge($objectParents, $terms);
                        $termToAncestor = [];

                        foreach ($termHierarchy as $anc => $descs) {
                            foreach ((array) $descs as $desc) {
                                $termToAncestor[$desc] = $anc;
                            }
                        }

                        foreach ($terms as $desc) {
                            do {
                                $taxonomyAncestors[$taxonomy][] = $desc;

                                if (isset($termToAncestor[$desc])) {
                                    $_desc = $termToAncestor[$desc];
                                    unset($termToAncestor[$desc]);
                                    $desc = $_desc;
                                } else {
                                    $desc = 0;
                                }
                            } while ( ! empty($desc));
                        }
                    }
                }
            }
        } elseif ( ! empty($queriedObject->taxonomy) && is_taxonomy_hierarchical($queriedObject->taxonomy)) {
            $termHierarchy = _get_term_hierarchy($queriedObject->taxonomy);
            $termToAncestor = [];

            foreach ($termHierarchy as $anc => $descs) {
                foreach ((array) $descs as $desc) {
                    $termToAncestor[$desc] = $anc;
                }
            }
            $desc = $queriedObject->term_id;

            do {
                $taxonomyAncestors[$queriedObject->taxonomy][] = $desc;

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

            $classes = (array) $menuItem->classes;
            $classes[] = 'menu-item';
            $classes[] = "menu-item-type-$menuItem->type";
            $classes[] = "menu-item-object-$menuItem->object";

            if ('post_type' === $menuItem->type) {
                if ($frontPageId === (int) $menuItem->object_id) {
                    $classes[] = 'menu-item-home';
                }

                if ($privacyPolicyPageId === (int) $menuItem->object_id) {
                    $classes[] = 'menu-item-privacy-policy';
                }
            }

            if ($wp_query->is_singular && 'taxonomy' === $menuItem->type && in_array((int) $menuItem->object_id, $objectParents, true)) {
                $parentObjectIds[] = (int) $menuItem->object_id;
                $activeParentItemIds[] = (int) $menuItem->db_id;
                $activeObject = $queriedObject->post_type;
            } elseif ($this->isCurrentMenuItemt($menuItem, $queriedObjectId, $queriedObject, $homePageId)) {
                $classes[] = 'current-menu-item';
                $menuItems[$key]->current = true;

                if ( ! in_array($menuItem->db_id, $ancestorItemIds, true)) {
                    $ancestorItemIds[] = $menuItem->db_id;
                }

                if ('post_type' === $menuItem->type && 'page' === $menuItem->object) {
                    $classes[] = 'page_item';
                    $classes[] = "page-item-$menuItem->object_id";
                    $classes[] = 'current_page_item';
                }

                $activeParentItemIds[] = (int) $menuItem->menu_item_parent;
                $parentObjectIds[] = (int) $menuItem->post_parent;
                $activeObject = $menuItem->object;
            } elseif ('post_type_archive' === $menuItem->type && is_post_type_archive([$menuItem->object])) {
                $classes[] = 'current-menu-item';
                $menuItems[$key]->current = true;

                if ( ! in_array((int) $menuItem->db_id, $ancestorItemIds, true)) {
                    $ancestorItemIds[] = (int) $menuItem->db_id;
                }
                $activeParentItemIds[] = (int) $menuItem->menu_item_parent;
            } elseif ('custom' === $menuItem->object && filter_input(INPUT_SERVER, 'HTTP_HOST')) {
                $rootRelativeCurrent = app_get_current_relative_url();

                $currentUrl = app_get_current_url();
                $rawItemUrl = strpos($menuItem->url, '#') ? substr(
                    $menuItem->url,
                    0,
                    strpos($menuItem->url, '#')
                ) : $menuItem->url;

                $itemUrl = set_url_scheme(untrailingslashit($rawItemUrl));
                $indexlessCurrent = untrailingslashit(
                    preg_replace('/' . preg_quote($wp_rewrite->index, '/') . '$/', '', $currentUrl)
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
                    $activeObject = $menuItem->object;
                } elseif ($itemUrl === $frontPageUrl && is_front_page()) {
                    $classes[] = 'current-menu-item';
                }

                if (untrailingslashit($itemUrl) === $frontPageUrl) {
                    $classes[] = 'menu-item-home';
                }
            }

            // Back-compat with wp_page_menu(): add "current_page_parent" to static home page link for any non-page query.
            if ( ! empty($homePageId) && 'post_type' === $menuItem->type
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
            $classes = (array) $parentItem->classes;
            $menuItems[$key]->current_item_ancestor = false;
            $menuItems[$key]->current_item_parent = false;

            if ($this->isCurrentMenuItemtAncestor($parentItem, $queriedObject, $taxonomyAncestors)) {
                $classes[] = sprintf(
                    'current-%s-ancestor',
                    $queriedObject->taxonomy ?: $queriedObject->post_type
                );
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
                $classes[] = 'current-' . $activeObject . '-parent';
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
     * @param WP_Post|WP_Post_Type|WP_Term|WP_User|null $queriedObject
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.CamelCaseVariableName)
     */
    private function isCurrentMenuItemt(stdClass $menuItem, int $queriedObjectId, $queriedObject, ?int $homePageId = null): bool {
        global $wp_query;

        if ((int) $menuItem->object_id === $queriedObjectId) {
            return true;
        }

        if ('post_type' === $menuItem->type) {
            if ( ! empty($homePageId) && $wp_query->is_home && $homePageId === (int) $menuItem->object_id) {
                return true;
            }

            if ($wp_query->is_singular) {
                return true;
            }
        }

        $isCategory = $wp_query->is_category || $wp_query->is_tag || $wp_query->is_tax;

        return 'taxonomy' === $menuItem->type && $isCategory && $queriedObject->taxonomy === $menuItem->object;
    }

    /**
     * @param WP_Post|WP_Post_Type|WP_Term|WP_User|null $queriedObject
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function isCurrentMenuItemtAncestor(stdClass $parentItem, $queriedObject, array $taxonomyAncestors): bool {
        if (empty($parentItem->type)) {
            return false;
        }

        if (
            'post_type' === $parentItem->type
            && ! empty($queriedObject->post_type)
            && is_post_type_hierarchical($queriedObject->post_type)
            && in_array((int) $parentItem->object_id, $queriedObject->ancestors, true)
            && $parentItem->object !== $queriedObject->ID
        ) {
            return true;
        }

        return 'taxonomy' === $parentItem->type
               && isset($taxonomyAncestors[$parentItem->object])
               && in_array((int) $parentItem->object_id, $taxonomyAncestors[$parentItem->object], true)
               && ( ! isset($queriedObject->term_id) || $parentItem->object_id !== $queriedObject->term_id);
    }

    public function setMenuFallbackParams(array $args): array {
        $args['fallback_cb'] = '__return_empty_string';

        return $args;
    }
}
