<?php

namespace JazzMan\Performance\Optimization;

use JazzMan\AutoloadInterface\AutoloadInterface;
use Symfony\Component\WebLink\GenericLinkProvider;
use Symfony\Component\WebLink\HttpHeaderSerializer;
use Symfony\Component\WebLink\Link;

class Http implements AutoloadInterface
{
    public function load()
    {
        add_action('init', [$this, 'removeActions']);

        add_action('template_redirect', [$this, 'preloadLinks']);
    }

    public function removeActions()
    {
        remove_action('wp_head', 'wp_resource_hints', 2);
        remove_action('wp_head', 'rest_output_link_wp_head', 10);
        remove_action('template_redirect', 'wp_shortlink_header', 11);
        remove_action('template_redirect', 'rest_output_link_header', 11);
    }

    public function preloadLinks()
    {
        if (\headers_sent()) {
            return;
        }

        $links = (array) apply_filters('app_preload_links', []);

        if (! empty($links)) {
            $link_provider = new GenericLinkProvider($links);

            $header = (new HttpHeaderSerializer())->serialize($link_provider->getLinks());

            if (! empty($header)) {
                \header(\sprintf('Link: %s', $header), false);
            }
        }

        /**
         * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/X-DNS-Prefetch-Control
         */
        \header('X-DNS-Prefetch-Control: on');
    }

    /**
     * @param string $href
     * @param string $as
     * @param string $rel
     * @return Link
     */
    public static function getPreloadLink(string $href, string $as, string $rel = Link::REL_PRELOAD)
    {
        return (new Link($rel, $href))
            ->withAttribute('as', $as)
            ->withAttribute('importance', 'high');
    }

    /**
     * @param string $href
     * @return Link
     */
    public static function getPrefetchLink(string $href)
    {
        return (new Link(Link::REL_PREFETCH, $href))
            ->withAttribute('as', 'fetch');
    }

    /**
     * @param string $href
     * @param string $type
     * @return Link
     */
    public static function getPreloadFont(string $href, string $type)
    {
        return (new Link(Link::REL_PRELOAD, $href))
            ->withAttribute('as', 'font')
            ->withAttribute('type', $type)
            ->withAttribute('importance', 'high')
            ->withAttribute('crossorigin', true);
    }

    /**
     * @param string $href
     * @return Link
     */
    public static function getDnsPrefetchLink(string $href)
    {
        return new Link(Link::REL_DNS_PREFETCH, $href);
    }

    /**
     * @param string $href
     * @return Link
     */
    public static function getPreconnectLink(string $href)
    {
        return new Link(Link::REL_PRECONNECT, $href);
    }
}
