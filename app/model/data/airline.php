<?php
/**
 * Specialized "Data" object related to airline list
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage DataModels
 * @version 2019.06.17
 */
class App_Model_Data_Airline extends App_Model_Data
{
	/**
	 * Load exchange rates at first instance
	 */
	public function __construct()
	{
		parent::__construct('airlines');
	}

	public $name = "Airline";
	public $name_plural = "Airlines";

	/**
	 * Returns the plugin's configuration
	 *
	 * @return array
	 */
	public function get_Config()
	{
		$cfg = array(
			'type'=> "airline",
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
			array('name'=> "AWB_prefix", 'type'=> "string"),
			array('name'=> "IATA", 'type'=> "string"),
			array('name'=> "ICAO", 'type'=> "string"),
			array('name'=> "domain", 'type'=> "string"),
			array('name'=> "country", 'type'=> "string"),
			array('name'=> "status", 'type'=> "string")
		);
		$cfg['base']['columns'] = array(
			array('text'=> "ID", 'datafield'=> "id", 'width'=> 70, 'align'=> "center", 'cellsalign'=> "right", 'filtertype'=> "number"),
			array('text'=> "Airline", 'datafield'=> "name"),
			array('text'=> "AWBp", 'datafield'=> "AWB_prefix", 'width'=> 60, 'align'=> "center", 'cellsalign'=> "center", 'filtercondition'=> "STARTS_WITH"),
			array('text'=> "IATA", 'datafield'=> "IATA", 'width'=> 50, 'align'=> "center", 'cellsalign'=> "center", 'filtercondition'=> "STARTS_WITH"),
			array('text'=> "ICAO", 'datafield'=> "ICAO", 'width'=> 60, 'align'=> "center", 'cellsalign'=> "center", 'filtercondition'=> "STARTS_WITH"),
			array('text'=> "Redirect URL", 'datafield'=> "domain", 'width'=> 180),
			array('text'=> "Country", 'datafield'=> "country", 'width'=> 200, 'filtertype'=> "textbox"),
			array('text'=> "Status", 'datafield'=> "status", 'width'=> 120, 'filtertype'=> "checkedlist",
				'filteritems' => array("disabled", "inactive", "registered", "hidden", "nosubmit"))
		);

		// Extra configuration for sub-modules (only when needed)
// 		$cfg['javascript']['modules']['sysdata_airline'] = array();

		return $cfg;
	}

}