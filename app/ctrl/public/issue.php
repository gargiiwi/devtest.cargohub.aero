<?php
/**
 * Issue display controller for public (submit, view list/item, reply)
 * -- All viewing actions require a password which is generated at submit time and sent by e-mail
 *
 * FIXME: This module loads the record type way too many times while it's already cached in MVC_Core::$cache-> current_rt ... Form generator should use that instead of App_Model_Issue in-object cache
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage Controllers
 * @version 2020.11.01
 */
class App_Ctrl_Public_Issue extends App_Ctrl_Base
{
    /**
     * Reference to the Contact Model
     * @var App_Model_Contact
     */
    private $con;
                    
    /**
     * Reference to the File Model
     * @var App_Model_File
     */
    private $file; 

    /**
     * Reference to the Communication Model (instanced on demand)
     * @var App_Model_Comm
     */ 
    private $comm;

    /**
     * Reference to the Conversation Model (instanced on demand)
     * @var App_Model_Conversation
     */
    private $cnv;

    /**
     * Issue operations controller Constructor
     */
    function __construct()
    {
        parent::__construct();

        // Controller specific models
        $this-> record = $this-> factory_Model('Issue',  "record");
        $this-> con = $this-> factory_Model("Contact", "con");
        $this-> file = $this-> factory_Model('File');
    }
	
	// Import MRN Data
	public function action_importmrndata (MVC_Request $in)
    {
		$this-> view("issues/nctsfw/import.html");
	}
	
