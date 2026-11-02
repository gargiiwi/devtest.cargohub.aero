<?php
/**
 * Contact Account management overview - Shows the logged in contact account or staff user information, any suplimentary managed accounts (in a grid), and a list of possible locations to go to (Submitted issues, Submit new issue, Etc.)
 *
 * @author Wavetrex (wavetrex@gmail.com)
 * @package CargoITS
 * @category Controller
 * @version 2.4
 */
class App_Ctrl_Contact_Account extends App_Ctrl_Base
{
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
	 * Makes initializations useful for any action
	 */
	public function __construct()
	{
		parent::__construct();

		// Communication related models
		$this-> cnv = $this-> factory_Model("Conversation", "cnv");
		$this-> con = $this-> factory_Model("Contact", "con");
	}

	/**
	 * Contact Account overview page
	 *
	 * @param MVC_Request $in
	 */
	public function action_Index(MVC_Request $in)
	{
		// Prepare the contact data
		if(MVC_Core::$ss-> user) {
			$user = MVC_Core::$ss-> user;
			$contact = array(
				'id'=> $user['contact_id'],
				'name'=> $user['name'],
				'email'=> $user['email'],
				'user_id'=> $user['id']
			);
		} elseif(MVC_Core::$ss-> contact_account) {
			$contact = MVC_Core::$ss-> contact_account['contact'];
		} else {
			// Not logged in ? Save location and attempt to login.
			$this-> save_Current_Location("contact_login", $in-> request_toArray(), "Free-access account", false, true); // last "true" = Remove any Query String from the URL (anything after ?, including)
			return $this-> redirect("/contact/access?action=login");
		}

		// Fix for browser not sending #hash
		if($in-> hash)
			return $this-> redirect("/contact/account#".$in-> hash);

		// setup frontend config
		$this-> CFG['components'] = array("modal", "contact_editor");
		array_push($this-> CFG['modules'], "contact_account");
		$this-> CFG['jqwidgets'][] = array("tabs", "scrollbar", "listbox", "dropdownlist", "input");
		$this-> CFG['jqwidgets'][] = array("calendar", "datetimeinput", "-gridcomplete");
		$this-> CFG['jqwidgets'][] = array("data.export", "grid.export");
		$this-> CFG['contact_account'] = array();
		$this-> CFG['xjs'] = array(
			'jqform' => true
		);

		// prepare output
		$this-> prepare_Header("Contact account", array("userbar", "evan"));
		$this->_out['header']['rightmenu'] = array();

		$this->_out['header']['rightmenu'][] = array('spacer'=> true);
		$this->_out['header']['rightmenu'][] = array(
			'link'=> "/contact/account#issues",
			'text'=> $contact['email']
		);

		$this-> contact = $this-> CFG['contact_account']['contact'] = $contact;
// 		if(!empty(MVC_Core::$ss-> contact_account['managed_ctx'])) {
// 			$this-> CFG['contact_account']['managed_ctx'] = explode(',', MVC_Core::$ss-> contact_account['managed_ctx']);
// 		}

		// Contact Account tabs, in the order they will be presented to the user:
		// TODO: Maybe a preference of changing this order?
		$all_available_tabs = array(
			// Overview of the contact account - Lists the basic contact info (Name/Email), and possible actions.
			// DISABLED - Not useful
			// User's current Access Control Lists, shows the user's privileges and other data compiled from both the organization features column and the calculated access_ids
			array(
				'id'=> "tab_issues",
				'title'=> "Submitted data",
				'icon'=> "tab_issues.png"
			),
			// Other managed contacts, if it exists
// 			array(
// 				'id'=> "tab_managed",
// 				'title'=> "Other contacts",
// 				'icon'=> "book_addresses.png"
// 			),
			// Displays full contact information (with edit ability) - Using "contact_editor" system
			array(
				'id'=> "tab_vcard",
//				'title'=> $contact['email'],
				'title'=> 'Profile',
				'icon'=> "tab_vcard.png"
			),
			array(
				'id'=> "tab_premium",
				'title'=> "Premium Account",
				'icon'=> "tab_premium.png"
			),
//                     IWI front end user record list page new design start
//			array(
//				'id'=> "tab_submit",
//				'title'=> "Submit new",
//				'icon'=> "add.png"
//			)
//                     IWI front end user record list page new design end
			// User's group access (this is also an ACL), presented as a table, in which the user can set some options for each group he has access to
			// TODO: This will be done a bit later, to shorten the time to update
// 			array(
// 				'id'=> "tab_comm",
// 				'title'=> "Communication",
// 				'icon'=> "comments.png"
// 			)
		);

		$this-> contact_tabs = $all_available_tabs;

		//== Contact editor JS data ==//

		// Fields structure for the contacts editor
		MVC_NLS::load_Groups("contact");
		$fields_structure = MVC_NLS::get_Object("contact", true);

		// Process the fields stucture a bit before sending it to JS
		foreach($fields_structure as &$group) {
			foreach($group['fields'] as $field=> $string) {
				if(strpos($string, "~"))
					$group['fields'][$field] = explode("~", $string);
			}
		}

		$this-> CFG['contact_account']['vcard_fields_structure'] = $fields_structure;

		// which body template to use, and run the view
		$this-> site_section = "contact". DS. "account.html";
		if(!empty(MVC_Core::$ss-> domain_CFG['html_settings']['embedded']))
			$this-> view("public_embedded.html");
		else
			$this-> view("public.html");
	}

