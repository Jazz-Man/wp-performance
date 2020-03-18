<?php

namespace JazzMan\Performance\Security;

use JazzMan\AutoloadInterface\AutoloadInterface;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use Spamassassin\Client;
use WPCF7_ContactForm;

/**
 * Class ContactFormSpamTester.
 */
class ContactFormSpamTester implements AutoloadInterface
{
    /**
     * @var \Spamassassin\Client
     */
    private $emailSpamTester;
    /**
     * @var bool
     */
    private $use_html;
    /**
     * @var bool
     */
    private $exclude_blank;
    /**
     * @var array
     */
    private $template;
    /**
     * @var string
     */
    private $locale;

    public function load()
    {
        add_filter('wpcf7_spam', [$this, 'wpcf7_spam']);
    }

    /**
     * @param bool $spam
     *
     * @return bool
     */
    public function wpcf7_spam($spam)
    {
        if (!\function_exists('wpcf7_get_current_contact_form')) {
            return $spam;
        }

        /** @var WPCF7_ContactForm $contact_form */
        $contact_form = wpcf7_get_current_contact_form();

        $is_form = $contact_form instanceof WPCF7_ContactForm;

        $this->emailSpamTester = new Client([
            'hostname' => 'localhost',
            'port' => 783,
            'protocolVersion' => '1.5',
            'enableZlib' => false,
        ]);

        $is_ready = $is_form && $this->emailSpamTester->ping();

        if ($is_ready) {
            $template = $contact_form->prop('mail');

            $this->use_html = !empty($template['use_html']);

            $this->exclude_blank = !empty($template['exclude_blank']);

            $this->template = wp_parse_args($template, [
                'subject' => '',
                'sender' => '',
                'body' => '',
                'recipient' => '',
                'additional_headers' => '',
                'attachments' => '',
            ]);

            $this->locale = $contact_form->locale();

            return $this->getSpamReport();
        }

        return $spam;
    }

    /**
     * @return bool
     */
    public function getSpamReport()
    {
        $phpmailer = new PHPMailer(true);

        $components = [
            'subject' => $this->get('subject', true),
            'sender' => $this->get('sender', true),
            'body' => $this->get('body', true),
            'recipient' => $this->get('recipient', true),
            'additional_headers' => $this->get('additional_headers', true),
        ];

        $subject = wpcf7_strip_newline($components['subject']);
        $sender = wpcf7_strip_newline($components['sender']);
        $recipient = wpcf7_strip_newline($components['recipient']);
        $body = $components['body'];
        $additional_headers = trim($components['additional_headers']);

        $charset = get_bloginfo('charset');

        try {
            $from = $phpmailer::parseAddresses($sender);

            foreach ($from as $item) {
                $phpmailer->setFrom($item['address'], $item['name']);
            }

            $to = $phpmailer::parseAddresses($recipient);

            foreach ($to as $item) {
                $phpmailer->addAddress($item['address'], $item['name']);
            }

            $phpmailer->CharSet = $charset;

            $phpmailer->Subject = $subject;

            if (!empty($additional_headers)) {
                $additional_headers = $phpmailer->DKIM_HeaderC($additional_headers);

                $additional_headers = explode("\n", $additional_headers);

                foreach ($additional_headers as $header) {
                    [$name, $content] = explode(':', trim($header), 2);

                    $name = trim($name);
                    $content = trim($content);

                    switch (strtolower($name)) {
                        case 'reply-to':

                            $_reply = $phpmailer::parseAddresses($content);

                            foreach ($_reply as $reply) {
                                $phpmailer->addReplyTo($reply['address'], $reply['name']);
                            }

                            break;
                        case 'cc':

                            $_cc = $phpmailer::parseAddresses($content);

                            foreach ($_cc as $cc) {
                                $phpmailer->addCC($cc['address'], $cc['name']);
                            }

                            break;
                        case 'bcc':
                            $_bcc = $phpmailer::parseAddresses($content);

                            foreach ($_bcc as $bcc) {
                                $phpmailer->addBCC($bcc['address'], $bcc['name']);
                            }

                            break;
                    }
                }
            }

            $phpmailer->msgHTML($body);

            $phpmailer->preSend();

            $_mail = $phpmailer->getSentMIMEMessage();

            return $this->emailSpamTester->isSpam($_mail);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @param $component
     * @param bool $replace_tags
     *
     * @return array|mixed|string
     */
    private function get($component, $replace_tags = false)
    {
        $use_html = ($this->use_html && 'body' === $component);
        $exclude_blank = ($this->exclude_blank && 'body' === $component);

        $component = $this->template[$component] ?? '';

        if ($replace_tags) {
            $component = $this->replace_tags($component, [
                'html' => $use_html,
                'exclude_blank' => $exclude_blank,
            ]);

            if ($use_html && !preg_match('%<html[>\s].*</html>%is', $component)) {
                $component = $this->htmlize($component);
            }
        }

        return $component;
    }

    /**
     * @param string $body
     *
     * @return string
     */
    private function htmlize($body)
    {
        if ($this->locale) {
            $lang_atts = sprintf(' %s', wpcf7_format_atts([
                'dir' => wpcf7_is_rtl($this->locale) ? 'rtl' : 'ltr',
                'lang' => str_replace('_', '-', $this->locale),
            ]));
        } else {
            $lang_atts = '';
        }

        $header = apply_filters('wpcf7_mail_html_header', '<!doctype html>
<html xmlns="http://www.w3.org/1999/xhtml"'.$lang_atts.'>
<head>
<title>'.esc_html($this->get('subject', true)).'</title>
</head>
<body>
', $this);

        $footer = apply_filters('wpcf7_mail_html_footer', '</body>
</html>', $this);

        return $header.wpautop($body).$footer;
    }

    /**
     * @param $content
     * @param string|array $args
     *
     * @return array|string
     */
    private function replace_tags($content, $args = '')
    {
        if (true === $args) {
            $args = ['html' => true];
        }

        $args = wp_parse_args($args, [
            'html' => false,
            'exclude_blank' => false,
        ]);

        return wpcf7_mail_replace_tags($content, $args);
    }

}
