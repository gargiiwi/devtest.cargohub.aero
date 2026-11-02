<?php
/**
 * "Conversations" model - Part of the communication system
 * -- Indicates a group of messages going back and forth between certain contacts, or logically connect messages into a coherent chain
 * -- Also allows managing visibility of messages under certain conditions
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage DataModels
 * @version 2.7
 */
class App_Model_Conversation extends MVC_Model
{
	private static $allowed_visibility = array('private', 'org', 'shared', 'public', 'hidden');

	/**
	 * Static instance of a Communication model, gets instanced at first use of the conversation model
	 * @var App_Model_Comm
	 */
	public static $comm = null;

	private static $missing_contact = array(
		'id'=> 0,
		'org_id'=> 0,
		'user_id'=> null,
		'type'=> 'system',
		'email'=> "unavailable",
		'name'=> "Unknown/Deleted",
		'xdata'=> null
	);

	/**
	 * Instance Constructor
	 */
	public function __construct()
	{
		// This model is strongly related to the Communication model, so get an instance at first use
		if(is_null(self::$comm))
			self::$comm = new App_Model_Comm();
	}

//----- Conversations CRUD -----//

	/**
	 * Creates a conversation record from an already existing message
	 * -- "basic" is the default conversation type, in which one contact is sending a message to another ( contact could be inside or outside the system )
	 *    Messages created from the "Compose" module without any parameters are created as "basic" conversations.
	 * -- "issue" conversations are linked to the issue in case and are used to manage the visibility of messages within an issue
	 * -- "broadcast" is not actually a conversation, but a mass message sent from one user to multiple contacts.
	 *    Those contacts can reply only to the original broadcaster, and do not see other contacts (will be implemented later)
	 * -- "system" are not conversations, they are just wrappers for messages sent from the system ( notifications ), so reading these messages work in a similar fashion like other types of conversations
	 *    System "conversations" cannot be replied. System conversations can be sent to multiple contacts, just like broadcasts. (will be implemented later)
	 *
	 * @param string $type One of "basic", "issue", "broadcast", "system"
	 * @param int|array $start_msg (Initial message of this conversation, either the message data itself (as returned by App_Model_Comm::read) or the ID (in which case the Conversation model reads the message)
	 * @param int $link_id Link this conversation to a record or a broadcaster
	 * @param string $description Description of the conversation (can be changed later) - If set to "true", the initial message subject is set as the description.
	 * @param bool $update_msg If true, update the $start_msg "cnv_id" to this conversation's ID
	 */
	public function create($type, $start_msg, $link_id = null, $description = null, $update_msg = false)
	{
		if(!in_array($type, array("basic", "issue", "broadcast", "system")))
			return MVC_Core::$debug-> dump("Invalid conversation type: $type", "Conversation::Create", null, 'error');

		// Attempt to read the initial message
		if(is_numeric($start_msg))
			$start_msg = self::$comm-> read($start_msg);
		if(!is_array($start_msg))
			return MVC_Core::$debug-> dump("Conversation starter message could not be located", "Conversation::Create", null, 'error');

		$msg_time = strtotime($start_msg['dt']);
		$convo = array(
			'type'=> $type,
			'link_id'=> $link_id,
			'org_id'=> $start_msg['org_id'], // Apparently some messages in the system do not have an org_id, fix that?
			'start_msg_id'=> $start_msg['id'],
			'start_msg_time'=> $msg_time,
			'last_msg_time'=> $msg_time,
			'last_msg_sender'=> $start_msg['from_id'],
			'description'=> $description===true? $start_msg['subject']: $description
		);
		// Add the contacts present in the starter message ( from, recipients ) if they exist
		$contacts = array();
		if($start_msg['from_id']>0)
			$contacts[] = $start_msg['from_id'];
		if(!empty($start_msg['recipients'])) {
			// Only add recipients from "to" and "cc", and skip the "bcc" - Those should be invisible in the conversation
			$recipients = explode(',', $start_msg['recipients']);
			list($c_to, $c_cc, $c_bcc) = array_pad(explode(',', $start_msg['recipient_distrib']), 3, 0); // ... just in case
			$recipients = array_slice($recipients, 0, $c_to+$c_cc);
			// Duplicate contact check ( in case sender is also recipient )
			foreach($recipients as $c_id)
				if(!in_array($c_id, $contacts))
					$contacts[] = $c_id;
		}
		$convo['contact_ids'] = implode(',', $contacts);

		// Type determines initial visibility
		switch($type) {
			case 'basic':
			case 'system':
				$convo['visibility'] = 'private'; break;
			case 'issue':
				$convo['visibility'] = 'public'; break;
			case 'broadcast':
				$convo['visibility'] = 'public'; break;
		}

		// Done, insert the conversation in the database
		$q=QueryFactory::insert()
		-> table(MVC_Core::$db-> tab['cnv'])
		-> values($convo);

		MVC_Core::$db-> query($q);
		$iid = MVC_Core::$db-> insert_id;

		// Update message if requested
		if($update_msg && $iid>0) {
			$q=QueryFactory::update()
			-> table(MVC_Core::$db-> tab['msg'])
			-> where("`id` = ?", (int)$start_msg['id'])
			-> values(array(
				'cnv_id'=> $iid
			));
			MVC_Core::$db-> query($q);
		}

		$this->_read_cache = null;
		return $iid;
	}

