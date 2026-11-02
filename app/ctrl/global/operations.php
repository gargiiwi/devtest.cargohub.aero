<?php
/**
 * Global Operations Controller (actions which could be called from any module)
 *
 * @author Wavetrex Infinity (wavetrex@gmail.com)
 * @package CargoConnect
 * @subpackage Controllers
 * @version 2.7
 */
class App_Ctrl_Global_Operations extends App_Ctrl_Base
{
	/**
	 * Heartbeat function, checks if the session is still active on the server side, if not forces the user to the index page.
	 * -- Also provides notifications on events that happen on the server independently on the current logged in user's action (like session being remotely killed, or new incoming message, etc.)
	 */
	public function action_HeartBeat()
	{
		if(($session=$this-> user-> check_Session())!==false) {
			$r = array(
				'logon'=> $session['logon'],
				'ss'=> $session['dt'],
				'lo'=> $session['count_liftoffs'],
				'evan'=> array()
			);

			$async = &MVC_Core::$ss-> evan['async'];

			// HeartBeat is the only AJAX request that runs the EVAN Analyser
			if(isset(MVC_Core::$ss-> user['check_evn'])) {
				$ntf_bar_id = $this-> evan-> analyse_Async_Evn();
				unset(MVC_Core::$ss-> user['check_evn']);
			} else
				$async['changed'] = false;

 			// If the notification bar was changed, update it on the JS side of the heartbeat
 			if($async['changed']) {
 				if($async['bar_id'])
	 				$r['evan'][] = $this-> evan[$async['bar_id']]-> prepare_Display();
 				else
 					$r['evan_close'] = array($ntf_bar_id); // Remotely close bars that may be visible
  				$r['update_notify'] = true;
 			}

			// Was external stuff injected in the session ?
			if(isset(MVC_Core::$ss-> user['external'])) {
				// Is it something EVAN related ?
				if(isset(MVC_Core::$ss-> user['external']['evan'])) {
					// We have evan messages injected !
					foreach(MVC_Core::$ss-> user['external']['evan'] as $msg)
						$r['evan'][] = $this-> evan-> add($msg['message'], isset($msg['type'])? $msg['type']: "notify", isset($msg['opts'])? $msg['opts']: null)-> prepare_Display();

					// Done !
					unset(MVC_Core::$ss-> user['external']['evan']);
				}
			}
			$this-> send_JSON($r);
		} else {
			$this-> evan-> reset();
			$this-> evan-> add(MVC_NLS::get_Object("evan.user.sessionexpired", true), "lock");
			return header("HTTP/1.0 408 Session timed out. Please relogin");
		}
	}

	/**
	 * Server-sent EVAN notification was closed by the user or a timer expiry.
	 * -- The message should be removed from the EVAN Session memory
	 */
	public function action_Evan_Close(MVC_Request $post)
	{
		unset($this-> evan[$post-> id]);
	}

	/**
	 * User preferences are a global item, which can be called from any module/submodule with controls to change a preference setting
	 * -- This method is merging any existing preferences with new incoming values, optionally removing "null" ones
	 * -- Preferences themselves are saved in the User_Profiles table under "preferences" key
	 */
	public function action_Save_Preferences(MVC_Request $post)
	{
		$preferences = @json_decode($post-> preferences, true);

		if(!is_array($preferences))
			$preferences = array();
		// merge new preferences with existing ones (which are in session)
		if(!empty(MVC_Core::$ss-> user['preferences']))
			$preferences = array_merge(MVC_Core::$ss-> user['preferences'], $preferences);

		// null values are eliminated from $preferences before encoding
		foreach($preferences as $key=> $value)
			if(is_null($value) || (is_array($value) && count($value)==0))
				unset($preferences[$key]);

		// Was theme changed ? If so, make the required visual change to the user-menu-bar and notify the user about the change
		if(isset($preferences['jqx_theme']) && $preferences['jqx_theme'] != MVC_Core::$ss-> user['preferences']['jqx_theme']) {
			$themes = MVC_Core::$spyc->loadFile(ROOT . DS . 'app' . DS . 'data' . DS . 'themes.yaml');
			$this-> evan-> add(sprintf("Your visual theme has been changed successfully to: <b>%s</b> !", $themes[$preferences['jqx_theme']]['display']), "ok");
		}

		// When issue type is changed, switch the type and notify the user.
		if(isset($preferences['rt_id']) && $preferences['rt_id'] != MVC_Core::$ss-> user['preferences']['rt_id']) {
			if(!isset(MVC_Core::$cache-> record_types_index))
				return header("HTTP/1.0 403 The record type with id: {$preferences['rt_id']} doesn't exist or is not valid for your account.");

			$this-> rt_Switch($preferences['rt_id']);
			$this-> evan-> add("Active Record Type has been changed successfully to: <b>". MVC_Core::$cache-> current_rt['name']. "</b>", "ok");
		}

		// Save the merged preferences and also Store inside the session as well (so they are properly used) - Normally these are only read at login time.
		$r = $this-> user-> profile_Write(MVC_Core::$ss-> user['id'], "preferences", $preferences);
		if(!$r)
			return header("HTTP/1.0 500 Unable to save updated user preferences ! Nothing was changed");

		MVC_Core::$ss-> user['preferences'] = $preferences;

		// Optionally, refresh session after preferences have been saved (so changes can take effect immediatly). Otherwise changes are effective only after next logon
		if($post-> update_session)
			$this-> user-> update_Session(MVC_Core::$ss-> user['id']);

		$this-> send_JSON(array('success'=> true));
	}

