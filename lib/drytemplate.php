<?php
/**
 * DonReY Framework 2012 :: Render Transform - DRY Template
 * Experimental minimalistic template system, with a clear symbol+keyword syntax, and programming language like content inclusion - with curly braces { }
 *
 * TODO: Create a plugin mechanism to allow this library to output debug information (for example when include/raw/plugin cannot find a file/object or when important variables are not found - for example for loops)
 *
 * @author Wavetrex <wavetrex@gmail.com>
 * @link http://www.donrey.net/Documentation/Modules/render.drytemplate.html
 * @license [GPL v3] http://www.gnu.org/licenses/gpl-3.0.html
 * @version 1.0.dev
 * @package DonReY
 * @subpackage Module.Render
 */
class Drytemplate
{
	// Exception/Error codes
	const
		ERR_FILE_MISSING = 1
		;

	/**
	 * Template search paths (if they exists, template files are searched for first in these directories)
	 * @var array[string]
	 */
	private $tpl_paths = array();

	/**
	 * A quick method to render a template by passing information directly to the constructor (Optional, arguments are not necessary)
	 * Transform Constructor: Args array format
	 * [0] = unused, should be "Drytemplate"
	 * [1] = template or template file
	 * [2] = data to be used (usually a reference), but it might be absent
	 * [3] = mode: "template"/"file", defaults to "template"
	 * @param SimpleXMLElement $CFG
	 * @param array $args
	 */
	public function __construct($CFG, $args = array())
	{
		if(count($args) >= 4) {
			$mode = $args[3]; // mode
		} else
			$mode = 'template';
		if(count($args) >= 2) {
			if($mode == 'template')
				$this-> template = $args[1];
			if($mode == 'file')
				$this-> template_file = $args[1];
		}
		if(count($args) >= 3)
			$this-> rootdata = &$args[2];

		// setup template path ( for engines that use templates )
		if(isset($CFG-> paths)) {
			$this-> tpl_paths = $CFG-> paths;
		}
	}

	/**
	 * Scans a section of a string for doublequote-delimited zone to determine it's length
	 *
	 * @param string $string
	 * $param int $p Scan start location
	 * @return int End location of the string ( final quote )
	 */
	private function measure_String(&$string, $p)
	{
		$s = $p;
		$len = strlen($string);
		do {
			$p++;
			// end of string
			if($string[$p] == '"') {
				break;
			}
			// check for escaped quotes inside the string ( \" ), and skip them, also check for backslash itself ( \\ )
			if($string[$p] == '\\') {
				if($string[$p+1] == '"' || $string[$p+1] == '\\') {
					$p++;
				}
			}
			if($p >= $len-1)
				throw new Exception("DRYT: Unterminated string");
		} while(1);

		return $p;
	}

	/**
	 * Scans the interior of a placeholder, which follows various syntax rules
	 *
	 * @param string $placeholder
	 */
	private function parse_Placeholder($placeholder, $modifiers=null)
	{
		if(strlen($placeholder) == 0)
			return null; // nothing to parse!

		$ph = new PH();
		// check if first character is special ( !, @, #, $, *, = )
		switch($placeholder[0]) {
			// comment, the entire content of the placeholder is ignored, including any (useless) modifiers
			case '!':
				return null;
			// literal section, doesn't get parsed, but modifier processing does occur
			case '@':
				if(!is_null($modifiers)) {
					$ph-> mode = 'L';
					$ph-> str = substr($placeholder,1);
					break;
				} else
					return substr($placeholder,1);
			// $ represents the data itself - used when you don't want to search inside the data like for variables, but the data itself is what must appear
			// usually in conjunction with #use or #repeat
			case '$':
				$ph-> mode = 'SELF';
				break;
			// * represents the var name of the object that contains the data
			// generally useful for #repeat's and perhaps #use
			case '*':
				$ph-> mode = 'INDEX';
				break;
			// keyword, special meaning for each
			case '#':
				$kwd = explode(':',$placeholder,3);
				$ph-> mode = 'KW';
				$ph-> keyword = strtoupper(substr($kwd[0],1));

				// basic or dynamic variable
				$trec1 = array();
				$this-> parse_Pattern($kwd[1], $trec1);
				if(count($trec1) == 1 && is_string($trec1[0])) {
					// basic variable (no more curly brackets inside)
					$ph->_var = $trec1[0];
				} else {
					// dynamic variable
					$ph->_var = $trec1;
				}

				switch($ph-> keyword) {
					// the 3rd part doesn't exist
					case 'BREAK':
					case 'RAW':
						break;
					// the 3rd part is raw data, don't go into it ( similar to {@...} )
					case 'EXP':
						$ph-> rawdata = $kwd[2];
						break;
					// the 3rd part is a pattern
					case 'USE':
					case 'UP':
					case 'ROOT':
					case 'IF':
					case 'IFNOT':
					case 'REPEAT':
					case 'STORE':
					case 'INCLUDE':
					case 'LOAD':
					case 'PLUGIN':
						$trec2 = array();
						$this-> parse_Pattern(isset($kwd[2])? $kwd[2]: "", $trec2);
						if(count($trec2) == 1 && is_string($trec2[0])) {
							$ph-> pattern = $trec2[0];
						} else {
							$ph-> pattern = $trec2;
						}
						break;
					default:
						throw new Exception("DRYT: Invalid keyword detected");
				}
				break;

			// basic or dynamic variable name
			default:
				$ph-> mode = 'VAR';

				// split into var and default
				$vd = explode(':',$placeholder,2);

				// parse the interior (for dynamic var names)
				$trec = array();
				$this-> parse_Pattern($vd[0], $trec);
				if(count($trec) == 1 && is_string($trec[0])) {
					// basic variable (no more curly brackets inside)
					$ph->_var = $trec[0];
				} else {
					// dynamic variable
					$ph->_var = $trec;
				}

				// check if default exists
				if(isset($vd[1])) {
					$def = array();
					$this-> parse_Pattern($vd[1], $def);
					if(count($def) == 1 && is_string($def[0])) {
						$ph->_default = $def[0];
					} else {
						$ph->_default = $def;
					}
				}
		}
		// modifiers
		if(!is_null($modifiers))
			$ph-> mods = $modifiers;
		return $ph;
	}

