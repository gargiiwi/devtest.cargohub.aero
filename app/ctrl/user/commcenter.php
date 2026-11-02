<?php
/**
 * Communication Center - Conversation and E-Mails
 *
 * @author Wavetrex (wavetrex@gmail.com)
 * @package Cargo ITS
 * @version 1.0.dev
 */
class App_Ctrl_User_CommCenter extends App_Ctrl_Base
{
	/**
	 * Communication model, used extensively in this module
	 * @var App_Model_Comm
	 */
	private $comm;

	/**
	 * Conversation model, used in various places around the module
	 * @var App_Model_Conversation
	 */
	private $cnv;

	/**
	 * Contacts model, also used all around this module
	 * @var App_Model_Contact
	 */
	private $con;

	/**
	 * Labels model, part of the communication system
	 * @var App_Model_Commlabel
	 */
	private $lab;

	/**
	 * Makes initializations useful for any action
	 */
	public function __construct()
	{
		parent::__construct();

		// Communication related models
		$this-> comm = $this-> factory_Model("Comm");
		$this-> cnv = $this-> factory_Model("Conversation", "cnv");
		$this-> con = $this-> factory_Model("Contact", "con");
		$this-> lab = $this-> factory_Model("Commlabel", "lab");
	}

	/**
	 * Index action for the Users display and management page
	 * @see MVC_Ctrl::action_Index()
	 */
	public function action_Index(MVC_Request $in)
	{
		array_push($this-> CFG['modules'], "contact_search", "contact_editor", "commcenter_main");
		$this-> CFG['jqwidgets'][] = array("tabs", "splitter", "panel", "tree", "scrollbar", "datetimeinput");
		$this-> CFG['jqwidgets'][] = array("calendar", "combobox", "buttongroup", "listbox", "dropdownlist");
		$this-> CFG['jqwidgets'][] = array("-gridcomplete");
		// These are used only by the WYSIWYG editor:
		$this-> CFG['jqwidgets'][] = array("dropdownbutton", "colorpicker", "editor");

		$this-> CFG['commcenter_main'] = array();
		$this-> CFG['xjs'] = array(
//  		'tinymce' => true, // Not yet, but will be part of the module in the (near) future
// 			'jqautosize'=> true,
			'jqform'=> true,
			'dateformat' => true
		);

		// Some default settings:
		$conversation_tab_title = "Select conversation";

		// Check the _load_config session variable, it changes the way the module is prepared and loaded
		if(isset(MVC_Core::$ss->_load_config) && isset(MVC_Core::$ss->_load_config['commcenter'])) {
			$_load_config = MVC_Core::$ss->_load_config;
			$lc_cc = $_load_config['commcenter'];

			switch($lc_cc['mode']) {

				// While the module enters on first tab ( idx 0, list ) by default, using load config it can load a specific label ( instead of simply defaulting to a-index )
				case 'list':
					if(is_numeric($lc_cc['label'])) {
						// Label ID specified directly, read it
						$label = $this-> lab-> read($lc_cc['label']);

						if($label) {
							// Found, check if this label actually belongs to the logged in user
							if($label['user_id'] == MVC_Core::$ss-> user['id']) {
								unset($lc_cc['label']);
								$lc_cc['label_type'] = "c-user";
								$lc_cc['label_id'] = (int)$label['id'];
							} else
								// Incorrect user
								unset($lc_cc);
						} else
							// Not found
							unset($lc_cc);
					} else {
						// Not numberic, so type or name is specified - Verify text against know types and some special texts, plus the label's name
						switch($lc_cc['label']) {
							// Inbox and Sentbox are already stored into the user's data, they don't need to be searched
							case 'a-inbox':
							case 'inbox':
							case 'received':
								$label = array('type'=> "a-inbox", 'id'=> MVC_Core::$ss-> user['inbox_id']);
								break;
							case 'b-sent':
							case 'sentbox':
							case 'sent':
								$label = array('type'=> "b-sent", 'id'=> MVC_Core::$ss-> user['sentbox_id']);
								break;
							// The other types need to be searched and found
							case 'drafts':
								$lc_cc['label'] = 'd-drafts';
							case 'd-drafts':
								// Find the drafts special label
								$label = $this-> lab-> find(MVC_Core::$ss-> user['id'], $lc_cc['label']);
								break;
							case 'trash':
								$lc_cc['label'] = 'e-bin';
							case 'e-bin':
								// Find the user's recycle bin
								$label = $this-> lab-> find(MVC_Core::$ss-> user['id'], $lc_cc['label']);
								break;
							default:
								// For everything else, attempt to search the label's visible name
								$label = $this-> lab-> find(MVC_Core::$ss-> user['id'], null, $lc_cc['label']);
						}
						if(isset($label) && is_array($label)) {
							unset($lc_cc['label']);
							$lc_cc['label_type'] = $label['type'];
							$lc_cc['label_id'] = (int)$label['id'];
						} else
							// Nothing found, wrong type or name specified, reset load config so default (loading inbox) can happen
							unset($lc_cc);
					}
					break;

				// Entering module directly on second tab ( idx 1)
				case 'conversation':
					$conversation_tab_title = "Loading...";

					// Is there a location location ? Read it from session...
					if(isset($lc_cc['location_key']))
						$lc_cc['location'] = MVC_Core::$ss-> locations[$lc_cc['location_key']];

					break;

				// Entering the module on the "compose" tab ( idx 2 )
				case 'compose':

					if(isset($lc_cc['location_key']))
						$lc_cc['location'] = MVC_Core::$ss-> locations[$lc_cc['location_key']];

					break;
			}

			// Done, remove load config from session, a refresh of the module would load it in default mode
 			unset($_load_config['commcenter']);
 			MVC_Core::$ss->_load_config = $_load_config;
		}

		if(isset($lc_cc))
			$this-> CFG['commcenter_main']['load_config'] = $lc_cc;
		else {
			// No special loading, or load config contained incorrect parameters, prepare some variables to load the "List" mode with "Inbox" label ready:
			$this-> CFG['commcenter_main']['load_config'] = array(
				'mode'=> "default", // This will default to #list tab, but if another is specified ( #compose, #contacts ) it will not forcibly change it
				'label_type'=> "a-inbox", // Initial label to show on #list tab
				'label_id'=> MVC_Core::$ss-> user['inbox_id']
			);
		}

		// Contact search scope - Used in Conversation, Compose (and perhaps other parts)
		if(isset(MVC_Core::$ss-> user['preferences']['csscope']))
			$this-> CFG['commcenter_main']['csscope'] = MVC_Core::$ss-> user['preferences']['csscope'];
		else
			$this-> CFG['commcenter_main']['csscope'] = new stdClass(); // empty - If an active issue is present, only contacts linked to that particular issue are searched.

		// Administrator can view/edit contacts from any org
/*		if(isset(MVC_Core::$ss-> user['is_admin']) || isset(MVC_Core::$ss-> user['is_supervisor'])) {
			if($in-> org_id) {
				$this-> CFG['commcenter_main']['load_config']['org_id'] = MVC_Core::$ss-> perspective = $in-> org_id;
				// When operating on another organization, read it so it's name can be displayed.
				$this-> myorg = $this-> org-> read(MVC_Core::$ss-> perspective, array('orgtype'));
			} else
				unset(MVC_Core::$ss-> perspective); // If the ID not present, remove this from session
		}
*/


		$this-> prepare_Header("Communication Center");

		// Message center system tabs ( fixed in number - 5 tabs, but variable in title and content )
		$all_available_tabs = array();

		// The primary tab for the message center, obviously the inbox itself
		$all_available_tabs[] = array(
			'id'=> "tab_list",
			'title'=> "Inbox", // Initial tab name, but will get changed if another folder is loaded
			'icon'=> "commlabel/a-inbox.png"
		);
		// The message content tab, used to view the full body of a message ( after selecting it from the folder's grid )
		$all_available_tabs[] = array(
			// initially disabled, must select a message first in order to view it's contents
			'id'=> "tab_conversation",
			'title'=> $conversation_tab_title,
			'icon'=> "conversation.png"
		);
		// Message compose, includes a TinyMCE editor
		$all_available_tabs[] = array(
			'id'=> "tab_compose",
			'title'=> "Compose",
			'icon'=> "draw_calligraphic.png"
		);

/*		// DISABLED - New module in place

		// The contacts (address book) grid, with ability to filter, add, remove contacts
		$all_available_tabs[] = array(
			'id'=> "tab_contacts",
			'title'=> "Address Book",
			'icon'=> "book_addresses.png"
		);
		// The address detail tab, which contains view/edit for an addressbook entry
		$all_available_tabs[] = array(
			// initially disabled, must select either a message ( to detect the sender's e-mail and locate him in address book ) or a contact itself from the address book
			'id'=> "tab_vcard",
			'title'=> "Select contact",
			'icon'=> "vcard.png"
		);
*/

		$this-> mc_tabs = $all_available_tabs;

		//== Contact editor JS data ==//

		// Read the contact field groups and settings so the dynamic contact editor can be initialized
		MVC_NLS::load_Groups("contact");
		$fields_structure = MVC_NLS::get_Object("contact", true);

		// Process the fields stucture a bit before sending it to JS
		foreach($fields_structure as &$group) {
			foreach($group['fields'] as $field=> $string) {
				if(strpos($string, "~"))
					$group['fields'][$field] = explode("~", $string);
			}
		}

		$this-> CFG['contact_editor'] = array(
			'fields_structure'=> $fields_structure
		);

		// which body template to use, and run the view
		$this-> site_section = "private". DS. "comm_center.html";
		$this-> view("private.html");
	}