	/**
	 * Clears the user preferences, reseting to the system or organization wide defaults.
	 */
	public function action_Reset_Preferences(MVC_Request $post)
	{
		$this-> user-> profile_Write(MVC_Core::$ss-> user['id'], "preferences", null); // DELETE record

		// Optionally, refresh session after preferences have been saved (so changes can take effect immediatly). Otherwise changes are effective only after next logon
		if($post-> update_session)
			$this-> user-> update_Session(MVC_Core::$ss-> user['id']);

		$this-> evan-> add("Preferences have been reset to defaults. <i>Some settings are effective only after next login</i>.", "ok");
		$this-> send_JSON(array('success'=> true));
	}

	/**
	 * Tests the master handler organization for a certain group ID.
	 * -- This is used to determine what will happen to an issue if it were to be submitted to a certain group
	 *
	 * -- Returns basic the organization data if "master_id" exists in group xdata
	 * -- If E-mail forward is on it reads the list of contacts of type "org_self", to be available for e-mail forwarding selector
	 *
	 * @param MVC_Request $in
	 */
	public function action_Test_Group_Master(MVC_Request $in)
	{
		$m_group = $this-> factory_Model("Group", null, $in-> group_type);
		$group_data = $m_group-> read($in-> group_id);

		$group_data['xdata'] = @json_decode($group_data['xdata'], true);
		if(is_array($group_data['xdata']) && isset($group_data['xdata']['master_id'])) {
			// the master handler organization id
			$master_id = (int)$group_data['xdata']['master_id'];

			$org = $this-> org-> read($master_id, array('organization', 'contacts_org_self'));

			// Basic contact information ( email, name ) - Perhaps others from xdata?
			// TODO: Contacts are only necessary when forward is ON
			$contacts = array();
			if(isset($org['contacts']) && is_array($org['contacts']))
				foreach($org['contacts'] as $contact)
					$contacts[] = array(
						'id'=> (int)$contact['id'],
						'email'=> $contact['email'],
						'name'=> $contact['name']
					);

			$this-> send_JSON(array(
				'success'=> true,
				'forward_on'=> !(isset($org['features']['active']) && $org['features']['active']), // When organization is not active, e-mail forward is on
				'org'=> array(
					'name'=> $org['name'],
					'domain'=> $org['features']['primary_domain'],
					'auto_email'=> isset($org['features']['auto_email'])? (int)$org['features']['auto_email']: null,
					'contacts'=> $contacts
				)
			));
		} else
			return $this-> send_Fail_JSON("Group's Master handler Not found");
	}

	/**
	 * Saves the current url (location) in the session with the purpose of coming back to this point after any number of clicks and loads.
	 * -- Supports multiple saved locations with text-based keys
	 *
	 * @param MVC_Request $in
	 */
	public function action_Save_Location(MVC_Request $in)
	{
		if(empty($in-> key))
			return $this-> send_Fail_JSON("Error, unable to save location without valid key.");

		$this-> save_Current_Location($in-> key, $in-> data, $in-> label, true);
		$this-> send_JSON(array('success'=> true));
	}

