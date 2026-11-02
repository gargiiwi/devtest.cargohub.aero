            <?php 

/**
 * "Note" model: Ability to attach text notes to various items (primary issues)
 * 
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage DataModels
 * @version 2.7 
 */
class App_Model_Note extends MVC_Model { 

    // Allowed values
    private static $allowed_linked_with = array('issue', 'org', 'dep', 'contact', 'task', 'task', 'group', 'report', 'file', 'custom1', 'custom2', 'custom3', 'custom4', 'custom5');
    private static $allowed_visibility = array('private', 'dep', 'org', 'shared', 'public');
            
    //----- Notes CRUD -----//

    /**
     * Adds an a note/document linked with an item
     * -- Note: The method is a bit rudimentary, it doesn't check if $note_data contains the correct fields !
     *
     * @param string $linked_with One of: issue, org, dep, user, contact, task, group, report, file, custom1, custom2, custom3, custom4, custom5
     * @param int $link_id The item ID for which the note is added
     * @param mixed $data Note/document data (various fields - visibility, edit_access, etc.)
     * @param bool $webview_state TRUE = create a webview key for this note/document ; FALSE = no external access until a key is created
     * @param int $dt_override Override note's Unixtime (not current time, but something else) - Only used when importing external data
     *
     * @return int|bool The insert id or false if create() has failed
     */
    public function create($linked_with, $link_id, $data, $webview_state = false, $webview_secure = false, $webview_publish = false,$wv_publish_for='private', $wv_publish_for_ids='', $dt_override = null) { // IWI Refresh after days
      

        if (empty($linked_with) || !in_array($linked_with, self::$allowed_linked_with))
            return MVC_Core::$debug->dump("Linked_with not specified or invalid value", __METHOD__, 'error');

        // any key outside accepted fields is discarded (to avoid database errors)
        // 19022025 508 document Publish
        $fields = array('wv_publish','visibility', 'edit_access', 'editor_id', 'org_id', 'dep_id', 'title', 'description', 'version', 'content_type', 'text', 'metadata', 'created_by', 'published_by','is_required_read_sign','refresh_after_days','ref_number','is_notification','notification_days'); 
        //is_required_read_sign,refresh_after_days added for --> IWI Refresh after days 
        //ref_number added for --> IWI ref no for documents

        // 'is_notification','notification_days'' added for -> IWI Create section for Notification
        // 'created_by','published_by' added for -> Document view footer change IWI 10-01-2024
        
        foreach ($data as $field => $value)
            if (!in_array($field, $fields))
                unset($data[$field]);

        $data['linked_with'] = $linked_with;
        $data['link_id'] = $link_id;

        // print_r($data);exit();

        if (isset($data['metadata']))
            $data['metadata'] = json_encode($data['metadata']);

        // Some defaults or checks
        if (empty($data['visibility']))
            $data['visibility'] = 'org';
        elseif (!in_array($data['visibility'], self::$allowed_visibility))
            return MVC_Core::$debug->dump("Visibility has an invalid value", __METHOD__, 'error');

        if (empty($data['edit_access']))
            $data['edit_access'] = 'user';
        elseif (!in_array($data['edit_access'], array('user', 'dep', 'org', 'shared')))
            return MVC_Core::$debug->dump("Edit access has an invalid value", __METHOD__, 'error');

        $data['dt'] = !empty($dt_override) ? $dt_override : time();

        if (!isset($data['content_type']) || !in_array($data['content_type'], array('plain', 'html', 'binary')))
            $data['content_type'] = 'plain'; // Compatibility with original notes

        switch ($data['content_type']) {
            case 'plain':
                // Automatic title if not specified (only for plain text notes)
                if (empty($data['title']))
                    $data['title'] = strtok($data['text'], "\n");
                break;
            case 'html':
                if (!isset($data['title']))
                    return MVC_Core::$debug->dump("Document must have a name/title for HTML content", __METHOD__, 'error');
                break;
            case 'binary':
                // TODO: Base64 encode the file or stream that is part of $data
                break;
        }

       
        // Limit length of title/name
        $data['title'] = substr(trim($data['title']), 0, 100);
        // If description doesn't have a length, set it null so it's not stored as empty string
        if (empty($data['description']))
            unset($data['description']);

        // Versioning
        if (empty($data['version']) || !is_numeric($data['version']))
            $data['version'] = 1; // This has no effect on basic notes, but whatever.


            
        // Webview key
        if ($webview_state)
            $data['wv_key'] = sha1(microtime(1) . uniqid()); // Random 40 character access key
        if ($webview_secure) {
            $data['wv_secure'] = 1;
            // 19022025 508 document Publish changes by iwi start
            if($data['wv_publish'] == 'true'){
                $data['wv_publish'] = 1;
            }else{
                $data['wv_publish'] = 0;
            }
            // 19022025 508 document Publish changes by iwi end
            // $data['wv_publish'] = 1;
        }
       
       
        
//        IWI Refresh after days start           
        
//        if (isset($data['is_required_read_sign'])) {
//            $data['is_required_read_sign'] = $data['is_required_read_sign']=='true'?1:0;
//        }        
//        
         if ($data['is_required_read_sign']=='true') {
            $data['is_required_read_sign'] = '1';
        }else{
            $data['is_required_read_sign'] = '0';
        }
        
        
        if (isset($data['refresh_after_days'])) {
            $data['refresh_after_days'] = ($data['is_required_read_sign']==true)?$data['refresh_after_days']:'0';
        }        
        
//        IWI Refresh after days end
        
//        IWI Create section for Notification start
        if ($data['is_notification']=='true') {
            $data['is_notification'] = '1';
            $data['notification_days'] = $data['notification_days'];
        }else{
            
            $data['is_notification'] = '0';
            $data['notification_days'] = '0';
        }
//        IWI Create section for Notification end
        

        $data['wv_publish_for'] = $wv_publish_for;
        $data['wv_publish_for_ids'] = $wv_publish_for != 'private' ? $wv_publish_for_ids : '';

        $q = QueryFactory::insert()
                ->table(MVC_Core::$db->tab['nte'])
                ->values($data);
        // echo         "145 q ".$q;exit;
        $r = MVC_Core::$db->query($q);
        if (!$r)
            return MVC_Core::$debug->dump("Error on creating a note/document", __METHOD__, 'error');

        return MVC_Core::$db->insert_id;
    }

