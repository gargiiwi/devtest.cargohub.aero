<?php
/**
 * Base interface for Group Plugins
 *
 * @author Wavetrex (wavetrex@gmail.com)
 * @package Cargo ITS
 * @version 1.0.dev
 */
interface App_Plugin_Group
{
	/**
	 * Optionally appends one or more foreign tables to the group table ( for when the group contains complex data )
	 * @param QueryFactory $q
	 */
	public function attach_Foreign_Data(QueryFactory &$q);

	/**
	 * Plugin controlled split-by-prefixes, when using Foreign Data
	 * @param MVC_Database_Result $r
	 */
	public function split_Prefixes(MVC_Database_Result &$r);

	/**
	 * Performs Post-Processing on group data (base table and optionally attached foreign data)
	 * -- Sometimes this method might also read complex data for each returned row
	 * @param array $group
	 */
	public function group_Post_Process(&$group);

	/**
	 * Creates or Updates foreign data for a group_id
	 * @param int $group_id
	 * @param array $group_data
	 * @param bool $update True when updating, False when creating
	 */
	public function save_Foreign_Data($group_id, $group_data, $update);

	/**
	 * Deletes (or update) foreign data related to the group that doesn't exist anymore
	 * @param int $group_id
	 */
	public function delete_Foreign_Data($group_id);
}