<?php

/**
 * Global Notes-related Controller (actions which could be called from any module)
 * - New notes model can be connected to multiple types of elements, so coordinate note operations here
 * - TODO: Rename "Notes" into "Documents" and support both plain-text and html documents
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage Controllers  
 * @version 2.7
 */
class App_Ctrl_Global_Note extends App_Ctrl_Base { 

    /**
     * Contact model, which is necessary for user display/editor to work properly
     * @var App_Model_Note
     */
    private $note;

    /**
     * Initialize the data model
     */
    public function __construct() {
        parent::__construct();
            
        $this->note = $this->factory_Model("Note");
        $this->record = $this->factory_Model("Issue", "rec");
    }

    /**
     * Reads the notes associated to an item, filtering the result by what the logged in user should see
     */
    public function action_Get_List(MVC_Request $rq) {
        if (empty(MVC_Core::$ss->user))
            return header("HTTP/1.0 403 This method is only available while logged in");

        if ($this->user->is_Unrestricted())
        // Superuser reads all linked, including private ones, along with the organization to which note belongs to ( extra column )
            $r = $this->note->get_List('link', $rq->link, $rq->link_id, array('note', 'editor', 'org'), function (QueryFactory &$q) use ($rq) {
                // Optional filters on note parameters
                if ($rq->content_type)
                    $q->where("`content_type` = ?", $rq->content_type);
            }, null, $rq->order ?: "`nte`.dt DESC", 0); // No limit here
        else {
            // Normal user visibility will be filtered by their organization, department and their own private ones.
            $r = $this->note->get_List('link', $rq->link, $rq->link_id, array('note', 'editor'), function (QueryFactory &$q) use ($rq) {
                $user = MVC_Core::$ss->user;
                if (!is_null($user['org_id']))
                    $q->where("CASE WHEN `nte`.visibility = 'org' THEN `nte`.`org_id` = ? ELSE 1 END", $user['org_id']);
                if (!is_null($user['cdep_id']))
                    $q->where("CASE WHEN `nte`.visibility = 'dep' THEN `nte`.`dep_id` = ? ELSE 1 END", $user['cdep_id']);
                if (!is_null($user['id']))
                    $q->where("CASE WHEN `nte`.visibility = 'private' THEN `nte`.`editor_id` = ? ELSE 1 END", $user['id']);

                // Optional filters on note parameters
                if ($rq->content_type)
                    $q->where("`content_type` = ?", $rq->content_type);
            }, null, $rq->order ?: "`nte`.dt DESC", 0); // No limit here
        }

        if (!$r || count($r) == 0)
            return $this->send_JSON(array());

        $notes = array();
        
        
//        IWI access control for publication edit 23032026 start
         $get_user_details= $this->user->get_user_details(MVC_Core::$ss->user['id']);
         $user_previliges=array();
         $check_list_access=false;
        if(MVC_Core::$ss->user['status']!='admin' && $get_user_details['publish_all_linked_department']=='1' ){
            $user_previliges = $this-> user-> get_publish_all_linked_department_ids_List(MVC_Core::$ss->user['id']);
            $check_list_access=true;
        }
        $user_previliges_access_data = array(); 
        foreach ($user_previliges as $row) {
            $user_previliges_access_data[] = trim($row['id']); 
        }
//        IWI access control for publication edit 23032026 end
        foreach ($r as $note) {
            // echo "<pre>";print_r($note);exit;
            // A bit of post-processing
            
            //            IWI access control for publication edit 23032026 start
            if (($check_list_access) && isset($rq->for_sec) && $rq->for_sec == 'doc_tab') {
                $publish_for_ids = json_decode($note['wv_publish_for_ids'], true);
                if (!is_array($publish_for_ids))
                    $publish_for_ids = array();
                $matches = array_intersect($publish_for_ids, $user_previliges_access_data);

                if (empty($matches)) {
                    continue;
                }
            }
//            IWI access control for publication edit 23032026 end
            $note['id'] = (int) $note['id'];
            $note['link_id'] = (int) $note['link_id'];
            $note['dt'] = date("Y-m-d H:i ", $note['dt']);
            if (empty($note['title']))
                $note['title'] = substr(strtok($note['text'], "\n"), 0, 100); // Automatic Title from text if it doesn't exist (first row, up to 100 characters)
            if (!empty($note['dep_id']))
                $note['dep_id'] = (int) $note['dep_id'];

            // Move User (editor) and Organization ID's into their sub-arrays
            if (isset($note['editor'])) {
                $note['editor']['id'] = (int) $note['editor_id'];
                $note['editor']['contact_id'] = (int) $note['editor']['contact_id'];
                unset($note['editor_id']);
            }

            if (isset($note['org'])) {
                $note['org']['id'] = empty($note['org_id']) ? null : (int) $note['org_id'];
                if (!empty($note['org']['contact_id']))
                    $note['org']['contact_id'] = (int) $note['org']['contact_id'];
                unset($note['org_id']);
            }
            $note['last_updated'] = $note['update_dt'] != '' ?  date("Y-m-d H:i ", strtotime($note['update_dt'])) : $note['dt'];
             
            $notes[] = $note;
        }
        $this->send_JSON($notes);
    }

