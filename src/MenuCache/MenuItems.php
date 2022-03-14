<?php

namespace JazzMan\Performance\MenuCache;

use Exception;
use JazzMan\Performance\Utils\Cache;
use JazzMan\PerformanceStub\NavMenuItemStub;
use function Latitude\QueryBuilder\alias;
use function Latitude\QueryBuilder\field;
use function Latitude\QueryBuilder\on;
use Latitude\QueryBuilder\Query;
use Latitude\QueryBuilder\QueryFactory;
use PDO;
use stdClass;
use WP_Error;
use WP_Post;
use WP_Post_Type;
use WP_Taxonomy;
use WP_Term;

class MenuItems {
    /**
     * @return NavMenuItemStub[]
     */
    public static function getItems(WP_Term $wpTerm): array {
        $cacheKey = Cache::getMenuItemCacheKey($wpTerm);

        /** @var false|NavMenuItemStub[] $menuItems */
        $menuItems = wp_cache_get($cacheKey, 'menu_items');

        if (false === $menuItems) {
            try {
                $pdo = app_db_pdo();

                $query = self::generateSql($wpTerm);

                $pdoStatement = $pdo->prepare($query->sql());

                $pdoStatement->execute($query->params());

                $menuItems = $pdoStatement->fetchAll(PDO::FETCH_OBJ);

                /** @var NavMenuItemStub[] $menuItems */
                $menuItems = (array) apply_filters('app_nav_menu_cache_items', $menuItems, $wpTerm);

                foreach ($menuItems as $key => $item) {
                    $menuItems[$key] = self::setupNavMenuItem($item);
                }

                wp_cache_set($cacheKey, $menuItems, 'menu_items');
            } catch (Exception $exception) {
                /** @var NavMenuItemStub $item */
                $item = new stdClass();
                $item->_invalid = true;

                $menuItems = [];
                $menuItems[] = $item;

                app_error_log($exception, __METHOD__);
            }
        }

        return $menuItems;
    }

    private static function generateSql(WP_Term $wpTerm): Query {
        global $wpdb;

        $factory = new QueryFactory();

        $query = $factory
            ->select(
                'menu.ID',
                'menu.post_title',
                'menu.post_name',
                'menu.post_parent',
                'menu.menu_order',
                'menu.post_type',
                'menu.post_content',
                'menu.post_excerpt'
            )
            ->from(alias($wpdb->posts, 'menu'))
            ->leftJoin(
                alias($wpdb->term_relationships, 'tr'),
                on('menu.ID', 'tr.object_id')
            )
            ->where(
                field('tr.term_taxonomy_id')
                    ->eq($wpTerm->term_taxonomy_id)
                    ->and(field('menu.post_type')->eq('nav_menu_item'))
                    ->and(field('menu.post_status')->eq('publish'))
            )
            ->groupBy('menu.ID', 'menu.menu_order')
            ->orderBy('menu.menu_order', 'asc')
        ;

        $menuMetaFields = [
            'classes' => '_menu_item_classes',
            'menu_item_parent' => '_menu_item_menu_item_parent',
            'object' => '_menu_item_object',
            'object_id' => '_menu_item_object_id',
            'target' => '_menu_item_target',
            'type' => '_menu_item_type',
            'url' => '_menu_item_url',
            'xfn' => '_menu_item_xfn',
            'hide_link' => 'menu-item-mm-hide-link',
            'image_link' => 'menu-item-mm-image-link',
        ];

        foreach ($menuMetaFields as $field => $metaKey) {
            $query->addColumns(alias("{$field}.meta_value", $field));
            $query->leftJoin(
                alias($wpdb->postmeta, $field),
                on('menu.ID', "{$field}.post_id")
                    ->and(
                        field("{$field}.meta_key")->eq($metaKey)
                    )
            );
        }

        $query->addColumns('term.taxonomy', 'term.term_id');
        $query->leftJoin(
            alias($wpdb->postmeta, 'term'),
            on('term.term_id', 'object_id.meta_value')
                ->and(
                    field('type.meta_value')->eq('taxonomy')
                )
        );

        return $query->compile();
    }

