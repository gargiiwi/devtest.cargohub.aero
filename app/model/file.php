<?php

/**
 * "Files" related data access and input/output processing
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage DataModels
 * @version 2019.10.11
 */
class App_Model_File extends MVC_Model {

    // Allowed values
    private static $allowed_linked_with = array('pre', 'free', 'issue', 'message', 'template', 'task', 'page');
    private static $allowed_visibility = array('private', 'dep', 'org', 'shared', 'public');
    private static $whCFG = null;

    /**
     * MVC_Warehouse object - Handles file upload/download
     * @var MVC_Warehouse
     */
    private static $wh = null;

    /**
     * This is a model that needs the primary database and the warehouse module, so set up to use them
     */
    public function __construct() {
        if (!is_null(self::$wh))
            return;

        self::$whCFG = array(
            'database' => array(
                'table' => MVC_Core::$db->tab['fil']
            ),
            'paths' => array(
                '_default' => array(
                    'dir' => ROOT . DS . 'warehouse_mount', // On live: 'warehouse_mount'
                    'mode' => "contentHash"
                )
            ),
            'analyzers' => array(
                "image" => array(// Configuration for the Image Analyzer (currently empty)
                    // When this is present, compatible detected images will generate scaled down versions of the image as JPGs, placed into the specified paths
                    'scaler' => array(
                        'thms' => array(// Small thumbnails
                            'store_mode' => "chash2", // Places the generated JPGs using first 2 letters from "chash" as the folder name (auto-created if it doesn't exist), and the rest as JPG file name
                            'path' => ROOT . DS . 'htdocs' . DS . 'uimg' . DS . 'thms',
                            'maxpixels' => 80,
                            'quality' => 70
                        ),
                        'thml' => array(// Large thumbnails
                            'store_mode' => "chash2",
                            'path' => ROOT . DS . 'htdocs' . DS . 'uimg' . DS . 'thml',
                            'maxpixels' => 320,
                            'quality' => 75
                        )
                    )//, 'exif'=> true
                )
            )
        );
        self::$wh = new MVC_Warehouse(MVC_Core::$db, self::$whCFG);
    }

    /**
     * Reads the list of files associated to another object or a list of file ID's without any specific association
     * TODO: Review this method and make similar with the way the notes searching function works (multiple methods) - It is used in several places so not changing it now
     *
     * @param string|array $linked_with_or_list
     * @param int $linked_id
     * @param array $wtr Indicates what information to read ( if null or empty, just verify that the file ID actually exists )
     * @param Callback $qfo_filter If no callback specified, it simply reads all the files linked with the specified element
     * @param array $filter_params
     * @param string $order_by
     * @param int $count_rows
     * @param int $start_row
     */
    public function get_List($linked_with_or_list, $linked_id = null, $wtr = array('file'), $qfo_filter = null, $filter_params = array(), $order_by = null, $count_rows = 1000, $start_row = 0) {
        if (!is_array($linked_with_or_list) && empty($linked_id))
            return false; // Error, when specifying to link with something, that something must have an ID

        $q = $this->get_List_QueryFactory_Helper($qfo_filter, $filter_params, is_null($order_by) ? "`fil`.name ASC" : $order_by, $count_rows, $start_row);
        if (is_null($q))
            return false; // Error, probably there was a problem with $qfo_filter

        $wtr = self::wtr_Helper($wtr);

        $cols = array('id'); // if empty, just check if file record exists
        if (in_array('file', $wtr)) {
            $cols = array_merge($cols, array('name', 'ext', 'size', 'mtime', 'mimetype', 'chash'));
            // Also read Meta info ? (Only when base file info is returned as well)
            if (in_array('meta', $wtr))
                $cols = array_merge($cols, array('filepath', 'analyzer', 'metadata'));
        }
        if (in_array('extra', $wtr))
            $cols = array_merge($cols, array('visibility', 'user_id', 'dep_id', 'org_id', 'access_orgs', 'description', 'labels', 'count_reads'));

        $q->table(MVC_Core::$db->table('fil'), $cols);

        if (is_array($linked_with_or_list))
            $q->where("`fil`.id IN(?)", $linked_with_or_list);
        else {
            $q->where("`fil`.linked_with = ?", $linked_with_or_list)
                    ->where("`fil`.linked_id = ?", $linked_id);
        }

        if (in_array('extra', $wtr) && in_array('extra_user', $wtr))
        // Read basic information about the user who uploaded this file into the system ( user_id )
            $q->table(MVC_Core::$db->table('usr'), QueryFactory::autoalias_Add_Prefix("name,contact_id", '_usr_'), 'left', '`fil`.user_id = `usr`.id');

        if (in_array('extra', $wtr) && in_array('extra_organization', $wtr))
            $q->table(MVC_Core::$db->table('org'), QueryFactory::autoalias_Add_Prefix("name,type_id,contact_id", '_org_'), 'left', '`fil`.org_id = `org`.id');

        $r = MVC_Core::$db->query($q);
        if (!$r)
            return false; // Query error

        $r->split_by_prefixes = array(
            // extra tables
            'user' => "_usr_",
            'org' => "_org_"
        );
//		return $r;
//                 foreach ($r as $rr){
//                    $metadata= json_decode($rr['metadata']);
//                    $metadata->width=$metadata->width!=''?$metadata->width:'100';
//                    $metadata->height=$metadata->height!=''?$metadata->height:'100';
//                    $rr['metadata']=json_encode($metadata);
//                    $result[]=$rr;
//                }
//		return $result;


        $result = array();
        foreach ($r as $rr) {
            if (in_array('file', $wtr) && in_array('meta', $wtr)) {
                $metadata = json_decode($rr['metadata'] ?? '');
                if (!is_object($metadata)) {
                    $metadata = new stdClass();
                }
                if (!isset($metadata->width) || $metadata->width === '') {
                    $metadata->width = '100';
                }
                if (!isset($metadata->height) || $metadata->height === '') {
                    $metadata->height = '100';
                }
                $rr['metadata'] = json_encode($metadata);
            }
            $result[] = $rr;
        }

        return $result;
    }

