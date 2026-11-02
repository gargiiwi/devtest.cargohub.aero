<?php

/**
 * "System User" related data access and input/output processing

 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect 
 * @subpackage DataModels  
 * @version 2.8
 */
class App_Model_User extends MVC_Model {

//----- User CRUD ----// 

    public static $pass_join_char = '~'; 
                
    /** 
     * Creates a SHA256 hash consisting of the concatenation between a config-defined salt, the username and the user's password
     * -- Changing either the username or the password requires a rehash for the account to work
     * -- Example: stored_pass = sha256("MYAPPHASH".'~'."wavetrex".'~'."12345");
     *
     * @param string $username
     * @param string $password 
     * @param string $custom_salt Use another salt than the global application salt
     */   
    private function hash_Password($username, $password, $custom_salt = null) { 
//		return $password;  
        return hash('sha256', ($custom_salt ? $custom_salt : MVC_Core::$settings['core']['security']['salt']) . self::$pass_join_char . $username . self::$pass_join_char . trim($password));
    }  

    /**
     * Generates a new random user password using any of the $alphabet characters
     *
     * @param int $length How many charactes should the password have ?
     * @return string
     */
    public static function generate_Random_Password($length = 12) {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*()-=[];/\\+';

        $pass = array(); //remember to declare $pass as an array

        for ($i = 0; $i < $length; $i++) {
            $n = rand(0, strlen($alphabet) - 1); //use strlen instead of count
            $pass[$i] = $alphabet[$n];
        }

        return implode($pass); //turn the array into a string
    }
    
      public static function return_encryp_password($user , $password)
      {
       return self::hash_Password($user , $password);
      }

    /**
     * Adds an user to the database
     *
     * @param array $user_data New user data, contains account name, password (is encrypted by this function), e-mail and other data
     * @return int The ID of the user that has been inserted in the database
     */
    public function create($user_data) {






        $mandatory_fields = array('user', 'password', 'name', 'email');
        $diff = array_diff($mandatory_fields, array_keys($user_data));
        if (!empty($diff))
            return $diff; // error, not all fields available in $user_data (the missing fields are returned)


















            
// any key outside mandatory+optional fields is discarded (to avoid database errors)
        $fields = array_merge($mandatory_fields, array('status', 'org_id', 'cdep_id', 'signature', 'language', 'inbox_id', 'sentbox_id'));
        foreach ($user_data as $field => $value)
            if (!in_array($field, $fields))
                unset($user_data[$field]);

        // Pre-Process
        $user_data['user'] = trim($user_data['user']);
        $user_data['security'] = self::hash_Password($user_data['user'], $user_data['password']);
        unset($user_data['password']); // not stored in clear, only as hash !
        // prepare query
        $q = QueryFactory::insert()
                ->table(MVC_Core::$db->tab['usr'])
                ->values($user_data);

        // and run it
        try {
            MVC_Core::$db->query($q);
            $insert_id = MVC_Core::$db->insert_id;
            $uPrefData = array(
                'logon_redirect' => '/user/overview',
                'jqx_theme' => 'fresh',
                'rt_id' => 1,
                'texted' => 'CodeMirror',
                'operations' => array(
                    'auto_contact' => true
                ),
            );
            $user_pref['user_id'] = MVC_Core::$db->insert_id;
            $user_pref['pkey'] = 'preferences';
            $user_pref['source'] = 'user';
            $user_pref['data'] = json_encode($uPrefData);

            $q2 = QueryFactory::insert()
                    ->table(MVC_Core::$db->tab['upr'])
                    ->values($user_pref);
            MVC_Core::$db->query($q2);

            return $insert_id;
        } catch (Exception $e) {
            // error, could not add user - possibly conflicting username (`user`) or e-mail address (`email`)
            return false;
        }
    }

    /**
     * Reads the user information and does some processing on some of the values.
     * -- The most common use for this method is the reading of all user's parameters at login time, and construction of the session variables.
     *
     * @param int $user_id
     * @param array $wtr Indicates what information to read ( if null or empty, just verify that the user ID actually exists )
     * @param bool $compute_acls Compute Access Control Lists directly in this method ( they might be computed outside, in which case "department" and "user2dep" entries are left intact
     * @param int $dep_id When specified, ACLs are computed in relation to THIS department ( the user must be member of ), and not to the user's currently active department
     */
    public function read($user_id, $wtr = null, $compute_acls = true, $dep_id = null) {
        $q = QueryFactory::select()
                ->where("`usr`.id = ?", $user_id)
                ->limit(1);

        // What to read?
        $wtr = self::wtr_Helper($wtr);

        if (in_array('user', $wtr))
        // all fields in the primary table except "security" and "pwreset" (which are never returned, just checked against the db)
            $q->table(MVC_Core::$db->table('usr'), 'id,user,status,oath_key,oath_turnon,logon_dt,ntf_dismiss_time,org_id,cdep_id,name,email,email_smtp,contact_id,signature,language,count_msgs,count_files,count_tasks,count_issues,count_unread,count_ctx,inbox_id,sentbox_id');
        elseif (in_array('user_comm', $wtr))
        // special case related to communications (which need this info but nothing of the others)
            $q->table(MVC_Core::$db->table('usr'), 'id,name,email,org_id,inbox_id,sentbox_id,contact_id,count_msgs,count_unread');
        else
        // certain important fields from the primary table
            $q->table(MVC_Core::$db->table('usr'), 'id,user,status,oath_turnon,cdep_id,org_id,contact_id');

        if (in_array('contact', $wtr))
        // Read detailed contact information for the user (contact xdata)
            $q->table(MVC_Core::$db->table('ctx'), QueryFactory::autoalias_Add_Prefix("xdata,count_msgs", '_ctx_'), 'left', '`usr`.`contact_id` = `ctx`.`id`');

        if (in_array('organization', $wtr)) {
            // organization data (necessary for ACLs)
            $q->table(MVC_Core::$db->table('org'), QueryFactory::autoalias_Add_Prefix("id,type_id,name,manager_id,features,privileges,access_ids,ist_or", '_org_'), 'left', '`usr`.org_id = `org`.id');

            // department data (also necessary for ACLs), but can only exist if the the organization data was also read
            if (in_array('department', $wtr)) {
                $q->table(MVC_Core::$db->table('dep'), QueryFactory::autoalias_Add_Prefix("name,chief_id,privileges_or,access_ids_or,ist_or", '_dep_'), 'left', !empty($dep_id) ? "`dep`.id' = {$dep_id}" : '`usr`.cdep_id = `dep`.id');
                $q->table(MVC_Core::$db->table('u2d'), QueryFactory::autoalias_Add_Prefix("comment,privileges_or,access_ids_or,disabled,hidden,ist_or", '_u2d_'), 'left', '`usr`.id = `u2d`.user_id AND ' . (!empty($dep_id) ? "`dep`.id' = {$dep_id}" : '`usr`.cdep_id = `u2d`.dep_id'));
            }
        }

        $r = MVC_Core::$db->query($q);
        if ($r && $r->num_rows == 1) {
            // user was found, read the data
            $r->split_by_prefixes = array(
                // tidy up
                'count' => "count_",
                // extra tables
                'contact' => "_ctx_",
                'organization' => "_org_",
                'department' => "_dep_",
                'user2dep' => "_u2d_"
            );
            $user = $r->current();

            // Post-Process
            $user['id'] = (int) $user['id'];
            $user['org_id'] = (int) $user['org_id'];
            $user['contact_id'] = (int) $user['contact_id'];

            // Full user data is read
            if (in_array('user', $wtr)) {
                $user['cdep_id'] = (int) $user['cdep_id'];

                // special user statuses
                if ($user['status'] == 'admin') // Admin has full access over all the system's functions, absolutely no restrictions at all, privileges or access ids are ignored
                    $user['is_admin'] = true;
                elseif ($user['status'] == 'supervisor') // A supervisor is like an admin, however has full access only on certain organizations (defined in "access_ids" for his organization, department or personal rights)
                    $user['is_supervisor'] = true;
                elseif ($user['status'] == 'invisible') // This user is not visible in the online list for other normal users. Admins and Supervisors however do see these invisibile users
                    $user['is_invisible'] = true;

                foreach ($user['count'] as $c => $val)
                    $user['count'][$c] = (int) $user['count'][$c];
            }

            // Decode contact xdata if present
            if (in_array('contact', $wtr) && !is_null($user['contact']['xdata']))
                $user['contact']['xdata'] = @json_decode($user['contact']['xdata'], true);

            // Data related to user's organization or department
            if (in_array('organization', $wtr)) {
                // organization (and possibly department) data was requested
                $user['organization']['manager_id'] = (int) $user['organization']['manager_id'];
                $user['organization']['is_manager'] = $user['id'] == $user['organization']['manager_id'];
                if (strlen($user['organization']['features']) > 0)
                    $user['organization']['features'] = @json_decode($user['organization']['features'], true);
                if (!empty($user['organization']['ist_or']))
                    $user['organization']['ist_or'] = @json_decode($user['organization']['ist_or'], true);

                // organization type definition (contains the main menu, list of available controllers, ist overrides, other features) - Normally only needed when logging in (update_Session)
                if (in_array('orgtype', $wtr))
                    $user['organization']['orgtype'] = App_Model_Organization::orgtype_Read($user['organization']['type_id']);

                // Department data can only be processed if organization data was read
                if (in_array('department', $wtr)) {
                    // extended statuses these can be present even if cdep_id = 0 (or they might be missing if no special rules are defined for an user)
                    // -- if there is no department, they take effect for the user for the entire organization
                    if (isset($user['user2dep']['hidden']))
                        $user['status_ex']['hidden'] = (int) $user['user2dep']['hidden']; // hides the user from his department or organization mates
                    if (isset($user['user2dep']['disabled']))
                        $user['status_ex']['disabled'] = (int) $user['user2dep']['disabled']; // doesn't allow the user to use this department (or the entire organization, effectively disabling his access to the system)

                    if ($user['cdep_id'] == 0) {
                        // the user doesn't have a department set, so he only inherits the organization's ACLs (but they can still be overridden with individual ACLs)
                        unset($user['department']);
                    } else {
                        // department data for current user's department was also requested
                        $user['department']['chief_id'] = (int) $user['department']['chief_id'];
                        $user['department']['is_chief'] = $user['id'] == $user['department']['chief_id'];
                        // Issue type override for department or user in department
                        if (!empty($user['user2dep']['ist_or']))
                            $user['department']['ist_or'] = @json_decode($user['user2dep']['ist_or'], true);
                        elseif (!empty($user['department']['ist_or']))
                            $user['department']['ist_or'] = @json_decode($user['department']['ist_or'], true);

                        // copy some data from the u2d relation table to the "department" info - as it might get deleted when computing ACLs
                        $user['department']['comment'] = $user['user2dep']['comment'];
                    }
                } // end $wtr ['department']
                // compute privileges and access ids for the current user (in the order: organization, department, user in department) - but not for global admins
                if ($compute_acls && $user['status'] != "admin")
                    $user['acls'] = $this->compute_ACLs($user);

                // remove the ACLs from organization, department, and the user2dep - not needed after ACLs have been set, or if the user is a global admin or supervisor
                if ($compute_acls || $user['status'] == "admin" || $user['status'] == "supervisor") {
                    unset($user['organization']['privileges'], $user['organization']['access_ids'], $user['user2dep']);
                    if (in_array('department', $wtr))
                        unset($user['department']['privileges_or'], $user['department']['access_ids_or']);
                }
            } // end $wtr ['organization']

            return $user;
        }
        return false; // error, fail to find user id
    }

    /**
     * Searches an unique user by one of the primary fields
     * -- Used to locate users by their e-mail, user(name), pwreset key or Name (warning, real name might not be unique, don't rely on it !)
     *
     * @param string $value
     * @param string $wts What to search
     * @return int|false Returns the user's ID
     */
    public function locate_Unique($value, $wts = 'email') {
        $q = QueryFactory::select()
                ->table(MVC_Core::$db->tab['usr'], 'id')
                ->where("`{$wts}` = ?", $value)
                ->limit(1);

        $r = MVC_Core::$db->query($q);

        if (!$r || !count($r))
            return false;

        // user was found, read the data
        $row = $r->current();
        return $row['id'];
    }

    /**
     * Updates the user information with new data ( can be used to change password, name, email, etc. )
     * -- NOT used to set privileges ( see App_Model_Organization::update(), App_Model_Department::update() and App_Model_Department::update_User_Dep_Relation() methods for that purpose )
     * @param int $user_id
     * @param array $new_user_data (1 or more fields to update)
     * @param bool $string_expressions This parameter is sent to query factory, and is used to disable string escaping (in order to allow expressions, for example to increment a counter)
     */
    public function update($user_id, $new_user_data = array(), $string_expressions = false) {
        // Pre-Process
        // hash the password if present in $new_user_data
//        print_r($new_user_data);exit();
        if (!empty($new_user_data['password']) && isset($new_user_data['user'])) {
            $new_user_data['user'] = trim($new_user_data['user']);
            $new_user_data['security'] = self::hash_Password($new_user_data['user'], $new_user_data['password']);
            unset($new_user_data['password']); // This isn't actually stored, only used to make the HASH.
        } else
            unset($new_user_data['security']);

        // filter out keys which do not belong in the users table (or can't be updated by this method)
        $fields = array('user', 'security', 'pwreset', 'oath_key', 'oath_turnon', 'status', 'logon_dt', 'ntf_dismiss_time',
            'org_id', 'cdep_id', 'name', 'email', 'email_smtp', 'contact_id', 'signature', 'language',
            'count_msgs', 'count_files', 'count_tasks', 'count_issues', 'inbox_id', 'sentbox_id', 'count_unread', 'count_ctx', 'count_profile_recs');
        foreach ($new_user_data as $field => $value)
            if (!in_array($field, $fields))
                unset($new_user_data[$field]);

        // update users table
        $q = QueryFactory::update()
                ->table(MVC_Core::$db->tab['usr'])
                ->values($new_user_data, null, $string_expressions)
                ->where("`id` = ?", $user_id)
                ->limit(1);

        $r = MVC_Core::$db->query($q);
        
        
//            IWI task status change based on user status change start  
        if ($r) {
            if ($new_user_data['status'] == 'disabled' || $new_user_data['status'] == 'invisible' || $new_user_data['status'] == 'eula' || $new_user_data['status'] == 'readonly') {
                $this->setAllTaskFailedByUser($user_id);
            }
        }
//            IWI task status change based on user status change end

        // If the user being updated is the logged in user, update the information in the session / local cache as well ( name, email ) - Usually when user edits himself
        if (isset(MVC_Core::$ss->user) && MVC_Core::$ss->user['id'] == $user_id) {
            if (isset($new_user_data['name']))
                MVC_Core::$ss->user['name'] = $this->base['name'] = $new_user_data['name'];
            if (isset($new_user_data['email']))
                MVC_Core::$ss->user['email'] = $this->base['email'] = $new_user_data['email'];
            if (isset($new_user_data['email_smtp']))
                MVC_Core::$ss->user['email_smtp'] = $this->base['email_smtp'] = $new_user_data['email_smtp'];

            if (isset($new_user_data['count_msgs']))
                MVC_Core::$ss->user['count']['msgs'] = (int) $new_user_data['count_msgs'];
            if (isset($new_user_data['count_files']))
                MVC_Core::$ss->user['count']['files'] = (int) $new_user_data['count_files'];
            if (isset($new_user_data['count_tasks']))
                MVC_Core::$ss->user['count']['tasks'] = (int) $new_user_data['count_tasks'];
        }

        return true;
    }

    /**
     * Recounts elements for this user and saves them into the user table
     * - Tasks
     * @todo Assigned records
     * @todo Created messages
     * @todo Uploaded files
     * @todo Count contacts
     *
     * @param unknown $user_id
     */
    public function recount($user_id, $count_what = array()) {
        if (empty($count_what))
            $count_what = array('tasks');

        $update = array();
        foreach ($count_what as $el)
            switch ($el) {
                case 'tasks':
                    $q = QueryFactory::select()
                            ->table(MVC_Core::$db->tab['tsk'], 'count(*) as `count`')
                            ->where("`assign_mode` = 'user'")
                            ->where("`assign_to` = ?", $user_id)
                            ->where("`status` NOT IN('completed', 'failed', 'closed')");

                    $r = MVC_Core::$db->query($q);
                    if (!$r)
                        break; // Nothing to update, no tasks found.

                    $c_row = $r->current();
                    $update['count_tasks'] = (int) $c_row['count'];
                    break;
            }

        $this->update($user_id, $update);
    }

    /**
     * Allows changing the status of an user (and the admin/non-admin sepparation), both in database and in the active session (if the user_id is not specified)
     *
     * @param int $user_id
     * @param string $new_status (Cannot set Admin or Jumper by the use of this function)
     * @return bool Success or Failure
     */
    public function set_Status($new_status, $user_id = null) {
        $available_statuses = array('active', 'online', 'disabled', 'invisible', 'supervisor', 'eula');
        if (!in_array($new_status, $available_statuses))
            return false;

        if (is_null($user_id)) {
            // Updating currently logged in user ?
            if (empty($this->base))
                return MVC_Core::$debug->dump("No user to set status for", __METHOD__, null, 'warning');

            $user_id = $this->base['id'];
            MVC_Core::$ss->user['status'] = $this->base['status'] = $new_status;
        }
        $update_data = array(
            'status' => $new_status,
            'pwreset' => null // every time the user's status is modified, it's clear-password feature is reset (for example, after a successful login after password was recovered)
        );
        if ($new_status == 'online')
            $update_data['logon_dt'] = date("Y-m-d H:i:s");

        return $this->update($user_id, $update_data);
    }

