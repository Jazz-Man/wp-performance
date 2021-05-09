<?php

use JazzMan\Performance\Utils\AttachmentData;
use JazzMan\Performance\Utils\Cache;
use Latitude\QueryBuilder\QueryFactory;
use function Latitude\QueryBuilder\alias;
use function Latitude\QueryBuilder\field;

if (!function_exists('app_get_image_data_array')) {
    /**
     * @param  int  $attachment_id
     * @param  array|string  $size
     *
     * @return array|bool
     */
    function app_get_image_data_array(int $attachment_id, $size = 'large')
    {
        $imageData = [
            'size' => $size,
        ];

        if (is_array($size)) {
            $image = wp_get_attachment_image_src($attachment_id, $size);

            if (!empty($image)) {
                [$url, $width, $height] = $image;

                $alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);

                $imageData['url'] = $url;
                $imageData['width'] = $width;
                $imageData['height'] = $height;
                $imageData['alt'] = $alt;

                return $imageData;
            }
        } else {
            try {
                $attachment = new AttachmentData($attachment_id);

                $image = $attachment->getUrl($size);

                $imageData['id'] = $attachment_id;
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

        return false;
    }
}

if (!function_exists('app_get_attachment_image_url')) {
    /**
     * @param  int  $attachment_id
     * @param  string  $size
     *
     * @return false|string
     */
    function app_get_attachment_image_url(int $attachment_id, string $size = AttachmentData::SIZE_THUMBNAIL)
    {
        try {
            $image = app_get_image_data_array($attachment_id, $size);
            if (!empty($image) && !empty($image['url'])) {
                return $image['url'];
            }

            return false;
        } catch (Exception $exception) {
            return false;
        }
    }
}

if (!function_exists('app_get_attachment_image')) {
    /**
     * @param  int  $attachment_id
     * @param  string  $size
     * @param  array|string  $attributes
     * @return string
     */
    function app_get_attachment_image(
        int $attachment_id,
        string $size = AttachmentData::SIZE_THUMBNAIL,
        $attributes = ''
    ): string {
        try {
            $image = app_get_image_data_array($attachment_id, $size);

            if (empty($image)) {
                throw new Exception(sprintf('Image not fount: attachment_id "%d", size "%s"', $attachment_id, $size));
            }

            $defaultAttributes = [
                'src' => $image['url'],
                'class' => sprintf('attachment-%1$s size-%1$s', $size),
                'alt' => app_trim_string(strip_tags($image['alt'])),
            ];

            if ($image['width']) {
                $defaultAttributes['width'] = (int) $image['width'];
            }
            if ($image['height']) {
                $defaultAttributes['height'] = (int) $image['height'];
            }

            // Add `loading` attribute.
            if (function_exists('wp_lazy_loading_enabled') && wp_lazy_loading_enabled('img', 'wp_get_attachment_image')) {
                $defaultAttributes['loading'] = 'lazy';
            }

            $attributes = wp_parse_args($attributes, $defaultAttributes);

            // If the default value of `lazy` for the `loading` attribute is overridden
            // to omit the attribute for this image, ensure it is not included.
            if (array_key_exists('loading', $attributes) && !$attributes['loading']) {
                unset($attributes['loading']);
            }

            if (empty($attributes['srcset']) && !empty($image['srcset'])) {
                $attributes['srcset'] = $image['srcset'];
            }

            if (empty($attributes['sizes']) && !empty($image['sizes'])) {
                $attributes['sizes'] = $image['sizes'];
            }

            $post = get_post($attachment_id);

            $attributes = apply_filters('wp_get_attachment_image_attributes', $attributes, $post, $size);

            return sprintf('<img %s/>', app_add_attr_to_el($attributes));
        } catch (Exception $exception) {
            app_error_log($exception, __FUNCTION__);

            return '';
        }
    }
}

if (!function_exists('app_get_term_link')) {
    /**
     * @param  int  $term_id
     * @param  string  $taxonomy
     * @return string|WP_Error
     */
    function app_get_term_link(int $term_id, string $taxonomy)
    {
        global $wp_rewrite;

        $term = get_term($term_id, $taxonomy);

        if (is_wp_error($term)) {
            return $term;
        }

        $taxonomy = $term->taxonomy;

        $termlink = $wp_rewrite->get_extra_permastruct($taxonomy);

        $termlink = apply_filters('pre_term_link', $termlink, $term);

        $slug = $term->slug;
        $t = get_taxonomy($taxonomy);

        if (empty($termlink)) {
            if ('category' === $taxonomy) {
                $termlink = '?cat='.$term->term_id;
            } elseif ($t->query_var) {
                $termlink = "?{$t->query_var}={$slug}";
            } else {
                $termlink = "?taxonomy={$taxonomy}&term={$slug}";
            }
            $termlink = home_url($termlink);
        } else {
            if ($t->rewrite['hierarchical']) {
                $hierarchical_slugs = [];

                if ((bool) $term->parent) {
                    $hierarchical_slugs = wp_cache_get(
                        "taxonomy_ancestors_{$term->term_id}_{$taxonomy}",
                        Cache::CACHE_GROUP
                    );

                    if (empty($hierarchical_slugs)) {
                        $result = [];
                        $ancestors = app_get_taxonomy_ancestors($term->term_id, $taxonomy, PDO::FETCH_CLASS);
                        foreach ((array) $ancestors as $ancestor) {
                            $result[] = $ancestor->term_slug;
                        }

                        $hierarchical_slugs = $result;

                        wp_cache_set(
                            "taxonomy_ancestors_{$term->term_id}_{$taxonomy}",
                            $result,
                            Cache::CACHE_GROUP
                        );
                    }
                    $hierarchical_slugs = array_reverse($hierarchical_slugs);
                }

                $hierarchical_slugs[] = $slug;

                $termlink = str_replace("%{$taxonomy}%", implode('/', $hierarchical_slugs), $termlink);
            } else {
                $termlink = str_replace("%{$taxonomy}%", $slug, $termlink);
            }
            $termlink = home_url(user_trailingslashit($termlink, 'category'));
        }

        if ('post_tag' === $taxonomy) {
            $termlink = apply_filters('tag_link', $termlink, $term->term_id);
        } elseif ('category' === $taxonomy) {
            $termlink = apply_filters('category_link', $termlink, $term->term_id);
        }

        return apply_filters('term_link', $termlink, $term, $taxonomy);
    }
}

if (!function_exists('app_get_taxonomy_ancestors')) {
    /**
     * @param  int  $term_id
     * @param  string  $taxonomy
     * @param  int  $mode
     * @param  mixed  ...$args
     *
     * @return array
     */
    function app_get_taxonomy_ancestors(int $term_id, string $taxonomy, $mode = PDO::FETCH_COLUMN, ...$args): array
    {
        global $wpdb;

        $pdo = app_db_pdo();

        $sql = $pdo->prepare(
            <<<SQL
with recursive ancestors as (
  select
    cat_1.term_id,
    cat_1.taxonomy,
    cat_1.parent
  from {$wpdb->term_taxonomy} as cat_1
  where
    cat_1.term_id = :term_id
  union all
  select
    a.term_id,
    cat_2.taxonomy,
    cat_2.parent
  from ancestors a
    inner join {$wpdb->term_taxonomy} cat_2 on cat_2.term_id = a.parent
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
  left join {$wpdb->terms} as term on term.term_id = a.parent
SQL
        );

        $sql->execute(compact('term_id', 'taxonomy'));

        return $sql->fetchAll($mode, ...$args);
    }
}

if (!function_exists('app_term_get_all_children')) {
    function app_term_get_all_children(int $term_id): array
    {
        $children = wp_cache_get("term_all_children_{$term_id}", Cache::CACHE_GROUP);

        if (empty($children)) {
            global $wpdb;

            $pdo = app_db_pdo();

            $sql = $pdo->prepare(
                <<<SQL
with recursive children as (
  select
    d.term_id,
    d.parent
  from {$wpdb->term_taxonomy} d
  where
    d.term_id = :term_id
  union all
  select
    d.term_id,
    d.parent
  from {$wpdb->term_taxonomy} d
    inner join children c on c.term_id = d.parent
  )
select
  c.term_id as term_id
from children c
SQL
            );

            $sql->execute(compact('term_id'));

            $children = $sql->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($children)) {
                sort($children);

                wp_cache_set("term_all_children_{$term_id}", $children, Cache::CACHE_GROUP);
            }
        }

        return $children;
    }
}

if (!function_exists('app_get_wp_block')) {
    /**
     * @param  string  $post_name
     *
     * @return false|WP_Post
     */
    function app_get_wp_block(string $post_name)
    {
        $cache_key = "wp_block_{$post_name}";
        $result = wp_cache_get($cache_key, Cache::CACHE_GROUP);

        if (false === $result) {
            global $wpdb;

            $pdo = app_db_pdo();

            $sql = (new QueryFactory())
                ->select('p.*')
                ->from(alias($wpdb->posts, 'p'))
                ->where(
                    field('p.post_type')
                        ->eq('wp_block')
                        ->and(field('p.post_name')->eq($post_name))
                )
                ->limit(1)
                ->compile()
            ;

            $st = $pdo->prepare($sql->sql());
            $st->execute($sql->params());

            $result = $st->fetchObject();

            wp_cache_set($cache_key, $result, Cache::CACHE_GROUP);
        }

        return $result;
    }
}

if (!\function_exists('app_attachment_url_to_postid')) {
    /**
     * @param  string  $url
     *
     * @return false|int
     */
    function app_attachment_url_to_postid(string $url)
    {
        if (!\filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        global $wpdb;
        $pdo = app_db_pdo();
        $path = $url;

        $upload_dir = wp_upload_dir();

        $site_url = \parse_url($upload_dir['url']);
        $image_path = \parse_url($path);

        if ((!empty($site_url['host']) && !empty($image_path['host'])) && $site_url['host'] !== $image_path['host']) {
            return false;
        }

        if (isset($image_path['scheme']) && ($image_path['scheme'] !== $site_url['scheme'])) {
            $path = \str_replace($image_path['scheme'], $site_url['scheme'], $path);
        }

        $st = $pdo->prepare(
            <<<SQL
select 
  i.ID
from {$wpdb->posts} as i 
where i.post_type = 'attachment' and i.guid = :guid
SQL
        );

        $st->execute([
            'guid' => esc_url_raw($path),
        ]);

        return $st->fetchColumn();
    }
}
