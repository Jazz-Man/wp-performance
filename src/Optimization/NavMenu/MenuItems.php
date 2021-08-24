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
use WP_Post;
use WP_Post_Type;
use WP_Taxonomy;
use WP_Term;

class MenuItems {
    /**
     * @return MenuItem[]|stdClass[]|false
     */
    public static function getItems(WP_Term $menuObject) {
        $cacheKey = Cache::getMenuItemCacheKey( $menuObject );

        /** @var MenuItem[]|stdClass[]|false $menuItems */
        $menuItems = wp_cache_get( $cacheKey, 'menu_items' );

        if ( false === $menuItems ) {
            try {
                $pdo = app_db_pdo();

                $sql = self::generateSql( $menuObject );

                $navStatement = $pdo->prepare( $sql->sql() );

                $navStatement->execute( $sql->params() );

                $menuItems = $navStatement->fetchAll( PDO::FETCH_OBJ );

                /** @var MenuItem[]|stdClass[] $menuItems */
                $menuItems = (array) apply_filters( 'app_nav_menu_cache_items', $menuItems, $menuObject );

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

    private static function generateSql(WP_Term $menuObject): Query {
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
                    ->eq( $menuObject->term_taxonomy_id )
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
    private static function setupNavMenuItem($menuItem): stdClass {
        if ( isset( $menuItem->post_type ) ) {
            if ( 'nav_menu_item' === $menuItem->post_type ) {
                $menuItem->db_id = (int) $menuItem->ID;
                $menuItem->menu_item_parent = (int) $menuItem->menu_item_parent;
                $menuItem->object_id = (int) $menuItem->object_id;

                $menuItem = self::setupNavMenuItemByType( $menuItem );

                $menuItem->attr_title = $menuItem->attr_title ?: apply_filters( 'nav_menu_attr_title', $menuItem->post_excerpt );

                if ( ! isset( $menuItem->description ) ) {
                    $menuItem->description = apply_filters( 'nav_menu_description', wp_trim_words( $menuItem->post_content, 200 ) );
                }

                $menuItem->classes = (array) maybe_unserialize( (string)$menuItem->classes );

                return $menuItem;
            }

            $menuItem->db_id = 0;
            $menuItem->menu_item_parent = 0;
            $menuItem->object_id = (int) $menuItem->ID;
            $menuItem->type = 'post_type';

            $object = get_post_type_object( $menuItem->post_type );

            $isPostType = $object instanceof WP_Post_Type;

            $menuItem->object = $isPostType ? $object->name : '';
            $menuItem->type_label = $isPostType ? $object->labels->singular_name : '';
            $menuItem->post_title = '' === $menuItem->post_title ? sprintf( __( '#%d (no title)' ), $menuItem->ID ) : $menuItem->post_title;

            $menuItem->title = $menuItem->post_title;
            $menuItem->url = get_permalink( $menuItem->ID );
            $menuItem->target = '';
            $menuItem->attr_title = apply_filters( 'nav_menu_attr_title', '' );
            $menuItem->description = apply_filters( 'nav_menu_description', '' );
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

            $object = get_taxonomy( $menuItem->taxonomy );

            $isTaxonomy = $object instanceof WP_Taxonomy;

            $menuItem->object = $isTaxonomy ? $object->name : '';
            $menuItem->type_label = $isTaxonomy ? $object->labels->singular_name : '';

            $menuItem->title = $menuItem->name;
            $menuItem->url = app_get_term_link( (int) $menuItem->term_id, (string) $menuItem->taxonomy );
            $menuItem->target = '';
            $menuItem->attr_title = '';

            $termDescription = get_term_field( 'description', $menuItem->term_id, $menuItem->taxonomy );

	        $menuItem->description = !is_wp_error($termDescription)? (string)$termDescription: '';

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
    private static function setupNavMenuItemByType($menuItem) {
        switch ( $menuItem->type ) {
            case 'post_type':
                $postTypeObject = get_post_type_object( $menuItem->object );

                $originalTerm = get_post( $menuItem->object_id );
                $isPost = $originalTerm instanceof WP_Post;

                $isPostType = $postTypeObject instanceof WP_Post_Type;
                $isInTrash = 'trash' === get_post_status( (int) $menuItem->object_id );

                $menuItem->type_label = $isPostType ? $postTypeObject->labels->singular_name : $menuItem->object;

                if ( $isPostType && function_exists( 'get_post_states' ) ) {
                    /** @var WP_Post $menuPost */
                    $menuPost = get_post( $menuItem->object_id );
                    $postStates = get_post_states( $menuPost );

                    if ( ! empty( $postStates ) ) {
                        $menuItem->type_label = wp_strip_all_tags( implode( ', ', $postStates ) );
                    }
                }

                $menuItem->_invalid = ! $isPostType || $isInTrash;

                $menuItem->url = $isPost ? get_permalink( $originalTerm->ID ) : '';

                $originalTitle = $isPost ?
                    apply_filters( 'the_title', $originalTerm->post_title, $originalTerm->ID ) :
                    sprintf( __( '#%d (no title)' ), $menuItem->object_id );

                $menuItem->title = $menuItem->post_title ?: $originalTitle;

                $menuItem->_invalid = ! $isPost;

                break;

            case 'post_type_archive':
                $postTypeObject = get_post_type_object( $menuItem->object );

                $isPostType = $postTypeObject instanceof WP_Post_Type;

                $menuItem->title = $menuItem->post_title ?: ( $isPostType ? $postTypeObject->labels->archives : '' );

                $menuItem->_invalid = ! $isPostType;

                $menuItem->type_label = __( 'Post Type Archive' );
                $menuItem->url = get_post_type_archive_link( $menuItem->object );

                break;

            case 'taxonomy':
                $taxonomyObject = get_taxonomy( $menuItem->object );
                $isTaxonomy = $taxonomyObject instanceof WP_Taxonomy;

                $originalTerm = get_term( $menuItem->object_id, $menuItem->object );

                $isTerm = $originalTerm instanceof WP_Term;

                $menuItem->type_label = $isTaxonomy ? $taxonomyObject->labels->singular_name : $menuItem->object;

                $menuItem->url = $isTerm ? (string)app_get_term_link( $menuItem->object_id, $menuItem->object ) : '';

                $originalTitle = $isTerm ? $originalTerm->name : sprintf( __( '#%d (no title)' ), $menuItem->object_id );

                $menuItem->_invalid = ! $isTerm || ! $isTaxonomy;

                $menuItem->title = $menuItem->post_title ?: $originalTitle;

                break;

            default:
                $menuItem->type_label = __( 'Custom Link' );
                $menuItem->title = $menuItem->post_title;

                break;
        }

        return $menuItem;
    }
}
