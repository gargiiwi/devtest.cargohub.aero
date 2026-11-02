<?php
/**
 * System Administrator Dashboard (Displays global activity in the system)
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage Controllers
 * @version 2019.01.24
 * For accessing the data from any external app or for creating your own external API use mysql-services
 */
class App_Ctrl_Admin_Dashboard extends App_Ctrl_Base
{
	public static $_module_name = "Administration Center";

	/**
	 * Statistics model reference
	 *
	 * @var App_Model_Statistics
	 * For accessing the data from any external app or for creating your own external API use mysql-services
	 * Just type in shell command prompt mysql-services, mysql-services -api
	 */
	public $stx;

	/**
	 * Issue model reference - Added here because most of the actions are related to issues
	 * @var App_Model_Issue
	 */
	public $iss;

	/**
	 * Makes initializations useful for any action
	 */
	public function __construct()
	{
		parent::__construct(true);

		// Admin-only module, redirect to home if no access
		if(!$this-> user-> is_Unrestricted()) {
			$this-> evan-> add("Move along now, nothing to see here.", 'lock');
			return $this-> redirect($this-> CFG['home']);
		}

		$this-> stx = $this-> factory_Model("Statistics", "stx");
		$this-> iss = $this-> factory_Model("Issue", "iss");
	}

	/**
	 * Index action for the Global Settings page
	 *
	 * @see MVC_Ctrl::action_Index()
	 */
	public function action_Index()
	{
		$this-> CFG['components'] = array('modal');
		$this-> CFG['modules'][] = "admin/_dashboard";
		$this-> CFG['jqwidgets'][] = array("docking", "scrollbar", "listbox", "dropdownlist", "datatable");
		$this-> CFG['xjs'] = array(
			'jqform'=> true,
			'dateformat' => true
		);

		// prepare output
		$this-> prepare_Header(self::$_module_name);

		$this-> global_statistics = $this-> stx-> read_Multiple(array("org_count", "user_count", "contact_count", "group_count", "issue_count", "issue_monthly_count"));

		$this-> module = array(
			'icon'=> "superman.png",
			'icon_alt'=> "(super)",
			'title'=> self::$_module_name,
			'title_para'=> MVC_Core::$ss-> user['name'],
			'description'=> "Monitor and Configure <b>CargoConnect</b>"
		);

		// Load docklet config (if it exists)
		$this-> CFG['admin_dashboard'] = array(
			'docklet_config'=> $this-> user-> profile_Read(MVC_Core::$ss-> user['id'], "docklet_admin_dashboard", true)	// true = Frontend requires this as a string (will be internally decoded with JSON.parse
		);

		// which body template to use, and run the view
		$this-> site_section = "admin". DS. "dashboard.html";
		$this-> view("private.html");
	}

	/**
	 * Returns the list of session (including joined tables - user
	 * @param MVC_Request $in
	 */
	public function action_Get_Sessions(MVC_Request $in)
	{
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> table('ass'), array('user_id', 'session_id', 'dt', 'logon', 'count_liftoffs', 'request_uri', 'ipaddr'))
		-> table(MVC_Core::$db-> table('usr'), QueryFactory::autoalias_Add_Prefix("user,status,name,email", '_usr_'), 'left', "`usr`.id = `ass`.user_id")
		-> table(MVC_Core::$db-> table('org'), QueryFactory::autoalias_Add_Prefix("name,type_id", '_org_'), 'left', "`org`.id = `usr`.org_id")
		-> order("`ass`.dt DESC")
		-> where("`usr`.status != 'admin'");

		$r = MVC_Core::$db-> query($q);
		if(!$r)
			return header("HTTP/1.0 500 Unable to read list of active sessions.");
		if(!count($r))
			return header("HTTP/1.0 204 Nobody is online.");

		$r-> split_by_prefixes = array(
			'user'=> "_usr_",
			'org'=> "_org_"
		);