	/**
	 * Auto-redirecting action which tells the message center to open/load with custom configuration ( #conversation, #compose, #vcard )
	 * -- Prepares some variables in the session then redirects to Index action. JavaScript does the rest...
	 * -- The index will remove the loading configuration after it's done preparing the variables.
	 *    (This means that reloading the module for any reason will not load in custom configuration)
	 * -- Subsequent ajax requests will have to receive variables as request parameters and should not rely on this stored session data !
	 *
	 * @param MVC_Request $in
	 */
	public function action_Custom_Load(MVC_Request $in)
	{
		// Configuration not specified or malformed, just load normal mode
		if(!isset($in-> config))
			MVC_Core::shutdown("/user/commcenter");

		$config = json_decode($in-> config, true);
		if(!$config)
			MVC_Core::shutdown("/user/commcenter");

		// Save loading configuration into session
		if(!isset(MVC_Core::$ss->_load_config))
			$_load_config = array();
		else
			$_load_config = MVC_Core::$ss->_load_config;
		$_load_config['commcenter'] = $config;
		MVC_Core::$ss->_load_config = $_load_config;

		// ...  then redirect to the "normal" url
		MVC_Core::shutdown("/user/commcenter#". $config['mode']);
	}

	//=== Dividing the ever growing action_Load_Tab_HTML into several sub-functions to relax the controller structure a bit

	/**
	 * Prepares the Conversation tab
	 * @param MVC_Request $in
	 */
	private function load_Tab_Conversation(MVC_Request $in)
	{
		// Load the conversation and it's messages, and send all to the template, + some stuff for the javascript so all the reply/etc. buttons work
		$convo = $this-> cnv-> read($in-> convo_id, array("conversation", "messages", "contacts")); // , "linked"
		if(!$convo)
			return $this-> send_JSON_Headers(array('tab_conversation'=> false));

		$this-> convo = $convo;

		// If this is an issue-linked conversation, read information about the issue when displaying it.
		if($convo['type'] == 'issue') {
			$this-> factory_Model("Issue");
	 		$iss = $this-> issue-> read($convo['link_id'], array('issue', 'type', 'organization', 'group', 'submitter'));
	 		if(isset($iss)) {
	 			$this-> link = $iss;
	 			$this-> link_class = "issue";
	 		}
		}

		// Make a smart guess on who to reply in the conversation
		// -- If last last_message_sender exists and is different from the current logged in user's contact id, that one is used as reply target
		// -- If last_message_sender is equal to the logged in user's contact id, reply_to will be the last contact in the list of contact_ids, which is not the user's contact id
		// -- ... unless there are no choices, in which case the reply_to ID will be the user's own contact id (talking to himself for some weird purpose?)
		if(!empty($convo['last_msg_sender'])) {
			if($convo['last_msg_sender'] != MVC_Core::$ss-> user['contact_id'])
				$reply_to = (int)$convo['last_msg_sender'];
			else {
				// Search the contact ids list in reverse, first result that is not the user's own contact id is chosen
				$rev_contact_ids = array_reverse($convo['contact_ids']);
				foreach($rev_contact_ids as $c_id)
					if($c_id != MVC_Core::$ss-> user['contact_id']) {
					$reply_to = $c_id;
					break;
				}
			}
		}
		// Nothing found, nobody else to reply to, pick the first contact in the list
		if(!isset($reply_to))
			$reply_to = $convo['contact_ids'][0];

		// If Label ID is present, this conversation was loaded as the result of opening it from the label list. Read the current status of the relation.
		if(isset($in-> label_id)) {
			$status = $this-> cnv-> cnv2lab_Read_Status($in-> convo_id, $in-> label_id);
			if($status == 'unread') {
				// This is a conversation which has an unread message, and is being displayed right now to the user:
				// - Read all links that this conversation has to the user
				$r = $this-> cnv-> cnv2lab_Get_Links(MVC_Core::$ss-> user['id'], $in-> convo_id);

				$lab_ids = array();
				if($r)
					foreach($r as $link)
					$lab_ids[] = (int)$link['lab_id'];

				// Change status of all links for the logged in to "normal" and decrease unread count by 1
				if(count($lab_ids)) {
					$this-> cnv-> cnv2lab_Mass_Status_Update($in-> convo_id, $lab_ids, 'normal');
					$this-> user-> update(MVC_Core::$ss-> user['id'], array(
						'count_unread'=> "`count_unread` - 1"
					), true);
				}


				// - Set the container relation as "normal"
				$this-> cnv-> cnv2lab_Update($in-> convo_id, $in-> label_id, "normal");
				// - Reduce the "unread" count for the logged in user by one
				$this-> user-> update(MVC_Core::$ss-> user['id'], array(
					'count_unread'=> '`count_unread` - 1'
				), true);
				MVC_Core::$ss-> user['count']['unread'] = MVC_Core::$ss-> user['count']['unread'] - 1;
			}
		}

		// Hybrid response data
		$data_response = array();
		$data_response['tab_conversation'] = array(
			'type'=> $convo['type'],
			'link_id'=> (int)$convo['link_id'], // Issue ID for "issue" conversations, Broadcaster User ID for broadcasts, Starter Contact ID for "basic" (Conversation creator)
			'reply_to'=> (int)$reply_to // Used to determine who to reply by default in this conversation
		);

		// If this is an issue-linked conversation, send the issue information to JavaScript
		if(isset($iss)) {
			$data_response['tab_conversation']['issue'] = array(
				'id'=> (int)$iss['id'],
				'track'=> $iss['track'],
				'key'=> $iss['access_key'],
				'submitter'=> $iss['submitter']
			);
		}

		if(isset($in-> label_type))
			// Conversation load following an open command in the #list tab
			// -- ??
			switch($in-> label_type) {
				case 'a-inbox':
					break;
				case 'b-sent':
					break;
				case 'c-user':
					break;
		}

		// Return the convo tab title in case it's needed (When loading a conversation directly)
		if($in-> get_tab_title)
			$data_response['tab_conversation']['tab_title'] = $convo['description']? substr($convo['description'], 0, 25).(strlen($convo['description'])>25? "...": ""): "(No subject)";

		// Hybrid response (HTML+JSON)
		$this-> send_JSON_Headers($data_response);
	}

