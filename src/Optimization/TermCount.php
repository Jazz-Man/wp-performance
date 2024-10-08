<?php

namespace JazzMan\Performance\Optimization;

use JazzMan\AutoloadInterface\AutoloadInterface;
use WP_Error;
use WP_Post;
use WP_Screen;
use WP_Taxonomy;

/**
 * Class TermCount.
 *
 * @see https://github.com/Automattic/vip-go-mu-plugins-built/blob/master/lightweight-term-count-update/lightweight-term-count-update.php
 */
final class TermCount implements AutoloadInterface {

    /**
     * Store the terms that have been incremented or decremented to avoid
     * duplicated efforts.
     *
     * @var array<int,array<string,array<string,int[]>>>
     */
    public array $countedTerms = [];

    /**
     * Post statuses which should be counted in term post counting. By default,
     * this is [ 'publish' ], but it can be altered via the
     * `ltcu_counted_statuses` filter.
     */
    private static string $countedStatus = 'publish';

    /**
     * Set up the singleton.
     */
    public function setup(): void {
        // Prevent core from counting terms.
        wp_defer_term_counting( true );

        remove_action( 'transition_post_status', '_update_term_count_on_transition_post_status' );

        add_action( 'transition_post_status', $this->transitionPostStatus( ... ), 10, 3 );

        add_action( 'added_term_relationship', $this->addedTermRelationship( ... ), 10, 3 );

        add_action( 'deleted_term_relationships', $this->deletedTermRelationships( ... ), 10, 3 );

        // Possibly recount posts for a term once it's been edited.
        add_action( 'edit_term', $this->maybeRecountPostsForTerm( ... ), 10, 3 );
    }

    public function load(): void {
        add_action( 'init', $this->setup( ... ) );
    }

    /**
     * When a term relationship is added, increment the term count.
     *
     * @param int    $objectId  object ID
     * @param int    $termTaxId single term taxonomy ID
     * @param string $taxonomy  taxonomy slug
     */
    public function addedTermRelationship( int $objectId, int $termTaxId, string $taxonomy ): void {
        $this->handleTermRelationshipChange( $objectId, (array) $termTaxId, $taxonomy, 'increment' );
    }

    /**
     * When a term relationship is deleted, decrement the term count.
     *
     * @param int    $objectId   object ID
     * @param int[]  $termTaxIds array of term taxonomy IDs
     * @param string $taxonomy   taxonomy slug
     */
    public function deletedTermRelationships( int $objectId, array $termTaxIds, string $taxonomy ): void {
        $this->handleTermRelationshipChange( $objectId, $termTaxIds, $taxonomy, 'decrement' );
    }

    /**
     * When a post transitions, increment or decrement term counts as
     * appropriate.
     *
     * @param string $newStatus new post status
     * @param string $oldStatus old post status
     */
    public function transitionPostStatus( string $newStatus, string $oldStatus, WP_Post $wpPost ): void {
        /** @var string[] $taxonomies */
        $taxonomies = get_object_taxonomies( $wpPost->post_type );

        foreach ( $taxonomies as $taxonomy ) {
            /** @var int[]|WP_Error $termIds */
            $termIds = wp_get_object_terms( $wpPost->ID, $taxonomy, [
                'fields' => 'tt_ids',
            ] );

            if ( empty( $termIds ) ) {
                continue;
            }

            if ( $termIds instanceof WP_Error ) {
                continue;
            }

            $this->quickUpdateTermsCount( $wpPost->ID, $termIds, $taxonomy, $this->transitionType( $newStatus, $oldStatus ) );
        }

        // For non-attachments, let's check if there are any attachment children
        // with inherited post status -- if so those will need to be re-counted.
        if ( 'attachment' !== $wpPost->post_type ) {
            /** @var WP_Post[] $attachments */
            $attachments = get_posts( [
                'post_type' => 'attachment',
                'post_parent' => $wpPost->ID,
                'post_status' => 'inherit',
                'ignore_sticky_posts' => true,
                'no_found_rows' => true,
                'posts_per_page' => -1,
                //                'fields' => 'ids',
                'orderby' => 'ID',
                'order' => 'ASC',
            ] );

            if ( ! empty( $attachments ) ) {
                foreach ( $attachments as $attachment ) {
                    $this->transitionPostStatus( $newStatus, $oldStatus, $attachment );
                }
            }
        }
    }

    /**
     * Force-recount posts for a term.  Do this only when the update originates from the edit term screen.
     *
     * @param int    $termId    the term id
     * @param int    $termTaxId the term taxonomy id
     * @param string $taxonomy  the taxonomy
     */
    public function maybeRecountPostsForTerm( int $termId, int $termTaxId, string $taxonomy ): void {
        $screen = \function_exists( 'get_current_screen' ) ? get_current_screen() : '';

        if ( ! $screen instanceof WP_Screen ) {
            return;
        }

        if ( \sprintf( 'edit-%s', $taxonomy ) === $screen->id ) {
            wp_update_term_count_now( [ $termTaxId ], $taxonomy );
        }
    }

