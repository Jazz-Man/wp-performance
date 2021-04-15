<?php

namespace JazzMan\Performance\Utils;

use Latitude\QueryBuilder\QueryFactory;
use function Latitude\QueryBuilder\alias;
use function Latitude\QueryBuilder\field;
use function Latitude\QueryBuilder\on;

class AttachmentData
{
    public const SIZE_FULL = 'full';
    public const SIZE_THUMBNAIL = 'thumbnail';
    public const SIZE_MEDIUM = 'medium';
    public const SIZE_MEDIUM_LARGE = 'medium_large';
    public const SIZE_LARGE = 'large';
    public const SIZES_JPEG = 'sizes';
    public const SIZES_WEBP = 'sizes_webp';

    /**
     * @var string[]
     */
    private static $validSizes = [
        self::SIZES_JPEG,
        self::SIZES_WEBP,
    ];

    /**
     * @var int
     */
    private $attachment_id = 0;

    /**
     * @var null|string
     */
    private $full_jpeg_url;

    /**
     * @var null|string
     */
    private $full_webp_url;

    /**
     * @var null|array<string,string>
     */
    private $metadata;

    /**
     * @var null|string
     */
    private $image_alt;
    /**
     * @var array<string,string>
     */
    private $upload_dir;

    public function __construct(int $attachment_id = 0)
    {
        $this->upload_dir = wp_upload_dir();

        $attachment_image = wp_cache_get("attachment_image_{$attachment_id}", Cache::CACHE_GROUP);

        if (empty($attachment_image)) {
            global $wpdb;
            $pdo = app_db_pdo();

            $sql = (new QueryFactory())
                ->select(
                    alias('i.ID', 'attachment_id'),
                    alias('i.guid', 'full_url'),
                    alias('metadata.meta_value', 'metadata'),
                    alias('image_alt.meta_value', 'image_alt'),
                )
                ->from(alias($wpdb->posts, 'i'))
                ->leftJoin(alias($wpdb->postmeta, 'metadata'), on('i.ID', 'metadata.post_id'))
                ->leftJoin(alias($wpdb->postmeta, 'image_alt'), on('i.ID', 'image_alt.post_id'))
                ->where(
                    field('i.ID')->eq($attachment_id)
                        ->and(field('metadata.meta_key')->eq('_wp_attachment_metadata'))
                        ->and(field('image_alt.meta_key')->eq('_wp_attachment_image_alt'))
                )
                ->limit(1)
                ->groupBy('i.ID')
                ->compile()
            ;

            $sql_select = $pdo->prepare($sql->sql());

            $sql_select->execute($sql->params());

            $attachment_image = $sql_select->fetchObject();

            if (!empty($attachment_image)) {
                wp_cache_set(
                    "attachment_image_{$attachment_id}",
                    $attachment_image,
                    Cache::CACHE_GROUP
                );
            }
        }

        if (!empty($attachment_image)) {
            $this->attachment_id = $attachment_id;
            $this->metadata = maybe_unserialize($attachment_image->metadata);
            if (!empty($this->metadata['file_webp'])) {
                $this->full_webp_url = "{$this->upload_dir['baseurl']}/{$this->metadata['file_webp']}";
            }

            $this->full_jpeg_url = $attachment_image->full_url;
            $this->image_alt = $attachment_image->image_alt;
        }

        if (0 === $this->attachment_id) {
            throw new \InvalidArgumentException(\sprintf('Invalid image ID, "%d" given.', $attachment_id));
        }
    }

    public function getAttachmentId(): int
    {
        return $this->attachment_id;
    }

    public function getUrl(string $size = self::SIZE_FULL): array
    {
        $sizes = null !== $this->full_webp_url && app_use_webp() ? 'sizes_webp' : 'sizes';

        return $this->getSizeArray($sizes, $size, false);
    }

    /**
     * @return null|string[]
     */
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function getImageAlt(): ?string
    {
        return $this->image_alt;
    }