    /**
     * @param NavMenuItemStub $menuItem
     *
     * @return NavMenuItemStub
     */
    private static function setupNavMenuItem($menuItem) {
        if (property_exists($menuItem, 'post_type') && null !== $menuItem->post_type) {
            if ('nav_menu_item' === $menuItem->post_type) {
                $menuItem->db_id = $menuItem->ID;
                $menuItem->menu_item_parent = (int) $menuItem->menu_item_parent;
                $menuItem->object_id = (int) $menuItem->object_id;

                $menuItem = self::setupNavMenuItemByType($menuItem);

                $attrTitleProp = !empty($menuItem->attr_title) ? $menuItem->attr_title : $menuItem->post_excerpt;

                $menuItem->attr_title = (string) apply_filters('nav_menu_attr_title', $attrTitleProp);

                unset($attrTitleProp);

                if (!isset($menuItem->description)) {
                    $menuItem->description = (string) apply_filters('nav_menu_description', wp_trim_words($menuItem->post_content, 200));
                }

                /** @var string[] $classes */
                $classes = [];

                if (!empty($menuItem->classes) && \is_string($menuItem->classes)) {
                    /** @var string[] $classes */
                    $classes = maybe_unserialize($menuItem->classes);
                }

                $menuItem->classes = $classes;

                return $menuItem;
            }

            $menuItem->db_id = 0;
            $menuItem->menu_item_parent = 0;
            $menuItem->object_id = $menuItem->ID;
            $menuItem->type = 'post_type';
            $menuItem->object = '';
            $menuItem->type_label = '';

            $object = get_post_type_object($menuItem->post_type);

            if ($object instanceof WP_Post_Type) {
                $menuItem->object = $object->name;
                $menuItem->type_label = (string) $object->labels->singular_name;
            }

            $menuItem->post_title = self::getBaseMenuItemTitle($menuItem);

            $menuItem->title = $menuItem->post_title;

            $permalink = get_permalink($menuItem->ID);

            $menuItem->url = empty($permalink) ? '' : $permalink;
            $menuItem->target = '';
            $menuItem->attr_title = (string) apply_filters('nav_menu_attr_title', '');
            $menuItem->description = (string) apply_filters('nav_menu_description', '');
            $menuItem->classes = [];
            $menuItem->xfn = '';

            return $menuItem;
        }

        if (!empty($menuItem->taxonomy)) {
            $menuItem->ID = $menuItem->term_id;
            $menuItem->db_id = 0;
            $menuItem->menu_item_parent = 0;
            $menuItem->object_id = $menuItem->term_id;
            $menuItem->post_parent = (int) $menuItem->parent;
            $menuItem->type = 'taxonomy';

            $menuItem->object = '';
            $menuItem->type_label = '';

            $object = get_taxonomy($menuItem->taxonomy);

            if ($object instanceof WP_Taxonomy) {
                $menuItem->object = $object->name;
                $menuItem->type_label = (string) $object->labels->singular_name;
            }

            unset($object);

            $menuItem->title = (string) $menuItem->name;

            $termLink = app_get_term_link($menuItem->term_id, $menuItem->taxonomy);

            $menuItem->url = empty($termLink) ? '' : $termLink;

            unset($termLink);

            $menuItem->target = '';
            $menuItem->attr_title = '';
            $menuItem->description = '';

            $termDescription = get_term_field('description', $menuItem->term_id, $menuItem->taxonomy);

            if (!($termDescription instanceof WP_Error)) {
                $menuItem->description = (string) $termDescription;
            }

            unset($termDescription);

            $menuItem->classes = [];
            $menuItem->xfn = '';
        }

        return $menuItem;
    }