	// Preview XLS data
	public function action_importmrndatasubmit (MVC_Request $in)
	{
		
		// Insert MRN Details
		if (isset($_POST['importmrndata'])) {
			/*print $_SERVER["DOCUMENT_ROOT"];   
			$type = explode(".", strrev($_FILES['files']['name']));
			$type = strrev($type[0]);
			if ($type == 'xls') {
				if ($_FILES['files']['error'] == '0') {
					$filename = $_FILES['files']['name'];
						//print $_SERVER["DOCUMENT_ROOT"]."/mrnfiles/" . $filename;
					if (is_uploaded_file($_FILES["files"]["tmp_name"])) {
						$file_move = move_uploaded_file($_FILES["files"]["tmp_name"], $_SERVER["DOCUMENT_ROOT"]."/mrnfiles/" . $filename);
						//if($file_move) {
						//	print "after";
						//}
						$attach = $_SERVER["DOCUMENT_ROOT"] . "/mrnfiles/" . $filename;
						chmod($attach, 0777);
					}
				}
			}*/
			require_once 'reader.php';
			
			$filename = $_POST['file_name'];
			if(!empty($filename)) {
				$filepath = $_POST['file_path'];
				$data = new Spreadsheet_Excel_Reader();
				$data->setOutputEncoding('UTF8');
				$data->read($filepath);
				error_reporting(E_ALL ^ E_NOTICE);
				  
				//print_r($data);exit();
	  
				//$type = explode(".", strrev($_FILES['files']['name']));
				//$type = strrev($type[0]);
				//if ($type == 'xls') {
				// Read xls file
				/*$data = new Spreadsheet_Excel_Reader();
				// Set output Encoding.
				$data->setOutputEncoding('UTF8');
				$data->read($attach);*/
				error_reporting(E_ALL ^ E_NOTICE);
				?>
				<html lang="en">
				<head>
					<meta http-equiv="X-UA-Compatible" content="IE=edge" />
					<meta charset="utf-8" />
					<title>{html.title}{#if:header.zone: | {header.zone}}</title>
					<link rel="shortcut icon" type="image/x-icon" href="{html.favicon:/favico/default.ico}">
					
					<style>
						
				{#raw:loading_overlay.css}
					#upload{background-color:red}
					</style>
					<link href="/css/{html.css_theme}/cargo_its.css?v=2.7" type="text/css" rel="stylesheet" />
					<link href="/css/{html.css_theme}/cargo_its_extra.css?v=0.1" type="text/css" rel="stylesheet" />
					 <link rel="stylesheet" href="/css/default/components/sweetalert2.min.css"  />
				</head>
				<body style="overflow-y: scroll;">
					<span id="claim_type_name" style="display: none;">{claim_type_name}</span>
					
					<div  tabindex="-1"><div class="site">
						<div class="header" style="margn:0px">
				
				<?php
				@include_once 'private/header.html';
				?>
						
				<table style="margin: 25px; border: 1px solid black; border-collapse: collapse; text-align: center; text-size:10px;">
				<tr >
					<th style="border: 1px solid black; border-collapse: collapse; padding: 10px;" align="justify">S.No </th>
					<th style="border: 1px solid black; border-collapse: collapse;">Customer Name</th>
					<th style="border: 1px solid black; border-collapse: collapse;">MRN No</th>
					<th style="border: 1px solid black; border-collapse: collapse;">Documents required (Followup)</th>
					<th style="border: 1px solid black; border-collapse: collapse;">Sub number</th>
					<th style="border: 1px solid black; border-collapse: collapse;">Data</th>
					<th style="border: 1px solid black; border-collapse: collapse;">MRN Date</th>
					<th style="border: 1px solid black; border-collapse: collapse;">Declaration code</th>
					<th style="border: 1px solid black; border-collapse: collapse;">Record Status</th>
					<th style="border: 1px solid black; border-collapse: collapse;">Status</th>
				</tr>
				<?php
				
				session_start();
				//print_r($_SESSION['user']);exit();
				// Import Type (1-Close, 2-Dont Close)
				$importtype = $_POST['importtype'];
				//$mrntype = $_POST['mrntype'];
				if(!empty($mrntype)) {
					$mrntype_values = implode("','", $mrntype);
					/*$mrntype_values = ' and ';
					foreach ($mrntype as $mrn => $mrntype_details) {
						$mrntype_values .= ' ifs.value = "' . $mrn . "' or ";. 
					}
					$mrntype_values = trim($mrntype_values, ' or ');*/
					
					$mrntype_values = "'" . $mrntype_values . "'";
				}
				//print_r($_POST);exit();
				$mrntypeimport = $_POST['mrntypeimport'];
				$org_id = $_SESSION['user']['org_id'];
				
				// Close Issues
				if($importtype == 1 & !empty($mrntypeimport)) {
					$close_issue_qry = MVC_Core::$db->query("SELECT ic.issue_id from issue_contacts ic join issues i on ic.issue_id = i.id join issue_fields ifs on ic.issue_id = ifs.issue_id where type_id = 26 and ifs.value in ( '" . $mrntypeimport . "' ) and org_id = " . $_SESSION['user']['org_id']);
					//print "SELECT ic.issue_id from issue_contacts ic join issues i on ic.issue_id = i.id join issue_fields ifs on ic.issue_id = ifs.issue_id where type_id = 26 and ifs.value in ( '" . $mrntypeimport . "' ) and org_id = " . $_SESSION['user']['org_id'];
					$close_ids = '';
					if ($close_issue_qry->num_rows >= 1) {
						foreach ($close_issue_qry as $close_qry => $close_details) {
							$close_ids .= $close_details['issue_id'] . ',';
						}
					}
					$close_ids = trim($close_ids, ',');
					//exit();
					
					// Close Records
					if(!empty($close_ids)) {
						$update_issue_qry = MVC_Core::$db->query("UPDATE issue_orgdata SET process = 'closed' where issue_id in ( " . $close_ids . " )");
						//print "UPDATE issue_orgdata SET process = 'closed' where issue_id in ( " . $close_ids . " )"; exit();
					}
				}
				$group_id = '';
				if(!empty($_SESSION['user']['acls']['groups'])) {
					foreach($_SESSION['user']['acls']['groups'] as $group_id => $val) {
						$group_id = $group_id;
					}
				}
				//echo '<pre>' . print_r($_SESSION, TRUE) . '</pre>';
				$manager_id = $_SESSION['organization']['manager_id'];
				$organization_id = $_SESSION['organization']['id'];
				$user_id = $_SESSION['user']['id'];
				$contact_id = $_SESSION['user']['contact_id'];
				$sno = 1;
				$records_imported = 0;
				for ($i = 2; $i <= $data->sheets[0]['numRows']; $i++) {
					if(!empty($data->sheets[0]['cells'][$i][2])) {
						$column_count = 0;
						foreach($data->sheets[0]['cells'][$i] as $columns) {
							$column_count++;
						}
						$mrn_no = $data->sheets[0]['cells'][$i][2];
						$mrn_no_array = str_split($mrn_no, 2);
						$mrn_no1 = $mrn_no_array[0];
						$mrn_no2 = $mrn_no_array[1];
						if(is_numeric($mrn_no1) and !is_numeric($mrn_no2) and $column_count == 7) {
							// Check Record there or not in current Organization
							$master_org_id = (int)MVC_Core::$ss-> domain_CFG['org_id'];
							$check_opendays_data = MVC_Core::$db->query("select ifs.issue_id, ifs.fk_id, ifs.value from issue_contacts ic JOIN issue_fields ifs on ic.issue_id = ifs.issue_id where ifs.fk_id = 2163 and ifs.value = '" . $data->sheets[0]['cells'][$i][2] . "' and ic.org_id = " . $_SESSION['user']['org_id'] );
							// Check Record there or not 
							//$check_opendays_data = MVC_Core::$db->query("select issue_id, fk_id, value from issue_fields where fk_id = 2163 and value = '" . $data->sheets[0]['cells'][$i][2] . "'" );
							if ($check_opendays_data->num_rows >= 1) { 
								// Update Status
								foreach ($check_opendays_data as $issue_key => $check_opendays_details) {
									$issue_id = $check_opendays_details['issue_id'];
								}
								$update_qry = MVC_Core::$db->query("UPDATE issue_orgdata set process = 'active' where issue_id = " . $issue_id. " ");
								$status = "Updated";
								$status_code = 'color:#000000;';
							}
							else {
								$issue = new App_Model_Issue();
								$track = $issue->generate_TrackID();
								
								
								$submit_ip = sprintf('%u', ip2long($_SERVER['REMOTE_ADDR']));
								$ctime = time(); 
								
								$master_org_id = (int)MVC_Core::$ss-> domain_CFG['org_id'];
								$submit_id = $_SESSION['user']['id'];
								
								$insert_qry = MVC_Core::$db->query("INSERT INTO issues (track, type_id, ctime, submit_ip, submit_id, master_org_id, submit_org_id, cache_primary, group_id) VALUES ( '" . $track  . "' , 26, '" . $ctime  . "', " . $submit_ip  . ", " . $contact_id  . ", " . $organization_id  . ", " . $organization_id  . ", '" . $data->sheets[0]['cells'][$i][2]  . "', '" . $group_id  . "')");
								
								$lastInsertId = MVC_Core::$db->insert_id;
								if(!empty($lastInsertId)) {
									
									// Issue Contacts
									$insert_qry = MVC_Core::$db->query("INSERT INTO issue_contacts (issue_id, contact_id, role, label, visibility, org_id, user_id) VALUES ( '" . $lastInsertId  . "', " . $contact_id  . ",  'submitter', 'Submitted by', 'public', '" . $organization_id  . "', " . $user_id  . ")");
									
									// Issue ORG Data
									$dep_ids = '';
									$dep_id_qry = MVC_Core::$db->query("select cdep_id from users where id = " . $user_id );
									if ($dep_id_qry->num_rows >= 1) {
										foreach ($dep_id_qry as $issue_key => $issue_details) {
											$dep_ids = $issue_details['cdep_id'];
										}
									}
									$insert_qry = MVC_Core::$db->query("INSERT INTO issue_orgdata (issue_id, org_id, is_enabled, process, priority_key, status_key, dep_ids) VALUES ( '" . $lastInsertId  . "', " . $organization_id  . ",  1, 'active', '4med', 'ndis', " . $dep_ids  . ")");
									
									// Issue Fields
									$insert_qry = MVC_Core::$db->query("INSERT INTO issue_fields (issue_id, fk_id, value) VALUES ( " . $lastInsertId  . " , 2245, '" . $data->sheets[0]['cells'][$i][1] . "') ");
									$insert_qry = MVC_Core::$db->query("INSERT INTO issue_fields (issue_id, fk_id, value) VALUES ( " . $lastInsertId  . " , 2163, '" . $data->sheets[0]['cells'][$i][2] . "') ");
									$insert_qry = MVC_Core::$db->query("INSERT INTO issue_fields (issue_id, fk_id, value) VALUES ( " . $lastInsertId  . " , 2438, '" . $data->sheets[0]['cells'][$i][3] . "') ");
									$insert_qry = MVC_Core::$db->query("INSERT INTO issue_fields (issue_id, fk_id, value) VALUES ( " . $lastInsertId  . " , 3611, '" . $data->sheets[0]['cells'][$i][4] . "') ");
									// MRN Type
									//$insert_qry = MVC_Core::$db->query("INSERT INTO issue_fields (issue_id, fk_id, value) VALUES ( " . $lastInsertId  . " , 2205, 'EXP') ");
									$insert_qry = MVC_Core::$db->query("INSERT INTO issue_fields (issue_id, fk_id, value) VALUES ( " . $lastInsertId  . " , 2205, '" . $mrntypeimport  . "') ");
									// MRN Date
									$explode_mrn_date = explode('/', $data->sheets[0]['cells'][$i][6]);
									$implode_mrn_date = implode('-', $explode_mrn_date);
									$mrn_date = strtotime($implode_mrn_date);
									$insert_qry = MVC_Core::$db->query("INSERT INTO issue_fields (issue_id, fk_id, int_value) VALUES ( " . $lastInsertId  . " , 2242, " . $mrn_date . ") ");
									$insert_qry = MVC_Core::$db->query("INSERT INTO issue_fields (issue_id, fk_id, value) VALUES ( " . $lastInsertId  . " , 3610, '" . $data->sheets[0]['cells'][$i][7] . "') ");
									
									// Open Days
									$date1_ts = strtotime(date("d-m-Y"));
									$date2_ts = strtotime($mrn_date);
									$diff = $date2_ts - $date1_ts;
									$days_diff = round($diff / 86400);
									$insert_qry = MVC_Core::$db->query("INSERT INTO issue_fields (issue_id, fk_id, int_value) VALUES ( " . $lastInsertId  . " , 3720, '" . $days_diff . "') ");
									
									$status = "New";
									$status_code = 'color:#09b968;';
								}
							}
							?>
							<tr >
								<td style="border: 1px solid black; border-collapse: collapse; "><?php print $sno;?></td>
								<td style="border: 1px solid black; border-collapse: collapse;" align="justify"><?php print $data->sheets[0]['cells'][$i][1];?></td>
								<td style="border: 1px solid black; border-collapse: collapse;"><?php print $data->sheets[0]['cells'][$i][2];?></td>
								<td style="border: 1px solid black; border-collapse: collapse;"><?php print $data->sheets[0]['cells'][$i][3];?></td>
								<td style="border: 1px solid black; border-collapse: collapse;"><?php print $data->sheets[0]['cells'][$i][4];?></td>
								<td style="border: 1px solid black; border-collapse: collapse;"><?php print $data->sheets[0]['cells'][$i][5];?></td>
								<td style="border: 1px solid black; border-collapse: collapse;"><?php print $data->sheets[0]['cells'][$i][6];?></td>
								<td style="border: 1px solid black; border-collapse: collapse;"><?php print $data->sheets[0]['cells'][$i][7];?></td>
								<td style="border: 1px solid black; border-collapse: collapse; color:#09b968;">Processed</td>
								<td style="border: 1px solid black; border-collapse: collapse; <?php print $status_code;?>"><?php print $status;?></td>
							<tr>
							<?php
							$sno++;
							$records_imported++;
						}
						else {
							?>
							<tr >
								<td style="border: 1px solid black; border-collapse: collapse;"><?php print $sno;?></td>
								<td style="border: 1px solid black; border-collapse: collapse;"><?php print $data->sheets[0]['cells'][$i][1];?></td>
								<td style="border: 1px solid black; border-collapse: collapse;"><?php print $data->sheets[0]['cells'][$i][2];?></td>
								<td style="border: 1px solid black; border-collapse: collapse;"><?php print $data->sheets[0]['cells'][$i][3];?></td>
								<td style="border: 1px solid black; border-collapse: collapse;"><?php print $data->sheets[0]['cells'][$i][4];?></td>
								<td style="border: 1px solid black; border-collapse: collapse;"><?php print $data->sheets[0]['cells'][$i][5];?></td>
								<td style="border: 1px solid black; border-collapse: collapse;"><?php print $data->sheets[0]['cells'][$i][6];?></td>
								<td style="border: 1px solid black; border-collapse: collapse;"><?php print $data->sheets[0]['cells'][$i][7];?></td>
								<td style="border: 1px solid black; border-collapse: collapse; color:#C70039;">Failed</td>
								<td style="border: 1px solid black; border-collapse: collapse;">-</td>
							<tr>
							<?php
						}
					}
				}
				?>
				</table>
				<div><h5>XLS file uploaded successfully, <?php print $records_imported;?> records are imported</h5></div>
				<h5 align="center"><a href='http://93.119.11.177/issue?action=importmrndata'><button >DONE</button></a></h5>
				<?php
				@include_once 'private/footer.html';
			}
		}
		
		
		
		if(isset($_POST['xls_submit'])) {
			//print $_SERVER["DOCUMENT_ROOT"];   
			$type = explode(".", strrev($_FILES['files']['name']));
			$type = strrev($type[0]);
			if ($type == 'xls') {
				if ($_FILES['files']['error'] == '0') {
					$filename = $_FILES['files']['name'];
						//print $_SERVER["DOCUMENT_ROOT"]."/mrnfiles/" . $filename;
					if (is_uploaded_file($_FILES["files"]["tmp_name"])) {
						$file_move = move_uploaded_file($_FILES["files"]["tmp_name"], $_SERVER["DOCUMENT_ROOT"]."/mrnfiles/" . $filename);
						//if($file_move) {
						//	print "after";
						//}
						$attach = $_SERVER["DOCUMENT_ROOT"] . "/mrnfiles/" . $filename;
						chmod($attach, 0777);
					}
				}
			}
			require_once 'reader.php';
			$type = explode(".", strrev($_FILES['files']['name']));
			$type = strrev($type[0]);
			if ($type == 'xls') {
				// Read xls file
				$data = new Spreadsheet_Excel_Reader();
				// Set output Encoding.
				$data->setOutputEncoding('UTF8');
				$data->read($attach);
				error_reporting(E_ALL ^ E_NOTICE);
				?>
				<html lang="en">
				<head>
					<meta http-equiv="X-UA-Compatible" content="IE=edge" />
					<meta charset="utf-8" />
					<title>{html.title}{#if:header.zone: | {header.zone}}</title>
					<link rel="shortcut icon" type="image/x-icon" href="{html.favicon:/favico/default.ico}">
					
					<style>
						
				{#raw:loading_overlay.css}
					#upload{background-color:red}
					</style>
					<link href="/css/{html.css_theme}/cargo_its.css?v=2.7" type="text/css" rel="stylesheet" />
					<link href="/css/{html.css_theme}/cargo_its_extra.css?v=0.1" type="text/css" rel="stylesheet" />
					 <link rel="stylesheet" href="/css/default/components/sweetalert2.min.css"  />
				</head>
				<body style="overflow-y: scroll;">
					<span id="claim_type_name" style="display: none;">{claim_type_name}</span>
					
					<div  tabindex="-1"><div class="site">
						<div class="header" style="margn:0px">
				
				<?php
				@include_once 'private/header.html';
				?>
				</br/>
				<form enctype = "multipart/form-data" method="post" action="http://93.119.11.177/issue?action=importmrndatasubmit" id="upload_xls_data">		
					<div style="font-family: verdana;" id="checkbox_mrntypeimport" >
						<div class="checkbox_mrntypeimport" >
							&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<label><b>Select MRN Type to Import Issues</b></label>&nbsp;&nbsp;
							<input type="radio" name="mrntypeimport" value="NCTS" required="required">NCTS
							<input type="radio" name="mrntypeimport" value="IMP" required="required">IMPORT
							<input type="radio" name="mrntypeimport" value="EXP" required="required">EXPORT
						</div>
					</div>
					<table style="margin: 25px; border: 1px solid black; border-collapse: collapse; text-align: center; text-size:10px; font-family: verdana;">
						<tr >
							<th style="border: 1px solid black; border-collapse: collapse; padding: 10px;" align="justify">S.No </th>
							<th style="border: 1px solid black; border-collapse: collapse;">Customer Name</th>
							<th style="border: 1px solid black; border-collapse: collapse;">MRN No</th>
							<th style="border: 1px solid black; border-collapse: collapse;">Documents required (Followup)</th>
							<th style="border: 1px solid black; border-collapse: collapse;">Sub number</th>
							<th style="border: 1px solid black; border-collapse: collapse;">Data</th>
							<th style="border: 1px solid black; border-collapse: collapse;">MRN Date</th>
							<th style="border: 1px solid black; border-collapse: collapse;">Declaration code</th>
						</tr>
						<?php
						$sno = 1;
						$records_imported = 0;
						for ($i = 2; $i <= $data->sheets[0]['numRows']; $i++) {
							if(!empty($data->sheets[0]['cells'][$i][2])) {
								$column_count = 0;		
								?>
								<tr >
									<td style="border: 1px solid black; border-collapse: collapse; "><?php print $sno;?></td>
									<td style="border: 1px solid black; border-collapse: collapse;" align="justify"><?php print $data->sheets[0]['cells'][$i][1];?></td>
									<td style="border: 1px solid black; border-collapse: collapse;"><?php print $data->sheets[0]['cells'][$i][2];?></td>
									<td style="border: 1px solid black; border-collapse: collapse;"><?php print $data->sheets[0]['cells'][$i][3];?></td>
									<td style="border: 1px solid black; border-collapse: collapse;"><?php print $data->sheets[0]['cells'][$i][4];?></td>
									<td style="border: 1px solid black; border-collapse: collapse;"><?php print $data->sheets[0]['cells'][$i][5];?></td>
									<td style="border: 1px solid black; border-collapse: collapse;"><?php print $data->sheets[0]['cells'][$i][6];?></td>
									<td style="border: 1px solid black; border-collapse: collapse;"><?php print $data->sheets[0]['cells'][$i][7];?></td>
								<tr>
								<?php
								$sno++;
								$records_imported++;

							}
						}
						//session_start();
						//print_r( $_SESSION); exit();
						?>
					</table>
					<div>
					<br/>
					
					<h5 style="font-family: verdana; " id="radiobutt">
						<p style="color:red;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;IMPORTANCE NOTICE</p>
						<p>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<?php print $records_imported;?> records will be uploaded and processed in the system</p>
						<p>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Do you wish to close existing records in the system which are not being updated from the uploaded file?</p>
						<br/>
						<!--<button name="importmrndatasubmit" type="submit" style="
								background-color:#09b968;border:none;
								color:#fff; font-weight: bold; border-radius:5px;cursor:pointer;;
								box-shadow: 5px 3px 8px lightgray;padding:10px 90px">Proceed</button>-->
						<input type="hidden" name="file_name" value="<? print $filename;?>" />
						<input type="hidden" name="file_path" value="<? print $attach;?>" />
						&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type="radio" name="importtype" value="1" onclick="javascript:yesnoCheck();" id="yesCheck" style="
								background-color:red;border:none;
								color:#fff; font-weight: bold; border-radius:5px;cursor:pointer;;
								box-shadow: 5px 3px 8px lightgray;padding:10px 90px" />YES, CLOSE EXISTING RECORDS
						&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type="radio" name="importtype" value="2" onclick="javascript:yesnoCheck();" checked style="
								background-color:#09b968;border:none;
								color:#fff; font-weight: bold; border-radius:5px;cursor:pointer;;
									box-shadow: 5px 3px 8px lightgray;padding:10px 90px" />NO, KEEP EXISTING RECORDS
					</h5>
					<!--<div style="font-family: verdana; display:none; " id="mrntype_checkbox" >
						<div class="checkbox-group" >
							&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<label>Select Record types to be closed</label>&nbsp;&nbsp;
							<input type="checkbox" name="mrntype[]" value="NCTS">NCTS
							<input type="checkbox" name="mrntype[]" value="IMP">IMPORT
							<input type="checkbox" name="mrntype[]" value="EXP">EXPORT
						</div>
					</div>-->
					<h5 style="font-family: verdana; ">
						&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type="submit" name="importmrndata" value="Proceed" style="
								background-color:#09b968;border:none;
								color:#fff; font-weight: bold; border-radius:5px;cursor:pointer;;
									box-shadow: 5px 3px 8px lightgray;padding:10px 90px" />
					</h5>
					
				</div>
				</form>
				<?php
				@include_once 'private/footer.html';
			}
		}

	}
	
	
	/*public function action_importmrndatasubmit (MVC_Request $in)
	{
		
		print $_SERVER["DOCUMENT_ROOT"];   
		$type = explode(".", strrev($_FILES['files']['name']));
		$type = strrev($type[0]);
		if ($type == 'xls') {
			if ($_FILES['files']['error'] == '0') {
				$filename = $_FILES['files']['name'];
					//print $_SERVER["DOCUMENT_ROOT"]."/mrnfiles/" . $filename;
				if (is_uploaded_file($_FILES["files"]["tmp_name"])) {
					$file_move = move_uploaded_file($_FILES["files"]["tmp_name"], $_SERVER["DOCUMENT_ROOT"]."/mrnfiles/" . $filename);
					//if($file_move) {
					//	print "after";
					//}
					$attach = $_SERVER["DOCUMENT_ROOT"] . "/mrnfiles/" . $filename;
					chmod($attach, 0777);
				}
			}
		}
		require_once 'reader.php';
		$type = explode(".", strrev($_FILES['files']['name']));
		$type = strrev($type[0]);
		if ($type == 'xls') {
			// Read xls file
			$data = new Spreadsheet_Excel_Reader();
			// Set output Encoding.
			$data->setOutputEncoding('UTF8');
			$data->read($attach);
			error_reporting(E_ALL ^ E_NOTICE);
			?>
			<html lang="en">
			<head>
				<meta http-equiv="X-UA-Compatible" content="IE=edge" />
				<meta charset="utf-8" />
				<title>{html.title}{#if:header.zone: | {header.zone}}</title>
				<link rel="shortcut icon" type="image/x-icon" href="{html.favicon:/favico/default.ico}">
				
				<style>
					
			{#raw:loading_overlay.css}
				#upload{background-color:red}
				</style>
				<link href="/css/{html.css_theme}/cargo_its.css?v=2.7" type="text/css" rel="stylesheet" />
				<link href="/css/{html.css_theme}/cargo_its_extra.css?v=0.1" type="text/css" rel="stylesheet" />
				 <link rel="stylesheet" href="/css/default/components/sweetalert2.min.css"  />
			</head>
			<body style="overflow-y: scroll;">
				<span id="claim_type_name" style="display: none;">{claim_type_name}</span>
				
				<div  tabindex="-1"><div class="site">
					<div class="header" style="margn:0px">
			
			<?php
			@include_once 'private/header.html';
			?>
					
			<table style="margin: 25px; border: 1px solid black; border-collapse: collapse; text-align: center; text-size:10px;">
			<tr >
				<th style="border: 1px solid black; border-collapse: collapse; padding: 10px;" align="justify">S.No </th>
				<th style="border: 1px solid black; border-collapse: collapse;">Customer Name</th>
				<th style="border: 1px solid black; border-collapse: collapse;">MRN No</th>
				<th style="border: 1px solid black; border-collapse: collapse;">Documents required (Followup)</th>
				<th style="border: 1px solid black; border-collapse: collapse;">Sub number</th>
				<th style="border: 1px solid black; border-collapse: collapse;">Data</th>
				<th style="border: 1px solid black; border-collapse: collapse;">MRN Date</th>
				<th style="border: 1px solid black; border-collapse: collapse;">Declaration code</th>
				<th style="border: 1px solid black; border-collapse: collapse;">Record Status</th>
				<th style="border: 1px solid black; border-collapse: collapse;">Status</th>
			</tr>
			<?php
			session_start();
			$group_id = '';
			if(!empty($_SESSION['user']['acls']['groups'])) {
				foreach($_SESSION['user']['acls']['groups'] as $group_id => $val) {
					$group_id = $group_id;
				}
			}
			//echo '<pre>' . print_r($_SESSION, TRUE) . '</pre>';
			$manager_id = $_SESSION['organization']['manager_id'];
			$organization_id = $_SESSION['organization']['id'];
			$user_id = $_SESSION['user']['id'];
			$contact_id = $_SESSION['user']['contact_id'];
			$sno = 1;
			$records_imported = 0;
			for ($i = 2; $i <= $data->sheets[0]['numRows']; $i++) {
				if(!empty($data->sheets[0]['cells'][$i][2])) {
					$column_count = 0;
					foreach($data->sheets[0]['cells'][$i] as $columns) {
						$column_count++;
					}
					$mrn_no = $data->sheets[0]['cells'][$i][2];
					$mrn_no_array = str_split($mrn_no, 2);
					$mrn_no1 = $mrn_no_array[0];
					$mrn_no2 = $mrn_no_array[1];
					if(is_numeric($mrn_no1) and !is_numeric($mrn_no2) and $column_count == 7) {
						$check_opendays_data = MVC_Core::$db->query("select issue_id, fk_id, value from issue_fields where fk_id = 2163 and value = '" . $data->sheets[0]['cells'][$i][2] . "'" );
						if ($check_opendays_data->num_rows >= 1) {
							$status = "Updated";
							$status_code = 'color:#000000;';
						}
						else {
							$issue = new App_Model_Issue();
							$track = $issue->generate_TrackID();
							
							
							$submit_ip = sprintf('%u', ip2long($_SERVER['REMOTE_ADDR']));
							$ctime = time(); 
							
							$master_org_id = (int)MVC_Core::$ss-> domain_CFG['org_id'];
							$submit_id = $_SESSION['user']['id'];
							
							$insert_qry = MVC_Core::$db->query("INSERT INTO issues (track, type_id, ctime, submit_ip, submit_id, master_org_id, submit_org_id, cache_primary, group_id) VALUES ( '" . $track  . "' , 26, '" . $ctime  . "', " . $submit_ip  . ", " . $contact_id  . ", " . $organization_id  . ", " . $organization_id  . ", '" . $data->sheets[0]['cells'][$i][2]  . "', '" . $group_id  . "')");
							
							$lastInsertId = MVC_Core::$db->insert_id;
							if(!empty($lastInsertId)) {
								
								// Issue Contacts
								$insert_qry = MVC_Core::$db->query("INSERT INTO issue_contacts (issue_id, contact_id, role, label, visibility, org_id, user_id) VALUES ( '" . $lastInsertId  . "', " . $contact_id  . ",  'submitter', 'Submitted by', 'public', '" . $organization_id  . "', " . $user_id  . ")");
								
								// Issue ORG Data
								$dep_ids = '';
								$dep_id_qry = MVC_Core::$db->query("select cdep_id from users where id = " . $user_id );
								if ($dep_id_qry->num_rows >= 1) {
									foreach ($dep_id_qry as $issue_key => $issue_details) {
										$dep_ids = $issue_details['cdep_id'];
									}
								}
								$insert_qry = MVC_Core::$db->query("INSERT INTO issue_orgdata (issue_id, org_id, is_enabled, process, priority_key, status_key, dep_ids) VALUES ( '" . $lastInsertId  . "', " . $organization_id  . ",  1, 'active', '4med', 'ndis', " . $dep_ids  . ")");
								
								// Issue Fields
								$insert_qry = MVC_Core::$db->query("INSERT INTO issue_fields (issue_id, fk_id, value) VALUES ( " . $lastInsertId  . " , 2245, '" . $data->sheets[0]['cells'][$i][1] . "') ");
								$insert_qry = MVC_Core::$db->query("INSERT INTO issue_fields (issue_id, fk_id, value) VALUES ( " . $lastInsertId  . " , 2163, '" . $data->sheets[0]['cells'][$i][2] . "') ");
								$insert_qry = MVC_Core::$db->query("INSERT INTO issue_fields (issue_id, fk_id, value) VALUES ( " . $lastInsertId  . " , 2438, '" . $data->sheets[0]['cells'][$i][3] . "') ");
								$insert_qry = MVC_Core::$db->query("INSERT INTO issue_fields (issue_id, fk_id, value) VALUES ( " . $lastInsertId  . " , 3611, '" . $data->sheets[0]['cells'][$i][4] . "') ");
								// MRN Type
								$insert_qry = MVC_Core::$db->query("INSERT INTO issue_fields (issue_id, fk_id, value) VALUES ( " . $lastInsertId  . " , 2205, 'EXP') ");
								// MRN Date
								$explode_mrn_date = explode('/', $data->sheets[0]['cells'][$i][6]);
								$implode_mrn_date = implode('-', $explode_mrn_date);
								$mrn_date = strtotime($implode_mrn_date);
								$insert_qry = MVC_Core::$db->query("INSERT INTO issue_fields (issue_id, fk_id, int_value) VALUES ( " . $lastInsertId  . " , 2242, " . $mrn_date . ") ");
								$insert_qry = MVC_Core::$db->query("INSERT INTO issue_fields (issue_id, fk_id, value) VALUES ( " . $lastInsertId  . " , 3610, '" . $data->sheets[0]['cells'][$i][7] . "') ");
								
								// Open Days
								$date1_ts = strtotime(date("d-m-Y"));
								$date2_ts = strtotime($mrn_date);
								$diff = $date2_ts - $date1_ts;
								$days_diff = round($diff / 86400);
								$insert_qry = MVC_Core::$db->query("INSERT INTO issue_fields (issue_id, fk_id, int_value) VALUES ( " . $lastInsertId  . " , 3720, '" . $days_diff . "') ");
								
								$status = "New";
								$status_code = 'color:#09b968;';
							}
						}
						?>
						<tr >
							<td style="border: 1px solid black; border-collapse: collapse; "><?php print $sno;?></td>
							<td style="border: 1px solid black; border-collapse: collapse;" align="justify"><?php print $data->sheets[0]['cells'][$i][1];?></td>
							<td style="border: 1px solid black; border-collapse: collapse;"><?php print $data->sheets[0]['cells'][$i][2];?></td>
							<td style="border: 1px solid black; border-collapse: collapse;"><?php print $data->sheets[0]['cells'][$i][3];?></td>
							<td style="border: 1px solid black; border-collapse: collapse;"><?php print $data->sheets[0]['cells'][$i][4];?></td>
							<td style="border: 1px solid black; border-collapse: collapse;"><?php print $data->sheets[0]['cells'][$i][5];?></td>
							<td style="border: 1px solid black; border-collapse: collapse;"><?php print $data->sheets[0]['cells'][$i][6];?></td>
							<td style="border: 1px solid black; border-collapse: collapse;"><?php print $data->sheets[0]['cells'][$i][7];?></td>
							<td style="border: 1px solid black; border-collapse: collapse; color:#09b968;">Processed</td>
							<td style="border: 1px solid black; border-collapse: collapse; <?php print $status_code;?>"><?php print $status;?></td>
						<tr>
						<?php
						$sno++;
						$records_imported++;
					}
					else {
						?>
						<tr >
							<td style="border: 1px solid black; border-collapse: collapse;"><?php print $sno;?></td>
							<td style="border: 1px solid black; border-collapse: collapse;"><?php print $data->sheets[0]['cells'][$i][1];?></td>
							<td style="border: 1px solid black; border-collapse: collapse;"><?php print $data->sheets[0]['cells'][$i][2];?></td>
							<td style="border: 1px solid black; border-collapse: collapse;"><?php print $data->sheets[0]['cells'][$i][3];?></td>
							<td style="border: 1px solid black; border-collapse: collapse;"><?php print $data->sheets[0]['cells'][$i][4];?></td>
							<td style="border: 1px solid black; border-collapse: collapse;"><?php print $data->sheets[0]['cells'][$i][5];?></td>
							<td style="border: 1px solid black; border-collapse: collapse;"><?php print $data->sheets[0]['cells'][$i][6];?></td>
							<td style="border: 1px solid black; border-collapse: collapse;"><?php print $data->sheets[0]['cells'][$i][7];?></td>
							<td style="border: 1px solid black; border-collapse: collapse; color:#C70039;">Failed</td>
							<td style="border: 1px solid black; border-collapse: collapse;">-</td>
						<tr>
						<?php
					}
				}
			}
			?>
			</table>
			<div><h5>XLS file uploaded successfully, <?php print $records_imported;?> records are imported</h5></div>
			<h5 align="center"><a href='http://93.119.11.177/issue?action=importmrndata'><button >DONE</button></a></h5>
			<?php
			@include_once 'private/footer.html';
		}
	}*/

    /**
     * Shows a presentation of what issues are about and provides link for further action
     * -- (this is the old "index" which has become a pure redirector)
     */
    public function action_Present(MVC_Request $in)
    {
        // If domain doesn't have this info configured, don't allow issues to be submitted
        if(!is_array(MVC_Core::$ss-> domain_CFG['public_settings'])) {
            echo "<h1>Unfortunately, this domain is not properly configured yet.</h1><p>Please contact support@cargohub.nl and ask for domain <b>{$_SERVER['HTTP_HOST']}</b> to be configured.</p>";
            return;
        }

        $this-> CFG['modules'][] = "issue_present";

        $this-> prepare_Header("CargoClaims", array("userbar", "evan"));

        // public_settings
        if(is_array(MVC_Core::$ss-> domain_CFG['public_settings'])) {
            $domain_ist_settings = MVC_Core::$ss-> domain_CFG['public_settings']['ist'];

            // strings variation
            if(isset(MVC_Core::$ss-> domain_CFG['public_settings']['string_set']))
                $public_string_set = MVC_Core::$ss-> domain_CFG['public_settings']['string_set'];
        } else
            $domain_ist_settings = array('select'=> true);

        // the default set, for non-recognized domains
        if(!isset($public_string_set))
            $public_string_set = "index.issue";

        // Load the public-specific strings
        MVC_NLS::load_Groups('public');
        $strings = MVC_NLS::get_Object('public.'.$public_string_set, true);

        $this->_out['links']['submit'] = (MVC_Core::$ss-> domain_CFG == false || $domain_ist_settings['select'])? "/issue?action=select": "/issue?action=form";

        $this->_out['header']['rightmenu'] = array(
            array(
                'link'=> "/access?action=entrance",
                'text'=> isset(MVC_Core::$ss-> user)? "Staff section" : "System User Login"
            )
// 			array(
// 				// select ist or add directly a certain type ?
// 				'link'=> $this->_out['links']['submit'],
// 				'text'=> $strings['submit']
// 			)
        );
        if(isset(MVC_Core::$ss-> contact_account)) {
            $this-> acc = MVC_Core::$ss-> contact_account;
            $this->_out['header']['rightmenu'][] = array(
                'link'=> "/", // TODO: Some kind of Contact Account control panel ?
                'text'=> MVC_Core::$ss-> contact_account['contact']['email']
            );
        }

        $this->_out['strings'] = &$strings;
        if(isset($in-> page))
            $this-> page = $in-> page;


if(MVC_Core::$ss-> domain_CFG['domain'] =='es5j.cargoclaims.aero')
  {
  $this-> view("public_cebu.html");
      
  } else{
  
        // which body template to use, and run the view
        $this-> site_section = "public". DS. "issue_present.html";
        if(!empty(MVC_Core::$ss-> domain_CFG['html_settings']['embedded']))
            $this-> view("public_embedded.html");
        else
            $this-> view("public.html");
  }
  
  
    }

    /**
     * Select what type of issue is being submitted
     */
    public function action_Select()
    {
        // If domain doesn't have this info configured, don't allow issues to be submitted
        if(!is_array(MVC_Core::$ss-> domain_CFG['public_settings'])) {
            echo "<h1>Unfortunately, this domain is not properly configured yet.</h1><p>Please contact support@cargohub.nl and ask for domain <b>{$_SERVER['HTTP_HOST']}</b> to be configured.</p>";
            return;
        }

        // public_settings
        if(is_array(MVC_Core::$ss-> domain_CFG['public_settings'])) {
            $domain_ist_settings = MVC_Core::$ss-> domain_CFG['public_settings']['ist'];

            if($domain_ist_settings['select'] == false) {
                // ist is not selectable, go directly to action: Add
                MVC_Core::shutdown('/issue?action=form');
            }

            if(!isset($domain_ist_settings['list']))
                $domain_ist_settings['list'] = null;
        } else
            $domain_ist_settings = array('list'=> null);

        array_push($this-> CFG['modules'], "ist_select");

        // prepare output
        $this-> prepare_Header("Select issue type", array("userbar", "evan"));
        $this->_out['header']['rightmenu'] = array(
            array(
                'link'=> "/contact/account?hash=issues",
                'text'=> isset(MVC_Core::$ss-> contact_account)? "List my issues": "Find an issue"
            )
        );

        if(isset(MVC_Core::$ss-> contact_account)) {
            $this-> acc = MVC_Core::$ss-> contact_account;
            $this->_out['header']['rightmenu'][] = array(
                'link'=> "/contact/account",
                'text'=> MVC_Core::$ss-> contact_account['contact']['email']
            );
        }

 
     
        $this-> ist_list = $this-> record-> ist_Get_List($domain_ist_settings['list']);
  
  if(MVC_Core::$ss-> domain_CFG['domain'] =='es5j.cargoclaims.aero')
  {
  $this-> view("public_cebu.html");
      
  } else{
  
       $this-> site_section = "public". DS. "ist_select.html";
        $this-> view("public.html");
  }
 
  

        
    }

    /**
     * Displays the form used to create a new issue from user input.
     * === This is a revised version of the two variants that existed until now, and works on both public and private versions ( public version doesn't have the menubar )
     *
     * This action only handles the actual recording of the issue into the database, and not sending via e-mail, user notifications, etc.
     * - Those are handled in an ajax response action which is used by several actions ( submit issue via e-mail, transfer to partner organization, send by e-mail to third party )
     *
     * TODO: Split the function into several functions, representing logical blocks: Data Preparation, Form generation, Data receive, Insert/Update, Post submit action
     * -- TODO: Support injecting data into form in a controlled way, via an inject/copy plugin (to allow external data to be placed in the form)
     * -- TODO: Support per organization defaults on certain fields
     * -- TODO: Ability to select one of several templates (instead of the current fixed one), without the necessity to create "variants" for record types
     *
     * @param MVC_Request $in
     */
    public function action_Form(MVC_Request $in)

    {
		//print_r($_POST['xfd'] );exit;
        //=== Phase 1: Initialization ===//

        // Initialize and read Record Type (either change or return existing one)
        if($in-> key)
            $rt_id = $this-> rt_Switch($in-> key);
        else
            $rt_id = $this-> rt_Return();

        $ist = $this-> record-> ist_Read($rt_id);
        
        
        $is_ajax = !empty($in->ajax);  //IWI Adding assets from Supplier control contract section 28072026 start

        // Domain settings
        $public_settings = MVC_Core::$ss-> domain_CFG['public_settings'];

        // Determine if staff user is logged in (User mode)
        if(MVC_Core::$ss-> user) {
            $user = MVC_Core::$ss-> user;

            // Test ACL for issue submitting by user on behalf of his organization
            if(!$this-> user-> test_ACL('privileges', 'issue_add')) {
                $this-> evan-> add("You are not allowed create new records.", 'lock');
                $this-> redirect("/user/overview");
            }

            $hide_tos = true;

            // Some user preferences related to submit form
            $operation_preferences = isset(MVC_Core::$ss-> user['preferences']['operations'])? MVC_Core::$ss-> user['preferences']['operations']: array();

            // Admin testing mode ( can load an existing issue into the form )
            if($this-> user-> is_Unrestricted()) {
                $iss_wtr = array('issue', 'type', 'organization', 'xfields', 'files', 'contacts');
                // Loading a random issue with the purpose as being used as test content for creation of a new issue
                if(isset($in-> random)) {
                    $loadiss = $this-> record-> read('#random#'.$ist['id'], $iss_wtr); // Read primary and xfields data from random issue of same type
                    $this-> evan-> add("Admin Testing Mode - Loading data from record ID: <b>{$loadiss['id']}</b>, Track: <b>{$loadiss['track']}</b>", "ok");
                }

                // Load mode is loading a specific issue to test the "Update" function done through the primary issue form
                if(isset($in-> load)) {
                    $loadiss = $this-> record-> read($in-> load, $iss_wtr,
                        null, null, null, array('public'=> true)); // Read the issue meta, xfields, attachments and already linked contacts. Filter the output to show only what should normally be visible to the submitter

                    if(!empty($loadiss)) {
                        $this-> evan-> add("Admin Testing Mode - Loading data from record with TrackID: <b>{$loadiss['track']}</b>", "ok");

                        $stored_issue = array(
                            'id'=> $loadiss['id'],
                            'track'=> $loadiss['track'],
                            'type_id'=> $loadiss['type_id'],
                            'type_key'=> $loadiss['type']['key']
                        );

                        $issue_attachments = $loadiss['files'];
                    } else
                        $this-> evan-> add("Admin Testing Mode - Record with specified ID not found!");
                }

                // Init with existing
                if(!empty($loadiss)) {
                    // reset IST, we only needed the data
                    $this-> record-> cache_Reset(array('ist'));
                    $ist = $this-> record-> ist_Read($this-> rt_Return());
                    $form = $this-> record-> ist_Factory_Form_Object($loadiss['xfields']);
                }
            }

        } else {
            // Public mode - Form presentation depends on "public_settings" (list of groups)
            if(!is_array(MVC_Core::$ss-> domain_CFG['public_settings'])) {
                // If domain doesn't have this info configured, don't allow issues to be submitted
                echo "<h1>This domain is not properly configured yet.</h1><p>Please contact support@cargohub.nl and ask for <b>{$_SERVER['HTTP_HOST']}</b> to be configured.</p>";
                return;
            }

            if(isset(MVC_Core::$ss-> contact_account)) {
                $cac = MVC_Core::$ss-> contact_account;

                // Logged in Contact Account is allowed to "finalize" records which were not sent by mail by feeding back an existing record into the submit form, like it has been just created
                if($in-> load) {
                    $loadiss = $this-> record-> read($in-> load, array('issue', 'type', 'organization', 'xfields', 'files', 'contacts'),
                        null, null, null, array('public'=> true)); // Read the issue meta, xfields, attachments and already linked contacts. Filter the output to show only what should normally be visible to the submitter

                    // Check if the loaded issue was actually submitted by the logged in contact account or one of his managed contacts.
                    if($loadiss['submitter']['id'] != $cac['id']) {
                        // Managed CTX?
                        if(!empty($cac['managed_ctx']))
                            $cac['managed_ctx'] = explode(',', $cac['managed_ctx']);
                        else
                            $cac['managed_ctx'] = array();

                        if(!in_array($loadiss['submitter']['id'], $cac['managed_ctx']))
                            $loadiss = null; // Not found in managed as well, don't allow updating not-owned issue.
                    }
                    if(!empty($loadiss)) {
                        $this-> evan-> add("Loaded record with TrackID: <b>{$loadiss['track']}</b>. Please review the data and complete submission !", "ok");

                        $stored_issue = array(
                            'id'=> $loadiss['id'],
                            'track'=> $loadiss['track'],
                            'type_id'=> $loadiss['type_id'],
                            'type_key'=> $loadiss['type']['key']
                        );

                        $issue_attachments = $loadiss['files'];

                        // Init with existing
                        if(!empty($loadiss))
                            $form = $this-> record-> ist_Factory_Form_Object($loadiss['xfields']);
                    } else {
                        $this-> evan-> add(sprintf("Error while initializing submit form. Record <b>%s</b> not found or not accessible from your account.", $in-> load), "error");
                        $this-> redirect("/contact/account#issues");
                    }
                }
            }
        }

        // Form control JavaScript
        $this-> CFG['components'] = array("modal", "contact_editor");
        array_push($this-> CFG['modules'], "contact_search", "issue/_submit");
        $this-> CFG['jqwidgets'][] = array("scrollbar", "listbox", "combobox", "datetimeinput", "calendar", "dropdownlist");
        $this-> CFG['xjs'] = array(
            'global'=> true,
            'dropzone'=> true,
            'timeentry'=> true,
            'airdate'=> true
        );

        // If $form object wasn't already defined - By either admin's demo/load modes, then create it blank/empty
        if(!isset($form))
            $form = $this-> record-> ist_Factory_Form_Object();

        //=== Phase 2: Form data preparation ===//
        // If using groups, initialize the group model, prepare groups list
        if(isset($ist['settings']['group_type'])) {
            $this-> factory_Model("Group", null, $ist['settings']['group_type']);
            // Load the group plugin for the used group type, it might be necessary
            $group_plugin = MVC_Core::factory_Plugin("group", $ist['settings']['group_type']);

            /*
             * $group_id_list value types:
             * - false: Disable selection, $auto_group must exist and be defined (per type)
             * - array(): Empty array means allowing all groups supported by the record type
             * - array(n, m, p...): A list of groups - Only these are being presented for selection
             */

            // List of groups that are available in the form ( admin has access to all system wide for the issue type, so the id_list is empty )
            if(isset($user)) {
                // Logged in as standard user - The list comes from the user's ACLs (unless he's admin)
                if($this-> user-> is_Unrestricted()) {
                    $group_id_list = array();
                } elseif(isset($public_settings['group']) && isset($public_settings['group']['users2']) && $public_settings['group']['users2']) {
                    // New setting - Force the domain-settings on logged in users too
                    if($public_settings['group']['select'])
                          $group_id_list = $public_settings['group']['list'];
                       
                    else {
                        // No groups selector at all - Use auto-group ( from domain settings )
                        $auto_group = $public_settings['group']['auto'];
                        $group_id_list = false;
                    }
                } else {
                    $group_id_list = array_keys(MVC_Core::$ss-> user['acls']['groups']);
                    
                    
                        
                    if(count($group_id_list)==1) {
                        // For logged in user, non-admin/supervisor, if there's only one group available, do not show the form control to find/select group
                        $auto_group = reset($group_id_list);
                        $group_id_list = false;
                    }
                }
            } elseif(isset($loadiss)) {
                // When an issue is loaded (contact account finalize) the auto-group is the one already present in the issue (cannot be changed?)
                $auto_group = $loadiss['group_id'];
                $group_id_list = false;
            } elseif(isset(MVC_Core::$ss-> contact_account)) {
                // When logged into a contact account, the domain doesn't restrict the list of groups anymore - Allowing to submit to any group available for the record type
                $group_id_list = array();
            } else {
            
            
           
                // Complete public - The domain settings determine the list of groups
                if(isset($public_settings['group'][$ist['settings']['group_type']]))
                    $public_group_settings = $public_settings['group'][$ist['settings']['group_type']];
                else
                    $public_group_settings = $public_settings['group']; // Compatibility mode, for domains with a single group type

                if(isset($public_group_settings['select']) && $public_group_settings['select'])
                    if(isset($public_group_settings['list']))
                        $group_id_list = $public_group_settings['list'];
                    else
                        $group_id_list = array(); // When list is missing, show all groups available for the record type
                else {
                    // No groups selector at all - Use auto-group ( from domain settings )
                    $auto_group = $public_group_settings['auto'];
                    $group_id_list = false;
                }
            }

            if($group_id_list!==false) {
            
          
          
         
                // Extra filter - Check if issue type has restricitons on which group IDs are visible for it ( this are absolute and do not depend on any rights or who accesses the issue type, including admin )
                // - It allows group type sharing between multiple issue types
                if(!empty($ist['group_ids']))
                    $group_id_list = array_intersect($group_id_list, $ist['group_ids']);
                if(empty($group_id_list) && !empty($ist['group_ids']))
                    $group_id_list = $ist['group_ids']; 
  
                $grp_ret = $this-> group-> get_List(array('group', 'foreign'), function(QueryFactory &$q) use ($group_id_list) {
                    // Limit the search to the specified groups
                    if(count($group_id_list)>0)
                        $q
                            -> where("`grp`.id IN(?)", $group_id_list);

                }, null, count($group_id_list)>0? // Order by the list of alphabetically if there's no list (admin or global portal)
                    sprintf("FIELD(`grp`.id, %s)", implode(',', $group_id_list)):
                    '`grp`.name ASC'
                    , 0);

                if($grp_ret && count($grp_ret)>0) {
                    $groups = array();
                    foreach($grp_ret as $grp) {
                        $grp['name'] = trim(preg_replace('/\[.*?\]/', "", $grp['name']));

                        $groups[] = array(
                            'id'=> $grp['id'],
                            'name'=> !empty($grp['xdata']['fullname'])? $grp['xdata']['fullname']: $grp['name'],
                            'link_key'=> !empty($grp['xdata']['search_id'])? $grp['xdata']['search_id']: null	// Search ID depends on group type. For "Airline" group for example is the AWB prefix. Some types do not have it.
                        );
                    }
                }

                // add the 'group' item to the form (to allow selection of the group) - This item is required when it exists.
                $form-> add_Element("Scalar", "group", null, $in-> group, null, 'issue', array('req'=> true));
            }
        }

//==== WRONG PLACEMENT ====//
        /**
         * This part is record type specific, it should be in a plugin, not in the main code !
         */
        if($in-> mawbp)
            $form-> input(array(
                'xfd'=> array('mawb'=> array(
                    'p'=> $in-> mawbp
                ))
            ));
        elseif($in-> mawb)
            $form-> input(array(
                'xfd'=> array('mawb'=> array(
                    'p'=> substr($in-> mawb, 0, 3),
                    's'=> substr($in-> mawb, 4)
                ))
            ));

        if($in-> pstage)
            $form-> input(array(
                'xfd'=> array('pstage'=> $in-> pstage)
            ));

        // Alternate MAWB entry (coming from record gate)
        if(!empty(MVC_Core::$cache-> gate_cfg['form_autofill'])) {
            if(!empty(MVC_Core::$cache-> gate_cfg['form_autofill']['mawb']))
                $form-> input(array(
                    'xfd'=> array('mawb'=> array(
                        'p'=> substr(MVC_Core::$cache-> gate_cfg['form_autofill']['mawb'], 0, 3),
                        's'=> substr(MVC_Core::$cache-> gate_cfg['form_autofill']['mawb'], 4)
                    ))
                ));
            $hide_tos = true;
        }
		
//==== End of wrong placement ====//

        // list of priorities for this issue type is available only for logged in user (they show up as radiobuttons)
        if(isset($user)) {
            $this->_out['ist']['priorities_ex'] = $ist['priorities_ex'];
            $form-> add_Element("Scalar", "priority", null, $ist['settings']['init']['self']['prio'], null, 'issue');

            // In demo/test mode, add the group and priority info to the form (from the demo issue being used)
            if(isset($loadiss)) {
                // Admin's issue base data
                $form-> input(array(
                    'issue'=> array(
                        'group'=> $loadiss['group_id'],
                        'priority'=> !empty($loadiss['priority_key'])? $loadiss['priority_key']: $ist['settings']['init']['self']['prio']
                    )
                ));

                // Admin's preloaded contacts list
                if(!empty($loadiss['contact_links'])) {
                    $contacts = array();
                    foreach($loadiss['contact_links'] as $clink) {
                        $clink['contact']['id'] = (int)$clink['contact_id'];
                        unset($clink['contact']['ctime'], $clink['contact']['user_id']); // Not relevant here
                        $contacts[$clink['role']=='submitter'? 'submitter': ('contact-i'.$clink['contact_id'])] = array(
                            'label'=> $clink['label'],
                            'contact'=> $clink['contact']
                        );
                    }
                }
            }
        }

        //=== Phase 3: Form submit checking/Error fields ===//
			
        if(!empty($_POST)) {
            $this-> comm = $this-> factory_Model('Comm');
            $post = new MVC_Request('post');

            $auto_accept_tos = true;

            // Update mode means that any changes in the form ( xfields, contacts, new uploads ) will update the issue, instead of creating a fresh one.
            if(isset($post-> stored_issue))
                $stored_issue = $post-> stored_issue;
            else {
                // Issue Create array - Will start receiving values depending on various factors
                $isc = array();
                if(isset(MVC_Core::$ss-> domain_CFG))
                    $isc['submit_domain'] = MVC_Core::$ss-> domain_CFG['domain'];
            }

            // Forward form contacts (should not be empty)
            if(isset($post-> contacts))
                $contacts = @json_decode($post-> contacts, true);

            if(empty($contacts)) {
                $this-> evan-> add("Contact information was not received correctly, unable to continue. Possible interface error.");
                goto submit_output;
            }

            // check if the issue type is using groups and yes, if a group for this issue has been selected.
            if(isset($group_plugin)) {
                // Auto-group is active (probably because there was only one group to choose from in the first place, so the control wasn't shown) - Only for issue create. Update doesn't change group if the contol doesn't exist
                if(isset($auto_group) && !isset($stored_issue))
                    $isc['group_id'] = $auto_group;

                if(!isset($auto_group)) {
                    // There was an option to choose a group, and none was selected
                    if($post-> issue['group']> 0) {
                        // the group was correctly selected, introduce it into the $isc for create mode
                        if(!isset($stored_issue))
                            $isc['group_id'] = $post-> issue['group'];
                    } else {
                        // error, must select a group (if group is selectable)
                        $this-> evan-> add("There is a problem with the field <b>Select {$ist['settings']['group_type']}</b>: No option chosen", "warning");
                        $form-> group-> display['error'] = "Select {$ist['settings']['group_type']}";
                        goto submit_output;
                    }
                }
            }

            $attachment_ids = array();

            // All existing and DropZone added attachments (Due to the "Mock" feature, it contains ID's for the existing ones as well)
            if(isset($post-> attachment_ids))
                $attachment_ids = @json_decode($post-> attachment_ids);

            // Legacy file upload via the $_FILES variable (using DropZone's IE <10 fallback mode) - Appends to the list of already existing attachments.
            if(!empty($_FILES['uploads'])) {
                $files_config = array(
                    'linked_with'=> 'pre',
                    'linked_id'=> null,
                    'visibility'=> 'public' // Anything that is created using the Submit form is automatically set as PUBLIC. They can be restricted further from the staff account.
                );

                if(isset($user)) {
                    // Staff member mode
                    $files_config['user_id'] = $user['id'];
                    $files_config['dep_id'] = $user['cdep_id'];
                    $files_config['org_id'] = $user['org_id'];
                }

                $attachment_ids = array_merge($attachment_ids, $this-> file-> process_Uploads($files_config));
            }


// TODO: Move all the storage/connections out of action_Form and into their own function, this one is way too large and difficult to manage!

            // Seems fine until now... Let's try to create or update the issue record - it's still possible that some fields are wrong and create() or update() will fail
            if(isset($stored_issue)) {
##Update mode
                // Is this an update of an existing issue ? ##Start Update Mode
                $new_issue_data = array();
                // On updating, group can be changed from the submit form (if the controls exist)
                if(isset($post-> issue['group']))
                    $new_issue_data['group_id'] = $stored_issue['group_id'] = $post-> issue['group'];

                // Update the primary issue data and xfields
                $riu = $this-> record-> update($stored_issue['id'], $new_issue_data, $post-> xfd, true, false, true);

                // Failed to update the issue? Mark the errors and redisplay the form.
                if($riu===false || is_array($riu)) {
                    if(is_array($riu))
                        foreach($riu as $iu_err) {
                            if(isset($iu_err['plugin_err'])) {
                                // TODO: A way to mark the field when an error is returned by a plugin
                                $this-> evan-> add("Field <b>{$iu_err['fn']}</b> error: {$iu_err['plugin_err']}", "warning");
                            } else {
                                // standard field error
                                $this-> evan-> add("There is a problem with the field <b>{$iu_err['fn']}</b>: ".__('issue.'.$iu_err['err'], true), "warning");
                                $form-> {$iu_err['fk']}-> display['error'] = __('issue.'.$iu_err['err'], true);
                            }
                        } else
                        $this-> evan-> add("Failed to update the issue record, data may have been lost. Please contact support!", "error");
                    goto submit_output;
                } else {
                    $operation_mode = 'update';

                    // No error, update successful
                    $this-> evan-> add("Verification completed successfully! Updated issue with tracking ID: <b>{$stored_issue['track']}</b>", "ok");

                    // Contact links updating: Read current public contact links in the issue, compare with the posted ones and update the links
                    $con_links = $this-> con-> con2iss_Get_List($stored_issue['id'], null, 'public');
                    $con_links_locator = array();
                    foreach($con_links as $idx=> $clink)
                        $con_links_locator[$clink['contact_id']] = $idx;

                    // Check all contacts that were posted against the existing contacts from the issue.
                    $new_contacts = array();
                    foreach($contacts as $key=> &$contact_line) {
                        if(isset($contact_line['contact']['id'])) {
                            $cid = $contact_line['contact']['id'];
                            // Existing contact?, keep
                            if(isset($con_links_locator[$cid]))
                                unset($con_links[$con_links_locator[$cid]], $con_links_locator[$cid]);
                            else
                                $new_contacts[$key] = $contact_line;
                        } else
                            // New contact, has no ID or not part of the issue yet.
                            $new_contacts[$key] = $contact_line;
                    }

                    // $con_links - Now only contains contacts that existed before and were removed from the list (by not submitting them) - These links will get disconnected
                    if(!empty($con_links)) {
                        foreach($con_links as $clink)
                            $this-> con-> con2iss_Delete($stored_issue['id'], $clink['contact_id']);
                        $this-> record-> counter_Update($stored_issue['id'], 0, 0, 0, 0, -count($con_links));
                    }
                }
##End Update Mode
            } else {
// Not an update. ##Start Create Mode
                if(!isset($contacts['submitter'])) {
                    $this-> evan-> add("The interface did not send a valid submitter contact, unable to continue.");
                    goto submit_output;
                }

                // Determine if the submitter contact is already saved, if not, attempt to discover it in existing contacts, or create it here if it doesn't exist - The ID is needed in $isc variable ("submit_id")
                if(isset($contacts['submitter']['contact']['id']))
                    $isc['submit_id'] = $contacts['submitter']['contact']['id'];
                else {
                    // First, find if there is an user account contact for the specified e-mail address (which should be an unique e-mail)
                    $con_find = $this-> con-> locate("user_self", $contacts['submitter']['contact']['email'], null, null, null, null, false, true);
                    if(is_array($con_find)) {
                        $subm_con_id = (int)$con_find['id'];
                        $this-> evan-> add(sprintf("Submitter is detected as existing staff member with name: <b>%s</b>", $con_find['name']), 'right');
// 						$this-> action_Add_To_Recent(false, $con_find['id']);
                    } else {
                        // Not found as user ? Attempt to locate the contact in existing issue submit contacts (isspost) or in newly added (draft), and reuse if detected
                        $con_find = $this-> con-> locate("isspost|draft", $contacts['submitter']['contact']['email']);
                        if(is_array($con_find)) {
                            foreach($con_find as $found_contact) {
                                // If matching one of the names, use the exact id. If not, let the contact create, but also find the contact account for the specified mail (if it exists) and add the newly created contact to it.
                                if($found_contact['name'] == trim($contacts['submitter']['contact']['name'])) {
                                    $subm_con_id = (int)$found_contact['id'];
                                    $this-> evan-> add(sprintf("Detected and reusing previously submitted contact with name: <b>%s</b>", $found_contact['name']), 'right');
// 									$this-> action_Add_To_Recent(false, $found_contact['id']);
                                }
                            }
                            // No exact match found, then let it be created
                        }
                    }

                    if(empty($subm_con_id))
                        // Not found previous contact ? Create a draft contact with the specified email and name; Will be used as the submitter
                        $subm_con_id = $this-> con-> create("draft", isset($user)? $user['org_id']: null, array(
                            'email'=> $contacts['submitter']['contact']['email'],
                            'name'=> $contacts['submitter']['contact']['name']
                        ), isset($user)? $user['id']: null);
                    // Still nothing ? It means the contact was neither found or not created - Can't submit
                    if(empty($subm_con_id)) {
                        $this-> evan-> add("Unable to create contact record for: <b>{$contacts['submitter']['contact']['email']}</b>.");
                        goto submit_output;
                    }

                    $isc['submit_id'] = $contacts['submitter']['contact']['id'] = (int)$subm_con_id;
                }

                // When logged in, the issue is always placed into the organization, no matter what. Then, depending on the submit_options, it might be shared/sent to another organization.
                if(isset($user))
                    $isc['master_org_id'] = $isc['submit_org_id'] = $user['org_id'];

                $org_specific = array(); // Will be filled by App_Model_Issue::create with data from the plugin
                $ric = $this-> record-> create($ist['id'], $isc, $post-> xfd, false, true, $org_specific); // $ric = Return from Issue Create

                // Failed to create the issue? Mark the errors and redisplay the form.
                if($ric===false || is_array($ric)) {
                    // if there is a problem with any of the fields, mark them by changing the form element's "display" feature
                    if(is_array($ric))
                        foreach($ric as $ic_err) {
                            if(isset($ic_err['plugin_err'])) {
                                // TODO: A way to mark the field when an error is returned by a plugin
                                $this-> evan-> add("Field <b>{$ic_err['fn']}</b> error: {$ic_err['plugin_err']}", "warning");
                            } else {
                                // standard field error
                                $this-> evan-> add("There is a problem with the field <b>{$ic_err['fn']}</b>: ".__('issue.'.$ic_err['err'], true), "warning");
                                $form-> {$ic_err['fk']}-> display['error'] = __('issue.'.$ic_err['err'], true);
                            }
                        }
                    else {
                        $this-> evan-> add("Unable to create the issue record, please try again or contact support!", "error");
                    }
                    goto submit_output;
                } else {
                    // Successful create switches the form into update mode for next data verification (in case the user cancels sending by e-mail and goes back to the form)
                    $stored_issue = array(
                        'id'=> (int)$ric,
                        'track'=> $this-> record-> get_TrackID(),
                        'type_key'=> $ist['key'],
                        'type_id'=> $ist['id'],
                        'group_id'=> $isc['group_id']
                    );
                    $operation_mode = 'create';

                    $this-> evan-> add("Verification completed successfully! Tracking ID assigned: <b>{$stored_issue['track']}</b>", "ok");

                    // Update issue type counter, as a new issue of this type was recorded
                    $this-> record-> ist_Counter_Update($ist['id'], 1);

                    // All contacts are new to this issue in "create" mode (including the "submitter" who might have been created before running issue::create()
                    $new_contacts = $contacts;

                    if(!isset($user)) {
                        // When not in user mode, make sure a contact account account exists for the submitter. Either the logged in one or a newly created one from the submitter's data
                        $m_cac = new App_Model_ContactAccount();
                        if(empty($cac))
                            $cac = $m_cac-> read($contacts['submitter']['contact']['email']);

                        if(empty($cac)) {
                            // When no contact account logged in or found, this is new address from public submit. Create the contact account for the submitter's e-mail and assign a password.
                            // Also send an e-mail about it (containing information about the submitted issue, and a link to access the contact account)
                            $cac_password = $m_cac-> create($contacts['submitter']['contact']['email'], $isc['submit_id'], null, null, array("ist_list"=> $ist['id']));

                            if(!empty($cac_password)) {
                                $cac = array(
                                    'email'=> $contacts['submitter']['contact']['email'],
                                    'password'=> $cac_password,
                                    'contact_id'=> $contacts['submitter']['contact']['id']
                                );

                                $tpl_data = array(
                                    'issue'=> $this-> record-> issue, // Issue cached data?
                                    'email'=> $contacts['submitter']['contact']['email'],
                                    'password'=> $cac['password'],
                                    'contact'=> $contacts['submitter']['contact']
                                );
                                // Add some quick information about the issue type that has just been created to the template.
                                $tpl_data['issue']['type'] = array(
                                    'name'=> $ist['name'],
                                    'short_plural'=> $ist['short_plural']
                                );

                                $message_id = $this-> comm-> create_System_Message($isc['submit_id'], "contact_account_from_new_issue.html", $tpl_data);
                                if($message_id) {
                                    $sent = $this-> comm-> email_Message($message_id, null, null, true, true);
                                    if(!$sent)
                                        $this-> evan-> add("There was a problem while attempting to send an e-mail. Please contact support !");
                                } else
                                    $this-> evan-> add("Unable to create the message containing your issue access password. Please contact support !");
                            } else
                                $this-> evan-> add("System error - Unable to create your account. Please contact support !");
                        } else {
                            // FIXME: IMPORTANT: Make sure the managed contacts that are about to be added are DRAFTS, and not existing contacts from other people !

                            // When not empty, the contact account exists - Add the submitted contact to the list of managed ones if new
                            $cac['managed_ctx'] = explode(',', $cac['managed_ctx']);
                            if($isc['submit_id']!=$cac['id'] && !in_array($isc['submit_id'], $cac['managed_ctx'])) {
                                $cac['managed_ctx'][] = (int)$isc['submit_id'];
                                $cac['managed_ctx'] = implode(',', $cac['managed_ctx']);

                                // Update the Contact Account session if logged in
                                if(MVC_Core::$ss-> contact_account)
                                    MVC_Core::$ss-> contact_account['managed_ctx'] = $cac['managed_ctx'];
                                // And update the database
                                $m_cac-> update($cac['id'], $cac['managed_ctx']);
                            }
                        }
                    }
                }
##End Create Mode
            }

            // $new_contacts - These have been added in the interface, prepare the list of contacts which will be linked
            if(!empty($new_contacts)) {
                $new_contacts_ids = array();
                foreach($new_contacts as $key=> &$contact_line) {
                    if(!isset($contact_line['contact']['id'])) {
                        // If ID not known, it means the user did not use the contact editing form and only typed an e-mail ( or name + email ) in the contact line. Create a contact out of this information
                        $subm_con_id = $this-> con-> create("draft", isset($user)? $user['org_id']: null, array(
                            'email'=> $contact_line['contact']['email'],
                            'name'=> $contact_line['contact']['name']
                        ), isset($user)? $user['id']: null);
                        if(!$subm_con_id) {
                            // Oops, contact could not be created.
                            $this-> evan-> add("Unable to create contact record for {$contact_line['contact']['email']}.");
                            goto submit_output; // No point in trying further, just fail the submit
                        }
                        $contact_line['contact']['id'] = $contacts[$key]['contact']['id'] = (int)$subm_con_id;
                    }

                    // Connect all new contacts to the issue, the submitter one in role "submitter", all others in role "custom"
                    // At this point they should all have ID's
                    $this-> con-> con2iss_Create($stored_issue['id'], $contact_line['contact']['id'], array(
                        'role'=> $key=='submitter'? 'submitter': 'custom',
                        'label'=> $contact_line['label'],
                        'visibility'=> "public", // Initial visibility for contacts in the submit form is public - They can be restricted later from staff section
                        'org_id'=> isset($user)? $user['org_id']: null,
                        'user_id'=> isset($user)? $user['id']: null
                    ), false);

                    $new_contacts_ids[] = $contact_line['contact']['id'];
                }

                // Update type of all new "draft" contacts associated to this issue to "isspost"
                // -- An other types are not affected by this query, they remain the way they are ( already isspost, or user_self or anything else )
                $q=QueryFactory::update()
                    -> table(MVC_Core::$db-> tab['ctx'])
                    -> where("`id` IN (?)", $new_contacts_ids)
                    -> where("`type` = 'draft'")
                    -> values(array(
                        'type'=> "isspost"
                    ));
                MVC_Core::$db-> query($q);
            }

            // Check if any attachments were submitted, either through standard upload or as a list created by DropZone
            if(count($attachment_ids)>0) {
                // Make sure any files (both pre-upload and post-submit) are properly connected to the issue
                $this-> file-> update_Extra($attachment_ids, array(
                    'linked_with'=> "issue",
                    'linked_id'=> $stored_issue['id']
                ));

                // Load complete list (to be displayed as rows or inside DropZone) - This overrides the "Admin Load" list of issue attachments
                $issue_attachments = $this-> file-> get_List($attachment_ids);
            }

            $this-> record-> counter_Update($stored_issue['id'], 0, count($attachment_ids), 0, 0, count($new_contacts));

            // Primary data recorded/updated successfuly ? Create per-organization data if user is logged in.

            // In user mode, per-organization data is created or updated (Department ids, priority, status, counters)
            // It will be further updated if the issue is shared/transferred
            if(isset($user)) {
                if(!isset($org_specific))
                    $org_specific = array(); // convos, notes, tasks, propfields are not added on record time (they might get updated later - for example when the issue is shared/transferred or edited in the staff account)

                if(isset($post-> issue['priority']))
                    $org_specific['priority_key'] = $post-> issue['priority'];

                switch($operation_mode) {
                    case 'create':

                        // TODO: Support here dynamic assignment, if configured for the organization
                        // -- Instead of the simple option of no assignment / user assignment, it should also support:
                        // -- Automatic choice of a user from one of the departemnts
                        // -- Assignment to an entire department

                        if(!empty($operation_preferences['auto_assign'])) {    
                            $org_specific['assigned_to'] = $user['id'];
                            //	DISABLED: If user chose to auto-assign new issues to himself, the status is set to "inpr" (Processing), otherwise it defaults to "new" (New)
                            //	$org_specific['status_key'] = "inpr";
                            // Update the user assigned counter
                            $this-> user-> update($user['id'], array(
                                'count_issues'=> "`count_issues` + 1"
                            ), true);
                            MVC_Core::$ss-> user['count']['issues']++;
                        }                                                   

                        // Connect user's org to the issue, having the starting point the user's own department
                        // -- params:
                        // -- false = Don't update issue, it was just created
                        // -- true = No issue and organization checks
                        // -- true = Propagate deps using the new reclinks feature
                        // -- "self" = Add the organization in "self created" mode (affects initial status/priority)
                        $this-> record-> org_Add($stored_issue['id'], $user['org_id'], $user['cdep_id'], $org_specific, false, true, true, 'self');

                        // And send notifications to the organization's subscribed users - This is also re-reading the issue !
                        // Must re-read the record, because notification variables are based on stored format, not on form content
                        $this-> record-> cache_Reset(array('issue'));
                        $rec = $this-> record-> read($stored_issue['id'], array('issue', 'group', 'xfields', 'submitter', 'contacts', 'files'));

                        // Trigger notification for new record, self-created mode
                        $this-> ntf_New_Record($rec, $user['org_id'], null, 'self');
                        break;
                    case 'update':
                        // - TODO: When updating, verify the department restriction setting and update the organization specific data (either just user dep or full org) - This only happens if the "Message" mode was set (otherwise the user is imediately redirected to the overview page)
                        // - User assignment is not changed

                        break;
                }
            }

            // Depending on various factors and options, a window might also pop up:
            // -- The send by mail / transfer window, with the ability to send the issue via e-mail to another party (or even transfer between organizations)
            // -- An simpler "Issue recorded" window which would then forward to another view of the issue when closed.
            if(isset($user) && isset($post-> submit_options)) {
                $process_mode = $post-> submit_options['process']; // record , transfer, tasks (Note: For staff users - Transfer means opening the E-mail tab)
            } else {
                $process_mode = "transfer"; // For frontend users or public - Transfer means opening the send mail window (to be replaced with newer, cleaner interface)
            }

            // On successful form submit, gate_cfg should be emptied, to avoid setting the incorrect defaults !
            unset(MVC_Core::$cache-> gate_cfg['form_autofill']);

            // Reset issue local cache so the entire issue content is available for any kind of transfer/send (ignoring if it was a create, update or just pass-through)
            $this-> record-> cache_Reset(array("issue"));
            
            
            
//                 IWI Risk calculation matrix calculation should be the same when adding a new record in the quality event  start
//          
                
                if($ist['id'] == 31){
            // if (isset($_POST['xfd']['severity']) && isset($_POST['xfd']['probability'])) {
                 $severity = $_POST['xfd']['severity'];
                 $probability = $_POST['xfd']['probability'];
 
               
                if ($severity == 0 && $probability == 0) {
                    $insertiodData['priority_key'] = "5low";
                   // $this->updateCapaiodStatus($this-> issue['id'], $insertiodData);
 
                } elseif ($severity == 0 && $probability == 1) {
                 $insertiodData['priority_key'] = "5low";
                //$this->updateCapaiodStatus($this-> issue['id'], $insertiodData);
 
                }
                elseif ($severity == 0 && $probability == 2 ) {
                    $insertiodData['priority_key'] = "4med";
                   //$this->updateCapaiodStatus($this-> issue['id'], $insertiodData);
   
                   }
                elseif ($severity == 1 && $probability == 0) {
                    $insertiodData['priority_key'] = "5low";
                   //$this->updateCapaiodStatus($this-> issue['id'], $insertiodData);
   
                   }
                   elseif ($severity == 1  && $probability == 1) {
                    $insertiodData['priority_key'] = "4med";
                   //$this->updateCapaiodStatus($this-> issue['id'], $insertiodData);
   
                   }
                   elseif ($severity == 2 && $probability == 0 ) {
                    $insertiodData['priority_key'] = "4med";
                   //$this->updateCapaiodStatus($this-> issue['id'], $insertiodData);
   
                   }
 
                   elseif ($severity == 1  && $probability == 4) {
                    $insertiodData['priority_key'] = "1crt";                                  //urgent changed to critical
                   //$this->updateCapaiodStatus($this-> issue['id'], $insertiodData);
   
                   }
                   elseif ($severity == 2 && $probability == 3) {
                    $insertiodData['priority_key'] = "1crt";                                   //urgent changed to critical
                   //$this->updateCapaiodStatus($this-> issue['id'], $insertiodData);
   
                   }
                   elseif ($severity == 3 && $probability == 2) {
                    $insertiodData['priority_key'] = "1crt";                                     //urgent changed to critical
                   //$this->updateCapaiodStatus($this-> issue['id'], $insertiodData);
   
                   }
                   elseif ($severity == 4 && $probability == 1) {
                    $insertiodData['priority_key'] = "4med";
                  // $this->updateCapaiodStatus($this-> issue['id'], $insertiodData);
   
                   }
                   elseif ($severity == 2 && $probability == 4 ) {
                    $insertiodData['priority_key'] = "1crt";
                  // $this->updateCapaiodStatus($this-> issue['id'], $insertiodData);
   
                   }
                   elseif ($severity == 3 && $probability ==  3) {
                    $insertiodData['priority_key'] = "1crt";
                   //$this->updateCapaiodStatus($this-> issue['id'], $insertiodData);
   
                   }
                   elseif ($severity == 4  && $probability ==  2) {
                    $insertiodData['priority_key'] = "3hgh";
                   //$this->updateCapaiodStatus($this-> issue['id'], $insertiodData);
   
                   }
 
                   elseif ($severity == 4   && $probability ==  4) {
                    $insertiodData['priority_key'] = "1crt";
                   //$this->updateCapaiodStatus($this-> issue['id'], $insertiodData);
   
                   }
                   elseif ($severity == 3   && $probability ==  4) {
                    $insertiodData['priority_key'] = "1crt";
                   //$this->updateCapaiodStatus($this-> issue['id'], $insertiodData);
   
                   }
                   elseif ($severity == 4   && $probability == 3) {
                    $insertiodData['priority_key'] = "1crt";                                             //urgent changed to critical
                   //$this->updateCapaiodStatus($this-> issue['id'], $insertiodData);
   
                   }
 
                   elseif ($severity == 0    && $probability == 4) {
                    $insertiodData['priority_key'] = "3hgh";
                   //$this->updateCapaiodStatus($this-> issue['id'], $insertiodData);
   
                   }
                   elseif ($severity == 0   && $probability == 3) {
                    $insertiodData['priority_key'] = "4med";
                   //$this->updateCapaiodStatus($this-> issue['id'], $insertiodData);
   
                   }
                   elseif ($severity == 1   && $probability == 3) {
                    $insertiodData['priority_key'] = "3hgh";
                   //$this->updateCapaiodStatus($this-> issue['id'], $insertiodData);
   
                   }
                   elseif ($severity == 1   && $probability == 2) {
                    $insertiodData['priority_key'] = "4med";
                   //$this->updateCapaiodStatus($this-> issue['id'], $insertiodData);
   
                   }
                   elseif ($severity == 2    && $probability == 2) {
                    $insertiodData['priority_key'] = "3hgh";
                   //$this->updateCapaiodStatus($this-> issue['id'], $insertiodData);
   
                   }
                   elseif ($severity == 2    && $probability == 1) {
                    $insertiodData['priority_key'] = "4med";
                   //$this->updateCapaiodStatus($this-> issue['id'], $insertiodData);
   
                   }
                   elseif ($severity == 3    && $probability == 1) {
                    $insertiodData['priority_key'] = "3hgh";
                   //$this->updateCapaiodStatus($this-> issue['id'], $insertiodData);
   
                   }
                   elseif ($severity == 3    && $probability == 0) {
                    $insertiodData['priority_key'] = "4med";
                   //$this->updateCapaiodStatus($this-> issue['id'], $insertiodData);
   
                   }
                   elseif ($severity == 4    && $probability == 0) {
                    $insertiodData['priority_key'] = "1crt";
                   //$this->updateCapaiodStatus($this-> issue['id'], $insertiodData);
   
                   }
                 else {
                    $insertiodData['priority_key'] = "5low";
                // $this->updateCapaiodStatus($this-> issue['id'], $insertiodData);
                }
                $this->record->updateCapaiodStatus($ric, $insertiodData);
                
            // }  
 
        }
       
                
                
//                 IWI Risk calculation matrix calculation should be the same when adding a new record in the quality event end
        
        
//                    IWI Adding assets from Supplier control contract section 28072026 start
                   
                    if ($is_ajax) {
                        header('Content-Type: application/json');
                        echo json_encode(array(
                            'success' => true,
                            'issue_id' => $stored_issue['id'],
                            'track' => $stored_issue['track']
                        ));

                        exit;
                    }
//                    IWI Adding assets from Supplier control contract section 28072026 end

            // Ok, what to do ?
            if($process_mode == 'transfer') {

                // When submitted by staff, jump to e-mail tab automatically and activate the Share Data window - Messaging and organization linking is handled by the user area
                if(isset($user))
                    return $this-> redirect("/record/process:{$stored_issue['track']}#email.share");

                // Non users will get a chance to send the information to an organization (both public and contact account)
                // 1. Organization allocation is determined by the domain settings - Group or Domain owner
                switch($public_settings['ownership']) {
                    // The organization that owns this domain/subdomain will get connected - This also triggers "New record notification"
                    case 'org':
                        $master_org_id = (int)MVC_Core::$ss-> domain_CFG['org_id'];
                        break;
                    // The data group ID is used to determine which organization receives the record (xdata / master_id)
                    case 'group':
                        $group_id = $post-> issue['group']?: $auto_group;
                        // Read the group information and the organization which is set as default handler for said group
                        $group_data = $this-> group-> read($group_id);

                        if($group_data) {
                            $group_data['xdata'] = @json_decode($group_data['xdata'], true);

                            if(is_array($group_data['xdata']) && isset($group_data['xdata']['master_id'])) {
                                // Read organization information for the selected group
                                $master_org_id = (int)$group_data['xdata']['master_id'];

                            } else {
                                $process_config = array(
                                    'mode'=> "error",
                                    'error'=> "Selected {$ist['settings']['group_type']} group doesn't have an associated organization. Please check configuration !"
                                );
                            }
                        } else {
                            // Group not found ?? WTF.
                            $process_config = array(
                                'mode'=> "error",
                                'error'=> "Configuration error - Selected {$ist['settings']['group_type']} group cannot be found."
                            );
                        }

                        break;
                    // If missing show an error message!
                    default:
                        $process_config = array(
                            'mode'=> "error",
                            'error'=> "Configuration error - No organization is set to receive this data. Please contact support"
                        );
                        break;
                }

                // Target organization - Must be configured or submit not possible (it's possible that it works for another data group)
                if(isset($master_org_id)) {
                    // Apparently so, read the information
                    $org = $this-> org-> read($master_org_id);

                    if(empty($org)) {
                        $process_config = array(
                            'mode'=> "error",
                            'error'=> "Configuration error - Incorrect target organization for selected data group."
                        );
                        goto submit_output;
                    }
                    // The record is always allocated to the selected "master" organization.
                    // -- E-mail system might add even more organizations ( based on target contacts )

                    // In transfer mode, the initial status/priority is the "receive" version (unless it was set by the processing plugin during record creation)
                    if(!isset($org_specific))
                        $org_specific = array();

                    // Determine the departments of the $master_org_id that are set to receive this record (based on record type and group_id )
                    $m_dep = new App_Model_Department();
                    $target_org_deps = $m_dep-> get_List($master_org_id, array('dep_target'), function(QueryFactory &$q) use($stored_issue) {
                        // Filter those which are compatible and have "listed" to 1
                        $q
                            -> where("`dep`.listed = 1")
                            -> where("FIND_IN_SET(?, `dep`.use4ist)", MVC_Core::$cache-> current_rt['key']);
                        if(!empty($stored_issue['group_id']))
                            $q-> where("FIND_IN_SET(?, `dep`.use4grp) OR `dep`.use4grp IS NULL", $stored_issue['group_id']);
                    });
                    $dep_ids = array();
                    if(!empty($target_org_deps)) {
                        foreach($target_org_deps as $dep)
                            $dep_ids[] = $dep['id'];
                    }
                    // Note: The list might still be empty if no departments are set to receive !

                    // Last value 'recv' = Set defaults based on 'received' mode (record shared with the org)
                    $r = $this-> record-> org_Add($stored_issue['id'], $master_org_id, $dep_ids, $org_specific, true, false, true, 'recv');
                    if(!empty($r)) {
                        if(empty($r['previously_linked'])) {
                            // Must re-read the record, because notification variables are based on stored format, not on form content
                            $this-> record-> cache_Reset(array('issue'));
                            $rec = $this-> record-> read($stored_issue['id'], array('issue', 'group', 'xfields', 'submitter', 'contacts', 'files'));

                            // Send new issue notification to users of the organization who just received the record
                            // TODO: Support conditional notification based on the data from the record (using the processing plugin)
                            $this-> ntf_New_Record($rec, $org, null, 'recv');

                            $r = $this-> record-> org_SetAsOwnerWithoutTime($stored_issue['id'], $master_org_id);
                            if(empty($r))
                                MVC_Core::$debug-> dump("Unable to set organization as owner/master.", __METHOD__, null, 'error');

                            // Only when creating, trigger an autoresponse - Necessary to avoid sending the message every time the record is fixed/updated
                            if($operation_mode == 'create') {
                                // Use the plugin to determine the keyword which will decide which notification (if any) is used
                                if(!empty($this-> record-> rec_plugin) && method_exists($this-> record-> rec_plugin, "arem_Keyword")) {
                                    $keyword = $this-> record-> rec_plugin-> arem_Keyword($rec, $org['type_id']);
                                } else
                                    $keyword = null;
                                // ... and trigger the response
                                // TODO: Last paramter is not yet used - It's the delay for the message ( so it's sent a bit later just like someone saw the message )
                                // -- This can only happen once an activity queue for the Skynet bot is implemented
                                // -- This will read the damn record AGAIN because of serialize... :(
                                $arem_sent = $this-> arem_Trigger($org['id'], 'recv', $stored_issue['id'], $keyword, 1800);
                                //Generic template sending by Deepak
                                $arem_exclude_sent = $this-> arem_Exclude_Trigger($org['id'], 'recv', $stored_issue['id'], 'general',$xfd_data['mawb']['p']);

                                if(empty($arem_sent) && empty($arem_exclude_sent))
                                    $arem_general_sent = $this-> arem_General_Trigger($org['id'], 'recv', $stored_issue['id'], 'all');
                            }
                        }
                        // else - Already linked, nothing to do.
                    } else {
                        $process_config = array(
                            'mode'=> "error",
                            'error'=> "Unable to forward the record to the organization: {$org['name']}."
                        );
                        goto submit_output;
                    }

                    // TODO - 2. Automatic mailing as variant to Issue2Message (if certain configuration is set)
                    // Issue2Message should be phased out as it's no longer optimal, and many people don't understand how it works ( the fact that they HAVE to send an e-mail )
                    // Instead, they expect the software to do it for them automagically...

                    // 3. Setup Issue2Message e-mail box for manual mailing
                    $process_config = array(
                        // Show the transfer window (is actually a communication window)
                        'mode'=> "transfer",
                        // Organization and Record type are needed to determine target contact(s) - Switch to new target contact system
                        'org_id'=> $master_org_id,
                        'rt_key'=> $stored_issue['type_key'],
                        'dep_ids'=> $dep_ids,
                        // Other organization information
                        'name'=> $org['name'],
                        'domain'=> $org['features']['primary_domain'],
// 						'auto_contact_id'=> isset($org['features']['auto_transfer_contact'])? (int)$org['features']['auto_transfer_contact']: null, // The default contact for automatic transfer
                        'real_attach'=> !empty($org['features']['real_attach'])
                    );

                    array_unshift($this-> CFG['modules'], "issue2message");
                    // These are used only by the WYSIWYG editor in the issue2message component:
                    $this-> CFG['jqwidgets'][] = array("dropdownbutton", "colorpicker", "editor", "dropdownlist");
                    $this-> CFG['issue2message'] = array(
                        'parts'=> $ist['settings']['panels']
                    );

                    // Filter out parts which not supposed to be show in the submit step
                    foreach($this-> CFG['issue2message']['parts'] as $idx=> $part)
                        if(isset($part['nosubmit']))
                            array_splice($this-> CFG['issue2message']['parts'], $idx, 1);

                    // Add the "Contacts" section to parts, which should default to only including the "submitter" contact
                    $this-> CFG['issue2message']['parts'][] = array(
                        "id"=> "contacts",
                        "title"=> "Contact Info",
                        "send"=> true
                    );

                    // Add the attachments section to the parts, having them on by default
                    $this-> CFG['issue2message']['parts'][] = array(
                        "id"=> "attachments",
                        "title"=> "File attachments",
                        "send"=> true
                    );

                    // Read the "auto-contact" if there is one, this will be passed to the ContactSearch System as initialization
// 					if(!is_null($process_config['auto_contact_id'])) {
// 						$process_config['auto_contact'] = $this-> con-> read($process_config['auto_contact_id'], false);
// 					}

                    // If there is a transfer template, use it with the issue data to get the initial subject and content of the message box
                    if(isset($ist['templates']['_includes']['transfer'])) {
                        // Sends the issue data through the transfer header template, to obtain the title
                        $tpl_data = array();
                        $content = $this-> record-> render($stored_issue['id'], array(), null, array($ist['templates']['_includes']['transfer']), $tpl_data);
                        if(isset($tpl_data['_subject']))
                            $subject = $tpl_data['_subject'];
                        else
                            $subject = "New {$ist['name']} submitted for your organization.";

                    } else {
                        // -- If there is no transfer template for this record type, use a hardcoded default subject. Also, nothing else is included before issue data.
                        $content = "";
                        $subject = "New {$ist['name']} submitted for your organization.";
                    }

                    $this-> CFG['issue2message']['message_defaults'] = array(
                        'subject'=> $subject,
                        'content'=> $content
                    );
                    // -- End Issue2Message

                    // Record creation feedback mail (rigid, but still necessary) - Sent AFTER the contact account mail
                    if($operation_mode == 'create' && empty($arem_sent)) {
                        $this-> deprecated_Issue_Create_Feedback_Mail(
                            $contacts['submitter']['contact']['id'], // Submitter
                            array(
                                'issue'=> array_merge($this-> record-> issue, array('type'=> array(
                                    'name'=> $ist['name'],
                                    'short_plural'=> $ist['short_plural']
                                ))),
                                'email'=> $contacts['submitter']['contact']['email'],
                                'password'=> $cac['password'],
                                'contact'=> $contacts['submitter']['contact']
                            )
                        );
                    }

                    if($operation_mode == 'create' && !empty($arem_sent) && empty($arem_exclude_sent)) {
                        $this-> deprecated_Issue_Create_Feedback_Mail(
                            $contacts['submitter']['contact']['id'], // Submitter
                            array(
                                'issue'=> array_merge($this-> record-> issue, array('type'=> array(
                                    'name'=> $ist['name'],
                                    'short_plural'=> $ist['short_plural']
                                ))),
                                'email'=> $contacts['submitter']['contact']['email'],
                                'password'=> $cac['password'],
                                'contact'=> $contacts['submitter']['contact']
                            )
                        );
                    }

                    if($operation_mode == 'create' && !empty($arem_sent) && !empty($arem_exclude_sent) && empty($arem_general_sent)) {
                        $this-> deprecated_Issue_Create_Feedback_Mail(
                            $contacts['submitter']['contact']['id'], // Submitter
                            array(
                                'issue'=> array_merge($this-> record-> issue, array('type'=> array(
                                    'name'=> $ist['name'],
                                    'short_plural'=> $ist['short_plural']
                                ))),
                                'email'=> $contacts['submitter']['contact']['email'],
                                'password'=> $cac['password'],
                                'contact'=> $contacts['submitter']['contact']
                            )
                        );
                    }

                }
                // -- End transfer
            } elseif($process_mode == 'tasks') {
                return $this-> redirect("/record/process:{$stored_issue['track']}#tasks.create"); // Setup tasks after record has been created
            } else { // "record" - Just show the record, wait for user input
                return $this-> redirect("/record/process:{$stored_issue['track']}#panels"); // Jump to record overview, panels tab
            }

            // ?? These probably should not be used here, but in the JS code that is executed after the "process" window completes (record/transfer/automatic)
            /* 				switch($post-> submit_options['finalize']) {
                                case 'show':
                                    break;
                                case 'print':
                                    break;
                                case 'repeat':
                                    break;
                                case 'form':
                                    break;
                            }
             */
        }

        submit_output:
        //=== Phase 4: Preparing output ===//

        if(!empty($_POST))
            // If there is a post and this point has been reached, it means there's a problem with the submit, so forward the post data back into the form
            $form-> input($_POST);

        $this-> prepare_Header("Submit {$ist['name']}");

        // Right menu when using the module without being logged in as staff member
        $this->_out['header']['rightmenu'] = array();
        // Switch issue type right from the submit form
        if(isset($public_settings) && $public_settings['ist']['select'])
            $this->_out['header']['rightmenu'][] = array(
                'link'=> "/issue?action=select",
                'text'=> "Switch issue type"	// TODO: Texts should be read from NLS?
            );

        if(isset($cac)) {
            // Contact Account ?
            $this->_out['header']['rightmenu'][] = array(
                'link'=> "/contact/account#issues",
                'text'=> "List issues"
            );
        }

        if(isset($cac))
            $this->_out['header']['rightmenu'][] = array(
                'spacer'=> true
            );
        $this->_out['header']['rightmenu'][] = array(
            'link'=> "/contact/access?action=login&return=issue-submit",
            'text'=> isset(MVC_Core::$ss-> contact_account)? $cac['contact']['email'] : "E-mail login"
        );

        $this-> form = $form-> output();
        $this-> iname = $ist['name'];
        $this-> jqxtheme = MVC_Core::$ss-> user['preferences']['jqx_theme'];

        //== Issue submit main JS data ==//

        $this-> CFG['issue_submit'] = array(
            'ist'=> array(
                'id'=> (int)$ist['id'],
                'name'=> $ist['name'],
                'short_plural'=> $ist['short_plural']
            )
        );
        // User-mode only stuff
        if(isset($user)) {
            $this-> CFG['issue_submit']['user_mode'] = true; // True = User mode, ability to set priority and have certain operations for when data is validated, adding issue to own organization; False = public mode, domain settings determine what happens

            $this-> organization = MVC_Core::$ss-> organization;
            $this-> department = MVC_Core::$ss-> department;

            // Default settings for Submit Options ?
// 			if(!empty($operation_preferences['submit_2org']))
// 				$this-> CFG['issue_submit']['submit_options']['department'] = 'org';
            if(!empty($operation_preferences['send_bymail']))
                $this-> CFG['issue_submit']['submit_options']['process'] = 'transfer';

            // Forward user mode submit options
            if(isset($post-> submit_options))
                $this-> CFG['issue_submit']['submit_options'] = $post-> submit_options;

            // Issue type flags affecting user operations
            if(isset($ist['settings']['flags']))
                $this-> CFG['issue_submit']['user_flags'] = $ist['settings']['flags'];
            else {
                // By default, show priority selector
                $this-> CFG['issue_submit']['user_flags'] = array('priority'=> true);
            }
        }

        if(isset($hide_tos)) {
            $this-> hide_tos = true;
            $this-> CFG['issue_submit']['hide_tos'] = true;
        } else
            if(isset($auto_accept_tos))
                $this-> CFG['issue_submit']['auto_accept_conditions'] = $auto_accept_tos;

        if(isset($stored_issue))
            $this-> stored_issue = $this-> CFG['issue_submit']['stored_issue'] = $stored_issue;

        // Group-related data
        if(isset($group_plugin)) {
            // Single automatic group
            if(isset($auto_group))
                $this-> CFG['issue_submit']['auto_group'] = $auto_group; // This is not really used in JS anymore?

            // Multiple groups available for selection
            if(!empty($groups)) {
                $this-> CFG['issue_submit']['group_name'] = $group_plugin-> name;
                $this-> CFG['issue_submit']['group_type'] = $ist['settings']['group_type'];
                $this-> CFG['issue_submit']['groups'] = $groups;
            }
        }

        // Attachments list, either from admin's "load" mode, or after successful form verification or forwarding list when verification failed)
        if(isset($issue_attachments) && count($issue_attachments)) {
            $this-> CFG['issue_submit']['attachments'] = array();
            foreach($issue_attachments as $file) {
                $file['visibility'] = 'public'; // This is not used by dropzone or file list, however it is used by issue2message in the transfer window
                $file['type'] = $file['mimetype']; unset($file['mimetype']);
                $this-> CFG['issue_submit']['attachments'][] = $file;
            }
            $this-> attachments = $this-> CFG['issue_submit']['attachments']; // Also send to template, for fallback mode. TODO: Make sure it's sent only when using IE8/9?
        }

        if(isset($contacts))
            // Contact list forwarding on post submit
            $this-> CFG['issue_submit']['contacts'] = $contacts;
        else {
            // Default submitter contact: user mode = logged in user, public mode = logged in contact account (if it exists)
            if(isset($user)) {
                if(isset($operation_preferences['auto_contact']) && $operation_preferences['auto_contact'] == true) {
                    // User's preferences indicate that his primary contact should be used in the Submitter contact field, but only when contacts were not already sent through the "post"
                    // This is only used for staff members,
                    // 
                      $auto_submitter = $this-> con-> getContactDetByUser(MVC_Core::$ss-> user['id'], false);
//                   if(MVC_Core::$ss-> user['contact_id']>0){
//                    $auto_submitter = $this-> con-> read(MVC_Core::$ss-> user['contact_id'], false);
//                   }else{
//                    $auto_submitter = $this-> con-> getContactDetByUser(MVC_Core::$ss-> user['id'], false);
//                   }
                    
                }else{
                    $auto_submitter = $this-> con-> read(MVC_Core::$ss-> user['contact_id'], false);
                }
            } elseif(isset(MVC_Core::$ss-> contact_account)) {
                // If contact account logged in, the default submitter contact is the logged in contact account
                $cac = MVC_Core::$ss-> contact_account;
                $auto_submitter = $cac['contact'];
            } elseif(!empty(MVC_Core::$cache-> gate_cfg)) {
                // Record-gate injected contact
                if(!empty(MVC_Core::$cache-> gate_cfg) && !empty(MVC_Core::$cache-> gate_cfg['form_autofill']))
                    $auto_submitter = $this-> con-> read(MVC_Core::$cache-> gate_cfg['form_autofill']['con_id'], false);
            }

            if(isset($auto_submitter))
                // Initialize contact list with one contact, the submitter:
                $this-> CFG['issue_submit']['contacts'] = array(
                    'submitter'=> array(
                        'label'=> "Submitted by",
                        'contact'=> array(
                            'id'=> (int)$auto_submitter['id'],
                            'type'=> $auto_submitter['type'],
                            'email'=> $auto_submitter['email'],
                            'name'=> $auto_submitter['name']
                        )
                    ));

            // Does the domain define extra contacts beside the submitter? - Using the "public settings" field, but valid for staff-version too
            if(!isset($public_settings))
                $public_settings = MVC_Core::$ss-> domain_CFG['public_settings'];
            if(isset($public_settings['form_extra_contacts'])) {
                $this-> CFG['issue_submit']['extra_contacts'] = $public_settings['form_extra_contacts'];
            }
        }

        // Data which is set after issue is verified successfuly
        // -- Contains instructions for JS on what to display in a popup after form reload
        // -- Note that the user might cancel this popup if it contains something which requires extra user input ( and not just a simple Alert() window ), which would allow the form to be verified again
        // -- On some modes (Record, Automatic Transafer), closing the window will automatically continue with the "Finalize" code ($submit_options['finalize'])
        if(isset($process_config))
            $this-> CFG['issue_submit']['process_config'] = $process_config;

        // Fields structure for the contact editor
        MVC_NLS::load_Groups("contact");
        $fields_structure = MVC_NLS::get_Object("contact", true);

        // Process the fields stucture a bit before sending it t4o JS
        foreach($fields_structure as &$group) {
            foreach($group['fields'] as $field=> $string) {
                if(strpos($string, "~"))
                    $group['fields'][$field] = explode("~", $string);
            }
        }

        $this-> CFG['issue_submit']['vcard_fields_structure'] = $fields_structure;
        
        
//IWI Risk calculation matrix calculation should be the same when adding a new record in the quality event  start
        
        $self_create_priority=$ist['settings']['init']['self']['prio'];
        $default_severity = '0';
        $default_probability = '0';
        if($self_create_priority=='5low'){
            $default_severity = '0';
        $default_probability = '0';
        }else if($self_create_priority=='4med'){
            $default_severity = '0';
        $default_probability = '4';
        }else if($self_create_priority=='3hgh'){
            $default_severity = '1';
        $default_probability = '4';
        }else if($self_create_priority=='1crt'){
            $default_severity = '1';
        $default_probability = '3';
        }
                $this-> CFG['matrix_val']['default_severity'] = $default_severity;
                $this-> CFG['matrix_val']['default_probability'] =$default_probability;
                
//                IWI Risk calculation matrix calculation should be the same when adding a new record in the quality event end

        // which body template to use (user or public mode), then run the view method
        $this-> site_section = "issues". DS. $ist['key']. DS. $ist['templates']['post'];


        if(isset($user)) { // User
            $this-> module = array(
                'icon'=> "add.png",
                'icon_alt'=> "(Add)",
                'description'=> "Fill in the form and press Proceed",
                'title'=> "Add Record", 'title_para'=> $ist['name']
            );

            $this-> view("private.html");
			
        } else { // Public

            $module_info = array(
//                'title'=> sprintf("Submit %s to <b>%s</b>", $ist['name'], MVC_Core::$ss-> domain_CFG['html_settings']['title'])
                'title'=> sprintf("Submit %s to <b>%s</b>", '', MVC_Core::$ss-> domain_CFG['html_settings']['title'])
            );
//            remove as per client conversation on 05-07-2024 start
//            if(MVC_Core::$ss-> domain_CFG['html_settings']['footer'])
//                $module_info['description'] = 'Free Service Offered by <a href="https://www.cargohub.nl/" target="_blank">CARGOHUB.nl</a>';
//                remove as per client conversation on 05-07-2024 end
            // Hack - only show this text for Air Cargo Claim
            if($ist['key'] == 'cargoclaim') {
                $module_info['details'] = 'Looking for another Airline or GSA ?  <a href="https://www.cargoclaims.aero/">Click here to search</a>&nbsp; &nbsp;';
            }

            $this-> module = $module_info;

            if(!empty(MVC_Core::$ss-> domain_CFG['html_settings']['embedded']))
                $this-> view("public_embedded.html");
            else
                $this-> view("public.html");
        }
    }

    /**
     * This function is called for interactive file uploads ( sent by DropZoneJS )
     * It should save a file to Warehouse, then return it's ID to JS so the files ID's can be linked to the issue being submitted (or updated)
     */
    public function action_Upload()
    {
        $post = new MVC_Request('post');

        if(!empty($_FILES)) {
            // All DropZone uploaded files start as "pre". They are connected to the issue at verification time (If the issue is not "checked" and browser moves away, they become orphaned, and deleted by the system occasionally)
            $files_settings = array(
                'linked_with'=> 'pre',
                'visibility'=> 'public'
            );
            // When submitting from a logged in staff member, register those files under his/her ID and his organization
            if(isset(MVC_Core::$ss-> user['id'])) {
                $files_settings['user_id'] = MVC_Core::$ss-> user['id'];
                $files_settings['dep_id'] = MVC_Core::$ss-> user['cdep_id'];
                $files_settings['org_id'] = MVC_Core::$ss-> user['org_id'];
            }

            // Process uploads: This will also update file counters for user and organization (including kbytes total), when these ID's exist (submitted by logged-in user)
            $ids = $this-> file-> process_Uploads($files_settings);
            if(count($ids) > 0) {
                $this-> send_JSON(array('success'=> true, 'id'=> reset($ids)));
            } else {
                header("HTTP/1.0 500 Fail");
                $this-> send_JSON(array('error'=> "File upload has failed."));
            }
        } else {
            header("HTTP/1.0 500 Fail");
            $this-> send_JSON(array('error'=> "No file sent to server."));
        }
    }

    /**
     * Removes a file from the warehouse, as the action of clicking the "Remove File" button in DropZone
     * FIXME: WARNING, This is horribly insecure, basically allowing any file to be deleted - The files/attachments model needs improvment.
     */
    public function action_Remove_File()
    {
        $post = new MVC_Request('post');
        if(!isset($post-> id))
            $this-> send_Fail_JSON("File ID missing from the request. Possible interface error.");

        $r = $this-> file-> delete($post-> id, 'pre');
        if($r)
            return $this-> send_JSON(array('success'=> true));
        else
            return $this-> send_Fail_JSON("File could not be found or no access to it.");
    }

    /**
     * JSON authenticate the view account
     */
    public function action_Authenticate(MVC_Request $in)
    {
        // required fields
        $rq = new MVC_Request('post');
        if(!$rq-> email || !$rq-> password)
            return $this-> send_Fail_JSON("Please fill E-mail address and Password");

        $test = $this-> va-> test_Login($rq-> email, $rq-> password);

        if($test) {
            $this-> va-> do_Login($test['email'], $test['name']);
            $this-> send_JSON(array('success'=> true));
        } else {
            $this-> send_Fail_JSON("Sorry, unable to find your e-mail and matching password.");
        }
    }

    /**
     * Little helper function that prevents displaying the issue by redirecting, when the currently asked track id is not permitted to see by the current user
     */
    private function deny_Issue(MVC_Request $in)
    {
        $this-> evan-> add("Issue with tracking id {$in-> track} was not found or is not accessible to you", "error");
        $this-> redirect("/contact/account?hash=issues");
    }

    /**
     * Displays the issue using a template which can be used for printing (Can fit on an A4 format)
     * - Reads the base issue data, xfields, group, organization data, attachment (file names), submitter contact but no other contacts or conversations.
     * - Does not contain any controls or update ability, it's a pure read-only view
     * - Has only one view mode, the "Key" mode (which must be present in the URL)
     *
     * @param MVC_Request $in
     */
    public function action_Print_View(MVC_Request $in)
    {
        // Check if TrackID is present in the GET request (and if it seems valid)
        $in-> add_Filter('track', "Is_TrackID");

        // redirect to list if trackid is missing or invalid
        if(!$in-> track || !$in-> key) {
            $this-> evan-> add("Cannot open an issue without a valid tracking ID!", "warning");
            $this-> redirect("/contact/account?hash=issues");
        }

        $issue_wtr = array('issue', 'type', 'xfields', 'group', 'organization', 'submitter', 'submitter_xdata', 'files');
        $iss = $this-> record-> read($in-> track, $issue_wtr, null, null, null, array('public'=> true)); // Read files data which should only be visible to everyone (as in, not by staff)

        if($iss['access_key'] != $in-> key)
            return $this-> deny_Issue();

        $ist_read_or = $this-> rt_Scan_Override($iss['type_id']);
        if($ist_read_or != $iss['type_id'])
            $this-> record-> cache_Reset(array('ist'));
        $ist = $this-> record-> ist_Read($ist_read_or);

        // Do a special processing on "Set" xfield types only needed here in print view, so the $ist contains a marker if values are on and off
        // Note: This is an UGLY hack. Make better way to process SET fields
        foreach($ist['xfield_def'] as $key=>&$xfd) {
            if($xfd['t'] == 'set') {
                $set_check = array();
                foreach($xfd['cd_ex'] as $store=> $view) {
                    $el = null;
                    if(isset($iss['xfields'][$key]))
                        foreach($iss['xfields'][$key] as $entry)
                            if($entry['key'] == $store) {
                                $el = array('view'=> $view, 'active'=> true);
                                break;
                            }
                    if(!$el)
                        $el = array('view'=> $view, 'active'=> false);
                    $set_check[] = $el;
                }
                $xfd['set_check'] = $set_check;
            }
        }

        $this-> iss = $iss;
        $this-> ist = $ist;

        // Very raw view of the template (Nothing else is included). It should contain styles directly inside the template, like the e-mail ones.
        if($in-> tpl)
            $template = "print". DS. $in-> tpl. ".html";
        else
            $template = $ist['templates']['print'];

        $this-> view("issues". DS. $ist['key']. DS. $template);
    }

    /**
     * TODO: FIX this entire function for the new data structure
     *
     * Show an existing issue by locating it through it's tracking ID. One of the following keys are required to view the issue:
     * - logged in staff user account, which shows similar information like the "contact_account", but also shows org/shared contacts/files/conversations.
     *   The issue data however is in "read only" mode, the full organization overview page is required to edit the issue, and the user must have access to it in order to change anything.
     *   + Allows writing into the visible conversations only if the logged in user's contact is present in the conversations OR the user is assigned this issue OR the user has universal conversation write privileges
     *   + Allows uploading mode files like the "contact_account" mode, but these files will be set with "org" visibility instead of "public" | Existing files cannot be removed from here, the staff issue overview is needed for that.
     * - logged in "contact_account" ( e-mail + password ), which allows certain updates to the issue data (depends on template), and beside the public information it also shows conversations involving the logged in contact
     *   + Allows writing into any of those conversations by the use of the "external conversation" section
     *   + Allows uploading more files using the dropzone/fallback (which will actually submit the form) - These files will be set as "public" | Existing files cannot be deleted or changed
     * - finally, "access_key" Allows only read-only, but the 'form' is inactive, and not possible to submit anything - Only "public" information is visible (submitted data, public contacts/files/conversations)
     */
    public function action_View(MVC_Request $in)
    {
        // Check if TrackID is present in the GET request (and if it seems valid)
        $in-> add_Filter('track', "Is_TrackID");

        // redirect to list if trackid is missing or invalid
        if(!$in-> track) {
            $this-> evan-> add("Cannot open an issue without a valid tracking ID!", "warning");
            $this-> redirect("/contact/account?hash=issues");
        }

        // If none of the three access methods are present, redirect to the "contact account" login
        if(!MVC_Core::$ss-> contact_account && !MVC_Core::$ss-> user && !isset($in-> key)) {
            $this-> save_Current_Location("contact_login", $in-> track, "View my issue");

            // If "cac" variable is present in the request, do an autologin on the contact account instead of redirecting
            if(isset($in-> email) && isset($in-> email))
                $this-> redirect("/contact/access?action=login&email=".$in-> email."&password=".$in-> password."&autologin=1");
            else
                $this-> redirect("/contact/access?action=login");
        }

        $issue_wtr = array('issue', 'xfields', 'group', 'organization', 'submitter', 'submitter_xdata', 'files', 'contacts', 'conversations');
        $read_settings = array('htmlsc'=> true);

        if(MVC_Core::$ss-> user) {
            // Method 1: Staff user account - Determine if the user has rights to view this issue
            $view_mode = "operator";
            $user = MVC_Core::$ss-> user;

            // Read the issue data and it's typedef
            if($this-> user-> is_Unrestricted()) {
                $read_settings['no_access_update'] = true;
                // Admin can access full issue data (unfiltered)
                $iss = $this-> record-> read($in-> track, $issue_wtr, null, null, null, $read_settings);
            } else {
                // Normal users only see what they're supposed to see (last 3 parametes are for filtering)
                if($this-> user-> test_ACL('privileges', 'issues_view_orgowned')) {
                    $read_settings['ignore_dep_filter'] = true;
                    $read_settings['ignore_user_filter'] = true;
                }
                if($this-> user-> test_ACL('privileges', 'issue_access_deps'))
                    $read_settings['ignore_dep_filter'] = true;
                if($user['status'] == 'invisible')
                    $read_settings['no_access_update'] = true;

                $iss = $this-> record-> read($in-> track, $issue_wtr, $user['org_id'], $user['cdep_id'], $user['id'], $read_settings);
            }

            if(empty($iss))
                return $this-> deny_Issue($in);

            // Admin always has access
            if($this-> user-> is_Unrestricted())
                goto allow_issue;

            // ... Everyone else must match the key
            if($iss['access_key'] == $in-> key)
                goto allow_issue;
        } elseif(MVC_Core::$ss-> contact_account) {
            // Method 2: Logged in Contact Account - Has the ability to change -some- data and also send messages.
            $view_mode = "submitter";
            $cac = MVC_Core::$ss-> contact_account;

            // Expand managed accounts
            if(!empty($cac['managed_ctx']))
                $cac['managed_ctx_arr'] = explode(',', $cac['managed_ctx']);

            $iss = $this-> record-> read($in-> track, $issue_wtr, null, null, null, array('public'=> true, 'contact_id'=> $cac['id'])); // Read the data (contacts, files) which is public, and conversations which are either public or relevant to the specified contact_id
            if(empty($iss))
                return $this-> deny_Issue($in);

            // A contact account can see (and update) an issue if the issue "submitter.id" matches the contact ID or one of the managed contacts
            if($iss['submitter']['id'] == $cac['id'] || (isset($cac['managed_ctx_arr']) && in_array($iss['submitter']['id'], $cac['managed_ctx_arr'])))
                goto allow_issue;
        } else {
            // Method 3: Nobody logged in, this is read-only key-based view
            $view_mode = "readonly";

            $iss = $this-> record-> read($in-> track, $issue_wtr, null, null, null, array('public'=> true)); // Read data (contacts, files, conversations) which should only be visible to everyone (as in, not by staff)
            if(empty($iss))
                return $this-> deny_Issue($in);

            if($iss['access_key'] == $in-> key)
                goto allow_issue;
        }
        return $this-> deny_Issue();
        // Go to here when displaying is valid ...
        allow_issue:

        // using MVC_From to forward the entered value when there is an error (update is actually working like the submit form, but with a limited template and certain restrictions)
        $form = $this-> record-> ist_Factory_Form_Object($iss['xfields']);

        $ist_read_or = $this-> rt_Scan_Override($iss['type_id']);
        if($ist_read_or != $iss['type_id'])
            $this-> record-> cache_Reset(array('ist'));
        $ist = $this-> record-> ist_Read($ist_read_or);

        // Contact list (used to assamble the list of contacts associated with this issue)
        $contacts = array();
        foreach($iss['contact_links'] as $clink) {
            $clink['contact']['id'] = (int)$clink['contact_id'];
            unset($clink['contact']['ctime'], $clink['contact']['user_id']); // Not relevant here
            $ct = array(
                'label'=> $clink['label'],
                'contact'=> $clink['contact']
            );
            if($clink['role']=='submitter')
                $ct['submitter'] = true;
            if($clink['role']=='transfer')
                $ct['transfer'] = true;
            $contacts[] = $ct;
        }

        // Attachments list (Both for the template and for JS)
        $files = array();
        foreach($iss['files'] as $attch) {
            $files[] = array(
                'id'=> (int)$attch['id'],
                'name'=> $attch['name'],
                'mimetype'=> $attch['mimetype'],
                'size'=> (int)$attch['size'],
                'mtime'=> (int)$attch['mtime'],
                'visibility'=> $attch['visibility']
            );
        }

        // Since the exteral conversation viewer only works if there's a key allocated to the conversation, make sure all conversations listed here have an external viewing key
        if(isset($iss['conversations']) && is_array($iss['conversations'])) {
            $m_cnv = new App_Model_Conversation();
            foreach($iss['conversations'] as &$convo) {
                if(empty($convo['key'])) {
                    // Set the key here (only happens first time this issue is displayed using this method)

                    $convo_update = array(
                        'access_key'=> sha1(microtime(1) . uniqid())
                    );

                    // Update (set) conversation access key
                    $m_cnv-> update($convo['id'], $convo_update);
                    $convo['key'] = $convo_update['access_key'];
                }
            }
            unset($convo);
        }

        // Was there anything changed ?
        if(!empty($_POST) || (isset($_FILES['uploads']) && $_FILES['uploads']['error'][0]==0)) { // Also support legacy file upload method.
            $post = new MVC_Request('post');

            // Dropzone file uploads
            if(isset($post-> dropzone_file_ids))
                $file_ids = @json_decode($post-> dropzone_file_ids);

            // Legacy file upload via the $_FILES variable (using DropZone's IE <10 fallback mode)
            if(!empty($_FILES['uploads'])) {

                $files_config = array(
                    'linked_with'=> 'pre',
                    'linked_id'=> null
                );
                switch($view_mode) {
                    case 'operator': // Staff member mode
                        $files_config['visibility'] = "shared"; // From "operator" account, new files are uploaded by default into "shared" mode (visible to all partners accessing the issue)
                        $files_config['user_id'] = $user['id'];
                        $files_config['dep_id'] = $user['cdep_id'];
                        $files_config['org_id'] = $user['org_id'];
                        break;
                    case 'submitter': // Contact account, submitter
                        $files_config['visibility'] = "public";
                        $files_config['org_id'] = $iss['org_id']; // The file is assigned to the organization that currently processes the issue
                        break;
                    case 'readonly': // Files should not be possible to be submitted in read-only mode !
                        $this-> evan-> add("Uploading files is not allowed when this module is operating in Read-Only mode !", "error");
                        goto view_output;
                }

                $file_ids = $this-> file-> process_Uploads($files_config);
            }

            // Deny data change in read-only mode ( assuming the HTML was hacked )
            // TODO: Make sure the _view template is aware of the "mode", so there are no fields to be edited (no form controls, no "update" button, no file uploads or DropZone) when in "readonly" mode.
            if($view_mode == 'readonly') {
                $this-> evan-> add("Changing data not allowed when this module is operating in Read-Only mode !", "error");
                goto view_output;
            }

            // Update the issue if some data was changed (the template supports updating and
            if(!empty($post-> xfd)) {
                $update_data = array(
                    'edtime'=> time()
                );
                $riu = $this-> record-> update($iss['id'], $update_data, $post-> xfd); // Don't "Clean", only partial update;
                if($riu===false || is_array($riu)) {
                    $this-> evan-> add("Failed to update !", "error");
                    goto view_output;
                }
            }

            // Make sure any new files (both pre-upload and post-submit) are properly connected to the issue
            if(isset($file_ids)) {
                $this-> file-> update_Extra($file_ids, array(
                    'linked_with'=> "issue",
                    'linked_id'=> $iss['id']
                ));
            }

            // Update success !
            $this-> evan-> add("You have successfully updated the issue {$iss['track']}", "ok", array(
                'permanent'=> true,
                'timeout'=> time()+ 30
            ));

            // TODO: Send a confirmation/notification message to the submitter ($iss['submitter']['id']) that the issue has been updated

            // TODO: Find the contacts of type "transfer" and notify them when an update to the issue is performed (data or new files uploaded)

            // When updating from "operator" mode, goes back to the "Overview" of the same issue (staff side)
            if($view_mode == "operator")
                return $this-> redirect("/record/process:{$iss['track']}#panels");

            // When updating from "submitter" mode, the form is shown again with auto-opening of a "Send by mail" window (similar to the one in Submit), but containing the string "Submit update for..." as the window caption.
            if($view_mode == "submitter") {

                $update_by_mail = true; // hide this for not sending mail 
                goto view_output;

                // TODO: ALL THIS IS GOING TO BE REMOVED
                /*
                                // Find the most recent contact with role "transfer" and send them a system message about the fact that this issue has been updated.
                                $q=QueryFactory::select()
                                -> table(array(MVC_Core::$db-> tab['c2i'], 'c2i'), 'contact_id,cnv_id')
                                -> table(array(MVC_Core::$db-> tab['ctx'], 'ctx'), QueryFactory::autoalias_Add_Prefix("user_id,type,ctime,email,name", '_ctx_'), 'left', '`c2i`.contact_id = `ctx`.id')
                                -> where("`c2i`.issue_id = ?", $iss['id'])
                                -> where("`c2i`.role = 'transfer'")
                                -> order("`c2i`.dt_link", "DESC")
                                -> limit(1);

                                $r = MVC_Core::$db-> query($q);

                                // Found a transfer contact !
                                if($r && count($r)==1) {
                                    $r-> split_by_prefixes = array(
                                        'contact'=> "_ctx_"
                                    );

                                    $conlink = $r-> current();
                                    $conlink['contact']['id'] = $conlink['contact_id'];

                                    // Prepare information about the updated fields
                                    $changed = array();
                                    foreach($_POST['xfd'] as $key=> $val) {

                                        if(empty($val))
                                            continue;

                // 						if($key=='pstage') {
                // 							if($val=='prel')
                // 								$val="Preliminary";
                // 							if($val=='fpr')
                // 								$val="Formal priced";
                // 						}

                // 						if($key=='claim_curr')
                // 							continue;

                                        $changed[] = array(
                                            'k'=> $key,
                                            'n'=> $ist['xfield_def'][$key]['n'],
                                            'val'=> $val // FIXME: This is raw data, it might not make any sense... however it does show that a certain field has been updated. Use some kind of decoding to show the actual data
                                        );
                                    }

                                    $m_comm = new App_Model_Comm();
                                    $msg_id = $m_comm-> create_System_Message($conlink['contact_id'], "issue_externally_updated.html", array(
                                        'ist'=> array(
                                            'name'=> $ist['name']
                                        ),
                                        'issue'=> $iss,
                                        'contact'=> $conlink['contact'],
                                        'updater'=> $this-> con-> read($cac['id']),
                                        'changed'=> $changed
                                    ));
                                    $m_comm-> email_Message($msg_id, null, null, true, true);
                                }
                */
                // TODO: Send an automatic message to the user assigned to the issue (if any), to notify that this issue has been changed by the submitter (The message should contain information of what was changed - uploaded new files or data)
// 				if($iss['assigned_to']) {
                // This is the old notification system, kept only as a reference.
                /* 					// read the info about assigne user
                                    $user = $this-> user-> read($iss['assigned_to'], array('user'), false);
                                    $subject = sprintf("New customer message for issue: %s", $iss['track']);
                                    // In-app message system
                                    $this-> send_Template_Message($user, $subject, "issue_reply.html", $tpldata); // NO LONGER EXISTS

                                    // Read the preferences of the target user to check if he wants to receive e-mail notifications
                                    $target_prefs = $this-> user-> profile_Read($user['id'], 'preferences');
                                    if(isset($target_prefs['email_notify']) && isset($target_prefs['email_notify']['on_reply']) && $target_prefs['email_notify']['on_reply'] == true) {
                                        // Yes, the user wants to receive e-mail
                                        $this-> send_Template_Email($user['email'], $subject, "issue_reply.html", $tpldata); // NO LONGER EXISTS
                                    }
                 */
// 				}

// 				return $this-> redirect("/contact/account#issues");
            }

            /*
            // Read issue again after update, fully... as it is needed to display the updated version and also send by e-mail
            $this-> record-> cache_Reset(array('issue'));
            // TODO: Find a way to do this without reading the entire fucking thing AGAIN...
            $iss = $this-> record-> read($in-> track, array('issue', 'group', 'xfields', 'organization', 'assigned', 'messages', 'files'));

            // Read the configuration file, it contains the subjects of these e-mails
            $email_config_filename = ROOT .DS. 'app'. DS. 'view'. DS. 'issues'. DS. $ist['key']. DS. 'email'. DS. 'config.yaml';

            if(file_exists($email_config_filename))
                // Found, load and decode
                $email_config = MVC_Core::$spyc-> loadFile($email_config_filename);
            else {
                // Config not found or e-mail directory totally missing, unable to send e-mails
                $email_config = null;
                $this-> evan-> add("E-mail configuration missing for issue type <b>{$ist['name']}</b>, e-mails cannot be sent !", "warning");
            }

            $tpldata = array(
                'issue'=> $iss,
                'ist'=> $ist,
                'html'=> MVC_Core::$ss-> domain_CFG['html_settings'],
                'message'=> $post-> msg
            );
            $tpldata['html']['domain'] = MVC_Core::$ss-> domain_CFG['domain'];

            // Forward E-mail, for organizations not yet using the system (but which were defined)
            if(!is_null($email_config) && isset($email_config['subjects']['update']) && $iss['org_id'] > 0 && isset($post-> mail_forward_recipients)) {
                $tpldata['title'] = sprintf("Update of %s with tracking ID: %s", $ist['name'], $iss['track']);
                $tpldata['datatpl'] = DS. "email". DS. "_update.html";

                // Allow responding to the user by using e-mail (Contact type - Reply-To)
                $reply_to = array(
                    $iss['xfields']['e_mail'],
                    $iss['xfields']['name']
                );
                $this-> send_Template_Email($post-> mail_forward_recipients, $email_config['subjects']['update'], "issue_contents.html", $tpldata, true, $reply_to); // NO LONGER EXISTS
            }

            // perform automation
            $this-> automate_On_Event("on_msg_front", $iss['id']);
            */
        }

        view_output:
        //=== Prepare output ===//

        if(isset($update_by_mail)) {
            // Only load the issue2message module and contact search when the view component is prepared for sending the update message
            array_push($this-> CFG['modules'], "issue2message", "contact_search", "contact_editor", "issue_view");
            $this-> update_by_mail = true;
        } else
            // Very fast/quick
            array_push($this-> CFG['modules'], "issue_view");

        $this-> CFG['jqwidgets'][] = array("scrollbar", "listbox", "combobox"); // Possible extras: dropdownlist, datetimeinput, calendar
        if(isset($update_by_mail)) {
            // Elements used in the WYSIWYG editor in the issue2message component:
            $this-> CFG['jqwidgets'][] = array("dropdownbutton", "colorpicker", "editor", "dropdownlist");

            // Data for the Issue2Message module
            $this-> CFG['issue2message'] = array(
                'parts'=> $ist['settings']['panels']
            );

            // Filter out parts which not supposed to be show in the submit step
            foreach($this-> CFG['issue2message']['parts'] as $idx=> $part)
                if(isset($part['nosubmit']))
                    array_splice($this-> CFG['issue2message']['parts'], $idx, 1);

            // Add the "Contacts" section to parts, which should default to only including the "submitter" contact
            $this-> CFG['issue2message']['parts'][] = array(
                "id"=> "contacts",
                "title"=> "Contact Info",
                "send"=> true
            );

            // Add the attachments section to the parts, having them on by default
            $this-> CFG['issue2message']['parts'][] = array(
                "id"=> "attachments",
                "title"=> "File attachments",
                "send"=> true
            );

            // TODO: Perhaps an update template? $ist['templates']['_includes']['update']

            $content = ($iss['submitter']['name']?: $iss['submitter']['xdata']['company_name'])." hereby submits an update to the {$ist['name']}: {$iss['cache']['primary']}";
            $subject = "Updated {$ist['name']}: {$iss['cache']['primary']}";

            $this-> CFG['issue2message']['message_defaults'] = array(
                'subject'=> $subject,
                'content'=> $content
            );
        }

        $this-> CFG['xjs'] = array(
            'global'=> true,
            'dropzone'=> true
        );

        if(!empty($_POST))
            // If there is a post and this point has been reached, it means there's a problem with the update, so forward the post data back into the form
            $form-> input($_POST);

        // Read some information about the organization that is the current owner of this issue
// 		$org = $this-> org-> read($iss['org_id'], array('organization', 'contacts_org_self'));
        // TODO: Contacts are only necessary when forward is ON
// 		$contacts = array();
// 		if(isset($org['contacts']) && is_array($org['contacts']))
// 			foreach($org['contacts'] as $contact)
// 			$contacts[] = array(
// 				'id'=> (int)$contact['id'],
// 				'email'=> $contact['email'],
// 				'name'=> $contact['name']
// 			);

// 		$this-> CFG['issue_view']['email_forward'] = array(
// 			'on'=> !(isset($org['features']['active']) && $org['features']['active']), // When organization is not active, e-mail forward is on
// 			'selectedIndex'=> -1
// 		);
// 		$this-> CFG['issue_view']['org'] = array(
// 			'name'=> $org['name'],
// 			'domain'=> $org['features']['primary_domain'],
// 			'auto_email'=> isset($org['features']['auto_email'])? (int)$org['features']['auto_email']: null,
// 			'contacts'=> $contacts
// 		);
// 		// E-mail autoselect
// 		foreach($contacts as $idx=> $contact)
// 			if($contact['id'] == $this-> CFG['issue_view']['org']['auto_email'])
// 				$this-> CFG['issue_view']['email_forward']['selectedIndex'] = $idx;

        // TODO: Check if this is still needed ? Multi issue type support needs some serious work...
// 		MVC_Core::$ss-> ist = $iss['type_id']; // switch the default issue type to the issue being read

        $this-> prepare_Header($iss['track'], array("userbar", "evan"));

        // Right-side menu
// 		$this->_out['header']['rightmenu'] = array(
// 			array(
// 				'link'=> "/issue?action=select",
// 				'text'=> "Submit new form"
// 			)
// 		);

        if(isset($user)) {
            // Staff user ?
            $this->_out['header']['rightmenu'][] = array(
                'link'=> "/issue/list",
                'text'=> "Issue search"
            );
            $this->_out['header']['rightmenu'][] = array('spacer'=> true);
            $this->_out['header']['rightmenu'][] = array(
                'link'=> "/user/overview#dashboard",
                'text'=> "Staff section"
            );
        } elseif(isset($cac)) {
            // Contact Account ?
            $this->_out['header']['rightmenu'][] = array(
                'link'=> "/contact/account#issues",
                'text'=> "List issues"
            );
            $this->_out['header']['rightmenu'][] = array('spacer'=> true);
            $this->_out['header']['rightmenu'][] = array(
                'link'=> "/contact/account",
                'text'=> $cac['contact']['email']
            );
        } else {
            $this->_out['header']['rightmenu'][] = array(
                'spacer'=> true
            );
            $this->_out['header']['rightmenu'][] = array(
                'link'=> "/contact/access?action=login",
                'text'=> "E-mail login"
            );
        }

        $this-> form = $form-> output();
        $this-> iname = $ist['name'];
        $this-> jqxtheme = MVC_Core::$ss-> user['preferences']['jqx_theme'];

        // Unfortuantely, this is a hybrid display/update thing, so part of the data is a form, part of it is just viewing information (redundancy). Perhaps find a better way?
        $this-> iss = $iss;
        $this-> ist = $ist;

        // Contacts list (read only)
        $this-> contacts = $contacts;

        //== Issue submit main JS data ==//
        $this-> CFG['issue_view'] = array(
            'ist'=> array(
                'id'=> (int)$ist['id'],
                'name'=> $ist['name'],
                'rt_key'=> $ist['key'],
                'short_plural'=> $ist['short_plural']
            ),
            'issue'=> array(
                'id'=> (int)$iss['id'],
                'track'=> $iss['track'],
                'access_key'=> $iss['access_key'],
                'org_id'=> (int)$iss['master_org_id'],
                'dep_ids'=> implode(',', $iss['dep_ids'])
            ),
            'mode'=> $view_mode
        );
        if(isset($update_by_mail)) {
            $this-> CFG['issue_view']['update_by_mail'] = true;
            $this-> CFG['issue_view']['contacts'] = $contacts; // Contact list is needed for issue2message (as contacts can be sent in the message itself)
            $this-> CFG['issue_view']['files'] = $files; // Files list is needed for issue2message (preview, dropdown with disabled elements - files cannot be unselected here)
        }

        // Check if $post contains the field "dropzone_file_ids", which means files were uploaded via ajax before form was checked/submitted
        // In this case, read those files so they can be displayed again in the dropzone (when form verification failed)
        if(isset($post-> dropzone_file_ids)) {
            $issue_files = $this-> file-> get_List(json_decode($post-> dropzone_file_ids));

            $this-> CFG['issue_submit']['dropzone'] = array();
            foreach($issue_files as $file)
                $this-> CFG['issue_submit']['dropzone'][] = array(
                    'id'=> (int)$file['id'],
                    'name'=> $file['name'],
                    'size'=> (int)$file['size'],
                    'type'=> $file['mimetype']
                );
        }

        // show the issue and reply form
        $this-> site_section = "issues". DS. $ist['key']. DS. $ist['templates']['view'];
        $this->_out['form'] = $form-> output();

        if(!empty(MVC_Core::$ss-> domain_CFG['html_settings']['embedded']))
            $this-> view("public_embedded.html");
        else
            $this-> view("public.html");
    }

    /**
     * Displays a list of issues submitted from the logged in view account's e-mail address, and also allows typing an issue TrackID directly in case the list is rather large
     * -- Used for finding issues
     *
     * @param MVC_Request $in
     */
    public function action_Find(MVC_Request $in)
    {
        if(!MVC_Core::$ss-> contact_account && !MVC_Core::$ss-> user) {
            // Not logged in (either as contact account or staff user account) - Redirect to the "Contact login"
            $this-> save_Current_Location("contact_login", $in-> type, "Find my issue");
            MVC_Core::shutdown("/contact/access?action=login");
        }

        // TODO: Review this function
        dd("FIND");
        dd(MVC_Core::$ss-> contact_account);
        die;










        $this-> CFG['modules'][] = "issue_find";

        $this-> CFG['jqwidgets'][] = array("scrollbar", "dropdownlist", "listbox", "calendar", "datetimeinput", "grid");
        $this-> CFG['jqwidgets'][] = array("grid.selection", "grid.sort", "grid.filter", "grid.pager");
        $this-> CFG['xjs'] = array(
            'global' => true,
            'dateformat'=> true
        );
        $this-> CFG['issue_find'] = array();

        // find the e-mail for which the issues are shown
        if(MVC_Core::$ss-> user) {
            $user = &MVC_Core::$ss-> user;
            $email = $user['email'];
            // assmble the variable $acc to simulate a view account for a staff user
            $acc = array(
                'name'=> $user['name'],
                'email'=> $user['email'],
                'xdata'=> array()
            );
            if($this-> user-> is_Unrestricted())
                $acc['xdata']['ist_list'] = null; // Admin has access to all existing issue types, this will tell ist_Get_List to read the full list
            else
                $acc['xdata']['ist_list'] = isset($user['acls']['ist'])? $user['acls']['ist']: null; // Everyone else has a list that depends on their organization, etc.
        } elseif(MVC_Core::$ss-> view_account) {
            $email = MVC_Core::$ss-> view_account['email'];
            // read the email-account info
            $acc = $this-> va-> read($email);
        } else
            throw new Exception("It appears that the logged in user is missing some data from the database. Strange?");

        $this-> CFG['issue_find']['ist_selector'] = array();
        $grid_reading_config = array(
            'email'=> $email,
            'name'=> $acc['name'],
            'ist_settings'=> array()
        );
        // If this account has a list of issue types which might contain issues under this e-mail address, read the issue types to find the xfield which will be displayed in the grid
        $ist_r = $this-> record-> ist_Get_List($acc['xdata']['ist_list'], true);
        if($ist_r) {
            $fk_list = array('e_mail');
            foreach($ist_r as $ist) {
                $ist['settings'] = json_decode($ist['settings'], true);

                // List of available issue types (name+id), for the Issue Type filter selector
                $this-> CFG['issue_find']['ist_selector'][] = array(
                    'id'=> (int)$ist['id'],
                    'key'=> $ist['key'],
                    'name'=> $ist['name']
                );
                // List of xfields
                if(isset($ist['settings']['view_xf']))
                    $grid_reading_config['ist_settings'][(int)$ist['id']] = $ist['settings']['view_xf'];
            }
        }

        MVC_Core::$ss-> view_gridcfg = $grid_reading_config; // put this in session, it will be deleted after the grid data has been read in a subsequent request

        // prepare output
        $this-> prepare_Header("Find your issue", array("userbar", "evan"));
        $this->_out['header']['rightmenu'] = array(
            array(
                'link'=> "/issue?action=select",
                'text'=> "Submit new form"
            )
        );

        if(isset(MVC_Core::$ss-> view_account)) {
            $this-> acc = MVC_Core::$ss-> view_account;
            $this->_out['header']['rightmenu'][] = array(
                'link'=> "/", // TODO: Some kind of ViewAccount control panel ?
                'text'=> MVC_Core::$ss-> view_account['email']
            );
        }

        $this-> acc = $acc;

        // show the form to enter the track-ID
        $this-> site_section = "public". DS. "issue_find.html";
        $this-> view("public.html");
    }

    /**
     * Reads lists of issues under certain conditions, depending on the value of the request parameter "locator". Returns only data from the PRIMARY table, supporting multiple types at once. Xfields are not read, only the "cached" version.
     * - Currently the only supported locator is "contact" - Gets issues submitted by a certain contact (field: submitter.id)
     * - TODO: Later support various other unusual filters, like: Issues in a certain group,  Issues assigned to a user, Issues from an IP address
     *
     * @param MVC_Request $in
     */
    public function action_Get_Special_List(MVC_Request $in)
    {
        // Data preparation
        $prep = array();
        switch($in-> locator) {
            // Show issues submitted by an exact contact (or a list of contacts)
            case 'contact';
                break;
            // Show issues associated with a contact (or more) associated with a certain e-mail address (doesn't matter which organization owns that contact)
            case 'email':
                // Find the contact ID's for the specified e-mail address
                $prep['con_ids'] = $this-> con-> locate($in-> contact_types, $in-> email, null, null, null, null, true);
                break;
            default:
                return $this-> send_JSON(array()); // When the locator type is not recognized, return empty list.
        }

        // Read the primary issue data (multiple types at once)
        $issues = $this-> record-> get_List(null, array(
            'issue', 'type', 'organization', 'group', 'org_specific',
            'flist'=> array('commtime', 'priority_key', 'status_key', 'xfer_time'),
            'custom'=> array(
                array('tab'=> MVC_Core::$db-> table('c2i'), 'fields'=> QueryFactory::autoalias_Add_Prefix("role,label,visibility", '_c2i_'), 'join'=> "right", 'on'=> '`iss`.id = `c2i`.issue_id', 'split'=> array('clink', "_c2i_"))
            )
        ),
            function(QueryFactory &$q, $params) use($in, $prep) {
                // Supported locators
                switch($in-> locator) {
                    case 'contact':
                        $q-> where("`iss`.`submit_id` IN(?)", explode(',', $in-> contact_ids));
                        break;
                    case 'email':
                        $q-> where("`c2i`.contact_id IN(?)", $prep['con_ids']);
                        if($in-> roles)
                            $q-> where("`c2i`.role IN(?)", explode('|', $in-> roles));
                        break;
                    default:
                        // Unrecognized special list - Return nothing.
                        return false;
                }
                // Extra Issue Type filter (Only return issues having a certain type)
                if(!empty($in-> ist_filter))
                    $q-> where("`iss`.type_id IN(?)", explode(',', $in-> ist_filter));

            }, null, '`iss`.`utime` DESC', 0); // 0 = Read all ( for now ). Later there will be server-side paging...

        // Post-Process issue getting them ready for the grid, by keeping only the important fields
        // This is especially needed because ALL these issues are sent at once here, the filtering is at javascript level
        $output = array();
        foreach($issues as $iss) {
            $iss_out = array(
                'id'=> (int)$iss['id'],
                'track'=> $iss['track'],
                'access_key'=> $iss['access_key'],
                'type_id'=> (int)$iss['type_id'],
                'type_key'=> $iss['type']['key'],
                'type_name'=> $iss['type']['name'],
                'cache'=> $iss['cache'],
                'ctime'=> date("Y-m-d H:i:s", $iss['ctime']),
                'utime'=> $iss['utime'],
                'group'=> $iss['group']['name']
            );

            if($iss['master_org_id']) {
                $iss_out['org_id'] = (int)$iss['master_org_id'];
                $iss_out['org_name'] = $iss['organization']['name'];
                $iss_out['priority'] = $iss['priority'];
                $iss_out['status'] = $iss['status'];
                if(isset($iss['xfer']['time']))
                    $iss_out['xfer_time'] = date("Y-m-d H:i:s", $iss['xfer']['time']);
            } else {
                $iss_out['org_name'] = "(unmanaged)";
                $iss_out['priority'] = "(unavailable)";
                $iss_out['status'] = "(unavailable)";
            }

            $output[] = $iss_out;
        }

        // Send the list of issues to the grid
        $this-> send_JSON($output);
    }

    /**
     * Formats an incoming XF record into a grid-visible format by following rules specified in $fks
     *
     * @param array $xf
     * @param array $fks
     * @param string $selector
     */
    private function format_XF(&$iss, &$ist_settings, $selector)
    {
        // little check for missing data to avoid notices
        if(!isset($iss[$selector]))
            return;

        $rule = $ist_settings[$iss['type_id']][$iss[$selector]['fk_id']];
        switch($rule[3]) {
            case 'value':
                $raw_value = $iss[$selector]['value'];
                break;
            case 'int_value':
                $raw_value = $iss[$selector]['raw_value'];
                break;
            case '!either':
                if(!empty($iss[$selector]['value']))
                    $raw_value = $iss[$selector]['value'];
                else
                    $raw_value = $iss[$selector]['int_value'];
        }
        switch($rule[4]) {
            case 'text':
                return $raw_value;
            case 'currency':
                // append the content of the field specified in $rule[5]
                return number_format($raw_value, 2).' '.$this-> format_XF($iss, $ist_settings, $rule[5]);
            case 'translate':
                return $rule[5][$raw_value];
        }
    }

    /**
     * Speciality action for the field type "Airport" which uses a combobox to set it's value.
     */
    public function action_Suggest_Airport(MVC_Request $in)
    {
        $q = $in-> q;

        $airport_field_plugin = MVC_Core::factory_Plugin('field', 'airport', true);

        if(is_numeric($q)) {
            // Search by database ID
            $r = $airport_field_plugin-> load_Data($q);
            if($r && count($r)==1) {
                $row = $r-> current();
                return $this-> send_JSON(array(
                    'id'=> $row['id'],
                    'text'=> $row['IATA'].(!empty($row['IATA'])?': ':'').$row['name'].', '.$row['country']
                ));
            } else
                return $this-> send_JSON(array('text'=> "Invalid airport ID."));
        }

        // remove the text "No results: " if it exists at the beginning of the query
        if(substr($q, 0, 12)=="No results: ")
            $q = substr($q, 12);

        if(preg_match("/([a-z]{3}):(.+)/i", $q, $matches)) { // if this pattern is there, we most likely have a previous result in the box.
            // check if the entry is correct
            $r = $airport_field_plugin-> search_Data($matches[1]);
            if($r && count($r)==1) { // yep !
                $row = $r-> current();
                $this-> send_JSON(array(
                    'id'=> $row['id'],
                    'text'=> $row['IATA'].(!empty($row['IATA'])?': ':'').$row['name'].', '.$row['country']
                ));
                return;
            } else {
                // not found by IATA code, try by country
                $r = $airport_field_plugin-> search_Data($matches[2]);
            }
        } else
            $r = $airport_field_plugin-> search_Data($in-> q);

        if($r && count($r)>0)
            $found = true;
        else {
            if(strlen($q) == 3) // no results for IATA, try forcing another search for 3 characters while ignoring the IATA part
                $r = $airport_field_plugin-> search_Data($in-> q, true);
            if($r && count($r)>0)
                $found = true;
        }

        if(isset($found) && $found) {
            $all = array();
            foreach($r as $row)
                $all[] = array(
                    'id'=> $row['id'],
                    'text'=> $row['IATA'].(!empty($row['IATA'])?': ':'').$row['name'].', '.$row['country']
                );
            $this-> send_JSON($all);
        } else
            $this-> send_JSON(array('text'=> "No results: ". $q));
    }

    /**
     * @deprecated No longer called anywhere
     * Perform automated modifications to an issue when other events are occuring
     * TODO: Maybe put this into the model itself, so it's not duplicated ?
     *
     * @param string $event_type
     * @param int $issue_id Issue which will be modified by the automation
     */
    protected function automate_On_Event($event_type, $issue_id)
    {
        $ist = $this-> record-> ist_Read($this-> rt_Return(), null); // read automation settings for issue type

        if(isset($ist['settings']['automate'][$event_type])) {
            $automation = explode(',', $ist['settings']['automate'][$event_type]);

            // the automation line is actually a list of updates to the issue with the specified issue_id (for now):
            $updates = array();
            foreach($automation as $update) {
                list($field, $value) = explode('=', $update);
                $updates[$field] = $value;
            }

            // update the issue then show it by redirecting to the index action.
            $this-> record-> update($issue_id, $updates);
            $changes = implode(', ', array_keys($updates));
            $this-> evan-> add("Automation system has also modified: <b>{$changes}</b>", "ok");
        }
    }


    /**
     * Connects the target organization to the record when an e-mail is sent to one of the special "target contacts"
     *
     * @param array $link The record to which a message is linked
     * @param array $target_org Should contain "org_id", "dep_ids" ; If "dep_ids" is empty, sharing goes to entire org (well, not exactly, it's controlled by the org_Add() function)
     * @param array $sender Sender contact Who performed the sharing ?
     * @param array $recipient Which target contact was used to determine that sharing should happen ?
     * @param bool $with_message
     * @return boolean
     */
    protected function share_Record_v2($link, $recipient, $sender = null, $with_message = false)
    {
        if(empty($link['id']))
            return false; // Error, no record ID present ?

        $target_org = $this-> org-> targetContact_Check($recipient['id'], $link['type_key']);
        if(empty($target_org))
            return false; // $recipient is not a target contact. Nothing to do

        // Found a valid target contact ! Should contain org_id (int), dep_ids (array) - Information used for share linking; Do the auto-sharing.
        $orglink = $this-> record-> org_Add($link['id'], $target_org['org_id'], $target_org['dep_ids'], array(
            'commtime'=> $with_message? time(): null
        ), true, false, false, 'recv');

        // Add was successful. Update current organization to indicate that sharing was performed (also update process marker to "shared")
        if(is_array($orglink) && !isset($orglink['previously_linked'])) {
            $this-> record-> org_UpdateData($link['id'], $sender['org_id'], array(
                'process'=> "shared",
                'xfer_by'=> $sender['id'],
                'xfer_to'=> $recipient['id'],
                'xfer_time'=> time(),
                'xfer_ip'=> sprintf('%u', ip2long($_SERVER['REMOTE_ADDR']))
            ), true);
            // clear export markers for the record (for all users)
            $this-> record-> marker_UpdateAll($link['id'], array('export_ut'=> false));
        }
        return $orglink;
    }

    /**
     * @deprecated Replaced by "share_Record_v2"
     * Shares the record with the specified partner organization (Done by staff)
     * - This connects the organization to the record, allowing their staff to view
     *
     * @param array $rec
     * @param int $org_id
     */
    private function share_Record($rec, $partner, $sender = null, $recipient = null, $with_message = false)
    {
        if(is_numeric($rec['id']))
            $locator = (int)$rec['id'];
        elseif(isset($rec['track']))
            $locator = $rec['track'];
        else
            return false; // Error, no ID or Track present in the request ?

        $r = $this-> record-> org_Add($locator, $partner['org_id'], $partner['deps_ids'], array(
            'commtime'=> $with_message? time(): null
        ));

        // Add was successful. Update current organization to indicate that sharing was performed (also update process marker to "shared")
        if($r!==false) {
            $this-> record-> org_UpdateData($locator, $sender['org_id'], array(
                'process'=> "shared",
                'xfer_by'=> $sender['id'],
                'xfer_to'=> $recipient['id'],
                'xfer_time'=> time(),
                'xfer_ip'=> sprintf('%u', ip2long($_SERVER['REMOTE_ADDR']))
            ));
        }
        return $r;
    }

    /**
     * @deprecated Replaced by "share_Record_v2"
     * Similar with sharing, it give access to $org_id to the issue, but this time actually changes the organization ownership of the issue (and control over certain things)
     * - This is done either on the frontend submit ( when there is no organization yet handing the issue ), or when an issue is finalized
     *
     * @param array $iss
     * @param int $org_id
     */
    private function transfer_Issue($iss, $org_id, $xfer_by = null, $xfer_to = null)
    {
        if(isset($iss['id']))
            $locator = (int)$iss['id'];
        elseif(isset($iss['track']))
            $locator = $iss['track'];
        else
            return false; // Error, no ID or Track present in the request ?

        // Add the organization to the issue (If already connected, it will silently return "true" without doing anything)
        $r = $this-> record-> org_Add($locator, $org_id);
        if(!$r)
            return false;
        // ... and give it control if connecting succeeded
        $this-> record-> org_SetAsOwner($locator, $org_id, $xfer_by, $xfer_to, true); // This is a transfer including communication
        return true;
    }

    /**
     * Constructs a preview of a message as a pure HTML which simulates how this message would look in the inbox of a recipient.
     * Uses a full body template to present a fake e-mail message view (with From/Subject/To and the body of the message)
     * -- Because the "header" of the message is a text sent and encoded in the URL itself, it cannot be too long. Might switch to POST because of this? Is it even possible using an iFrame ?
     *
     * @param MVC_Request $in
     */
    public function action_Preview_Issue2Message(MVC_Request $in)
    {
        $message = @json_decode($in-> message, true);
        if(!$message) {
            echo "<html><body><p>There was an error in decoding the message parameters.<br />Cannot display the preview !</p></body></html>";
            return;
        }

        $this-> from = $message['sender']['name']. " (". $message['sender']['email']. ")";
        if(!empty($message['subject']))
            $this-> subject = $message['subject'];
        $this-> dt = date("d-M-Y H:i");

        // Read the recipient contact from database or if ID not present, the 'recipient' entry is a new contact that will be created when sending the message
        if(isset($message['recipient']))
            $message['primary'] = $message['recipient'];

        if(!empty($message['primary'])) {
            if(isset($message['primary']['id']))
                $recipient = $this-> con-> read($message['primary']['id'], false);
            else
                $recipient = $message['primary'];
        }

        if(!empty($recipient))
            $this-> to = $recipient;

        if(!empty($message['cc_self']) && ($cc_p = array_search($message['sender']['email'], $message['cc_list']))!==false)
            unset($message['cc_list'][$cc_p]);

        // Self is always placed last
        if(!empty($message['cc_self']))
            // CC-self option present (add "sender" to the list of Cc's - Should be last)
            $message['cc_list'][] = $message['sender'];

        // Check CC
        if(!empty($message['cc_list'])) {
            foreach($message['cc_list'] as &$cc_entry)
                if(is_string($cc_entry)) {
                    preg_match("/^([^@]*)@/", $cc_entry, $name_fragment);
                    $cc_entry = array(
                        'name'=> ucwords(str_replace(".", " ", trim($name_fragment[1]))),
                        'email'=> $cc_entry
                    );
                }
            $this-> cc_list = $message['cc_list'];
        }

        if($in-> params)
            $params = @json_decode($in-> params, true);

        // Prepare Extra Contents ( contents_user, contents_system, _includes )
        $tpl_data = array(); $includes_list = null;

        if(isset($message['text']))
            $message['body'] = $message['text'];
        $tpl_data['_contents_user'] = trim($message['body']);

        if(isset($message['sig_option']) && $message['sig_option']=="true" && !empty(MVC_Core::$ss-> user['signature']))
            $tpl_data['_user_signature'] = MVC_Core::$ss-> user['signature'];

        if(isset($message['issue']))
            $message['record'] = $message['issue'];

        $this-> prologue = "<h3 style='cursor: pointer;' onclick=\"var el = document.getElementById('preview_toggle'); if(el.style.display !== 'none') el.style.display = 'none'; else el.style.display = 'block'; \">
			This is a PREVIEW of an HTML E-mail! <span class='gray'>(click here for more information)</span></h3><div id='preview_toggle' style='display: none;'>
		<p>* Due to software incompatibilities it might look different for the recipient(s) if they are using old/outdated software to read their E-mails. Images might not be visible or the text might not be aligned, missing colors or otherwise deformed.<br />
		* We assume no responsability if the message arrives at the destination in a different shape or with certain missing or difficult to see information.<br />
		* Please make sure your message contains all the required information by using this preview, as the SEND button will immediately transmit the message without any further confirmation!</p>
		<p style='color: blue; text-align: right;'>( This note doesn't appear on the actual E-mail being sent )</p></div>";
        $this-> prologue_style = "padding: 0px 12px; margin-bottom: 8px;";
        $org_id = isset($message['record']['org_id'])? $message['record']['org_id']: null;
        $this-> contents = $this-> record-> render($message['record']['id'], $message['parts'], $org_id, null, $tpl_data, $includes_list); // Render the contents of the message
        $this-> epilogue = true;

        // The rest are added by the issue::render() method:
        $this-> istkey = $tpl_data['ist']['key'];
        $this-> html = $tpl_data['html'];

        // This action is handled as an ajax request
        $this-> init_Template_System();
        $this-> view("fakemailclient.html"); // This template simulates how the e-mail might look to the recipient. It is inspired by Thunderbird.
    }

    /**
     * Attempts to discover an e-mail in the contact database, related to submission of records
     * If just e-mail addresses, an attempt is made to locate the e-mail address in the existing contacts, in order:
     * - 1. If $wts contains "isspost", "isslink" - $link should contain the record id
     * - 2. If $wts contains "issue_target", discover the special target contacts for data sharing; $link should contain record "id" and "type_key"; The record base data is read to get the record type (used for targets)
     * - 3. If $wts contains "org_self", "dep_self", "user_self" - Specific contacts are located in the entire database (to submit to a certain user, depertment or organization)
     * (Other types are not searched for now)
     *
     * @param string $email
     * @param array $wts What to search (Type of contacts); Defaults to "issue_target" (The only use case here)
     * @param array $link Contact linking information (depends on $link['type']); In case of "issue", it should contain ID, TrackID, Type_Key
     * // TODO: Rename "issue" into "record" on all linkage (and code)
     *
     * @return array Contact information matching the e-mail address. The returned contact always contains an ID !
     */
    protected function discover_Contact($email, $wts = array('issue_target'), $link)
    {
        // What to search, what to read... same thing
        $wts = MVC_Model::wtr_Helper($wts);

        // 1. Search in linked record or targets valid for the record type ($link['type_key'])
        if(in_array('isspost', $wts) || in_array('isslink', $wts)) {
            $linked_cons = $this-> con-> con2iss_Get_List($link['id'], false, 'partner');
            // Is the e-mail in one of these (Alread linked) ? If yes, return it for reuse
            foreach($linked_cons as $lcon)
                if(strcasecmp($email, $lcon['contact']['email'])==0) {
                    $lcon['contact']['id'] = (int)$lcon['contact_id'];
                    $lcon['contact']['is_linked'] = true; // To avoid attempting to link again
                    return $lcon['contact'];
                }
        }

        // 2. Try the targets for specified record type - All organizations !
        if(in_array('issue_target', $wts)) {
            $target_con = $this-> org-> targetContact_Find_Email($email, $link['type_key']);
            if(!empty($target_con))
                return $target_con;
        }

        // 3. Locate relevant account contacts (organization, departments or users)
        if(count(array_intersect(array("org_self", "dep_self", "user_self"), $wts))>0) {
            $con_types = array('global');
            if(in_array('org_self', $wts)) {
                $con_types[] = "org_self";
            }
            if(in_array('dep_self', $wts)) {
                $con_types[] = "dep_self";
            }
            if(in_array('user_self', $wts)) {
                $con_types[] = "user_self";
            }

            $acc_con = $this-> con-> locate(implode('|', $con_types), $email, null, null, null, null, false, true); // last true = $return_one (if contact is found, return it, don't continue search)
            if(!empty($acc_con))
                return $acc_con;
        }

        return null; // Nothing found!
    }

    /**
     * Creates a message from the specified issue data and attaches it to the issue itself as a conversation from the sender to the target recipient.
     * -- Builds the message content out of the incoming parameter "message", creates the message itself and a conversation attached to the issue, also sends the message out as an e-mail
     * -- The Transfer action below also calls for this, however it also changes ownership of the issue
     */
    public function action_Issue2Message(MVC_Request $post)
    {
        if(empty($post-> issue['id']))
            return header("HTTP/1.0 400 The record ID is missing from the request");

        $user = MVC_Core::$ss-> user; // Current logged in user, he/she becomes the sender of the message - Or $user is null if there's no logged in user.
        $rec = $post-> issue;
        $convo_type = "issue";
        $convo_link = $rec['id'];

        $message = @json_decode($post-> message, true);
        if(!$message)
            return header("HTTP/1.0 500 There was an error in decoding the message parameters");

        // Read the sender contact
        $sender = $this-> con-> read($post-> sender_id, false);
        if(!$sender)
            return header("HTTP/1.0 404 Sender contact could not be located in the database");
        $sender['is_linked'] = true;

        if(isset($message['recipient']['id'])) {
            // The recipient is exact (has an ID) - Attempt to read it
            $recipient = $this-> con-> read($message['recipient']['id'], false);
            if(!$recipient)
                return header("HTTP/1.0 404 Primary recipient contact could not be located in the database");
        } else {
            // The recipient contact is not precise (but a valid e-mail address might exist)
            $recipient = $message['recipient'];

            if(!isset($recipient['email']))
                return header("HTTP/1.0 400 Error: Neither a contact ID or E-mail address was present in the request");
        }

        // Build recipients array ( To:, Cc: )
        $recipients = array(
            'to'=> array($recipient),
            'cc'=> array()
        );
        $this->record->mailSendTimeUpdate($post-> issue['id'],(isset($post-> issue['org_id']) ? $post-> issue['org_id']: $post->target_org_id));
        // Copy all "extra recipients" into [cc] (e-mail only)
        if(!empty($message['cc_list'])) {
            // ... except if primary is also part of extras (in which case it's discarded)
            if(($cc_p = array_search($recipient['email'], $message['cc_list']))!==false)
                unset($message['cc_list'][$cc_p]);
            // ... or the sender recipient is also part of cc_list
            if(!empty($message['cc_self']) && ($cc_p = array_search($sender['email'], $message['cc_list']))!==false)
                unset($message['cc_list'][$cc_p]);

            foreach($message['cc_list'] as $cc_email) {
                preg_match("/^([^@]*)@/", $cc_email, $name_fragment);
                $recipients['cc'][] = array(
                    'name'=> ucwords(str_replace(".", " ", trim($name_fragment[1]))),
                    'email'=> $cc_email
                );
            }
        }
        // Self is always placed last
        if(!empty($message['cc_self']))
            // CC-self option present (add "sender" to the list of Cc's - Should be last)
            $recipients['cc'][] = $sender;

        // Recipients list built - Attempt to locate contacts in case they don't have ID's
        // - If ID's are present, then they are used directly
        // - If just e-mail addresses, an attempt is made to locate the e-mail address in the existing contacts
        // - If certain contacts are found to be "user_self", add the message to the user's "Inbox" label (org_self, dep_self cannot be processed yet since there is no container)
        $r_ids = array();
        $users_inboxes = array();
        $orgs_linked = array();
        $c_linked_ctx = 0;

        foreach($recipients as $r_group=> &$r_list)
            foreach($r_list as &$r_con) {
                // If primary $recipient and the $sender have the same e-mail, deny sending.
                if($post-> transfer=='true' && $r_group == 'to' && $r_con['email'] == $sender['email'])
                    return header("HTTP/1.0 403 Please use &quot;Include me in CC&quot; option to message yourself. Recipient (To:) must not be your own adddress.");

                if(!isset($r_con['id'])) {
                    // Attempt to locate the recipient in the database by searching for the e-mail
                    $contact = $this-> discover_Contact($r_con['email'], array("isspost", "isslink", "issue_target", "org_self", "dep_self", "user_self"), $rec);
                    if(!$contact) {
                        // Nothing found, this must be new (or unknown to the application) - create contact as "isslink" (since it will be linked anyway)
                        $r_con['id'] = $this-> con-> create(
                            "isslink", !empty($user)? $user['org_id']: null, array(
                            'email'=> $r_con['email'],
                            'name'=> $r_con['name'],
                            'xdata'=> array(
                                'rem'=> sprintf("Auto-generated on submission of record: %s", $rec['track'])
                            ),
                            'count_msgs'=> 0
                        ), !empty($user)? $user['id']: null
                        );

                        // Well, if even create failed, there's a serious problem - Abort sending the message
                        if(empty($r_con['id']))
                            return header("HTTP/1.0 500 Unable to store contact information for address {$r_con['email']}. Sending not possible");

                    } else
                        $r_con = $contact;
                } else {
                    // Check if the contact is not already linked ... not very sure about this
                    /*					$linked_cons = $this-> con-> con2iss_Get_List($rec['id'], null, 'partner');
                                        // Is the e-mail in one of these (Alread linked) ? If yes, return it for reuse
                                        foreach($linked_cons as $lcon)
                                            if($r_con['id'] == $lcon['contact_id'])
                                                $r_con['is_linked'] = true; // To avoid attempting to link again
                    */
                }

                // At this point the contact should have an ID - Depending on what type of contact it is, create additional links or data sharing

                if($r_con['type'] == "user_self") {
                    // TODO: Recipient was found to be a system user. Link the message to his inbox after it is created
                    $to_user = $this-> user-> read($r_con['user_id'], array('user_comm'), false);
                    $users_inboxes[$r_con['user_id']] = (int)$to_user['inbox_id'];

                    // TODO: Perhaps automatically assign the record to that user if he/she is in the recipients list ?
                    $r_con['role'] = "recipient";
                    $r_con['label'] = "Platform user";
                } elseif($r_con['type'] == "org_self") {
                    // TODO: Recipient was found to be an organization. The message should somehow be connected to it

                    // ???
                } else {
                    // General contact type, do some checks:
                    // Discover if this is a target address and share the record if yes
                    $orglink = $this-> share_Record_v2($rec, $r_con, $sender, true);
                    if(!empty($orglink)) {
                        $r_con['role'] = "transfer";
                        $r_con['label'] = "Shared with";
                        if(!isset($orglink['previously_linked']))
                            $orgs_linked[] = (int)$orglink['org_id'];

                    } elseif($r_con['id'] != $sender['id']) {
                        // Add any other recipients as general recipients (however, not the sender himself, since he's probably already part of the record)
                        $r_con['role'] = "recipient";
                        $r_con['label'] = "General recipient";
                    }
                }

                // Link all recipient contacts to the record (if not already linked) - Bcc's are not linked
                // - Note: This might silently fail if some of these contacts have already been linked as part of the form submission
                $ret = $this-> con-> con2iss_Create($rec['id'], $r_con['id'], array(
                    'role'=> $r_con['role'],
                    'label'=> $r_con['label'],
                    'visibility'=> !empty($user)? "org": "public", // Linked contact visibility is public when this is a contact account submit, or "org" when is not
                    'org_id'=> !empty($user)? $user['org_id']: null,
                    'user_id'=> !empty($user)? $user['id']: null
                ), false);
                if(!empty($ret))
                    $c_linked_ctx++;

                if(empty($r_ids[$r_group]))
                    $r_ids[$r_group] = array();
                $r_ids[$r_group][] = (int)$r_con['id'];
            }

        // Start building the message
        $meta = array(
            'org_id'=> !empty($user)? $user['org_id']: null, // If user is logged in, his organization will be the owner of the message. If none is logged in, leave it null (created from outside)
            'from_id'=> $sender['id'],
            'recipients'=> $r_ids // Array of "to", "cc", "bb" - The sender can ses the complete list. All recipients however will get "bcc" removed from their copy of the message
        );

        // Assamble the message contents
        $tpl_data = array(); $includes_list = null;
        $tpl_data['_contents_user'] = trim($message['text']);

        if(isset($message['sig_option']) && $message['sig_option']=="true" && !empty(MVC_Core::$ss-> user['signature']))
            $tpl_data['_user_signature'] = MVC_Core::$ss-> user['signature'];

        // Include the transfer template in the message ?
// 	  	if(???)
// 	  		$includes_list = array('transfer');

        // If "True Attachments" is on, files are not present in the message itself. Instead, they are sent as actual e-mail attachments
        if(isset($message['true_att']) && $message['true_att'] && $message['parts']['attachments'])
            $return_att = true;
        else
            $return_att = false;

        // Not sure what this is... ?
//  	$org_id = isset($message['record']['org_id'])? $message['record']['org_id']: null;
        $this-> record-> cache_Reset();
        $contents = $this-> record-> render($rec['id'], $message['parts'], null, null, $tpl_data, $includes_list, $return_att); // Build the contents from the header text + issue data

        // The data models used for this operation
        $this-> comm = $this-> factory_Model("Comm");
        $this-> cnv = $this-> factory_Model("Conversation", "cnv");

        $message['subject'] = trim($message['subject']);
        // Create the message with the record data
        $msg_iid = $this-> comm-> create('isspost', $contents, empty($message['subject'])? null: $message['subject'], $meta);
        if(!$msg_iid)
            return header("HTTP/1.0 500 Database error: Unable to record the message: Sending not possible");

        // Create the conversation from the message, marking it as started by the logged in user and directed toward the recipient
        $cnv_iid = $this-> cnv-> create('issue', $msg_iid, $rec['id'], true, true);

        if($cnv_iid) { // Conversation successfully created!
            // Not really useful for now... connect the message with the user inboxes if they were in the list of recipients
            if(!empty($users_inboxes))
                foreach($users_inboxes as $rec_user_id=> $rec_user_inbox_id) {
                    // Connect the conversation to the "Inbox" of recipient user, as "unread", and increase the "count_unread" count for that user
                    $this-> cnv-> cnv2lab_Create_Link($rec_user_id, $cnv_iid, $rec_user_inbox_id, "unread");
                    $this-> user-> update($rec_user_id, array(
                        'count_unread'=> "`count_unread`+1"
                    ), true);
                }

            // Send the recorded message as an e-mail
            // "issues". DS. $tpl_data['ist']['key']. DS. "standalone_styles.css" (CSS in e-mails doesn't work properly)
            $ret = $this-> comm-> email_Message($msg_iid, null, null, true, false, $return_att, null, array('extret'=> true));
            if(empty($ret) || empty($ret['sent'])) {
                // TODO: Warn the user somehow that while the message was internally created, the e-mail failed to send.
                MVC_Core::$debug-> dump("E-mail sending has failed.", __METHOD__, null, 'error');
            } else {
                // Update "sui_*" ( SuperUser Info ) fields related to communication
                $this-> record-> update($rec['id'], array(
                    'sui_lmid'=> $msg_iid,
                    'sui_lmdt'=> date("Y-m-d H:i:s"),
                    'sui_lmctx'=> json_encode($ret)
                ));
            }

            // Send "New record" notification to all orgs which received the record by sharing
            if(!empty($orgs_linked)) {
                // Record already loaded
                foreach($orgs_linked as $org_id) {
                    $this-> ntf_New_Record($rec, $org_id, null, 'recv');
                }
            }

            // Update record global counter for conversations and contacts (convos, ctx)
            $this-> record-> counter_Update($rec['id'], 1, 0, 0, 0, $c_linked_ctx);
            /*
                        // ##### OLD Method - Used by the frontend submit only (Staff version is changed)
                        if($post-> operation == "submit") {
                            $r = $this-> transfer_Issue($rec, $post-> target_org_id, $sender['id'], $recipient['id']);
                            if(empty($r))
                                return $this-> send_Fail_JSON("Database error: The message was sent but there was a problem updating the database. SNAFU.");
                            else {
                                if($post-> evan == "true")
                                    $this-> evan-> add("Record submission was successful and the message was sent to the recipient(s). Please check your e-mail.", "ok");

                                // Send "New" notification
                                $this-> ntf_New_Record($rec['type_id'], $rec['id'], $post-> target_org_id);
                                $response = "Record submission was successful and the message was sent to the recipient(s).";
                            }
                        }
                        // ##### NEW Method - Send Record from staff area (No longer used here, since that area was moved to another module !)
                        elseif($post-> partner) {
                            $r = $this-> share_Record($rec, $post-> partner, $sender, $recipient, true);

                            if(!$r)
                                return header("HTTP/1.0 500 Database error: The message was sent but there was a problem updating the database. SNAFU.");

                            $response = "The message was sent to the recipient(s).";
                            if(is_int($r)) {
                                $this-> ntf_New_Record($rec['type_id'], $rec['id'], $post-> partner['org_id'], $post-> partner['deps_ids']);
                                $response.= " Target organization added to the record.";
                            }
                        } else
            */
            // target_org_id should be present in the request - If yes, also transfer ownership of the record to that organization (But not if logged in !)
            // Note: If a target organization was not discovered by scanning the contacts, the record is not shared, which means setAsOwner will fail.
            if($post-> target_org_id && empty($user)) {
                $ret = $this-> record-> org_SetAsOwner($rec['id'], $post-> target_org_id, $sender['id'], $recipients['to'][0]['id'], true); // Transfer via e-mail
                if(empty($ret)) {
                    MVC_Core::$debug-> dump("Unable to transfer ownership of the record to the target organization", __METHOD__, null, 'error');

                    // To handle these edge cases better, such records are instead linked to Organization "1" which is a general capture for all submissions which bad/mismatched/incorrect e-mail addresses
                    // Everything shared to this organization needs to be checked by CargoHub staff
                    $this-> record-> org_Add($rec['id'], 1, null, array(
                        'priority_key'=> '1crt', // All start as critical, because this might mean the data hasn't reached the intended recipient !
                        'status_key'=> 'moni'
                    ));
                    $this-> record-> org_SetAsOwner($rec['id'], 1, $sender['id'], null, true);
                }
            }

            $response = "Sending successful! The message was sent to the recipient(s).";
            $this-> evan-> add($response, "add");
            return $this-> send_JSON(array("success"=> true, "response"=> $response));

        } else
            return header("HTTP/1.0 500 Database error: Unable to setup the communication and send the message");
    }

    /**
     * Notification system - Create an "issue_created_{type}" notification for the sent issue (which should already be cached by the "render" function) for all subscribed users of specified organization
     *
     * @param int $ist_id_or_key Issue type ID (or Key)
     * @param int $rec_id Issue within the specified type
     * @param int $org_id_or_data Organization for which users are searched (or just it's ID)
     * @param string $deps_ids List of departments to filter users
     * @param string $source Where is this record coming from. 'self' Created by users of own organization ; 'recv' Received from a portal or another organization ; 'webapi' Created via the WebAPI
     */
    private function ntf_New_Record($rec, $org_id_or_data, $deps_ids = null, $source = 'self')
    {
        $ist = MVC_Core::$cache-> current_rt;

        // Read the organization information if only ID is specified
        if(is_numeric($org_id_or_data)) {
            $org_id = (int)$org_id_or_data;
            $org = $this-> org-> read($org_id);
        } else {
            $org_id = (int)$org_id_or_data['id'];
            $org = $org_id_or_data;
        }
        unset($org_id_or_data);

        $ntf_subs = $this-> evan-> get_Users_Subscribed_For("issue_created_". $ist['key'], $org_id, null, !empty($deps_ids)? explode(',', $deps_ids): null);
        foreach($ntf_subs as $user_id=> $subscription) {
            // Ignore non-active subscription (normally it should not be read by get_Users_Subscribed_For anyway)
            if(!$subscription['opts']['active'])
                continue;

            // Use the plugin to alter the notification
            if(!empty($this-> record-> rec_plugin) && method_exists($this-> record-> rec_plugin, "ntf_Check")) {

                $ntf_r = $this-> record-> rec_plugin-> ntf_Check("issue_created", $ist, $rec, isset($org['features']['ntf_settings'][$ist['key']])? $org['features']['ntf_settings'][$ist['key']]: null, $subscription['opts']);
                // IF the plugin determined that notification should not be sent, the user is skipped (in some cases they will all be skipped, aka, no notification is being sent at all)
                if(isset($ntf_r['enabled']) && $ntf_r['enabled'] == false)
                    continue;
            }

            $xdata = array(
                'type'=> $ist['name'],
                'track'=> $rec['track'],
                'access_key'=> $rec['access_key'],
            );
            if(isset($rec['cache'])) {
                $xdata['primary'] = $rec['cache']['primary'];
                $xdata['secondary'] = $rec['cache']['secondary'];
            }

            $ntf_iid = $this-> evan-> create($user_id, 'issue', 'created', "New {$ist['name']} added: {$rec['track']}",
                $rec['id'], $ist['id'], !empty($rec['submitter']['org_id'])? $rec['submitter']['org_id']: null,
                $xdata, null, $subscription['opts']['instant']
            );

            if($subscription['opts']['email'])
                if($subscription['opts']['enhanced'])
                    $this-> evan-> generate_Message($ntf_iid, true,
                        "issues". DS. $ist['key']. DS. "_render.html",
                        array('issue'=> $rec, 'ist'=> $ist, 'override'=> true),
                        null // "issues". DS. $ist['key']. DS. "standalone_styles.css"
                    ); // Send the notification e-mail including a custom template and extra data
                else
                    $this-> evan-> generate_Message($ntf_iid, true); // Send standard notification e-mail
        }
    }

    /**
     * Automatic Responder Mail - Locates tempplates for automatic mailing following an event ( like receiving record )
     *
     * @param int $org_id Organization data
     * @param string $event Event for which templates are located
     * @param array $rec Record data
     * @param string $keyword Set from the interface, the template is used and a message is sent when the keyword matches
     * @return bool|array If a template was found, template information is returned. If it failed or there are none, it returns false
     */
    private function arem_Trigger($org_id, $event, $rec_id, $keyword)
    {
        $this-> record-> cache_Reset(array('issue'));
        $rec = $this-> record-> read($rec_id, array('issue', 'group', 'xfields', 'submitter', 'organization'), null, null, null, array('serialize'=> true));

        if(!in_array($event, array('recv')))
            return MVC_Core::$debug-> dump(sprintf("Unsupported automatic mail event: %s", $event), __METHOD__, null, 'error');

        if(empty($keyword))
            return MVC_Core::$debug-> dump("Triggering keyword is necessary for this method", __METHOD__, null, 'error');

        // Load any "arem" templates for the specified record type ID
        $m_tpl = new App_Model_Template();
        $r = $m_tpl-> get_List($org_id, array('template', 'body'), function(QueryFactory &$q) use ($rec) {
            $q
                -> where("`tpl`.role = 'arem'")
                -> where("`tpl`.rt_id = ?", $rec['type_id']);
        });

        if(empty($r))
            return false; // No templates found or error occured during read

        foreach($r as $row) {
            // Post-process
            $row['id'] = (int)$row['id'];
            if(empty($row['description']))
                $row['description'] = $row['title'];

            // decode xdata
            if(!empty($row['xdata'])) {
                $row['xdata'] = json_decode($row['xdata'], true);

                // Check if event matches
                if($row['xdata']['arem_event'] == $event && $row['xdata']['arem_kw'] == $keyword) {
                    // Found it !
                    $template = $row;
                    break;
                }
            }
        }

        if(!isset($template))
            return MVC_Core::$debug-> dump(sprintf("Out of %d AREM templates found, none of them match the keyword %s", count($r), $keyword), __METHOD__, null, 'notice');

        // Construct the message based on the template and record data, then use the mentioned "arem_contact" as the sender, and send the message
        // Note: This uses the new mailer. TODO: Replace issue2message with the new mailer as well (PHPMailer)

        // Assamble the message contents
        $tpl_data = array(
            'domain'=> MVC_Core::$ss-> domain_CFG['domain'],
            'html'=> MVC_Core::$ss-> domain_CFG['html_settings'],
            'organization'=> $rec['organization']
        );

        // Find a way to replace "issue" with "record" and "ist" with "rt" in all templates... ?
        $tpl_data['issue'] = $rec;
        $tpl_data['ist'] = $this-> record-> ist_Read($template['rt_id']);

        $this-> init_Template_System();

        try {
            $template['title'] = $this->_drytpl-> render_String($template['title'], $tpl_data);
            $template['body'] = $this->_drytpl-> render_String($template['body'], $tpl_data);
        } catch(Exception $e) {
            // Error during render ? Probably bad template
            return MVC_Core::$debug-> dump("Template rendering error - Unable to respond", __METHOD__, null, 'error');
        }

        // $emplate should now be actual content, ready to be assembled into a message and then mailed
        $msg_meta = array(
            'org_id'=> $org_id,
            'from_id'=> $template['xdata']['arem_contact'],
            'recipients'=> array(
                'to'=> array($rec['submitter']['id'])
            )
            // TODO: 'cnv_id' - This reply should actually be connected to the message that was sent by the record submitter, and not as a separate conversaiton !
        );

        $msg_iid = $this-> comm-> create('issreply', $template['body'], $template['title'], $msg_meta);
        if(!$msg_iid)
            return MVC_Core::$debug-> dump("Database error: Unable to record the message: Send not possible", __METHOD__, null, 'error');

        // Create the conversation to connect the message to the record
        $this-> cnv = $this-> factory_Model("Conversation", "cnv");
        $cnv_iid = $this-> cnv-> create('issue', $msg_iid, $rec_id, true, true);
        $this-> record-> counter_Update($rec_id, 1);

        // Use PHPMailer to send the message
        return $this-> comm-> msg_Mailer($msg_iid);
    }

    /**
     * Automatic Responder Mail for general mail excluding awb present in keyword- Locates tempplates for automatic mailing following an event ( like receiving record )
     *
     * @param int $org_id Organization data
     * @param string $event Event for which templates are located
     * @param $rec_id
     * @param string $keyword Set from the interface, the template is used and a message is sent when the keyword matches
     * @param $awb
     * @return bool|array If a template was found, template information is returned. If it failed or there are none, it returns false
     */
    private function arem_Exclude_Trigger($org_id, $event, $rec_id, $keyword,$awb)
    {
        $this-> record-> cache_Reset(array('issue'));
        $rec = $this-> record-> read($rec_id, array('issue', 'group', 'xfields', 'submitter', 'organization'), null, null, null, array('serialize'=> true));

        if(!in_array($event, array('recv')))
            return MVC_Core::$debug-> dump(sprintf("Unsupported automatic mail event: %s", $event), __METHOD__, null, 'error');

        if(empty($keyword))
            return MVC_Core::$debug-> dump("Triggering keyword is necessary for this method", __METHOD__, null, 'error');

        // Load any "arem" templates for the specified record type ID
        $m_tpl = new App_Model_Template();
        $r = $m_tpl-> get_List($org_id, array('template', 'body'), function(QueryFactory &$q) use ($keyword, $rec) {
            $q
                -> where("`tpl`.role = 'arem'")
                -> where("`tpl`.rt_id = ?", $rec['type_id'])
                -> where("`tpl`.xdata LIKE(?)", "%$keyword%");
        });

        if(empty($r))
            return false; // No templates found or error occured during read

        foreach($r as $row) {
            // Post-process
            $row['id'] = (int)$row['id'];
            if(empty($row['description']))
                $row['description'] = $row['title'];

            // decode xdata
            if(!empty($row['xdata'])) {
                $row['xdata'] = json_decode($row['xdata'], true);
                $key = explode("-",$row['xdata']['arem_kw']);
                $newKeyword = $key[0];
                if($newKeyword !== "general"){
                    return false;
                }
                if(!empty($key[1]) && isset($key[1])){
                    $awbList = explode(",",$key[1]);
                    foreach ($awbList as $value) {
                        if($value == $awb)
                            return false;
                    }
                }
                // Check if event matches
                if($row['xdata']['arem_event'] == $event && strpos($row['xdata']['arem_kw'], $keyword) !== false) {
                    // Found it !
                    $template = $row;
                    break;
                }
            }
        }

        if(!isset($template))
            return MVC_Core::$debug-> dump(sprintf("Out of %d AREM templates found, none of them match the keyword %s", count($r), $keyword), __METHOD__, null, 'notice');

        // Construct the message based on the template and record data, then use the mentioned "arem_contact" as the sender, and send the message
        // Note: This uses the new mailer. TODO: Replace issue2message with the new mailer as well (PHPMailer)

        // Assamble the message contents
        $tpl_data = array(
            'domain'=> MVC_Core::$ss-> domain_CFG['domain'],
            'html'=> MVC_Core::$ss-> domain_CFG['html_settings'],
            'organization'=> $rec['organization']
        );

        // Find a way to replace "issue" with "record" and "ist" with "rt" in all templates... ?
        $tpl_data['issue'] = $rec;
        $tpl_data['ist'] = $this-> record-> ist_Read($template['rt_id']);

        $this-> init_Template_System();

        try {
            $template['title'] = $this->_drytpl-> render_String($template['title'], $tpl_data);
            $template['body'] = $this->_drytpl-> render_String($template['body'], $tpl_data);
        } catch(Exception $e) {
            // Error during render ? Probably bad template
            return MVC_Core::$debug-> dump("Template rendering error - Unable to respond", __METHOD__, null, 'error');
        }

        // $emplate should now be actual content, ready to be assembled into a message and then mailed
        $msg_meta = array(
            'org_id'=> $org_id,
            'from_id'=> $template['xdata']['arem_contact'],
            'recipients'=> array(
                'to'=> array($rec['submitter']['id'])
            )
            // TODO: 'cnv_id' - This reply should actually be connected to the message that was sent by the record submitter, and not as a separate conversaiton !
        );

        $msg_iid = $this-> comm-> create('issreply', $template['body'], $template['title'], $msg_meta);
        if(!$msg_iid)
            return MVC_Core::$debug-> dump("Database error: Unable to record the message: Send not possible", __METHOD__, null, 'error');

        // Create the conversation to connect the message to the record
        $this-> cnv = $this-> factory_Model("Conversation", "cnv");
        $cnv_iid = $this-> cnv-> create('issue', $msg_iid, $rec_id, true, true);
        $this-> record-> counter_Update($rec_id, 1);

        // Use PHPMailer to send the message
        return $this-> comm-> msg_Mailer($msg_iid);
    }

    /**
     * Automatic Responder Mail for general mail when no other template is set in keyword- Locates tempplates for automatic mailing following an event ( like receiving record )
     *
     * @param int $org_id Organization data
     * @param string $event Event for which templates are located
     * @param $rec_id
     * @param string $keyword Set from the interface, the template is used and a message is sent when the keyword matches
     * @param $awb
     * @return bool|array If a template was found, template information is returned. If it failed or there are none, it returns false
     */
    private function arem_General_Trigger($org_id, $event, $rec_id, $keyword)
    {
        $this-> record-> cache_Reset(array('issue'));
        $rec = $this-> record-> read($rec_id, array('issue', 'group', 'xfields', 'submitter', 'organization'), null, null, null, array('serialize'=> true));

        if(!in_array($event, array('recv')))
            return MVC_Core::$debug-> dump(sprintf("Unsupported automatic mail event: %s", $event), __METHOD__, null, 'error');

        if(empty($keyword))
            return MVC_Core::$debug-> dump("Triggering keyword is necessary for this method", __METHOD__, null, 'error');

        // Load any "arem" templates for the specified record type ID
        $m_tpl = new App_Model_Template();
        $r = $m_tpl-> get_List($org_id, array('template', 'body'), function(QueryFactory &$q) use ($keyword, $rec) {
            $q
                -> where("`tpl`.role = 'arem'")
                -> where("`tpl`.rt_id = ?", $rec['type_id'])
                -> where("`tpl`.xdata LIKE(?)", "%$keyword%");
        });

        if(empty($r))
            return false; // No templates found or error occured during read

        foreach($r as $row) {
            // Post-process
            $row['id'] = (int)$row['id'];
            if(empty($row['description']))
                $row['description'] = $row['title'];

            // decode xdata
            if(!empty($row['xdata'])) {
                $row['xdata'] = json_decode($row['xdata'], true);

                // Check if event matches
                if($row['xdata']['arem_event'] == $event && $row['xdata']['arem_kw'] == $keyword) {
                    // Found it !
                    $template = $row;
                    break;
                }
            }
        }

        if(!isset($template))
            return MVC_Core::$debug-> dump(sprintf("Out of %d AREM templates found, none of them match the keyword %s", count($r), $keyword), __METHOD__, null, 'notice');

        // Construct the message based on the template and record data, then use the mentioned "arem_contact" as the sender, and send the message
        // Note: This uses the new mailer. TODO: Replace issue2message with the new mailer as well (PHPMailer)

        // Assamble the message contents
        $tpl_data = array(
            'domain'=> MVC_Core::$ss-> domain_CFG['domain'],
            'html'=> MVC_Core::$ss-> domain_CFG['html_settings'],
            'organization'=> $rec['organization']
        );

        // Find a way to replace "issue" with "record" and "ist" with "rt" in all templates... ?
        $tpl_data['issue'] = $rec;
        $tpl_data['ist'] = $this-> record-> ist_Read($template['rt_id']);

        $this-> init_Template_System();

        try {
            $template['title'] = $this->_drytpl-> render_String($template['title'], $tpl_data);
            $template['body'] = $this->_drytpl-> render_String($template['body'], $tpl_data);
        } catch(Exception $e) {
            // Error during render ? Probably bad template
            return MVC_Core::$debug-> dump("Template rendering error - Unable to respond", __METHOD__, null, 'error');
        }

        // $emplate should now be actual content, ready to be assembled into a message and then mailed
        $msg_meta = array(
            'org_id'=> $org_id,
            'from_id'=> $template['xdata']['arem_contact'],
            'recipients'=> array(
                'to'=> array($rec['submitter']['id'])
            )
            // TODO: 'cnv_id' - This reply should actually be connected to the message that was sent by the record submitter, and not as a separate conversaiton !
        );

        $msg_iid = $this-> comm-> create('issreply', $template['body'], $template['title'], $msg_meta);
        if(!$msg_iid)
            return MVC_Core::$debug-> dump("Database error: Unable to record the message: Send not possible", __METHOD__, null, 'error');

        // Create the conversation to connect the message to the record
        $this-> cnv = $this-> factory_Model("Conversation", "cnv");
        $cnv_iid = $this-> cnv-> create('issue', $msg_iid, $rec_id, true, true);
        $this-> record-> counter_Update($rec_id, 1);

        // Use PHPMailer to send the message
        return $this-> comm-> msg_Mailer($msg_iid);
    }

    /**
     * Old message mechanism for creating contact account
     * -- This should be replaced by a modern, database template-driven mechanism, which should be configurable per organization
     * @param $submitter_id Submitter's contact id
     * @param $tpl_data Template data which will be used in the message (contains record data)
     */
    private function deprecated_Issue_Create_Feedback_Mail($submitter_id, $tpl_data)
    {
        // Record creation feedback mail
        $message_id = $this-> comm-> create_System_Message($submitter_id, "issue_create_feedback.html", $tpl_data);
        if(empty($message_id)) {
            return $this-> evan-> add("Unable to create the message containing your account password. Please contact support !");
        }

        $sent = $this-> comm-> msg_Mailer($message_id);
        if(!$sent)
            $this-> evan-> add("There was a problem while attempting to send an e-mail. Please contact support !");
    }

    private static $airlines_table = "data_airlines";

    /**
     * Match airwaybill from db
     * @param MVC_Request $in
     *
     * @throws Exception
     */
    public function action_Match_Awb(MVC_Request $in){
        $response = $this-> record->_match_awb_org($in,(int)MVC_Core::$ss-> user['id']);
        $this-> send_JSON($response);
    }
   public function action_fetchawbdata(MVC_Request $in){
     
        $fileContents = file_get_contents('https://cebulived2dmobileapp.azurewebsites.net/Webservices/SKpublicws.asmx/GetAWBInfo?userName=rraoulpaul&passWord=Cargohub1234!&awbPrefix='.$in-> prefix.'&awbNumber='.$in-> suffix.'');
                   
                  
                   
                    $xml = simplexml_load_string($fileContents);
                    $json = json_encode($xml);
                    $array = json_decode($json,TRUE);
                    $_array = json_decode($array[0],TRUE);
                     $this-> send_JSON($_array);
        
    }
    
  

}