    /**
     * Read an existing document (note) located by it's ID
     *
     * @param int $id
     * @param bool $decode_metadata True = Decode metadata if present
     * @return array
     */
    public function read($id, $decode_metadata = true) {
        if (!is_numeric($id))
            return MVC_Core::$debug->dump("Cannot read a Note/Document without a valid ID", __METHOD__, 'error');

        $q = QueryFactory::select()
                ->table(MVC_Core::$db->tab['nte'])
                ->where("`id` = ?", $id)
                ->limit(1);

        $r = MVC_Core::$db->query($q);
        if (!$r || count($r) == 0)
            return MVC_Core::$debug->dump("Note/Document could not be located for ID: {$id}", __METHOD__, 'warning');

        $note = $r->current();
        if ($decode_metadata && !empty($note['metadata']))
            $note['metadata'] = @json_decode($note['metadata'], true);
        return $note;
    }

    public function readByKey($key, $decode_metadata = true) {

//		if(!is_numeric($key))
//			return MVC_Core::$debug-> dump("Cannot read a Note/Document without a valid ID", __METHOD__, 'error');

        $q = QueryFactory::select()
                ->table(MVC_Core::$db->tab['nte'])
                ->where("`wv_key` = ?", $key)
                ->limit(1);

        $r = MVC_Core::$db->query($q);
        if (!$r || count($r) == 0)
            return MVC_Core::$debug->dump("Note/Document could not be located for ID: {$id}", __METHOD__, 'warning');

        $note = $r->current();
        if ($decode_metadata && !empty($note['metadata']))
            $note['metadata'] = @json_decode($note['metadata'], true);
        return $note;
    }

