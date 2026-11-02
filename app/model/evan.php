<?php
/**
 * "EVents And Notifications" system, used to display important information in the header of pages.
 * Also handles external notifications, which are recorded in the database and displayed in the "Notifications" module.
 * - The ArrayAccess and Iterator refers to the the Session-stored messages, and not the database parth of this model.
 *   However, the database part is accessed at startup, to determine if important notifications need to be displayed in the header area.
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage DataModels
 * @version 2.7
 */
class App_Model_Evan extends MVC_Model implements ArrayAccess, IteratorAggregate
{
	/**
	 * Number of messages Evan has stored (and will be displayed when iterating). Set by the constructor
	 * @var int
	 */
	public $msg_count = 0; 

	/**
	 * Instance of the communication model, used to send e-mail based notifications (Created on first use)
	 *
	 * @var App_Model_Comm
	 */
	protected static $m_comm = null;

	/**
	 * This is a model that uses only the session (for now)
	 *
	 * @param array $tpl_paths
	 */
	public function __construct($params = array())
	{
		// If this is an ajax request, do not perform any processing - Evan is initialized only for normal page loads.
		if(App_Router::$ajax)
			return;

		// Initialize the session if it's the first instance of Model/Evan
		if(empty(MVC_Core::$ss-> evan))
			$this-> reset();

		// If there are new notifications for the user, refresh the EVAN messages
		if(!empty(MVC_Core::$ss-> user)) {
			if(isset(MVC_Core::$ss-> user['check_evn'])) {
				$this-> analyse_Async_Evn();
				unset(MVC_Core::$ss-> user['check_evn']);
			}
		}

		// process all messages before they can be accessed ( timeouts, self-remove, etc. )
		$msg_store = &MVC_Core::$ss-> evan['msg_store'];
		foreach($msg_store as $id=> $msg) {
			if($msg instanceof App_Model_Evan_Message) {
				if($msg-> self_Check())
					unset($msg_store[$id]);
			} else // clean non-messages
				unset($msg_store[$id]);
		}

		$this-> msg_count = count($msg_store);
	}

	/**
	 * Removes its data from the session, and prevents saving it at __destruct time
	 * -- (useful when logging out for example)
	 */
	public function reset()
	{
		MVC_Core::$ss-> evan = array(
			'id_ai'=> 0,
			'msg_store'=> array(), // On-screen notifications store
			'async'=> array( // Asynchronous notifications (Stored in DB)
				'count'=> 0, // Number at last check
				'changed'=> false, // Number changed since last check
				'bar_id'=> null, // The ID of the bar saying how many unread notifications exist
				'bar_closed'=> false // User has chosen to hide the bar - The bar won't appear unless the number has changed
			)
		);
		$this-> msg_count = 0;
	}

	/**
	 * Creates a new message and automatically assign a new ID to it.
	 *
	 * @param string $message The message to record
	 * @param string $type Notify, Error
	 * @param array $opts Options for the more special types of messages
	 * @return App_Model_Evan_Message The message object reference (can be displayed immediatly or used later)
	 */
	public function &add($message, $type='notify', $opts=null)
	{
		if(!is_array($opts))
			$opts = array();
		$opts['type'] = $type;

		$id = ++MVC_Core::$ss-> evan['id_ai'];
		MVC_Core::$ss-> evan['msg_store'][$id] = new App_Model_Evan_Message($id, $message, $opts);
		$this-> msg_count++;
		return MVC_Core::$ss-> evan['msg_store'][$id];
	}

	/**
	 * ArrayAccess isset() implementation
	 * @param int $id
	 * @return bool
	 */
	public function offsetExists($id)
	{
		return isset(MVC_Core::$ss-> evan['msg_store'][$id]);
	}

	/**
	 * ArrayAccess Read v = [] implementation
	 * @param int $id
	 * @return App_Model_Evan_Message
	 */
	public function offsetGet($id)
	{
		if(isset(MVC_Core::$ss-> evan['msg_store'][$id]))
			return MVC_Core::$ss-> evan['msg_store'][$id];
		else {
			$n = null;
			return $n;
		}
	}

	/**
	 * ArrayAccess Write [] = ? implementation (Update existing message)
	 * @param int $id
	 */
	public function offsetSet($id, $text)
	{
		if(isset(MVC_Core::$ss-> evan['msg_store'][$id]))
			MVC_Core::$ss-> evan['msg_store'][$id]-> text = $text;
	}

