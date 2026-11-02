<?php
/**
 * "Communication Label" model - Part of the communication system
* -- Allows linking conversations or contacts under a common label, and also manages the "system" labels like "Inbox", "Sent mail" or "Trash"
* -- Conversations and contacts can be connected to multiple labels at once, on the same user or even the same conversation to multiple users, but the same conversation can be connected to the same label id only once (PRIMARY index)
*
* @author Wavetrex (wavetrex@gmail.com)
* @package Cargo ITS
* @version 1.0.dev
*/
class App_Model_Commlabel extends MVC_Model
{
	//----- Communication labels CRUD -----//
	// Note: In current implementation, they are not hierarchical, so a label can only connect to conversations or contacts, but not to another label

	/**
	 * Creates a communication label record
	 *
	 * @param int $link_id The entity to which this label is linked (user, department, organization), or (TODO:) another label (hierarchical structure)
	 * @param string $name
	 * @param string $color Labels can be assigned a color (out of 8) for easier recognition
	 * @return int Newly created label id
	 */
	public function create($link_id, $name, $type = 'c-user', $color = null)
	{
		if(!is_null($color) && !in_array($color, array('blue', 'green', 'orange', 'pink', 'purple', 'red', 'yellow', 'black')))
			$color = null; // Either set a correct color or no color.

		// build the query and run it
		$q=QueryFactory::insert()
		-> table(MVC_Core::$db-> tab['lab'])
		-> values(array(
			'link_id'=> $link_id,
			'name'=> $name,
			'type'=> $type,
			'color'=> $color
		));

		$r = MVC_Core::$db-> query($q);
		if(!$r)
			return false; // DB Error when inserting label

		return MVC_Core::$db-> insert_id;
	}

	/**
	 * Reads the label data
	 * @param int $label_id
	 */
	public function read($label_id)
	{
		$q= QueryFactory::select()
		-> table(MVC_Core::$db-> tab['lab'])
		-> where("`id` = ?", $label_id);

		$r = MVC_Core::$db-> query($q);
		if($r && count($r)==1) {
			return $r-> current();
		} else
			return false; // not found or other DB error
	}

	/**
	 * Finds a user's communication label having a certain type or an exact name. Returns the ID or false if not found.
	 *
	 * @param int $link_id
	 * @param string $type
	 * @param string $name
	 * @return array The label's data (contains the ID and everything else)
	 */
	public function find($link_id, $type = null, $name = null)
	{
		$q= QueryFactory::select()
		-> table(MVC_Core::$db-> tab['lab'])
		-> where("`link_id` = ?", $link_id)
		-> limit(1);

		if(!is_null($type))
			$q-> where("`type` = ?", $type);
		elseif(!is_null($name))
			$q-> where("`name` = ?", $name);
		else
			return false; // error, must specify either type or name

		$r = MVC_Core::$db-> query($q);

		if($r && count($r)==1)
			return $r-> current();
		else
			return false; // not found, wrong type or name
	}

	/**
	 * Simply updates the name of a label (and optionally it's color - For user labels )
	 *
	 * @param int $label_id The label's ID
	 * @param string $new_name
	 * @param string $new_color For user labels, a "color" can be defined
	 */
	public function rename($label_id, $new_name, $new_color = null)
	{
		if(!is_null($new_color) && !in_array($new_color, array('blue', 'green', 'orange', 'pink', 'purple', 'red', 'yellow', 'black')))
			$new_color = null; // Either set a correct color or no color.

		$q=QueryFactory::update()
		-> table(MVC_Core::$db-> tab['lab'])
		-> where("`id` = ?", $label_id)
		-> values(array(
			'name'=> $new_name,
			'color'=> $new_color
		));

		return MVC_Core::$db-> query($q);
	}

	/**
	 * Deletes a specific label (system or user)
	 * -- All conversations and contacts must be disconnected from this label, as the ID will become invalid after deletion
	 *
	 * @param int $label_id The label's ID
	 */
	public function delete($label_id)
	{
		// Delete the label record
		$q= QueryFactory::delete()
		-> table(MVC_Core::$db-> tab['lab'])
		-> where("`id` = ?", $label_id);

		return MVC_Core::$db-> query($q);
	}

	/**
	 * Remove all communication labels that have a certain Link ID
	 * -- Used mainly when the linked entity is deleted (along with everything else)
	 * -- All conversations and contacts must be disconnected from all the labels, as their ID's will become invalid after deletion
	 *
	 * @param int $link_id
	 */
	public function cleanup_Collection($link_id)
	{
		$q= QueryFactory::delete()
		-> table(MVC_Core::$db-> tab['lab'])
		-> where("`link_id` = ?", $link_id);

		return MVC_Core::$db-> query($q);
	}

	/**
	 * Reads the communication labels for an user ID
	 * -- This is used primarely for showing the list of labels on the Communication Center module
	 * -- Arranges the labels in a certain order, ready to be sent to the frontend module.
	 *
	 * @param int $link_id
	 * @param string $type 'convos' = Conversation labels (several type) or 'con-?' = Contacts labels (used in the Address Book: user = personal, dep = department, org = organization)
	 * -- Note: Contacts can only have one label, while conversations can have multiple at once ( c2l table )
	 * @return array
	 */
	public function get_List($link_id, $type = 'convos')
	{
		$q= QueryFactory::select()
		-> table(MVC_Core::$db-> tab['lab'])
		-> where("`link_id` = ?", $link_id)
		-> order("type")
		-> order("name");

		if($type == 'convos')
			$q-> where('`type` IN(?)', array('a-inbox', 'b-sent', 'c-user', 'd-drafts', 'e-bin'));
		else
			$q-> where('`type` = ?', $type);

		$r = MVC_Core::$db-> query($q);

		if($r && count($r)>0) {
			$labels = array();
			foreach($r as $row) {

				if($type == 'convos') {
					// A little arrangement of user custom labels
					if($row['type'] != "c-user")
						$labels[] = $row;
					else {
						// find the index of the user's custom labels, because all custom ones are displayed hierarchical
						if(!isset($user_cons_idx)) {
							$labels[] = array('type'=> 'c-user', 'name'=> "Labels");
							$user_cons_idx = count($labels)-1;
						}
						$labels[$user_cons_idx]['children'][] = $row;
					}
				} else
					// contacts labels are linear
					$labels[] = $row;
			}

			return $labels;
		} else
			return false; // user not found or other DB error
	}

}