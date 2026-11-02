<?php
/**
 * Global Task-related Controller (actions which could be called from any module)
 * - Tasks can be connected to multiple types of elements, however right now only connection to Primary Records is implemented
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage Controllers
 * @version 2020.04.17
 */
class App_Ctrl_Global_Task extends App_Ctrl_Base
{
	/**
	 * Task model object
	 * @var App_Model_Task
	 */
	private $task;

	/**
	 * Actions model object, used for delayed task notifications (reminder, deadline, overdue)
	 * @var App_Model_Actions
	 */
	private $act;

	/**
	 * Initialize the data models (used pretty much everywhere)
	 */
	public function __construct()
	{
		parent::__construct();

		$this-> task = $this-> factory_Model("Task");
		$this-> record = $this-> factory_Model("Issue", 'rec');
		$this-> act = $this-> factory_Model("Actions", 'act');
	}

	/**
	 * Helper function that defines a notification action for a specific date (used in task Create, task Update)
	 *
	 * @param int $task_id
	 * @param DateTime|string $trigger_dt A DateTime object or Date in ISO format, or "now")
	 * @param string $operation (one of: "assign", "unassign", "reminder", "deadline", "overdue", "completion", "failure", "closure")
	 * @param array $task_data
	 * @return int ID of the created action (will be stored with the task)
	 */
	protected function assignee_Notification($task_id, $trigger_dt, $operation, $task_data)
	{
		$action_data = array(
			'type'=> $task_data['action_ntf_type'],		// (email, sys) - Uses different method to generate the notification
			'mode'=> $task_data['assign_mode'],			// Assign mode (con, user, dep, group) - The "group" will send multiple notifications, while "dep" has special processing
			'to'=> (int)$task_data['assign_to'], 		// can be: Contact ID, User ID, Department ID, Group ID
			// Used to identify which user performed an action that is generating a notification
			'by'=> (int)MVC_Core::$ss-> user['id']
		);
		// Add references to the element the task is linked to (record), as it will probably be needed for generating the notification
		if(isset($task_data['link_type']))
			$action_data['link'] = array(
				'type'=> $task_data['link_type'],
				'id'=> (int)$task_data['link_id']
			);

		// Defines the Action; Due to the way the Create method works, if the date is in the past it will return -1 and not generate an actual action
		return $this-> act-> create($trigger_dt instanceof DateTime? $trigger_dt: new DateTime($trigger_dt), "task", $operation, $task_id, $action_data, $task_data['org_id']);
	}

	/**
	 * Helper function that defines a notification action for a specific user (always by E-mail), and doesn't depend on inform settings
	 * - Used to inform task Creator about certain actions, like task Completed by assigned user/dep
	 *
	 * @param int $task_id
	 * @param string $trigger_dt (Date in ISO format, or "now")
	 * @param string $operation (one of: "reassign", "completion", "closure")
	 * @param int $to_id
	 * @param array $task_data
	 */
	protected function user_Notification($task_id, $trigger_dt, $operation, $to_id, $task_data)
	{
		$action_data = array(
			'type'=> "email",
			'mode'=> "user",
			'to'=> $to_id,
			// Used to identify which user performed an action that is generating a notification
			'by'=> (int)MVC_Core::$ss-> user['id']
		);

		// Defines the Action; Due to the way the Create method works, if the date is in the past it will return -1 and not generate an actual action
		return $this-> act-> create(new DateTime($trigger_dt), "task", $operation, $task_id, $action_data, $task_data['org_id']);
	}