    /**
     * Removes the user record from the system (data which is directly connected to the user itself and not affecting anything else)
     * -- User connections to other modules should be removed separrately in their respective models (user's contacts, user to organization, user to department, user to issues, and so on... )
     *
     * @param int $user_id
     */
    public function delete($user_id) {
        // Force the user to logout (this probably would never happen, but just in case a user which has been compromised needs to be deleted when they are still doing stuff)
        $this->do_Logout($user_id);

        //remove all data in department and u2d table
        try {
            $this->removeUserDependency($user_id);
        } catch (Exception $e) {
            return MVC_Core::$debug->dump(sprintf("System error during removing user dependency: %s", $e->getMessage()), __METHOD__);
        }

        // delete record from users table
        $q = QueryFactory::delete()
                ->table(MVC_Core::$db->tab['usr'])
                ->where("`id` = ?", $user_id);

        $r = MVC_Core::$db->query($q);
        if (!$r)
            return false; // error when deleting


            
//            IWI task status change based on user status change start  
        if ($r) {
            $this->setAllTaskFailedByUser($user_id);
        }
//            IWI task status change based on user status change end

            
// delete all records related to this user from the profile table
        $q = QueryFactory::delete()
                ->table(MVC_Core::$db->tab['upr'])
                ->where("`user_id` = ?", $user_id);

        MVC_Core::$db->query($q);

        // delete all records related to this user from the user-to-group table (which is usually used for notifications and other group-connected operations)
        // FIXME: u2g is outdated, remove it from the application once the new notification system is operational
        $q = QueryFactory::delete()
                ->table(MVC_Core::$db->tab['u2g'])
                ->where("`user_id` = ?", $user_id);

        MVC_Core::$db->query($q);
        return true;
    }

//----- Lists -----//

    public $found_rows = 0;

    /**
     * Reads a list of users by filtering with the help of a callback that can add conditions to the QueryFactory object
     * -- Supports paging
     *
     * @param Callback $qfo_filter If no callback specified, it simply reads all the users in the system
     * @param array $wtr Indicates what information to read ( if null or empty, just verify that the user ID actually exists )
     * @param int $count_rows Paging Size
     * @param int $start_row Paging Start
     * @param string $order_by Which field to be used for ordering
     * @param bool $calc_found_rows Runs one extra query so sever-side paging is possible
     * @return array
     */
    public function get_List($wtr = array('user'), $qfo_filter = null, $filter_params = array(), $order_by = null, $count_rows = 1000, $start_row = 0, $calc_found_rows = false) {
        $q = $this->get_List_QueryFactory_Helper($qfo_filter, $filter_params, $order_by, $count_rows, $start_row);
        if (is_null($q))
            return false; // Error, $qfo_filter failed

        if ($calc_found_rows)
            $q->flags("SQL_CALC_FOUND_ROWS");

        // What to read?
        $wtr = self::wtr_Helper($wtr);

        if (in_array('user', $wtr))
        // full mode, most fields in the primary table except "security" and "pwreset" (which are never returned, just checked against the db)
            $q->table(array(MVC_Core::$db->tab['usr'], 'usr'), 'id,user,status,logon_dt,org_id,cdep_id,name,email,email_smtp,contact_id,signature,language,count_msgs,count_files,count_tasks,count_issues,inbox_id,sentbox_id,count_unread,count_ctx,count_profile_recs,oath_key'); // IWI show user's 2auth status 
        else
        // quick mode, less fields (usually no linked tables as well)
            $q->table(array(MVC_Core::$db->tab['usr'], 'usr'), 'id,user,name,status,org_id,cdep_id'); // quick lists, less fields, no details or joins

        if (in_array('contact', $wtr))
        // Read detailed contact information for the users (contact xdata)
            $q->table(array(MVC_Core::$db->tab['ctx'], 'ctx'), QueryFactory::autoalias_Add_Prefix("xdata", '_ctx_'), 'left', '`usr`.`contact_id` = `ctx`.`id`');

        if (in_array('organization', $wtr))
        // user's organization details
            $q->table(array(MVC_Core::$db->tab['org'], 'org'), QueryFactory::autoalias_Add_Prefix("type_id,name" . (in_array('acls', $wtr) ? ",privileges,access_ids" : ""), '_org_'), 'left', '`usr`.org_id = `org`.id');

        if (in_array('department', $wtr)) {
            // read department information ( 2 tables )
            $q->table(array(MVC_Core::$db->tab['dep'], 'dep'), QueryFactory::autoalias_Add_Prefix("name" . (in_array('acls', $wtr) ? ",privileges_or,access_ids_or" : ""), '_dep_'), 'left', '`usr`.cdep_id = `dep`.id');
            $q->table(array(MVC_Core::$db->tab['u2d'], 'u2d'), QueryFactory::autoalias_Add_Prefix("comment,disabled,hidden" . (in_array('acls', $wtr) ? ",privileges_or,access_ids_or" : ""), '_u2d_'), 'left', '`usr`.id = `u2d`.user_id AND `usr`.cdep_id = `u2d`.dep_id');
        }

        $r = MVC_Core::$db->query($q);
        if (!$r || count($r) == 0)
            return MVC_Core::$debug->dump("Users list empty", "User Model::get_List", null, 'warning');

        $r->split_by_prefixes = array(
            // tidy up
            'count' => "count_",
            // extra table(s)
            'contact' => "_ctx_",
            'organization' => "_org_",
            'department' => "_dep_",
            'user2dep' => "_u2d_"
        );

        $all = array();
        foreach ($r as $user) {

            // Post-process user data
            $user['id'] = (int) $user['id'];
            $user['org_id'] = (int) $user['org_id'];

            if (in_array('user', $wtr)) {
                // intvars
                $user['cdep_id'] = (int) $user['cdep_id'];
                $user['inbox_id'] = (int) $user['inbox_id'];
                $user['sentbox_id'] = (int) $user['sentbox_id'];
                $user['email_smtp'] = (int) $user['email_smtp'];
                foreach ($user['count'] as $c => $val)
                    $user['count'][$c] = (int) $val;
            }

            // A little more processing related to departments
            if (in_array('department', $wtr)) {
                if ($user['cdep_id'] == 0)
                // when user doesn't have an active department, these two fields are empty
                    unset($user['department'], $user['user2dep']);
                else {
                    // otherwise, move data from "user2dep" directly into "department" and remove "user2dep"
                    $user['department']['comment'] = $user['user2dep']['comment'];
                    $user['department']['disabled'] = (bool) $user['user2dep']['disabled'];
                    $user['department']['hidden'] = (bool) $user['user2dep']['hidden'];
                    unset($user['user2dep']);
                }
            }

            // calculate privileges and access ids for the current user (in the order: organization, department, user in department)
            if (in_array('acls', $wtr) && in_array('organization', $wtr) && in_array('department', $wtr)) {
                // ACLs calculation can only be done when organization and department are also read
                $user['acls'] = $this->compute_ACLs($user, false); // - Group access is not calculated automatically, as it would require additional queries, one per row
                // remove the unnecessary fields
                unset($user['organization']['privileges'], $user['organization']['access_ids']);
                unset($user['department']['privileges_or'], $user['department']['access_ids_or']);
            }

            // decode contact xdata if present
            if (in_array('contact', $wtr) && !is_null($user['contact']['xdata']))
                $user['contact']['xdata'] = @json_decode($user['contact']['xdata'], true);

            
//            IWI show user�s 2auth status start
            $user['2auth']=($user['oath_key']!='')?'<img src="/img/icons/online10px.png" alt="2FA">':'<img src="/img/icons/offline10px.png" alt="2FA">';
            unset($user['oath_key']);
//            IWI show user�s 2auth status end
            $all[] = $user;
        }

        // Check if the calc_found flag is used
        if ($calc_found_rows) {
            $fr_row = MVC_Core::$db->query("SELECT FOUND_ROWS() AS `found_rows`;")->current();
            $this->found_rows = (int) $fr_row['found_rows'];
        }

        return $all;
    }

//===== Access Control System =====//

    /**
     * Calculates all the privileges and access lists that the user has, using a 3-tier override system (organization, department, user to department)
     *   and also returns organization information (which can be used to select a layout or header or other stuff)
     *
     * @param array $user The user's data (which contains the organization and department data)
     * @param bool $calc_groups true = Read and calculate groups for the incoming user data (default).
     * - This uses a database read, so disable this when using compute_ACL in a loop where groups access information is not necessary
     * @return array
     */
    protected function compute_ACLs(&$user, $calc_groups = true) {
        $acls = array();

        // Tier 1: Organization level
        // privileges
        $acls['privileges'] = !empty($user['organization']['privileges']) ? explode(',', $user['organization']['privileges']) : array();
        // access lists - WARNING: The "privileges" key must not be present in "access_ids" field - It exists in it's own field (which was exploded above)
        $xacls = @json_decode($user['organization']['access_ids'], true);

        // group id's, organization wide
        if ($calc_groups) {
            // get list of groups this organization has access to
            $com = new App_Model_Organization();
            $grp_r = $com->get_Groups((int) $user['org_id']);

            $com_groups = array();
            // keep only the ID and Name of the group in $acls (nothing else is needed here)
            foreach ($grp_r as $group)
                $com_groups[$group['id']] = $group['name'];

            // if "groups" key present in access_ids, filter out the groups for the user (to restrict access to certain groups to everyone in the organization, even if the organization itself is connected to multiple groups)
            if (is_array($xacls) && isset($xacls['groups'])) {
                foreach ($xacls['groups'] as $grp_id)
                    if (isset($com_groups[$grp_id]))
                        $acls['groups'][$grp_id] = $com_groups[$grp_id];
                unset($xacls['groups']); // no longer neede
            } else
            // no, all the users start with all groups as their primary list
                $acls['groups'] = $com_groups;
        }

        if (is_array($xacls))
        // everything else is merged.
            $acls = array_merge($acls, $xacls);

        // Tier 2: Department level override ( if department exists )

        if (!empty($user['department'])) {
            if (!empty($user['department']['privileges_or'])) {
                $acls['privileges'] = explode(',', $user['department']['privileges_or']);
            }
            // WARNING: The "privileges" key must not be present in "access_ids_or" field
            if (!is_null($user['department']['access_ids_or'])) {
                $xacls = @json_decode($user['department']['access_ids_or'], true);
                if (is_array($xacls)) {
                    foreach ($xacls as $list => $xacl)
                        if (is_array($xacl)) { // complex override
                            if ($list == 'groups') { // group overrides are not directly merged (simple id list), but constructed as id=> name
                                if ($calc_groups) {
                                    $acls['groups'] = array();
                                    foreach ($xacl as $grp_id)
                                        if (isset($com_groups[$grp_id]))
                                            $acls['groups'][$grp_id] = $com_groups[$grp_id];
                                }
                            } else
                                $acls[$list] = $xacl; // for the rest, it's just IDs
                        } elseif ($xacl == true) { // evaluates to true, it means "give all"
                            if ($list == 'groups') {
                                if ($calc_groups)
                                    $acls['groups'] = $com_groups; // all, but for groups only the groups that the organization has access to (or is part of)
                            } else
                                unset($acls[$list]);
                        } else // when not an array and not false, it means "no access" - so an empty array for that specific ACL
                            $acls[$list] = array();
                }
            }
        }

        // Tier 3: User's personal privileges override (inside the active department)

        if (!empty($user['user2dep'])) {
            if (!empty($user['user2dep']['privileges_or'])) {
                $acls['privileges'] = explode(',', $user['user2dep']['privileges_or']);
            }
            // WARNING: The "privileges" key must not be present in "access_ids_or" field
            if (!empty($user['user2dep']['access_ids_or'])) {
                $xacls = @json_decode($user['user2dep']['access_ids_or'], true);
                if (is_array($xacls)) {
                    foreach ($xacls as $list => $xacl)
                        if (is_array($xacl)) { // complex override
                            if ($list == 'groups') { // group overrides are not directly merged (simple id list), but constructed as id=> name
                                if ($calc_groups) {
                                    $acls['groups'] = array();
                                    foreach ($xacl as $grp_id)
                                        if (isset($com_groups[$grp_id]))
                                            $acls['groups'][$grp_id] = $com_groups[$grp_id];
                                }
                            } else
                                $acls[$list] = $xacl; // for the rest, it's just IDs
                        } elseif ($xacl == true) { // evaluates to true, it means "give all"
                            if ($list == 'groups') {
                                if ($calc_groups)
                                    $acls['groups'] = $com_groups; // all, but for groups only the groups that the organization has access to (or is part of)
                            } else
                                unset($acls[$list]);
                        } else // when not an array and not false, it means "no access" - so an empty array for that specific ACL
                            $acls[$list] = array();
                }
            }
        }

        return $acls;
    }

    /**
     * Simple function that checks if user is admin or supervisor ( full unrestricted access, in first case on the entire application, in second case over the organization(s) the user manages )
     */
    public function is_Unrestricted() {
        if (!empty(MVC_Core::$ss->user) && !empty(MVC_Core::$ss->user['is_admin']) || !empty(MVC_Core::$ss->user['is_supervisor']))
            return true;
        else
            return false;
    }

    /**
     * Reads a stored access control list (defaults to privileges)
     *
     * @param string $list
     * @param bool $as_indexes Read the list as indexes of the array with all values being "true", instead of values
     */
    public function read_ACL($list = 'privileges', $as_indexes = false) {
        if ($this->acls === true)
            return true;

        if (!isset($this->acls[$list]))
            return array();

        if ($as_indexes) {
            $items = array();
            foreach ($this->acls[$list] as $item)
                $items[$item] = true;
            return $items;
        } else
            return $this->acls[$list];
    }

    /**
     * Helper function that returns true if a particular key exists in the specified list for an user ACLs array.
     * -- If either the list is missing or the key is missing, returns false. If both exist, return true
     *
     * @param string $list
     * @param string $key
     * @param array $acls If specified, instead of using local data, this ACLs array is used ( useful for texting ALCs for another user than the logged in one )
     * @return bool
     */
    public function test_ACL($list, $key, $acls = null) {
        if (is_null($acls))
            $acls = &$this->acls;

        if ($acls === true)
            return true; // answer is always true for global admin (can see/do everything)

        if (!isset($acls[$list]))
            return false;

        if ($list == 'groups') { // groups are a bit different, as their IDs are the keys
            if (!isset($acls[$list][$key]))
                return false;
        } else {
            if (!in_array($key, $acls[$list]))
                return false;
        }
        return true;
    }

    /**
     * Updates the local storage ACL (as long as current script is in progress)
     * -- Also stores the local ACLs in session until next launch
     * -- Used to pass modified ACLs to AJAX calls within the same page
     *
     * @param string $list
     * @param scalar|array $ids
     * @param bool $remove Remove the $id(s) from the ACL instead of adding
     */
    public function update_Local_ACL($list, $ids, $remove = false) {
        if ($this->acls === true)
            return true; // nothing to update for admin, it can already do everything

        if (!is_array($ids))
            $ids = explode(',', $ids);

        if ($list == 'groups') { // groups are a bit different, as their IDs are the keys
            // ???
        } else {
            foreach ($ids as $id)
                if ($remove) {
                    if (($pos = array_search($id, $this->acls[$list])) !== false)
                        unset($this->acls[$list][$pos]);
                } else {
                    if (!in_array($id, $this->acls[$list]))
                        $this->acls[$list][] = $id;
                }
        }

        // Note: This is only created here and valid until next "full" launch, where they are cleared
        MVC_Core::$ss->user['local_acls'] = $this->acls;
    }

//----- Session Management -----//

    /**
     * Contains base information about the logged in user (id, account id, oath_key, status, org_id, cdep_id, name, email, contact_id)
     * -- This information is also being transmitted to JavaScript to application module with every initialization
     *
     * @var array|bool
     */
    private $base = false;

    /**
     * Active ACLS storage, loaded from session at every run.
     * - It can be overwritten to handle special cases, but does not persist into the next load
     *
     * @var array|bool
     */
    private $acls = false; // false = user not logged in, no access to user functions

    /**
     * Caches into object storage information from the session, to avoid accessing the session directly on user-related data and support temporary changes to user session information (without affecting the session itself)
     */
    private function set_Local_Storage() {
        if (!MVC_Core::$ss->user)
            return; // $base stays "false"

        $user = MVC_Core::$ss->user;

        // Base data
        $this->base = array(
            'id' => (int) $user['id'],
            'account_id' => $user['user'],
            'oath_key' => $user['oath_key'],
            'status' => $user['status'],
            'org_id' => (int) $user['org_id'],
            'cdep_id' => $user['cdep_id'] ? (int) $user['cdep_id'] : null,
            'contact' => array(
                'id' => $user['contact_id'],
                'name' => $user['name'],
                'email' => $user['email'],
            )
        );

        // Access Control data
        if (!empty($user['is_admin']) || !empty($user['is_supervisor']))
            $this->acls = true; // Full access
        elseif (empty($user['acls']))
            $this->acls = false; // No access
        else {
            // Standard access
            $this->acls = isset($user['local_acls']) ? $user['local_acls'] : $user['acls'];
        }
    }

    /**
     * Return user's ID if logged in
     * @return int|null
     */
    public function ID() {
        if (!empty($this->base))
            return $this->base['id'];
    }

    /**
     * Reads the cached base session data for the user (contains the most common and useful values)
     */
    public function read_Base() {
        return $this->base;
    }

    /**
     * Constructor always loads the information from session
     */
    public function __construct() {
        $this->set_Local_Storage();
    }

