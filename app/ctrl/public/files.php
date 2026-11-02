<?php

/**
 * File operations Controller (used to retrieve or send warehouse stored files from/to the system)
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage Controllers
 * @version 2020.07.08
 */
class App_Ctrl_Public_Files extends App_Ctrl_Base {

    /**
     * Contact model, which is necessary for user display/editor to work properly
     * @var App_Model_File
     */
    private $file;

    /**
     * Initialize the data model
     */
    public function __construct() {
        parent::__construct();

        $this->file = $this->factory_Model("File");
        $this->record = $this->factory_Model('Issue', "record");
    }

    /**   Added for chunk upload */
    public function rrmdir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (filetype("$dir/$object") == "dir") {
                        $this->rrmdir("$dir/$object");
                    } else {
                        unlink("$dir/$object");
                    }
                }
            }
            reset($objects);
            rmdir($dir);
        }
    }

    public function cleanUp($file_chunks_folder) {
        // rename the temporary directory (to avoid access from other concurrent chunks uploads) and than delete it
        if (rename($file_chunks_folder, $file_chunks_folder . '_UNUSED')) {
            $this->rrmdir($file_chunks_folder . '_UNUSED');
        } else {
            $this->rrmdir($file_chunks_folder);
        }
    }

    public function resumableUpload($tmp_file_path, $filename) {
        $successes = array();
        $errors = array();
        $warnings = array();
        $dir = ROOT . DS . 'htdocs/tmp/';

        // echo "in resumableUpload"; 


        $identifier = ( isset($_POST['dzuuid']) ) ? trim($_POST['dzuuid']) : '';

        $file_chunks_folder = "$dir$identifier";
        if (!is_dir($file_chunks_folder)) {
            mkdir($file_chunks_folder, 0777, true);
        }

        $filename = str_replace(array(' ', '(', ')'), '_', $filename); # remove problematic symbols
        $info = pathinfo($filename);
        $extension = isset($info['extension']) ? '.' . strtolower($info['extension']) : '';
        $filename = $info['filename'];

        $totalSize = (isset($_POST['dztotalfilesize']) ) ? (int) $_POST['dztotalfilesize'] : 0;
        $totalChunks = (isset($_POST['dztotalchunkcount']) ) ? (int) $_POST['dztotalchunkcount'] : 0;
        $chunkInd = (isset($_POST['dzchunkindex']) ) ? (int) $_POST['dzchunkindex'] : 0;
        $chunkSize = (isset($_POST['dzchunksize']) ) ? (int) $_POST['dzchunksize'] : 0;
        $startByte = (isset($_POST['dzchunkbyteoffset']) ) ? (int) $_POST['dzchunkbyteoffset'] : 0;

        $chunk_file = "$file_chunks_folder/{$filename}.part{$chunkInd}";

//            if (!move_uploaded_file($tmp_file_path, $chunk_file)) {
//                $errors[] = array( 'text'=>'Move error', 'name'=>$filename, 'index'=>$chunkInd );
//            }

        if (!move_uploaded_file($tmp_file_path, $chunk_file)) {
            $errors[] = array(
                'text' => 'Move error',
                'name' => $filename,
                'index' => $chunkInd,
                'tmp_exists' => file_exists($tmp_file_path),
                'dir_writable' => is_writable($file_chunks_folder),
                'destination' => $chunk_file
            );
        }

        if (count($errors) == 0 and $new_path = $this->checkAllParts($file_chunks_folder,
                        $filename,
                        $extension,
                        $totalSize,
                        $totalChunks,
                        $successes, $errors, $warnings) and count($errors) == 0) {
            return array('final' => true, 'path' => $new_path, 'successes' => $successes, 'errors' => $errors, 'warnings' => $warnings);
        }
        return array('final' => false, 'successes' => $successes, 'errors' => $errors, 'warnings' => $warnings);
    }

    public function checkAllParts($file_chunks_folder,
            $filename,
            $extension,
            $totalSize,
            $totalChunks,
            &$successes, &$errors, &$warnings) {

        // echo "in checkAllParts";
        // reality: count all the parts of this file
        $parts = glob("$file_chunks_folder/*");
        $successes[] = count($parts) . " of $totalChunks parts done so far in $file_chunks_folder";

        // check if all the parts present, and create the final destination file
        if (count($parts) == $totalChunks) {
            $loaded_size = 0;
            foreach ($parts as $file) {
                $loaded_size += filesize($file);
            }
            if ($loaded_size >= $totalSize and $new_path = $this->createFileFromChunks(
                            $file_chunks_folder,
                            $filename,
                            $extension,
                            $totalSize,
                            $totalChunks,
                            $successes, $errors, $warnings) and count($errors) == 0) {
                $this->cleanUp($file_chunks_folder);
                return $new_path;
            }
        }
        return false;
    }

    /**
     * Check if all the parts exist, and
     * gather all the parts of the file together
     * @param string $file_chunks_folder - the temporary directory holding all the parts of the file
     * @param string $fileName - the original file name
     * @param string $totalSize - original file size (in bytes)
     */
    public function createFileFromChunks($file_chunks_folder, $fileName, $extension, $total_size, $total_chunks,
            &$successes, &$errors, &$warnings) {

        //  echo "in createFileFromChunks";

        $rel_path = ROOT . DS . 'htdocs/tmp/uploads/';
        $saveName = $this->getNextAvailableFilename($rel_path, $fileName, $extension, $errors);

        if (!$saveName) {
            return false;
        }
//echo "after func";
        $fp = fopen("$rel_path$saveName$extension", 'w');
        if ($fp === false) {
            $errors[] = 'cannot create the destination file';
            return false;
        }
        for ($i = 0; $i < $total_chunks; $i++) {
            fwrite($fp, file_get_contents($file_chunks_folder . '/' . $fileName . '.part' . $i));
        }
        fclose($fp);

//echo "$rel_path$saveName$extension   ";
        return "$rel_path$saveName$extension";
    }

    public function getNextAvailableFilename($rel_path, $orig_file_name, $extension, &$errors) {

        //echo " ingetNextAvailableFilename";
        //  echo "$rel_path$orig_file_name$extension";
        if (file_exists("$rel_path$orig_file_name$extension")) {
            $i = 0;
            while (file_exists("$rel_path{$orig_file_name}_" . (++$i) . $extension) and $i < 10000) {
                
            }
            if ($i >= 10000) {
                $errors[] = "Can not create unique name for saving file $orig_file_name$extension";
                return false;
            }

            return $orig_file_name . "_" . $i;
        }
        return $orig_file_name;
    }

    public function returnJson($arr) {
        header('Content-type: application/json');
        print json_encode($arr);
        exit;
    }

    /**   Added for chunk upload */

    /**
     * This function is called for interactive file uploads ( sent by DropZoneJS )
     * It should save a file to Warehouse, then return it's ID to JS so the files ID's can be linked to the issue being submitted (or updated)
     */
    /*
      public function action_Upload()
      {
      if(!empty($_FILES)) {

      foreach ($_FILES as $file) {
      if ($file['error'] != 0) {
      $errors[] = array( 'text'=>'File error', 'error'=>$file['error'], 'name'=>$file['name']);
      continue;
      }
      if(!$file['tmp_name']){
      $errors[] = array( 'text'=>'Tmp file not found', 'name'=>$file['name']);
      continue;
      }

      $tmp_file_path = $file['tmp_name'];
      $filename =  (isset($file['filename']) )? $file['filename'] : $file['name'];

      if( isset($_POST['dzuuid'])){

      $chunks_res = $this->resumableUpload($tmp_file_path, $filename);
      if(!$chunks_res['final']){
      $this->returnJson( $chunks_res );
      }
      $tmp_file_path = $chunks_res['path'];
      //$file['tmp_name']=$tmp_file_path;
      }

      }




      $_FILES['file']['tmp_name']=$tmp_file_path;
      $_FILES['file']['size']=filesize($tmp_file_path);
      // var_dump($_FILES);


      // All DropZone uploaded files start as "pre". They are connected to the issue at verification time (If the issue is not "checked" and browser moves away, they become orphaned, and deleted by the system occasionally)
      $files_settings = array(
      'linked_with'=> 'pre',
      'visibility'=> 'public'
      );
      // When submitting from a logged in staff member, register those files under his/her ID (as private)
      if(isset(MVC_Core::$ss-> user['id'])) {
      $files_settings['user_id'] = MVC_Core::$ss-> user['id'];
      $files_settings['visibility'] = 'private'; // Files uploaded from staff account are not initially public - Instead they are set as private, until "Link_Pre_Files" method sets them to the final values.
      }

      // Process uploads: This will also update file counters for user and organization (including kbytes total), when these ID's exist (submitted by logged-in user)
      $uploads = $this-> file-> process_Uploads($files_settings, true); // true = Return detailed info
      $file = reset($uploads);
      $file['doctype'] = self::mimetype2doctype($file['mimetype'], $file['ext']);
      // Derive "Title" out of the file name
      $file['title'] = preg_replace('/\\.[^.\\s]{2,4}$/', '', $file['name']);

      if(count($uploads) > 0)
      $this-> send_JSON(array('success'=> true, 'id'=> (int)$file['id'], 'details'=> $file));
      else {
      header("HTTP/1.0 500 Fail");
      $this-> send_JSON(array('error'=> "File upload has failed."));
      }
      } else {
      header("HTTP/1.0 500 Fail");
      $this-> send_JSON(array('error'=> "No file sent to server."));
      }
      }
     */


    public function action_Upload() {
        if (empty($_FILES) || !isset($_FILES['file'])) {
            header("HTTP/1.0 500 Fail");
            $this->send_JSON(array('error' => "No file sent to server."));
            return;
        }

        $errors = array();
        $file = $_FILES['file'];

        if ($file['error'] != UPLOAD_ERR_OK) {
            header("HTTP/1.0 500 Fail");
            $this->send_JSON(array(
                'error' => "File upload error.",
                'code' => $file['error'],
                'name' => $file['name']
            ));
            return;
        }

        if (empty($file['tmp_name']) || !is_file($file['tmp_name'])) {
            header("HTTP/1.0 500 Fail");
            $this->send_JSON(array('error' => "Tmp file not found."));
            return;
        }

        $tmp_file_path = $file['tmp_name'];
        $filename = isset($file['filename']) ? $file['filename'] : $file['name'];

        if (isset($_POST['dzuuid'])) {
            $chunks_res = $this->resumableUpload($tmp_file_path, $filename);

            if (empty($chunks_res['final'])) {
                $this->returnJson($chunks_res);
                return;
            }

            if (empty($chunks_res['path']) || !is_file($chunks_res['path'])) {
                header("HTTP/1.0 500 Fail");
                $this->send_JSON(array('error' => "Completed upload file not found."));
                return;
            }

            $tmp_file_path = $chunks_res['path'];
        }

        $file_size = filesize($tmp_file_path);

        if ($file_size === false) {
            header("HTTP/1.0 500 Fail");
            $this->send_JSON(array('error' => "Unable to determine file size."));
            return;
        }

        $_FILES['file']['tmp_name'] = $tmp_file_path;
        $_FILES['file']['size'] = $file_size;

        $files_settings = array(
            'linked_with' => 'pre',
            'visibility' => 'public'
        );

        if (isset(MVC_Core::$ss->user['id'])) {
            $files_settings['user_id'] = MVC_Core::$ss->user['id'];
            $files_settings['visibility'] = 'private';
        }

        $uploads = $this->file->process_Uploads($files_settings, true);

        if (!is_array($uploads) || count($uploads) === 0) {
            header("HTTP/1.0 500 Fail");
            $this->send_JSON(array('error' => "File upload has failed."));
            return;
        }

        $uploaded_file = reset($uploads);

        if (!is_array($uploaded_file) || !isset($uploaded_file['id'], $uploaded_file['mimetype'], $uploaded_file['ext'], $uploaded_file['name'])) {
            header("HTTP/1.0 500 Fail");
            $this->send_JSON(array('error' => "Invalid upload response."));
            return;
        }

        $uploaded_file['doctype'] = self::mimetype2doctype(
                        $uploaded_file['mimetype'],
                        $uploaded_file['ext']
        );

        $uploaded_file['title'] = preg_replace(
                '/\\.[^.\\s]{2,4}$/',
                '',
                $uploaded_file['name']
        );

        $this->send_JSON(array(
            'success' => true,
            'id' => (int) $uploaded_file['id'],
            'details' => $uploaded_file
        ));
    }

    /**
     * This function is called for interactive file uploads to view
     * It should save a file to Warehouse, then return it's ID to JS so the files ID's can be linked to the issue being submitted (or updated)
     * returns file path url to view
     */
    public function action_Open_Upload() {
        if (!empty($_FILES)) {
            // All DropZone uploaded files start as "pre". They are connected to the issue at verification time (If the issue is not "checked" and browser moves away, they become orphaned, and deleted by the system occasionally)
            $files_settings = array(
                'linked_with' => 'pre',
                'visibility' => 'public'
            );
            // When submitting from a logged in staff member, register those files under his/her ID (as private)
            if (isset(MVC_Core::$ss->user['id'])) {
                $files_settings['user_id'] = MVC_Core::$ss->user['id'];
                $files_settings['visibility'] = 'public'; // Files uploaded from staff account are not initially public - Instead they are set as private, until "Link_Pre_Files" method sets them to the final values.
            }

            // Process uploads: This will also update file counters for user and organization (including kbytes total), when these ID's exist (submitted by logged-in user)
            $uploads = $this->file->process_Uploads($files_settings, true); // true = Return detailed info
            $file = reset($uploads);
            $file['doctype'] = self::mimetype2doctype($file['mimetype'], $file['ext']);
            // Derive "Title" out of the file name
            $file['title'] = preg_replace('/\\.[^.\\s]{2,4}$/', '', $file['name']);
            if (count($uploads) > 0) {
                if ($file['chash']) {
                    $protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                    $curWebUrl = $protocol . $_SERVER['HTTP_HOST'];
                    $this->send_JSON(array('location' => $curWebUrl . '/vich/' . $file['chash']));
                }
            } else {
                header("HTTP/1.0 500 Fail");
                $this->send_JSON(array('error' => "File upload has failed."));
            }
        } else {
            header("HTTP/1.0 500 Fail");
            $this->send_JSON(array('error' => "No file sent to server."));
        }
    }

    /**
     * Issue attachment download action - Can be called from Issue modules, or as a link from an e-mail
     *
     * @param MVC_Request $in GET parameters, should contain "track" = The TrackID of the issue to which this file is connected, and "ch" = Content Hash, to locate the file itself
     * -- Note: while files have a numeric ID, to avoid being able to download files which should not be accessible, the impossible to guess CHASH is used instead of the primary index (id)
     */
    public function action_Download_Attachment(MVC_Request $in) {
        $m_issue = new App_Model_Issue();

        // First check if the issue track ID actually exists and find the numeric ID
        $good_track_id = true; // start by assuming the user will enter a correct trackid
        $in->add_Filter('track', "Is_TrackID");

        /*
          if(!$in-> track) {
          // assumption was wrong...
          $good_track_id = false;
          if(isset($_GET['track']))
          $this-> evan-> add("No issue TrackID was found or the request is in an outdated format", "warning");
          } else {
          // Only needing the issue numeric ID
          $iss = $m_issue-> read($in-> track, array());
          if(!$iss) {
          // trackid looks ok, but the issue doesn't exist
          $good_track_id = false;
          $this-> evan-> add("File download attempt for an invalid issue tracking ID", "error");
          }
          }

          // Issue TrackID is good, check if the content hash is also valid for this specific issue
          if($good_track_id) {
          if($in-> ch) {
          $file_meta = $this-> file-> find(function(QueryFactory $q) use($in, $iss)
          {
          $q	-> where("`linked_with` = 'issue'")
          -> where("`linked_id` = ?", $iss['id'])
          -> where("`chash` = ?", $in-> ch);
          });

          if($file_meta)
          $file_id = $file_meta['id'];
          else
          $this-> evan-> add("The file could not be located, nothing to download.", "warning");

          } else
          $this-> evan-> add("File locator code is not present in the request, nothing to download.", "error");
          }
         */

        // New version doesn't need track id - Just the HASH; TODO: Figure out how to secure downloads later
        if ($in->ch) {
            $file_meta = $this->file->find(function (QueryFactory $q) use ($in) {
                $q->where("`chash` = ?", $in->ch);
                if ($in->qp)
                    $q->where("`id` = ?", MVC_Core::qp_conv($in->qp));
                else
                    $q->order("`id` ASC")->limit(1);
            });

            if ($file_meta)
                $file_id = $file_meta['id'];
            else
                $this->evan->add("The file could not be located, nothing to download.", "warning");
        } else
            $this->evan->add("File locator code is not present in the request, nothing to download.", "error");

        if (isset($file_meta) && is_array($file_meta)) {
            // Download the file if ID has been correctly located
            $r = $this->file->read_Content((int) $file_meta['id'], $file_meta);
            if ($r) {
                $updated_data = array(
                    'downloaded' => 1,
                    'file_name' => $file_meta['name']
                );
                $this->record->history_Create($file_meta['linked_id'], $updated_data, 'file', null, null);
                return;
            }
            $this->evan->add("The file was located, but there was an error when attempting to read it. Please contact system administrator.", "error");
        }

        // Various problems while attmepting to download the file, display an error page
        $this->prepare_Header("Download attachment", array("evan"));

        $this->site_section = "static" . DS . "download_error.html";
        ;
        $this->view("public.html");
    }

    /**
     * @param MVC_Request $in
     * This function is used to view the attachment (usually used in tinymce editor)
     */
    public function action_View_Attachment(MVC_Request $in) {
        $m_issue = new App_Model_Issue();

        // First check if the issue track ID actually exists and find the numeric ID
        $good_track_id = true; // start by assuming the user will enter a correct trackid
        $in->add_Filter('track', "Is_TrackID");
        // New version doesn't need track id - Just the HASH; TODO: Figure out how to secure downloads later
        if ($in->ch) {
            $file_meta = $this->file->find(function (QueryFactory $q) use ($in) {
                $q->where("`chash` = ?", $in->ch);
                if ($in->qp)
                    $q->where("`id` = ?", MVC_Core::qp_conv($in->qp));
                else
                    $q->order("`id` ASC")->limit(1);
            });
            if ($file_meta)
                $file_id = $file_meta['id'];
            else
                $this->evan->add("The file could not be located, nothing to download.", "warning");
        } else
            $this->evan->add("File locator code is not present in the request, nothing to download.", "error");

        if (isset($file_meta) && is_array($file_meta)) {
            // Download the file if ID has been correctly located
            $r = $this->file->view_Content((int) $file_meta['id'], $file_meta);
            $this->evan->add("The file was located, but there was an error when attempting to read it. Please contact system administrator.", "error");
        }

        // Various problems while attmepting to download the file, display an error page
        $this->prepare_Header("View attachment", array("evan"));

        $this->site_section = "static" . DS . "download_error.html";
        ;
        $this->view("public.html");
    }

    private static function mimetype2doctype(&$mimetype, $ext) {
        // Recognise some mimetypes and extensions and translate them into human text
        // TODO: The variations on mimetype should be saved/changed on upload, and not while viewing lists...
        // TODO: Put this into NLS
        switch ($mimetype) {
            case 'application/msexcel': case 'application/vnd.ms-excel': $doctype = "Microsoft Excel Document (XLS, XLSX)";
                break;
            case 'application/msword': case 'application/vnd.ms-word': $doctype = "Microsoft Word Document (DOC, DOCX)";
                break;
            case 'application/mspowerpoint': case 'application/vnd.ms-powerpoint': $doctype = "Microsoft PowerPoint Document (PPT, PPTX)";
                break;
            case 'application/pdf': $doctype = "Adobe Acrobat Document (PDF)";
                break;
            case 'application/photoshop': case 'image/vnd.adobe.photoshop': $doctype = "Adobe Photoshop Image (PSD)";
                break;
            case 'application/rar': case 'application/x-rar': $doctype = "WinRAR Archive (RAR)";
                break;
            case 'application/zip': switch ($ext) {
                    case 'cdr':
                        $mimetype = "image/vnd.corel.draw";
                        $doctype = "Corel Draw Document (CDR)";
                        break;
                    default:
                        $doctype = "WinZIP Archive (ZIP)";
                };
                break;
            case 'application/x-gzip': $doctype = "Linux G-Zip Package (GZ, GZIP)";
                break;
            case 'application/x-dosexec': $doctype = "Windows Executable (EXE)";
                break;
            case 'application/vnd.ms-opentype': $doctype = "Open-Type Font (OTF)";
                break;
            case 'application/xml': $doctype = "eXtended Markup Language (XML)";
                break;
            case 'image/jpeg': $doctype = "JPEG Photograph (JPG, JPEG)";
                break;
            case 'image/png': $doctype = "PNG Lossless Picture (PNG)";
                break;
            case 'image/tiff': $doctype = "Scanned Document/Image (TIF, TIFF)";
                break;
            case 'image/x-ms-bmp': $doctype = "Bitmap Picture (BMP)";
                break;
            case 'image/x-icon': $doctype = "Windows Icon (ICO)";
                break;
            case 'image/gif': $doctype = "Graphics Interchange Format (GIF)";
                break;
            case 'message/rfc822': $doctype = "Saved E-mail (EML)";
                break;
            case 'text/csv': $doctype = "Comma Separated Values Table (CSV)";
                break;
            case 'text/html': $doctype = "Hypertext Document (HTML, XML)";
                break;
            case 'text/javascript': $doctype = "JavaScript Code (JS)";
                break; // Doesn't seem to be recognized properly?
            case 'text/rtf': $doctype = "Rich-Text Format (RTF)";
                break;
            case 'text/plain': switch ($ext) {
                    case 'js':
                        $mimetype = "text/javascript";
                        $doctype = "JavaScript Code (JS)";
                        break;
                    case 'json':
                        $mimetype = "text/json";
                        $doctype = "JavaScript Object (JSON)";
                        break;
                    case 'sql';
                        $mimetype = "text/sql";
                        $doctype = "Structured Query Language (SQL)";
                        break;
                    default:
                        $doctype = "Simple text document (TXT)";
                };
                break;
            case 'text/x-c++': switch ($ext) {
                    case 'php':
                        $mimetype = "text/php";
                        $doctype = "Hypertext Preprocessor Code (PHP)";
                        break;
                    default:
                        $doctype = "C++ like Code (C, CPP)";
                };
                break;
            default:
                if (substr($mimetype, 0, 9) == "Composite") {
                    if ($ext == 'msg') {
                        $mimetype = "application/vnd.ms-outlook";
                        $doctype = "Microsoft Outlook Message (MSG)";
                    } else {
                        $mimetype = "unknown";
                        $doctype = "Composite Document (Unknown)";
                        break;
                    }
                } else {
                    $mimetype = "unknown";
                    $doctype = "Unrecognized file format";
                }
        }

        return $doctype;
    }

    /**
     * Reads the notes associated to an item, filtering the result by what the logged in user should see
     *
     * @note: While files are accesible outside of staff accounts, this is the action for an ajax request on a file manager grid.
     * Public view of files usually comes from different sources (reading the issue, or the communication)
     */
    public function action_Get_List(MVC_Request $rq) {
        if (empty(MVC_Core::$ss->user))
            return header("HTTP/1.0 403 This method is only available while logged in");

        if ($this->user->is_Unrestricted())
            $r = $this->file->get_List($rq->link, $rq->link_id, array('file', 'meta', 'extra', 'extra_user', 'extra_organization')); // Admin reads all linked files, no limits, including private ones!
        else
            $r = $this->file->get_List($rq->link, $rq->link_id, array('file', 'meta', 'extra', 'extra_user'), function (QueryFactory &$q) use ($rq) {
                $user = MVC_Core::$ss->user;
                if (!empty($user)) {
                    $q->where("CASE WHEN `fil`.visibility = 'org' THEN `fil`.org_id = ? ELSE 1 END", $user['org_id']);
                    $q->where("CASE WHEN `fil`.visibility = 'shared' THEN FIND_IN_SET(?, `fil`.access_orgs) ELSE 1 END", $user['org_id']);

                    if (!is_null($user['cdep_id']))
                        $q->where("CASE WHEN `fil`.visibility = 'dep' THEN `fil`.dep_id = ? ELSE 1 END", $user['cdep_id']);

                    $q->where("CASE WHEN `fil`.visibility = 'private' THEN `fil`.user_id = ? ELSE 1 END", $user['id']);
                }
            }, null, "`fil`.`" . ($rq->order ?: "name") . "` ASC");

        if (!$r || count($r) == 0)
            return $this->send_JSON(array());

        $files = array();
        foreach ($r as $file) {
            // On-demand post-upload analyzer check - Is the file matching an analyzer path? If yes, do the analisys just like the file has been uploaded
            $this->file->analyze($file);

            // A bit of post-processing
            $file['id'] = (int) $file['id'];
            if (!empty($file['linked_id']))
                $file['linked_id'] = (int) $file['linked_id'];
            $file['mtime'] = date("Y-m-d H:i:s", $file['mtime']);
            $file['size'] = (int) $file['size'];
            if (!empty($file['dep_id']))
                $file['dep_id'] = (int) $file['dep_id'];

            // Move User and Organization ID's into their sub-arrays
            if (isset($file['user'])) {
                $file['user']['id'] = empty($file['user_id']) ? null : (int) $file['user_id'];
                if (!empty($file['user']['contact_id']))
                    $file['user']['contact_id'] = (int) $file['user']['contact_id'];
                unset($file['user_id']);
            }
            if (isset($file['org'])) {
                $file['org']['id'] = empty($file['org_id']) ? null : (int) $file['org_id'];
                if (!empty($file['org']['contact_id']))
                    $file['org']['contact_id'] = (int) $file['org']['contact_id'];
                unset($file['org_id']);
            }

            $file['doctype'] = self::mimetype2doctype($file['mimetype'], $file['ext']);
            // Combine some values into a "title" to be shown in a grid
            $file['title'] = !empty($file['description']) ? $file['description'] : preg_replace('/\\.[^.\\s]{2,4}$/', '', $file['name']);

            $files[] = $file;
        }

        $this->send_JSON($files);
    }

    /**
     * A combination of Get_List and Download, this one reads the list and builds a ZIP archive out of it then presents it for download.
     * Could be very useful for records with 10+ attachments (saving time to download each one individually)
     *
     * @param MVC_Request $rq
     */
    public function action_Download_ZIP_Package(MVC_Request $rq) {
        if (empty(MVC_Core::$ss->user))
            return header("HTTP/1.0 403 This method is only available while logged in");

        $user = MVC_Core::$ss->user;
        if ($this->user->is_Unrestricted())
            $r = $this->file->get_List($rq->link, $rq->link_id, array('file', 'meta')); // Admin reads all linked files, no limits, including private ones!
        else
            $r = $this->file->get_List($rq->link, $rq->link_id, array('file', 'meta'), function (QueryFactory &$q) use ($rq, $user) {
                if (!empty($user)) {
                    $q->where("CASE WHEN `fil`.visibility = 'org' THEN `fil`.org_id = ? ELSE 1 END", $user['org_id']);
                    $q->where("CASE WHEN `fil`.visibility = 'shared' THEN FIND_IN_SET(?, `fil`.access_orgs) ELSE 1 END", $user['org_id']);

                    if (!is_null($user['cdep_id']))
                        $q->where("CASE WHEN `fil`.visibility = 'dep' THEN `fil`.dep_id = ? ELSE 1 END", $user['cdep_id']);

                    $q->where("CASE WHEN `fil`.visibility = 'private' THEN `fil`.user_id = ? ELSE 1 END", $user['id']);
                }
            }, null, "`fil`.`" . ($rq->order ?: "name") . "` ASC");

        if (!$r || count($r) == 0)
            $this->evan->add("No files found, there is nothing to pack into a ZIP Download.", "error");
        else {
            // ZIP package is a temporary file which receives a name based on the the date when the package is created and the record ID
            // TODO: Find a way to reuse this cache (for example, by saving the package ID within the record itself), to avoid making the server construct the zip again...
            $zipfile = ROOT . DS . 'cache' . DS . 'zip' . DS . sprintf("rz-%s-r%d-u%d.zip", date("Y_m_d-H_i_s"), $rq->link_id, $user['id']);
            $zip = new ZipArchive();
            if ($zip->open($zipfile, ZIPARCHIVE::CREATE) !== true) {
                $this->evan->add("Error encountered while attempting to create the ZIP.", "error");
            } else {

                foreach ($r as $file_meta) {
                    $this->file->get_Real_Path($file_meta);
                    if ($file_meta['fullpath'] === false)
                        continue; // Skip files that were not detected (no longer on the filesystem)

                    $zip->addFile($file_meta['fullpath'], $file_meta['name']);
                }
                $zip->close();
                unset($zip);

                if (!file_exists($zipfile))
                    $this->evan->add("Error encountered while attempting to create the ZIP.", "error");
                else {
                    $updated_data = array(
                        'all_downloaded' => 1
                    );
                    $this->record->history_Create($rq->link_id, $updated_data, 'file', null, null);
                    // Success, prepare the file for downloading
                    header("Content-Type: application/force-download");
                    header("Content-Disposition: attachment; filename=\"ZIP Package {$rq->track} " . date("Y-m-d H_i_s") . ".zip\"");
//					header("Content-Length: ".(string)filesize($zipfile)); // For some really strange reason this doesn't seem to be working o.O ... file is cut
                    // Disable all caching
                    header("Pragma: public");
                    header("Pragma: no-cache");
                    header("Expires: 0");
                    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
                    header("Cache-Control: private", false); // required for certain browsers

                    return @readfile($zipfile);
                }
            }
        }

        $this->prepare_Header("Download ZIP Package", array("evan"));
        $this->site_section = "static" . DS . "download_error.html";
        ;
        $this->view("public.html");
    }

    /**
     * Updates some data for an existing file (visibility, user/dep/org ID, name, description, labels)
     * -- File linking, extension, path, size, chash and other values related to the content are not editable
     *
     * FIXME: This shouldn't be here... actually, the entire "file" control module should not be in /public, but in /global
     */
    public function action_Update(MVC_Request $post) {
        if (empty(MVC_Core::$ss->user))
            return header("HTTP/1.0 403 This method is only available while logged in");

        if (empty($post->id))
            return header("HTTP/1.0 400 File identifier was found in the request");

        // A bit of pre-processing of the file data
        // - trim and cut name+extension to less than 160 chars, same goes for description )
        // - break the labels and trim each one of them, also checking for duplicates (if same label is used more than once, it is ignored)
        $file_update = $post->file;

        if (empty($file_update['name']) || strlen($file_update['name']) < 5 || strlen($file_update['name']) >= 160)
            return header("HTTP/1.0 400 The file name is empty, too short or too long");
        // Remove invalid characters and trim:
        $file_update['name'] = str_replace(array('\\', '/', ':', '*', '?', '"', '<', '>', '|'), '_', trim($file_update['name']));

        if (!empty($file_update['description']))
            $file_update['description'] = trim($file_update['description']);
        if (!empty($file_update['labels']))
            $file_update['labels'] = self::filter_Labels($file_update['labels']);

        // Read the file's metadata so it can be checked for security and compared with the updates
        $file = $this->file->find($post->id);
        if (empty($file))
            return header("HTTP/1.0 404 The file cannot be accessed, it may have been deleted.");

        // Compare new meta with current file meta
        foreach ($file_update as $key => $value) {
            if ($value == $file[$key])
                unset($file_update[$key]);
        }

        // If the file doesn't end in the extension of the original file, append that extension automatically (aka, forbid changing of the extension)
        if (isset($file_update['name']) && substr($file_update['name'], -strlen($file['ext']) - 1) !== '.' . $file['ext'])
            $file_update['name'] .= '.' . $file['ext'];

        if (empty($file_update))
            return header("HTTP/1.0 409 No values were changed. There's nothing to update !");

        if (!empty($file_update['visibility'])) {
            // When visibility is changed, some extra conditions need to be verified
            switch ($file_update['visibility']) {
                case 'private':
                    // You can't force the file to look as if uploaded by yourself or only part of a department ... so it can't be made private
                    if (empty($file['user_id']))
                        return header("HTTP/1.0 403 This file was uploaded externally, it cannot be made private.");

                    if ($file['user_id'] != MVC_Core::$ss->user['id'])
                        return header("HTTP/1.0 403 The file can only be set private by the uploader him(her)self.");

                    break;
                case 'dep':
                    if (empty($file['dep_id']))
                        return header("HTTP/1.0 403 This file was uploaded externally, it cannot be made private to departments.");

                    if ($file['dep_id'] != MVC_Core::$ss->user['cdep_id'])
                        return header("HTTP/1.0 403 The file can only be set private to the department if the uploader dep. is the same as yours.");
                    break;
                case 'shared':
                    if (empty($file_update['access_orgs']))
                        $file_update['access_orgs'] = MVC_Core::$ss->user['org_id']; // At minimum, the user's own org should be in the list of access_orgs
                    break;
                case 'org':
                    // If the file didn't have an owner organization before, just set the owner to current user's org. This is a bit insecure, it allows "stealing" public files, but it will have to do for now
                    if (empty($file['org_id']))
                        $file_update['org_id'] = (int) MVC_Core::$ss->user['org_id'];
                    break;
            }
        }

        // TODO: Check if the user is allowed to modify this file
        $r = $this->file->update_Extra($post->id, $file_update, $post->link_id);
        if (!$r)
            return header("HTTP/1.0 500 Error - Could not update the file");

        $this->send_JSON(array("success" => true, "response" => "The file with id {$post->id} has been updated."));
    }

    /**
     * Filters labels for a file, trimming each one and removing duplicats
     * @param string $raw_label_string
     */
    private static function filter_Labels($raw_label_string) {
        $labels = explode(',', $raw_label_string);
        $filtered_labels = array();
        $used_labels = array();
        foreach ($labels as $label) {
            $label = trim($label);
            if (in_array(strtolower($label), $used_labels))
                continue;
            $filtered_labels[] = $label;
            $used_labels[] = strtolower($label);
        }
        return implode(',', $used_labels);
    }

    /**
     * Removes a file from the warehouse, as the action of clicking the "Remove File" button in DropZone
     * Only supports deleting "PRE" files, so the ones that were just uploaded.
     * -- Already connected files need a better way to be removed/disconnected
     */
    public function action_Remove(MVC_Request $rq) {
        if ($rq->qp)
            $id = (int) MVC_Core::qp_conv($rq->qp);
        elseif ($rq->id)
            $id = (int) $rq->id;
        elseif (!$rq->ids)
            return header("HTTP/1.0 400 File ID(s) missing from the request. Possible interface error.");

        if ($rq->ids) {
            // Removing multiple "pre" files
            $file_ids = explode(',', $rq->ids);
            $c = 0;
            foreach ($file_ids as $id) {
                if ($this->file->delete($id, 'pre'))
                    $c++;
            }
            if ($c == count($file_ids))
                return $this->send_JSON(array('success' => true));
            else
                return header("HTTP/1.0 404 Some of the files in the list were not successfully removed.");
        } elseif (!empty($id)) {
            // Removing one single "pre" file
            $r = $this->file->delete($id, 'pre');
            if ($r)
                return $this->send_JSON(array('success' => true));
            else
                return header("HTTP/1.0 404 File could not be found or no access to it.");
        } else
            return header("HTTP/1.0 400 Invalid request parameters");
    }

    /**
     * Creates new references of existing files as "pre" files. Return the new references ids.
     * This is used to link existing files to new object (without the need to reattach those files)
     */
    public function action_CopyToPre(MVC_Request $rq) {
        if (empty(MVC_Core::$ss->user))
            return header("HTTP/1.0 403 This method is only available while logged in");

        $copied_files = $this->file->copy_Ref(explode(',', $rq->ids), array(
            'linked_with' => "pre",
            'linked_id' => null,
            'visibility' => "public",
            'user_id' => $this->user->ID(),
            'org_id' => MVC_Core::$ss->user['org_id']
        ));
        if (empty($copied_files))
            return header("HTTP/1.0 404 Files were not found or another error occured");

        foreach ($copied_files as &$file) {
            $file['doctype'] = self::mimetype2doctype($file['mimetype'], $file['ext']);
            $file['title'] = !empty($file['description']) ? $file['description'] : preg_replace('/\\.[^.\\s]{2,4}$/', '', $file['name']);
        }

        return $this->send_JSON(array('success' => true, 'files' => $copied_files));
    }

    /**
     * Returns the list of files in the Clipboard, to be used in a grid
     * @param MVC_Request $rq
     */
    public function action_Clipboard(MVC_Request $rq) {
        if (empty(MVC_Core::$ss->user))
            return header("HTTP/1.0 403 This method is only available while logged in");

        $r = $this->file->clipboard_List($this->user->ID());
        if (!$r || count($r) == 0)
            return $this->send_JSON(array());

        $clipboard = array();
        foreach ($r as $clip) {
            // A bit of post-processing
            $clip['file_id'] = (int) $clip['file_id'];
            $clip['size'] = (int) $clip['size'];

            $clip['doctype'] = self::mimetype2doctype($clip['mimetype'], $clip['ext']);
            $clipboard[] = $clip;
        }

        return $this->send_JSON($clipboard);
    }

    /**
     * Removes a single file reference from the Clipboard
     *
     * @param MVC_Request $rq
     */
    public function action_Remove_Clips(MVC_Request $rq) {
        if (empty(MVC_Core::$ss->user))
            return header("HTTP/1.0 403 This method is only available while logged in");

        if (isset($rq->empty_clipboard))
            $this->file->clipboard_Empty($this->user->ID());
        else
            $this->file->clipboard_Detach($this->user->ID(), @json_decode($rq->file_ids));

        return $this->send_JSON(array('success' => true));
    }

    /**
     * Creates a file in the warehouse structure that gets it's content from a record "Document"
     * At the moment it creates a HTML file by wrapping the document with a header and footer
     *
     * @param MVC_Request $rq
     */
    public function action_ImportDocument(MVC_Request $rq) {
        if (empty(MVC_Core::$ss->user))
            return header("HTTP/1.0 403 This method is only available while logged in");

        if (!$rq->id)
            return header("HTTP/1.0 400 Document ID missing from the request. Possible interface error.");

        // Read document
        $m_note = $this->factory_Model("Note");
        $doc = $m_note->read($rq->id);
        if (!$doc)
            return header("HTTP/1.0 404 Requested document was not found (perhaps it was already deleted?)");

        // What kind of imports are supported?
        switch ($rq->type) {
            // The "HTML" import type incapsulates the document into a basic HTML file template, then stores the result as the new file
            case "html":
                $extension = "html";
                $mimetype = "text/html";

                $this->init_Template_System();
                $file = $this->file->create_From_String(array(
                    'name' => mb_ereg_replace('([\.]{2,})', '', mb_ereg_replace('([^\w\s\d\-_~,;\[\]\(\).])', '', $doc['title'])) . '.' . $extension,
                    'description' => $doc['title'],
                    'ext' => $extension,
                    'mimetype' => $mimetype,
                    'linked_with' => "pre",
                    'linked_id' => null,
                    'visibility' => "public",
                    'user_id' => MVC_Core::$ss->user['id'],
                    'org_id' => MVC_Core::$ss->user['org_id']
                        ), $this->_drytpl->render_File("document_file_container.html", $doc));
                break;
            default:
                $extension = "text";
                $mimetype = "text/plain";
                // Not implemented yet, specify type !
                return header("HTTP/1.0 500 The document import type is not specified.");
        }

        // Only used for the visual representation of the file meta
        $file['doctype'] = self::mimetype2doctype($file['mimetype'], $file['ext']);
        $file['title'] = $doc['title'];
        $file['original_id'] = $doc['id'];

        return $this->send_JSON(array('file' => $file));
    }

    //        IWI Create a files upload section in task start  
    public function action_TaskFileUpload($link_id) {

//            print_r($_POST);
//            print_r($_FILES);
////            
//            exit();
        if (!empty($_FILES)) {

            foreach ($_FILES as $file) {
                if ($file['error'] != 0) {
                    $errors[] = array('text' => 'File error', 'error' => $file['error'], 'name' => $file['name']);
                    continue;
                }
                if (!$file['tmp_name']) {
                    $errors[] = array('text' => 'Tmp file not found', 'name' => $file['name']);
                    continue;
                }

                $tmp_file_path = $file['tmp_name'];
                $filename = (isset($file['filename']) ) ? $file['filename'] : $file['name'];

                if (isset($_POST['dzuuid'])) {

                    $chunks_res = $this->resumableUpload($tmp_file_path, $filename);
                    if (!$chunks_res['final']) {
                        $this->returnJson($chunks_res);
                    }
                    $tmp_file_path = $chunks_res['path'];
                    //$file['tmp_name']=$tmp_file_path;
                }
            }




//            $_FILES['file']['tmp_name'] = $tmp_file_path; 
//            $_FILES['file']['size'] = filesize($tmp_file_path);
            // var_dump($_FILES);
            // All DropZone uploaded files start as "pre". They are connected to the issue at verification time (If the issue is not "checked" and browser moves away, they become orphaned, and deleted by the system occasionally)
            $files_settings = array(
                'linked_with' => 'issue',
                'visibility' => 'org',
                'linked_id' => $link_id,
                'org_id' => $_POST['org_id'],
                'access_orgs' => $_POST['org_id'],
            );
            // When submitting from a logged in staff member, register those files under his/her ID (as private)
            if (isset(MVC_Core::$ss->user['id'])) {
                $files_settings['user_id'] = MVC_Core::$ss->user['id'];
                $files_settings['visibility'] = 'org'; // Files uploaded from staff account are not initially public - Instead they are set as private, until "Link_Pre_Files" method sets them to the final values.
            }

            // Process uploads: This will also update file counters for user and organization (including kbytes total), when these ID's exist (submitted by logged-in user)
            $uploads = $this->file->process_Uploads($files_settings, true); // true = Return detailed info
            $file = reset($uploads);
            $file['doctype'] = self::mimetype2doctype($file['mimetype'], $file['ext']);
            // Derive "Title" out of the file name
            $file['title'] = preg_replace('/\\.[^.\\s]{2,4}$/', '', $file['name']);

            if (count($uploads) > 0)
                return $uploads;
            else {
                return array();
            }
        }
    }

    //        IWI Create a files upload section in task end
}
