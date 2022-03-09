<?php

use JazzMan\Performance\Utils\AttachmentData;
use JazzMan\Performance\Utils\Cache;

if ( ! function_exists('app_get_image_data_array')) {
    /**
     * @param int[]|string $size
     *
     * @return array|false
     *
     * @psalm-return array{size: array<int>|string, url: string, width: numeric, height: numeric, alt?: null|string, id?: int, srcset?: string, sizes?: string}|false
     */
    function app_get_image_data_array(int $attachmentId, $size = 'large') {
        $imageData = [
            'size' => $size,
        ];

        if (is_array($size)) {
            $image = wp_get_attachment_image_src($attachmentId, $size);

            if ( ! empty($image)) {
                list($url, $width, $height) = $image;

                /** @var string $alt */
                $alt = get_post_meta($attachmentId, '_wp_attachment_image_alt', true);

                $imageData['url'] = $url;
                $imageData['width'] = $width;
                $imageData['height'] = $height;

                if (!empty($alt)) {
                    $imageData['alt'] = $alt;
                }

                return $imageData;
            }
        }

        try {
            $attachment = new AttachmentData($attachmentId);

            $image = $attachment->getUrl((string) $size);

            $imageData['id'] = $attachmentId;
            $imageData['url'] = $image['src'];
            $imageData['width'] = $image['width'];
            $imageData['height'] = $image['height'];
            $imageData['alt'] = $attachment->getImageAlt();
            $imageData['srcset'] = $image['srcset'];
            $imageData['sizes'] = $image['sizes'];

            return $imageData;
        } catch (Exception $exception) {
            app_error_log($exception, 'app_get_image_data_array');

            return false;
        }
    }
}

if ( ! function_exists('app_get_attachment_image_url')) {
    /**
     * @return false|string
     */
    function app_get_attachment_image_url(int $attachmentId, string $size = AttachmentData::SIZE_THUMBNAIL) {
        try {
            $image = app_get_image_data_array($attachmentId, $size);

            if (!is_array($image)) {
                return false;
            }

            if (empty($image['url'])) {
                return false;
            }

            return $image['url'];
        } catch (Exception $exception) {
            return false;
        }
    }
}

if ( ! function_exists('app_get_attachment_image')) {
    /**
     * @param array<string,mixed>|string $attributes
     */
    function app_get_attachment_image(int $attachmentId, string $size = AttachmentData::SIZE_THUMBNAIL, $attributes = ''): string {
        /** @var array<string,mixed> $image */
        $image = app_get_image_data_array($attachmentId, $size);

        if (empty($image)) {
            $exception = new Exception(sprintf('Image not fount: attachment_id "%d", size "%s"', $attachmentId, $size));
            app_error_log($exception, 'app_get_attachment_image');

            return '';
        }

        try {
            $lazyLoading = function_exists('wp_lazy_loading_enabled') && wp_lazy_loading_enabled( 'img', 'wp_get_attachment_image' );

            $defaultAttributes = [
                'src' => $image['url'],
                'class' => sprintf('attachment-%1$s size-%1$s', $size),
                'alt' => app_trim_string(strip_tags((string) $image['alt'])),
                'width' => empty($image['width']) ? false : (int) $image['width'],
                'height' => empty($image['height']) ? false : (int) $image['height'],
                'loading' => $lazyLoading ? 'lazy' : false,
                'srcset' => empty($attributes['srcset']) ? (empty($image['srcset']) ? false : $image['srcset']) : ($attributes['srcset']),
                'sizes' => empty($attributes['sizes']) ? (empty($image['sizes']) ? false : $image['sizes']) : ($attributes['sizes']),
            ];

            $attributes = wp_parse_args($attributes, $defaultAttributes);

            // If the default value of `lazy` for the `loading` attribute is overridden
            // to omit the attribute for this image, ensure it is not included.
            if (array_key_exists('loading', $attributes) && ! $attributes['loading']) {
                unset($attributes['loading']);
            }

            $post = get_post($attachmentId);

            $attributes = apply_filters('wp_get_attachment_image_attributes', $attributes, $post, $size);

            return sprintf('<img %s/>', app_add_attr_to_el($attributes));
        } catch (Exception $exception) {
            app_error_log($exception, __FUNCTION__);

            return '';
        }
    }
}