    /**
     * Finds one file by using a callback to set search conditions, or it's primary ID; Returns the file metadata (database entry)
     * @param callback $locator_callback_or_id
     */
    public function find($locator_callback_or_id) {
        $q = QueryFactory::select()
                ->table(MVC_Core::$db->table('fil'))
                ->limit(1);

        if (is_callable($locator_callback_or_id))
            $locator_callback_or_id($q);
        elseif (is_numeric($locator_callback_or_id))
            $q->where('`id` = ?', (int) $locator_callback_or_id);
        else
        // not ID, no callback, fail.
            return false;

        $r = MVC_Core::$db->query($q);
        if (!$r || count($r) == 0)
            return false;

        return $r->current();
    }

    /**
     * Reads and outputs a stored warehouse file (with downloading headers) and also returns it's metadata
     * @param int $id
     * @param array $file_meta Associated file metadata, either specified or returned
     * @return With the specified options, the returned value will either be number of bytes actually read from the file or False if there was an error
     */
    public function read_Content($file_id, &$file_meta = null) {
        return self::$wh->read_File_Content($file_id, $file_meta, array('generate_headers' => true, 'direct_output' => true, 'force_download' => true));
    }

    /**
     * Reads and outputs a stored warehouse file (with downloading headers) and also returns it's metadata
     * @param int $id
     * @param array $file_meta Associated file metadata, either specified or returned
     * @return With the specified options, the returned value will either be number of bytes actually read from the file or False if there was an error
     * Specially used for tinymce editor when file is uploaded in the content
     */
    public function view_Content($file_id, &$file_meta = null) {
        return self::$wh->read_File_Content($file_id, $file_meta, array('generate_headers' => true, 'direct_output' => true, 'force_download' => false));
    }

    /**
     * Makes a calculation on where the file is currently located (the complete path), based on chash + extension
     * The location is placed into $file_meta and also returned
     *
     * @param array $file_meta
     */
    public function get_Real_Path(&$file_meta) {
        // Determine full path ( config root, based on analyzer )
        if (!empty($file_meta['analyzer']) && !empty(self::$whCFG['paths'][$file_meta['analyzer']]))
            $root = self::$whCFG['paths'][$file_meta['analyzer']]['dir'];
        else
            $root = self::$whCFG['paths']['_default']['dir'];
        // Fix filepath for windows and assamble $fullpath
        if (DS == '\\')
            $file_meta['filepath'] = str_replace("/", DS, $file_meta['filepath']);
        $fullpath = $root . DS . $file_meta['filepath'];

        if (!file_exists($fullpath))
            $fullpath = false;

        $file_meta['fullpath'] = $fullpath;
        return $fullpath;
    }