    /**
     * Creates a note and links it to the specified element (Deprecated, used only by notes - not documents)
     */
    public function action_Create(MVC_Request $rq) {
        if (empty(MVC_Core::$ss->user))
            return header("HTTP/1.0 403 This method is only available while logged in");

        if (empty($rq->link) || empty($rq->link_id))
            return header("HTTP/1.0 400 Request did not contain the ID of the linked element");

        $note_data = $rq->note;
        $note_data['org_id'] = isset(MVC_Core::$ss->perspective) ? MVC_Core::$ss->perspective : ($this->user->is_Unrestricted() ? $rq->org_id : MVC_Core::$ss->user['org_id']);
        $note_data['editor_id'] = $this->user->ID(); // The user is creating this note (even if creating it for a different organization)
        if (!$this->user->is_Unrestricted())
            $note_data['dep_id'] = MVC_Core::$ss->user['cdep_id'];

        $webview_state = '';
        $webview_secure = '';
        $webview_publish = '';
        $wv_publish_for = '';
        $wv_publish_for_ids = '';

        $iid = $this->note->create($rq->link, $rq->link_id, $note_data, $webview_state, $webview_secure, $webview_publish, $wv_publish_for, $wv_publish_for_ids);
        if (!$iid)
            return header("HTTP/1.0 500 Database Error: Unable to record the note");

        // (Re)Count number of notes for this element
        $count_notes = $this->note->count($rq->link, $rq->link_id, 'link', $note_data['org_id']);

        // Depending on what is being linked, update counters or statistics field
        if ($rq->link == "issue") {
            $this->record->org_UpdateData($rq->link_id, $note_data['org_id'], array(
                'count_notes' => $count_notes // For now, recount them every time; In the future after a global counters fix is deployed, just increment ( "`count_notes` + 1" )
                    ), true, false); // Don't recheck locator ; String expressions
        }

        $this->send_JSON(array(
            "success" => true,
            "response" => "The note with ID: {$iid} has been recorded.",
            "count" => $count_notes,
            "iid" => $iid
        ));
    }