	/**
	 * Loads contact information for editing or creating a new contact
	 *
	 * @param MVC_Request $in
	 */
	private function load_Tab_Vcard(MVC_Request $in)
	{
		// Check if a contact_id is present in the request (load one of the extra managed contacts).
		if($in-> contact_id) {

		} elseif($in-> create) {
			// No contact id, but the "create" flag is set - it means the vcard tab is in "create new contact" mode
			$contact['_create'] = array(
				'name'=> true,
				'email'=> true
			);
			$contact['name'] = "(Type the name here)";
			$contact['email'] = "(Enter the e-mail address)";
		} else {
			// No contact id, no create mode - Edit the logged in contact
			if(MVC_Core::$ss-> user)
				$contact_id = MVC_Core::$ss-> user['contact_id'];
			elseif(MVC_Core::$ss-> contact_account)
				$contact_id = MVC_Core::$ss-> contact_account['id'];
			else
				// Not logged in ? WTF.
				return;

			$contact = $this-> con-> read($contact_id);
			if(!$contact)
				// Not found...
				return;

			// Prepare the contact information
			$contact['xdata'] = $this-> con-> decode_Xdata($contact['xdata']);
		}

		// This tab is always in "edit" mode
		$contact['_edit'] = true;
		$contact['edit_mode'] = true;

		$this-> contact = $contact;

		// Sends to the frontend the fact that the contact has loaded correctly and the contact editor can be initialized
		$data_response = array();
		$data_response['tab_vcard'] = true;

		// Hybrid response (HTML+JSON)
		$this-> send_JSON_Headers($data_response);
	}

	/**
	 * Loads the list of issues that a staff user or contact account has submitted.
	 * Also prepares the list of issue types which the logged in entity should be able to see as the selector of issue type in the grid.
	 *
	 * @param MVC_Request $in
	 */
	private function load_Tab_Issues(MVC_Request $in)
	{
		if(MVC_Core::$ss-> user) {
			$user = MVC_Core::$ss-> user;

			$acc_data = array();
			// The module requires the list of issue types available for the user
			if($this-> user-> is_Unrestricted())
				$acc_data['ist_list'] = null; // Admin has no restriciton on issue types
			else
				$acc_data['ist_list'] = !empty($user['acls']['ist'])? $user['acls']['ist']: null;
		} elseif(MVC_Core::$ss-> contact_account) {
			// Contact Account has that information in the xdata column (List of all types that were ever submitted using this e-mail address)
			// FIXME: Because ist_list not updated in xdata, just use no filter for now
			$acc_data = MVC_Core::$ss-> contact_account['cac_xdata'];
			$acc_data['ist_list'] = null;
		} else {
			// TODO: A better error message loaded in the tab.
			echo "Apparently there is no information in the session... how did that happen?";
			return;
		}

		$data_response = array();
		$data_response['ist_selector'] = array();

		// If this account has a list of issue types which might contain issues under this e-mail address, read the issue types to find the xfield which will be displayed in the grid
		$this-> factory_Model('Issue');
		$ist_r = $this-> issue-> ist_Get_List($acc_data['ist_list']);
		if($ist_r) {
			foreach($ist_r as $ist) {
				// List of available issue types (name+id), for the Issue Type filter selector
				$data_response['ist_selector'][] = array(
					'id'=> (int)$ist['id'],
					'name'=> $ist['name'],
					'key'=> $ist['key']
				);
			}
		}

		// Hybrid response (HTML+JSON)
		$this-> send_JSON_Headers($data_response);
	}