	/**
	 * Prepares the Compose tab
	 *
	 * @param MVC_Request $in
	 */
	private function load_Tab_Compose(MVC_Request $in)
	{
		if(isset($in-> issue_id)) {
			// Compose is connected to an issue id - Attempt to load it TODO: and verify if $in-> issue_track matches ( a bit of security check )
			$this-> factory_Model("Issue");
			$iss = $this-> issue-> read($in-> issue_id, array('issue', 'type', 'organization', 'group', 'submitter'));
			if(isset($iss)) {
				$this-> link = $iss;
				$this-> link_class = "issue";
			}
		}

		// Hybrid response data
		$data_response = array();
		$data_response['tab_compose'] = array();

		// If this is an issue-linked conversation, send the issue information to JavaScript
		if(isset($iss)) {
			$data_response['tab_compose']['type'] = 'issue';
			$data_response['tab_compose']['issue'] = array(
				'id'=> (int)$iss['id'],
				'track'=> $iss['track'],
				'key'=> $iss['access_key'],
				'submitter'=> $iss['submitter']
			);
		} else
			$data_response['type'] = 'basic';

		// Hybrid response (HTML+JSON)
		$this-> send_JSON_Headers($data_response);
	}

	/**
	 * Prepares the Contacts tab ( folders on lift, grid with contacts on right)
	 *
	 * @param MVC_Request $in
	 */
	private function load_Tab_Contacts(MVC_Request $in)
	{
//		TODO: Ability for admin to edit contacts from any organization (all lists)
// 		if(isset(MVC_Core::$ss-> perspective))
// 			$org_id = MVC_Core::$ss-> perspective;

		if(isset(MVC_Core::$ss-> perspective)) {
			// Read some basic information about the organization being loaded (instead of the default)
			$org = $this-> org-> read(MVC_Core::$ss-> perspective, array());
		}

		// TODO: How to test if organization manager and department chief? - Can't remember...

		// Prepare the special contact lists to which the user has access. Edit ability depends on certain user rights.
		$system_containers = array();

		// TODO: Restrict these depending on user's rights to see these lists

		// The own address/contact information - If multiple are used, the user has the ability to link his user account to one of the contacts here.
		// Any incoming message to the user_self contacts will be placed in the user's inbox and the user will be notified of new messages.
		if(!isset($org))
			$system_containers[] = array(
				'id'=> "sysc_us",
				'name'=> "My own address(es)",
				'type'=> "user_self",
				'edit'=> true // User-self are always editable
			);

		// This is the organization the user is part of - Same as with user_self, it should be possible to choose which one is used as the
		$system_containers[] = array(
			'id'=> "sysc_os",
			'name'=> isset($org)? "The organization": "My organization",
			'type'=> "org_self",
			'edit'=> $this-> user-> test_ACL('privileges', 'org_contacts_manager')
		);

		// Contacts visible for everyone in the organization. Editable by users with "org_contacts_manager" right
		if($this-> user-> test_ACL('privileges', 'org_contacts_view')) {
			$system_containers[] = array(
				'id'=> "sysc_org",
				'name'=> isset($org)? $org['name']: MVC_Core::$ss-> organization['name'],
				'type'=> "organization",
				'edit'=> $this-> user-> test_ACL('privileges', 'org_contacts_manager')
			);
			$system_containers[] = array(
				'id'=> "sysc_it",
				'name'=> "Issue targets",
				'type'=> "issue_target",
				'edit'=> $this-> user-> test_ACL('privileges', 'org_contacts_manager')
			);
		}

		// Addresses to which the user has sent messages without selecting an existing contact.
		// There should be an option to convert them to a different type...
		$system_containers[] = array(
			'id'=> "sysc_ao",
			'name'=> "From sent e-mails",
			'type'=> "auto_out",
			'edit'=> true
		);

		$system_containers[] = array(
			'id'=> "sysc_glo",
			'name'=> "Yellow pages",
			'type'=> "global",
			'edit'=> isset(MVC_Core::$ss-> user['is_admin'])
		);

		// User contact containers
		// TODO: The personal contacts labels - type "personal" ; Including a fake label named "All" in which all contacts of type "personal" are shown no matter if they have assigned labels or not

		// Finally, build the full tree
		$label_tree = array(
			array(
				'type'=> 'system_ctx',
				'name'=> "System contacts",
				'children'=> $system_containers
			)//,
//			array(
//				'type'=> 'user_ctx',
//				'name'=> "My contacts",
//				'children'=> $user_containers
//			)
		);

		$this-> label_tree = $label_tree;
	}

