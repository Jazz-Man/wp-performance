<?php

namespace JazzMan\Performance\Optimization;

use JazzMan\AutoloadInterface\AutoloadInterface;
use WP_Query;
use WP_Screen;
use WP_Taxonomy;

/**
 * Class TermCount.
 *
 * @see https://github.com/Automattic/vip-go-mu-plugins-built/blob/master/lightweight-term-count-update/lightweight-term-count-update.php
 */
class TermCount implements AutoloadInterface
{
    /**
     * Post statuses which should be counted in term post counting. By default,
     * this is [ 'publish' ], but it can be altered via the
     * `ltcu_counted_statuses` filter.
     *
     * @var string
     */
    private static $countedStatus = 'publish';
    /**
     * Store the terms that have been incremented or decremented to avoid
     * duplicated efforts.
     *
     * @var array
     */
    public $countedTerms = [];

    /**
     * @return void
     */
    public function load()
    {
        add_action('init', [$this, 'setup']);
    }

    /**
     * Set up the singleton.
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
     * @param int    $objectId  object ID
     * @param int    $termTaxId single term taxonomy ID
     * @param string $taxonomy  taxonomy slug
     */
    public function addedTermRelationship(int $objectId, int $termTaxId, string $taxonomy): void
    {
        $this->handleTermRelationshipChange($objectId, (array) $termTaxId, $taxonomy, 'increment');
    }

    /**
     * When a term relationship is deleted, decrement the term count.
     *
     * @param int    $objectId   object ID
     * @param array  $termTaxIds array of term taxonomy IDs
     * @param string $taxonomy   taxonomy slug
     *
     */
    public function deletedTermRelationships(int $objectId, array $termTaxIds, string $taxonomy): void
    {
        $this->handleTermRelationshipChange($objectId, $termTaxIds, $taxonomy, 'decrement');
    }

    /**
     * Update term counts when term relationships are added or deleted.
     *
     * @param int    $objectId       object ID
     * @param array  $termTaxIds     array of term taxonomy IDs
     * @param string $taxonomy       taxonomy slug
     * @param string $transitionType transition type (increment or decrement)
     *
     */
    protected function handleTermRelationshipChange(int $objectId, array $termTaxIds, string $taxonomy, string $transitionType): void
    {
        $post = get_post($objectId);

        if ( ! $post || ! is_object_in_taxonomy($post->post_type, $taxonomy)) {
            // If this object isn't a post, we can jump right into counting it.
            $this->quickUpdateTermsCount($objectId, $termTaxIds, $taxonomy, $transitionType);

        } elseif ($post->post_status === self::$countedStatus) {
            // If this is a post, we only count it if it's in a counted status.
            // If the status changed, that will be caught by
            // `LTCU_Plugin::transition_post_status()`. Also note that we used
            // `get_post_status()` above because that checks the parent status
            // if the status is inherit.
            $this->quickUpdateTermsCount($objectId, $termTaxIds, $taxonomy, $transitionType);
        } else {
            clean_term_cache($termTaxIds, $taxonomy, false);
        }
    }

