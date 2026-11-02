<?php
/**
 * System Statistics - Used to retrieve information about what's going on
 * - Primarely used by Admin, to provide overview on the activity in the system, but (in the future) organizations can also use it for the same purpose, with data limited to their own organization
 * - Caches information in a memory table, because some of the requests are very intensive and they should not be run very often
 *
 * TODO: Implement preloaded cache (so only one query is necessary for admin or for an org)
 * TODO: Move specialized global statistics ( like admin's funcitons "get_Org_Count", "get_User_Count" into a derived class (a plugin for example)
 *
 * @author Wavetrex (wavetrex@gmail.com)
 * @package CargoClaims
 * @version 2.9.1
 */
class App_Model_Statistics extends MVC_Model
{
	/**
	 * Cache Time-To-Live, in seconds
	 * - Indicates how often the information is recalculated and updated into cache
	 *
	 * @var array
	 */
	protected static $cache_ttl = array( 
		'org_count'=> 3600,				// one hour, this doesn't really change.
		'user_count'=> 600,				// 10 minutes, might change but not too often.
		'contact_count'=> 300,			// 5 minutes, this number can change as often as issue count
		'issue_count'=> 120,			// every 2 minutes, issue count might vary wildly with high system utilization
		'issue_count_org'=> 300,		// issue count for a specific organization is only read every 5 minutes
		'issue_monthly_count'=> 3600,	// only update once per hour, this might be a heavy query
		'record_statistics'=> 3600,		//86400,	// A full day - User's record statistics are updated automatically when the record is changed by the user; However, everyone else should also grab statistics periodically
		'total_records'=> 120,			// One hour - This doesn't need extreme precision... it's about information from a long time ago, in a galaxy far-far away... (Might even make it weekly)
		'records_per_group'=> 120,		// ...
		'xfield_enum'=> 120,			// XField based statistics (Enum) - Often, but not THAT often, since a record needs to be actually changed (and usually these fields are edited later, not when the record is created)
		'xfield_set'=> 120,				// XField based statistics (Set),
		'meta_sp'=> 120					// Status/Priority statistics
	);

	private static $accepted_statistics_keys = array(
		'org_count', 'user_count', 'contact_count', 'issue_count', 'issue_monthly_count', 'total_records'
	);

	/**
	 * Array with the first 3 letters of english months - used for drawing charts
	 * @var array
	 */
	public static $english_months_iso3 = array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');

	/**
	 * Array with the four quarters of an year, using 3 letters of months
	 * @var array
	*/
	public static $english_quarters_iso3 =  array('Jan-Mar', 'Apr-Jun', 'Jul-Sep', 'Oct-Dec');

	/**
	 * Array with the first 13 numbers, starting with 0 (up to twelve); TODO: Have a number generator here that knows how to count (as text) correctly to ... millions
	 * @var array
	*/
	public static $english_numbers13 = array('zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve');

	/**
	 * Reads a statistics information
	 * - Returns value (or values if that particular key returns multiple items
	 *
	 * @param unknown_type $statistic_key
	 * @param into $org_id Only for a specific organization, not global
	 * @return mixed
	 */
	public function read($statistic_key, $org_id = null)
	{
		if(!in_array($statistic_key, self::$accepted_statistics_keys))
			return false; // Key unsupported

		return $this-> {"get_". $statistic_key}($org_id);
	}

	/**
	 * Reads multiple statistics information and returns an array of them.
	 * - If a key is unsupported that key won't be part of the returned array
	 *
	 * @param array $statistic_key_list
	 * @param int $org_id
	 * @return array
	 */
	public function read_Multiple($statistic_key_list, $org_id = null)
	{
		$r = array();
		foreach($statistic_key_list as $statistic_key) {
			if(!in_array($statistic_key, self::$accepted_statistics_keys))
				continue;

			$r[$statistic_key] = $this-> read($statistic_key, $org_id);
		}

		return $r;
	}

	/**
	 * Reads the number of organizations in the system (uses cache)
	 * @return number
	 */
	private function get_Org_Count()
	{
		$cache = $this-> cache_Read("org_count", time()- self::$cache_ttl['org_count']);

		if(!$cache) {
			$q=QueryFactory::select()
			-> table(MVC_Core::$db-> tab['org'], array('org_count'=> "COUNT(*)"));

			$r = MVC_Core::$db-> query($q)-> current();
			$cache = (int)$r['org_count'];
			$this-> cache_Write("org_count", $cache);
		}

		return $cache;
	}

	/**
	 * Reads the number of users in the system (uses cache)
	 * @return number
	 */
	private function get_User_Count()
	{
		$cache = $this-> cache_Read("user_count", time()- self::$cache_ttl['user_count']);

		if(!$cache) {
			$q=QueryFactory::select()
			-> table(MVC_Core::$db-> tab['usr'], array('user_count'=> "COUNT(*)"));

			$r = MVC_Core::$db-> query($q)-> current();
			$cache = (int)$r['user_count'];
			$this-> cache_Write("user_count", $cache);
		}

		return $cache;
	}

	private function get_Contact_Count()
	{
		$cache = $this-> cache_Read("contact_count", time()- self::$cache_ttl['contact_count']);

		if(!$cache) {
			$q=QueryFactory::select()
			-> table(MVC_Core::$db-> tab['ctx'], array('contact_count'=> "COUNT(*)"));

			$r = MVC_Core::$db-> query($q)-> current();
			$cache = (int)$r['contact_count'];
			$this-> cache_Write("contact_count", $cache);
		}

		return $cache;
	}

	/**
	 * Counts the number of issues of each type and returns an array (uses cache)
	 * TODO: Support for organization issue count
	 */
	private function get_Issue_Count()
	{
 		$cache = $this-> cache_Read("issue_count", time()- self::$cache_ttl['issue_count']);

		if(!$cache) {
			$q=QueryFactory::select()
			-> table(MVC_Core::$db-> table('iss'), array('issue_count'=> "COUNT(*)"))
			-> table(MVC_Core::$db-> table('ist'), 'key,name', "left", "`iss`.type_id = `ist`.id")
			-> where("`ist`.scope = 'global'")
			-> group("`iss`.type_id");

			$r = MVC_Core::$db-> query($q);
			$cache = array(); $total = 0;
			foreach($r as $row) {
				$cache[$row['key']] = array(
					'name'=> $row['name'],
					'count'=> $row['issue_count']
				);
				$total+= $row['issue_count'];
			}
			$cache['_total'] = array(
				'sum'=> true,
				'name'=> "Total",
				'count'=> $total
			);

			$this-> cache_Write("issue_count", $cache);
		}

		return $cache;
	}

	/**
	 * Counts the number of issues submitted in the last 24 months and returns an array (uses cache)
	 * TODO: Support for organization issue count
	 */
	private function get_Issue_Monthly_Count()
	{
		$cache = $this-> cache_Read("issue_monthly_count", time()- self::$cache_ttl['issue_monthly_count']);

		if(!$cache) {
			$q=QueryFactory::select()
			-> table(array(MVC_Core::$db-> tab['iss'], 'iss'), array('c'=> "COUNT(*)", 'year'=> "YEAR(FROM_UNIXTIME(`ctime`))", 'month'=> "MONTHNAME(FROM_UNIXTIME(`ctime`))"))
			-> group("YEAR(FROM_UNIXTIME(`ctime`)), MONTH(FROM_UNIXTIME(`ctime`))")
			-> where("`ctime` > ?", time()-63113852)
			-> order("`ctime` DESC");

			$r = MVC_Core::$db-> query($q);
			$cache = array(); $c_year = null; $total_year = 0;
			foreach($r as $row) {
				if(is_null($c_year))
					$c_year = $row['year'];
				elseif($c_year != $row['year']) {
					$cache[] = array(
						"sum"=> true,
						"year"=> $c_year,
						"month"=> "Total",
						"c"=> $total_year,
					);
					$c_year = $row['year'];
					$total_year = 0;
				}
				$total_year+= $row['c'];
				$cache[] = $row;
			}
			// Total for last year?
			$cache[] = array(
				"sum"=> true,
				"year"=> $c_year,
				"month"=> "Total",
				"c"=> $total_year,
			);

			$this-> cache_Write("issue_monthly_count", $cache);
		}

		return $cache;
	}

// 	private static $cache_params = array('i1'=> '1_int', 'i2'=> '2_int', 's3'=> '3_string');

	/**
	 * Reads an statistics variable from cache (either global version, or organization-wide)
	 *
	 * @param string $var The variable that is accessed from cache
	 * @param int $dt Cached value date-time must be newer than this
	 * @param int $org_id Filter by organization
	 * @param array $params Extra parameters to find proper element in cache (maps to certain
	 * @param bool $decode_data Decode JSON or leave it like it is (for when the data is pushed towards JS directly)
	 */
	public function cache_Read($var, $dt, $org_id = null, $decode_data = true, $params = array())
	{
		if(empty($var))
			return false; // Error $var-iable name must exist

		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> tab['stx'], array("var", "dt", "data"))
		-> where("`var` = ?", $var)
		-> where("`dt` > ?", $dt)
		-> limit(1);

		if(is_null($org_id))
			$q-> where("`org_id` IS NULL");
		else
			$q-> where("`org_id` = ?", $org_id);

		// Extra conditions (params)
		if(isset($params['i1']))
			$q-> where("`param1_int` = ?", $params['i1']);
		if(isset($params['i2']))
			$q-> where("`param2_int` = ?", $params['i2']);
		if(isset($params['s3']))
			$q-> where("`param3_string` = ?", $params['s3']);
		// echo "269 ".$q;
		$r = MVC_Core::$db-> query($q);
		if(!$r || count($r)==0)
			return false; // Cache record not found or is older than time

