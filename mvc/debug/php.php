<?php
/**
 * Classic PHP debugging system (using output displayed on the screen, directly in page)
 * -- Not very efficient when using templates, as the errors would be generated even before the first byte of output is displayed
 * -- Uses GeSHi class, but at the moment not included in the library ( geshi.php, /geshi )
 *
 * @author Wavetrex (wavetrex@gmail.com)
 * @package CargoClaims
 * @version 1.0.dev
 */
class MVC_Debug_PHP implements MVC_Debug_base
{
    /**
     * The translated debug level (0 - 3)
     */
    public int $level = 0;

    /**
     * The template manager object used to display debug data in-page (dumps, errors, exceptions)
     */
    protected Drytemplate $drytpl;

    /**
     * An array containing all debug output (which can be displayed inside the application in a nice way, without interfering with the design)
     * -- Contains debug "output_elements" (arrays)
     */
    public array $buffer = [];

    /**
     * Constructor, initializes the debugging system
     *
     * @param int $level Debug level (0 - 3)
     * @param array $settings Additional settings
     */
    public function __construct(int $level = 0, array $settings = [])
    {
        $this->level = $level;
        if ($level > 0) {
            // Errors are always hidden (as they are captured)
            ini_set('display_errors', 'stderr');
            error_reporting(0);

            $CFG = new stdClass();
            $CFG->paths = [
                'templates' => [
                    ROOT . DS . 'app' . DS . 'view' . DS . 'debug'
                ]
            ];
            $this->drytpl = new Drytemplate($CFG);

            match ($level) {
                1 => null, // Just errors
                2 => null, // Errors and warnings, but not notices
                3 => null, // Everything
                default => null,
            };
        } else {
            // No errors displayed, at all, completely hides debug from the user (production mode)
            ini_set('display_errors', 'stderr');
            error_reporting(0);
        }
    }

    /**
     * Displays arbitrary data (variable) on the page (using "echo"). Still, this dump can be captured using ob_{functions}
     *
     * @param array $el Array with information to be displayed
     * @param int|null $time Number of milliseconds to be placed in front of the label (optional)
     * @param bool $buffered Whether to buffer the output or display it immediately
     */
    #[\Override]
    public function output(array $el, ?int $time = null, bool $buffered = true): void
    {
        if ($time !== null) {
            $el['time'] = sprintf("%.3fms ", $time);
        }

        // TODO: Make this function nicer, and use a custom variable display that shows arrays and objects in a colorful, easy-to-see way (instead of var_dump())
        // TODO: If $el['data'] instanceof Exception ... display it some other way (maybe with a template?) - Instead of var_dump()
        ob_start();
        var_dump($el['data']);
        $el['var_dump'] = htmlentities(ob_get_clean());

        if (isset($el['show_source'])) {
            $el = array_merge($el, $this->load_Source_Fragment($el['meta']['File'], $el['meta']['Line']));
        }

        if ($buffered) {
            $this->buffer[] = $el;
        } else {
            // Not buffered, dump the output (for raw testing)
            echo $this->drytpl->render_File("dump.html", $el);
        }
    }

    /**
     * Returns the buffered output, formatted
     *
     * @param string $template The template to use for rendering
     * @return string The rendered output
     */
    public function render_Buffer(string $template = "multi_dump.html"): string
    {
        return $this->drytpl->render_File($template, $this->buffer);
    }

    /**
     * Loads a fragment of the source code and optionally colorizes it for display (useful to locate where a problem occurred)
     *
     * @param string $file Name of the PHP file which contains the source
     * @param int $mark Line inside the source where the fragment is centered (and is being marked as the cause of the debug output)
     * @param int $lines Number of lines to display
     * @param bool $colorize Use highlight_string() to colorize PHP code
     * @return array An array containing the line numbers and source code fragment
     */
    public function load_Source_Fragment(string $file, int $mark, int $lines = 15, bool $colorize = true): array
    {
        if (!file_exists($file)) {
            return []; // The source file was not found
        }

        $source_lines = file($file);

        // Calculate start and end line for source (line numbers start at 1, not 0)
        $source_size = count($source_lines);
        if ($source_size < $lines) {
            $lines = $source_size;
        }

        $begin = (int)($mark - ($lines - 1) / 2);
        if ($begin < 1) {
            $begin = 1;
            $end = $begin + $lines - 1;
        } else {
            // Not clamped in the beginning
            $end = (int)ceil($mark + ($lines - 1) / 2);
            if ($end > $source_size) {
                $end = $source_size;
                $begin = $end - $lines + 1;
            }
        }

        // Cut the part of the source array that is of interest to us
        $source_lines = array_slice($source_lines, $begin - 1, $lines);

        // Make a list of all line numbers (that will be displayed before the code)
        $line_numbers = [];
        foreach ($source_lines as $line => &$code) {
            if ($line + $begin == $mark) {
                // TODO: Better way to mark the affected line
                $line_numbers[] = "<span class='mark'>" . ($line + $begin) . '</span>';
            } else {
                $line_numbers[] = $line + $begin;
            }
            $code = rtrim($code);
        }

        // Recombine the lines
        $source = implode("\n", $source_lines);

        if ($colorize) {
            // Use GeSHi to highlight it (if requested)
            $geshi = new GeSHi($source, 'php');
            $geshi->enable_classes();
            $geshi->enable_keyword_links(false);
            $source = $geshi->parse_code();
        }

        return [
            'line_numbers' => $line_numbers,
            'source' => $source
        ];
    }
}