	/**
	 * Loads contact information for both viewing and editing
	 *
	 * @param MVC_Request $in
	 */
	private function load_Tab_Vcard(MVC_Request $in)
	{
		$contact = array();
		if(!$in-> contact_id && isset($in-> create)) {
			// In create mode, the returned html contains some specific helpers for the user to start typing in contact informaiton
			$contact['_create'] = array();
			if(isset($in-> init_contact['name']))
				$contact['name'] = $in-> init_contact['name'];
			else {
				$contact['name'] = "(Type the name here)";
				$contact['_create']['name'] = true;
			}
			if(isset($in-> init_contact['email']))
				$contact['email'] = $in-> init_contact['email'];
			else {
				$contact['email'] = "(Enter the e-mail address)";
				$contact['_create']['email'] = true;
			}
		} else {
			$contact = $this-> con-> read($in-> contact_id);
			if(!$contact)
				return;

			// Prepare the contact information
			$contact['xdata'] = $this-> con-> decode_Xdata($contact['xdata']);
		}

		if(isset($in-> edit) || isset($in-> create))
			$contact['_edit'] = true;

		$this-> contact = $contact;

		// Not sure exactly for what is this useful...
		$data_response = array();
		$data_response['tab_vcard'] = true;

		// Hybrid response (HTML+JSON)
		$this-> send_JSON_Headers($data_response);
	}

	/**
	 * Loads and prepares HTML document which will become the content of one of the tabs for the My Organization module
	 *   Loads specific data on demand, for the dynamic content in that tab.
	 */
	public function action_Load_Tab_HTML(MVC_Request $in)
	{
		switch($in-> tab) {
			// The list of containers this user sees: Inbox, Sent, User (customizable), Drafts, Bin (Deleted)
			case 'tab_list':
				$label_tree = $this-> lab-> get_List(MVC_Core::$ss-> user['id'], 'convos');

				// Append system pseudo-labels (not user labels) to the user's label tree
				// -- They load stuff which is not directly connected to a label
/*				$label_tree[] = array(
					'type'=> 'other_comm',
					'name'=> "Others",
					'children'=> array(
						// System-sent notifications are not placed in Inbox, and they are automatically deleted after a while - These are individual messages not inserted into conversations
						array(
							'id'=> "o_s",
							'name'=> "Notifications",
							'type'=> "system",
							'count_msgs'=> 0
						),
						// Pseudo-label which will show all issue-related conversations that this user has access to.
						array(
							'id'=> "o_i",
							'name'=> "Issue communication",
							'type'=> "issue",
							'count_msgs'=> 0
						),
						// Pseudo-container that shows broadcasts (mass messages)
						array(
							'id'=> "o_b",
							'name'=> "Broadcasts",
							'type'=> "broadcast",
							'count_msgs'=> 0
						)
					)
				);*/

				$this-> label_tree = $label_tree;
				break;

			case 'tab_conversation':
				$this-> load_Tab_Conversation($in);
				break;

			case 'tab_compose':
				$this-> load_Tab_Compose($in);
				break;

			// The list of contacts that this user is able to access. Contacts types and containers are shown on the left side, while the contacts grid on the right side (similar to the message grid)
			case 'tab_contacts':
				$this-> load_Tab_Contacts($in);
				break;

			// The contact viewer and editor (in-line editing of contact details)
			case 'tab_vcard':
				$this-> load_Tab_Vcard($in);
				break;
		}

		// This is an ajax request, but needs the template. Initialize the template object on demand
		$this-> init_Template_System();
		echo $this->_drytpl-> render_File("private". DS. "commcenter". DS.  $in-> tab. ".html", $this->_out);
	}

	/**
	 * Reads the conversations linked to a communication label and returns grid-usable data
	 *
	 * Depending on label type, the data returned can be:
	 * -- conversations ( a-inbox, b-sent, c-user ) - TODO: Support server-level paging
	 * -- individual messages ( d-drafts ) - These are messages which haven't yet been "sent" (attached to a conversation, and may or may not have a destination contact ) - No paging
	 *    (Drafts are the result of auto-saving feature in the Compose or Reply modules, and unlike normal messages which can only be "hidden" by users, created drafts are either converted into full-message or can be deleted)
	 * -- conversations AND deleted messages ( e-bin ) - Only browser-level paging
	 * Pseudo-container types (These are not actual saved containers, but entities to which conversations can be linked):
	 * -- system: Notifications are single messages which can't be replied
	 * -- issue: List conversations linked to issues that the user can access
	 * -- broadcast: Broadcasts (or Announcements) are conversations which have only one sender and multiple recipients. Replies can only be sent back to the original sender.
	 *
	 * @param MVC_Request $in
	 */
	public function action_Read_Label_Convos(MVC_Request $in)
	{
		if(!$in-> label_id || !$in-> label_type)
			return $this-> send_JSON(array()); // missing label id, no results

		$rec_list = array();
		switch($in-> label_type) {
			// Read conversations
			case 'a-inbox':
			case 'b-sent':
			case 'c-user':
				$r = $this-> cnv-> get_List("label", $in-> label_id, array('convo', 'starter'), function(QueryFactory $q) {
					// Filter out conversations which are not visible (trash, deleted)
					$q-> where("`c2l`.`status` IN('normal','unread')");
				});
				if(!$r || count($r)==0)
					return $this-> send_JSON(array());

				// Ok, found some conversations in this container, prepare the grid's return data
				foreach($r as $convo) {
					$line = array(
						'status'=> $convo['status'],
						'id'=> (int)$convo['id'],
						'link_id'=> (int)$convo['link_id'],
						'access_key'=> $convo['access_key'],
						'dt'=> date("Y-m-d H:i:s", $convo['last_msg_time']),
						'starter_id'=> (int)$convo['starter']['id'],
						'starter'=> $convo['starter']['name'],
						'descr'=> $convo['description'],
						'count_msgs'=> (int)$convo['count']['msgs']
					);
					$rec_list[] = $line;
				}

				break;
			// Read drafts (individual messages)
			case 'd-drafts':

				break;
			// Read deleted messages and conversations (The function assambles a combination of the two)
			case 'e-bin':
				// For now, just conversations...
				// TODO: This is very similar with the standard one, except for the status filter - Perhaps merge them somehow?
				$r = $this-> cnv-> get_List("label", $in-> label_id, array('convo', 'starter'), function(QueryFactory $q) {
					// Only show conversations of type "trash", while those that are "deleted" are completely invisible and not recoverable via user's interface
					$q-> where("`c2l`.`status` = 'trash'");
				});
				if(!$r || count($r)==0)
					return $this-> send_JSON(array());

				// Ok, found some conversations in this container, prepare the grid's return data
				foreach($r as $convo) {
					$line = array(
						// Status is always "trash", no need to send it
						'id'=> (int)$convo['id'],
						'link_id'=> (int)$convo['link_id'],
						'dt'=> date("Y-m-d H:i:s", $convo['last_msg_time']),
						'starter_id'=> (int)$convo['starter']['id'],
						'starter_name'=> $convo['starter']['name'],
						'descr'=> $convo['description'],
						'count_msgs'=> (int)$convo['count']['msgs']
					);
					$rec_list[] = $line;
				}

				break;
			// Read messages sent by the system (individual messages)
			case 'system':

				break;
			// Read conversations connected to issues (all issues that the user has access to)
			case 'issue':

				break;
			// Read conversations of type "Broadcast" which were either sent or received by this user
			case 'broadcast':

				break;
		}
		$this-> send_JSON($rec_list);
	}