	/**
	 * Creates a task and links it to the specified element
	 */
	public function action_Create(MVC_Request $post)
	{
		$user = MVC_Core::$ss-> user;
		if(!$user)
			return header("HTTP/1.0 403 This function is only available while logged in");

		// Prepare some fields
		$task = $post-> task;
		$task['org_id'] = $this-> user-> is_Unrestricted()? (isset(MVC_Core::$ss-> perspective)? MVC_Core::$ss-> perspective: ($post-> org_id?: MVC_Core::$ss-> user['org_id'])): MVC_Core::$ss-> user['org_id'];
		$task['added_by'] = MVC_Core::$ss-> user['id'];

		// Empty description is possible (as the Title might explain the task sufficiently)
		if(empty($task['description']))
			unset($task['description']);

		// Assignment
		if($task['assign_mode'] == 'self') {
			$task['assign_mode'] = 'user';
			$task['assign_to'] = MVC_Core::$ss-> user['id'];
			$task['assign_name'] = MVC_Core::$ss-> user['name'];
		} elseif(empty($task['assign_to']))
			return header("HTTP/1.0 400 Request did not contain the assignee ID. Try to reload the module and create the task again.");

		$task['status'] = "assigned"; // Tasks always start assigned

		// Notification
		if(!empty($task['deadline'])) {
			$dt_deadline = new DateTime($task['deadline']);
			$task['deadline'] = $dt_deadline-> format("Y-m-d H:i:s");

			// Optional Reminder and Overdue Interval
			if(!empty($task['reminder'])) {
				$dt_reminder = new DateTime($task['reminder']);
				$task['reminder'] = $dt_reminder-> format("Y-m-d H:i:s");
			} else
				unset($task['reminder']);
			$task['ovd_intv'] = !empty($task['ovd_intv'])? (int)$task['ovd_intv']: null;

			// Calculate first overdue notification date from deadline + ovd_intv
			if(!empty($task['ovd_intv'])) {
				$dt_firstovd = clone $dt_deadline;
				$dt_firstovd-> add(new DateInterval("P{$task['ovd_intv']}D"));
			}

			$task['ovd_rep_limit'] = !empty($task['ovd_rep_limit'])? (int)$task['ovd_rep_limit']: null;
		} else
			// No value in deadline? Also clear Reminder, Overdue interval (they don't have any meaning without a deadline)
			unset($task['deadline'], $task['reminder'], $task['ovd_intv'], $task['ovd_rep_limit']);

		switch($post-> link_type) {
			// Task connected to a "Record" ( ex Issue ) - The only available option for now
			case 'record':
				if(empty($post-> link_id))
					return header("HTTP/1.0 400 Request did not contain the ID of the linked element. Possible interface error");

				// Create the task
				$iid = $this-> task-> create($task, 'record', $post-> link_id);
				if(!$iid)
					return header("HTTP/1.0 500 Error - Could not create a record-linked task");

				// (Re)Count number of tasks for this element
				$count_tasks = $this-> task-> count($post-> link_type, $post-> link_id, $task['org_id']);

				// Depending on what is being linked, update counters or statistics field
				$this-> record-> org_UpdateData($post-> link_id, $task['org_id'], array(
					'count_tasks'=> $count_tasks[0],
					'count_active_tasks'=> $count_tasks[1] // For now, recount them every time; In the future after a global counters fix is deployed, just increment ( "`count_tasks` + 1" )
				), true, false); // Don't recheck locator ; String expressions

				$task['link_type'] = $post-> link_type;
				$task['link_id'] = (int)$post-> link_id;
				break;
			default:
				// Create unlinked task - These are only visible in the Task Manager and are not related to a Record
				$iid = $this-> task-> create($task);
				if(!$iid)
					return header("HTTP/1.0 500 Error - Could not create a general task");
		}
		$task['id'] = (int)$iid;

		// Prepare the delayed Actions for notification, when "deadline", "overdue" and "ovd_intv" parameters are present, via the Actions model
		if(isset($dt_deadline)) {
			$actions_update = array('action_stage'=> "prep");
			$actions_update['dl_action_id'] = $this-> assignee_Notification($iid, $dt_deadline, "deadline", $task);

			// The Reminder and Overdue actions are created only if there's a deadline in the first place
			if(isset($dt_reminder))
				$actions_update['rem_action_id'] =  $this-> assignee_Notification($iid, $dt_reminder, "reminder", $task);

			// Overdue Action is a self-repeating Action (every time it triggers, it reinserts itself for a later date, until stopped or reaching limit)
			if(isset($dt_firstovd))
				$actions_update['ovd_action_id'] =  $this-> assignee_Notification($iid, $dt_firstovd, "overdue", $task);

			// Update the task to link the defined actions into it
			$this-> task-> update($iid, $actions_update, 0, null, true);
		}

		switch($task['assign_mode']) {
			// User is assigned
			case 'user':
				// Recount active tasks for the user (not very efficient, as it should only be +1 on assign, -1 on unassign or completion
				// TODO: Resolve this in a more efficient way
				$this-> user-> recount($task['assign_to'], array('tasks'));

				// Initial assign action (will be triggered at next Skynet cycle because the trigger date is set to "now")
				if($task['assign_to'] != $task['added_by'])
					$this-> assignee_Notification($iid, "now", "assign", $task);
				break;
			// Department level assignment (notification is sent to "Chief" only when the action triggers)
			// TODO: Also add "count_tasks" to departments (beside _members, _ctx)
			case 'dep':
				// Always (even if task creator is the department chief)
				$this-> assignee_Notification($iid, "now", "assign", $task);

				if(!empty($task['delegate_id'])) {
					// Notification to delegate
					$this-> user_Notification($iid, "now", "delegate", $task['delegate_id'], $task);
				}
				break;
		}
		$userObj_Create=new App_Model_User();
		$userObj_Create-> update_Session(MVC_Core::$ss-> user['id']);
		$this-> send_JSON(array(
			'success'=> true,
			'response'=> "The task with ID: {$iid} has been defined.",
			'count'=> $count_tasks[0],
			'iid'=> $iid
		));
	}

