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
    private $preload_links = [];

    public function load()
    {
        add_action('init', [$this, 'remove_actions']);

        add_action('template_redirect', [$this, 'preload_links']);
        add_action('wp_head', [$this, 'preload_links_in_header']);
    }

    public function remove_actions()
    {
        remove_action('wp_head', 'wp_resource_hints', 2);
    }

    public function preload_links_in_header()
    {
        if (! empty($this->preload_links)) {
            $link_provider = new GenericLinkProvider($this->preload_links);

            foreach ($link_provider->getLinks() as $link) {
                if (! $link->isTemplated()) {
                    $attributes = [
                        'rel' => $link->getRels(),
                        'href' => $link->getHref(),
                    ];

                    if (! empty($link->getAttributes())) {
                        foreach ($link->getAttributes() as $key => $value) {
                            $attributes[$key] = $value;
                        }
                    }

                    \printf('<link %s/>', self::build_inline_link_attributes($attributes));
                }
            }
        }
    }

    public function preload_links()
    {
        if (\headers_sent()) {
            return;
        }

        $this->preload_links = (array) apply_filters('app_preload_links', $this->preload_links);

        if (! empty($this->preload_links)) {
            $link_provider = new GenericLinkProvider($this->preload_links);

            $header = (new HttpHeaderSerializer())->serialize($link_provider->getLinks());

            if (! empty($header)) {
                \header(\sprintf('Link: %s', $header), false);
            }
        }

        /*
         * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/X-DNS-Prefetch-Control
         */
        \header('X-DNS-Prefetch-Control: on');
    }

    public static function get_preload_link(string $href, string $as, string $rel = Link::REL_PRELOAD): Link
    {
        return (new Link($rel, self::make_link_relative($href)))
            ->withAttribute('as', $as)
            ->withAttribute('importance', 'high');
    }

    public static function get_prefetch_link(string $href): Link
    {
        return (new Link(Link::REL_PREFETCH, self::make_link_relative($href)))
            ->withAttribute('as', 'fetch');
    }

    public static function get_preload_font(string $href, string $type): Link
    {
        return (new Link(Link::REL_PRELOAD, self::make_link_relative($href)))
            ->withAttribute('as', 'font')
            ->withAttribute('type', $type)
            ->withAttribute('importance', 'high')
            ->withAttribute('crossorigin', true);
    }

    public static function get_dns_prefetch_link(string $href): Link
    {
        return new Link(Link::REL_DNS_PREFETCH, self::make_link_relative($href));
    }

    public static function get_preconnect_link(string $href): Link
    {
        return new Link(Link::REL_PRECONNECT, self::make_link_relative($href));
    }

    /**
     * @param $href
     *
     * @return mixed|string
     */
    public static function make_link_relative($href)
    {
        if (self::is_current_host($href)) {
            $href = wp_make_link_relative($href);
        }

        return $href;
    }

    /**
     * @param mixed $url
     *
     * @return bool
     */
    public static function is_current_host($url): bool
    {
        if (! \filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $current_host = (string) $_SERVER['HTTP_HOST'];
        $url_host = \parse_url($url, PHP_URL_HOST);

        return ! empty($url_host) && $current_host === $url_host;
    }

    public static function build_inline_link_attributes(array $attr): string
    {
        $attributes = [];
        foreach ($attr as $key => $value) {
            if (\is_array($value)) {
                $value = \implode(' ', \array_filter($value));
            }

            if (! \is_bool($value)) {
                $attributes[] = \sprintf('%s="%s"', esc_attr($key), esc_attr($value));
            }

            if (true === $value) {
                $attributes[] = esc_attr($key);
            }
        }

        return ' '.\implode(' ', $attributes);
    }
}