    /**
     * Updates the extra information associated with a file (download name, description, labels, access count) or it's connection (linked_with, linked_id, user_id, dep_id, org_id)
     * @param int|array $file_ids A single file id or an array with file IDs for multi-update | Note: All will be uplaoded with same data - Might affect functionality when updating ext, mimetype !
     * @param array $file_update
     * @param int $link_id If present, verifies that the "link_id" field matches this value, otherwise the file isn't updated (safety check)
     * FIXME: The field is currently named "linked_id"
     */
    public function update_Extra($file_ids, $file_update = array(), $link_id = null) {
        // filter out keys which do not belong in the files table (or can't be updated by this method)
        $fields = array('linked_with', 'linked_id', 'visibility', 'name', 'ext', 'mimetype', 'user_id', 'dep_id', 'org_id', 'access_orgs', 'count_reads', 'description', 'labels');
        foreach ($file_update as $field => $value)
            if (!in_array($field, $fields))
                unset($file_update[$field]);

        if (!empty($file_update['linked_with']) && !in_array($file_update['linked_with'], self::$allowed_linked_with))
            return false; // error, linked_with has an invalid value

        if (!empty($file_update['visibility']) && !in_array($file_update['visibility'], self::$allowed_visibility))
            return false; // error, visibility has an invalid value

        if (!empty($file_update['access_orgs']) && is_array($file_update['access_orgs']))
            $file_update['access_orgs'] = implode(',', $file_update['access_orgs']);
        if (empty($file_update['access_orgs']))
            $file_update['access_orgs'] = null;

        // update files table
        $q = QueryFactory::update()
                ->table(MVC_Core::$db->tab['fil'])
                ->values($file_update);

        if (is_array($file_ids))
            $q->where("`id` IN(?)", $file_ids);
        elseif (is_numeric($file_ids))
            $q->where("`id` = ?", $file_ids);
        else
            return false;

        if (!empty($link_id))
            $q->where("`linked_id` = ?", $link_id);

        return MVC_Core::$db->query($q);
    }

    /**
     * Copies one or more references to files that already exists into a new ones (database only copying)
     * FIXME: This method doesn't increase the file counter for the user/organization, resulting in incorrect decreasing when the files are being removed
     * - Useful for virtual copying of files (E-mail Attachments, Files Clipboard)
     *
     * @param array|int $file_ids List of files that are to be copied
     * @param array $file_update Data to be updated in the copied versions (If empty, complete information is copied)
     * @return boolean|array Returns the file information for the copies or false if error encountered
     */
    public function copy_Ref($file_ids, $file_update = array()) {
        // filter out keys which do not belong in the files table (or can't be updated by this method)
        $fields = array('linked_with', 'linked_id', 'visibility', 'name', 'ext', 'mimetype', 'user_id', 'dep_id', 'org_id', 'access_orgs', 'count_reads', 'description', 'labels');
        foreach ($file_update as $field => $value)
            if (!in_array($field, $fields))
                unset($file_update[$field]);

        if (!empty($file_update['linked_with']) && !in_array($file_update['linked_with'], self::$allowed_linked_with))
            return false; // error, linked_with has an invalid value

        if (!empty($file_update['visibility']) && !in_array($file_update['visibility'], self::$allowed_visibility))
            return false; // error, visibility has an invalid value

        if (!empty($file_update['access_orgs']) && is_array($file_update['access_orgs']))
            $file_update['access_orgs'] = implode(',', $file_update['access_orgs']);
        if (empty($file_update['access_orgs']))
            $file_update['access_orgs'] = null;

        // update files table
        $q = QueryFactory::select()
                ->table(MVC_Core::$db->tab['fil']);

        if (is_array($file_ids))
            $q->where("`id` IN(?)", $file_ids);
        elseif (is_numeric($file_ids))
            $q->where("`id` = ?", $file_ids);
        else
            return false;

        $r = MVC_Core::$db->query($q);
        if (!$r || count($r) == 0)
            return false;

        $file_update['id'] = null;
        $file_update['count_reads'] = 0;

        $copies = array();
        foreach ($r as $file) {
            // Insert copies one by one and grab the ID's
            $q = QueryFactory::insert()
                    ->table(MVC_Core::$db->tab['fil'])
                    ->values($file_update + $file);

            MVC_Core::$db->query($q);
            $copy_file = $file_update + $file;
            $copy_file['original_id'] = $file['id'];
            $copy_file['id'] = MVC_Core::$db->insert_id;
            $copy_file['title'] = !empty($file['description']) ? $file['description'] : preg_replace('/\\.[^.\\s]{2,4}$/', '', $file['name']);
            $copies[] = $copy_file;
        }

        return $copies;
    }