if ( ! function_exists('app_get_term_link')) {
    /**
     * @return false|string
     */
    function app_get_term_link(int $termId, string $termTaxonomy) {
        global $wp_rewrite;

        $term = get_term($termId, $termTaxonomy);

        if ( ! ($term instanceof WP_Term)) {
            return false;
        }

        $taxonomy = get_taxonomy($term->taxonomy);

        if ( ! ($taxonomy instanceof WP_Taxonomy)) {
            return false;
        }

        $termlink = $wp_rewrite->get_extra_permastruct($term->taxonomy);

        /** @var string|false $termlink */
        $termlink = apply_filters('pre_term_link', $termlink, $term);

        $termlinkSlug = $term->slug;

        if (empty($termlink)) {
            switch (true) {
                case 'category' === $term->taxonomy:
                    $termlink = sprintf('?cat=%s', $term->term_id);

                    break;

                case ! empty($taxonomy->query_var):
                    $termlink = sprintf('?%s=%s', $taxonomy->query_var, $term->slug);

                    break;

                default:
                    $termlink = sprintf('?taxonomy=%s&term=%s', $term->taxonomy, $term->slug);

                    break;
            }

            return app_term_link_filter($term, home_url($termlink));
        }

        if ( ! empty($taxonomy->rewrite) && $taxonomy->rewrite['hierarchical']) {
            /** @var string[] $hierarchicalSlugs */
            $hierarchicalSlugs = [];

            if ($term->parent) {
                $ancestorsKey = sprintf('taxonomy_ancestors_%d_%s', $term->term_id, $term->taxonomy );
                /** @var string[]|false $hierarchicalSlugs */
                $hierarchicalSlugs = wp_cache_get($ancestorsKey, Cache::CACHE_GROUP);

                if (empty($hierarchicalSlugs)) {
                    /** @var string[] $result */
                    $result = [];

                    /** @var \stdClass[]|false $ancestors */
                    $ancestors = app_get_taxonomy_ancestors($term->term_id, $term->taxonomy, PDO::FETCH_CLASS);

                    if ( ! empty($ancestors)) {
                        foreach ($ancestors as $ancestor) {
                            $result[] = (string) $ancestor->term_slug;
                        }
                    }

                    $hierarchicalSlugs = $result;

                    wp_cache_set($ancestorsKey, $result, Cache::CACHE_GROUP);
                }
                $hierarchicalSlugs = array_reverse($hierarchicalSlugs);
            }

            $hierarchicalSlugs[] = $term->slug;

            $termlinkSlug = implode('/', $hierarchicalSlugs);
        }

        $termlink = str_replace(sprintf('%%%s%%', $term->taxonomy), $termlinkSlug, $termlink);

        $termlink = home_url(user_trailingslashit($termlink, 'category'));

        return app_term_link_filter($term, $termlink);
    }
}

if ( ! function_exists('app_term_link_filter')) {
    /**
     * @param \WP_Term $term
     * @param string   $termlink
     *
     * @return string
     */
    function app_term_link_filter(WP_Term $term, string $termlink): string {
        if ($term->taxonomy == 'post_tag') {
            $termlink = (string) apply_filters('tag_link', $termlink, $term->term_id);
        } elseif ($term->taxonomy == 'category') {
            $termlink = (string) apply_filters('category_link', $termlink, $term->term_id);
        }

        return (string) apply_filters('term_link', $termlink, $term, $term->taxonomy);
    }
}

if ( ! function_exists('app_get_taxonomy_ancestors')) {
    /**
     * @param array<array-key, mixed>|null ...$args PDO fetch options
     *
     * @return array<string,string|int>|false
     */
    function app_get_taxonomy_ancestors(int $termId, string $taxonomy, int $mode = PDO::FETCH_COLUMN, ...$args) {
        global $wpdb;

        try {
            $pdo = app_db_pdo();

            $pdoStatement = $pdo->prepare(
                <<<SQL
with recursive ancestors as (
  select
    cat_1.term_id,
    cat_1.taxonomy,
    cat_1.parent
  from $wpdb->term_taxonomy as cat_1
  where
    cat_1.term_id = :term_id
  union all
  select
    a.term_id,
    cat_2.taxonomy,
    cat_2.parent
  from ancestors a
    inner join $wpdb->term_taxonomy cat_2 on cat_2.term_id = a.parent
  where
    cat_2.parent > 0
    and cat_2.taxonomy = :taxonomy
  )
select
  a.parent as term_id,
  a.taxonomy as taxonomy,
  term.name as term_name,
  term.slug as term_slug
from ancestors a
  left join $wpdb->terms as term on term.term_id = a.parent
SQL
            );

            $pdoStatement->execute(['termId' => $termId, 'taxonomy' => $taxonomy]);

            return $pdoStatement->fetchAll($mode, ...$args);
        } catch (Exception $exception) {
            app_error_log($exception, 'app_get_taxonomy_ancestors');

            return false;
        }
    }
}