	/**
	 * Creates a conversation record directly (ignoring the presence of an initial message).
	 * This is not recommended, but can be used to simulate a conversation in which the initial message has been deleted (while keeping the conversation)
	 *
	 * @param array $convo
	 */
	public function create_Raw($convo)
	{
		// any key outside the correct fields is discarded (to avoid database errors)
		$fields = array('type', 'link_id', 'contact_ids', 'org_id', 'start_msg_id', 'start_msg_time', 'last_msg_time', 'last_msg_sender', 'description');
		foreach($convo as $field=> $value)
			if(!in_array($field, $fields))
				unset($convo[$field]);

		// Extra auto fields:
		// -- count_msgs - Starts with 0 in this version
		$convo['count_msgs'] = 0;

		// Done, insert the conversation in the database
		$q=QueryFactory::insert()
		-> table(MVC_Core::$db-> tab['cnv'])
		-> values($convo);

		MVC_Core::$db-> query($q);
		$iid = MVC_Core::$db-> insert_id;

		$this->_read_cache = null;
		return $iid;
	}

	// Read methods

	/**
	 * Attach function reads the conversation to make sure it exists.
	 * If it was previously read, this operation is skipped
	 * @var array
	 */
	private $_read_cache = null;

	/**
	 * Read the conversation metadata (and also messages within the conversation)
	 *
	 * @param int|string $cnv_access Either numeric conversation id ( most cases, inside the system ) or "access_key" (a 40 character string), for accessing conversations from outside the system.
	 * @param array $wtr What to read ("conversation", "organization", "linked", "messages", "contacts" )
	 * @param bool $use_cache True: Save all read data cache and return reference to the cache | False: Just return the data
	 */
	public function &read($cnv_access, $wtr = array("conversation"), $use_cache = false)
	{
		$false = false;

		$q=QueryFactory::select()
		-> limit(1);

		if(is_string($cnv_access) && strlen($cnv_access)==40)
			$q-> where("`cnv`.`access_key` = ?", $cnv_access);
		elseif(is_numeric($cnv_access))
			$q-> where("`cnv`.`id` = ?", $cnv_access);
		else
			return $false; // Invalid access method

		$wtr = self::wtr_Helper($wtr);

		if(in_array('conversation', $wtr))
			$q-> table(array(MVC_Core::$db-> tab['cnv'],'cnv')); // read everything
		else
			$q-> table(array(MVC_Core::$db-> tab['msg'],'cnv'), 'id,type'); // just id, type (check if conversation id exists)

		// do some joins
		if(in_array('organization', $wtr))
			$q-> table(array(MVC_Core::$db-> tab['org'],'org'), QueryFactory::autoalias_Add_Prefix("type_id,name", '_org_'), 'left', '`cnv`.org_id = `org`.id');

		$r = MVC_Core::$db-> query($q);

		if(!$r || count($r)==0)
			return $false; // Conversation not found

		$r-> split_by_prefixes = array(
			// extra tables
			'organization'=> "_org_"
		);

		$convo = $r-> current();

		// Reads the basic contact information for the contacts present in the conversation (email, name), not the details, those are read only when one single contact is shown
		if(in_array('contacts', $wtr)) {
			$contact_ids = explode(',', $convo['contact_ids']);

			$contact_wtr = array('contact', 'allstates');
			if(in_array('contacts_user', $wtr))
				$contact_wtr[] = 'user';

			$m_con = new App_Model_Contact();
			$convo['contacts'] = $m_con-> get_List(null, $contact_ids, 'idlist', $contact_wtr, "FIELD(`ctx`.id, {$convo['contact_ids']})", 0); // Read all contacts; Sorting is the order from the conversation

			// Make the index array which is used to connect messages with contacts

			if(in_array('messages', $wtr)) {
				$contacts_index = array();
				foreach($convo['contacts'] as $idx=> &$contact)
					$contacts_index[$contact['id']] = $idx;
			}
			$convo['contact_ids'] = $contact_ids;
		}

		// The linked data cannot be read with a join, as what data is connected depends on the conversation type. So, another query is used.
		if(in_array('linked', $wtr)) {
			switch($convo['type']) {
				// Issue conversation, the linked data is read using the Issue model
				case 'issue':
					$m_issue = new App_Model_Issue();
					$convo['link'] = $m_issue-> read($convo['link_id'], array('issue', 'type', 'organization', 'group', 'submitter'));
					break;

				case 'u2u':
					break;

				case 'broadcast':
					break;
			}
		}

		// Reads all messages which are part of the conversation, in order (time based)
		if(in_array('messages', $wtr)) {
			$post_processs_wtr = array();
			if(in_array('attachments', $wtr))
				$post_processs_wtr[] = "files";

			$comm_r = self::$comm-> get_List(array("meta", "content"), function(QueryFactory &$q) use($convo) {
				$q-> where("`msg`.cnv_id = ?", $convo['id'])->order("`msg`.`id` DESC"); //Added desc to show order date from oldest to newest in frontend issue submit
			}, null, null, 0);

			if($comm_r && count($comm_r)>0) {
				$convo['messages'] = array();
				foreach($comm_r as $msg) {

					self::$comm-> post_Process_Message($msg, $post_processs_wtr);

					// Locate the contacts involved with this message and link contact data into the message as references
 					if(in_array('contacts', $wtr)) {
 						if(!is_null($msg['from_id'])) {
 							if($msg['from_id'] == 0)
 								$msg['from'] = &self::$missing_contact;
 							else
 								$msg['from'] = &$convo['contacts'][$contacts_index[$msg['from_id']]];
						}

						if(!empty($msg['recipients'])) {
							// Replace the recipient ID's with the actual contacts; For To/Cc
							foreach($msg['recipients']['to'] as $idx=> $con_id)
								if($con_id == 0 || !isset($contacts_index[$con_id]))
									$msg['recipients']['to'][$idx] = &self::$missing_contact;
								else
									$msg['recipients']['to'][$idx] = &$convo['contacts'][$contacts_index[$con_id]];
							if(isset($msg['recipients']['cc']) && count($msg['recipients']['cc'])>0)
								foreach($msg['recipients']['cc'] as $idx=> $con_id)
									if($con_id == 0 || !isset($contacts_index[$con_id]))
										$msg['recipients']['cc'][$idx] = &self::$missing_contact;
									else
										$msg['recipients']['cc'][$idx] = &$convo['contacts'][$contacts_index[$con_id]];
						}
 					}

					$convo['messages'][] = $msg;
				}
			}
		}

		if($use_cache) {
			$this->_read_cache = $convo;
			return $this->_read_cache;
		} else
			return $convo;
	}

