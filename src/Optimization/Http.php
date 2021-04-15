<?php

namespace JazzMan\Performance\Optimization;

use JazzMan\AutoloadInterface\AutoloadInterface;
use Symfony\Component\WebLink\GenericLinkProvider;
use Symfony\Component\WebLink\HttpHeaderSerializer;
use Symfony\Component\WebLink\Link;

/**
 * Class Http.
 */
class Http implements AutoloadInterface
{
    /**
     * @var Link[]
     */
    private $preloadLinks = [];

    public function load()
    {
        add_action('init', [$this, 'removeResourceHints']);

        add_action('template_redirect', [$this, 'preloadLinks']);
        add_action('wp_head', [$this, 'preloadLinksInHeader']);
    }

    public function removeResourceHints(): void
    {
        remove_action('wp_head', 'wp_resource_hints', 2);
    }

    public function preloadLinksInHeader(): void
    {
        if (!empty($this->preloadLinks)) {
            $provider = new GenericLinkProvider($this->preloadLinks);

            foreach ($provider->getLinks() as $link) {
                if (!$link->isTemplated()) {
                    $attributes = [
                        'rel' => $link->getRels(),
                        'href' => $link->getHref(),
                    ];

                    if (!empty($link->getAttributes())) {
                        foreach ($link->getAttributes() as $key => $value) {
                            $attributes[$key] = $value;
                        }
                    }

                    \printf('<link %s/>', app_add_attr_to_el($attributes));
                }
            }
        }
    }

    public function preloadLinks(): void
    {
        if (\headers_sent()) {
            return;
        }

        $this->preloadLinks = (array) apply_filters('app_preload_links', $this->preloadLinks);

        if (!empty($this->preloadLinks)) {
            $link_provider = new GenericLinkProvider($this->preloadLinks);

            $header = (new HttpHeaderSerializer())->serialize($link_provider->getLinks());

            if (!empty($header)) {
                \header(\sprintf('Link: %s', $header), false);
            }
        }

        // @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/X-DNS-Prefetch-Control
        \header('X-DNS-Prefetch-Control: on');
    }

    public static function preloadLink(string $href, string $as, string $rel = Link::REL_PRELOAD): Link
    {
        return (new Link($rel, self::makeLinkRelative($href)))
            ->withAttribute('as', $as)
            ->withAttribute('importance', 'high')
        ;
    }

    public static function prefetchLink(string $href): Link
    {
        return (new Link(Link::REL_PREFETCH, self::makeLinkRelative($href)))
            ->withAttribute('as', 'fetch')
        ;
    }

    public static function preloadFont(string $href, string $type): Link
    {
        return (new Link(Link::REL_PRELOAD, self::makeLinkRelative($href)))
            ->withAttribute('as', 'font')
            ->withAttribute('type', $type)
            ->withAttribute('importance', 'high')
            ->withAttribute('crossorigin', true)
        ;
    }

    public static function dnsPrefetchLink(string $href): Link
    {
        return new Link(Link::REL_DNS_PREFETCH, self::makeLinkRelative($href));
    }

    public static function preconnectLink(string $href): Link
    {
        return new Link(Link::REL_PRECONNECT, self::makeLinkRelative($href));
    }

    private static function makeLinkRelative(string $href): string
    {
        if (app_is_current_host($href)) {
            $href = wp_make_link_relative($href);
        }

        return $href;
    }

}
