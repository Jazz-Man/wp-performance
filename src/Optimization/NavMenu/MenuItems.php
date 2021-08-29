<?php

namespace JazzMan\Performance\Optimization\NavMenu;

use Exception;
use JazzMan\Performance\Optimization\NavMenu\Placeholder\MenuItem;
use JazzMan\Performance\Utils\Cache;
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
     * @param WP_Term $wpTerm
     *
     * @return MenuItem[]|stdClass[]
     */
    public static function getItems(WP_Term $wpTerm): array {
        $cacheKey = Cache::getMenuItemCacheKey( $wpTerm );

        /** @var MenuItem[]|stdClass[]|false $menuItems */
        $menuItems = wp_cache_get( $cacheKey, 'menu_items' );

        if ( false === $menuItems ) {
            try {
                $pdo = app_db_pdo();

                $query = self::generateSql( $wpTerm );

                $pdoStatement = $pdo->prepare( $query->sql() );

                $pdoStatement->execute( $query->params() );

                /** @var MenuItem[]|stdClass[] $menuItems */
                $menuItems = $pdoStatement->fetchAll( PDO::FETCH_OBJ );

                /** @var MenuItem[]|stdClass[] $menuItems */
                $menuItems = (array) apply_filters( 'app_nav_menu_cache_items', $menuItems, $wpTerm );

                foreach ( $menuItems as $key => $item ) {
                    $menuItems[ $key ] = self::setupNavMenuItem( $item );
                }

                wp_cache_set( $cacheKey, $menuItems, 'menu_items' );
            } catch ( Exception $exception ) {
                $item = new stdClass();
                $item->_invalid = true;

                $menuItems = [];
                $menuItems[] = $item;

                app_error_log( $exception, __METHOD__ );
            }
        }

        return $menuItems;
    }

    private static function generateSql(WP_Term $wpTerm): Query {
        global $wpdb;

        return ( new QueryFactory() )
            ->select(
                'm.ID',
                'm.post_title',
                'm.post_name',
                'm.post_parent',
                'm.menu_order',
                'm.post_type',
                'm.post_content',
                'm.post_excerpt',
                alias( 'classes.meta_value', 'classes' ),
                alias( 'menu_item_parent.meta_value', 'menu_item_parent' ),
                alias( 'object.meta_value', 'object' ),
                alias( 'object_id.meta_value', 'object_id' ),
                alias( 'target.meta_value', 'target' ),
                alias( 'type.meta_value', 'type' ),
                alias( 'url.meta_value', 'url' ),
                alias( 'xfn.meta_value', 'xfn' ),
                alias( 'hide_link.meta_value', 'hide_link' ),
                alias( 'image_link.meta_value', 'image_link' )
            )
            ->from( alias( $wpdb->posts, 'm' ) )
            ->leftJoin( alias( $wpdb->term_relationships, 'tr' ), on( 'm.ID', 'tr.object_id' ) )
            ->leftJoin(
                alias( $wpdb->postmeta, 'classes' ),
                on( 'm.ID', 'classes.post_id' )
                    ->and( field( 'classes.meta_key' )->eq( '_menu_item_classes' ) )
            )
            ->leftJoin(
                alias( $wpdb->postmeta, 'menu_item_parent' ),
                on( 'm.ID', 'menu_item_parent.post_id' )
                    ->and( field( 'menu_item_parent.meta_key' )->eq( '_menu_item_menu_item_parent' ) )
            )
            ->leftJoin(
                alias( $wpdb->postmeta, 'object' ),
                on( 'm.ID', 'object.post_id' )
                    ->and( field( 'object.meta_key' )->eq( '_menu_item_object' ) )
            )
            ->leftJoin(
                alias( $wpdb->postmeta, 'object_id' ),
                on( 'm.ID', 'object_id.post_id' )
                    ->and( field( 'object_id.meta_key' )->eq( '_menu_item_object_id' ) )
            )
            ->leftJoin(
                alias( $wpdb->postmeta, 'target' ),
                on( 'm.ID', 'target.post_id' )
                    ->and( field( 'target.meta_key' )->eq( '_menu_item_target' ) )
            )
            ->leftJoin(
                alias( $wpdb->postmeta, 'type' ),
                on( 'm.ID', 'type.post_id' )
                    ->and( field( 'type.meta_key' )->eq( '_menu_item_type' ) )
            )
            ->leftJoin(
                alias( $wpdb->postmeta, 'url' ),
                on( 'm.ID', 'url.post_id' )
                    ->and( field( 'url.meta_key' )->eq( '_menu_item_url' ) )
            )
            ->leftJoin(
                alias( $wpdb->postmeta, 'xfn' ),
                on( 'm.ID', 'xfn.post_id' )
                    ->and( field( 'xfn.meta_key' )->eq( '_menu_item_xfn' ) )
            )
            ->leftJoin(
                alias( $wpdb->postmeta, 'hide_link' ),
                on( 'm.ID', 'hide_link.post_id' )
                    ->and( field( 'hide_link.meta_key' )->eq( 'menu-item-mm-hide-link' ) )
            )
            ->leftJoin(
                alias( $wpdb->postmeta, 'image_link' ),
                on( 'm.ID', 'image_link.post_id' )
                    ->and( field( 'image_link.meta_key' )->eq( 'menu-item-mm-image-link' ) )
            )
            ->where(
                field( 'tr.term_taxonomy_id' )
                    ->eq( $wpTerm->term_taxonomy_id )
                    ->and( field( 'm.post_type' )->eq( 'nav_menu_item' ) )
                    ->and( field( 'm.post_status' )->eq( 'publish' ) )
            )
            ->groupBy( 'm.ID', 'm.menu_order' )
            ->orderBy( 'm.menu_order', 'asc' )
            ->compile();
    }

    /**
     * @param MenuItem|stdClass $menuItem
     */
    private static function setupNavMenuItem(stdClass $menuItem): stdClass {
        if ( property_exists($menuItem, 'post_type') && $menuItem->post_type !== null ) {
            if ( 'nav_menu_item' === $menuItem->post_type ) {
                $menuItem->db_id = (int) $menuItem->ID;
                $menuItem->menu_item_parent = (int) $menuItem->menu_item_parent;
                $menuItem->object_id = (int) $menuItem->object_id;

                $menuItem = self::setupNavMenuItemByType( $menuItem );

                $menuItem->attr_title = empty($menuItem->attr_title) ? (string) apply_filters( 'nav_menu_attr_title', (string) $menuItem->post_excerpt ) : (string) $menuItem->attr_title;

                if ( ! (property_exists($menuItem, 'description') && $menuItem->description !== null) ) {
                    $menuItem->description = (string) apply_filters( 'nav_menu_description', wp_trim_words( (string) $menuItem->post_content, 200 ) );
                }

                $menuItem->classes = (array) maybe_unserialize( (string) $menuItem->classes );

                return $menuItem;
            }

            $menuItem->db_id = 0;
            $menuItem->menu_item_parent = 0;
            $menuItem->object_id = (int) $menuItem->ID;
            $menuItem->type = 'post_type';
            $menuItem->object = '';
            $menuItem->type_label = '';

            $object = get_post_type_object( (string) $menuItem->post_type );

            if ($object instanceof WP_Post_Type) {
                $menuItem->object = $object->name;
                $menuItem->type_label = (string) $object->labels->singular_name;
            }

            $menuItem->post_title = self::getBaseMenuItemTitle($menuItem);

            $menuItem->title = $menuItem->post_title;

            $permalink = get_permalink( (int) $menuItem->ID );

            $menuItem->url = empty($permalink) ? '' : $permalink;
            $menuItem->target = '';
            $menuItem->attr_title = (string) apply_filters( 'nav_menu_attr_title', '' );
            $menuItem->description = (string) apply_filters( 'nav_menu_description', '' );
            $menuItem->classes = [];
            $menuItem->xfn = '';

            return $menuItem;
        }

        if ( ! empty( $menuItem->taxonomy ) ) {
            $menuItem->ID = $menuItem->term_id;
            $menuItem->db_id = 0;
            $menuItem->menu_item_parent = 0;
            $menuItem->object_id = (int) $menuItem->term_id;
            $menuItem->post_parent = (int) $menuItem->parent;
            $menuItem->type = 'taxonomy';

            $menuItem->object = '';
            $menuItem->type_label = '';

            $object = get_taxonomy( (string) $menuItem->taxonomy );

            if ($object instanceof WP_Taxonomy) {
                $menuItem->object = $object->name;
                $menuItem->type_label = (string) $object->labels->singular_name;
            }

            unset($object);

            $menuItem->title = (string) $menuItem->name;

            $termLink = app_get_term_link( (int) $menuItem->term_id, (string) $menuItem->taxonomy );

            $menuItem->url = empty($termLink) ? '' : $termLink;

            unset($termLink);

            $menuItem->target = '';
            $menuItem->attr_title = '';
            $menuItem->description = '';

            $termDescription = get_term_field( 'description', (int) $menuItem->term_id, (string) $menuItem->taxonomy );

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
     * @param MenuItem|stdClass $menuItem
     *
     * @return MenuItem|stdClass
     */
    private static function setupNavMenuItemByType(stdClass $menuItem): stdClass {
        switch ( $menuItem->type ) {
            case 'post_type':
                $postTypeObject = get_post_type_object( (string) $menuItem->object );

                $menuItem->_invalid = true;
                $menuItem->url = '';

                $originalTitle = self::getBaseMenuItemTitle($menuItem);

                $originalPost = get_post( (int) $menuItem->object_id );

                $menuItem->type_label = self::getPostTypeLabel($menuItem);

                if ($originalPost instanceof WP_Post) {
                    $menuItem->url = (string) get_permalink( $originalPost->ID );
                    $originalTitle = (string) apply_filters( 'the_title', $originalPost->post_title, $originalPost->ID );

                    $menuItem->_invalid = 'trash' === $originalPost->post_status;
                }

                if ($postTypeObject instanceof WP_Post_Type) {
                    $menuItem->_invalid = false;
                }

                $menuItem->title = $originalTitle;

                break;

            case 'post_type_archive':
                $postTypeObject = get_post_type_object( (string) $menuItem->object );

                $menuItem->_invalid = true;

                $menuItem->title = (string) $menuItem->post_title;

                if ($postTypeObject instanceof WP_Post_Type) {
                    $menuItem->title = (string) $postTypeObject->labels->archives;
                    $menuItem->_invalid = false;
                }

                $menuItem->type_label = __( 'Post Type Archive' );

                $archiveLink = get_post_type_archive_link( (string) $menuItem->object );

                $menuItem->url = empty($archiveLink) ? '' : $archiveLink;

                break;

            case 'taxonomy':

                $menuItem->_invalid = true;
                $menuItem->url = '';

                $originalTitle = self::getBaseMenuItemTitle($menuItem);

                $menuItem->title = (string) $menuItem->post_title;

                $originalTerm = get_term( (int) $menuItem->object_id, (string) $menuItem->object );

                $menuItem->type_label = self::getTaxonomyLabel($menuItem);

                if ($originalTerm instanceof WP_Term) {
                    $menuItem->url = (string) app_get_term_link( (int) $menuItem->object_id, (string) $menuItem->object );

                    $originalTitle = $originalTerm->name;

                    $menuItem->_invalid = false;
                }

                if (taxonomy_exists( (string) $menuItem->object )) {
                    $menuItem->_invalid = false;
                }

                $menuItem->title = $originalTitle;

                break;

            default:
                $menuItem->type_label = __( 'Custom Link' );
                $menuItem->title = (string) $menuItem->post_title;

                break;
        }

        return $menuItem;
    }

    /**
     * @param MenuItem|stdClass $menuItem
     *
     * @return string
     */
    private static function getTaxonomyLabel(stdClass $menuItem): string {
        $taxonomyObject = get_taxonomy( (string) $menuItem->object );

        if ($taxonomyObject instanceof WP_Taxonomy) {
            $taxonomyLabels = get_taxonomy_labels($taxonomyObject);

            return empty($taxonomyLabels->singular_name) ? (string) $menuItem->object : (string) $taxonomyLabels->singular_name;
        }

        return (string) $menuItem->object;
    }

    /**
     * @param MenuItem|stdClass $menuItem
     *
     * @return string
     */
    private static function getPostTypeLabel(stdClass $menuItem): string {
        $label = (string) $menuItem->object;

        $postTypeObject = get_post_type_object( (string) $menuItem->object );

        $originalPost = get_post( (int) $menuItem->object_id );

        if ($postTypeObject instanceof WP_Post_Type) {
            if ($originalPost instanceof WP_Post) {
                $label = wp_strip_all_tags( implode( ', ', get_post_states( $originalPost ) ) );
            } else {
                $label = (string) $postTypeObject->labels->singular_name;
            }
        }

        return $label;
    }

    /**
     * @param MenuItem|stdClass $menuItem
     *
     * @return string
     */
    private static function getBaseMenuItemTitle(stdClass $menuItem): string {
        return '' === $menuItem->post_title ? sprintf( __( '#%d (no title)' ), (int) $menuItem->ID ) : (string) $menuItem->post_title;
    }
}