	/**
	 * Searches and reads multiple conversations metadata.
	 *
	 * @param string $type Conversation type or the pseudo-type "label", in which "search_id" is actually the label id (normally being the "link_id")
	 * @param string $search_id Either a label id to show label-linked conversations, or a link_id to show conversations linked to another element ( like an issue )
	 * @param array $wtr What to read ( convo, starter, start_msg, recipient, org )
	 * @param int $search_id
	 * @param callback $qfo_filter Without any filtering, all conversations linked to a certain element are returned
	 */
	public function get_List($type, $search_id, $wtr = array('convo'), $qfo_filter = null, $filter_params = array(), $order_by = null, $count_rows = 0, $start_row = 0)
	{
		if(!in_array($type, array("basic", "issue", "broadcast", "label")))
			return false; // Error, invalid conversation type

		if(empty($search_id))
			return false; // Must exist

		$q = $this-> get_List_QueryFactory_Helper($qfo_filter, $filter_params, is_null($order_by)? "`cnv`.`last_msg_time` DESC": $order_by, $count_rows, 0); // TODO - Support server level paging
		if(empty($q))
			return false; // Error, filter failed

		// What to read?
		$wtr = self::wtr_Helper($wtr);

		// The conversation metadata (full or partial)
		if(in_array('convo', $wtr))
			$convo_fields = null;
		else
			$convo_fields = array("id", "visibility");

		// Which one is the primary table, and which primary filter ?
		if($type == "label")
			$q	-> table(MVC_Core::$db-> table('c2l'), 'status')
				-> where("`c2l`.`lab_id` = ?", $search_id)
				-> table(MVC_Core::$db-> table('cnv'), $convo_fields, 'left', "`c2l`.`cnv_id` = `cnv`.`id`");
		else
			$q	-> where("`cnv`.`link_id` = ?", $search_id)
				-> where("`cnv`.`type` = ?", $type)
				-> table(MVC_Core::$db-> table('cnv'), $convo_fields);

		if(in_array('starter', $wtr))
			// The starter contact (sender contact of first message in the conversation)
			$q-> table(array(MVC_Core::$db-> tab['ctx'],'starter'), QueryFactory::autoalias_Add_Prefix("id,type,user_id,email,name", '_starter_'), 'left', "SUBSTRING_INDEX(`cnv`.contact_ids, ',', 1) = `starter`.id");

		if(in_array('start_msg', $wtr)) {
			// The starter message is included with the conversation data
			$q-> table(MVC_Core::$db-> table('msg'), QueryFactory::autoalias_Add_Prefix("source,from_id,recipients,recipient_distrib,subject", '_msg_'), 'left', '`cnv`.start_msg_id = `msg`.id'); // ,content - No content...

			/* // Not valid anymore, as to_id disappears
			if(in_array('recipient', $wtr))
				// Initial recipient of the starting message (most conversations will only have the starter and the recipient) as contacts
				$q-> table(array(MVC_Core::$db-> tab['ctx'],'initrec'), QueryFactory::autoalias_Add_Prefix("id,type,user_id,email,name", '_initrec_'), 'left', "`msg`.`to_id` = `initrec`.id");
			*/
		}

		if(in_array('organization', $wtr))
			// user's organization details
			$q-> table(MVC_Core::$db-> table('org'), QueryFactory::autoalias_Add_Prefix("name,type_id,contact_id", '_org_'), 'left', '`cnv`.org_id = `org`.id');

		$r = MVC_Core::$db-> query($q);
		if(!$r || count($r)==0)
			return false; // No conversations found

		$r-> split_by_prefixes = array(
			// Extra tables
			'starter'=> "_starter_",
			'start_msg'=> "_msg_",
//			'recipient'=> "_initrec_",
			'org'=> "_org_",
			// Tidy
			'count'=> "count_"
		);

		return $r;
	}