	/**
	 * Scans the interior of a modifiers section, which follows various syntax rules
	 * Returns the "modifiers" object which can be used by parse_Placeholder
	 *
	 * @param string $modifiers
	 */
	private function parse_Modifiers($modifiers)
	{
		if(strlen($modifiers) == 0)
			return null; // nothing to parse!

		// modifiers array
		$ma = array();

		$mstart = 0; $p = 0; $modo = new PH(); // modifier object
		$mode = 'kw';
		$len = strlen($modifiers);
		do {
			// detect strings inside the modifiers to skip them
			if($modifiers[$p] == '"') {
				$p = $this-> measure_String($modifiers, $p);
			}

			// start parameters detected
			if($modifiers[$p] == '(') {
				if($mode == 'params')
					throw new Exception("DRYT: Bad modifier format - Open bracket encountered while expecting closed bracket");

				$modo-> kw = substr($modifiers,$mstart,$p-$mstart);

				$pstart = $p+1;
				$params = array();
				$mode = 'params';
			}

			// next parameter
			if($mode == 'params' && $modifiers[$p] == ',') {
				$param = substr($modifiers,$pstart,$p-$pstart);
				if($param && $param[0] == '"') // param is a string
					$param = stripslashes(substr($param,1,-1));
				$pstart = $p+1;
				$params[] = $param;
			}

			// end parameters detected, this is also end of modifier
			if($modifiers[$p] == ')') {
				if($mode!='params')
					throw new Exception("DRYT: Bad modifier format - Closed bracket encountered without open bracket");

				// final parameter
				$param = substr($modifiers,$pstart,$p-$pstart);
				if($param && $param[0] == '"') // param is a string
					$param = stripslashes(substr($param,1,-1));
				$params[] = $param;

				// apply some type juggling on params
				$ptest = null;
				foreach($params as &$ptest) {
					// numeric
					if(is_numeric($ptest)) {
						if(strpos($ptest,'.')!==false) // check if float
							settype($ptest, 'float');
						elseif(strpos($ptest,'x')!==false) { // check if hex
							$ptest = hexdec($ptest);
						} else { // no, just your average int
							settype($ptest, 'int');
						}
					}
					// boolean literal
					if($ptest === 'true') {
						$ptest = true;
					}
					if($ptest === 'false')
						$ptest = false;
					// null
					if($ptest === 'null')
						$ptest = null;
				}

				$modo-> params = $params;
				$mode = 'endp';
			}

			// found the end of one modifier, record it
			if($modifiers[$p] == ';') {
				if($mode == 'params')
					throw new Exception("DRYT: Bad modifier format - Semicolon encountered while expecting closed bracket");
				// mode is still "keyword, no paranthesis were encountered so far
				if($mode == 'kw')
					$modo-> kw = substr($modifiers,$mstart,$p-$mstart);

				$mode = 'kw';
				$mstart = $p+1;
				if($modo-> kw) // remove empty "modifiers"
					$ma[] = $modo;
				$modo = new PH();
			}
			$p++;
		} while($p < $len);
		// end encountered
		if($mode == 'params')
			throw new Exception("DRYT: Bad modifier format - Tilde (end of parameters) encountered while expecting closed bracket");
		if($mode == 'kw') {
			$modo-> kw = substr($modifiers,$mstart,$p-$mstart);
		}
		if($modo-> kw) // remove empty "modifiers"
			$ma[] = $modo;

		return $ma;
	}

	/**
	 * Parses a template into it's components ( identifies all placeholders and other special elements, saves fragments.
	 * Calls iteself recursively for patterns inside placeholders.
	 *
	 * @param string $pattern Current pattern being analysed
	 * @param array $record Analysis recording location
	 */
	protected function parse_Pattern($pattern, &$record)
	{
		// Initialization: pattern position, length, where scanning has begin, test-depth inside curly brackets
		$p = 0; $len = strlen($pattern); $check_start = 0; $depth = 0;
		if($len == 0)
			return;

		// identify the start and stop of a {placeholder} . It might have other {}'s inside it
		do {
			// If we are inside a placeholder ( $depth > 0 ), {}'s might exist inside a string, so if a quote is detected, find the end of the string first
			if($depth > 0) {
				if($pattern[$p] == '"') {
					$p = $this-> measure_String($pattern, $p);
				}
			}
			// not inside brackets, not in string, find the matching end bracket
			if($pattern[$p] == '{') {
				if($depth == 0) {
					$begin = $p + 1;
				}
				$depth++;
			}
			// }'s are ignored if a placeholder hasn't been started
			if($depth > 0 && $pattern[$p] == '}') {
				$depth--;
				// bingo, here it is, analyse the placeholder
				if($depth == 0) {
					$end = $p;

					// first, record the plain-text fragment before the placeholder (only if it's length is > 0 )
					if($begin-$check_start-1 > 0)
						$record[] = substr($pattern,$check_start,$begin-$check_start-1);
					// move past the } character
					$p++;

					// after placeholders, a modifier section can appear ( which is delimited by two ~ ~ ), check if there is such item
					$modifier = null;
					if($p < $len-1 && $pattern[$p] == '~') { // yes !
						$mod_start = $p + 1;
						do {
							$p++;
							// check for strings inside modifiers ( they might contain ~ character, so it must be ignored )
							if($pattern[$p] == '"') {
								$p = $this-> measure_String($pattern, $p);
							}
							if($pattern[$p] == '~') { // found the end
								$modifier = $this-> parse_Modifiers(substr($pattern, $mod_start, $p-$mod_start));
								$p++;
								break;
							}
							if($p == $len-1)
								throw new Exception("DRYT: Unterminated modifier section");
						} while(1);
					}
					// then, the placeholder ( which is an object )
					$ph = $this-> parse_Placeholder(substr($pattern,$begin,$end-$begin),$modifier);
					if(!is_null($ph))
						$record[] = $ph;
					// continue from after the placeholder
					$check_start = $p;
					// $p has already been advanced after the placeholder
					continue;
				}
			}
			$p++;
		} while($p < $len);

		// oops, trouble!
		if($depth > 0) {
			throw new Exception("DRYT: Unterminated placeholder !");
		}
		// when we're done, record the last part of the string
		if($check_start < $len)
			$record[] = substr($pattern, $check_start);
	}