if ( ! function_exists('app_term_get_all_children')) {
    /**
     * @return int[]
     */
    function app_term_get_all_children(int $termId): array {
        global $wpdb;

        /** @var int[] $children */
        $children = wp_cache_get(sprintf('term_all_children_%d', $termId), Cache::CACHE_GROUP);

        if (empty($children)) {
            try {
                $pdo = app_db_pdo();

                $pdoStatement = $pdo->prepare(
                    <<<SQL
with recursive children as (
  select
    d.term_id,
    d.parent
  from $wpdb->term_taxonomy d
  where
    d.term_id = :term_id
  union all
  select
    d.term_id,
    d.parent
  from $wpdb->term_taxonomy d
    inner join children c on c.term_id = d.parent
  )
select
  c.term_id as term_id
from children c
SQL
                );

                $pdoStatement->execute(['termId' => $termId]);

                /** @var int[] $children */
                $children = $pdoStatement->fetchAll(PDO::FETCH_COLUMN);

                if ( ! empty($children)) {
                    sort($children);

                    wp_cache_set(sprintf('term_all_children_%d', $termId), $children, Cache::CACHE_GROUP);
                }
            } catch (Exception $exception) {
                app_error_log($exception, __FUNCTION__);
            }
        }

        return $children;
    }
}

if ( ! function_exists('app_get_wp_block')) {
    /**
     * @param string $postName
     *
     * @return false|\WP_Post
     */
    function app_get_wp_block(string $postName) {
        $cacheKey = sprintf('wp_block_%s', $postName);

        /** @var \WP_Post|false $result */
        $result = wp_cache_get($cacheKey, Cache::CACHE_GROUP);

        if (false === $result) {
            /** @var \WP_Post[] $posts */
            $posts = get_posts([
                'post_type' => 'wp_block',
                'numberposts' => 1,
                'name' => $postName,
            ]);

            if ( ! empty($posts)) {
                $result = reset($posts);
                wp_cache_set($cacheKey, $result, Cache::CACHE_GROUP);
            }
        }

        return $result;
    }
}

if ( ! function_exists('app_attachment_url_to_postid')) {
    /**
     * @param string $url
     *
     * @return false|int
     */
    function app_attachment_url_to_postid(string $url) {
        if ( ! app_is_current_host($url)) {
            return false;
        }

        global $wpdb;

        $uploadDir = wp_upload_dir();

        $siteUrl = (object) parse_url($uploadDir['url']);
        $imagePath = (object) parse_url($url);

        if ((string) $imagePath->scheme !== (string) $siteUrl->scheme) {
            $url = str_replace((string) $imagePath->scheme, (string) $siteUrl->scheme, $url);
        }

        try {
            $pdo = app_db_pdo();

            $pdoStatement = $pdo->prepare(
                <<<SQL
select
  i.ID
from $wpdb->posts as i 
where 
  i.post_type = 'attachment' 
  and i.guid = :guid
SQL
            );

            $pdoStatement->execute([
                'guid' => esc_url_raw($url),
            ]);

            return (int) $pdoStatement->fetchColumn();
        } catch (Exception $exception) {
            return false;
        }
    }
}

if ( ! function_exists('app_make_link_relative')) {
    function app_make_link_relative(string $link): string {
        if (app_is_current_host($link)) {
            $link = wp_make_link_relative($link);
        }

        return $link;
    }
}

if ( ! function_exists('app_is_wp_importing')) {
    function app_is_wp_importing(): bool {
        return defined('WP_IMPORTING') && WP_IMPORTING;
    }
}

if ( ! function_exists('app_is_wp_cli')) {
    function app_is_wp_cli(): bool {
        return defined('WP_CLI') && WP_CLI;
    }
}

if ( ! function_exists('app_is_enabled_wp_performance')) {
    /**
     * Checks when plugin should be enabled. This offers nice compatibilty with wp-cli.
     */
    function app_is_enabled_wp_performance(): bool {
        static $enabled;

        if ($enabled === null) {
            $enabled = ! wp_doing_cron() && ! app_is_wp_cli() && ! app_is_wp_importing();
        }

        return (bool) $enabled;
    }
}