	// Update methods

	/**
	 * Attaches an existing message to a conversation (Conversation update)
	 * -- Updates the "last_msg_sender" value to the ID of the sender of this new attached message
	 *
	 * @param int $cnv_id
	 * @param int|array $message Either the full message or it's ID, in which case the function will read the message
	 * @param bool $update_msg If true, update the $message "cnv_id" to this conversation's ID ( for now, the field is named "connect_id" )
	 */
	public function attach($cnv_id, $message, $update_msg = false)
	{
		if(!$cnv_id > 0)
			return false; // A conversation id must exist and be positive or attach doesn't work

		// Read the conversation if necessary
		if(is_null($this->_read_cache))
			$convo = $this-> read($cnv_id, array('conversation'), true);
		else
			$convo = &$this->_read_cache;
		$convo['contact_ids'] = explode(',', $convo['contact_ids']);

		// Attempt to read the initial message
		if(is_numeric($message))
			$message = self::$comm-> read($message);
		if(!is_array($message))
			return false; // Error, message to connect could not be located

		$convo_update = array(
			'count_msgs'=> $convo['count_msgs'] + 1,
			'last_msg_time'=> strtotime($message['dt']),
			'last_msg_sender'=> $message['from_id']
		);

		// Check if the new message contacts are present in the conversation contacts, if not, add them
		if(!empty($message['from_id']) && !in_array($message['from_id'], $convo['contact_ids']))
			$convo['contact_ids'][] = $message['from_id'];

		if(!empty($message['recipients'])) {
			// Only add recipients from "to" and "cc", and skip the "bcc" - Those should be invisible in the conversation
			$recipients = explode(',', $message['recipients']);
			list($c_to, $c_cc, $c_bcc) = array_pad(explode(',', $message['recipient_distrib']), 3, 0); // ... just in case
			$recipients = array_slice($recipients, 0, $c_to+$c_cc);
			// Duplicate contact check ( in case sender is also recipient )
			foreach($recipients as $c_id)
				if(!in_array($c_id, $convo['contact_ids']))
					$convo['contact_ids'][] = $c_id;
		}

		// Update cache with new values
		$this->_read_cache = array_merge($this->_read_cache, $convo_update);
		$this->_read_cache['contact_ids'] = $convo['contact_ids'];

		// Reimplode contact_ids
		$convo_update['contact_ids'] = implode(',', $convo['contact_ids']);
		// ... and update the database

		$q=QueryFactory::update()
		-> table(MVC_Core::$db-> tab['cnv'])
		-> where("`id` = ?", $convo['id'])
		-> values($convo_update);

		$r = MVC_Core::$db-> query($q);
		if(!$r)
			return false; // Error, could not update convo

		// Update message if requested
		if($update_msg) {
			$q=QueryFactory::update()
			-> table(MVC_Core::$db-> tab['msg'])
			-> where("`id` = ?", $message['id'])
			-> values(array(
				'cnv_id'=> $convo['id']
			));
			MVC_Core::$db-> query($q);
		}

		return true;
	}

