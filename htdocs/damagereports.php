<?php
ini_set("display_errors", 1);
error_reporting(1);
class damagereports
{

    public $con;

    public $trackid;

    public function __construct()
    {
    
        /* $servername = "3.108.122.241";
        $username = "cargoits_v10";
        $password = "cargoits1@3$";
        $database = "cargoits_v10";*/

		// $servername = "5.61.249.25";
		// $username = "cargoits_v10";
		// $password = "cY7TSyuC6uDNCexr";
		// $database="cargoits_v10";
		
		
					$servername = 'localhost';
			$database = 'cargoits_v10';
			$username = 'cargoits_v10';
			$password = '7e31100dc9f66856';

               
        // Create connection
        $this->con = mysqli_connect($servername, $username, $password, $database);
        $this->con = $this->con;
        return $this->con;
    }

    public function its2restapi()
    {
        ini_set('memory_limit', '2048M');
        ini_set('max_execution_time', 0);
        set_time_limit(0);
        
        // mysqli_autocommit($this->con,FALSE);
        
        $ftp_server = "15.207.45.56";
        $ftp_user_name = "damagereports";
        $ftp_user_pass = "4@m@53~R3p0";
        $ftp_conn = ftp_connect($ftp_server);
        $login_result = ftp_login($ftp_conn, $ftp_user_name, $ftp_user_pass);
         
       
        
        if ($login_result) {
             $datamessage = array();
          ftp_pasv($ftp_conn, true);
            $file_list = ftp_nlist($ftp_conn, "files/");
              
             // var_dump($file_list);
              
            foreach ($file_list as $fk => $fv) {
              
 
              $res=  $this->readFolders($fv,$ftp_conn);
           
            } // foreach end $file_list1
             $datamessage['status'] = 1;
            $datamessage['responsecode'] = 1;
            $datamessage['message'] = "Records moved successfully";
        } else {
            $datamessage["message"] = "ftp not connected";
            // mysqli_rollback($this->con);
        }
         echo json_encode($datamessage);
      // echo json_encode($res);
    }
    
    
    
    
     

    function readFolders($fv, $ftp_conn)
    {
        $fileval = explode('/', $fv);
        $file_list1 = ftp_nlist($ftp_conn, "files/" . $fileval[1]);
           $jsfiles = array();
            $imgfiles = array();
            $pdffiles = array();
            $pathfiles = array();
            $datamessage = array();
            $sha1_url = array();
        foreach ($file_list1 as $fk1 => $fv1) {
            $fileval1 = explode('/', $fv1);
            $file_list2 = ftp_nlist($ftp_conn, "files/" . $fileval[1] . "/" . $fileval1[2]);
            foreach ($file_list2 as $key => $value) {
                $fileexp = explode("/", $value);
                
                if ($fileexp[2] != "success" && $fileexp[2] != "failure") {
                    echo $ext = pathinfo($fileexp[3], PATHINFO_EXTENSION);
                    
                    if ($ext == 'json') {
                        $jsfiles[] = $fileexp[3] . '~' . $fileexp[1]; // $fileexp[0]---damagereports [1]---demo handeler [2]---folder of files [3]----files path(json,image,pdfs)
                        $pathfiles[] = $fileexp[2];
                    }
                    if ($ext == 'jpeg' || $ext == 'gif') {
                        $imgfiles[] = $fileexp[2] . '~' . $fileexp[3] . '~' . $fileexp[1];
                        $imgpath = $fileexp[2];
                        // echo json_encode($fileexp);
                       // $sha1_url[] = 'damagereports/' . $fileexp[1] . '/' . $fileexp[2] . '/' . $fileexp[3];
                        // echo 'damagereports/' . $fileexp[1] . '/' . $fileexp[2] . '/' . $fileexp[3];
                         $sha1_url[] = sha1_file('files/' . urlencode($fileexp[1]) . '/' . str_replace(" ", "%20", $fileexp[2]) . '/' . str_replace(" ", "%20", $fileexp[3]));
                    }
                    if ($ext == 'pdf')
                        $pdffiles[] = str_replace(" ", "%20", $fileexp[2]) . '~' . str_replace(" ", "%20", $fileexp[3]) . '~' . urlencode($fileexp[1]);
                        $pdfpath = $fileexp[2];
                }
            } // foreach end $file_list2
        }

 var_dump($pdffiles);
 // exit(); 
    
       return $this->jsFile($jsfiles,$ftp_conn,$pathfiles,$sha1_url,$pdffiles,$imgfiles);
    }

   

