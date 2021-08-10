<?php

namespace JazzMan\Performance\Security;

use JazzMan\AutoloadInterface\AutoloadInterface;

/**
 * Class ContactFormSpamTester.
 */
class ContactFormSpamTester implements AutoloadInterface
{
    public function load()
    {
        $recaptchaEnable = ! app_is_wp_cli() && apply_filters('contact_form_recaptcha_enable', true);

        if ($recaptchaEnable) {
            $recaptchaSiteKey = defined('RECAPTCHA_SITE_KEY') ? RECAPTCHA_SITE_KEY : '6LeTc6EUAAAAABNvNwjdbiho6ZatEQdMo5IH7uhd';
            $recaptchaSecretKey = defined('RECAPTCHA_SECRET_KEY') ? RECAPTCHA_SECRET_KEY : '6LeTc6EUAAAAAD01OZ98Kgv46VdRss7-aEIZ95bA';

            define('WPCF7_RECAPTCHA_SITEKEY', $recaptchaSiteKey);
            define('WPCF7_RECAPTCHA_SECRET', $recaptchaSecretKey);
        }
    }
}
