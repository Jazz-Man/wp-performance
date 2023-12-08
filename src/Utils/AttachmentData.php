<?php

namespace JazzMan\Performance\Utils;

use Exception;
use InvalidArgumentException;
use PDO;

final class AttachmentData {

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

    private readonly string $fullJpegUrl;

    /**
     * @var null|array<string,array{file: string, width: int, height: int, mime-type: string}>
     */
    private ?array $attachmentSizes = null;

    private readonly ?string $imageAlt;

    private ?string $imgFile = null;

    private ?int $attachmentWidth = 0;

    private ?int $attachmentHeight = 0;

    /**
     * @throws Exception
     */
    public function __construct( int|string $attachmentId = 0 ) {
        $attachment = $this->getAttachmentFromDb( $attachmentId );

        if ( ! empty( $attachment['metadata'] ) ) {
            $this->readImgMetadata( $attachment['metadata'] );
        }

        $this->fullJpegUrl = app_upload_url( $attachment['fullUrl'] );
        $this->imageAlt = empty( $attachment['imageAlt'] ) ? null : $attachment['imageAlt'];
    }

    /**
     * @return array<string,boolean|int|string>
     */
    public function getUrl( string $attachmentSize = self::SIZE_FULL ): array {
        $sizeArray = $this->getSizeArray( $attachmentSize, false );

        $sizeArray['srcset'] = $this->getImageSrcset( $attachmentSize );

        return $sizeArray;
    }

    public function getImageAlt(): ?string {
        return $this->imageAlt;
    }

    private function readImgMetadata( string $metadata ): void {
        /**
         * @var array{width?: int, height?: int, file?: string, sizes?: array<string, array{file: string, width: int, height: int, mime-type: string}>, image_meta?: mixed} $data
         */
        $data = maybe_unserialize( $metadata );

        $this->imgFile = empty( $data['file'] ) ? null : $data['file'];

        if ( ! empty( $data['sizes'] ) ) {
            $this->attachmentSizes = $data['sizes'];
        }

        if ( ! empty( $data['width'] ) ) {
            $this->attachmentWidth = $data['width'];
        }

        if ( ! empty( $data['height'] ) ) {
            $this->attachmentHeight = $data['height'];
        }
    }

    /**
     * @return array{attachmentId:int, fullUrl: string, metadata?: string, imageAlt?: string}
     */
    private function getAttachmentFromDb( int|string $attachmentId = 0 ): array {
        global $wpdb;

        $cacheKey = sprintf( 'attachment_image_%d', (int) $attachmentId );

        /** @var array{attachmentId:int, fullUrl: string, metadata?: string, imageAlt?: string}|false $attachment */
        $attachment = wp_cache_get( $cacheKey, Cache::CACHE_GROUP );

        if ( false === $attachment ) {
            try {
                $pdo = app_db_pdo();

                $pdoStatement = $pdo->prepare(
                    <<<SQL
                    select
                      img.ID as attachmentId,
                      imgFile.meta_value as fullUrl,
                      metadata.meta_value as metadata,
                      imgAlt.meta_value as imageAlt
                    from {$wpdb->posts} as img
                    left join {$wpdb->postmeta} as metadata on img.ID = metadata.post_id and metadata.meta_key = '_wp_attachment_metadata'
                    left join {$wpdb->postmeta} as imgFile on img.ID = imgFile.post_id
                    left join {$wpdb->postmeta} as imgAlt on img.ID = imgAlt.post_id and imgAlt.meta_key = '_wp_attachment_image_alt'
                    where img.ID = :attachmentId
                    and imgFile.meta_key = '_wp_attached_file'
                    group by img.ID
                    limit 1

                    SQL
                );

                $pdoStatement->execute( [
                    'attachmentId' => (int) $attachmentId,
                ] );

                /** @var array{attachmentId:int, fullUrl: string, metadata?: string, imageAlt?: string}|false $attachment */
                $attachment = $pdoStatement->fetch( PDO::FETCH_ASSOC );

                if ( ! empty( $attachment ) ) {
                    wp_cache_set( $cacheKey, $attachment, Cache::CACHE_GROUP );
                }
            } catch ( Exception $exception ) {
                app_error_log( $exception, __METHOD__ );
            }
        }

        if ( empty( $attachment ) ) {
            throw new InvalidArgumentException( sprintf( 'Invalid image ID, "%d" given.', $attachmentId ) );
        }

        /* @var array{attachmentId:int, fullUrl: string, metadata?: string, imageAlt?: string} $attachment */
        return $attachment;
    }

    /**
     * @return array{file: string, width: int, height: int, mime-type: string}|false
     */
    private function getAttachmentSizes( string $attachmentSize ): array|false {
        if ( empty( $this->attachmentSizes ) ) {
            return false;
        }

        if ( empty( $this->attachmentSizes[ $attachmentSize ] ) ) {
            return false;
        }

        return $this->attachmentSizes[ $attachmentSize ];
    }