	/**
	 * Scans data for a value using the dot.syntax format
	 * It searches in array or objects, dynamically
	 *
	 * @param string $key Dot.syntax.formatted.data.key
	 * @param mixed $data Data to be searched
	 * @param string $default Default text for when data is not found (or unsearchable)
	 * @return mixed The found data or $default if it wasn't located (which is null by default)
	 */
	private function scan_Data($key, $data, $updata = null, $default = null)
	{
		if(strlen($key)==0) // no key, return data as is
			return $data;

		// % percentage in the beginning of a key name forces the data to be searched in $this-> rootdata instead of the current location.
		// ^ caret in the same positions uses the previous scope ( $updata ) instead.
		// Useful when location has been overridden by #repeat or #use and we want to access outside it.
		// Ignores the passed $data
		if($key[0] == '%') {
			$key = substr($key, 1);
			$data = $this-> rootdata;
		} elseif($key[0] == '^' && !is_null($updata)) {
			$key = substr($key, 1);
			$data = $updata;
		}

		// Extra modifier, + requires the resulting data not to be an empty string (even if it exists), otherwise the default portion is used instead
		if($key[0] == '+') {
			$must_not_be_blank = true;
			$key = substr($key, 1);
		}

		if(empty($data))
			return null; // ... nothing to search for, nothing to return

		// search in array|object the proper key (recursive)
		$m = explode('.',$key);
		foreach($m as $segment) {

			if(is_object($data)) {
				if(isset($data-> $segment))
					$data = $data-> $segment;
				else // not found !
					return $default; // the optional default value when data is not found
			} elseif(is_array($data)) {
				if(isset($data[$segment]))
					$data = $data[$segment];
				else
					return $default;
			} else
				return $default; // we still have segments to search, but data is something else, not object, not array - does not contain keys (or member names)
		}

		if(!empty($must_not_be_blank) && is_string($data))
			if(strlen($data)==0)
				return $default;

		return $data; // all segments processed, the searched data was found.
	}

	/**
	 * Checks how some data compares with a specified compare string (
	 * -- If data is string, the data is compared as strings (supports =, ==, ~=, !=, <>, !~ signs)
	 * -- If data is a number, it can be compared with $compare (supports =, ==, !=, <>, >, <, >=, <= signs)
	 * @param mixed $cdata
	 * @param string $compare
	 * @param string $sign
	 * @return bool Result of the comparison
	 */
	private function check_Comparison($cdata, $compare, $sign = '=')
	{
		if(is_string($cdata)) {
			// optionally trim the quotes of the $compare string
			$compare = trim($compare, '\'"');

			// case insensitive comparasion, this is a template system, should not matter how the designer writes his string
			switch($sign) {
				case '=':
				case '==':
					// Value equals the searched string
					return strtolower($cdata)===strtolower($compare);
				case '~=':
					// Value contains the searched string
					return strpos(strtolower($cdata),strtolower($compare))!==false;
				case '!=':
				case '<>':
					// Value is different from the searched string
					return strtolower($cdata)!==strtolower($compare);
				case '!~':
					// Value doesn't contain the searched string
					return strpos(strtolower($cdata),strtolower($compare))===false;
				case '!_':
					// Value is not an empty string (has minimum length 1) - This would be considered "True" for the number 0
					return strlen($cdata)>0;
			}
		} else
			switch($sign) {
				case '=':
				case '==':
					return $cdata == $compare;
				case '!=':
				case '<>':
					return $cdata != $compare;
				case '>':
					return $cdata > $compare;
				case '>=':
					return $cdata >= $compare;
				case '<':
					return $cdata < $compare;
				case '<=':
					return $cdata <= $compare;
			}
	}

