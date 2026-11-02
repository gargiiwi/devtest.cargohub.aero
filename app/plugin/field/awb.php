<?php
/**
 * A plugin defining functionality of the "awb" field typoe
 *
 * @author Wavetrex (wavetrex@gmail.com)
 * @package Cargo ITS
 * @version 1.0.dev
 */
class App_Plugin_Field_AWB
{
	public $name = "Air Waybill";

	private static $db_table = "data_airlines";

	/**
	 * Field definition for the next field being processed
	 * @var array
	 */
	public $fdef;

	/**
	 * Indicates in which column in IXF this plugin stores it's data (value or int_value)
	 * @var unknown_type
	 */
	public $data_column = "value";

	public function check(&$fdata, $update = false)
	{
		// In update mode, if both fields are empty the plugin passes the check. However, if anything's typed in the fields, it must be correct.
		if($update)
			if(empty($fdata['p']) && empty($fdata['s']))
				return false;

		// Values must not be empty or shorter than 3, 8
		if(strlen($fdata['p'])<3 || strlen($fdata['s'])<8)
			return "AWB must be formed from a 3-digit prefix and a 8-digit serial";

		// Test if the serial checks, but only for prefixes other than 000
		if($fdata['p'] != '000') {
			// Test if the non-000 prefix is a valid one
			$q=QueryFactory::select()
			-> table(self::$db_table)
			-> where("`AWB_prefix` = ?", $fdata['p'])
			-> limit(1);

			$r = MVC_Core::$db-> query($q);
			if(!$r || count($r)!=1) {
				// not found
				return "AWB prefix is not a valid airline ID assigned by IATA";
			}

			$serial7 = substr($fdata['s'], 0, 7);
			$check7 = substr($fdata['s'], 7, 1);
			if($serial7 % 7 != $check7)
				return "Invalid AWB serial number";
		}

		return false;
	}

	public function get_Field_Store(&$fdata)
	{
		if(is_array($fdata))
			$fdata = $fdata['p'].'-'.$fdata['s'];
		$field_store = array(
			'value'=> $fdata
		);
		return $field_store;
	}

	public function get_Default()
	{
		$field_store = array(
			'value'=> $this-> fdef['o']['def']
		);
	}

	/**
	 * Used in the template rendering, this should match the pseudo-serialized version of the field output
	 *
	 * @param string $key
	 * @param string $primary_field_name
	 * @return array
	 */
	public function get_Serialized_Placeholders($key, $primary_field_name = null, $group = null)
	{
		return array(
			array(
				'n'=> $primary_field_name,
				'group'=> $group,
				'ph'=> '{'.$key.'}'
			)
		);
	}

	/**
	 * Decodes the stored special field into the usabled data
	 * @param array $xf Field data
	 * @param bool $serialize Serialize the field data (for the grid)
	 * @return array
	 */
	public function decode($xf, $serialize = false)
	{
		if($serialize)
			return $xf['value'];
		else {
			$awb = array();
			list($awb['p'], $awb['s']) = explode('-', $xf['value']);
			return $awb;
		}
	}

	/**
	 * Sets up the form objects for this field type
	 * @param MVC_Form $form
	 * @param string $key
	 * @param array $xfields // all incoming form values
	 */
	public function setup_Form(MVC_Form &$form, $key, &$xfields)
	{
		$value = (is_array($xfields)&&isset($xfields[$key]))? $xfields[$key]: null;

		if(!is_null($value))
			$default = $value;
		elseif(isset($this-> fdef['o']) && isset($this-> fdef['o']['def'])) {
			$default = array();
			list($default['p'], $default['s']) = explode('-',(string)$this-> fdef['o']['def']);
		} else
			$default = array('p'=>'','s'=>'');

		$required = isset($this-> fdef['o']) && isset($this-> fdef['o']['req']) && ($this-> fdef['o']['req'] == true);

		$form-> add_Element("Scalar", 'mawb_p', 'p', $default['p'], null, "xfd.{$key}", array('label'=> $this-> fdef['n'], 'req'=> (bool)$required));
		$form-> add_Element("Scalar", 'mawb_s', 's', $default['s'], null, "xfd.{$key}", array('label'=> $this-> fdef['n'], 'req'=> (bool)$required));
	}

	public function form_Compat($xfield, $key)
	{
		if(!empty($xfield['value'])) {
			$value = array();
			list($value['p'], $value['s']) = explode('-', $xfield['value']);
			return array(
				'p'=> $value['p'],
				's'=> $value['s']
			);
		} else
			return array('p'=> null, 's'=> null);
	}

	/**
	 * Converts a field search value from the format it is displayed (and possibly searched for) to the format it is stored
	 * @param array $search_value
	 */
	public function prepare_Data_Search(&$filter_item, &$filter_info)
	{
		// AWB is stored as clear text, nothing to do here.
	}

}