    /**
     * Updates the note/content data (and also replaces the editor_id with the current user, unless the user is an admin - stealth edit)
     *
     * @param int $id Note/Document ID
     * @param array $data Should contain "editor_id". If not, the <dt> is not updated as well (stealth update)
     * @param int $link_id If present, verifies that the "link_id" field matches this value, otherwise the note isn't updated (safety check)
     * @param bool|string $webview_state Webview key (wv_key) generation or removal
     * - If null (default), the wv_key is not affected in any way
     * - If TRUE, and the note did not have a key before, one is created to allow external access to this note/document content. If one already exists, nothing happens
     * - If FALSE, existing wv_key is removed which will break any existing links (the wv_key cannot be set, only refreshed)
     * - If containing the text "replace", a new wv_key is assigned (still on, but breaks existing links)
     * @param bool $webview_secure TRUE = Set the vw_secure flag to 1; FALSE = set to 0; null = No change
     */
    public function update($id, $data = array(), $link_id = null, $webview_state = null, $webview_secure = null, $webview_publish = null, $wv_publish_for = null, $wv_publish_for_ids = null) { //IWI Refresh after days
        
        // any key outside accepted fields is discarded (to avoid database errors)
        $fields = array('visibility', 'edit_access', 'editor_id', 'org_id', 'dep_id', 'title', 'description', 'version', 'content_type', 'text', 'metadata', 'published_by','is_required_read_sign','refresh_after_days','ref_number','is_notification','notification_days'); 

//               'is_notification','notification_days'' added for -> IWI Create section for Notification
        //               'published_by' added for -> Document view footer change IWI 10-01-2024
        //is_required_read_sign,refresh_after_days added for --> IWI Refresh after days 
        //ref_number added for --> IWI ref no for documents
        
        foreach ($data as $field => $value)
            if (!in_array($field, $fields))
                unset($data[$field]);

        // if (isset($data['editor_id']))
        // $data['dt'] = time(); Documents  create time remove dt 20032025_450

        if (isset($data['metadata']))
            $data['metadata'] = json_encode($data['metadata']);

        // Title size limit
        if (isset($data['title']))
            $data['title'] = substr($data['title'], 0, 100);

        if (!is_null($webview_state)) {
            // Not null, wv_key will be affected
            if ($webview_state === false)
                $data['wv_key'] = null;
            else {
                // Read current note to determine if it already has a wv_key
                $q = QueryFactory::select()
                        ->table(MVC_Core::$db->tab['nte'], array('wv_key'))
                        ->where("`id` = ?", $id)
                        ->limit(1);

                $r = MVC_Core::$db->query($q);
                if (!$r || count($r) == 0)
                    return MVC_Core::$debug->dump("Document could not be located for ID: {$id}", __METHOD__, 'warning');
                $note = $r->current();
                if (empty($note['wv_key']) || $webview_state === 'replace')
                    $data['wv_key'] = sha1(microtime(1) . uniqid()); // Random 40 character access key
            }
        }
        if (!is_null($webview_secure)) {
            $data['wv_secure'] = $webview_secure ? 1 : 0;
//                $data['wv_publish'] = $webview_secure? 1: 0;
        }
        if (!is_null($webview_publish)) {
            $data['wv_publish'] = ($webview_publish == 'true') ? 1 : 0;
        }
        //Document view footer change IWI 10-01-2024 start
        if ($data['wv_publish'] == 0) {
            $data['published_by'] = null;
        }

//                echo $data['wv_publish'];

        if (!is_null($wv_publish_for)) {
            $data['wv_publish_for'] = $wv_publish_for;
        }
        if (!is_null($wv_publish_for_ids)) {
            $data['wv_publish_for_ids'] = $wv_publish_for_ids;
        }
        
//        IWI Refresh after days start           
        
//        if (isset($data['is_required_read_sign'])) {
//            $data['is_required_read_sign'] = $data['is_required_read_sign']=='true'?1:0;
//        }  
        
         if ($data['is_required_read_sign']=='true') {
            $data['is_required_read_sign'] = '1';
        }else{
            $data['is_required_read_sign'] = '0';
        }
        if (isset($data['refresh_after_days'])) {
            $data['refresh_after_days'] = ($data['is_required_read_sign']==true)?$data['refresh_after_days']:'0';
        }        
        
//        IWI Refresh after days end
        
        
//        IWI Create section for Notification start
        if ($data['is_notification']=='true') {
            $data['is_notification'] = '1';
            $data['notification_days'] = $data['notification_days'];
        }else{
            
            $data['is_notification'] = '0';
            $data['notification_days'] = '0';
        }
//        IWI Create section for Notification end
        
        
        
        $data['update_dt'] = date('Y-m-d h:i:s a', time());

        $q = QueryFactory::update()
                ->table(MVC_Core::$db->tab['nte'])
                ->where("`id` = ?", $id)
                ->values($data)
                ->limit(1);
        

        if (!empty($link_id))
            $q->where("`link_id` = ?", $link_id);

        return MVC_Core::$db->query($q);
    }