	/**
	 * Updates a task. Can be linked with an element (For now, just Primary Records)
	 */
	public function action_Update(MVC_Request $post)
	{
		$user = MVC_Core::$ss-> user;
		if(!$user)
			return header("HTTP/1.0 403 This function is only available while logged in");

		if(empty($post-> id))
			return header("HTTP/1.0 400 The task ID is missing from the request");

		// Prepare some fields, cleanup
		$task_update = $post-> task;
		if(array_key_exists('description', $task_update) && empty($task_update['description'])) // Empty string in description results in null value in DB
			$task_update['description'] = null;

		// Assigning to "self" will simply set the values those of the current user session and consider this a normal user assignment
		if(isset($task_update['assign_mode']) && $task_update['assign_mode'] == 'self') {
			$task_update['assign_mode'] = 'user';
			$task_update['assign_to'] = $user['id'];
			$task_update['assign_name'] = $user['name'];
		}

		// Data format conversions
		if(array_key_exists('deadline', $task_update))
			$task_update['deadline'] = !empty($task_update['deadline'])? date("Y-m-d H:i:s", strtotime($task_update['deadline'])): null;
		if(array_key_exists('reminder', $task_update))
			$task_update['reminder'] = !empty($task_update['reminder'])? date("Y-m-d H:i:s", strtotime($task_update['reminder'])): null;
		if(array_key_exists('ovd_intv', $task_update))
			$task_update['ovd_intv'] = !empty($task_update['ovd_intv'])? (int)$task_update['ovd_intv']: null;
		if(array_key_exists('ovd_rep_limit', $task_update))
			$task_update['ovd_rep_limit'] = !empty($task_update['ovd_rep_limit'])? (int)$task_update['ovd_rep_limit']: null;

		// Read the existing task information to compare with the update value.
		// If there are no differences return an error message indicating that nothing was changed.
		// Then, the update itself only needs the differences (which are stored into TSU)
		$task = $this-> task-> read($post-> id, array('task'));

		// If task was previously on "new" and new status is not present or something else than new, switch to "assigned"
		if(!empty($task_update['assign_to']) && (empty($task_update['status']) || $task_update['status'] == 'new') && $task['status'] == 'new')
			$task_update['status'] = 'assigned'; // Automatically switch to assigned if it was previously "new" when an assignment is present.

		// Clear non-different values, with the exception of "assign_to", when "assign_mode" is changed
		// (to resolve conflict when assign_to "dep" can potentially be equal with assign_to "user")
		if(isset($task_update['assign_mode']) && $task_update['assign_mode'] != $task['assign_mode'])
			$skip_assign_to = true;
		// FIXME: The situation where a delegate is previously set for a department and the same user is chosen as delegate for another department in which they are member is not being handled correctly


		foreach($task_update as $key=> $value) {
			if($key=='assign_to' && isset($skip_assign_to))
				continue;

			if($value == $task[$key])
				unset($task_update[$key]);
		}


		if(empty($task_update))
			return header("HTTP/1.0 409 No values were changed. There's nothing to update !");

		// When status is set to Completed, Failed or Closed, all existing Time-based Actions are disabled (no longer sending notifications)
		// - Instead, a notification is generated for the Task Creator
		// - Changing from 'completed' to 'failed', 'closed' has no efefct on Actions
		// Also, the number of active tasks is reduced for the user(s), if the task is in "user" or "group" modes
		if(array_key_exists('status', $task_update)) {
			switch($task_update['status']) {
				// Status that can be set by Creator or Assignee
				// - In all cases, any pending Actions are removed and set as "cancelled" (their link id set to -2)
				case 'completed':
					// Changing status from one of the active ones cancels and archives all Actions
					if(in_array($task['status'], array('assigned', 'inprogress', 'onhold'))) {
						$task_update['action_stage'] = "end";
						if($task['dl_action_id'] > 0) {
							// Deadline action defined and positive (prepared but not sent) - Cancel the action
							$this-> act-> archive($this-> act-> read($task['dl_action_id']), 0, array('near'=> App_Model_Actions::NEAR_END_BEFORE_TRIGGER));
							$task_update['dl_action_id'] = -2;
						}
						if($task['rem_action_id'] > 0) {
							// Deadline action defined and positive (prepared but not sent) - Cancel the action
							$this-> act-> archive($this-> act-> read($task['rem_action_id']), 0, array('near'=> App_Model_Actions::NEAR_END_BEFORE_TRIGGER));
							$task_update['rem_action_id'] = -2;
						}
						if($task['ovd_action_id'] > 0) {
							// Deadline action defined and positive (prepared but not sent) - Cancel the action
							$this-> act-> archive($this-> act-> read($task['ovd_action_id']), 0, array('near'=> App_Model_Actions::NEAR_END_BEFORE_TRIGGER));
							$task_update['ovd_action_id'] = -2;
						}

						if(!empty($task['assign_to']))
							switch($task['assign_mode']) {
								case 'user':
									// Immediate notifications (informing about task completion)
									if($user['id'] == $task['added_by']) {
										// - If set by Creator, a notification is sent to Assignee that the task has been resolved and should no longer be pursued
										$this-> assignee_Notification($task['id'], "now", "completion", $task);
									} elseif($user['id'] == $task['assign_to']) {
										// - If set by Assignee, a notification is sent to Creator, that the Task is finished
										$this-> user_Notification($task['id'], "now", "completion", $task['added_by'], $task);
									} else {
										// - If set by another user with access to tasks, a notification is send to both Creator and Assignee that the task is set as completed (and by who)
										$this-> assignee_Notification($task['id'], "now", "completion", $task);
										$this-> user_Notification($task['id'], "now", "completion", $task['added_by'], $task);
									}
									break;
									// TODO: Handle if task was assigned to Dep
								case 'dep':

									break;
						}

					} else
						// Changing status back to 'completed' from a post-completed one has no effect on Actions, but it does send notification to the Creator if done by another user with access to Tasks
						if(in_array($task['status'], array('failed', 'expired', 'closed'))) {
							if($user['id'] != $task['added_by']) {
								$this-> user_Notification($task['id'], "now", "completion", $task['added_by'], $task);
							}
						}

					break;
					// Status that can only be set by Creator or another user with Task editing abilities (even after "completed" was set)
				case 'failed':
				case 'closed':
					// If Actions still exist on active statuses, they are Archvied and set as "cancelled" (their link id set to -2)
					if(in_array($task['status'], array('assigned', 'inprogress', 'onhold'))) {
						$task_update['action_stage'] = "end";
						if($task['dl_action_id'] > 0) {
							// Deadline action defined and positive (prepared but not sent) - Cancel the action
							$this-> act-> archive($this-> act-> read($task['dl_action_id']), 0, array('near'=> App_Model_Actions::NEAR_END_BEFORE_TRIGGER));
							$task_update['dl_action_id'] = -2;
						}
						if($task['rem_action_id'] > 0) {
							// Deadline action defined and positive (prepared but not sent) - Cancel the action
							$this-> act-> archive($this-> act-> read($task['rem_action_id']), 0, array('near'=> App_Model_Actions::NEAR_END_BEFORE_TRIGGER));
							$task_update['rem_action_id'] = -2;
						}
						if($task['ovd_action_id'] > 0) {
							// Deadline action defined and positive (prepared but not sent) - Cancel the action
							$this-> act-> archive($this-> act-> read($task['ovd_action_id']), 0, array('near'=> App_Model_Actions::NEAR_END_BEFORE_TRIGGER));
							$task_update['ovd_action_id'] = -2;
						}

						// If coming from one of the active statuses, the Assignee receives a notification that the task was set to "Failed" or "Closed"
						if(!empty($task['assign_to']))
							switch($task['assign_mode']) {
								case 'user':
									// Immediate notification to assignee (informing about task being set to failed/closed)
									// Note: Tasks can no longer get the status closed; Will be replaced with something else
// 									if($task_update['status'] == 'closed')
// 										$this-> assignee_Notification($task['id'], "now", "closure", $task);
									if($task_update['status'] == 'failed')
										$this-> assignee_Notification($task['id'], "now", "failure", $task);
									break;
										// TODO: Handle if task was assigned to Dep
								case 'dep':

									break;
						}
					} else
						// Changing from any of the inactive statuses has no effect on Actions
						if(in_array($task['status'], array('completed', 'failed', 'expired', 'closed'))) {
						}

					break;
					// Other manually set statuses are not affecting the Actions
				default:
					// Nothing here.
			}

			// TODO: Review if working properly
			// Check if task status was changed, and if this is a record-linked tasks. In this case, update the record if status was changed to or from "closed" (decrease or increase number of active tasks)
			if($task['link_type'] == 'record') {
				$active_counter_modify = 0;

				$initial_closed = in_array($task['status'], array('completed', 'failed', 'expired', 'closed'));
				$after_closed = in_array($task_update['status'], array('completed', 'failed', 'expired', 'closed'));

				if($initial_closed != $after_closed) {
					// Read current number of tasks for the element
					$count_tasks = $this-> task-> count('record', $task['link_id'], $task['org_id']);

					$this-> record-> org_UpdateData($task['link_id'], $task['org_id'], array(
						'count_active_tasks'=> $count_tasks[1]
					), true, false); // Don't recheck locator ; String expressions
				}
			}
		}

		// Deadline/Reminder and Overdue handling - Change of "deadline", "reminder" *1, "ovd_intv", "ovd_rep_limit", "action_ntf_type" *2 or status set to "completed", "failed" or "closed" will affect the Actions
		// *1 Dates which are set in the past will not create new notifications; If there are any existing notifications, they will be archieved instead
		// *2 A change of "action_ntf_type" will read existing actions yet to be triggered and recreate them on the alternate type
		// NOTE: This might modify or create extra values that will be part of the update
		if(array_key_exists('deadline', $task_update)) {
			// Addition of deadline (was not there before) - Create the relevant Action
			if(!empty($task_update['deadline'])) {
				if(empty($task['deadline'])) {

					// Create the Action for the newly added Deadline
					$task['deadline'] = $task_update['deadline'];
					$task_update['dl_action_id'] = $this-> assignee_Notification($task['id'], $task['deadline'], "deadline", $task);
					// If the return is -1, the trigger date is in the past; set the stage to "dl"
					$task_update['action_stage'] = $task_update['dl_action_id'] == -1? "dl": "prep";
				} else {
					// Change of deadline (was a different date):
					$task['deadline'] = $task_update['deadline'];

					// - Change date for Deadline action, create or update notification Actions
					if(empty($task['dl_action_id'])) {
						// Deadline action doesn't exist yet (probably task defined before the new Actions update) - Create one
						$task_update['action_stage'] = "prep";
						$task_update['dl_action_id'] = $this-> assignee_Notification($task['id'], $task['deadline'], "deadline", $task);
						// If the return is -1, the trigger date is in the past; set the stage to "dl"
						$task_update['action_stage'] = $task_update['dl_action_id'] == -1? "dl": "prep";
					} elseif($task['dl_action_id'] > 0) {
						// Deadline action defined and positive (is ready but not yet sent) - Just update existing action
						$r_upd = $this-> act-> update($task['dl_action_id'], new DateTime($task_update['deadline']));
						// Result of update; If -1 it means the action was not updated, instead it was archived - Set the Task columns accordingly
						if($r_upd === -1) {
							$task_update['dl_action_id'] = -1;
							$task_update['action_stage'] = "dl";
						}
					} else {
						// Deadline action already triggered (negative value) - Create a new action but don't touch "action_stage"
						$task_update['dl_action_id'] = $this-> assignee_Notification($task['id'], $task['deadline'], "deadline", $task);
					}

					// As the Deadline has changed, force overdue "change" calculation even if there was no change in the overdue interval itself
					if(!array_key_exists('ovd_intv', $task_update))
						$task_update['ovd_intv'] = $task['ovd_intv'];
				}
			} else {
				// Removal of deadline (was there before but not anymore) - Delete the relevant action(s)
				if(!empty($task['deadline'])) {
					if($task['dl_action_id'] > 0) {
						// Deadline action defined and positive (prepared but not sent) - Cancel the action
						$this-> act-> delete($task['dl_action_id']);
						$task_update['dl_action_id'] = null;

						// Reminder and Overdue actions also deleted
						if($task['rem_action_id'] > 0) {
							$this-> act-> delete($task['rem_action_id']);
							$task['rem_action_id'] = null;
						}
						if($task['ovd_action_id'] > 0) {
							$this-> act-> delete($task['ovd_action_id']);
							$task['ovd_action_id'] = null;
						}

					} elseif($task['dl_action_id'] < 0) {
						// Deadline action already sent (negative), remove the indicator as a deadline date doesn't exist anymore
						$task_update['dl_action_id'] = null;
					}
					// else: In case no action existed before, no deadline date in current update, nothing to change

					// Also, cancel any pending reminder and overdue actions
					$task_update['rem_action_id'] = null;
					$task_update['ovd_action_id'] = null;
					$task_update['ovd_intv'] = null;
				}
			}
		}

		// Same process as for Deadline, with difference in parameters
		if(array_key_exists('reminder', $task_update)) {
			// "Reminder" action only valid if a deadline is set (reminder cannot be set alone)
			// (Already covered in JavaScript, but just in case that verification is defeated, the reminder date is cleared as well)
			if(empty($task['deadline']))
				$task_update['reminder'] = null;

			if(!empty($task_update['reminder'])) {
				if(empty($task['reminder'])) {
					// Create the Action for the newly added Reminder
					$task['reminder'] = $task_update['reminder'];
					$task_update['rem_action_id'] = $this-> assignee_Notification($task['id'], $task['reminder'], "reminder", $task);
					// If the return is -1, the trigger date is in the past; set the stage to "rem" (But only if not already set to "dl" in previous logic block)
					if(empty($task_update['action_stage']) || $task_update['action_stage'] != 'dl')
						$task_update['action_stage'] = $task_update['rem_action_id'] == -1? "rem": "prep";
				} else {
					// Change of Reminder (was a different date)

					// Reminder action defined and positive (is ready but not yet sent) - Just update existing action
					// (This doesn't have the case of a date existing without matching action, since Reminder date didn't exist before the Actions update)
					if($task['dl_action_id'] > 0) {
						$r_upd = $this-> act-> update($task['rem_action_id'], new DateTime($task_update['reminder']));
						// Result of update; If -1 it means the action was not updated, instead it was archived - Set the Task columns accordingly
						if($r_upd === -1) {
							$task_update['rem_action_id'] = -1;
							if(empty($task_update['action_stage']) || $task_update['action_stage'] != 'dl')
								$task_update['action_stage'] = "rem";
						}
					} else {
						// Reminder action already triggered (negative value) - Create a new action but don't touch "action_stage"
						$task['reminder'] = $task_update['reminder'];
						$task_update['rem_action_id'] = $this-> assignee_Notification($task['id'], $task['reminder'], "reminder", $task);
					}
				}
			} else {
				// Removal of reminder (was there before but not anymore) - Delete the relevant action
				if(!empty($task['reminder'])) {
					if($task['rem_action_id'] > 0) {
						// Reminder action defined and positive (prepared but not sent) - Cancel the action
						$this-> act-> delete($task['rem_action_id']);
						$task_update['rem_action_id'] = null;
					} elseif($task['rem_action_id'] < 0) {
						// Reminder action already sent (negative), remove the indicator as a Reminder date doesn't exist anymore
						$task_update['rem_action_id'] = null;
					}
					// else: In case no action existed before, no reminder date in current update, nothing to change
				}
			}

		}

		// If ovd_intv is changed or if deadline is affected, an update to overdue notification should happen
		if(array_key_exists('ovd_intv', $task_update)) {
			// 1) Overdue action only valid if a deadline is set (reminder cannot be set alone)
			// (Already covered in JavaScript, but just in case that verification is defeated, the reminder date is cleared as well)
			// 2) No Overdue action if status is one of the inactive ones
			if(empty($task['deadline']) || !in_array($task['status'], array('new', 'assigned', 'inprogress', 'onhold')))
				$task_update['ovd_intv'] = null;

			if(!empty($task_update['ovd_intv'])) {
				$dt_overdue = new DateTime($task['deadline']); // Deadline value should have already chenged at previous step
				$dt_overdue-> add(new DateInterval("P{$task_update['ovd_intv']}D"));
				// If dt_overdue lands in the past (because deadline is already passed as well), it sets the overdue to "now" (immediately trigger first overdue notif at next Skynet cycle)
				// - Due to the status-related condition above, it only applies if task is still active
				$now = new DateTime();
				if($dt_overdue < $now)
					$dt_overdue = $now;

				if(empty($task['ovd_intv'])) {
					$task_update['ovd_action_id'] = $this-> assignee_Notification($task['id'], $dt_overdue, "overdue", $task);
				} else {
					$this-> act-> update($task['ovd_action_id'], $dt_overdue, null, null, "updated by Task::action_Update", false); // No need to check/read action, as overdue will always be set in the future
				}
			} else {
				if(!empty($task['ovd_intv'])) {
					// Delete the overdue action
					if($task['ovd_action_id']) {
						$this-> act-> delete($task['ovd_action_id']);
						$task_update['ovd_action_id'] = null;
					}
					// else: Overdue action cannot be -1 (triggered), as when active it always self regenerates until stopped (in which case the ID is set to null)
				}
			}
		}

		// Reassignment (must be intentionally selected, normally the currently assigned user or department isn't selected on editing

		// Switching from one Mode to another? (only has a value when it's different, due to the equality filtering above)
		if(array_key_exists('assign_mode', $task_update)) {
			switch($task_update['assign_mode']) {
				// Changed assignment from Department to User
				case 'user':
					// Send unassign notification for department
					$this-> assignee_Notification($task['id'], "now", "unassign", $task);
					// ... and delegate if one existed
					if(!empty($task['delegate_id'])) {
						$this-> user_Notification($task['id'], "now", "retract", $task['delegate_id'], $task);
						$task_update['delegate_id'] = null;
					}

					// Change the parameters and send assign notification to new user (but not if said user is the editor himself)
					if($task_update['assign_to'] != $user['id']) {
						$task['assign_mode'] = 'user';
						$task['assign_to'] = $task_update['assign_to'];
						$this-> assignee_Notification($task['id'], "now", "assign", $task);
					}

					break;
				// Changed assignment from User to Department
				case 'dep':
					// Send unassign notification to user, but only if not the current editor
					if($task['assign_to'] != $user['id']) {
						$this-> assignee_Notification($task['id'], "now", "unassign", $task);
					}

					// Change parameters and send assign notification for dep
					$task['assign_mode'] = 'dep';
					$task['assign_to'] = $task_update['assign_to'];
					$this-> assignee_Notification($task['id'], "now", "assign", $task);

					// ... and notification to delegate if one was specified
					if(array_key_exists('delegate_id', $task_update)) {
						$task['delegate_id'] = $task_update['delegate_id'];
						$this-> user_Notification($task['id'], "now", "delegate", $task_update['delegate_id'], $task);
					}
					break;
			}
			$reassigned = true;

		} elseif(array_key_exists('assign_to', $task_update)) {	// Or reassigning within the existing mode
			// What is the existing mode?
			switch($task['assign_mode']) {
				// User change - Send "unassign" notification to existing user, and "assign" to new user
				case 'user':
					// Check if the previously assigned user wasn't already the editor, if not, send "unassign" notification to previous user
					if($task['assign_to'] != $user['id']) {
						$this-> assignee_Notification($task['id'], "now", "unassign", $task);
					} // else - no unassign notifications to the editor

					// ... but do send assign notification to new user, if the new user is not the editor
					if($task_update['assign_to'] != $user['id']) {
						$task['assign_to'] = $task_update['assign_to'];
						$this-> assignee_Notification($task['id'], "now", "assign", $task);
					}
					break;
				// Reassignment Department to Department - Notifications will be sent to respective department chief(s)
				case 'dep':
					// Unassign notification for department (and delegation retraction if one existed)
					$this-> assignee_Notification($task['id'], "now", "unassign", $task);
					// When changing department, a previous delegate (if existing) will automatically get retracted
					if(!empty($task['delegate_id'])) {
						$this-> user_Notification($task['id'], "now", "retract", $task['delegate_id'], $task);
					}

					// Update assign_to value for Notificaiton
					$task['assign_to'] = $task_update['assign_to'];
					$this-> assignee_Notification($task['id'], "now", "assign", $task);

					// Notification to new delegate (if one specified)
					if(array_key_exists('delegate_id', $task_update)) {
						if($task_update['delegate_id'] > 0) {
							// New delegate
							$task['delegate_id'] = $task_update['delegate_id'];
							$this-> user_Notification($task['id'], "now", "delegate", $task_update['delegate_id'], $task);
						} else {
							// ... or delegate cleared, no new notification
							$task_update['delegate_id'] = null;
						}
					}
				break;
			}

			$reassigned = true;
		} elseif(array_key_exists('delegate_id', $task_update)) {
			// Change or addition of delegate without department change
			// -- This operation can be done by the delegate as well (at which point they will lose the ability to make edits)

			// Delegate changed or removed, send "retract" notification to previous delegate
			if(!empty($task['delegate_id'])) {
				$this-> user_Notification($task['id'], "now", "retract", $task['delegate_id'], $task);
			}

			if($task_update['delegate_id'] > 0) {
				// New delegate
				$task['delegate_id'] = $task_update['delegate_id'];
				$this-> user_Notification($task['id'], "now", "delegate", $task_update['delegate_id'], $task);
			} else {
				// ... or existing one just cleared, no new notification
				$task_update['delegate_id'] = null;
			}
		}

		// Move any future notifications (rem, dl, ovd) to the new ID (update data_json);
		// -- Note: They might have been created or already changed in previous steps related to dl/rem, ovd_intv and status
		// -- These updates only change action data, but do not affect trigger dates
		if(!empty($reassigned)) {
			// Read existing actions (if they exists), update
			if($task['dl_action_id'] > 0) {
				$ar = $this-> act-> read($task['dl_action_id']);

				if(!empty($ar)) {
					$action_data = json_decode($ar['data_json'], true);
					$action_data['to'] = $task_update['assign_to'];
					if(isset($task_update['assign_mode']))
						$action_data['mode'] = $task_update['assign_mode'];
					$this-> act-> update($task['dl_action_id'], null, null, $action_data, null, false); // False = Do not re-read the action, as this is a simple data update
				}
				unset($action_data, $ar);
			}
			if($task['rem_action_id'] > 0) {
				$ar = $this-> act-> read($task['rem_action_id']);
				if(!empty($ar)) {
					$action_data = json_decode($ar['data_json'], true);
					$action_data['to'] = $task_update['assign_to'];
					if(isset($task_update['assign_mode']))
						$action_data['mode'] = $task_update['assign_mode'];
					$this-> act-> update($task['rem_action_id'], null, null, $action_data, null, false);
				}
				unset($action_data, $ar);
			}

			if($task['ovd_action_id'] > 0) {
				$ar = $this-> act-> read($task['ovd_action_id']);
				if(!empty($ar)) {
					$action_data = json_decode($ar['data_json'], true);
					$action_data['to'] = $task_update['assign_to'];
					if(isset($task_update['assign_mode']))
						$action_data['mode'] = $task_update['assign_mode'];
					$this-> act-> update($task['ovd_action_id'], null, null, $action_data, null, false);
				}
				unset($action_data, $ar);
			}
		}

		$r = $this-> task-> update($post-> id, $task_update, $user['id'], $post-> comment);
		if(!$r)
			return header("HTTP/1.0 500 Error - Could not update the task");

		// Recount active tasks for user/dep (after update)
		if(!empty($reassigned)) {
			switch($task['assign_mode']) { // This should be the new value (if changed)
				case 'user':
					$this-> user-> recount($task_update['assign_to'], array('tasks'));
					break;
				case 'dep':
					// TODO: Implement task count for departments
					break;
			}
		}
		$userObj=new App_Model_User();
		$userObj-> update_Session(MVC_Core::$ss-> user['id']); 
		$this-> send_JSON(array(
			'success'=> true,
			'response'=> "The task has been updated."//,
// 			'count'=> !empty($count_tasks[1])? (int)$count_tasks[1]: null
		));
	}

