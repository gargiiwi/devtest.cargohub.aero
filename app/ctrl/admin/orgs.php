<?php
/**
 * Organizations management (Admin and HQ)
 *
 * @author Wavetrex (wavetrex@gmail.com)
 * @package CargoITS
 * @version 1.0.dev
 */
class App_Ctrl_Admin_Orgs extends App_Ctrl_Base
{
	public static $_module_name = "System Organizations";

	/**
	 * Makes initializations useful for any action
	 */
	public function __construct()
	{
		parent::__construct();

		// Admin and Supervisor only module, redirect to home if no access
		if(!$this-> user-> is_Unrestricted()) {
			$this-> evan-> add("Move along now, nothing to see here.", 'lock');
			$this-> redirect($this-> CFG['home']);
		}
	}

	/**
	 * Index action for the Organizations list/search
	 *
	 * @see MVC_Ctrl::action_Index()
	 */
	public function action_Index()
	{
		unset(MVC_Core::$ss-> perspective); // clear this value, we are here to select an(other) organization

		$this-> CFG['modules'][] = "admin/_orgs";
		$this-> CFG['jqwidgets'][] = array("scrollbar", "listbox", "dropdownlist", "datetimeinput", "calendar", "expander");
		$this-> CFG['jqwidgets'][] = array("-gridcomplete");
		$this-> CFG['xjs'] = array(
			'jqform'=> true
		);

		// Scan All Orgtypes
		$orgtypes = App_Model_Organization::orgtypes_ScanAll();
		$this-> CFG['admin_orgs'] = array('orgtypes'=> array());
		foreach($orgtypes as $type_id=> $orgtype_data) {
			if(!empty($orgtype_data['disallowcreate']))
				continue;

			$this-> CFG['admin_orgs']['orgtypes'][] = array(
				'id'=> $type_id,
				'name'=> $orgtype_data['name'],
				'shortname'=> $orgtype_data['shortname']
			);
		}

		$this-> prepare_Header(self::$_module_name);

		$this-> module = array(
			'icon'=> "company-building-icon.png",
			'icon_alt'=> "(org)",
			'title'=> self::$_module_name,
			'description'=> "Lists organizations and shows statistical information"
		);

		// which body template to use, and run the view
		$this-> site_section = "admin". DS. "organizations.html";
		$this-> view("private.html");
	}

	/**
	 * Reads information about an organization and uses a template to display it as a quick preview.
	 * @param MVC_Request $in
	 */
	public function action_Quick_Org_Info(MVC_Request $in)
	{
		if(!$in-> id) {
			echo "Organization ID not present in the request";
			return;
		}

		$org = $this-> org-> read($in-> id, array('orgtype', 'manager', 'departments'));
// 		dd($org);

		$this-> init_Template_System();
		echo $this->_drytpl-> render_File("admin". DS. "quick_org_info.html", $org);
	}

	/**
	 * Get the list of organizations (for use in jqx controls)
	 */
	public function action_Get_List(MVC_Request $in)
	{
		if($in-> mode == 'quick') {
			// TODO: More flexibility, more filters ( for example, choosing organization type? )

			$r = $this-> org-> get_List(array(), null, null, $in-> sort?:"`org`.`name` ASC", 0);
			if(empty($r))
				return $this-> send_JSON(array()); // empty result ?

			return $this-> send_JSON($r);
		}

		// Full mode

		// 1. Server-side Filters
		list($ss_filters, $filter_callback) = self::helper_Grid_Filters($in);

		// 2. Server-side Sort
		if($in-> sortdatafield && $in-> sortorder) {
			// Determine the database field used for sorting
			if($in-> sortdatafield == 'manager_name')
				$ss_sort = "`usr`.name ";
			else
				$ss_sort = "`org`.{$in-> sortdatafield} ";

			$ss_sort.= $in-> sortorder;
		} else {
			$ss_sort = "`org`.update_dt DESC"; // default sorting is on update time, descending (show the most recently organization that did something)
		}

		$orgs = $this-> org-> get_List(array("organization", "orgtype", "manager"),
			function(QueryFactory $q) use ($ss_filters, $filter_callback) {
				foreach($ss_filters as $fkey=> $filter_set) {
					// Which table and database field is used for the filter column ?
					if($fkey == 'manager_name')
						$dbfield = "`usr`.name ";
					else
						$dbfield = "`org`.{$fkey} ";
					$filter_callback($q, $filter_set, $dbfield);
				}
			}, null, $ss_sort, $in-> pagesize, $in-> pagenum * $in-> pagesize, true); // true = CALC FOUND!

		if(!$orgs || count($orgs) == 0)
			$orgs = array();

		// Send the list of users to the grid (or nothing if nothing found)
		$this-> send_JSON(array(
			"found_rows"=> $this-> org-> found_rows,
			"data"=> $orgs
		));
	}