    /**
     * Creates a new warehouse file using the contents of a string.
     * - The size of the file will be the exact length of the string, and obviously limited by process memory size.
     * - This function cannot update or append existing files.
     *
     * @param array $file_meta File metadata to be added to the warehouse entry
     * @param string $contents The contents of the new file
     */
    public function create_From_String($file_meta, $contents) {
        // filter out keys which do not belong in the files table
        $fields = array('linked_with', 'linked_id', 'visibility', 'user_id', 'dep_id', 'org_id', 'access_orgs', 'name', 'ext', 'mimetype', 'description', 'labels');
        foreach ($file_meta as $field => $value)
            if (!in_array($field, $fields))
                unset($file_meta[$field]);

        if (!empty($file_meta['linked_with']) && !in_array($file_meta['linked_with'], self::$allowed_linked_with))
            return false; // error, linked_with has an invalid value

        if (!empty($file_meta['visibility']) && !in_array($file_meta['visibility'], self::$allowed_visibility))
            return false; // error, visibility has an invalid value

        if (!empty($file_meta['access_orgs']) && is_array($file_meta['access_orgs']))
            $file_meta['access_orgs'] = implode(',', $file_meta['access_orgs']);

        self::$wh->config['extra'] = $file_meta;
        self::$wh->config['database']['fields'][] = 'linked_with';
        self::$wh->config['database']['fields'][] = 'linked_id';
        // Visibility fields
        if (isset($file_meta['visibility']))
            self::$wh->config['database']['fields'][] = 'visibility';
        if (isset($file_meta['user_id']))
            self::$wh->config['database']['fields'][] = 'user_id';
        if (isset($file_meta['dep_id']))
            self::$wh->config['database']['fields'][] = 'dep_id';
        if (isset($file_meta['org_id']))
            self::$wh->config['database']['fields'][] = 'org_id';
        if (isset($file_meta['access_orgs']))
            self::$wh->config['database']['fields'][] = 'access_orgs';
        if (isset($file_meta['description']))
            self::$wh->config['database']['fields'][] = 'description';
        if (isset($file_meta['labels']))
            self::$wh->config['database']['fields'][] = 'labels';

        // Creates the temporary file, then uses Warehouse to import it into it's proper location
        $_tname = tempnam('/var/tmp', 'STR'); // On Live: '/var/tmp'
        $_file = fopen($_tname, 'w');
        fwrite($_file, $contents);
        $file = self::$wh->import_File(array(
            'name' => $file_meta['name'],
            'type' => $file_meta['mimetype'],
            'tmp_name' => $_tname,
            'size' => strlen($contents)
                ), true);
        // Delete the original temporary file as it was moved into Warehouse
        unlink($_tname);

        return $file;
    }

