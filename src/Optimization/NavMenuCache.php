<?php

namespace JazzMan\Performance\Optimization;

use JazzMan\AutoloadInterface\AutoloadInterface;
use JazzMan\Performance\Utils\Cache;
use Latitude\QueryBuilder\QueryFactory;
use function Latitude\QueryBuilder\alias;
use function Latitude\QueryBuilder\field;
use function Latitude\QueryBuilder\on;

class NavMenuCache implements AutoloadInterface
{
    public function load()
    {
        add_filter('wp_nav_menu_args', [$this, 'setMenuFallbackParams']);
        add_filter('pre_wp_nav_menu', [$this, 'buildWpNavMenu'], 10, 2);
    }

    /**
     * @param  \stdClass  $menuItem
     *
     * @return \stdClass
     */
    public static function setupNavMenuItem(\stdClass $menuItem): \stdClass
    {
        if (isset($menuItem->post_type)) {
            if ('nav_menu_item' === $menuItem->post_type) {
                $menuItem->db_id = (int) $menuItem->ID;
                $menuItem->menu_item_parent = (int) $menuItem->menu_item_parent;
                $menuItem->object_id = (int) $menuItem->object_id;

                switch ($menuItem->type) {
                    case 'post_type':
                        $object = get_post_type_object($menuItem->object);
                        if ($object) {
                            $menuItem->type_label = $object->labels->singular_name;
                            if (\function_exists('get_post_states')) {
                                $menu_post = get_post($menuItem->object_id);
                                $post_states = get_post_states($menu_post);
                                if ($post_states) {
                                    $menuItem->type_label = wp_strip_all_tags(\implode(', ', $post_states));
                                }
                            }
                        } else {
                            $menuItem->type_label = $menuItem->object;
                            $menuItem->_invalid = true;
                        }

                        if ('trash' === get_post_status($menuItem->object_id)) {
                            $menuItem->_invalid = true;
                        }

                        $original_object = get_post($menuItem->object_id);

                        if ($original_object) {
                            $menuItem->url = get_permalink($original_object->ID);
                            $original_title = apply_filters(
                                'the_title',
                                $original_object->post_title,
                                $original_object->ID
                            );
                        } else {
                            $menuItem->url = '';
                            $original_title = '';
                            $menuItem->_invalid = true;
                        }

                        if ('' === $original_title) {
                            $original_title = \sprintf(__('#%d (no title)'), $menuItem->object_id);
                        }

                        $menuItem->title = ('' === $menuItem->post_title) ? $original_title : $menuItem->post_title;

                        break;

                    case 'post_type_archive':
                        $object = get_post_type_object($menuItem->object);
                        if ($object) {
                            $menuItem->title = ('' === $menuItem->post_title) ? $object->labels->archives : $menuItem->post_title;
                        } else {
                            $menuItem->_invalid = true;
                        }

                        $menuItem->type_label = __('Post Type Archive');
                        $menuItem->url = get_post_type_archive_link($menuItem->object);

                        break;

                    case 'taxonomy':
                        $object = get_taxonomy($menuItem->object);
                        if ($object) {
                            $menuItem->type_label = $object->labels->singular_name;
                        } else {
                            $menuItem->type_label = $menuItem->object;
                            $menuItem->_invalid = true;
                        }

                        $original_object = get_term((int) $menuItem->object_id, $menuItem->object);

                        if ($original_object && !is_wp_error($original_object)) {
                            $menuItem->url = app_get_term_link((int) $menuItem->object_id, $menuItem->object);
                            $original_title = $original_object->name;
                        } else {
                            $menuItem->url = '';
                            $original_title = '';
                            $menuItem->_invalid = true;
                        }

                        if ('' === $original_title) {
                            $original_title = \sprintf(__('#%d (no title)'), $menuItem->object_id);
                        }

                        $menuItem->title = ('' === $menuItem->post_title) ? $original_title : $menuItem->post_title;

                        break;

                    default:
                        $menuItem->type_label = __('Custom Link');
                        $menuItem->title = $menuItem->post_title;

                        break;
                }

                $menuItem->attr_title = !isset($menuItem->attr_title) ? apply_filters(
                    'nav_menu_attr_title',
                    $menuItem->post_excerpt
                ) : $menuItem->attr_title;

                if (!isset($menuItem->description)) {
                    $menuItem->description = apply_filters(
                        'nav_menu_description',
                        wp_trim_words($menuItem->post_content, 200)
                    );
                }

                $menuItem->classes = (array) maybe_unserialize($menuItem->classes);
            } else {
                $menuItem->db_id = 0;
                $menuItem->menu_item_parent = 0;
                $menuItem->object_id = (int) $menuItem->ID;
                $menuItem->type = 'post_type';

                $object = get_post_type_object($menuItem->post_type);
                $menuItem->object = $object->name;
                $menuItem->type_label = $object->labels->singular_name;

                if ('' === $menuItem->post_title) {
                    $menuItem->post_title = \sprintf(__('#%d (no title)'), $menuItem->ID);
                }

                $menuItem->title = $menuItem->post_title;
                $menuItem->url = get_permalink($menuItem->ID);
                $menuItem->target = '';
                $menuItem->attr_title = apply_filters('nav_menu_attr_title', '');
                $menuItem->description = apply_filters('nav_menu_description', '');
                $menuItem->classes = [];
                $menuItem->xfn = '';
            }
        } elseif (isset($menuItem->taxonomy)) {
            $menuItem->ID = $menuItem->term_id;
            $menuItem->db_id = 0;
            $menuItem->menu_item_parent = 0;
            $menuItem->object_id = (int) $menuItem->term_id;
            $menuItem->post_parent = (int) $menuItem->parent;
            $menuItem->type = 'taxonomy';

            $object = get_taxonomy($menuItem->taxonomy);
            $menuItem->object = $object->name;
            $menuItem->type_label = $object->labels->singular_name;

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

    /**
     * @param  null|string  $output
     * @param  \stdClass|null  $args
     *
     * @return null|mixed
     */
    public function buildWpNavMenu($output = null, \stdClass $args = null)
    {
        $menu = wp_get_nav_menu_object($args->menu);

        if (empty($menu)) {
            return $output;
        }

        static $menuIdSlugs = [];

        $menuItems = self::getMenuItems($menu);

        if (!is_admin()) {
            $menuItems = \array_filter($menuItems, '_is_valid_nav_menu_item');
        }

        if ((empty($menuItems) && !$args->theme_location)
            && isset($args->fallback_cb) && $args->fallback_cb && \is_callable($args->fallback_cb)) {
            return \call_user_func($args->fallback_cb, (array) $args);
        }

        $navMenu = '';
        $items = '';

        $showContainer = false;
        if ($args->container) {
            $allowedTags = apply_filters('wp_nav_menu_container_allowedtags', ['div', 'nav']);

            if (\is_string($args->container) && \in_array($args->container, $allowedTags, true)) {
                $showContainer = true;

                $containerAttributes = [
                    'class' => $args->container_class ?: sprintf('menu-%s-container', $menu->slug),
                ];
                if ($args->container_id) {
                    $containerAttributes['id'] = $args->container_id;
                }

                if ('nav' === $args->container && !empty($args->container_aria_label)) {
                    $containerAttributes['aria-label'] = $args->container_aria_label;
                }

                $navMenu .= sprintf('<%s %s>', $args->container, app_add_attr_to_el($containerAttributes));
            }
        }

        $this->setMenuItemClassesByContext($menuItems);

        $sortedMenuItems = [];
        $menuItemsWithChildren = [];
        foreach ((array) $menuItems as $menuItem) {
            $sortedMenuItems[$menuItem->menu_order] = $menuItem;
            if ($menuItem->menu_item_parent) {
                $menuItemsWithChildren[$menuItem->menu_item_parent] = true;
            }
        }

        // Add the menu-item-has-children class where applicable.
        if ($menuItemsWithChildren) {
            foreach ($sortedMenuItems as &$menuItem) {
                if (isset($menuItemsWithChildren[$menuItem->ID])) {
                    $menuItem->classes[] = 'menu-item-has-children';
                }
            }
        }

        unset($menuItems, $menuItem);

        $sortedMenuItems = apply_filters('wp_nav_menu_objects', $sortedMenuItems, $args);

        $items .= walk_nav_menu_tree($sortedMenuItems, $args->depth, $args);
        unset($sortedMenuItems);

        // Attributes.
        if (!empty($args->menu_id)) {
            $wrapId = $args->menu_id;
        } else {
            $wrapId = "menu-{$menu->slug}";

            while (\in_array($wrapId, $menuIdSlugs, true)) {
                if (\preg_match('#-(\d+)$#', $wrapId, $matches)) {
                    $wrapId = \preg_replace('#-(\d+)$#', '-'.++$matches[1], $wrapId);
                } else {
                    $wrapId = $wrapId.'-1';
                }
            }
        }
        $menuIdSlugs[] = $wrapId;

        $wrapClass = $args->menu_class ?: '';

        $items = apply_filters('wp_nav_menu_items', $items, $args);

        $items = apply_filters("wp_nav_menu_{$menu->slug}_items", $items, $args);

        if (empty($items)) {
            return false;
        }

        $navMenu .= \sprintf($args->items_wrap, esc_attr($wrapId), esc_attr($wrapClass), $items);
        unset($items);

        if ($showContainer) {
            $navMenu .= '</'.$args->container.'>';
        }

        return apply_filters('wp_nav_menu', $navMenu, $args);
    }

    /**
     * @param  \WP_Term  $menuObject
     * @return array|bool|mixed
     */
    public static function getMenuItems(\WP_Term $menuObject)
    {
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
                    ->compile();

                $navStatement = $pdo->prepare($sql->sql());

                $navStatement->execute($sql->params());

                $menuItems = $navStatement->fetchAll(\PDO::FETCH_OBJ);

                $menuItems = apply_filters('app_nav_menu_cache_items', $menuItems, $menuObject);

                $menuItems = \array_map([__CLASS__, 'setupNavMenuItem'], $menuItems);

                wp_cache_set($cacheKey, $menuItems, 'menu_items');

            }catch (\Exception $exception){
                $item = new \stdClass();
                $item->_invalid = true;

                $menuItems = [];
                $menuItems[] = $item;

                app_error_log($exception,__METHOD__);
            }
        }

        return $menuItems;
    }

