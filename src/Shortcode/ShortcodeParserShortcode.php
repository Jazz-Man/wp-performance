<?php

namespace JazzMan\Performance\Shortcode;

class ShortcodeParserShortcode
{
    /**
     * Name of shortcode.
     *
     * @example "gallery"
     *
     * @since 0.1.0
     *
     * @var string
     */
    public $shortcodeName;
    /**
     * Optional set of attributes from shortcode.
     *
     * @example null
     * @example array( 'columns' => 3 )
     *
     * @since 0.1.0
     *
     * @var array|null
     */
    public $attrs;
    /**
     * List of inner shortcodes (of this same class).
     *
     * @since 0.1.0
     *
     * @var ShortcodeParserShortcode[]
     */
    public $innerShortcodes;
    /**
     * Raw shortcode.
     *
     * @example "[shortcode]Just [test] testing[/shortcode]"
     *
     * @since 0.1.0
     *
     * @var string
     */
    public $rawTag;
    /**
     * Raw content of shortcode.
     *
     * @example "...Just [test] testing..."
     *
     * @since 0.1.0
     *
     * @var string
     */
    public $rawContent;
    /**
     * List of string fragments and null markers where nested shortcodes were found.
     *
     * @example array(
     *   'innerHTML'    => 'BeforeInnerAfter',
     *   'innerShortcodes'  => array( shortcode, shortcode ),
     *   'innerContent' => array( 'Before', null, 'Inner', null, 'After' ),
     * )
     *
     * @since 0.1.0
     *
     * @var array
     */
    public $innerContent;

    /**
     * Constructor.
     *
     * Will populate object properties from the provided arguments.
     *
     * @since 3.8.0
     *
     * @param string $name            name of shortcode
     * @param array  $attrs           optional set of attributes from shortcode
     * @param        $innerShortcodes
     * @param        $rawTag
     * @param string $rawContent      raw content of a shortcode, including all nested shortcodes
     * @param array  $innerContent    list of string fragments and null markers where nested shortcodes were found
     */
    public function __construct($name, $attrs, $innerShortcodes, $rawTag, $rawContent, $innerContent)
    {
        $this->shortcodeName = $name;
        $this->attrs = $attrs;
        $this->innerShortcodes = $innerShortcodes;
        $this->rawTag = $rawTag;
        $this->rawContent = $rawContent;
        $this->innerContent = $innerContent;
    }
}
