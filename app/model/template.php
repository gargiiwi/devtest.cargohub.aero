<?php
/**
 * "Templates" editor data and file access
 * - Includes both system templates ( as files ), and also database stored templates ( organization based )
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage DataModels
 * @version 2020.01.30
 */
class App_Model_Template extends MVC_Model
{
	// TODO: Review the "File" model, it's quite outdated.

	/**
	 * Root directory where the model finds content
	 * @var string
	 */
	public $directory;

	/**
	 * This is a model that uses files from the disk, so set up for that.
	 *
	 * @param string $root_dir
	 */
	public function __construct($params = array())
	{
		if(isset($params['root_dir']))
			$this-> directory = $params['root_dir'];
		else
			$this-> directory =	ROOT. DS. 'app'. DS. 'view';
	}

	/**
	 * Reads the list of all canned messages from the database
	 */
	public function get_Tree()
	{
		return $this-> dirToArray($this->directory);
	}

	/**
	 * Get Template contents
	 *
	 * @param base64 encoded string $path
	 */
	public function get_Template($path)
	{
		return @file_get_contents($path);
	}

	/**
	 * Save Template
	 *
	 * @param base64 encoded string $path
	 * @param string $data
	 */
	public function save_Template($path, $data)
	{
		return @file_put_contents($path, $data);
	}

	/**
	 * Helper function (recursive)
	 *
	 * @param string $dir The start directory
	 */
	private function dirToArray($dir)
	{
		$result = array();
		foreach(scandir($dir) as $key => $value) {
			if(!in_array($value, array(".", "..", ".svn"))) {
				if(is_dir($dir. DS. $value))
					$result['dir_'. $value] = array('name'=> $value, 'children'=> $this-> dirToArray($dir. DS. $value));
	         	else
	         		$result['file_'. $value] = array('name'=> $value, 'path'=> base64_encode($dir. DS. $value));
			}
		}

		ksort($result);
		return $result;
	}

    public function create_Template($data)
    {
		return $data;
    }

	public function delete_Template($data)
	{
		fclose($data);
		chown($data,666);
		if(@unlink($data))
			return true;
		else
			return false;
	}

// New Database-stored template model (Organization related)

	private static $accepted_roles = array(
		'email',	// Template used to construct an e-mail
		'print',	// Template used to print a primary record
		'task',		// Task preset (title, content, days until deadline) - "xdata.deadline_days", "xdata.auto_assign"
		'view',		// Template used to view record data as a webpage
		'arem',		// Automatic Response Mail
		'ntf',		// E-mail Notification Template
		'export'	// Export template, usually not HTML (Can be used to create XML, YAML, JSON or other text-based data exchange formats)
	);

	// These go into XData
	private static $accepted_formats = array(
		'html',		// For viewing, printing and e-mailing
		'text',		// General purpose "text" template (without a structured format)
		'xml',		// Data export
		'yaml',		// Data export
		'json',		// Data export or sending to frontend JavaScript
		'custom'	// Data export but also other purposes
	);

	/**
	 * Contains the Drytemplate object used to test-render the template
	 * @var Drytemplate
	 */
	protected static $_drytpl = null;

	/**
	 * Test the string through the renderer to check for exceptions. If yes, refuse to create or update the template
	 * @param string $text
	 * @return bool True if the text could be rendered correctly as a template, False if not
	 */
	protected static function test_Render($text)
	{
		if(empty(self::$_drytpl)) {
			$dtCFG = new stdClass();
			self::$_drytpl = new Drytemplate($dtCFG);
		}

		// Test ?
		try {
			self::$_drytpl-> render_String($text);
			return true;
		} catch(Exception $e) {
			return false;
		}
	}

