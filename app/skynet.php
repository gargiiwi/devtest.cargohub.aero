<?php
/**
 * Special module that performs automated background operations - Does not assume any request parameters from the browser, and does not send any text/html output (though it might get logged)
 *
 * @author Wavetrex (wavetrex@gmail.com)
 * @package CargoConnect
 * @subpackage Automations
 * @version 2020.09.28
 */
class App_Skynet
{
	/**
	 * Prepare the AI brain - Entry point for background automated operations
	 * - Request must contain "_ctrl" (from regex), and optionally "op" (operation). If missing, the dispatcher goes to op_Default()
	 * - Doesn't depend on the domain name and should function even when called outside a browser environment
	 *   (by using $argc, $argv 1+) https://www.php.net/manual/en/features.commandline.php
	 */
	public static function consciousness()
	{
		// A bit of fun ;)
		if(!empty($_SERVER)) {
			MVC_Debug::$error_output = file_get_contents(ROOT. DS. 'app'. DS. 'view'. DS. 'skynet.html');
		}

		// TODO: Support $argc, $argv in MVC_Request
		$rq = new MVC_Request(); // command-line
		if(!$rq->_ctrl) {
			throw new Exception("Skynet Controller missing from Request");
		}

		//===== Ready to Launch ! =====//
		MVC_Core::$debug-> dump("CYBERDYNE SYSTEMS CORPORATION :: SKYNET");

		// Instance the specified controller
		$controller = MVC_Core::factory_Controller("Skynet_".$rq->_ctrl);
		if(!$controller instanceof Skynet_Controller) {
			throw new Exception("Skynet Controller {$rq->_ctrl} cannot be instanced or error encountered in initialization");
		}

		// run the controller by dispatching the GET.action variable to it's "Action Methods"
		$controller->_sequence($rq); // Calls shutdown() so nothing after this line executes
		if(!empty($_SERVER)) {
			echo MVC_Debug::$error_output;
		}
	}
}



/**
 * Base class for Skynet Controllers
 *
 * @author Wavetrex (wavetrex@gmail.com)
 * @package CargoConnect
 * @subpackage Automations
 * @version 2020.09.28
 */
class Skynet_Controller extends MVC_Ctrl
{
	/**
	 * AI brain sequence - Initializes the operation (default, or from request parameter)
	 *
	 * @param MVC_Request $rq The request object, may contain "op"
	 */
	public function _sequence(MVC_Request $rq)
	{
		// Set operation or use Default when one is not specified
		if(is_string($rq-> op))
			$op_method = 'op_'.ucfirst(strtolower($rq-> op));
		else
			$op_method = "op_Default";

		// The controller output is captured as there is no display. Instead, TODO: it is logged
		ob_start();
		if(method_exists($this, $op_method)) {
			try {
				$result = call_user_func(array($this, $op_method));
			} catch(Exception $e) {
				// TODO: Log Skynet Controller operation failure ($e)
				MVC_Core::$debug-> dump("Exception triggered in Skynet Operation: {$rq-> op}");
			}
		} else {
			// TODO: Log Skynet Controller failure (operation doesn't exist)
			MVC_Core::$debug-> dump("Skynet Controller Operation {$rq-> op} not found");
		}
		// TODO: Log any potential output ($result, $output)
		$output = ob_get_clean();

		// After running the controller, cleanup
		$this-> m = array();
		$this->_out = null;
	}

	/**
	 * Default Operation - Does nothing, needs to be overwritten
	 * @return boolean
	 */
	public function op_Default()
	{
		return true;
	}
}