    /**
     * Reads the body of a rich-text document (for now, note text).
     * Returns the content of the body as the output stream, however as part of the headers, certain elements from the document are being sent as well (json encoded)
     *
     * @param MVC_Request $rq
     */
    public function action_Load_Body(MVC_Request $rq) {
        if (empty($rq->id))
            return header("HTTP/1.0 400 Request did not contain the document ID");

        $doc = $this->note->read($rq->id, false); // False = Do not decode metadata json, it will be streamed directly
        if (!$doc)
            return header("HTTP/1.0 404 Document was not found or another database error occurred");

        // Send the document metadata if it exists in the header
        if (!empty($doc['metadata']))
            $this->send_JSON_Headers($doc['metadata'], 'metadata', true);

        // Do some processing based on asked content tpye
        switch ($rq->content_type) {
            case 'plain':
                switch ($doc['content_type']) {
                    case 'html':
                        // Convert HTML back into plain text - This is a lossy operation !
                        $html2text = new html2text();
                        $doc['text'] = $html2text->convert_html_to_text($doc['text']);
                        break;
                    case 'binary':
                        // Binary cannot be converted to text, throw an error
                        return header("HTTP/1.0 500 Unable to convert binary documents into plaintext !");
                }
                break;
            case 'html':
                switch ($doc['content_type']) {
                    // Convert plain text note into HTML, by normalizing newline and then doing nl2br
                    case 'plain':
                        // Begin by stripping any existing HTML tags except for a few basic formatting and non-dangerous content
                        $doc['text'] = strip_tags($doc['text'], "<b><i><em><strong><code><samp><mark><cite><dfn><kbd><var><a><img>");

                        // HTML special chars & => &amp; " => &quot;
                        $doc['text'] = str_replace(array("&", "\""), array("&amp;", "&quot;"), $doc['text']);

                        // Convert E-mail addresses into clickable links
                        $doc['text'] = preg_replace('/(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,6}))/', '<a href="mailto:$0">$0</a>', $doc['text']);

                        // Recognize links in the text and convert them to actual links
                        // TODO: It should detect image URL's (stuff ending with .jpg, .png, .gif and convert into an actual embedded image instead of linking to it)
// 						$doc['text'] = preg_replace('@(http(s)?)?(://)?(([a-zA-Z])([-\w]+\.)+([^\s\.]+[^\s]*)+[^,.\s])@', '<a href="http$2://$4" target="_blank" title="$0">$0</a>', $doc['text']); // Too broad, it messes up e-mails
                        $doc['text'] = preg_replace('@(https?://([-\w\.]+)+(:\d+)?(/([\w/_\.]*(\?\S+)?)?)?)@', '<a href="$1">$1</a>', $doc['text']);

                        $doc['text'] = str_replace("\r\n", "\n", $doc['text']);
                        $doc['text'] = str_replace("\r", "\n", $doc['text']);

                        // Line Blocks detection
                        // -- Detect series of linex in the format  Label: content and convert into dual-column table <th><td>
                        // -- Detect lines in the format * Some text -> Convert into <ul><li>
                        // -- Detect lines which start with consecutive numbers followed by a dash, dot, colon 1. 2- 3: -> convert into <ol><li>
                        // -- Detect attempts to form a newline ( -_=~ ) repeated at least 3 times (same character). Replace with <hr />
                        // -- Nothing special ? Series of lines which are followed by an empty line form a paragraph <p>, cutting the trailing \n (Detect paragraph)
                        $lines = explode("\n", $doc['text']);
                        $constructed_html = "";
                        $detected_block_type = null;
                        $block_start = null;
                        $block_data = null;
                        $block_detect_meta = null;
                        $matches = null;
                        $block_end_function = function () use (&$constructed_html, &$detected_block_type, &$block_start, &$block_data) {
                            if (!empty($detected_block_type)) {
                                switch ($detected_block_type) {
                                    // Make a TABLE out of a definition list
                                    case 'definition':
                                        // Should have at least 2 lines - If not, treat as paragraph with one line
                                        if (count($block_data) == 1) {
                                            $constructed_html .= "<p><i>{$block_data[0]['label']}</i> {$block_data[0]['content']}</p>\n";
                                            break;
                                        }

                                        $constructed_html .= "<table style=\"border: 1px solid gray; border-spacing: 2px;\">\n";
                                        foreach ($block_data as $block_line)
                                            $constructed_html .= "	<tr><th style='font-weight: bold;'>{$block_line['label']}</th><td>{$block_line['content']}</td></tr>\n";
                                        $constructed_html .= "</table>\n";
                                        break;

                                    // Detected something which looks like an unordered list - Convert into actul <ul>
                                    case 'unordered':
                                        $constructed_html .= "<ul>\n";
                                        foreach ($block_data as $block_line)
                                            $constructed_html .= "	<li>{$block_line}</li>\n";
                                        $constructed_html .= "</ul>\n";
                                        break;

                                    case 'ordered':
                                        // Should have at least 2 lines - If not, treat as standard paragraph with one line
                                        if (count($block_data) == 1) {
                                            $constructed_html .= "<p>{$block_data[0]}</p>\n";
                                            break;
                                        }

                                        $constructed_html .= "<ol>\n";
                                        foreach ($block_data as $block_line)
                                            $constructed_html .= "	<li>{$block_line}</li>\n";
                                        $constructed_html .= "</ol>\n";
                                        break;

                                    // Nothing special, just plain lines forming a paragraph
                                    case 'paragraph':
                                        $constructed_html .= "<p>\n" . implode("<br />", $block_data) . "</p>\n";
                                        break;
                                }
                            }
                            $detected_block_type = null;
                            $block_start = null;
                        };
                        foreach ($lines as $line_no => $line) {
                            $line = trim($line);
                            // Detect possible definition list in the format - Label: Content
                            if (preg_match("/([^:]+:) (.*)/", $line, $matches)) {
                                if (strlen($matches[1]) < 25) {
                                    // We're in the middle of another block. End it before starting definitions
                                    if (!empty($detected_block_type) && $detected_block_type != 'definition')
                                        $block_end_function();

                                    if (empty($detected_block_type)) {
                                        $detected_block_type = 'definition';
                                        $block_start = $line_no;
                                        $block_data = array();
                                    }

                                    $block_data[] = array('label' => trim($matches[1]), 'content' => $matches[2]);
                                    continue;
                                }
                            }

                            // TODO: Detect lists within lists (by using a different character) - More complicated. For now, any of the supported characters are accepted
                            // Detect possible <ul> list
                            if (preg_match("/^([*o+#-=]) (.*)/", $line, $matches)) {
                                // We're in the middle of another block. End it before starting definitions
                                if (!empty($detected_block_type) && $detected_block_type != 'unordered')
                                    $block_end_function();

                                if (empty($detected_block_type)) {
                                    $detected_block_type = 'unordered';
                                    $block_start = $line_no;
                                    $block_data = array();
                                }

                                $block_data[] = $matches[2];
                                continue;
                            }

                            // Detect possible <li> list
                            if (preg_match("/^([0-9]+)[.:=#_] ?(.*)/", $line, $matches)) {
                                // We're in the middle of another block. End it before starting definitions
                                if (!empty($detected_block_type) && $detected_block_type != 'ordered')
                                    $block_end_function();

                                if (empty($detected_block_type)) {
                                    $detected_block_type = 'ordered';
                                    $block_start = $line_no;
                                    $block_data = array();
                                }

                                $block_data[] = $matches[2];
                                continue;
                            }

                            // Detect possible separator line (===, ---, ___, ~~~). Replace with <hr />
                            if (preg_match("/^[-=_~]{3,}/", $line, $matches)) {
                                // We're in the middle of another block. End it before adding the horizontal line
                                if (!empty($detected_block_type) && $detected_block_type != 'ordered')
                                    $block_end_function();
                                $constructed_html .= "<hr />\n";
                                continue;
                            }

                            // Empty line should indicate end of block
                            if (strlen($line) == 0) {
                                $block_end_function();
                                continue;
                            }

                            // Nothing special ? Form a paragraph of all the lines until the next empty one
                            if (empty($detected_block_type)) {
                                $detected_block_type = 'paragraph';
                                $block_start = $line_no;
                                $block_data = array();
                            }
                            $block_data[] = $line;
                            continue;
                        }
                        // Reached the end and still have a block open?
                        if (!empty($detected_block_type))
                            $block_end_function();

                        $doc['text'] = $constructed_html;
                        break;

                    case 'binary':
                        // Binary cannot be converted to html, throw an error
                        return header("HTTP/1.0 500 Unable to convert binary documents into HTML !");
                }
                break;
            default:
            // Unknown/Not present/Not supported - Do nothing
        }

        echo $doc['text'];
    }

