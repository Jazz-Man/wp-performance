<?php

namespace JazzMan\Performance\Utils;

use JazzMan\AutoloadInterface\AutoloadInterface;
use WP_Error;
use WP_Post;

/**
 * Class DuplicatePost.
 */
class DuplicatePost implements AutoloadInterface {
    private static string $action = 'duplicate_post_as_draft';

    private static string $nonce = 'duplicate_nonce';

    public function load(): void {
        add_filter( 'post_row_actions', [__CLASS__, 'duplicatePostLink'], 10, 2 );
        add_filter( 'page_row_actions', [__CLASS__, 'duplicatePostLink'], 10, 2 );

        add_action( sprintf('admin_action_%s', self::$action), [__CLASS__, 'duplicatePostAsDraft'] );
    }

    /**
     * @param array<string,string> $actions
     *
     * @return array<string,string>
     */
    public static function duplicatePostLink(array $actions, WP_Post $wpPost): array {
        if ( 'publish' === $wpPost->post_status && current_user_can( 'edit_posts' ) ) {
            $actions['duplicate'] = sprintf(
                '<a href="%s" title="%s" rel="permalink">%s</a>',
                wp_nonce_url(
                    add_query_arg(
                        [
                            'action' => self::$action,
                            'post' => $wpPost->ID,
                        ],
                        'admin.php'
                    ),
                    basename( __FILE__ ),
                    self::$nonce
                ),
                esc_attr__( 'Duplicate this item' ),
                esc_attr__( 'Duplicate' )
            );
        }

        return $actions;
    }

    public static function duplicatePostAsDraft(): void {
        check_ajax_referer( basename( __FILE__ ), self::$nonce );

        $parameterBag = app_get_request_data();

        /** @var int|null $postId */
        $postId = $parameterBag->getDigits( 'post' );
        /** @var string|null $action */
        $action = $parameterBag->get( 'action' );

        if ( !$postId && self::$action !== $action ) {
            wp_die( 'No post to duplicate has been supplied!' );
        }

        // get the original post data
        $post = get_post( $postId );

        // if post data exists, create the post duplicate
        if ( $post instanceof WP_Post ) {
            self::createNewDraftPost( $post, (int) $postId );

            exit;
        }

        $title = 'Post creation failed!';
        wp_die( sprintf( '<span>%s</span>> could not find original post: %d', $title, $postId ), $title );
    }

    private static function createNewDraftPost(WP_Post $wpPost, int $oldPostId): void {
        /**
         * if you don't want current user to be the new post author,
         * then change next couple of lines to this: $new_post_author = $post->post_author;.
         */
        $currentUser = wp_get_current_user();

        $newPostAuthor = (int) $wpPost->post_author === $currentUser->ID ? $wpPost->post_author : $currentUser->ID;

        // new post data array
        $postData = $wpPost->to_array();
        unset(
            $postData['post_date'],
            $postData['post_date_gmt'],
            $postData['post_modified'],
            $postData['post_modified_gmt'],
            $postData['page_template'],
            $postData['guid'],
            $postData['ancestors']
        );

        /** @var array<string,string|string[]|int> $newPostArgs */
        $newPostArgs = wp_parse_args(
            [
                'post_author' => $newPostAuthor,
                'post_status' => 'draft',
            ],
            $postData
        );

        $newPostId = wp_insert_post( $newPostArgs, true );

        if ( $newPostId instanceof WP_Error ) {
            wp_die( $newPostId->get_error_message() );
        }

        self::addTerms( $wpPost, (int) $newPostId, $oldPostId );
        self::addMetaData( (int) $newPostId, $oldPostId );

        $editPostLink = get_edit_post_link( (int) $newPostId, 'edit' );

        if ( ! empty( $editPostLink ) ) {
            // finally, redirect to the edit post screen for the new draft
            wp_redirect( $editPostLink );
        }
    }

    private static function addTerms(WP_Post $wpPost, int $newPostId, int $oldPostId): void {
        /** @var string[] $taxonomies */
        $taxonomies = get_object_taxonomies( $wpPost->post_type );

        foreach ( $taxonomies as $taxonomy ) {
            /** @var string[] $postTerms */
            $postTerms = wp_get_object_terms( $oldPostId, $taxonomy, [ 'fields' => 'slugs' ] );

            if ( ! empty( $postTerms ) ) {
                wp_set_object_terms( $newPostId, $postTerms, $taxonomy, false );
            }
        }
    }

    private static function addMetaData(int $newPostId, int $oldPostId): void {
        /** @var array<string,string>|null $data */
        $data = get_post_custom( $oldPostId );

        if ( empty( $data ) ) {
            return;
        }

        foreach ( $data as $metaKey => $metaValues ) {
            add_post_meta( $newPostId, $metaKey, $metaValues );
        }
    }
}