    /**
     * Update term counts when term relationships are added or deleted.
     *
     * @param int    $objectId       object ID
     * @param int[]  $termTaxIds     array of term taxonomy IDs
     * @param string $taxonomy       taxonomy slug
     * @param string $transitionType transition type (increment or decrement)
     */
    private function handleTermRelationshipChange( int $objectId, array $termTaxIds, string $taxonomy, string $transitionType ): void {
        /** @var WP_Post|null $wpPost */
        $wpPost = get_post( $objectId );

        if ( ! $wpPost || ! is_object_in_taxonomy( $wpPost->post_type, $taxonomy ) ) {
            // If this object isn't a post, we can jump right into counting it.
            $this->quickUpdateTermsCount( $objectId, $termTaxIds, $taxonomy, $transitionType );
        } elseif ( $wpPost->post_status === self::$countedStatus ) {
            // If this is a post, we only count it if it's in a counted status.
            // If the status changed, that will be caught by
            // `LTCU_Plugin::transition_post_status()`. Also note that we used
            // `get_post_status()` above because that checks the parent status
            // if the status is inherit.
            $this->quickUpdateTermsCount( $objectId, $termTaxIds, $taxonomy, $transitionType );
        } else {
            clean_term_cache( $termTaxIds, $taxonomy, false );
        }
    }

    /**
     * Update term counts using a very light SQL query.
     *
     * @param int            $objectId       object ID with the term relationship
     * @param int[]|string[] $termTaxIds     term taxonomy IDs
     * @param string         $taxonomy       taxonomy slug
     * @param bool|string    $transitionType 'increment' or 'decrement'
     */
    private function quickUpdateTermsCount( int $objectId, array $termTaxIds, string $taxonomy, bool|string $transitionType ): void {
        global $wpdb;

        if ( ! $transitionType ) {
            return;
        }

        $termTaxIds = array_filter( array_map( 'intval', $termTaxIds ) );

        if ( empty( $termTaxIds ) ) {
            return;
        }

        $taxonomyObj = get_taxonomy( $taxonomy );

        if ( ! $taxonomyObj instanceof WP_Taxonomy ) {
            return;
        }

        // Respect if a taxonomy has a callback override.
        if ( isset( $taxonomyObj->update_count_callback ) && \is_callable( $taxonomyObj->update_count_callback ) ) {
            \call_user_func( $taxonomyObj->update_count_callback, $termTaxIds, $taxonomyObj );
        }

        if ( ! isset( $this->countedTerms[ $objectId ][ $taxonomy ][ (string) $transitionType ] ) ) {
            $this->countedTerms[ $objectId ][ $taxonomy ][ (string) $transitionType ] = [];
        }

        // Ensure that these terms haven't already been counted.
        $termTaxIds = array_diff( $termTaxIds, $this->countedTerms[ $objectId ][ $taxonomy ][ (string) $transitionType ] );

        if ( empty( $termTaxIds ) ) {
            // No term to process. So return.
            return;
        }

        $this->countedTerms[ $objectId ][ $taxonomy ][ (string) $transitionType ] = array_merge(
            $this->countedTerms[ $objectId ][ $taxonomy ][ (string) $transitionType ],
            $termTaxIds
        );

        foreach ( $termTaxIds as $termTaxId ) {
            // This action is documented in wp-includes/taxonomy.php
            do_action( 'edit_term_taxonomy', $termTaxId, $taxonomy );
        }

        $isIncrement = 'increment' === $transitionType;

        $wpdb->query(
            \sprintf(
                'update %s as tt set tt.count = tt.count %s 1 where tt.term_taxonomy_id in %s %s',
                $wpdb->term_taxonomy,
                $isIncrement ? '+' : '-',
                '('.implode( ',', $termTaxIds ).')',
                $isIncrement ? '' : 'AND tt.count > 0'
            )
        );

        foreach ( $termTaxIds as $termTaxId ) {
            // This action is documented in wp-includes/taxonomy.php
            do_action( 'edited_term_taxonomy', $termTaxId, $taxonomy );
        }

        clean_term_cache( $termTaxIds, $taxonomy, false );
    }

    /**
     * Determine if a term count should be incremented or decremented.
     *
     * @return bool|string 'increment', 'decrement', or false
     *
     * @psalm-return 'decrement'|'increment'|false
     */
    private function transitionType( string $newStatus, string $oldStatus ): bool|string {
        $newIsCounted = $newStatus === self::$countedStatus;
        $oldIsCounted = $oldStatus === self::$countedStatus;

        if ( $newIsCounted && ! $oldIsCounted ) {
            return 'increment';
        }

        if ( ! $oldIsCounted ) {
            return false;
        }

        if ( $newIsCounted ) {
            return false;
        }

        return 'decrement';
    }
}
