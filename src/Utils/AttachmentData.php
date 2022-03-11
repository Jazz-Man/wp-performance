<?php

namespace JazzMan\Performance\Utils;

use Exception;
use InvalidArgumentException;
use PDO;

class AttachmentData {
    /**
     * @var string
     */
    public const SIZE_FULL = 'full';

    /**
     * @var string
     */
    public const SIZE_THUMBNAIL = 'thumbnail';

    /**
     * @var string
     */
    public const SIZE_MEDIUM = 'medium';

    /**
     * @var string
     */
    public const SIZE_MEDIUM_LARGE = 'medium_large';

    /**
     * @var string
     */
    public const SIZE_LARGE = 'large';

    /**
     * @var string
     */
    public const SIZES_JPEG = 'sizes';

    /**
     * @var string
     */
    public const SIZES_WEBP = 'sizes_webp';

    /**
     * @var string[]
     */
    private static array $validSizeKeys = [
        self::SIZES_JPEG,
        self::SIZES_WEBP,
    ];

    private string $fullJpegUrl;

    private ?string $fullWebpUrl = null;

    /**
     * @var array{width:int, height:int, file: string, image_meta?: mixed, sizes?: array<string, array{file:string, width:int, height:int, mime-type:string}>}
     */
    private array $metadata;

    /**
     * @var string|null
     */
    private ?string $imageAlt;

    /**
     * @throws Exception
     */
    public function __construct(int $attachmentId = 0) {
        $attachment = $this->getAttachmentFromDb($attachmentId);

        /* @psalm-suppress MixedPropertyTypeCoercion */
        $this->metadata = !empty($attachment['metadata']) ? (array) maybe_unserialize((string) $attachment['metadata']) : [];

        if ( ! empty($this->metadata['file_webp'])) {
            $this->fullWebpUrl = sprintf('%s/%s', self::getBaseUploadUrl(), (string) $this->metadata['file_webp']);
        }

        $this->fullJpegUrl = sprintf('%s/%s', self::getBaseUploadUrl(), (string) $attachment['fullUrl']);
        $this->imageAlt = !empty($attachment['imageAlt']) ? (string) $attachment['imageAlt'] : null;
    }

    /**
     * @param int $attachmentId
     *
     * @throws InvalidArgumentException
     *
     * @return array<string, scalar|null>
     *
     * @psalm-return array<string, null|scalar>
     */
    private function getAttachmentFromDb(int $attachmentId = 0): array {
        global $wpdb;

        $cacheKey = sprintf('attachment_image_%d', $attachmentId);

        /** @var array<string,string|null>|false $attachment */
        $attachment = wp_cache_get($cacheKey, Cache::CACHE_GROUP);

        if (empty($attachment)) {
            $pdo = app_db_pdo();

            $pdoStatement = $pdo->prepare(<<<SQL
select
  img.ID as attachmentId,
  imgFile.meta_value as fullUrl,
  metadata.meta_value as metadata,
  imgAlt.meta_value as imageAlt
from $wpdb->posts as img
left join $wpdb->postmeta as metadata on img.ID = metadata.post_id and metadata.meta_key = '_wp_attachment_metadata'
left join $wpdb->postmeta as imgFile on img.ID = imgFile.post_id
left join $wpdb->postmeta as imgAlt on img.ID = imgAlt.post_id and imgAlt.meta_key = '_wp_attachment_image_alt'
where img.ID = :attachmentId
and imgFile.meta_key = '_wp_attached_file'
group by ID
limit 1

SQL);

            $pdoStatement->execute([
                'attachmentId' => $attachmentId,
            ]);

            $attachment = $pdoStatement->fetch(PDO::FETCH_ASSOC);

            if ( ! empty($attachment)) {
                wp_cache_set($cacheKey, $attachment, Cache::CACHE_GROUP);
            }
        }

        if (empty($attachment)) {
            throw new InvalidArgumentException(sprintf('Invalid image ID, "%d" given.', $attachmentId));
        }

        /* @var array<string,string|null> $attachment */
        return $attachment;
    }

    /**
     * @param string $attachmentSize
     *
     * @return array{src: string, width: int, height: int, sizes: false|string, dirname?: string, image_baseurl?: string, srcset: bool|string}
     */
    public function getUrl(string $attachmentSize = self::SIZE_FULL): array {
        $sizesKey = null !== $this->fullWebpUrl && app_use_webp() ? 'sizes_webp' : 'sizes';

        $sizeArray = $this->getSizeArray($sizesKey, $attachmentSize, false);

        $sizeArray['srcset'] = $this->getImageSrcset($sizesKey, $attachmentSize);

        return $sizeArray;
    }

    public function getImageAlt(): ?string {
        return $this->imageAlt;
    }

