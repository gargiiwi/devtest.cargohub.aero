<?php
/**
 * MVC Debug System - Allows retrieving debug information from the system using various methods
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage MVC_Framework
 * @version 2020.01
 */
class MVC_Debug
{
    const
        LEVEL_DISABLED = 0,    // all debugging functions disabled, and on critical errors a predefined page is sent instead of any debugging information
        LEVEL_CRITICAL = 1,    // only critical errors (that stop the script) are displayed
        LEVEL_WARNING = 2,     // warnings (possible problem causes) are also displayed
        LEVEL_ALL = 3;         // Everything, including notices and data dumps (sent using MVC_Core::dump($data, $label) )

    public int $level = self::LEVEL_DISABLED;

    /**
     * The debug mode:
     * -- "console" - using FirePHP
     * -- "php" - using php's normal error output (in page)
     * TODO: -- "file" - no data is sent to the browser, debug output is instead logged into a file
     */
    public string $mode = 'php';

    /**
     * The object that handles the actual debugging
     */
    public ?MVC_Debug_base $driver = null;

    /**
     * Constructor, initializes the debugging system
     *
     * @param string $level
     * @param string $mode
     * @param array $settings Parameters for a plugin-based mode
     */
    public function __construct(string $level = "DISABLED", string $mode = "php", array $settings = [])
    {
       
    }

    /**
     * Records the last "dump", so it can be retrieved by a higher-level function
     */
    protected ?array $last_dump = null;

    /**
     * General data output function, passed onto the driver
     *
     * @param mixed $data
     * @param string|null $label
     * @param array|null $meta When this dump() method is not called directly (instead, the non-oop version is used, meta() is the passed location)
     * @param string $type
     * @param bool $show_source In certain debug plugins, it shows the PHP code at the debug point
     */
    public function dump(mixed $data, ?string $label = null, $meta = null, string $type = 'plain', bool $show_source = false): bool
    {
        // only sends dumps if the debug level is ALL (and a driver has been loaded)
        if ($this->driver instanceof MVC_Debug_base && $this->level >= self::LEVEL_ALL) {
            // Identify location from where dump was called (file, line, class and function)
            if (!is_array($meta)) {
                $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
                $dump_found = false;
                foreach ($trace as $entry) {
                    if ($entry['class'] === 'MVC_Debug' && $entry['function'] === 'dump') { // found the call point of dump()
                        // File and Line are from the entry itself
                        $meta = [
                            'File' => $entry['file'],
                            'Line' => $entry['line']
                        ];
                        $dump_found = true;
                        continue;
                    }
                    if ($dump_found) {
                        // Class and Function are from the next entry
                        if (isset($entry['class']))
                            $meta['Class'] = $entry['class'];
                        if (isset($entry['function']))
                            $meta['Function'] = $entry['function'];
                        break;
                    }
                }
            }

            $output_el = [
                'type' => $type,
                'data' => $data,
                'label' => $label,
                'meta' => $meta
            ];
            if ($show_source)
                $output_el['show_source'] = true;

            $this->last_dump = $output_el;
            // send the data to the driver
            $this->driver->output($output_el);
        }
        return false;
    }

    /**
     * Returns the last dumped information
     */
    public function get_Last_Dump(): ?array
    {
        return $this->last_dump;
    }

    /**
     * Function pass-through to debug driver
     *
     * @param string $fname
     * @param array $args
     * @return mixed return from the debug driver
     */
    public function __call(string $fname, array $args): mixed
    { 
        return null;
    }

    /**
     * Error types, as text
     */
    public static array $e_types = [
        // caught warnings, notices and recoverable errors
        E_WARNING => "E_WARNING",
        E_USER_WARNING => "E_USER_WARNING",
        E_STRICT => "E_STRICT",
        E_NOTICE => "E_NOTICE",
        E_USER_NOTICE => "E_USER_NOTICE",
        // These two are only available in PHP 5.3 and up
        E_DEPRECATED => "E_DEPRECATED",
        E_USER_DEPRECATED => "E_USER_DEPRECATED",
        E_USER_ERROR => "E_USER_ERROR",
        E_RECOVERABLE_ERROR => "E_RECOVERABLE_ERROR",
        // not caught, fatal errors
        E_ERROR => "E_ERROR",
        E_PARSE => "E_PARSE",
        E_CORE_ERROR => "E_CORE_ERROR",
        E_COMPILE_ERROR => "E_COMPILE_ERROR"
    ];

    /**
     * Error handler. Handles recording of Warnings, Notices, Errors and also transforms Errors into Exceptions, so they can be caught
     *
     * @param int $errno Error number (predefined constants)
     * @param string $errstr Error string
     * @param string $errfile Where in the code it has occurred
     * @param int $errline At which line
     */
    public function handler_Errors(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        // not registering any error if level = 0
        if ($this->level === self::LEVEL_DISABLED)
            return true;

        // prepare the data to be dumped
        $output_el = [
            'data' => $errstr,
            'label' => self::$e_types[$errno] ?? "UNKNOWN_ERROR",
            'meta' => [
                'File' => $errfile,
                'Line' => $errline
            ],
            'show_source' => true
        ];

        $output_el['type'] = match ($errno) {
            E_WARNING, E_USER_WARNING, E_STRICT => 'warning',
            E_NOTICE, E_USER_NOTICE, E_DEPRECATED, E_USER_DEPRECATED => 'notice',
            E_USER_ERROR, E_RECOVERABLE_ERROR => 'error',
            default => 'plain'
        };

        $this->driver->output($output_el);
        return !in_array($errno, [E_USER_ERROR, E_RECOVERABLE_ERROR]);
    }