	/**
	 * Little helper function for all keywords that need a "condition"
	 *
	 * @param string $varname
	 * @param mixed $data
	 * @return bool
	 */
	private function check_Condition($varname, &$data, &$updata = null, $index = null)
	{
		// basic support for OR, AND (multiple variables in condition)
		// Note - Not yet possible for the strings to contain | or &
		if(strpos($varname, '|')!==false) {
			$varnames = explode('|', $varname);
			$operator = 'OR';
		} elseif(strpos($varname, '&')!==false) {
			$varnames = explode('&', $varname);
			$operator = 'AND';
		} else {
			$varnames = array($varname);
			$operator = null;
		}

		$final = false;
		foreach($varnames as $varname) {
			unset($sign);
			// check for comparison operator inside "varname"
			if(strpos($varname, '==')!==false)
				$sign = '==';
			elseif(strpos($varname, '~=')!==false)
				$sign = '~=';
			elseif(strpos($varname, '!=')!==false)
				$sign = '!=';
			elseif(strpos($varname, '!~')!==false)
				$sign = '!~';
			elseif(strpos($varname, '<>')!==false)
				$sign = '<>';
			elseif(strpos($varname, '!_')!==false)
				$sign = '!_';
			elseif(strpos($varname, '>=')!==false)
				$sign = '>=';
			elseif(strpos($varname, '<=')!==false)
				$sign = '<=';
			elseif(strpos($varname, '>')!==false)
				$sign = '>';
			elseif(strpos($varname, '<')!==false)
				$sign = '<';
			elseif(strpos($varname, '=')!==false)
				$sign = '=';

			if(isset($sign))
				list($varname, $compare) = explode($sign, $varname, 2);

			// transform the varname into data (cdata = condition's data)
			if($varname == '*')
				$cdata = $index;
			else
				$cdata = $this-> scan_Data($varname, $data, $updata);
			// depending on what the data is, comparasion is done in different ways
			if(is_array($cdata) || is_object($cdata)) {
				// data is a container, compare against it's size
				if(isset($sign))
					$condition = $this-> check_Comparison(count($cdata), $compare, $sign);
				else
					$condition = count($cdata)>0;

			} elseif(is_string($cdata)) {
				// data is a string
				if(isset($sign))
					$condition = $this-> check_Comparison(trim($cdata), $compare, $sign);
				else {
					$cdata = trim($cdata); // spaces are removed for the purpose of string evaluation
					if($cdata === '0' || strtolower($cdata) == 'false' ) // some strings have special meanings
						return false;
					// for everything else, return true if the string is not empty
					$condition = strlen($cdata)>0;
				}

			} elseif(is_numeric($cdata)) {
				// data is a number
				if(isset($sign))
					$condition = $this-> check_Comparison($cdata, $compare, $sign);
				else
					$condition = (bool)$cdata;

			} elseif(is_bool($cdata))
				$condition = $cdata;

			elseif(is_null($cdata))
				$condition = false;

			else
				throw new Exception("DRYT: Unsupported data type in comparison");

			switch($operator) {
				case 'OR':
					if($condition) // in an OR sequence, when finding a "true" result, the entire sequence is true, and the rest of tests can be ignored
						return true;
					break;
				case 'AND':
					if(!$condition) // in an AND sequence, when finding just one "false" result, the entire sequence is false
						return false;
					break;
			}
		}

		// all scanned, no break
		switch($operator) {
			case 'OR':
				return false; // none were found true
			case 'AND':
				return true; // none were found false
			default:
				return $condition; // when just one, return the only condition that existed
		}
	}

	/**
	 * Modifies a placeholder's output according to modifier rules
	 *
	 * @param mixed $c
	 * @param array $modifiers
	 */
	private function modify_Content($c, $modifiers)
	{
		// all mods are processed in order from left to right, passing the output forward in the chain
		foreach($modifiers as $mod) {
			switch($mod-> kw) {

				// simple (string) modifiers
				case 'uppercase':
					$c = strtoupper($c);
					break;
				case 'lowercase':
					$c = strtolower($c);
					break;
				case 'capfirst':
					$c = ucfirst($c);
					break;
				case 'capitalize':
					$c = ucwords($c);
					break;
				case 'nl2br':
					$c = nl2br($c);
					break;
				case 'addslashes':
					$c = addslashes($c);
					break;
				case 'stripslashes':
					$c = stripslashes($c);
					break;
				case 'md5':
					$c = md5($c);
					break;
				case 'sha1':
					$c = sha1($c);
					break;
				case 'length':
					$c = strlen($c);
					break;
				case 'reverse':
					$c = strrev($c);
					break;
				case 'striptags':
					$c = strip_tags($c);
					break;
				case 'urlencode':
					$c = urlencode($c);
					break;
				case 'urldecode':
					$c = urldecode($c);
					break;
				case 'htmlentities':
					$c = htmlentities($c, ENT_QUOTES, 'UTF-8', false); // pre 5.4.0 needs the parameters
					break;
				case 'htmlspecialchars':
					$c = htmlspecialchars($c, ENT_QUOTES, 'UTF-8', false); // pre 5.4.0 needs the parameters
					break;
				case 'htmldecodeentities':
					$c = html_entity_decode($c, ENT_QUOTES, 'UTF-8');
					break;
					// cleans all spaces between html tags, glueing them together. Leaves all spaces inside a html tag intact
				case 'htmlstripspaces':
					// umm this looks ugly, no idea how it actually works... Thank you Google :)
					$c = preg_replace('/(?:(?<=\>)|(?<=\/\>))(\s+)(?=\<\/?)/', '', $c);
					break;

				// complex modifiers

				// Trims any spaces or other empty characters from the output
				case 'trim':
					$c = trim($c);
					break;

				// Appends the specified suffix only if the string has content. If the string is empty and a 2nd parameter is present, it is used instead (like a default value)
				case 'suffix':
					if(strlen($c)>0)
						$c.= $mod-> params[0];
					elseif(!empty($mod-> params[1]))
						$c = $mod-> params[1];
					break;

				// Same thing as "suffix" but before the text
				case 'prefix':
					if(strlen($c)>0)
						$c= $mod-> params[0].$c;
					elseif(!empty($mod-> params[1]))
						$c = $mod-> params[1];
					break;

					// wordwraps the string
				case 'wordwrap':
					if(isset($mod-> params)) {
						if(count($mod-> params) == 2)
							$c = wordwrap($c, (int)$mod-> params[0], (string)$mod-> params[1]);
						elseif(count($mod-> params) == 1)
							$c = wordwrap($c, (int)$mod-> params[0]);
					} else
						$c = wordwrap($c);
					break;

				case 'json':
					$encode_options = JSON_HEX_APOS; // Always encode apostrophe, as otherwise it will break JSON.parse(' ... ') on the JS side
					// Extra options
					if(isset($mod-> params) && count($mod-> params) == 1) {
						$encode_option_texts = explode('|', (string)$mod-> params[0]);
						if(in_array("JSON_HEX_TAG", $encode_option_texts))
							$encode_options |= JSON_HEX_TAG;
						if(in_array("JSON_HEX_AMP", $encode_option_texts))
							$encode_options |= JSON_HEX_AMP;
						if(in_array("JSON_HEX_QUOT", $encode_option_texts))
							$encode_options |= JSON_HEX_QUOT;
						if(in_array("JSON_FORCE_OBJECT", $encode_option_texts))
							$encode_options |= JSON_FORCE_OBJECT;
						if(in_array("JSON_NUMERIC_CHECK", $encode_option_texts))
							$encode_options |= JSON_NUMERIC_CHECK;
					}
					$c = json_encode($c, $encode_options);
					// Convert \" into \\" otherwise it won't get decoded properly by JSON.parse
					$c = str_replace('\\', '\\\\', $c);
					break;

				// number format
				case 'numberformat':
					if(isset($mod-> params)) {
						if(count($mod-> params) == 3)
							$c = number_format((double)$c, (int)$mod-> params[0], (string)$mod-> params[1], (string)$mod-> params[2]);
						elseif(count($mod-> params) == 2)
							$c = number_format((double)$c, (int)$mod-> params[0], (string)$mod-> params[1]);
						elseif(count($mod-> params) == 1)
							$c = number_format((double)$c, (int)$mod-> params[0]);
					} else
						$c = number_format((double)$c);
					break;

				// cuts a fragment of the string (from beginning or arbitrary point). Can also add a tail to the cut string, but only if the string was actually cut.
				case 'cut':
					if(isset($mod-> params)) {
						if(count($mod-> params) == 3) {
							// all features
							$initial_len = strlen($c);
							$c = substr($c, (int)$mod-> params[1], (int)$mod-> params[0]);
							if($mod-> params[0]+$mod-> params[1] < $initial_len)
								$c.= (string)$mod-> params[2];
						} elseif(count($mod-> params) == 2) {
							if(is_numeric($mod-> params[1])) {
								// second param is start position
								$c = substr($c, (int)$mod-> params[1], (int)$mod-> params[0]);
							} else {
								// second param is cut completion
								$initial_len = strlen($c);
								$c = substr($c, 0, (int)$mod-> params[0]);
								if($mod-> params[0] < $initial_len)
									$c.= (string)$mod-> params[1];
							}
						} elseif(count($mod-> params) == 1)
							$c = substr($c, 0, (int)$mod-> params[0]);
					}
					// no effect without parameters
					break;

				// converts a recognizable date (strtotime) into a formatted date
				case 'date':
					if(is_numeric($c))
						$unixtime = (int)$c;
					else
						$unixtime = strtotime($c);
					$c = date($mod-> params[0]?: DATE_ATOM, $unixtime);
					break;

				case 'clock24':
				// convers an integer number into a 24-hour international clock (00:00:00 to 23:59:59)
					$sec0 = (int)$c % 86400;
					$hours = (int)($sec0/3600);
					$rem = $sec0 % 3600;
					$minutes = (int)($rem/60);
					$seconds = $rem - $minutes*60;
					if($hours<10)
						$hours = "0".$hours;
					if($minutes<10)
						$minutes = "0".$minutes;
					if($seconds<10)
						$seconds = "0".$seconds;
					$c = $hours. ':'. $minutes. ':'. $seconds;
					break;
			}
		}
		return $c;
	}