	/**
	 * ArrayAccess unset() implementation (delete a message)
	 * @param int $id
	 */
	public function offsetUnset($id)
	{
		if(isset(MVC_Core::$ss-> evan['msg_store'][$id])) {
			unset(MVC_Core::$ss-> evan['msg_store'][$id]);
			$this-> msg_count--;

			$async = &MVC_Core::$ss-> evan['async'];
			if($id == $async['bar_id']) {
				$async['bar_id'] = null;
				$async['bar_closed'] = true;
			}
		}
	}

	/**
	 * IteratorAggregate implementation
	 * @return ArrayIterator
	 */
	public function getIterator() {
		if(empty(MVC_Core::$ss-> evan))
			// just a check for the attempt to iterate an already cleaned up Evan
			return new ArrayIterator(array());
		else
			return new ArrayIterator(MVC_Core::$ss-> evan['msg_store']);
	}

	/**
	 * Returns the oldest message from the list
	 * @return App_Model_Evan_Message
	 */
	public function &first()
	{
		if(!empty(MVC_Core::$ss-> evan['msg_store'])) {
			reset(MVC_Core::$ss-> evan['msg_store']);
			return MVC_Core::$ss-> evan['msg_store'][key(MVC_Core::$ss-> evan['msg_store'])];
		} else {
			$n = null;
			return $n;
		}
	}

	/**
	 * Returns the newest message from the list
	 * @return App_Model_Evan_Message
	 */
	public function &last()
	{
		if(!empty(MVC_Core::$ss-> evan['msg_store'])) {
			end(MVC_Core::$ss-> evan['msg_store']);
			return MVC_Core::$ss-> evan['msg_store'][key(MVC_Core::$ss-> evan['msg_store'])];
		} else {
			$n = null;
			return $n;
		}
	}

//===== Notifications CRUD =====//

	/**
	 * Accepted notification types and subtypes - Also contains the title, and in comments, relevant data
	 * @todo Grab this from the YAML file used for subscriptions?
	 *
	 * @var array[string]
	 */
	private static $accepted_notifications_types = array(
		'system'=> array( // System notifications are sent to all users, unless they opted-out
			'message'=> "Message from administrator",
			'shutdown'=> "The system will be shut down for maintenance",
			'boot'=> "System has been turned back on, operation normal",
			'updated'=> "Application updated", // ver: New version number
			'error'=> "Error discovered, fix in progress"
		),
		'issue'=> array( // Record notifications need to be opted-in on an issue-type basis
			'created'=> "New data available", // item_id: Issue id ; extra_id1: Issue type id ; extra_id2: (Optional) Organization ID which created or sent the issue
				// This issue should not be visible in the main grid unless using "show closed"
			'updated'=> "Record has been changed", // item_id: Issue id ; extra_id1: Issue type id ; extra_id2: (Optional) Organization ID if the change happend on staff side
			'assigned'=> "Record assigned to user", // item_id: Issue id ; extra_id1: Issue type id ; extra_id2: (Optional) Organization ID if the change happend on staff side
                        'unassigned'=> "Record unassigned to user" // item_id: Issue id ; extra_id1: Issue type id ; extra_id2: (Optional) Organization ID if the change happend on staff side // Send the notification when assign and un assign
		),
		// Note: Tasks are not yet fully implemented
		'task'=> array( // Tasks notification are only sent to the users affected (task assignee, task creator)
			'assigned'=> "New task has been assigned", // item_id: Task ID ; extra_id1: Task creator user
			'assigdep'=> "New task assigned to department", // item_id: Task ID ; extra_id1: Task creator user
			'unassigned'=> "Task is unassigned", // item_id: Task ID ; extra_id1: User or Dep previously assigned to the task ; extra_id2 = user or dep
			'stop'=> "Task processing has stopped", // item_id: Task ID ; extra_id1: User or Dep assigned to the task ; extra_id2 = user or dep
			'deleted'=> "Task has been removed", // item_id: Task ID ; extra_id1: Task creator user
		),
		'other'=> true // Has no subtype
	);

