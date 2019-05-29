<?php

namespace JazzMan\Performance\Shortcode;

/**
 * Class ShortcodeParser.
 */
class ShortcodeParser
{
    /**
     * flag enabling debug output.
     *
     * @since 0.1.0
     *
     * @var bool
     */
    public $debug;
    /**
     * Input document being parsed.
     *
     * @example "Pre-text\n[shortcode att="example"]This is inside a shortcode![/shortcode]"
     *
     * @since 0.1.0
     *
     * @var string
     */
    public $document;
    /**
     * Tracks parsing progress through document.
     *
     * @since 0.1.0
     *
     * @var int
     */
    public $offset;
    /**
     * List of parsed shortcodes0.
     *
     * @since 0.1.0
     *
     * @var ShortcodeParserShortcode[]
     */
    public $output;
    /**
     * Stack of partially-parsed structures in memory during parse.
     *
     * @since 0.1.0
     *
     * @var ShortcodeParserFrame[]
     */
    public $stack;

    /**
     * ShortcodeParser constructor.
     *
     * @param bool $debug
     */
    public function __construct($debug = false)
    {
        $this->debug = $debug;
    }

    /**
     * Parses a document and returns a list of shortcode structures.
     *
     * When encountering an invalid parse will return a best-effort
     * parse. In contrast to the specification parser this does not
     * return an error on invalid inputs.
     *
     * @since 0.1.0
     *
     * @param string $document input document being parsed
     *
     * @return ShortcodeParserShortcode[]
     */
    public function parse($document)
    {
        $this->document = $document;
        $this->offset = 0;
        $this->output = [];
        $this->stack = [];
        do {
            // twiddle our thumbs.
        } while ($this->proceed());

        return $this->output;
    }

    /**
     * Processes the next token from the input document
     * and returns whether to proceed eating more tokens.
     *
     * This is the "next step" function that essentially
     * takes a token as its input and decides what to do
     * with that token before descending deeper into a
     * nested shortcode tree or continuing along the document
     * or breaking out of a level of nesting.
     *
     * @internal
     *
     * @since 0.1.0
     *
     * @return bool
     */
    public function proceed()
    {
        $next_token = $this->nextToken();
        $token_type = $next_token['token_type'];
        $shortcode_name = $next_token['shortcode_name'];
        $attrs = $next_token['attrs'];
        $start_offset = $next_token['start_offset'];
        $token_length = $next_token['token_length'];
        $rawToken = substr($this->document, $start_offset, $token_length);
        $stack_depth = \count($this->stack);
        // we may have some HTML soup before the next shortcode.
        $leading_html_start = null;
        if ($start_offset > $this->offset) {
            $leading_html_start = $this->offset;
        }
        switch ($token_type) {
            case 'no-more-tokens':
                // if not in a shortcode then flush output.
                if (0 === $stack_depth) {
                    $this->addFreeform();

                    return false;
                }
                // NOTE: THIS NEEDS TO BE REPLACED, but will work for testing.
                /*
                 * for the nested case where it's more difficult we'll
                 * have to assume that multiple closers are missing
                 * and so we'll collapse the whole stack piecewise
                 */
                while (0 < \count($this->stack)) {
                    $this->addShortcodeFromStack();
                }

                return false;
            case 'escaped-shortcode':
                $this->output[] = (array) $this->freeform(
                    substr(
                        $this->document,
                        $start_offset + 1,
                        $token_length - 2
                    )
                );
                $this->offset = $start_offset + $token_length;

                return true;
            case 'void-shortcode':
                /*
                 * easy case is if we stumbled upon a void shortcode
                 * in the top-level of the document
                 */
                if (0 === $stack_depth) {
                    if (isset($leading_html_start)) {
                        $this->output[] = (array) $this->freeform(
                            substr(
                                $this->document,
                                $leading_html_start,
                                $start_offset - $leading_html_start
                            )
                        );
                    }
                    $this->output[] = (array) new ShortcodeParserShortcode($shortcode_name, $attrs, [], $rawToken, '', []);
                    $this->offset = $start_offset + $token_length;

                    return true;
                }
                // otherwise we found an inner shortcode.
                $this->addInnerShortcode(
                    new ShortcodeParserShortcode($shortcode_name, $attrs, [], $rawToken, '', []),
                    $start_offset,
                    $token_length
                );
                $this->offset = $start_offset + $token_length;

                return true;
            case 'shortcode-opener':
                // track all newly-opened shortcodes on the stack.
                $this->stack[] = new ShortcodeParserFrame(new ShortcodeParserShortcode($shortcode_name,
                    $attrs, [], $rawToken, '', []), $start_offset, $token_length, $start_offset + $token_length,
                    $leading_html_start);
                $this->offset = $start_offset + $token_length;

                return true;
            case 'shortcode-closer':
                /*
                 * if we're missing an opener we're in trouble
                 * This is an error
                 */
                if (0 === $stack_depth) {
                    $this->addFreeform($token_length);

                    return false;
                }
                $stack_position = $this->findLastInStack($shortcode_name);
                if (false === $stack_position) {
                    $this->addFreeform($token_length);

                    return true;
                }
                $this->reflowToSelfClosing($stack_position);
                $stack_depth = \count($this->stack);
                // if we're not nesting then this is easy - close the block.
                if (1 === $stack_depth) {
                    $this->addShortcodeFromStack($start_offset, $start_offset + $token_length);
                    $this->offset = $start_offset + $token_length;

                    return true;
                }
                $stack_top = array_pop($this->stack);
                $rawTag = substr($this->document, $stack_top->token_start, $start_offset + $token_length - $stack_top->token_start);
                $stack_top->shortcode->rawTag = $rawTag;
                $start_of_content = $stack_top->token_start + $stack_top->token_length;
                $rawContent = substr($this->document, $start_of_content, $start_offset - $start_of_content);
                $stack_top->shortcode->rawContent = $rawContent;
                $html = substr($this->document, $stack_top->prev_offset, $start_offset - $stack_top->prev_offset);
                $stack_top->shortcode->innerContent[] = $html;
                $stack_top->prev_offset = $start_offset + $token_length;
                $this->addInnerShortcode(
                    $stack_top->shortcode,
                    $stack_top->token_start,
                    $stack_top->token_length,
                    $start_offset + $token_length
                );
                $this->offset = $start_offset + $token_length;

                return true;
            default:
                // This is an error.
                $this->addFreeform();

                return false;
        }
    }

