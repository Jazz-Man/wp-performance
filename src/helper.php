<?php

use JazzMan\Performance\Utils\AttachmentData;
use JazzMan\Performance\Utils\Cache;
use Latitude\QueryBuilder\QueryFactory;
use function Latitude\QueryBuilder\alias;
use function Latitude\QueryBuilder\field;

if ( ! function_exists('app_get_image_data_array')) {
    /**
     * @param array|string $size
     *
     * @return array|bool
     */
    function app_get_image_data_array(int $attachmentId, $size = 'large')
    {
        $imageData = [
            'size' => $size,
        ];

        if (is_array($size)) {
            $image = wp_get_attachment_image_src($attachmentId, $size);

            if ( ! empty($image)) {
                [$url, $width, $height] = $image;

                $alt = get_post_meta($attachmentId, '_wp_attachment_image_alt', true);

                $imageData['url'] = $url;
                $imageData['width'] = $width;
                $imageData['height'] = $height;
                $imageData['alt'] = $alt;

                return $imageData;
            }
        } else {
            try {
                $attachment = new AttachmentData($attachmentId);

                $image = $attachment->getUrl($size);

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

        return false;
    }
}

if ( ! function_exists('app_get_attachment_image_url')) {
    /**
     * @return false|string
     */
    function app_get_attachment_image_url(int $attachmentId, string $size = AttachmentData::SIZE_THUMBNAIL)
    {
        try {
            $image = app_get_image_data_array($attachmentId, $size);
            if ( ! empty($image) && ! empty($image['url'])) {
                return $image['url'];
            }

            return false;
        } catch (Exception $exception) {
            return false;
        }
    }
}

if ( ! function_exists('app_get_attachment_image')) {
    /**
     * @param array|string $attributes
     */
    function app_get_attachment_image(
        int $attachmentId,
        string $size = AttachmentData::SIZE_THUMBNAIL,
        $attributes = ''
    ): string {
        try {
            $image = app_get_image_data_array($attachmentId, $size);

            if (empty($image)) {
                throw new Exception(sprintf('Image not fount: attachment_id "%d", size "%s"', $attachmentId, $size));
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
            if (array_key_exists('loading', $attributes) && ! $attributes['loading']) {
                unset($attributes['loading']);
            }

            if (empty($attributes['srcset']) && ! empty($image['srcset'])) {
                $attributes['srcset'] = $image['srcset'];
            }

            if (empty($attributes['sizes']) && ! empty($image['sizes'])) {
                $attributes['sizes'] = $image['sizes'];
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
     * @return string|WP_Error
     */
    function app_get_term_link(int $termId, string $termTaxonomy)
    {
        global $wp_rewrite;

        $term = get_term($termId, $termTaxonomy);

        if (is_wp_error($term)) {
            return $term;
        }

        $termTaxonomy = $term->taxonomy;

        $termlink = $wp_rewrite->get_extra_permastruct($termTaxonomy);

        $termlink = apply_filters('pre_term_link', $termlink, $term);

        $slug = $term->slug;
        $taxonomy = get_taxonomy($termTaxonomy);

        if (empty($termlink)) {
            if ('category' === $termTaxonomy) {
                $termlink = '?cat='.$term->term_id;
            } elseif ($taxonomy->query_var) {
                $termlink = "?$taxonomy->query_var=$slug";
            } else {
                $termlink = "?taxonomy=$termTaxonomy&term=$slug";
            }

            $termlink = home_url($termlink);
        } else {
            if ($taxonomy->rewrite['hierarchical']) {
                $hierarchicalSlugs = [];

                if ($term->parent) {
                    $hierarchicalSlugs = wp_cache_get(
                        "taxonomy_ancestors_{$term->term_id}_$termTaxonomy",
                        Cache::CACHE_GROUP
                    );

                    if (empty($hierarchicalSlugs)) {
                        $result = [];
                        $ancestors = app_get_taxonomy_ancestors($term->term_id, $termTaxonomy, PDO::FETCH_CLASS);
                        foreach ($ancestors as $ancestor) {
                            $result[] = $ancestor->term_slug;
                        }

                        $hierarchicalSlugs = $result;

                        wp_cache_set(
                            "taxonomy_ancestors_{$term->term_id}_$termTaxonomy",
                            $result,
                            Cache::CACHE_GROUP
                        );
                    }
                    $hierarchicalSlugs = array_reverse($hierarchicalSlugs);
                }

                $hierarchicalSlugs[] = $slug;

                $termlink = str_replace("%$termTaxonomy%", implode('/', $hierarchicalSlugs), $termlink);
            } else {
                $termlink = str_replace("%$termTaxonomy%", $slug, $termlink);
            }
            $termlink = home_url(user_trailingslashit($termlink, 'category'));
        }

        if ('post_tag' === $termTaxonomy) {
            $termlink = apply_filters('tag_link', $termlink, $term->term_id);
        } elseif ('category' === $termTaxonomy) {
            $termlink = apply_filters('category_link', $termlink, $term->term_id);
        }

        return apply_filters('term_link', $termlink, $term, $termTaxonomy);
    }
}

if ( ! function_exists('app_get_taxonomy_ancestors')) {
    /**
     * @param int   $mode
     * @param mixed ...$args
     */
    function app_get_taxonomy_ancestors(int $termId, string $taxonomy, $mode = PDO::FETCH_COLUMN, ...$args): array
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

        $sql->execute(compact('termId', 'taxonomy'));

        return $sql->fetchAll($mode, ...$args);
    }
}

if ( ! function_exists('app_term_get_all_children')) {
    function app_term_get_all_children(int $termId): array
    {
        $children = wp_cache_get("term_all_children_$termId", Cache::CACHE_GROUP);

        if (empty($children)) {
            global $wpdb;

            $pdo = app_db_pdo();

            $sql = $pdo->prepare(
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

            $sql->execute(compact('termId'));

            $children = $sql->fetchAll(PDO::FETCH_COLUMN);

            if ( ! empty($children)) {
                sort($children);

                wp_cache_set("term_all_children_$termId", $children, Cache::CACHE_GROUP);
            }
        }

        return $children;
    }
}

if ( ! function_exists('app_get_wp_block')) {
    /**
     * @return false|WP_Post
     */
    function app_get_wp_block(string $postName)
    {
        $cacheKey = "wp_block_$postName";
        $result = wp_cache_get($cacheKey, Cache::CACHE_GROUP);

        if (false === $result) {
            global $wpdb;

            $pdo = app_db_pdo();

            $sql = (new QueryFactory())
                ->select('p.*')
                ->from(alias($wpdb->posts, 'p'))
                ->where(
                    field('p.post_type')
                        ->eq('wp_block')
                        ->and(field('p.post_name')->eq($postName))
                )
                ->limit(1)
                ->compile()
            ;

            $statement = $pdo->prepare($sql->sql());
            $statement->execute($sql->params());

            $result = $statement->fetchObject();

            wp_cache_set($cacheKey, $result, Cache::CACHE_GROUP);
        }

        return $result;
    }
}

if ( ! function_exists('app_attachment_url_to_postid')) {
    /**
     * @return false|int
     */
    function app_attachment_url_to_postid(string $url)
    {
        if ( ! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        global $wpdb;
        $pdo = app_db_pdo();
        $path = $url;

        $uploadDir = wp_upload_dir();

        $siteUrl = parse_url($uploadDir['url']);
        $imagePath = parse_url($path);

        if (( ! empty($siteUrl['host']) && ! empty($imagePath['host'])) && $siteUrl['host'] !== $imagePath['host']) {
            return false;
        }

        if (isset($imagePath['scheme']) && ($imagePath['scheme'] !== $siteUrl['scheme'])) {
            $path = str_replace($imagePath['scheme'], $siteUrl['scheme'], $path);
        }

        $statement = $pdo->prepare(
            <<<SQL
                select 
                  i.ID
                from $wpdb->posts as i 
                where i.post_type = 'attachment' and i.guid = :guid
SQL
        );

        $statement->execute([
            'guid' => esc_url_raw($path),
        ]);

        return $statement->fetchColumn();
    }
}
