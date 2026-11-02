<?php
/**
 * A plugin used to process the group data of type "transport"
 *
 * @author Wavetrex (wavetrex@gmail.com)
 * @package Cargo ITS
 * @version 1.0.dev
 */
class App_Plugin_Group_Training implements App_Plugin_Group
{
	public $name = "Training Type";
	public $name_plural = "Training Types";

	/**
	 * Returns the plugin's configuration
	 *
	 * @return array
	 */
	public function get_Config()
	{
		$cfg = array(
			'type'=> "training",
			'name'=> $this-> name,
			'name_plural'=> $this-> name_plural
		);

		return $cfg;
	}

	/**
	 * Returns the plugin's configuration for the editor.
	 * This is only used when editing the group from the interface
	 *
	 * @param string $editor_module Name of the editor module
	 * @return array
	 */
	public function get_Editor_Config($editor_module)
	{
		$cfg = array(
			'group'=> $this-> get_Config(),
			// JavaScript related configuration
			'javascript'=> array(
				'xjs'=> array(
					'jqform'=> true
				),
				'modules'=> array(
					$editor_module=> array()
				)
			)
		);
		$cfg['javascript']['modules'][$editor_module]['datafields'] = array(
			// group general
			array('name'=> "id", 'type'=> "int"),
			array('name'=> "name"),
			array('name'=> "count_elements", 'type'=> "int")
		);
		$cfg['javascript']['modules'][$editor_module]['columns'] = array(
			array('text'=> "Method", 'dataField'=> "name"),
			array('text'=> "Issues", 'dataField'=> "count_elements", 'width'=> 60, 'align'=> "center", 'cellsalign'=> "center", 'filtertype'=> "number")
		);

		return $cfg;
	}

	/**
	 * Nothing to do here, the "transport" plugin has no foreign data
	 * @see App_Plugin_Group::attach_Foreign_Data()
	 */
	public function attach_Foreign_Data(QueryFactory &$q)
	{
	}

	/**
	 * Nothing to do here, the "transport" plugin has no foreign data
	 * @see App_Plugin_Group::split_Prefixes()
	 */
	public function split_Prefixes(MVC_Database_Result &$r)
	{
	}

	/**
	 * Nothing to do here, the "transport" plugin doesn't store any special data
	 * @see App_Plugin_Group::group_Post_Process()
	 */
	public function group_Post_Process(&$group)
	{
	}

	/**
	 * Nothing to do here, the "transport" plugin has no foreign data
	 * @see App_Plugin_Group::save_Foreign_Data()
	 */
	public function save_Foreign_Data($group_id, $group_data, $update)
	{
	}

	/**
	 * Nothing to do here, the "transport" plugin has no foreign data
	 * @see App_Plugin_Group::delete_Foreign_Data()
	 */
	public function delete_Foreign_Data($group_id)
	{
	}
}