		$row = $r-> current();
		return $decode_data? @json_decode($row['data'], true): $row['data'];
	}

	/**
	 * Creates, updates or deletes a user profile data set, by key.
	 * -- On update it overwrites any setting that might be there with the provided data
	 *
	 * @param string $var The variable that is accessed from cache
	 * @param int $org_id Filter by organization
	 * @param array $data If null or empty, the statistics cache record is deleted instead
	 * @param bool $data_encoded Indicates that the data has been pre-encoded as JSON before calling this method (possibly directly from JS before reaching PHP)
	 */
	public function cache_Write($var, $data = null, $org_id = null, $data_encoded = false, $params = array())
	{
		if(empty($var))
			return false; // Error $var-iable name must exist

		if(empty($data)) {
			// Remove the entry from cache ?
			$q=QueryFactory::delete()
			-> table(MVC_Core::$db-> tab['stx'])
			-> where("`var` = ?", $var)
			-> limit(1);

			if(is_null($org_id))
				$q-> where("`org_id` IS NULL");
			else
				$q-> where("`org_id` = ?", $org_id);

			// Extra conditions (params)
			if(isset($params['i1']))
				$q-> where("`param1_int` = ?", $params['i1']);
			if(isset($params['i2']))
				$q-> where("`param2_int` = ?", $params['i2']);
			if(isset($params['s3']))
				$q-> where("`param3_string` = ?", $params['s3']);

			MVC_Core::$db-> query($q);
		} else {
			// try an update first
			$q=QueryFactory::update()
			-> table(MVC_Core::$db-> tab['stx'])
			-> values(array(
				"data"=> $data_encoded? $data: json_encode($data),
				"dt"=> time()
			))
			-> where("`var` = ?", $var)
			-> limit(1);

			if(is_null($org_id))
				$q-> where("`org_id` IS NULL");
			else
				$q-> where("`org_id` = ?", $org_id);

			// Extra conditions (params)
			if(isset($params['i1']))
				$q-> where("`param1_int` = ?", $params['i1']);
			if(isset($params['i2']))
				$q-> where("`param2_int` = ?", $params['i2']);
			if(isset($params['s3']))
				$q-> where("`param3_string` = ?", $params['s3']);

			MVC_Core::$db-> query($q);
			// see what happened
			$sql_response = MVC_Core::$db-> get_Response();
			if(!isset($sql_response['rows_matched']) || $sql_response['rows_matched']==0) {
				// update failed, possibly because the row doesn't yet exist, so try to insert
				$values = array(
					"org_id"=> $org_id,
					"var"=> $var,
					"dt"=> time(),
					"data"=> $data_encoded? $data: json_encode($data)
				);
				if(isset($params['i1']))
					$values['param1_int'] = (int)$params['i1'];
				if(isset($params['i2']))
					$values['param2_int'] = (int)$params['i2'];
				if(isset($params['s3']))
					$values['param3_string'] = (string)$params['s3'];

				$q=QueryFactory::insert()
				-> table(MVC_Core::$db-> tab['stx'])
				-> values($values);

				MVC_Core::$db-> query($q);
			}
		}
		return true;
	}

	/**
	 * Calculates number of visible attached elements for a record, from the perspective of an user inside an organization.
	 * The value is then written in table 'rst' (record_statistics)
	 *
	 * @param int $rec_id
	 * @param int $org_id Necessary for the queries that calculate attached data (Empty when viewed from Admin account)
	 * @param int $user_id User for which the data is counted (Only used for non-admin)
	 * @param int $user_contact_id Contact ID for user (needed for messages - to properly calculate stats for messages that are private to the user
	 * @param int $user_cdep_id Department ID for the user (needed for files, notes);  (Only used for non-admin)
	 * @param array $stats_calc_list Selector for what kind of information is calculated (each part can be turned on and off individually)
	 * @param array $stx When non-empty, new calculations are overwriting existing ones (for the purpose of merging data)
	 */
	public function calc_Record_Statistics($rec_id, $org_id, $user_id, $user_contact_id = null, $user_cdep_id = null, $stats_calc_list = array(), $stx = array())
	{
		// Perhaps these queries should be in their respective models ? Or avoid loading too many classes by just replicating them here (only as counters, not full reading of data)

		// Calculate conversations count "convos"
		if(isset($stats_calc_list['convos'])) {
			$q=QueryFactory::select()
			-> table(MVC_Core::$db-> table('cnv'), "count(*) AS `convos`")
			-> where("`cnv`.type = 'issue'")
			-> where("`cnv`.link_id = ?", $rec_id);
			if(empty($org_id)) {
				// Admin mode - count all conversations which are not hidden
				$q-> where("`cnv`.visibility != 'hidden'");
			} else {
				// User mode; Depends on various things
				$q
				-> where("( CASE WHEN `cnv`.visibility = 'org' THEN `cnv`.org_id = ? END", $org_id)
				-> where_or("CASE WHEN `cnv`.visibility = 'shared' THEN FIND_IN_SET(?, `cnv`.access_orgs) END", $org_id)
				-> where_or("`cnv`.visibility = 'public'")
				-> where_or("(FIND_IN_SET(?, `cnv`.contact_ids) AND `cnv`.visibility != 'hidden') )", $user_contact_id);
			}
			// Private conversations show up only if the user's contact id is part of the involved contacts
			// -- This might have the unintended side effect of not showing private conversations sent using aliases

			$_tmp = MVC_Core::$db-> query($q)-> current();
			$stx = array_merge($stx, $_tmp);
		}
		// End "convos"

		// Calculate files count and total size "files", "filesize_total"
		if(isset($stats_calc_list['files'])) {
			$q=QueryFactory::select()
			-> table(MVC_Core::$db-> table('fil'), array('files'=> "count(*)", 'filesize_total' => "SUM(`size`)"))
			-> where("`fil`.linked_with = 'issue'")
			-> where("`fil`.linked_id = ?", $rec_id);
			if(!empty($org_id)) {
				// User mode; Depends on various things (Admin mode shows total files for record)
				$q
				-> where("CASE WHEN `fil`.visibility = 'org' THEN `fil`.org_id = ? ELSE 1 END", $org_id)
				-> where("CASE WHEN `fil`.visibility = 'shared' THEN FIND_IN_SET(?, `fil`.access_orgs) ELSE 1 END", $org_id)
				-> where("CASE WHEN `fil`.visibility = 'private' THEN `fil`.user_id = ? ELSE 1 END", $user_id);
				if(!is_null($user_cdep_id))
					$q-> where("CASE WHEN `fil`.visibility = 'dep' THEN `fil`.dep_id = ? ELSE 1 END", $user_cdep_id);
			}
			$_tmp = MVC_Core::$db-> query($q)-> current();
			if(empty($_tmp['filesize_total']))
				$_tmp['filesize_total'] = 0;
			$stx = array_merge($stx, $_tmp);
		}
		// End "files"

		// Calculate notes count "notes" - For notes, "public" or "shared" means the same / Share list for notes is not yet implemented
		// Extra: Only notes of content_type "plain" are calculated, because documents are stored here as well. Documents at the moment do not have a count.
		if(isset($stats_calc_list['notes'])) {
			$q=QueryFactory::select()
			-> table(MVC_Core::$db-> table('nte'), "count(*) AS `notes`")
			-> where("`nte`.linked_with = 'issue'")
			-> where("`nte`.link_id = ?", $rec_id)
			-> where("`nte`.content_type = 'plain'");
			if(!empty($org_id)) {
				// User mode; Depends on various things (Admin mode shows total notes for record)
				$q
				-> where("CASE WHEN `nte`.visibility = 'org' THEN `nte`.org_id = ? ELSE 1 END", $org_id)
				-> where("CASE WHEN `nte`.visibility = 'private' THEN `nte`.editor_id = ? ELSE 1 END", $user_id);
				if(!is_null($user_cdep_id))
					$q-> where("CASE WHEN `nte`.visibility = 'dep' THEN `nte`.dep_id = ? ELSE 1 END", $user_cdep_id);
			}
			$_tmp = MVC_Core::$db-> query($q)-> current();
			$stx = array_merge($stx, $_tmp);
		}
		// End "notes"

		// Calculate contacts count "ctx" - They are either "overall" or "per organization"; Special visibility cannot be set for contacts
		if(isset($stats_calc_list['ctx'])) {
			// TODO: Contacts statistics
			$q=QueryFactory::select()
			-> table(MVC_Core::$db-> table('c2i'), "count(*) AS `contacts`")
			-> where("`c2i`.issue_id = ?", $rec_id);
			if(!empty($org_id)) {
				// User mode; Count all linked contacts for the organization
				$q
				-> where("(CASE WHEN `c2i`.visibility = 'org' THEN `c2i`.org_id = ? ELSE 1 END)", $org_id);
			}
			$_tmp = MVC_Core::$db-> query($q)-> current();
			$stx = array_merge($stx, $_tmp);
		}
		// End "ctx"

		// Done; Write to the record statistics table

		$stx['rec_id'] = $rec_id;
		$stx['user_id'] = $user_id;
		$stx['cache_ut'] = time();

		MVC_Core::$db-> query(
			QueryFactory::replace()
			-> table(MVC_Core::$db-> tab['rst'])
			-> values($stx)
		);

		return $stx;
	}

	/**
	 * Reads record statistics for an user
	 * Also checks the timer when the statistics were saved. If expired, it returns nothing
	 *
	 * @param int $rec_id
	 * @param int $user_id
	 * @return array
	 */
	public function read_Record_Statistics($rec_id, $user_id)
	{
		// If statistics age is older than time()- self::$cache_ttl['org_count'], return null (so the statistics are recalculated/replaced)
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> tab['rst'], array('refs', 'convos', 'files', 'filesize_total', 'tasks', 'notes', 'contacts', 'custom_data'))
		-> where("`rec_id`= ?", $rec_id)
		-> where("`user_id` = ?", $user_id)
		-> where("`cache_ut` > ?", time()- self::$cache_ttl['record_statistics'])
		-> limit(1);

		return MVC_Core::$db-> query($q)-> current();
	}

	/**
	 * Clears record statistics for either a record (when record is deleted) or an user (when user security is altered or the user is deleted - In which case all statistics need to be recalculated)
	 * @param string $rec_id
	 * @param string $user_id
	 */
	public function clear_Record_Statistics($rec_id = null, $user_id = null)
	{

	}