	/**
	 * Creates a new notification record for a certain user
	 *
	 * @param int $user_id The user for which the notification is created
	 * @param string $type Type of notifications ( see the $accepted_notifications_types static )
	 * @param string $subtype Subtype ( see $accepted_notifications_types static )
	 * @param string $title Title for this notification, will also be used as e-mail title if the user has opted-in to be notified via e-mail ( If missing, there are several ways on which the title is constructed )
	 * @param int $item_id Item to which this notification reffers to ( depends on type )
	 * @param int $extra_id1 Other ID necessary for some type
	 * @param int $extra_id2 Supplimentary ID for a few types
	 * @param string $xdata Data which will be JSON encoded and packed with the notification record
	 * @param string $description Optional description - Used in message sent to the user ( if opted in to receive a message )
	 * @param bool $instant Allows instant notifications to be disabled for the user (They are still shown on next login !)
	 * @param bool $email If true, the function immediately creates and sends a system message containing this notification. The content is constructed from a template based on type, subtype and the other parameters
	 */
	public function create($user_id, $type, $subtype = null, $title = null, $item_id = null, $extra_id1 = null, $extra_id2 = null, $xdata = null, $description = null, $instant = true)
	{
		if(empty($user_id))
			return MVC_Core::$debug-> dump("User ID is empty", "EVAN::Create", null, "error");

		// Check $type and $subtype
		if(!isset(self::$accepted_notifications_types[$type]))
			return MVC_Core::$debug-> dump("Unknown notification type", "EVAN::Create", null, "error");

		if(!is_null($subtype)) {
			$type_group = self::$accepted_notifications_types[$type];
			if(is_array($type_group)) {
				if(!isset($type_group[$subtype]))
					return MVC_Core::$debug-> dump("Invalid notification subtype or for wrong type", "EVAN::Create", null, "error");
				else
					$default_title = $type_group[$subtype];
			}
		}

		if(empty($title))
			$title = isset($default_title)? (string)$default_title: "";

		// Initial priority depends on type, but can be changed later
		$importance = array(
			'system'=> "high",
			'issue'=> "low",
			'task'=> "med",
			'other'=> "low"
		);

		$notification = array(
			'time'=> time(),
			'user_id'=> $user_id,
			'importance'=> $importance[$type],
			'status'=> "new",
			'type'=> $type,
			'subtype'=> is_null($subtype)? "other": $subtype,
			'item_id'=> $item_id,
			'extra_id1'=> $extra_id1,
			'extra_id2'=> $extra_id2,
			'title'=> $title,
			'xdata'=> empty($xdata)? null: json_encode($xdata),
			'description'=> $description
		);

		$q=QueryFactory::insert()
		-> table(MVC_Core::$db-> tab['evn'])
		-> values($notification);

		if(MVC_Core::$db-> query($q)) {
			$iid = MVC_Core::$db-> insert_id;

			if($instant) {
				// If the user has any active sessions, activate "check_evn" for the user, so next session check (for any reason) will test the notifications table (and detect the newly inserted one)
				$q=QueryFactory::update()
				-> table(MVC_Core::$db-> tab['ass'])
				-> where("`user_id` = ?", $user_id)
				-> values(array('check_evn'=> 1));

				MVC_Core::$db-> query($q);
			}

			return $iid;
		} else
			return MVC_Core::$debug-> dump("Failed inserting notification into the database", "EVAN::Create", null, "error");
	}

	/**
	 * Creates a system-wide mass notification ( to all users of the system ) using a single query.
	 * -- Used to announce things like "system shutting down" or "update performed" to all users. Type is always "system"
	 * -- First it reads all the ID's of active users and then a multi-row insert with the same data.
	 *
	 * @param string $subtype Subtype ( see $accepted_notifications_types static )
	 * @param string $title Title for this notification, will also be used as e-mail title if the user has opted-in to be notified via e-mail ( If missing, there are several ways on which the title is constructed )
	 * @param string $description Optional description - Used in message sent to the user ( if opted in to receive a message )
	 * -- All other are not used in normal system messages.
	 * @param int $item_id Item to which this notification reffers to ( depends on type )
	 * @param int $extra_id1 Other ID necessary for some type
	 * @param int $extra_id2 Supplimentary ID for a few types
	 * @param string $xdata Data which will be JSON encoded and packed with the notification record
	 */
	public function create_System($subtype, $importance = 'high', $title = null, $description = null, $item_id = null, $extra_id1 = null, $extra_id2 = null, $xdata = null)
	{
		if(empty($subtype))
			return MVC_Core::$debug-> dump("Notification subtype must not be empty for system-wide notification", "EVAN::Create System-Wide", null, "error");

		$type_group = self::$accepted_notifications_types['system'];
		if(is_array($type_group)) {
			if(!isset($type_group[$subtype]))
				return MVC_Core::$debug-> dump("Invalid notification subtype or for wrong type", "EVAN::Create System-Wide", null, "error");
			else
				$default_title = $type_group[$subtype];
		}

		if(empty($title))
			$title = isset($default_title)? (string)$default_title: "";

		$notification = array(
			'time'=> time(),
			'user_id'=> null, // Placeholder, one row is inserted for every user!
			'importance'=> $importance,
			'status'=> "new",
			'type'=> 'system',
			'subtype'=> $subtype,
			'item_id'=> $item_id,
			'extra_id1'=> $extra_id1,
			'extra_id2'=> $extra_id2,
			'title'=> $title,
			'xdata'=> empty($xdata)? null: json_encode($xdata),
			'description'=> $description
		);

		// Get list of users using the subscription model.
		$ntf_subs = $this-> get_Users_Subscribed_For("system_".$subtype);

		// Create the insert query
		// -- (E-mails will be sent later, depending on user notification subscription preferences - TODO)
		// -- Insert max 100 at a time, to avoid massive queries in case the number of users is high.
		$c = 0;
		$mail_list = array();
		foreach($ntf_subs as $user_id=> $subscription) {
 			if(empty($subscription['opts']['active']))
 				continue; // Ignore non-active subscription

			if($subscription['opts']['email'])
				$mail_list[] = $subscription['user'];

			if($c==0) {
				$q=QueryFactory::insert()
				-> table(MVC_Core::$db-> tab['evn']);
			}
			$notification['user_id'] = (int)$user_id;
			$q-> values($notification);
			$c++;
			// Run the query if max has been reached.
			if($c==100) {
				MVC_Core::$db-> query($q);
				unset($q);
				$c=0;
			}
		}

		if(!$c)
			return 0;

		// Not reaching 100 ? Run the query with whatever we have so far.
		if($c<100)
			MVC_Core::$db-> query($q);

		// Force notifications to be re-read on all users (including admin)
		$q=QueryFactory::update()
		-> table(MVC_Core::$db-> tab['ass'])
		-> values(array('check_evn'=> 1));

		MVC_Core::$db-> query($q);

		return count($ntf_subs);
	}

