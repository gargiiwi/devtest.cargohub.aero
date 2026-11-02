<?php

/**
 * Global Contact-related Controller (actions which could be called from any module)
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage Controllers
 * @version 2018.07
 */
class App_Ctrl_Global_Contact extends App_Ctrl_Base {

    /**
     * Contact model, which is necessary for user display/editor to work properly
     * @var App_Model_Contact
     */
    private $con;

    /**
     * ID list of recently used contacts (used in several places)
     * @var array
     */
    private $recent_list;

    /**
     * Initialize the data model
     */
    public function __construct() {
        parent::__construct();

        $this->con = $this->factory_Model("Contact", "con");

        $cookie_name = "its_recent_contacts";
        // User mode?
        if (isset(MVC_Core::$ss->user))
            $cookie_name .= "_" . MVC_Core::$ss->user['id'];

        $cookies = new MVC_Persist('cookie');
        $this->recent_list = $cookies->{$cookie_name} ?: array();
    }

    /**
     * Reads and returns the Warehouse stored picture matching the specified contact ID
     *
     * @param MVC_Request $in
     */
    public function action_Get_Picture(MVC_Request $in) {
        // TODO: Check if contact actually exists
        // TODO: Check if the picture file exists and return it, if not return a system "no-picture" image
        // for now, just return the "no-picture" stream
        header("Content-Type: image/png");
        readfile(ROOT . DS . "htdocs" . DS . "img" . DS . "extra" . DS . "NoPicture.png");
    }

    /**
     * The function attempts to find an existing contact in the database for a certain e-mail address.
     *
     * @param string $email
     * @param array $wts What to search (Type of contacts)
     * @param string $name If specified, the contact must have this exact name (otherwise it's not considered a match, even if e-mail matches) - Search continues through the other types
     * @return array The contact data if located
     */
    private function locate_Existing_Email($email, $wts = array('draft', 'user_self', 'isspost'), $name = null) {
        if (!is_array($wts))
            return MVC_Core::$debug->dump("List of localizable contacts is not an array", __METHOD__, null, 'error');

        // Search in contact drafts (unlinked), but not older than 2 hours
        if (in_array('draft', $wts)) {
            $con_find = $this->con->locate("draft", $email, null, null, null, null, false, true);
            if (!empty($con_find))
                return $con_find;
        }

        // Try user address ( user is not logged in but uses his address - The system recognizes it's users )
        if (in_array('user_self', $wts)) {
            // First, find if there is an user account contact for the specified e-mail address
            $con_find = $this->con->locate("user_self", $email, null, null, null, null, false, true);
            if (!empty($con_find))
                return $con_find;
        }

        // Search in previously submitted records and reuse contacts to minimize contact table clutter
        if (in_array('isspost', $wts)) {
            if (empty($name))
                return false; // Cannot search for ISSPost type contacts without a name


                
// Not found ? Attempt to locate the contact in existing issue submit contacts (isspost), and reuse if the name also matches.
            $con_find = $this->con->locate("isspost", $email);
            if (is_array($con_find)) {
                foreach ($con_find as $contact) {
                    if ($contact['name'] == trim($name))
                        return $contact;
                }
            }
        }

        return false; // Nothing found, this is either a completely new contact or a new name for an existing e-mail
    }

    /**
     * Attempts to discover an e-mail in the contact database, in a way relevant to the logged in user or the organization sending the message (or in case admin is logged in, the perspective organization)
     * If just e-mail addresses, an attempt is made to locate the e-mail address in the existing contacts, in order:
     * - 1. If $wts contains "isspost", "isslink", then $link_type must be "issue" and $link should contain the record id
     * - 2. If $wts contains "issue_target", discover the special target contacts for data sharing; $link_type must be "issue" and $link should contain record id; The record base data is read to get the record type (used for targets)
     * - 3. If $wts contains "global", "organization", "department", "personal", "auto_out" the data is searched in logged in address book (or if admin is logged in, "perspective" organization is considered
     * - 4. If $wts contains "org_self", "dep_self", "user_self" - Specific contacts are located in the entire database
     * (Other types are not searched for now)
     *
     * @param string $email
     * @param array $wts What to search (Type of contacts); Defaults to "issue_target"
     * @param array $link Contact linking information (depends on $link['type']); In case of "issue", it should contain ID, TrackID, RT_Key
     * @param int $org_id If present, organization and department contacts are searched for the specified organization ( and not the user's organization ) - Admin/Supervisor feature
     * // TODO: Rename "issue" into "record" on all linkage (and code)
     *
     * @return array Contact information matching the e-mail address. The returned contact always contains an ID !
     */
    protected function discover_Contact($email, $wts = array('issue_target'), $link = null, $org_id = null) {
        // What to search, what to read... same thing
        $wts = MVC_Model::wtr_Helper($wts);

        // Search in linked record or targets valid for the record type ($link['rt_key'])
        // Currently, the only supported $link['type'] is "issue"
        if (count(array_intersect(array('isspost', 'isslink', 'issue_target'), $wts)) > 0 && is_array($link) && $link['type'] == 'issue') {

            // 1. Already linked contacts
            if (empty($org_id))
                $linked_cons = $this->con->con2iss_Get_List($link['rec_id'], false, 'public');
            elseif ($this->user->is_Unrestricted())
                $linked_cons = $this->con->con2iss_Get_List($link['rec_id'], false, 'unrestricted');
            else
                $linked_cons = $this->con->con2iss_Get_List($link['rec_id'], false, 'org', $org_id);

            // Is the e-mail in one of these (Alread linked) ? If yes, return
            foreach ($linked_cons as $lcon)
                if (strcasecmp($email, $lcon['contact']['email']) == 0) {
                    $lcon['contact']['id'] = (int) $lcon['contact_id'];
                    return $lcon['contact'];
                }

            // nope, moving on...
            // 2. Try the targets for specified record type - All organizations !
            $target_con = $this->org->targetContact_Find_Email($email, $link['rt_key']);
            if (!empty($target_con))
                return $target_con;
        }

        // These are valid only when an user is logged in (search in user's address book)
        if (count(array_intersect(array('organization', 'department', 'personal', 'auto_out'), $wts)) > 0 && !empty(MVC_Core::$ss->user)) {
            // 3. Address book search for logged in user;
            if (!$org_id)
                $org_id = MVC_Core::$ss->user['org_id'];

            $con_types = array('global');
            if (in_array('organization', $wts)) {
                $con_types[] = "organization";
                $con_types[] = "org_self";
            }
            if (in_array('department', $wts)) {
                $con_types[] = "department";
                $con_types[] = "dep_self";
            }
            if (in_array('personal', $wts)) {
                $con_types[] = "personal";
                $con_types[] = "user_self";
            }
            if (in_array('auto_out', $wts))
                $con_types[] = "auto_out";

            $ab_con = $this->con->locate(implode('|', $con_types), $email, null, $org_id, MVC_Core::$ss->user['id'], MVC_Core::$ss->user['cdep_id'], false, true); // last true = $return_one (if contact is found, return it, don't continue search)
            if (!empty($ab_con))
                return $ab_con;
        }

        // 4. (Not implemented yet) - Locate arbitrary organizations, departments and users by their "org_self", "dep_self", "user_self"

        return null; // Nothing found!
    }