	/**
	 * Reads a saved location from JS (with the purpose of redirecting back to it).
	 * -- Location may be removed from the session if $in-> remove exists and evaluates to "true"
	 * -- If not found, it returns a fail_JSON
	 *
	 * @param MVC_Request $in
	 */
	public function action_Read_Location(MVC_Request $in)
	{
		$location = $this-> get_Location($in-> key, $in-> remove);

		if($location === false)
			return $this-> send_Fail_JSON("Error, location not found or invalid key.");

		$this-> send_JSON(array('success'=> true, 'location'=> $location));
	}

	/**
	 * Universal function to change various session parameters on the fly as the result of an action from the javascript frontend
	 * (Not permanent settings like the preferences, which are also saved into the database)
	 * @param MVC_Request $in
	 */
	public function action_Set_Session_Setting(MVC_Request $in)
	{
		if(!isset($in-> value))
			return $this-> send_Fail_JSON("No value received, possible configuration error.");

		switch($in-> setting) {
			// Affects the user preference for displaying files order in the attachments panel, issue overview module
			case 'issue_files_order':
				if(isset($in-> value))
					MVC_Core::$ss-> user['preferences']['issue_files_order'] = $in-> value;
				break;
				// Unrecognized setting
			default:
				return $this-> send_Fail_JSON("Unrecognized setting, possible interface error.");
		}

		$this-> evan-> add("Temporary setting changed successfuly".(isset($in-> setting_info)? $in-> setting_info: ""), "ok");
		$this-> send_JSON(array('success'=> true));
	}

	/**
	 * Reads a set of canned messages and for certain usage classes also the item they will be used with, running the content/title through the template system
	 * - Usually used when building a message from existing data for a fast message sending
	 *
	 * TODO: Create a global class related to messaging, which may contain this method and other messages related ones.
	 *
	 * =OBSOLETE= Going to get replaced by Get_Templates/Render_Template
	 *
	 * @param MVC_Request $in
	 */
	public function action_Get_Canned(MVC_Request $in)
	{
		// Who is logged in ?
		if(isset(MVC_Core::$ss-> user))
			$user = MVC_Core::$ss-> user;
		elseif(isset(MVC_Core::$ss-> contact_account))
			$cac = MVC_Core::$ss-> contact_account;
		else
			$visitor = true;

		if(!$in-> usage_class)
			return $this-> send_JSON(array(array(
				'title'=> "Interface Error - Missing usage class",
				'message'=> "The canned messages class is missing from the request."
			)));

		// Determine the organization for which the canned messages are read.
		if($in-> org_id) {
			$org_id = (int)$in-> org_id;
			// Check if the organization exists
			$org = $this-> org-> read($org_id, array());
			if(!$org)
				return $this-> send_JSON(array(array(
					'title'=> "Database Error - Missing Organization ID",
					'message'=> "The organization for which canned messages are supposed to be read does not exist or is deactivated."
				)));

			$orgtype_id = $org['type_id'];

		} elseif(isset($user)) {
			$org_id = $user['org_id'];
			$orgtype_id = MVC_Core::$ss-> organization['type_id'];
		}

		// Prepare the query data
		switch($in-> usage_class) {
			// These canned messages are associated with an issue, as they may contain variables which are valid for a loaded issue.
			case 'issreply':
			case 'issmessage':
			case 'issletter':
				// The Issue ID must be present in the request
				if(!$in-> issue_id || !is_numeric($in-> issue_id))
					return $this-> send_JSON(array(array(
						'title'=> "Interface Error - Missing Record ID",
						'message'=> "Attempting to read the list of canned messages of class &quot;{$in-> usage_class}&quot; without a Record ID in the request."
					)));

				// Read the involved issue
				$this-> factory_Model('Issue');
				$iss = $this-> issue-> read($in-> issue_id, array('issue', 'organization', 'group', 'submitter', 'submitter_xdata', 'assigned', 'xfields'), null, null, null, array('serialize'=> true));
				break;
		}

		$m_can = new App_Model_Canned();;
		$canned = $m_can-> get_List($org_id, $orgtype_id, isset($user)? $user['id']: null, isset($iss)? $iss['type_id']: null, $in-> usage_class, true); // true = Read the template too, for message assembly

		if($canned && count($canned)>0) {
			// we have at least one message template, prepare the data

			// Global content ( domain, html )
			$tpl_data = array('global'=> array(
				'domain'=> MVC_Core::$ss-> domain_CFG['domain'],
				'title'=> MVC_Core::$ss-> domain_CFG['html_settings']['title']
			));

			if(isset($iss)) {
				$tpl_data['issue'] = $iss;
				$tpl_data['ist'] = $this-> issue-> ist_Read($iss['type_id']);
			}

			// Logged in user's info (user, department, organization)
			if(isset($user)) {
				$tpl_data['organization'] = MVC_Core::$ss-> organization;
				$tpl_data['department'] = MVC_Core::$ss-> department;
				$tpl_data['user'] = $user;
				// Read the user's linked contact information so it can be used in the template
				$m_con = new App_Model_Contact();
				$tpl_data['user']['contact'] = $m_con-> read($user['contact_id']);
			}

			$this-> init_Template_System();

			//  Apply the data on each of the templates and return the assambled messages
			$all = array();
			foreach($canned as $can_msg) {
				// Attempt to render canned messages one by one, and mark defective ones
				try {
					$all[] = array(
						'description'=> $can_msg['description'],
						'title'=> $this->_drytpl-> render_String($can_msg['title'], $tpl_data),
						'message'=> $this->_drytpl-> render_String($can_msg['template'], $tpl_data)
					);
				} catch(Exception $e) {
					$all[] = array(
						'description'=> "***** ERROR ***** on: ".$can_msg['description'],
						'title'=> "***** Canned message ERROR *****",
						'message'=> "<h2>".$e-> getMessage()."</h2><p>Please check Canned Messages in the Organization module, an error has slipped into this template and the software is unable to construct the message"
					);
				}
			}

			$this-> send_JSON($all);
		} else
			$this-> send_JSON(array(array(
				'title'=> "No canned messages",
				'description'=> "No canned messages",
				'message'=> "There are no canned messages currently available for you."
			)));
	}

