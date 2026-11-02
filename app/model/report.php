<?php
/**
 * "Report" model: Data analysis on issue information
 *
 * @author Wavetrex (wavetrex@gmail.com)
 * @package CargoClaims
 * @version 1.0.dev
 */
class App_Model_Report extends MVC_Model
{
	/**
	 * This is a model that needs the database, so set up to use it
	 */
	public function __construct()
	{
		$this-> db = MVC_Core::$db;
	}

//----- Reports CRUD -----//

	/**
	 * Creates a report type
	 * @param array $report_data
	 */
	public function create($report_data)
	{
		// Pre-process
		$report_data['columns'] = json_encode($report_data['columns']);

		$q=QueryFactory::insert()
		-> table($this-> db-> tab['rep'])
		-> values($report_data);

		$this-> db-> query($q);
		return $this-> db-> insert_id;
	}

	/**
	 * Reads a single report type
	 * @param int $report_id
	 * @return array
	 */
	public function read($report_id)
	{
		$q=QueryFactory::select()
		-> table($this-> db-> tab['rep'])
		-> where("`id` = ?", $report_id)
		-> limit(1);

		$r = $this-> db-> query($q);
		if($r && count($r)==1)
			return $r-> current();
	}

	/**
	 * Updates a report type with new column order (and new name, etc.)
	 * @param int $report_id
	 * @param array $new_report_data
	 */
	public function update($report_id, $new_report_data)
	{
		// Pre-process
		$new_report_data['columns'] = json_encode($new_report_data['columns']);

		$q=QueryFactory::update()
		-> table($this-> db-> tab['rep'])
		-> where("`id` = ?", $report_id)
		-> values($new_report_data)
		-> limit(1);

 		return $this-> db-> query($q);
	}

	/**
	 * Deletes a report type
	 * @param int $report_id
	 */
	public function delete($report_id)
	{
		$q=QueryFactory::delete()
		-> table($this-> db-> tab['rep'])
		-> where("`id` = ?", $report_id)
		-> limit(1);

		return $this-> db-> query($q);
	}

	/**
	 * Returns a list of available reports for an user (organization report types)
	 *
	 * @param int $org_id
	 * @param int $its_id If not null, restrict the list to a certain Issue Type (normal usage)
	 * -- However, when editing report types, it should be possible to see all (for all issue types)
	 * @param array $wtr
	 * @return MVC_Database_Result
	 */
	public function get_List($org_id = null, $its_id = null, $wtr = array('rtype', 'user'))
	{
		$q=QueryFactory::select()
		-> order('name ASC');

		if(is_int($org_id))
			$q-> where("`rep`.`org_id` = ?", $org_id);
		if(is_int($its_id))
			$q-> where("`rep`.`its_id` = ?", $its_id);

		// What to read?
		$wtr = self::wtr_Helper($wtr);

		if(in_array('rtype', $wtr))
			$q-> table(array($this-> db-> tab['rep'],'rep'));
		else
			$q-> table(array($this-> db-> tab['rep'],'rep'), 'id');
		if(in_array('user', $wtr))
			$q-> table(array($this-> db-> tab['usr'],'usr'), QueryFactory::autoalias_Add_Prefix("id,name,email", '_usr_'), 'left', '`rep`.created_by = `usr`.id');
		if(in_array('organization', $wtr))
			$q-> table(array($this-> db-> tab['org'],'org'), QueryFactory::autoalias_Add_Prefix("id,name", '_org_'), 'left', '`rep`.org_id = `org`.id');

		$r = $this-> db-> query($q);
		$r-> split_by_prefixes = array(
			// extra tables
			'creator'=> "_usr_",
			'organization'=> "_org_"
		);

		return $r;
	}

}