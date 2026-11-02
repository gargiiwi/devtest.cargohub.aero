<?php
/**
 * A plugin used to process the group data of type "marine"
 *
 * @author Wavetrex (wavetrex@gmail.com)
 * @package Cargo ITS
 * @version 2.6
 */
class App_Plugin_Group_Marine implements App_Plugin_Group
{
	public $name = "Shipping Line/Agent";
	public $name_plural = "Lines/Agents";
    
	/**
	 * Returns the plugin's configuration
	 *
	 * @return array
	 */
	public function get_Config()
	{
		$cfg = array(
			'type'=> "marine",
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
			array('name'=> "count_elements", 'type'=> "int"),
		    // Ocean Liner specific
		    array('name'=> "master", 'map'=> 'xdata>master'),
		    array('name'=> "master_id", 'type'=> 'int', 'map'=> 'xdata>master_id')
		);
		$cfg['javascript']['modules'][$editor_module]['columns'] = array(
			array('text'=> "Line or agent name", 'dataField'=> "name"),
			array('text'=> "Issues", 'dataField'=> "count_elements", 'width'=> 80, 'align'=> "center", 'cellsalign'=> "center", 'filtertype'=> "number"),
			array('text'=> "Master Handler", 'dataField'=> "master")
		);

		return $cfg;
	}

	/**
	 * Nothing to do here, the "marine" plugin has no foreign data
	 * @see App_Plugin_Group::attach_Foreign_Data()
	 */
	public function attach_Foreign_Data(QueryFactory &$q)
	{
	}

	/**
	 * Nothing to do here, the "marine" plugin has no foreign data
	 * @see App_Plugin_Group::split_Prefixes()
	 */
	public function split_Prefixes(MVC_Database_Result &$r)
	{
	}

	/**
	 * Nothing to do here, the "marine" plugin doesn't store any special data
	 * @see App_Plugin_Group::group_Post_Process()
	 */
	public function group_Post_Process(&$group)
	{
	}

	/**
	 * Nothing to do here, the "marine" plugin has no foreign data
	 * @see App_Plugin_Group::save_Foreign_Data()
	 */
	public function save_Foreign_Data($group_id, $group_data, $update)
	{
	    return true; // Nothing to save
	}

	/**
	 * Nothing to do here, the "marine" plugin has no foreign data
	 * @see App_Plugin_Group::delete_Foreign_Data()
	 */
	public function delete_Foreign_Data($group_id)
	{
	    return true; // Nothing to delete
	}
}