    /**
     * Tests if an username and it's corresponding password exists in the database.
     * -- This method should be used for the login process
     *
     * @param string|id $user User account (string) or user ID (int)
     * @param string $password
     * @param null $security
     * @param null $otp
     * @return int|false Returns the user's ID when found, or false if the user was not found or the password doesn't match
     * @throws Exception
     */
    public function test_Access($user, $password, $security = null, $otp = null) {
        $user = trim($user);

        if (is_null($security))
            $security = self::hash_Password($user, $password);

        $field = (is_numeric($user) && strlen($user) <= 5) ? 'id' : 'user';

//		$q=QueryFactory::select()
//		-> table(MVC_Core::$db-> tab['usr'], 'id,security,status,oath_key')
//		-> where("`{$field}` = ?", $user)
//		-> where("`security` = ?", $security)
//		-> limit(1);

        /**
         * Tests if password contain #, it's not reflecting
         * that's why using a raw query
         * Code by Gargi 15-11-2022
         */
        $q = "SELECT `id`, `security`, `status`, `oath_key` FROM `" . MVC_Core::$db->tab['usr'] . "` WHERE `$field` = '$user' AND `security` = '$security' LIMIT 1";

        $r = MVC_Core::$db->query($q);
        if ($r && $r->num_rows == 1) {
            // user was found, read the data
            $test = $r->current();

            if ($test['status'] == 'admin') {
                // An Admin user bypasses all restrictions of any kind and logs in directly
                // However, it is required to enter the 2FA PIN ( if enabled ) with every login.
                if (!empty($test['oath_key']))
                    if (is_null($otp)) {
                        // Ask for the PIN
                        $test['status'] = 'twofa';
                    } else {
                        // Verify the PIN, and proceed only if it matches
                        $otp_valid = $this->test_2FA_OTP($otp, $test['oath_key']);
                        if (!$otp_valid)
                            $test['status'] = 'otp_fail';
                    }
                return $test;
            }

            if ($test['status'] == 'disabled') {
                // user is disabled and not allowed to login
                $this->record_Session_History(array(
                    'user_id' => $test['id'],
                    'login_status' => 'disabled',
                ));
                return $test;
            }

            // test if the IP address is valid
            $ipr = new App_Model_IPRule();
            $rule = $ipr->find_Rule_for_IP($_SERVER['REMOTE_ADDR']);
            if ($rule !== false) { // a rule was found
                switch ($rule['mode']) {
                    case 'deny': // This IP address is not allowed for login
                        $this->record_Session_History(array(
                            'user_id' => $test['id'],
                            'login_status' => "ipdeny",
                            'ipr_id' => $rule['id']
                        ));
                        $test['status'] = "ipdeny";
                        $test['ipr'] = $rule;
                        return $test;
                    case 'notify':
                        // $rule['name'], $rule['added_by'], $rule['notify_user_id']
                        // TODO: Send an internal message to an user that a login is happening from one of the notification ranges

                        $test['ipr'] = $rule;
                        break;
                    case 'warn':
                        // $rule['name'], $rule['added_by']
                        // TODO: Add a warning to the user's notification system that this IP range is set to "warn" status

                        $test['ipr'] = $rule;
                        break;
                    case 'dn':

                        // Deny and also notify
                        $this->record_Session_History(array(
                            'user_id' => $test['id'],
                            'login_status' => "ipdeny",
                            'ipr_id' => $rule['id']
                        ));
                        $test['status'] = "ipdeny";
                        $test['ipr'] = $rule;
                        return $test;
                    case 'nw':

                        // Notify and also warn
                        $test['ipr'] = $rule;
                        break;
                }
            } // if no rules found, then we proceed just as if the IP rules system didn't exist

            if ($test['status'] == 'invisible' || $test['status'] == 'active' || $test['status'] == 'jumper' || $test['status'] == 'eula') {
                // user was found and login is possible
                if (!empty($test['oath_key'])) {
                    // OATH active ? Check if 2FA cookie exists, if so proceed with the login
                    $oath_sha1 = sha1($test['id'] . $test['oath_key']);
                    $the_cake_is_a_lie = new MVC_Persist('cookie', 604800); // Valid one week, after that PIN will be asked again

                    if ($the_cake_is_a_lie->laser_frate != $oath_sha1) {
                        // No or invalid cookie

                        if (is_null($otp)) {
                            $test['status'] = 'twofa';
                        } else {
                            //	Verify the PIN, and proceed only if it matches
                            $otp_valid = $this->test_2FA_OTP($otp, $test['oath_key']);
                            if (!$otp_valid)
                                $test['status'] = 'otp_fail';
                            else {
                                // Validated, save the cookie
                                $the_cake_is_a_lie->laser_frate = $oath_sha1;
                            }
                        }
                    }
                }

                return $test;
            }


//            publication read only IWI start
            if ($test['status'] == 'readonly') {
                // user was found and login is possible
                if (!empty($test['oath_key'])) {
                    // OATH active ? Check if 2FA cookie exists, if so proceed with the login
                    $oath_sha1 = sha1($test['id'] . $test['oath_key']);
                    $the_cake_is_a_lie = new MVC_Persist('cookie', 604800); // Valid one week, after that PIN will be asked again

                    if ($the_cake_is_a_lie->laser_frate != $oath_sha1) {
                        // No or invalid cookie

                        if (is_null($otp)) {
                            $test['status'] = 'twofa';
                        } else {
                            //	Verify the PIN, and proceed only if it matches
                            $otp_valid = $this->test_2FA_OTP($otp, $test['oath_key']);
                            if (!$otp_valid)
                                $test['status'] = 'otp_fail';
                            else {
                                // Validated, save the cookie
                                $the_cake_is_a_lie->laser_frate = $oath_sha1;
                            }
                        }
                    }
                }

                return $test;
            }
//            publication read only IWI end

            if ($test['status'] == 'online')
            // user is already online. Check in settings if it's possible to login again
                if (MVC_Core::$settings['core']['security']['multi_login']) { // Yes
                    return $test;
                } else { // No
                    $this->record_Session_History(array(
                        'user_id' => $test['id'],
                        'login_status' => "online" // deny because of already logged in
                    ));
                    $test['status'] = "online_deny";
                    return $test;
                }
        }
        // error, fail to find user (wrong username and/or password) - Add or increment an entry to the "log" table
        // -- When reaching a certain number of failed logins, this will completely disable access to the website from this IP address for a period of time (anticracker measure)
        $ip_lu = sprintf('%u', ip2long($_SERVER['REMOTE_ADDR']));
        $q = QueryFactory::insert()
                ->table(MVC_Core::$db->tab['crk'])
                ->values(array('ip' => $ip_lu));

        $q = $q . " ON DUPLICATE KEY UPDATE `number`=`number`+1";

        MVC_Core::$db->query($q);
        return false;
    }

    /**
     * Checks the specified One-Time Password (or PIN) against the stored oath_key for the user.
     *
     * @param string $otp
     * @return True if the OTP checks; False if it does not and NULL if the user is not using 2FA
     */
    public function test_2FA_OTP($otp, $oath_key = null) {
        if (is_null($oath_key))
            $oath_key = $this->base['oath_key'];

        if (empty($oath_key))
            return null;

        GA2FA::initialize();
        return GA2FA::verify_key($oath_key, $otp);
    }

    /**
     * Performs the session initialization after a user tested successfuly with self::test_Login()
     * -- Also modifies user status (active-> online), cleans the failed "logins" table (IP Bans) for the current IP and adds a line into the "online" table
     *
     * @param int $user_id Logs in this user ID (usually, yourself, but it can be another user - Administrative function)
     * @param bool $security Saves the user ID and security hash into a cookie, so the system automatically logins on check_Session (using an already encrypted password)
     */
    public function do_Login($user_id, $security = null) {
        try {
            // 1. Saves the remember cookie
            // -- (user's numeric ID and the security hash, but NOT the username - in case the cookie leaks, it's useless as it has no connection to the actual username)
            if (!is_null($security)) {
                $the_cake_is_a_lie = new MVC_Persist('cookie');
                $the_cake_is_a_lie->the_fifth_of_november = array($user_id, $security);
            }

            // 2. Reads user information, profile and other data, and fills the session
            $this->update_Session($user_id);
            $ip_lu = sprintf('%u', ip2long($_SERVER['REMOTE_ADDR']));

            // 3. Remove the current IP from the failed logins table (in case it existed)
            $q = QueryFactory::delete()
                    ->table(MVC_Core::$db->tab['crk'])
                    ->where("`ip` = ?", $ip_lu);
            MVC_Core::$db->query($q);

            // 4. Sets the user as "online" in the users table, if it was previously "active" (all the other statuses are untouched) - But not when xlogging
            if (MVC_Core::$ss->user['status'] == 'active')
                $this->set_Status('online');

            // 5. Add the current user as being online in the "active sessions" table
            $online_info = array(
                'user_id' => $user_id,
                'session_id' => session_id(),
                'logon' => date("Y-m-d H:i:s"),
                'request_uri' => $_SERVER['REQUEST_URI'],
                'ipaddr' => $ip_lu
            );

            // prepare query
            $q = QueryFactory::insert()
                    ->table(MVC_Core::$db->tab['ass'])
                    ->values($online_info);
            // and run it
            MVC_Core::$db->query($q);

            return true;
        } catch (Exception $e) {
            return MVC_Core::$debug->dump(sprintf("System error during login: %s", $e->getMessage()), __METHOD__);
        }
    }

    /**
     * Logs out an user by cleaning up the active session.
     * -- Also modifies the user status (online-> active) and removes the user from the "online" table
     *
     * @param int $user_id Defaults to currently logged in user, but allows to forcibly login another user
     * @param string $session_id If not null, it's used to remote logout a specific session, not all (for example, the janitor does that)
     * @param bool $by_user This logout was requested by user, by clicking the "Logout" button/link. Used to remove the autologin cookie
     */
    public function do_Logout($user_id = null, $session_id = null, $by_user = false) {
        if (empty($user_id) || ($user_id == $this->base['id'] && $session_id == session_id())) {
            // logout the user in this session
            // 1. Check the online status of the user in the "active sessions" table
            $q = QueryFactory::select()
                    ->table(MVC_Core::$db->tab['ass'])
                    ->where("`user_id` = ?", $this->base['id']);
            $r = MVC_Core::$db->query($q);

            if ($r && $r->num_rows > 0) {
                // found the user in the logins table
                if ($r->num_rows == 1) {
                    // just one active session, set the user as being "active" (aka, functional, but offline) if it was "online" before
                    if (MVC_Core::$ss->user['status'] == 'online')
                        $this->set_Status('active');
                }

                $ip_lu = sprintf('%u', ip2long($_SERVER['REMOTE_ADDR']));

                // find which session is the current one, calculate the session time and save the information in the "ssh" table
                foreach ($r as $ss)
                    if ($ss['ipaddr'] == $ip_lu) {
                        $this->record_Session_History(array(
                            'logon' => $ss['logon'],
                            'length' => time() - strtotime($ss['logon']),
                            'count_liftoffs' => $ss['count_liftoffs'],
                            'login_status' => 'ok' // TODO - Support "ipwarn" status - Transmit that this session existed on an IP which inside a warn range
                        ));
                    }

                // remove the entry from the active sessions table
                $q = QueryFactory::delete()
                        ->table(MVC_Core::$db->tab['ass'])
                        ->where("`user_id` = ?", $this->base['id'])
                        ->where("`session_id` = ?", session_id());
                MVC_Core::$db->query($q);
            } else {
                // also set the user as "active" when the session was remotely deleted (when it was "online" before)
                if (MVC_Core::$ss->user['status'] == 'online')
                    $this->set_Status('active');

                // Note that it's not possible to record a session history correctly when the row in active sessions table doesn't exist anymore.
                // It's the responsability of the remote session stop to record this information (also using this function, but by specifying an ID)
            }

            // 2. Clean up session of the staff user's information
            $this->base = false;
            $this->acls = false;
            // Note that the session itself is not destroyed, as it might contain information which is useful in the public area
            unset(MVC_Core::$ss->user, MVC_Core::$ss->organization, MVC_Core::$ss->department);
            // TODO: Mass some special session elements which are used in arbitrary modules into one, so it can be cleaned all at once when user logs out
            unset(MVC_Core::$ss->rt, MVC_Core::$ss->admin_org_id, MVC_Core::$ss->perspective, MVC_Core::$ss->umb_dds);
            // Remove Global Data Cache (May contain various things, temporary variables, settings persistent through the session, etc.)
            unset(MVC_Core::$ss->cache);

            // 3. remove the special cake in case it existed, if the user logged out manually (in case of expired sessions, the cake is not removed)
            if ($by_user) {
                // When user requests logout (by clicking), also refresh domain cached data. (On automatic logout it is kept)
                unset(MVC_Core::$ss->domain_CFG);

                $the_cake_is_a_lie = new MVC_Persist('cookie');
                unset($the_cake_is_a_lie->the_fifth_of_november);
            }

            // Exit, stage left.
            return true;
        } else {
            // logout another user, remove all the user's entries from the active sessions table, and set it "active" if it was "online" before.
            // -- next time a user loads a page, and check_Session() is run, it should detect that the session has been terminated and forcibly logout itself
            // first, check if the user actually exists (and read it's status)
            $q = QueryFactory::select()
                    ->table(MVC_Core::$db->tab['usr'], "id,status")
                    ->where("`id` = ?", $user_id)
                    ->limit(1);
            $r = MVC_Core::$db->query($q);
            if ($r && $r->num_rows == 1) {
                // user was found, read the data
                $user = $r->current();

                // only affects normal online users, admins, disabled and invisible users aren't affected.
                if ($user['status'] == 'online')
                    $this->set_Status('active', $user_id);

                if (is_null($session_id)) {
                    // read all the active sessions for this user, and record their ending
                    $q = QueryFactory::select()
                            ->table(MVC_Core::$db->tab['ass'])
                            ->where("`user_id` = ?", $user_id);
                    $r = MVC_Core::$db->query($q);

                    if ($r && $r->num_rows > 0) {
                        foreach ($r as $ss) {
                            $this->record_Session_History(array(
                                'user_id' => $user_id,
                                'ipaddr' => $ss['ipaddr'],
                                'logon' => $ss['logon'],
                                'length' => time() - strtotime($ss['logon']),
                                'count_liftoffs' => $ss['count_liftoffs'],
                                'login_status' => 'ok' // TODO - Support "ipwarn" status - Transmit that this session existed on an IP which inside a warn range
                            ));
                        }

                        // remove all entry(es) from "ass" table
                        $q = QueryFactory::delete()
                                ->table(MVC_Core::$db->tab['ass'])
                                ->where("`user_id` = ?", $user_id);
                        MVC_Core::$db->query($q);
                    }
                } else {
                    // logout just one particular session ID

                    $q = QueryFactory::select()
                            ->table(MVC_Core::$db->tab['ass'])
                            ->where("`user_id` = ?", $user_id)
                            ->where("`session_id` = ?", $session_id)
                            ->limit(1);
                    $r = MVC_Core::$db->query($q);

                    if ($r && $r->num_rows == 1) {
                        $ss = $r->current();
                        $this->record_Session_History(array(
                            'user_id' => $user_id,
                            'ipaddr' => $ss['ipaddr'],
                            'logon' => $ss['logon'],
                            'length' => time() - strtotime($ss['logon']),
                            'count_liftoffs' => $ss['count_liftoffs'],
                            'login_status' => 'ok' // TODO - Support "ipwarn" status - Transmit that this session existed on an IP which inside a warn range
                        ));

                        // remove that entry
                        $q = QueryFactory::delete()
                                ->table(MVC_Core::$db->tab['ass'])
                                ->where("`user_id` = ?", $user_id)
                                ->where("`session_id` = ?", $session_id)
                                ->limit(1);
                        MVC_Core::$db->query($q);
                    }
                }

                // Done, thrown out in the streets.
                return true;
            }
            return false; // error, fail to find user id
        }
    }

    /**
     * Tests the last access time for all users that are logged in and if last access is olders than max session time, forces the user to be logged out
     * Also cleans up temp banned IP addresses. The janitor is not run for every page load, but a random value is used to determine the ratio of janitor runs to page loads.
     * ( Calibrate the janitor run ratio depending on how much traffic there is on the website, for very few users put it 1:1 - every time )
     */
    public function logins_Janitor() {
        $ratio = 1 / MVC_Core::$settings['core']['janitor_ratio'];

        if (mt_rand() / mt_getrandmax() < $ratio) { // ok, run the janitor
            // 1. Cleanup older failed logins (allow users to log in again after some time )
            $ip_ban_time = isset(MVC_Core::$settings['core']['security']['ip_ban_time']) ? MVC_Core::$settings['core']['security']['ip_ban_time'] : 3600;
            $q = QueryFactory::delete()
                    ->table(MVC_Core::$db->tab['crk'])
                    ->where("`last_attempt` < ?", date("Y-m-d H:i:s", time() - $ip_ban_time));
            MVC_Core::$db->query($q);

            // 2. Force logout of users which simply closed their browsers without clicking "logout"
            // -- (read active session which didn't have activity in the last N seconds to find out the user id's, and do logout for those users)
            if (isset(MVC_Core::$settings['core']['session_expiration']))
                $ss_expire = (int) MVC_Core::$settings['core']['session_expiration'];
            else
                $ss_expire = @ini_get('session.gc_maxlifetime');
            if (!$ss_expire)
                $ss_expire = 7200; // defaults to two hours if session.gc_maxlifetime is not accesible and there is no setting in the config about this

            $q = QueryFactory::select()
                    ->table(MVC_Core::$db->tab['ass'], 'user_id,session_id')
                    ->where("`dt` < ?", date("Y-m-d H:i:s", time() - $ss_expire));
            $r = MVC_Core::$db->query($q);

            foreach ($r as $row) {
                // this is a bit inefficient, as each of them are read and written again - TODO: The ability to send all the findings to do_Logout, for a multi-remote-logout
                $this->do_Logout($row['user_id'], $row['session_id']);
            }
        }
    }

    /**
     * Records information about a successful or failed session.
     * -- Successful is recorded when an user logs out or is forcibly logged out by the expiration of his session
     * -- Failed is recorded by the login procedure when an user is denied access (by disabled, already online or denied IP address)
     *
     * @param array $record_data
     */
    public function record_Session_History($record_data = array()) {
        // Pre-Process
        if (!isset($record_data['user_id']))
            $record_data['user_id'] = MVC_Core::$ss->user['id'];
        if (!isset($record_data['logon']))
            $record_data['logon'] = date("Y-m-d H:i:s");
        $record_data['ipaddr'] = sprintf('%u', ip2long($_SERVER['REMOTE_ADDR']));

        // prepare query
        $q = QueryFactory::insert()
                ->table(MVC_Core::$db->tab['ssh'])
                ->values($record_data);

        // and run it (might throw exception if $record_data is malformed!)
        $r = MVC_Core::$db->query($q);
    }

    /**
     * Test if one special cake exists in the user's fridge.
     *
     * @return boolean
     */
    public function check_The_Cake() {
        $the_cake_is_a_lie = new MVC_Persist('cookie');
        if (is_array($the_cake_is_a_lie->the_fifth_of_november)) {
            list($user_id, $security) = $the_cake_is_a_lie->the_fifth_of_november;
            $test = $this->test_Access($user_id, "", $security);

            // autologin if the cookie exists and the security string checks
            switch ($test['status']) {
                case 'admin':
                case 'supervisor':
                case 'jumper':
                case 'active':
                case 'invisible':
                case 'online':
                    $this->do_Login((int) $test['id']);
            }
            return MVC_Core::$ss->user['id'];
        }
        // nope, no cake
        return false;
    }