//*** Statistics used by Charts ***//

	/**
	 * Chart configuration, depends on $statistics_method
	 * @var array
	 */
	protected $chart_config;

	/**
	 * After the data is read, more chart configuration is computed ( like max height ) - This can be done from cached data too
	 * - Can change Data and $this-> chart_config
	 * - Can be overridden by method-specific
	 *
	 * @param array $data
	 * @param array $params
	 * @param string $statistics_method Optionally, specify which method has been used ( some need extra post-processing )
	 */
	protected function stats_Post_Processing(&$data, $params, $statistics_method = null)
	{
		// Extra processing
		switch($params['period']) {
			case 'months':
				// Roll the data to end with previous month (so not Jan-> Dec but "Current Month+1 of last year" => "Current Month")
				$c_month = date('n');
				if($c_month!=12) { // 12 = December, nothing to roll
					$data_cut1 = array_splice($data, $c_month);
					$data = array_merge($data_cut1, $data);
				}
				break;
		}

		// Compute maxValue and unitInterval (for chart sizing)
		$this-> chart_config['maxValue'] = 0;
		foreach($data as $bar) {
			// Add everything together
			if(isset($params['maxHeightMode']) && $params['maxHeightMode'] == 'maximum') {
				foreach($bar as $index=> $count) {
					if($index!==$this-> chart_config['h_var'] && $count > $this-> chart_config['maxValue'])
						$this-> chart_config['maxValue'] = $count;
				}
			} else {
				// Sum
				$bar_sum = 0;
				foreach($bar as $index=> $count)
					if($index!==$this-> chart_config['h_var'])
						$bar_sum+=$count;

				if($bar_sum > $this-> chart_config['maxValue'])
					$this-> chart_config['maxValue'] = $bar_sum;
			}
		}
		$this-> chart_config['unitInterval'] = ceil(1+(int)($this-> chart_config['maxValue']/8)); // Attempt approximately 8 divisons on the Y-axis (divide in 2/4/8)

		switch($statistics_method) {
			// Related to "records_per_group" method, constructs series based on the data itself
			case 'records_per_group':
				// Extra filter, only used in model "records_per_group" - Filters out groups where there are are no records; This changes $this-> user_groups
				// 1. Parse groups and find the index into the array for each group ID
				$g_index = array();
				foreach($this-> user_groups as $idx=>$group)
					$g_index[$group['id']] = $idx;

				// Detected data entries which are not in a group which is part of original $this-> user_groups
				$invalid_group_detected = 0;
				foreach($data as &$serie)
					foreach($serie as $k=>$val) {
						if($k==$this-> chart_config['h_var'] || $k=='GrErr')
							continue; // Skip the name of the serie (or the special GrErr key)

						if(!isset($g_index[$k])) {
							unset($serie[$k]);
							if(!isset($serie['GrErr']))
								$serie['GrErr'] = 0;
							$serie['GrErr']+= $val;
							$invalid_group_detected+= $val;
							continue;
						}

						// Everything else gets calculated into total per group
						if(!isset($this-> user_groups[$g_index[$k]]['total']))
							$this-> user_groups[$g_index[$k]]['total'] = 0;

						$this-> user_groups[$g_index[$k]]['total']+= $val;
					}

				if($invalid_group_detected > 0)
					$this-> user_groups[] = array(
						'id'=> "GrErr",
						'name'=> "(invalid data group)",
						'total'=> $invalid_group_detected
					);
				break;
			// Related to "records_per_group" method, constructs series based on the data itself
			case 'records_per_provision':
				// Extra filter, only used in model "records_per_group" - Filters out groups where there are are no records; This changes $this-> user_groups
				// 1. Parse groups and find the index into the array for each group ID
				$g_index = array();
				foreach($this-> user_groups as $idx=>$group)
					$g_index[$group['id']] = $idx;

				// Detected data entries which are not in a group which is part of original $this-> user_groups
				$invalid_group_detected = 0;
				foreach($data as &$serie)
					foreach($serie as $k=>$val) {
						if($k==$this-> chart_config['h_var'] || $k=='GrErr')
							continue; // Skip the name of the serie (or the special GrErr key)

						if(!isset($g_index[$k])) {
							unset($serie[$k]);
							if(!isset($serie['GrErr']))
								$serie['GrErr'] = 0;
							$serie['GrErr']+= $val;
							$invalid_group_detected+= $val;
							continue;
						}

						// Everything else gets calculated into total per group
						if(!isset($this-> user_groups[$g_index[$k]]['total']))
							$this-> user_groups[$g_index[$k]]['total'] = 0;

						$this-> user_groups[$g_index[$k]]['total']+= $val;
					}

				if($invalid_group_detected > 0)
					$this-> user_groups[] = array(
						'id'=> "GrErr",
						'name'=> "Provision (USD)",
						'total'=> $invalid_group_detected
					);
				break;
		}
	}

	/**
	 * Organization record statistics (calls the plugin-version which derives from this data model)
	 */
	public function org_Statistics($org_id, $rt_id, $statistics_method, $params = null, $visible = true)
	{
		
		// Parameters processing (in all cases)
		if(empty($params))
			$params = array();

		$cache_params = array(
			'i1'=> $rt_id
		);
		if(isset($params['period'])) {
			$params['period'] = strtolower($params['period']);
			$cache_params['s3'] = $params['period'];
		}
		if(isset($params['subset']))
			$cache_params['i2'] = $params['subset'];
				
		// Verify if the method name is special (like "xfield" based stats) - in that case, swap to the special function, instead of searching for a class method

		if(substr($statistics_method, 0, 7)=='xfield_') {
			// echo "<br> 763 statistics_method : ".$statistics_method;
			// Xfield - The $statistics_method contains field type and it's key (which is searched in the database to get the ID)
			list($_unused, $t, $fk) = explode('_', $statistics_method, 3);
			// echo "<br> 670 fk : ".$fk;
			if(!in_array($t, array('enum', 'set')))
				return MVC_Core::$debug-> dump("Unsupported custom field statistics: {$t}.{$fk}", __METHOD__, null, "error");

			// Locate field by key, read and decode it's information
			$q=QueryFactory::select()
			-> table(MVC_Core::$db-> tab['xfd'], array('id', 'n', 't', 'o', 'cd'))
			-> where('`ist_id` = ?', $rt_id)
			-> where("`fk` = ?", $fk);
			$fdef = MVC_Core::$db-> query($q)-> current();


			if(!$fdef)
				return MVC_Core::$debug-> dump("XField not found for key: {$fk}. Cannot build statistics", __METHOD__, null, "error");

			// Create "cd_ex" out of "cd" (choice data), if it exists
			if(isset($fdef['cd']) && is_string($fdef['cd'])) {
				$fdef['cd'] = json_decode($fdef['cd'], true);
				$choices = array();
				foreach($fdef['cd'] as $c) {
					$c = trim($c);
					if(preg_match("/#([^:]+): (.*)/", $c, $matches))
						$choices[$matches[1]] = $matches[2];
					else
						$choices[$c] = $c;
				}
				$fdef['cd_ex'] = $choices;
			}
			// Explode options
			if(isset($fdef['o']))
				$fdef['o'] = json_decode($fdef['o'], true);

			// Some sxtra verifications depending on xfield type
			switch($t) {
				// Single choice
				case 'enum':
					if(!isset($fdef['cd_ex']))
						return MVC_Core::$debug-> dump("Choice data for Enum field not present or invalid", __METHOD__, null, "error");
					// Determine which column stores the data ("value" or "int_value")
					$fdef['fstore'] = isset($fdef['o']['num']) && $fdef['o']['num']? 'int_value': 'value';
					break;
				// Multiple choice
				case 'set':
					if(!isset($fdef['cd_ex']))
						return MVC_Core::$debug-> dump("Choice data for Set field not present or invalid", __METHOD__, null, "error");
					break;
			}
			return $this-> org_Statistics_XField($org_id, $rt_id, $fdef, $params, $cache_params, $visible);
		}
		// Meta field based statistics, from IOD (priority, status, count_tasks, count_convos, count_files, activity, process_length)
		// - TODO: The "count" methods are based number of attached elements: "none" = 0, "few" = 1-3, "several" = 4-9, "lots" = 10-39, "swarm" = 40+
		// - TODO: activity: "pending", "assig", "shared", "assigshar" (assigned and shared) - Based on the "assigned_to" and "xfer_to" columns ( 0, 0 = pending; >0, 0 = assig; 0, >0 = shared; >0, >0 = assigshar )
		// - TODO: process_length: "week" (<1 week), "month" (<1 mo), "quart" (<3 mo), "year" (<1 yr), "toolong" (>1 yr or not set)
		elseif(substr($statistics_method, 0, 5)=='meta_') {
			// Get the fragment after "meta" (eg: "meta_status")
			list($_unused, $t) = explode('_', $statistics_method, 2);

			if(!in_array($t, array('priority', 'status', 'count_tasks', 'count_convos', 'count_files', 'activity', 'process_length')))
				return MVC_Core::$debug-> dump("Unsupported meta statistics: {$t}", __METHOD__, null, "error");

			switch($t) {
				// Priority or Status statistics
				case 'priority':
				case 'status':
					return $this-> org_Statistics_Meta_SP($org_id, $rt_id, $t, $params, $cache_params, $visible);
				// Attached data counters statistics
				case 'count_tasks':
				case 'count_convos':
				case 'count_files':
				// 					return $this-> org_Statistics_Meta_Counter($org_id, $rt_id, substr($t, 6), $params, $cache_params);
				// Combines two fields to show how many records are being processed by humans (or not)
				case 'activity':
				// 					return $this-> org_Statistics_Meta_Activity($org_id, $rt_id, $params, $cache_params);
				// Statistics based on "process_length" field, grouping records based on how long it took for them to be closed, from the moment of organization linkage
				case 'process_length':
			// 					return $this-> org_Statistics_Meta_Process($org_id, $rt_id, $params, $cache_params);
			}
			return false;
		}


		// ... nope, not a special method, just search if standard method is implemented

		// Test if the statistics method exists in this plugin
		// if(!method_exists($this, "stats_".$statistics_method))
		// 	return MVC_Core::$debug-> dump("Invalid statistics method: {$statistics_method}", __METHOD__, null, "error");

		
		// 1: Prepare chart config, depending on statistics method (function MUST exist)
		// $this-> chart_config = array();
		// list($_unused, $t, $fk) = explode('_', $statistics_method, 3);
		// if(!call_user_func(array($this, "ccfg_".$statistics_method), $rt_id, $params))
		// 	return false;		

		
			$this->chart_config = array();

			list($_unused, $t, $fk) = explode('_', $statistics_method, 3);

			$callback = "ccfg_" . $statistics_method;

			if (!is_callable(array($this, $callback))) {
				// Log the missing callback for debugging
				error_log(
					'Missing statistics callback: ' . get_class($this) . '::' . $callback
				);

				return false;
			}

			if (!call_user_func(array($this, $callback), $rt_id, $params)) {
				return false;
			}


		if(!$visible)
			return null; // Just return nothing when non-visible; Config however is set

		// 2: Caching system
		//$data = $this-> cache_Read($statistics_method, time()- self::$cache_ttl[$statistics_method], $org_id, true, $cache_params);
		
		
		// if(!$data) {
		// 	$data = call_user_func(array($this, "stats_".$statistics_method), $org_id, $rt_id, $params);
		// 	$this-> cache_Write($statistics_method, $data, $org_id, false, $cache_params);
		// }
			
		
		$data = call_user_func(array($this, "stats_".$statistics_method), $org_id, $rt_id, $params);
	
		
		$this-> cache_Write($statistics_method, $data, $org_id, false, $cache_params);

		$this-> stats_Post_Processing($data, $params, $statistics_method);
		return $data;
	}

	/**
	 * Construct statistics information related to one "Enum" field (it's values become the series)
	 *
	 * @param int $org_id
	 * @param int $rt_id
	 * @param array $fdef Field definition (must contain "id", "n", "cd_ex"
	 * @param array $params
	 * @param array $cache_params
	 * @param bool $visible
	 */
	protected function org_Statistics_XField($org_id, $rt_id, $fdef, $params, $cache_params, $visible = true)
	{
            
		// 1: Chart config for "Enum Xfield"
		$this-> chart_config = array(
			'name'=> $fdef['n']
		);
		if(!$this-> ccfg_Setup_Period($params['period'], $fdef['n']))
			return false; // CCFG failed

		if(!$visible)
			return null; // Just return nothing when non-visible; Config however is set

		// 2: Caching system
		$statistics_method = "xf_{$fdef['t']}_{$fdef['id']}"; // Field's Type and ID is in the cache entry name
		$data = $this-> cache_Read($statistics_method, time()- self::$cache_ttl["xfield_{$fdef['t']}"], $org_id, true, $cache_params);

		// 18102023 - added by sourav start
		$data = array();
		// 18102023 - added by sourav end

		if(!$data) {
			// Depending on field type...
			switch($fdef['t']) {
				case 'enum':
					$data = $this-> stats_XField_Enum($org_id, $rt_id, $fdef, $params); break;
				case 'set':
					$data = $this-> stats_XField_Set($org_id, $rt_id, $fdef, $params); break;
			}
 			$this-> cache_Write($statistics_method, $data, $org_id, false, $cache_params);
		}

		// Finally, extra parameters also depending on field type
		switch($fdef['t']) {
			case 'set':
				$params['maxHeightMode'] = "maximum";
				break;
		}
		$this-> stats_Post_Processing($data, $params);

		// Build 'Series' out of $fdef['cd_ex'], to be used in build_Chart_Config which will unavoidably come later
		$this-> chart_config['series'] = array();
		foreach($fdef['cd_ex'] as $option=> $display)
			$this-> chart_config['series'][] = array('dataField'=> (string)$option, 'displayText'=> (string)$display);

		// For Enum's, check if the options do not already contain a value "0"; If they don't, add "(not set)" to the options, unless the field is mandatory or it has a default value and it's not possible to have "not set"
		if($fdef['t'] == 'enum' && !isset($fdef['cd_ex']["0"]) && !isset($fdef['o']['def']) && empty($fdef['o']['req'])) {
			$this-> chart_config['series'][] = array('dataField'=> "0", 'displayText'=> "(not set)");
		}

		// ... and finally return the data
		return $data;
	}

	// 18102023 - create new function by iwi | start
	protected function filter_charts_query_func($org_id, $rt_id, $fdef, $params)
	{
		$rt_status =  $params['rt_status'] !='' ? $params['rt_status'] : '';
		if ($rt_status == 'active' OR $_SESSION['chart_filter']['status'] == 'active') {
			$where = "`iod`.process != 'hidden' AND `iod`.process != 'closed'";
		}elseif ($rt_status == 'closed' OR $_SESSION['chart_filter']['status'] == 'closed') {
			$where = "`iod`.process = 'closed'";
		}else{
			$where = "`iod`.process != 'hidden'";
		}
		
		$joinQuery = '';
		$_grouping = '';
		$array_chart_name = array('pstage','subm_type');
		if(!empty($_SESSION['filter_chart_name']) && $rt_id == 1){  
			foreach($_SESSION['filter_chart_name'] as $key=>$val) {
				$q=QueryFactory::select()
				-> table(MVC_Core::$db-> tab['xfd'], array('id', 'n', 't', 'o', 'cd'))
				-> where('`ist_id` = ?', $rt_id)-> where("`fk` = ?", $val);
				$_fdef = MVC_Core::$db-> query($q)-> current();
				
				if(!empty($_SESSION['chart_filter']['claim_type']) && $_SESSION['chart_filter']['claim_type'] != 'all' && $val == 'pstage'){
					$where .= " AND `ixf".$key."`.`value` = '".$_SESSION['chart_filter']['claim_type']."' ";
				}
				
				if(!empty($_SESSION['chart_filter']['claimant_type']) && $_SESSION['chart_filter']['claimant_type'] != 'all' && $val == 'subm_type'){
					$where .= " AND `ixf".$key."`.`value` = '".$_SESSION['chart_filter']['claimant_type']."' ";
				}

				if(!empty($_SESSION['chart_filter']['movement']) && $_SESSION['chart_filter']['movement'] != 'all' && $val == 'move'){
					$where .= " AND `ixf".$key."`.`value` = '".$_SESSION['chart_filter']['movement']."' ";
				}

				if(!empty($_SESSION['chart_filter']['carrier_liability']) && $_SESSION['chart_filter']['carrier_liability'] != 'all' && $val == 'liable'){
					$where .= " AND `ixf".$key."`.`value` = '".$_SESSION['chart_filter']['carrier_liability']."' ";
				}

				if(!empty($_SESSION['chart_filter']['claim_aspects']) && $_SESSION['chart_filter']['claim_aspects'] != 'all' && $val == 'claim_type'){
					$where .= " AND `ixf".$key."`.`value` LIKE '%".$_SESSION['chart_filter']['claim_aspects']."%' ";
				}
				
				$joinQuery .= " LEFT JOIN `issue_fields` AS ixf".$key." ON (`ixf".$key."`.issue_id = `iss`.id AND `ixf".$key."`.fk_id = ".$_fdef['id'].")";
			}
		}
		switch($params['period']) {
			// Shows last 12 months, ending with previous month
			case 'months':
				// $_grouping = "  YEAR(FROM_UNIXTIME(`iod`.`linktime`)) " ;
				$_grouping = "`iod`.linktime > ".strtotime(date("Y-m-t")." +1 day -1 year") ;
				break;
				// Shows quarters for current year
			case 'quarters':
				$_grouping = "YEAR(FROM_UNIXTIME(`iod`.`linktime`)) = YEAR(CURDATE())";
				break;
				// Show five years ; TODO: It should be more configurable
			case 'years':
				$_grouping = "YEAR(FROM_UNIXTIME(`iod`.`linktime`))";
				break;
			default:
				return MVC_Core::$debug-> dump("Invalid parameter: Period: {$params['period']}", __METHOD__, null, "error");
		}
		return array($where,$joinQuery,$_grouping);
		// 18102023 - added by sourav end
	}
	// 18102023 - create new function by iwi | end
	

	/**
	 * Special XField Enum statistics - May apply for any xfield of this type
	 * @param int $org_id
	 * @param int $rt_id
	 * @param array $fdef
	 * @param unknown $params
	 */
	protected function stats_XField_Enum($org_id, $rt_id, $fdef, $params)
{   
    // Determine process filter
    $rt_status = !empty($params['rt_status']) ? $params['rt_status'] : '';
    if ($rt_status === 'active' || $_SESSION['chart_filter']['status'] === 'active') {
        $where = "`iod`.process != 'hidden' AND `iod`.process != 'closed'";
    } elseif ($rt_status === 'closed' || $_SESSION['chart_filter']['status'] === 'closed') {
        $where = "`iod`.process = 'closed'";
    } else {
        $where = "`iod`.process != 'hidden'";
    }

    // Apply organization-specific chart filters
    $charts_query_func = $this->filter_charts_query_func($org_id, $rt_id, $fdef, $params);
    $where = !empty($charts_query_func[0]) ? $charts_query_func[0] : $where;  // fallback
    $joinQuery = !empty($charts_query_func[1]) ? $charts_query_func[1] : '';
    $_grouping = !empty($charts_query_func[2]) ? $charts_query_func[2] : '1=1'; // safe default

    // Base query
    $q = QueryFactory::select()
        ->table(MVC_Core::$db->table('iss'), array('y_axis' => "COUNT(*)"))
        ->where("`iss`.type_id = ?", $rt_id);

    // Special handling for quality_event charts
    if (!empty($params['chart_page']) && $params['chart_page'] === 'quality_event') {

        // Alias for extra issue_fields join
        $joinQuery .= " LEFT JOIN `issue_fields` AS if_1228 
                        ON `iss`.id = if_1228.issue_id AND if_1228.fk_id = '1228' ";

        // Determine grouping and time filter
        $period = !empty($params['period']) ? $params['period'] : '';
        switch ($period) {
            case 'months':
                $grouping = "MONTH(FROM_UNIXTIME(if_1228.int_value))";
                $timestamp = strtotime(date("Y-m-t") . " +1 day -1 year"); // last 12 months
                $where .= " AND if_1228.int_value > $timestamp";
                break;

            case 'quarters':
                $grouping = "QUARTER(FROM_UNIXTIME(if_1228.int_value))";
                $where .= " AND YEAR(FROM_UNIXTIME(if_1228.int_value)) = YEAR(CURDATE())";
                break;

            case 'years':
                $grouping = "YEAR(FROM_UNIXTIME(if_1228.int_value))";
                break;

            default:
                return MVC_Core::$debug->dump("Invalid parameter: Period: " . $params['period'], __METHOD__, null, "error");
        }

    } else {
        $grouping = $this->mqy_grouping($q, $params);
    }

    // Apply subset filters
    $this->ac_subset($q, $params['subset']);

    // Build final SQL
    $sql = "SELECT COUNT(*) AS `y_axis`, 
                   $grouping AS `x_axis`, 
                   `ixf`.`" . $fdef['fstore'] . "` AS `value`
            FROM `issues` AS iss
            LEFT JOIN `issue_orgdata` AS iod ON `iod`.issue_id = `iss`.id
            LEFT JOIN `issue_fields` AS ixf 
                ON (`ixf`.issue_id = `iss`.id AND `ixf`.fk_id = " . $fdef['id'] . ")
            $joinQuery
            WHERE `iss`.type_id = $rt_id
              
              AND `iod`.org_id = $org_id
              AND $where
            GROUP BY $grouping, `ixf`.`" . $fdef['fstore'] . "`";

    // Debug
//    echo "<br><br>SQL: " . $sql;

    // Execute query
    $r = MVC_Core::$db->query($sql);
    if (!$r) {
        return MVC_Core::$debug->dump("Statistics query failed", __METHOD__, null, "error");
    }

    return $this->compile_Org_Statistics($r, $fdef['fstore']);
}


	/**
	 * Special XField Enum statistics - May apply for any xfield of this type
	 * @param int $org_id
	 * @param int $rt_id
	 * @param array $fdef
	 * @param unknown $params
	 */
	protected function stats_XField_Set($org_id, $rt_id, $fdef, $params)
	{	
		
		$charts_query_func =  $this->filter_charts_query_func($org_id, $rt_id, $fdef, $params);
		$where = $charts_query_func[0];
		$joinQuery = $charts_query_func[1];
		$_grouping = $charts_query_func[2];

		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> table('iss'), array('y_axis'=> "COUNT(*)")) // We're counting records, no need for other fields
		-> where("`iss`.type_id = ?", $rt_id);

		if (!empty($params['chart_page']) && $params['chart_page'] === 'quality_event') {

        // Alias for extra issue_fields join
        $joinQuery .= " LEFT JOIN `issue_fields` AS if_1228 
                        ON `iss`.id = if_1228.issue_id AND if_1228.fk_id = '1228' ";

        // Determine grouping and time filter
        $period = !empty($params['period']) ? $params['period'] : '';
        switch ($period) {
            case 'months':
                $grouping = "MONTH(FROM_UNIXTIME(if_1228.int_value))";
                $timestamp = strtotime(date("Y-m-t") . " +1 day -1 year"); // last 12 months
                $where .= " AND if_1228.int_value > $timestamp";
                break;

            case 'quarters':
                $grouping = "QUARTER(FROM_UNIXTIME(if_1228.int_value))";
                $where .= " AND YEAR(FROM_UNIXTIME(if_1228.int_value)) = YEAR(CURDATE())";
                break;

            case 'years':
                $grouping = "YEAR(FROM_UNIXTIME(if_1228.int_value))";
                break;

            default:
                return MVC_Core::$debug->dump("Invalid parameter: Period: " . $params['period'], __METHOD__, null, "error");
        }

    }else {
		$grouping = $this-> mqy_grouping($q, $params);
               }


		$q = "SELECT COUNT(*) AS `y_axis`,$grouping AS `x_axis`,`ixf`.`value`
		FROM `issues` AS iss
		LEFT JOIN `issue_orgdata` AS iod ON `iod`.issue_id = `iss`.id
		LEFT JOIN `issue_fields` AS ixf ON (`ixf`.issue_id = `iss`.id AND `ixf`.fk_id = ".$fdef['id'].") $joinQuery 
		WHERE `iss`.type_id = $rt_id  AND `iod`.org_id = $org_id AND $where
		GROUP BY $grouping, `ixf`.value ";