    /**
     * Checks if the contact is allowed to be edited by the current logged in contact account or user
     * - TODO: non-logged accounts can only edit drafts which are present in their cookies and are no longer than 2 hours ( their recently submitted contacts )
     * @param array $contact
     */
    private function check_Edit_Ability($contact) {
        if (isset(MVC_Core::$ss->user) && !empty(MVC_Core::$ss->user['is_admin']))
        // Admin can always edit contacts
            return true;
        elseif (isset(MVC_Core::$ss->user)) {
            // Logged in user can edit himself
            if ($contact['type'] == 'user_self' && $contact['id'] == MVC_Core::$ss->user['contact_id'])
                return true;

            // Logged in users can edit contacts of various types that were created by themselves
            if (isset($contact['user_id']) && $contact['user_id'] == MVC_Core::$ss->user['id'] && in_array($contact['type'], array("organization", "department", "personal", "auto_out", "isspost", "isslink", "draft")))
                return true;

            // TODO: Check list of personal contacts for user and allow reusing/editing one of them.
        } elseif (isset(MVC_Core::$ss->contact_account) && $contact['type'] == 'isspost') {
            // Contact Account can edit his own contact or one of his managed contacts
            $cac = MVC_Core::$ss->contact_account;
            $cac['managed_ctx'] = explode(',', $cac['managed_ctx']);
            if ($contact['id'] == $cac['id'] || in_array($contact['id'], $cac['managed_ctx']))
                return true;
        } elseif ($contact['type'] == 'draft') {
            // Non-logged can only edit drafts created in the last 2 hours
            $ctime = strtotime($contact['ctime']);
            if (time() - $ctime < 7200)
                return true;
            // ...but checks its_recent_contacts cookie as well so own drafts are always editable (even if older)
            if (in_array($contact['id'], $this->recent_list))
                return true;
        }
        return false;
    }

    /**
     * Generates and returns as HTML the contact information for a system contact
     * -- Uses a template to display the card, template which might be reused in other parts which display this card integrated into other information pages
     * -- Also supports displaying an empty card which can be edited using the "contact editor" (javascript)
     *
     * @param MVC_Request $rq
     */
    public function action_Visual_Card(MVC_Request $rq) {
        if ($rq->id) {
            // There is an ID, this contact is being displayed or edited. Try to read it:
            $contact = $this->con->read($rq->id);
            if (!$contact) {
                echo "<h1>Error, unable to read the contact !</h1>";
                return MVC_Core::$debug->dump("Attempting to read an invalid contact: {$rq->id}", __FUNCTION__, null, 'error');
            }

            $this->con->add_To_Recent($contact['id']);

            // When loading in edit mode, check if there is user or contact account logged in and if the user is allowed to edit this contact
            if ($rq->edit)
                $this->_edit = $this->check_Edit_Ability($contact);
        } else {
            // No ID, working in Create mode (fresh contact), or attempt to locate existing contact for reuse
            if (isset($rq->init_contact['email']) && isset($rq->init_contact['name'])) {
                // When using the initialize vars, check if the name and e-mail doesn't exist already (to be reused)
                $contact = $this->locate_Existing_Email($rq->init_contact['email'], array('draft', 'user_self', 'isspost'), $rq->init_contact['name']);

                // Check if found contact can be edited
                if ($contact !== false) {
                    $this->con->add_To_Recent($contact['id']);

                    // A little hack to prevent editing of user's own contact in some cases
                    if ($contact['type'] == "user_self" && $rq->deny_edit_user_self)
                        $this->_edit = false;
                    else
                        $this->_edit = $this->check_Edit_Ability($contact);
                }
            }

            if (empty($contact)) {
                // When contact is not initialized (with both name and e-mail) or no contact found, enter created mode
                $_create = array();
                // Support partial initialization:
                if (isset($rq->init_contact['name'])) {
                    $this->name = $rq->init_contact['name'];
                } else {
                    $_create['name'] = true;
                }
                if (isset($rq->init_contact['email']))
                    $this->email = $rq->init_contact['email'];
                else {
                    $_create['email'] = true;
                }
                $this->_create = $_create;
                $this->_edit = true; // Create is also edit
            }
        }

        // Setup the VCard for existing contact
        if (!empty($contact)) {
            $this->id = $contact['id'];
            $this->name = $contact['name'];
            $this->email = $contact['email'];
            $this->count_msgs = $contact['count_msgs'];
            $this->loggedin_type = $_SESSION['user']['status'] == 'admin' ? true : false;
            $this->xdata = $this->con->decode_Xdata($contact['xdata']);
            $this->edit_mode = $rq->edit;
        }

        // This is an ajax request, but with a HTML response, so initialize the template object on demand
        $this->init_Template_System();

        if (!$rq->template)
            $card_tpl = "vcard.html";
        else
            $card_tpl = $rq->template . ".html";
        $this->view("includes" . DS . $card_tpl);
    }