    /**
     * Processes all uploads and return their database ID's as an array.
     *
     * @param array $file_meta File metadata to be added to the warehouse entries
     * @param bool $detailed_info Returns actual analysis from WareHouse::process_Uploads - Not just IDs
     * @return array An array of ID's of the successfully uploaded files
     */
    public function process_Uploads($file_meta, $detailed_info = false) {

        // filter out keys which do not belong in the files table
        $fields = array('linked_with', 'linked_id', 'visibility', 'user_id', 'dep_id', 'org_id', 'access_orgs', 'description', 'labels');

//		 echo "531<pre>";
//		 print_r($file_meta);
//		 echo "<hr>";
//
//		 echo "531<pre>";
//		 print_r($_FILES);
//		 echo "<hr>";
//
//		 exit;
        if (!empty($_FILES['files'])) {
            $_FILES['file'] = $_FILES['files'];
        }

        foreach ($file_meta as $field => $value)
            if (!in_array($field, $fields))
                unset($file_meta[$field]);

        if (!empty($file_meta['linked_with']) && !in_array($file_meta['linked_with'], self::$allowed_linked_with))
            return false; // error, linked_with has an invalid value

        if (!empty($file_meta['visibility']) && !in_array($file_meta['visibility'], self::$allowed_visibility))
            return false; // error, visibility has an invalid value

        if (!empty($file_meta['access_orgs']) && is_array($file_meta['access_orgs']))
            $file_meta['access_orgs'] = implode(',', $file_meta['access_orgs']);

        self::$wh->config['extra'] = $file_meta;
        self::$wh->config['database']['fields'][] = 'linked_with';
        self::$wh->config['database']['fields'][] = 'linked_id';
        // Visibility fields
        if (isset($file_meta['visibility']))
            self::$wh->config['database']['fields'][] = 'visibility';
        if (isset($file_meta['user_id']))
            self::$wh->config['database']['fields'][] = 'user_id';
        if (isset($file_meta['dep_id']))
            self::$wh->config['database']['fields'][] = 'dep_id';
        if (isset($file_meta['org_id']))
            self::$wh->config['database']['fields'][] = 'org_id';
        if (isset($file_meta['access_orgs']))
            self::$wh->config['database']['fields'][] = 'access_orgs';
        if (isset($file_meta['description']))
            self::$wh->config['database']['fields'][] = 'description';
        if (isset($file_meta['labels']))
            self::$wh->config['database']['fields'][] = 'labels';

        $uploads = self::$wh->process_Uploads(true);
//                print_r($uploads);exit();
        $ids = array();
        $upload_total = 0;
        foreach ($uploads as $up)
            if (!isset($up['error'])) {
                $ids[] = (int) $up['id'];
                $upload_total += $up['size'];
            }

        if (count($ids) > 0) {
            // Update the file counter for the specified user_id
            if (isset($file_meta['user_id']) && $file_meta['user_id'] > 0) {
                $q = QueryFactory::update()
                        ->table(MVC_Core::$db->tab['usr'])
                        ->values(array(
                            'count_files' => "`count_files`+" . count($ids)
                                ), null, true)
                        ->where("`id` = ?", $file_meta['user_id']);

                MVC_Core::$db->query($q);
//				echo "<br>443 ".$q;
            }

            // Update the file counter and total kb. for the specified org_id
            if (isset($file_meta['org_id']) && $file_meta['org_id'] > 0) {
                $q = QueryFactory::update()
                        ->table(MVC_Core::$db->tab['org'])
                        ->values(array(
                            'count_files' => "`count_files`+" . count($ids),
                            'files_total_kbytes' => "`files_total_kbytes`+" . (int) round($upload_total / 1024)
                                ), null, true)
                        ->where("`id` = ?", $file_meta['org_id']);

                MVC_Core::$db->query($q);
//				echo "<br>457 ".$q;
            }
        }

        if ($detailed_info)
            return $uploads;
        else
            return $ids;
    }

    /**
     * Runs analyzer on existing file (and appends info if necessary)
     * @param array $fileinfo
     */
    public function analyze(&$fileinfo) {
        return self::$wh->analyze_Existing_File($fileinfo, true);
    }

    /**
     * Removes an attachment (actual file and database record)
     * @param int $file_id
     * @param string $link_filter If present, only files which have "linked_with" with this value can be removed. This is a security measure not to allow random files to be deleted by ID, with the wrong link
     *
     * @return array Returns the extra data which was contained in the file record so the object(s) that has this file referenced can be properly updated
     *
     * @TODO Transfer this method directly in the Warehouse module, using the "instances" system, and a file is removed only if it has reached 0 instances (doesn't exist anymore in warehouse as the combination filepath/analyzer)
     */
    public function delete($file_id, $link_filter = 'pre') {
        $file_data = self::$wh->read_File_Data($file_id, false);
        if (is_array($file_data)) {

            if (!empty($link_filter) && $file_data['linked_with'] != $link_filter)
                return false; // Not allow deleting if different link type

                
// Actual file delete DISABLED, because same file might be uploaded by multiple parties and the system doesn't track instances yet...
// 			unlink(??);
            // Update the file counter for the user_id that owned this file
            if ($file_data['user_id'] > 0) {
                $q = QueryFactory::update()
                        ->table(MVC_Core::$db->tab['usr'])
                        ->values(array(
                            'count_files' => "`count_files`-1"
                                ), null, true)
                        ->where("`id` = ?", $file_data['user_id']);

                MVC_Core::$db->query($q);
            }

            // Update the file counter and total storage space for the org_id that owns this file
            if ($file_data['org_id'] > 0) {
                $q = QueryFactory::update()
                        ->table(MVC_Core::$db->tab['org'])
                        ->values(array(
                            'count_files' => "`count_files`-1",
                            'files_total_kbytes' => "`files_total_kbytes`- " . (int) round($file_data['size'] / 1024)
                                ), null, true)
                        ->where("`id` = ?", $file_data['org_id']);

                MVC_Core::$db->query($q);
            }

            // Remove the system data (which is no longer valid) from the file_data then return it
            unset($file_data['id'], $file_data['filepath']);

            // finally, remove the file record itself
            $q = QueryFactory::delete()
                    ->table(MVC_Core::$db->tab['fil'])
                    ->where('`id` = ?', $file_id)
                    ->limit(1);

            MVC_Core::$db->query($q);

            return $file_data;
        } else
            return false; // error, file ID not found
    }

