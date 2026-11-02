<?php
/**
 * "IP Rules" (used for login process) related data access
 *
 * @author Wavetrex (wavetrex@gmail.com)
 * @package CargoClaims
 * @version 1.0.dev
 */
class App_Model_IPRule extends MVC_Model
{
	/**
	 * Database tables used by this model, and their short aliases
	 * @var array
	 */
	public $tab = array(
		'ip'=> "ip_rules"			// Ip address ranges table
	);

	/**
	 * This is a model that needs the database, so set up to use it
	 */
	public function __construct()
	{
		$this-> db = MVC_Core::$db;
	}

//----- IP Rules CRUD -----//

	/**
	 * Adds an IP rule to the system
	 *
	 * @param array $rule_data
	 */
	public function create($rule_data)
	{

	}

	public function read($rule_id)
	{

	}

	public function update($rule_id, $new_rule_data)
	{

	}

	public function delete($rule_id)
	{

	}

//----- Search IP -----//

	/**
	 * Used to determine which rule applies to a certain IP address
	 * -- Does this by reading the highest order rule that matches
	 *
	 * @param string $ip_addr
	 * @param int $org_id Optionally, also use the rules that apply to just one organization
	 */
	public function find_Rule_for_IP($ip_addr, $org_id = null)
	{
		$ip_lu = sprintf('%u', ip2long($ip_addr));

		$q = QueryFactory::select()
			-> table($this-> tab['ip'], 'id,name,added_by,mode,org_id,notify_user_id')
			-> where("? BETWEEN `start_ip` AND `end_ip`", $ip_lu)
			-> order("`order` DESC")
			-> limit(1);
		if(!is_null($org_id))
			$q-> where("`org_id` IS NULL OR `org_id` = ?", $org_id);

		$r = $this-> db-> query($q);
		if($r && $r-> num_rows == 1) {
			$ipr = $r-> current();

			return $ipr;
		}
		return false; // error, fail to find any rule that matches
	}

}