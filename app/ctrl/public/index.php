<?php

/**
 * Public Index (root)
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage Controllers
 * @version 2020.04.29
 */
class App_Ctrl_Public_Index extends App_Ctrl_Base {

    /**
     * Main Root Action
     * -- System redirector, can be configured per domain to load any other URL (even those which require a session, however a password will be asked if session is not active)
     */
    public function action_Index(MVC_Request $in) {
        $this->redirect(MVC_Core::$ss->domain_CFG['html_settings']['index_route']);
    }

    /**
     * A little helper that redirects the user to his chosen location after login (or returns the redirect URL to be sent to JS)
     *
     * @param bool $auto_redirect If true, redirects to "page after login"; If false, just returns it
     */
    protected function redirect_After_Login($auto_redirect = true) {
        // construct the redirect URL, depending on how the entry was requested
        if (MVC_Core::$ss->original_url) {
            $redirect_url = MVC_Core::$ss->original_url;
            unset(MVC_Core::$ss->original_url);
        } else
            $redirect_url = MVC_Core::$ss->user['preferences']['logon_redirect'];

        // ... and redirect or return it
        if ($auto_redirect)
            $this->redirect($redirect_url);
        else
            return $redirect_url;
    }

    /**
     * Universal Logout Method ( Logs out staff or contact account )
     */
    public function action_Logout() {
        // Reset Evan, any previous messages will disspear
        $this->evan->reset();

        $home = MVC_Core::$ss->domain_CFG['html_settings']['index_route'];

        // must be logged in first
        if (MVC_Core::$ss->user) {
            // logout and clear autologin cookie,
            if ($this->user->do_Logout(null, null, true))
                $this->evan->add("You have been successfully logged out of the system", "lock");
        } else
        // the same action logs out a frontend user
        if (MVC_Core::$ss->contact_account) {
            $cac = new App_Model_ContactAccount();
            $cac->do_Logout();
            $this->evan->add("Your Contact Account has been successfully logged out.", "lock");
        }

        // ... then redirect to home page
        $this->redirect($home);
    }

    /**
     * HACKENZY !
     */
    public function action_Confirm_EULA(MVC_Request $post) {
        if ($post->eula_confirm == 1) {
            if (isset(MVC_Core::$ss->eula_check)) {
                $test = MVC_Core::$ss->eula_check;
                $this->user->do_Login((int) $test['id'], $test['security']);
                unset(MVC_Core::$ss->eula_check);
                // Change the user status from "eula" to "online", so the user is not bothered anymore by this
                $this->user->set_Status("online");
                $this->action_Login();
            }
        } else
            $this->redirect("/access?action=login");
    }