    /**
     * Smart Ajax Search function for locating contacts by their e-mail, name, organization ( and other parameters )
     * -- TODO: For empty query string, it returns the user's favourite contacts (if any)
     * -- Prioritized search order ( personal > department > organization > auto_out > isspost > global )
     * -- Attempts to determine what the search string represents (multi-tier search):
     *    - If it contains (at) or search string ends with a typical web TLD (.com, .net, .org, .aero), it searches in e-mail addresses
     *    - Otherwise (or when not located by e-mail), it attempts to locate the contact name starting with the query string
     *    - If nothing found, looks into xdata, by prepending the search string with: "company":" or "phone":" (the most common fields)
     *    - If still nothing found it returns a special contact (with id 0) which says: "No results", + TODO: user's favourite contacts (if any)
     *
     * @param MVC_Request $rq
     */
    public function action_Search_Contacts(MVC_Request $rq) {
        $empty_init = array(
            'id' => 0,
            'org_id' => 0,
            'type' => 'system',
            'name' => "Start typing to search contacts",
            'email' => "You can always type an e-mail address into the box."
        );

        $rq->add_Filter("check_editable", "boolean");

        if (isset($rq->id)) {
            // We have an exact id, this function should return exactly one row, ignoring any other parameters (this is used to reopen the contact that has been previously selected)
            $contact = $this->con->read($rq->id);
            if ($rq->check_editable && $this->check_Edit_Ability($contact))
                $contact['editable'] = true;

            if ($contact)
                return $this->send_JSON(array($contact));
            else
                return $this->send_JSON(array());
        }

        // Preset mode - Return a fixed list of results (controlled by the "initialize" parameter) that do not depend on any other factors like query, scope
        if (isset($rq->initialize) && !isset($rq->q)) {
            $initialize = @json_decode($rq->initialize, true);
            // Defaults to "string" (so compatible with old initialize)
            if (empty($initialize))
                $initialize = array(
                    'method' => $rq->initialize
                );

            switch ($initialize['method']) {
                // Do not return anything ( the dropdown doesn't show )
                case 'empty':
                    return $this->send_JSON(array());

                case 'recent':
                    $cookie_name = "its_recent_contacts";
                    // User mode?
                    if (isset(MVC_Core::$ss->user))
                        $cookie_name .= "_" . MVC_Core::$ss->user['id'];

                    $cookies = new MVC_Persist('cookie');
                    $id_list = $cookies->{$cookie_name};
                    // Initialize if necessary ( if there is nothing saved yet )
                    if (!$id_list || !is_array($id_list))
                        $id_list = array();
                    break;

                // Check some special prefixes to the initialize variable - UNUSED
                /* 				case 'issue':
                  // Get the list
                  if(MVC_Core::$ss-> user) {
                  if(isset(MVC_Core::$ss-> user['is_admin']))
                  $visibility_type = 'unrestricted';
                  else {
                  // Read very basic information about the issue, to see if the user from the organization owning the issue, or a partner to which the issue was shared with. Visiblity depends on this.
                  $m_issue = new App_Model_Issue();
                  $issue_basic = $m_issue-> read($issue_id, array()); // , MVC_Core::$ss-> user['org_id']
                  if(empty($issue_basic))
                  return $this-> send_JSON(array()); // Ooops, attempting to see contacts from an issue the user doesn't have access to

                  if($issue_basic['org_id'] == MVC_Core::$ss-> user['org_id'])
                  $visibility_type = 'org';
                  else
                  $visibility_type = 'partner';
                  }
                  } else {
                  $visibility_type = 'public';
                  }
                  // Read the issue-linked contacts and offer them as initial list
                  $contact_links = $this-> con-> con2iss_Get_List($issue_id, null, $visibility_type, MVC_Core::$ss-> user['org_id']);
                  $id_list = array();
                  foreach($contact_links as $link)
                  $id_list[] = (int)$link['contact_id'];

                  break;
                 */

                case 'comm_target':
                    if (!empty($initialize['dep_ids'])) {
                        // New target contacts
                        $con_ids = $this->org->targetContact_Get_IDs($initialize['org'], $initialize['rt_key'], $initialize['dep_ids'], true);
                        if (!empty($con_ids))
                            $ctx = $this->con->get_List($initialize['org'], $con_ids, 'idlist', array());
                    } else {
                        $con_ids = $this->org->targetContact_Get_IDs($initialize['org']);
                        // Fallback to old universal target contact when departments aren't set up properly (none set to recieve)
                        $ctx = $this->con->get_List($initialize['org'], $con_ids, "idlist", array('contact', 'xdata'));
                    }
                    if (isset($ctx) && is_array($ctx))
                        return $this->send_JSON($ctx);
                    else
                        return $this->send_JSON(array(array(
                                        'id' => 0,
                                        'org_id' => 0,
                                        'type' => 'system',
                                        'name' => "Predefined address is not available",
                                        'email' => "Enter the recipient's E-mail address in order to send your data.<br />This box is <b>NOT</b> for your own e-mail!"
                        )));
                    break;

                default:
                    return $this->send_JSON(array($empty_init));
            }

            if (empty($id_list))
                return $this->send_JSON(array($empty_init));
            else {
                $id_csv_list = implode(",", $id_list);
                $ctx = $this->con->get_List(null, $id_list, "idlist", array('contact', 'xdata'),
                        "FIELD(`ctx`.id, {$id_csv_list})", 7); // Order by the presented ID list ; Max 7 results

                if ($rq->check_editable)
                    foreach ($ctx as &$contact)
                        if ($this->check_Edit_Ability($contact))
                            $contact['editable'] = true;

                return $this->send_JSON($ctx);
            }
        }

        // Search mode - $csscope and $q are used --- Currently check_editable not enabled on search results (not needed)
        // Decode contact search scope
        if (isset($rq->css))
            $csscope = @json_decode($rq->css, true);
        if (!isset($csscope) or is_null($csscope))
            $csscope = array();

        // Prepare parameters
        $types = array();
        $locator = array();

        // Issue linked - They take priority above everything else
        $id_list = array();
        if (isset($csscope['isslink'])) { // Only used when logged in - staff area
            // Get the list
            if (MVC_Core::$ss->user['is_admin'])
                $visibility_type = 'unrestricted';
            else {
                // Read very basic information about the issue, to see if the user from the organization owning the issue, or a partner to which the issue was shared with. Visiblity depends on this.
                $m_issue = new App_Model_Issue();
                $issue_basic = $m_issue->read($csscope['isslink'], array()); // , MVC_Core::$ss-> user['org_id']
                if (empty($issue_basic))
                    return $this->send_JSON(array()); // Ooops, attempting to see contacts from an issue the user doesn't have access to

                if ($issue_basic['org_id'] == MVC_Core::$ss->user['org_id'])
                    $visibility_type = 'org';
                else
                    $visibility_type = 'partner';
            }

            // Read the issue-linked and offer them in the search results as well.
            $contact_links = $this->con->con2iss_Get_List($csscope['isslink'], null, $visibility_type, MVC_Core::$ss->user['org_id']);
            foreach ($contact_links as $link)
                $id_list[] = (int) $link['contact_id'];
        }

        if (isset($csscope['user_self']) && !empty(MVC_Core::$ss->user)) {
            $org_id = (int) MVC_Core::$ss->user['org_id'];
            $locator['user_id'] = (int) MVC_Core::$ss->user['id'];
            $types[] = "user_self";
        }

        // Personal contacts, user_id is needed (so only contacts of the logged in user are searched for)
        if (isset($csscope['personal']) && $csscope['personal'] == true && !empty(MVC_Core::$ss->user)) {
            $org_id = (int) MVC_Core::$ss->user['org_id'];
            $locator['user_id'] = (int) MVC_Core::$ss->user['id'];
            $types[] = "personal";
        }
        // Department contacts, con_id is needed and will match the user's active department. TODO: Includes system users that are in the user's active department (TODO: but only if the user has a certain privilege to see them)
        if (isset($csscope['department']) && $csscope['department'] == true && !empty(MVC_Core::$ss->user)) {
            $org_id = (int) MVC_Core::$ss->user['org_id'];
            $locator['dep_id'] = (int) MVC_Core::$ss->user['cdep_id'];
            $types[] = "department";
        }
        // Organization contacts (those available to all users of the organization).
        if (isset($csscope['organization']) && $csscope['organization'] == true && !empty(MVC_Core::$ss->user)) {
            $org_id = (int) MVC_Core::$ss->user['org_id'];
            $types[] = "organization";
        }

        // Issue submit targets - Search among the contacts of type "issue_target" belonging to a certain organization
        if (isset($csscope['isstarget']) && $csscope['isstarget'] > 0) {
            $org_id = (int) $csscope['isstarget'];
            $types[] = "issue_target";
        }

        // When searching in either department and organization contacts, also search system user contacts
        if ((isset($csscope['department']) && $csscope['department'] == true) || (isset($csscope['organization']) && $csscope['organization'] == true)) {
            // if(having privilege to see colleagues)
            $types[] = "user_self"; // Includes system users in the same department or organization (TODO: but only if the user has a certain privilege to see them)
        }

        // Other contacts to which the user has send messages to (auto_out), when address was manually typed (instead of using the contact locator)
        // -- These contacts can be converted by the user into personal, department, organization or global contacts (assuming he has the required privileges)
        if (isset($csscope['auto_out']) && $csscope['auto_out'] == true && !empty(MVC_Core::$ss->user)) {
            $locator['user_id'] = (int) MVC_Core::$ss->user['id'];
            $types[] = "auto_out";
        }
        // Contacts which have been created from posting issues to the system - They are related to the user's organization
        if (isset($csscope['isspost']) && $csscope['isspost'] == true) {
            $org_id = (int) MVC_Core::$ss->user['org_id'];
            $types[] = "isspost";
        }

        // Yellow-pages type contacts, available to anyone
        if (isset($csscope['global']) && $csscope['global'] == true) {
            $types[] = "global";
        }

        // Drafts are similar to personal contacts, but the might not have an associated user - They are checked last
        /*
          if(isset($csscope['draft']) && $csscope['draft']==true) {
          if(isset(MVC_Core::$ss-> user)) {
          $org_id = (int)MVC_Core::$ss-> user['org_id'];
          $locator['user_id'] = (int)MVC_Core::$ss-> user['id'];
          }
          $types[] = "draft";
          }
         */

        // Also use recent. Append to IDList
        if (isset($csscope['recent']) && $csscope['recent'] == true) {
            // Append (merge) $id_list with $recent_list
            $id_list = array_unique(array_merge($id_list, $this->recent_list));
        }

        // When no types are specified, only the favourite contacts are returned
        // TODO: Helper function for loading user's favourite contacts - since there are several cases in which these are needed
        if (empty($types) && empty($id_list)) {
            return $this->send_JSON(array(array(
                            'id' => 0,
                            'org_id' => 0,
                            'type' => 'system',
                            'name' => "No results", //All search options are unchecked",
                            'email' => "No E-mails are available at this moment"//"Please select at least one type of contact or there's nothing to search for !"
            )));
        }

        if (!empty($types)) {
            // Prepare the "list" for the FIELD() function of MySQL, which is used for custom sorting (results are returned in this preffered order)
            $mysql_field_function = "FIELD(`ctx`.`type`";
            foreach ($types as $type)
                $mysql_field_function .= ", '{$type}'";
            $mysql_field_function .= ")";
        }

        $wtr = array('contact', 'xdata');
        if (isset($csscope['department']) && $csscope['department'] == true && !(isset($csscope['organization']) && $csscope['organization'] == true)) {
            $wtr[] = 'user';
            $dep_user_filter = true;
        }

        // First, attempt to locate the text withing the $id_list, but still checking the query string
        /// The order is by the list of IDs present in $id_list
        if (!empty($id_list)) {
            $id_csv_list = implode(",", $id_list);
            $ctx = $this->con->get_List(null, $id_list, "idlist", $wtr,
                    "FIELD(`ctx`.id, {$id_csv_list})",
                    6, 0,
                    function (QueryFactory &$q) use ($rq) {// The extra filtering, since this is a search
                        $qs = trim($rq->q);

                        // If the query string is in the format: "Name <email@address>" (the way the JS part places it into the combobox after a successful result), extract the e-mail and search by it
                        if (preg_match("/<(.*@.*)>/", $qs, $m))
                            $qs = $m[1];

                        if (strpos($qs, "@") !== false || in_array(substr($qs, -4), array(".com", ".org", ".net")) || in_array(substr($qs, -5), array(".aero")))
                        // The query string contains an @ sign or it ends with a typical domain name, an e-mail address is probably typed in, attempt to locate in the "email" field
                            $q->where("`ctx`.`email` LIKE(?)", "%$qs%");
                        else
                        // Unknown, might be name or e-mail, search in both
                            $q->where("`ctx`.name LIKE(?) OR `ctx`.email LIKE(?)", "%$qs%", "%$qs%");
                    }
            );

            // If not located in the ID list and there's no other type of contact to search, return the empty result information
            if (empty($ctx) && empty($types)) {
                $ctx[] = array(
                    'id' => 0,
                    'org_id' => 0,
                    'type' => 'system',
                    'name' => "No recipient address currently available",
                    'email' => "You can always type an e-mail address into the box."
                );
            }
        }

        // Phase 1 Search
        if (empty($ctx)) {
            $ctx = $this->con->get_List(
                    isset($org_id) ? $org_id : null, // Org ID if one of types "personal", "department", "organization" are present in the search scope
                    $locator, // The "locator" might contain user id and department id (for certain types)
                    $types, // Array with types of contacts that will be searched
                    $wtr, // Read and decode Xdata too, it will be needed
                    $mysql_field_function . ", `ctx`.`name` ASC", // Sort by type first (custom order), then name
                    6, 0, // Up to 6 results here, first in the list
                    function (QueryFactory &$q) use ($rq) {// The extra filtering, since this is a search
                        $qs = trim($rq->q);

                        // If the query string is in the format: "Name <email@address>" (the way the JS part places it into the combobox after a successful result), extract the e-mail and search by it
                        if (preg_match("/<(.*@.*)>/", $qs, $m))
                            $qs = $m[1];

                        if (strpos($qs, "@") !== false || in_array(substr($qs, -4), array(".com", ".org", ".net")) || in_array(substr($qs, -5), array(".aero")))
                        // The query string contains an @ sign or it ends with a typical domain name, an e-mail address is probably typed in, only attempt to locate in the "email" field
                            $q->where("`ctx`.email LIKE(?)", "%$qs%");
                        elseif (strpos($qs, " ") !== false)
                        // The query string contains a space, it's definitely not an e-mail, search just in names
                            $q->where("`ctx`.name LIKE(?)", "%$qs%");
                        else
                        // Unknown, might be name or e-mail, search in both
                            $q->where("`ctx`.name LIKE(?) OR `ctx`.email LIKE(?)", "$qs%", "$qs%"); // Only left to right, to avoid weird results


                            
// If only department-wide is selected but not the organization-wide, limit system user search to the same department as the logged in user's
                        if (isset($dep_user_filter))
                            $q->where("CASE WHEN `ctx`.`type` IN('user_self') THEN `usr`.`cdep_id` = ? ELSE 1 END", $locator['dep_id']);
                    }
            );
        }
        // Initial search by e-mail or name failed, attempt to locate stuff into Xdata, but only when search list for missing Q value is not present
        // Phase 2 Search
        if (empty($ctx)) {
            $ctx = $this->con->get_List(
                    isset($org_id) ? $org_id : null, $locator, $types, array('contact', 'xdata'),
                    $mysql_field_function . ", `ctx`.`name` ASC", 6, 0,
                    function (QueryFactory &$q) use ($rq) {
                        $qs = trim($rq->q);
                        // Search in Xdata field (which might be slow) - Only for start of JSON strings
                        // TODO: Special indexing algorithm that uses xdata fields to locate contacts, and searches very fast (and also more correct, current search in xdata is very rudimentary)
                        $q->where("`ctx`.`xdata` LIKE (?)", "%\":\"$qs%");
                    }
            );
        }

        // Still no results ? Create a fake contact entry which has ID 0 and contains the name "No results" - JavaScript will know not to use this when clicked on
        if (empty($ctx)) {
            $ctx[] = array(
                'id' => 0,
                'org_id' => 0,
                'type' => 'system',
                'name' => "Search returned no results",
                'email' => "You can always type an E-mail address into the box"
            );
        }

        // TODO: Also append user's favourites if they exist
        // $ctx[0]['name'].=", returning only your favourites";

        $this->send_JSON($ctx);
    }