	/**
	 * Reads complete data about a notification
	 * - May include the generated message, basic information about the item connected to the notification, extras
	 *
	 * @param int $notif_id
	 * @param array[string] $wtr What to read: [ notification, message, item, extra1, extra2 ]
	 */
	public function read($notif_id, $wtr = array('notification'))
	{
		if(empty($notif_id))
			return MVC_Core::$debug-> dump("Notification record ID empty", "EVAN::Read", null, "error");

		$q=QueryFactory::select()
		-> where("`evn`.`id` = ?", $notif_id)
		-> limit(1);

		$wtr = self::wtr_Helper($wtr);

		if(in_array('notification', $wtr))
			$q-> table(MVC_Core::$db-> table('evn')); // all fields in the primary table
		else
			$q-> table(MVC_Core::$db-> table('evn'), 'id,time,user_id,status'); // Check if it exists

		if(in_array('user', $wtr))
			$q-> table(MVC_Core::$db-> table('usr'), QueryFactory::autoalias_Add_Prefix("user,org_id,name,email,contact_id,inbox_id", '_usr_'), 'left', '`evn`.user_id = `usr`.id');

		$r = MVC_Core::$db-> query($q);
		if(!$r || count($r)==0)
			return MVC_Core::$debug-> dump("Notification record {$notif_id} not found", "EVAN::Read", null, "error");

		$r-> split_by_prefixes = array(
			// extra tables
			'user'=> "_usr_"
		);
		$notif = $r-> current();

		// Post-Process
		if(in_array('notification', $wtr)) {
			$notif['dt'] = date("Y-m-d H:i:s", $notif['time']);

			if(!empty($notif['xdata']))
				$notif['xdata'] = @json_decode($notif['xdata'], true);

			if(in_array('rel_item', $wtr)) {
				// Read the related item depending on notification type
				switch($notif['type']) {
					case 'issue':
						$m_issue = new App_Model_Issue();
						$notif['item'] = $m_issue-> read($notif['item_id'], in_array('rel_details', $wtr)? array('issue', 'type', 'group', 'submitter'): array());
						break;
					case 'task':
						break;
					default:
						$notif['item'] = false; // System/Other do not have a related item.
				}
			}
		}

		return $notif;
	}

