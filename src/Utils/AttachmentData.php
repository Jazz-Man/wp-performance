<?php

namespace JazzMan\Performance\Utils;

use Latitude\QueryBuilder\QueryFactory;
use function Latitude\QueryBuilder\alias;
use function Latitude\QueryBuilder\field;
use function Latitude\QueryBuilder\on;

class AttachmentData
{
    const SIZE_FULL = 'full';
    const SIZE_THUMBNAIL = 'thumbnail';
    const SIZE_MEDIUM = 'medium';
    const SIZE_MEDIUM_LARGE = 'medium_large';
    const SIZE_LARGE = 'large';
    const SIZES_JPEG = 'sizes';
    const SIZES_WEBP = 'sizes_webp';

    /**
     * @var string[]
     */
    private static $validSizeKeys = [
        self::SIZES_JPEG,
        self::SIZES_WEBP,
    ];

    /**
     * @var null|string
     */
    private $fullJpegUrl;

    /**
     * @var null|string
     */
    private $fullWebpUrl;

    /**
     * @var null|array<string,string>
     */
    private $metadata;

    /**
     * @var null|string
     */
    private $imageAlt;
    /**
     * @var array<string,string>
     */
    private $uploadDir;

    public function __construct(int $attachmentId = 0)
    {
        $this->uploadDir = wp_upload_dir();

        $attachment = wp_cache_get("attachment_image_{$attachmentId}", Cache::CACHE_GROUP);

        if (empty($attachment)) {
            global $wpdb;
            $pdo = app_db_pdo();

            $sql = (new QueryFactory())
                ->select(
                    alias('i.ID', 'attachmentId'),
                    alias('file.meta_value', 'fullUrl'),
                    alias('metadata.meta_value', 'metadata'),
                    alias('alt.meta_value', 'imageAlt')
                )
                ->from(alias($wpdb->posts, 'i'))
                ->leftJoin(alias($wpdb->postmeta, 'metadata'), on('i.ID', 'metadata.post_id'))
                ->leftJoin(alias($wpdb->postmeta, 'file'), on('i.ID', 'file.post_id'))
                ->leftJoin(
                    alias($wpdb->postmeta, 'alt'),
                    on('i.ID', 'alt.post_id')
                        ->and(field('alt.meta_key')->eq('_wp_attachment_image_alt'))
                )
                ->where(
                    field('i.ID')->eq($attachmentId)
                        ->and(field('metadata.meta_key')->eq('_wp_attachment_metadata'))
                        ->and(field('file.meta_key')->eq('_wp_attached_file'))
                )
                ->limit(1)
                ->groupBy('i.ID')
                ->compile()
            ;

            $statement = $pdo->prepare($sql->sql());

            $statement->execute($sql->params());

            $attachment = $statement->fetchObject();

            if (!empty($attachment)) {
                wp_cache_set(
                    "attachment_image_{$attachmentId}",
                    $attachment,
                    Cache::CACHE_GROUP
                );
            }
        }

        if (!empty($attachment)) {
            $this->metadata = maybe_unserialize($attachment->metadata);
            if (!empty($this->metadata['file_webp'])) {
                $this->fullWebpUrl = "{$this->uploadDir['baseurl']}/{$this->metadata['file_webp']}";
            }

            $this->fullJpegUrl = "{$this->uploadDir['baseurl']}/{$attachment->fullUrl}";
            $this->imageAlt = $attachment->imageAlt;
        } else {
            throw new \InvalidArgumentException(\sprintf('Invalid image ID, "%d" given.', $attachmentId));
        }
    }

    /**
     * @param  string  $attachmentSize
     *
     * @return array
     */
    public function getUrl(string $attachmentSize = self::SIZE_FULL): array
    {
        $sizesKey = null !== $this->fullWebpUrl && app_use_webp() ? 'sizes_webp' : 'sizes';

        $sizeArray = $this->getSizeArray($sizesKey, $attachmentSize, false);

        $sizeArray['srcset'] = $this->getImageSrcset($sizesKey, $attachmentSize);

        return $sizeArray;
    }

    public function getImageAlt(): ?string
    {
        return $this->imageAlt;
    }

    /**
     * @param  string  $sizeKey
     * @param  string  $attachmentSize
     * @param  bool  $addDirData
     *
     * @return array
     */

