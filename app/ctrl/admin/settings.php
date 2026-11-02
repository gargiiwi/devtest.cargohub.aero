<?php
/**
 * System Settings (Global system settings)
 *
 * @author Wavetrex (wavetrex@gmail.com)
 * @package CargoITS
 * @version 1.0.dev
 */
class App_Ctrl_Admin_Settings extends App_Ctrl_Base
{
	/**
	 * Extra database connection to the "Live" version ( a copy actually )
	 * - Used as source for the conversion procedure between current database on LIVE and the extended one on DEV
	 *
	 * @var MVC_Database
	 */
// 	private $ldb;

	/**
	 * Makes initializations useful for any action
	 */
	public function __construct()
	{
		parent::__construct();

// 		$ldb_cfg = MVC_Core::$settings['database'];
// 		$ldb_cfg['name'] = "cargohub_dolly";
// 		$this-> ldb = new MVC_Database($ldb_cfg);

		// Admin-only module, rediorect to home if no access
		if(!isset(MVC_Core::$ss-> user['is_admin']))
			MVC_Core::shutdown("/");
	}

	/**
	 * Index action for the Global Settings page
	 */
	public function action_Index()
	{
		// prepare output
		$this-> prepare_Header("How to train your octopus");

		$this-> module = array(
			'title'=> "Nothing to see here, move along...",
			'description'=> "This module doesn't exist, you are just dreaming"
		);

		// which body template to use, and run the view
		$this-> site_section = "admin". DS. "settings.html";
		$this-> view("private.html");
		// LINK: http://dev.cargoclaims.nl/admin/settings - Admin can login and run Steps

		// LOGIN: batman / g4eU|N55
	}

	/**
	 * Reads an SQL file from /db and concatenates rows until one with ; at the end is found.
	 * Returns an array of queries.
	 * @param string $name
	 */
	private function read_SQL($name)
	{
		$sqla = @file(ROOT. DS. 'db'. DS. $name. ".sql", FILE_SKIP_EMPTY_LINES);
		if($sqla === false)
			return false;

		$queries = array(); $query = "";
		foreach($sqla as $line) {
			$trim_line = trim($line);
			if(substr($trim_line, 0, 2) == "--")
				continue; // Ignore comment lines

			$query.= $line;
			if(substr($trim_line, -1) == ";") {
				// If last character is ; the query is finished, save it
				$queries[] = trim($query);
				$query = "";
			}
		}

		return $queries;
	}

	/**
	 * Upgrade 0.9g to 1.0a
	 * -- Step 1. Copy existing tables (from $this-> ldb to MVC_Core::$db) ; Insert new table fields, Create new tables related to communication
	 */
	public function action_CommPreConvert()
	{
		$queries = $this-> read_SQL("db_import_09_10_step1");

		$count_success = 0;
		foreach($queries as $query) {
			$r = MVC_Core::$db-> query($query);
			if($r)
				$count_success++;
		}

		if($count_success == count($queries))
			echo "Step 1 Finished";
		else
			echo "Some queries failed !";
	}

	/**
	 * Upgrade 0.9g to 1.0a
	 * -- Step 2. System users receive contacts, link contacts to users
	 * Creates "user_self" contact entries for all users in the system
	 * -- Reads all users in the system and, if exists, associated contact
	 * -- Checks if the associated contact has the correct name/email, if not update it
	 * -- If there is no associated contact, create one and update the user record
	 */
	public function action_UsersContacts()
	{
		// Fix the admin user contact.
		MVC_Core::$db-> query("UPDATE `cargohub_its`.`comm_contacts` SET  `email` =  'support@cargohub.nl' WHERE  `comm_contacts`.`id` =2");

		$m_con = new App_Model_Contact();

		$all_users = $this-> user-> get_List(array('user', 'organization'), null, null, null, 0);
		foreach($all_users as $user) {
			if(isset($user['contact_id'])) {
				// A joined contact appears to exist, read it
				$contact = $m_con-> read($user['contact_id']);

				// Check match against user data
				if($contact['name'] != $user['name'] || $contact['email'] != $user['email'] || !isset($contact['xdata']['company_name']) || $contact['xdata']['company_name'] != $user['organization']['name']) {
					if(empty($contact['xdata']))
						$contact['xdata'] = array();
					$contact['xdata']['company_name'] = $user['organization']['name'];
					// update contact
					$m_con-> update($user['contact_id'], array(
						'email'=> $user['email'],
						'name'=> $user['name'],
						'xdata'=> $contact['xdata']
					));
				}
			} else {
				// There is no matching contact, create it
				$iid = $m_con-> create("user_self", $user['org_id'], array(
					'email'=> $user['email'],
					'name'=> $user['name'],
					'xdata'=> array(
						'company_name'=> $user['organization']['name']
					)
				), $user['id']);

				// ... and connect the user
				$this-> user-> update($user['id'], array(
					'contact_id'=> $iid,
					'count_ctx'=> 1
				));
			}
		}

		echo "Step 2 Finished";
	}