	/**
	 * Updates status and importance of a notification ( data cannot be changed once the notification has been created )
	 *
	 * @param int $notif_id
	 * @param string $status [ new, read, dismissed, archived ]
	 * @param string $importance [ high, med, low ]
	 */
	public function update($notif_id, $status = null, $importance = null)
	{
		if(empty($notif_id))
			return MVC_Core::$debug-> dump("Notification record ID empty", "EVAN::Update", null, "error");

		$val = array();

		if(!is_null($status)) {
			if(!in_array($status, array('read', 'dismissed', 'archived')))
				return MVC_Core::$debug-> dump("Invalid value for notification status", "EVAN::Update", null, "error");
			else
				$val['status'] = $status;
		}

		if(!is_null($importance)) {
			if(!in_array($importance, array('high', 'med', 'low')))
				return MVC_Core::$debug-> dump("Invalid value for notification importance", "EVAN::Update", null, "error");
			else
				$val['importance'] = $importance;
		}

		if(empty($val))
			return MVC_Core::$debug-> dump("Both Status and Importance are null. Nothing to update!", "EVAN::Update", null, "warning");

		$q=QueryFactory::update()
		-> table(MVC_Core::$db-> tab['evn'])
		-> where("`id` = ?", $notif_id)
		-> values($val);

		return MVC_Core::$db-> query($q);
	}

	/**
	 * Updates multiple notifications at once
	 *
	 * @param int $user_id
	 * @param callback $update_rules
	 */
	public function update_Bulk($user_id, $update_function)
	{
		if(empty($user_id))
			return MVC_Core::$debug-> dump("User ID is empty", "EVAN::Update Bulk", null, "error");

		if(!is_callable($update_function))
			return MVC_Core::$debug-> dump("Update function is not callable", "EVAN::Update Bulk", null, "error");

		$q=QueryFactory::update()
		-> table(MVC_Core::$db-> table('evn', false))
		-> where("`user_id` = ?", $user_id);

		$update_function($q);

		$r = MVC_Core::$db-> query($q);
		if(!$r)
			return MVC_Core::$debug-> dump("Bulk update function failed", "EVAN::Update Bulk", null, "error");

		$aff_rows = MVC_Core::$db-> affected_rows;

		// If the user has any active sessions, activate "check_evn" for the user, so next session check (for any reason) will test the notifications table
		$q=QueryFactory::update()
		-> table(MVC_Core::$db-> tab['ass'])
		-> where("`user_id` = ?", $user_id)
		-> values(array('check_evn'=> 1));

		MVC_Core::$db-> query($q);

		return $aff_rows;
	}

	/**
	 * Deletes the notification record completely (used as the last step after reading or archiving)
	 *
	 * @param int $notif_id
	 */
	public function delete($notif_id)
	{
		if(empty($notif_id))
			return MVC_Core::$debug-> dump("Notification record ID empty", "EVAN::Delete", null, "error");

		$q=QueryFactory::delete()
		-> table(MVC_Core::$db-> tab['evn'])
		-> where("`id` = ?", $notif_id)
		-> limit(1);

		return MVC_Core::$db-> query($q);
	}

//----- Lists -----//

	public $found_rows = 0;

	/**
	 * Reads several notificaitons at once
	 *  - Usually to be displayed in a grid, but also for other purposes
	 *
	 * @param array[string] $wtr
	 * @param callable $qfo_filter QueryFactory filter
	 * @param array $filter_params
	 * @param string $order_by Which field to be used for ordering - Defaults to order by name, alphabetically ascending
	 * @param int $count_rows Paging Size
	 * @param int $start_row Paging Start
	 */
	public function get_List($wtr = array('notification'), $qfo_filter = null, $filter_params = array(), $order_by = "`evn`.`time` DESC", $count_rows = 100, $start_row = 0, $calc_found_rows = false)
	{
		$q = $this-> get_List_QueryFactory_Helper($qfo_filter, $filter_params, $order_by, $count_rows, $start_row);
		if(is_null($q))
			return false; // Error, $qfo_filter failed

		if($calc_found_rows)
			$q-> flags("SQL_CALC_FOUND_ROWS");

		$wtr = self::wtr_Helper($wtr);

		if(in_array('notification', $wtr))
			// full notification data (including xdata)
			$q-> table(MVC_Core::$db-> table('evn'));
		else
			// basic data
			$q-> table(MVC_Core::$db-> table('evn'), 'id,time,status');
                
//echo $q;exit();
		$r = MVC_Core::$db-> query($q);

		if(!$r || count($r)==0)
			return false; // error or empty result

		$r-> split_by_prefixes = array(
			// extra tables
			'user'=> "_usr_"
		);

		// Some post-processing
		$all = array();
		foreach($r as $row) {
			$row['id'] = (int)$row['id'];
			$row['dt'] = date("Y-m-d H:i:s", $row['time']);
			$row['status'] = ucfirst($row['status']);

			if(in_array('notification', $wtr)) {
				if(!is_null($row['user_id']))
					$row['user_id'] = (int)$row['user_id'];
				$row['importance'] = ucfirst($row['importance']);
				$row['type'] = ucfirst($row['type']);
				if(!empty($row['subtype']))
					$row['subtype'] = ucfirst($row['subtype']);

				if(!empty($row['xdata'])){
					$row['xdata'] = @json_decode($row['xdata'], true);
					$row['xdata_title'] =$row['xdata']['track'];
					$row['xdata_type'] = $row['xdata']['type'];
                                }
			}
                        
//                        $q_rec=QueryFactory::select()-> table('issues')-> where("`track` = ?", $row['xdata']['track']);
//                        $r_rec = MVC_Core::$db-> query($q_rec)-> current();
//                
//                        print_r($r_rec);exit();
                
                
//                        $row['claim_type']=$r_rec;
                        $claim_type=$row['xdata']['primary']?$row['xdata']['primary']:'000-00000000';
                        $row['claim_type']='<a href="/record/process:'.$row['xdata']['track'].'#panels">'.$claim_type.'</a>';
			$all[] = $row;
		}

		// Check if the calc_found flag is used
		if($calc_found_rows) {
			$fr_row = MVC_Core::$db-> query("SELECT FOUND_ROWS() AS `found_rows`;")-> current();
			$this-> found_rows = (int)$fr_row['found_rows'];
		}

		return $all;
	}

