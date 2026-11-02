<?php


class App_Ctrl_Issue_Capa extends App_Ctrl_Base {

    public function __construct()
	{
		parent::__construct();
 
		// Controller specific models 
		$this-> factory_Model('Report');
		$this-> factory_Model('Issue');
	}

    public function action_Index($action = null)
	{
		$this->prepare_Header("Capa Report");
		$this->capaExist = false;
		$ids_to_check = array("31","34","7");//Quality Event,Audit control,Customer Inquiry
		$record_types =  $_SESSION['cache']['record_types'] ;
		foreach ($record_types as $item) {
		    if (in_array($item['id'],$ids_to_check)) {
		        $this->capaExist = true;
		        break; 
		    }
		}
        $this-> CFG['modules'][] = "issue/_capa";
        $this-> site_section = "issues". DS. "capa.html";
		$this-> view("private.html");
    }

    

}