	/**
	 * Helper function that attempts to locate a contact or create one based on the xfields from an issue
	 * -- Used when there is no associated message and the only source of information for the contact is the issue data itself
	 *
	 * @param array $iss_xfields
	 */
	private function contact_From_Issue(App_Model_Contact &$m_con, $iss_xfields, $iss_org_id, $update_count = false)
	{
		$contact = $m_con-> locate("isspost", $iss_xfields['e_mail'], $iss_xfields['name'], $iss_org_id, null, null, false, true);

		if(!$contact) {
			// Not found, attempt to create it
			$contact = array(
				'email'=> $iss_xfields['e_mail'],
				'name'=> $iss_xfields['name'],
				'xdata'=> array(
					'phone'=> isset($iss_xfields['phone'])? $iss_xfields['phone']: null,
					'company_name'=> isset($iss_xfields['comp_name'])? $iss_xfields['comp_name']: null
				),
				'count_msgs'=> (int)$update_count
			);

			$con_iid = $m_con-> create("isspost", $iss_org_id, $contact);

			$contact['org_id'] = $iss_org_id;
			$contact['type'] = "isspost";
			$contact['id'] = $con_iid;
		} else {
			if($update_count)
				// And update the counter for that contact
				$m_con-> update($contact['id'], array(
					'count_msgs'=> $contact['count_msgs'] + 1
				));
		}

		return $contact;
	}