	/**
	 * Processes parsed template (which is now an array of fragments and objects) and returns a string
	 *
	 * @param array $ptpl Parsed TemPLate
	 * @param mixed $data The current scope
	 * @param mixed $updata The previous scope (for keywords that modify the scope, like #use, #repeat, #root)
	 * @param mixed $index The varname which was used to discover $data
	 */
	protected function process_Parsed($ptpl, &$data, &$updata = null, $index = null)
	{
		$dataref = null; $s = '';
		if(is_string($ptpl)) // ?
			$s = $ptpl;
		else

		foreach($ptpl as $fragment) {
			if(is_string($fragment)) // plain text, no processing, no replacing
				$s.= $fragment;
			else {
				// if ->_var exist, assamble it
				if(isset($fragment->_var))
					if(is_string($fragment->_var)) {
						// simple var (dot syntax, use the data scanner to locate it
						$varname = $fragment->_var;
					} else {
						// dynamic var (has subpatterns)
						$varname = $this-> process_Parsed($fragment->_var, $data, $updata);
					}
				// else, reset it (clear last iteration)
				else
					$varname = '';

				// here comes the fun part, it depends on what kind of placeholder
				switch($fragment-> mode) {

					// Literal
					case 'L':
						$content = (string)$fragment-> str;

						if(isset($fragment-> mods))
							$s.= $this-> modify_Content($content, $fragment-> mods);
						else
							$s.= $content;
						break;

					// data itself
					case 'SELF':
						if(isset($fragment-> mods))
							$s.= $this-> modify_Content($data, $fragment-> mods);
						else
							$s.= (string)$data;
						break;

					// the index in an array or property name for an object, which contains $data
					case 'INDEX':
						if(isset($fragment-> mods))
							$s.= $this-> modify_Content($index, $fragment-> mods);
						else
							$s.= (string)$index;
						break;

					// search variable in data
					case 'VAR':
						// grab or process default
						if(isset($fragment->_default)) {
							if(is_string($fragment->_default))
								$default = $fragment->_default;
							else
								$default = $this-> process_Parsed($fragment->_default, $data, $updata, $varname);
						} else
							$default = '';

						// ~ tilde in the beginning of a variable name forces the content of a variable to behave like a pattern ( and get run through the parser )
						// Note: When used along the ^, %, + modifiers, they must appear in the order: ~^ , ~% , ~+ ( ^~ won't work )
						if($varname[0] == '~') {
							$varname = substr($varname, 1);
							$patternmode = true;
						} else
							$patternmode = false;

						$content = $this-> scan_Data($varname, $data, $updata, $default); // find the variable content - it must be serializable, or the template system might fail

						if($patternmode) { // not that this cannot be pre-parsed, it's like PHP's eval()
							$pa = array();
							$this-> parse_Pattern($content, $pa);
							$content = $this-> process_Parsed($pa, $data, $updata, $varname);
						}

						if(isset($fragment-> mods))
							$s.= $this-> modify_Content($content, $fragment-> mods);
						else
							$s.= $content;

						break;

					// Keyword
					case 'KW':
						switch($fragment-> keyword) {

							// {#use:var:pattern}
							case 'USE':
								$usedata = $this-> scan_Data($varname, $data, $updata);
								$content = $this-> process_Parsed($fragment-> pattern, $usedata, $data, $varname); // use data as is, this keyword needs it.

								if(isset($fragment-> mods))
									$s.= $this-> modify_Content($content, $fragment-> mods);
								else
									$s.= $content;
								break;

							// {#root:pattern} - Goes to the root of the data object
							case 'ROOT':
								$usedata = $this-> scan_Data($varname, $this-> rootdata, $updata);
								$content = $this-> process_Parsed($fragment-> pattern, $usedata, $data, $varname); // use data as is, this keyword needs it.

								if(isset($fragment-> mods))
									$s.= $this-> modify_Content($content, $fragment-> mods);
								else
									$s.= $content;
								break;

							// {#up:pattern} - Goes to previous location in the data object (used to temporarily escape {#use} or other location modifiers)
							case 'UP':
								$usedata = $this-> scan_Data($varname, $updata);
								$content = $this-> process_Parsed($fragment-> pattern, $usedata, $data, $varname); // use data as is, this keyword needs it.

								if(isset($fragment-> mods))
									$s.= $this-> modify_Content($content, $fragment-> mods);
								else
									$s.= $content;
								break;

							// {#if:condition:pattern}
							case 'IF':
								if($this-> check_Condition($varname, $data, $updata, $index)) {
									$content = $this-> process_Parsed($fragment-> pattern, $data, $updata, $index);

									if(isset($fragment-> mods))
										$s.= $this-> modify_Content($content, $fragment-> mods);
									else
										$s.= $content;
								}
								break;

							// {#ifnot:condition:pattern}
							case 'IFNOT':
								// condition variable analysed, now test it
								if(!$this-> check_Condition($varname, $data, $updata, $index)) {
									$content = $this-> process_Parsed($fragment-> pattern, $data, $updata, $index);

									if(isset($fragment-> mods))
										$s.= $this-> modify_Content($content, $fragment-> mods);
									else
										$s.= $content;
								}
								break;

							// {#repeat:iterative-var:pattern}
							case 'REPEAT':
								$usedata = $this-> scan_Data($varname, $data, $updata);
								if(is_null($usedata)) // nothing to do when data is not found
									break;
								if(is_scalar($usedata) || is_resource($usedata)) {
									// error, attempt to use non-iterative data in a REPEAT, consider as just "one", and skip the foreach
									$content = $this-> process_Parsed($fragment-> pattern, $usedata, $data, $varname);
									if(isset($fragment-> mods))
										$s.= $this-> modify_Content($content, $fragment-> mods);
									else
										$s.= $content;
								} else {
									// data is iterable (or it should be)
									foreach($usedata as $usedata_key=> $usedata_element) {
										$processed_element = $this-> process_Parsed($fragment-> pattern, $usedata_element, $data, $usedata_key);
										if(($before_break = strstr($processed_element,'%%BREAK%%',true))!==false) {
											$content = $before_break;
											break;
										} else
											$content = $processed_element;
										// modification is applied to each iteration individually, not as a whole (for example, cutting affects the iteration, not the full string resulting from repeat)
										if(isset($fragment-> mods))
											$s.= $this-> modify_Content($content, $fragment-> mods);
										else
											$s.= $content;
									}
								}
								break;

							// {#break:condition}
							case 'BREAK':
								// if condition is true, returns a special notification to the above level (which should only be a #repeat keyword!)
								if($this-> check_Condition($varname, $data, $updata, $index))
									$s.= '%%BREAK%%';
								break;

							// {#store:key:pattern}
							case 'STORE':
								// use a bit of reference magic :)
								unset($dataref);

								if(strlen($varname)>0) { // search only if there is a key to search for

									if($varname[0] == '%') {
										$varname = substr($varname, 1);
										$dataref = &$this-> rootdata;
									} elseif($varname[0] == '^') {
										$dataref = &$updata;
									} else
										$dataref = &$data;

									// search in array|object the proper key (recursive)
									$m = explode('.',$varname);
									foreach($m as $segment) {
										if(is_object($dataref)) {
											// when not found, create it (object) - Note that creation replicates the previous container type and keeps replicating it deeper
											if(!isset($dataref-> $segment))
												$dataref-> $segment = new stdClass();
											$dataref = &$dataref-> $segment;
										} elseif(is_array($dataref)) {
											// when not found, create it (array) - So when starting with array, we'll end up with arrays in arrays, if starting with objects, we end up with objects in objects
											if(!isset($dataref[$segment]))
												$dataref[$segment] = array();
											$dataref = &$dataref[$segment];
										} else
											break; // we still have segments to search, but data is something else, not object, not array - does not contain keys (or member names)
									}
								}
								// ... and update inside the source data
								$content = $this-> process_Parsed($fragment-> pattern, $data, $updata, $varname);

								if(isset($fragment-> mods))
									$dataref = $this-> modify_Content($content, $fragment-> mods);
								else
									$dataref = $content;
								break;

							// {#include:data:filename} - Same as with {#use}, but loads the pattern from a file instead of using the inside content
							case 'INCLUDE':
								$filename = $this-> process_Parsed($fragment-> pattern, $data, $updata, $varname);

								// use references to allow the included template to modify data
								// unfortunately I haven't found a way to put this into a method, so it's a copy of the above code ( for #store )
								unset($dataref);
								if(strlen($varname)>0) { // search only if there is a key to search for

									if($varname[0] == '%') {
										$varname = substr($varname, 1);
										$dataref = &$this-> rootdata;
									} elseif($varname[0] == '^') {
										$varname = substr($varname, 1);
										$dataref = &$updata;
									} else
										$dataref = &$data;

									// Include is able to ignore the current $dataref and use $rootdata or $updata, by writing {#include:%:template_name} or {#include:^:template_name}
									if(strlen($varname)>1) {
										// search in array|object the proper key (recursive)
										$m = explode('.',$varname);
										foreach($m as $segment) {
											if(is_object($dataref)) {
												// when not found, create it (object)
												if(!isset($dataref-> $segment))
													$dataref-> $segment = new stdClass();
												$dataref = &$dataref-> $segment;
											} elseif(is_array($dataref)) {
												// when not found, create it (array)
												if(!isset($dataref[$segment]))
													$dataref[$segment] = array();
												$dataref = &$dataref[$segment];
											} else
												break; // we still have segments to search, but data is something else, not object, not array - does not contain keys (or member names)
										}
									}
								} else
									$dataref = &$data;

								$content = $this-> render_File($filename, $dataref, false); // do not save root for included files !

								if(isset($fragment-> mods))
									$s.= $this-> modify_Content($content, $fragment-> mods);
								else
									$s.= $content;
								break;

							// {#RAW:filename} - Loads a file and puts it where the placeholder is without parsing the inside (still, modifiers can apply!)
							case 'RAW':
								// Get filename from the placeholder, replacing any \ or / with value of DS, for filename consistency
								$filename = str_replace(array('\\', '/'), DS, trim($this-> process_Parsed($fragment->_var, $data, $updata, $varname)));

								$content = null;
								if(file_exists($filename)) {
									$content = file_get_contents($filename);
								} else {
									// nope. Try in predefined paths
									if(isset($this-> tpl_paths['templates']))
										foreach($this-> tpl_paths['templates'] as $path) {
											if(file_exists($path. DS. $filename)) {
												$content = file_get_contents($path. DS. $filename);
										}
									}
								}

								if(isset($fragment-> mods))
									$s.= $this-> modify_Content($content, $fragment-> mods);
								else
									$s.= $content;
								break;

							// {#load:data:filename} - Loads a pattern from a file into the "data", without interpreting it - A combination of {#store} and {#raw}
							case 'LOAD':
								// Get filename from the placeholder, replacing any \ or / with value of DS, for filename consistency
								$filename = str_replace(array('\\', '/'), DS, trim($this-> process_Parsed($fragment-> pattern, $data, $updata, $varname)));

								$content = null;
								if(file_exists($filename)) {
									$content = file_get_contents($filename);
								} else {
									// nope. Try in predefined paths
									if(isset($this-> tpl_paths['templates']))
										foreach($this-> tpl_paths['templates'] as $path) {
										if(file_exists($path. DS. $filename)) {
											$content = file_get_contents($path. DS. $filename);
										}
									}
								}

								if(!is_null($content)) {

									// the store part
									// references are creepy. Because a function cannot return a reference to a reference (uh?), I can't do it with functions, so... 3 copies of the same stuff.
									unset($dataref);
									if(strlen($varname)>0) { // search only if there is a key to search for
										if($varname[0] == '%') {
											$varname = substr($varname, 1);
											$dataref = &$this-> rootdata;
										} elseif($varname[0] == '^') {
											$dataref = &$updata;
										} else
											$dataref = &$data;

										// search in array|object the proper key (recursive)
										$m = explode('.',$varname);
										foreach($m as $segment) {
											if(is_object($dataref)) {
												// when not found, create it (object)
												if(!isset($dataref-> $segment))
													$dataref-> $segment = new stdClass();
												$dataref = &$dataref-> $segment;
											} elseif(is_array($dataref)) {
												// when not found, create it (array)
												if(!isset($dataref[$segment]))
													$dataref[$segment] = array();
												$dataref = &$dataref[$segment];
											} else
												break; // we still have segments to search, but data is something else, not object, not array - does not contain keys (or member names)
										}
									} else
										$dataref = &$data;

									// file loading into the store location
									if(isset($fragment-> mods))
										$dataref = $this-> modify_Content($content, $fragment-> mods);
									else
										$dataref = $content;

								} else
									break; // TODO: file not found, shoot a warning in the debug system (for now just break, nothing to store)

								break;

							// {#plugin:plugin_name:param_string} - Calls the render_Output() method of the plugin. Data is not directly used,
							// however the "param_string" could be generated using {#plugin:plugin_name:...{placeholder}...} to pass loaded data to the plugin
							// Note that plugin name can also be dynamic ;)
							case 'PLUGIN':
								$plugin_name = $this-> process_Parsed($fragment->_var, $data, $updata, $varname);

								// All this happens only if the plugin has been already registered and has the proper method
								if(isset($this-> plugins[$plugin_name]) && is_callable(array($this-> plugins[$plugin_name], "render_Output"))) {
									// param string is second part
									$param_string = $this-> process_Parsed($fragment-> pattern, $data, $updata);

									$output = call_user_func(array($this-> plugins[$plugin_name], "render_Output"), $param_string);

									if(isset($fragment-> mods))
										$s.= $this-> modify_Content($output, $fragment-> mods);
									else
										$s.= $output;
								}
								break;
						}
						break;
				}

			}
		}
		return $s;
	}