    /**
     * Views a note based on it's ID (encoded with qp_conv)
     *
     * @param MVC_Request $rq
     */
    public function action_View(MVC_Request $rq) {
        $id = @MVC_Core::qp_conv($rq->qp);

        // Read document
        $doc = $this->note->read($id);
        if (!$doc) {
            $this->evan->add("Requested document was not found or you followed an incorrect link", "error");
            MVC_Core::shutdown('/kb?page=404');
        }

        if ($doc['wv_key'] != $rq->key) {
            $this->evan->add("Invalid access key for the requested document", "error");
            MVC_Core::shutdown('/kb?page=403');
        }

        $this->title = $doc['title'];
        if (!empty($doc['description']))
            $this->description = $doc['description'];
        $this->text = $doc['text'];

        // Prepare for output
        $this->view("document_view_container.html");
    }

    /**
     * Updates a note data linked with an item (Deprecated, used only by notes - not documents)
     *
     * @param MVC_Request $rq
     */
    public function action_Update(MVC_Request $rq) {
        if (empty(MVC_Core::$ss->user))
            return header("HTTP/1.0 403 This method is only available while logged in");

        if (empty($rq->id))
            return header("HTTP/1.0 400 Note identifier was found in the request");

        $r = $this->note->update($rq->id, $rq->note, $rq->link_id);
        if (!$r)
            return header("HTTP/1.0 500 Error - Could not update the note");

        $org_id = isset(MVC_Core::$ss->perspective) ? MVC_Core::$ss->perspective : ($this->user->is_Unrestricted() ? $rq->org_id : MVC_Core::$ss->user['org_id']);
        $this->record->org_UpdateData($rq->link_id, $org_id, array(
            'utime' => date("Y-m-d H:i:s")
        ));

        $this->send_JSON(array("success" => true, "response" => "The note with id {$rq->id} has been updated."));
    }

