<?php
/**
 * CODEC for the CSV format
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage WebAPI Plugins
 * @version 2.10
 */
class App_Plugin_WebAPI_codec_CSV implements App_Plugin_WebAPI_codec
{
	private $CFG;

	/**
	 * Constructor receives configuration for this instance of the plugin
	 * @param array $CFG
	 */
	public function __construct($CFG)
	{
		$this-> CFG = $CFG;
	}

	/**
	 * Decodes a CSV data stream into a bidimensional array
	 * @param unknown $data
	 */
	public function decode($data)
	{
		$decoded = array(
			'meta'=> $data['meta'] // Meta is always passed on
		);

		// Decode CSV stream into a bidimensional array
		//match all the non-quoted text and one series of quoted text (or the end of the string)
		//each group of matches will be parsed with the callback, with $matches[1] containing all the non-quoted text,
		//and $matches[3] containing everything inside the quotes
		$stream = preg_replace_callback('/([^"]*)("((""|[^"])*)"|$)/s', function($matches) {
			//anything inside the quotes that might be used to split the string into lines and fields later,
			//needs to be quoted. The only character we can guarantee as safe to use, because it will never appear in the unquoted text, is a CR
			//So we're going to use CR as a marker to make escape sequences for CR, LF, Quotes, and Commas.
			if(isset($matches[3])) {
				$str = str_replace("\r", "\rR", $matches[3]);
				$str = str_replace("\n", "\rN", $str);
				$str = str_replace('""', "\rQ", $str);
				$str = str_replace(',', "\rC", $str);
			} else
				$str = '';

			//The unquoted text is where commas and newlines are allowed, and where the splits will happen
			//We're going to remove all CRs from the unquoted text, by normalizing all line endings to just LF
			//This ensures us that the only place CR is used, is as the escape sequences for quoted text
			return preg_replace('/\r\n?/', "\n", $matches[1]) . $str;
		}, $data['stream']);

		//remove the very last newline to prevent a 0-field array for the last line
		$stream = preg_replace('/\n$/', '', $stream);

		//split on LF and parse each line with a callback
		$decoded['contents'] = array_filter(array_map(function($line) {
			return array_map(function ($field) {
				$field = str_replace("\rC", ',', $field);
				$field = str_replace("\rQ", '"', $field);
				$field = str_replace("\rN", "\n", $field);
				$field = str_replace("\rR", "\r", $field);
				return $field;
			}, explode(',', $line));
		}, explode("\n", $stream)), function($value) { return $value!=='' && $value['0']!==''; });

		// CSV has column definition as the first row. Extract that row and place it into 'meta'
		$decoded['meta']['fields'] = array_flip(array_shift($decoded['data']));
		// Also specify that this data contains multiple lines (so it's not just ONE row of data that was decoded, but several)
		$decoded['meta']['structure'] = "matrix";
		$decoded['meta']['width'] = count($decoded['meta']['fields']);
		$decoded['meta']['height'] = count($decoded['data']);

		return $decoded;
	}

}