    /**
     * Checks if an user is logged in ( has an active session and returns it's ID ). If none, returns false
     * @return array Session information as stored in the database
     */
    public function check_Session() {
        $this->logins_Janitor();
        if (isset(MVC_Core::$ss->user)) {
            // Test if the active session exists in the "active sessions" table, and remove the session if not. (Remote forced logout)
            $q = QueryFactory::select()
                    ->table(MVC_Core::$db->tab['ass'], 'user_id,session_id,dt,logon,count_liftoffs,ipaddr,check_evn,external')
                    ->where("`user_id` = ?", $this->base['id'])
                    ->where("`session_id` = ?", session_id());

            $r = MVC_Core::$db->query($q);
            if ($r && count($r) == 1) {
                // ok, session found, do some more checking
                $ssrow = $r->current();

                $ip_lu = sprintf('%u', ip2long($_SERVER['REMOTE_ADDR']));

//                if ($ssrow['ipaddr'] != $ip_lu) {
//                    // WARNING: Possible XSS Attack attempted, or the user's IP changed while the session was active - Force this session to logout
//                    $this->last_error = "Your IP Address or Session ID has changed unexpectedly. For security reasons, you must re-login!";
//                    $this->do_Logout($ssrow['user_id'], $ssrow['session_id']);
//                    return false;
//                }
                
                

                // Update the session with the value found in the "external" field - HeartBeat can pick that up.
                // - This is used to insert data into a user's session ( for example, by the message system, to notify logged in users about messages that just "arrived" )
                $ass_update = array();
                if (!empty($ssrow['external'])) {
                    MVC_Core::$ss->user['external'] = json_decode($ssrow['external'], true);
                    $ass_update['external'] = null;
                }

                // If this is true, an external action transmitted into the user's session that there are notifications to be displayed. This is injected into the session as well.
                if ($ssrow['check_evn']) {
                    MVC_Core::$ss->user['check_evn'] = true;
                    $ass_update['check_evn'] = 0;
                }

                // Update liftoff and request_uri for non-ajax session updates.
                if (!App_Router::$ajax) {
                    $ass_update['count_liftoffs'] = $ssrow['count_liftoffs'] + 1;
                    $ass_update['request_uri'] = substr($_SERVER['REQUEST_URI'], 0, 255);
                }
                if (!empty($ass_update)) {
                    $q = QueryFactory::update()
                            ->table(MVC_Core::$db->tab['ass'])
                            ->where("`user_id` = ?", $this->base['id'])
                            ->where("`session_id` = ?", session_id())
                            ->values($ass_update);

                    MVC_Core::$db->query($q);
                }
                return $ssrow;
            } else {
                // session not found anymore, was it deleted? We should logout locally as well.
                $this->last_error = "Session synchronization data not found in the database. Logging out.";
                $this->do_Logout();
                return false;
            }

            return $ssrow;
        }

        // if there's no session, test if the IP is currently banned because of failed logins. If yes, stop the script
        if (!App_Router::$ajax) {
            $ip_lu = sprintf('%u', ip2long($_SERVER['REMOTE_ADDR']));
            $q = "SELECT `number`,`last_attempt` FROM `" . MVC_Core::$db->tab['crk'] . "` WHERE `ip`={$ip_lu} LIMIT 1"; // Why not QF?
            $r = MVC_Core::$db->query($q);
            if ($r && $r->num_rows == 1) {
                $ip_ban = $r->current();
                if ($ip_ban['number'] >= MVC_Core::$settings['core']['security']['max_failed_logins']) {
                    header('Content-type: text/plain');
                    echo "Your IP address [ {$_SERVER['REMOTE_ADDR']} ] is temporarily banned for over {$ip_ban['number']} failed login attempts.\n\n";
                    echo "Contact Cargohub Support for assistance if this happened unexpectedly.";
                    die;
                }
            }
        }
        $this->last_error = "No session";
        return false; // not yet banned, just no session
    }

    /**
     * Reads information about the logged in user and sets up the active session (creates or updates it)
     * This method should be called after a successful login or when important data has been changed for the user in the database (so all the data in the session is also updated)
     * -- This method can be called for example after the user has switch department, in order to update all credentials.
     *
     * @param int $user_id
     */
    public function update_Session($user_id) {
        // read the user's data
        $user = $this->read($user_id, array('user', 'organization', 'orgtype', 'department'), true);
        if ($user === false) {
            // error, the user doesn't exist (anymore?), force logout
            $this->do_Logout();
            throw new Exception("Account cannot be accessed or has been removed");
        }

        // Move the "organization" and "department" out of "user" and directly into the session - for shorter variable access. They still reffer to the currently logged-in user.
        if (isset($user['organization']))
            MVC_Core::$ss->organization = $user['organization'];
        if (isset($user['department']))
            MVC_Core::$ss->department = $user['department'];

        // Read "preferences" special key from the profiles table when constructing session
        // -- It contains various settings which are personal to the user (like current jqx_theme, current issue type, e-mail settings, where to "land" after login, what to show on the user-menu-bar, user's shortcuts (to various modules/sub-modules/activities), etc.
        // -- Preferences are kept in session because they usually don't change while the user is operating the system so it's basically a cache system
        $user['preferences'] = $this->profile_Read($user['id'], "preferences");

        // In case there are no preferences set yet, give some defaults (some of them are absolutely necessary for correct operation)
        if (!isset($user['preferences']['logon_redirect']))
        // Which page to redirect to after logging in? Defaults to first controller in organization type
            $user['preferences']['logon_redirect'] = '/' . str_replace('_', '/', $user['organization']['orgtype']['controllers'][0]);
        if (!isset($user['preferences']['jqx_theme']))
        // Defaults to the domain-set jqx_theme or the default one in the global settings
            $user['preferences']['jqx_theme'] = isset(MVC_Core::$ss->domain_CFG['html_settings']['jqx_theme']) ? MVC_Core::$ss->domain_CFG['html_settings']['jqx_theme'] : MVC_Core::$settings['html']['jqx_theme'];
        if (!isset($user['preferences']['rt_id']))
        // Defaults to first issue type that the user has access to, or issue type "1" when there are no restrictions (Admin only?)
            $user['preferences']['rt_id'] = isset($user['acls']['ist']) ? reset($user['acls']['ist']) : 1;

        // Read the record types (modules) the user has access to - They are displayed in a lot of places, including on the "userbar" when the option has been turned on.
        // -- Also used to locate names of the record types based on their ID (in grids and other places)
        // -- This is a session-based global cache for record types
        $m_issue = new App_Model_Issue();
        $ist_r = $m_issue->ist_Get_List(!empty($user['acls']['ist']) ? $user['acls']['ist'] : null);

        if (!$ist_r || count($ist_r) == 0)
            throw new Exception("Account misconfigured. No record types are enabled.");

        $record_types = array();
        $record_types_index = array();
        $record_types_keys = array();
        foreach ($ist_r as $c => $item) {
            $record_types[] = array(
                'id' => (int) $item['id'],
                'key' => $item['key'],
                'name' => $item['name']
            );
            $record_types_index[(int) $item['id']] = $c;
            $record_types_keys[$item['key']] = $c;
        };
        MVC_Core::$cache->record_types = $record_types;
        MVC_Core::$cache->record_types_index = $record_types_index; // Used to locate the record type in the list array by it's numeric ID
        MVC_Core::$cache->record_types_keys = $record_types_keys; // Used to locate the record type in the list array by the record type key
        // Read and store detailed information about current record type (last one used by the user) - This is only updated on RT changes App_Ctrl_Base::rt_Switch
        // Used then everywhere in the software where this information is needed ( TODO: Many modules read this again from the db, this should not be needed ! )
        $base_rt_id = $user['preferences']['rt_id'];
        // Scan overrides for loading proper "current_rt" directly from login
        // - Level 3 - Organization type for the user's organization
        if (isset(MVC_Core::$ss->organization['orgtype']['ist_or']) && isset(MVC_Core::$ss->organization['orgtype']['ist_or'][$base_rt_id]))
            $or_id = MVC_Core::$ss->organization['orgtype']['ist_or'][$base_rt_id];
        // - Level 2 - Organization of the user itself
        if (isset(MVC_Core::$ss->organization['ist_or']) && isset(MVC_Core::$ss->organization['ist_or'][$base_rt_id]))
            $or_id = MVC_Core::$ss->organization['ist_or'][$base_rt_id];
        // - Level 1 - The user's department or the user's personal override in the department
        if (isset(MVC_Core::$ss->department['ist_or']) && isset(MVC_Core::$ss->department['ist_or'][$base_rt_id]))
            $or_id = MVC_Core::$ss->department['ist_or'][$base_rt_id];
        if (empty($or_id))
            $or_id = $base_rt_id;

        MVC_Core::$cache->current_rt = $m_issue->ist_Read($or_id, false, false, true);

        // Dropdowns that show up in the "user-menu-bar" in header, aligned to the right, allowing the user to choose persistent settings (like their visual theme, active issue type) or some other operations
        // -- They are constructed only when session is updated (usually at login time)
        $usermenubar_dropdowns = array();

        // 1. jqWidgets Themes list, constructed by analysing the "styles" folder in jqWidgets, if user's preferences say that that list should exist in the usermenubar
// 		if(isset($user['preferences']['usermenubar']) && isset($user['preferences']['usermenubar']['jqx_themes']) && $user['preferences']['usermenubar']['jqx_themes']==true) {
        // TEMP: theme always available
        $usermenubar_dropdowns['jqx_themes'] = array(
            'dd_name' => "Theme",
            'dd_id' => "jqxtheme",
            'dd_width' => 120
        );
        // Load themes from the config file
        $themes = MVC_Core::$spyc->loadFile(ROOT . DS . 'app' . DS . 'data' . DS . 'themes.yaml');

        foreach ($themes as $id => $theme) {
            if (!empty($theme['disabled']))
                continue; // Skip disabled ones
            $usermenubar_dropdowns['jqx_themes']['list'][] = array(
                'id' => $id,
                'name' => $theme['display']
            );
            if ($id == $user['preferences']['jqx_theme'])
                $usermenubar_dropdowns['jqx_themes']['dd_name'] .= sprintf(": %s", $theme['display']); // used as the visible menuitem
        }
// 		}
        // 2. Issue types list, depending on user's access (or, for admin and supervisor, all available issue types), also if the user's preferences say that this list should be in the usermenubar
        $usermenubar_dropdowns['ist'] = array(
            'dd_name' => "Module",
            'dd_id' => "ist",
            'dd_width' => 180
        );

        $usermenubar_dropdowns['ist']['list'] = $record_types;

        // 3. Initialization of certain defaults
        if (!empty($user['preferences']['send_alias']))
            MVC_Core::$cache->persistent_mail_alias = $user['preferences']['send_alias'];

        // 4. TODO: Shortcuts system
        if (isset($user['preferences']['usermenubar']) && isset($user['preferences']['usermenubar']['shortcuts']) && $user['preferences']['usermenubar']['shortcuts'] == true) {
            
        }

        // reverse the order of $usermenubar_dropdowns, so the first one appears on the right side, with each subsequent one to it's left
        // ... and save this in session
        // -- TODO: Perhaps, another preference for choosing the order on which these things are shown in the menu?
        MVC_Core::$ss->umb_dds = array_reverse($usermenubar_dropdowns);
        $task = new App_Model_Task();
        // User stuff that should not be here
        unset($user['organization'], $user['department']);

        // write user's data in session and local cache
        MVC_Core::$ss->user = $user;
        MVC_Core::$ss->taskCount = (int) $task->get_Task_Count();

        // 30012025 header count add by iwi | start
        $whereCondition2 = $this->records_based_permission_current_depertment();
        $get_total_capa = $m_issue ->get_total_number_of_capa_audit($user['org_id']," AND audit_activity.status = 'active'",$whereCondition2);
        $_get_total_capa = 0;
        foreach($get_total_capa as $val){
            $_get_total_capa = $val['total'];
        }
        MVC_Core::$ss->capaCount =$_get_total_capa ;
        $m_evan = new App_Model_Evan();
        MVC_Core::$ss->notificationCount = $m_issue ->get_total_number_of_notification($user_id);
        // 30012025 header count add by iwi | start

                
//        <!-- new header start -->
//         $getOrdAllDetails = App_Model_Organization::read($user['org_id']);
         $orgModel = new App_Model_Organization();
$getOrdAllDetails = $orgModel->read($user['org_id']);
        MVC_Core::$ss->curr_org_logo = (isset($getOrdAllDetails['org_logo']) && !empty($getOrdAllDetails['org_logo']))?$getOrdAllDetails['org_logo']:'/img/icons/cargohub_logo_common.png';
        
//        <!-- new header end --> 

        $this->set_Local_Storage();
    }

    // 06122024 permission add start | IWI user can view records based on only there current active depertment  start
    public function records_based_permission_current_depertment(){
        $org_lebel_issues_view_orgowned=false;
        $dep_lebel_issues_view_orgowned=false;
        $usr_lebel_issues_view_orgowned=false;
        
        $q_access_org = QueryFactory::select()
                ->table(MVC_Core::$db->tab['org'], 'privileges') // the json-encoded fields
                ->where("id = ?", $_SESSION['user']['org_id'])
                ->limit(1);
        $r_access_org = MVC_Core::$db->query($q_access_org);
        $org_access_org_data = $r_access_org->current();
        $orl_lebel_issues_view_orgowned=false;
        if (!empty($org_access_org_data['privileges']) && in_array("issues_view_orgowned", explode(",", $org_access_org_data['privileges']))) {
            $org_lebel_issues_view_orgowned=true;
        }
        
        $q_access_dep = QueryFactory::select()
        ->table(MVC_Core::$db->tab['dep'], 'privileges_or') // the json-encoded fields
        ->where("id = ?", $_SESSION['user']['cdep_id'])
        ->limit(1);
        $r_access_dep = MVC_Core::$db->query($q_access_dep);
        $org_access_dep_data = $r_access_dep->current();
        $orl_lebel_issues_view_orgowned=false;
        if (!empty($org_access_dep_data['privileges_or']) && in_array("issues_view_orgowned", explode(",", $org_access_dep_data['privileges_or']))) {
            $dep_lebel_issues_view_orgowned=true;
        }
        $havingCondition2 = '';
        if(!empty($_SESSION['user']['acls']['groups'])){
            $groups = array_keys($_SESSION['user']['acls']['groups']);
            $groups = implode(",",$groups);
        }
        
        if(!empty($org_access_dep_data['privileges_or'])){
        if(!$dep_lebel_issues_view_orgowned){
            if ($_SESSION['user']['status'] != 'admin') {
                // $q->where('find_in_set("' . $_SESSION['user']['cdep_id'] . '", dep_ids) <> 0');
                $havingCondition2 .= ' AND `issues`.`group_id` IN('.$groups.') ';
                $havingCondition2 .= ' AND find_in_set("' . $_SESSION['user']['cdep_id'] . '", dep_ids) <> 0';
            }
        }
        }else if(!empty($org_access_org_data['privileges'])){
            if(!$org_lebel_issues_view_orgowned){
            if ($_SESSION['user']['status'] != 'admin') {
                $havingCondition2 .= ' AND `issues`.`group_id` IN('.$groups.') ';
                // $q->where('find_in_set("' . $_SESSION['user']['cdep_id'] . '", dep_ids) <> 0');
                $havingCondition2 .= '  AND  find_in_set("' . $_SESSION['user']['cdep_id'] . '", dep_ids) <> 0';
            }
        }
        }else{
            if (!empty($_SESSION['user']['status']) && ($_SESSION['user']['status'] != 'admin')) {
                $havingCondition2 .= ' AND `issues`.`group_id` IN('.$groups.') ';
                // $q->where('find_in_set("' . $_SESSION['user']['cdep_id'] . '", dep_ids) <> 0');
                $havingCondition2 .= ' AND find_in_set("' . $_SESSION['user']['cdep_id'] . '", dep_ids) <> 0';
            }
        }
        return $havingCondition2;
    }
    //  06122024 permission add end | IWI user can view records based on only there current active depertment  end

//----- User Profile -----//

    /**
     * Reads an user profile data set, by key. If not found and no default exists, it returns false
     *
     * @param int $user_id
     * @param string $pkey Profile key which needs to be read
     * @param bool $do_not_decode Do not decode the entry, possibly it has to be transmitted further direcly as JSON
     * @param int $variant Support for multiple variants of the same profile key
     * @param bool $no_org_default If the user profile record was not found, don't attempt to discover (and copy) an organization based. It means this is a strictly personal profile information which doesn't make sense to have a default.
     * @return string|array|bool
     */
    public function profile_Read($user_id, $pkey, $do_not_decode = false, $variant = 1, $no_org_default = false) {
        if (empty($user_id) || empty($pkey))
            return false; // Error $user_id or $pkey empty, not specified, can't run query

        $q = QueryFactory::select()
                ->table(MVC_Core::$db->tab['upr'], 'data')
                ->where("`user_id` = ?", $user_id) // Loads the user-specific
                ->where("`pkey` = ?", $pkey)
                ->where("`variant` = ?", $variant)
                ->limit(1);

        $r = MVC_Core::$db->query($q);
        if (!$r || count($r) == 0) {
            if ($no_org_default)
                return false;

            // When the profile was not found, attempt to read the "default" for the user's organization, then copy that to the user's personal profile ( auto-initialization )
//            $q = QueryFactory::select()
//                    ->table(MVC_Core::$db->table('upd'), 'org_id,pname,data')
//                    ->table(MVC_Core::$db->table('usr'), false, 'right', '(`org_id`)')
//                    ->where("`usr`.id = ?", $user_id)
//                    ->where("`upd`.pkey = ?", $pkey);
            
            $q = QueryFactory::select()
        ->table(MVC_Core::$db->table('upd'), 'org_id,pname,data')
        ->table(MVC_Core::$db->table('usr'), false, 'right', '`upd`.`org_id` = `usr`.`org_id`')
        ->where("`usr`.id = ?", $user_id)
        ->where("`upd`.pkey = ?", $pkey);

            $r2 = MVC_Core::$db->query($q);
            if ($r2 && count($r2) == 1) {
                $r2_row = $r2->current();

                $q = QueryFactory::insert()
                        ->table(MVC_Core::$db->tab['upr'])
                        ->values(array(
                    "user_id" => $user_id,
                    "org_id" => $r2_row['org_id'],
                    "pkey" => $pkey,
                    "pname" => isset($r2_row) ? $r2_row['pname'] : null,
                    "data" => isset($r2_row) ? $r2_row['data'] : null,
                    "source" => "default"
                ));

                MVC_Core::$db->query($q);
            }

            if (!isset($r2_row))
                return false;
            else
                return $do_not_decode ? $r2_row['data'] : @json_decode($r2_row['data'], true);
        }

        // User's own profile entry found - Use it directly (could still return false if there's no data)
        $row = $r->current();
        return $do_not_decode ? $row['data'] : (empty($row['data']) ? false : @json_decode($row['data'], true));
    }

