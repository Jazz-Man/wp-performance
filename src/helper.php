<?php

use JazzMan\Performance\Utils\AttachmentData;

if (!\function_exists('app_get_image_data_array')) {
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

        if (\is_array($size)) {
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
            } catch (\Exception $exception) {
                app_error_log($exception, 'app_get_image_data_array');

                return false;
            }
        }

        return false;
    }
}

if (!\function_exists('app_get_attachment_image_url')) {
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
        } catch (\Exception $exception) {
            return false;
        }
    }
}

if (!\function_exists('app_get_attachment_image')) {
    /**
     * @param  int  $attachment_id
     * @param  string  $size
     * @param  array|string  $attributes
     * @return string
     */
    function app_get_attachment_image(int $attachment_id, string $size = AttachmentData::SIZE_THUMBNAIL, $attributes = ''): string
    {
        try {
            $image = app_get_image_data_array($attachment_id, $size);

            if (empty($image)) {
                throw new Exception(sprintf('Image not fount: attachment_id "%d", size "%s"', $attachment_id, $size));
            }

            $defaultAttributes = [
                'src' => $image['url'],
                'class' => sprintf('attachment-%1$s size-%1$s', $size),
                'alt' => app_trim_string(\strip_tags($image['alt'])),
            ];

            if ($image['width']) {
                $defaultAttributes['width'] = (int) $image['width'];
            }
            if ($image['height']) {
                $defaultAttributes['height'] = (int) $image['height'];
            }

            // Add `loading` attribute.
            if (wp_lazy_loading_enabled('img', 'wp_get_attachment_image')) {
                $defaultAttributes['loading'] = 'lazy';
            }

            $attributes = wp_parse_args($attributes, $defaultAttributes);

            // If the default value of `lazy` for the `loading` attribute is overridden
            // to omit the attribute for this image, ensure it is not included.
            if (\array_key_exists('loading', $attributes) && !$attributes['loading']) {
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
        } catch (\Exception $exception) {
            app_error_log($exception, __FUNCTION__);

            return '';
        }
    }
}
