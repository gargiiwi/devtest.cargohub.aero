<?php
/**
 * "Canned Messages" model - Part of the communication system
* -- These are templates used when sending messages. They can contain variables and logic (uses DRYTemplate system for rendering)
* -- Canned messages can only be used in combination with a source data (that will fill the variables).
*    This is mostly valid for sending messages related to issues, but there might be other messages on which the source data is the user data, the organization data ( or others )
* TODO: Dolly version has improvements to the canned messages - Check that code and merge it here
*
* @author Wavetrex (wavetrex@gmail.com)
* @package Cargo ITS
* @version 1.0.dev
*/
class App_Model_Canned extends MVC_Model
{
	//----- Canned Message (templates) CRUD -----//

	/**
	 * Accepted canned message visibility scope
	 *
	 * @var array[string]
	 */
	private static $accepted_visibilities = array(
		'global',	// Visible for everyone, operated only by admins
		'org',		// Organization's canned messages, visible by all users of the organization (but not outside it) - Editable by user with ability "org_canned_manager"
		'dep',		// Department restricted canned message, visible only to the users within the department
		'user'		// User's personal canned messages, operated only by the user specified in "editor_id"
	);

	/**
	 * Accepted canned message usage classes
	 * @var array[string]
	 */
	private static $accepted_usage_classes = array(
		'issreply',		// Used as a reply to issue message; link_id is the Issue Type ID
		'issmessage',	// Used in the Send By Mail window (which is also the Transfer window), with the ability to append various data to the e-mail being sent out; link_id is the Issue Type ID
		'issletter',	// For new planned function to send letters to 3rd parties containing arbitrary text, but using variables extracted from the issue
		'organization',	// Canned message used to send out official organization letters (can be used for newsletter purposes). Variables are extracted from the organization data and the linked contact info; link_id CAN BE a department id)
		'personal',		// Personal user canned message not linked to anything special; Variables are extracted from user's session ( and contact info ); link_id not used (null)
		'var'			// Varied (reserved for future usage); link_id not used (null)
	);

	private static $renderer = null;

	/**
	 * Uses DRYTemplate to check if the string can be rendered correctly as a template.
	 * - It does this by attempting to render it and captures the crash. Returns false in that case, or "true" when the render completed successfuly
	 * - This is used when creating or updating a canned message, to prevent invalid templates from being saved
	 *
	 * @param unknown $string
	 */
	private static function test_Render($string)
	{
		try {
			if(is_null(self::$renderer))
				self::$renderer = new Drytemplate(new stdClass());
			self::$renderer-> render_String($string);
			return true;
		} catch (Exception $e) {
			return $e-> getMessage();
		}
	}

	/**
	 * Adds a canned messages for a organization
	 *
	 * @param array $canned_data All parameters of the canned message
	 * @param int $ist_id
	 * @param int $org_id
	 * @param int $created_by
	 * @param string $title
	 * @param string $template
	*/
	public function create($canned_data)
	{
		// any key outside the correct fields is discarded (to avoid database errors)
		$fields = array('usage_class', 'link_id', 'visibility', 'orgtype_id', 'org_id', 'dep_id', 'editor_id', 'title', 'description', 'template');
		foreach($canned_data as $field=> $value) {
			if(!in_array($field, $fields))
				unset($canned_data[$field]);
			// Check Visibility and Usage Class
			if($field=='visibility' && !in_array($value, self::$accepted_visibilities))
				return false; // Wrong visibility (not supported by the system)
			if($field=='usage_class' && !in_array($value, self::$accepted_usage_classes))
				return false; // Wrong usage class (not supported by the system)
		}

		// Test dynamic elements if they can render correctly: title, template
		$_test = self::test_Render($canned_data['template']);
		if($_test !== true)
			return array(-1, $_test);
		$_test = self::test_Render($canned_data['title']);
		if($_test !== true)
			return array(-2, $_test);

		$q=QueryFactory::insert()
		-> table(MVC_Core::$db-> tab['can'])
		-> values($canned_data);

		if(MVC_Core::$db-> query($q))
			return MVC_Core::$db-> insert_id;
		else
			return false;
	}