//		 echo "<br><br> 1090 sql : ".$q;
		$r = MVC_Core::$db->query($q);
		if(!$r)
			return MVC_Core::$debug-> dump("Statistics query Failed", __METHOD__, null, "error");

		// "Set" is a special case where one row can add to multiple stats simultaneously
		$data = array();
		$h_var = $this-> chart_config['h_var'];
		if(isset($this-> chart_config['h_names'])) {
			// Returned values are indexes in h_names ( months, quarters, perhaps other indexable periods, like day of month )
			foreach($this-> chart_config['h_names'] as $hname) {
				$data[] = array($h_var=> $hname);
			};
			// Begin distributing results into $data
			foreach($r as $row) {
				if(is_null($row['value']))
					continue; // Nothing here = Nothing is marked, nothing to add

				$values = json_decode($row['value']);
				foreach($values as $relvalue) {
					if(!isset($data[(int)$row['x_axis']-1][$relvalue]))
						$data[(int)$row['x_axis']-1][$relvalue] = 0;
					$data[(int)$row['x_axis']-1][$relvalue]+= $row['y_axis'];
				}
			}
		} else {
			// Without h_names, returned values are actual array indexes (Named values, as returned from the database)
			foreach($r as $row) {
				if(is_null($row['value']))
					continue; // Nothing here = Nothing is marked, nothing to add

				// Init column
				if(!isset($data[$row['x_axis']]))
					$data[$row['x_axis']] = array($h_var=> $row['x_axis']);

				$values = json_decode($row['value']);
				foreach($values as $relvalue) {
					// Init bar
					if(!isset($data[$row['x_axis']][$relvalue]))
						$data[$row['x_axis']][$relvalue] = 0;

					$data[$row['x_axis']][$relvalue]+= $row['y_axis'];
				}
			}
			if(count($data) == 0)
				return array(array($h_var=> date('Y'))); // When completly empty, return just current year with no actual data.
			$data = array_values($data);
		}

		return $data;
	}

	/**
	 * Org statistics based on Meta fields from IOD ("priority_key", "status_key")
	 * @param int $org_id
	 * @param int $rt_id
	 * @param array $which_one "priority" or "status"
	 * @param array $params
	 * @param array $cache_params
	 * @param bool $visible
	 */
	protected function org_Statistics_Meta_SP($org_id, $rt_id, $which_one, $params, $cache_params, $visible = true)
	{


		// 1: Chart config for "Meta"
		$this-> chart_config = array(
			'name'=> "Records with ".ucfirst($which_one)
		);
		if(!$this-> ccfg_Setup_Period($params['period'], "Records with ".ucfirst($which_one).'...'))
			return false; // CCFG failed

		if(!$visible)
			return null; // Just return nothing when non-visible; Config however is set

		// 2: Caching system
		$statistics_method = "meta_{$which_one}"; // Field's Type and ID is in the cache entry name
		$data = $this-> cache_Read($statistics_method, time()- self::$cache_ttl["meta_sp"], $org_id, true, $cache_params);
		if(!$data) {
			$data = $this-> stats_Meta_SP($org_id, $rt_id, $which_one, $params);
			$this-> cache_Write($statistics_method, $data, $org_id, false, $cache_params);
		}


		$this-> stats_Post_Processing($data, $params);

		// Build 'Series' from the list of Statuses or Priorities
		$m_record = new App_Model_Issue();
		$rt_def = $m_record-> ist_Read($rt_id, null);
		if(empty($rt_def))
			return MVC_Core::$debug-> dump("Unable to locate record type: {$rt_id}", __METHOD__, null, "error");

		$this-> chart_config['series'] = array();
		if($which_one == 'priority') {
			foreach($rt_def['priorities_ex'] as $option=> $display)
				$this-> chart_config['series'][] = array('dataField'=> (string)$option, 'displayText'=> substr($display, 2)); // Remove the auto-sorting number
		} else { // Status
			foreach($rt_def['statuses_ex'] as $option=> $display)
				$this-> chart_config['series'][] = array('dataField'=> (string)$option, 'displayText'=> $display);
		}

		// ... and finally return the data
		
		return $data;
	}

	/**
	 * Meta Status or Priority statistics
	 * @param int $org_id
	 * @param int $rt_id
	 * @param string $which_one
	 * @param array $params
	 */
	protected function stats_Meta_SP($org_id, $rt_id, $which_one, $params)
	{
		// Start building the chart and data query
		// This function uses the organization specific data
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> table('iss'), array('y_axis'=> "COUNT(*)")) // We're counting records, no need for other fields
		-> where("`iss`.type_id = ?", $rt_id);

		$grouping = $this-> mqy_grouping($q, $params);

		// Join IOD for organization and process filter
		$q	-> table(MVC_Core::$db-> table('iod'), array($which_one=> $which_one.'_key', "x_axis" => $grouping), "left", "`iod`.issue_id = `iss`.id")
		-> where("`iod`.org_id = ?", $org_id)
		-> where("`iod`.process != 'hidden'")
		-> group($grouping. ", `iod`.".$which_one.'_key');

		// echo "<hr>";
		// echo "<pre>";
		// echo "1024".$q;
		
		$this-> ac_subset($q, $params['subset']);

		$r = MVC_Core::$db->query($q);
		if(!$r)
			return MVC_Core::$debug-> dump("Statistics query Failed", __METHOD__, null, "error");

		return $this-> compile_Org_Statistics($r, $which_one);
	}