    private function getSizeArray(
        string $sizes = self::SIZES_JPEG,
        string $size = self::SIZE_FULL,
        bool $add_dir_data = true
    ): array {
        if (!\in_array($sizes, self::$validSizes, true)) {
            throw new \InvalidArgumentException(\sprintf("Invalid sizes prop, \"%s\" given.\nAvailable: %s", $sizes, \implode(', ', self::$validSizes)));
        }

        $img_url = self::SIZES_JPEG === $sizes ? $this->full_jpeg_url : $this->full_webp_url;

        $image_basename = wp_basename($img_url);

        if (!empty($this->metadata[$sizes]) && !empty($this->metadata[$sizes][$size])) {
            $_sizes = $this->metadata[$sizes][$size];
            $image_src = \str_replace($image_basename, $_sizes['file'], $img_url);

            $size_array = [
                'src' => $image_src,
                'width' => (int) $_sizes['width'],
                'height' => (int) $_sizes['height'],
            ];
        } else {
            $size_array = [
                'src' => $img_url,
                'width' => $this->metadata ? (int) $this->metadata['width'] : 0,
                'height' => $this->metadata ? (int) $this->metadata['height'] : 0,
            ];
        }

        if ($add_dir_data) {
            $dirname = _wp_get_attachment_relative_path($this->metadata['file']);

            $size_array['dirname'] = trailingslashit($dirname);

            $image_baseurl = trailingslashit($this->upload_dir['baseurl']).$size_array['dirname'];

            if (is_ssl()
                && 'https' !== \substr($image_baseurl, 0, 5)
                && \parse_url($image_baseurl, PHP_URL_HOST) === $_SERVER['HTTP_HOST']) {
                $image_baseurl = set_url_scheme($image_baseurl, 'https');
            }

            $size_array['image_baseurl'] = $image_baseurl;
        }

        return $size_array;
    }

    /**
     * @return false|string
     */
    public function getImageSrcset(string $sizes = self::SIZES_JPEG, string $size = self::SIZE_FULL)
    {
        if (!\in_array($sizes, self::$validSizes, true)) {
            throw new \InvalidArgumentException(\sprintf("Invalid sizes prop, \"%s\" given.\nAvailable: %s", $sizes, \implode(', ', self::$validSizes)));
        }

        $sizes_key = self::SIZES_JPEG === $sizes ? 'sizes' : 'sizes_webp';

        $size_data = $this->getSizeArray($sizes, $size);

        $image_src = $size_data['src'];

        // Get the width and height of the image.
        $image_width = (int) $size_data['width'];
        $image_height = (int) $size_data['height'];

        // Bail early if error/no width.
        if ($image_width < 1) {
            return false;
        }

        // Retrieve the uploads sub-directory from the full size image.
        $dirname = $size_data['dirname'];

        $image_baseurl = $size_data['image_baseurl'];

        $image_edited = \preg_match('/-e[0-9]{13}/', wp_basename($image_src), $image_edit_hash);

        $max_srcset_image_width = 2048;
        // Array to hold URL candidates.
        $sources = [];

        $src_matched = false;

        foreach ($this->metadata[$sizes_key] as $size => $image) {
            $is_src = false;

            if (!\is_array($image)) {
                continue;
            }

            // If the file name is part of the `src`, we've confirmed a match.
            if (!$src_matched && false !== \strpos($image_src, $dirname.$image['file'])) {
                $src_matched = true;
                $is_src = true;
            }

            if ($image_edited && !\strpos($image['file'], $image_edit_hash[0])) {
                continue;
            }

            if ($max_srcset_image_width && $image['width'] > $max_srcset_image_width && !$is_src) {
                continue;
            }

            // If the image dimensions are within 1px of the expected size, use it.
            if (wp_image_matches_ratio($image_width, $image_height, $image['width'], $image['height'])) {
                // Add the URL, descriptor, and value to the sources array to be returned.
                $source = [
                    'url' => $image_baseurl.$image['file'],
                    'descriptor' => 'w',
                    'value' => $image['width'],
                ];

                // The 'src' image has to be the first in the 'srcset', because of a bug in iOS8. See #35030.
                if ($is_src) {
                    $sources = array_merge([$image['width'] => $source], $sources);
                } else {
                    $sources[$image['width']] = $source;
                }
            }
        }

        // Only return a 'srcset' value if there is more than one source.
        if (!$src_matched || !\is_array($sources) || \count($sources) < 2) {
            return false;
        }

        $srcset = [];

        foreach ($sources as $source) {
            $srcset[] = \sprintf('%s %d%s', $source['url'], $source['value'], $source['descriptor']);
        }

        return \implode(', ', $srcset);
    }
}