	/**
	 * Deletes a Task and linked Actions; Updates counters
	 *
	 * @param MVC_Request $post
	 */
	public function action_Remove(MVC_Request $post)
	{
		if(empty(MVC_Core::$ss-> user))
			return header("HTTP/1.0 403 This method is only available while logged in");

		if(empty($post-> id))
			return header("HTTP/1.0 400 Task identifier is missing from the request");

		// Read the task. Required to check security and also determine for which organization the task counter is reduced.
		$task = $this-> task-> read($post-> id);
		if(!$task)
			return header("HTTP/1.0 404 The task could not be located. Might have already been deleted");

		// Check if the task that was read actually belongs to the linked element
		if(!empty($task['link_type']) && ($task['link_type'] != $post-> link_type || $task['link_id'] != $post-> link_id))
			return header("HTTP/1.0 403 The task doesn't belong to the specified element");

		$r = $this-> task-> delete($post-> id);
		if(!$r)
			return header("HTTP/1.0 500 Task deletion failed. Possible database error.");

		// Remove any Actions and actions Archive that were connected to the task
		$this-> act-> cleanup_Collection('task', $post-> id);

		$result = array('success'=> true, "response"=> "The task has been removed.");
		// Depending on what is being linked, update counters or statistics field - But only if there's no error in the counting of tasks
		$count_tasks = $this-> task-> count($post-> link_type, $post-> link_id, $task['org_id']);

		if($post-> link_type == "record" && is_array($count_tasks)) {
			$this-> record-> org_UpdateData($post-> link_id, $task['org_id'], array(
				'count_tasks'=> $count_tasks[0],
				'count_active_tasks'=> $count_tasks[1]
			), true); // Don't recheck locator

			if($task['org_id'] == $this-> user-> is_Unrestricted()? (isset(MVC_Core::$ss-> perspective)? MVC_Core::$ss-> perspective: $post-> org_id): MVC_Core::$ss-> user['org_id']) {
				$result['update_count'] = true;
				$result['count'] = $count_tasks[0];
			}
		}

		if($task['assign_mode'] == 'user' && !empty($task['assign_to'])) {
			// Count all tasks assigned to the user with a "status" different from "closed"
			$this-> user-> recount($task['assign_to'], array('tasks'));
		}

		$this-> send_JSON($result);
	}