	/**
	 * Upgrade 0.9g to 1.0a
	 * -- Step 3. Prepare the messages table, so they can be inserted into conversations
	 * Converts existing message data from existing format (mostly user to user) to contact-to-contact messages
	 * -- For user to user messages it conects messages to the freshly created contacts
	 * -- For messages from outside the system ( isspost ) it searches the contacts for the specified e-mail
	 *    - If found, uses that contact
	 *    - If not, creates a contact and then updates the message
	 * -- Fixes missing "org_id" on certain messages, by setting the "org_id" of the linked issue
	 *
	 * Note: There is no "update" function in the model, this method has to do it directly
	 *
	 * > After the data upgrade, the communication model code needs to be changed, as fields represent a different thing
	 * > "email" column can be removed from messages after all upgrade is complete, it will be redundant as "from_id" will always contain a contact ID (which has an associated e-mail)
	 * > "connect_id" will become "cnv_id" after all messages have been placed into conversations.
	 */
	public function action_PrepareMessages()
	{
		$m_comm = new App_Model_Comm();
		$m_issue = new App_Model_Issue();
		$m_con = new App_Model_Contact();

		$messages = $m_comm-> get_List(array('meta'), function(QueryFactory $q) {
			$q
			-> where("`converted` = 0")
			-> where("`source` IN(?)", array("isspost", "issreply", "system", "user")); // "isspost", "issreply", "system", "user"
		}, null, "`dt` ASC", 1000);

		if(!$messages) {
			echo "Nothing to convert !";
			return;
		}

		foreach($messages as $msg) {

			switch($msg['source']) {
				case 'isspost':
					// Initial issue message (generated when issue was created)

					// Read the issue to which this message is linked to get xdata (contains fields: name, e_mail - used to search for/create the associated contact)
 					$iss = $m_issue-> read((int)$msg['connect_id'], array("issue", "xfields"));

 					if(!$iss || !isset($iss['xfields']['e_mail'])) {
 						// If the associated issue is missing, or there is no e_mail field in the issue, set the message as lost/orphaned
 						$q = QueryFactory::update()
 						-> table(MVC_Core::$db-> tab['msg'])
 						-> where("`id` = ?", $msg['id'])
 						-> values(array(
							'from_id'=> 0,
							'converted'=> 1
 						));
 						MVC_Core::$db-> query($q);

 						continue;
 					}

 					// Attempt to discover if a contact of types "user_self" or "isspost" exists with the e-mail specified in the issue xfields
					$con_user = $m_con-> locate("user_self", $iss['xfields']['e_mail'], null, null, null, null, false, true);
// 					dd($con_user);

					if(is_array($con_user)) {
						// Found this contact in the application's users:
						// -- Connect the message to the user (from_id = contact id, owner_id = user id)
						// -- Set "org_id" to the user's organization
						$q = QueryFactory::update()
						-> table(MVC_Core::$db-> tab['msg'])
						-> where("`id` = ?", $msg['id'])
						-> values(array(
							'from_id'=> $con_user['id'],
							'owner_id'=> $con_user['user_id'],
							'org_id'=> $con_user['org_id'],
							'converted'=> 1
						));
						MVC_Core::$db-> query($q);

						// And update the counter for that contact
						$m_con-> update($con_user['id'], array(
							'count_msgs'=> $con_user['count_msgs'] + 1
						));

					} else {
						// Not found in users, attempt to find in "isspost" contacts for the same organization the issue belongs to
						// - Note, at the beginning of conversion there is none, they get created when parsing all messages
						$con_subm = $m_con-> locate("isspost", $iss['xfields']['e_mail'], $iss['xfields']['name'], $iss['org_id'], null, null, false, true);
// 						dd($con_subm);

						if(is_array($con_subm)) {
							// Found this contact in the issue-related contacts (external submitters of issues):
							// -- Connect the message to the contact (from_id = contact id)
							//    Set "org_id" to the same org as the issue this message is for (doesn't matter if it was already set)
							$q = QueryFactory::update()
							-> table(MVC_Core::$db-> tab['msg'])
							-> where("`id` = ?", $msg['id'])
							-> values(array(
								'from_id'=> $con_subm['id'],
								'org_id'=> $iss['org_id'],
								'converted'=> 1
							));
							MVC_Core::$db-> query($q);

							// -- In case the contact doesn't have the xdata entries "phone", "company_name", update the contact to the content the fields in the issue
							//    This is useful if first detected post didn't have the form properly completed, but one of next ones do.
							$update_contact_xdata = false;
							if(empty($con_subm['xdata']['phone']) && !empty($iss['xfields']['phone'])) {
								$con_subm['xdata']['phone'] = $iss['xfields']['phone'];
								$update_contact_xdata = true;
							}
							if(empty($con_subm['xdata']['company_name']) && !empty($iss['xfields']['comp_name'])) {
								$con_subm['xdata']['company_name'] = $iss['xfields']['comp_name'];
								$update_contact_xdata = true;
							}
							// -- Increase "count_msgs" for the contact by 1
							$new_contact_data = array(
								'count_msgs'=> $con_subm['count_msgs'] + 1
							);
							if($update_contact_xdata)
								$new_contact_data['xdata'] = $con_subm['xdata'];

							$m_con-> update($con_subm['id'], $new_contact_data);

							// Update issue (set number of contacts to 1), Also the "submitted_by" field
							$m_issue-> update($iss['id'], array(
								'submitted_by'=> $con_subm['id']
							), null, false, true);
						} else {
							// Not found, must create "isspost" contact
							// -- Create contact by using the xfields from the issue, org_id as the issue's org_id, user_id = null, and xdata from issue xfields (phone => phone, comp_name => company_name), count_msgs = 1
							$iid = $m_con-> create("isspost", $iss['org_id'], array(
								'email'=> $iss['xfields']['e_mail'],
								'name'=> $iss['xfields']['name'],
								'xdata'=> array(
									'phone'=> isset($iss['xfields']['phone'])? $iss['xfields']['phone']: null,
									'company_name'=> isset($iss['xfields']['comp_name'])? $iss['xfields']['comp_name']: null
								),
								'count_msgs'=> 1
							));

							// -- Connect the message to the newly created contact (from_id = contact id), also set "org_id" to the issue's org_id
							$q = QueryFactory::update()
							-> table(MVC_Core::$db-> tab['msg'])
							-> where("`id` = ?", $msg['id'])
							-> values(array(
								'from_id'=> $iid,
								'org_id'=> $iss['org_id'],
								'converted'=> 1
							));
							MVC_Core::$db-> query($q);

							// Update issue (set number of contacts to 1), Also the "submitted_by" field
							$m_issue-> update($iss['id'], array(
								'submitted_by'=> $iid
							), null, false, true);

						}
					}
					break;

				case 'issreply':
					// Reply to an issue message - Might be from staff or from external user
					// Due to the way the system worked until now, all "external" messages come from the same contact (in the future it will be possible for multiple contacts to reply to the same issue)

					$iss = $m_issue-> read((int)$msg['connect_id'], array("issue", "xfields"));
// 					dd($iss);
					$con_subm = $m_con-> read($iss['submitted_by']);
// 					dd($con_subm);

					if(!is_null($msg['from_id'])) {
						// If "from_id" is non-null, this is a staff reply
						// -- Read the corresponding user and connect the message to the user's "user_self" contact
						// -- Set "org_id" to the user's org id
						$user = $this-> user-> read($msg['from_id'], array('contact'), false); // Only need Contact ID
// 						dd($user);

						if(!empty($user)) {
							$q = QueryFactory::update()
							-> table(MVC_Core::$db-> tab['msg'])
							-> where("`id` = ?", $msg['id'])
							-> values(array(
								'from_id'=> $user['contact_id'],
								'to_id'=> $con_subm['id'], // Reply to the original submitter
								'org_id'=> $iss['org_id'],
								'converted'=> 1
							));
							MVC_Core::$db-> query($q);

							$update_msg_count_con = $user['contact'];
							$update_msg_count_con['id'] = $user['contact_id'];
						} else {
							// User doesn't exist anymore in system, unable to determine autoamtically who sent the message, set contact to "0"
							$q = QueryFactory::update()
							-> table(MVC_Core::$db-> tab['msg'])
							-> where("`id` = ?", $msg['id'])
							-> values(array(
								'from_id'=> 0,
								'converted'=> 1
							));
							MVC_Core::$db-> query($q);
						}

					} else {
						// If it's null, it's from an outside user (or it should be)
						// -- Use the same contact as specified in the issue "submitted_by"

						if(empty($msg['email'])) {
							// NOTE: There are some bugged entries on old messages - Attempt to discover if poster is staff member contact (user_self) or an already existing outside contact (isspost)
							//   By looking a the "subject", which for old messages was the name of the poster
							//   If everything fails (not found either way), assume the message poster is the outside contact.
							if(strtolower($msg['subject']) == strtolower($con_subm['name'])) {
								// Written by external user
								$q = QueryFactory::update()
								-> table(MVC_Core::$db-> tab['msg'])
								-> where("`id` = ?", $msg['id'])
								-> values(array(
									'from_id'=> $con_subm['id'],
									'org_id'=> $iss['org_id'],
									'converted'=> 1
								));
								MVC_Core::$db-> query($q);

								$update_msg_count_con = $con_subm;
							} else {
								// Nope, the subject is different, attempt to locate the name of the person in the users of the same organization.
								$q= QueryFactory::select()
								-> table(MVC_Core::$db-> tab['ctx'], 'id,count_msgs')
								-> where("`name` = ?", $msg['subject'])
								-> where("`type` = ?", "user_self")
								-> where("`org_id` = ?", $iss['org_id'])
								-> limit(1);

								$r = MVC_Core::$db-> query($q);
								if($r && count($r)==1) {
									// Found staff member !
									$con_user = $r-> current();

									$q = QueryFactory::update()
									-> table(MVC_Core::$db-> tab['msg'])
									-> where("`id` = ?", $msg['id'])
									-> values(array(
										'from_id'=> $con_user['id'],
										'to_id'=> $con_subm['id'],
										'org_id'=> $iss['org_id'],
										'converted'=> 1
									));
									MVC_Core::$db-> query($q);

									$update_msg_count_con = $con_user;
								} else {
									// Not found. Attempt to locate the name in "unknown" contacts, and associate with one with the same name
									// -- Unknown contacts are not associated to one organization in particular
									$q= QueryFactory::select()
									-> table(MVC_Core::$db-> tab['ctx'], 'id,count_msgs')
									-> where("`name` = ?", $msg['subject'])
									-> where("`type` = ?", "unknown")
									-> limit(1);

									$r = MVC_Core::$db-> query($q);
									if($r && count($r)==1) {
										$con_unkn = $r-> current();

										$update_msg_count_con = $con_unkn;
									} else {
										// If all fails, create the "unknown" contact
										// Does not have an actual valid e-mail address
										$iid = $m_con-> create("unknown", $iss['org_id'], array(
											'email'=> "unknown@contact.demo",
											'name'=> $msg['subject'],
											'count_msgs'=> 1
										));

										$con_unkn = array(
											'id'=> $iid,
											'count_msgs'=> 1
										);
									}
									$q = QueryFactory::update()
									-> table(MVC_Core::$db-> tab['msg'])
									-> where("`id` = ?", $msg['id'])
									-> values(array(
										'from_id'=> $con_unkn['id'],
										'org_id'=> $iss['org_id'],
										'converted'=> 1
									));
									MVC_Core::$db-> query($q);
								}

							}

						} else {
							// E-mail record is present, this is definitely the external user, but still check the e-mail

							if(strtolower($msg['email']) == strtolower($con_subm['email'])) {
								// -- Also read the contact of the assigned user (if any), as it is used as the "to_id" for messages sent by the external user
								if(!empty($iss['assigned_to'])) {
									$con_assgn = $m_con-> read($iss['assigned_to']);
									$con_assgn_id = $con_assgn['id'];
// 									dd($con_assgn);
								} else
									$con_assgn_id = null;

								$q = QueryFactory::update()
								-> table(MVC_Core::$db-> tab['msg'])
								-> where("`id` = ?", $msg['id'])
								-> values(array(
									'from_id'=> $con_subm['id'],
									'to_id'=> $con_assgn_id,
									'org_id'=> $iss['org_id'],
									'converted'=> 1
								));
								MVC_Core::$db-> query($q);

								$update_msg_count_con = $con_subm;
							} else {
								// There is an error, different e-mail address detected in reply versus submitter contact - Needs to be checked manually.
								$q = QueryFactory::update()
								-> table(MVC_Core::$db-> tab['msg'])
								-> where("`id` = ?", $msg['id'])
								-> values(array(
									'from_id'=> 0,
									'converted'=> 1
								));
								MVC_Core::$db-> query($q);
							}
						}


					}
					if(isset($update_msg_count_con)) {
						// Update the message count for the contact
						$m_con-> update($update_msg_count_con['id'], array(
							'count_msgs'=> $update_msg_count_con['count_msgs'] + 1
						));
					}
					break;

				case 'system':
					// These messages have been originally sent to users (they are internal messages, not associated to an e-mail address)
					// Simply convert "to_id" from an user id to the corresponding user's contact id, everything else stays the same.

					if(!is_null($msg['to_id'])) {
						// -- Read the corresponding user and connect the message to the user's "user_self" contact
						// -- Set "org_id" to the user's org id
						$user = $this-> user-> read($msg['to_id'], array('contact'), false); // Only need Contact ID
// 						dd($user);

 						if(!empty($user)) {
							$q = QueryFactory::update()
							-> table(MVC_Core::$db-> tab['msg'])
							-> where("`id` = ?", $msg['id'])
							-> values(array(
								'from_id'=> 0, // Coming from System (Contact zero)
								'to_id'=> $user['contact_id'], // User's associated contact_id
								'org_id'=> $user['org_id'],
								'converted'=> 1
							));
							MVC_Core::$db-> query($q);

							// Update the message count for the user-associated contact
							$m_con-> update($user['contact_id'], array(
								'count_msgs'=> $user['contact']['count_msgs'] + 1
							));
						} else {
							// User doesn't exist anymore in system, remove the message (system to nobody = pointless in keeping it)
							$q = QueryFactory::delete()
							-> table(MVC_Core::$db-> tab['msg'])
							-> where("`id` = ?", $msg['id']);
							MVC_Core::$db-> query($q);
						}

					}
					break;

				case 'user':
					// User to User message - Both from_id and to_id should be user ID's.

					if(is_null($msg['to_id'])) {
						// If to_id is null and there is an e-mail address in the "email" field, check the contacts for that e-mail address, and convert the message into a "sendmail" message
						// If the contact doesn't exist, create it (as an "auto_out" contact)

						// -- Apparently, there are only 4 such mails, from which 3 of them are tests, so manual conversion is better (or simply remove those messages)
						// -- Nothing to do here.

					} else {
						// Search both "from_id" and "to_id" users, and convert these values into contact id's
						$from_user = $this-> user-> read($msg['from_id'], array('contact'), false); // Only need Contact ID
						$to_user = $this-> user-> read($msg['to_id'], array('contact'), false); // Only need Contact ID

						if(empty($from_user) || empty($to_user))
							break; // Problem, users cannot be found, manual check needed.

						$q = QueryFactory::update()
						-> table(MVC_Core::$db-> tab['msg'])
						-> where("`id` = ?", $msg['id'])
						-> values(array(
							'from_id'=> $from_user['contact_id'],
							'to_id'=> $to_user['contact_id'],
							'converted'=> 1
						));
						MVC_Core::$db-> query($q);

						// Update the message count for the user-associated contact (to_user only)
						$m_con-> update($to_user['contact_id'], array(
							'count_msgs'=> $to_user['contact']['count_msgs'] + 1
						));
					}

					break;
				// TODO: All other types?
			}
		}

		echo "Step 3 Finished";
	}