	/**
	 * Attempts to load the contents of a file by searching for it's name directly or inside the template paths
	 * @param string $filename
	 */
	private function load_File($filename)
	{
		// nothing to return if no filename was specified
		if(!$filename)
			return false;
		// Try absolute path first (should be relative to htdocs index, since that's the only entry point)
		if(file_exists($filename)) {
			$this-> template = file_get_contents($filename);
		} else {
			// nope. Try relateive to predefined or dynamic paths
			if(isset($this-> tpl_paths['templates']))
			foreach($this-> tpl_paths['templates'] as $path) {
				if(file_exists($path. DS. $filename)) {
					// When a file is found, it's basedir is added to tpl_paths['templates'], unless it already exists - To support relative names for #include commands within that file
					$dirname = dirname($path. DS. $filename);
					if(!in_array($dirname, $this-> tpl_paths['templates']))
						$this-> tpl_paths['templates'][] = $dirname;

					$this-> template = file_get_contents($path. DS. $filename);
					return true;
				}
			}
		}
		// template file not found
		return false;
	}

	/**
	 * Stored template or reference (or last template used - it can be reused with different data)
	 * Also contains content of a template file
	 * @var string
	 */
	protected $template;

	/**
	 * Stored name of template file (or last template file used)
	 * @var string
	 */
	protected $template_file;