    /**
     * Saves a contact id ($_POST['id']) to list of most recently used contacts (max 25?), stored in a cookie.
     * -- The cookie name contains User ID, so different user will have different lists.
     * -- However, if no user is logged in, there is only one unique list for each browser/cookie repository.
     *
     * TODO: Put this functionality in the contact model instead, not here, so it can be used by other controller/actions
     *
     * @param bool $respond JSON responses can be disabled if this method is called from another action.
     */
    public function action_Add_To_Recent($respond = true, $override_id = null) {
        if (is_null($override_id)) {
            $post = new MVC_Request('post');
            if (!isset($post->id))
                return $respond ? $this->send_Fail_JSON("No contact id present in the request.") : false;

            $id = $post->id;
        } else
            $id = (int) $override_id;

        $cookie_name = "its_recent_contacts";
        // User mode?
        if (isset(MVC_Core::$ss->user))
            $cookie_name .= "_" . MVC_Core::$ss->user['id'];

        // Read the cookie (content should be a json string)
        $cookies = new MVC_Persist('cookie');
        $recent_contacts = $cookies->{$cookie_name};
        // Initialize if necessary ( if there is nothing saved yet )
        if (!$recent_contacts || !is_array($recent_contacts))
            $recent_contacts = array();

        // Check if the contact is already in the list, if yes, nothing to do
        if (count($recent_contacts) > 0 && in_array($id, $recent_contacts))
            return $respond ? $this->send_JSON(array('success' => true)) : true;

        // If already at max, delete the oldest one
        if (count($recent_contacts) >= 25)
            array_shift($recent_contacts);

        $recent_contacts[] = $id;

        // Finally, save the cookie.
        $cookies->{$cookie_name} = $recent_contacts;
        return $respond ? $this->send_JSON(array('success' => true)) : true;
    }

