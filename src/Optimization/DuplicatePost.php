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

    public function duplicatePostAsDraft()
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

        /*
         * if you don't want current user to be the new post author,
         * then change next couple of lines to this: $new_post_author = $post->post_author;
         */
        $currentUser = wp_get_current_user();
        $newPostAuthor = $currentUser->ID;

        // if post data exists, create the post duplicate
        if ( ! empty($post)) {
            // new post data array
            $args = [
                'comment_status' => $post->comment_status,
                'ping_status' => $post->ping_status,
                'post_author' => $newPostAuthor,
                'post_content' => $post->post_content,
                'post_excerpt' => $post->post_excerpt,
                'post_name' => $post->post_name,
                'post_parent' => $post->post_parent,
                'post_password' => $post->post_password,
                'post_status' => 'draft',
                'post_title' => $post->post_title,
                'post_type' => $post->post_type,
                'to_ping' => $post->to_ping,
                'menu_order' => $post->menu_order,
            ];

            $newPostId = wp_insert_post($args, true);

            if (is_wp_error($newPostId)) {
                wp_die($newPostId->get_error_message());
            }

            $taxonomies = get_object_taxonomies($post->post_type);
            foreach ($taxonomies as $taxonomy) {
                $postTerms = wp_get_object_terms($postId, $taxonomy, ['fields' => 'slugs']);

                if ( ! empty($postTerms)) {
                    wp_set_object_terms($newPostId, $postTerms, $taxonomy, false);
                }
            }

            $data = get_post_custom($postId);

            foreach ($data as $key => $values) {
                foreach ($values as $value) {
                    add_post_meta($newPostId, $key, $value);
                }
            }

            // finally, redirect to the edit post screen for the new draft
            wp_redirect(get_edit_post_link($newPostId, 'edit'));

            exit;
        }

        $title = 'Post creation failed!';
        wp_die(
            sprintf(
                '<span>%s</span>> could not find original post: %d',
                $title,
                esc_attr($postId)
            ),
            $title
        );
    }
}
