<?php

namespace JazzMan\performance;

use JazzMan\AutoloadInterface\AutoloadInterface;
use WP_Error;

/**
 * Class RestAPI.
 */
class RestAPI implements AutoloadInterface
{
    public function load()
    {
        add_filter('rest_authentication_errors', [$this, 'rest_authentication_errors']);
    }

    /**
     * @param WP_Error|mixed $result
     *
     * @return \WP_Error
     */
    public function rest_authentication_errors($result)
    {
        if (!empty($result)) {
            return $result;
        }
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            return new WP_Error('rest_not_logged_in', 'You are not currently logged in.', ['status' => 401]);
        }

        return $result;
    }
}