	/**
	 * Changes the relation between a conversation and a communication label
	 * TODO: Review this function to properly support multiple labels
	 * -- Can be used to add another label to a conversation, to move to the special "Trash" label, to restore a conversation from trash, or permanently make a conversation invisible for the user (status = "deleted")
	 * -- This action does some checking, the target label(s) must exist and status must be valid for the target container
	 */
	public function action_Change_Label_Relation()
	{
		$post = new MVC_Request('post');

		// Special operations change the convo 2 container relation in a way not possible to be transmitted directly from the frontend
		if(isset($post-> special_op)) {
			switch($post-> special_op) {
				// Works on individual conversations or messages, it restores them in either "Sent mail" or "Inbox" depending if the sender is the logged in user or someone else
				// (For now, just conversations)
				case 'trash_restore':
					// Read the conversation's metadata to determine sender
					$convo = $this-> cnv-> read($post-> item_id);
					if($convo['type'] != 'basic')
						return $this-> send_Fail_JSON("Attempting to perform a restore operation on an invalid conversation type, possible interface error.");

					// Check if the link_id (which is the sender contact id) matches the logged in user's contact_id - If so, this conversation is sent by the user, restore to "Sent mail" (sentbox_id)
					if($convo['link_id'] == MVC_Core::$ss-> user['contact_id'])
						$target_label_id = MVC_Core::$ss-> user['sentbox_id'];
					else
						$target_label_id = MVC_Core::$ss-> user['inbox_id'];

					// Move the conversation to one of the user's containers
					$r = $this-> cnv-> cnv2lab_Update($convo['id'], $post-> label_id, 'normal', $target_label_id);
					if(!$r)
						return $this-> send_Fail_JSON("Database error: Unable to restore a conversation from the trash container");

					return $this-> send_JSON(array('success'=> true));
				// Sets status on all elements in the "e-bin" to "deleted", to make them invisible for the user
				case 'trash_empty':
					$removed = $this-> cnv-> cnv2lab_Mass_Status_Update(null, $post-> label_id, 'deleted');

					if($removed === false)
						return $this-> send_Fail_JSON("Database error: Unable to empty the trash container");

					return $this-> send_JSON(array('success'=> true, 'removed'=> $removed));
			}
			return $this-> send_Fail_JSON("Special container operation not available, possible interface error.");
		}

		// Check if the target container is a number, then this is the container ID in which the conversation(s) will be moved
		if(is_numeric($post-> target_con)) {
			// Read the target container
			$target_con = $this-> comm-> label_Read($post-> target_con);
		} elseif(!empty($post-> target_con)) {
			// No, it's text, attempt to determine it's id
			$target_con = $this-> comm-> label_Find(MVC_Core::$ss-> user['id'], $post-> target_con);
			if(!$target_con)
				return $this-> send_Fail_JSON("Invalid container type specified, possible interface error.");
		}

		// Check the conversation list
		$convo_list = @json_decode($post-> convos);
		if(!is_array($convo_list) || count($convo_list)<1)
			return $this-> send_Fail_JSON("Conversation list empty or containing wrong data, possible interface error");

		// Check if the specified status is valid for the container type (e-bin only supports statuses "trash", "deleted", the others only "normal" or "unread)
		if(!isset($target_con)) {
			// Nothing to do here, target container not specified
		} elseif($target_con['type'] == 'e-bin') {
			if($post-> status != "trash" && $post-> status != "deleted")
				return $this-> send_Fail_JSON("Wrong conversation status value, possible interface error.");

			// When moving conversations to trash, because the status changes, it is necessary to count how many were unread, for the purpose of decreasing (if necessary) the user's "count_unread" field
			$count_unread = $this-> cnv-> cnv2lab_Count_Status_Of($post-> label_id, "unread", $convo_list);
			if($count_unread > 0) {
				// Some are still unread - Decrease the user's unread count by that amount
				$this-> user-> update(MVC_Core::$ss-> user['id'], array(
					'count_unread'=> '`count_unread` - '.$count_unread
				), true);
				MVC_Core::$ss-> user['count']['unread'] = MVC_Core::$ss-> user['count']['unread'] - $count_unread;
			}
		} else {
			if($post-> status != "normal" && $post-> status != "unread")
				return $this-> send_Fail_JSON("Wrong conversation status value, possible interface error.");
		}

		// Set the container ID for the specified conversation-to-container relations
		$r = $this-> cnv-> cnv2lab_Update($convo_list, $post-> label_id, $post-> status, isset($target_con)? $target_con['id']: null);
		if(!$r)
			return $this-> send_Fail_JSON("Database error: Unable to update conversation to container relation");

		// TODO: Reduce the element count for the source container, and increase it for the target container
		if(isset($target_con) && $target_con['id'] != $post-> label_id) {
// 			dd($post-> label_id, "Current con");
// 			dd(count($convo_list), "Count");
		}

		return $this-> send_JSON(array('success'=> true));
	}

	//==== Conversation tab actions ====//

