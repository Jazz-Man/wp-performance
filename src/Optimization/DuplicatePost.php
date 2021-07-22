<?php

namespace JazzMan\Performance\Optimization;

use JazzMan\AutoloadInterface\AutoloadInterface;
use WP_Post;

/**
 * Class DuplicatePost.
 */
class DuplicatePost implements AutoloadInterface
{
    /**
     * @var string
     */
    private $action = 'duplicate_post_as_draft';
    /**
     * @var string
     */
    private $nonce = 'duplicate_nonce';

    public function load()
    {
        add_filter('post_row_actions', [$this, 'duplicatePostLink'], 10, 2);
        add_filter('page_row_actions', [$this, 'duplicatePostLink'], 10, 2);

        add_action("admin_action_$this->action", [$this, 'duplicatePostAsDraft']);
    }

    /**
     * @param array<string,string> $actions
     *
     * @return array<string,string>
     */
    public function duplicatePostLink(array $actions, WP_Post $post): array
    {
        if ('publish' === $post->post_status && current_user_can('edit_posts')) {
            $actions['duplicate'] = sprintf(
                '<a href="%s" title="%s" rel="permalink">%s</a>',
                wp_nonce_url(
                    add_query_arg(
                        [
                            'action' => $this->action,
                            'post' => $post->ID,
                        ],
                        'admin.php'
                    ),
                    basename(__FILE__),
                    $this->nonce
                ),
                esc_attr__('Duplicate this item'),
                esc_attr__('Duplicate')
            );
        }

        return $actions;
    }

    public function duplicatePostAsDraft(): void
    {
        check_ajax_referer(basename(__FILE__), $this->nonce);

        $request = app_get_request_data();

        $postId = $request->getDigits('post');
        $action = $request->get('action');

        if ( ! ($postId || ($this->action === $action))) {
            wp_die('No post to duplicate has been supplied!');
        }

        // get the original post data
        $post = get_post($postId);

        // if post data exists, create the post duplicate
        if ($post instanceof WP_Post) {
            $this->createNewDraftPost($post, $postId);

            exit;
        }

        $title = 'Post creation failed!';
        wp_die(sprintf('<span>%s</span>> could not find original post: %d', $title, $postId), $title);
    }

    private function createNewDraftPost(WP_Post $post, int $oldPostId): void
    {
        /**
         * if you don't want current user to be the new post author,
         * then change next couple of lines to this: $new_post_author = $post->post_author;.
         */
        $currentUser = wp_get_current_user();

        $newPostAuthor = (int) $post->post_author === $currentUser->ID ? $post->post_author : $currentUser->ID;

        // new post data array
        $postData = $post->to_array();
        unset(
            $postData['post_date'],
            $postData['post_date_gmt'],
            $postData['post_modified'],
            $postData['post_modified_gmt'],
            $postData['page_template'],
            $postData['guid'],
            $postData['ancestors']
        );

        $newPostArgs = wp_parse_args([
            'post_author' => $newPostAuthor,
            'post_status' => 'draft',
        ], $postData);

        $newPostId = wp_insert_post($newPostArgs, true);

        if (is_wp_error($newPostId)) {
            wp_die($newPostId->get_error_message());
        }

        /** @var string[] $taxonomies */
        $taxonomies = get_object_taxonomies($post->post_type);
        foreach ($taxonomies as $taxonomy) {
            /** @var string[] $postTerms */
            $postTerms = wp_get_object_terms($oldPostId, $taxonomy, ['fields' => 'slugs']);

            if ( ! empty($postTerms)) {
                wp_set_object_terms($newPostId, $postTerms, $taxonomy, false);
            }
        }

        $data = get_post_custom($oldPostId);

        foreach ($data as $key => $values) {
            foreach ($values as $value) {
                add_post_meta($newPostId, $key, $value);
            }
        }

        $editPostLink = get_edit_post_link($newPostId, 'edit');
        if ( ! empty($editPostLink)) {
            // finally, redirect to the edit post screen for the new draft
            wp_redirect($editPostLink);
        }
    }
}
