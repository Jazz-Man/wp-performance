<?php

namespace JazzMan\Performance\Optimization\NavMenu;

use JazzMan\AutoloadInterface\AutoloadInterface;
use stdClass;
use WP_Term;

class NavMenuCache implements AutoloadInterface {
    /**
     * @return void
     */
    public function load() {
        add_filter('wp_nav_menu_args', [$this, 'setMenuFallbackParams']);
        add_filter('pre_wp_nav_menu', [$this, 'buildWpNavMenu'], 10, 2);
    }

    /**
     * @param mixed $output
     *
     * @return string|false
     */
    public function buildWpNavMenu($output, stdClass $args) {
        $menu = wp_get_nav_menu_object($args->menu);

        if (empty($menu)) {
            return $output;
        }

        $menuItems = MenuItems::getItems($menu);

        if (is_array($menuItems) && ! is_admin()) {
            $menuItems = array_filter($menuItems, '_is_valid_nav_menu_item');
        }

        if ((empty($menuItems) && ! $args->theme_location) && isset($args->fallback_cb) && $args->fallback_cb && is_callable($args->fallback_cb)) {
            return call_user_func($args->fallback_cb, (array) $args);
        }

	    MenuItemClasses::setMenuItemClassesByContext($menuItems);

        $sortedMenuItems = [];
        $menuWithChildren = [];

        foreach ($menuItems as $menuItem) {
            $sortedMenuItems[$menuItem->menu_order] = $menuItem;

            if ($menuItem->menu_item_parent) {
                $menuWithChildren[$menuItem->menu_item_parent] = true;
            }
        }

        // Add the menu-item-has-children class where applicable.
        if ($menuWithChildren) {
            foreach ($sortedMenuItems as &$menuItem) {
                if (isset($menuWithChildren[$menuItem->ID])) {
                    $menuItem->classes[] = 'menu-item-has-children';
                }
            }
        }

        unset($menuItems, $menuItem, $menuWithChildren);

        $sortedMenuItems = apply_filters('wp_nav_menu_objects', $sortedMenuItems, $args);

        $items = walk_nav_menu_tree($sortedMenuItems, $args->depth, $args);
        unset($sortedMenuItems);

        $wrapId = $this->getMenuWrapId($menu, $args);

        $wrapClass = $args->menu_class ?: '';

        $items = apply_filters('wp_nav_menu_items', $items, $args);

        $items = apply_filters("wp_nav_menu_{$menu->slug}_items", $items, $args);

        if (empty($items)) {
            return false;
        }

        $navMenu = sprintf($args->items_wrap, esc_attr($wrapId), esc_attr($wrapClass), $items);
        unset($items);

        $navMenu = $this->wrapToContainer($args, $menu, $navMenu);

        return apply_filters('wp_nav_menu', $navMenu, $args);
    }

	/**
	 * @param  \WP_Term  $menu
	 * @param  \stdClass  $args
	 *
	 * @return string
	 */
	private function getMenuWrapId(WP_Term $menu, stdClass $args): string {
        static $menuIdSlugs = [];

        // Attributes.
        if ( ! empty($args->menu_id)) {
            return (string) $args->menu_id;
        }

        $wrapId = "menu-$menu->slug";

        while (in_array($wrapId, $menuIdSlugs, true)) {
            $pattern = '#-(\d+)$#';
            preg_match($pattern, $wrapId, $matches);

            $wrapId = ! empty($matches) ? (string) preg_replace($pattern, '-' . ++$matches[1], $wrapId) : $wrapId . '-1';
        }

        $menuIdSlugs[] = $wrapId;

        return $wrapId;
    }

	/**
	 * @param  \stdClass  $args
	 * @param  \WP_Term  $menu
	 * @param  string  $navMenu
	 *
	 * @return string
	 */
    private function wrapToContainer(stdClass $args, WP_Term $menu, string $navMenu): string {
        $allowedTags = apply_filters('wp_nav_menu_container_allowedtags', ['div', 'nav']);

        if (($args->container && is_string($args->container)) && in_array($args->container, $allowedTags, true)) {
            $attributes = [
                'class' => $args->container_class ?: sprintf('menu-%s-container', $menu->slug),
            ];

            if ($args->container_id) {
                $attributes['id'] = $args->container_id;
            }

            if ('nav' === $args->container && ! empty($args->container_aria_label)) {
                $attributes['aria-label'] = $args->container_aria_label;
            }

            return sprintf(
                '<%1$s %2$s>%3$s</%1$s>',
                $args->container,
                app_add_attr_to_el($attributes),
                $navMenu
            );
        }

        return $navMenu;
    }

    /**
     * @param array<string,mixed> $args
     *
     * @return array
     *
     * @psalm-return array<string, mixed>
     */
    public function setMenuFallbackParams(array $args): array {
        $args['fallback_cb'] = '__return_empty_string';

        return $args;
    }
}