    /**
     * Combined Create/Update method for notes, documents or anything stored into "notes" table
     * -- (should really rename everything to "documents" at some point - Even a simple note is a document, just "plain text")
     * @param MVC_Request $rq
     */
    public function action_Save(MVC_Request $rq) {
        if (empty(MVC_Core::$ss->user))
            return header("HTTP/1.0 403 This method is only available while logged in");

        // Extra checks depend on content_type
        switch ($rq->doc['content_type']) {
            // Recording a basic text note
            case 'plain':
                break;
            // Recording a html document (editable by TinyMCE)
            case 'html':
                if (empty($rq->doc['title']))
                    return header("HTTP/1.0 400 Cannot record a document without a valid name");

                break;
            // Recording a database-stored file (encoded in Base64) - Used to store pictures or other files
            case 'binary':
                break;
        }

        $rq->add_Filter('version_update', "Boolean");
        $rq->add_Filter('webview_state', "Boolean");
        $rq->add_Filter('webview_secure', "Boolean");
      
       
        
         
         $curr_note_id=''; //IWI Create a button to bulk mail send

        if ($rq->id) {
            
                //IWI if update any doc without any change, then no update icon will show start
                $getDocDetails = $this->note->read($rq->id);
                $oldDocData = array();
                foreach ($rq->doc as $k => $vl) {
                    $oldDocData[$k] = $getDocDetails[$k];
                }
                $getPostData=$rq->doc;
                
              
                                
                //$getPostData['wv_secure']=$getPostData['wv_secure']==true?'1':'0';
                $getPostData['wv_publish']=$getPostData['wv_publish']==true?'1':'0';
                $getDiff = array_diff_assoc($getPostData, $oldDocData);
                //                print_r($getDiff);
                //                print_r($oldDocData);
                if(empty($getDiff)){
                    $this->send_JSON(array('response' => "409",'message'=>"No values were changed. There's nothing to update !"));
                    exit();
                }
                //IWI if update any doc without any change, then no update icon will show end 



            if ($rq->version_update) {
                //IWI if trying to version up any doc without any change, then there will be showing a alert end restrict start
                unset($getPostData['version']);
                $getDiffVer = array_diff_assoc($getPostData, $oldDocData);
                 if(empty($getDiffVer)){
                    $this->send_JSON(array('response' => "408",'message'=>"No values were changed. There's nothing to update !"));
                    exit();
                }
                // IWI if trying to version up any doc without any change, then there will be showing a alert end restrict end 
                // Copy existing note/doc into a new one, with some slight changes:
                $old_ver = $this->note->read($rq->id);
                // - Visibility becomes "private" for the user performing the version update (basically hiding the old versions for everyone else), with edit access: "user"
                $old_ver['visibility'] = "private";
                $old_ver['edit_access'] = "user";
                // - Title gets the text "(old version)" appended to it (unless the title already contains this text)
                if (substr($old_ver['title'], -13) != "(old version)")
                    $old_ver['title'] .= " (old version)";
                // - Webview key is by default OFF for archive copy
                //                IWI Refresh after days start
                //                $this->note->create($old_ver['linked_with'], $old_ver['link_id'], $old_ver, false, $old_ver['dt'], $rq->webview_publish, $rq->wv_publish_for, $rq->wv_publish_for_ids, $rq->doc_read_sign); // Original date is moved into the new "old version" copy
                                
                $this->note->create($old_ver['linked_with'], $old_ver['link_id'], $old_ver, false, $old_ver['dt'], $rq->webview_publish, $rq->wv_publish_for, $rq->wv_publish_for_ids); // Original date is moved into the new "old version" copy
                
                // IWI Refresh after days end
                
            }

            // Update existing note/document
            $doc = $rq->doc;
            
            // if (!$this->user->is_Unrestricted()) // Current user becomes the last document editor, unless it's an admin
            //     $doc['editor_id'] = $this->user->ID();

            // 28012025 last edit used id add every time
            $doc['editor_id'] = $this->user->ID();

            //Document view footer change IWI 10-01-2024 start
            // 21012025 Documents -> Publish section changes
            if ($rq->webview_state  && $getDocDetails['published_by'] < 1) {
                $doc['published_by'] = $this->user->ID();
            }  
            //Document view footer change IWI 10-01-2024 end
            //IWI Refresh after days start
            //$r = $this->note->update($rq->id, $doc, $rq->link_id, $rq->webview_state, $rq->webview_secure, $rq->webview_publish, $rq->wv_publish_for, $rq->wv_publish_for_ids, $rq->doc_read_sign);
                        
            
            //IWI ref no for documents start
            if($doc['ref_number']==''){
            $uniqueNumber = microtime(true) * 1000000;
            $uniqueNumber = (int) $uniqueNumber;
            $doc['ref_number'] = $uniqueNumber;
            }
            // IWI ref no for documents end 
            
            
            
            $r = $this->note->update($rq->id, $doc, $rq->link_id, $rq->webview_state, $rq->webview_secure, $rq->webview_publish, $rq->wv_publish_for, $rq->wv_publish_for_ids);
            
            //IWI Refresh after days end
            if (!$r) {
                $err = MVC_Core::$debug->get_Last_Dump();
                return header(sprintf("HTTP/1.0 500 Database error: %s", (string) $err['data']));
            } 
              
              
            // IWI identify what was change in version start
            $date_time=date('Y-m-d h:i:s a');
            foreach ($getDiff as $key => $dff) {             
                if($key=='text'){ 
                $data=array(
                    'note_id'=>$rq->id,
                    'field_name'=>$key,
                    'old_val'=>$oldDocData[$key],
                    'new_val'=>$dff,
                    'editor_id'=>$this->user->ID(),
                    'updated_at'=>$date_time,
                );
                    $this->note->insertUpdateLog($data);
                }
            }
            
            // IWI identify what was change in version end
              
            $curr_note_id=$rq->id; //IWI Create a button to bulk mail send
        } else {
            // Create new
            


            if (empty($rq->link) || empty($rq->link_id))
                return header("HTTP/1.0 400 Request did not contain the ID of the linked element");

            $doc = $rq->doc;
//			$doc['org_id'] = isset(MVC_Core::$ss-> perspective)? MVC_Core::$ss-> perspective: ($this-> user-> is_Unrestricted()? $rq-> org_id: MVC_Core::$ss-> user['org_id']);
            $doc['org_id'] = MVC_Core::$ss->user['org_id'];  // Document view footer change IWI 10-01-2024 

            $doc['editor_id'] = $this->user->ID(); // The user is the creator of this document (even if admin creating it for a different organization)
//                        Document view footer change IWI 10-01-2024 start
            $doc['created_by'] = $this->user->ID();
            if ($rq->webview_state) {
                $doc['published_by'] = $this->user->ID();
            }
//                                Document view footer change IWI 10-01-2024 end

            if (!$this->user->is_Unrestricted())
                $doc['dep_id'] = MVC_Core::$ss->user['cdep_id'];
            
            
//             IWI ref no for documents start
            $uniqueNumber = microtime(true) * 1000000;
            $uniqueNumber = (int) $uniqueNumber;
            $doc['ref_number'] = $uniqueNumber;

            // 19022025 508 changes by iwi start
            // echo "wv_publish : ".$doc['wv_publish'];
            // $wv_publish = ($doc['wv_publish'] == 'true' ? '1' : '0');  
            // $doc['wv_publish'] = $wv_publish;  
            
            // echo "<br><pre>";
            // print_r($doc);
            // exit;
            // 19022025 508 changes by iwi end

//            IWI ref no for documents end 

//            IWI Refresh after days start
//            $iid = $this->note->create($rq->link, $rq->link_id, $doc, $rq->webview_state, $rq->webview_secure, $rq->webview_publish, $rq->wv_publish_for, $rq->wv_publish_for_ids, $rq->doc_read_sign);
            $iid = $this->note->create($rq->link, $rq->link_id, $doc, $rq->webview_state, $rq->webview_secure, $rq->webview_publish, $rq->wv_publish_for, $rq->wv_publish_for_ids);
            
//            IWI Refresh after days end
            
            if (!$iid) {
                $err = MVC_Core::$debug->get_Last_Dump();
                return header(sprintf("HTTP/1.0 500 Database error: %s", (string) $err['data']));
            }
            
            $curr_note_id=$iid; //IWI Create a button to bulk mail send
        }

        $org_id = isset(MVC_Core::$ss->perspective) ? MVC_Core::$ss->perspective : ($this->user->is_Unrestricted() ? $rq->doc['org_id'] : MVC_Core::$ss->user['org_id']);
        $this->record->org_UpdateData($rq->link_id, $org_id, array(
            'utime' => date("Y-m-d H:i:s")
        ));
//        echo count($_FILES['files']['name']);
//        print_r($_FILES);
//        echo $curr_note_id;
//        exit();
//        
          
//        IWI doc section file upload start
        
        $selectedUploadIds=array();
        if ($_FILES && count($_FILES['file']['name']) > 0) {
            $getFileDetails = $this->uploadDocFile($rq->link_id);
            $selectedUploadIds[]=$getFileDetails[0]['id'];


        }
        
         
        if ($curr_note_id) {
            $allAttachIds = array();
            if (!empty($rq->selectedAttachIds)) {
                $allAttachIds = $rq->selectedAttachIds;
            }
            if (count($selectedUploadIds) > 0) {
                $allAttachIds = array_merge($selectedUploadIds, $allAttachIds);
            }
            if (!empty($allAttachIds)) {
                $sendAttachedData = array(
                    'note_id' => $curr_note_id,
                    'ord_id' => $org_id,
                    'issue_id' => $rq->link_id,
                    'selectedAttachIds' => $allAttachIds,
                );
                $this->note->updateAttachFiles($sendAttachedData);
            }
        }

//        IWI doc section file upload end

        $this->send_JSON(array('response' => "Document stored",'curr_note_id'=>$curr_note_id)); //IWI Create a button to bulk mail send
    }