		$rows = array();
		foreach($r as $row) {
			// Post-process
			$row['user_id'] = (int)$row['user_id'];
			$row['count_liftoffs'] = (int)$row['count_liftoffs'];
			$row['ipaddr'] = long2ip($row['ipaddr']);

			$rows[] = $row;
		}
		$this-> send_JSON($rows);
	}

	/**
	 * Sends a mass notification to all users of the system !
	 * @param MVC_Request $post
	 */
	public function action_Send_SysNtf(MVC_Request $post)
	{
		if(!isset($post-> subtype) || !isset($post-> title))
			return $this-> send_Fail_JSON("The request is missing data");

		$count_users = $this-> evan-> create_System($post-> subtype, $post-> importance, $post-> title, $post-> description);

		$this-> send_JSON(array('success'=> true, 'response'=> "The mass message has been sent to {$count_users} users !"));
	}

	/**
	 * Performs Database Maintenance - Recalculates organization statistics
	 */
	public function action_DBm_Org_Stats()
	{
// 		MVC_Core::$db-> query("DROP TABLE org_count_update");

		// Neat, eh ? ;)
		$q=QueryFactory::create('table')
		-> name("org_count_update")
		-> flags("MEMORY", "temporary")
		-> column("id")
		-> column("count_users", array("int", 10, "u"), array('default'=> 0))
		-> column("count_issues", array("int", 10, "u"), array('default'=> 0))
		-> column("count_msgs", array("int", 10, "u"), array('default'=> 0))
		-> column("count_files", array("int", 10, "u"), array('default'=> 0))
		-> column("count_ctx", array("int", 10, "u"), array('default'=> 0))
		-> index("primary", "id");

		MVC_Core::$db-> query($q);

		// Insert Select into the temp table all existing orgainzations and current counters
  		$q=QueryFactory::insert()
		-> table("org_count_update")
		-> values(
			QueryFactory::select()
			-> table(MVC_Core::$db-> tab['org'], "id,count_users,count_issues,count_msgs,count_files,count_ctx")
			-> order("id")
		);

		MVC_Core::$db-> query($q);

		// Calculate number of users per organization by looking into users table and checking org_id, udpate the temporary table with the correct values
		MVC_Core::$db-> query("UPDATE `org_count_update` AS `tmp` LEFT JOIN (
			SELECT COUNT(*) AS `count_users`, `org_id` FROM `users` GROUP BY `org_id` ORDER BY `org_id`
		) AS `usrc` ON `tmp`.`id`=`usrc`.`org_id` SET `tmp`.`count_users`=IF(`usrc`.`count_users` IS NULL, 0, `usrc`.`count_users`)");

		// Calculate number of issues registered for each organization, and update the temporary table
		MVC_Core::$db-> query("UPDATE `org_count_update` AS `tmp` LEFT JOIN (
			SELECT COUNT(*) AS `count_issues`, `org_id` FROM `issues` GROUP BY `org_id` ORDER BY `org_id`
		) AS `issc` ON `tmp`.`id`=`issc`.`org_id` SET `tmp`.`count_issues`=IF(`issc`.`count_issues` IS NULL, 0, `issc`.`count_issues`)");

		// Count messages recorded to the organizations
		MVC_Core::$db-> query("UPDATE `org_count_update` AS `tmp` LEFT JOIN (
			SELECT COUNT(*) AS `count_msgs`, `org_id` FROM `comm_messages` GROUP BY `org_id` ORDER BY `org_id`
		) AS `msgc` ON `tmp`.`id`=`msgc`.`org_id` SET `tmp`.`count_msgs`=IF(`msgc`.`count_msgs` IS NULL, 0, `msgc`.`count_msgs`)");

		// Count files uploaded
		MVC_Core::$db-> query("UPDATE `org_count_update` AS `tmp` LEFT JOIN (
			SELECT COUNT(*) AS `count_files`, `org_id` FROM `files` GROUP BY `org_id` ORDER BY `org_id`
		) AS `filc` ON `tmp`.`id`=`filc`.`org_id` SET `tmp`.`count_files`=IF(`filc`.`count_files` IS NULL, 0, `filc`.`count_files`)");

		// Finally, count contacts
		MVC_Core::$db-> query("UPDATE `org_count_update` AS `tmp` LEFT JOIN (
			SELECT COUNT(*) AS `count_ctx`, `org_id` FROM `comm_contacts` GROUP BY `org_id` ORDER BY `org_id`
		) AS `ctxc` ON `tmp`.`id`=`ctxc`.`org_id` SET `tmp`.`count_ctx`=IF(`ctxc`.`count_ctx` IS NULL, 0, `ctxc`.`count_ctx`)");

		// Update-Join master organizations table with all collected data.
		$q=QueryFactory::update()
		-> table(array(MVC_Core::$db-> tab['org'], 'org'))
		-> table(array("org_count_update", 'updc'), null, "LEFT", "(id)")
		-> values(array(
			'count_users'=> "`updc`.`count_users`",
			'count_issues'=> "`updc`.`count_issues`",
			'count_msgs'=> "`updc`.`count_msgs`",
			'count_files'=> "`updc`.`count_files`",
			'count_ctx'=> "`updc`.`count_ctx`"
		), 'org', true);

		MVC_Core::$db-> query($q);

		echo "Organizations statistics updated.";
	}

	/**
	 * Performs Database Maintenance - Recalculates user statistics
	 */
	public function action_DBm_User_Stats()
	{
		// Phase One - Assigned issues statistics:
		// Scans Issue_orgdata for "assigned_to", and computes total user assigned statistics
		// Part 1: Conversations
		MVC_Core::$db-> query("UPDATE `users` LEFT JOIN
		(SELECT COUNT(*) AS `count_issues`, `assigned_to` FROM `issue_orgdata` WHERE `assigned_to` IS NOT NULL GROUP BY `assigned_to`) AS `cassgn`
		ON `users`.`id` = `cassgn`.`assigned_to` SET `users`.count_issues = IF(`cassgn`.`count_issues` IS NULL, 0, `cassgn`.`count_issues`)");

		echo "User statistics updated (work in progress).";

// 		echo "Not yet implemented.";
	}

	/**
	 * Performs Database Maintenance - Verifies sender/recipient for all messages, updates as necessary (replacing missing contacts with 0)
	 */
	public function action_DBm_Mop_Messages()
	{
		echo "Not yet implemented.";
	}

	/**
	 * Performs Database Maintenance - Updates Comm Labels to have the correct number of elements (count_el)
	 */
	public function action_DBm_Lab_Stats()
	{
		// Part 1: Conversations
		MVC_Core::$db-> query("UPDATE `comm_labels` LEFT JOIN
		(SELECT COUNT(*) AS `count_el`, `lab_id` FROM `comm_cnv2lab` WHERE `status` != 'deleted' GROUP BY `lab_id` ORDER BY `lab_id`) AS `ccnv`
		ON `comm_labels`.`id` = `ccnv`.`lab_id` SET `comm_labels`.`count_el` = IF(`ccnv`.`count_el` IS NULL, 0, `ccnv`.`count_el`) WHERE `comm_labels`.`type` != 'contacts'");

		// Part 2: Personal User Contacts
		MVC_Core::$db-> query("UPDATE `comm_labels` LEFT JOIN
		(SELECT COUNT(*) AS `count_el`, `con_id` FROM `comm_contacts` WHERE `type` = 'personal' GROUP BY `con_id` ORDER BY `con_id`) AS `ccon`
		ON `comm_labels`.`id` = `ccon`.`con_id` SET `comm_labels`.`count_el` = IF(`ccon`.`count_el` IS NULL, 0, `ccon`.`count_el`) WHERE `comm_labels`.`type` = 'contacts'");

		echo "Label statistics updated.";
	}

	/**
	 * Performs Database Maintenance - Sets the "dep_ids" field for all issues, to all departments in all organizations with access to the issue.
	 * -- The access can then be further restricted on a per-issue basis
	 */
	public function action_DBm_Dep_Access()
	{
		// Reads all issues, makes the list of organizations (from subm_org_id, org_id and sahred_org_ids), then reads departments in all those organizations
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> tab['iss'], 'id,subm_org_id,org_id,shared_org_ids,dep_ids')
		-> where("`dep_ids` IS NULL")
		-> limit(1000);

		$r = MVC_Core::$db-> query($q);
		$updated = 0;
		foreach($r as $iss) {
			// Needs to be fixed. Assamble the org ids
			$org_ids = array();
			if(!empty($iss['subm_org_id']))
				$org_ids[] = (int)$iss['subm_org_id'];
			if(!empty($iss['org_id']))
				$org_ids[] = (int)$iss['org_id'];
			if(!empty($iss['shared_org_ids'])) {
				$shared_org_ids = explode(',', $iss['shared_org_ids']);
				$org_ids = array_merge($org_ids, $shared_org_ids);
			}
			$org_ids = array_values(array_unique($org_ids));
			if(!empty($org_ids)) {
				// Read all departments in the specified list of organizations
				$q=QueryFactory::select()
				-> table(MVC_Core::$db-> tab['dep'], 'id')
				-> where('`org_id` IN (?)', $org_ids);

				$dr = MVC_Core::$db-> query($q);
				$dep_ids = array();
				foreach($dr as $dep)
					$dep_ids[] = $dep['id'];

				// Update the issue
				$q=QueryFactory::update()
				-> table(MVC_Core::$db-> tab['iss'])
				-> values(array(
					'dep_ids'=> implode(',', $dep_ids)
				))
				-> where("`id` = ?", $iss['id']);

				MVC_Core::$db-> query($q);
				$updated++;
			} else
				echo "Record ID {$iss['id']} does not have any organization ID set !<br />";
		}
		if($updated)
			echo "{$updated} issues were updated. There might be more... repeat the operation.";
		else
			echo "No issues updated. All issues are probably fixed or there were errors.";
	}

	/**
	 * Helper used to build the cached data for issues
	 *
	 * @param array $cache_settings
	 * @param array $xfields
	 */
	private function grab_Data4Cache($cache_settings, &$xfields)
	{
		if(strpos($cache_settings[1], '.')!==false) {
			// The field is a plugin that creates multiple entries when serializing a field data
			$key = explode('.', $cache_settings[1]); $c = 0;
			if(!isset($xfields[$key[$c]]))
				return null;
			$entry = $xfields[$key[$c]];
			while(is_array($entry) && isset($key[$c+1])) {
				if(!isset($entry[$key[$c]]))
					return null;
				$entry = $entry[$key[++$c]];
			}
			return substr($entry, 0, 50);
		} else {
			// Direct string copy
			return isset($xfields[$cache_settings[1]])? substr($xfields[$cache_settings[1]], 0, 100): null;
		}
		// Max 100 characters, due to the varchar() limit
	}

	/**
	 * Performs Database Maintenance - Rebuilds the "xf_cache" fields for all records of a certain type
	 * -- This is used after the field choices for the cache have been modified, to resync the data for all records
	 * -- Self loading loop that goes 100 records at a time
	 * -- Also makes sure records have an "access_key" set
	 */
	public function action_DBm_Rebuild_CFields(MVC_Request $rq)
	{
		if(!$rq-> rt_id) {
			// Get list of record types and their IDs
			$rt_list = $this-> iss-> ist_Get_List();
?>
<h2>Cache-Fields Rebuilding</h2>
<form action="" method="post">
	<select name='rt_id'><?php
		foreach($rt_list as $rt)
			echo "<option value=\"{$rt['id']}\">{$rt['name']}</option>\n";
	?></select><br />
	Batch size: <input name='batch' value="100" /><br />
	Start from: <input name='startfrom' value="0" />
	<br />
	<input type="submit" value='HIT IT !' />
</form>
<p>Please note, after hitting it, the function will update a group of records, take a small pause and move on to the next (Start from automatically increases until there are no more records to update)<br />
The process can be interrupted at any time by pressing the STOP button which will cancel the advance.</p>
<?php
			return;
		} else {
			$RT_id = (int)($rq-> rt_id?: 1); // Defaults to 1 - Air Cargo Claim
			$batch = (int)($rq-> batch?: 100); // Defaults to 100 records one loop
			$startfrom = (int)($rq-> startfrom?: 0); // Defaults to 0, self incrementing via page reload
		}

		$issues = $this-> iss-> get_List($RT_id, array('issue', 'xfields'), function(QueryFactory &$q) {
			// No specific filters
		}, null, '`iss`.id DESC', $batch, $startfrom, false, true);

		if(!count($issues)) {
			echo "Operation complete.\nNo more records remaining to be processed";
			return;
		}

		$istdef = $this-> iss-> ist_Read($RT_id);

		$count = 0; $trackids = array();
		foreach($issues as $record) {
			$trackids[] = $record['track'];

 			$update = array();
 			if(empty($record['access_key']))
 				$update['access_key'] = sha1(microtime(1) . uniqid());

			// Build cache + Quickindex
			$CFields = $this-> iss-> prepare_CFields($record['xfields'], false, false, null, true);

			$this-> iss-> update($record['id'], $CFields);
			$count++;

			usleep(mt_rand(5, 30));
		}

		echo "<img src='/img/extra/loader.gif' style='float: left; margin-right: 10px;' /><h2>Cache-Fields Rebuilding</h2>
		<h3>... currently at database row number: {$startfrom}</h3>
		<p>Cache-Fields rebuilt for {$count} records of type <b>{$istdef['name']}</b>.<br />
		Reloading in <span id='countdown_clock' style='font-weight: bold;'>5</span> seconds for next batch<br />
		<button onClick='clearTimeout(stopme); clearInterval(countdown);'>EMERGENCY BREAK</button></p>";

		echo "<h3>Tracking numbers of updated records</h3>\n";
		foreach($trackids as $track)
			echo "<a href='/record/process:{$track}#panels' target='_blank'>{$track}</a><br />";

		$continuefrom = $startfrom + $batch;
		echo "<script>
			var stopme = setTimeout(function() {
				document.location = '?action=dbm_Rebuild_CFields&rt_id={$RT_id}&batch={$batch}&startfrom={$continuefrom}';
			}, 5200);

			var clock = 5, countdown = setInterval(function() {
				document.getElementById('countdown_clock').innerHTML = --clock;
			}, 1000);
		</script>";
	}

	/**
	 * @deprecated This was used in a previous update, no longer necessary - TODO: Remove this function
	 * Checks and builds organization specific data
	 * @param MVC_Request $in
	 */
	public function action_DBm_OrgSpec(MVC_Request $in)
	{
		$IST = (int)($in-> ist?: 1);
		$count = (int)($in-> count?: 500);
		$startfrom = $in-> startfrom;

		$issues = $this-> iss-> get_List($IST, array('issue'), null, null, '`iss`.id ASC', $count, $startfrom);

		if(!count($issues)) {
			echo "No more issues remaining to be processed";
			return;
		}

		$count = 0;
		foreach($issues as $issue) {
			if(empty($issue['master_org_id'])) {
				// This issue does not have any org associated !
				echo "<div>Issue without organization detected: {$issue['track']}</div>\n";
				continue;
			}

			// Build the list of organization ID's, from "master_org_id", "shared_org_ids" and the "org_id" value from IOD
			$org_ids = array((int)$issue['master_org_id']);

			if(!is_null($issue['shared_org_ids'])) {
				$shared_ids = explode(',', $issue['shared_org_ids']);
				$org_ids = array_merge($org_ids, $shared_ids);
			}

			$q=QueryFactory::select()
			-> table(MVC_Core::$db-> tab['iod'], 'org_id,is_enabled')
			-> where('issue_id = ?', $issue['id']);

			$iod = MVC_Core::$db-> query($q);
			$iod_ids = array();
			if(count($iod)) {
				foreach($iod as $blah) {
					$org_ids[] = (int)$blah['org_id'];
					$iod_ids[] = (int)$blah['org_id'];
				}
			}

			// Finally, remove any duplicates from the array
			$org_ids = array_unique($org_ids);

// 'dep_ids', 'count_contacts', 'count_files', 'filesize_total', 'count_convos', 'count_notes', 'count_tasks', 'count_propfields', 'commtime', 'priority_key', 'status_key', 'xfer_to', 'xfer_time', 'xfer_ip', 'xfer_confirmip', 'assigned_to'

			// Check if IP and Delivery Confirm IP are actual numbers
			if(strpos($issue['transfer_ip'], '.')!==false)
				$issue['transfer_ip'] = sprintf('%u', ip2long($issue['transfer_ip']));
			if(strpos($issue['delivery_confirm_ip'], '.')!==false)
				$issue['delivery_confirm_ip'] = sprintf('%u', ip2long($issue['delivery_confirm_ip']));

			// For each of the $org_ids, check if there is a record in IOD, update it with the correct dep_ids and copy some of the issue data into it.
			foreach($org_ids as $org_id) {
				if(in_array($org_id, $iod_ids)) {
					$upd_data = array(
						'dep_ids'=> true,
						'commtime'=> $issue['commtime'],
						'priority_key'=> $issue['priority_key'],
						'status_key'=> $issue['status_key'],
						'xfer_time'=> $issue['ttime'],
						'xfer_ip'=> $issue['transfer_ip'],
						'xfer_confirmip'=> $issue['delivery_confirm_ip'],
						'assigned_to'=> $issue['assigned_to']
					);

					// Yes, it exists, just update values
					$this-> iss-> org_UpdateData((int)$issue['id'], $org_id, $upd_data);
				} else {
					$insert_data = array(
						'commtime'=> $issue['commtime'],
						'priority_key'=> $issue['priority_key'],
						'status_key'=> $issue['status_key'],
						'xfer_time'=> $issue['ttime'],
						'xfer_ip'=> $issue['transfer_ip'],
						'xfer_confirmip'=> $issue['delivery_confirm_ip'],
						'assigned_to'=> $issue['assigned_to']
					);

					// No, create the entry
					$this-> iss-> org_Add((int)$issue['id'], $org_id, null, $insert_data, false); // Do not update issue here (Will be updated when all orgs are connected)
				}
			}
			// Repair the "shared_org_ids" of the issue
			$p=array_search($issue['master_org_id'], $org_ids);
			unset($org_ids[$p]);

			if(empty($org_ids) && empty($issue['shared_org_ids']))
				continue;

			if(implode(',', $org_ids) != $issue['shared_org_ids'])
				$this-> iss-> update($issue['id'], array(
					'shared_org_ids'=> $org_ids
				));
		}
	}

	/**
	 * Reads tasks which have "assign_name" empty and their associated "assign_to" element and updates "assign_name" for the search grid.
	 * -- Further readings will use the assign_name directly
	 */
	public function action_DBm_Fix_Tasks(MVC_Request $in)
	{
		$count = (int)($in-> count?: 500);
		$startfrom = $in-> startfrom;

		$m_tasks = new App_Model_Task();

		$tasks = $m_tasks-> get_List(array('task', 'assigned', 'record', 'org'), null, null, null, $count, $startfrom);
		if(!$tasks || count($tasks)==0) {
			echo "No more tasks remaining to be processed";
			return;
		}

		$count = 0;
		foreach($tasks as $task) {
			$task_update = array();
			// Verify if the assignment is correct; If not, reset the assignment
			if(empty($task['assign_to']) && !empty($task_update['assign_name']) || ($task['assign_to'] && empty($task['assign']['id']))) {
				$task_update['assign_mode'] = "user";
				$task_update['assign_name'] = null;
				$task_update['assign_to'] = null;
			} elseif(!empty($task['assign']['name']) && $task['assign_name']!=$task['assign']['name'])
				// The assignment is correct, but "assign_name" is still not updated
				$task_update['assign_name'] = $task['assign']['name'];

			// Fix tasks for which the organization is undefined /missing, reallocate them to organization "1" (Wavetrex Multimedia) - They will be checked later
			// - This is a bit dangerous, disabled for now.
// 			if(empty($task['org']['name']))
// 				$task_update['org_id'] = 1;

// 			// Fix tasks linked to records which do not exist anymore, by making them unlinked
			if(empty($task['rec']['track'])) {
				$task_update['link_type'] = null;
				$task_update['link_id'] = null;
			}

 			if(count($task_update)) {
 				$m_tasks-> update($task['id'], $task_update, 0, null, true);
 				$count++;
 			}
		}

		echo "{$count} tasks updated with reparied/updated data.";
	}

	/**
	 * Sets all "old" communication targets as "New" target method for istkey = cargoclaim, in their respective organizations
	 * @param MVC_Request $in
	 */
	public function action_DBm_CommTargets(MVC_Request $in)
	{
		$m_con = new App_Model_Contact();
		$old_comm_targets = $m_con-> get_List(null, null, "issue_target", array(), null, 0);

		foreach($old_comm_targets as $contact)
			$this-> org-> targetContact_Set($contact['org_id'], "cargoclaim", $contact['id']);

		echo count($old_comm_targets)." old targets are now part of the new link system. DO NOT PRESS THIS BUTTON AGAIN !";
	}

	/**
	 * Processes database messages from the single-recipient to multi-recipients - Only if "to_id" is not null
	 * - Messages which have "to_id" = '0' are deleted (tests), along with their matching "conversation" (cnv_id)
	 * - "to_id" goes to "recipients" in the first position
	 * - any extra recipients which are already stored are maintained
	 * - if recipient distrib is "1" or "1,0,0" the recipients are counted, the one on "to_id" becomes the first "1", and any extras are put in "cc" (second value), resulting in 1,n,0
	 * - "to_id" becomes null
	 *
	 *
	 *
	 * @param MVC_Request $rq
	 */
	public function action_DBm_ProcessMessages(MVC_Request $rq)
	{
		if(!$rq-> batch) {
			echo "Add &batch={batch size} to process that number of messages. The procedure doesn't repeat through messages already converted!";
			return;
		}

		// Information prepared, start processing
		$batch = (int)($rq-> batch);

		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> tab['msg'], array('id', 'org_id', 'from_id', 'to_id', 'email', 'recipients', 'recipient_distrib', 'cnv_id'))
		-> where("`to_id` IS NOT NULL") // Case 1 - Still using "to_id", this has to go
		-> where_or("`to_id` IS NULL AND `email` IS NOT NULL") // Case 2 - In even older times when contacts didn't exist, there was just the "email" put in the address itself. These messages have no recipients and they SHOULD
		-> where_or("`to_id` IS NULL AND `recipients` IS NULL AND `cnv_id` IS NOT NULL") // Case 3 - Old reply.. linked to a conversation, but it's unknown who it was replied to
		-> order('`id` DESC')
		-> limit($batch);

		$r = MVC_Core::$db-> query($q);
		if(!$r || count($r)==0) {
			// DONE !
			echo "Operation complete.\nNo more records remaining to be processed";
			return;
		}

		$counter = 0;
		foreach($r as $msg) {

			// Case 2 - The mentioned e-mail has to be searched in contacts and becomes the "to" recipient
			if(!empty($msg['email']) && empty($msg['to_id'])) {
				// Find the first contact that matches that e-mail for the same organization as the message itself
				$qs=QueryFactory::select()
				-> table(MVC_Core::$db-> tab['ctx'], array('id'))
				-> where("`org_id` = ?", $msg['org_id'])
				-> where("`email` = ?", $msg['email'])
				-> order('`id` ASC')
				-> limit(1);

				$ctx = MVC_Core::$db-> query($qs)-> current();
				if(!empty($ctx)) {
					// Found a contact, set it as $msg['to_id'] and continue processing
					$contact_id = $ctx['id'];
				} else {
					// Contact for this E-mail NOT found; Create it instead
					$contact_id = MVC_Core::$db-> query(
						QueryFactory::insert()
						-> table(MVC_Core::$db-> tab['ctx'])
						-> values(array(
							'org_id'=> $msg['org_id'],
							'dep_id'=> null,
							'user_id'=> null,
							'type'=> "auto_out",
							'ctime'=> date("Y-m-d H:i:s"),
							'email'=> $msg['email'],
							'name'=> "Old_Msg_Autogen"
						))
					);
				}

				// Is this contact the "FROM" contact? If so, nothing to do, remove the e-mail and then NULL everything in recipients/... as this message has not been sent
				if($msg['from_id'] == $contact_id) {
					$msg_update = array(
						'to_id'=> null,
						'email'=> null,
						'recipients'=> null,
						'recipient_distrib'=> '0,0,0' // No recipients at all ! Will have to see what can be done with these strange message records that are stored but apparently not sent to anyone...
					);

					MVC_Core::$db-> query(
						QueryFactory::update()
						-> table(MVC_Core::$db-> tab['msg'])
						-> where("`id` = ?", $msg['id'])
						-> values($msg_update)
					);
					$counter++;
					continue;
				} else
					$msg['to_id'] = $contact_id;
			}

			// Case 3 - Message without a target, but linked to a conversation
			if(empty($msg['to_id']) && empty($msg['recipients']) && !empty($msg['cnv_id'])) {
				// Read the conversation contacts, and first one found which is NOT the contact mentioned in "from_id" becomes the recipient

				$qv=QueryFactory::select()
				-> table(MVC_Core::$db-> tab['cnv'], array('contact_ids'))
				-> where("`id` = ?", $msg['cnv_id'])
				-> limit(1);

				$convo = MVC_Core::$db-> query($qv)-> current();
				$ctx_ids = explode(',', $convo['contact_ids']);
				$found_recipient = false;
				foreach($ctx_ids as $con)
					if($con != $msg['from_id']) {
						// Found a contact in the list which is not the sender !
						$msg['to_id'] = $con;
						$found_recipient = true;
						break;
					}
				if(!$found_recipient) {
					// A recipient was not found at all... unable to determine where this message went. Consider it as sent to self (kinda crappy but no other way to filter it out of the list)
					$msg['to_id'] = $msg['from_id'];
				}
			}

			if($msg['to_id'] === '0') {
				// 'to_id' cannot be 0, as it's not possible to send a message to "nobody" - This is an errored message
				if(!empty($msg['cnv_id'])) {
					// Attempt to read the matching conversation, if it contains just one message (this one), remove the conversation as well.
					$q2=QueryFactory::select()
					-> table(MVC_Core::$db-> tab['cnv'], array('start_msg_id', 'count_msgs'))
					-> where("`id` = ?", $msg['cnv_id']);
					$convo = MVC_Core::$db-> query($q2)-> current();
					if($convo['count_msgs'] == 1 && $convo['start_msg_id'] == $msg['id'])
						// Remove the conversation as well
						MVC_Core::$db-> query(
							QueryFactory::delete()
							-> table(MVC_Core::$db-> tab['cnv'])
							-> where("`id` = ?", $msg['cnv_id'])
						);
					else
						MVC_Core::$db-> query(
							QueryFactory::update()
							-> table(MVC_Core::$db-> tab['cnv'])
							-> where("`id` = ?", $msg['cnv_id'])
							-> values(array(
								'count_msgs'=> $convo['count_msgs']-1
							))
						);
				}
				// Remove the erroned message
				MVC_Core::$db-> query(
					QueryFactory::delete()
					-> table(MVC_Core::$db-> tab['msg'])
					-> where("`id` = ?", $msg['id'])
				);
				continue;
			}

			// Normal message; Compute the recipients
			$recipients = array(
				$msg['to_id']
			);
			$recipient_distrib = array(
				'to'=> 1,
				'cc'=> 0,
				'bcc'=> 0
			);

			if(!empty($msg['recipients'])) {
				$c_recs = explode(',', $msg['recipients']);
				foreach($c_recs as $rec) {
					if(in_array($rec, $recipients))
						continue;
					$recipients[] = $rec;
					$recipient_distrib['cc']++;
				}
			}

			$msg_update = array(
				'to_id'=> null,
				'email'=> null,
				'recipients'=> implode(',', $recipients),
				'recipient_distrib'=> implode(',', array_values($recipient_distrib))
			);

			// Write back the update
			MVC_Core::$db-> query(
			QueryFactory::update()
				-> table(MVC_Core::$db-> tab['msg'])
				-> where("`id` = ?", $msg['id'])
				-> values($msg_update)
			);
			$counter++;
		}
		echo "$counter messages processed. Last ID: {$msg['id']}";
	}

	/**
	 * Convers old format currency pairs into new format currency field
	 * - Allows selecting a record type and the two old fields, as well as the "new" field in which data will be stored.
	 * - On proceed, all the record values are being copied into the new field
	 *
	 * @param MVC_Request $in
	 */
	public function action_DBm_Convert_Currency(MVC_Request $rq)
	{
		if($rq-> reset)
			unset($_SESSION['curr_convert']);

		if(isset($_SESSION['curr_convert'])) {
			$fields = $_SESSION['curr_convert']['fields'];
			$map = $_SESSION['curr_convert']['map'];

			// Information prepared, converting is in progress...
			$batch = (int)($rq-> batch?: 100); // Defaults to 100 records one loop
			$startfrom = (int)($rq-> startfrom?: 0); // Defaults to 0, self incrementing via page reload

			// The conversion procedure reads the two original field values for the same "issue_id" and then creates another one for same "issue_id" with the merged (and converted) values
			$q=QueryFactory::select()
			-> table(MVC_Core::$db-> tab['ixf'], array('issue_id', 'key_ids'=> "GROUP_CONCAT(fk_id)", 'cvals'=> "GROUP_CONCAT(CONCAT_WS('|', COALESCE(int_value, ''), COALESCE(value, '')) SEPARATOR '~~')"))
			-> where("`fk_id` IN(?)", $fields['old'])
			-> group("`issue_id`")
			-> limit("$batch, $startfrom");

			$r = MVC_Core::$db-> query($q);

			if(!$r || count($r)==0) {
				// DONE !
				unset($_SESSION['curr_convert']);
				echo "Operation complete.\nNo more records remaining to be processed";
				return;
			}

			foreach($r as $ixf) {
				$key_ids = explode(',', $ixf['key_ids']);
				$values = explode('~~', $ixf['cvals']);

				// Make sure the data has been arranged properly
				$assemble = array();
				foreach($key_ids as $x=> $id) {
					$assemble[(int)$id] = explode('|',$values[$x]);
				}
				// Construct the new field by taking the "value" (1) from field 0, and "int_value" (0) from field 1, converting it with the mapping
				$new = array(
					'int_value'=> isset($assemble[(int)$fields['old'][1]])?$assemble[(int)$fields['old'][1]][0]:0, // Assume Euro if not found
					'value'=> isset($assemble[(int)$fields['old'][0]])?(float)($assemble[(int)$fields['old'][0]][1]?:$assemble[(int)$fields['old'][0]][0]):null // Floaty ?
				);

				// Now convert the old "single select" into the ISO code.
				$new['int_value'] = (int)$map[$new['int_value']];

				// Insert the new field into the DB
				$qi=QueryFactory::replace()
				-> table(MVC_Core::$db-> tab['ixf'])
				-> values(array(
					'issue_id'=> $ixf['issue_id'],
					'fk_id'=> $fields['new'],
					'int_value'=> $new['int_value'],
					'value'=> $new['value']
				));

				MVC_Core::$db-> query($qi);
			}

			echo "<img src='/img/extra/loader.gif' style='float: left; margin-right: 10px;' /><h2>Convert Currency Fields</h2>
			<h3>... currently at database row number: {$startfrom}</h3>
			<p id='reload'>Reloading in <span id='countdown_clock' style='font-weight: bold;'>5</span> seconds for next batch<br />
			<button onClick='clearTimeout(stopme); clearInterval(countdown); document.getElementById(\"reload\").innerHTML = \"STOPPED!\";'>EMERGENCY BREAK</button></p>";

			$continuefrom = $startfrom + $batch;
			echo "<script>
			var stopme = setTimeout(function() {
				document.location = '?action=DBm_Convert_Currency&batch={$batch}&startfrom={$continuefrom}';
			}, 5200);

			var clock = 5, countdown = setInterval(function() {
			document.getElementById('countdown_clock').innerHTML = --clock;
			}, 1000);
			</script>";

			return;
		}

		if(!$rq-> rt_id) {
			// Get list of record types and their IDs
			$rt_list = $this-> iss-> ist_Get_List(null, false, 'converter');
?>
<h2>Convert Currency Fields - Step 1: Record type</h2>
<form action="" method="post">
	<select name='rt_id'><?php
		foreach($rt_list as $rt)
			echo "<option value=\"{$rt['id']}\">{$rt['name']} [{$rt['id']}]</option>\n";
	?></select><br />
	<br />
	<input type="submit" value='&raquo; Step 2 - Choose Fields' />
</form>
<?php
			return;
		} elseif(!$rq-> fields) {
			$rt = $this-> iss-> ist_Read($rq-> rt_id);

			// Make list of fields which CAN be currency values (must be numeric: t = "number"), and those with their key ending with "_curr"
			$old = array();
			// Make list of fields of the new type "money" for use as the target for conversion
			$new = array();

			foreach($rt['xfield_def'] as $k=> $fdef) {
				if($fdef['t']=='money') {
					$new[] = array(
						'name'=> $fdef['n'],
						'val'=> $fdef['id']
					);
					continue;
				}

				if($fdef['t']!='number')
					continue; // Skip anything that's not a number

				// Attempt to correct certain field keys
				if(isset($rt['xfield_def'][$k.'_curr']))
					$k_base = $k;
				else
					$k_base = strtok($k, '_');

				if(isset($rt['xfield_def'][$k_base.'_curr'])) {
					// We have a match !
					$old[] = array(
						'name'=> $fdef['n'].' ('.$k.')',
						'val'=> $fdef['id'].'|'.$rt['xfield_def'][$k_base.'_curr']['id']
					);
				}
			}
?>
<h2>Convert Currency Fields - Step 2: Choose Field (Old and New)</h2>
<h3>Record type: <?=$rt['name']?></h3>
<p>Note: Some fields might not be detected due to unusual naming, and they have to be renamed (field key) before this procedure can be used</p>
<form action="" method="post">

	Old pair: <select name='fields[old]'><?php
		foreach($old as $f)
			echo "<option value=\"{$f['val']}\">{$f['name']}</option>\n";
	?></select><br />
	New field: <select name='fields[new]'><?php
		foreach($new as $f)
			echo "<option value=\"{$f['val']}\">{$f['name']}</option>\n";
	?></select>
	<br />
	Map:<br />
<textarea name="mapping" rows="7">
0:978
1:840
2:826
3:392
4:682
5:578
</textarea><br />
	Batch size: <input name='batch' value="100" /><br />
	Start from: <input name='startfrom' value="0" /><br />
	<input type="hidden" name="rt_id" value="<?php echo $rt['id']; ?>" />
	<input type="submit" value='&raquo; Step 3 - Prepare conversion' />
</form>
<?php
			return;
		} else {
			// The procedure is assuming the following values for the choice of currency:
			/* Old option, which money, new ISO code ($rq-> mapping)
			0 = EUR = 978
			1 = USD = 840
			2 = GBP = 826
			3 = JPY = 392
			4 = SAR = 682
			5 = NOK = 578
			 */
			// If a field is defined differently, another mapping must be entered
			$mapping = explode("\r\n", $rq-> mapping); $map = array();
			foreach($mapping as $map_line) {
				$map_line = trim($map_line);
				if(empty($map_line))
					continue;
				else {
					list($old_c, $new_c) = explode(':', $map_line);
					$map[$old_c] = $new_c;
				}
			}

			$fields = $rq-> fields;
			$fields['old'] = explode('|', $fields['old']);

			$_SESSION['curr_convert'] = array(
				'fields'=> $fields,
				'map'=> $map
			);

			echo "<h3>Information prepared.</h3><p><a href='?action=DBm_Convert_Currency&amp;batch={$rq-> batch}&amp;startfrom={$rq-> startfrom}'>Click here to start the conversion</a></p>";
		}
	}

	/**
	 * View and manually clear IP Bans that resulted from failed login attempts
	 *
	 * @param MVC_Request $rq
	 */
	public function action_DBm_Clear_Bans(MVC_Request $rq)
	{
		if($rq-> clear) {
			// Clear bans
			$q=QueryFactory::delete()
			-> table(MVC_Core::$db-> tab['crk']);

			$r = MVC_Core::$db-> query($q);
			if(!$r) {
				echo "There was an error while attempting to delete bans or they automatically expired";
				return;
			}

			header("Location: /admin/dashboard?action=DBm_Clear_Bans");
			die;

		} else {
			// Display if there are active bans
			$q=QueryFactory::select()
			-> table(MVC_Core::$db-> tab['crk']);

			$r = MVC_Core::$db-> query($q);

			if(!$r || count($r)==0) {
				echo "There are no IP bans right now.";
				return;
			}

			echo "<h3>Listing active failed logins</h3>\n";
			foreach($r as $row) {
				echo "<p><b>". long2ip($row['ip']). "</b> Attempts: {$row['number']} - Last attempt: ". $row['last_attempt']. "</p>\n";
			}

			echo "<p><a href='?action=DBm_Clear_Bans&amp;clear=1'>Click here to clear</a></p>";
		}
	}

	/**
	 * Converts existing canned messages of type "issmessage", "issreply" into Organization Templates
	 * - Those with visibility other than "org" are not converted (need manual operation due to their larger effect)
	 *
	 * @param MVC_Request $rq
	 */
	public function action_DBm_Canned2Templates(MVC_Request $rq)
	{
		if(!$rq-> org) {
			echo "Add Org id as &org={nnn}";
			return;
		}

		$m_can = new App_Model_Canned();
		$m_tpl = new App_Model_Template();
		// Read all canned for an org (ID's from the database)

		$counter = 0;
		$canned = $m_can-> get_List($rq-> org, null, null, null, $rq-> class?: "issmessage", true);
		foreach($canned as $cm) {
			if($cm['visibility'] != 'org')
				continue; // Skip those that are not of visibility "org" ( they should be processed manually before inserting as org template, since there is no org id in them )

 			$iid = $m_tpl-> create($rq-> org, $cm['editor_id'], array(
				"rt_id"=> $cm['link_id'],
				"title"=> trim($cm['title']),
				"body"=> trim($cm['template']),
				"description"=> trim($cm['description']),	// bloody Anti-Raoul filter needed everywhere...
				"role"=> "email"
			));

			if($iid === false)
				return MVC_Core::$debug-> dump($cm, __METHOD__, null, 'error');

			// Success, remove existing "Canned"
			$m_can-> delete($cm['id']);
			$counter++;
		}

		printf("Success ! %n Canned messages have been converted into Org. templates", $counter);
	}

	/**
	 * Converts an X-field from "enum" to "set", configuration and data. Selected by field ID
	 * !! WARNING - There is no way back !!
	 * @param MVC_Request $rq
	 */
	public function action_DBm_XFSingle2Multi(MVC_Request $rq)
	{
		if(!$rq-> id) {
			echo "Add X-Field ID as <b>&id={nnn}</b>";
			return;
		}

		// Read X-Field definition to check if this is truly a single-select
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> tab['xfd'])
		-> where("`id` = ?", $rq-> id)
		-> limit(1);

		$r = MVC_Core::$db-> query($q);
		if(!$r) {
			echo "X-Field <b>not found</b>, possibly wrong ID";
			return;
		}

		$fdef = $r-> current();
		if($fdef['t'] != "enum") {
			echo "X-Field is not a <b>Single choice</b>, possibly wrong ID";
			return;
		}

		if(!$rq-> start) {
			echo "Checked and ready !<br /><a href='?action=DBm_XFSingle2Multi&id={$rq-> id}&start=1'>Click here</a> to Start converting: {$fdef['s']}/<b>{$fdef['n']}</b>";
			echo "<br /><br /><i>=== DOES NOT CONVERT TEMPLATES ===</i><br />The procedure only affects the database.<br /><b />After conversion, the field will not work until templates are adjusted !</b>";
			return;
		}

		// Decode Field Definition
		$this-> iss-> ist_XfieldDef_Prep($fdef);
		$numeric_val = !empty($fdef['o']['num']);

		// Update the field definition (change type to "set"); Also, "numeric" optimization must be removed
		unset($fdef['o']['num']);
		$qu=QueryFactory::update()
		-> table(MVC_Core::$db-> tab['xfd'])
		-> where("`id` = ?", $rq-> id)
		-> limit(1)
		-> values(array(
			't'=> "set",
			'o'=> !empty($fdef['o'])? json_encode($fdef['o']): null
		));
		MVC_Core::$db-> query($qu);

		// Read and update data fields
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> tab['ixf'])
		-> where("`fk_id` = ?", $rq-> id)
		-> order('`issue_id`');
		// -> limit(...) // No limits for now, but a looping function might be necessary if the database increases more

		$r = MVC_Core::$db-> query($q);
		if(!$r) {
			echo "No data left to be processed (or the field hasn't been used)<br />";
			return;
		}

		$c = 0; $del = 0;
		foreach($r as $fval) {
			$value = $numeric_val? $fval['int_value']: $fval['value'];
			// Before saving, check if value still exists in the choice data. If not, delete the value record

			if(!isset($fdef['cd_ex'][$value])) {
				$qd=QueryFactory::delete()
				-> table(MVC_Core::$db-> tab['ixf'])
				-> where("`issue_id` = ?", $fval['issue_id'])
				-> where("`fk_id` = ?", $rq-> id)
				-> limit(1);
				MVC_Core::$db-> query($qd);

				$del++;
				continue;
			}

			$update = array(
				'int_value'=> null,
				'value'=> json_encode(array($value))
			);

			$qu=QueryFactory::update()
			-> table(MVC_Core::$db-> tab['ixf'])
			-> where("`issue_id` = ?", $fval['issue_id'])
			-> where("`fk_id` = ?", $rq-> id)
			-> values($update);
			MVC_Core::$db-> query($qu);

			$c++;
		}

		echo "Finished converting field: {$fdef['s']}/<b>{$fdef['n']}</b><br /><b>{$c}</b> value(s) converted.<br />";
		if($del > 0) {
			echo "Also discovered and removed <b>{$del}</b> invalid value(s).";
		}
	}

	/**
	 * Updates choices in a multi-select while updating the data as well
	 * @param MVC_Request $rq
	 */
	public function action_DBm_XFMSChoices(MVC_Request $rq)
	{
		if(!$rq-> id) {
			echo "Add X-Field ID as <b>&id={nnn}</b>";
			return;
		}

		// Read X-Field definition to check if this is truly a multi-select
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> tab['xfd'])
		-> where("`id` = ?", $rq-> id)
		-> limit(1);

		$r = MVC_Core::$db-> query($q);
		if(!$r) {
			echo "X-Field <b>not found</b>, possibly wrong ID";
			return;
		}

		$fdef = $r-> current();
		if($fdef['t'] != "set") {
			echo "X-Field is not a <b>Multi-Select</b>, possibly wrong ID";
			return;
		}

		// Decode Field Definition
		$this-> iss-> ist_XfieldDef_Prep($fdef);

		echo "<h3>Choice data for {$fdef['s']}/{$fdef['n']}:</h3>";
		if(!$rq-> start) {
			// Prepare mode
			echo "<p>This function <b>CHANGES USER DATA</b>. Handle with care !</p>";
			echo "<form action='?action=DBm_XFMSChoices&id={$rq-> id}&start=1' method='post'><table border=0>";
			echo "<th style='width: 80px;'>Key</th><th>Value</th><th style='width: 80px;'>New key</th><th style='width: 300px;'>New value</th>";
			foreach($fdef['cd_ex'] as $kw=> $string)
				echo "<tr><th>{$kw}</th><td>{$string}</td><td><input style='width: 100%;' type='text' name='nk[{$kw}]' /></td><td><input style='width: 100%;' type='text' name='nv[{$kw}]' /></td></tr>";
			echo '</table><input type="submit" value="Update the field and values" /></form>';
			echo "<p style='color: red;'>This function submits a form via POST. Do NOT refresh after submit as it might corrupt the data</p>";
			echo "<p><i>=== DOES NOT CHANGE TEMPLATES ===</i><br />The procedure only affects the database.<br /><b />After this change, the application forms might malfunction until templates are adjusted !</b></p>";
				return;
		} else {
			// Update mode

			// Reconstruct the choice data when the values from matching [nk] or [nv] are not empty:
			$fdef['cd'] = array(); $chng = 0;
			foreach($fdef['cd_ex'] as $kw=> $string) {
				if(!isset($rq-> nk[$kw])) {
					echo "<p>Key <b>{$kw}</b> was not found in POST. Perhaps Choice-Data was already updated? Did you hit refresh by accident ?</p>";
				} else {
					$nk = trim($rq-> nk[$kw]); $nv = trim($rq-> nv[$kw]);
					if(strlen($nk) > 0 || strlen($nv) > 0) {
						$chng++;
						// Field is changed, must update the element
						if(strlen($nk) > 0 && $nk !== $kw) {
							echo "<p>Changing key <b>{$kw}</b> into <b>{$nk}</b> ... ";
							$q = "UPDATE `issue_fields` SET `value`= replace(`value`, '\"{$kw}\"', '\"{$nk}\"') WHERE `fk_id`={$rq-> id}";
							MVC_Core::$db-> query($q);
							$response = MVC_Core::$db-> get_Response();
							echo "affected entries: {$response['affected_rows']}</p>";
							// Key is changed; Field values must update
							$kw = $nk;
						}
						if(strlen($nv) > 0 && $nv != $string) {
							// Values is changed (does not require updating fields, only the definition)
							$string = $nv;
						}
					}
				}

				$fdef['cd'][] = "#{$kw}: {$string}";
			}

			if($chng > 0) {
				echo "<p>Updated Choice-Data - Changes made: {$chng}</p>";
				$qu=QueryFactory::update()
				-> table(MVC_Core::$db-> tab['xfd'])
				-> where("`id` = ?", $rq-> id)
				-> limit(1)
				-> values(array(
					'cd'=> json_encode($fdef['cd'])
				));
				MVC_Core::$db-> query($qu);
			} else
				echo "<p>Nothing to do, no changes detected</p>";

			echo "<p><a href='?action=DBm_XFMSChoices&id={$rq-> id}'>Click here to see choice-data again</a></p>";
		}
	}

	/**
	 * Allows changing a field name and key, keeping User/View Columns intact
	 * @param MVC_Request $rq
	 */
	public function action_DBm_XFRekey(MVC_Request $rq)
	{
		if(!$rq-> id) {
			echo "Add X-Field ID as <b>&id={nnn}</b>";
			return;
		}

		// Read X-Field definition to check if this is truly a multi-select
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> tab['xfd'])
		-> where("`id` = ?", $rq-> id)
		-> limit(1);

		$r = MVC_Core::$db-> query($q);
		if(!$r) {
			echo "X-Field <b>not found</b>, possibly wrong ID";
			return;
		}

		// Read and Decode Field Definition
		$fdef = $r-> current();

		echo "<h3>Rename and Re-Key field {$fdef['s']}/{$fdef['n']}:</h3>";
		if(!$rq-> start) {
			// Prepare mode
			echo "<p>This function <b>CHANGES USER DATA</b>. Handle with care !</p>";
			echo "<form action='?action=DBm_XFRekey&id={$rq-> id}&start=1' method='post'>";
			echo "<p>Name: <input name='n' value='{$fdef['n']}' /> Key: <input name='fk' value='{$fdef['fk']}' /></p>";

			echo '<input type="submit" value="Update the field and user config" /></form>';
			echo "<p style='color: red;'>This function submits a form via POST. Do NOT refresh after submit as it might corrupt the data</p>";
			echo "<p><i>=== DOES NOT CHANGE TEMPLATES ===</i><br />The procedure only affects the database.<br /><b />After this change, the application forms might malfunction until templates are adjusted !</b></p>";
				return;
		} else {
			$nfk = trim($rq-> fk);
			$nn = trim($rq-> n);
			// Update mode
			if($nfk != $fdef['fk'] || $nn != $fdef['n']) {
				// Changes found - If Field Key is changed, extra search & replace queries are required to maintain views/profile synchronization
				if($nfk != $fdef['fk']) {
					// Find RTkey for the ist_id
					$q=QueryFactory::select()
					-> table(MVC_Core::$db-> tab['ist'], array('name','key'))
					-> where("`id` = ?", $fdef['ist_id'])
					-> limit(1);
					$r = MVC_Core::$db-> query($q);
					$rti = $r-> current();

					$q1 ="UPDATE `user_profiles` SET `data`= replace(`data`, '\"{$fdef['fk']}\"', '\"{$nfk}\"') WHERE `pkey`='columns_ist_{$fdef['ist_id']}';";
					$q2 ="UPDATE `user_profile_defs` SET `data`= replace(`data`, '\"{$fdef['fk']}\"', '\"{$nfk}\"') WHERE `pkey`='columns_ist_{$fdef['ist_id']}';";
					$q3 ="UPDATE `grid_views` SET `columns`= replace(`columns`, '\"{$fdef['fk']}\"', '\"{$nfk}\"') WHERE `rt_key`='{$rti['key']}';";
					// Run the queries
					MVC_Core::$db-> query($q1);
					$response = MVC_Core::$db-> get_Response();
					echo "Updated user profiles: {$response['affected_rows']} affected</p>";
					MVC_Core::$db-> query($q2);
					$response = MVC_Core::$db-> get_Response();
					echo "Updated user profile defaults: {$response['affected_rows']} affected</p>";
					MVC_Core::$db-> query($q3);
					$response = MVC_Core::$db-> get_Response();
					echo "Update grid views: {$response['affected_rows']} affected</p>";
				}

				// Update field info
				$qu=QueryFactory::update()
				-> table(MVC_Core::$db-> tab['xfd'])
				-> where("`id` = ?", $rq-> id)
				-> limit(1)
				-> values(array(
					'fk'=> $nfk,
					'n'=> $nn
				));
				MVC_Core::$db-> query($qu);
				echo '<p>Field info updated</p>';

			} else
				echo "<p>Nothing to do, no changes detected</p>";

			echo "<p><a href='?action=DBm_XFRekey&id={$rq-> id}'>Click here to see the field again</a></p>";
		}
	}
}