	/**
	 * Last used data or reference (can be reused with different template)
	 * The root data when using the template parser/processor. Also used by the {#root} keyword
	 * @var mixed
	 */
	protected $rootdata;

	/**
	 * An array of references to "plugin" objects that must contain a public "render_Output" method in them
	 *    Plugins are supposed to exist, be loaded and initialized before Drytemplate is parsed
	 * -- When the placeholder {#plugin:plugin_name:paremeter_string} is discovered, the corresponding plugin's "render_Output" method is called (if it exists), and content is then passed to the modifier
	 *    This basically allows the template to decide if some php code is executed or not.
	 *    array: Key = plugin name ; Value = plugin object
	 *
	 * @var array[objects]
	 */
	public $plugins;

	/**
	 * Registers an existing plugin object to be used by the Drytemplate object when {#plugin} is encountered
	 * @param string $name
	 * @param object $plugin Reference to the plugin object
	 */
	public function register_Plugin($name, &$plugin)
	{
		if(isset($this-> plugins[$name]))
			return false; // error, a plugin with the same name is already registered

		$this-> plugins[$name] = &$plugin;
		return true;
	}

	/**
	 * Adds a new path to be searched for templates when rendering and including
	 *
	 * @param string $path
	 * @param bool $insert Inserts the new path before existing ones, otherwise append at the last
	 */
	public function register_Path($path, $insert = false)
	{
		if(in_array($path, $this-> tpl_paths['templates']))
			return false; // error, path already exists

		if($insert)
			array_unshift($this-> tpl_paths['templates'], $path);
		else
			array_push($this-> tpl_paths['templates'], $path);
		return true;
	}