	/**
	 * Upgrade 0.9g to 1.0a
	 * -- Step 4. Create issue contact links (from all issue-linked Messages)
	 */
	public function action_IssueContacts()
	{
		$m_comm = new App_Model_Comm();
		$m_issue = new App_Model_Issue();
		$m_con = new App_Model_Contact();

		// Read all messages, in order of "connect_id" and "dt";
		// - Make a list of contact ids which appear in the messages (from/to)
		// - The first one is linked to the issue id ( connect_id ) as the "submitter", all the others as "assignee" ( presuming the staff person replied to the submitter ) - Might convert "assignee" to something else later?
		$org_id = null; $issue_id = null; $contacts = array(); $submitter = null;

		$messages = $m_comm-> get_List(array('meta'), function(QueryFactory $q) {
			$q
			-> where("`source` IN(?)", array("isspost", "issreply"));
		}, null, "`connect_id` ASC, `dt` ASC", 0);

		foreach($messages as $msg) {
			if($msg['connect_id'] != $issue_id) {
				// New issue detected, save existing list of contacts and reinitialize
				if(count($contacts)>0) {
					foreach($contacts as $contact_id) {
						if($contact_id == $submitter)
							continue; // Already created, skip it
						// Make the extra contact links

 						$m_con-> con2iss_Create($issue_id, $contact_id, array(
							'role'=> "assignee",
							'label'=> "Staff",
							'org_id'=> $org_id
						));
					}
				}

				$org_id = (int)$msg['org_id'];
				$issue_id = (int)$msg['connect_id'];
				$contacts = array();

				if(!empty($msg['from_id'])) {
					$contacts[] = $submitter = $msg['from_id'];
					// Create the submitter contact
 					$m_con-> con2iss_Create($issue_id, $submitter, array(
						'role'=> "submitter",
						'label'=> "Submitted by",
						'org_id'=> $org_id
					));
				}

				// Is there a "to_id" in the original message ? Add it to the contacts
				if(!empty($msg['to_id']))
					if(!in_array($msg['to_id'], $contacts))
						$contacts[] = $msg['to_id'];
			} else {
				// Reply to current issue - Check and add from_id and to_id to the list of contacts
				if(!empty($msg['from_id']))
					if(!in_array($msg['from_id'], $contacts))
						$contacts[] = $msg['from_id'];
				if(!empty($msg['to_id']))
					if(!in_array($msg['to_id'], $contacts))
						$contacts[] = $msg['to_id'];
			}
		}

		// Final check
		if(count($contacts)>0) {
			foreach($contacts as $contact_id) {
				if($contact_id == $submitter)
					continue; // Already created, skip it
				// Make the extra contact links

				$m_con-> con2iss_Create($issue_id, $contact_id, array(
					'role'=> "assignee",
					'label'=> "Staff",
					'org_id'=> $org_id
				));
			}
		}

		echo "Step 4 Finished";
	}

