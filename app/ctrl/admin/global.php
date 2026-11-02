<?php

/**

 * Global Administrative functions

 * ===== NON FUNCTIONAL =====

 *

 *

 * @author Wavetrex (wavetrex@gmail.com)

 * @package CargoITS

 * @version 1.0.dev

 */

class App_Ctrl_Admin_Global extends App_Ctrl_Base

{

	/**

	 * Index action for the Global Settings page

	 *

	 * @see MVC_Ctrl::action_Index()

	 */

	public function action_Index()

	{

//  		$this-> evan-> add("Shit has happened", "error", array(

//  			'permanent'=> true

//  			'timeout'=> time()+ 300

//  		));

// 		dd(MVC_Core::$ss-> evan);



//   		$msg = $this-> evan-> last();

//  		unset($msg-> opts['timeout']);



// 		$this-> evan[2] = "I'm changing the message";

// 		dd($msg);

// 		$msg-> last_Display();



		// prepare output

		$this-> prepare_Header("TESTING AREA");



		// which body template to use, and run the view

		$this-> site_section = "private". DS. "admin_global.html";

		$this-> view("private.html");



		// Nuclear Testing Area, Do Not Enter!



		echo "<hr /><p>TESTING</p>";



// 		$this-> evan-> cleanup();

		dd(array(1, 2), "Hello World !");



		$config = array(

			'database'=> array(

				'table'=> "files",

			),

			'link'=> array(

				'with'=> "issue",

				'id'=> 4

			),

			'analyzers'=> array(

				'image'=> array(

					'exif'=> true

				)

			),

			'paths'=> array(

				'default'=> array(

					'dir'=> ROOT. DS. 'warehouse_mount',

					'mode'=> "contentHash"

				),

				'image'=> array(

					'dir'=> ROOT. DS. 'warehouse_mount'. DS. 'images',

					'mode'=> "contentHash"

				)

			)

		);



		if(!empty($_FILES)) {

			$wh = new MVC_Warehouse(MVC_Core::$db, $config);

			$uploads = $wh-> process_Uploads(true);

			foreach($uploads as $up)

				dd($up);

		}

	}



	public function action_Getfile()

	{

		$in = new MVC_Request();



		$config = array(

			'database'=> array(

				'table'=> "files",

			),

			'paths'=> array(

				'default'=> array(

					'dir'=> ROOT. DS. 'warehouse_mount',

					'mode'=> "contentHash"

				),

				'image'=> array(

					'dir'=> ROOT. DS. 'warehouse_mount'. DS. 'images',

					'mode'=> "contentHash"

				)

			)

		);



		$wh = new MVC_Warehouse(MVC_Core::$db, $config);



		$fileinfo = null;

		$wh-> read_File_Content($in-> id, $fileinfo, true, true);

	}





	/**

	 * Reads and returns list of organizations related to a certain element ( issue type, partners of an organization, organizations to which a jumper user can jump, etc. )

	 * -- This is used by certain dropdowns to select an organization.

	 */

	public function action_Get_Related_Orgs(MVC_Request $rq)

	{

		if(!isset($rq-> mode))

			return $this-> send_JSON(array('id'=> 0, 'name'=> "Error: Mode not found"));



		$orgs = array();

		switch($rq-> mode) {

			// Return all organizations in the system, no filtering.

			case 'all':

				// TODO: Paging ?

				$orgs = $this-> org-> get_List(array(), null, null, $rq-> sort?:"`org`.name ASC", 0);

				break;



			// Return the list of organizations that have access to a certain issue type (and is enabled for them) - Table i2o.

			case 'ist':
				
				$orgs = $this-> org-> get_List(array(

					'joins'=> array(

						array(

							'tab'=> MVC_Core::$db-> table("i2o"),

							'fields'=> false,

							'on'=> "`i2o`.org_id = `org`.id AND `i2o`.istkey = '{$rq-> key}'"

						)

					)

				), function(QueryFactory &$q) {

					$q-> where("`i2o`.enabled = 1");

				}, null, $rq-> sort?:"`org`.name ASC", 0);

				break;



			default:

				return $this-> send_JSON(array('id'=> 0, 'name'=> "Error: Invalid access mode"));

		}



		// No orgs found for this record type (probably internal/not used)

		if(empty($orgs))

			return $this-> send_JSON(array('id'=> 0, 'name'=> "No organizations found"));



		if($rq-> unselect_entry == 'true')

			array_unshift($orgs, array(

				'id'=> null,

				'type_id'=> '.',

				'name'=> "(unselect)"

			));



		$this-> send_JSON($orgs);

	}



}