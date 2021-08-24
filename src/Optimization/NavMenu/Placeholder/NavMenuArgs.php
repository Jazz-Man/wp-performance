<?php

namespace JazzMan\Performance\Optimization\NavMenu\Placeholder;

class NavMenuArgs {
	/**
	 * Desired menu. Accepts a menu ID, slug, name, or object. Default empty.
	 *
	 * @var int|string|\WP_Term
	 */
	public $menu;

	/**
	 * CSS class to use for the ul element which forms the menu. Default 'menu'.
	 *
	 * @var string
	 */
	public $menu_class;

	/**
	 * The ID that is applied to the ul element which forms the menu.
	 * Default is the menu slug, incremented.
	 *
	 * @var string|null
	 */
	public ?string $menu_id;

	/**
	 * Whether to wrap the ul, and what to wrap it with. Default 'div'.
	 *
	 * @var string
	 */
	public string $container;

	/**
	 * Class that is applied to the container. Default 'menu-{menu slug}-container'.
	 *
	 * @var string|null
	 */
	public ?string $container_class;

	/**
	 * The ID that is applied to the container. Default empty.
	 *
	 * @var string|null
	 */
	public ?string $container_id;

	/**
	 * The aria-label attribute that is applied to the container when it's a nav element.
	 *
	 * @var string|null
	 */
	public ?string $container_aria_label;

	/**
	 * If the menu doesn't exists, a callback function will fire.
	 * Default is 'wp_page_menu'. Set to false for no fallback.
	 *
	 * @var callable|bool
	 */
	public $fallback_cb;

	/**
	 * Text before the link markup. Default empty.
	 *
	 * @var string|null
	 */
	public ?string $before;

	/**
	 * Text after the link markup. Default empty.
	 *
	 * @var string|null
	 */
	public ?string $after;

	/**
	 * Text before the link text. Default empty.
	 *
	 * @var string|null
	 */
	public ?string $link_before;

	/**
	 * Text after the link text. Default empty.
	 *
	 * @var string|null
	 */
	public ?string $link_after;

	/**
	 * Whether to echo the menu or return it. Default true.
	 *
	 * @var bool
	 */
	public bool $echo;

	/**
	 * How many levels of the hierarchy are to be included. 0 means all.
	 * Default 0.
	 *
	 * @var int
	 */
	public int $depth;

	/**
	 * Instance of a custom walker class. Default empty.
	 *
	 * @var \Walker_Nav_Menu|null
	 */
	public $walker;

	/**
	 * Theme location to be used. Must be registered with register_nav_menu()
	 * in order to be selectable by the user.
	 *
	 * @var string|null
	 */
	public ?string $theme_location;

	/**
	 * How the list items should be wrapped. Default is a ul with an id and class.
	 * Uses printf() format with numbered placeholders.
	 *
	 * @var string
	 */
	public string $items_wrap;

	/**
	 * Whether to preserve whitespace within the menu's HTML. Accepts 'preserve' or 'discard'.
	 * Default 'preserve'.
	 *
	 * @var string
	 */
	public string $item_spacing;
}