    private function getSizeArray(
        string $sizeKey = self::SIZES_JPEG,
        string $attachmentSize = self::SIZE_FULL,
        bool $addDirData = true
    ): array {
        if (!\in_array($sizeKey, self::$validSizeKeys, true)) {
            throw new \InvalidArgumentException(
                \sprintf(
                    'Invalid size key, "%s" given. Available: %s',
                    $sizeKey,
                    \implode(', ', self::$validSizeKeys)
                )
            );
        }

        $imgUrl = self::SIZES_JPEG === $sizeKey ? $this->fullJpegUrl : $this->fullWebpUrl;

        $imageBasename = wp_basename($imgUrl);

        if (!empty($this->metadata[$sizeKey]) && !empty($this->metadata[$sizeKey][$attachmentSize])) {
            $sizes = $this->metadata[$sizeKey][$attachmentSize];
            $imageSrc = \str_replace($imageBasename, $sizes['file'], $imgUrl);

            $sizeArray = [
                'src' => $imageSrc,
                'width' => (int) $sizes['width'],
                'height' => (int) $sizes['height'],
            ];
        } else {
            $sizeArray = [
                'src' => $imgUrl,
                'width' => $this->metadata ? (int) $this->metadata['width'] : 0,
                'height' => $this->metadata ? (int) $this->metadata['height'] : 0,
            ];
        }

        if ((bool) $sizeArray['width']) {
            $sizeArray['sizes'] = sprintf('(max-width: %1$dpx) 100vw, %1$dpx', $sizeArray['width']);
        } else {
            $sizeArray['sizes'] = false;
        }

        if ($addDirData && !empty($this->metadata['file'])) {
            $dirname = _wp_get_attachment_relative_path($this->metadata['file']);

            $sizeArray['dirname'] = trailingslashit($dirname);

            $imageBaseurl = trailingslashit($this->uploadDir['baseurl']).$sizeArray['dirname'];

            if (is_ssl()
                && 'https' !== \substr($imageBaseurl, 0, 5)
                && \parse_url($imageBaseurl, PHP_URL_HOST) === $_SERVER['HTTP_HOST']) {
                $imageBaseurl = set_url_scheme($imageBaseurl, 'https');
            }

            $sizeArray['image_baseurl'] = $imageBaseurl;
        }

        return $sizeArray;
    }

    /**
     * @param  string  $sizeKey
     * @param  string  $attachmentSize
     *
     * @return false|string
     */
    private function getImageSrcset(string $sizeKey = self::SIZES_JPEG, string $attachmentSize = self::SIZE_FULL)
    {
        if (!\in_array($sizeKey, self::$validSizeKeys, true)) {
            throw new \InvalidArgumentException(
                \sprintf(
                    'Invalid size key, "%s" given. Available: %s',
                    $sizeKey,
                    \implode(', ', self::$validSizeKeys)
                )
            );
        }

        $sizesKey = self::SIZES_JPEG === $sizeKey ? 'sizes' : 'sizes_webp';

        $sizeData = $this->getSizeArray($sizeKey, $attachmentSize);

        $imageSrc = $sizeData['src'];

        // Get the width and height of the image.
        $imageWidth = (int) $sizeData['width'];
        $imageHeight = (int) $sizeData['height'];

        // Bail early if error/no width.
        if ($imageWidth < 1) {
            return false;
        }

        if (empty($sizeData['dirname']) || empty($sizeData['image_baseurl'])){
            return false;
        }

        // Retrieve the uploads sub-directory from the full size image.
        $dirname = $sizeData['dirname'];

        $imageBaseurl = $sizeData['image_baseurl'];

        $isImageEdited = \preg_match('/-e[0-9]{13}/', wp_basename($imageSrc), $imageEditHash);

        $maxSrcsetImageWidth = 2048;
        // Array to hold URL candidates.
        $sources = [];

        $srcMatched = false;

        if (!empty($this->metadata[$sizesKey])) {
            foreach ($this->metadata[$sizesKey] as $attachmentSize => $image) {
                $isSrc = false;

                if (!\is_array($image)) {
                    continue;
                }

                // If the file name is part of the `src`, we've confirmed a match.
                if (!$srcMatched && false !== \strpos($imageSrc, $dirname.$image['file'])) {
                    $srcMatched = true;
                    $isSrc = true;
                }

                if ($isImageEdited && !\strpos($image['file'], $imageEditHash[0])) {
                    continue;
                }

                if ($maxSrcsetImageWidth && $image['width'] > $maxSrcsetImageWidth && !$isSrc) {
                    continue;
                }

                // If the image dimensions are within 1px of the expected size, use it.
                if (wp_image_matches_ratio($imageWidth, $imageHeight, $image['width'], $image['height'])) {
                    // Add the URL, descriptor, and value to the sources array to be returned.
                    $source = [
                        'url' => $imageBaseurl.$image['file'],
                        'descriptor' => 'w',
                        'value' => $image['width'],
                    ];

                    // The 'src' image has to be the first in the 'srcset', because of a bug in iOS8. See #35030.
                    if ($isSrc) {
                        $sources = array_merge([$image['width'] => $source], $sources);
                    } else {
                        $sources[$image['width']] = $source;
                    }
                }
            }
        }

        // Only return a 'srcset' value if there is more than one source.
        if (!$srcMatched || !\is_array($sources) || \count($sources) < 2) {
            return false;
        }

        $srcset = [];

        foreach ($sources as $source) {
            $srcset[] = \sprintf('%s %d%s', $source['url'], $source['value'], $source['descriptor']);
        }

        return \implode(', ', $srcset);
    }
}