	/**
	 * Verifies if a template field is conforming to standard, to make sure the template can be used correctly
	 *
	 * @param string $key Field key
	 * @param string $value Field value
	 * @param unknown $fields
	 * @return boolean|Ambigous <unknown, string>
	 */
	protected static function field_Check($key, &$fields)
	{
		$value = $fields[$key];
		switch($key) {
			// Check Usage Role and Template Format
			case 'role':
				if(!in_array($value, self::$accepted_roles))
					return MVC_Core::$debug-> dump("Unsupported role: {$value}", __METHOD__, null, "warning"); // Wrong visibility (not supported by the system)
				break;
			case 'format':
				if(empty($value))
					$value = 'html'; // Defaults to HTML if empty, but if present it must be of correct kind
				else
					if(!in_array($value, self::$accepted_formats))
						return MVC_Core::$debug-> dump("Unsupported format: {$value}", __METHOD__, null, "warning"); // Invalid formats (not supported by the system)
				break;
			// Trim empty characters from the text-based fields
			case 'locator_key':
				$value = trim($value);
				if(empty($value))
					return MVC_Core::$debug-> dump("Mandatory Locator key is missing for Notification Template", __METHOD__, null, "warning");
				elseif(preg_match('/[^a-z_\-0-9]/', $value))
					return MVC_Core::$debug-> dump('Invalid characters in Locator Key. Accepted: [a-z0-9_-]', __METHOD__, null, "warning");
				break;
			case 'description':
				$value = trim($value);
				break;
			// Test render Title and Body because they may contain placehodlers
			case 'title':
			case 'body':
				$value = trim($value);
				if(!self::test_Render($value))
					return MVC_Core::$debug-> dump("Test render failed", __METHOD__, null, "warning");
				break;
			// For some template types the record type ID is unnecessary, so it can be missing
			case 'rt_id':
				if(empty($value))
					return null;
				else
					$value = (int)$value;
				break;
			// Title (Subject) must exist for emails, so it's copied from description when empty
			case 'title':
				if($fields['role'] == 'email' && empty($value))
					$value = $fields['description'];
					break;
			// TODO: Check other limits on data (locator_key format, etc)
		}
		return $value;
	}

	/**
	 * Checks and prepares Xdata field (and intialize defaults if missing)
	 *
	 * @param string $role
	 * @param array $xdata
	 * @return encoded Xdata as JSON
	 */
	protected static function field_Xdata_Check($role, $xdata = null)
	{
		switch($role) {
			// TASK template
			case 'task':
				if(!is_array($xdata))
					$xdata = array();

					// When not specified, wrong or negative, deadline days get set to "7 days" (one week from now)
					if(empty($xdata['deadline_days']) || !is_numeric($xdata['deadline_days']) || (int)$xdata['deadline_days'] < 0)
						$xdata['deadline_days'] = 7;
					else
						$xdata['deadline_days'] = (int)$xdata['deadline_days'];

					if(empty($xdata['auto_assign']))
						unset($xdata['auto_assign']);
					else
						$xdata['auto_assign'] = (int)$xdata['auto_assign'];

					if(empty($xdata['ovd_intv']))
						unset($xdata['ovd_intv']);
					else
						$xdata['ovd_intv'] = (int)$xdata['ovd_intv'];
					break;
			// Automatic Response E-Mail template
			case 'arem':
				if(!is_array($xdata))
					$xdata = array();

					// If triggering event [arem_event] is not empty, a responder contact must be selected
					if(!empty($xdata['arem_event']) && empty($xdata['arem_contact']))
						return MVC_Core::$debug-> dump("Xdata Error", __METHOD__, null, "warning");

					if(!empty($xdata['arem_contact']))
						$xdata['arem_contact'] = (int)$xdata['arem_contact'];
					break;
			// NoTiFication e-mail template
			case 'ntf':
				if(!is_array($xdata))
					$xdata = array();

					// Notification sender contact may be empty (default system E-mail is used)
					if(!empty($xdata['ntf_contact']))
						$xdata['ntf_contact'] = (int)$xdata['ntf_contact'];

					break;
			// Not sure yet... ?
			// ... 'export_plugin' ? (Not implemented)
			case 'export':
				break;
		}

		if(is_array($xdata))
			return json_encode($xdata);
		else
			return null;
	}

