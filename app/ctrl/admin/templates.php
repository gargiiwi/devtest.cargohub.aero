<?php
/**
 * Template Editor Controller (Admin only module)
 *
 * @author Highstrike (highstrike@gmail.com)
 * @author Wavetrex (wavetrex@gmail.com)
 * @package Cargo ITS
 * @version 1.0.dev
 */
class App_Ctrl_Admin_Templates extends App_Ctrl_Base
{
	/**
	 * Models used by this controller
	 *
	 * @var array
	 */
	public $m = array();

	/**
	 * Makes initializations useful for any action
	 */
	public function __construct()
	{
		parent::__construct();

		// Admin-only module, redirect to home if no access
		if(!isset(MVC_Core::$ss-> user['is_admin'])) {
			$this-> evan-> add("Move along now, nothing to see here.", 'lock');
			$this-> redirect($this-> CFG['home']);
		}

		// Controller specific models
		$this-> factory_Model('Template', 'tpl', array('root_dir'=> ROOT. DS. 'app'. DS. 'view'));
	}

	/**
	 * Index action for the Template Editor controller
	 * @see MVC_Ctrl::action_Index()
	 */
	public function action_Index()
	{
		$this-> CFG['modules'][] = "templates_main";
		$this-> CFG['jqwidgets'][] = array("scrollbar", "panel", "tree", "expander", "splitter");
		$this-> CFG['xjs'] = array(
			'codemirror'=> "htmlmixed",
			'codemirror_theme'=> "eclipse",
			'jqform'=> "true"
		);

		// prepare output
		$this-> prepare_Header("System Templates");

		// output tree
		$this-> templates = $this-> tpl-> get_Tree();

		// which body template to use, and run the view
		$this-> site_section = "admin". DS. "templates.html";
		$this-> view("private.html");
	}

	/**
	 * Load Template (ajax)
	 */
	public function action_Load()
	{
		// load the template contents
		$post = new MVC_Request('post');

		$path = base64_decode($post-> path);
		$contents = $this-> tpl-> get_Template($path);

		// calculate response based on data
		if($contents)
			$this-> send_JSON(array(
				'success'=> true,
				'path'=> $path,
				'contents'=> $contents
			));
		else
			$this-> send_Fail_JSON("Error: Unable to retrieve the template contents.");
	}

	/**
	 * Save Template (ajax)
	 */
	public function action_Save()
	{
 		// save the template contents
		$post = new MVC_Request('post');
		$data = $this-> tpl-> save_Template(base64_decode($post-> path), $post-> contents);

		// calculate response based on data
		if($data)
			$this-> send_JSON(array('success' => true));
		else
			$this-> send_Fail_JSON("Error: Unable to save the template file.");
 	}

    public function action_Create()
    {
		$post = new MVC_Request('post');
        $data = $this-> tpl-> create_Template($post-> name.".html");

		if($data)
			$this-> send_JSON(array('success' => true));
		else
			$this-> send_Fail_JSON("Error: Unable to create the template file.");
    }

	public function action_Delete()
	{
		$post = new MVC_Request('post');

		$path = base64_decode($post-> path);

		//Check if the file is in static folder
		if(strrpos($path,"app/view/static")){
			$data = $this-> tpl-> delete_Template($path);
			if($data)
				$this-> send_JSON(array('success' => true));
			else
				$this->send_Fail_JSON ("Error: Unable to delete the template file.");
		}else
			$this->send_Fail_JSON ("Error: Unable to delete the template file. \nOnly templates from static folder can be deleted!");

	}

}