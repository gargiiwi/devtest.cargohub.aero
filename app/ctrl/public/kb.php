<?php
/**
 * Help, information, other semi-static pages (KnowledgeBase) - Accesible anywhere (but with certain pages possible restricted for public)
 *
 * @author Wavetrex (wavetrex@gmail.com)
 * @package CargoITS
 * @version 2018.05
 */
class App_Ctrl_Public_Kb extends App_Ctrl_Base
{
	/**
	 * Index action for the Issues display and management page
	 *
	 * @see MVC_Ctrl::action_Index()
	 */
	public function action_Index(MVC_Request $rq)
	{
		// setup frontend config
//		$this-> CFG['modules'][] = "kb_main";
//		array_push($this-> CFG['jqwidgets'], "input");
//		$this-> CFG['kb_main'] = array();

		if(MVC_Core::$ss-> original_request_uri)
			$this-> original_request_url = MVC_Core::HTTPS(true)."://".$_SERVER['HTTP_HOST'].MVC_Core::$ss-> original_request_uri; // TODO: A way to determine if it's http or https
		unset(MVC_Core::$ss-> original_request_uri);

		$this-> prepare_Header("Help and Support", array("userbar", "evan"));
		if(!isset($rq-> page))
			$rq-> page = "index";

		// which body template to use, and run the view
		$this-> site_section = "static". DS. "{$rq-> page}.html";
		$this-> view("public.html");
	}

}