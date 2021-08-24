<?php

namespace JazzMan\Performance\Optimization\NavMenu;

use JazzMan\AutoloadInterface\AutoloadInterface;
use JazzMan\Performance\Optimization\NavMenu\Placeholder\MenuItem;
use JazzMan\Performance\Optimization\NavMenu\Placeholder\NavMenuArgs;
use stdClass;
use WP_Term;

class NavMenuCache implements AutoloadInterface {
    public function load(): void {
        add_filter('wp_nav_menu_args', fn (array $args): array => $this->setMenuFallbackParams($args));
        add_filter('pre_wp_nav_menu', fn (?string $output, stdClass $args) => $this->buildWpNavMenu($output, $args), 10, 2);
    }

    /**
     * @param NavMenuArgs|stdClass $args
     *
     * @return false|mixed|string
     */
    public function buildWpNavMenu(?string $output, stdClass $args) {
        $menu = wp_get_nav_menu_object($args->menu);

        if ($menu === false) {
            return $output;
        }

        $menuItems = MenuItems::getItems($menu);

        if (is_array($menuItems) && ! is_admin()) {
            $menuItems = array_filter($menuItems, '_is_valid_nav_menu_item');
        }

        if ((empty($menuItems) && ! $args->theme_location) && (property_exists($args, 'fallback_cb') && $args->fallback_cb !== null) && $args->fallback_cb && is_callable($args->fallback_cb)) {
            return call_user_func($args->fallback_cb, (array) $args);
        }

        MenuItemClasses::setMenuItemClassesByContext($menuItems);

        /** @var array<int,MenuItem> $sortedMenuItems */
        $sortedMenuItems = [];
        /** @var array<int,boolean> $menuWithChildren */
        $menuWithChildren = [];

        foreach ($menuItems as $menuItem) {
            $sortedMenuItems[(int) $menuItem->menu_order] = $menuItem;

            if ($menuItem->menu_item_parent) {
                $menuWithChildren[(int) $menuItem->menu_item_parent] = true;
            }
        }

        // Add the menu-item-has-children class where applicable.
        if ($menuWithChildren !== []) {
            foreach ($sortedMenuItems as &$menuItem) {
                if (isset($menuWithChildren[$menuItem->ID])) {
                    $menuItem->classes[] = 'menu-item-has-children';
                }
            }
        }

        unset($menuItems, $menuItem, $menuWithChildren);

        /** @var array<int,MenuItem> $sortedMenuItems */
        $sortedMenuItems = apply_filters('wp_nav_menu_objects', $sortedMenuItems, $args);

        $items = walk_nav_menu_tree($sortedMenuItems, $args->depth, $args);
        unset($sortedMenuItems);

        $wrapId = $this->getMenuWrapId($menu, $args);

        $wrapClass = (string) $args->menu_class ?: '';

        $items = (string) apply_filters('wp_nav_menu_items', $items, $args);

        $items = (string) apply_filters("wp_nav_menu_{$menu->slug}_items", $items, $args);

        if (empty($items)) {
            return false;
        }

        $navMenu = sprintf(
            $args->items_wrap,
            esc_attr($wrapId),
            esc_attr($wrapClass),
            $items
        );
        unset($items);

        $navMenu = $this->wrapToContainer($args, $menu, $navMenu);

        return (string) apply_filters('wp_nav_menu', $navMenu, $args);
    }

    /**
     * @param NavMenuArgs|stdClass $args
     */
    private function getMenuWrapId(WP_Term $menu, stdClass $args): string {
        /** @var string[] $menuIdSlugs */
        static $menuIdSlugs = [];

        // Attributes.
        if ( ! empty($args->menu_id)) {
            return (string) $args->menu_id;
        }

        $wrapId = "menu-$menu->slug";

        while (in_array($wrapId, $menuIdSlugs, true)) {
            $pattern = '#-(\d+)$#';
            preg_match($pattern, $wrapId, $matches);

            $wrapId = empty($matches) ? $wrapId . '-1' : (string) preg_replace($pattern, '-' . ++$matches[1], $wrapId);
        }

        $menuIdSlugs[] = $wrapId;

        return $wrapId;
    }

    /**
     * @param NavMenuArgs|stdClass $args
     */
    private function wrapToContainer(stdClass $args, WP_Term $menu, string $navMenu): string {
        /** @var string[] $allowedTags */
        $allowedTags = (array) apply_filters('wp_nav_menu_container_allowedtags', ['div', 'nav']);

        if (($args->container && is_string($args->container)) && in_array($args->container, $allowedTags, true)) {
            /** @var array<string,string|string[]> $attributes */
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
     * @psalm-return array<string, mixed>
     * @return array<string, mixed>
     */
    public function setMenuFallbackParams(array $args): array {
        $args['fallback_cb'] = '__return_empty_string';

        return $args;
    }
}
