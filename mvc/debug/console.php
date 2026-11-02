<?php
/**
 * JavaScript Console-based debugging system (uses FirePHP to send data from PHP to the FirePHP plugin)
 * -- Very useful to separate output from errors and have an unobtrusive debug system, but requires a browser plugin to work.
 *
 * @author Wavetrex (wavetrex@gmail.com)
 * @package CargoClaims
 * @version 1.0.dev
 */
class MVC_Debug_Console extends FirePHP implements MVC_Debug_base
{
    /**
     * The translated debug level (0 - 3)
     */
    public int $level = 0;

    /**
     * Constructor, initializes the debugging system
     *
     * @param int $level Debug level (0 - 3)
     * @param array $settings Additional settings for FirePHP
     */
    public function __construct(int $level = 0, array $settings = [])
    {
        // Load FirePHP library
        $this->level = $level;

        // Using this debug driver, the built-in PHP debug display is disabled (no matter the level)
        ini_set('display_errors', 'stderr');
        error_reporting(0);

        if ($this->level > 0) {
            // FirePHP has some default handlers
            if (isset($settings['register'])) {
                if (in_array('error', $settings['register'])) {
                    $this->registerErrorHandler();
                }
                if (in_array('exception', $settings['register'])) {
                    $this->registerExceptionHandler();
                }
                if (in_array('assertion', $settings['register'])) {
                    $this->registerAssertionHandler();
                }
            }

            match ($this->level) {
                1, 2, 3 => null, // No specific actions for levels 1-3
                default => null,
            };
        }

        // Capture all output, to be displayed AFTER all the ->output() calls
        if ($this->level > 0) {
            ob_start();
        }
    }

    public function __destruct()
    {
        // Finally, send the buffered output
        if ($this->level > 0) {
            ob_end_flush();
        }
    }

    /**
     * Outputs arbitrary data to the Firebug console
     *
     * @param array $output_element Array with information to be sent to FirePHP
     * @param int|null $time Number of milliseconds to be placed in front of the label (optional)
     */
    #[\Override]
    public function output(array $output_element, ?int $time = null): void
    {
        $type_conf = [
            'plain' => 'LOG',
            'notice' => 'INFO',
            'warning' => 'WARN',
            'error' => 'ERROR',
            'exception' => 'ERROR',
        ];

        $time_prefix = $time !== null ? sprintf("%.3fms ", $time) : '';

        if (!isset($output_element['meta'])) {
            $output_element['meta'] = null;
        }

        $this->fb(
            $output_element['data'],
            $time_prefix . $output_element['label'],
            constant('FirePHP::' . $type_conf[$output_element['type']]),
            $output_element['meta']
        );
    }
}