// HELPERS //

	/**
	 * Standard Monthly/Quarterly/Yearly grouping of data (reused in lots of places)
	 * @param QueryFactory $q
	 * @param array $params
	 */
	protected function mqy_grouping(QueryFactory &$q, $params)
	{
		switch($params['period']) {
			// Shows last 12 months, ending with previous month
			case 'months':
				$grouping = "MONTH(FROM_UNIXTIME(`iod`.linktime))";
				$q-> where("`iod`.linktime > ?", strtotime(date("Y-m-t")." +1 day -1 year")); // Show all exactly from 12 months before end of this month
				break;

				// Shows quarters for current year
			case 'quarters':
				$grouping = "QUARTER(FROM_UNIXTIME(`iod`.linktime))";
				$q-> where("YEAR(FROM_UNIXTIME(`iod`.linktime)) = YEAR(CURDATE())"); // Show data from this year
				break;

				// Show five years ; TODO: It should be more configurable
			case 'years':
				$grouping = "YEAR(FROM_UNIXTIME(`iod`.linktime))";
				break;

			default:
				return MVC_Core::$debug-> dump("Invalid parameter: Period: {$params['period']}", __METHOD__, null, "error");
		}
		return $grouping;
	}

	/**
	 * Adds the conditions for filtering based on the Active/Closed subset (The only one used right now)
	 * @param QueryFactory $q
	 * @param int $subset 0 = All, 1 = Active, 2 = Closed
	 */
	protected function ac_subset(QueryFactory &$q, $subset)
	{
		switch($subset) {
			case 0: // All
				// Nothing to do here, all non-hidden are already counted
				break;
			case 1: // Active
				$q-> where("`iod`.process != 'closed'");
				break;
			case 2: // Closed
				$q-> where("`iod`.process = 'closed'");
				break;
			default:
				return MVC_Core::$debug-> dump("Invalid parameter - Subset: {$subset}", __METHOD__, null, "error");
		}
	}

	/**
	 * Helper function that builds $this-> chart_config (to shorten the ccfg function code)
	 * @param string $period
	 * @param string $title
	 */
	protected function ccfg_Setup_Period($period, $title, $description = null)
	{	



		switch($period) {
			case 'months':
				// Shows last 12 months, ending with previous month
				$this-> chart_config['title'] = $title." (per Month)";
				$this-> chart_config['description'] = $description?:("From ".date("M Y", strtotime(date("Y-m-t")." +1 day -1 year"))." to ".date("M Y"));
				$this-> chart_config['h_names'] = self::$english_months_iso3;
				$this-> chart_config['h_var'] = "Month";
				break;
			case 'quarters':
				// Shows quarters for current year
				$this-> chart_config['title'] =  $title." (per Quarter)";
				$this-> chart_config['description'] = $description?:"This year (".date("Y").")";
				$this-> chart_config['h_names'] = self::$english_quarters_iso3;
				$this-> chart_config['h_var'] = "Quarter";
				break;
			case 'years':
				// Show five years ; TODO: It should be more configurable
				$this-> chart_config['title'] = $title;
				$this-> chart_config['description'] = $description?:"(last five Years)";
				// Direct display of returned values ( "h_names" doesn't exist )
				$this-> chart_config['h_var'] = "Year";
				break;
			default:
				return MVC_Core::$debug-> dump("Invalid parameter - Period: {$period}", __METHOD__, null, "error");
		}


		return true;
	}

	/**
	 * Builds and returns data by compiling the returned statistics
	 * @param MVC_Database_Result $r
	 */
	protected function compile_Org_Statistics(MVC_Database_Result $r, $relevant_variable, $translator = null)
	{
		$data = array();

		$h_var = $this-> chart_config['h_var'];
		if(isset($this-> chart_config['h_names'])) {
			// Returned values are indexes in h_names ( months, quarters, perhaps other indexable periods, like day of month )
			foreach($this-> chart_config['h_names'] as $hname) {
				$data[] = array($h_var=> $hname);
			};
			// Begin distributing results into $data
			foreach($r as $row) {
				if(!empty($translator))
					$relvalue = $translator[$row[$relevant_variable]];
				else {
					$relvalue = $row[$relevant_variable];
					if(is_null($relvalue))
						$relvalue = 0; // Translate NULLs to 0 (needed in some cases where not having a value means "option 0")
				}

				if(!isset($data[(int)$row['x_axis']-1][$relvalue]))
					$data[(int)$row['x_axis']-1][$relvalue] = 0;
				$data[(int)$row['x_axis']-1][$relvalue]+= $row['y_axis'];
			}
		} else {
			// Without h_names, returned values are actual array indexes (Named values, as returned from the database)
			foreach($r as $row) {
				if(!empty($translator))
					$relvalue = $translator[$row[$relevant_variable]];
				else {
					$relvalue = $row[$relevant_variable];
					if(is_null($relvalue))
						$relvalue = 0; // Translate NULLs to 0 (needed in some cases where not having a value means "option 0")
				}

				// Init column
				if(!isset($data[$row['x_axis']]))
					$data[$row['x_axis']] = array($h_var=> $row['x_axis']);
				// Init row
				if(!isset($data[$row['x_axis']][$relvalue]))
					$data[$row['x_axis']][$relvalue] = 0;

				$data[$row['x_axis']][$relvalue]+= $row['y_axis'];
			}
			if(count($data) == 0)
				return array(array($h_var=> date('Y'))); // When completly empty, return just current year with no actual data.
			$data = array_values($data);
		}

		// print_r($data);
		return $data;
	}

	/**
	 * Gets the chart name (sepparate from the jqxChart parameters) or the configuration of the controls. Can be called after everything else has been initialized
	 * @param unknown $statistics_method
	 * @return string
	 */
	public function get_Chart_Name($statistics_method)
	{
		return $this-> chart_config['name']?:"Unnamed chart"; // Not part of the normal config - Doesn't include time period or other elements ; Removed on the JS side before initializing the chart.
	}

	/**
	 * Separate function for constructing chart configuration, because org_Statistics should be Cached; However, chart_Config doesn't change
	 * @param string $statistics_method
	 * @param string $params
	 */
	public function build_Chart_Config($statistics_method, $params = null)
	{
		
		// Check if $statistics_method is special (universal)
		if(substr($statistics_method, 0, 7)=='xfield_') {
			// Xfield - The $statistics_method contains field type and it's key (which is searched in the database to get the ID)
			list($_unused, $t, $fk) = explode('_', $statistics_method, 3);

			switch($t) {
				case 'enum':
					return $this-> chart_config_Columns($params, 'stackedcolumn');
				case 'set':
					return $this-> chart_config_Columns($params, 'column');
				default:
					return MVC_Core::$debug-> dump("Unsupported custom field statistics: {$t}.{$fk}", __METHOD__, null, "error");
			}
		} elseif(substr($statistics_method, 0, 5)=='meta_') {

			// Get the fragment after "meta" (eg: "meta_status")
			list($_unused, $t) = explode('_', $statistics_method, 2);

			if(!in_array($t, array('priority', 'status', 'count_tasks', 'count_convos', 'count_files', 'activity', 'process_length')))
				return MVC_Core::$debug-> dump("Unsupported meta statistics: {$t}", __METHOD__, null, "error");

			// All these are standard stacked columns - "Series" are built before
			return $this-> chart_config_Columns($params, 'stackedcolumn');
		}

		// Not special - Go ahead with standard method locator

		if(!method_exists($this, "chart_".$statistics_method))
			return MVC_Core::$debug-> dump("Invalid chart configuration method: {$statistics_method}", __METHOD__, null, "error");

		// echo "<br>string : "."chart_".$statistics_method."<br>";

		// Ok, it does exist. Call it
		if(empty($params))
			$params = array();

		return call_user_func(array($this, "chart_".$statistics_method), $params);
	}

	/**
	 * Common xAxis configuration for all charts
	 * @param array $params Extra visual parameters (passed from build_Chart_Config)
	 */
	protected function chart_config_xAxis($params)
	{
		return array(
			'dataField'=> $this-> chart_config['h_var'],
			'unitInterval'=> 1,
			'axisSize'=> "auto",
			'tickMarks'=> array(
				'visible'=> true,
				'interval'=> 1,
				'color'=> "lightgray",
			),
			'gridLines'=> array(
				'visible'=> true,
				'interval'=> 1,
				'color'=> "lightgray"
			)
		);
	}

	/**
	 * Common valueAxis configuration for all charts
	 * @param array $params Extra visual parameters (passed from build_Chart_Config)
	 */
	protected function chart_config_valueAxis($params)
	{
		return array(
			'unitInterval'=> $this-> chart_config['unitInterval'],
			'minValue'=> 0,
			'maxValue'=> $this-> chart_config['maxValue'],
			'labels'=> array(
				'horizontalAlignment'=> "right"
			),
			'tickMarks'=> array(
				'color'=> "lightgray",
			)
		);
	}

	/**
	 * General chart configuration that builds columns based chart ($this-> chart_config['series'] must contain proper configuration for series)
	 *
	 * @param array $params
	 */
	protected function chart_config_Columns($params, $column_type)
	{
		if(!isset($this-> chart_config['series']))
			return MVC_Core::$debug-> dump("Series definition not present in Chart_Config", __METHOD__, null, "error");

		// Determine some variations on how columns look, depending on $column_type
		switch($column_type) {
			case 'stackedcolumn':
				$columnsGapPercent = 50;
				$seriesGapPercent = 10;
				break;
			case 'column':
				$columnsGapPercent = 10;
				$seriesGapPercent = 0;
				break;
			default:
				$columnsGapPercent = null;
				$seriesGapPercent = null;
		}

		return array(
			'title'=> $this-> chart_config['title']?:"Unnamed columns chart", // Title as it appears on the chart header
			'description'=> $this-> chart_config['description']?:"",
			'xAxis'=> $this-> chart_config_xAxis($params),
			'valueAxis'=> $this-> chart_config_valueAxis($params),
			// Groups are specific for chart type
			'seriesGroups'=> array(
				array(
					'type'=> $column_type,
					'columnsGapPercent'=> $columnsGapPercent,
					'seriesGapPercent'=> $seriesGapPercent,
					'series'=> $this-> chart_config['series']
				)
			)
		);
	}

	/**
	 * Reads from the database information about the groups from the specific record id that the user has access to
	 * This can be used to filter out statistics which they shouldn't normally see, or in some case display all available groups
	 *
	 * FIXME: This should probably be cached in session, as it's unlikely to change during normal operation. Also, it's placement is not ok here !
	 */
	protected static function user_Available_Groups($rt_id)
	{
		$m_rec = new App_Model_Issue();
		$rt_def = $m_rec-> ist_Read($rt_id, null);

		if(!isset($rt_def['settings']['group_type']))
			return MVC_Core::$debug-> dump("This record type doesn't support groups", __METHOD__, null, "error");

		$m_user = new App_Model_User();
		$group_id_list = $m_user-> is_Unrestricted() || $m_user-> test_ACL('privileges', 'issues_view_orgowned')? array(): array_keys($m_user-> read_ACL('groups'));

		// Extra filter - Check if issue type has restrictions on which group IDs are visible for it ( this are absolute and do not depend on any rights or who accesses the issue type, including admin )
		// - It allows group type sharing between multiple issue types
		if(!empty($rt_def['group_ids']))
			$group_id_list = array_intersect($group_id_list, $rt_def['group_ids']);
		if(empty($group_id_list))
			$group_id_list = $rt_def['group_ids'];

		$m_group = new App_Model_Group($rt_def['settings']['group_type']);

		// Read the available groups, based on the list
		$r = $m_group-> get_List(array(), function (QueryFactory &$q) use ($group_id_list) {
			if (count($someVariable ?? []) > 0)
				$q-> where('`grp`.id IN(?)', $group_id_list);
		}, null, "`name` ASC");

		if(!$r || count($r)==0)
			return MVC_Core::$debug-> dump("No groups were found", __METHOD__, null, "error");

		$groups = array();
		foreach($r as $row) {
			// Filter for group name tags (removes trailing [...])
			$name = trim(preg_replace('/\[.*?\]/', "", $row['name']));
			$groups[] = array(
				'id'=> (int)$row['id'],
				'name'=> $name
			);
		}
		return $groups;
	}

