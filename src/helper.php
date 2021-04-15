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
        $image = [
            'id' => false,
            'size' => $size,
        ];

        if (\is_array($size)) {
            $_image = wp_get_attachment_image_src($attachment_id, $size);

            if (!empty($_image)) {
                [$url, $width, $height] = $_image;

                $alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);

                $image['url'] = $url;
                $image['width'] = $width;
                $image['height'] = $height;
                $image['alt'] = $alt;
            }
        } else {
            try {
                $attachment = new AttachmentData($attachment_id);

                $_image = $attachment->getUrl($size);

                $image['id'] = $attachment_id;
                $image['url'] = $_image['src'];
                $image['width'] = $_image['width'];
                $image['height'] = $_image['height'];
                $image['alt'] = $attachment->getImageAlt();
            } catch (\Exception $exception) {
                $image['id'] = false;
            }
        }

        if (false === $image['id']) {
            return false;
        }

        return $image;
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
     * @param  string|array  $attr
     *
     * @return string
     */
    function app_get_attachment_image(int $attachment_id, string $size = AttachmentData::SIZE_THUMBNAIL, $attr = ''): string
    {
        try {
            $_image = app_get_image_data_array($attachment_id, $size);

            if (empty($_image)){
                throw new Exception(sprintf('Image not fount: attachment_id "%d", size "%s"',$attachment_id,$size));
            }

            $size_class = $size;

            $default_attr = [
                'src' => $_image['url'],
                'class' => "attachment-{$size_class} size-{$size_class}",
                'alt' => app_trim_string(\strip_tags($_image['alt'])),
            ];

            if ($_image['width']){
                $default_attr['width'] =  (int)$_image['width'];
            }
            if ($_image['height']){
                $default_attr['height'] =  (int)$_image['height'];
            }

            // Add `loading` attribute.
            if (wp_lazy_loading_enabled('img', 'wp_get_attachment_image')) {
                $default_attr['loading'] = 'lazy';
            }

            $attr = wp_parse_args($attr, $default_attr);

            // If the default value of `lazy` for the `loading` attribute is overridden
            // to omit the attribute for this image, ensure it is not included.
            if (\array_key_exists('loading', $attr) && !$attr['loading']) {
                unset($attr['loading']);
            }

//            if ( empty( $attr['srcset'] ) ) {
//                $srcset = $attachment->getImageSrcset(AttachmentData::SIZES_JPEG,$size);
//                if (!empty($srcset)){
//                    $attr['srcset'] = $srcset;
//                }
//            }

            $post = get_post($attachment_id);

            $attr = apply_filters('wp_get_attachment_image_attributes', $attr, $post, $size);

            $html = sprintf('<img %s/>',app_add_attr_to_el($attr));
        } catch (\Exception $exception) {
            $html = '';
            app_error_log($exception,__FUNCTION__);
        }

        return $html;
    }
}