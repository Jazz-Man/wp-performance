<?php

namespace JazzMan\Performance\MenuCache;

use JazzMan\AutoloadInterface\AutoloadInterface;
use JazzMan\PerformanceStub\MenuItem;
use JazzMan\PerformanceStub\NavMenuArgs;
use WP_Term;

class NavMenuCache implements AutoloadInterface {
    public function load(): void {
        add_filter('wp_nav_menu_args', [__CLASS__, 'setMenuFallbackParams']);
        add_filter('pre_wp_nav_menu', [$this, 'buildWpNavMenu'], 10, 2);
    }

    /**
     * @param NavMenuArgs $args
     *
     * @return false|mixed|string
     */
    public function buildWpNavMenu(?string $output, $args) {
        $menu = wp_get_nav_menu_object($args->menu);

        if (false === $menu) {
            return $output;
        }

        $menuItems = MenuItems::getItems($menu);

        if (!empty($menuItems) && !is_admin()) {
            $menuItems = array_filter($menuItems, '_is_valid_nav_menu_item');
        }

        /** @var MenuItem[]|\stdClass[] $menuItems */
        $menuItems = apply_filters('wp_get_nav_menu_items', $menuItems, $menu, $args);

        if (empty($menuItems) && (!empty($args->fallback_cb) && \is_callable($args->fallback_cb))) {
            return \call_user_func($args->fallback_cb, (array) $args);
        }

        $menuCssClassses = new MenuItemClasses();

        $menuCssClassses->setMenuItemClassesByContext($menuItems);

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
        if ([] !== $menuWithChildren) {
            /** @var MenuItem $sortedMenuItem */
            foreach ($sortedMenuItems as &$sortedMenuItem) {
                if (isset($menuWithChildren[$sortedMenuItem->ID])) {
                    $classes = (array) $sortedMenuItem->classes;

                    $classes[] = 'menu-item-has-children';

                    $sortedMenuItem->classes = $classes;
                }
            }
        }

        unset($menuItems, $menuItem, $menuWithChildren);

        /** @var array<int,MenuItem> $sortedMenuItems */
        $sortedMenuItems = apply_filters('wp_nav_menu_objects', $sortedMenuItems, $args);

        $items = walk_nav_menu_tree($sortedMenuItems, $args->depth, $args);
        unset($sortedMenuItems);

        $wrapId = $this->getMenuWrapId($menu, $args);

        $wrapClass = $args->menu_class ?: '';

        $items = (string) apply_filters('wp_nav_menu_items', $items, $args);

        $items = (string) apply_filters(sprintf('wp_nav_menu_%s_items', $menu->slug), $items, $args);

        if (empty($items)) {
            return false;
        }

        $navMenu = sprintf(
            (string) $args->items_wrap,
            esc_attr($wrapId),
            esc_attr($wrapClass),
            $items
        );
        unset($items);

        $navMenu = $this->wrapToContainer($args, $menu, $navMenu);

        return (string) apply_filters('wp_nav_menu', $navMenu, $args);
    }

    /**
     * @param array<string,mixed> $args
     *
     * @psalm-return array<string, mixed>
     *
     * @return array<string, mixed>
     */
    public static function setMenuFallbackParams(array $args): array {
        $args['fallback_cb'] = '__return_empty_string';

        return $args;
    }

    /**
     * @param NavMenuArgs $args
     */
    private function getMenuWrapId(WP_Term $wpTerm, $args): string {
        /** @var string[] $menuIdSlugs */
        static $menuIdSlugs = [];

        // Attributes.
        if (!empty($args->menu_id)) {
            return $args->menu_id;
        }

        $wrapId = sprintf('menu-%s', $wpTerm->slug);

        while (\in_array($wrapId, $menuIdSlugs, true)) {
            $pattern = '#-(\d+)$#';
            preg_match($pattern, $wrapId, $matches);

            $wrapId = empty($matches) ? $wrapId.'-1' : (string) preg_replace($pattern, '-'.++$matches[1], $wrapId);
        }

        $menuIdSlugs[] = $wrapId;

        return $wrapId;
    }

    /**
     * @param NavMenuArgs $args
     */
    private function wrapToContainer($args, WP_Term $wpTerm, string $navMenu): string {
        /** @var string[] $allowedTags */
        $allowedTags = (array) apply_filters('wp_nav_menu_container_allowedtags', ['div', 'nav']);

        if (!empty($args->container) && \in_array($args->container, $allowedTags, true)) {
            /** @var array<string,string|string[]> $attributes */
            $attributes = [
                'class' => $args->container_class ?: sprintf('menu-%s-container', $wpTerm->slug),
            ];

            if ($args->container_id) {
                $attributes['id'] = $args->container_id;
            }

            if ('nav' === $args->container && !empty($args->container_aria_label)) {
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
}