    /**
     * Creates or saves a contact. (Move the function from the communication module here, since ability to create contacts should exist now throughout the application).
     * -- Creating or saving a contact also adds it to the list of recently used ($this-> action_Add_To_Recent)
     */
    public function action_Save_Contact(MVC_Request $post) {
        $contact_data = array();
        // Name and E-mail are not mandatory for Update, but they are for Create
        if (!empty($post->name)) {
            $contact_data['name'] = trim($post->name);
            // "name" should not be an e-mail ...
            if (preg_match('/^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,6}))$/', $contact_data['name']))
                return header("HTTP/1.0 400 Please write your name or company name in the NAME field, and not your E-mail !");
            if (preg_match('/[<>:;@\,"]/', $contact_data['name']))
                return header("HTTP/1.0 400 The name must not contain invalid characters: &lt; &gt; , : ; @ \\ &quot;");
            // Extra filter - Disallow comma "," and a few other characters in the name <>:;@\,"
//            if (strpos($contact_data['name'], '.') === 0 || strrpos($contact_data['name'], '.') === strlen($contact_data['name']) - 1)
//                return header("HTTP/1.0 400 The name must not start or end with a dot");
        }
        if (!empty($post->email)) {
            $contact_data['email'] = trim($post->email);
        }
        // Other contact info (xdata)
        if ($post->xdata != "{}")
            $contact_data['xdata'] = $post->xdata;
        else
            $contact_data['xdata'] = null;

        $post->add_Filter('stealth', 'boolean');

        if (!empty($post->id)) {
            // Edit mode
            // TODO: Check if this contact can actually be edited by the logged in user or contact account (drafts can be edited by anyone, even not logged in)
            // New function - Determine if the contact is being used in any messages. If yes, and the email itself is changed, instead of updating a contact a new copy is created and it's ID is returned
            // This allows all messages to retain the original sender/recipients address, even if the contact record is altered
            // A. Read the contact to determine if E-mail is changed or not (Xdata not needed)
            $orig_con = $this->con->read($post->id, false);
            if ($orig_con === false)
                return header("HTTP/1.0 400 Original contact with ID {$post->id} not found. Maybe it was just deleted?");

            if (isset($contact_data['email']) && $contact_data['email'] != $orig_con['email']) {

                // B. E-mail was changed, check how many messages are using it (either in "from_id" or as part of the "recipients")
                $count_msgs = $this->con->count_Messages($post->id);

                // C. At least one message used it
                if ($count_msgs > 0) {
                    $iid = $this->con->copy($post->id);
                    if (!$iid)
                        return header("HTTP/1.0 500 Contact could not be updated, possible database error");

                    $copy_mode = true;
                    // Update the original contact, marking it as "copied" - This contact should not be visible in any address book (but should still be visible in the messages)
                    $this->con->update($post->id, array('state' => "copied"));
                    // D. Update contact account to point to the new copied contact
                    $m_cac = new App_Model_ContactAccount();
                    $m_cac->update_to_Contact($orig_con['id'], $iid, $contact_data['email']);

                    // Switch to the copy
                    $post->id = $iid;
                } else {
                    // When same contact is kept, we still have to determine if this is the contact used as the "account", and change the login e-mail:
                    if (MVC_Core::$ss->contact_account) {
                        $cac = MVC_Core::$ss->contact_account;
                        if ($cac['id'] == $orig_con['id']) {
                            $m_cac = new App_Model_ContactAccount();
                            $m_cac->update($cac['id'], null, null, false, false, $contact_data['email']);
                        }
                    }
                }
            }

            // Draft accounts get their ctime changed every time they are updated
            if ($orig_con['type'] == 'draft')
                $contact_data['ctime'] = date("Y-m-d H:i:s");

            $r = $this->con->update($post->id, $contact_data, true);
            if (!$r)
                return header("HTTP/1.0 500 Contact could not be updated, possible database error.");

            // Also update user data if the contact is of a user (Superuser editing other users)
            if ($post->update_user && $post->type == 'user_self' && isset($contact_data['name']) && isset($contact_data['email']) && isset($post->user_id)) {
                $this->user->update($post->user_id, array(
                    'name' => $contact_data['name'],
                    'email' => $contact_data['email']
                ));
            }

            $this->send_JSON(array(
                'success' => true,
                'replaced' => true,
                'id' => (int) $post->id,
                'response' => "Contact has been updated"
            ));
            if ($post->stealth !== true)
                $this->action_Add_To_Recent(false);
        } else {
            // Create mode - In create mode Name and E-mail are mandatory, if not present in the request an error is returned
            if (!isset($contact_data['name']) || !isset($contact_data['email']))
                return header("HTTP/1.0 400 Name and E-mail are mandatory for creating a new contact");

            if ($post->label && !$post->type) {
                if (substr($post->label, 0, 5) == 'user_') {
                    $selector = substr($post->label, 5);

                    if ($selector == 'SELF')
                        $type = "user_self";
                    else
                        $type = "personal";

                    // Create a contact linked to a label?
                    if (is_numeric($selector)) {
                        $contact_data['label_id'] = (int) $selector;
                    }
                } elseif (substr($post->label, 0, 4) == 'dep_') {
                    $selector = substr($post->label, 4);

                    if (is_numeric($selector)) {
                        $type = "department";
                    } else {
                        $type = "organization";
                    }

                    if ($post->lab_id)
                        ;
                    $contact_data['label_id'] = (int) $post->lab_id;
                } elseif (substr($post->label, 0, 4) == 'org_') {
                    $selector = substr($post->label, 4);

                    if ($selector == 'SELF')
                        $type = "org_self";
                    elseif ($selector == 'OLDTARGET')
                        $type = "issue_target";
                    else
                        $type = "organization";

                    if ($post->lab_id)
                        ;
                    $contact_data['label_id'] = (int) $post->lab_id;
                } elseif ($post->label == "global") {
                    $type = "global";
                } else
                    return header("HTTP/1.0 403 Unable to detect where to save the contact; Cannot create.");
                $post->type = $type;
            }

            //== E-mail address descovery ==//
            // Try to find if the contact doesn't already exist (same e-mail). If also matching name, the contact record is reused and it's ID returned (as in, not creating another).
            // If only the e-mail matches, another contact is created, but it is added to the list of managed contacts for a contact account with that e-mail.
            // E-mails matching system users ( contact found with type "user_self" ) are always returned. There can only be ONE conect of type "user_self" for one e-mail address. The name is also returned/corrected when detecting such a contact.

            if ($post->type == 'draft') {
                // First, find if there is an user account contact for the specified e-mail address
                $contact = $this->con->locate("user_self", $contact_data['email'], null, null, null, null, false, true); // Return just one (System users have unique addresses)
                if (is_array($contact)) {
                    // Detected user contact, return information about it
                    $this->send_JSON(array(
                        'success' => true,
                        'id' => (int) $contact['id'],
                        'response' => sprintf("Detected and using existing platform user contact with name: <b>%s</b>", $contact['name']),
                        'name' => $contact['name'],
                        'type' => $contact['type']
                    ));
                    if ($post->stealth !== true)
                        $this->action_Add_To_Recent(false, $contact['id']);
                    return;
                }

                // Second, attempt to locate the contact in existing issue submit contacts (isspost), and reuse if detected
                $con_results = $this->con->locate("isspost|isslink", $contact_data['email']);
                if (is_array($con_results)) {
                    foreach ($con_results as $contact) {
                        // If matching one of the names, use the exact id. If not, let the contact create, but also find the contact account for the specified mail (if it exists) and add the newly created contact to it.
                        if ($contact['name'] == trim($contact_data['name'])) {
                            $this->send_JSON(array(
                                'success' => true,
                                'id' => (int) $contact['id'],
                                'response' => sprintf("Detected and reusing previously recorded contact with name: <b>%s</b>", $contact['name']),
                                'type' => $contact['type']
                            ));
                            if ($post->stealth !== true)
                                $this->action_Add_To_Recent(false, $contact['id']);
                            return;
                        }
                    }
                    // No exact match found, then let it be added
                }
            }

            // Depending on the context, $org_id, $user_id and $dep_id are set
            $org_id = null;
            $user_id = null;
            $dep_id = null;
            // Logged in ? Check if creating for self or for another
            if (isset(MVC_Core::$ss->user)) {
                // Only admin can create or edit contacts for other organizations or users (for now); In the (near) future it will be an user function as well.
                if ($this->user->is_Unrestricted()) {
                    if (isset(MVC_Core::$ss->perspective))
                        $org_id = MVC_Core::$ss->perspective;
                    elseif ($post->org_id)
                        $org_id = $post->org_id;
                    else
                        $org_id = MVC_Core::$ss->user['org_id']; // Nothing specified, admin is creating contact for his own organization
                    if ($post->user_id)
                        $user_id = $post->user_id;
                    else
                        $user_id = MVC_Core::$ss->user['id'];
                } else {
                    // Non-admin always creates for self (or own organization)
                    $org_id = MVC_Core::$ss->user['org_id'];
                    $user_id = MVC_Core::$ss->user['id'];
                }
                // Department level contacts can be created for both own department or another
                if ($post->dep_id)
                    $dep_id = $post->dep_id;
                elseif ($post->type == 'department') {
                    if (is_numeric($selector))
                        $dep_id = (int) $selector;
                    else
                        $dep_id = MVC_Core::$ss->user['cdep_id'];
                }
            } else {
                // Not logged in - Create for the organization which will receive this contact, or "null" if unknown
                if ($post->org_id)
                    $org_id = $post->org_id;
            }

            switch ($post->type) {
                // Draft contact. This isn't normally linked to anything. Drafts are converted to other types at a later stage, or automatically deleted by the system if they are too old.
                case 'draft':
                    break;
                // User's own address(es) or personal contacts
                case 'personal':
                case 'user_self':
                    break;
                // User's organization's address(es)
                case 'org_self':
                case 'dep_self':
                    break;
                // Organization wide contacts
                case 'organization':
                case 'department':
                case 'issue_target': // @deprecated, a different system is used now
                    break;
                case 'global':
                    $org_id = null; // Global contacts do not belong to an organization
                    break;
                // Contact types which cannot be created by actions from the user interface. These are system-created contacts (they can be converted to other types later)
                case 'system':
                case 'auto_out':
                case 'auto_in':
                case 'unknown':
                default:
                    return header("HTTP/1.0 403 Not possible to manually create contact of type {$post->type}.");
            }

            $iid = $this->con->create($post->type, $org_id, $contact_data, $user_id, $dep_id, true);

            if (!$iid)
                return header("HTTP/1.0 500 Contact could not be created, possible database error.");

            // If the submitter is sent, check if there is a contact account under this e-mail address, and add the $iid to the list of managed contacts.
            if ($post->role && $post->role == "submitter") {
                $m_cac = new App_Model_ContactAccount();
                // When contact account is logged in, use it - If not, find it
                if (MVC_Core::$ss->contact_account)
                    $cac = MVC_Core::$ss->contact_account;
                else
                    $cac = $m_cac->read($contact_data['email']);
                if ($cac) {
                    $cac['managed_ctx'] = explode(',', $cac['managed_ctx']);
                    if (!in_array($iid, $cac['managed_ctx'])) {
                        $cac['managed_ctx'][] = (int) $iid;
                        $cac['managed_ctx'] = implode(',', $cac['managed_ctx']);
                        // If Contact Acccount logged in, update the in-session managed contact list to contain the newly created contact
                        if (MVC_Core::$ss->contact_account)
                            MVC_Core::$ss->contact_account['managed_ctx'] = $cac['managed_ctx'];
                        // And update the database
                        $m_cac->update($cac['id'], $cac['managed_ctx']);
                    }
                }
            }

            $this->send_JSON(array(
                'success' => true,
                'id' => $iid,
                'response' => sprintf("A contact record has been created for: %s", $contact_data['email']),
                'type' => $post->type // Some calls expect this to exist in the response
            ));
            if ($post->stealth !== true)
                $this->action_Add_To_Recent(false, $iid);
        }
    }