// Specific statistics methods - Global methods //

// "total_records" (active/closed) - Valid in every record type //

	/**
	 * Statistics Method for calculating total records per time period for a specified organization
	 * - Requires that pre_Read_Chart_Config to be called before this ( automatically done by the "org_Statistics" method )
	 * - To complete the operation, post_Read_Processing should be called as well ( automaticall done by the "org_Statistics" method )
	 *
	 * @param $org_id Organization ID
	 * @param $params Extra parameters (like time period)
	 */
	protected function stats_total_records($org_id, $rt_id, $params)
	{	
		// Default parameters - Show record statistics per month
		if(!is_array($params)) {
			$params = array(
				'period'=> "month"
			);
		}

		
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> table('iss'), array('y_axis'=> "COUNT(*)")) // We're counting records, no need for other fields
		-> where("`iss`.type_id = ?", $rt_id);
//		$grouping = $this-> mqy_grouping($q, $params);
               if(isset($params['chart_page']) && $params['chart_page']=='quality_event'){
                   $q-> table('issue_fields', array('event_time'=> "int_value"), "left", '`iss`.id = `issue_fields`.issue_id AND `issue_fields`.fk_id = "1228"') ;
                switch($params['period']) {
			// Shows last 12 months, ending with previous month
			case 'months':
				$grouping = "MONTH(FROM_UNIXTIME(`issue_fields`.int_value))";
				$q-> where("`issue_fields`.int_value > ?", strtotime(date("Y-m-t")." +1 day -1 year")); // Show all exactly from 12 months before end of this month
				break;

				// Shows quarters for current year
			case 'quarters':
				$grouping = "QUARTER(FROM_UNIXTIME(`issue_fields`.int_value))";
				$q-> where("YEAR(FROM_UNIXTIME(`issue_fields`.int_value)) = YEAR(CURDATE())"); // Show data from this year
				break;

				// Show five years ; TODO: It should be more configurable
			case 'years':
				$grouping = "YEAR(FROM_UNIXTIME(`issue_fields`.int_value))";
				break;

			default:
				return MVC_Core::$debug-> dump("Invalid parameter: Period: {$params['period']}", __METHOD__, null, "error");
		}
               } else {
                   $grouping = $this-> mqy_grouping($q, $params);
               }
		
		$charts_query_func =  $this->filter_charts_query_func($org_id, $rt_id, array(), $params);
		$where = $charts_query_func[0];
		$joinQuery = $charts_query_func[1];
		$_grouping = $charts_query_func[2];
                if(isset($params['chart_page']) && $params['chart_page']=='quality_event'){
                    $joinQuery .=" LEFT JOIN `issue_fields`  ON `iss`.id = `issue_fields`.issue_id AND `issue_fields`.fk_id = '1228' ";
                }
                


		$sql = "SELECT COUNT(*) AS `y_axis`, `iod`.`process`, $grouping AS `x_axis`
		FROM `issues` AS iss
		LEFT JOIN `issue_orgdata` AS iod ON `iod`.issue_id = `iss`.id
		
		$joinQuery   
		WHERE `iss`.type_id = $rt_id AND $_grouping AND `iod`.org_id = $org_id AND $where
		GROUP BY $grouping, `iod`.process";

		$r = MVC_Core::$db->query($sql);

		if(!$r)
			return MVC_Core::$debug-> dump("Statistics query Failed", __METHOD__, null, "error");


		return $this-> compile_Org_Statistics($r, 'process', array(
			'pending'=> "Active",
			'opened'=> "Active",
			'active'=> "Active",
			'shared'=> "Active",
			'closed'=> "Closed"
		));
	}

	protected function ccfg_records_per_provision($rt_id, $params)
	{
		$this-> chart_config['name'] = "Total Provision In USD ";
		$this-> chart_config['description'] = "All Active Provisions ";
		return $this-> ccfg_Setup_Period($params['period'], $this-> chart_config['name'],$this-> chart_config['description']);
	}

	/**
	 * Chart config specific to "total_records" model
	 * @param array $params
	 * @return boolean
	 */
	protected function ccfg_total_records($rt_id, $params)
	{
		$this-> chart_config['name'] = "Total Records";
		return $this-> ccfg_Setup_Period($params['period'], $this-> chart_config['name']);

	}

	/**
	 * Chart configuration associated with the returned statistics "total_records"
	 */
	protected function chart_total_records($params)
	{
		// Known, fixed series for this one
		$this-> chart_config['series'] = array(
			array('dataField'=> "Active", 'displayText'=> "Active"),
			array('dataField'=> "Closed", 'displayText'=> "Closed (hidden)")
		);

		return $this-> chart_config_Columns($params, "stackedcolumn");
	}

	###############################################################################################
	protected function stats_records_per_provision($org_id, $rt_id, $params)
	{
		if(!is_array($params)) {
			$params = array(
				'period'=> "month",
				'subset'=> 0
			);
		}
		$grouping = '';
		$whereCond = '';
	 	switch($params['period']) {
			// Shows last 12 months, ending with previous month
			case 'months':
				$grouping = "MONTH(FROM_UNIXTIME(`iss`.ctime))";
				$whereCond = "AND `iss`.ctime > ".strtotime(date("Y-m-t")." +1 day -1 year"); // Show all exactly from 12 months before end of this month
				break;
			case 'quarters':
				$grouping  =  "QUARTER(FROM_UNIXTIME(`iss`.ctime))";
				$whereCond =  "AND YEAR(FROM_UNIXTIME(`iss`.ctime)) = YEAR(CURDATE())"; 
				break;
			case 'years':
				$grouping = "YEAR(FROM_UNIXTIME(`iss`.ctime))";
				break;
			default:
				return MVC_Core::$debug-> dump("Invalid parameter: Period: {$params['period']}", __METHOD__, null, "error");
		}

		$q="SELECT SUM(ixf.value) AS `y_axis`, $grouping AS `x_axis`,`iod`.`process`,COUNT(ixf.value) AS `y_count`
		 FROM `issues` AS iss
		 LEFT JOIN `issue_fields` AS ixf ON `iss`.id = `ixf`.issue_id AND  `ixf`.int_value = '840' AND `ixf`.fk_id = '2834' 
		 LEFT JOIN `issue_orgdata` AS iod ON `iss`.id = `iod`.issue_id  AND `iod`.org_id =$org_id
		 WHERE `iss`.type_id = $rt_id   AND process!='closed'  $whereCond
		 GROUP BY $grouping";
		 


		 // $q="SELECT SUM(ixf.value) AS `y_axis`, $grouping AS `x_axis`,`iod`.`process`
		 // FROM `issues` AS iss
		 // LEFT JOIN `issue_fields` AS ixf ON `iss`.id = `ixf`.issue_id
		 // LEFT JOIN `issue_orgdata` AS iod ON `iss`.id = `iod`.issue_id
		 // WHERE `iss`.type_id = $rt_id AND `iod`.org_id = $org_id AND `ixf`.int_value = '840' AND `ixf`.fk_id = '2834' $whereCond
		 // GROUP BY $grouping";
		 
		// $q="SELECT SUM(ixf.value) AS `y_axis`, YEAR(FROM_UNIXTIME(`iss`.ctime)) AS `x_axis`
		//  FROM `issues` AS iss
		//  LEFT JOIN `issue_fields` AS ixf ON `iss`.id = `ixf`.issue_id
		//  LEFT JOIN `issue_orgdata` AS iod ON `iss`.id = `iod`.issue_id
		//  WHERE `iss`.type_id = '1' AND `iod`.org_id = '5' AND `ixf`.fk_id = '1787'
		//  GROUP BY YEAR(FROM_UNIXTIME(`iss`.ctime))";
		$r = MVC_Core::$db->query($q);
		if(!$r)
			return MVC_Core::$debug-> dump("Statistics query Failed", __METHOD__, null, "error");

		return $this-> compile_Org_Statistics($r, 'process',array('active'=> "active",'closed'=> "closed"));
	}

	protected function chart_records_per_provision($params)
	{
		// Show all available groups which have data in them
		// - it's possible to have tons of groups - Example: Quality/AEO Event)
		// - TODO: There should be a way to select a maximum of N groups out of all available (and only display those)
		$this-> chart_config['series'] = array();
		foreach($this-> user_groups as $group) {
			// Filter - Only show groups which have values
			if(!empty($group['total']))
				$this-> chart_config['series'][] = array(
					'dataField'=> $group['id'],
					'displayText'=> $group['name']
				);
		}
		// Nothing detected, to avoid showing error, create a fake non-existing serie with the name "No records found"
		if(empty($this-> chart_config['series'])) {
			$this-> chart_config['series'][] = array(
				'dataField'=> "NoGrp",
				'displayText'=> "No records were found in any data group"
			);
		}

		return $this-> chart_config_Columns($params, "stackedcolumn");

		// Known, fixed series for this one
		// $this-> chart_config['series'] = array(
		// 	array('dataField'=> "Active", 'displayText'=> "Active"),
		// 	array('dataField'=> "Closed", 'displayText'=> "Closed (hidden)")
		// );

		// return $this-> chart_config_Columns($params, "stackedcolumn");
	}
	// "records_per_group" - Valid for data types where the available groups are limited, like "Categories" ( aka, not airlines, customers, etc. ) //

	protected $user_groups = array();

	/**
	 * Calculates records per group -
	 *
	 * @param int $org_id
	 * @param int $rt_id
	 * @param array $params
	 * @return boolean|array
	 */
	protected function stats_records_per_group($org_id, $rt_id, $params)
	{
		if(!is_array($params)) {
			// Default parameters - Show record statistics per month
			$params = array(
				'period'=> "month",
				'subset'=> 0
			);
		}

		// Start building the chart and data query
		// This function uses the organization specific data
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> table('iss'), array("group_id", 'y_axis'=> "COUNT(*)")) // We're counting records, no need for other fields
		-> where("`iss`.type_id = ?", $rt_id);

                if(isset($params['chart_page']) && $params['chart_page']=='quality_event'){
                   $q-> table('issue_fields', array('event_time'=> "int_value"), "left", '`iss`.id = `issue_fields`.issue_id AND `issue_fields`.fk_id = "1228"') ;
                switch($params['period']) {
			// Shows last 12 months, ending with previous month
			case 'months':
				$grouping = "MONTH(FROM_UNIXTIME(`issue_fields`.int_value))";
				$q-> where("`issue_fields`.int_value > ?", strtotime(date("Y-m-t")." +1 day -1 year")); // Show all exactly from 12 months before end of this month
				break;

				// Shows quarters for current year
			case 'quarters':
				$grouping = "QUARTER(FROM_UNIXTIME(`issue_fields`.int_value))";
				$q-> where("YEAR(FROM_UNIXTIME(`issue_fields`.int_value)) = YEAR(CURDATE())"); // Show data from this year
				break;

				// Show five years ; TODO: It should be more configurable
			case 'years':
				$grouping = "YEAR(FROM_UNIXTIME(`issue_fields`.int_value))";
				break;

			default:
				return MVC_Core::$debug-> dump("Invalid parameter: Period: {$params['period']}", __METHOD__, null, "error");
		}
               } else {
		$grouping = $this-> mqy_grouping($q, $params);
               }

		// Join IOD for organization and process filter
		$q
		-> table(MVC_Core::$db-> table('iod'), array("x_axis" => $grouping), "left", "`iod`.issue_id = `iss`.id") // Not even sure if field name is needed?
		-> where("`iod`.org_id = ?", $org_id)
		-> where("`iod`.process != 'hidden'")
		-> group($grouping. ", `iss`.group_id");

		$this-> ac_subset($q, $params['subset']);