	/**
	 * Simple update function that allows changing some fields from the conversation which do not affect the message chain and item linking:
	 * - "visibility", "org_id", "access_key", "description"
	 *
	 * @param int $convo_id
	 * @param array $convo_update
	 * @param bool $string_expressions This parameter is sent to query factory, and is used to disable string escaping (in order to allow expressions, for example to increment a counter)
	 */
	public function update($convo_id, $convo_update, $string_expressions = false, $link_id = null)
	{
		// any key outside the correct fields is discarded (to avoid database errors)
		// Organization and user ID's, as well as type cannot be modified anymore (and there's no reason, contacts are associated with a certain entity which doesn't change ID)
		$fields = array("visibility", "contact_ids", "org_id", "access_orgs", "access_key", "description");
		foreach($convo_update as $field=> $value) {
			if(!in_array($field, $fields))
				unset($convo_update[$field]);
		}

		if(!empty($convo_update['visibility']) && !in_array($convo_update['visibility'], self::$allowed_visibility))
			return false; // error, visibility has an invalid value

		if(!empty($convo_update['access_orgs']) && is_array($convo_update['access_orgs']))
			$convo_update['access_orgs'] = implode(',', $convo_update['access_orgs']);

		$q=QueryFactory::update()
		-> table(MVC_Core::$db-> tab['cnv'])
		-> where("`id` = ?", $convo_id)
		-> values($convo_update, null, $string_expressions);

		if(!empty($link_id))
			$q-> where("`link_id` = ?", $link_id);

		return MVC_Core::$db-> query($q);
	}

	/**
	 * Removes all the messages from a conversation and then the conversation itself.
	 * The conversation needs to be unlinked first from all labels, or any external elements.
	 * This method does the deletion and update contacts, users and organization counters.
	 *
	 * @param int $cnv_id
	 * @return array Number of messages deleted, or false if the procedure failed
	 */
	public function delete($cnv_id)
	{
// 		dd("DELETING CONVO ". $cnv_id);

		// Delete procedure:

		// 1. Check if the conversation exists, by doing a very shallow read
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> tab['cnv'], "type,org_id")
		-> where("`id` = ?", $cnv_id)
		-> limit(1);