    public static string $error_output = "Exception Encountered. Check Console.";

    /**
     * Uncaught exception handler that displays a nice output when the system crashes.
     *
     * @param Throwable $e
     */
    public static function handler_Exceptions(Throwable $e): void
    {
        // not registering the exception if level = 0
        if (MVC_Core::$debug->level === self::LEVEL_DISABLED)
            self::graceful_Crash();

        // prepare the data to be dumped
        $output_el = [
            'type' => 'exception',
            'data' => $e,
            'label' => '', // No label on exceptions
            'meta' => [
                'File' => $e->getFile(),
                'Line' => $e->getLine(),
                'Code' => $e->getCode()
            ],
            'show_source' => true
        ];

        MVC_Core::$debug->output($output_el);
        if (MVC_Core::$debug->level > self::LEVEL_DISABLED) {
            if (MVC_Core::$debug->mode === 'php')
                self::final_Crash("Uncaught exception '" . get_class($e) . "' with message '" . $e->getMessage() . "'");
            elseif (MVC_Core::$debug->mode === 'console')
                echo self::$error_output;
            else
                echo "Unhandled Error in the debug plugin.";
        }
    }

    /**
     * Shutdown handler. Will always be called when script ends (this might allow catching critical errors which cannot be handled by handler_Exceptions),
     *   as well as showing the "crash" screen if enabled in configuration
     */
    public static function handler_Shutdown(): void
    {
        if (is_array($error = error_get_last())) {
            // not registering the final error if level = 0
            if (MVC_Core::$debug->level === self::LEVEL_DISABLED)
                self::graceful_Crash();

            if (in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
                $output_el = [
                    'type' => 'error',
                    'data' => $error['message'],
                    'label' => self::$e_types[$error['type']] ?? "UNKNOWN_ERROR",
                    'meta' => [
                        'File' => $error['file'],
                        'Line' => $error['line']
                    ],
                    'show_source' => true
                ];
                MVC_Core::$debug->output($output_el);
            }

            // Crash !
            if (MVC_Core::$debug->level > self::LEVEL_DISABLED) {
                if (MVC_Core::$debug->mode === 'php')
                    self::final_Crash((self::$e_types[$error['type']] ?? "UNKNOWN_ERROR") . ": " . $error['message']);
                elseif (MVC_Core::$debug->mode === 'console')
                    echo self::$error_output;
                else
                    echo "Unhandled Error in the debug plugin.";
            }
        }
    }

    /**
     * Temporary function that shows an error message on crashes when debugging is disabled
     */
    private static function graceful_Crash(): void
    {
        header("Document-type: text/plain");
        if ($_SERVER['REQUEST_URI'] !== '/issue?action=importmrndatasubmit') {
            echo "Sorry, unhandled error. Please contact the administrator.";
        }
        die;
    }

    /**
     * Shows a fancy crash screen
     * -- Not used at the moment, as the PHP debugger is out of commission due to lack of GeSHi library.
     *
     * @param string $title
     */
    private static function final_Crash(string $title): void
    {
        // TODO: Move all this HTML and CSS out of here !
        ?>
        <!DOCTYPE HTML>
        <html>
        <head>
        <title><?php echo $title; ?></title>
        <style type="text/css">
            /* CSS styles omitted for brevity */
        </style>
        </head>
        <body>
        <h2><?php echo $title; ?></h2>
        <div id="debug">
        <?php
        echo MVC_Core::$debug->render_Buffer();
        ?>
        </div>
        </body>
        </html>
        <?php
    }
}

// A little fix to php pre 5.3.6? - Note that it's completely ignored in 5.2
if (!defined("DEBUG_BACKTRACE_IGNORE_ARGS"))
    define("DEBUG_BACKTRACE_IGNORE_ARGS", true);

if (!function_exists("dd")) {
    /**
     * Debug Dump, forwards to MVC_Core::$debug->dump, integrated in the framework, follows the "debug" attribute - displays nothing if debug is disabled
     *
     * @param mixed $data
     * @param string|null $label
     * @param bool $show_source True means the dump will also show the piece of code where the dump was called
     */
    function dd(mixed $data = null, ?string $label = null, bool $show_source = false): void
    {
        // identify source of dump
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        foreach ($trace as $entry) {
            if ($entry['function'] === 'dd') {
                $meta = [
                    'Line' => $entry['line'],
                    'File' => $entry['file'],
                ];
                break;
            }
        }
        MVC_Core::$debug->dump($data, $label, $meta, 'plain', $show_source);
    }
}

/**
 * Required methods for a debug handler object
 *
 * @author Wavetrex (wavetrex@gmail.com)
 * @package CargoClaims
 * @version 1.0.dev
 */
interface MVC_Debug_base
{
    public function output(array $output_element, ?int $time = null): void;
}