	/**
	 * Applies transformation engine on a string that contains the template (can reuse existing template and/or data)
	 * @param string $template Contents of a template
	 * @param mixed $data
	 */
	public function render_String($template=null, &$data=null)
	{
		if(!is_null($template))
			$this-> template = $template;

		// The magic starts here.
		if(is_null($this-> template))
			return '';

		$pa = array();
		$this-> parse_Pattern($this-> template, $pa);

		// use either provided data or the already stored rootdata
		if(is_null($data))
			return $this-> process_Parsed($pa, $this-> rootdata);
		else
			return $this-> process_Parsed($pa, $data);
	}

	/**
	 * Loads a file from the template directory then applies the transformation engine on it
	 * @param string $template_file Name of a template file
	 * @param mixed $data
	 * @param bool $save_root
	 */
	public function render_File($template_file=null, &$data=null, $save_root = true)
	{
		// Replace / or \ in $tempalte_file with DS (for consistency)
		if(!is_null($template_file)) {
			$this-> template_file = str_replace(array('\\', '/'), DS, trim($template_file));
		}

		if($save_root && !is_null($data))
			$this-> rootdata = &$data;

		// try to load the file
		if($this-> load_File($this-> template_file)) {
			if($save_root)
				return $this-> render_String();
			else
				return $this-> render_String(null, $data);
		}

		return '';
	}

	/**
	 * Resets internal data (so they don't get reused without passing new template/file/data)
	 */
	public function reset()
	{
		$this-> template = null;
		$this-> template_file = null;
		$this-> rootdata = null;
	}

	/**
	 * Arguments format
	 * [0] = template or template file
	 * [1] = data to be used (usually a reference), but it might be absent
	 * [2] = mode: "template"/"file", defaults to "template"
	 * @see DRY\Render\Transform_base::set_Data()
	 */
	public function set_Data()
	{
		$args = func_get_args();
		if(count($args) >= 3) {
			$mode = $args[2]; // mode
		} else
			$mode = 'template';
		if(count($args) >= 1) {
			if($mode == 'template') {
				$this-> template = $args[0];
				$this-> template_file = null;
			} if($mode == 'file') {
				$this-> template_file = $args[0];
				$this-> template = null; // clear "template" cache when asked to load another file
			}
		}
		if(count($args) >= 2 && !empty($args[1]))
			$this-> rootdata = &$args[1];
	}

	/**
	 * @see DRY\Render\Transform_base::__toString()
	 */
	public function __toString()
	{
		// __toString automatically uses the cached template string if it exists, then tries to load a template file
		if(!is_null($this-> template))
			return $this-> render_String();
		elseif(!is_null($this-> template_file))
			return $this-> render_File();
		else
			return '';
	}

}

/**
 * PlaceHolder helper class (will be used by the caching system)
 *
 * @author Wavetrex <wavetrex@gmail.com>
 */
class PH {

	/**
	 * Not that "magic" method
	 * @param array $arr
	 */
	public static function __set_state($arr)
    {
		return (object)$arr;
    }

}