    /**
     * @param NavMenuItemStub $menuItem
     *
     * @return NavMenuItemStub
     */
    private static function setupNavMenuItemByType($menuItem) {
        switch ($menuItem->type) {
            case 'post_type':
                $postTypeObject = get_post_type_object((string) $menuItem->object);

                $menuItem->_invalid = true;
                $menuItem->url = '';

                $originalTitle = self::getBaseMenuItemTitle($menuItem);

                $originalPost = get_post((int) $menuItem->object_id);

                $menuItem->type_label = self::getPostTypeLabel($menuItem);

                if ($originalPost instanceof WP_Post) {
                    $menuItem->url = (string) get_permalink($originalPost->ID);
                    $originalTitle = (string) apply_filters('the_title', $originalPost->post_title, $originalPost->ID);

                    $menuItem->_invalid = 'trash' === $originalPost->post_status;
                }

                if ($postTypeObject instanceof WP_Post_Type) {
                    $menuItem->_invalid = false;
                }

                $menuItem->title = $originalTitle;

                break;

            case 'post_type_archive':
                $postTypeObject = get_post_type_object((string) $menuItem->object);

                $menuItem->_invalid = true;

                $menuItem->title = (string) $menuItem->post_title;

                if ($postTypeObject instanceof WP_Post_Type) {
                    $menuItem->title = (string) $postTypeObject->labels->archives;
                    $menuItem->_invalid = false;
                }

                $menuItem->type_label = __('Post Type Archive');

                $archiveLink = get_post_type_archive_link((string) $menuItem->object);

                $menuItem->url = !empty($archiveLink) ? $archiveLink : '';

                break;

            case 'taxonomy':
                $menuItem->_invalid = true;
                $menuItem->url = '';

                $originalTitle = self::getBaseMenuItemTitle($menuItem);

                $menuItem->title = (string) $menuItem->post_title;

                $originalTerm = get_term((int) $menuItem->object_id, (string) $menuItem->object);

                $menuItem->type_label = self::getTaxonomyLabel($menuItem);

                if ($originalTerm instanceof WP_Term) {
                    $menuItem->url = (string) app_get_term_link((int) $menuItem->object_id, (string) $menuItem->object);

                    $originalTitle = $originalTerm->name;

                    $menuItem->_invalid = false;
                }

                if (taxonomy_exists((string) $menuItem->object)) {
                    $menuItem->_invalid = false;
                }

                $menuItem->title = $originalTitle;

                break;

            default:
                $menuItem->type_label = __('Custom Link');
                $menuItem->title = (string) $menuItem->post_title;

                break;
        }

        return $menuItem;
    }

    /**
     * @param NavMenuItemStub $menuItem
     */
    private static function getTaxonomyLabel($menuItem): string {
        $taxonomyObject = get_taxonomy((string) $menuItem->object);

        if ($taxonomyObject instanceof WP_Taxonomy) {
            $taxonomyLabels = get_taxonomy_labels($taxonomyObject);

            return empty($taxonomyLabels->singular_name) ? (string) $menuItem->object : (string) $taxonomyLabels->singular_name;
        }

        return (string) $menuItem->object;
    }

    /**
     * @param NavMenuItemStub $menuItem
     */
    private static function getPostTypeLabel($menuItem): string {
        $label = (string) $menuItem->object;

        $postTypeObject = get_post_type_object($label);

        if ($postTypeObject instanceof WP_Post_Type) {
            $originalPost = get_post((int) $menuItem->object_id);

            if ($originalPost instanceof WP_Post) {
                return wp_strip_all_tags(implode(', ', get_post_states($originalPost)));
            }

            return (string) $postTypeObject->labels->singular_name;
        }

        return $label;
    }

    /**
     * @param NavMenuItemStub $menuItem
     */
    private static function getBaseMenuItemTitle($menuItem): string {
        return '' === $menuItem->post_title ? sprintf(__('#%d (no title)'), $menuItem->ID) : (string) $menuItem->post_title;
    }
}