	/**
	 * Reads the list of canned messages visible for an user ( global, department, organization and personal )
	 *
	 * @param int $org_id The organization ID for which canned messages are read (if missing, organization-wide messages cannot be visible - visibility "org")
	 * @param int $org_type The organization Type, which is needed to filter "global" canned (which are valid for all organizatin of that particular type)
	 * @param int $user_id The user for which canned messages are read (usually the logged in user) - visibility "user"
	 * @param int $link_ids Optionally, specify a set of link ids (ussually issue types)
 	 * @param string $usage_class If specified, filter by usage class
	 * @param bool $read_template If true, read the template field
	 * @param bool $read_editor If true, read the user who last edited the template
	 * @return MVC_Database_Result
	 */
	public function get_List($org_id = null, $org_type = null, $user_id = null, $link_ids = null, $usage_class = null, $read_template = false, $read_editor = false)
	{
		// TODO: Support Department Level too
		$q=QueryFactory::select()
		-> table(array(MVC_Core::$db-> tab['can'], 'can'), 'id,usage_class,link_id,visibility,org_id,dep_id,editor_id,title,description'. ($read_template? ',template': ''))
		-> order('`can`.`title` ASC');

		if($read_editor)
			$q-> table(array(MVC_Core::$db-> tab['usr'], 'usr'), QueryFactory::autoalias_Add_Prefix("name", '_usr_'), 'left', '`can`.`editor_id` = `usr`.`id`');

		// limit by link ids if specified
		if(!is_null($link_ids)) {
			is_array($link_ids)?
				$q-> where("(CASE WHEN `can`.`usage_class` IN('issreply','issmessage','issletter') THEN `can`.`link_id` IN(?) ELSE 1 END)", $link_ids): // yes
				$q-> where("(CASE WHEN `can`.`usage_class` IN('issreply','issmessage','issletter') THEN `can`.`link_id` = ? ELSE 1 END)", $link_ids); // no
			// The other usage classes don't suport links yet (so only issue-linked canned messages for now)
		}

		// "Global" canned, which usually are per organization type
		$where_or = array(
			// Everyone can see the global ones, as long as they are for the proper issue type and organizaiton type - Globals can only be added/edited/deleted by admin
			!is_null($org_id) && !is_null($org_type)? $q-> quote_Into("(`can`.`visibility` = 'global' AND `can`.`orgtype_id` = ?)", $org_type): "`can`.`visibility` = 'global'"
		);

		// If organization id is specified, the organization's canned messages are visible
		if(!is_null($org_id))
			$where_or[] = $q-> quote_Into("(`can`.`visibility` = 'org' AND `can`.`org_id` = ?)", $org_id);
		// If the user id is specified, the personal (user) canned messages are also visible
		if(!is_null($user_id))
			$where_or[] = $q-> quote_Into("(`can`.`visibility` = 'user' AND `can`.`editor_id` = ?)", $user_id);
		$q-> where(implode(' OR ', $where_or));

		// Limit by usage class
		if(!is_null($usage_class))
			$q-> where("`can`.`usage_class` = ?", $usage_class);

		$r = MVC_Core::$db-> query($q);

		if($r && count($r)>0) {
			$r-> split_by_prefixes = array(
				// extra tables
				'editor'=> "_usr_"
			);
			return $r;
		} else
			return false;
	}

	/**
	 * Reads the template for a canned message, used in the editor for canned messages (might be large content)
	 * @param int $canned_id
	 * @return string
	 */
	public function read_Template($canned_id)
	{
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> tab['can'], 'template')
		-> where("`id` = ?", $canned_id)
		-> limit(1);

		$r = MVC_Core::$db-> query($q);
		if($r && count($r)==1) {
			$row = $r-> current();
			return $row['template'];
		} else
			return false;
	}

	/**
	 * Updates an existing canned message
	 * -- Can change "usage_class", "title", "description" or "template"
	 *
	 * @param int $canned_id
	 * @param array $new_canned_data
	 */
	public function update($canned_id, $new_canned_data)
	{
		// any key outside the correct fields is discarded (to avoid database errors)
		$fields = array('usage_class', 'visibility', 'dep_id', 'title', 'description', 'template');
		foreach($new_canned_data as $field=> $value)
			if(!in_array($field, $fields))
				unset($new_canned_data[$field]);

		// Test dynamic elements if they can render correctly: title, template
		$_test = self::test_Render($new_canned_data['template']);
		if($_test !== true)
			return array(-1, $_test);
			$_test = self::test_Render($new_canned_data['title']);
		if($_test !== true)
			return array(-2, $_test);

		$q=QueryFactory::update()
		-> table(MVC_Core::$db-> tab['can'])
		-> where("`id` = ?", $canned_id)
		-> limit(1);

		$q-> values($new_canned_data);

		return MVC_Core::$db-> query($q);
	}

	/**
	 * Removes a canned message by ID
	 *
	 * @param int $canned_id
	 */
	public function delete($canned_id)
	{
		$q= QueryFactory::delete()
		-> table(MVC_Core::$db-> tab['can'])
		-> where("`id` = ?", $canned_id)
		-> limit(1);

		return MVC_Core::$db-> query($q);
	}

	/**
	 * Remove all canned messages that belong to an organization or to an user
	 *
	 * @param int $org_id When not null, deletes canned of type "org"
	 * @param int $user_id When not null, deletes canned of type "user"
	 * -- Note: If both are non-null, $user_id is ignored and $org_id is considered
	 */
	public function cleanup_Collection($org_id = null, $user_id = null)
	{
		if(!is_null($org_id)) {
			$q= QueryFactory::delete()
			-> table(MVC_Core::$db-> tab['can'])
			-> where("`org_id` = ?", $org_id)
			-> where("`type` = 'org'");

			return MVC_Core::$db-> query($q);;
		}

		if(!is_null($user_id)) {
			$q= QueryFactory::delete()
			-> table(MVC_Core::$db-> tab['can'])
			-> where("`editor_id` = ?", $user_id)
			-> where("`type` = 'user'");

			return MVC_Core::$db-> query($q);
		}
	}


}