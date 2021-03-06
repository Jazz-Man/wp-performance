<?php

namespace JazzMan\Performance\Optimization;

use JazzMan\AutoloadInterface\AutoloadInterface;
use JazzMan\Performance\App;
use WP_Query;
use WP_Screen;

/**
 * Class TermCount.
 *
 * @see https://github.com/Automattic/vip-go-mu-plugins-built/blob/master/lightweight-term-count-update/lightweight-term-count-update.php
 */
class TermCount implements AutoloadInterface
{
    /**
     * Post statuses which should be counted in term post counting. By default
     * this is [ 'publish' ], but it can be altered via the
     * `ltcu_counted_statuses` filter.
     *
     * @var string
     */
    private $countedStatus = 'publish';
    /**
     * Store the terms that have been incremented or decremented to avoid
     * duplicated efforts.
     *
     * @var array
     */
    public $counted_terms = [];

    public function load()
    {
        if (App::enabled()) {
            add_action('init', [$this, 'setup']);
        }
    }

    /**
     * Setup the singleton.
     */
    public function setup(): void
    {
        // Prevent core from counting terms.
        wp_defer_term_counting(true);
        remove_action('transition_post_status', '_update_term_count_on_transition_post_status');

        add_action('transition_post_status', [$this, 'transitionPostStatus'], 10, 3);
        add_action('added_term_relationship', [$this, 'addedTermRelationship'], 10, 3);
        add_action('deleted_term_relationships', [$this, 'deletedTermRelationships'], 10, 3);
        // Possibly recount posts for a term once it's been edited.
        add_action('edit_term', [$this, 'maybeRecountPostsForTerm'], 10, 3);
    }

    /**
     * When a term relationship is added, increment the term count.
     *
     * @param int    $object_id object ID
     * @param int    $tt_id     single term taxonomy ID
     * @param string $taxonomy  taxonomy slug
     */
    public function addedTermRelationship(int $object_id, int $tt_id, string $taxonomy): void
    {
        $this->handleTermRelationshipChange($object_id, (array) $tt_id, $taxonomy, 'increment');
    }

    /**
     * When a term relationship is deleted, decrement the term count.
     *
     * @param int    $object_id object ID
     * @param array  $tt_ids    array of term taxonomy IDs
     * @param string $taxonomy  taxonomy slug
     *
     *@see {LTCU_Plugin::handle_term_relationship_change()}
     */
    public function deletedTermRelationships(int $object_id, array $tt_ids, string $taxonomy): void
    {
        $this->handleTermRelationshipChange($object_id, $tt_ids, $taxonomy, 'decrement');
    }

    /**
     * Update term counts when term relationships are added or deleted.
     *
     * @param int    $object_id       object ID
     * @param array  $tt_ids          array of term taxonomy IDs
     * @param string $taxonomy        taxonomy slug
     * @param string $transition_type transition type (increment or decrement)
     *
     *@see {LTCU_Plugin::added_term_relationship()}
     * @see {LTCU_Plugin::deleted_term_relationships()}
     */
    protected function handleTermRelationshipChange(int $object_id, array $tt_ids, string $taxonomy, string $transition_type): void
    {
        $post = get_post($object_id);
        if (!$post || !is_object_in_taxonomy($post->post_type, $taxonomy)) {
            // If this object isn't a post, we can jump right into counting it.
            $this->quickUpdateTermsCount($object_id, $tt_ids, $taxonomy, $transition_type);
        } elseif ($post->post_status === $this->countedStatus) {
            // If this is a post, we only count it if it's in a counted status.
            // If the status changed, that will be caught by
            // `LTCU_Plugin::transition_post_status()`. Also note that we used
            // `get_post_status()` above because that checks the parent status
            // if the status is inherit.
            $this->quickUpdateTermsCount($object_id, $tt_ids, $taxonomy, $transition_type);
        } else {
            clean_term_cache($tt_ids, $taxonomy, false);
        }
    }

    /**
     * When a post transitions, increment or decrement term counts as
     * appropriate.
     *
     * @param string $new_status new post status
     * @param string $old_status old post status
     * @param object $post       {
     *                           Post being transitioned. This not always a \WP_Post.
     *
     *     @var int    $ID        post ID
     *     @var string $post_type Post type.
     * }
     */
    public function transitionPostStatus(string $new_status, string $old_status, $post)
    {
        foreach (get_object_taxonomies($post->post_type) as $taxonomy) {
            $tt_ids = wp_get_object_terms($post->ID, $taxonomy, [
                'fields' => 'tt_ids',
            ]);
            if (!empty($tt_ids) && !is_wp_error($tt_ids)) {
                $this->quickUpdateTermsCount(
                    $post->ID,
                    $tt_ids,
                    $taxonomy,
                    $this->transitionType($new_status, $old_status)
                );
            }
        }
        // For non-attachments, let's check if there are any attachment children
        // with inherited post status -- if so those will need to be re-counted.
        if ('attachment' !== $post->post_type) {
            $attachments = new WP_Query([
                'post_type' => 'attachment',
                'post_parent' => $post->ID,
                'post_status' => 'inherit',
                'ignore_sticky_posts' => true,
                'no_found_rows' => true,
                'posts_per_page' => -1,
                'fields' => 'ids',
                'orderby' => 'ID',
                'order' => 'ASC',
            ]);
            if ($attachments->have_posts()) {
                foreach ($attachments->posts as $attachment_id) {
                    $this->transitionPostStatus($new_status, $old_status, (object) [
                        'ID' => $attachment_id,
                        'post_type' => 'attachment',
                    ]);
                }
            }
        }
    }