    /**
     * Creates, updates or deletes a user profile data set, by key.
     * -- On update it overwrites any setting that might be there with the provided data
     *
     * @param int $user_id
     * @param string $pkey Profile key (to locate profile entries)
     * @param array $data If null or empty, the profile key is deleted instead
     * @param bool $data_encoded Indicates that the data has been pre-encoded as JSON before calling this method (possibly directly from JS before reaching PHP)
     * @param string $pname Profile name (not mandatory)
     * @param int $variant // Variant 1 is the default ; All other variants > 1 are customizations | Defaults to "1" - If using "Null" means creating new variant
     */
    public function profile_Write($user_id, $pkey, $data = null, $data_encoded = false, $pname = null, $variant = 1) {
        if (empty($data)) {
            // No data ? Clear the profile setting
            $q = QueryFactory::delete()
                    ->table(MVC_Core::$db->tab['upr'])
                    ->where("`user_id` = ?", $user_id)
                    ->where("`pkey` = ?", $pkey)
                    ->where("`variant` = ?", $variant);

            return MVC_Core::$db->query($q);
        } else {
            $rec = array(
                "user_id" => $user_id,
                "pkey" => $pkey,
                "source" => "user",
                "pname" => $pname,
                "data" => $data_encoded ? $data : json_encode($data)
            );

            if (!empty($variant))
                $rec["variant"] = $variant;

            // Just ... replace ;)
            $q = QueryFactory::replace()
                    ->table(MVC_Core::$db->tab['upr'])
                    ->values($rec);

            MVC_Core::$db->query($q);

            if (empty($variant))
                return MVC_Core::$db->insert_id;
            else
                return $variant;
        }
    }

    /**
     * Updates a profile record description without chaning it's data. Returns the number of affected rows (1) or (0) if row was not found.
     *
     * @param int $user_id
     * @param string $pkey
     * @param string $pname
     * @param int $variant
     */
    public function profile_Update_Pname($user_id, $pkey, $pname = null, $variant = 1) {
        $q = QueryFactory::update()
                ->table(MVC_Core::$db->tab['upr'])
                ->where("`user_id` = ?", $user_id)
                ->where("`pkey` = ?", $pkey)
                ->where("`variant` = ?", $variant)
                ->values(array(
            'pname' => $pname
        ));

        MVC_Core::$db->query($q);
        return MVC_Core::$db->affected_rows;
    }

    /**
     * Reads the profile records for a certain user, optionally filtering by pkeys that being with a prefix
     * -- Can be used to locate multiple variants of the same type of records or for the user profile manager
     * @param int $user_id
     * @param callback $qfo_filter When the resurs need to be filtered by custom rules
     * @return Iterable Result
     */
    public function profile_Get_List($user_id, $qfo_filter = null) {
        if (empty($user_id))
            return false; // Error $user_id empty, not specified, can't run query

        $q = QueryFactory::select()
                ->table(MVC_Core::$db->tab['upr'], "pkey,variant,source,pname,utime")
                ->where("`user_id` = ?", $user_id)
                ->order('`pkey` ASC, `variant` ASC');

        // custom filter callback
        if (is_callable($qfo_filter)) {
            call_user_func_array($qfo_filter, array(&$q));
            // when filter fails, it nulls the QueryFactory object, and get_List must fail
            if (is_null($q))
                return false; // Error, the custom filter crashed/failed
        }

        $r = MVC_Core::$db->query($q);

        if (!$r || count($r) == 0)
            return false; // error or empty result

        return $r;
    }

//----- User to Group relation -----//
// FIXME: No longer used, see if still necessary if not remove.

    private static $u2g_supported_columns = array('preferences');

    /**
     * Reads data about the relation of an user to a group
     *
     * @param int $user_id
     * @param int $group_id
     * @param bool $decode Decode the read data (preferences) before returning
     * @return array
     */
    public function user2group_Read($user_id, $group_id, $decode = true) {
        $q = QueryFactory::select()
                ->table(MVC_Core::$db->tab['u2g'], self::$u2g_supported_columns)
                ->where("`user_id` = ?", $user_id)
                ->where("`group_id` = ?", $group_id)
                ->limit(1);

        $r = MVC_Core::$db->query($q);
        if ($r && count($r) == 1) {
            $row = $r->current();
            if ($decode) // decode if requested
                foreach ($row as $column => &$values)
                    $values = @json_decode($values, true);
            return $row;
        } else
            return false;
    }

    /**
     * Creates, Updates or Deletes data about relations between users and groups
     * -- If the passed data is empty, it means deleting the row
     *
     * @param int $user_id
     * @param int $group_id
     * @param array $data (array of arrays or strings) - The data which will be stored/modified in the u2g table
     * @param bool $encoded The passed data is already encoded, do not attempt to json-encode it
     */
    public function user2group_Write($user_id, $group_id, $data, $encoded = false) {
        if (empty($data)) {
            $q = QueryFactory::delete()
                    ->table(MVC_Core::$db->tab['u2g'])
                    ->where("`user_id` = ?", $user_id)
                    ->where("`group_id` = ?", $group_id)
                    ->limit(1);

            return MVC_Core::$db->query($q);
        } else {
            // encode each element of the data if requested (they are stored in different columns)
            if (!is_array($data))
                return false; // error, data not an array


















                
// when not already encoded, try a merge ; When encoded, it's always an overwrite !
            if (!$encoded) {
                // read existing data and merge with the new ones
                $q = QueryFactory::select()
                        ->table(MVC_Core::$db->tab['u2g'], self::$u2g_supported_columns)
                        ->where("`user_id` = ?", $user_id)
                        ->where("`group_id` = ?", $group_id)
                        ->limit(1);

                $r = MVC_Core::$db->query($q);
                if ($r) {
                    $row = $r->current();
                    // current values found, combine and do update
                    foreach ($data as $column => &$values) {
                        if (!in_array($column, self::$u2g_supported_columns))
                            return false; // error, unsupported column

                        $current_values = @json_decode($row[$column], true);
                        if (is_array($current_values))
                            $values = array_merge($current_values, $values);
                        $values = json_encode($values);
                    }

                    $mode = 'update';
                } else
                    $mode = 'insert';
            }

            if ($mode == 'update') {
                // try update
                $q = QueryFactory::update()
                        ->table(MVC_Core::$db->tab['u2g'])
                        ->values($data)
                        ->where("`user_id` = ?", $user_id)
                        ->where("`group_id` = ?", $group_id)
                        ->limit(1);

                $r = MVC_Core::$db->query($q);

                // see what happened
                $sql_response = MVC_Core::$db->get_Response();
                if ($sql_response['rows_matched'] == 0)
                    $mode = 'insert';
                else
                    return $r;
            }
            if ($mode == 'insert') {
                // not found or nothing updated, try to do insert
                $data['user_id'] = $user_id;
                $data['group_id'] = $group_id;

                // oops, update failed, possibly because the row doesn't yet exist, so try to insert
                $q = QueryFactory::insert()
                        ->table(MVC_Core::$db->tab['u2g'])
                        ->values($data);

                return MVC_Core::$db->query($q);
            }
        }
    }

    /**
     * Searches the user-2-group table for a certain value of a variable in one of the encoded columns
     * -- Returns a list of IDs ( either user_ids or group_ids ) which contain that value
     *
     * @param string $data_column
     * @param string $variable
     * @param mixed $value
     * @param int $user_id
     * @param int $group_id
     */
    public function user2group_Find_Value($data_column, $variable, $value, $user_id = null, $group_id = null) {
        if (!in_array($data_column, self::$u2g_supported_columns))
            return false; // error, unsupported column


















            
// determine what to read
        if (!is_null($user_id)) { // note that since user_id is checked first, if both are not-null, user_id is considered first for search
            $wtr_columns = array('group_id', $data_column);
            $cond_column = 'user_id';
            $id_column = 'group_id';
            $cond_value = $user_id;
        } elseif (!is_null($group_id)) {
            $wtr_columns = array('user_id', $data_column);
            $cond_column = 'group_id';
            $id_column = 'user_id';
            $cond_value = $group_id;
        } else
            return false; // cannot work if both user_id and group_id are null !

        $q = QueryFactory::select()
                ->table(MVC_Core::$db->tab['u2g'], $wtr_columns)
                ->where("`{$cond_column}` = ?", $cond_value)
                ->order("`{$id_column}` ASC");

        $r = MVC_Core::$db->query($q);

        $id_list = array();
        if ($r && count($r) > 0) {
            foreach ($r as $row) {
                $data = @json_decode($row[$data_column], true);
                if (isset($data[$variable]) && $data[$variable] == $value)
                    $id_list[] = $row[$id_column];
            }
        }
        return $id_list; // might return empty result if nothing found (not an error)
    }

    /**
     * Reads a set of groups and the stored user2group for an user_id - all columns (used for listing/editing of data)
     *
     * @param int $user_id Read connections only for this user_id
     * @param array $group_ids List of groups to read (array of numeric IDs)
     * @param bool $decode Decode the read data (preferences) before returning
     * @return array
     */
    public function user2group_Get_List($user_id, $group_ids, $decode = true) {
        $q = QueryFactory::select()
                ->table(array(MVC_Core::$db->tab['grp'], 'grp'), QueryFactory::autoalias_Add_Prefix("id,name,type", '_grp_'))
                ->table(array(MVC_Core::$db->tab['u2g'], 'u2g'), self::$u2g_supported_columns, 'left', "`u2g`.`user_id` = {$user_id} AND `u2g`.`group_id` = `grp`.`id`") // reads all data columns
                ->where("`grp`.`id` IN (?)", $group_ids)
                ->order("`grp`.`name` ASC");

        $r = MVC_Core::$db->query($q);

        if (!$r || count($r) == 0)
            return false; // error or empty result

        $r->split_by_prefixes = array(
            // group tabe
            'group' => "_grp_"
        );

        $all = array();
        foreach ($r as $row) {
            if ($decode) // decode if requested
                foreach ($row as $column => &$values) {
                    if (!empty($values) && in_array($column, self::$u2g_supported_columns)) // only data columns will be decoded ...
                        $values = @json_decode($values, true);
                }
            $all[] = $row;
        }
        return $all;
    }

    public function user_IssueCount($user_id, $org_Id, $status, $issueTypes) {
        $usr = MVC_Core::$ss->user;
        $issueTypeIds = array();
        foreach ($issueTypes as $key => $value) {
            $issueTypeIds[] = $key;
        }
        //print_r($_SESSION);
        if ($status != 'admin') {
            $q = QueryFactory::select()
                    ->table(MVC_Core::$db->table('iss'), array('issue_count' => "COUNT(*)"))
                    ->table(MVC_Core::$db->table('iod'), array(), "right", "`iss`.id = `iod`.issue_id  AND `iod`.org_id =" . $org_Id . " AND `iod`.is_enabled = 1")
                    ->table(MVC_Core::$db->table('ist'), array('claim_count_type' => "name", 'id' => "id"), "RIGHT", "`iss`.type_id = `ist`.id")
                    ->where("`iss`.master_org_id = ? OR FIND_IN_SET(?, `iss`.shared_org_ids)", $org_Id, $org_Id)
                    ->where("`iod`.process NOT IN('closed', 'hidden')")
                    ->where("(`iss`.`type_id` in (?))", $issueTypeIds)
                    ->group("`iss`.type_id")
                    ->order("`iss`.id DESC");
            if (!$this->test_ACL('privileges', 'issues_view_orgowned')) {
                // Group based restrictions, only show issues in the groups this user has access to
                $restriction_filters[] = $q->quote_Into("`iss`.`group_id` IN(?)", array_keys($usr['acls']['groups']));

                // Assignment based restricitons:
                $unassgn = $this->test_ACL('privileges', 'issues_view_unassgn');
                $otherassgn = $this->test_ACL('privileges', 'issues_view_assgn_oth');

                if ($unassgn == true && $otherassgn == true) {
                    // see all, nothing to limit (this is just a placeholder case)
                } elseif ($unassgn == true && $otherassgn == false) {
                    // assigned to self and unassigned
                    $restriction_filters[] = $q->quote_Into('(`iod`.assigned_to = ? OR `iod`.assigned_to IS NULL)', $usr['id']);
                } elseif ($unassgn == false && $otherassgn == true) {
                    // assigned to self and to others
                    $restriction_filters[] = $q->quote_Into('(`iod`.assigned_to = ? OR `iod`.assigned_to IS NOT NULL)', $usr['id']);
                } elseif ($unassgn == false && $otherassgn == false) {
                    // only assigned to self
                    $restriction_filters[] = $q->quote_Into('`iod`.assigned_to = ?', $usr['id']);
                }

                // Department-level filter - User's active department must be in the list of "dep_ids" in the issue (unless the user has access to all)
                if (!$this->test_ACL('privileges', 'issue_access_deps'))
                    if (!empty($usr['cdep_id'])) {
                        $restriction_filters[] = $q->quote_Into('FIND_IN_SET(?, `iod`.`dep_ids`)', $usr['cdep_id']);
                    }
                $permission_filters = implode(" AND ", $restriction_filters);
                $q->where($permission_filters);
                //echo $q;
            }
        } else if ($status == 'admin') {
            $q = QueryFactory::select()
                    ->table("issue_count_s_admin_aggr_check", array('issue_count' => "count", 'id' => "type_id", 'claim_count_type' => "claim_count_type")); //array("count","claim_count_type","type_id"));//,QueryFactory::autoalias_Add_Prefix("count,claim_count_type,type_id", '_sCou_'));echo json_encode($q);
        }
        // echo $q;
        $r = MVC_Core::$db->query($q);
        //print_r($r);

        if (!$r || count($r) == 0)
            return false;
        else {
            return $r;
        }
    }

    public function user_IssueCountMyAssigned($user_id, $org_Id, $status, $issueTypes) {
        $usr = MVC_Core::$ss->user;
        $issueTypeIds = array();
        foreach ($issueTypes as $key => $value) {
            $issueTypeIds[] = $key;
        }
        if ($status != 'admin') {
            $q = QueryFactory::select()
                    ->table(MVC_Core::$db->table('iss'), array('issue_count' => "COUNT(*)"))
                    ->table(MVC_Core::$db->table('iod'), array(), "right", "`iss`.id = `iod`.issue_id  AND `iod`.org_id =" . $org_Id . " AND `iod`.is_enabled = 1")
                    ->table(MVC_Core::$db->table('ist'), array('claim_count_type' => "name", 'id' => "id"), "RIGHT", "`iss`.type_id = `ist`.id")
                    ->where("`iss`.master_org_id = ? OR FIND_IN_SET(?, `iss`.shared_org_ids)", $org_Id, $org_Id)
                    ->where("`iod`.process NOT IN('closed', 'hidden')")
                    ->where("(`iss`.`type_id` in (?))", $issueTypeIds)
                    ->where("`iod`.`assigned_to` IN(?) ", $usr['id'])
                    ->group("`iss`.type_id")
                    ->order("`iss`.id DESC");
            if (!$this->test_ACL('privileges', 'issues_view_orgowned')) {
                // Group based restrictions, only show issues in the groups this user has access to
                $restriction_filters[] = $q->quote_Into("`iss`.`group_id` IN(?)", array_keys($usr['acls']['groups']));

                // Assignment based restricitons:
                $unassgn = $this->test_ACL('privileges', 'issues_view_unassgn');
                $otherassgn = $this->test_ACL('privileges', 'issues_view_assgn_oth');

                if ($unassgn == true && $otherassgn == true) {
                    // see all, nothing to limit (this is just a placeholder case)
                } elseif ($unassgn == true && $otherassgn == false) {
                    // assigned to self and unassigned
                    $restriction_filters[] = $q->quote_Into('(`iod`.assigned_to = ? OR `iod`.assigned_to IS NULL)', $usr['id']);
                } elseif ($unassgn == false && $otherassgn == true) {
                    // assigned to self and to others
                    $restriction_filters[] = $q->quote_Into('(`iod`.assigned_to = ? OR `iod`.assigned_to IS NOT NULL)', $usr['id']);
                } elseif ($unassgn == false && $otherassgn == false) {
                    // only assigned to self
                    $restriction_filters[] = $q->quote_Into('`iod`.assigned_to = ?', $usr['id']);
                }

                // Department-level filter - User's active department must be in the list of "dep_ids" in the issue (unless the user has access to all)
                if (!$this->test_ACL('privileges', 'issue_access_deps'))
                    if (!empty($usr['cdep_id'])) {
                        $restriction_filters[] = $q->quote_Into('FIND_IN_SET(?, `iod`.`dep_ids`)', $usr['cdep_id']);
                    }
                $permission_filters = implode(" AND ", $restriction_filters);
                $q->where($permission_filters);
            }
        }
        /* else if($status=='admin'){
          $q=QueryFactory::select()
          ->table("issue_count_s_admin_aggr_check", array('issue_count'=> "count",'id'=>"type_id",'claim_count_type'=>"claim_count_type"));//array("count","claim_count_type","type_id"));//,QueryFactory::autoalias_Add_Prefix("count,claim_count_type,type_id", '_sCou_'));echo json_encode($q);
          } */
        $r = MVC_Core::$db->query($q);
        if (!$r || count($r) == 0)
            return false;
        else {
            return $r;
        }
    }

