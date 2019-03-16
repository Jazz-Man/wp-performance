<?php

namespace JazzMan\Performance\Shortcode;

/**
 * Class ShortcodeParserFrame.
 */
class ShortcodeParserFrame
{
    /**
     * Full or partial shortcode.
     *
     * @since 0.1.0
     *
     * @var ShortcodeParserShortcode
     */
    public $shortcode;
    /**
     * Byte offset into document for start of parse token.
     *
     * @since 0.1.0
     *
     * @var int
     */
    public $token_start;
    /**
     * Byte length of entire parse token string.
     *
     * @since 0.1.0
     *
     * @var int
     */
    public $token_length;
    /**
     * Byte offset into document for after parse token ends
     * (used during reconstruction of stack into parse production).
     *
     * @since 0.1.0
     *
     * @var int
     */
    public $prev_offset;
    /**
     * Byte offset into document where leading HTML before token starts.
     *
     * @since 0.1.0
     *
     * @var int
     */
    public $leading_html_start;

    /**
     * Constructor.
     *
     * Will populate object properties from the provided arguments.
     *
     * @since 0.1.0
     *
     * @param ShortcodeParserShortcode $shortcode          full or partial block
     * @param int                      $token_start        byte offset into document for start of parse token
     * @param int                      $token_length       byte length of entire parse token string
     * @param int                      $prev_offset        byte offset into document for after parse token ends
     * @param int                      $leading_html_start byte offset into document where leading HTML before token starts
     */
    public function __construct(ShortcodeParserShortcode $shortcode, $token_start, $token_length, $prev_offset = null, $leading_html_start = null)
    {
        $this->shortcode = $shortcode;
        $this->token_start = $token_start;
        $this->token_length = $token_length;
        $this->prev_offset = $prev_offset ?? $token_start + $token_length;
        $this->leading_html_start = $leading_html_start;
    }
}