	/**
	 * Creates a message out of input data and appends it to a conversation, or creates a new conversation (forward to "Add_Message" action)
	 * -- The message is marked as sent by the logged in user's linked contact
	 */
	public function action_Add_Reply()
	{
		$post = new MVC_Request('post');

		// Is it a forward?
		if($post-> mode == 'forward')
			return $this-> action_Add_Message();
		// No, just a reply to current conversation: "convo_id"

		$user = &MVC_Core::$ss-> user; // Current logged in user, he/she becomes the sender of the message

		$recipient = $post-> recipient;
		if(isset($post-> issue)) {
			$issue = $post-> issue;
			$convo_type = "issue";
			$convo_link = $issue['id'];
		} else {
			$convo_type = "basic";
			$convo_link = null;
		}

		// Read the conversation specified
		if(!isset($post-> cnv_id))
			return $this-> send_Fail_JSON("Conversation identifier missing, possible interface error.");

		$convo = $this-> cnv-> read($post-> cnv_id, array('conversation'));
		if(!$convo)
			return $this-> send_Fail_JSON("Conversation not found, possible interface error.");

		// TODO: Put the contact locator ( by e-mail ) into an independent function. It is used both here and in the "add_message", so no point of having a duplicate

		// Check if an exact contact is set as the recipient, if attempt to locate it or create it
		if(!isset($recipient['id'])) {
			// The recipient contact is not precise (but a valid e-mail address might exist)
			if(!isset($recipient['email']))
				return $this-> send_Fail_JSON("Error: Neither a contact ID or E-mail address was present in the request.");

			//== E-mail address descovery ==//

			// 1. When an issue ID exists, attempt to locate the e-mail address first in the public and shared issue-linked contacts first
			if(isset($issue)) {
				$issue_conlinks = $this-> con-> con2iss_Get_List($issue['id'], null, 'partner');
				if(!empty($issue_conlinks)) {
					// Make the search list out of these
					$id_list = array();
					foreach($issue_conlinks as $conlink)
						$id_list[] = (int)$conlink['contact_id'];

					$recipient['id'] = $this-> con-> locate($id_list, $recipient['email'], null, null, null, null, true, true);
				} else
					$recipient['id'] = null;
			}

			// 2. Check if there's a system user with that e-mail address - If so, that user will become the contact. This permits in-system user to user communication without those users having each other's contacts
			if(!$recipient['id'])
				$recipient['id'] = $this-> con-> locate("user_self", $post-> to_email, null, null, null, null, true, true);

			// 3. Still nothing? Attempt to locate an existing "auto_out" contact with that specific e-mail address in the user's address book (to reuse existing "auto_out" contacts)
			if(!$recipient['id'])
				$recipient['id'] = $this-> con-> locate("auto_out", $post-> to_email, null, null, $user['id'], null, true, true);

			// 4. STILL not found? Create an "auto_out" or "isslink" contact from the e-mail address (and name, which should come from JS)
			if(!$recipient['id']) {
				$recipient['id'] = $this-> con-> create(
					isset($issue)? "isslink": "auto_out", // Type of contact is "isslink" ; "isspost" contacts are only used when the issue is initially submitted (posted)
					$user['org_id'], array(
						'email'=> $recipient['email'],
						'name'=> $recipient['name'],
						'count_msgs'=> 0
					), $user['id']
				);

				// Well, if even create failed, there's a serious problem - Abort sending the message
				if(!$recipient['id'])
					return $this-> send_Fail_JSON("Database error: Unable to create contact record for specified e-mail address.");
			}
		}

		// If there is an issue link, connect the contact to the issue. To do that, the create function must check if the contact is not already linked for the specified organization.
		if(isset($issue)) {
			$this-> con-> con2iss_Create($issue['id'], $recipient['id'], array(
				'role'=> "recipient", // Auto created contact, recipient of an issue conversation
				'label'=> "Recipient",
				'visibility'=> "org", // By default, this auto-contact is only visible within the organization
				'org_id'=> $user['org_id'],
				'user_id'=> $user['id']
			));
		}

		// Assamble message data depending on conversation type:
		switch($convo['type']) {

			case 'basic':
				$msg = array(
					'org_id'=> $user['org_id'], // The user's organization id
					'cnv_id'=> $convo['id'],
					'from_id'=> $user['contact_id'],
					'to_id'=> $recipient['id']
				);
				break;

			case 'issue':
				$msg = array(
					'org_id'=> $convo['org_id'], // Same organization as the conversation this message will be connected to
					'cnv_id'=> $convo['id'],
					'from_id'=> $user['contact_id'],
					'to_id'=> $recipient['id']
				);
				break;

			default:
				return;
		}

		// Create the message with the incoming data
		$msg_iid = $this-> comm-> create('user', $post-> content, empty($post-> subject)? null: trim($post-> subject), $msg);
		if(!$msg_iid)
			return $this-> send_Fail_JSON("Database error: Unable to create the message");

		// Add the message to the conversation
		$r = $this-> cnv-> attach($convo['id'], $msg_iid);
		if($r) {
			// Try to see if the target contact is a system user, in that case locate the conversation into the user's labels.
			// -- For all results which are not "trash", set the status to "unread"
			$recipient = $this-> con-> read($recipient['id'], false);

			// TODO: This is very similar to the one in "Add Message", see if they can be combined into a common function

			switch($recipient['type']) {
				// This is a system user contact - The message doesn't go out as an e-mail, unless the user chose to receive messages via e-mail, or just get notified about new messages via e-mail
				case 'user_self':
					// Read the target user information, needed to locate inbox and a few other information
					$to_user = $this-> user-> read($recipient['user_id'], array('user_comm'), false);

					// Check if the user has this conversation linked to any labels
					$r = $this-> cnv-> cnv2lab_Get_Links($to_user['id'], $convo['id']);
					$lab_ids = array();
					if($r)
						foreach($r as $link) {
							// If the link status is "trash" or "deleted", there's nothing to do. The user is not interested in this conversation anymore
							if($link['status'] == 'trash' || $link['status'] == 'deleted') {
								$this-> evan-> add("Your message has been added to the conversation but the recipient has already marked this conversation as &quot;trash&quot; and might not see your message.", "send");
								$this-> send_JSON(array('success'=> true));
							}

							$lab_ids[] = (int)$link['lab_id'];
						}

					// Change status of all links for the target user to "unread" and increase unread count by 1
					if(count($lab_ids)) {
	 					$this-> cnv-> cnv2lab_Mass_Status_Update($convo['id'], $lab_ids, 'unread');
	 					$this-> user-> update($to_user['id'], array(
							'count_unread'=> "`count_unread` + 1"
	 					), true);
					}

					// Was the user's inbox among the list of links ?
					if(!in_array($to_user['inbox_id'], $lab_ids)) {
						// Nope, create the link to the inbox and set it as well to unread.
						$this-> cnv-> cnv2lab_Create_Link($to_user['id'], $convo['id'], $to_user['inbox_id'], "unread");
					}

					// TODO: Check the user preferences about how to be notified when receiving new messages

					break;
					// The message is being sent to the target organization asa a whole. Certain users from that organization will get notified about it and might choose to "take over" the communication,
					// in which case the recipient is converted to that particular user
				case 'org_self':

					break;
					// Same thing as for organization contacts, but only at department level
				case 'dep_self':

					break;
					// In all other cases, this message is sent out as an e-mail without any further processing (outside contact)
				default:
					// ??
			}
			// For now, just send as e-mail anyway (in all cases)
			$this-> comm-> email_Message($msg_iid);

			$this-> evan-> add(sprintf("Your message has been sent to <b>%s</b>.", $recipient['name']), "send");

			// Finally, send the response to JS
			$this-> send_JSON(array('success'=> true,
				'result'=> array(
					'cnv_id'=> $convo['id'],
					'msg_id'=> $msg_iid
				)
			));
		} else
			$this-> send_Fail_JSON("Database error, could not add the message to the conversation");
	}

	/**
	 * Makes sure that the conversation for which this action is called has a valid "access_key", and returns it.
	 * -- Used to display the frontend (semi-public, key based) access to a conversation.
	 */
	public function action_Convo_Access_Key()
	{
		$post = new MVC_Request('post');
		if(!$post-> id)
			return $this-> send_Fail_JSON("Request error: Conversation ID is missing.");

		$convo_update = array(
			'access_key'=> sha1(microtime(1) . uniqid())
		);

		// Update (set) conversation access key
		$r = $this-> cnv-> update($post-> id, $convo_update);
		if(!$r)
			return $this-> send_Fail_JSON("Database error: Unable to set conversation access key.");

		$this-> send_JSON(array('success'=> true, 'access_key'=> $convo_update['access_key']));
	}