	/**
	 * Creates a new template record
	 *
	 * @param int $org_id Template owner organization
	 * @param int $user_id User who creates this template (Can be null, for system-generated templates)
	 * @param array $fields Other parameters for template creation:
	 * -"rt_id"			Record Type ID (numeric)
	 * -"title"			The title of the template, as it appears in various menus *Mandatory
	 * -"body"			Content of the template. If missing, it will be created empty
	 * -"description"	Information on what this template is about
	 * -"role"			One of [view, print, export, email]. Defaults to "view"
	 * -"format"		One of [html, xml, yaml, custom]. Defaults to "html"
	 * @param array $xdata eXtra Data for certain roles
	 * -"deadline_days" For "task" role, it contains the number of days in the future the deadline will be set automatically when creating a task from this template
	 * -"auto_assign" For "task" role, it may indicate the ID of a department which will get automatic assignment when creating a task
	 * -"export_plugin"	Plugin of type "export", uses record data to construct other data in an arbitrary format - Role must be "export and Format must be "custom" if this is used.
	 */
	public function create($org_id, $user_id, $fields = array(), $xdata = null)
	{
		// Assemble the data and insert it
		$template = array(
			'org_id'=> (int)$org_id,
			'editor_id'=> (int)$user_id
		);

		$accepted_fields = array('role', 'format', 'locator_key', 'rt_id', 'title', 'body', 'description'); // , 'export_plugin'
		foreach($fields as $field=> $value) {
			// any field outside the correct ones is discarded (to avoid database errors)
			if(!in_array($field, $accepted_fields))
				continue;

			$template[$field] = self::field_Check($field, $fields);
			// Field check failed ? Don't allow the template to be created
			if($template[$field] === false)
				return false;
		}

		// For certain roles, Prepare Xdata field
		$template['xdata'] = self::field_Xdata_Check($fields['role'], $xdata);
		if($template['xdata'] === false)
			return false;

		$q=QueryFactory::insert()
		-> table(MVC_Core::$db-> tab['tpl'])
		-> values($template);

		if(!MVC_Core::$db-> query($q))
			return false;

		return MVC_Core::$db-> insert_id;
	}

	/**
	 * Reads an existing template.
	 *
	 * @param int $id
	 * @param bool $get_only_body If true, it returns the body only as a string ( not the entire template as an array )
	 * @return array
	 */
	public function read($id, $get_only_body = false)
	{
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> tab['tpl'], $get_only_body?"body":null)
		-> where("`id` = ?", $id);

		$r = MVC_Core::$db-> query($q);

		if(!$r || count($r)==0)
			return false; // error or empty result