    /**
     * @param string $sizeKey
     * @param string $attachmentSize
     * @param bool   $addDirData
     *
     * @return array{src: string, width: int, height: int, sizes: false|string, dirname?: string, image_baseurl?: string, srcset?: false|string}
     */
    private function getSizeArray(string $sizeKey = self::SIZES_JPEG, string $attachmentSize = self::SIZE_FULL, bool $addDirData = true): array {
        if ( ! in_array($sizeKey, self::$validSizeKeys, true)) {
            throw new InvalidArgumentException(sprintf('Invalid size key, "%s" given. Available: %s', $sizeKey, implode(', ', self::$validSizeKeys)));
        }

        /** @var string $imgUrl */
        $imgUrl = self::SIZES_JPEG === $sizeKey ? $this->fullJpegUrl : $this->fullWebpUrl;

        $sizeArray = [
            'src' => $imgUrl,
            'width' => !empty($this->metadata['width']) ? $this->metadata['width'] : 0,
            'height' => !empty($this->metadata['height']) ? $this->metadata['height'] : 0,
        ];

        if ( ! empty($this->metadata[$sizeKey]) && ! empty($this->metadata[$sizeKey][$attachmentSize])) {
            /** @var array<string,string|int> $sizes */
            $sizes = $this->metadata[$sizeKey][$attachmentSize];

            $sizeArray = [
                'src' => str_replace(wp_basename($imgUrl), (string) $sizes['file'], $imgUrl),
                'width' => (int) $sizes['width'],
                'height' => (int) $sizes['height'],
            ];
        }

        $sizeArray['sizes'] = empty($sizeArray['width']) ? false : sprintf('(max-width: %1$dpx) 100vw, %1$dpx', $sizeArray['width']);

        if ($addDirData && ! empty($this->metadata['file'])) {
            $dirname = _wp_get_attachment_relative_path($this->metadata['file']);

            $sizeArray['dirname'] = trailingslashit($dirname);

            $imageBaseurl = trailingslashit(self::getBaseUploadUrl()) . $sizeArray['dirname'];

            if (is_ssl()
                && 'https' !== substr($imageBaseurl, 0, 5)
                && parse_url($imageBaseurl, PHP_URL_HOST) === filter_input(INPUT_SERVER, 'HTTP_HOST')) {
                $imageBaseurl = set_url_scheme($imageBaseurl, 'https');
            }

            $sizeArray['image_baseurl'] = $imageBaseurl;
        }

        return $sizeArray;
    }

    /**
     * @return bool|string
     */
    private function getImageSrcset(string $sizeKey = self::SIZES_JPEG, string $attachmentSize = self::SIZE_FULL) {
        if ( ! in_array($sizeKey, self::$validSizeKeys, true)) {
            throw new InvalidArgumentException(sprintf('Invalid size key, "%s" given. Available: %s', $sizeKey, implode(', ', self::$validSizeKeys)));
        }

        $sizeData = $this->getSizeArray($sizeKey, $attachmentSize);

        // Bail early if error/no width.
        if ($sizeData['width'] < 1) {
            return false;
        }

        if (empty($sizeData['dirname'])) {
            return false;
        }

        if (empty($sizeData['image_baseurl'])) {
            return false;
        }

        $sources = $this->calculateImageSecretSources($sizeKey, $sizeData);

        if (empty($sources)) {
            return false;
        }

        $srcset = [];

        foreach ($sources as $source) {
            $srcset[] = sprintf('%s %d%s', $source['url'], $source['value'], $source['descriptor']);
        }

        return implode(', ', $srcset);
    }

    /**
     * @param string                                                                                                     $sizesKey
     * @param array{src: string, width: int, height: int, sizes: false|string, dirname?: string, image_baseurl?: string} $sizeData
     *
     * @return array<array-key, array{url: string, descriptor: string, value: int}>|false
     */
    private function calculateImageSecretSources(string $sizesKey, array $sizeData) {
        if (empty($sizeData['dirname']) || empty($sizeData['image_baseurl'])) {
            return false;
        }

        $sizesKey = self::SIZES_JPEG === $sizesKey ? 'sizes' : 'sizes_webp';

        // Retrieve the uploads sub-directory from the full size image.
        $dirname = $sizeData['dirname'];

        $isImageEdited = preg_match('#-e\d{13}#', wp_basename($sizeData['src']), $imageEditHash);

        $maxSrcsetImageWidth = (int) apply_filters('max_srcset_image_width', 2048, [
            $sizeData['width'],
            $sizeData['height'],
        ]);

        // Array to hold URL candidates.
        $sources = [];

        $srcMatched = false;

        if ( ! empty($this->metadata[$sizesKey])) {
            /** @var array<array-key,array<string,string|int>> $attachmentSizes */
            $attachmentSizes = (array) $this->metadata[$sizesKey];

            foreach ($attachmentSizes as  $image) {
                $isSrc = false;

                /** @var array<string,string|int>|null $image */
                if ( ! is_array($image)) {
                    continue;
                }

                // If the file name is part of the `src`, we've confirmed a match.
                if ( ! $srcMatched && false !== strpos($sizeData['src'], $dirname . $image['file'] )) {
                    $srcMatched = true;
                    $isSrc = true;
                }

                if ($isImageEdited && ! strpos((string) $image['file'], $imageEditHash[0])) {
                    continue;
                }

                if ($maxSrcsetImageWidth && (int) $image['width'] > $maxSrcsetImageWidth && ! $isSrc) {
                    continue;
                }

                // If the image dimensions are within 1px of the expected size, use it.
                if (wp_image_matches_ratio($sizeData['width'], $sizeData['height'], (int) $image['width'], (int) $image['height'])) {
                    // Add the URL, descriptor, and value to the sources array to be returned.
                    $source = [
                        'url' => $sizeData['image_baseurl'] . $image['file'],
                        'descriptor' => 'w',
                        'value' => (int) $image['width'],
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
        if (! $srcMatched) {
            return false;
        }

        if (empty($sources)) {
            return false;
        }

        if (count($sources) < 2) {
            return false;
        }

        return $sources;
    }

    private static function getBaseUploadUrl(): string {

        /** @var array{path:string, url:string, subdir:string, basedir:string, baseurl:string, error:string|false}|null $uploadDir */
        static $uploadDir;

        if ($uploadDir === null) {
            $uploadDir = wp_upload_dir();
        }

        return $uploadDir['baseurl'];
    }
}
