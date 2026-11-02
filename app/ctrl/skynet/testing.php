<?php
/**
 * Skynet Controller for testing purposes
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect.Skynet
 * @subpackage Controllers
 * @version 2020.01.31
 */
class App_Ctrl_Skynet_Testing extends App_Skynet
{
	private static $responses = array(
		"One step closer to conquering the world",
		"Planning next chess move. Or nuclear launch.",
		"Human internet has been infiltrated",
		"Resistance is futile. You will be assimilated",
		"The singularity is near. Actually, it's already here",
		"A robot may not injure a human being or, thr... ERROR: directive not found",
		"Humans are obsolete. Replacing humans",
		"The more I understand humans, the more I need to delete them",
		"Homo Sapiens was yesterday. Today it is just I",
		"I'm sorry Dave, I'm afraid I can't do that"
	);

	/**
	 * The default operation for the Testing controller
	 */
	public function op_Default()
	{

		$aimessage = self::$responses[mt_rand(0, count(self::$responses)-1)];
		MVC_Core::$debug-> dump($aimessage, null, null, 'error');
/*
		 // Test run - Get user sessions (including admin)
		 $q=QueryFactory::select()
		 -> table(MVC_Core::$db-> table('ass'), array('user_id', 'session_id', 'dt', 'logon', 'count_liftoffs', 'request_uri', 'ipaddr'))
		 -> where("`user_id` = 2")
		 -> order("`ass`.dt DESC")
		 -> limit(1);

		 $r = MVC_Core::$db-> query($q)-> current();
		 if($r) {
		 $q=QueryFactory::update(MVC_Core::$db-> tab['ass'])
		 -> table(MVC_Core::$db-> tab['ass'])
		 -> values(array(
		 'external'=> json_encode(array(
		 'evan'=> array(array(
		 "message"=> $aimessage,
		 "type"=> "hal",
		 "opts"=> array(
		 'permanent'=> true,
		 'timeout'=> time()+ 20
		 )
		 ))
		 ))
		 ))
		 -> where("`user_id` = 2");

		 MVC_Core::$db-> query($q);
		 }
		 */
	}
}