    /**
     * Deletes an existing note located by it's ID
     *
     * @param int $note_id
     * @param int $link_id If present, verifies that the "link_id" field matches this value, otherwise the note isn't deleted (safety check)
     */
    public function delete($note_id, $link_id = null) {
        $q = QueryFactory::delete()
                ->table(MVC_Core::$db->tab['nte'])
                ->where("`id` = ?", $note_id)
                ->limit(1);

        if (!empty($link_id))
            $q->where("`link_id` = ?", $link_id);

        return MVC_Core::$db->query($q);
    }

    /**
     * Gets the full list of notes for an item ID, user ID or organization ID
     * -- Always sorted by date, descending (newest first)
     *
     * @param string $wts What-To-Search for: ids, link, editor, org, dep
     * -- "ids" - A list of IDs is provided in $search_ids
     * -- "link" - Search by what the notes are linked to ( $linked_with should be non-null )
     * -- "editor", "org", "dep" - Search by one of the other (...)_id fields
     * @param string $linked_with - Type of an element to which the note is linked (issue, org, dep...)
     * @param int $search_ids - ID or IDs of the searched element (if array, any is considered)
     * @param array $wtr What to read ( note data, user that last edited )
     * @param Callback $qfo_filter If no callback specified, it simply reads all the files linked with the specified element
     * @param array $filter_params
     * @param string $order_by
     * @param int $count_rows
     * @param int $start_row
     * @return array
     */
    public function get_List($wts = 'link', $linked_with = null, $search_ids = null, $wtr = array('note'), $qfo_filter = null, $filter_params = array(), $order_by = null, $count_rows = 1000, $start_row = 0) {
        if (!in_array($wts, array("id", "link", "editor", "org", "dep")))
            return false; // Invalid search method

        $q = $this->get_List_QueryFactory_Helper($qfo_filter, $filter_params, is_null($order_by) ? "`nte`.dt DESC" : $order_by, $count_rows, $start_row);
        if (is_null($q))
            return false; // Error, probably there was a problem with $qfo_filter

        if (is_string($search_ids))
            $search_ids = explode(',', $search_ids);

        switch ($wts) {
            case 'ids':
                $q->where("`nte`.id IN(?)", $search_ids);
                break;
            case 'link':
                $q->where("`nte`.linked_with = ?", $linked_with);
            case 'editor':
            case 'org':
            case 'dep':
                if (is_array($search_ids))
                    $q->where("`nte`.{$wts}_id IN(?)", $search_ids);
                else
                    $q->where("`nte`.{$wts}_id = ?", $search_ids);
        }

        $wtr = self::wtr_Helper($wtr);

        $q->table(MVC_Core::$db->table('nte'), in_array('note', $wtr) ? null : (count($wtr) > 1 ? false : 'id'));
        // Note creator/Editor user information
        if (in_array('editor', $wtr))
            $q->table(MVC_Core::$db->table('usr'), QueryFactory::autoalias_Add_Prefix("name,email,contact_id", '_usr_'), 'left', '`nte`.editor_id = `usr`.id');

        // Link the organization owning the note
        if (in_array('org', $wtr))
            $q->table(MVC_Core::$db->table('org'), QueryFactory::autoalias_Add_Prefix("name,type_id,contact_id", '_org_'), 'left', '`nte`.org_id = `org`.id');
        $r = MVC_Core::$db->query($q);
        if (!$r)
            return false; // Query error

        $r->split_by_prefixes = array(
            // extra tables
            'editor' => "_usr_",
            'org' => "_org_"
        );
        return $r;
    }