    public function active_TaskCount($user_id, $org_Id, $status, $issueTypes, $taskType = NULL) {
        $usr = MVC_Core::$ss->user;
        $issueTypeIds = array();
        foreach ($issueTypes as $key => $value) {
            $issueTypeIds[] = $key;
        }
        if ($taskType != NULL) {
            $q = QueryFactory::select()
                    ->table(MVC_Core::$db->table('tsk'), array('issue_count' => "COUNT(*)"))
                    ->table(MVC_Core::$db->table('iss'), array(), "left", "`tsk`.link_id = `iss`.id")
                    ->table(MVC_Core::$db->table('ist'), array('claim_count_type' => "name", 'id' => "id"), "left", "`iss`.type_id = `ist`.id")//grp
                    ->table(MVC_Core::$db->table('grp'), array(), "left", "`grp`.id = `iss`.group_id")
                    ->table(array(MVC_Core::$db->tab['usr'], 'usr'), array(), "left", "`tsk`.added_by = `usr`.id")
                    ->table(array(MVC_Core::$db->tab['usr'], 'dd'), array(), "left", "`tsk`.added_by = `dd`.id")
                    // -> where("`tsk`.org_id = ?", $org_Id)
                    ->where("`tsk`.assign_mode = ? AND `tsk`.assign_to = ?", "user", $user_id)
                    ->where("`tsk`.status NOT IN('failed', 'closed', 'completed') OR `tsk`.status IS NULL")
                    ->where("`tsk`.link_type = 'record'")
                    ->where("(`iss`.`type_id` in (?))", $issueTypeIds)
                    ->where("tsk.deadline < ?", date('Y-m-d H:i:s'))
                    ->group("`ist`.id")
                    ->order("IF(ISNULL(`tsk`.deadline), 1, 0), `tsk`.deadline asc");
        } else {
            $q = QueryFactory::select()
                    //-> table(MVC_Core::$db-> table(tsk), array('issue_count'=> "COUNT(*)"))
                    ->table(MVC_Core::$db->table('tsk'), array('issue_count' => "COUNT(*)"))
                    ->table(MVC_Core::$db->table('iss'), array(), "left", "`tsk`.link_id = `iss`.id")
                    ->table(MVC_Core::$db->table('ist'), array('claim_count_type' => "name", 'id' => "id"), "left", "`iss`.type_id = `ist`.id")//grp
                    ->table(MVC_Core::$db->table('grp'), array(), "left", "`grp`.id = `iss`.group_id")
                    ->table(array(MVC_Core::$db->tab['usr'], 'usr'), array(), "left", "`tsk`.added_by = `usr`.id")
                    ->table(array(MVC_Core::$db->tab['usr'], 'dd'), array(), "left", "`tsk`.added_by = `dd`.id")
                    // -> where("`tsk`.org_id = ?", $org_Id)
                    ->where("`tsk`.assign_mode = ? AND `tsk`.assign_to = ?", "user", $user_id)
                    ->where("`tsk`.status NOT IN('failed', 'closed', 'completed') OR `tsk`.status IS NULL")
                    ->where("`tsk`.link_type = 'record'")
                    ->where("(`iss`.`type_id` in (?))", $issueTypeIds)
                    ->group("`ist`.id")
                    ->order("IF(ISNULL(`tsk`.deadline), 1, 0), `tsk`.deadline asc");
        }

        // echo $q;
        $r = MVC_Core::$db->query($q);
        if (!$r || count($r) == 0)
            return false;
        else {
            return $r;
        }
    }

    /**
     * Removes the user record from the notification system
     *
     * @param int $user_id
     */
    public function removeUserNotify($user_id) {
        // Force the user to logout (this probably would never happen, but just in case a user which has been compromised needs to be deleted when they are still doing stuff)
        $this->do_Logout($user_id);

        // delete record from notify_subscriptions table
        $q = QueryFactory::delete()
                ->table(MVC_Core::$db->tab['nsu'])
                ->where("`user_id` = ?", $user_id);

        $r = MVC_Core::$db->query($q);
        if (!$r)
            return false; // error when deleting

        return true;
    }

    /**
     * Removes the user record from u2d and department table if exists
     *
     * @param $user_id
     *
     * @return bool
     * @throws Exception
     */
    public function removeUserDependency($user_id) {
        // Force the user to logout (this probably would never happen, but just in case a user which has been compromised needs to be deleted when they are still doing stuff)
        $this->do_Logout($user_id);
        //selecting dept_id of user (could be more than one)
        $q = QueryFactory::select()
                ->table(MVC_Core::$db->table('u2d'), "dep_id")
                ->table(MVC_Core::$db->table('dep'), "count_members", 'left', '`u2d`.dep_id = `dep`.id')
                ->where("`u2d`.`user_id` = ?", $user_id);
        $r = MVC_Core::$db->query($q);
        if ($r && count($r) > 0) {
            //deleting user from u2d table
            $qr = QueryFactory::delete()
                    ->table(MVC_Core::$db->tab['u2d'])
                    ->where("`user_id` = ?", $user_id);
            MVC_Core::$db->query($qr);
            foreach ($r as $key => $value) {
                $dept_id = $value['dep_id'];
                $count_mem = $value['count_members'];
                if ($count_mem > 0 && $dept_id > 0) {
                    $current_mem = $count_mem - 1;
                    $data['count_members'] = $current_mem;
                    $qd = QueryFactory::update()
                            ->table(MVC_Core::$db->tab['dep'])
                            ->where("`id` = ?", $dept_id)
                            ->values($data);

                    MVC_Core::$db->query($qd);
                }
            }
        }
        return true;
    }

    /**
     * Modify Oauth
     * @param $arr
     *
     * @return mixed
     */
    public function modifyOauth($arr) {
        //if user oath is being disabled
        if ($arr['oath_key_switch']) {
            $arr['oath_key'] = NULL;
            $arr['oath_turnon'] = NULL;
            unset($arr['oath_key_switch']);
        }
        return $arr;
    }

    /**
     * Reset two factor auth of user by setting values to null of oauth_key and oath_turnon field in user table
     * @param $user_id
     *
     * @return bool
     * @throws Exception
     */
    public function resetTwoFactorAuth($user_id) {
        if (!empty($user_id)) {
            $q = QueryFactory::update()
                    ->table(MVC_Core::$db->tab['usr'])
                    ->where("`id` = ?", $user_id)
                    ->values(array(
                'oath_key' => NULL,
                'oath_turnon' => NULL
            ));

            $r = MVC_Core::$db->query($q);
            if (!$r)
                return false;
        } else {
            return false;
        }
        return true;
    }

    

    public function getPublishDocuments($user_id, $pkey, $do_not_decode = false, $variant = 1, $no_org_default = false, $search = array()) {

        if ($search['tab_type'] != 'private' && $search['search_by_department_list'] == '' && $search['tab_type'] != 'fetch_all_published_by_org') {
            return array();
        }
//        IWI user can view all published doc by assigned organization


        if (empty($user_id) || empty($pkey))
            return false; // Error $user_id or $pkey empty, not specified, can't run query
//echo $user_id;
        $q = QueryFactory::select()
                // ->table(MVC_Core::$db->tab['nte'], 'id,wv_key,wv_publish,link_id,visibility,edit_access,editor_id,org_id,dep_id,dt,title,version,wv_publish_for_ids,wv_publish_for,description,created_by,published_by,is_required_read_sign,update_dt,ref_number') 
                // 16012025 bug fixed get_publications -> Departments list 
                ->table(MVC_Core::$db->table('nte'),'id,wv_key,wv_publish,link_id,visibility,edit_access,editor_id,org_id,dep_id,dt,title,version,wv_publish_for_ids,wv_publish_for,description,created_by,published_by,is_required_read_sign,update_dt,ref_number')
                ->table(MVC_Core::$db->table('iss'), array(),"inner", "`nte`.link_id = `iss`.id");
                
                // IWI if any update in the doc section, then the update icon will show in publication list
        // add ref_number for IWI ref no for documents 
        $q->where("`wv_publish` = ?", '1');
        $q->where("`visibility` != ?", 'private');
        $q->where("`is_notification` = ?", '0'); //IWI read and sign required
//        $q->where("`visibility` = ?", 'org');
        if (!empty($search['tab_type']) && $search['tab_type'] == 'private') {
            $q->where("`editor_id` = ?", $user_id);
        } else if (!empty($search['tab_type']) && $search['tab_type'] == 'mydata') {
            $q->where("`published_by` = ?", $user_id);
        } else if (!empty($search['tab_type']) && $search['tab_type'] == 'fetch_my_allocate_data') {
            $q->where("`published_by` = ?", $user_id);
            $q->where("`wv_publish_for` != ?", 'private'); // IWI remove private docs from published tab
        } 
//        IWI user can view all published doc by assigned organization start
        else if (!empty($search['tab_type']) && $search['tab_type'] == 'fetch_all_published_by_org') {
            $q->where("`org_id` = ?", MVC_Core::$ss->user['org_id']);
            $q->where("`wv_publish_for` != ?", 'private'); // IWI remove private docs from published tab
        } 
//        IWI user can view all published doc by assigned organization end
        else {
            $q->where("`org_id` = ?", MVC_Core::$ss->user['org_id']);
        }
//        $q->where("`wv_publish` = ?", '1');
        if (!empty($search['search_type'])) {
            foreach ($search['search_type'] as $s) {
                if ($s == 'by_name') {
                    $q->where('title  like "%' . $search['keyword'] . '%"');
                }
                if ($s == 'by_desc') {
                    $q->where('text  like "%' . $search['keyword'] . '%"');
                }
                
//                IWI ref no for documents start
                if ($s == 'by_ref_no') {
                    $q->where('ref_number  like "%' . $search['keyword'] . '%"');
                }
//                IWI ref no for documents end
                
            }
        }
        if ($search['tab_type'] != 'mydata' && $search['tab_type'] != 'fetch_my_allocate_data') {
            if (!empty($search['search_by_department_list'])) {
                $q->where('wv_publish_for_ids  like "%' . $search['search_by_department_list'] . '%" ');
            }
        }

        if (!empty($search['tab_type'])) {

            if ($search['tab_type'] == 'all') {
                $q->where('wv_publish_for_ids  like "%all%"');
            } else if ($search['tab_type'] == 'department') {
                $q->where("`wv_publish_for` = ?", 'department');
            } else if ($search['tab_type'] == 'private') {
                $q->where("`wv_publish_for` = ?", 'private');
            }
        }

        $q->order('`id` DESC');
//                ->where("`editor_id` = ?", $user_id);


        if ($search != '') {
//            echo $search;
//            $q->where("`title`", 'like', '%' . $search . '%');
        }
//   echo $q ; exit;

        $r = MVC_Core::$db->query($q);

        $finalData = array();
        if ($r && count($r) > 0) {

            foreach ($r as $row) {
//                echo $row['wv_publish_for'] . '//';
//echo "<pre>";
//    print_r($_SESSION['user']['cdep_id']);
//    echo 2121;

                $noteDepArr = json_decode($row['wv_publish_for_ids']);

//                if (($row['wv_publish_for'] == 'private') && ($row['editor_id'] != $_SESSION['user']['id'])) {
//                    continue;
//                } else if (($row['wv_publish_for'] == 'department') && (!in_array($_SESSION['user']['cdep_id'], $noteDepArr))) {
//                    continue;
//                }

                $noteDepCount=0;
                if (!empty($noteDepArr)) {
                    $noteDepTemp = array();
                    foreach ($noteDepArr as $dp) {
                        $q_dep = QueryFactory::select()->table(MVC_Core::$db->tab['dep'])->where("`id` = ?", $dp)->limit(1);
                        $r_dep = MVC_Core::$db->query($q_dep);
                        $result_dep = $r_dep->current();
                        $noteDepTemp[] = $result_dep['name'];
                    }
                    $noteDep = implode(", ", $noteDepTemp);
                    $noteDepCount = count(array_filter($noteDepTemp));
                } else {
                    $noteDep = '---';
                }
//                echo $row['id'].'----/';
                $q2 = QueryFactory::select()
                        ->table(MVC_Core::$db->tab['iss'], 'track,type_id,cache_primary,group_id')
                        ->where("`id` = ?", $row['link_id']);

                $row['nid'] = base64_encode($row['id']);

                $r2 = MVC_Core::$db->query($q2);

                foreach ($r2 as $row2) {
                    $issue_details = $row2;
                    $row['ist'] = $row2['type_id'];
                    $row['pri'] = $row2['cache_primary'];
                    $row['group_id'] = $row2['group_id'];

                    $q3 = QueryFactory::select()
                            ->table(MVC_Core::$db->tab['grp'], '*')
                            ->where("`id` = ?", $row2['group_id']);

                    $r3 = MVC_Core::$db->query($q3);

                    foreach ($r3 as $row3) {
                        $group_details = $row3;
                        $row['group_name'] = $group_details['name'];
                    }
                }


                $row['track_id'] = $issue_details['track'];
                $row['department'] = $noteDep;
                $row['department_count'] = $noteDepCount;
                 if($row['update_dt']!=''){
                $row['dt'] =  date("Y-m-d", strtotime($row['update_dt']));
                }else{
                     $row['dt'] = date("Y-m-d", $row['dt']);
                }

                $getSignInDataCount = array();
                if ($search['tab_type'] == 'mydata') {
                    $getSignInDataSQL = QueryFactory::select()->table('user_read_sign_note')
                            ->where("`note_id` = ?", $row['id'])
                            ->where("`read_sign` = ?", '1');
                    $getSignInDataQuery = MVC_Core::$db->query($getSignInDataSQL);
                    foreach ($getSignInDataQuery as $getSignInData) {
                        $getSignInDataCount[] = $getSignInData;
                    }
                    $row['getSignInDataCount'] = count($getSignInDataCount);

                    $depUsers = intval(0);
                    if (!empty($noteDepArr)) {
                        foreach ($noteDepArr as $dp) {
                            $q_getUserCount = QueryFactory::select()
                                    ->table(MVC_Core::$db->table('u2d'), "dep_id")
                                    ->table(MVC_Core::$db->table('dep'), "count_members", 'left', '`u2d`.dep_id = `dep`.id')
                                    ->where("`u2d`.`dep_id` = ?", $dp);

                            $r_getUserCount = MVC_Core::$db->query($q_getUserCount);
                            $r_getUserCount->current();
                            $depUsers += intval($r_getUserCount->num_rows);
                        }
                    }

                    $row['getTotDepUsers'] = $depUsers;
                }

                $getSignInDataCount2 = array();
                if ($search['tab_type'] == 'fetch_my_allocate_data') {
                    $getSignInDataSQL = QueryFactory::select()->table('user_read_sign_note')
                            ->where("`note_id` = ?", $row['id'])
                            ->where("`read_sign` = ?", '1');
                    $getSignInDataQuery = MVC_Core::$db->query($getSignInDataSQL);
                    foreach ($getSignInDataQuery as $getSignInData) {
                        $getSignInDataCount2[] = $getSignInData;
                    }
                    $row['getSignInDataCount'] = count($getSignInDataCount2);

                    $depUsers = intval(0);
                    if (!empty($noteDepArr)) {
                        $depUsersArr = array();
                        foreach ($noteDepArr as $dp) {
                            if ($dp != 'all') {
                                $q_getUserCount = QueryFactory::select()
                                        ->table(MVC_Core::$db->table('u2d'), "dep_id,user_id")
                                        ->table(MVC_Core::$db->table('usr'), array('email', 'name'), 'left', '`u2d`.user_id = `usr`.id')
                                        ->where("`u2d`.`dep_id` = ?", $dp)
                                        ->where("`usr`.`status` != ?", 'disabled');
                                
                               
                                
                                
                                $r_getUserCount = MVC_Core::$db->query($q_getUserCount);

                                foreach ($r_getUserCount as $getDepusr) {
                                    $depUsersArr[] = $getDepusr['user_id'];
                                }
                            }
                        }
                        $depUsersArr = array_unique($depUsersArr);
                        $depUsers = count($depUsersArr);
                    }


                    $row['getTotDepUsers'] = $depUsers;
                }
                
//                IWI user can view all published doc by assigned organization start

                $getSignInDataCount3 = array();
                if ($search['tab_type'] == 'fetch_all_published_by_org') {
                    $getSignInDataSQL = QueryFactory::select()->table('user_read_sign_note', 'count(id) as tot_r_d_user')
                            ->where("`note_id` = ?", $row['id'])
                            ->where("`read_sign` = ?", '1');
                    $getSignInDataQuery = MVC_Core::$db->query($getSignInDataSQL);
                    $getSignInData = $getSignInDataQuery->current();
                    $row['getSignInDataCount'] = $getSignInData['tot_r_d_user'];

                    $depUsers = intval(0);
                    if (!empty($noteDepArr)) {
                        $depUsers_count = intval(0);
                        foreach ($noteDepArr as $dp) {
                            if ($dp != 'all') {
                                $q_getUserCount = QueryFactory::select()
                                        ->table(MVC_Core::$db->table('u2d'), "count(`u2d`.`user_id`) as tot_dep_user_count ")
                                        ->table(MVC_Core::$db->table('usr'), array(), 'left', '`u2d`.user_id = `usr`.id')
                                        ->where("`u2d`.`dep_id` = ?", $dp)
                                        ->where("`usr`.`status` != ?", 'disabled');
                                $r_getUserCount = MVC_Core::$db->query($q_getUserCount);

                                $getDepusr = $r_getUserCount->current();
//                    print_r($getDepusr);
                                $depUsers_count += intval($getDepusr['tot_dep_user_count']);
                                $depUsers = $depUsers_count;
                            }
                            $row['getTotDepUsers'] = $depUsers;
                        }
                    }
                    
                }
//                IWI user can view all published doc by assigned organization end
                
                
                
                
                
                
                
                
                   //iwi doctype enhancement start
                         $q_doctype="SELECT value, TRIM( BOTH '\"' FROM TRIM( TRAILING ',' FROM SUBSTRING_INDEX( SUBSTRING_INDEX(issue_xfdef.cd, CONCAT('#', issue_fields.value, ': '), -1), ',', 1 ) ) ) AS full_form FROM issue_fields INNER JOIN issue_xfdef ON issue_xfdef.id = issue_fields.fk_id INNER JOIN issues ON issues.id = issue_fields.issue_id WHERE issue_xfdef.fk = 'doctype' And issues.id='".$row['link_id']."'";
                         $r_doctype= MVC_Core::$db->query($q_doctype);
                         $result_doctype = $r_doctype->current();
                         $row['doc_type_full_name']= isset($result_doctype['full_form']) && $result_doctype['full_form']!=''?$result_doctype['full_form']:'--';
                
                //iwi doctype enhancement end
                
                
                
                
                
                
                
                
                
//                IWI Create an icon for archived published doc start
                if(($search['tab_type'] == 'fetch_all_published_by_org') && (intval($row['getSignInDataCount']) < intval($row['getTotDepUsers']))){
                $finalData[] = $row;
                }else{
                    $finalData[] = $row;
                }
//                IWI Create an icon for archived published doc end

//                $finalData[] = $row;
            }
        }

        return $finalData;
    }

    public function getDepartmentsByUserId($user_id) {
        if (!empty($user_id)) {
            $q = QueryFactory::select()
                    ->table(MVC_Core::$db->table('u2d'), "dep_id")
                    ->table(MVC_Core::$db->table('dep'), "id,name", 'left', '`u2d`.dep_id = `dep`.id')
                    ->where("`u2d`.`user_id` = ?", $user_id);

            $r = MVC_Core::$db->query($q);
            if ($r && count($r) > 0) {
                return $r;
            }
        } else {
            return false;
        }
        return true;
    }

    public function getPriviligesPublicationRightByUserId($user_id) {
        if (!empty($user_id)) {
            $q = QueryFactory::select()
                    ->table(MVC_Core::$db->table('u2d'), "dep_id,privileges_or")
                    ->table(MVC_Core::$db->table('dep'), "id,name", 'left', '`u2d`.dep_id = `dep`.id')
                    ->where('`u2d`.`privileges_or` like "%publication_right%"')
                    ->where("`u2d`.`user_id` = ?", $user_id);

            $r = MVC_Core::$db->query($q);
            if ($r && count($r) > 0) {
                return $r;
            }
        } else {
            return false;
        }
        return true;
    }