    /**
     * Scans the document from where we last left off
     * and finds the next valid token to parse if it exists.
     *
     * Returns the type of the find: kind of find, shortcode information, attributes
     *
     * @internal
     *
     * @since 0.1.0
     *
     * @return array
     */
    public function nextToken()
    {
        $matches = null;
        $regex = $this->buildTokenizerRegex();
        $has_match = preg_match(
            $regex,
            $this->document,
            $matches,
            PREG_OFFSET_CAPTURE,
            $this->offset
        );
        // no more matches
        if (false === $has_match || 0 === $has_match) {
            return [
                'token_type' => 'no-more-tokens',
                'shortcode_name' => null,
                'attrs' => null,
                'start_offset' => null,
                'token_length' => null,
            ];
        }
        list($match, $started_at) = $matches[0];
        $length = \strlen($match);
        $is_escaped = isset($matches['escleft']) && -1 !== $matches['escleft'][1]
                      && isset($matches['escright']) && -1 !== $matches['escright'][1];
        $is_closer = isset($matches['closer']) && -1 !== $matches['closer'][1];
        $is_void = isset($matches['void']) && -1 !== $matches['void'][1];
        $name = $matches['name'][0];
        $has_attrs = isset($matches['attrs']) && -1 !== $matches['attrs'][1];
        $attrs = $has_attrs
            ? $this->decodeAttributes($matches['attrs'][0])
            : [];
        $type = 'error';
        if ($is_escaped) {
            $type = 'escaped-shortcode';
        } elseif ($is_void) {
            $type = 'void-shortcode';
        } elseif ($is_closer) {
            $type = 'shortcode-closer';
            $attrs = null; // closing tags don't have attributes
        } else {
            $type = 'shortcode-opener';
        }
        $this->debug($type.':'.$name, [$started_at, $started_at + $length]);

        return [
            'token_type' => $type,
            'shortcode_name' => $name,
            'attrs' => $attrs,
            'start_offset' => $started_at,
            'token_length' => $length,
        ];
    }