    /**
     * Update term counts using a very light SQL query.
     *
     * @param int    $object_id       object ID with the term relationship
     * @param array  $tt_ids          term taxonomy IDs
     * @param string $taxonomy        taxonomy slug
     * @param string $transition_type 'increment' or 'decrement'
     */
    private function quickUpdateTermsCount(int $object_id, array $tt_ids, string $taxonomy, string $transition_type): bool
    {
        global $wpdb;
        if (!$transition_type) {
            return false;
        }
        $tax_obj = get_taxonomy($taxonomy);
        if ($tax_obj) {
            $tt_ids = array_filter(array_map('intval', $tt_ids));
            // Respect if a taxonomy has a callback override.
            if (!empty($tax_obj->update_count_callback)) {
                \call_user_func($tax_obj->update_count_callback, $tt_ids, $tax_obj);
            } elseif (!empty($tt_ids)) {
                if (!isset($this->counted_terms[$object_id][$taxonomy][$transition_type])) {
                    $this->counted_terms[$object_id][$taxonomy][$transition_type] = [];
                }
                // Ensure that these terms haven't already been counted.
                $tt_ids = array_diff($tt_ids, $this->counted_terms[$object_id][$taxonomy][$transition_type]);
                if (empty($tt_ids)) {
                    // No term to process. So return.
                    return false;
                }
                $this->counted_terms[$object_id][$taxonomy][$transition_type] = array_merge(
                    $this->counted_terms[$object_id][$taxonomy][$transition_type],
                    $tt_ids
                );
                $tt_ids = array_map('absint', $tt_ids);
                $tt_ids_string = '('.implode(',', $tt_ids).')';
                if ('increment' === $transition_type) {
                    // Incrementing.
                    $update_query = "UPDATE {$wpdb->term_taxonomy} AS tt SET tt.count = tt.count + 1 WHERE tt.term_taxonomy_id IN {$tt_ids_string}";
                } else {
                    // Decrementing.
                    $update_query = "UPDATE {$wpdb->term_taxonomy} AS tt SET tt.count = tt.count - 1 WHERE tt.term_taxonomy_id IN {$tt_ids_string} AND tt.count > 0";
                }
                foreach ($tt_ids as $tt_id) {
                    // This action is documented in wp-includes/taxonomy.php
                    do_action('edit_term_taxonomy', $tt_id, $taxonomy);
                }

                $wpdb->query($update_query); // WPCS: unprepared SQL ok.

                foreach ($tt_ids as $tt_id) {
                    // This action is documented in wp-includes/taxonomy.php
                    do_action('edited_term_taxonomy', $tt_id, $taxonomy);
                }
            }
            clean_term_cache($tt_ids, $taxonomy, false);
        }

        return true;
    }

    /**
     * Determine if a term count should be incremented or decremented.
     *
     * @return bool|string 'increment', 'decrement', or false
     */
    private function transitionType(string $newStatus, string $oldStatus)
    {
        $newIsCounted = $newStatus === $this->countedStatus;
        $oldIsCounted = $oldStatus === $this->countedStatus;
        if ($newIsCounted && !$oldIsCounted) {
            return 'increment';
        }

        if ($oldIsCounted && !$newIsCounted) {
            return 'decrement';
        }

        return false;
    }

    /**
     * Force-recount posts for a term.  Do this only when the update originates from the edit term screen.
     *
     * @param int    $term_id  the term id
     * @param int    $tt_id    the term taxonomy id
     * @param string $taxonomy the taxonomy
     *
     * @return bool false if the screen check fails, true otherwise
     */
    public function maybeRecountPostsForTerm($term_id, int $tt_id, string $taxonomy)
    {
        $screen = \function_exists('get_current_screen') ? get_current_screen() : '';
        if (!($screen instanceof WP_Screen)) {
            return false;
        }
        if ("edit-{$taxonomy}" === $screen->id) {
            wp_update_term_count_now([$tt_id], $taxonomy);
        }

        return true;
    }
}