    public function get_publication_access_user_List() {
//            $q = QueryFactory::select()
//                    ->table(MVC_Core::$db->table('u2d'), "*")
//                    ->table(MVC_Core::$db->table('dep'), "id,name", 'left', '`u2d`.dep_id = `dep`.id')
//                    ->table(MVC_Core::$db->table('usr'), "*", 'left', '`usr`.id = `u2d`.user_id')
//                    ->where('`u2d`.`privileges_or` like "%publication_right%"');




        $q = "SELECT user2dep.*, departments.id as department_id, departments.name as department_name, users.*  FROM user2dep LEFT JOIN departments ON user2dep.dep_id = departments.id  LEFT JOIN users ON users.id = user2dep.user_id WHERE user2dep.privileges_or like '%publication_right%'";
//exit();

        $r = MVC_Core::$db->query($q);
        if ($r && count($r) > 0) {
            $user = array();
            foreach ($r as $row) {
                $user[] = $row;
            }
            return $user;
        }

        return true;
    }

    public function getUserListCondi($user_id) {
        if (!empty($user_id)) {
            $q = "SELECT users.name as UserName,active_sessions.user_id,departments.name as DepartmentName FROM users JOIN active_sessions ON users.id = active_sessions.user_id JOIN user2dep ON user2dep.user_id = active_sessions.user_id JOIN departments ON departments.id = user2dep.dep_id;";

            $r = MVC_Core::$db->query($q);
            if ($r && count($r) > 0) {
                return $r;
            }
        } else {
            return false;
        }
        return true;
    }

    // public function get_user_List() {
    // $q = "SELECT users.name,users.user FROM users JOIN user2dep ON users.id = user2dep.user_id"; 
    //         $r = MVC_Core::$db->query($q);
    //         if ($r && count($r) > 0) {
    //             $user = array();
    //             foreach ($r as $row) {
    //                 $user[] = $row;
    //             }
    //             return $user;
    //         }
    //         return true;
    //     }

    public function get_User_Previliges_Access($user_id) {

        $q_dep_user_label = "SELECT user2dep.dep_id,user2dep.privileges_or FROM user2dep  WHERE user2dep.user_id = '$user_id'";
        $r_dep_user_label = MVC_Core::$db->query($q_dep_user_label);
        if ($r_dep_user_label && count($r_dep_user_label) > 0) {
            $user_privileges_access_label = array();
            foreach ($r_dep_user_label as $row_dep_user_label) {
//                      $user_privileges_access_label[]=$row_dep_user_label;
//                echo "<pre>";
//                print_r($row_dep_user_label);
                $this_privileges_or = explode(',', $row_dep_user_label['privileges_or']);
                if (in_array("publication_right", $this_privileges_or)) {
                    $user_privileges_access_label[] = $row_dep_user_label;
                } else {
                    if ($row_dep_user_label['privileges_or'] == '') {
                        $dep_id = $row_dep_user_label['dep_id'];
                        $q_dep_department_label = "SELECT departments.privileges_or, departments.id as dep_id   FROM departments WHERE departments.id = '$dep_id' ";
                        $r_dep_department_label = MVC_Core::$db->query($q_dep_department_label);
                        foreach ($r_dep_department_label as $row_dep_department_label) {
//                            print_r($row_dep_department_label);
                            $this_privileges_or = explode(',', $row_dep_department_label['privileges_or']);
                            if (in_array("publication_right", $this_privileges_or)) {
                                $user_privileges_access_label[] = $row_dep_department_label;
                            }
                        }
                    }
                }
                $final_dep_access_data = array();
                foreach ($user_privileges_access_label as $row_user_privileges_access_label) {
                    $q_dep_id = $row_user_privileges_access_label['dep_id'];
                    $q = "SELECT departments.id,departments.name  FROM departments WHERE departments.id = '$q_dep_id'";
                    $r = MVC_Core::$db->query($q);

                    foreach ($r as $row) {
                        $final_dep_access_data[] = $row;
                    }
                }
            }
//            echo "<pre>";
//            print_r($final_dep_access_data);
            return $final_dep_access_data;
        }


        return true;
    }

    public function get_publication_user_List($org_id, $status, $login_user_id) {
        if ($status == 'not_active') {
            $where = "and  publish_all_linked_department='0' ";
        }
        if ($status == 'active') {
            $where = "and  publish_all_linked_department='1' ";
        }
        $q = "SELECT id,user,name,email,publish_all_linked_department,org_id,publish_all_linked_department_ids,status  FROM users where org_id='$org_id' and ((status!='disabled' and status!='invisible' and status!='admin'  ) or id='$login_user_id') $where";
        $r = MVC_Core::$db->query($q);
        $all = array();
        foreach ($r as $user) {
            $user['is_publish_all_linked_department'] = $user['publish_all_linked_department'] == 0 ? 'No' : 'Yes';
            if ($status == 'active') {
                $depName = '';
                $publish_all_linked_department_ids = json_decode($user['publish_all_linked_department_ids']);
                foreach ($publish_all_linked_department_ids as $ids) {
                    $q2 = QueryFactory::select()
                            ->table(MVC_Core::$db->tab['dep'], 'name')
                            ->where("`id` = ?", $ids)
                            ->limit(1);
                    $depDet = MVC_Core::$db->query($q2);
                    foreach ($depDet as $depDet_row) {
                        $depName .= $depDet_row['name'] . '</br>';
                    }
                }
                $user['dep_name'] = $depName;
            }
            $user['status'] = $user['status'] == 'admin' ? ' (Admin) ' : '';
            if ($user['id'] == $login_user_id) {
                $user['login_as'] = 'No';
            } else {
                $user['login_as'] = 'Yes';
            }

            $all[] = $user;
        }
//        echo "<pre>";
//print_r($all);

        return $all;
    }

    public function get_user_details($user_id) {
         $q = QueryFactory::select()
                            ->table(MVC_Core::$db->tab['usr'], 'id,publish_all_linked_department,cdep_id,name,email,publish_all_linked_department_ids')
                            ->where("`id` = ?", $user_id);
        $r = MVC_Core::$db->query($q);
        $all = array();
        foreach ($r as $user) {
            $all = $user;
        }
        return $all;
    }

    public function get_publish_all_linked_department_ids_List($user_id) {
        $q = "SELECT id , publish_all_linked_department_ids  FROM users where id='$user_id' ";
        $r = MVC_Core::$db->query($q);
        $all = array();
        foreach ($r as $user) {
            $publish_all_linked_department_ids = json_decode($user['publish_all_linked_department_ids']);
            foreach ($publish_all_linked_department_ids as $ids) {
                $q2 = QueryFactory::select()
                        ->table(MVC_Core::$db->tab['dep'], 'name,id')
                        ->where("`id` = ?", $ids)
                        ->limit(1);
                $depDet = MVC_Core::$db->query($q2);
                foreach ($depDet as $depDet_row) {
                    $all[] = $depDet_row;
                }
            }
        }
        return $all;
    }

//     Document view footer change IWI 10-01-2024 start
    public function get_user_custom_details($user_id = null, $fields = '') {
        $q = "SELECT $fields  FROM users where id='$user_id' ";
        $r = MVC_Core::$db->query($q);
        $all = array();
        foreach ($r as $user) {
            $all = $user;
        }
        return $all;
    }

//     Document view footer change IWI 10-01-2024 end

    public function get_publication_read_sign_user_list($note_id) {
        $q = "SELECT ursn.id as ursn_id,us.id as us_id,us.*,ursn.* FROM user_read_sign_note as ursn "
                . " Left join users as us on ursn.user_id=us.id"
                . " where ursn.note_id='$note_id' AND ursn.read_sign='1'  "
                . " AND us.status!='disabled'  AND us.status!='invisible'  AND us.status!='jumper' AND us.status!='eula'  AND us.status!='supervisor'";
//        echo $q;
//        exit();
        $r = MVC_Core::$db->query($q);
        $all = array();
        foreach ($r as $user) {
            $all[] = $user;
        }
        return $all;
    }

    public function get_publication_user_list_by_noteid($note_id) {
        $q = "SELECT id , wv_publish_for_ids  FROM notes where id='$note_id' ";
        $r = MVC_Core::$db->query($q);
        $depUsers = array();
        foreach ($r as $notes) {
            $wv_publish_for_ids = json_decode($notes['wv_publish_for_ids']);
            foreach ($wv_publish_for_ids as $ids) {
                $q2 = QueryFactory::select()
                        ->table(MVC_Core::$db->tab['dep'], 'name,id')
                        ->where("`id` = ?", $ids)
                        ->limit(1);
                $depDet = MVC_Core::$db->query($q2);
                foreach ($depDet as $depDet_row) {
                    $q_getUserCount = QueryFactory::select()
                            ->table(MVC_Core::$db->table('u2d'), "dep_id,user_id")
                            ->table(MVC_Core::$db->table('dep'), array('*'), 'left', '`u2d`.dep_id = `dep`.id')
                            ->table(MVC_Core::$db->table('usr'), array('email', 'name'), 'left', '`u2d`.user_id = `usr`.id')
                            ->where("`u2d`.`dep_id` = ?", $depDet_row['id'])
                            ->where("`usr`.`status` != ?", 'disabled');
//echo $q_getUserCount;exit();
                    $r_getUserCount = MVC_Core::$db->query($q_getUserCount);
                    foreach ($r_getUserCount as $data_row) {
                        $depUsers[] = $data_row;
                    }
                }
            }
        }

        return $depUsers;
    }
    
       
//    IWI show total record count based on user�s active department start
    
    public function getAllDepRecordCount($user_id, $org_Id, $status, $issueTypes) {
        $usr = MVC_Core::$ss->user;
        $issueTypeIds = array();
        foreach ($issueTypes as $key => $value) {
            $issueTypeIds[] = $key;
        }
        
        //print_r($_SESSION);
//        if ($status != 'admin') {
            $q = QueryFactory::select()
                    ->table(MVC_Core::$db->table('iss'), array('issue_count' => "COUNT(*)"))
                    ->table(MVC_Core::$db->table('iod'), array(), "right", "`iss`.id = `iod`.issue_id  AND `iod`.org_id =" . $org_Id . " AND `iod`.is_enabled = 1")
                    ->table(MVC_Core::$db->table('ist'), array('claim_count_type' => "name", 'id' => "id"), "RIGHT", "`iss`.type_id = `ist`.id")
                    ->where("`iss`.master_org_id = ? OR FIND_IN_SET(?, `iss`.shared_org_ids)", $org_Id, $org_Id)
                    ->where("`iod`.process NOT IN('closed', 'hidden')")
                    ->where("(`iss`.`type_id` in (?))", $issueTypeIds)
                    ->group("`iss`.type_id")
                    ->order("`iss`.id DESC");
           if($status != 'admin'){
//            if (!$this->test_ACL('privileges', 'issues_view_orgowned')) {
                // Group based restrictions, only show issues in the groups this user has access to
                $restriction_filters[] = $q->quote_Into("`iss`.`group_id` IN(?)", array_keys($usr['acls']['groups']));

                // Assignment based restricitons:
                $unassgn = $this->test_ACL('privileges', 'issues_view_unassgn');
                $otherassgn = $this->test_ACL('privileges', 'issues_view_assgn_oth');

                if ($unassgn == true && $otherassgn == true) {
                    // see all, nothing to limit (this is just a placeholder case)
                } elseif ($unassgn == true && $otherassgn == false) {
                    // assigned to self and unassigned
                    $restriction_filters[] = $q->quote_Into('(`iod`.assigned_to = ? OR `iod`.assigned_to IS NULL)', $usr['id']);
                } elseif ($unassgn == false && $otherassgn == true) {
                    // assigned to self and to others
                    $restriction_filters[] = $q->quote_Into('(`iod`.assigned_to = ? OR `iod`.assigned_to IS NOT NULL)', $usr['id']);
                } elseif ($unassgn == false && $otherassgn == false) {
                    // only assigned to self 
                    $restriction_filters[] = $q->quote_Into('`iod`.assigned_to = ?', $usr['id']);
                }

                // Department-level filter - User's active department must be in the list of "dep_ids" in the issue (unless the user has access to all)
//                if (!$this->test_ACL('privileges', 'issue_access_deps'))
                    if (!empty($usr['cdep_id'])) {
                        $restriction_filters[] = $q->quote_Into('FIND_IN_SET(?, `iod`.`dep_ids`)', $usr['cdep_id']);
                    }
                $permission_filters = implode(" AND ", $restriction_filters);
                $q->where($permission_filters);
                //echo $q;
//            }
           }else if($status == 'admin'){
                        $restriction_filters[] = $q->quote_Into('FIND_IN_SET(?, `iod`.`dep_ids`)', $usr['cdep_id']);
                $permission_filters = implode(" AND ", $restriction_filters);
                $q->where($permission_filters);
           }
            
//        } else if ($status == 'admin') {
//            $q = QueryFactory::select()
//                    ->table("issue_count_s_admin_aggr_check", array('issue_count' => "count", 'id' => "type_id", 'claim_count_type' => "claim_count_type")); //array("count","claim_count_type","type_id"));//,QueryFactory::autoalias_Add_Prefix("count,claim_count_type,type_id", '_sCou_'));echo json_encode($q);
//        }
//         echo $q;
        $r = MVC_Core::$db->query($q);
        //print_r($r);

        if (!$r || count($r) == 0)
            return false;
        else {
            return $r;
        }
    }
    
//    IWI show total record count based on user�s active department end
    
        
//    IWI Create a log for read and sign reminder mail send start 
     public function submit_read_sign_mail_log($data) {
        $q = QueryFactory::insert()
                ->table('user_read_sign_reminder_log')
                ->values($data);
        MVC_Core::$db->query($q);
    }
     public function getNoteDetails($note_id) {
        $q =  QueryFactory::select()
                   ->table(MVC_Core::$db->tab['nte'],array('title','id'))
                ->table(MVC_Core::$db->tab['iss'],array('track'), "LEFT", "`issues`.id = `notes`.link_id")
                ->where("`notes`.id = ?", $note_id);
        $r = MVC_Core::$db->query($q);
        if (!$r || count($r) == 0)
            return MVC_Core::$debug->dump("Note/Document could not be located for ID: {$id}", __METHOD__, 'warning');

        $note = $r->current();
        return $note;
    }
    
//    IWI Create a log for read and sign reminder mail send end 
    
    
//    IWI send bulk reminder mail start
    
     public function get_publication_user_list_for_reminder_mail_by_noteid($note_id) {
        $q = "SELECT id , wv_publish_for_ids  FROM notes where id='$note_id' ";
        $r = MVC_Core::$db->query($q);
        $depUsers = array();
        foreach ($r as $notes) {
            $wv_publish_for_ids = json_decode($notes['wv_publish_for_ids']);
            foreach ($wv_publish_for_ids as $ids) {
                $q2 = QueryFactory::select()
                        ->table(MVC_Core::$db->tab['dep'], 'name,id')
                        ->where("`id` = ?", $ids)
                        ->limit(1);
                $depDet = MVC_Core::$db->query($q2);
                foreach ($depDet as $depDet_row) {
                    $q_getUserCount = QueryFactory::select()
                            ->table(MVC_Core::$db->table('u2d'), "dep_id,user_id")
//                            ->table(MVC_Core::$db->table('dep'), array('*'), 'left', '`u2d`.dep_id = `dep`.id')
                            ->table(MVC_Core::$db->table('usr'), array('name','email'), 'left', '`u2d`.user_id = `usr`.id')
                            ->where("`u2d`.`dep_id` = ?", $depDet_row['id'])
                            ->where("`usr`.`status` != ?", 'disabled');
//echo $q_getUserCount;exit();
                    $r_getUserCount = MVC_Core::$db->query($q_getUserCount);
                    foreach ($r_getUserCount as $data_row) {
                        $depUsers[] = $data_row;
                    }
                }
            }
        }

        return $depUsers;
    }
    
//    IWI send bulk reminder mail end
    
        
    //IWI Create a log for read and sign reminder mail send start
     public function get_publication_read_sign_log_list($note_id) {
        $q = QueryFactory::select()
                ->table(array(MVC_Core::$db->tab['ursrl'], 'ursrl'), ' COUNT(ursrl.user_id) as count_user,user_id,reminder_type,doc_id, MAX(date(ursrl.created_at)) as date ')
                ->table(array(MVC_Core::$db->tab['usr'], 'usr'), 'name ', 'left', ' usr.id=ursrl.user_id')
                ->where("`ursrl`.doc_id = ?", $note_id)
                ->where("ursrl.sending_report = ?", '1')
//                ->group("ursrl.user_id,`ursrl`.`reminder_type`")
                ->group("ursrl.user_id")
                ->order("ursrl.created_at DESC");
//        echo $q;

        $r = MVC_Core::$db->query($q);
        $all = array();
        $i=1;
        foreach ($r as $user) {
            $user['sl_no'] =  $i;
            $all[] = $user;
            $i++;
        }
        return $all;
    }

    //IWI Create a log for read and sign reminder mail send end
    
    
    
//    IWI task status change based on user status change  start
    
    public function setAllTaskFailedByUser($user_id) {
        $q_task = QueryFactory::update()
                ->table(MVC_Core::$db->tab['tsk'], 'id')
                ->where("`assign_to` = ?", $user_id)
                ->where('status != ', 'completed')
                ->or_group_start() //this will start grouping
                ->where('status != ', 'failed')
                ->where('status != ', 'expired')
                ->where('status != ', 'closed')
                ->group_end()
                ->values(array(
                            'status' => 'failed'
                        ));
        $r_task = MVC_Core::$db->query($q_task);
    }

//        IWI task status change based on user status change  end
                
    
    
    
//     IWI all doc list needs to read & sign start
    
    
//    public function getAllSignedDoc($user_id) {
//        $q = QueryFactory::select()
//                ->table(array('user_read_sign_note', 'ursn'), '*')
//                ->where("`ursn`.user_id = ?", $user_id);
//        $r = MVC_Core::$db->query($q);
//        $signedNoteId=array();
//        foreach ($r as $data){
//            $signedNoteId[]=$data['note_id'];
//        }
//        return $signedNoteId;
//    }
//    public function getAllPendingReadSign($user_id, $dep_id,$getAllSignedDoc) {
//        $process_not_in=array('pending', 'webapi', 'shared', 'closed', 'hidden');
//        $q = QueryFactory::select()
//                ->table(array(MVC_Core::$db->tab['nte'], 'nte'), 'id,wv_key,title')
//                ->table(array(MVC_Core::$db->tab['iod'], 'iod'), 'process', 'Left', 'iod.issue_id = nte.link_id')
//                ->table(array(MVC_Core::$db->tab['iss'], 'iss'), array('track'), 'Left', 'iss.id = nte.link_id')
//                ->table(array(MVC_Core::$db->tab['usr'], 'usr'), array('published_id'=>'id','published_name'=>'name'), 'Left', 'usr.id = nte.published_by')
//                ->where("`nte`.wv_publish = ?", '1')
//                ->where("`nte`.wv_publish_for = ?", 'department')
//                ->where("`nte`.visibility = ?", 'org')
//                ->where("`nte`.id NOT IN (?)", $getAllSignedDoc)
//                ->where('`nte`.wv_publish_for_ids  like "%' . $dep_id . '%"')
//                ->where("`iod`.process NOT IN (?)", $process_not_in)
//                ->order("nte.update_dt DESC");
////        echo $q;
//        $r = MVC_Core::$db->query($q);
//        return $r;
//    }

//     IWI all doc list needs to read & sign end
    
    
    
    
    
    
    
    
//    IWI Create an icon for archived published doc start
    