		$r = MVC_Core::$db-> query($q);
		if(!$r || count($r)==0)
			return false; // Conversation not found or a database error.

		// 2. Read all messages linked to this conversation, finding org_id, and contact id's (from/to), also the user_ids linked to these contacts
		// -- Make list of user ids, with the same purpose of updating counters

		$r = self::$comm-> get_List(array('meta', 'sender'), function(QueryFactory &$q) use ($cnv_id) {
			$q-> where("`cnv_id` = ?", $cnv_id);
		});
		if(!$r)
			return 0; // There are no messages, the conversation was already empty

		$organizations = array(); $sender_contacts = array(); $users = array();
		foreach($r as $msg) {
			// Make list of organizations that will get their counters updated (org_id of messages indicates the controlling organization for each message, and is either sender's organization or target organization for incoming public message)
			// -- All lists are counts of messages, keys being ID's of those entities.
			if($msg['org_id']> 0) {
				if(!isset($organizations[$msg['org_id']]))
					$organizations[$msg['org_id']] = 0;
				$organizations[$msg['org_id']]++;
			}

			// Make list of "sender" contacts (from_id), that will also get their counters updated (assuming the sender still exists in the system, and is not "system" itself (ID = 0)
			if($msg['from_id']> 0) {
				if(!isset($sender_contacts[$msg['from_id']]))
					$sender_contacts[$msg['from_id']] = 0;
				$sender_contacts[$msg['from_id']]++;

				// Also make list of user ids, with the same purpose of updating counters
				if($msg['sender']['user_id']> 0) {
					if(!isset($users[$msg['sender']['user_id']]))
						$users[$msg['sender']['user_id']] = 0;
					$users[$msg['sender']['user_id']]++;
				}
			}
		}

		try {
			// 3. Delete the messages linked to this conversation
			$q=QueryFactory::delete()
			-> table(MVC_Core::$db-> tab['msg'])
			-> where("`cnv_id` = ?", $cnv_id);

			MVC_Core::$db-> query($q);

			// 4. Update the message counter for organizations (in one query using the magic of CASE WHEN :P)
			// -- Unfortunately this can't be done with QueryFactory yet because it doesn't have support for CASE/WHEN in SET fields.
			// TODO: Maybe implement this in QF ? It seems quite useful...

			$q = "UPDATE `".MVC_Core::$db-> tab['org']."` SET `count_msgs` = CASE `id` ";
			foreach($organizations as $org_id=> $msg_count)
				$q.="WHEN {$org_id} THEN `count_msgs`-{$msg_count} ";
			$q.="END WHERE `id` IN (".implode(',', array_keys($organizations)).")";

			MVC_Core::$db-> query($q);

			// 5. Update the message counter for all contacts (same procedure)
			$q = "UPDATE `".MVC_Core::$db-> tab['ctx']."` SET `count_msgs` = CASE `id` ";
			foreach($sender_contacts as $ctx_id=> $msg_count)
				$q.="WHEN {$ctx_id} THEN `count_msgs`-{$msg_count} ";
			$q.="END WHERE `id` IN (".implode(',', array_keys($sender_contacts)).")";

			MVC_Core::$db-> query($q);

			// 6. Update the message coutner for all users (if it is the case)
			if(!empty($users)) {
				$q = "UPDATE `".MVC_Core::$db-> tab['usr']."` SET `count_msgs` = CASE `id` ";
				foreach($users as $user_id=> $msg_count)
					$q.="WHEN {$user_id} THEN `count_msgs`-{$msg_count} ";
				$q.="END WHERE `id` IN (".implode(',', array_keys($users)).")";

				MVC_Core::$db-> query($q);
			}

			// 7. Delete the conversation itself
			$q=QueryFactory::delete()
			-> table(MVC_Core::$db-> tab['cnv'])
			-> where("`id` = ?", $cnv_id);

			return MVC_Core::$db-> query($q);
		} catch(Exception $e) {
			// One of the queries failed, for any reason.
			return false;
		}
	}

