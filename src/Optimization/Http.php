<?php

namespace JazzMan\Performance\Optimization;

use JazzMan\AutoloadInterface\AutoloadInterface;
use Psr\Link\LinkInterface;
use Symfony\Component\WebLink\GenericLinkProvider;
use Symfony\Component\WebLink\HttpHeaderSerializer;
use Symfony\Component\WebLink\Link;
use Traversable;

/**
 * Class Http.
 */
class Http implements AutoloadInterface {
    /**
     * @var Link[]
     */
    private array $preloadLinks = [];

    public function load(): void {
        add_action('init', function (): void {
            $this->removeResourceHints();
        });

        add_action('template_redirect', function (): void {
            $this->preloadLinks();
        });
        add_action('wp_head', function (): void {
            $this->preloadLinksInHeader();
        });
    }

    public function removeResourceHints(): void {
        remove_action('wp_head', 'wp_resource_hints', 2);
    }

    public function preloadLinksInHeader(): void {
        if (!empty($this->preloadLinks)) {
            $genericLinkProvider = new GenericLinkProvider($this->preloadLinks);

            /** @var Link[] $links */
            $links = $genericLinkProvider->getLinks();

            foreach ($links as $link) {
                if (!$link->isTemplated()) {
                    /** @var array<string,string|string[]> $attributes */
                    $attributes = [
                        'rel' => $link->getRels(),
                        'href' => $link->getHref(),
                    ];

                    /** @var array<string,string|string[]> $linkAttributes */
                    $linkAttributes = $link->getAttributes();

                    if (!empty($linkAttributes)) {
                        foreach ($linkAttributes as $key => $value) {
                            $attributes[$key] = $value;
                        }
                    }

                    printf('<link %s/>', app_add_attr_to_el($attributes));
                }
            }
        }
    }

    public function preloadLinks(): void {
        if (headers_sent()) {
            return;
        }

        $this->preloadLinks = apply_filters('app_preload_links', $this->preloadLinks);

        if (!empty($this->preloadLinks)) {
            /** @var LinkInterface[]|Traversable $links */
            $links = (new GenericLinkProvider($this->preloadLinks))->getLinks();

            $header = (new HttpHeaderSerializer())->serialize($links);

            if (!empty($header)) {
                header(sprintf('Link: %s', $header), false);
            }
        }

        // @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/X-DNS-Prefetch-Control
        header('X-DNS-Prefetch-Control: on');
    }

    public static function preloadLink(string $href, string $asAttribute, string $relAttribute = Link::REL_PRELOAD): Link {
        $link = new Link($relAttribute, app_make_link_relative($href));
        $link->withAttribute('as', $asAttribute);
        $link->withAttribute('importance', 'high');

        return $link;
    }

    public static function prefetchLink(string $href): Link {
        $link = new Link(Link::REL_PREFETCH, app_make_link_relative($href));
        $link->withAttribute('as', 'fetch');

        return $link;
    }

    public static function preloadFont(string $href, string $type): Link {
        $link = new Link(Link::REL_PRELOAD, app_make_link_relative($href));
        $link->withAttribute('as', 'font');
        $link->withAttribute('type', $type);
        $link->withAttribute('importance', 'high');
        $link->withAttribute('crossorigin', true);

        return $link;
    }

    public static function dnsPrefetchLink(string $href): Link {
        return new Link(Link::REL_DNS_PREFETCH, app_make_link_relative($href));
    }

    public static function preconnectLink(string $href): Link {
        return new Link(Link::REL_PRECONNECT, app_make_link_relative($href));
    }
}