	/**
	 * Reads the tasks associated to an item, filtering the result by what the logged in user should see
	 * @param $rq-> virtualmode If "true", the function operates for the "virtual mode" grid ( paging, sorting, filtering is done on server side, in this function )
	 * -- Otherwise, the method just returns all the rows valid under specified conditions and doesn't receive another request
	 */
	public function action_Get_List(MVC_Request $rq)
	{
		if(!MVC_Core::$ss-> user)
			return header("HTTP/1.0 403 This function is only available while logged in");

		$rq-> add_Filter("virtualmode", "boolean");
		if($rq-> virtualmode) {
			$count_rows = $rq-> pagesize;
			$start_row = $rq-> pagenum * $rq-> pagesize;
		} else {
			$count_rows = 1000; // Hard limit on returned rows when working in client-side filter - TODO: Send a warning to the user that there are too many records and they can't all be loaded
			$start_row = 0;
		}

		// Requested sort order?
		if($rq-> sortdatafield && $rq-> sortorder) {
			// Sorting on one of the attached tables
			switch($rq-> sortdatafield) {
				// Basic task fields
				case 'creator_name':
					$ss_sort = "`usr`.name "; break;
				case 'creator_email':
					$ss_sort = "`usr`.email "; break;
				// Linked record fields
				case 'rec_track':
					$ss_sort = "`iss`.track "; break;
				case 'rec_primary':
					$ss_sort = "`iss`.cache_primary "; break;
				case 'rec_secondary':
					$ss_sort = "`iss`.cache_secondary "; break;
				case 'rt_name':
					$ss_sort = "`ist`.name "; break;
				case 'group_name':
					$ss_sort = "`grp`.name "; break;
				// Others (assigned, date, etc.) - Put NULLS last while sorting
				default:
					if($rq-> sortorder == 'desc')
						$ss_sort = "IF(ISNULL(`tsk`.{$rq-> sortdatafield}),0,1), `tsk`.{$rq-> sortdatafield} ";
					else
						$ss_sort = "IF(ISNULL(`tsk`.{$rq-> sortdatafield}),1,0), `tsk`.{$rq-> sortdatafield} ";
			}
			// 	'assignee_name'
			// 		???; // Not possible to sort or filter by assignee because it can be either a user or a department; FIXME - Find a way ( perhaps cached copy of the assignee name/title/etc. in the task itself )

			$ss_sort.= $rq-> sortorder;
		}

		// Set some defaults
		if(!isset($ss_sort))
			$ss_sort = "`tsk`.deadline ASC";  // default sorting by deadline, ascending

		$rq-> add_Filter("nodescr", "boolean");
		if($rq-> nodescr)
			$wtr = array('tasklimited', 'creator', 'delegate'); // 'assigned' - Not necessary, already part of main table (as a cache field)
		else
			$wtr = array('task', 'creator', 'delegate');

		if($rq-> link_type == "record")
			$wtr[] = "record";

		if($rq-> read_org == "true")
			$wtr[] = "org";

		// Server-side Filters (for full grid)
		list($ss_filters, $filter_callback) = self::helper_Grid_Filters($rq);

		// Reading/Filtering part itself is universal
		$user = &$this-> user;
		$tasks = $this-> task-> get_List($wtr, function(QueryFactory &$q) use ($rq, $user, $ss_filters, $filter_callback) {

			// Filter for task linked to items
			if($rq-> link_type && $rq-> link_id) {
				$q
				-> where("`tsk`.link_type = ?", $rq-> link_type)
				-> where("`tsk`.link_id = ?", $rq-> link_id);
			}

			// Filters for non-admin user (can only see own organization tasks, and subsequent rights apply)
			if(!$user-> is_Unrestricted()) {
				$q-> where("`tsk`.org_id = ?", MVC_Core::$ss-> user['org_id']);

				if($user-> test_ACL('privileges', 'tasks_access_org') || ($rq-> link_type && $user-> test_ACL('privileges', 'tasks_access'))) {
					// 1) User can access all organization tasks
					// 2) Access to all tasks linked to a record
					// -- Unless "tasks_manager" is also given, the non-assigned tasks will be read-only view
					// -- Nothing to do here, org filter is already sufficient
				} else {
					// When user can't access all tasks (or all linked tasks), he can always access the ones he's assigned to (User and Department)
					$q-> where("CASE WHEN `tsk`.assign_mode = 'user' THEN `tsk`.assign_to = ? ELSE 1 END", MVC_Core::$ss-> user['id']);
					$q-> where("CASE WHEN `tsk`.assign_mode = 'dep' THEN `tsk`.assign_to = ? ELSE 1 END", MVC_Core::$ss-> user['cdep_id']);
				}
			}

			// Filter for task overview (user, dep, org)
			if($rq-> class_filter) {
				switch($rq-> class_filter['mode']) {
					// Tasks assigned to the logged in user or another user
					case 'self':
						// Normal user is already restricted by the conditions above. Use this only for admin to show tasks for himself.
						if($user-> is_Unrestricted())
							$q-> where("`tsk`.assign_mode = 'user' AND `tsk`.assign_to = ?", MVC_Core::$ss-> user['id']);
							break;
					case 'user':
						$q-> where("`tsk`.assign_mode = 'user' AND `tsk`.assign_to = ?", empty($rq-> class_filter['id'])? MVC_Core::$ss-> user['id']: (int)$rq-> class_filter['id']);
						break;
						// Tasks assigned to a department
					case 'dep':
						$q-> where("`tsk`.assign_mode = 'dep' AND `tsk`.assign_to = ?", empty($rq-> class_filter['id'])? MVC_Core::$ss-> user['cdep_id']: (int)$rq-> class_filter['id']);
						break;
						// Tasks that belong to an organization
					case 'org':
						$q-> where("`tsk`.org_id = ?", empty($rq-> class_filter['id'])? MVC_Core::$ss-> user['org_id']: (int)$rq-> class_filter['id']);
						break;
				}
			}

			// Virtual mode jqxGrid filters
			if($rq-> virtualmode == "true") {
				foreach($ss_filters as $fkey=> $filter_set) {
					// Which table and database field is used for the filter column ?
					switch($fkey) {
						case 'creator_name':
							$dbfield = "`usr`.name"; break;
						case 'creator_email':
							$dbfield = "`usr`.email"; break;
							// Linked record fields
						case 'rec_track':
							$dbfield = "`iss`.track"; break;
						case 'rec_primary':
							$dbfield = "`iss`.cache_primary"; break;
						case 'rec_secondary':
							$dbfield = "`iss`.cache_secondary"; break;
						case 'rt_name':
							$dbfield = "`ist`.name"; break;
						case 'group_name':
							$dbfield = "`grp`.name"; break;
						default:
							$dbfield = "`tsk`.{$fkey}";
					}
					$filter_callback($q, $filter_set, $dbfield);
				}
			}

			// Extra filter "show_closed" - It must be "true" to show tasks with status "closed" (which are hidden by default)
			if(!$rq-> show_closed || $rq-> show_closed != 'true')
				$q-> where("`tsk`.status != 'closed'");

		}, null, $ss_sort, $count_rows, $start_row, $rq-> virtualmode); // virtualmode = true: Calculate FOUND_ROWS();

		if(!$tasks)
			return $this-> send_JSON(array());

		if($rq-> virtualmode)
			$this-> send_JSON(array(
				'found_rows'=> $this-> task-> found_rows,
				'data'=> $tasks
			));
		else
			$this-> send_JSON($tasks);
	}

