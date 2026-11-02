<?php
/**
 * Specialized "Data" object related to airport list
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage DataModels
 * @version 2019.06.18
 */
class App_Model_Data_Airport extends App_Model_Data
{
	/**
	 * Load exchange rates at first instance
	 */
	public function __construct()
	{
		parent::__construct('airports');
	}

	public $name = "Airport";
	public $name_plural = "Airports";

	/**
	 * Returns the plugin's configuration
	 *
	 * @return array
	 */
	public function get_Config()
	{
		$cfg = array(
			'type'=> "airport",
			'name'=> $this-> name,
			'name_plural'=> $this-> name_plural,
		);

		return $cfg;
	}

	/**
	 * Returns the plugin's configuration for the editor.
	 * This is only used when editing the group from the interface
	 *
	 * @return array
	 */
	public function get_Editor_Config()
	{
		$cfg = array(
			'base'=> $this-> get_Config(),
			// JavaScript related configuration
			'javascript'=> array(
				'jqwidgets'=> array('scrollbar', 'listbox', 'combobox'), // Extra jqx modules which are needed for this group type
				'xjs'=> array(
					'jqform'=> true
				),
				'modules'=> array(
					'sysdata_airline'=> array()
				)
			)
		);
		$cfg['base']['datafields'] = array(
			// group general
			array('name'=> "id", 'type'=> "int"),
			array('name'=> "name", 'type'=> "string"),
			array('name'=> "IATA", 'type'=> "string"),
			array('name'=> "ICAO", 'type'=> "string"),
			array('name'=> "country", 'type'=> "string")
		);
		$cfg['base']['columns'] = array(
			array('text'=> "ID", 'datafield'=> "id", 'width'=> 70, 'align'=> "center", 'cellsalign'=> "right", 'filtertype'=> "number"),
			array('text'=> "Airport", 'datafield'=> "name"),
			array('text'=> "IATA", 'datafield'=> "IATA", 'width'=> 50, 'align'=> "center", 'cellsalign'=> "center", 'filtercondition'=> "STARTS_WITH"),
			array('text'=> "ICAO", 'datafield'=> "ICAO", 'width'=> 60, 'align'=> "center", 'cellsalign'=> "center", 'filtercondition'=> "STARTS_WITH"),
			array('text'=> "Country", 'datafield'=> "country", 'width'=> 220, 'filtertype'=> "textbox")
		);

		return $cfg;
	}

}