		$template = $r-> current();
		if($get_only_body)
			return $template['body'];
		else
			return $template;
	}

	/**
	 * Attempts to find an organization template of the specified role, having the $locator_key
	 * -- If $org_id is specified, it tests first if a template exists in the organization, if not it makes a second attempt to locate in organization "1" (System Organization)
	 * -- If not found in System organization, it returns false (the calling method should test if a template can be successfully located)
	 * -- The function only returns the newest template that matches (in case multiple have the same key, it ignores older "versions")
	 *
	 * @param string $role
	 * @param string $locator_key
	 * @param int $org_id
	 * @return int|bool Returns the matching template ID or False
	 */
	public function locate($role, $locator_key, $org_id = null)
	{
		$q1=QueryFactory::select()
		-> table(MVC_Core::$db-> tab['tpl'], "id")
		-> where("`role` = ?", $role)
		-> where("`locator_key` = ?", $locator_key)
		-> order("`edited_on` DESC");
		$q2 = $q1; // Save the QueryFactory object for the 2nd attempt, if necessary

		if($org_id > 0) {
			// Organization is present, run the first attempt
			$q1-> where("`org_id` = ?", $org_id);

			$r1 = MVC_Core::$db-> query($q1);
			if($r1 && count($r1)==1) {
				// Found in organization, we're done
				$template = $r1-> current();
				return (int)$template['id'];
			}
		}
		// Either Org is not mentioned or first search failed, try again for organization "1"

		$q2-> where("`org_id` = 1");
		$r2 = MVC_Core::$db-> query($q2);
		if(!$r2 || count($r2)==0)
			return false; // error or empty result

		// Found in System organization !
		$template = $r2-> current();
		return (int)$template['id'];
	}

	/**
	 * Updates an existing template information
	 *
	 * @param int $id
	 * @param int $user_id Who makes this update ? (Might be null for system updates)
	 * @param array $fields Template fields which can be updated
	 * @param array $xdata eXtra Data for certain roles
	 */
	public function update($id, $user_id, $fields, $xdata = null)
	{
		// Assemble the data and insert it
		$tpl_update = array(
			'editor_id'=> $user_id
		);

		$accepted_fields = array('role', 'locator_key', 'rt_id', 'title', 'body', 'description'); // 'format' cannot be changed after template creation
		foreach($fields as $field=> $value) {
			// any field outside the accepted ones for update is discarded
			if(!in_array($field, $accepted_fields))
				continue;

			$tpl_update[$field] = self::field_Check($field, $fields);
			// Field check failed ? Don't allow the update to proceed with invalid data
			if($tpl_update[$field] === false)
				return false;
		}

		// When role is present, for certain roles, Prepare Xdata field
		if(!empty($fields['role'])) {
			$tpl_update['xdata'] = self::field_Xdata_Check($fields['role'], $xdata);
			if($tpl_update['xdata'] === false)
				return false;
		}

		$q=QueryFactory::update()
		-> table(MVC_Core::$db-> tab['tpl'])
		-> where("`id` = ?", $id)
		-> values($tpl_update);

		return MVC_Core::$db-> query($q);
	}

	/**
	 * Removes an existing template from the database
	 *
	 * @param int $id
	 */
	public function delete($id)
	{
		$q=QueryFactory::delete()
		-> table(MVC_Core::$db-> tab['tpl'])
		-> where("`id` = ?", $id);

		return MVC_Core::$db-> query($q);
	}

	/**
	 * Reads a list of templates for an organization (usually for the grid, but it can be for other purposes as well)
	 *
	 * @param int $org_id
	 * @param array $wtr What to read [template, body, editor, recordtype]
	 * @param [callable] $qfo_filter Callback function used to limit the search
	 * @param array $filter_params
	 * @param string $order_by Which field to be used for ordering
	 * @param int $count_rows Paging Size
	 * @param int $start_row Paging Start
	 */
	public function get_List($org_id, $wtr = array('template'), $qfo_filter = null, $filter_params = array(), $order_by = null, $count_rows = 0, $start_row = 0)
	{
		$q = $this-> get_List_QueryFactory_Helper($qfo_filter, $filter_params, $order_by, $count_rows, $start_row);
		if(is_null($q))
			return false; // Error, $qfo_filter failed

		$q-> where('`tpl`.org_id = ?', $org_id);

		// What to read?
		$wtr = self::wtr_Helper($wtr);

		if(in_array('template', $wtr))
			// full mode, all fields (body excluded by default, needs $wtr to contain 'body'
			$q-> table(MVC_Core::$db-> table('tpl'), 'id,role,locator_key,format,rt_id,org_id,editor_id,title,description,xdata'.(in_array('body', $wtr)?',body':''));
		else
			// quick mode, less fields (usually no linked tables as well)
			$q-> table(MVC_Core::$db-> table('tpl'), 'id,role,locator_key,format,description,xdata');

		if(in_array('editor', $wtr))
			// Read detailed contact information for the users (contact xdata)
			$q-> table(MVC_Core::$db-> table('usr'), QueryFactory::autoalias_Add_Prefix("name", '_usr_'), 'left', '`usr`.id = `tpl`.editor_id');

		if(in_array('recordtype', $wtr))
			// Read the name and key of the record type this template is ment for
			$q-> table(MVC_Core::$db-> table('ist'), QueryFactory::autoalias_Add_Prefix("name,key", '_rt_'), 'left', '`ist`.id = `tpl`.rt_id');

		$r = MVC_Core::$db-> query($q);
		if(!$r || count($r)==0)
			return false; // error or empty result

		$r-> split_by_prefixes = array(
			// extra table(s)
			'editor'=> "_usr_",
			'rt'=> "_rt_"
		);
		return $r;
	}

}