    /**
     * Note counter for a link element + optionally organization filter (all notes related to a certain element which were created by users of one organization)
     * - Returns the count as a an integer, or 0 if nothing was found.
     *
     * @param string $linked_with
     * @param int $org_id
     * @param int $id ID to search
     * @param string $wts One of: link, editor, dep
     * @return int
     */
    public function count($linked_with, $id, $wts = 'link', $org_id = null) {
        if (!in_array($wts, array("link", "editor", "dep")))
            return false; // Invalid search element

        if (!in_array($linked_with, self::$allowed_linked_with))
            return false; // error, linked_with not specified or invalid value

        $q = QueryFactory::select()
                ->table(MVC_Core::$db->tab['nte'], 'count(*) as `count`')
                ->where("`linked_with` = ?", $linked_with)
                ->where("`{$wts}_id` = ?", $id)
                // Only count plain-text notes (for now)
                ->where("`content_type` = 'plain'");

        if (!empty($org_id))
            $q->where("`org_id` = ?", $org_id);

        $r = MVC_Core::$db->query($q);
        if (!$r)
            return false; // Could not run the query ?

        $c_row = $r->current();
        return (int) $c_row['count'];
    }

    /**
     * Deletes all notes for a linked item, user, organization or department
     * -- Normally used when the element itself is removed
     *
     * @param string $linked_with
     * @param int $id
     * @param string $wts What to search for (link, editor, org, dep)
     */
    public function delete_All($linked_with, $id, $wts = 'link') {
        if (!in_array($wts, array("linked", "editor", "org", "dep")))
            return false; // Invalid search element

        if (!in_array($linked_with, self::$allowed_linked_with))
            return false; // error, linked_with not specified or invalid value

        $q = QueryFactory::delete()
                ->table(MVC_Core::$db->tab['nte'])
                ->where("`linked_with` = ?", $linked_with)
                ->where("`{$wts}_id` = ?", $id);

        return MVC_Core::$db->query($q);
    }

//        IWI doc seen & sign start
    public function getUserReadSignData($user_id, $note_id) {
        $q = QueryFactory::select()
                ->table('user_read_sign_note', 'read_sign,signed_at,is_need_refresh') //IWI Refresh after days
                ->where("`note_id` = ?", $note_id)
                ->where("`user_id` = ?", $user_id);
        $r = MVC_Core::$db->query($q);
        if (!$r)
            return false; // Could not run the query ?

        $c_row = $r->current();
        return $c_row;
    }

    public function readSignLog($user_id, $note_id, $read_sign) {
        $q = QueryFactory::select()
                ->table('user_read_sign_note', 'read_sign')
                ->where("`note_id` = ?", $note_id)
                ->where("`user_id` = ?", $user_id);
        $r = MVC_Core::$db->query($q);
        $c_row = $r->current();
        if (count($c_row) > 0) {
            $data['read_sign'] = $read_sign;
            $data['signed_at'] = date('d-m-Y h:i:s a');
            $q = QueryFactory::update()
                    ->table('user_read_sign_note')
                    ->where("`note_id` = ?", $note_id)
                    ->where("`user_id` = ?", $user_id)
                    ->values($data)
                    ->limit(1);
            $r = MVC_Core::$db->query($q);
            if (!$r)
                return MVC_Core::$debug->dump("Error on creating a note/document", __METHOD__, 'error');

            return true;
        } else {
            $data = array(
                'note_id' => $note_id,
                'user_id' => $user_id,
                'read_sign' => $read_sign,
                'signed_at' => date('d-m-Y h:i:s a'),
            );
            $q = QueryFactory::insert()
                    ->table('user_read_sign_note')
                    ->values($data);
            $r = MVC_Core::$db->query($q);
            if (!$r)
                return MVC_Core::$debug->dump("Error on creating a note/document", __METHOD__, 'error');

            return MVC_Core::$db->insert_id;
        }
    }