	/**
	 * Upgrade 0.9g to 1.0a
	 * -- Step 5. Issue messages are transformed into conversations
	 */
	public function action_IssueConversations()
	{
		$m_comm = new App_Model_Comm();
		$m_issue = new App_Model_Issue();
		$m_con = new App_Model_Contact();
		$m_cnv = new App_Model_Conversation();

// 		MVC_Core::$db-> query("UPDATE `issues` SET `count_msgs`=0");

		// Type ID's in the system: 1(thousands), 2(few), 4(tens), 7(few)
		$issues = $m_issue-> get_List(1, array('issue','xfields','submitter'), function(QueryFactory $q) {
			// 0 convos = unconverted, they will have at least one conversation after conversion
			$q
			-> where("`iss`.`count_msgs` = 0"); // Use this value as tester - It will be updated to something more than 0 when conversation is created.
		}, null, "`id` ASC", 250); // Don't read too many, it causes problems.

		if(!$issues) {
			echo "Nothing to convert !";
			return;
		}

		foreach($issues as $iss) {
			// Read the messages associated with this issue:
			$messages = array();
			$comm = new App_Model_Comm();
			$messages = $m_comm-> get_List(array('meta','sender','content'), function (QueryFactory &$q) use ($iss) {
				$q
				-> where('`source` = "isspost" OR `source` = "issreply"')
				-> where("`connect_id` = ?", $iss['id']);
			}, null, "`dt` ASC");

				if($messages && count($messages)>0) {
					$convo_iid = null;
					foreach($messages as $msg) {
						// 					dd($msg, "MSG");

						if($msg['source'] == "isspost") {
							// Create a conversation out of the "isspost" message.
							$convo_iid = $m_cnv-> create("issue", $msg, $iss['id'], true, true);
							// 						$convo_iid = 3; // TESTING
						} else {
							if(is_null($convo_iid)) {
								// Replies without the original "POST"... it might have been deleted

								// Locate or create the contact by using the data from an issue
								$con_subm = $this-> contact_From_Issue($m_con, $iss['xfields'], $iss['org_id']);
								// 							dd($con_subm);

								// Create the conversation without a message, by specifying parameters directly
								$convo_iid = $m_cnv-> create_Raw(array(
									'type'=> "issue",
									'link_id'=> $iss['id'],
									'contact_ids'=> $con_subm['id'],
									'org_id'=> $iss['org_id'],
									'start_msg_id'=> 0,
									'start_msg_time'=> null,
									'last_msg_time'=> null,
									'description'=> $iss['track']
								));
								if(!$convo_iid) {
									dd("Error, unable to create Raw conversation");
									die;
								}
							}

							// Attach all subsequent messages to the same conversation (If ISSPOST is not the first, all others are ignored!)
							$m_cnv-> attach($convo_iid, $msg, true);
						}
					}

					// Read the final state of the conversation and update issue with various parameters resulted from the conversation assembly
					if($convo_iid) {
						$convo = $m_cnv-> read($convo_iid);

						// Extra step: Read the initial contact, in case it's the contact of an user, make sure the value "subm_org_id" is set to the user's organization
						$submitter = $m_con-> read($convo['contact_ids'][0]);
						if(!$submitter)
							die; // Error, unable to read the user...

						$issue_update = array(
							'commtime'=> $convo['last_msg_time'], // Last message of the conversation is kept in sync with the issue "commtime"
							'count_msgs'=> $convo['count_msgs'], // Since this a conversion, there is only one conversation. In the future, the nubmer will be a total of all conversations, not just the "only one" resulted after the data conversion
							'submitted_by'=> $submitter['id']
						);

						// This is used as part of the detection of users from within the system that have submitted issues while not being logged in (under their e-mail address)
						if($submitter['type'] == "user_self")
							$issue_update['subm_org_id'] = $submitter['org_id'];

						// 					dd($issue_update, "ISS UPDATE");
						$m_issue-> update($iss['id'], $issue_update, null, false, true);
					}

				} else {
					// Issues without messages will receive the submitter contact even if there is no message (and also make submitter contact link)

					// Attempt to locate (or create) the contact
 					$con_subm = $this-> contact_From_Issue($m_con, $iss['xfields'], $iss['org_id'], true);

 					if($con_subm) {
 						$m_con-> con2iss_Create($iss['id'], $con_subm['id'], array(
 							'role'=> "submitter",
 							'label'=> "Submitted by",
 							'org_id'=> $iss['org_id']
 						));

						// Mark this issue as converted
						$m_issue-> update($iss['id'], array(
							'submitted_by'=> $con_subm['id'],
							'count_msgs'=> 1,
						), null, false, true);
					}
				}

		}

		echo "Step 5 Finished";
	}