	/**
	 * Followed by a grid operation which affects returned data (filter, sort, page size, page number), saves the current values into the session so the grid can be reloaded within the same state
	 * - Can also reset the grid state by passing "state=false"
	 * @param MVC_Request $post
	 */
	public function action_Save_Grid_State(MVC_Request $post)
	{
		if($post-> state == 'false') {
			// Is it a session state clear?
			unset(MVC_Core::$ss-> cache['grid_state'][$post-> grid_id]);
			$this-> evan-> add("Grid state has been reset to default", "ok");
			return $this-> send_JSON(array('success'=> true));
		}

		$grid_state = json_decode($post-> state, true);
		if(empty($grid_state))
			return header("HTTP/1.0 503 There was an error saving the grid state. You can ignore this message but if it repeats please contact support");

		// Save the state into the session
		if(!isset(MVC_Core::$ss-> cache['grid_state']))
			MVC_Core::$ss-> cache['grid_state'] = array();
		MVC_Core::$ss-> cache['grid_state'][$post-> grid_id] = $grid_state;
		$this-> send_JSON(array('success'=> true));
	}

	/**
	 * Persistent docklet system / Stores panel configuration in the user profiles
	 * @param MVC_Request $post
	 */
	public function action_Save_Docking_Layout(MVC_Request $post)
	{
		if($post-> layout == 'false') {
			// Layout clear (reset to default)
			$this-> user-> profile_Write(MVC_Core::$ss-> user['id'], "docklet_".$post-> dock_id);
			$this-> evan-> add("Docking layout has been reset to default", "ok");
			return $this-> send_JSON(array('success'=> true));
		}

		// Save the layout (data is already JSON, store as-is)
		$this-> user-> profile_Write(MVC_Core::$ss-> user['id'], "docklet_".$post-> dock_id, $post-> layout, true);
		$this-> send_JSON(array('success'=> true));
	}