    /**
     * Provides a way for external parties to participate into system conversations, by using a special "access_key" that is used to connect to the conversation record.
     * TODO: Implement this method !
     * -- Show the conversation
     * -- Support confirming delivery of e-mails from messages being part of the conversation (op = confirm_email) - Show the message and automatically set it as "confirmed" (link from e-mail)
     * -- Show the conversation with a reply box in it (op = reply) - (Find a way to determine who to reply to)
     * -- Allow creating a new message addressed to a contact within the system (Only user/dep/org contacts)
     * All message sending operations restrict the address to which the message can be send, to be only to users within the system, or issue submitters ( not possible to send to random addresses, this isn't a webmail )
     *
     * TODO: Contact account ( which was just created ) is also required to interact with the conversation beyond simply viewing it ( Replying requires the contact account to be logged in ).
     *
     * TODO: Maybe this action shouldn't be in this controller, but in it's own communication-related controller?
     *
     * @param MVC_Request $in
     */
    public function action_External_Comm(MVC_Request $in) {
        if (!isset($in->key) || strlen($in->key) != 40) {
            $this->evan->add("Invalid communication access key. In case you copy/pasted a link, please check if you copied it completely", 'error');
            return $this->redirect("/");
        }

        // Attempt to read the conversation having the key
        $m_cnv = new App_Model_Conversation();
        $convo = $m_cnv->read($in->key, array('conversation', 'organization', 'contacts', 'messages'));

        // Prepare Output
        $this->CFG['modules'][] = "commcenter_external";
        if (!empty($convo)) {

            // If this is an issue-linked conversation, "link_id" represents the issue ID. Read that and set in the "link" variable.
            if ($convo['type'] == 'issue') {
                $m_issue = new App_Model_Issue();
                $iss = $m_issue->read($convo['link_id'], array('issue', 'type', 'organization', 'group', 'submitter'));
            }

            // Commands?
            if (isset($in->op)) {
                switch ($in->op) {
                    // Confirms that a certain message has been viewed in the recipient's e-mail.
                    case 'confirm_email':
                        $m_comm = new App_Model_Comm();
                        $confirm_fail = "Attempting to confirm a message which either doesn't exist or is not part of this communication.";

                        // FIXME: This is VERY rudimentary...
                        // First, check if the message is actually part of this conversation
                        $found = false;
                        foreach ($convo['messages'] as $message) {
                            if ($message['id'] == $in->msg_id) {
                                $found = true;
                                break;
                            }
                        }
                        if (!$found) {
                            $this->evan->add($confirm_fail);
                            break;
                        }

                        // Attempt to confirm the message delivery - The confirmation function should read the message, check variouos things and return an array with the result or "FALSE" if there was an error reading the message
                        $confirm_r = $m_comm->confirm_Email_Message($in->msg_id);

                        if (!$confirm_r) {
                            $this->evan->add($confirm_fail);
                            break;
                        }

                        if ($confirm_r['status'] != 'confirming') {
                            $this->evan->add($confirm_r['message']);
                            break;
                        }

                        // Confirmed!. Do more checks depending on what this message is about
                        $this->evan->add($confirm_r['message'], "ok");

                        // If this is an issue-linked conversation, the message source is "isspost" (Transfer message). So change the status of the issue to "conf"
                        if ($convo['type'] == 'issue' && ($confirm_r['msg_source'] == 'isspost' || $confirm_r['msg_source'] == 'sendmail')) {
                            // Update the issue
                            $m_issue->update($iss['id'], array(
                                'status_key' => "dlv",
                                'delivery_confirm_ip' => $confirm_r['confirm_ip_u']
                            ));

                            // Send back a notification to the sender that the message has been confirmed.
                            $msg_id = $m_comm->create_System_Message($confirm_r['from_id'], "delivery_confirm.html", array(
                                'confirm' => $confirm_r,
                                'issue' => $iss
                            ));
                            $m_comm->email_Message($msg_id, null, null, true, true);
                        }
                        break;
                }
            }

            if (isset($iss)) {
                $this->link = $iss;
                $this->link_class = "issue";
            }

            $this->CFG['jqwidgets'][] = array("scrollbar", "listbox", "dropdownlist", "buttongroup");
            // These are used only by the WYSIWYG editor:
            $this->CFG['jqwidgets'][] = array("dropdownbutton", "colorpicker", "editor");
            $this->CFG['xjs'] = array(
                'jqform' => true,
                'dateformat' => true
            );

            $this->CFG['commcenter_external'] = array(
                // Some information about the conversation being loaded
                'convo' => array(
                    'id' => (int) $convo['id'],
                    'type' => $convo['type'],
                    'access_key' => $convo['access_key'],
                    'organization' => $convo['organization']
                )
                    // TODO: Reply related data and preparation
            );

            $this->convo = $convo;
        }

        $this->prepare_Header(empty($convo) ? "Communication error" : $convo['description'], array("userbar", "evan"));

        if (MVC_Core::$ss->user) {
            $user = MVC_Core::$ss->user;
            $contact = array(
                'id' => $user['contact_id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'user_id' => $user['id']
            );
            $cac_managed_ctx = array(); // Just so there is no warning... a staff user doesn't "manage" any frontend contacts. He/she himself must be in the conversation in order to reply though this page.
        } elseif (MVC_Core::$ss->contact_account) {
            $contact = MVC_Core::$ss->contact_account['contact'];
            $cac_managed_ctx = explode(',', MVC_Core::$ss->contact_account['managed_ctx']);
        } else
        // If no user logged in, show the contact login form
            $this->_out['header']['rightmenu'] = array(
                array(
                    'link' => "/contact/access?action=login&return=convo&key=" . $convo['access_key'],
                    'text' => "Contact Login"
                )
            );

        if (isset($contact)) {
            $this->contact = $this->CFG['contact_account']['contact'] = $contact;
            if (!empty($convo)) {
                // Check if the logged in contact or any of it's "managed contacts" are in the list of conversation contacts. Replying is only possible if yes.
                $can_reply = false;
                if (in_array($contact['id'], $convo['contact_ids']))
                    $can_reply = true;
                else {
                    foreach ($cac_managed_ctx as $mc_id)
                        if (in_array($mc_id, $convo['contact_ids']))
                            $can_reply = true;
                }

                if ($can_reply) {
                    $this->reply_active = $this->CFG['commcenter_external']['reply_active'] = true;

                    // Determine the default reply target, as the last user of the conversation which is not the currently logged one.
                    // If not found (the logged in contact is the only member of the conversation), there won't be a reply target, in which case the javascript will simply add (nobody) as the communication target.
                    // - Of course, nobody will receive the message, but if this is an issue-connected conversation (TODO: Determine if it is?), the "commtime" should be updated anyway, and status set to "wr"
                    // - TODO: Which should also trigger a notification to the assigned user...
                    $r_con = array_reverse($convo['contact_ids']);
                    foreach ($r_con as $contact_id) {
                        if ($contact_id != $contact['id'] && !in_array($contact_id, $cac_managed_ctx)) {
                            $this->CFG['commcenter_external']['reply_to'] = $contact_id;
                            break;
                        }
                    }
                }
            }
        }

        // View the public area with the "external_comm" content page ( which is similar to the conversation tab in the staff communication module )
        $this->site_section = "public" . DS . "external_comm.html";
        if (!empty(MVC_Core::$ss->domain_CFG['html_settings']['embedded']))
            $this->view("public_embedded.html");
        else
            $this->view("public.html");
    }

    /**
     * Adds a reply to a conversation, when either a contact account or an user account is logged in.
     */
    public function action_External_Comm_Reply(MVC_Request $rq) {
        if (MVC_Core::$ss->contact_account) {
            $contact = MVC_Core::$ss->contact_account['contact'];
        } elseif (MVC_Core::$ss->user) {
            $user = MVC_Core::$ss->user;
            $contact = array(
                'id' => $user['contact_id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'user_id' => $user['id']
            );
        } else
            return header("HTTP/1.0 403 Unable to reply if not logged in !");

        $recipient = $rq->recipient;
        if (isset($rq->issue)) {
            $issue = $rq->issue;
            $convo_type = "issue";
            $convo_link = $issue['id'];
        } else {
            $convo_type = "basic";
            $convo_link = null;
        }

        // Read the conversation specified
        if (!isset($rq->cnv_id))
            return header("HTTP/1.0 400 Conversation identifier missing, possible interface error");

        $m_cnv = new App_Model_Conversation();
        $convo = $m_cnv->read($rq->cnv_id, array('conversation'));
        if (!$convo)
            return header("HTTP/1.0 404 Conversation not found, possible interface error");

        // Assamble message data depending on conversation type:
        $msg = array(
            'org_id' => $convo['org_id'], // Same organization as the conversation this message will be connected to
            'cnv_id' => $convo['id'],
            'from_id' => $contact['id'],
            'recipients' => $recipient['id'],
            'recipient_distrib' => "1,0,0"
        );
        // Create the message with the incoming data
        $m_comm = new App_Model_Comm();
        $msg_iid = $m_comm->create('user', $rq->content, empty($rq->subject) ? ("Re: " . $convo['description']) : trim($rq->subject), $msg);
        if (!$msg_iid)
            return header("HTTP/1.0 500 Database error: Unable to create the message");

        $r = $m_cnv->attach($convo['id'], $msg_iid);
        if ($r) {
            // Try to see if the target contact is a system user, in that case locate the conversation into the user's labels.
            // -- For all results which are not "trash", set the status to "unread"
            $m_con = new App_Model_Contact();
            $recipient = $m_con->read($recipient['id'], false);

            // TODO: This is very similar to the one in "Add Message", see if they can be combined into a common function

            switch ($recipient['type']) {
                // This is a system user contact - The message doesn't go out as an e-mail, unless the user chose to receive messages via e-mail, or just get notified about new messages via e-mail
                case 'user_self':
                    // Read the target user information, needed to locate inbox and a few other information
                    $to_user = $this->user->read($recipient['user_id'], array('user_comm'), false);

                    // Check if the user has this conversation linked to any labels
                    $r = $m_cnv->cnv2lab_Get_Links($to_user['id'], $convo['id']);
                    $lab_ids = array();
                    if ($r)
                        foreach ($r as $link) {
                            // If the link status is "trash" or "deleted", there's nothing to do. The user is not interested in this conversation anymore
                            if ($link['status'] == 'trash' || $link['status'] == 'deleted') {
                                $this->evan->add("Your message has been added but the recipient has hidden this conversation and might not see your message.", "send");
                                $this->send_JSON(array('success' => true));
                            }

                            $lab_ids[] = (int) $link['lab_id'];
                        }

                    // Change status of all links for the target user to "unread" and increase unread count by 1
                    if (count($lab_ids)) {
                        $m_cnv->cnv2lab_Mass_Status_Update($convo['id'], $lab_ids, 'unread');
                        $this->user->update($to_user['id'], array(
                            'count_unread' => "`count_unread` + 1"
                                ), true);
                    }

                    // Was the user's inbox among the list of links ?
                    if (!in_array($to_user['inbox_id'], $lab_ids)) {
                        // Nope, create the link to the inbox and set it as well to unread.
                        $m_cnv->cnv2lab_Create_Link($to_user['id'], $convo['id'], $to_user['inbox_id'], "unread");
                    }

                    // TODO: Check the user preferences about how to be notified when receiving new messages

                    break;
                // The message is being sent to the target organization asa a whole. Certain users from that organization will get notified about it and might choose to "take over" the communication,
                // in which case the recipient is converted to that particular user
                case 'org_self':

                    break;
                // Same thing as for organization contacts, but only at department level
                case 'dep_self':

                    break;
                // In all other cases, this message is sent out as an e-mail without any further processing (outside contact)
                default:
                // ??
            }
            // For now, just send as e-mail anyway (in all cases)
            $m_comm->email_Message($msg_iid);

            if ($recipient['id'])
                $this->evan->add(sprintf("Your message has been sent to <b>%s</b>.", $recipient['name']), "send");
            else
                $this->evan->add("Your message has been added to the conversation.", "send");

            // Finally, send the response to JS
            $this->send_JSON(array('success' => true));
        } else
            $this->send_Fail_JSON("Database error, could not add the message to the conversation");
    }

    /**
     * Adds a reply to a conversation, when either a contact account or an user account is logged in with custom
     */
    public function action_External_Comm_Reply_Custom(MVC_Request $rq) {
        if (MVC_Core::$ss->contact_account) {
            $contact = MVC_Core::$ss->contact_account['contact'];
        } elseif (MVC_Core::$ss->user) {
            $user = MVC_Core::$ss->user;
            $contact = array(
                'id' => $user['contact_id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'user_id' => $user['id']
            );
        } else
            return header("HTTP/1.0 403 Unable to reply if not logged in !");

        $recipient = $rq->recipient;
        if (isset($rq->issue)) {
            $issue = $rq->issue;
            $convo_type = "issue";
            $convo_link = $issue['id'];
        } else {
            $convo_type = "basic";
            $convo_link = null;
        }

        // Read the conversation specified
        if (!isset($rq->cnv_id))
            return header("HTTP/1.0 400 Conversation identifier missing, possible interface error");

        $m_cnv = new App_Model_Conversation();
        $convo = $m_cnv->read($rq->cnv_id, array('conversation'));
        if (!$convo)
            return header("HTTP/1.0 404 Conversation not found, possible interface error");

        // Assamble message data depending on conversation type:
        $msg = array(
            'org_id' => $convo['org_id'], // Same organization as the conversation this message will be connected to
            'cnv_id' => $convo['id'],
            'from_id' => $contact['id'],
            'to_id' => $recipient['id']
        );
        $extra = array();
        //sender
        $extra[0]['email'] = $recipient['sender_email'];
        $extra[0]['name'] = $recipient['sender_name'];
        //recipient
        $extra[1]['email'] = $recipient['receiver_email'];
        $extra[1]['name'] = $recipient['receiver_name'];

        // Create the message with the incoming data
        $m_comm = new App_Model_Comm();
        $msg_iid = $m_comm->create('user', $rq->content, empty($rq->subject) ? ("Re: " . $convo['description']) : trim($rq->subject), $msg);
        if (!$msg_iid)
            return header("HTTP/1.0 500 Database error: Unable to create the message");

        $r = $m_cnv->attach($convo['id'], $msg_iid);
        if ($r) {
            // Try to see if the target contact is a system user, in that case locate the conversation into the user's labels.
            // -- For all results which are not "trash", set the status to "unread"
            $m_con = new App_Model_Contact();
            $recipient = $m_con->read($recipient['id'], false);

            // TODO: This is very similar to the one in "Add Message", see if they can be combined into a common function

            switch ($recipient['type']) {
                // This is a system user contact - The message doesn't go out as an e-mail, unless the user chose to receive messages via e-mail, or just get notified about new messages via e-mail
                case 'user_self':
                    // Read the target user information, needed to locate inbox and a few other information
                    $to_user = $this->user->read($recipient['user_id'], array('user_comm'), false);

                    // Check if the user has this conversation linked to any labels
                    $r = $m_cnv->cnv2lab_Get_Links($to_user['id'], $convo['id']);
                    $lab_ids = array();
                    if ($r)
                        foreach ($r as $link) {
                            // If the link status is "trash" or "deleted", there's nothing to do. The user is not interested in this conversation anymore
                            if ($link['status'] == 'trash' || $link['status'] == 'deleted') {
                                $this->evan->add("Your message has been added but the recipient has hidden this conversation and might not see your message.", "send");
                                $this->send_JSON(array('success' => true));
                            }

                            $lab_ids[] = (int) $link['lab_id'];
                        }

                    // Change status of all links for the target user to "unread" and increase unread count by 1
                    if (count($lab_ids)) {
                        $m_cnv->cnv2lab_Mass_Status_Update($convo['id'], $lab_ids, 'unread');
                        $this->user->update($to_user['id'], array(
                            'count_unread' => "`count_unread` + 1"
                                ), true);
                    }

                    // Was the user's inbox among the list of links ?
                    if (!in_array($to_user['inbox_id'], $lab_ids)) {
                        // Nope, create the link to the inbox and set it as well to unread.
                        $m_cnv->cnv2lab_Create_Link($to_user['id'], $convo['id'], $to_user['inbox_id'], "unread");
                    }

                    // TODO: Check the user preferences about how to be notified when receiving new messages

                    break;
                // The message is being sent to the target organization asa a whole. Certain users from that organization will get notified about it and might choose to "take over" the communication,
                // in which case the recipient is converted to that particular user
                case 'org_self':

                    break;
                // Same thing as for organization contacts, but only at department level
                case 'dep_self':

                    break;
                // In all other cases, this message is sent out as an e-mail without any further processing (outside contact)
                default:
                // ??
            }
            // For now, just send as e-mail anyway (in all cases)
            $m_comm->email_Message($msg_iid, $extra);

            if ($recipient['id'])
                $this->evan->add(sprintf("Your message has been sent to <b>%s</b>.", $recipient['name']), "send");
            else
                $this->evan->add("Your message has been added to the conversation.", "send");

            // Finally, send the response to JS
            $this->send_JSON(array('success' => true));
        } else
            $this->send_Fail_JSON("Database error, could not add the message to the conversation");
    }

    /**
     * Export server for all file generation/download procedures that come from jqWidgets (Grid export, Chart export)
     */
    public function action_Export(MVC_Request $post) {
        $filename = $post->filename ?: ($post->fname ?: null);
        if (empty($filename))
            return header("HTTP/1.0 400 Invalid parameters received: Missing file name");

        if ($post->format) {
            // Create headers depending on format
            switch ($post->format) {
                case 'xls':
                    header('Content-type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'); // XML Excel (XLSX), not classic XLS
                    break;
                case 'html':
                    header('Content-type: text/html');
                    break;
                case 'cclp':
                case 'csv':
                    header('Content-type: text/csv');
                    break;
                case 'json':
                    header('Content-type: text/json');
                    break;
                default:
                    return header("HTTP/1.0 400 Invalid parameters received: Unsupported format {$post->format}");
            }
            $extension = $post->format;
            $contents = $post->content;

            if ($filename[0] == '#') {
                // Special prefix, cut at |
                $filename_frags = explode('|', substr($filename, 1));
                switch ($filename_frags[0]) {
                    // Special export mode that uploads data to an FTP instead of sending it back as a download
                    case 'cclp':
                        if (!empty($filename_frags[2])) { // Perspective (org id)
                            $m_org = new App_Model_Organization();
                            $org = $m_org->read($filename_frags[2], null);
                        } else
                            $org = MVC_Core::$ss->organization;

                        // Fix invalid characters in org name:
                        $orgname = mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '_', $org['name']);

                        $cclp_filename = sprintf("#%d-%s|%s", $org['id'], $orgname, $filename_frags[1]) . date("|Y-m-d|H_i_s");
                        $filename = $filename_frags[1];
                        break;
                }
            }
            // Append current timestamp to filename, before extension
            $filename .= date(" [Y-m-d H_i_s]");
        } else {
            // Filename already contains extension, create headers depending on the extension;
            // Content comes encoded as "base64" for these types
            if (substr($filename, -4) == '.png') {
                $extension = "png";
                $filename = substr($filename, 0, -4);
                $contents = base64_decode($post->content);
                header('Content-type: image/png');
            } elseif (substr($filename, -4) == '.jpg') {
                $contents = base64_decode($post->content);
                $extension = "jpg";
                $filename = substr($filename, 0, -4);
                header('Content-type: image/jpg');
            } else
                return header("HTTP/1.0 400 Invalid parameters received: Extension not recognized");
        }
        if (empty($contents))
            return header("HTTP/1.0 400 Invalid parameters received: No contents");

        // Patch upload to CCLP ftp
        if (isset($cclp_filename)) {
            $conn = ftp_connect("cargohub.aero", 21, 5);
            if ($conn !== false) {
                $auth_result = @ftp_login($conn, "cclp@cargohub.aero", "Zl44koYpZ");
                if (!$auth_result)
                    ftp_close($conn);
                else {
                    // We're good, create the file and upload
                    ftp_pasv($conn, true);
//					$chdr = @ftp_chdir($conn, "/upload");
//					if($chdr) {
                    // If change dir succeeded, attempt to create the file
                    $tmpfile = fopen('php://memory', 'r+');
                    fputs($tmpfile, $contents);
                    rewind($tmpfile);

                    ftp_fput($conn, sprintf("%s.%s", $cclp_filename, $extension), $tmpfile, FTP_BINARY);
//					}
                    ftp_close($conn);
                }
            }
        }

        /*
          // TODO: Support export log ?
          if ($handle = fopen("export_log.txt", "a+"))
          {
          date_default_timezone_set('UTC');
          $data = date(DATE_RFC822, $_SERVER['REQUEST_TIME']) . ", " . $_SERVER['REMOTE_ADDR'] . ", "
          . $_SERVER['REMOTE_HOST'] ."\n";
          fputs($handle, $data);
          fflush($handle);
          fclose($handle);
          }
         */
        if (empty($filename) || empty($extension))
            return header("HTTP/1.0 400 Invalid filename, something went wrong");

        header("Content-Disposition: attachment; filename=\"{$filename}.{$extension}\"");
        header("Content-Transfer-Encoding: binary");

        header("Content-length: " . (string) strlen($contents));
        header("Pragma: public");
        header("Pragma: no-cache");
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Cache-Control: private", false); // required for certain browsers
        echo $contents;
    }

    /**
     * Confirms (sets as "read") a database notification which was sent externally via e-mail
     * -- Triple checks using ID, Time and User_ID
     *
     * @param MVC_Request $in
     */
    public function action_Evan_Confirm(MVC_Request $in) {
        if (!$in->id || !$in->time || !$in->user_id) {
            $this->evan->add("The parameters sent to this function are incorrect. Either you have followed an old invalid link or something else is broken.", 'error');
            MVC_Core::shutdown('/kb?page=link_error');
        }

        $ntf = $this->evan->read($in->id, array());
        if (!$ntf || $ntf['time'] != $in->time || $ntf['user_id'] != $in->user_id) {
            $this->evan->add("Notification record not found. You have followed an old link or the notification does not exist anymore.", 'error');
            MVC_Core::shutdown('/kb?page=link_error');
        }

        if ($ntf['status'] != 'new') {
            $this->evan->add("This notification has already been confirmed. Thank you for clicking the link !", 'info');
            MVC_Core::shutdown('/kb?page=op_success');
        }

        // Ok, we have a valid link and a notification which is still "new"
        if ($this->evan->update($in->id, 'read')) {
            $this->evan->add("Thank you for confirming this notification. Current status is now set to &quot;Read&quot;", 'ok');
            MVC_Core::shutdown('/kb?page=op_success');
        } else {
            $this->evan->add("An error occured when attempting to confirm your notification. Please contact support.", 'error');
            MVC_Core::shutdown('/kb?page=op_fail');
        }
    }

    /**
     * Views a document based on it's ID (encoded with qp_conv)
     *
     * @param MVC_Request $rq
     */
    public function action_Document(MVC_Request $rq) {

        $id = @MVC_Core::qp_conv($rq->qp);
        $m_note = $this->factory_Model("Note");
        $m_file = $this->factory_Model("File"); //IWI doc section file upload 
        // Read document
        if (!empty($rq->nid)) {
            $note_id = base64_decode($rq->nid);
            //		$doc = $m_note-> read($id);
            $doc = $m_note->read($note_id);
        } else {
            $doc = $m_note->readByKey($rq->key);
        }

//                print_r($doc);exit();

        if (!$doc) {
            $this->evan->add("Requested document was not found or you followed an incorrect link", "error");
            MVC_Core::shutdown('/kb?page=404');
        }

//		if($doc['wv_key'] != $rq-> key) {
//			$this-> evan-> add("Invalid access key for the requested document", "error");
//			MVC_Core::shutdown('/kb?page=403');
//		}

        if ($doc['wv_secure']) {
            if (!$this->user->check_Session()) {
                // Not logged in. Redirect to the login page, with forward redirect back here
                MVC_Core::$ss->original_url = $_SERVER['REQUEST_URI'];
                $this->evan->add("This document is secured. Please login into your organization account to access it.", 'lock');
                $this->redirect("/access?action=entrance");
            } elseif (!$this->user->is_Unrestricted()) {
                // Check if logged in user is from the document's owner organization (that doesn't apply for admin, which can access any document)
                if ($doc['org_id'] != MVC_Core::$ss->user['org_id']) {
                    $this->evan->add("This restricted document does not belong to your organization. Viewing it is not possible.", "lock");
                    MVC_Core::shutdown('/kb?page=403');
                }
            }
        }

        // Read organization data to check if a webview_template is being used
        $org = $this->org->read($doc['org_id']);
        if (isset($org['features']['webview_template'])) {
            // Read the webview template...
            $m_tpl = new App_Model_Template();
            $tpl_body = $m_tpl->read($org['features']['webview_template'], true); // Only body is needed here
            $tpl_data = array(
                'doc' => $doc
            );

            // ... the basic info for the record where this document is placed ...
            if (!empty($doc['linked_with']) && $doc['linked_with'] == "issue" && $doc['link_id'] > 0) {
                $this->record = $this->factory_Model('Issue', 'record');
                $tpl_data['issue'] = $this->record->read($doc['link_id'], array(), $doc['org_id']); // 'issue', 'organization'
            }

            // ...  and render the data into it
            try {
//                            print_r($doc);
                $dtCFG = new stdClass();
                $dryt = new Drytemplate($dtCFG);
//				$this-> body = $dryt-> render_String($tpl_body, $tpl_data);
                $this->body = $doc['text'];
                $this->track_id = $tpl_data['issue']['track'];
                $this->version = $doc['version'];
                $this->title = $doc['title'];
                $this->dt = ($doc['update_dt'] != '') ? date("Y-m-d ", strtotime($doc['update_dt'])) : date("Y-m-d ", $doc['dt']);

                $this->ref_number = $doc['ref_number']; //IWI ref no for documents 
//                                 Document view footer change IWI 10-01-2024 start
                if ($doc['created_by'] != '') {
                    
                }
                $created_by = $doc['created_by'] != '' ? $doc['created_by'] : $doc['editor_id'];
                $published_by = $doc['published_by'] != '' ? $doc['published_by'] : $doc['editor_id'];
                $last_edited_by = $doc['editor_id'];
                $created_by_user_details = $this->user->get_user_custom_details($created_by, 'id,name');
                $published_by_user_details = $this->user->get_user_custom_details($published_by, 'id,name');
                $last_edited_by_user_details = $this->user->get_user_custom_details($last_edited_by, 'id,name');
                $this->created_by = $created_by_user_details['name'];
                $this->published_by = $published_by_user_details['name'];
                $this->last_edited_by = $last_edited_by_user_details['name'];
//                                 Document view footer change IWI 10-01-2024 end
//                                 

                $this->is_notification = $doc['is_notification'] == 1 ? 'yes' : 'no'; //IWI read and sign required
                $this->is_required_read_sign = $doc['is_required_read_sign'] == 1 ? 'yes' : 'no'; //IWI read and sign required
//                                 
//                              IWI doc seen & sign  start
                $this->note_id = $doc['id'];
                $this->logged_in_user = isset(MVC_Core::$ss->user['id']) ? MVC_Core::$ss->user['id'] : '';

                $getUserReadSignData = $m_note->getUserReadSignData(MVC_Core::$ss->user['id'], $doc['id']);
                if (is_array($getUserReadSignData) && !empty($getUserReadSignData)) {

                    // IWI: If any update in the doc section, then the update icon will show in publication list - start
                    if (trim($doc['update_dt'] ?? '') != '') {
                        $update_dt_time = strtotime($doc['update_dt']);
                        $signed_at_time = strtotime($getUserReadSignData['signed_at'] ?? '');

                        if ($update_dt_time !== false && $signed_at_time !== false && $update_dt_time > $signed_at_time) {
                            $this->read_sign = '0';
                        } else {
                            $this->read_sign = $getUserReadSignData['read_sign'] ?? '0';

                            if (!empty($getUserReadSignData['signed_at'])) {
                                $this->signed_at = date('d-m-Y', strtotime($getUserReadSignData['signed_at']));
                            }
                        }
                    }
                    // IWI: Update icon logic - end
                } else {
                    $this->read_sign = '0';
                }
                //             IWI doc seen & sign end


                $this->is_readonly = MVC_Core::$ss->user['status'] == 'readonly' ? 'yes' : 'no';  //publication read only IWI
            } catch (Exception $e) {
                $this->body = "<div class='red'>There was an error while trying to display this document.<br />Please contact your supervisor or Cargohub Support (support@cargohub.nl) for more information</div>";
            }
        } else {
            // No... then the document is shown on an empty page
            $this->body = $doc['text'];
        }


//        IWI identify what was change in version start
        $getHistory = array();
        $getHistoryData = $m_note->getDocHistoryData($doc['id']);
        $i = 0;
        foreach ($getHistoryData as $hRow) {
            $hRow['curr_row'] = $i;
            $hRow['updated_at'] = date("jS, F Y h:i A", strtotime($hRow['updated_at']));
            $getHistory[] = $hRow;
            $i++;
        }
        $this->get_history = $getHistory;
        $this->get_history_count = count($getHistory);

        $getLastBody = $m_note->getDocHistoryDataByField($doc['id'], 'text');
        $this->getLastBody = $getLastBody['old_val'];

//        IWI identify what was change in version end
//        IWI doc section file upload start
        $getAttachedFiles = array();
        $getAttachedFilesData = $m_note->load_AttachFiles($doc['id']);
//        $i=0;
        $getFiles = $m_file->getAttachFileSource(json_decode($getAttachedFilesData['file_ids']), '', array('file', 'meta', 'extra', 'extra_user'));
        foreach ($getFiles as $file) {
            $m_file->analyze($file);
            $file['id'] = (int) $file['id'];
            if (!empty($file['linked_id']))
                $file['linked_id'] = (int) $file['linked_id'];
            $file['mtime'] = date("Y-m-d H:i:s", $file['mtime']);
            $file['size'] = (int) $file['size'];
            if (!empty($file['dep_id']))
                $file['dep_id'] = (int) $file['dep_id'];

            $file['title'] = !empty($file['description']) ? $file['description'] : preg_replace('/\\.[^.\\s]{2,4}$/', '', $file['name']);
            $thumble_img = '';
            if ($file['analyzer'] === 'image') {
                $metadata = json_decode($file['metadata'], true);
                if ($metadata && isset($metadata['scaler']['thms'])) {
                    $chash = $file['chash'];
                    $file['thumbnailPath'] = '/uimg/thms/' . substr($chash, 0, 2) . '/' . substr($chash, 2) . '.jpg';
                }
            }
            if ($thumble_img == '') {
                $file['thumbnailPath'] = '/img/mime/' . $file['mimetype'] . '.png';
            }
            $getAttachedFiles[] = $file;
        }
//        echo "<pre>";
//        print_r($getAttachedFiles);
//        echo "</pre>";
        $this->get_AttachedFiles = $getAttachedFiles;
//        IWI doc section file upload end
        // Prepare for output
        $this->view("document_view_container.html");
    }

    public function action_Document_sign_submit() {
        //print_r($_POST['note_id']);

        $m_note = new App_Model_Note();
        $sql = $m_note->readSignLog(MVC_Core::$ss->user['id'], $_POST['note_id'], $_POST['read_sign']);
        if (!$sql) {
            return header("HTTP/1.0 500 Database error: Unable to create the log");
        } else {
            $this->send_JSON(array('success' => true));
        }
    }

}