	/**
	 * Notification system - Create "task_{operation}" notification in relation to a new or modified task
	 *
	 * @param int $task_id Issue type ID
	 * @param int $issue_id Issue within the specified type
	 * @param int $org_id Organization for which users are searched
	 */

	/**
	 * Notification system - Create "task_{operation}" notification in relation to a new or modified task
	 *
	 * @param int $org_id Organization for which users are checked
	 * @param string $ntf_type What type of notification?
	 * @param int $task Task data (New, existing - Necessary to build the notificationa and it's message)
	 */
	private function send_Task_NTF($org_id, $ntf_type, $task)
	{
		switch($ntf_type) {
			// Task is assigned to user - notify that particular user about it
			case 'assigned':
				$opts = $this-> evan-> check_User_Subscription($task['assign_to'], "task_assigned");
				if(!$opts || !$opts['active'])
					return false; // Nothing to do, user is not subscribed or subscription is not active

				$xdata = array(
					'deadline'=> $task['deadline'],
					'link_id'=> isset($task['link_id'])? $task['link_id']: null,
					'progress'=> isset($task['progress'])? $task['progress']: null
				);

				$ntf_iid = $this-> evan-> create($task['assign_to'], 'task', 'assigned', $task['title'],
					$task['id'], $task['added_by'], null,
					$xdata, $task['description'], $opts['instant']);

				if(empty($ntf_iid))
					return MVC_Core::$debug-> dump("Notification could not be created", __METHOD__, null, "error");

				if($opts['email'])
					$this-> evan-> generate_Message($ntf_iid, true); // Send standard notification e-mail

				break;
			// Task is assigned to a department - notify all users from that department who have subscribed to this kind of notification
			case 'assigdep':
				return false;
			// Task is no longer assigned to an user (or department - Check HOW?) - Notify the user(s) about it
			case 'unassigned':
				$opts = $this-> evan-> check_User_Subscription($task['assign_to'], "task_unassigned");
				if(!$opts || !$opts['active'])
					return false; // Nothing to do, user is not subscribed or subscription is not active

				$xdata = array(
					'link_id'=> isset($task['link_id'])? $task['link_id']: null,
					'progress'=> $task['progress']
				);

				$ntf_iid = $this-> evan-> create($task['assign_to'], 'task', 'unassigned', $task['title'],
					$task['id'], $task['added_by'], null,
					$xdata, $task['description'], $opts['instant']);

				if(empty($ntf_iid))
					return MVC_Core::$debug-> dump("Notification could not be created", __METHOD__, null, "error");

				if($opts['email'])
					$this-> evan-> generate_Message($ntf_iid, true); // Send standard notification e-mail

				return false;
			case 'stop':
				return false;
			case 'deleted':
				return false;
			default:
				return MVC_Core::$debug-> dump("Invalid notification type: {$ntf_type}", __METHOD__, null, "error");
		}

		return true;
	}