	/**
	 * Creates a conversation (Action of the Compose form)
	 */
	public function action_Add_Message()
	{
		$post = new MVC_Request('post');
		$user = &MVC_Core::$ss-> user; // Current logged in user, he/she becomes the sender of the message

		$recipient = $post-> recipient;

		if(isset($post-> issue)) {
			$issue = $post-> issue;
			$convo_type = "issue";
			$convo_link = $issue['id'];
		} else {
			$convo_type = "basic";
			$convo_link = null;
		}
		// TODO: Also support "Broadcast" conversation later (in which case the $recipient is actually a list of recipients

		// Check if the "recipient" is already in the cc_list (usually last one), and remove it
		if(!empty($post-> cc_list)) {
			if(($cc_p = array_search($post-> recipient['email'], $post-> cc_list))!==false) {
				unset($post-> cc_list[$cc_p]);
			}
		}

		if(!isset($recipient['id'])) {
			// The recipient contact is not precise (but a valid e-mail address might exist)
			if(!isset($recipient['email']))
				return $this-> send_Fail_JSON("Error: Neither a contact ID or E-mail address was present in the request.");

			//== E-mail address descovery ==//

			// 1. When an issue ID exists, attempt to locate the e-mail address first in the public and shared issue-linked contacts first
			if(isset($issue)) {
				$issue_conlinks = $this-> con-> con2iss_Get_List($issue['id'], null, 'partner');
				if(!empty($issue_conlinks)) {
					// Make the search list out of these
					$id_list = array();
					foreach($issue_conlinks as $conlink)
						$id_list[] = (int)$conlink['contact_id'];

					$recipient['id'] = $this-> con-> locate($id_list, $recipient['email'], null, null, null, null, true, true);
				} else
					$recipient['id'] = null;
			}

			// 2. Check if there's a system user with that e-mail address - If so, that user will become the contact. This permits in-system user to user communication without those users having each other's contacts
			if(!$recipient['id'])
				$recipient['id'] = $this-> con-> locate("user_self", $recipient['email'], null, null, null, null, true, true);

			// 3. Still nothing? Attempt to locate an existing "auto_out" contact with that specific e-mail address in the user's address book (to reuse existing "auto_out" contacts)
			if(!$recipient['id'])
				$recipient['id'] = $this-> con-> locate("auto_out", $recipient['email'], null, null, $user['id'], null, true, true);

			// 4. STILL not found? Create an "auto_out" or "isslink" contact from the e-mail address (and name, which should come from JS)
			if(!$recipient['id']) {
				$recipient['id'] = $this-> con-> create(
					isset($issue)? "isslink": "auto_out", // Type of contact is "isslink" ; "isspost" contacts are only used when the issue is initially submitted (posted)
					$user['org_id'], array(
						'email'=> $recipient['email'],
						'name'=> $recipient['name'],
						'count_msgs'=> 0
					), $user['id']
				);

				// Well, if even create failed, there's a serious problem - Abort sending the message
				if(!$recipient['id'])
					return $this-> send_Fail_JSON("Database error: Unable to create contact record for specified e-mail address.");
			}
		}
		// If there is an issue link, connect the contact to the issue. To do that, the create function must check if the contact is not already linked for the specified organization.
		if(isset($issue)) {
			$this-> con-> con2iss_Create($issue['id'], $recipient['id'], array(
				'role'=> "recipient", // Auto created contact, recipient of an issue conversation
				'label'=> "Recipient",
				'visibility'=> "org", // By default, this auto-contact is only visible within the organization
				'org_id'=> $user['org_id'],
				'user_id'=> $user['id']
			));
		}

		// Send message to the specified, found or created contact
		$msg = array(
			'org_id'=> $user['org_id'], // Same organization as the user creating the conversation
			'from_id'=> $user['contact_id'], // Message is sent from the user's primary contact
			'to_id'=> $recipient['id']
		);

		// Create the message with the incoming data
		$msg_iid = $this-> comm-> create('user', $post-> content, empty($post-> subject)? null: trim($post-> subject), $msg);
		if(!$msg_iid)
			return $this-> send_Fail_JSON("Database error: Unable to create a new message.");

		// Create the conversation from the message, marking it as started by the logged in user and directed toward the recipient
		$cnv_iid = $this-> cnv-> create($convo_type, $msg_iid, $convo_link, true, true);

		if($cnv_iid) { // Conversation successfully created!

			// Read the recipient contact (even if it was just created) to read its type (system user, system organization, external contact). This determines what kind of processing is done with the message (beside adding it to the database)
			$recipient = $this-> con-> read($recipient['id'], false);

			// TODO: Move this processing out of here, because the same thing can happen in many situations (not just for the "Compose" tab in Comm module)
			switch($recipient['type']) {
				// The message is being sent to the target organization asa a whole. Certain users from that organization will get notified about it and might choose to "take over" the communication,
				// at that time the recipient is updated to that particular user's contact_id
				case 'org_self':

					break;
				// Same thing as for organization contacts, but only at department level
				case 'dep_self':

					break;
				// This is a system user contact - The message doesn't normally go out as an e-mail, unless the user chose to receive messages via e-mail, or just get notified about new messages via e-mail
				case 'user_self':
					// Read the target user id information
					$to_user = $this-> user-> read($recipient['user_id'], array('user_comm'), false);

					// Connect the conversation to the "Inbox" of recipient user, as "unread", and increase the "count_unread" count for that user
					$this-> cnv-> cnv2lab_Create_Link($recipient['user_id'], $cnv_iid, $to_user['inbox_id'], "unread");
					$this-> user-> update($to_user['id'], array(
						'count_unread'=> $to_user['count']['unread']+1
					));

					// TODO: Check the user preferences about how to be notified when receiving new messages

					break;
				// This contact is connected to an issue, if composing an issue-linked message, a different template might be used, which includes a reference to the issue
				case 'isspost':
				case 'isslink':

					break;
				// In all other cases, this message is sent out as an e-mail without any further processing (outside contact)
				default:
					// ??
			}
			// Carbon Copy ?
			if(!is_array($post-> cc_list))
				$cc_list = array();
			else
				$cc_list = $post-> cc_list;

			// For now, just send as e-mail anyway (in all cases)
			$this-> comm-> email_Message($msg_iid, $cc_list);

			// Connect the conversation to the "Sent items" folder of the sender user (even if this is an issue conversation, the user should be able to locate his created messages)
			$this-> cnv-> cnv2lab_Create_Link($user['id'], $cnv_iid, $user['sentbox_id']);

			$this-> evan-> add(sprintf("Your message has been sent to <b>%s</b>.", $recipient['name']), "send");

			// Finally, send the response to JS
			$this-> send_JSON(array('success'=> true,
				'result'=> array(
					'cnv_id'=> $cnv_iid,
					'msg_id'=> $msg_iid
				)
			));
		} else
			$this-> send_Fail_JSON("Database error, could not create the conversation");
	}