    public function jsFile($jsfiles,$ftp_conn,$pathfiles,$sha1_url,$pdffiles,$imgfiles)
    {

        $issue_ids = array();
        $mawb_ids = array();

        if (sizeof($jsfiles) > 0) {
                    
            foreach ($jsfiles as $jk => $jv) {

                
                $ftppath = explode("~", $jv);

                $path = 'files/' . $ftppath[1] .'/'. $pathfiles[$jk] . '/' . $ftppath[0];
     
                $a = tmpfile();
                $metaData = stream_get_meta_data($a);
                // ftp_fget($ftp_conn, $a, 'cargoclaim1/Demo Airlines_11/Demo Airlines_20220118085708.json', FTP_BINARY, 0);
                ftp_fget($ftp_conn, $a, $path, FTP_BINARY, 0);
                fseek($a, 0);
                $content = fread($a, filesize($metaData['uri']));
                fclose($a);
                $jsondata = json_decode($content, true);
                        

               echo $master_org_id_qry = "select id from organizations where name like '%" . $jsondata['orgInfo']['master_org_id'] . "%'";
                  
                $master_org_id_res = mysqli_query($this->con, $master_org_id_qry);
                $master_org_id_row = mysqli_fetch_assoc($master_org_id_res);
                $master_org_id = $master_org_id_row['id'];
              
              echo  $group_id_qry = "select id from groups where name like '%" . $jsondata['orgInfo']['group_id'] . "%'";
                $group_id_res = mysqli_query($this->con, $group_id_qry);
                $group_id_row = mysqli_fetch_assoc($group_id_res);
                $group_id = $group_id_row['id'];
                                    
                // $master_org_id=1;
                // +$group_id=33;
                
                $trackid = $this->get_trackid();
                $access_key = $this->get_access_key();
                
                

                if ($master_org_id > 0 && $group_id > 0) {
                    $trackid = $this->get_trackid();
                    $access_key = $this->get_access_key();
                    $keyarray = array();
                    $valarray = array();
                    $dep = array();

                    foreach ($jsondata['damageCategoryItems'] as $keyj => $valuej) {
                        foreach ($valuej as $key1 => $value1) {
                            if ($key1 != 'reportId' && $key1 != 'status' && $key1 != 'jsonStatus' && $key1 != 'capaType') {
                                array_push($keyarray, $key1);
                                array_push($valarray, $value1);
                            }
                        }
                    }
                     var_dump($keyarray);
                    $keys = array();
                    foreach ($keyarray as $key2 => $val2) {
                      echo  $data_qry = "select id from issue_xfdef where fk='" . $val2 . "' and ist_id=4";
                        // echo $data_qry.'<br/>';
                        $data_res = mysqli_query($this->con, $data_qry);
                        $data_row = mysqli_fetch_assoc($data_res);
                        $data = $data_row['id'];
                        $keys[] = json_decode(json_encode($data), true);
                    }


                  echo   $data_qry_bk = "select id, track, cache_primary from issues where cache_primary='" . $jsondata['damageCategoryItems'][0]['mawb'] . "'";
                    $data_res_bk = mysqli_query($this->con, $data_qry_bk);
                    $data_row_bk = mysqli_fetch_assoc($data_res_bk);
                    $data_track = $data_row_bk['track'];
                    $data_issue_id = $data_row_bk['id'];
                    $data_awb = $data_row_bk['cache_primary'];

                    if(!empty($data_track)){

                    $time = strtotime(date('Y-m-d H:i:s'));
                  echo   $issue_qry = "update issues SET ctime = " . strtotime(date('Y-m-d H:i:s')) . ", submit_ip = 1, group_id = '" . $group_id . "', master_org_id = '" . $master_org_id . "', cache_primary = '" . $jsondata['damageCategoryItems'][0]['mawb'] . "', submit_org_id = '" . $master_org_id . "' where track='" . $data_track . "'";
                    $issue_res == mysqli_query($this->con, $issue_qry);
                    $issue_id = $data_issue_id;
                    }else{

                    $time = strtotime(date('Y-m-d H:i:s'));
                   echo $issue_qry = "insert into issues(track,access_key,type_id,ctime,submit_ip,group_id,master_org_id,cache_primary,submit_org_id) values('" . $trackid . "','" . $access_key . "',4," . strtotime(date('Y-m-d H:i:s')) . ",0,$group_id,$master_org_id,'" . $jsondata['damageCategoryItems'][0]['mawb'] . "',$master_org_id)";
                    $issue_res == mysqli_query($this->con, $issue_qry);
                    $issue_id = mysqli_insert_id($this->con);

                    }    
                    
                    
                         
                    foreach ($keys as $ik => $iv) {
                        if (isset($keys[$ik])) {
                            
                            $textval = "";
                            $intval = "";
                            if (is_array($valarray[$ik])) {
                            
                            
                                // $lastkey=array_key_last($valarray[$ik]);
                                $lastkey = count($valarray[$ik]) - 1;
                                $textval .= '[';
                                foreach ($valarray[$ik] as $valk => $valv) {
                                    if ($valk != $lastkey)
                                        $textval .= '"' . $valv . '",';
                                    else
                                        $textval .= '"' . $valv . '"';
                                }
                                $textval .= ']';
                            } else {
                                
                                if (preg_match('/[A-Za-z-]/', $valarray[$ik])) {
                                    $textval = str_replace("\'", "", $valarray[$ik]);
                                } else {
                                    $intval = $valarray[$ik];
                                }
                            }
                            if ($keyarray[$ik] == "airp_dep" || $keyarray[$ik] == "airp_dest") {
                                $airpotidsdata_qry = "select id from data_airports where IATA like '%" . $valarray[$ik] . "%'";
                                $airpotidsdata_res = mysqli_query($this->con, $airpotidsdata_qry);
                                $airpotidsdata_row = mysqli_fetch_assoc($airpotidsdata_res);
                                $intval = $airpotidsdata_row['id'];
                                $textval = "";
                            }
                            if ($intval == null || empty($intval) || $intval == "")
                                $intval = 0;
                            if ($textval == null || empty($textval) || $textval == "")
                                $textval = null;
                            if(!empty($data_issue_id)){
                               //echo  $del_issue_fields_qry = "delete from issue_fields where issue_id = '" . $data_issue_id . "'";
                               // $del_issue_fields_res = mysqli_query($this->con, $del_issue_fields_qry);

                               echo $issue_fields_qry = "insert into issue_fields(issue_id,fk_id,int_value,value) values('" . $data_issue_id . "',$keys[$ik],$intval,'" . $textval . "')";
                                $issue_fields_res = mysqli_query($this->con, $issue_fields_qry);
                            }else{
                              echo  $issue_fields_qry = "insert into issue_fields(issue_id,fk_id,int_value,value) values($issue_id,$keys[$ik],$intval,'" . $textval . "')";
                                $issue_fields_res = mysqli_query($this->con, $issue_fields_qry);
                            }

                        }
                    } // end of foreach
                    
                    if(!empty($data_issue_id)){
                        $orgdata_qry = "update issue_orgdata set org_id = '" . $master_org_id . "',is_enabled = 1, process = '" . $jsondata['damageCategoryItems'][0]['status'] . "',linktime = '" . strtotime(date('H:i:s')) . "', priority_key = 'low', status_key = 'new' where issue_id = '" . $data_issue_id . "'";
                        $orgdata_res = mysqli_query($this->con, $orgdata_qry);
                    }else{
                        $orgdata_qry = "insert into issue_orgdata(issue_id,org_id,is_enabled,process,linktime,priority_key,status_key)  values($issue_id,$master_org_id,1,'" . $jsondata['damageCategoryItems'][0]['status'] . "','" . strtotime(date('H:i:s')) . "','5low','new')";
                        $orgdata_res = mysqli_query($this->con, $orgdata_qry);
                    }

                         
                    array_push($mawb_ids, $jsondata['damageCategoryItems'][0]['mawb']);
                    array_push($issue_ids, $issue_id);

                                            } // end of master_org_id and group_id checking
            } // end of $jsfiles
              // Images moving to remote server
              
              var_dump($mawb_ids);
             // exit();
          
            if (sizeof($mawb_ids) > 0) {
                foreach ($mawb_ids as $mak => $mav) {

                    $data_qry_count = "select count(*) as total from files where linked_id='" . $issue_ids[$mak] . "'";
                    $data_res_count = mysqli_query($this->con, $data_qry_count);
                    $data_row_count = mysqli_fetch_assoc($data_res_count);
                    $data_count = $data_row_count['total'];

                    if($data_count > 0){
                        $del_files_qry = "delete from files where linked_id='" . $issue_ids[$mak] . "'";
                        $del_res_data = mysqli_query($this->con, $del_files_qry);
                    }

                    foreach ($imgfiles as $imgk => $imgv) {

                        $imgpath = explode("~", $imgv);
                        $imgnaexplode = explode("_", $imgpath[1]);
                        if ($mawb_ids[$mak] == $imgnaexplode[0]) {
                            $url = 'files/' . $imgpath[2] . '/' . $imgpath[0] . '/' . $imgpath[1];
                            echo $url;
                            
                            
                            // $url='damagereports/'.str_replace(" ","%20",$imgpath[2]).'/'.str_replace(" ","%20",$imgpath[0]).'/'.str_replace(" ","%20",$imgpath[1]);
                            $a = tmpfile();
                            $metaData = stream_get_meta_data($a);
                            ftp_fget($ftp_conn, $a, $url, FTP_BINARY, 0);
                            fseek($a, 0);
                            $content = fread($a, filesize($metaData['uri']));
                            // $contents = file_get_contents($url);
                            // $name = substr($url, strrpos($url, '/') + 1);
                            $imgexp_exp = explode(".", $imgpath[1]);
                            $ext_image = sha1($url);
                            // $headers = get_headers($url, true);
                            // $file_size=$headers['Content-Length'];
                            // $file_type=$headers['Content-Type'];
                            $first_folder = substr($ext_image, 0, 1);
                            $second_folder = substr($ext_image, 1, 1);
                            $first_folder_2 = substr($ext_image, 0, 2);
                           echo $file_size = ftp_size($ftp_conn, $url);
                            
                            $url1 = file_get_contents('ftp://damagereports:4@m@53~R3p0@3@15.207.45.56/' . 'files/' . $imgpath[2] . '/' . $imgpath[0] . '/' . $imgpath[1]);
                            
                            $im = imagecreatefromstring($url1);
                            $ori_img_width = imagesx($im);
                            $ori_img_height = imagesy($im);
                            // echo $ori_img_width;
                            if ($ori_img_width > 0 && $ori_img_height > 0) {
                                if ($ori_img_width > $ori_img_height) {
                                    $thms_width = 80;
                                    $thml_width = 320;
                                    
                                    $thms_percentage = $thms_width / $ori_img_width;
                                    $thms_height = round($thms_percentage * $ori_img_height);
                                    
                                    $thml_percentage = $thml_width / $ori_img_width;
                                    $thml_height = round($thml_percentage * $ori_img_height);
                                } else 
                                    if ($ori_img_height > $ori_img_width) {
                                        $thms_height = 80;
                                        $thml_height = 320;
                                        
                                        $thms_percentage = $thms_height / $ori_img_height;
                                        $thms_width = round($thms_percentage * $ori_img_width);
                                        
                                        $thml_percentage = $thml_height / $ori_img_height;
                                        $thml_width = round($thml_percentage * $ori_img_width);
                                    } else 
                                        if ($ori_img_width == $ori_img_height) {
                                            $thms_height = $ori_img_height;
                                            $thml_height = $ori_img_height;
                                            
                                            $thms_percentage = $thms_height / $ori_img_height;
                                            $thms_width = round($thms_percentage * $ori_img_width);
                                            
                                            $thml_percentage = $thml_height / $ori_img_height;
                                            $thml_width = round($thml_percentage * $ori_img_width);
                                        }
                            } else {
                                $thms_width = 0;
                                $thms_height = 0;
                                $thml_width = 0;
                                $thml_height = 0;
                            }
                            
                            if ($imgexp_exp[1] == 'jpeg')
                                $img_ext = 'jpg';
                            else
                                $img_ext = $imgexp_exp[1];
                            //live urls : /home/cargoits/apps/its_v10/warehouse_mount/
                            //dev urls : /var/www/cargohub-2/warehouse_mount/
                            $folder = $first_folder . '/' . $second_folder;
                            $folder_final = '/home/cargoits/apps/its_v10/warehouse_mount/' . $first_folder . '/' . $second_folder;
                            $folder_small = '/home/cargoits/apps/its_v10/htdocs/uimg/thms/' . $first_folder_2;
                            $folder_large = '/home/cargoits/apps/its_v10/htdocs/uimg/thml/' . $first_folder_2;
                            
                            if (! is_dir($folder)) {
                                // dir doesn't exist, make it
                                mkdir($folder, 0777, true);
                            }
                            if (! is_dir($folder_small)) {
                                // dir doesn't exist, make it
                                mkdir($folder_small, 0777, true);
                            }
                            if (! is_dir($folder_large)) {
                                // dir doesn't exist, make it
                                mkdir($folder_large, 0777, true);
                            }  
                            
                            file_put_contents($folder_final . '/' . $ext_image . '.' . $img_ext, $content);
                            file_put_contents($folder_small . '/' . substr($ext_image, 2, strlen($ext_image)) . '.' . $img_ext, $content);
                            file_put_contents($folder_large . '/' . substr($ext_image, 2, strlen($ext_image)) . '.' . $img_ext, $content);

                            
                            echo $files_qry = "insert into files(linked_with,linked_id,visibility,org_id,access_orgs,user_id,metadata,filepath,name,mtime,ext,size,mimetype,analyzer,chash) values('issue',$issue_ids[$mak],'org',$master_org_id,$master_org_id,2222,'{\"width\":$ori_img_width,\"height\":$ori_img_height,\"type\":\"image\/jpg\",\"bits\":8,\"scaler\":{\"thms\":{\"width\":$thms_width,\"height\":$thms_height,\"fsize\":19},\"thml\":{\"width\":$thml_width,\"height\":$thml_height,\"fsize\":19}}}','$folder/$ext_image.$img_ext','$imgexp_exp[0].$imgexp_exp[1]','" . strtotime(date('Y-m-d H:i:s')) . "','$img_ext',$file_size,'image/$imgexp_exp[1]','image','$ext_image')";
                            $files_res = mysqli_query($this->con, $files_qry);
                            

                        } // end of if
                    } // Img end of foreach

echo "\n";
var_dump($pdffiles);
                    foreach ($pdffiles as $pdfk => $pdfv) {
                    
                    echo "IN Pdf \n";
                    
                        $pdfpath = explode("~", $pdfv);
                        $pdfnaexplode = explode("_", $pdfpath[1]);
                       
                        echo "========PDF Path =========";
                       var_dump($pdfpath);
                       echo "========PDF Path =========";
                        $pdfexplode = explode(".pdf", $pdfnaexplode[1]);
                        
                        
                        
                        echo $mawb_ids[$mak];
                        echo $pdfexplode[0];
                        
                         $pdfpath[2] = str_replace("+", "%20",  $pdfpath[2]);
                        
                        if ( str_replace(" ", "%20", $mawb_ids[$mak]) == $pdfexplode[0]) {
                        
                        //echo "=========== file path ====";
                          // echo  $url_pdf = 'files/' .  $pdfpath[2] . '/' . $pdfpath[0] . '/' . $pdfpath[1];
                          //  $b = tmpfile();
                          //  $metaDataPdf = stream_get_meta_data($b);
                          //  ftp_fget($ftp_conn, $b, $url_pdf, FTP_BINARY, 0);
                          //  echo "============";
                          //  var_dump($b);
                          //   echo "============";
                          //  fseek($b, 0);
                          //  $contentpdf = fread($b, filesize($metaDataPdf['uri']));
                          //   echo "=========== file content  ====";
                          //  var_dump($contentpdf);
$url_pdf = 'files/' .  $pdfpath[2] . '/' . $pdfpath[0] . '/' . $pdfpath[1];
                            $pdfexp_exp = explode(".", $pdfpath[1]);
                           echo $ext_pdf = sha1($url_pdf);
                            $first_pdf_folder = substr($ext_pdf, 0, 1);
                            $second_pdf_folder = substr($ext_pdf, 1, 1);
                            $first_pdf_folder_2 = substr($ext_pdf, 0, 2);
                            $file_pdf_size = ftp_size($ftp_conn, $url_pdf);
                            
                            var_dump($pdfpath);
                            
                          //   $url2 = file_get_contents('ftp://damagereports:4@m@53~R3p0@3@15.207.45.56/' . 'files/' . $pdfpath[2] . '/' . $pdfpath[0] . '/' . $pdfpath[1]);

//var_dump($url2);
                            $pdf_ext = $pdfexp_exp[1];
                            //live urls : /home/cargoits/apps/its_v10/warehouse_mount/
                            
                            //dev urls : /var/www/cargohub-2/warehouse_mount/
                            $folder_pdf_final = $first_pdf_folder . '/' . $second_pdf_folder;
                           echo $folder_pdf = '/home/cargoits/apps/its_v10/warehouse_mount/' . $first_pdf_folder . '/' . $second_pdf_folder;
   
                            if (! is_dir($folder_pdf)) {
                                // dir doesn't exist, make it
                                mkdir($folder_pdf, 0777, true);
                            }
                            
                          
                           
                            
                           
                           
                           $local_file = $folder_pdf . '/' . $ext_pdf . '.' . $pdf_ext;
$server_file = '/files/' . str_replace("%20", " ", $pdfpath[2])   . '/' . str_replace("%20", " ", $pdfpath[0]) . '/' . str_replace("%20", " ", $pdfpath[1]);

  //$b = tmpfile();
//$b = fopen($a, 'r+');
$handle = fopen($local_file, 'w');

                            $metaDataPdf = stream_get_meta_data($b);
                            
                            
// download server file
if (ftp_fget($ftp_conn, $handle, $server_file, FTP_BINARY,0))
  {
  echo "Successfully written to $local_file.";
   
  }
else
  {
  echo "Error downloading $server_file.";
  }
                     
                           
                           // file_put_contents($folder_pdf . '/' . $ext_pdf . '.' . $pdf_ext, $contentpdf);
                             
                          echo  $files_pdf_qry = "insert into files(linked_with,linked_id,visibility,org_id,access_orgs,user_id,metadata,filepath,name,mtime,ext,size,mimetype,analyzer,chash) values('issue',$issue_ids[$mak],'org',$master_org_id,$master_org_id,2222,'','$folder_pdf_final/$ext_pdf.$pdf_ext','$pdfexp_exp[0].$pdfexp_exp[1]','" . strtotime(date('Y-m-d H:i:s')) . "','$pdf_ext',$file_pdf_size,'application/pdf','pdf','$ext_pdf')";
                            $files_pdf_res = mysqli_query($this->con, $files_pdf_qry);
                          
                           
                           
                            
                           
                         

                        } // end of if
                    } // PDF's end of foreach
            
              } // end of foreach
               
            }

        }  // end of $jsfiles size


             $desDirec = 'files/' . $ftppath[1] . '/success';
        
             $source = 'files/' . $ftppath[1] ;

            $file_list = ftp_nlist($ftp_conn, $source);
          
            if (! file_exists($desDirec)) {
                ftp_mkdir($ftp_conn, $desDirec);
                ftp_chmod($ftp_conn, 0777, $desDirec);
            } 
 
            foreach ($file_list as $file1) {

                 $fileexp = explode("/", $file1); 
                  $subFilder = $fileexp[2]; 
                    if ($fileexp[2] != "success" && $fileexp[2] != "failure") {
                        ftp_rename($ftp_conn, $file1, $desDirec . '/' . basename($file1));
                      }
                        ftp_rmdir($ftp_conn, $source.'/'.$subFilder);
            }
           
       // return  $datamessage;
    }
 public function get_trackid()
    {
        // Last one in grops stays: 0COQ / I1 / S5 / FE / 7Z / B8
        $chars = "AEUY" . "DGHJKLMNPQRTVWXZ" . "12345689";
        $l = strlen($chars) - 1;
        while (1) {
            $tid = '';
            for ($c = 0; $c < 12; $c ++) {
                if ($c == 3 || $c == 7)
                    $tid .= '-';
                else
                    $tid .= $chars[mt_rand(0, $l)];
            }
            // discover some "bad" 3-letter words in the $tid, and if they are present, restart the process
            // TODO: Support custom word list, to addapt to other languages bad words
            // Note that not all alphanumeric characters are used, and since I, O, C, F, S and 0 are missing, many words cannot be formed.
            $badwords = array(
                'A55',
                'GAY',
                'KUM',
                'PEE',
                '5EX',
                'T1T',
                'XXX',
                'ANAL',
                'ANU5',
                'AR5E',
                'BUTT',
                'DYKE',
                'DUMP',
                'JERK',
                'J1ZZ',
                'KUNT',
                'P155',
                'PU55',
                'PU5Y',
                '5H1T',
                '5LUT',
                'TURD',
                'TWAT',
                'WANK'
            );
            foreach ($badwords as $word)
                if (strpos($tid, $word) !== false)
                    continue;
            break;
        }
        return $tid;
    }

    public function get_access_key()
    {
        return sha1(microtime(1) . uniqid());
    }

}

$obj = new damagereports();
$obj->its2restapi();
?>