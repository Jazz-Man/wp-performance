<?php

namespace JazzMan\Performance\Security;

use JazzMan\AutoloadInterface\AutoloadInterface;
use JazzMan\Performance\App;

/**
 * Class ContactFormSpamTester.
 */
class ContactFormSpamTester implements AutoloadInterface
{
    /**
     * @var string|bool
     */
    private $recaptcha_site_key;
    /**
     * @var string|bool
     */
    private $recaptcha_secret_key;

    /**
     * @var bool
     */
    private $recaptcha_enable;

    public function load()
    {

        if (App::isCli()) {
            $this->recaptcha_enable = false;
        } else {
            $this->recaptcha_enable = (bool) apply_filters('contact_form_recaptcha_enable', true);
        }

        if ($this->recaptcha_enable){

            $this->recaptcha_site_key = \defined('RECAPTCHA_SITE_KEY') ? RECAPTCHA_SITE_KEY : '6LeTc6EUAAAAABNvNwjdbiho6ZatEQdMo5IH7uhd';
            $this->recaptcha_secret_key = \defined('RECAPTCHA_SECRET_KEY') ? RECAPTCHA_SECRET_KEY : '6LeTc6EUAAAAAD01OZ98Kgv46VdRss7-aEIZ95bA';

            define('WPCF7_RECAPTCHA_SITEKEY',$this->recaptcha_site_key);
            define('WPCF7_RECAPTCHA_SECRET',$this->recaptcha_secret_key);
        }

    }

}