    /**
     * Send OTP via Email to new user
     * @param MVC_Request $post
     */
    public function action_Email_Contact(MVC_Request $post) {
        $contact_data = array();
        // Name and E-mail are not mandatory for Update, but they are for Create
        if (!empty($post->name)) {
            $contact_data['name'] = trim($post->name);
            // "name" should not be an e-mail ...
            if (preg_match('/^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,6}))$/', $contact_data['name']))
                return header("HTTP/1.0 400 Please write your name or company name in the NAME field, and not your E-mail !");
            if (preg_match('/[<>:;@\,"]/', $contact_data['name']))
                return header("HTTP/1.0 400 The name must not contain invalid characters: &lt; &gt; , : ; @ \\ &quot;");
            // Extra filter - Disallow comma "," and a few other characters in the name <>:;@\,"
            if (strpos($contact_data['name'], '.') === 0 || strrpos($contact_data['name'], '.') === strlen($contact_data['name']) - 1)
                return header("HTTP/1.0 400 The name must not start or end with a dot");
        }
        if (!empty($post->email)) {
            $contact_data['email'] = trim($post->email);
        }
        if (!isset($contact_data['name']) || !isset($contact_data['email']))
            return header("HTTP/1.0 400 Name and E-mail are mandatory for creating a new contact");

        $m_comm = new App_Model_Comm();
        $content = '<h3>Your OTP for Email Verification: ' . $post->otp . '</h3>';
        $r = $m_comm->email_Open_Message($post->email, $post->name, $content); // true = standalone message, no conversation.
        if (!$r)
            return header("HTTP/1.0 500 Unable to send the recovery E-mail. Please contact support");

        $this->send_JSON(array(
            'success' => true,
            'response' => sprintf("An OTP is sent to : %s", $contact_data['email']),
        ));
    }

