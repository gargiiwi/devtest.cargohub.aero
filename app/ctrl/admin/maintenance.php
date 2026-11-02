<?php

/**
 * Module "Administration.Maintenance" - Various functions called from /admin/center
 * -- They usually have a rudimentary UI and do not load the full project GUI
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage Controllers
 * @version 2020.09.05
 */
class App_Ctrl_Admin_Maintenance extends App_Ctrl_Base
{

	/**
	 * Restrict the module for Admin only
	 */
	public function __construct()
	{
		parent::__construct(true);

		// Admin-only module, redirect to home if no access
		if (! $this->user->is_Unrestricted()) {
			$this->evan->add("Move along now, nothing to see here.", 'lock');
			return $this->redirect($this->CFG['home']);
		}
	}

	/**
	 * Performs Database Maintenance - Recalculates organization statistics
	 */
	public function action_Refresh_Themes()
	{
		$themes_config_file = ROOT . DS . 'app' . DS . 'data' . DS . 'themes.yaml';

		// Read existing themes config (may contain them renaming). If not found, initialize it
		$themes = MVC_Core::$spyc->loadFile($themes_config_file);
		if (empty($themes))
			$themes = array();
		$found_ids = array();
		foreach (array_diff(scandir(ROOT . DS . 'htdocs' . DS . 'js' . DS . 'lib' . DS . 'jqwidgets' . DS . 'styles'), array(
			'.',
			'..',
			'.svn',
			'.htaccess',
			'images',
			'jqx.base.css'
		)) as $file) {
			$id = str_replace(array(
				'jqx.',
				'.css'
			), '', $file);
			$name = ucfirst(strtolower(str_replace(array(
				'jqx.ui-',
				'jqx.',
				'.css'
			), '', $file)));
			$found_ids[] = $id;

			// Add theme if it doesn't exist
			if (! isset($themes[$id])) {
				$themes[$id] = array(
					'name' => $name,
					'display' => $name
				);
			}
		}
		// Now set as disabled any theme which is no longer there (don't remove it, as we should be able to check which users still have a theme that is gone)
		foreach ($themes as $id => &$theme) {
			if (! in_array($id, $found_ids))
				$theme['disabled'] = ! in_array($id, $found_ids);
			else
				unset($theme['disabled']);
		}
		// Order themes by name
		uasort($themes, function($a, $b) {
			return strcasecmp($a['display'], $b['display']);
		});

		$bytes = MVC_Core::$spyc-> dumpFile($themes_config_file, $themes);
		if (! $bytes)
			throw new Exception("Error occurred while writing config file");

		echo sprintf("<h2>Themes updated, %d themes found</h2><p>%d bytes written to %s</p>", count($found_ids), $bytes, $themes_config_file);
	}
}