	/**
	 * Upgrade 0.9g to 1.0a
	 * -- Step 6. All conversations including system user contacts get placed in inbox/sentbox labels of that user
	 */
	public function action_UserLabelConvos()
	{
		$m_comm = new App_Model_Comm();
		$m_con = new App_Model_Contact();
		$m_cnv = new App_Model_Conversation();

		// Read all conversations from the system
		$q=QueryFactory::select()
		-> table(array(MVC_Core::$db-> tab['cnv'],'cnv'))
		-> where("`type` = 'issue'")
		-> where("`label_link_test` = 0")
		-> order("`id`");

		$r = MVC_Core::$db-> query($q);

		if(!$r || count($r) == 0) {
			echo "No conversations left to set";
			return;
		}

		foreach($r as $convo) {
			// Read all contact ids mentioned in the conversation.
			$ctx_ids = explode(',', $convo['contact_ids']);
			if(empty($ctx_ids)) {
				echo "ERROR, Conversation id {$convo['id']} has no contacts !";
				break;
			}

			$ctx = $m_con-> get_List(null, $ctx_ids, 'idlist', array('contact', 'user'), "FIELD(`ctx`.`id`, {$convo['contact_ids']})");

			// Assamble the list of user information (user_id, inbox_id, sentbox_id) for each contact where type is "user_self"
			$user_contacts = array();
 			foreach($ctx as $contact) {
 				if($contact['type'] == 'user_self') {
 					$user_contacts[$contact['id']] = array(
 						'id'=> $contact['user_id'],
 						'inbox'=> $contact['user']['inbox_id'],
 						'sentbox'=> $contact['user']['sentbox_id']
 					);

 				}
 			}

 			if(count($user_contacts)>0) {
 				// There are user contacts involved in the conversation - Read all the messages in the conversation that have these user contacts as either from_id or to_id
 				$msgs = $m_comm-> get_List(array(), function(QueryFactory &$q) use($convo, $user_contacts) {
 					$ct_filter = array_keys($user_contacts);
					$q
					-> where("`cnv_id` = ?", $convo['id'])
					-> where("`from_id` IN(?) OR `to_id` IN(?)", $ct_filter, $ct_filter);
 				});

 				// Check each message, and if from_id or to_id are user contacts, setup to link the conversation to that user's inbox/sentbox label
 				foreach($msgs as $msg) {
 					if(isset($user_contacts[$msg['from_id']]))
 						$user_contacts[$msg['from_id']]['link_sentbox'] = true;
 					if(isset($user_contacts[$msg['to_id']]))
 						$user_contacts[$msg['to_id']]['link_inbox'] = true;
 				}

 				// Parsed all messages, make the required links if located.
 				foreach($user_contacts as $userc) {
 					if(isset($userc['link_sentbox']))
 						try {
 							$m_cnv-> cnv2lab_Create_Link($userc['id'], $convo['id'], $userc['sentbox']);
 						} catch(Exception $e) {};
 					if(isset($userc['link_inbox']))
 						try {
 							$m_cnv-> cnv2lab_Create_Link($userc['id'], $convo['id'], $userc['inbox']);
 						} catch(Exception $e) {};
 				}
 			}

			// Mark the conversation as processed
			$m_cnv-> update($convo['id'], array(
				'label_link_test'=> 1
			));
		}

		echo "Step 6 Finished";
	}