    /**
     * Returns the tokenizer regex used to identify shortcodes in the content.
     *
     * @internal
     *
     * @since 0.1.0
     *
     * @return string tokenizer regular expression
     */
    public function buildTokenizerRegex()
    {
        /*
         * aye the magic
         * we're using a single RegExp to tokenize the shortcode delimiters
         * we're also using a trick here because the only difference between a
         * shortcode opener and a shortcode closer is the leading `/` (and
         * a closer has no attributes). we can trap them both and process the
         * match back in PHP to see which one it was.
         */
        global $shortcode_tags;
        $tagnames = [];
        if (empty($tagnames)) {
            $tagnames = array_keys($shortcode_tags);
        }
        $tags = implode('|', array_map('preg_quote', $tagnames));
        $regex = '' // blank line to improve readability
                 .'/\\['                   // open bracket
                 .'(?<escleft>\\[)?'       // optional second bracket to escape shortcode
                 .'(?<closer>\\/)?'        // if this is a closing tag, it starts with a slash
                 .'\\s*'                   // optional whitespace
                 .'(?<name>'.$tags.')' // the shortcode name
                 .'(?![\\w-])'             // the shortcode name must not be followed by more word-like characters
                 // NOTE: this portion is lifted from WordPress's existing regex, but not fully understood
                 .'(?<attrs>'              // Unroll the loop: Inside the opening shortcode tag
                 .'[^\\]\\/]*'         // Not a closing bracket or forward slash
                 .'(?:'
                 .'\\/(?!\\])'     // A forward slash not followed by a closing bracket
                 .'[^\\]\\/]*'     // Not a closing bracket or forward slash
                 .')*?'
                 .')'
                 // END NOTE
                 .'(?<void>\\/)?'
                 .'(?<escright>\\])?'      // optional second close bracket to escape shortcode
                 .'\\]/s';                 // close bracket
        return $regex;
    }

    /**
     * Returns a new shortcode object for freeform HTML.
     *
     * @internal
     *
     * @since 0.1.0
     *
     * @param string $rawContent HTML content of shortcode
     *
     * @return ShortcodeParserShortcode freeform shortcode object
     */
    public function freeform($rawContent)
    {
        return new ShortcodeParserShortcode(null, [], [], $rawContent, $rawContent, [$rawContent]);
    }

    /**
     * Pushes a length of text from the input document
     * to the output list as a freeform shortcode.
     *
     * @internal
     *
     * @since 0.1.0
     *
     * @param null $length how many bytes of document text to output
     */
    public function addFreeform($length = null)
    {
        $length = $length ?: \strlen($this->document) - $this->offset;
        if (0 === $length) {
            return;
        }
        $this->output[] = (array) $this->freeform(substr($this->document, $this->offset, $length));
    }

    /**
     * Given a shortcode structure from memory pushes
     * a new shortcode to the output list.
     *
     * @internal
     *
     * @since 3.8.0
     *
     * @param ShortcodeParserShortcode $shortcode    the shortcode to add to the output
     * @param int                      $token_start  byte offset into the document where the first token for the shortcode starts
     * @param int                      $token_length byte length of entire shortcode from start of opening token to end of closing token
     * @param int|null                 $last_offset  last byte offset into document if continuing form earlier output
     */
    public function addInnerShortcode(ShortcodeParserShortcode $shortcode, $token_start, $token_length, $last_offset = null)
    {
        $parent = $this->stack[\count($this->stack) - 1];
        $parent->shortcode->innerShortcodes[] = (array) $shortcode;
        $html = substr($this->document, $parent->prev_offset, $token_start - $parent->prev_offset);
        if (!empty($html)) {
            $parent->shortcode->innerContent[] = $html;
        }
        $parent->shortcode->innerContent[] = null;
        $rawTag = substr($this->document, $parent->token_start, $token_start + $token_length - $parent->token_start);
        $parent->shortcode->rawTag = $rawTag;
        $start_of_content = $parent->token_start + $parent->token_length;
        $parent->shortcode->rawContent = substr($this->document, $start_of_content, $token_start - $start_of_content);
        $parent->prev_offset = $last_offset ?: $token_start + $token_length;
    }