//		 echo " query :  ".$q;
		$r = MVC_Core::$db->query($q);
		if(!$r)
			return MVC_Core::$debug-> dump("Statistics query Failed", __METHOD__, null, "error");

		return $this-> compile_Org_Statistics($r, 'group_id');
	}

	/**
	 * Chart shows all available data groups (for the user)
	 * FIXME: user_groups is in the derived classes, only when possible to chart by groups
	 * @param array $params
	 * @return array
	 */
	protected function chart_records_per_group($params)
	{
		// Show all available groups which have data in them
		// - it's possible to have tons of groups - Example: Quality/AEO Event)
		// - TODO: There should be a way to select a maximum of N groups out of all available (and only display those)
		$this-> chart_config['series'] = array();
		foreach($this-> user_groups as $group) {
			// Filter - Only show groups which have values
			if(!empty($group['total']))
				$this-> chart_config['series'][] = array(
					'dataField'=> $group['id'],
					'displayText'=> $group['name']
				);
		}
		// Nothing detected, to avoid showing error, create a fake non-existing serie with the name "No records found"
		if(empty($this-> chart_config['series'])) {
			$this-> chart_config['series'][] = array(
				'dataField'=> "NoGrp",
				'displayText'=> "No records were found in any data group"
			);
		}

		return $this-> chart_config_Columns($params, "stackedcolumn");
	}


	/**
	 * Export Organization record statistics (calls the plugin-version which derives from this data model)
	 */
	public function export_org_Statistics($org_id, $rt_id, $statistics_method, $params = null, $visible = true)
	{

		// Parameters processing (in all cases)
		if(empty($params))
			$params = array();

		$cache_params = array(
			'i1'=> $rt_id
		);
		if(isset($params['period'])) {
			$params['period'] = strtolower($params['period']);
			$cache_params['s3'] = $params['period'];
		}
		if(isset($params['subset']))
			$cache_params['i2'] = $params['subset'];
		
		$this-> chart_config = array();
		 if(!call_user_func(array($this, "ccfg_".$statistics_method), $rt_id, $params))
			return false;		


		if(!$visible)
			return null; // Just return nothing when non-visible; Config however is set

		
		$data = call_user_func(array($this, "export_".$statistics_method), $org_id, $rt_id, $params);
	
		return $data;
		
	}

   // Export records
	protected function export_records_per_provision($org_id, $rt_id, $params)
	{
				
		if(!is_array($params)) {
			$params = array(
				'period'=> "month",
				'subset'=> 0
			);
		}
		$grouping = '';
		$whereCond = '';
	 	switch($params['period']) {
			// Shows last 12 months, ending with previous month
			case 'months':
				$grouping = "MONTH(FROM_UNIXTIME(`iss`.ctime))";
				$whereCond = "AND `iss`.ctime > ".strtotime(date("Y-m-t")." +1 day -1 year"); // Show all exactly from 12 months before end of this month
				break;
			case 'quarters':
				$grouping  =  "QUARTER(FROM_UNIXTIME(`iss`.ctime))";
				$whereCond =  "AND YEAR(FROM_UNIXTIME(`iss`.ctime)) = YEAR(CURDATE())"; 
				break;
			case 'years':
				$grouping = "YEAR(FROM_UNIXTIME(`iss`.ctime))";
				break;
			default:
				return MVC_Core::$debug-> dump("Invalid parameter: Period: {$params['period']}", __METHOD__, null, "error");
		}
         /*
		$q="SELECT SUM(ixf.value) AS `y_axis`, $grouping AS `x_axis`,`iod`.`process`,COUNT(ixf.value) AS `y_count`
		 FROM `issues` AS iss
		 LEFT JOIN `issue_fields` AS ixf ON `iss`.id = `ixf`.issue_id
		 LEFT JOIN `issue_orgdata` AS iod ON `iss`.id = `iod`.issue_id
		 WHERE `iss`.type_id = $rt_id AND  `ixf`.int_value = '840' AND `ixf`.fk_id = '2834' $whereCond
		 GROUP BY $grouping";
		 */
                
//                IWI Aircargoclaim chart page update 12012026 start
                $join = '';
                if (isset($params['page_for']) && $params['page_for'] == 'claim_chart') {
                    $join .= ' INNER JOIN issue_fields iss_awb ON(iss_awb.issue_id=iss.id AND iss_awb.fk_id=3) ';
                    if ($org_id == '208') {
                        if (isset($_REQUEST['airline']) && $_REQUEST['airline'] == '1') {
                            $whereCond .= " AND iss_awb.value LIKE '369-%' ";
                        } else if (isset($_REQUEST['airline']) && $_REQUEST['airline'] == '2') {
                            $whereCond .= " AND iss_awb.value LIKE '403-%'  ";
                        }
                    }
                    if ($org_id == '9') {
                        $whereCond .= " AND iss_awb.value LIKE '065-%'  ";
                    }
                }
                
//                IWI Aircargoclaim chart page update 12012026 end
		 
	   $q="SELECT iss.track,ixf.value,  $grouping AS `h_names`,`iod`.`process` ,cache_primary , FROM_UNIXTIME(time_start, '%d-%m-%Y' ) as formal_claim ,
		FROM_UNIXTIME(linktime, '%d-%m-%Y' ) as Claim_receive_date	, `ixfs`.value as clm_amt_usd , `ixfs_nat_goods`.value as nat_goods, iod.process as stts,
		`ixfs_ship`.value as shipper 
		 FROM `issues` AS iss
		 LEFT JOIN `issue_fields` AS ixf ON `iss`.id = `ixf`.issue_id AND `ixf`.int_value = '840' 

		 LEFT JOIN `issue_fields` AS ixfs ON `iss`.id = `ixfs`.issue_id AND `ixfs`.fk_id = '1729' 
		
		 LEFT JOIN `issue_fields` AS ixfs_ship ON `iss`.id = `ixfs_ship`.issue_id AND `ixfs_ship`.fk_id = '587'
		
		 LEFT JOIN `issue_fields` AS ixfs_nat_goods ON `iss`.id = `ixfs_nat_goods`.issue_id AND `ixfs_nat_goods`.fk_id = '10'
		 
		
		 
		 LEFT JOIN `issue_orgdata` AS iod ON `iss`.id = `iod`.issue_id  AND `iod`.org_id = $org_id
		 $join
		 WHERE `iss`.type_id = $rt_id 
		 
		   AND `ixf`.fk_id = '2834'  $whereCond AND ixf.value!='' AND  iod.process!='closed'"; 
		   
		   
		   // 
		  
		$r = MVC_Core::$db->query($q);
		if(!$r)
			return MVC_Core::$debug-> dump("Statistics query Failed", __METHOD__, null, "error");

		$data = array();
		

		$h_var = $this-> chart_config['h_var'];
		if(isset($this-> chart_config['h_names'])) {
			// Returned values are indexes in h_names ( months, quarters, perhaps other indexable periods, like day of month )
			foreach($this-> chart_config['h_names'] as $key => $hname) {
				$data[$h_var][$key]['label'] = $hname;
			};
			
			// Begin distributing results into $data
			foreach($r as $row) {
				
				$data[$h_var][(int)$row['h_names']-1]['records'][] = $row;
				
				if(!isset($data[$h_var][(int)$row['h_names']-1])){
					 $data[$h_var][(int)$row['h_names']-1]['total'] = 0;
				}
				
				$data[$h_var][(int)$row['h_names']-1]['total'] = $data[$h_var][(int)$row['h_names']-1]['total'] + $row['value'];
				
			}
		}else {
			// Without h_names, returned values are actual array indexes (Named values, as returned from the database)
			foreach($r as $row) {
				
				$data[$h_var][$row['h_names']]['label']    = $row['h_names'];
				$data[$h_var][$row['h_names']]['records'][]= $row;
				
				$data[$h_var][$row['h_names']]['total'] = $data[$h_var][$row['h_names']]['total'] + $row['value'];
				 
			}
			
		}
		  
		return $data; 
		
	}
	
	
	protected function export_records_per_provision_print($org_id, $rt_id, $params)
	{
				
		if(!is_array($params)) {
			$params = array(
				'period'=> "month",
				'subset'=> 0
			);
		}
		$grouping = '';
		$whereCond = '';
	 	switch($params['period']) {
			// Shows last 12 months, ending with previous month
			case 'months':
				$grouping = "MONTH(FROM_UNIXTIME(`iss`.ctime))";
				$whereCond = "AND `iss`.ctime > ".strtotime(date("Y-m-t")." +1 day -1 year"); // Show all exactly from 12 months before end of this month
				break;
			case 'quarters':
				$grouping  =  "QUARTER(FROM_UNIXTIME(`iss`.ctime))";
				$whereCond =  "AND YEAR(FROM_UNIXTIME(`iss`.ctime)) = YEAR(CURDATE())"; 
				break;
			case 'years':
				$grouping = "YEAR(FROM_UNIXTIME(`iss`.ctime))";
				break;
			default:
				return MVC_Core::$debug-> dump("Invalid parameter: Period: {$params['period']}", __METHOD__, null, "error");
		}
         /*
		$q="SELECT SUM(ixf.value) AS `y_axis`, $grouping AS `x_axis`,`iod`.`process`,COUNT(ixf.value) AS `y_count`
		 FROM `issues` AS iss
		 LEFT JOIN `issue_fields` AS ixf ON `iss`.id = `ixf`.issue_id
		 LEFT JOIN `issue_orgdata` AS iod ON `iss`.id = `iod`.issue_id
		 WHERE `iss`.type_id = $rt_id AND  `ixf`.int_value = '840' AND `ixf`.fk_id = '2834' $whereCond
		 GROUP BY $grouping";
		 */
		 
	   $q="SELECT iss.track,ixf.value,  $grouping AS `h_names`,`iod`.`process` ,cache_primary , FROM_UNIXTIME(time_start, '%d-%m-%Y' ) as formal_claim ,
		FROM_UNIXTIME(linktime, '%d-%m-%Y' ) as Claim_receive_date	, `ixfs`.value as clm_amt_usd , `ixfs_nat_goods`.value as nat_goods, 
		`ixfs_ship`.value as shipper 
		 FROM `issues` AS iss
		 LEFT JOIN `issue_fields` AS ixf ON `iss`.id = `ixf`.issue_id AND `ixf`.int_value = '840' 

		 LEFT JOIN `issue_fields` AS ixfs ON `iss`.id = `ixfs`.issue_id AND `ixfs`.fk_id = '1729' 
		
		 LEFT JOIN `issue_fields` AS ixfs_ship ON `iss`.id = `ixfs_ship`.issue_id AND `ixfs_ship`.fk_id = '587'
		
		 LEFT JOIN `issue_fields` AS ixfs_nat_goods ON `iss`.id = `ixfs_nat_goods`.issue_id AND `ixfs_nat_goods`.fk_id = '10'
		 
		
		 
		 LEFT JOIN `issue_orgdata` AS iod ON `iss`.id = `iod`.issue_id     AND `iod`.org_id = $org_id
		 
		 WHERE `iss`.type_id = $rt_id 
		 
		   AND `ixf`.fk_id = '2834'  $whereCond AND ixf.value!='' AND  iod.process!='closed'"; 
		  
		   
		$r = MVC_Core::$db->query($q);
		if(!$r)
			return MVC_Core::$debug-> dump("Statistics query Failed", __METHOD__, null, "error");

		$data = array();
		

		$h_var = $this-> chart_config['h_var'];
		if(isset($this-> chart_config['h_names'])) {
			// Returned values are indexes in h_names ( months, quarters, perhaps other indexable periods, like day of month )
			foreach($this-> chart_config['h_names'] as $key => $hname) {
				$data[$h_var][$key]['label'] = $hname;
			};
			
			// Begin distributing results into $data
			foreach($r as $row) {
				
				$data[$h_var][(int)$row['h_names']-1]['records'][] = $row;
				
				if(!isset($data[$h_var][(int)$row['h_names']-1])){
					 $data[$h_var][(int)$row['h_names']-1]['total'] = 0;
				}
				
				$data[$h_var][(int)$row['h_names']-1]['total'] = $data[$h_var][(int)$row['h_names']-1]['total'] + $row['value'];
				
			}
		}else {
			// Without h_names, returned values are actual array indexes (Named values, as returned from the database)
			foreach($r as $row) {
				
				$data[$h_var][$row['h_names']]['label']    = $row['h_names'];
				$data[$h_var][$row['h_names']]['records'][]= $row;
				
				$data[$h_var][$row['h_names']]['total'] = $data[$h_var][$row['h_names']]['total'] + $row['value'];
				 
			}
			
		}
		  
		return $data; 
		
	}


	
}