    public function deleteReadSignData($note_id) {
        $q = QueryFactory::delete()
                ->table('user_read_sign_note')
                ->where("`note_id` = ?", $note_id)
                ->limit(1);
        return MVC_Core::$db->query($q);
    }

//        IWI doc seen & sign end
    
    
//    IWI identify what was change in version start
     public function insertUpdateLog($log_data) {       
            $q = QueryFactory::insert()
                    ->table('note_update_log')
                    ->values($log_data);
            $r = MVC_Core::$db->query($q);
            return MVC_Core::$db->insert_id;
    }
    
    public function getDocHistoryData($note_id) {       
            $q =  QueryFactory::select()
                ->table('note_update_log', '* , DATE(created_at) as created_at')
                ->where("`note_id` = ?", $note_id)
                    ->order('id','DESC');
//            echo $q;
            $r = MVC_Core::$db->query($q);
            return $r;
    }
    
    
    
    public function getDocHistoryDataByField($note_id,$field) {       
            $q =  QueryFactory::select()
                ->table('note_update_log', 'id,old_val')
                ->where("`note_id` = ?", $note_id)
                ->where("`field_name` = ?", $field)
                    ->order('id','DESC')
                    ->limit(1);
//            echo $q;
            $r = MVC_Core::$db->query($q);
            return $r->current();
    }
    
    
//    IWI identify what was change in version end
    
    
//     IWI Create section for Notification start

    public function getDocumentNotification($searchData) {
       
        $q = QueryFactory::select()->table(array(MVC_Core::$db->tab['nte'],'nte'), 'id,wv_key,wv_publish,link_id,visibility,edit_access,editor_id,org_id,dep_id,dt,title,version,wv_publish_for_ids,wv_publish_for,description,created_by,published_by,is_required_read_sign,update_dt,ref_number,is_notification,notification_days');
        $q->where("`is_notification` = ?", '1');
        $q->where("`wv_publish` = ?", '1');
        $q->where("`visibility` != ?", 'private');
        $q->where("`is_notification_archive` = ?", '0');
        $q->table(array(MVC_Core::$db->tab['iss'],'iss'), array('track','type_id'), "right", "`nte`.`link_id` = `iss`.`id`");
        $q->table(MVC_Core::$db->table('ist'), array('rt_name' => "name",'rt_key' => "key"), "RIGHT", "`iss`.type_id = `ist`.id");//IWI new dashboard design 11082026
        if (MVC_Core::$ss->user['status']!='admin' && !empty($searchData['org_id'])) {
            $q->where("`org_id`= ?", $searchData['org_id']);
        }
        
//         IWI notification changes 17032026 start
//        if (MVC_Core::$ss->user['status']!='admin' && !empty($searchData['dep_id'])) {
//            $q->where('`wv_publish_for_ids`  like "%' . $searchData['dep_id'] . '%" ');
//        }

        if (MVC_Core::$ss->user['status'] != 'admin' && !empty($searchData['publish_all_linked_department_ids'])) {
            $ids = array();
            if (!empty($searchData['publish_all_linked_department_ids']) && is_array($searchData['publish_all_linked_department_ids'])) {
                foreach ($searchData['publish_all_linked_department_ids'] as $row) {
                    if (is_array($row) && isset($row['id'])) {
                        $ids[] = (int) $row['id'];
                    }
                }
            }
            if (!empty($ids)) {
                $conditions = array();
                foreach ($ids as $id) {
                    $conditions[] = "`wv_publish_for_ids` LIKE '%\"" . $id . "\"%'";
                }
                $conditions[] = "`wv_publish_for_ids` LIKE '%\"" . $searchData['dep_id'] . "\"%'";

                $q->where('(' . implode(' OR ', $conditions) . ')');
            }
        }
// IWI notification changes 17032026 end
        // echo $q;exit();
        $r = MVC_Core::$db->query($q);
        
        return $r;
    }