    /**
     * When a post transitions, increment or decrement term counts as
     * appropriate.
     *
     * @param string $newStatus new post status
     * @param string $oldStatus old post status
     * @param object $post      {
     *                          Post being transitioned. This not always a \WP_Post.
     *
     * @var int    $ID        post ID
     * @var string $post_type Post type.
     * }
     *
     * @return void
     */
    public function transitionPostStatus(string $newStatus, string $oldStatus, $post): void
    {
        foreach (get_object_taxonomies($post->post_type) as $taxonomy) {
            $termIds = wp_get_object_terms($post->ID, $taxonomy, [
                'fields' => 'tt_ids',
            ]);
            if ( ! empty($termIds) && ! is_wp_error($termIds)) {
                $this->quickUpdateTermsCount(
                    $post->ID,
                    $termIds,
                    $taxonomy,
                    $this->transitionType($newStatus, $oldStatus)
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
                foreach ($attachments->posts as $attachmentId) {
                    $this->transitionPostStatus($newStatus, $oldStatus, (object) [
                        'ID' => $attachmentId,
                        'post_type' => 'attachment',
                    ]);
                }
            }
        }
    }

    /**
     * Update term counts using a very light SQL query.
     *
     * @param int    $objectId       object ID with the term relationship
     * @param array  $termTaxIds     term taxonomy IDs
     * @param string $taxonomy       taxonomy slug
     * @param string|bool $transitionType 'increment' or 'decrement'
     *
     */
    private function quickUpdateTermsCount(int $objectId, array $termTaxIds, string $taxonomy, $transitionType): bool
    {
        global $wpdb;

        $taxonomyObj = get_taxonomy($taxonomy);

        $termTaxIds = array_filter(array_map('intval', $termTaxIds));

        if (! $transitionType || empty($termTaxIds) || !($taxonomyObj instanceof WP_Taxonomy)){
          return false;
        }

        // Respect if a taxonomy has a callback override.
        if ( ! empty($taxonomyObj->update_count_callback)) {
            call_user_func($taxonomyObj->update_count_callback, $termTaxIds, $taxonomyObj);
        }

        if ( ! isset($this->countedTerms[$objectId][$taxonomy][$transitionType])) {
            $this->countedTerms[$objectId][$taxonomy][$transitionType] = [];
        }

        // Ensure that these terms haven't already been counted.
        $termTaxIds = array_diff($termTaxIds, $this->countedTerms[$objectId][$taxonomy][$transitionType]);
        if (empty($termTaxIds)) {
            // No term to process. So return.
            return false;
        }

        $this->countedTerms[$objectId][$taxonomy][$transitionType] = array_merge(
            $this->countedTerms[$objectId][$taxonomy][$transitionType],
            $termTaxIds
        );

        $termIdsString = '('.implode(',', $termTaxIds).')';

        $isIncrement = 'increment' === $transitionType;

        $operand = $isIncrement ? '+' : '-';
        $ttCount = $isIncrement ? '' : 'AND tt.count > 0';

        $sql = "UPDATE $wpdb->term_taxonomy AS tt SET tt.count = tt.count $operand 1 WHERE tt.term_taxonomy_id IN $termIdsString $ttCount";

        foreach ($termTaxIds as $termId) {
            // This action is documented in wp-includes/taxonomy.php
            do_action('edit_term_taxonomy', $termId, $taxonomy);
        }

        $wpdb->query($sql); // WPCS: unprepared SQL ok.

        foreach ($termTaxIds as $termId) {
            // This action is documented in wp-includes/taxonomy.php
            do_action('edited_term_taxonomy', $termId, $taxonomy);
        }
        clean_term_cache($termTaxIds, $taxonomy, false);

        return true;
    }

    /**
     * Determine if a term count should be incremented or decremented.
     *
     * @return false|string 'increment', 'decrement', or false
     *
     * @psalm-return 'decrement'|'increment'|false
     */
    private function transitionType(string $newStatus, string $oldStatus)
    {
        $newIsCounted = $newStatus === self::$countedStatus;
        $oldIsCounted = $oldStatus === self::$countedStatus;
        if ($newIsCounted && ! $oldIsCounted) {
            return 'increment';
        }

        if ($oldIsCounted && ! $newIsCounted) {
            return 'decrement';
        }

        return false;
    }

    /**
     * Force-recount posts for a term.  Do this only when the update originates from the edit term screen.
     *
     * @param int    $termId    the term id
     * @param int    $termTaxId the term taxonomy id
     * @param string $taxonomy  the taxonomy
     *
     * @return bool false if the screen check fails, true otherwise
     */
    public function maybeRecountPostsForTerm(int $termId, int $termTaxId, string $taxonomy): bool
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : '';
        if ( ! ($screen instanceof WP_Screen)) {
            return false;
        }
        if ("edit-$taxonomy" === $screen->id) {
            wp_update_term_count_now([$termTaxId], $taxonomy);
        }

        return true;
    }
}
