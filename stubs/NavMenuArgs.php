<?php

namespace JazzMan\PerformanceStub;

use stdClass;
use Walker_Nav_Menu;
use WP_Term;

/**
 * Class NavMenuArgs.
 *
 * @SuppressWarnings(PHPMD)
 */
class NavMenuArgs extends stdClass {
    /**
     * Desired menu. Accepts a menu ID, slug, name, or object. Default empty.
     *
     * @var int|string|WP_Term
     * @phpstan-ignore-next-line
     */
    public $menu = 0;

    /**
     * CSS class to use for the ul element which forms the menu. Default 'menu'.
     */
    public string $menu_class = 'menu';

    /**
     * The ID that is applied to the ul element which forms the menu.
     * Default is the menu slug, incremented.
     */
    public ?string $menu_id = null;

    /**
     * Whether to wrap the ul, and what to wrap it with. Default 'div'.
     */
    public string $container = 'div';

    /**
     * Class that is applied to the container. Default 'menu-{menu slug}-container'.
     */
    public ?string $container_class = null;

    /**
     * The ID that is applied to the container. Default empty.
     */
    public ?string $container_id = null;

    /**
     * The aria-label attribute that is applied to the container when it's a nav element.
     */
    public ?string $container_aria_label = null;

    /**
     * If the menu doesn't exists, a callback function will fire.
     * Default is 'wp_page_menu'. Set to false for no fallback.
     *
     * @var bool|string|callable|null
     */
    public $fallback_cb = '__return_empty_string';

    /**
     * Text before the link markup. Default empty.
     */
    public ?string $before = null;

    /**
     * Text after the link markup. Default empty.
     */
    public ?string $after = null;

    /**
     * Text before the link text. Default empty.
     */
    public ?string $link_before = null;

    /**
     * Text after the link text. Default empty.
     */
    public ?string $link_after = null;

    /**
     * Whether to echo the menu or return it. Default true.
     */
    public bool $echo = true;

    /**
     * How many levels of the hierarchy are to be included. 0 means all.
     * Default 0.
     */
    public int $depth = 0;

    /**
     * Instance of a custom walker class. Default empty.
     *
     * @phpstan-ignore-next-line
     */
    public ?Walker_Nav_Menu $walker = null;

    /**
     * Theme location to be used. Must be registered with register_nav_menu()
     * in order to be selectable by the user.
     */
    public ?string $theme_location = null;

    /**
     * How the list items should be wrapped. Default is a ul with an id and class.
     * Uses printf() format with numbered placeholders.
     */
    public ?string $items_wrap = null;

    /**
     * Whether to preserve whitespace within the menu's HTML. Accepts 'preserve' or 'discard'.
     * Default 'preserve'.
     */
    public string $item_spacing = 'preserve';
}