	/**
	 * Creates an organization by setting up all the basic required data.
	 * After this, users can be added to the organization, or it can be further configurade
	 */
	public function action_Add_Organization()
	{
		$post = new MVC_Request('post');
		$org_data = $post-> org;

		// Data error check ( unique organization name, primary domain not already used, organization type exists )
		$test_name = $this-> org-> get_List(array(), function(QueryFactory $q) use($org_data) {
			$q-> where("`org`.`name` = ?", $org_data['name']);
		});
		if($test_name !== false && count($test_name)>0)
			return $this-> send_Fail_JSON("The name {$org_data['name']} is already used in the system! Organization name must be unique.");

		// If primary domain was typed in, check if it doesn't exist already, and put it into the "features" field ...
		if(!empty($post-> domain['name'])) {
			$primary_domain = $post-> domain['name'];

			$test_domain = $this-> org-> domain_Read($primary_domain, array('organization'));
			if($test_domain!=false)
				return $this-> send_Fail_JSON("The domain <b>{$primary_domain}</b> already exists and belongs to {$test_domain['organization']['name']}");

			$org_data['features']['domains'] = 1;
			$org_data['features']['primary_domain'] = $primary_domain;
		}

		// copy the default features from organization types
		$orgtype = App_Model_Organization::orgtype_Read($org_data['type_id']);
		if(!$orgtype)
			return $this-> send_Fail_JSON("Organization type {$org_data['type_id']} not found. Possible interface error");

		// Copy orgtype defaults
		if(!empty($orgtype['default_features']))
			$org_data['features'] = $orgtype['default_features'];
		if(!empty($orgtype['default_privileges']))
			$org_data['privileges'] = $orgtype['default_privileges'];
		if(!empty($orgtype['default_access_ids']))
			$org_data['access_ids'] = $orgtype['default_access_ids'];

		// Initial manager is the admin itself, this can be changed later when editing the organization
		$org_data['manager_id'] = MVC_Core::$ss-> user['id'];

		$iid = $this-> org-> create($org_data);

		if(!$iid || is_array($iid))
			return $this-> send_Fail_JSON("Unable to create organization, possible database error.");

		// ... and also add the domain record to the domains table, connecting it to the organization just created
		if(isset($primary_domain)) // primary domain ( if specified )
			$this-> org-> domain_Create($iid, array(
				'domain'=> $post-> domain['name'],
				'html_settings'=> array(
					'css_theme'=> $post-> domain['css_theme'],
					'title'=> $org_data['name'] // Initial title is the name of the organization ( it can be changed later )
				)
			));

		$this-> evan-> add("The organization {$org_data['name']} has been created !", "add", array(
			'permanent'=> true,
			'timeout'=> time()+ 30
		));

		$this-> send_JSON(array('success'=> true, 'org_id'=> $iid));
	}

	/**
	 * Deletes an existing organization and removes all the data which is associated with it, from all the tables
	 * -- VERY destructive, and irreversible, use with extreme caution !
	 */
	public function action_Delete_Organization(MVC_Request $post)
	{
// 		return $this-> send_Fail_JSON("This function needs updating...");

		// read the organization to check some parameters
		$org = $this-> org-> read($post-> org_id, array('organization'));

		if(!$org) {
			// not found
			$this-> send_Fail_JSON("Organization with id {$post-> org_id} was not found, it might have already been deleted.");
			return;
		}

		// Admin org cannot be removed for safety reason ( it would disable access to the system )
		if($org['type_id'] == 'admin')
			return $this-> send_Fail_JSON("For safety reasons, it's not possible to remove the ADMIN organization. Sorry !");

		// Organizations can be locked by setting a certain flag in their "features" so they can't be accidentally deleted.
		if(isset($org['features']['delete_lock']))
			return $this-> send_Fail_JSON("<b>Safety Lock</b> is active for {$org['name']}. Contact Kal-El to resolve this ;)");

		$r = $this-> org-> delete($org['id']);

		if(!$r)
			$this-> send_Fail_JSON("An error occured while attempting to delete the organization {$org['name']}");

		$this-> evan-> add("The organization {$org['name']} has been deleted... <b>&quot;She's dead, Jim!&quot;</b>", "remove", array(
			'permanent'=> true,
			'timeout'=> time()+ 30
		));

		$this-> send_JSON(array('success'=> true));
	}
        
        
    //        IWI create api for getting record details start
    public function action_getSecretKey(MVC_Request $in) {
        $r = $this->org->getSecretKey($in->org_id);
        if (empty($r)) {
            return $this->send_JSON(array());
        } else {
            $this->send_JSON(array(
                "success" => true,
                "secretKey" => $r['secret_key'],
                "status" => $r['status'],
            ));
        }
    }
    public function action_createSecretKey(MVC_Request $in) {
        $r = $this->org->createSecretKey($in->org_id);
        if (empty($r)) {
            return $this->send_JSON(array());
        } else {
            $get = $this->org->getSecretKey($in->org_id);
            $this->send_JSON(array(
                "success" => true,
                "secretKey" => $get['secret_key'],
                "status" => $get['status'],
            ));
        }
    }
    public function action_regenerateSecretKey(MVC_Request $in) {
        $r = $this->org->regenerateSecretKey($in->org_id);
        if (empty($r)) {
            return $this->send_JSON(array("success" => true));
        } else {
            $get = $this->org->getSecretKey($in->org_id);
            $this->send_JSON(array(
                "success" => true,
                "secretKey" => $get['secret_key'],
                "status" => $get['status'],
            ));
        }
    }
    public function action_toggleSecretKeyStatus(MVC_Request $in) {
        $updateData=array(
            'status'=>$in->curr_status=='1'?'0':'1',
        );
        $r = $this->org->toggleSecretKeyStatus($in->org_id,$updateData);
        if (empty($r)) {
            return $this->send_JSON(array("success" => true));
        } else {
            $get = $this->org->getSecretKey($in->org_id);
            $this->send_JSON(array(
                "success" => true,
                "secretKey" => $get['secret_key'],
                "status" => $get['status'],
            ));
        }
    }

//        IWI create api for getting record details end

}