    /**
     * Deletes a note and updates counters
     */
    public function action_Remove(MVC_Request $post) {
        if (empty(MVC_Core::$ss->user))
            return header("HTTP/1.0 403 This method is only available while logged in");

        if (empty($post->id))
            return header("HTTP/1.0 400 Note identifier is missing from the request");

        // Read the note. Required to check security and also determine for which organization the note counter is reduced.
        $note = $this->note->read($post->id);
        if (!$note)
            return header("HTTP/1.0 404 The note could not be located. Might have already been deleted");

        // Check if the note that was read actually belongs to the linked element
        if ($note['linked_with'] != $post->link || $note['link_id'] != $post->link_id)
            return header("HTTP/1.0 403 The note doesn't belong to the specified element");

        // Check if the user is allowed to delete the note
        $user = MVC_Core::$ss->user;
        if (!($this->user->is_Unrestricted() || $note['edit_access'] == 'shared' || ($note['edit_access'] == 'org' && ($note['visibility'] == 'dep' || $note['visibility'] == 'org') && $note['org_id'] == $user['org_id']) || ($note['edit_access'] == 'dep' && $note['dep_id'] == $user['cdep_id']) || ($note['edit_access'] == 'user' && $note['editor_id'] == $user['id'])
                ))
            return header("HTTP/1.0 403 Unfortunately, you are not allowed to delete this Note or Document");

        $r = $this->note->delete($post->id);
        if (!$r)
            return header("HTTP/1.0 500 Note deletion failed. Possible database error.");

        $result = array('success' => true, "response" => "Information removed from the database.");

        // Depending on what is being linked, update counters or statistics field
        $count_notes = $this->note->count($post->link, $post->link_id, 'link', $note['org_id']);
        if ($post->link == "issue") {
            $this->record->org_UpdateData($post->link_id, $note['org_id'], array(
                'count_notes' => $count_notes
                    ), true); // Don't recheck locator

            if ($note['org_id'] == (isset(MVC_Core::$ss->perspective) ? MVC_Core::$ss->perspective : ($this->user->is_Unrestricted() ? $post->org_id : $user['org_id']))) {
                $result['update_count'] = true;
                $result['count'] = $count_notes;
            }
        }

        $this->send_JSON($result);
    }

