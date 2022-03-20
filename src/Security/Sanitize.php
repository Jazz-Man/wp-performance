<?php

namespace JazzMan\Performance\Security;

use JazzMan\AutoloadInterface\AutoloadInterface;

/**
 * Class Sanitizer.
 */
class Sanitize implements AutoloadInterface {
    public function load(): void {
        // Remove accents from all uploaded files

        add_filter('sanitize_html_class', 'app_string_slugify');
        add_filter('sanitize_file_name', [__CLASS__, 'sanitizeFilename']);
        add_filter('sanitize_email', [__CLASS__, 'sanitizeEmail']);
    }

    /**
     * Removes all accents from string.
     *
     * @param string $fileName - any filename with absolute path
     */
    public static function sanitizeFilename(string $fileName): string {
        // Get path and basename
        $fileInfo = pathinfo($fileName);

        $fileExt = !empty($fileInfo['extension']) ? '.'.strtolower($fileInfo['extension']) : '';

        // Trim beginning and ending seperators
        $fileName = app_string_slugify($fileInfo['filename']).$fileExt;

        if ('.' !== $fileInfo['dirname']) {
            $fileName = $fileInfo['dirname'].'/'.$fileName;
        }

        // Return full path
        return $fileName;
    }

    public static function sanitizeEmail(string $sanitizedEmail): string {
        if (!empty($sanitizedEmail) && filter_var($sanitizedEmail, FILTER_VALIDATE_EMAIL)) {
            /** @var false|string $email */
            $email = filter_var($sanitizedEmail, FILTER_SANITIZE_EMAIL);

            return !empty($email) ? $email : $sanitizedEmail;
        }

        return $sanitizedEmail;
    }
}