	/**
	 * Reads the list of available templates (of all types) which are available for the user (e-mail, print/documents, task, data, etc.)
	 * - This method only reads the description for the template (used in a selector or menu)
	 * - Supports extra filters and functions for using those templates in various circumstances
	 * @param MVC_Request $rq
	 */
	public function action_Get_Templates(MVC_Request $rq)
	{
// 		$org_id = isset(MVC_Core::$ss-> perspective)? MVC_Core::$ss-> perspective: ($this-> user-> is_Unrestricted()? $rq-> org_id: MVC_Core::$ss-> user['org_id']);
		$org_id = ($this-> user-> is_Unrestricted() && $rq-> org_id)? (int)$rq-> org_id: MVC_Core::$ss-> user['org_id'];

		// When organization id is known, read the very basic organization info to get it's type, needed for global filtering
		$org = $this-> org-> read($org_id, array('orgtype'));
		if(!$org)
			return header("HTTP/1.0 404 Organization not found. Possible ID error");

		$rq-> add_Filter("include_default", "boolean");

		if($rq-> include_default && !$rq-> rt_id)
			return header("HTTP/1.0 400 Record type ID must be present when default automatic template is included");

		if(!$rq-> role)
			return header("HTTP/1.0 400 Template roles must be present in the request");


		// Read all templates for the issuetypes that are accessible by the user
		$m_tpl = new App_Model_Template();

		$r = $m_tpl-> get_List($org_id, null, function(QueryFactory &$q) use ($rq) {
			// Filter by role and record type ID
			$q
			-> where("`tpl`.role = ?", $rq-> role);
			if($rq-> rt_id)
				$q-> where("`tpl`.rt_id = ?", $rq-> rt_id);

			// Search descriptions (when the selector is a combobox) - For cases where there could be hundreds of templates
			if($rq-> q)
				$q-> where("`tpl`.description LIKE(?)", '%'.$rq-> q.'%');

		}, null, '`tpl`.description ASC');

		$templates = array();

		// Certain templete types have a system-wide default (per record type) - Only when listing/searching templates, not when managing
		// - In those cases, return the default template even when no results have been found
		if($rq-> include_default) {

			// Read quick record type info
			$this-> record = $this-> factory_Model("Issue", "record");
			$rt = $this-> record-> ist_Read($rq-> rt_id, null);

			// Depending on role, the template is accessed either as a file, or from an external location.
			switch($rq-> role) {
				case 'email':
					// Add the global default as first in the list
					$templates[] = array(
						'id'=> 0,
						'description'=> "Default E-mail template for ".$rt['name']
					);
					break;
				case 'print':
					// Add the global default as first in the list
					$templates[] = array(
						'id'=> 0,
						'description'=> "Default Printing template for ".$rt['name']
					);
					break;
				default:
					// No default. If also no custom, return error 404
					if(!$r || count($r)==0)
						return header("HTTP/1.0 404 No templates currently available");
			}

			// When no custom templates are available, simply return the default as the single row
			if(!$r || count($r)==0)
				return $this-> send_JSON($templates);

		} else
			if(!$r || count($r)==0)
				return header("HTTP/1.0 404 No templates currently available");

		foreach($r as $row) {
			// Post-process
			$row['id'] = (int)$row['id'];
			$templates[] = $row;
		}

		$this-> send_JSON($templates);
	}