    /**
     * Deletes a contact from the database.
     * -- TODO: Check if whoever is calling this function does actually have the right to delete this contact. Some contacts can't be deleted.
     * -- TODO: Make sure this function actually updates issues, conversations and any other elements connected to a contact.
     * -- TODO: Update counters to where this contact is present
     *
     * @param MVC_Request $in
     * @return boolean
     */
    public function action_Delete_Contact() {
        $post = new MVC_Request('post');

        if (!$post->id)
            return $this->send_Fail_JSON("Contact ID not present in the request.");

        return $this->send_Fail_JSON("This function is disabled until it is secured properly.");

        // Deletes the contact
//		$r = $this-> con-> delete($post-> id);
//		if($r)
//			$this-> send_JSON(array('success'=> true));
//		else
//			$this-> send_Fail_JSON("Contact could not be deleted, possible database error.");
    }

    /**
     * Analyzes an input text for one or more e-mail addresses.
     * If it detects existing e-mails in the system, it also returns their ID's and other info
     * If not, it just returns the E-mail address.
     *
     * If the text doesn't contain valid E-mails, it will return an error code, which should be interpreted by the interface as a failed contact search (display with red dots)
     */
    public function action_Scan_For_Contacts(MVC_Request $rq) {
        if (empty($rq->text))
            return $this->send_JSON(false);

        if (!empty(MVC_Core::$ss->user))
            $org_id = $this->user->is_Unrestricted() ? (isset(MVC_Core::$ss->perspective) ? MVC_Core::$ss->perspective : $rq->org_id) : MVC_Core::$ss->user['org_id'];
        else
            $org_id = null; // No organization because not logged in.


            
// TODO: Names within quotes should not be broken apart, instead, they should be considered a single identifier
        // TODO: This procedure currently doesn't support names with "," in them ( multiple people, one e-mail )
        // Locate certain characters which might separate multiple e-mail addresses ; , / ( | is not used as separator )
        // - If any found, break the text into an array and scan each piece individually
        $fragments = preg_split('/;|,|\/|\s/', $rq->text, -1, PREG_SPLIT_NO_EMPTY);
        $count_fragments = count($fragments);

        if ($count_fragments > 1) {
            //dd("Contains separators");
        }

        $analysis = array();

        $name_check = '';
        $space = '';
        foreach ($fragments as $frag) {
            // Start interpreting...
            // TODO: This should verify if this looks like an actual e-mail... and not this simple crap.
            if (!preg_match('/<?(.*@[^>]*)>?/', $frag, $email_extract)) {
                // Not an e-mail - Could be a name; Continue testing until an E-mail has been found
                $name_check .= $space . $frag;
                $space = ' ';
            } else {
                // Found an e-mail ! - Is it a real e-mail ? If not, return as an error element
                if (!preg_match('/^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,6}))$/', $email_extract[1])) {
                    if (!empty($name_check)) {
                        $analysis[] = array(
                            'name' => $name_check,
                            'error' => $email_extract[1]
                        );
                        $name_check = '';
                        $space = ''; // Reset to prepare for next name
                    } else
                        $analysis[] = array(
                            'error' => $email_extract[1]
                        );
                    continue;
                }

                // If $name_check not empty, pair the name with the found e-mail
                if (!empty($name_check)) {
                    $analysis[] = array(
                        'name' => $name_check,
                        'email' => $email_extract[1]
                    );
                    $name_check = '';
                    $space = ''; // Reset to prepare for next name
                } else
                    $analysis[] = array(
                        // No name - use only E-mail part
                        'email' => $email_extract[1],
                        'name' => null
                    );
            }
        }

        // TODO: If an E-mail has been found, attempt to locate it in the user's available contacts (Self, Personal, Department(s), Organization, Yellow pages and "Linked")
        // The idea is to reuse an e-mail when found, and also show it's ID, instead of creating a new one with the same address...
        // For now attempting to create this into /global/comm::locate_Contact - Later it could be linked here somehow

        $wts = array("org_self", "dep_self", "user_self", "global");
        // Logged in ? Scan user's address book
        if (!empty(MVC_Core::$ss->user))
            $wts = array_merge(array("organization", "department", "personal", "auto_out"), $wts);

        // Message linked to a record ? Scan the already linked contacts and targets as well for that particular record type
        if ($rq->link) {
            $link = $rq->link;
            $wts = array_merge(array("isspost", "isslink", "issue_target"), $wts);
        } else
            $link = null;

        foreach ($analysis as &$fragment) {
            // Do not attempt to scan for invalid e-mails
            if (isset($fragment['error']))
                continue;

            $contact = $this->discover_Contact($fragment['email'], $wts, $link, $org_id);
            // We have a matching contact ! Use it.
            if (!empty($contact))
                $fragment = $contact;
        }

        if (!empty($name_check)) {
            // We have some text without an E-mail
            // TODO: Find contacts only by (partial) name? - Preferably only already linked ones

            if (!empty($analysis)) {
                // Already got a valid contact - Just append another one with error
                $analysis[] = array(
                    'error' => $name_check
                );
            } else
            // Nothing good, return general error
                return $this->send_JSON(false);
        }

        // Here we should start digging in the database for those e-mails, and return ID's if found...

        return $this->send_JSON($analysis);
    }

