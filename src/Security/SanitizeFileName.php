<?php

namespace JazzMan\Performance\Security;

use JazzMan\AutoloadInterface\AutoloadInterface;
use Normalizer;

/**
 * Class Sanitizer.
 *
 * @see https://github.com/devgeniem/wp-sanitize-accented-uploads
 */
class SanitizeFileName implements AutoloadInterface {
    public function load(): void {
        // Remove accents from all uploaded files

        add_filter('sanitize_file_name', fn (string $filename): string => $this->sanitizeFilenamesOnUpload($filename));
    }

    /**
     * Tries to move any version of NFC & NFD unicode compositions of $old_file
     * Also tries to fix typical unix file encoding errors.
     *
     * @param $oldFile - full path to original file
     * @param $newFile - full path to new file
     *
     * @return bool|mixed - return the name of the file which could be found
     */
    public static function renameAccentedFilesInAnyForm(string $oldFile, string $newFile) {
        // Try to move the file without any hacks before continuing
        $result = rename($oldFile, $newFile);

        if ($result) {
            return $oldFile;
        }

        if (!file_exists($newFile)) {
            // Continue if we couldn't rename $old_file and $new_file doesn't yet exist

            $possibleOldFiles = [
                Normalizer::normalize($oldFile), // This is the normal way to do unicode
                Normalizer::normalize($oldFile, Normalizer::FORM_D), // This is the OS-X way to do unicode
                // Even try to fix files which have already been corrupted
                // by creating encoding errors on purpose
                self::replaceFilenameWithEncodingErrors($oldFile),
            ];

            foreach ($possibleOldFiles as $possibleOldFile) {
                // Rename the file if it exists, ignore errors because this is encoding bingo
                $result = rename($possibleOldFile, $newFile);

                // Stop immediately if we found a solution
                if ($result) {
                    return $possibleOldFile;
                }
            }
        }

        // Return false if it didn't find a file earlier
        return false;
    }

    /**
     * Creates counterpart for filename with encoding errors.
     *
     * @param string $filename - possibly corrupted filename
     *
     * @return string - filename with encoding errors
     */
    public static function replaceFilenameWithEncodingErrors(string $filename): string {
        // Get associative array of fixes
        $fixList = self::getEncodingFixList();

        // Check if developer filtered all of these away
        if (empty($fixList)) {
            return $filename;
        }

        // Get encoding errors
        $errorChars = array_keys($fixList);
        $realChars = array_values($fixList);

        // The errors can happen in both nfd/nfc format so convert chars into nfd
        // Check which one $filename uses and use it
        // If $filename doesn't use FORM_D or FORM_C don't convert errors
        if (Normalizer::isNormalized($filename, Normalizer::FORM_D)) {
            $errorChars = array_map(static fn ($string) => Normalizer::normalize($string, Normalizer::FORM_D), $errorChars);
        } elseif (Normalizer::isNormalized($filename)) {
            $errorChars = array_map(static fn ($string) => Normalizer::normalize($string), $errorChars);
        }

        // Replaces all accented characters with encoding errors
        return str_replace($realChars, $errorChars, $filename);
    }

