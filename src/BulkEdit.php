<?php

namespace JazzMan\Performance;

use JazzMan\AutoloadInterface\AutoloadInterface;

/**
 * Class BulkEdit.
 *
 * @see https://github.com/Automattic/vip-go-mu-plugins-built/blob/master/performance/bulk-edit.php
 */
class BulkEdit implements AutoloadInterface
{
    const BULK_EDIT_LIMIT = 20;

    public function load()
    {
        add_action('load-edit.php', [$this, 'defer_term_counting']);
        add_action('wp_loaded', [$this, 'limit_bulk_edit_for_registered_post_types']);

        add_action('admin_notices', [$this,'bulk_edit_admin_notice']);
    }

    public function defer_term_counting()
    {
        if (isset($_REQUEST['bulk_edit'])) {
            wp_defer_term_counting(true);
            add_action('shutdown', static function () {
                wp_defer_term_counting(false);
            });
        }
    }

    /**
     * Determine if bulk editing should be blocked.
     */
    private function bulk_editing_is_limited()
    {
        global $wp_query;

        $per_page = get_query_var('posts_per_page');
        // Get total number of entries
        $total_posts = $wp_query->found_posts;
        // Core defaults to 20 posts per page
        // Do no hide bulk edit actions if number of total entries is less than 20
        if (isset($total_posts) && self::BULK_EDIT_LIMIT > $total_posts) {
            return false;
        }
        // If requesting all entries, or more than 20, hide bulk actions
        if (-1 === $per_page) {
            return true;
        }

        return $per_page > self::BULK_EDIT_LIMIT;
    }

    /**
     * @param array $bulk_actions
     *
     * @return array
     */
    public function limit_bulk_edit($bulk_actions)
    {
        if ($this->bulk_editing_is_limited()) {
            $bulk_actions = [];
        }

        return $bulk_actions;
    }

    public function limit_bulk_edit_for_registered_post_types()
    {
        $types = get_post_types([
            'show_ui' => true,
        ]);

        foreach ($types as $type) {
            add_action("bulk_actions-edit-{$type}", [$this, 'limit_bulk_edit']);
        }
    }

    public function bulk_edit_admin_notice()
    {
        if ( ! $this->bulk_editing_is_limited() ) {
            return;
        }

        // HTML class doubles as key used to track dismissed notices
        $id = 'notice-vip-bulk-edit-limited';

        $dismissed_pointers = array_filter( explode( ',', (string) get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) ) );
        if ( in_array( $id, $dismissed_pointers, true ) ) {
            return;
        }

        $email_subject = sprintf( '[%s] Bulk Edit Help', home_url() );
        $mailto = 'mailto:vasyl@upmedio.com?subject=' . urlencode( $email_subject );
        ?>
        <div id="<?php echo esc_attr( $id ); ?>" class="notice notice-error is-dismissible">
            <p><?php printf( __( 'Bulk actions are disabled because more than %s items were requested. To re-enable bulk edit, please adjust the "Number of items" setting under <em>Screen Options</em>. If you have a large number of posts to update, please <a href="%s">get in touch</a> as we may be able to help.', 'wpcom-vip' ), number_format_i18n( self::BULK_EDIT_LIMIT ), esc_url( $mailto ) ); ?></p>

            <script>jQuery(document).ready( function($) { $( '#<?php echo esc_js( $id ); ?>' ).on( 'remove', function() {
                $.ajax( {
                  url: ajaxurl,
                  type: 'POST',
                  xhrFields: {
                    withCredentials: true
                  },
                  data: {
                    action: 'dismiss-wp-pointer',
                    pointer: '<?php echo esc_js( $id ); ?>'
                  }
                } );
              } ) } );</script>
        </div>
        <?php

    }
}