	/**
	 * Sends an existing message as an E-mail to the intended recipient contact's e-mail.
	 * - The Communication system is loosely coupled with e-mails, so it is possible to create database records of messages, notify internal users (and other features), without actually sending out e-mails.
	 * - This function allows previously stored messages to be sent (or re-sent) as e-mails, no matter if they were originally sent as e-mails or not.
	 */
	public function action_Email_Message()
	{
		$post = new MVC_Request('post');
		$user = &MVC_Core::$ss-> user; // Current logged in user, he/she becomes the sender of the message

		$r = $this-> comm-> email_Message($post-> msg_id);
		if(!$r)
			return $this-> send_Fail_JSON("E-mail server error: The message could not be send");

		return $this-> send_JSON(array("success"=> true, "response"=> "The E-mail has been sent out."));
	}

	/**
	 * Reads the contacts from an user or system container
	 * @param MVC_Request $in
	 */
	public function action_Get_Contacts(MVC_Request $in)
	{
		$con_id = $in-> label_id;

		if(!$con_id)
			return $this-> send_JSON(array()); // missing container id, no contacts

		$org_id = isset(MVC_Core::$ss-> perspective)? MVC_Core::$ss-> perspective: MVC_Core::$ss-> user['org_id'];

		if(is_numeric($con_id)) {
			// User's contact folder
			$user_or_con_id = (int)$con_id;
			$type = "container";
		} else {
			// System contact folder (what is read might depend on user_id)
			$user_or_con_id = MVC_Core::$ss-> user['id'];
			$type = $in-> label_type;
		}

		$contacts = $this-> con-> get_List($org_id, $user_or_con_id, $type, array("contact", "xdata"));
		if($contacts)
			$this-> send_JSON($contacts);
		else
			$this-> send_JSON(array());
	}

	/**
	 * Helper function for reading a contact and assambling data for the viewer/editor
	 *
	 * @param array $contact Raw contact data
	 * @param array $fields_structure Preloaded contact fields structure, used for decoding xdata
	 * @param string $con_id A verification process, to make sure the specified contact is actually part of the virtual/real container
	 */
	private function decode_Contact(&$contact, $fields_structure, $con_id = null)
	{
		if(!is_null($con_id)) {
			// verify container
			switch($con_id{0}) {
				// System pseudo-containers
				case 'u':
					break;
				case 'm':
					break;
				case 'o':
					break;
				case 'c':
					break;
				case 'g':
					break;
				// User container
				default:
					if($contact['con_id'] != $con_id)
						return false;
			}
		}

		// Format and decode xdata
		$xdata_trans = array();
		$used_fields = array();
		if(empty($contact['xdata']))
			$contact['xdata'] = array();

		foreach($fields_structure as &$group) {
			foreach($group['fields'] as $key=> $field) {
				// Avoid field duplication (some fields might be specified in the menu in multiple places)
				if(isset($used_fields[$key]))
					continue;

				// Autogen group fields will be set even if there is nothing encoded for these fields in the xdata - They serve as a guideline to the user for entering contact data
				if(isset($group['meta']['autogen']) && $group['meta']['autogen'] == true && !isset($contact['xdata'][$key]))
					$contact['xdata'][$key] = "";

				// Non mandatory fields are only shown if the data actually exists in $contact['xdata']
				if(isset($contact['xdata'][$key])) {
					$xdata_trans[] = array(
						'key'=> $key,
						'label'=> $field['field_label'],
						'info'=> (string)$contact['xdata'][$key] // In the future, they will be more than text (perhaps complex editors - phone number with country selector, date selector, ...
					);
					$used_fields[$key] = true;
				}
			}
		}

		$contact['xdata_trans'] = $xdata_trans;
	}

	/**
	 * Reads and returns the Warehouse stored picture matching this contact
	 *
	 * @param MVC_Request $in
	 */
	public function action_Get_Contact_Picture(MVC_Request $in)
	{
		// TODO: Check if coresponding picture (linked_with = "contact") exists, and return it, if not return a system "no-picture" image

		// for now, just return the "no-picture" stream
		header("Content-Type: image/png");
		readfile(ROOT. DS. "htdocs". DS. "img". DS. "extra". DS. "NoPicture.png");
	}

	/**
	 * Updates contact information
	 *
	 * @param MVC_Request $in
	 */
	public function action_Save_Contact(MVC_Request $in)
	{
		$post = new MVC_Request('post');

		$new_contact_data = array(
			'name'=> $post-> name,
			'email'=> $post-> email
		);
		if($post-> xdata != "{}")
			$new_contact_data['xdata'] = $post-> xdata;
		else
			$new_contact_data['xdata'] = null;

		if($post-> id) {
			// Edit mode
			$r = $this-> comm-> contact_Update($post-> id, $new_contact_data, true);
			if($r)
				$this-> send_JSON(array('success'=> true));
			else
				$this-> send_Fail_JSON("Contact could not be updated, possible database error.");
		} else {
			// Create mode

			// Depending on type, $org_id, $user_id and $con_id are set up
			$con_id = null;
			$org_id = isset(MVC_Core::$ss-> perspective)? MVC_Core::$ss-> perspective: MVC_Core::$ss-> user['org_id'];
			$user_id = MVC_Core::$ss-> user['id'];
			switch($post-> type) {
				// User's own address(es)
				case 'user_self':
					break;
				// User's organization's address(es)
				case 'org_self':
					break;
				// Organization wide contacts
				case 'organization':
					break;
				// Addresses automatically created (by submitting issues, by sending out e-mails to new (unknown) addresses, by incoming e-mails from addresses which are not yet in the system)
				case 'customer':
				case 'auto_out':
				case 'auto_in':
					return $this-> send_Fail_JSON("You should not be able to create in this container normally. (Will be fixed later)");
				// Custom user containers
				case 'personal':
					$con_id = (int)$in-> linkto;
					break;
			}

			$r = $this-> comm-> contact_Create($post-> type, $org_id, $user_id, $new_contact_data, $con_id, true);

			if($r)
				$this-> send_JSON(array('success'=> true));
			else
				$this-> send_Fail_JSON("Contact could not be created, possible database error.");
		}

	}

	public function action_Delete_Contact(MVC_Request $in)
	{
		$post = new MVC_Request('post');

		if(!$post-> id)
			return $this-> send_Fail_JSON("Contact ID not present in the request.");

		// Edit mode
		$r = $this-> comm-> contact_Delete($post-> id);
		if($r)
			$this-> send_JSON(array('success'=> true));
		else
			$this-> send_Fail_JSON("Contact could not be deleted, possible database error.");
	}

}