	/**
	 * Reads the task update history for one task
	 *
	 * @param MVC_Request $rq
	 */
	public function action_Get_Updates($rq)
	{
		if(!MVC_Core::$ss-> user)
			return header("HTTP/1.0 403 This function is only available while logged in");

		$r = $this-> task-> get_History_List($rq-> task_id);
		if(!$r)
			$this-> send_JSON(array());

		$updates = array();
		foreach($r as $row) {
			if(!empty($row['update_data'])) {
				$update_data = json_decode($row['update_data'], true);
				$row['update_text'] = '';

				// This should only happen once, when created
				if(array_key_exists("created", $update_data)) {
					$row['update_text'].= sprintf("Created with Title: %s", $update_data['created']);
				}

				// Analyze the update data and compose the update string
				if(array_key_exists("progress", $update_data)) {
					$row['update_text'].=(!empty($row['update_text'])?'; ':'').sprintf("Progress set to: %d%%", $update_data['progress']);
				}

				// Assignment/reassignment
				if(array_key_exists("assign_to", $update_data)) {
					if(isset($update_data['assign_mode'])) {
						if($update_data['assign_mode'] == 'user') {
							$update_tpl = "Assigned to user: %s";
						} else {
							$update_tpl = "Assigned to department: %s";
						}
					} else
						$update_tpl = "Reassigned to: %s";
					$row['update_text'].=(!empty($row['update_text'])?'; ':'').sprintf($update_tpl, $update_data['assign_name']);
				}

				// Department mode - Delegate change
				if(array_key_exists("delegate_id", $update_data)) {
					if($update_data['delegate_id'] > 0) {
						// Actual delegate, read user with id
						$m_user = new App_Model_User();
						$delegate = $m_user-> read($update_data['delegate_id'], array('user_comm'), false);
						$row['update_text'].=(!empty($row['update_text'])?'; ':'').sprintf("New department delegate: %s", $delegate['name']);
					} else {
						// Delegate removed
						$row['update_text'].=(!empty($row['update_text'])?'; ':'')."Department delegation retracted";
					}
				}

				// Title and Description update (description is being cut while updating)
				if(array_key_exists("title", $update_data)) {
					$row['update_text'].=(!empty($row['update_text'])?'; ':'').sprintf("New title: %s", $update_data['title']);
				}
				if(array_key_exists("description", $update_data))
					$row['update_text'].=(!empty($row['update_text'])?'; ':'').(strlen($update_data['description'])? "New task description set": "Description cleared");

				// Deadline, Reminder
				if(array_key_exists("deadline", $update_data)) {
					if(!empty($update_data['deadline'])) {
						$dt_dl = new DateTime($update_data['deadline']);
						$row['update_text'].=(!empty($row['update_text'])?'; ':'').sprintf("Due date set: %s", $dt_dl-> format("Y-m-d"));
					} else
						$row['update_text'].=(!empty($row['update_text'])?'; ':'')."Due date cleared";
				}
				if(array_key_exists("reminder", $update_data)) {
					if(!empty($update_data['reminder'])) {
						$dt_rem = new DateTime($update_data['reminder']);
						$row['update_text'].=(!empty($row['update_text'])?'; ':'').sprintf("Reminder set: %s", $dt_rem-> format("Y-m-d"));
					} else
						$row['update_text'].=(!empty($row['update_text'])?'; ':'')."Reminder cleared";
				}

				if(array_key_exists("ovd_intv", $update_data)) {
					if(!empty($update_data['ovd_intv']))
						$row['update_text'].=(!empty($row['update_text'])?'; ':'').sprintf("Overdue notification interval set to %d days", $update_data['ovd_intv']);
					else
						$row['update_text'].=(!empty($row['update_text'])?'; ':'')."Overdue notification interval cleared";
				}
				if(array_key_exists("ovd_rep_limit", $update_data)) {
					if(!empty($update_data['ovd_rep_limit']))
						$row['update_text'].=(!empty($row['update_text'])?'; ':'').sprintf("Overdue notification repetition limit set: %d", $update_data['ovd_rep_limit']);
					else
						$row['update_text'].=(!empty($row['update_text'])?'; ':'')."Overdue notification repetition limit cleared";
				}
			}
			unset($row['task_id'], $row['update_by'], $row['update_data']);

			$updates[] = $row;
		}
		$this-> send_JSON($updates);
	}

}