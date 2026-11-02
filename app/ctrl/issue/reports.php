<?php
/**
 * Issue data Reports generation
 * (and management controller)
 *
 * @author Wavetrex (wavetrex@gmail.com)
 * @package Cargo ITS
 * @version 1.0.dev
 * For accessing the data from any external app or for creating your own external API use mysql-services
 */
class App_Ctrl_Issue_Reports extends App_Ctrl_Base
{
	/**
	 * Makes initializations useful for any action
	 */
	public function __construct()
	{
		parent::__construct();

		// Controller specific models
		$this-> factory_Model('Report');
		$this-> factory_Model('Issue');
	}

	/**
	 * The filter for the list of groups the current user has access to
	 * @param QueryFactory $q
	 * @param array $params
	 */
	public function filter_Group_Get_List(QueryFactory &$q)
	{
		$usr = MVC_Core::$ss-> user;
		if($usr['status']!='admin') {
			// for non-admin users, limit the groups to what the user can see
			$q-> where('`grp`.`id` IN(?)', array_keys($usr['acls']['groups']));
		}
	}

	/**
	 * Index action for the tasks display and management page
	 * @see MVC_Ctrl::action_Index()
	 */
	public function action_Index($action = null)
	{
		$this-> CFG['modules'][] = "reports_main";

		$this-> CFG['jqwidgets'][] = array("scrollbar", "splitter", "dropdownlist", "listbox", "dragdrop", "calendar", "datetimeinput", "checkbox", "input", "numberinput", "slider");
		$this-> CFG['xjs'] = array(
			'global' => true,
			'dateformat' => true,
			'jqform'=> true
		);

		// Get the list of issue types (necessary for the "Add report type" function)
		$ist_list = $this-> issue-> ist_Get_List();
		$all = array();
		foreach($ist_list as $ist)
			$all[] = array(
				'id'=> (int)$ist['id'],
				'name'=> $ist['name']
			);
		$this-> CFG['reports_main'] = array(
			'ist_list'=> $all
		);

		// prepare output
		$this-> prepare_Header("Reports");

		// Get the list of airlines available for the user operating (it's needed for the report filter)
		$this-> factory_Model("Group", null, 'airline');
		$r = $this-> group-> get_List(array(), array($this, "filter_Group_Get_List"), null, "`name` ASC");
		if($r && count($r)>0) {
			$all = array(array(
				'id'=> 0,
				'name'=> ""
			));
			foreach($r as $row) {
				$all[] = array(
					'id'=> (int)$row['id'],
					'name'=> $row['name']
				);
			}
			$this-> CFG['reports_main']['groups'] = $all;
		}

		// which body template to use, and run the view
		$this-> site_section = "private". DS. "reports_index.html";
		$this-> view("private.html");
	}

	/**
	 * Get the list of defined report types
	 */
	public function action_Get_List()
	{
		$in = new MVC_Request('get');

		// only report types of the user's own organization (Admin can read all)
		$usr = MVC_Core::$ss-> user;
		$reports = $this-> report-> get_List(
			$usr['status']!='admin'? $usr['org_id']: null,
			null,
			$usr['status']=='admin'? array('rtype', 'user', 'organization'): array('rtype', 'user') // Admin can also see which organization has this report type
		);

 		if($reports && count($reports)>0) {
 			$all = array();
 			foreach($reports as $report) {
 				// prepare the data for the frontend
 				$rtype = array(
 					'id'=> (int)$report['id'],
 					'ist'=> (int)$report['ist_id'],
 					'name'=> $report['name'],
 					'columns'=> $report['columns'],
 					'creator'=> $report['creator']
 				);
 				$rtype['creator']['id'] = (int)$rtype['creator']['id'];
 				if($usr['status']=='admin') {
 					$rtype['org'] = $report['organization'];
 					$rtype['org']['id'] = (int)$rtype['org']['id'];
 				}

 				$all[] = $rtype;
 			}

 			$this-> send_JSON($all);
 		} else
 			$this-> send_JSON(array());
	}

