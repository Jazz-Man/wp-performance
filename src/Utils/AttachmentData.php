<?php

namespace JazzMan\Performance\Utils;

use InvalidArgumentException;
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
    private static array $validSizeKeys = [
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

    /**
     * @throws \Exception
     */
    public function __construct(int $attachmentId = 0)
    {
        $this->uploadDir = wp_upload_dir();

        $attachment = $this->getAttachmentFromDb($attachmentId);

        $this->metadata = maybe_unserialize($attachment->metadata);
        if ( ! empty($this->metadata['file_webp'])) {
            $this->fullWebpUrl = "{$this->uploadDir['baseurl']}/{$this->metadata['file_webp']}";
        }

        $this->fullJpegUrl = "{$this->uploadDir['baseurl']}/$attachment->fullUrl";
        $this->imageAlt = $attachment->imageAlt;
    }

    /**
				 * @throws \InvalidArgumentException
				 *
				 * @return mixed
				 */
				private function getAttachmentFromDb(int $attachmentId = 0)
    {
	    global $wpdb;

        $attachment = wp_cache_get("attachment_image_$attachmentId", Cache::CACHE_GROUP);

        if (empty($attachment)) {
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
                    field('i.ID')
                        ->eq($attachmentId)
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

            if ( ! empty($attachment)) {
                wp_cache_set("attachment_image_$attachmentId", $attachment, Cache::CACHE_GROUP);
            }
        }

        if (empty($attachment)) {
            throw new InvalidArgumentException(sprintf('Invalid image ID, "%d" given.', $attachmentId));
        }

        return $attachment;
    }

    /**
     * @return (false|int|string)[]
     *
     * @psalm-return array{src: string, width: int, height: int, sizes: false|string, dirname?: string, image_baseurl?: string, srcset: false|string}
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
     * @return (false|int|string)[]
     *
     * @psalm-return array{src: string, width: int, height: int, sizes: false|string, dirname?: string, image_baseurl?: string}
     */
    private function getSizeArray(string $sizeKey = self::SIZES_JPEG, string $attachmentSize = self::SIZE_FULL, bool $addDirData = true): array
    {
        if ( ! in_array($sizeKey, self::$validSizeKeys, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid size key, "%s" given. Available: %s',
                    $sizeKey,
                    implode(', ', self::$validSizeKeys)
                )
            );
        }

        /** @var string $imgUrl */
        $imgUrl = self::SIZES_JPEG === $sizeKey ? $this->fullJpegUrl : $this->fullWebpUrl;

        $sizeArray = [
            'src' => $imgUrl,
            'width' => $this->metadata ? (int) $this->metadata['width'] : 0,
            'height' => $this->metadata ? (int) $this->metadata['height'] : 0,
        ];

        if ( ! empty($this->metadata[$sizeKey]) && ! empty($this->metadata[$sizeKey][$attachmentSize])) {
            $sizes = $this->metadata[$sizeKey][$attachmentSize];

            $imageBasename = wp_basename($imgUrl);

            $imageSrc = str_replace($imageBasename, $sizes['file'], $imgUrl);

            $sizeArray = [
                'src' => $imageSrc,
                'width' => (int) $sizes['width'],
                'height' => (int) $sizes['height'],
            ];
        }

        $sizeArray['sizes'] = empty($sizeArray['width']) ? false : sprintf('(max-width: %1$dpx) 100vw, %1$dpx', $sizeArray['width']);

        if ($addDirData && ! empty($this->metadata['file'])) {
            $dirname = _wp_get_attachment_relative_path($this->metadata['file']);

            $sizeArray['dirname'] = trailingslashit($dirname);

            $imageBaseurl = trailingslashit($this->uploadDir['baseurl']).$sizeArray['dirname'];

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
				private function getImageSrcset(string $sizeKey = self::SIZES_JPEG, string $attachmentSize = self::SIZE_FULL)
    {
        if ( ! in_array($sizeKey, self::$validSizeKeys, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid size key, "%s" given. Available: %s',
                    $sizeKey,
                    implode(', ', self::$validSizeKeys)
                )
            );
        }

        $sizeData = $this->getSizeArray($sizeKey, $attachmentSize);

        // Bail early if error/no width.
        if ($sizeData['width'] < 1) {
            return false;
        }

        if (empty($sizeData['dirname']) || empty($sizeData['image_baseurl'])) {
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
     * @param array<string,mixed> $sizeData
     *
     * @return array<string,mixed>|false
     *
     */
    private function calculateImageSecretSources(string $sizesKey, array $sizeData)
    {
        $sizesKey = self::SIZES_JPEG === $sizesKey ? 'sizes' : 'sizes_webp';

        // Get the width and height of the image.
        $imageWidth = (int) $sizeData['width'];
        $imageHeight = (int) $sizeData['height'];

        // Retrieve the uploads sub-directory from the full size image.
        $dirname = $sizeData['dirname'];

        $isImageEdited = preg_match('/-e\d{13}/', wp_basename($sizeData['src']), $imageEditHash);

        $maxSrcsetImageWidth = apply_filters('max_srcset_image_width', 2048, [
            $imageWidth,
            $imageHeight,
        ]);

        // Array to hold URL candidates.
        $sources = [];

        $srcMatched = false;

        if ( ! empty($this->metadata[$sizesKey])) {
            foreach ($this->metadata[$sizesKey] as $attachmentSize => $image) {
                $isSrc = false;

                if ( ! is_array($image)) {
                    continue;
                }

                // If the file name is part of the `src`, we've confirmed a match.
                if ( ! $srcMatched && false !== strpos($sizeData['src'], $dirname.$image['file'])) {
                    $srcMatched = true;
                    $isSrc = true;
                }

                if ($isImageEdited && ! strpos($image['file'], $imageEditHash[0])) {
                    continue;
                }

                if ($maxSrcsetImageWidth && $image['width'] > $maxSrcsetImageWidth && ! $isSrc) {
                    continue;
                }

                // If the image dimensions are within 1px of the expected size, use it.
                if (wp_image_matches_ratio($imageWidth, $imageHeight, $image['width'], $image['height'])) {
                    // Add the URL, descriptor, and value to the sources array to be returned.
                    $source = [
                        'url' => $sizeData['image_baseurl'].$image['file'],
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
        if ( ! $srcMatched || ! is_array($sources) || count($sources) < 2) {
            return false;
        }

        return $sources;
    }
}