    public function getArchiveDocsByOrgId($user_id, $org_id, $search = array()) {


        $q = QueryFactory::select()
                ->table(array(MVC_Core::$db->tab['nte'], 'nte'), 'id,wv_key,wv_publish,link_id,visibility,edit_access,editor_id,org_id,dep_id,dt,title,version,wv_publish_for_ids,wv_publish_for,description,created_by,published_by,is_required_read_sign,update_dt')
                ->table(array(MVC_Core::$db->tab['iss'], 'iss'), 'track,type_id,cache_primary,group_id', 'LEFT', 'iss.id=nte.link_id')
                ->table(array(MVC_Core::$db->tab['grp'], 'grp'), array('group_name' => 'name'), 'LEFT', 'grp.id=iss.group_id')
                ->table(array(MVC_Core::$db->tab['usr'], 'usr'), array('published_by_name' => 'name'), 'LEFT', 'usr.id=nte.published_by');
        $q->where("`nte`.`wv_publish` = ?", '1');
        $q->where("`nte`.`visibility` != ?", 'private');
        $q->where("`nte`.`wv_publish_for` != ?", 'private');
        $q->where("`nte`.`org_id` = ?", $org_id);
        $q->order('`nte`.`id` DESC');
//echo $q;

        $r = MVC_Core::$db->query($q);

        $finalData = array();
        if ($r && count($r) > 0) {

            foreach ($r as $row) {

                $noteDepArr = json_decode($row['wv_publish_for_ids']);
                $matchingValues = array_intersect($search['publish_all_linked_department_ids'], $noteDepArr);
                 if (!empty($matchingValues)) {
                $noteDepCount = 0;
                if (!empty($noteDepArr)) {
                    $noteDepTemp = array();
                    foreach ($noteDepArr as $dp) {
                        $q_dep = QueryFactory::select()->table(MVC_Core::$db->tab['dep'])->where("`id` = ?", $dp)->limit(1);
                        $r_dep = MVC_Core::$db->query($q_dep);
                        $result_dep = $r_dep->current();
                        $noteDepTemp[] = $result_dep['name'];
                    }
                    $noteDep = implode(", ", array_filter($noteDepTemp));
                    $noteDepCount = count(array_filter($noteDepTemp));
                } else {
                    $noteDep = '---';
                }



                $depUsers = intval(0);
                if (!empty($noteDepArr)) {
                    $depUsers_count = intval(0);
                    foreach ($noteDepArr as $dp) {
                        if ($dp != 'all') {
                            $q_getUserCount = QueryFactory::select()
                                    ->table(MVC_Core::$db->table('u2d'), "count(`u2d`.`user_id`) as tot_dep_user_count ")
                                    ->table(MVC_Core::$db->table('usr'), array(), 'left', '`u2d`.user_id = `usr`.id')
                                    ->where("`u2d`.`dep_id` = ?", $dp)
                                    ->where("`usr`.`status` != ?", 'disabled')
                                    ->where("`usr`.`status` != ?", 'invisible')
                                    ->where("`usr`.`status` != ?", 'jumper')
                                    ->where("`usr`.`status` != ?", 'eula')
                                    ->where("`usr`.`status` != ?", 'supervisor');
                            $r_getUserCount = MVC_Core::$db->query($q_getUserCount);

                            $getDepusr = $r_getUserCount->current();
                            $depUsers_count += intval($getDepusr['tot_dep_user_count']);
                            $depUsers = $depUsers_count;
                        }
                        $row['getTotDepUsers'] = $depUsers;
                    }
                }




                $row['nid'] = base64_encode($row['id']);
                $row['ist'] = $row['type_id'];
                $row['pri'] = $row['cache_primary'];
                $row['group_id'] = $row['group_id'];
                $row['group_name'] = $row['group_name'];

                $row['track_id'] = $row['track'];
                $row['department'] = $noteDep;
                $row['department_count'] = $noteDepCount;
                if ($row['update_dt'] != '') {
                    $row['dt'] = date("Y-m-d", strtotime($row['update_dt']));
                } else {
                    $row['dt'] = date("Y-m-d", $row['dt']);
                }


                $getSignInDataSQL = QueryFactory::select()->table('user_read_sign_note', 'count(id) as tot_r_d_user')
                        ->where("`note_id` = ?", $row['id'])
                        ->where("`read_sign` = ?", '1');
                $getSignInDataQuery = MVC_Core::$db->query($getSignInDataSQL);
                $getSignInData = $getSignInDataQuery->current();
                $row['getSignInDataCount'] = $getSignInData['tot_r_d_user'];

                if(intval($row['getSignInDataCount']) >= intval($row['getTotDepUsers'])){
                $finalData[] = $row;
                }
                 }
            }
        }

        return $finalData;
    }

//    IWI Create an icon for archived published doc end
    
    
    
//    IWI all doc list needs to read & sign start
    
    public function getPendingReadSignPublishDocuments($user_id, $pkey, $do_not_decode = false, $variant = 1, $no_org_default = false, $search = array()) {

       $org_id = MVC_Core::$ss->user['org_id'];


        if (empty($user_id) || empty($pkey))
            return false;
        
        
        $q="SELECT `id`, `wv_key`, `wv_publish`, `link_id`, `visibility`, `edit_access`, `editor_id`, `org_id`, `dep_id`, `dt`, `title`, `version`, `wv_publish_for_ids`, `wv_publish_for`, `description`, `created_by`, `published_by`, `is_required_read_sign`, `update_dt` FROM `notes` WHERE `wv_publish` = '1' AND `is_notification` != '1' AND `visibility` != 'private' AND `org_id` = $org_id"; //IWI notification changes 17032026
        if (!empty($search['this_allocate_dep'])) {
              $q .=" AND (  ";
             $a=0;
                foreach ($search['this_allocate_dep'] as $this_allocate_dep){
                $q .=' `wv_publish_for_ids` LIKE "%' . $this_allocate_dep . '%" ';
                $a++;
                if(  $a < count($search['this_allocate_dep'])){
                    $q .=" OR ";
                }
                }
                 $q .=" ) ";
            }
        $q .=" AND `wv_publish_for` = 'department' ORDER BY `id` DESC";

//        echo $q;
        $r = MVC_Core::$db->query($q);

        $finalData = array();
        if ($r && count($r) > 0) {

            foreach ($r as $row) {
                $noteDepArr = json_decode($row['wv_publish_for_ids']);

                $noteDepCount=0;
                if (!empty($noteDepArr)) {
                    $noteDepTemp = array();
                    foreach ($noteDepArr as $dp) {
                        $q_dep = QueryFactory::select()->table(MVC_Core::$db->tab['dep'])->where("`id` = ?", $dp)->limit(1);
                        $r_dep = MVC_Core::$db->query($q_dep);
                        $result_dep = $r_dep->current();
                        $noteDepTemp[] = $result_dep['name'];
                    }
                    $noteDep = implode(", ", array_filter($noteDepTemp));
                    $noteDepCount = count(array_filter($noteDepTemp));
                } else {
                    $noteDep = '---';
                }
                $q2 = QueryFactory::select()
                        ->table(MVC_Core::$db->tab['iss'], 'track,type_id,cache_primary,group_id')
                        ->where("`id` = ?", $row['link_id']);

                $row['nid'] = base64_encode($row['id']);

                $r2 = MVC_Core::$db->query($q2);

                foreach ($r2 as $row2) {
                    $issue_details = $row2;
                    $row['ist'] = $row2['type_id'];
                    $row['pri'] = $row2['cache_primary'];
                    $row['group_id'] = $row2['group_id'];

                    $q3 = QueryFactory::select()
                            ->table(MVC_Core::$db->tab['grp'], '*')
                            ->where("`id` = ?", $row2['group_id']);

                    $r3 = MVC_Core::$db->query($q3);

                    foreach ($r3 as $row3) {
                        $group_details = $row3;
                        $row['group_name'] = $group_details['name'];
                    }
                }


                $row['track_id'] = $issue_details['track'];
                $row['department'] = $noteDep;
                $row['department_count'] = $noteDepCount;
                 if($row['update_dt']!=''){
                $row['dt'] =  date("Y-m-d", strtotime($row['update_dt']));
                }else{
                     $row['dt'] = date("Y-m-d", $row['dt']);
                }


                $finalData[] = $row;
            }
        }

        return $finalData;
    }

//      IWI all doc list needs to read & sign start end  
    
    
    
    
//    IWI popup to view all reminder dates start

       public function getReminderMailLogByUserDocId($user_id, $doc_id) {
            $q = QueryFactory::select()
                    ->table(array(MVC_Core::$db->tab['ursrl'], 'ursrl'), ' *')
                    ->where("`ursrl`.user_id = ?", $user_id)
                    ->where("`ursrl`.doc_id = ?", $doc_id)
                    ->order("ursrl.created_at DESC");
            $r = MVC_Core::$db->query($q);
            return $r;
        }

//    IWI popup to view all reminder dates end
    
     
//    IWI Create an icon for archived published doc start
    
    public function getPublishedDocByOrgDepAccess($user_id, $org_id, $search = array()) {
        $q = QueryFactory::select()
                ->table(array(MVC_Core::$db->tab['nte'], 'nte'), 'id, wv_key, wv_publish, link_id, visibility, edit_access, editor_id, org_id, dep_id, dt, title, version, wv_publish_for_ids, wv_publish_for, description, created_by, published_by, is_required_read_sign, update_dt,ref_number')
                 // add ref_number for IWI ref no for documents 
                ->table(array(MVC_Core::$db->tab['iss'], 'iss'), 'track, type_id, cache_primary, group_id', 'RIGHT', 'iss.id=nte.link_id')
                ->table(array(MVC_Core::$db->tab['grp'], 'grp'), array('group_name' => 'name'), 'LEFT', 'grp.id=iss.group_id')
                ->table(array(MVC_Core::$db->tab['usr'], 'usr'), array('published_by_name' => 'name'), 'LEFT', 'usr.id=nte.published_by');
        $q->where("`nte`.`wv_publish` = ?", '1')
                ->where("`nte`.`visibility` != ?", 'private')
                ->where("`nte`.`wv_publish_for` != ?", 'private')
                ->where("`nte`.`is_notification` = ?", '0') //IWI read and sign required
                ->where("`nte`.`org_id` = ?", $org_id);
        $q->order('`nte`.`id` DESC');
        
        // echo $q;exit;
        $r = MVC_Core::$db->query($q);

        $finalData = array();
        if ($r && count($r) > 0) {
        foreach ($r as $row) {
                $noteDepArr = json_decode($row['wv_publish_for_ids']);
                
                $matchingValues = array_intersect($search['publish_all_linked_department_ids'], $noteDepArr);
                
                 if (((MVC_Core::$ss->user['status'] != 'admin') && (!empty($matchingValues))) || (MVC_Core::$ss->user['status'] == 'admin') )  {
                    $noteDepCount = 0;
                    if (!empty($noteDepArr)) {
                        $noteDepTemp = array();
                        foreach ($noteDepArr as $dp) {
                            $q_dep = QueryFactory::select()->table(MVC_Core::$db->tab['dep'])->where("`id` = ?", $dp)->limit(1);
                            $r_dep = MVC_Core::$db->query($q_dep);
                            $result_dep = $r_dep->current();
                            $noteDepTemp[] = $result_dep['name'];
                        }
                        $noteDep = implode(", ", array_filter($noteDepTemp));
                        $noteDepCount = count(array_filter($noteDepTemp));
                    } else {
                        $noteDep = '---';
                    }
                    $depUsers = intval(0);
                    if (!empty($noteDepArr)) {
                        $depUsers_count = intval(0);
                        foreach ($noteDepArr as $dp) {
                            if ($dp != 'all') {
                                $q_getUserCount = QueryFactory::select()
                                        ->table(MVC_Core::$db->table('u2d'), "count(`u2d`.`user_id`) as tot_dep_user_count ")
                                        ->table(MVC_Core::$db->table('usr'), array(), 'left', '`u2d`.user_id = `usr`.id')
                                        ->where("`u2d`.`dep_id` = ?", $dp)
                                        ->where("`usr`.`status` != ?", 'disabled')
                                        ->where("`usr`.`status` != ?", 'invisible')
                                        ->where("`usr`.`status` != ?", 'jumper')
                                        ->where("`usr`.`status` != ?", 'eula')
                                        ->where("`usr`.`status` != ?", 'supervisor');
                                $r_getUserCount = MVC_Core::$db->query($q_getUserCount);

                                $getDepusr = $r_getUserCount->current();
                                $depUsers_count += intval($getDepusr['tot_dep_user_count']);
                                $depUsers = $depUsers_count;
                            }
                            $row['getTotDepUsers'] = $depUsers;
                        }
                    }
                    $row['nid'] = base64_encode($row['id']);
                    $row['ist'] = $row['type_id'];
                    $row['pri'] = $row['cache_primary'];
                    $row['group_id'] = $row['group_id'];
                    $row['group_name'] = $row['group_name'];

                    $row['track_id'] = $row['track'];
                    $row['department'] = $noteDep;
                    $row['department_count'] = $noteDepCount;
                    if ($row['update_dt'] != '') {
                        $row['dt'] = date("Y-m-d", strtotime($row['update_dt']));
                    } else {
                        $row['dt'] = date("Y-m-d", $row['dt']);
                    }
                    $getSignInDataSQL = QueryFactory::select()->table('user_read_sign_note', 'count(id) as tot_r_d_user')
                            ->where("`note_id` = ?", $row['id'])
                            ->where("`read_sign` = ?", '1');
                    $getSignInDataQuery = MVC_Core::$db->query($getSignInDataSQL);
                    $getSignInData = $getSignInDataQuery->current();
                    $row['getSignInDataCount'] = $getSignInData['tot_r_d_user'];



                   //iwi doctype enhancement start
                   $q_doctype="SELECT value, TRIM( BOTH '\"' FROM TRIM( TRAILING ',' FROM SUBSTRING_INDEX( SUBSTRING_INDEX(issue_xfdef.cd, CONCAT('#', issue_fields.value, ': '), -1), ',', 1 ) ) ) AS full_form FROM issue_fields INNER JOIN issue_xfdef ON issue_xfdef.id = issue_fields.fk_id INNER JOIN issues ON issues.id = issue_fields.issue_id WHERE issue_xfdef.fk = 'doctype' And issues.id='".$row['link_id']."'";
                   $r_doctype= MVC_Core::$db->query($q_doctype);
                   $result_doctype = $r_doctype->current();
                   $row['doc_type_full_name']= isset($result_doctype['full_form']) && $result_doctype['full_form']!=''?$result_doctype['full_form']:'--';
          
          //iwi doctype enhancement end
          


                    
                    if (intval($row['getSignInDataCount']) < intval($row['getTotDepUsers'])) {
                        $finalData[] = $row;
                    }
                }
                
                
                
                
            }
        }

        return $finalData;
    }

//    IWI Get data in published tab as per publisher department access level end
    
    
    
      
    public function getSequrePass($user,$password) {
         $getSequrePass= self::hash_Password($user, $password);
       
         return $getSequrePass;
    }
    
    
//     IWI new dashboard design 17082026 start

    public function saveTodo($id, $saveData) {
        if (empty($id)) {
            $saveData['status'] = 'pending';
            $saveData['is_deleted'] = 0;
            $saveData['created_at'] = date('Y-m-d H:i:s');
            $saveData['updated_at'] = date('Y-m-d H:i:s');
            $q = QueryFactory::insert()
                    ->table('user_todo')
                    ->values($saveData);
            $r = MVC_Core::$db->query($q);

            if ($r) {
                return MVC_Core::$db->insert_id;
            }

            return false;
        } else {
            $saveData['updated_at'] = date('Y-m-d H:i:s');
            $q = QueryFactory::update()
                    ->table('user_todo')
                    ->values($saveData)
                    ->where("`id` = ?", $id)
                    ->limit(1);
            $r = MVC_Core::$db->query($q);
            if ($r) {
                return $id;
            }

            return false;
        }
    }
    public function loadActiveTodoListByUser($user_id) {
        $q = QueryFactory::select()
                ->table('user_todo')
                ->where("`user_id` = ?", $user_id)
                ->where("`is_deleted` = ?", 0)
                ->where("`status` != ?", 'completed')
                ->orderBy("`due_date` ASC, `id` DESC");
        $r = MVC_Core::$db->query($q);
        $data = array();
        foreach ($r as $row) {
            $data[] = $row;
        }
        return $data;
    }
    public function loadTodoListByUser($user_id) {
        $q = QueryFactory::select()
                ->table('user_todo')
                ->where("`user_id` = ?", $user_id)
                ->orderBy("`due_date` ASC, `id` DESC");
        $r = MVC_Core::$db->query($q);
        $data = array();
        foreach ($r as $row) {
            $data[] = $row;
        }
        return $data;
    }

    public function loadTodoDetails($id) {
        $q = QueryFactory::select()
                ->table('user_todo')
                ->where("`id` = ?", $id);
//        echo $q;
        $r = MVC_Core::$db->query($q);
        return $r->current();
    }

    public function deleteTodo($id) {
$q = QueryFactory::delete()
                ->table('user_todo')
                ->where("`id` = ?", $id);

        $r = MVC_Core::$db->query($q);
        if (!$r){
          return false;  
        }else{
            return true;
        }
            
    }

//     IWI new dashboard design 17082026 end
    
    
}