	/**
	 * Returns template information with Title / Body rendered according to specified Record ID (process the variables)
	 * - This is the final step of using a template (of any role)
	 *
	 * @param MVC_Request $rq
	 */
	public function action_Render_Template(MVC_Request $rq)
	{
		if(!$rq-> rt_id)
			return header("HTTP/1.0 400 Record type ID must be present in the request");

		if(!$rq-> rec_id)
			return header("HTTP/1.0 400 Record ID must be present in order to render the template");

		$this-> record = $this-> factory_Model("Issue", "record");

		if($rq-> parts)
			$parts = json_decode($rq-> parts, true);
		else
			$parts = array();
		if($rq-> id) {
			// Read the specified template
			$m_tpl = new App_Model_Template();

			$template = $m_tpl-> read($rq-> id);

			if(!$template)
				return header(sprintf("HTTP/1.0 404 Template with ID %d was not found", $rq-> id));

			// Post-processing...
			$template['id'] = (int)$template['id'];
			$template['rt_id'] = (int)$template['rt_id'];
			$template['org_id'] = (int)$template['org_id'];
			$template['editor_id'] = (int)$template['editor_id'];

			// Read the record...
			$record = $this-> record-> read($rq-> rec_id, array('issue', 'organization', 'group', 'submitter', 'submitter_xdata', 'assigned', 'xfields'), $rq-> org_id, null, null,
				array('serialize'=> true, 'ist_or'=> $template['rt_id'], 'htmlsc'=> true, 'ignore_xf_hidden'=> true));
			if(!$record)
				return header(sprintf("HTTP/1.0 404 Data record with ID %d was not found", $rq-> rec_id));

			// ... and prepare all the variables for rendered

			// Global content ( domain, html )
			$tpl_data = array('global'=> array(
				'domain'=> MVC_Core::$ss-> domain_CFG['domain'],
				'title'=> MVC_Core::$ss-> domain_CFG['html_settings']['title']
			));

			// Find a way to replace "issue" with "record" and "ist" with "rt" in all templates... ?
			$tpl_data['issue'] = $record;
			$tpl_data['ist'] = $this-> record-> ist_Read($template['rt_id']);

			// Logged in user's info (user, department, organization)
			if(isset(MVC_Core::$ss-> user)) {
				$tpl_data['user'] = MVC_Core::$ss-> user;
				$tpl_data['department'] = MVC_Core::$ss-> department;
				$tpl_data['organization'] = MVC_Core::$ss-> organization;
				// Read the user's linked contact information so it can be used in the template
				$m_con = new App_Model_Contact();
				$tpl_data['user']['contact'] = $m_con-> read($tpl_data['user']['contact_id']);
			}

			$this-> init_Template_System();

			try {
				// In custom template mode, initialize the RecordRender plugin to allow including record data
				if(!empty($parts['auto_render'])) {
					$vars = $rq-> request_toArray();
					$vars['parts'] = $parts;
					$vars['tpldata'] = $tpl_data;
					$recordrender = new App_Plugin_RecordRender(array($this->_drytpl, $this-> record, $vars)); // Transmit the request as "configuration", so the plugin has information about the record itself
					$this->_drytpl-> register_Plugin("RecordRender", $recordrender);

					// Append {#plugin:RecordRender} to the template automatically at the end, unless it's already present
					if(strpos($template['body'], "{#plugin:RecordRender}")===false)
						$template['body'].="\n{#plugin:RecordRender}";
				}

				$template['title'] = $this->_drytpl-> render_String($template['title'], $tpl_data);
				$template['body'] = $this->_drytpl-> render_String($template['body'], $tpl_data);
			} catch(Exception $e) {
				// Error during render ? Probably bad template
				$template['title'] = "***** Template rendering ERROR *****";
				$template['body'] = "<h3 style='color: blue;'>{$template['description']}</h3><h2 style='color: red;'>".$e-> getMessage()."</h2><p>Please check Templates in the Organization module, an error has slipped into this template and the software is unable to construct the content</p>";
			}

		} else {
			// When none is present, read the old global template (stored as a file _render.html, _print.html)
			// These will be phased out in time, but right now we still need them

			$template = array(
				'id'=> 0,
				'role'=> 'email',
				'format'=> 'html',
				'rt_id'=> (int)$rq-> rt_id,
				'org_id'=> (int)$rq-> org_id,
				'title'=> '', // The default template doesn't have a title... user must type their own in
				'description'=> "Default E-mail Template"
			);

			// FIXME: Very, very bad...
			// Need better way of handling the auto-header for certain record types
			if($rq-> auto_message == 'true') {
				$tpl_data = array();

				// Render the record + Auto-message ; Record part first because we need all the parts (and due to caching system, the record is not read again)
				$return_att = false;
				$template['body'] = $this-> record-> render($rq-> rec_id, $parts, $rq-> org_id, null, $tpl_data, null, $return_att, $rq-> rt_id);
				$template['body'] = $this-> record-> render($rq-> rec_id, null, $rq-> org_id, array("includes/transfer.html"), $tpl_data)."<br />\n".$template['body'];
				if(isset($tpl_data['_subject']))
					$template['title'] = html_entity_decode($tpl_data['_subject']); // A subject has been defined in the template, override the auto-subject.
			} else
				$template['body'] = "<br />\n".$this-> record-> render($rq-> rec_id, $parts, $rq-> org_id); // Just render the record
		}

		$this-> send_JSON($template);
	}

}