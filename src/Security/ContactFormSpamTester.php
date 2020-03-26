<?php

namespace JazzMan\Performance\Security;

use JazzMan\AutoloadInterface\AutoloadInterface;
use JazzMan\Performance\App;
use ReCaptcha\ReCaptcha;

/**
 * Class ContactFormSpamTester.
 */
class ContactFormSpamTester implements AutoloadInterface
{
    /**
     * @var string
     */
    private $recaptcha_input_name = 'g-recaptcha-response';

    /**
     * @var string|bool
     */
    private $recaptcha_site_key;
    /**
     * @var string|bool
     */
    private $recaptcha_secret_key;
    /**
     * @var string
     */
    private $recaptcha_action;

    /**
     * @var bool
     */
    private $recaptcha_enable;

    public function load()
    {
        $home = parse_url(home_url(), PHP_URL_HOST);

        $server_name = !empty($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : $home;

        $this->recaptcha_action = sanitize_key($server_name);

        if (App::isCli()) {
            $this->recaptcha_enable = false;
        } else {
            $this->recaptcha_enable = (bool) apply_filters('contact_form_recaptcha_enable', true);
        }

        $this->recaptcha_site_key = \defined('RECAPTCHA_SITE_KEY') ? RECAPTCHA_SITE_KEY : '6LeTc6EUAAAAABNvNwjdbiho6ZatEQdMo5IH7uhd';
        $this->recaptcha_secret_key = \defined('RECAPTCHA_SECRET_KEY') ? RECAPTCHA_SECRET_KEY : '6LeTc6EUAAAAAD01OZ98Kgv46VdRss7-aEIZ95bA';

        add_filter('wpcf7_spam', [$this, 'wpcf7_spam']);

        add_filter('wpcf7_form_autocomplete', static function ($autocomplete) {
            return 'off';
        });

        if ($this->recaptcha_enable) {
            add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);

            add_filter('wpcf7_form_hidden_fields', [$this, 'wpcf7_form_hidden_fields']);
        }
    }

    public function enqueue_scripts()
    {
        wp_register_script('recaptcha', add_query_arg([
            'render' => $this->recaptcha_site_key,
        ], 'https://www.google.com/recaptcha/api.js'));

        wp_add_inline_script('recaptcha', /* @lang JavaScript */ "        grecaptcha.ready(function() {
          
          var allRecaptchaInput = document.querySelectorAll('input[name={$this->recaptcha_input_name}]');
          
          if (allRecaptchaInput){
            allRecaptchaInput.forEach(function(input) {
              
              grecaptcha.execute('{$this->recaptcha_site_key}', {action: '{$this->recaptcha_action}'}).then(function(token) {
                input.value=token;
              });
              
            })
          }
        });");
    }

    /**
     * @param array $fields
     *
     * @return array
     */
    public function wpcf7_form_hidden_fields($fields)
    {
        wp_enqueue_script('recaptcha');

        return array_merge($fields, [
            $this->recaptcha_input_name => '',
        ]);
    }

    /**
     * @param bool $spam
     *
     * @return bool
     */
    public function wpcf7_spam($spam)
    {
        if ($this->recaptcha_enable) {
            if (empty($_POST[$this->recaptcha_input_name])) {
                return true;
            }

            $recaptcha = new ReCaptcha($this->recaptcha_secret_key);

            $res = $recaptcha->setExpectedHostname($_SERVER['SERVER_NAME'])
                ->setExpectedAction($this->recaptcha_action)
                ->setScoreThreshold(0.9)
                ->verify($_POST[$this->recaptcha_input_name], $_SERVER['REMOTE_ADDR'])
            ;

            return $res->isSuccess();
        }

        return $spam;
    }
}