    public function getDocumentNotificationArchieve($searchData) {
       
        $q = QueryFactory::select()->table(array(MVC_Core::$db->tab['nte'],'nte'), 'id,wv_key,wv_publish,link_id,visibility,edit_access,editor_id,org_id,dep_id,dt,title,version,wv_publish_for_ids,wv_publish_for,description,created_by,published_by,is_required_read_sign,update_dt,ref_number,is_notification,notification_days');
        $q->where("`is_notification` = ?", '1');
        $q->where("`wv_publish` = ?", '1');
        $q->where("`visibility` != ?", 'private');
        $q->where("`is_notification_archive` = ?", '1');
        $q->table(array(MVC_Core::$db->tab['iss'],'iss'), array('track'), "right", "`nte`.`link_id` = `iss`.`id`");
        if (MVC_Core::$ss->user['status']!='admin' && !empty($searchData['org_id'])) {
            $q->where("`org_id`= ?", $searchData['org_id']);
        }
       
//        IWI notification changes 17032026 start
//        if (MVC_Core::$ss->user['status']!='admin' && !empty($searchData['dep_id'])) {
//            $q->where('`wv_publish_for_ids`  like "%' . $searchData['dep_id'] . '%" ');
//        }
        if (MVC_Core::$ss->user['status'] != 'admin' && !empty($searchData['publish_all_linked_department_ids'])) {
            $ids = array();
            if (!empty($searchData['publish_all_linked_department_ids']) && is_array($searchData['publish_all_linked_department_ids'])) {
                foreach ($searchData['publish_all_linked_department_ids'] as $row) {
                    if (is_array($row) && isset($row['id'])) {
                        $ids[] = (int) $row['id'];
                    }
                }
            }
            if (!empty($ids)) {
                $conditions = array();
                foreach ($ids as $id) {
                    $conditions[] = "`wv_publish_for_ids` LIKE '%\"" . $id . "\"%'";
                }
                $conditions[] = "`wv_publish_for_ids` LIKE '%\"" . $searchData['dep_id'] . "\"%'";

                $q->where('(' . implode(' OR ', $conditions) . ')');
            }
        }
//        IWI notification changes 17032026 end
    //    echo $q;exit();
        $r = MVC_Core::$db->query($q);
        
        return $r;
    }

// IWI Create section for Notification end 
    
    
//    IWI doc section file upload start
    public function updateAttachFiles($sendAttachedData = array()) {
        $q = QueryFactory::select()->table('note_files');
        $q->where("`note_id` = ?", $sendAttachedData['note_id']);
        $q->limit(1);
        $r = MVC_Core::$db->query($q);
        
        $data = array(
            'org_id' => $sendAttachedData['ord_id'],
            'issue_id' => $sendAttachedData['issue_id'],
            'note_id' => $sendAttachedData['note_id'],
            'file_ids' => json_encode($sendAttachedData['selectedAttachIds']),
        );
        if (!$r || count($r) == 0) {
            $q_file = QueryFactory::insert()
                    ->table('note_files')
                    ->values($data);
            $r_file = MVC_Core::$db->query($q_file);
        } else {
            $prv_data=$r->current();
            $q_file = QueryFactory::update()
                ->table('note_files')
                ->where("`id` = ?", $prv_data['id'])
                ->values($data)
                ->limit(1);
            $r_file = MVC_Core::$db->query($q_file);
        }
        if (!$r || count($r) == 0) {
            return MVC_Core::$debug->dump("Error on creating a note/document", __METHOD__, 'error');
        } else {
            return true;
        }
    }
    public function load_AttachFiles($note_id) {
        $q = QueryFactory::select()->table('note_files');
        $q->where("`note_id` = ?", $note_id)->limit(1);
        $r = MVC_Core::$db->query($q);
        
        
        if (!$r || count($r) == 0) {
            return MVC_Core::$debug->dump("Error on creating a note/document", __METHOD__, 'error');
        } else {
             return $r->current();
        }
    }
    

//    IWI doc section file upload end
    
    
      
}