	/**
	 * Reads the issue type information (necessary to show the proper names for report columns)
	 */
	public function action_Get_IST()
	{
		$in = new MVC_Request('get');

		$ist = $this-> issue-> ist_Read($in-> ist);
		if($ist) {
			$xfields = array();
			foreach($ist['xfield_def'] as $k=>$xf)
				$xfields[$k] = $xf['n'];

			$group_plugin = MVC_Core::factory_Plugin("group", $ist['settings']['group_type']); // Airline? Other?

			$this-> send_JSON(array(
				'success'=> true,
				'ist'=> array(
					'name'=> $ist['name'],
					'xfields'=> $xfields,
					'group_name'=> $group_plugin-> name,
					'statuses'=> $ist['statuses_ex']
				)
			));
		} else
			$this-> send_Fail_JSON("Issue type {$in-> ist} doesn't exist.");
	}

	/**
	 * Creates or updates a report type (name + columns)
	 */
	public function action_Save()
	{
		$post = new MVC_Request('post');

		if(!$post-> report)
			$this-> send_Fail_JSON("Invalid data posted.");

		$report_data = $post-> report;
		$report_data['columns'] = explode(',', $report_data['columns']);
		if($post-> id>0) {
			// it's an update
			$r = $this-> report-> update($post-> id, $report_data);
			if($r) {
				$this-> evan-> add("The report <b>{$report_data['name']}</b> has been updated.", "ok", array(
					'permanent'=> true,
					'timeout'=> time()+ 30
				));

				$this-> send_JSON(array('success'=> true));
			} else
				$this-> send_Fail_JSON("Unable to update the report, database error.");
		} else {
			// it's creation
			$report_data['created_by'] = MVC_Core::$ss-> user['id'];
			$report_data['org_id'] = MVC_Core::$ss-> user['org_id'];
			$r = $this-> report-> create($report_data);
			if($r>0) {
				$this-> evan-> add("The report <b>{$report_data['name']}</b> has been created.", "add", array(
					'permanent'=> true,
					'timeout'=> time()+ 30
				));
				$this-> send_JSON(array('success'=> true));
			} else
				$this-> send_Fail_JSON("Unable to create report, database error.");
		}
	}

	/**
	 * Deletes an existing report type
	 */
	public function action_Delete()
	{
		$post = new MVC_Request('post');

		$r = $this-> report-> delete($post-> id);
		if($r) {
			$this-> evan-> add("A report type has been removed.", "remove", array(
				'permanent'=> true,
				'timeout'=> time()+ 30
			));
			$this-> send_JSON(array('success'=> true));
		} else
			$this-> send_Fail_JSON("Unable to delete the report, database error.");
	}

	/**
	 * Filter for reading issues for reports - date range, one/all group(s), one/all status(es)
	 * @param QueryFactory $q
	 * @param MVC_Request $in
	 */
	public function filter_Generate(QueryFactory &$q, MVC_Request $in)
	{
		$usr = MVC_Core::$ss-> user;

//		if($date_start < $date_start) // filter by date
		$q-> where('`iss`.`ctime` BETWEEN ? AND ?', $in-> date_start, $in-> date_end + 86400);

		if($in-> group != '0') {
			$q-> where('`iss`.`group_id` = ?', $in-> group);
		} else {
			if($usr['status']!='admin') {
				// for non-admin users, limit the groups to what the user can see
				$q-> where('`iss`.`group_id` IN(?)', array_keys($usr['acls']['groups']));
			}
		}
		if($in-> status != '0')
			$q-> where('`iss`.`status_key` = ?', $in-> status);
	}

