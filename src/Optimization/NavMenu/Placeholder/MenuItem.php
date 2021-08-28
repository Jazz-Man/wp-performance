<?php

namespace JazzMan\Performance\Optimization\NavMenu\Placeholder;

use stdClass;

/**
 * Class MenuItem.
 *
 * @SuppressWarnings(PHPMD)
 */
class MenuItem extends stdClass {
    /**
     * The term_id if the menu item represents a taxonomy term.
     *
     * @overrides WP_Post
     *
     * @var int
     */
    public int $ID = 0;

    /**
     * The title attribute of the link element for this menu item.
     */
    public ?string $attr_title = null;

    /**
     * The array of class attribute values for the link element of this menu item.
     *
     * @var string|string[]
     */
    public $classes;

    /**
     * The DB ID of this item as a nav_menu_item object, if it exists (0 if it doesn't exist).
     *
     * @var int|string
     */
    public $db_id = 0;

    /**
     * The description of this menu item.
     */
    public ?string $description = null;

    /**
     * The DB ID of the nav_menu_item that is this item's menu parent, if any. 0 otherwise.
     */
    public int $menu_item_parent = 0;

    /**
     * The type of object originally represented, such as "category," "post", or "attachment.".
     *
     * @var string
     */
    public string $object;

    public ?string $taxonomy = null;

    /**
     * @var string|int
     */
    public $parent;

    /**
     * The DB ID of the original object this menu item represents,
     * e.g. ID for posts and term_id for categories.
     *
     * @var int|string
     */
    public $object_id = 0;

    /**
     * The DB ID of the original object's parent object, if any (0 otherwise).
     *
     * @overrides WP_Post
     */
    public int $post_parent = 0;

    /**
     * A "no title" label if menu item represents a post that lacks a title.
     *
     * @overrides WP_Post
     */
    public ?string $post_title = null;

    /**
     * The target attribute of the link element for this menu item.
     */
    public ?string $target = null;

    /**
     * The title of this menu item.
     */
    public ?string $title = null;

    /**
     * The family of objects originally represented, such as "post_type" or "taxonomy.".
     */
    public ?string $type = null;

    /**
     * The singular label used to describe this type of menu item.
     */
    public ?string $type_label = null;

    /**
     * The URL to which this menu item points.
     */
    public ?string $url = null;

    /**
     * The XFN relationship expressed in the link of this menu item.
     */
    public ?string $xfn = null;

    /**
     * Whether the menu item represents an object that no longer exists.
     */
    public bool $_invalid = false; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

    /**
     * Whether the menu item represents the active menu item.
     */
    public bool $current = false;

    /**
     * Whether the menu item represents an parent menu item.
     */
    public bool $current_item_parent = false;

    /**
     * Whether the menu item represents an ancestor menu item.
     */
    public bool $current_item_ancestor = false;

    /* Copy of WP_Post */

    /**
     * ID of post author.
     *
     * A numeric string, for compatibility reasons.
     *
     * @var string|int|null
     */
    public $post_author = 0;

    /**
     * The post's local publication time.
     */
    public string $post_date = '0000-00-00 00:00:00';

    /**
     * The post's GMT publication time.
     */
    public string $post_date_gmt = '0000-00-00 00:00:00';

    /**
     * The post's content.
     */
    public string $post_content = '';

    /**
     * The post's excerpt.
     */
    public string $post_excerpt = '';

    /**
     * The post's status.
     */
    public string $post_status = 'publish';

    /**
     * Whether comments are allowed.
     */
    public string $comment_status = 'open';

    /**
     * Whether pings are allowed.
     */
    public string $ping_status = 'open';

    /**
     * The post's password in plain text.
     */
    public string $post_password = '';

    /**
     * The post's slug.
     */
    public string $post_name = '';

    /**
     * URLs queued to be pinged.
     */
    public string $to_ping = '';

    /**
     * URLs that have been pinged.
     */
    public string $pinged = '';

    /**
     * The post's local modified time.
     */
    public string $post_modified = '0000-00-00 00:00:00';

    /**
     * The post's GMT modified time.
     */
    public string $post_modified_gmt = '0000-00-00 00:00:00';

    /**
     * A utility DB field for post content.
     */
    public string $post_content_filtered = '';

    /**
     * The unique identifier for a post, not necessarily a URL, used as the feed GUID.
     */
    public string $guid = '';

    /**
     * A field used for ordering posts.
     *
     * @var int|null
     */
    public ?int $menu_order = 0;

    /**
     * The post's type, like post or page.
     */
    public ?string $post_type = 'post';

    /**
     * An attachment's mime type.
     */
    public string $post_mime_type = '';

    /**
     * Cached comment count.
     *
     * A numeric string, for compatibility reasons.
     *
     * @var int|null
     */
    public ?int $comment_count = null;

    /**
     * Stores the post object's sanitization level.
     *
     * Does not correspond to a DB field.
     */
    public ?string $filter = null;
}