    /**
     * Tests SMTP values that are coming from the Contact Editor (E-mail sending settings)
     * Saves or updates a record with the passed request parameters, and if test is successful, it also marks the settings as "tested", switching sending to SMTP for that contact
     * FIXME: The "email_smtp" field in the users table should be renamed into "email_mode" with possible values: local, smtp, spf
     * FIXME: When editing a contact, if that contact is the primary contact of a user, the mode is changed; Doesn't matter if the user is changing it himself or is being changed by a supervisor
     *
     * @param MVC_Request $rq
     */
    public function action_Sending_Settings(MVC_Request $rq) {
        if (!$rq->send_mtd)
            return header("HTTP/1.0 400 Sending method not present in the request.");

        // Attempt to see if there's already settings saved for current account
        $server = $this->con->server_Read($rq->contact_id, true);

        // FIXME: This part is a bit messy... rethink
        if ($rq->send_mtd == 'local') {
            // Turn SMTP off
            if (!$server)
                return $this->send_JSON(array(
                            'response' => "Nothign to update! Default method is already selected."
                ));
            else {
                $this->con->server_Update($rq->contact_id, 'local');

                // Update logged in user if contact id matches, set parameter "email_smtp" to false
// 				if($rq-> contact_id == MVC_Core::$ss-> user['contact_id']) {
// 					$this-> user-> update(MVC_Core::$ss-> user['id'], array(
// 						'email_smtp'=> false
// 					));
// 					MVC_Core::$ss-> user['email_smtp'] = false;
// 				}

                return $this->send_JSON(array(
                            'response' => "SMTP/SPF is now disabled. Future E-mails will be sent by Cargohub"
                ));
            }
        } elseif ($rq->send_mtd == 'spf') {
            // Use SPF - This works like local, but with their own addres instead of the system default
            if (!$server) {
                // Not found, attempt to create
                $r = $this->con->server_Create($rq->contact_id, "spf");
                if (!$r) {
                    $error = MVC_Core::$debug->get_Last_Dump();
                    return header("HTTP/1.0 400 " . $error['data']);
                }
            } else {
                $this->con->server_Update($rq->contact_id, 'spf');
            }

            // Update logged in user if contact id matches, set parameter "email_smtp" to false
// 			if($rq-> contact_id == MVC_Core::$ss-> user['contact_id']) {
// 				$this-> user-> update(MVC_Core::$ss-> user['id'], array(
// 					'email_smtp'=> false
// 				));
// 				MVC_Core::$ss-> user['email_smtp'] = false;
// 			}

            return $this->send_JSON(array(
                        'response' => "Sending E-mail using the E-mail address. Please make sure SPF is enabled by your IT department !"
            ));
        }

        $smtp = $rq->smtp;
        if (!$server) {
            // Not found, attempt to create
            $r = $this->con->server_Create($rq->contact_id, trim($smtp['host']), $smtp['smtp_encryption'], $smtp['smtp_auth'], array(
                'username' => trim($smtp['username']),
                'password' => trim($smtp['password'])
                    ), trim($smtp['port']));

            if (!$r) {
                $error = MVC_Core::$debug->get_Last_Dump();
                return header("HTTP/1.0 400 " . $error['data']);
            }
        } else {
            // When found, compare current sent settings with the saved ones, if matching, nothing to do
            if (
                    $server['tested'] == true &&
                    $server['host'] == trim($smtp['host']) &&
                    $server['port'] == trim($smtp['port']) &&
                    $server['smtp_encryption'] == $smtp['smtp_encryption'] &&
                    $server['smtp_auth'] == $smtp['smtp_auth'] &&
                    $server['username'] == trim($smtp['username']) &&
                    $server['password'] == trim($smtp['password'])
            )
                return header("HTTP/1.0 400 Settings have not changed and are already tested. Nothing to do");
            // Something changed, save the new settings and retrigger test
            $r = $this->con->server_Update($rq->contact_id, trim($smtp['host']), $smtp['smtp_encryption'], $smtp['smtp_auth'], array(
                'username' => trim($smtp['username']),
                'password' => trim($smtp['password'])
                    ), trim($smtp['port']));

            if (!$r) {
                $error = MVC_Core::$debug->get_Last_Dump();
                return header("HTTP/1.0 400 " . $error['data']);
            }
        }

        // Perform testing with new settings
        $test_result = $this->con->server_Test($rq->contact_id, true);
        if ($test_result['tested'] == false) {
            $error = MVC_Core::$debug->get_Last_Dump();
            return header("HTTP/1.0 403 FAILED | " . $error['data']);
        }

        // Update current user, set parameter "email_smtp" to 1
// 		if($rq-> contact_id == MVC_Core::$ss-> user['contact_id']) {
// 			$this-> user-> update(MVC_Core::$ss-> user['id'], array(
// 				'email_smtp'=> true
// 			));
// 			MVC_Core::$ss-> user['email_smtp'] = true;
// 		}

        return $this->send_JSON(array(
                    'response' => "SMTP test successful ! Future E-mails will be sent directly by this mailbox"
        ));
    }

    /**
     * Turns SMTP off for a contact. Doesn't matter if it was working or not before.
     * FIXME: This function is probably not needed anymore
     * @param MVC_Request $rq
     */
    public function action_Deactivate_SMTP(MVC_Request $rq) {
        $this->con->server_Update($rq->contact_id, false);
        return $this->send_JSON(array(
                    'response' => "SMTP/SPF is now disabled. Future E-mails will be sent by Cargohub"
        ));

        // Update current user, set parameter "email_smtp" to 1
// 		$this-> user-> update(MVC_Core::$ss-> user['id'], array(
// 			'email_smtp'=> false
// 		));
// 		MVC_Core::$ss-> user['email_smtp'] = false;
    }

    public function action_Save_password_change_Contact(MVC_Request $rq) {
        $getPostData = array(
            'id' => $rq->id,
            'email' => $rq->email,
            'password' => $rq->password,
            'count_msgs' => $rq->count_msgs,
            'old_password' => $rq->old_password,
        );
//                    print_r($getPostData);

        $q_user_exist_in_contact_accounts = QueryFactory::select()->table(MVC_Core::$db->tab['cac'], '*')->where("`email` = ?", $rq->email);
        $r_user_exist_in_contact_accounts = MVC_Core::$db->query($q_user_exist_in_contact_accounts);
        foreach ($r_user_exist_in_contact_accounts as $rr) {
            $cac_list[] = $rr;
        }
        //                print_r($cac_list);
        if (count($cac_list) > 0) {
            if ($rq->type == 'admin_change_password') {
                MVC_Core::$db->query("UPDATE `contact_accounts` SET password='$rq->password' WHERE email='$rq->email' ");

                return $this->send_JSON(array(
                            'status' => "updated",
                            'response' => "Password Undated."
                ));
            } else if ($rq->type == 'user_change_password') {
                if ($rq->old_password == $cac_list[0]['password']) {
                    MVC_Core::$db->query("UPDATE `contact_accounts` SET password='$rq->password' WHERE email='$rq->email' ");
                    return $this->send_JSON(array(
                                'status' => "updated",
                                'response' => "Password Undated."
                    ));
                } else {
                    return $this->send_JSON(array(
                                'status' => "not_match",
                                'response' => "Old password not match."
                    ));
                }
            }
        } else {
            return $this->send_JSON(array(
                        'status' => "not_found",
                        'response' => "User not found."
            ));
        }
    }

    public function action_Send_auto_generated_password(MVC_Request $rq) {
        $new_pass = substr(str_shuffle("abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 0, 10);
        MVC_Core::$db->query("UPDATE `contact_accounts` SET password='$new_pass' WHERE email='$rq->email' ");
        $to = $rq->email;
        $subject = "Auto Generated Password";

        $mailData = array(
            'user_name' => 'User',
            'contact_email' => $to,
            'password' => $new_pass,
        );
        $template = $this->action_Get_mail_template($mailData);
        $m_comm = new App_Model_Comm();
        $content = $template;
        $r = $m_comm->send_custom_email($to, 'User', $content,$subject); // true = standalone message, no conversation.

        if ($r) {
            return $this->send_JSON(array(
                        'status' => "ok",
                        'new_pass' => $new_pass,
                        'response' => "Password sent to $to." . "\r\n" . "The new passsword is :  $new_pass"
            ));
        } else {
            return $this->send_JSON(array(
                        'status' => "no",
                        'response' => "Try again!"
            ));
        }


    }

    public function action_Get_mail_template($data) {
        $msg = '';
        $msg .= "<div style='max-width: 750px; margin: 30px auto; border: 1px solid lightgray; padding: 20px; border-radius: 10px; background-color: #eee;'>
    <h2>Dear " . $data['user_name'] . ",</h2>
    <p>This message was sent as the result of a <b>password reset procedure:</p>
    <p >Your new auto generated password is : " . $data['password'] . "</p>

<!--    <div style=\"padding: 40px; text-align: center; font-size: 1.5em;\">
            <a href=\"https://www.cargohub.aero/access?action=update_password&amp;email=" . $data['contact_email'] . "&amp;code=" . $data['password'] . "\" style=\"display: block; border-radius: 7px; background-color: #589acc; width: 380px; margin: 0 auto; padding: 12px 0; text-decoration: none; color: white;\">Click here to reset your password</a>
    </div>-->


    <p>If you did not request this, please contact <a href='mailto:support@cargohub.nl'>support@cargohub.nl</a><br />
    If the reset link is not followed, your account will continue to function normally.</p>

            <hr style=\"background-color: lightgray; border: none; height: 1px; margin: 5px 0 9px;\" />
    <h4 style='text-align: center;'>Thank you for using Cargohub Connect !</h4>
    </div>";

        return $msg;
    }

}
