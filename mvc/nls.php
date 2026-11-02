<?php
/**
 * MVC Component - Native Language Support
 * Used to load the language files and provide translations of strings according to the current active language
 *
 * @author Wavetrex (wavetrex@gmail.com)
 * @package CargoClaims
 * @version 1.0.dev
 */
class MVC_NLS
{
	/**
	 * The current loaded language configuration
	 * @var array
	 */
	public static $config = array();

	/**
	 * The language strings, grouped - This is loaded from the language files after self::load_Groups()
	 * @var array
	 */
	public static $groups = array();

	/**
	 * Sets up the NLS by specifying which language is currently to be displayed in the system (key "language:" in the settings.yaml)
	 * @param string $language Specify clear name, ex: "English", and not by code (en)
	 * @return bool True = Successfully switched language or existing one already set | False = This language is not implemented
	 */
	public static function set_Language($language)
	{
		if(empty($language)) {
			user_error(sprintf("MVC_NLS|No language was specified", $language), E_USER_WARNING);
			return false; // Error, can't set if not specified
		}

		// Check if the language that is being set is different from the other being loaded. If it's not, do nothing
		if(!empty(self::$config) && $language == self::$config['name'])
			return true;

		// search the "languages:" key in settings to discover the proper location for the $language
		foreach(MVC_Core::$settings['languages'] as $lang) {
			if($lang[0] == $language)
				$lang_iso2 = $lang[1];
		}
		if(!isset($lang_iso2)) {
			user_error(sprintf("MVC_NLS|Specified language %s is not implemented", $language), E_USER_WARNING);
			return false; // Not found
		}

		// now, load the $config for the discovered language
		$language_location = ROOT. DS. 'app'. DS. 'nls'. DS. $lang_iso2;
		if(MVC_Core::real_path($language_location. DS. "config.php"))
			include_once $language_location. DS. "config.php";
		else
			throw new Exception(sprintf("MVC_NLS|Unable to locate configuration file for language %s.", $language));
		if(self::$config['name'] != $language)
			throw new Exception(sprintf("MVC_NLS|Misconfiguration in the settings for language %s, please check.", $language));

		self::$config['location'] = $language_location;
		self::$groups = array(); // clear groups when switching language!
		return true;
	}

	/**
	 * Load langauge files under current language and in the specified groups
	 *
	 * @param string|array $groups
	 */
	public static function load_Groups($groups)
	{
		if(is_string($groups))
			$groups = explode(',', $groups);

		if(empty(self::$config))
			throw new Exception("Current language not yet set, unable to load language files.");

		// test if the specified groups exist in the $config
		foreach($groups as $group) {

			if(isset(self::$groups[$group]))
				continue; // skip groups which have already been loaded

			if(in_array($group, self::$config['groups'])) { // ok, the language should support this group
				$filename = self::$config['location']. DS. $group. '.yaml';
				if(file_exists($filename))
					self::$groups[$group] = MVC_Core::$spyc-> loadFile($filename);
			}

			if(!isset(self::$groups[$group]) || empty(self::$groups[$group])) {
				// failed to load that specific group in a language, try the default one
				$default_language = MVC_Core::$settings['core']['default_language'];
				if($default_language == self::$config['name']) // current language is the default, and the group wasn't found, abort
					return;

				// find location for default language
				foreach(MVC_Core::$settings['languages'] as $lang) {
					if($lang[0] == $default_language)
						$lang_iso2 = $lang[1];
				}
				$filename = ROOT. DS. 'app'. DS. 'nls'. DS. $lang_iso2. DS. $group. '.yaml';
				if(file_exists($filename))
					self::$groups[$group] = MVC_Core::$spyc-> loadFile($filename);
				// note that at this point if the loader still failed, there's nothing to be done, simply the group doesn't exist in either the specified language or the default one
			}
		}
	}

	/**
	 * Returns or searches for an object inside the language groups array using a dot.syntax to locate the string
	 * Uses two methods of search - Precise location in the language groups, or Searching anywhere in the groups
	 *
	 * @param string $id
	 * @param bool $precise
	 */
	public static function get_Object($id, $precise = true)
	{
		if($precise)
			// first method, when specifying the object location directly
			return self::locate_Object(self::$groups, $id);
		else
			// second variant, go recursively in the entire $groups array to locate the first key named $id
			return self::search_Object(self::$groups, $id);
	}

	/**
	 * Locates an object using a dot.syntax (the object must exist in the precise location $groups specified by $id)
	 *
	 * @param mixed $where Where to start locating?
	 * @param string $id What to locate
	 */
	private static function locate_Object(&$where, $id)
	{
		$m = explode('.', $id); // exploded dot.syntax id
		foreach($m as $segment) {
			if(is_array($where)) {
				if(isset($where[$segment]))
					$where = &$where[$segment];
				else
					return null;
			} else
				return null; // we still have segments to search, but data is something else, not object, not array - does not contain keys (or member names)
		}
		return $where; // all segments processed, the searched data was found.
	}

	/**
	 * Locates an object anywhere in the $groups, by searching the sequences inside the $id
	 *
	 * @param mixed $where Where to start locating?
	 * @param string $id What to locate
	 */
	private static function search_Object(&$where, $id)
	{
		if(is_array($where)) {

			$locate_res = self::locate_Object($where, $id);
			if(!is_null($locate_res))
				return $locate_res;

			foreach($where as $key=>$content) {
				// attempt to use each element of the content as possible location for the $id
				if($id == $key)
					return $content;

				if(is_array($content)) {
					$result = self::search_Object($content, $id);
					if(!is_null($result))
						return $result;
				}
			}
		} else
			return null;
	}
}


// The language searching method (non-oop, but VERY short)
function __($id, $precise = false)
{
	return MVC_NLS::get_Object($id, $precise);
}