    public function action_Load_Data(MVC_Request $rq) {
        if (empty($rq->id))
            return header("HTTP/1.0 400 Request did not contain the document ID");

        $doc = $this->note->read($rq->id, false); // False = Do not decode metadata json, it will be streamed directly


        echo json_encode($doc);
    }
    
//     IWI docs list  export , active ,  closed button create  start
    public function action_Get_DocsCount() {
       
        
        if (empty(MVC_Core::$ss->user))
            return header("HTTP/1.0 403 This method is only available while logged in");

        if ($this->user->is_Unrestricted())
        {
        // Superuser reads all linked, including private ones, along with the organization to which note belongs to ( extra column )
            $r = $this->note->get_List('link', $_POST['link'], $_POST['link_id'], array('note', 'editor', 'org'), function (QueryFactory &$q) use ($rq) {
                // Optional filters on note parameters
                if ($_POST['content_type'])
                    $q->where("`content_type` = ?", $_POST['content_type']);
            }, null, $rq->order ?: "`nte`.dt DESC", 0); // No limit here
            
            
        }
        else {
            // Normal user visibility will be filtered by their organization, department and their own private ones.
            $r = $this->note->get_List('link', $_POST['link'], $_POST['link_id'], array('note', 'editor'), function (QueryFactory &$q) use ($rq) {
                $user = MVC_Core::$ss->user;
                if (!is_null($user['org_id']))
                    $q->where("CASE WHEN `nte`.visibility = 'org' THEN `nte`.`org_id` = ? ELSE 1 END", $user['org_id']);
                if (!is_null($user['cdep_id']))
                    $q->where("CASE WHEN `nte`.visibility = 'dep' THEN `nte`.`dep_id` = ? ELSE 1 END", $user['cdep_id']);
                if (!is_null($user['id']))
                    $q->where("CASE WHEN `nte`.visibility = 'private' THEN `nte`.`editor_id` = ? ELSE 1 END", $user['id']);

                // Optional filters on note parameters
                if ($_POST['content_type'])
                    $q->where("`content_type` = ?", $_POST['content_type']);
            }, null, $rq->order ?: "`nte`.dt DESC", 0); // No limit here
        }

        if (!$r || count($r) == 0)
            return $this->send_JSON(array());

        $notes = array();
        foreach ($r as $note) {                   
             if($note['visibility']=='private') {
                 $old_tasks[] = $note;
             }  else{
                 $active_tasks[] = $note;
             }    
        }
        $this->send_JSON(array('success' => true, 'active' => count($active_tasks), 'old' => count($old_tasks)));
    }
//     IWI docs list  export , active ,  closed button create end
    
    
//    IWI pdf to doc start
    public function action_Get_filetohtml() {
        $targetDir = "pdf/";
        $fileType = strtolower(pathinfo($_FILES["file"]["name"], PATHINFO_EXTENSION));
        $new_base_file_name = 'file_' . date("Y-m-d-h-i-s");
        $newFileName = $new_base_file_name . '.' . $fileType;
        $targetFile = $targetDir . $newFileName;

        if (move_uploaded_file($_FILES["file"]["tmp_name"], $targetFile)) {
            $data = $this->action_Get_filetohtml_data($targetFile, $new_base_file_name);
            echo $data;
        } else {
            echo "Sorry, there was an error uploading your file.";
        }
    }

    public function action_Get_filetohtml_data($targetFile, $new_base_file_name) {
        $url = 'http://mydemowork.co.in/cargohub_doc_to_html.php';
        $file = 'https://cargohub.aero/' . $targetFile;
        $ch = curl_init($url);
        $data = array(
            'file' => $file,
            'file_base_name' => $new_base_file_name,
        );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl_handle, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        $content = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Curl error: ' . curl_error($ch);
        } else {
            echo $content; // Output or process the content as needed
        }
        curl_close($ch);
    }

//    IWI pdf to doc end
    