    /**
     * @param \stdClass[] $menuItems {
     * @type bool $current
     * @type bool $current_item_ancestor
     * @type bool $current_item_parent
     * @type string|array $classes
     * @type string $type
     * @type string $object
     * @type int $object_id
     * @type int $db_id
     * @type int $menu_item_parent
     * @type int $post_parent
     *
     * }
     */
    private function setMenuItemClassesByContext(array &$menuItems)
    {
        global $wp_query, $wp_rewrite;

        $queriedObject = $wp_query->get_queried_object();
        $queriedObjectId = (int) $wp_query->queried_object_id;

        $activeObject = '';
        $activeAncestorItemIds = [];
        $activeParentItemIds = [];
        $activeParentObjectIds = [];
        $possibleTaxonomyAncestors = [];
        $possibleObjectParents = [];
        $homePageId = (int) get_option('page_for_posts');

        if ($wp_query->is_singular
            && !empty($queriedObject->post_type)
            && !is_post_type_hierarchical($queriedObject->post_type)) {
            /** @var \WP_Taxonomy[] $taxonomies */
            $taxonomies = get_object_taxonomies($queriedObject->post_type, 'objects');

            foreach ($taxonomies as $taxonomy => $taxonomyObject) {
                if ($taxonomyObject->hierarchical && $taxonomyObject->public) {
                    $termHierarchy = _get_term_hierarchy($taxonomy);
                    $terms = wp_get_object_terms($queriedObjectId, $taxonomy, ['fields' => 'ids']);
                    if (\is_array($terms)) {
                        $possibleObjectParents = \array_merge($possibleObjectParents, $terms);
                        $termToAncestor = [];
                        foreach ((array) $termHierarchy as $anc => $descs) {
                            foreach ((array) $descs as $desc) {
                                $termToAncestor[$desc] = $anc;
                            }
                        }

                        foreach ($terms as $desc) {
                            do {
                                $possibleTaxonomyAncestors[$taxonomy][] = $desc;
                                if (isset($termToAncestor[$desc])) {
                                    $_desc = $termToAncestor[$desc];
                                    unset($termToAncestor[$desc]);
                                    $desc = $_desc;
                                } else {
                                    $desc = 0;
                                }
                            } while (!empty($desc));
                        }
                    }
                }
            }
        } elseif (!empty($queriedObject->taxonomy) && is_taxonomy_hierarchical($queriedObject->taxonomy)) {
            $termHierarchy = _get_term_hierarchy($queriedObject->taxonomy);
            $termToAncestor = [];
            foreach ((array) $termHierarchy as $anc => $descs) {
                foreach ((array) $descs as $desc) {
                    $termToAncestor[$desc] = $anc;
                }
            }
            $desc = $queriedObject->term_id;
            do {
                $possibleTaxonomyAncestors[$queriedObject->taxonomy][] = $desc;
                if (isset($termToAncestor[$desc])) {
                    $_desc = $termToAncestor[$desc];
                    unset($termToAncestor[$desc]);
                    $desc = $_desc;
                } else {
                    $desc = 0;
                }
            } while (!empty($desc));
        }

        $possibleObjectParents = \array_filter($possibleObjectParents);

        $frontPageUrl = home_url();
        $frontPageId = (int) get_option('page_on_front');
        $privacyPolicyPageId = (int) get_option('wp_page_for_privacy_policy');

        foreach ((array) $menuItems as $key => $menuItem) {
            $menuItems[$key]->current = false;

            $classes = (array) $menuItem->classes;
            $classes[] = 'menu-item';
            $classes[] = "menu-item-type-{$menuItem->type}";
            $classes[] = "menu-item-object-{$menuItem->object}";

            if ('post_type' === $menuItem->type) {
                if ($frontPageId === (int) $menuItem->object_id) {
                    $classes[] = 'menu-item-home';
                }

                if ($privacyPolicyPageId === (int) $menuItem->object_id) {
                    $classes[] = 'menu-item-privacy-policy';
                }
            }

            if ($wp_query->is_singular && 'taxonomy' === $menuItem->type
                && \in_array((int) $menuItem->object_id, $possibleObjectParents, true)
            ) {
                $activeParentObjectIds[] = (int) $menuItem->object_id;
                $activeParentItemIds[] = (int) $menuItem->db_id;
                $activeObject = $queriedObject->post_type;
            } elseif (
                (int) $menuItem->object_id === $queriedObjectId
                && (
                    (
                        !empty($homePageId)
                        && 'post_type' === $menuItem->type
                        && $wp_query->is_home
                        && $homePageId === (int) $menuItem->object_id
                    )
                    || ('post_type' === $menuItem->type && $wp_query->is_singular)
                    || (
                        'taxonomy' === $menuItem->type
                        && ($wp_query->is_category || $wp_query->is_tag || $wp_query->is_tax)
                        && $queriedObject->taxonomy === $menuItem->object
                    )
                )
            ) {
                $classes[] = 'current-menu-item';
                $menuItems[$key]->current = true;

                if (!\in_array($menuItem->db_id, $activeAncestorItemIds, true)) {
                    $activeAncestorItemIds[] = $menuItem->db_id;
                }

                if ('post_type' === $menuItem->type && 'page' === $menuItem->object) {
                    $classes[] = 'page_item';
                    $classes[] = "page-item-{$menuItem->object_id}";
                    $classes[] = 'current_page_item';
                }

                $activeParentItemIds[] = (int) $menuItem->menu_item_parent;
                $activeParentObjectIds[] = (int) $menuItem->post_parent;
                $activeObject = $menuItem->object;
            } elseif ('post_type_archive' === $menuItem->type && is_post_type_archive([$menuItem->object])) {
                $classes[] = 'current-menu-item';
                $menuItems[$key]->current = true;
                if (!\in_array((int) $menuItem->db_id, $activeAncestorItemIds, true)) {
                    $activeAncestorItemIds[] = (int) $menuItem->db_id;
                }
                $activeParentItemIds[] = (int) $menuItem->menu_item_parent;
            } elseif ('custom' === $menuItem->object && filter_input(INPUT_SERVER,'HTTP_HOST')) {
                $rootRelativeCurrent = app_get_current_relative_url();

                $currentUrl = app_get_current_url();
                $rawItemUrl = \strpos($menuItem->url, '#') ? \substr(
                    $menuItem->url,
                    0,
                    \strpos($menuItem->url, '#')
                ) : $menuItem->url;

                $itemUrl = set_url_scheme(untrailingslashit($rawItemUrl));
                $indexlessCurrent = untrailingslashit(
                    \preg_replace('/'.\preg_quote($wp_rewrite->index, '/').'$/', '', $currentUrl)
                );

                $matches = [
                    $currentUrl,
                    \urldecode($currentUrl),
                    $indexlessCurrent,
                    \urldecode($indexlessCurrent),
                    $rootRelativeCurrent,
                    \urldecode($rootRelativeCurrent),
                ];

                if ($rawItemUrl && \in_array($itemUrl, $matches, true)) {
                    $classes[] = 'current-menu-item';
                    $menuItems[$key]->current = true;

                    if (!\in_array((int) $menuItem->db_id, $activeAncestorItemIds, true)) {
                        $activeAncestorItemIds[] = (int) $menuItem->db_id;
                    }

                    if (\in_array(
                        $frontPageUrl,
                        [untrailingslashit($currentUrl), untrailingslashit($indexlessCurrent)],
                        true
                    )) {
                        // Back compat for home link to match wp_page_menu().
                        $classes[] = 'current_page_item';
                    }
                    $activeParentItemIds[] = (int) $menuItem->menu_item_parent;
                    $activeParentObjectIds[] = (int) $menuItem->post_parent;
                    $activeObject = $menuItem->object;
                } elseif ($itemUrl === $frontPageUrl && is_front_page()) {
                    $classes[] = 'current-menu-item';
                }

                if (untrailingslashit($itemUrl) === $frontPageUrl) {
                    $classes[] = 'menu-item-home';
                }
            }

            // Back-compat with wp_page_menu(): add "current_page_parent" to static home page link for any non-page query.
            if (!empty($homePageId) && 'post_type' === $menuItem->type
                 && empty($wp_query->is_page) && $homePageId === (int) $menuItem->object_id
            ) {
                $classes[] = 'current_page_parent';
            }

            $menuItems[$key]->classes = \array_unique($classes);
        }
        $activeAncestorItemIds = \array_filter(\array_unique($activeAncestorItemIds));
        $activeParentItemIds = \array_filter(\array_unique($activeParentItemIds));
        $activeParentObjectIds = \array_filter(\array_unique($activeParentObjectIds));

        // Set parent's class.
        foreach ((array) $menuItems as $key => $parentItem) {
            $classes = (array) $parentItem->classes;
            $menuItems[$key]->current_item_ancestor = false;
            $menuItems[$key]->current_item_parent = false;

            if (isset($parentItem->type)
                && (
                    ('post_type' === $parentItem->type
                     && !empty($queriedObject->post_type)
                     && is_post_type_hierarchical($queriedObject->post_type)
                     && \in_array((int) $parentItem->object_id, $queriedObject->ancestors, true)
                     && $parentItem->object !== $queriedObject->ID)
                    || ('taxonomy' === $parentItem->type
                        && isset($possibleTaxonomyAncestors[$parentItem->object])
                        && \in_array(
                            (int) $parentItem->object_id,
                            $possibleTaxonomyAncestors[$parentItem->object],
                            true
                        )
                        && (!isset($queriedObject->term_id) || $parentItem->object_id !== $queriedObject->term_id))
                )
            ) {

                $classes[] = sprintf(
                    'current-%s-ancestor',
                    $queriedObject->taxonomy?: $queriedObject->post_type
                );
            }

            if (\in_array((int) $parentItem->db_id, $activeAncestorItemIds, true)) {
                $classes[] = 'current-menu-ancestor';

                $menuItems[$key]->current_item_ancestor = true;
            }
            if (\in_array((int) $parentItem->db_id, $activeParentItemIds, true)) {
                $classes[] = 'current-menu-parent';

                $menuItems[$key]->current_item_parent = true;
            }
            if (\in_array((int) $parentItem->object_id, $activeParentObjectIds, true)) {
                $classes[] = 'current-'.$activeObject.'-parent';
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

            $menuItems[$key]->classes = \array_unique($classes);
        }
    }

    public function setMenuFallbackParams(array $args): array
    {
        $args['fallback_cb'] = '__return_empty_string';

        return $args;
    }
}