	/**
	 * Upgrade 0.9g to 1.0a
	 * -- Step 7. All "submitter" contacts will receive an "contact_accounts" record
	 */
	public function action_ContactAccounts()
	{
		$m_con = new App_Model_Contact();
		$m_cac = new App_Model_ContactAccount();

		$q=QueryFactory::select()
		-> table(array(MVC_Core::$db-> tab['c2i'], 'c2i'))
		-> table(array(MVC_Core::$db-> tab['ctx'], 'ctx'), QueryFactory::autoalias_Add_Prefix("user_id,type,ctime,email,name", '_ctx_'), 'left', '`c2i`.contact_id = `ctx`.id')
		// The issues table, to find the issue type id, which needs to be placed into the contact_account xdata
		// (so the system knows which issue types this contact has submitted into the system)
		-> table(array(MVC_Core::$db-> tab['iss'], 'iss'), QueryFactory::autoalias_Add_Prefix("type_id", '_iss_'), 'left', '`c2i`.issue_id = `iss`.id')
		-> where("`c2i`.role = 'submitter'")
		-> where("`ctx`.cac_check = 0")
		-> order("`c2i`.dt_link", "ASC");
// 		-> limit(10);

		$r = MVC_Core::$db-> query($q);

		if(!$r || count($r) == 0) {
			echo "No contacts left to parse.";
			return;
		}

		$r-> split_by_prefixes = array(
			// extra table
			'contact'=> "_ctx_",
			'issue'=> "_iss_"
		);

		// Parse all contacts and create or update contact_accounts
		foreach($r as $row) {

			// Try to see if there's already a contact account for this e-mail address. If there is add the contact to the "managed_ctx"
			$cac = $m_cac-> read($row['contact']['email']);
			if(!$cac) {
				// Not found, create it.
				$xdata = array(
					'ist_list'=> array((int)$row['issue']['type_id'])
				);

				$m_cac-> create($row['contact']['email'], $row['contact_id'], $row['contact']['user_id'], null, $xdata);
			} else {
				$xdata = null;
				$managed_ctx = null;

				// First, see if the returned issue type is in ist_list, if not, add it
				if(!is_null($cac['xdata']) && !in_array($row['issue']['type_id'], $cac['xdata']['ist_list'])) {
					$xdata = $cac['xdata'];
					$xdata['ist_list'][] = (int)$row['issue']['type_id'];
				}

				if($row['contact_id'] != $cac['contact_id']) {
					// Different contact_id, but same address... add it to "managed_ctx"
					if(is_null($cac['managed_ctx']))
						$managed_ctx = array();
					else
						$managed_ctx = explode(',', $cac['managed_ctx']);

					if(!in_array($row['contact_id'], $managed_ctx))
						$managed_ctx[] = $row['contact_id'];
				}

				// Update
				$m_cac-> update($row['contact']['email'], $managed_ctx, $xdata);
			}

			// Finally, mark the contact as being processed
 			$m_con-> update($row['contact_id'], array("cac_check"=> true));
		}

		echo "Step 7 Finished";
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
 			$entry = $xfields[$key[$c]];

 			while(is_array($entry) && isset($key[$c+1])) {
 				$entry = $entry[$key[++$c]];
 			}
 			return substr($entry, 0, 50);
		} else {
			// Direct string copy
			return substr($xfields[$cache_settings[1]], 0, 50);
		}
		// Max 50 characters, due to the varchar() limit
	}

	/**
	 * Rebuilds missing issue data
	 * - Note: Doesn't replace "access_key" if it's already set !
	 */
	public function action_RebuildIssuePrimaryExtras()
	{
		$m_issue = new App_Model_Issue();

		$issues = $m_issue-> get_List(1, array('issue', 'xfields'), function(QueryFactory &$q) {
			$q-> where("`iss`.cache_primary IS NULL");
		}, null, '`iss`.id ASC', 500, 0, false, true);

		$istdef = $m_issue-> ist_Read(1);

		foreach($issues as $issue) {
			$update = array();
			if(empty($issue['access_key']))
				$update['access_key'] = sha1(microtime(1) . uniqid());

			// Build cache
			if(isset($istdef['settings']['xf_cache'])) {
				foreach($istdef['settings']['xf_cache'] as $xfc_id=> $xfc_def)
					$update['cache_'.$xfc_id] = $this-> grab_Data4Cache($istdef['settings']['xf_cache'][$xfc_id], $issue['xfields']);
			}

			$m_issue-> update($issue['id'], $update);

			usleep(mt_rand(5, 30));
		}

		echo "Step 8 Finished. Up to 500 issues processed, so repeat this as necessary.";
	}

	/**
	 * Upgrade 0.9g to 1.0a
	 * -- Step 8. Contact XFields are removed from all issue types and deleted from the DB
	 */
	public function action_RemContactXFields()
	{
		echo "Step 9 Finished";
	}

	/**
	 * Upgrade 0.9g to 1.0a
	 * -- Step 9. Add and convert extra tables, set values, reset changed user preferences. Cleanup obsolete fields and tables.
	 */
	public function action_Finalize()
	{
		$queries = $this-> read_SQL("db_import_09_10_step9");

		$count_success = 0;
		foreach($queries as $query) {
			$r = MVC_Core::$db-> query($query);
			if($r)
				$count_success++;
		}

		if($count_success == count($queries))
			echo "Step 10 Finished";
		else
			echo "Some queries failed !";
	}

}