//    IWI doc section file upload start
    
    public function action_Get_Attach_File_Source(MVC_Request $rq) {
        $this->file = $this->factory_Model("File");
        $org_id = ($this->user->is_Unrestricted() && $rq->org_id) ? (int) $rq->org_id : MVC_Core::$ss->user['org_id'];
        $link_id = $rq->link_id;
        if (empty(MVC_Core::$ss->user))
            return header("HTTP/1.0 403 This method is only available while logged in");

        if ($this->user->is_Unrestricted())
            $r = $this->file->getAttachFileSource('issue', $rq->link_id, array('file', 'meta', 'extra', 'extra_user', 'extra_organization')); // Admin reads all linked files, no limits, including private ones!
        else
            $r = $this->file->getAttachFileSource('issue', $rq->link_id, array('file', 'meta', 'extra', 'extra_user'), function (QueryFactory &$q) use ($rq) {
                $user = MVC_Core::$ss->user;
                if (!empty($user)) {
                    $q->where("CASE WHEN `fil`.visibility = 'org' THEN `fil`.org_id = ? ELSE 1 END", $user['org_id']);
                    $q->where("CASE WHEN `fil`.visibility = 'shared' THEN FIND_IN_SET(?, `fil`.access_orgs) ELSE 1 END", $user['org_id']);

                    if (!is_null($user['cdep_id']))
                        $q->where("CASE WHEN `fil`.visibility = 'dep' THEN `fil`.dep_id = ? ELSE 1 END", $user['cdep_id']);

                    $q->where("CASE WHEN `fil`.visibility = 'private' THEN `fil`.user_id = ? ELSE 1 END", $user['id']);
                }
            }, null, "`fil`.`name` ASC");

        if (!$r || count($r) == 0)
            return $this->send_JSON(array());

        $files = array();
        foreach ($r as $file) {
            $this->file->analyze($file);

            // A bit of post-processing
            $file['id'] = (int) $file['id'];
            if (!empty($file['linked_id']))
                $file['linked_id'] = (int) $file['linked_id'];
            $file['mtime'] = date("Y-m-d H:i:s", $file['mtime']);
            $file['size'] = (int) $file['size'];
            if (!empty($file['dep_id']))
                $file['dep_id'] = (int) $file['dep_id'];

            $file['doctype'] = self::mimetype2doctype($file['mimetype'], $file['ext']);
            // Combine some values into a "title" to be shown in a grid
            $file['title'] = !empty($file['description']) ? $file['description'] : preg_replace('/\\.[^.\\s]{2,4}$/', '', $file['name']);
//            $file['file_id'] = $file['id'];
//            $file['file_desc'] = $thumble_img.$file['title'];
            $thumble_img='';
 if ($file['analyzer'] === 'image') {
    $metadata = json_decode($file['metadata'], true);
    if ($metadata && isset($metadata['scaler']['thms'])) {
        $chash = $file['chash'];
        $thumbnailPath = '/uimg/thms/' . substr($chash, 0, 2) . '/' . substr($chash, 2) . '.jpg';
       $thumble_img = '<img style="width:40px; height:35px;" src="' . $thumbnailPath . '" alt="' . htmlspecialchars($file['ext']) . '" />';
    }
}
if($thumble_img==''){
    $thumble_img = '<img style="width:40px; height:35px;" src="/img/mime/' . $file['mimetype']. '.png" alt="' . htmlspecialchars($file['ext']) . '" />';
}
 
            $finalData=array(
                'file_id'=>$file['id'],
                'file_desc'=>$thumble_img.'  '.$file['title'],
                
            );
            $files[] = $finalData;
        }

        $this->send_JSON($files);
    }

    private static function mimetype2doctype(&$mimetype, $ext)
	{
		// Recognise some mimetypes and extensions and translate them into human text
		// TODO: The variations on mimetype should be saved/changed on upload, and not while viewing lists...
		// TODO: Put this into NLS
		switch($mimetype) {
			case 'application/msexcel': case 'application/vnd.ms-excel': $doctype = "Microsoft Excel Document (XLS, XLSX)"; break;
			case 'application/msword': case 'application/vnd.ms-word': $doctype = "Microsoft Word Document (DOC, DOCX)"; break;
			case 'application/mspowerpoint': case 'application/vnd.ms-powerpoint': $doctype = "Microsoft PowerPoint Document (PPT, PPTX)"; break;
			case 'application/pdf': $doctype = "Adobe Acrobat Document (PDF)"; break;
			case 'application/photoshop': case 'image/vnd.adobe.photoshop': $doctype = "Adobe Photoshop Image (PSD)"; break;
			case 'application/rar': case 'application/x-rar': $doctype = "WinRAR Archive (RAR)"; break;
			case 'application/zip': switch($ext) {
				case 'cdr':
					$mimetype = "image/vnd.corel.draw"; $doctype = "Corel Draw Document (CDR)"; break;
				default:
					$doctype = "WinZIP Archive (ZIP)";
			}; break;
			case 'application/x-gzip': $doctype = "Linux G-Zip Package (GZ, GZIP)"; break;
			case 'application/x-dosexec': $doctype = "Windows Executable (EXE)"; break;
			case 'application/vnd.ms-opentype': $doctype = "Open-Type Font (OTF)"; break;
			case 'application/xml': $doctype = "eXtended Markup Language (XML)"; break;
			case 'image/jpeg': $doctype = "JPEG Photograph (JPG, JPEG)"; break;
			case 'image/png': $doctype = "PNG Lossless Picture (PNG)"; break;
			case 'image/tiff': $doctype = "Scanned Document/Image (TIF, TIFF)"; break;
			case 'image/x-ms-bmp': $doctype = "Bitmap Picture (BMP)"; break;
			case 'image/x-icon': $doctype = "Windows Icon (ICO)"; break;
			case 'image/gif': $doctype = "Graphics Interchange Format (GIF)"; break;
			case 'message/rfc822': $doctype = "Saved E-mail (EML)"; break;
			case 'text/csv': $doctype = "Comma Separated Values Table (CSV)"; break;
			case 'text/html': $doctype = "Hypertext Document (HTML, XML)"; break;
			case 'text/javascript': $doctype = "JavaScript Code (JS)"; break; // Doesn't seem to be recognized properly?
			case 'text/rtf': $doctype = "Rich-Text Format (RTF)"; break;
			case 'text/plain': switch($ext) {
				case 'js':
					$mimetype = "text/javascript"; $doctype = "JavaScript Code (JS)"; break;
				case 'json':
					$mimetype = "text/json"; $doctype = "JavaScript Object (JSON)"; break;
				case 'sql';
				$mimetype = "text/sql"; $doctype = "Structured Query Language (SQL)"; break;
				default:
					$doctype = "Simple text document (TXT)";
			}; break;
			case 'text/x-c++': switch($ext) {
				case 'php':
					$mimetype = "text/php"; $doctype = "Hypertext Preprocessor Code (PHP)"; break;
				default:
					$doctype = "C++ like Code (C, CPP)";
			}; break;
			default:
				if(substr($mimetype, 0, 9)=="Composite") {
					if($ext == 'msg') {
						$mimetype = "application/vnd.ms-outlook";
						$doctype = "Microsoft Outlook Message (MSG)";
					} else {
						$mimetype = "unknown";
						$doctype = "Composite Document (Unknown)"; break;
					}
				} else {
					$mimetype = "unknown";
					$doctype = "Unrecognized file format";
				}
		}

		return $doctype;
	}
        
    public function action_load_AttachFiles(MVC_Request $rq) {
        $file_ids=$this->note->load_AttachFiles($rq->note_id);
        $this->send_JSON($file_ids['file_ids']);
    }
//    IWI doc section file upload end
    
    //        IWI Create a files upload section in task start
    public function uploadDocFile($link_id)
    {
        //        print_r($files);

        require_once 'app/ctrl/public/files.php';

        $filesController = new App_Ctrl_Public_Files();
        $getDetails = $filesController->action_TaskFileUpload($link_id);
        return $getDetails;
    }

    //        IWI Create a files upload section in task end
}