	/**
	 * Quickly checks how many notifications with state "new" exist in an user's queue, with date newer than $dt
	 * -- Used to show a notification box following every heartbeat.
	 *
	 * @param int $user_id
	 * @param int $time If non-zero, this is the minimum time on which notifications are checked - Used to avoid reading all the table after a "Dismiss All" command.
	 * @param string $min_importance Minimum importance - The user might opt not to be bothered by low or med importance notifications
	 * @param bool $total Returns just the total amount ( not distributed by type )
	 * @return array Returns an array with number of each type of notification that matches
	 */
	private function check_New($user_id, $time, $total = true, $min_importance = 'low')
	{
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> table('evn'), $total? array('count'=> 'COUNT(*)'): array('type', 'count'=> 'COUNT(*)'))
		-> where("`evn`.`user_id` = ?", $user_id)
		-> where("`evn`.`status` = 'new'");

		if($time > 0)
			$q-> where("`evn`.`time` > ?", $time);

		if(!$total)
			$q-> group("`evn`.`type`");

		if($min_importance=='med')
			$q-> where("`evn`.`importance` = 'high' || `evn`.`importance` = 'med'");
		elseif($min_importance=='high')
			$q-> where("`evn`.`importance` = 'high'");

		$r = MVC_Core::$db-> query($q);
		if(!$r || count($r)==0)
			return array(); // No results ( there are no notifications that match )