    /**
     * This is a list of usual encoding errors.
     *
     * @return string[]
     *
     * @psalm-return array<string, string>
     */
    public static function getEncodingFixList(): array {
        // source: http://www.i18nqa.com/debug/utf8-debug.html,
        return [
            // 3 char errors first
            'â€š' => '‚',
            'â€ž' => '„',
            'â€¦' => '…',
            'â€¡' => '‡',
            'â€°' => '‰',
            'â€¹' => '‹',
            'â€˜' => '‘',
            'â€™' => '’',
            'â€œ' => '“',
            'â€¢' => '•',
            'â€“' => '–',
            'â€”' => '—',
            'â„¢' => '™',
            'â€º' => '›',
            'â‚¬' => '€',

            // 2 char errors
            'Ã‚' => 'Â',
            'Æ’' => 'ƒ',
            'Ãƒ' => 'Ã',
            'Ã„' => 'Ä',
            'Ã…' => 'Å',
            'â€' => '†',
            'Ã†' => 'Æ',
            'Ã‡' => 'Ç',
            'Ë†' => 'ˆ',
            'Ãˆ' => 'È',
            'Ã‰' => 'É',
            'ÃŠ' => 'Ê',
            'Ã‹' => 'Ë',
            'Å’' => 'Œ',
            'ÃŒ' => 'Ì',
            'Å½' => 'Ž',
            'ÃŽ' => 'Î',
            'Ã‘' => 'Ñ',
            'Ã’' => 'Ò',
            'Ã“' => 'Ó',
            'â€' => '”',
            'Ã”' => 'Ô',
            'Ã•' => 'Õ',
            'Ã–' => 'Ö',
            'Ã—' => '×',
            'Ëœ' => '˜',
            'Ã˜' => 'Ø',
            'Ã™' => 'Ù',
            'Å¡' => 'š',
            'Ãš' => 'Ú',
            'Ã›' => 'Û',
            'Å“' => 'œ',
            'Ãœ' => 'Ü',
            'Å¾' => 'ž',
            'Ãž' => 'Þ',
            'Å¸' => 'Ÿ',
            'ÃŸ' => 'ß',
            'Â¡' => '¡',
            'Ã¡' => 'á',
            'Â¢' => '¢',
            'Ã¢' => 'â',
            'Â£' => '£',
            'Ã£' => 'ã',
            'Â¤' => '¤',
            'Ã¤' => 'ä',
            'Â¥' => '¥',
            'Ã¥' => 'å',
            'Â¦' => '¦',
            'Ã¦' => 'æ',
            'Â§' => '§',
            'Ã§' => 'ç',
            'Â¨' => '¨',
            'Ã¨' => 'è',
            'Â©' => '©',
            'Ã©' => 'é',
            'Âª' => 'ª',
            'Ãª' => 'ê',
            'Â«' => '«',
            'Ã«' => 'ë',
            'Â¬' => '¬',
            'Ã¬' => 'ì',
            'Â®' => '®',
            'Ã®' => 'î',
            'Â¯' => '¯',
            'Ã¯' => 'ï',
            'Â°' => '°',
            'Ã°' => 'ð',
            'Â±' => '±',
            'Ã±' => 'ñ',
            'Â²' => '²',
            'Ã²' => 'ò',
            'Â³' => '³',
            'Ã³' => 'ó',
            'Â´' => '´',
            'Ã´' => 'ô',
            'Âµ' => 'µ',
            'Ãµ' => 'õ',
            'Â¶' => '¶',
            'Ã¶' => 'ö',
            'Â·' => '·',
            'Ã·' => '÷',
            'Â¸' => '¸',
            'Ã¸' => 'ø',
            'Â¹' => '¹',
            'Ã¹' => 'ù',
            'Âº' => 'º',
            'Ãº' => 'ú',
            'Â»' => '»',
            'Ã»' => 'û',
            'Â¼' => '¼',
            'Ã¼' => 'ü',
            'Â½' => '½',
            'Ã½' => 'ý',
            'Â¾' => '¾',
            'Ã¾' => 'þ',
            'Â¿' => '¿',
            'Ã¿' => 'ÿ',
            'Ã€' => 'À',

            // 1 char errors last
            'Ã' => 'Á',
            'Å' => 'Š',
            'Ã' => 'Í',
            'Ã' => 'Ï',
            'Ã' => 'Ð',
            'Ã' => 'Ý',
            'Ã' => 'à',
            'Ã­' => 'í',
        ];
    }

    /**
     * Replaces all files immediately on upload.
     */
    public function sanitizeFilenamesOnUpload(string $filename): string {
        // Remove accents and filename to lowercase for better urls
        // Don't sanitize file here because wordpress does this automatically
        return strtolower(self::removeAccents($filename, false));
    }

    /**
     * Removes all accents from string.
     *
     * @param string $fileName - any filename with absolute path
     * @param bool   $sanitize - Sanitized all special characters as well?
     */
    public static function removeAccents(string $fileName, bool $sanitize = true): string {
        // Get path and basename
        $fileInfo = pathinfo($fileName);
        $fileName = $fileInfo['basename'];

        // If available remove all NFD characters before doing anything else
        if (class_exists('Normalizer')) {
            $fileName = Normalizer::normalize($fileName);
        }

        // Removes accents using wordpress function
        $fileName = remove_accents($fileName);

        if ($sanitize) {
            // Sanitize special characters for files so that they work in urls
            $fileName = sanitize_file_name($fileName);
        }

        // And then just remove anything fancy like ¼ and ™
        $fileName = self::removeNonASCIICharacters($fileName);

        // If this was full path return it like it was before
        // pathinfo returns . for only filenames
        if ('.' !== $fileInfo['dirname']) {
            $fileName = $fileInfo['dirname'] . '/' . $fileName;
        }

        // Return full path
        return $fileName;
    }

    /**
     * Removes all non-ascii characters.
     */
    public static function removeNonASCIICharacters(string $string): ?string {
        return preg_replace("/[^(\x20-\x7F)]*/", '', $string);
    }
}
