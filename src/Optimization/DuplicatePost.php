<?php

namespace JazzMan\Performance\Optimization;

use JazzMan\AutoloadInterface\AutoloadInterface;

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
        add_filter('post_row_actions', [$this, 'duplicate_post_link'], 10, 2);
        add_filter('page_row_actions', [$this, 'duplicate_post_link'], 10, 2);

        add_action("admin_action_{$this->action}", [$this, 'duplicate_post_as_draft']);
    }

    public function duplicate_post_link(array $actions, \WP_Post $post): array
    {
        if ('publish' === $post->post_status && current_user_can('edit_posts')) {
            $actions['duplicate'] = sprintf(
                '<a href="%s" title="Duplicate this item" rel="permalink">Duplicate</a>',
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
                )
            );
        }

        return $actions;
    }

    public function duplicate_post_as_draft()
    {
        check_ajax_referer(basename(__FILE__), $this->nonce);

        $request = app_get_request_data();

        $post_id = $request->getDigits('post');
        $action = $request->get('action');

        if (!($post_id || ($this->action === $action))) {
            wp_die('No post to duplicate has been supplied!');
        }

        // get the original post data
        $post = get_post($post_id);

        /*
         * if you don't want current user to be the new post author,
         * then change next couple of lines to this: $new_post_author = $post->post_author;
         */
        $current_user = wp_get_current_user();
        $new_post_author = $current_user->ID;

        // if post data exists, create the post duplicate
        if (!empty($post)) {
            // new post data array
            $args = [
                'comment_status' => $post->comment_status,
                'ping_status' => $post->ping_status,
                'post_author' => $new_post_author,
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

            $new_post_id = wp_insert_post($args, true);

            if (is_wp_error($new_post_id)) {
                wp_die($new_post_id->get_error_message());
            }

            $taxonomies = get_object_taxonomies($post->post_type);
            foreach ($taxonomies as $taxonomy) {
                $post_terms = wp_get_object_terms($post_id, $taxonomy, ['fields' => 'slugs']);

                if (!empty($post_terms)) {
                    wp_set_object_terms($new_post_id, $post_terms, $taxonomy, false);
                }
            }

            $data = get_post_custom($post_id);

            foreach ($data as $key => $values) {
                foreach ($values as $value) {
                    add_post_meta($new_post_id, $key, $value);
                }
            }

            // finally, redirect to the edit post screen for the new draft
            wp_redirect(get_edit_post_link($new_post_id, 'edit'));

            exit;
        }

        $title = 'Post creation failed!';
        wp_die(
            sprintf(
                '<span>%s</span>> could not find original post: %d',
                $title,
                esc_attr($post_id)
            ),
            $title
        );
    }
}