    /**
     * File and Filesize counter for a link element + optionally organization filter (all notes related to a certain element which were created by users of one organization)
     * - Returns the count as a an integer, or 0 if nothing was found.
     *
     * @param string $linked_with
     * @param int $id
     * @param string $wts What kind of ID to count ?
     * @param int $org_id
     * @return int
     */
    public function count($linked_with, $id, $wts = 'linked', $org_id = null) {
        if (!in_array($wts, array("linked", "user", "dep")))
            return false; // Invalid search element

        if (!in_array($linked_with, self::$allowed_linked_with))
            return false; // error, linked_with not specified or invalid value

        $q = QueryFactory::select()
                ->table(MVC_Core::$db->tab['fil'], 'count(*) as `files`, sum(`size`) as `bytes`')
                ->where("`linked_with` = ?", $linked_with)
                ->where("`{$wts}_id` = ?", $id);

        if (!empty($org_id))
            $q->where("`org_id` = ?", $org_id);

        $r = MVC_Core::$db->query($q);
        if (!$r)
            return false; // Could not run the query ?

        $c_row = $r->current();
        $c_row['kbytes'] = (int) round($c_row['bytes'] / 1024);
        return $c_row;
    }

//=== Methods related to the Files Clipboard ("pasting" is done via self::copy_Ref) ===//