	/**
	 * Loads and prepares HTML document which will become the content of one of the tabs for the Contact Account module
	 * -- Loads specific data on demand, for the dynamic content in that tab.
	 *
	 * @param MVC_Request $in
	 */
	public function action_Load_Tab_HTML(MVC_Request $in)
	{
		// Group depenent data which might be needed for the template (nothing yet)
		switch($in-> tab) {
			case 'tab_overview':

				if(MVC_Core::$ss-> user)
					$contact_id = MVC_Core::$ss-> user['contact_id'];
				elseif(MVC_Core::$ss-> contact_account)
					$contact_id = MVC_Core::$ss-> contact_account['id'];
				else {
					// TODO: A better error message loaded in the tab.
					echo "Apparently there is no information in the session... how did that happen?";
					return;
				}

				// Read the contact info
				$contact = $this-> con-> read($contact_id);
				if(!$contact) {
					// TODO: LoL... see above.
					echo "Well, how the Jumpin' Jupiter did you login with a contact that doesn't exist ?";
					return;
				}

				// Prepare the contact information
				$contact['xdata'] = $this-> con-> decode_Xdata($contact['xdata']);

				$this-> contact = $contact;
				break;
			case 'tab_issues':
				$this-> load_Tab_Issues($in);
				break;
// 			case 'tab_managed':
// 				break;
			case 'tab_vcard':
				$this-> load_Tab_Vcard($in);
				break;
			// Premium tab shows a CMS page inside it
			case 'tab_premium':
				$m_cms = new App_Model_CMS();
				$page = $m_cms-> read("/marketing/premium");
				echo $page['contents'];
				return;
		}

		// This is an AJAX request, but needs the template as it's a HTML response (not JSON or XML), so, initialize the template object on demand
		$this-> init_Template_System();
		echo $this->_drytpl-> render_File("contact". DS. $in-> tab. ".html", $this->_out);
	}

	/**
	 * Returns a list of e-mails which are associated with the logged in Contact Account (read the contacts from the fields "contact_id" and "managed_ctx")
	 */
	public function action_Get_Emails()
	{
		// System users have one primary E-mail and more possible aliases (can be defined by Admin)
		if(MVC_Core::$ss-> user) {
			$user = MVC_Core::$ss-> user;
			return $this-> send_JSON(array(array(
				'id'=> $user['contact_id'],
				'name'=> $user['name'],
				'email'=> $user['email']
			)));
		} elseif(!MVC_Core::$ss-> contact_account)
			$this-> send_JSON(array()); // WTF?

		if(!MVC_Core::$ss-> contact_account)
			$this-> send_JSON(array()); // WTF?

		$cac = MVC_Core::$ss-> contact_account;
		$contacts = array();
		// First, the logged in contact itself is added to the list
		$contacts[] = array(
			'id'=> (int)$cac['id'],
			'name'=> $cac['contact']['name'],
			'email'=> $cac['contact']['email']
		);
		$dupe_verify = array($cac['contact']['name'].'|'.$cac['contact']['email']);
		// Second, if there are managed contacts, they are read and added as well
		if(!empty($cac['managed_ctx'])) {
			$managed_ctx = $this-> con-> get_List(null, explode(',', $cac['managed_ctx']), 'idlist', array('allstates'));
			if($managed_ctx) {
				foreach($managed_ctx as $con) {
					$dupe_check = $con['name'].'|'.$con['email'];
					if(in_array($dupe_check, $dupe_verify))
						continue;
					$contacts[] = array(
						'id'=> (int)$con['id'],
						'name'=> $con['name'],
						'email'=> $con['email']
					);
					$dupe_verify[] = $dupe_check;
				}
			}
		}

		$this-> send_JSON($contacts);
	}

}