		if($total) {
			$c = $r-> current();
			return $c['count'];
		} else
			return $r-> current();
	}

	/**
	 * Checks recorded notifications, determines if there's any change (against session $ss-> evan['async']) and inserts or updates the EVAN Message related to async notifications
	 * -- This is the link between the two parts of EVAN, the recorded asynchronous notifications and the on-screen notifications.
	 * -- This function always applies to the currently logged in user
	 */
	public function analyse_Async_Evn()
	{
		$evn_count = $this-> check_New(MVC_Core::$ss-> user['id'], MVC_Core::$ss-> user['ntf_dismiss_time']);
		$async = &MVC_Core::$ss-> evan['async'];

		if($evn_count != $async['count']) {
			// The number of notifications has changed, the user should be informed - Add or update the notification bar.
			if($evn_count > 0) {
				$async['changed'] = true;
				$async['count'] = $evn_count;
				$async['bar_closed'] = false;
				$this-> activate_Notifications_Bar($evn_count);
				return $async['bar_id'];
			} else {
				// When count is zero, make the bar dissapear, but send a message back with the bar ID, so it can be properly removed
				$bar_id = $async['bar_id'];
				$async['changed'] = true;
				$async['count'] = 0;
				$this-> offsetUnset($async['bar_id']);
				return $bar_id;
			}
		} else {
			$async['changed'] = false;
		}
	}

	/**
	 * Shows or updates the notification bar with the new amount of notifications.
	 *
	 * @param int $count
	 */
	private function activate_Notifications_Bar($count)
	{
		$async = &MVC_Core::$ss-> evan['async'];

		// TODO: Make the bar more fancy, perhaps displaying icons for the numbers of notifications of different types, etc...
		$ntf_text = "You have <b>{$count}</b> unread notification".($count>1? 's':'')."! <a href='/user/overview#notify'>[ Click here ]</a> to view and/or dismiss ".($count>1? 'them':'it').".";

		if(is_null($async['bar_id'])) {
			$ntf_bar = $this-> add($ntf_text, 'notify', array(
	 			'permanent'=> true // This bar doesn't self-close, only the user can remove it.
	 		));
			$async['bar_id'] = $ntf_bar-> id;
		} else
			$this-> offsetSet($async['bar_id'], $ntf_text);
	}

	/**
	 * Generates a message addressed to the contact of a user for which a notification exists (was previously inserted with ::create)
	 * -- The returned value is the message ID, which can then be sent by mail if necessary
	 * -- Messages are stored in view/sysmsg/usr_notif and depend on type/subtype
	 *
	 * @param int $notif_id
	 * @param bool $by_email Also send the generated message via e-mail
	 * @param string $tpl_include Includes this template in the sent message
	 * @param mixed $include_xdata Extra Data for the included component.
	 * @return int Message ID
	 */
	public function generate_Message($notif_id, $by_email = false, $tpl_include = null, $include_xdata = null, $use_css = null)
	{
		$wtr = array('notification', 'user');

		$notif = $this-> read($notif_id, $wtr);

		if(empty($notif))
			return MVC_Core::$debug-> dump("Unable to read the notification record", "EVAN::Generate Message", null, "error");

		if(is_null(self::$m_comm))
			self::$m_comm = new App_Model_Comm();

		$tpl = 'usr_notif'. DS. $notif['type']. (!empty($notif['subtype'])? '_'.$notif['subtype']: '').'.html';

		$tpl_data = array(
			'notif'=> $notif,
			'user'=> $notif['user']
		);
		unset($tpl_data['notif']['user']);

		if(!is_null($tpl_include))
			$tpl_data['_include'] = $tpl_include;
		if(!is_null($include_xdata))
			$tpl_data = array_merge($tpl_data, $include_xdata);
		if(!is_null($use_css))
			$tpl_data['_use_css'] = $use_css;

		$msg_iid = self::$m_comm-> create_System_Message($tpl_data['user']['contact_id'], $tpl, $tpl_data);

		if(empty($msg_iid))
			return MVC_Core::$debug-> dump("Unable to create the system message", "EVAN::Generate Message", null, "error");

		if($by_email) {
			$send = self::$m_comm-> email_Message($msg_iid, null, null, true, true, null, array(
				// The notification type/subtype icon
				'htdocs'. DS. 'img'. DS. 'icons'. DS. 'evan'. DS. 'ntf'. DS. $notif['type']. (!empty($notif['subtype'])? '_'.$notif['subtype']: ''). '.png'
			));
			if(!$send)
				MVC_Core::$debug-> dump("There was an error in the e-mail function.", "EVAN::Generate Message", null, "error");
		}

		return $msg_iid;
	}

	/**
	 * Prepares and writes (create/update) the notification subscriptions data
	 * -- Multiple rows at once!
	 *
	 * @param array $data
	 */
	public function subscription_Write($data, $user_id)
	{
		// Prepare the data, then merge with the existing user subscriptions (by deleting updated keys and recreating them)
		$vals = array();
		foreach($data as $key=> &$opts) {
			$opts = 16*(int)$opts[0] + 8*(int)$opts[1] + 4*(int)$opts[2] + 2*(int)$opts[3] + (int)$opts[4];
			if($opts)
				// Only those that have at least one checkbox active (well, [1][2][3][4] doesn't matter if [0] is off)
				$vals[] = array(
					'user_id'=> $user_id,
					'ntf_key'=> $key,
					'opts'=> $opts
				);
		}

		// Remove exiting values
		$q=QueryFactory::delete()
		-> table(MVC_Core::$db-> tab['nsu'])
		-> where("`user_id` = ?", $user_id)
		-> where("`ntf_key` IN(?)", array_keys($data));

		MVC_Core::$db-> query($q);

		if(count($vals)) {
			// And reinsert them
			$q=QueryFactory::insert()
			-> table(MVC_Core::$db-> tab['nsu']);
			foreach($vals as $val)
				$q-> values($val);

			return MVC_Core::$db-> query($q);
		} else
			return true;
	}

	/**
	 * Reads all subscriptions for an user and decodes the binary options
	 *
	 * @param int $user_id
	 */
	public function get_All_User_Subscriptions($user_id)
	{
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> tab['nsu'])
		-> where("`user_id` = ?", $user_id);

		$r = MVC_Core::$db-> query($q);
		if(!$r)
			return false;

		$all = array();
		foreach($r as $sub) {
			// Post-Process
			$bin = sprintf("%05d", decbin($sub['opts']));
			$opts = array(
				'active'=> (bool)$bin[0],
				'instant'=> (bool)$bin[1],
				'email'=> (bool)$bin[2],
				'enhanced'=> (bool)$bin[3],
				'summary'=> (bool)$bin[4]
			);
			$all[$sub['ntf_key']] = $opts;
		}
		return $all;
	}

	/**
	 * Gets the list of users (their ID + Name + E-Mail) which are subscribed to a certain "key"
	 * -- Restricted per organization (otherwise all users from all organizaitons that have subscribed would be notified for all issues)
	 *
	 * @param string $key
	 * @param int|array $org_ids Optional: Organization(s) for the users
	 * @param int $opts_code Optional: Only users which have THIS specific options code
	 * @param array Optional: List of departments in which the users must be to receive notifications (optional)
	 */
	public function get_Users_Subscribed_For($key, $org_ids = null, $opts_code = null, $deps_ids = null)
	{
		if(empty($key))
			return MVC_Core::$debug-> dump("The subscription key is empty", "EVAN::UsersAllSubs", null, "error");

		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> table('nsu'), array('user_id', 'opts'))
		-> table(MVC_Core::$db-> table('usr'), QueryFactory::autoalias_Add_Prefix("name,email,status,org_id", '_usr_'), 'left', '`usr`.`id` = `nsu`.`user_id`')
		-> where("`nsu`.ntf_key = ?", $key)
		-> where('`usr`.status IN(?)', array('active', 'online', 'invisible', 'supervisor', 'admin', 'jumper')) // The user must be active in order for his subscriptions to apply
		-> order("`nsu`.user_id ASC");

		// Organization(s) list
		if(is_array($org_ids))
			$q-> where("`usr`.org_id IN(?)", $org_ids);
		elseif(is_numeric($org_ids))
		$q-> where("`usr`.org_id = ?", $org_ids);

		// Specific options codes
		if(!is_null($opts_code))
			$q-> where("`nsu`.opts = ?", $opts_code); // Custom codes (might be for different purposes other than notifying)
		else
			$q-> where("`nsu`.opts >= 16"); // "Get notified" must be on for notifications to be considered. The user can turn it off (values 0-7) while keeping his settings, but the system ignores values smaller than 8 when checking subscriptions

		// Limit departments
		if(is_array($deps_ids) && !empty($deps_ids))
			$q-> where("`usr`.cdep_id IN(?)", $deps_ids);

		$r = MVC_Core::$db-> query($q);
		if(!$r)
			return false;

		$r-> split_by_prefixes = array(
			// extra table(s)
			'user'=> "_usr_"
		);

		$all = array();
		foreach($r as $sub) {
			// Post-Process
			$bin = sprintf("%05d", decbin($sub['opts']));
			$opts = array(
				'active'=> (bool)$bin[0],
				'instant'=> (bool)$bin[1],
				'email'=> (bool)$bin[2],
				'enhanced'=> (bool)$bin[3],
				'summary'=> (bool)$bin[4]
			);
			$all[$sub['user_id']] = array(
				'opts'=> $opts,
				'user'=> $sub['user']
			);
		}
		return $all;
	}

	/**
	 * Verifies if an user is subscribed to one type of notification. Used for direct notifications addressed to that user ( like Task assigned )
	 *
	 * @param int $user_id
	 * @param string $key Type of notificatin the user might be subscribed to
	 * @param int $opts_code If present, the user must have THIS specific options code, or the function returns "false" ( no subscription )
	 */
	public function check_User_Subscription($user_id, $key, $opts_code = null)
	{
		if(empty($key))
			return MVC_Core::$debug-> dump("The subscription key is empty", "EVAN::UserSub", null, "error");

		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> table('nsu'), "opts")
		-> where("`nsu`.user_id = ?", $user_id)
		-> where("`nsu`.ntf_key = ?", $key)
		-> limit(1);

		if(!is_null($opts_code))
			$q-> where("`nsu`.opts = ?", $opts_code); // Custom codes (might be for different purposes other than notifying)
		else
			$q-> where("`nsu`.opts >= 16"); // "Get notified" must be on for notifications to be considered.

		$r = MVC_Core::$db-> query($q);
		if(!$r || !count($r))
			return false;

		$sub = $r-> current();
		$bin = sprintf("%05d", decbin($sub['opts']));
		$opts = array(
			'active'=> (bool)$bin[0],
			'instant'=> (bool)$bin[1],
			'email'=> (bool)$bin[2],
			'enhanced'=> (bool)$bin[3],
			'summary'=> (bool)$bin[4]
		);

		return $opts;
	}

}