    /**
     * Adds a file reference to the clipboard
     * Note: This method does NOT check if the user actually has the right to access this file, that should be done in the calling method
     *
     * @param int $user_id Both IDs are mandatory, as the same file can exist in the clipboard of multiple users
     * @param int $file_id --/--
     */
    public function clipboard_Attach($user_id, $file_id) {
        if (empty($user_id) || empty($file_id))
            return false; // Missing IDs

            
// First, read the file to check if it actually exists
        $q = QueryFactory::select()
                ->table(MVC_Core::$db->table('fil'), array('chash', 'name', 'ext', 'mimetype', 'size'))
                ->where('`id` = ?', (int) $file_id)
                ->limit(1);

        $r = MVC_Core::$db->query($q);
        if (!$r || count($r) == 0)
            return false; // Not found

        $file_meta = $r->current();

        $clipboard = array(
            'user_id' => (int) $user_id,
            'file_id' => (int) $file_id,
            'chash' => $file_meta['chash'],
            'name' => $file_meta['name'],
            'ext' => $file_meta['ext'],
            'mimetype' => $file_meta['mimetype'],
            'size' => $file_meta['size'],
            'added' => date("Y-m-d H:i:s")
        );

        try {
            $q = QueryFactory::insert()
                    ->table(MVC_Core::$db->tab['fcb'])
                    ->values($clipboard);

            return MVC_Core::$db->query($q);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Verifies if there are any files linked in the clipboard - Returns the number of files or 0
     * @param int $user_id
     */
    public function clipboard_Check($user_id) {
        if (empty($user_id))
            return false;

        $q = QueryFactory::select()
                ->table(MVC_Core::$db->tab['fcb'], array('c' => "count(*)"))
                ->where('`user_id` = ?', (int) $user_id);

        $r = MVC_Core::$db->query($q);
        if (!$r || count($r) == 0)
            return 0; // Not found

        $c = $r->current();
        return $c['c'];
    }

    /**
     * Returns the list of files in the clipboard (or empty array if none)
     *
     * @param int $user_id
     */
    public function clipboard_List($user_id) {
        $q = QueryFactory::select()
                ->table(MVC_Core::$db->tab['fcb'])
                ->where('`user_id` = ?', (int) $user_id);

        $r = MVC_Core::$db->query($q);
        if (!$r)
            return false; // Query error

        return $r;
    }

    /**
     * Removes a file reference from the clipboard of a user
     *
     * @param int $user_id Must be non-empty
     * -- Variant 1: User id is positive, it represents an actual user;
     * -- Variant 2: If $user_id is -1, the file is detacted from any and all clipboards (used when a file reference is deleted)
     * @param int|array $file_ids When int, it removes a single entry. When array, it's a list of entries
     */
    public function clipboard_Detach($user_id, $file_ids) {
        if (empty($user_id) || empty($file_ids))
            return false;

        // No extra checks, simply removes the reference from the buffer
        $q = QueryFactory::delete()
                ->table(MVC_Core::$db->tab['fcb']);

        // When -1, user_id is not used in the filter, so deletion occurs for all users which might have this file in the clipboard
        if ($user_id > 0)
            $q->where('`user_id` = ?', (int) $user_id);

        if (is_array($file_ids))
            $q->where('`file_id` IN(?)', $file_ids);
        else
            $q->where('`file_id` = ?', (int) $file_ids);

        return MVC_Core::$db->query($q);
    }

    /**
     * Remvoes all entries from the file clipboard from a user (empties the clipboard)
     *
     * @param int $user_id
     */
    public function clipboard_Empty($user_id) {
        if (empty($user_id))
            return false;

        // No extra checks, simply remove all references related to one user
        $q = QueryFactory::delete()
                ->table(MVC_Core::$db->tab['fcb'])
                ->where('`user_id` = ?', (int) $user_id);

        return MVC_Core::$db->query($q);
    }

    /**
     * Removes all files linked with a certain entity ( fields: linked_with, linked_id )
     * -- Removes the entries from the database and also the files themselves
     * @TODO: Review? Deprecated
     *
     * @param string $linked_with
     * @param int $linked_id
     */
    public function cleanup_Collection($linked_with, $linked_id) {
        try {
            // first, read the list of messages connected to the entity, using the method read_Messages (to find out other data related to the messages)
            $r = $this->get_List($linked_with, $linked_id, array('file', 'extra'), null, null, null, 0); // read all, no limit in size

            if ($r && count($r) > 0) {

                $user_file_count_decrease = array(); // to calculate the reduction of file counts for each user
                $org_file_count_decrease = array(); // to calculate the reduction of file counts for each organization
                $org_storage_decrease = array(); // to calculate the reduction of file counts for each user
                foreach ($r as $file) {
                    // calc for user
                    if (!is_null($file['user_id'])) {
                        if (!isset($user_file_count_decrease[$file['user_id']]))
                            $user_file_count_decrease[$file['user_id']] = 1;
                        else
                            $user_file_count_decrease[$file['user_id']]++;
                    }
                    // calc for organization
                    if (!is_null($file['org_id'])) {
                        if (!isset($org_file_count_decrease[$file['org_id']])) {
                            $org_file_count_decrease[$file['org_id']] = 1;
                            $org_storage_decrease[$file['org_id']] = (int) round($file['size'] / 1024);
                        } else {
                            $org_file_count_decrease[$file['org_id']]++;
                            $org_storage_decrease[$file['org_id']] += (int) round($file['size'] / 1024);
                        }
                    }

                    // also delete the actual file while counting
                    unlink($file['path']);
                }

                // update the users with their respective file count decreases
                foreach ($user_file_count_decrease as $user_id => $count_decrease) {
                    $q = QueryFactory::update()
                            ->table(MVC_Core::$db->tab['usr'], 'count_files')
                            ->values(array("`count_files`" => "`count_files` - {$count_decrease}"), null, true)
                            ->where("`id` = ?", $user_id);

                    MVC_Core::$db->query($q);
                }

                // update the organizations with their respective file count decreases and storage space decrease
                foreach (array_keys($org_file_count_decrease) as $org_id) {
                    $q = QueryFactory::update()
                            ->table(MVC_Core::$db->tab['org'], array('count_files', 'files_total_kbytes'))
                            ->values(array(
                                "`count_files`" => "`count_files` - {$org_file_count_decrease[$org_id]}",
                                "`files_total_kbytes`" => "`files_total_kbytes` - {$org_storage_decrease[$org_id]}"
                                    ), null, true)
                            ->where("`id` = ?", $org_id);

                    MVC_Core::$db->query($q);
                }

                // finally, remove the file records
                $q = QueryFactory::delete()
                        ->table(MVC_Core::$db->tab['fil'])
                        ->where("`linked_with` = ?", $linked_with)
                        ->where("`linked_id` = ?", $linked_id);

                MVC_Core::$db->query($q);
                return true;
            }
            if ($r == false)
                return false; // error?
            else
                return true; // success, but no files were actually deleted
        } catch (Exception $e) {
            return false;
        }
    }

//        IWI doc section file upload start

    public function getAttachFileSource($linked_with_or_list, $linked_id = null, $wtr = array('file'), $qfo_filter = null, $filter_params = array(), $order_by = null, $count_rows = 1000, $start_row = 0) {
        if (!is_array($linked_with_or_list) && empty($linked_id))
            return false; // Error, when specifying to link with something, that something must have an ID

        $q = $this->get_List_QueryFactory_Helper($qfo_filter, $filter_params, is_null($order_by) ? "`fil`.name ASC" : $order_by, $count_rows, $start_row);
        if (is_null($q))
            return false; // Error, probably there was a problem with $qfo_filter

        $wtr = self::wtr_Helper($wtr);

        $cols = array('id'); // if empty, just check if file record exists
        if (in_array('file', $wtr)) {
            $cols = array_merge($cols, array('name', 'ext', 'size', 'mtime', 'mimetype', 'chash'));
            // Also read Meta info ? (Only when base file info is returned as well)
            if (in_array('meta', $wtr))
                $cols = array_merge($cols, array('filepath', 'analyzer', 'metadata'));
        }
        if (in_array('extra', $wtr))
            $cols = array_merge($cols, array('visibility', 'user_id', 'dep_id', 'org_id', 'access_orgs', 'description', 'labels', 'count_reads'));

        $q->table(MVC_Core::$db->table('fil'), $cols);

        if (is_array($linked_with_or_list))
            $q->where("`fil`.id IN(?)", $linked_with_or_list);
        else {
            $q->where("`fil`.linked_with = ?", $linked_with_or_list)
                    ->where("`fil`.linked_id = ?", $linked_id);
        }

//		if(in_array('extra', $wtr) && in_array('extra_user', $wtr))
//			// Read basic information about the user who uploaded this file into the system ( user_id )
//			$q-> table(MVC_Core::$db-> table('usr'), QueryFactory::autoalias_Add_Prefix("name,contact_id", '_usr_'), 'left', '`fil`.user_id = `usr`.id');
//
//		if(in_array('extra', $wtr) && in_array('extra_organization', $wtr))
//			$q-> table(MVC_Core::$db-> table('org'), QueryFactory::autoalias_Add_Prefix("name,type_id,contact_id", '_org_'), 'left', '`fil`.org_id = `org`.id');
//echo $q;exit();
        $r = MVC_Core::$db->query($q);
        if (!$r)
            return false; // Query error

        $r->split_by_prefixes = array(
            // extra tables
            'user' => "_usr_",
            'org' => "_org_"
        );
//		return $r;

        foreach ($r as $rr) {
            $metadata = json_decode($rr['metadata']);
            $metadata->width = $metadata->width != '' ? $metadata->width : '100';
            $metadata->height = $metadata->height != '' ? $metadata->height : '100';
            $rr['metadata'] = json_encode($metadata);
            $result[] = $rr;
        }
        return $result;
    }

//        IWI doc section file upload end
    //file_images show in the task tab _start
    public function get_Task_Files($task_id) {
//    echo $task_id;exit();
        $sql = "SELECT tasks.id, task_files.file_id, filepath, name, files.user_id, linked_id, size, metadata 
        FROM tasks
        JOIN task_files ON task_files.task_id = tasks.id
        JOIN files ON files.id = task_files.file_id
        WHERE task_files.task_id = '$task_id'";
        $q = MVC_Core::$db->query($sql);

        return $q;
    }

    //file_images show in the task tab _end
}