    /**
     * Pushes the top shortcode from the parsing stack to the output list.
     *
     * @internal
     *
     * @since 3.8.0
     *
     * @param int|null $end_offset byte offset into document for where we should stop sending text output as HTML
     * @param null     $end_tag
     */
    public function addShortcodeFromStack($end_offset = null, $end_tag = null)
    {
        $stack_top = array_pop($this->stack);
        $prev_offset = $stack_top->prev_offset;
        $html = isset($end_offset)
            ? substr($this->document, $prev_offset, $end_offset - $prev_offset)
            : substr($this->document, $prev_offset);
        if (!empty($html)) {
            $stack_top->shortcode->innerContent[] = $html;
        }
        $rawTag = isset($end_tag)
            ? substr($this->document, $stack_top->token_start, $end_tag - $stack_top->token_start)
            : substr($this->document, $stack_top->token_start);
        if (!empty($rawTag)) {
            $stack_top->shortcode->rawTag = $rawTag;
        }
        $start_of_content = $stack_top->token_start + $stack_top->token_length;
        $content = isset($end_offset)
            ? substr($this->document, $start_of_content, $end_offset - $start_of_content)
            : substr($this->document, $start_of_content);
        if (!empty($content)) {
            $stack_top->shortcode->rawContent = $content;
        }
        if (isset($stack_top->leading_html_start)) {
            $this->output[] = (array) $this->freeform(
                substr(
                    $this->document,
                    $stack_top->leading_html_start,
                    $stack_top->token_start - $stack_top->leading_html_start
                )
            );
        }
        $this->output[] = (array) $stack_top->shortcode;
    }

    /**
     * @param string $shortcode_name
     *
     * @return bool|int
     */
    public function findLastInStack($shortcode_name = '')
    {
        $last_index = \count($this->stack) - 1;
        for ($i = $last_index; $i >= 0; --$i) {
            $name = $this->stack[$i]->shortcode->shortcodeName;
            if ($name === $shortcode_name) {
                return $i;
            }
        }

        return false;
    }

    /**
     * @param int $index
     */
    public function reflowToSelfClosing($index = 0)
    {
        $to_reflow = array_splice($this->stack, $index + 1);
        foreach ($to_reflow as $stack_entry) {
            $this->debug('Reflowing to close: '.$stack_entry->shortcode->shortcodeName, [$stack_entry->token_start, $stack_entry->token_start + $stack_entry->token_length]);
            $this->addInnerShortcode(
                $stack_entry->shortcode,
                $stack_entry->token_start,
                $stack_entry->token_length
            );
        }
    }

    /**
     * @param $raw_atts
     *
     * @return array|string
     */
    public function decodeAttributes($raw_atts)
    {
        return shortcode_parse_atts($raw_atts);
    }

    /**
     * @param      $message
     * @param      $markers
     * @param bool $echo
     *
     * @return string
     */
    private function debug($message, $markers, $echo = true)
    {
        if (!$this->debug) {
            return;
        }
        if (!\is_array($markers)) {
            $markers = [$markers];
        }
        $markers[] = \strlen($this->document) - 1;
        $parts = [];
        $last_index = 0;
        foreach ($markers as $marker) {
            $part = substr($this->document, $last_index, $marker - $last_index);
            $part = htmlspecialchars($part);
            $part = str_replace(["\n", "\r", "\t"], ['\\n', '\\r', '\\t'], $part);
            $parts[] = $part;
            $last_index = $marker;
        }
        $output = "\n";
        $output .= '<div class="shortcode-parser-debug">';
        $output .= '<div class="spb-header">DEBUG: '.htmlspecialchars($message).'</div>'."\n";
        $output .= '<pre class="spd-body">';
        $output .= implode('<span class="spb-marker">|</span>', $parts);
        $output .= '</pre></div>'."\n";
        if ($echo) {
            echo $output;
        }

        return $output;
    }
}