	/**
	 * Generate and display a report, depending on input parameters (GET)
	 */
	public function action_Generate()
	{
		$in = new MVC_Request();
		$in-> add_Filter('date_start', "unixtime");
		$in-> add_Filter('date_end', "unixtime");

		if(!$in-> id) {
			// error, no report id specified
			$this-> evan-> add("Error, no report ID specified (database error?)", "error");
			$this-> redirect("?");
		}

		// read the report type
		$report = $this-> report-> read($in-> id);

		$ist = $this-> issue-> ist_Read($report['ist_id']);

		$report['ist_name'] = $ist['name'];

		// calculate the column set for this report type (for both the get_List method, and the table header)

		$bic = array( // built-in-columns
			'_track'=> "Tracking ID",
			'_id'=> "ID",
			'_ctime'=> "Creation time",
			'_utime'=> "Update time",
			'_priority'=> "Priority",
			'_status'=> "Status",
			'_assigned'=> "Assigned to",
			'_post_ip'=> "IP Address",
			'_count_msgs'=> "Message count",
			'_count_files'=> "Attachments"
		);

		$selected_cols = json_decode($report['columns']);
		$wtr = array('issue'); $fields = array(); $xfields = array(); $fieldn = array();
 		$fieldn[] = array(
 			'key'=> '_idx',
 			'name'=> '#'
 		);
		foreach($selected_cols as $col) {
			if(substr($col, 0, 1)=='_') // we have default columns
				switch(substr($col, 1)) {
					case 'id':
					case 'track':
					case 'ctime':
					case 'utime':
					case 'post_ip':
					case 'count_msgs':
					case 'count_files':
						$fields[] = substr($col, 1);
						$fieldn[] = array(
							'key'=> $col,
							'name'=> $bic[$col]
						);
						break;
					case 'group':
						if(!in_array('group', $wtr)) {
							$wtr[] = 'group';
							$fields[] = 'group_id';
						}
						// load the "group" plugin for the issue type to get some information
						$group_plugin = MVC_Core::factory_Plugin("group", $ist['settings']['group_type']); // Airline? Other?
						$fieldn[] = array(
							'key'=> $col,
							'name'=> $group_plugin-> name
						);
						break;
					case 'priority':
						$fields[] = 'priority_key';
						$fieldn[] = array(
							'key'=> $col,
							'name'=> $bic[$col]
						);
						break;
					case 'status':
						$fields[] = 'status_key';
						$fieldn[] = array(
							'key'=> $col,
							'name'=> $bic[$col]
						);
						break;
					case 'assigned':
						if(!in_array('assigned', $wtr)) {
							$wtr[] = 'assigned';
							$fields[] = 'assigned_to';
						}
						$fieldn[] = array(
							'key'=> $col,
							'name'=> $bic[$col]
						);
						break;
			}
			else {
				$xfields[] = $col;
				$f = array(
					'key'=> $col,
					'name'=> $ist['xfield_def'][$col]['n']
				);
				if(isset($ist['xfield_def'][$col]['o']))
					$f['opt'] = $ist['xfield_def'][$col]['o'];
				$fieldn[] = $f;
			}
		}

		if(!empty($fields))
			$wtr['flist'] = $fields;
		else
			$wtr['flist'] = array();
		if(!empty($xfields)) {
			$wtr[] = 'xfields';
			$wtr['xflist'] = $xfields;
		}

		// read issues according to the report type and the input parameters
		if(isset($wtr['flist']['group_id']))
			$order = '`grp`.name ASC';
		else
			$order = null;

		$issues = $this-> issue-> get_List($report['ist_id'], $wtr, array($this, 'filter_Generate'), $in, $order, 1000);

		if($issues && count($issues)> 0) {
			// do some processing so the report template can display it in a simple way
			$tbody = array();
			$row_counter = 0;
			foreach($issues as $issue) {
				$row_counter++;
				if(isset($issue['ctime']))
					$issue['ctime'] = date("Y-m-d H:i:s", $issue['ctime']);
				if(isset($issue['xfields']))
					foreach($issue['xfields'] as &$xfield) {
					if(is_array($xfield) && isset($xfield['value']))
						$xfield = $xfield['value'];
					elseif(is_array($xfield) && isset($xfield[0]) && isset($xfield[0]['value'])) {
						$values = array();
						foreach($xfield as $set_entry)
							$values[] = $set_entry['value'];
						$xfield = implode(", ", $values);
					} elseif(is_array($xfield) && isset($xfield['p'])) {
						// AWB stores it's values in an array having the keys "p" and "s" (prefix, serial)
						$xfield = $xfield['p'].'-'.$xfield['s'];
					}
				}

				// build the data array with columns in the same order as $fieldn - To display rows as a table properly
				$rowdata = array();
				foreach($fieldn as $fi) {
					if(substr($fi['key'], 0, 1)=='_') { // built in columns
						switch(substr($fi['key'], 1)) {
							case 'idx':
								$chtml = '<b>'.$row_counter.'.</b>'; break;
							case 'group':
								$chtml = $issue['group']['name']; break;
							case 'count_msgs':
								$chtml = $issue['count']['msgs']; break;
							case 'count_files':
								$chtml = $issue['count']['files']; break;
							case 'assigned':
								$chtml = $issue['assigned']['name']; break;
							default:
								$chtml = $issue[substr($fi['key'], 1)];
						}
					} else
						if(isset($issue['xfields'][$fi['key']]))
							$chtml = $issue['xfields'][$fi['key']];
						else
							$chtml = ""; // no data => empty string

					// Extra options, like column align, content formatting, etc.
					$cell_align = null;
					if(isset($fi['opt']['colr'])) {
						switch($fi['opt']['colr']) {
							case 1: // currency - TODO: Configurable
								if(strlen($chtml) > 0) {
									$chtml = number_format($chtml, 2)." €";
									$cell_align = " style='text-align: right;'";
								}
								break;
							case 2: // yes/no
								$chtml = (bool)$chtml? "Yes": "No";
								break;
							case 3: // center align
								$cell_align = " style='text-align: center;'";
								break;
							case 4: // right align
								$cell_align = " style='text-align: right;'";
								break;
							case 5: // relative date

								break;
							case 6: // only date date
								if(strlen($chtml) > 0) {
									$chtml = date("Y-m-d", strtotime($chtml));
								}
								break;
						}
					}

					$rowdata[$fi['key']] = array(
						'html'=> $chtml,
						'align'=> $cell_align
					);


				}
				$tbody[] = $rowdata;
			}
		}
		dd($fieldn);

		$this-> thead = $fieldn;
		$this-> tbody = $tbody;

		$this-> input = $in;
		$this-> report = $report;

		//generate html file
		if($in->type == 'html')
		{
			$this-> view("private". DS. "content_report.html");
		}
		//generate pdf file
		else if($in->type == 'pdf')
		{
			// Not supported yet
/*			include_once "lib". DS."tcpdf". DS."tcpdf.php";
			//table header
			$th_all = array();
			foreach($fieldn as $f){ $th_all[] = $f['name']; }

			$html = "<h2>".$report['name']."</h2>
						<p><b>".date("Y-m-d",$in->date_start)." </b> - <b>".date("Y-m-d",$in->date_end)."</b></p>";

			$html .= '<table border="1">
					<tr>';
					foreach($th_all as $t){
			$html .= '<th><b>'.$t.'</b></th>';
					}
			$html .= '</tr>';
					foreach($tbody as $f)
					{
			$html .= '<tr>';
					foreach($f as $v){
			$html .= '<td>'.$v['html'].'</td>';
					}
			$html .= '</tr>';
					}
			$html .= '</table>';

			$pdf = new TCPDF();
			$pdf->setPageOrientation('l');
			$pdf->SetAutoPageBreak(TRUE, 0);
			$pdf->setHeaderFont(Array('helvetica', '', 8));
			$pdf->setFooterFont(Array('helvetica', '', 6));
			$pdf->SetMargins(5, 5, 5);

			//Set Page Size Format like A1,A0,etc..
			$pdf->AddPage('L','B1');

			$pdf_file = $report['name'].'.pdf';
			$pdf->writeHTML($html, true, false, true, false, '');
			$pdf->Output($pdf_file, 'D');*/
		}
	}

}

// $signs = array('EUR'=>"€", 'USD'=>"$", 'GBP'=>"£", 'JPY'=>"¥");