//----- Conversation To Label relation CRUD -----//

	/**
	 * Links an existing conversation to a label.
	 * -- Does not check if the conversation or label id exists, please make sure they do !
	 *
	 * @param int $user_id
	 * @param int $cnv_id
	 * @param int $lab_id
	 * @param string $status Initial status of the conversation for that label (defaults to "normal")
	 */
	public function cnv2lab_Create_Link($user_id, $cnv_id, $lab_id, $status = 'normal')
	{
		if(!in_array($status, array('normal', 'unread', 'trash', 'deleted')))
			return false; // Error, invalid status

		$q=QueryFactory::insert()
		-> table(MVC_Core::$db-> tab['c2l'])
		-> values(array(
			'user_id'=> $user_id,
			'cnv_id'=> $cnv_id,
			'lab_id'=> $lab_id,
			'status'=> $status
		));

		$r = MVC_Core::$db-> query($q);
		if(!$r)
			return false; // Error, could not create c2l link

		return true;
	}

	/**
	 * Reads the status of a conversation in relation to an user label - Useful to determine if a conversation is unread
	 *
	 * @param int $cnv_id
	 * @param int $lab_id
	 * @return string $status Current status of the relation
	 */
	public function cnv2lab_Read_Status($cnv_id, $lab_id)
	{
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> tab['c2l'], 'status')
		-> where("`cnv_id` = ?", $cnv_id)
		-> where("`lab_id` = ?", $lab_id)
		-> limit(1);

		$r = MVC_Core::$db-> query($q);

		if(!$r || count($r)==0)
			return false; // Relation not found or wrong id's passed

		$row = $r-> current();
		return $row['status'];
	}

	/**
	 * Reads all the links from an user
	 * -- optionally filtering by a certain conversation
	 * -- optionally filtering by status
	 *
	 * @param int $user_id
	 * @param int $cnv_id
	 * @param string $status
	 */
	public function cnv2lab_Get_Links($user_id, $cnv_id = null, $status = null)
	{
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> tab['c2l'], 'cnv_id,lab_id,status')
		-> where("`user_id` = ?", $user_id);

		if(!is_null($cnv_id))
			$q-> where("`cnv_id` = ?", $cnv_id);
		if(is_string($status))
			$q-> where("`status` = ?", $status);

		$r = MVC_Core::$db-> query($q);

		if(!$r || count($r)==0)
			return false; // No results

		return $r;
	}

	/**
	 * Counts how many conversations have a certain status in relation to a label.
	 * -- Optionally, count only among specified conversation IDs
	 *
	 * @param int $lab_id
	 * @param string $status
	 * @param array $cnv_ids
	 */
	public function cnv2lab_Count_Status_Of($lab_id, $status, $cnv_ids = null)
	{
		if(!in_array($status, array('normal', 'unread', 'trash', 'deleted')))
			return false; // Error, invalid status

		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> tab['c2l'], array('status_count'=> "count(*)"))
		-> where("`lab_id` = ?", $lab_id)
		-> where("`status` = ?", $status);

		if(is_array($cnv_ids))
			$q-> where("`cnv_id` IN(?)", $cnv_ids);

		$r = MVC_Core::$db-> query($q);

		if(!$r || count($r)==0)
			return false; // Database error?

		$row = $r-> current();
		return $row['status_count'];
	}

	/**
	 * Updates the relation between one or more conversations and a label
	 * -- Usually used to move conversations to a different label or simply change the status (without changing the label)
	 *
	 * @param int|array $cnv_id
	 * @param int $lab_id Current label ID
	 * @param string $update_status Set new status
	 * @param int $update_lab_id If not null, the selected conversations are moved to this new label
	 * -- At least one of these must be set, or the function does nothing
	 */
	public function cnv2lab_Update($cnv_ids, $lab_id, $update_status = null, $update_lab_id = null)
	{
		$update = array();
		if(!is_null($update_status)) {
			if(!in_array($update_status, array('normal', 'unread', 'trash', 'deleted')))
				return false; // Error, invalid status
			$update['status'] = $update_status;
		}
		if(is_numeric($update_lab_id))
			$update['lab_id'] = (int)$update_lab_id;

		if(empty($update))
			return false; // Nothing to update !

		$q=QueryFactory::update()
		-> table(MVC_Core::$db-> tab['c2l'])
		-> where("`lab_id` = ?", $lab_id);

		if(is_array($cnv_ids))
			$q-> where("`cnv_id` IN(?)", $cnv_ids);
		else
			$q-> where("`cnv_id` = ?", $cnv_ids);

		$q-> values($update);

		$r = MVC_Core::$db-> query($q);
		if(!$r)
			return false; // Error, could not update c2l link(s)

		return true;
	}

	/**
	 * Updates status on all conversation to label relations for a conversation and one or more labels
	 * -- Returns the number of affected rows, or false if there was an error
	 *
	 * @param int $cnv_id
	 * @param int|array $lab_ids
	 * @param string $status New status
	 * @return int Affected rows
	 */
	public function cnv2lab_Mass_Status_Update($cnv_id, $lab_ids, $status)
	{
		if(!in_array($status, array('normal', 'unread', 'trash', 'deleted')))
			return false; // Error, invalid status

		$q=QueryFactory::update()
		-> table(MVC_Core::$db-> tab['c2l']);

		if(!is_null($cnv_id))
			$q-> where("`cnv_id` = ?", $cnv_id);

		if(is_array($lab_ids))
			$q-> where("`lab_id` IN(?)", $lab_ids);
		else
			$q-> where("`lab_id` = ?", $lab_ids);

		$q-> values(array(
			'status'=> $status
		));

		$r = MVC_Core::$db-> query($q);
		if(!$r)
			return false; // Error, could not update c2l

		return MVC_Core::$db-> affected_rows;
	}

	/**
	 * Unlinks the conversation from all labels (this is usually done prior to deleting the conversation)
	 * -- Should also update count_el for each label, according to the number of conversations unlinked from each label.
	 *
	 * @param int $cnv_id
	 * @return int Number of labels from which the conversation was disconnected, or false if none found
	 */
	public function cnv2lab_Unlink_Conversation($cnv_id)
	{
		// 1. Discover all the label ids that this conversation is linked to
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> tab['c2l'], 'user_id,lab_id,status')
		-> where("`cnv_id` = ?", $cnv_id);

		$r = MVC_Core::$db-> query($q);

		if(!$r || count($r)==0)
			return false; // No results - This conversation isn't linked to any labels, or there's a database error

		$labels = array(); $users = array();
		foreach($r as $link) {
			if(!isset($labels[$link['lab_id']]))
				$labels[$link['lab_id']] = true;

			// 2. Also make a list of user_ids for which the label status is "unread"
			if(!isset($users[$link['user_id']]))
				$users[$link['user_id']] = true;
		}

		// 3. Unlink the conversation from all labels
		$q=QueryFactory::delete()
		-> table(MVC_Core::$db-> tab['c2l'])
		-> where("`cnv_id` = ?", $cnv_id);

		MVC_Core::$db-> query($q);

		// 4. Update all found label counters (reduce by 1)
		if(!empty($labels)) {
			$q=QueryFactory::update()
			-> table(MVC_Core::$db-> tab['lab'])
			-> values(array('count_el'=> "`count_el` - 1"), null, true)
			-> where("`id` IN(?)", array_keys($labels));

			MVC_Core::$db-> query($q);
		}

		// 5. Update the "count_unread" value for all the discovered users (reduce by 1)
		if(!empty($users)) {
			$q=QueryFactory::update()
			-> table(MVC_Core::$db-> tab['usr'])
			-> values(array('count_unread'=> "`count_unread` - 1"), null, true)
			-> where("`id` IN(?)", array_keys($users));

			MVC_Core::$db-> query($q);
		}

		return count($labels);
	}

	/**
	 * Unlinks the label from all conversations (this is normally done if the label is deleted)
	 * -- Note that the conversations should instead be moved to another label by using $this-> cnv2lab_Update() or they might become invisible for that user if no other labels are linked to these conversations.
	 *
	 * @param int $lab_id
	 */
	public function cnv2lab_Unlink_Label($lab_id)
	{

	}

}