    /**
     * @return array<string,boolean|int|string>
     */
    private function getSizeArray( string $attachmentSize = self::SIZE_FULL, bool $addDirData = true ): array {
        /** @var array<string,int|string> $sizeArray */
        $sizeArray = [
            'src' => $this->fullJpegUrl,
            'width' => $this->attachmentWidth,
            'height' => $this->attachmentHeight,
        ];

        $sizes = $this->getAttachmentSizes( $attachmentSize );

        if ( ! empty( $sizes ) ) {
            $sizeArray['src'] = str_replace( wp_basename( $this->fullJpegUrl ), $sizes['file'], $this->fullJpegUrl );
            $sizeArray['width'] = $sizes['width'];
            $sizeArray['height'] = $sizes['height'];
        }

        $sizeArray['sizes'] = empty( $sizeArray['width'] ) ? false : sprintf( '(max-width: %1$dpx) 100vw, %1$dpx', $sizeArray['width'] );

        if ( $addDirData && ! empty( $this->imgFile ) ) {
            $dirname = _wp_get_attachment_relative_path( $this->imgFile );

            $sizeArray['dirname'] = trailingslashit( $dirname );

            $imageBaseurl = trailingslashit( app_upload_url( $sizeArray['dirname'] ) );

            if ( is_ssl()
                 && ! str_starts_with( $imageBaseurl, 'https' )
                 && parse_url( $imageBaseurl, PHP_URL_HOST ) === app_get_server_data( 'HTTP_HOST' ) ) {
                $imageBaseurl = set_url_scheme( $imageBaseurl, 'https' );
            }

            $sizeArray['image_baseurl'] = $imageBaseurl;
        }

        return $sizeArray;
    }

    private function getImageSrcset( string $attachmentSize = self::SIZE_FULL ): bool|string {
        $sizeData = $this->getSizeArray( $attachmentSize );

        // Bail early if error/no width.
        if ( 1 > $sizeData['width'] ) {
            return false;
        }

        if ( empty( $sizeData['dirname'] ) ) {
            return false;
        }

        if ( empty( $sizeData['image_baseurl'] ) ) {
            return false;
        }

        $sources = $this->calculateImageSecretSources( $sizeData );

        if ( empty( $sources ) ) {
            return false;
        }

        $srcset = [];

        foreach ( $sources as $source ) {
            $srcset[] = sprintf( '%s %d%s', $source['url'], $source['value'], $source['descriptor'] );
        }

        return implode( ', ', $srcset );
    }

    /**
     * @param array<string,boolean|int|string> $sizeData
     *
     * @return array<array-key, array{url: string, descriptor: string, value: int}>|false
     */
    private function calculateImageSecretSources( array $sizeData ): array|false {
        if ( empty( $sizeData['dirname'] ) ) {
            return false;
        }

        if ( empty( $sizeData['image_baseurl'] ) ) {
            return false;
        }

        // Retrieve the uploads sub-directory from the full size image.
        $dirname = (string) $sizeData['dirname'];

        $isImageEdited = preg_match( '#-e\d{13}#', wp_basename( (string) $sizeData['src'] ), $imageEditHash );

        $maxSrcsetImageWidth = (int) apply_filters( 'max_srcset_image_width', 2048, [
            $sizeData['width'],
            $sizeData['height'],
        ] );

        // Array to hold URL candidates.
        $sources = [];

        $srcMatched = false;

        if ( ! empty( $this->attachmentSizes ) ) {
            foreach ( $this->attachmentSizes as $attachmentSize ) {
                $isSrc = false;

                if ( ! \is_array( $attachmentSize ) ) {
                    continue;
                }

                // If the file name is part of the `src`, we've confirmed a match.
                if ( ! $srcMatched && str_contains( (string) $sizeData['src'], $dirname.$attachmentSize['file'] ) ) {
                    $srcMatched = true;
                    $isSrc = true;
                }

                if ( $isImageEdited && ! strpos( (string) $attachmentSize['file'], $imageEditHash[0] ) ) {
                    continue;
                }

                if ( $maxSrcsetImageWidth && (int) $attachmentSize['width'] > $maxSrcsetImageWidth && ! $isSrc ) {
                    continue;
                }

                // If the image dimensions are within 1px of the expected size, use it.
                if ( wp_image_matches_ratio( (int) $sizeData['width'], (int) $sizeData['height'], (int) $attachmentSize['width'], (int) $attachmentSize['height'] ) ) {
                    // Add the URL, descriptor, and value to the sources array to be returned.
                    $source = [
                        'url' => $sizeData['image_baseurl'].$attachmentSize['file'],
                        'descriptor' => 'w',
                        'value' => (int) $attachmentSize['width'],
                    ];

                    // The 'src' image has to be the first in the 'srcset', because of a bug in iOS8. See #35030.
                    if ( $isSrc ) {
                        $sources = array_merge( [ $attachmentSize['width'] => $source ], $sources );
                    } else {
                        $sources[ $attachmentSize['width'] ] = $source;
                    }
                }
            }
        }

        // Only return a 'srcset' value if there is more than one source.
        if ( ! $srcMatched ) {
            return false;
        }

        if ( empty( $sources ) ) {
            return false;
        }

        if ( \count( $sources ) < 2 ) {
            return false;
        }

        return $sources;
    }
}
