<?php
/**
 * Skynet Controller handling Time-triggered actions
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect.Skynet
 * @subpackage Controllers
 * @version 2020.09.29
 */
class App_Ctrl_Skynet_Actions extends Skynet_Controller
{
	/**
	 * Actions data model
	 * @var App_Model_Actions
	 */
	private $actions;

	/**
	 * Actions controller constructor
	 */
	function __construct()
	{
		$this-> actions = $this-> factory_Model("actions");
	}

	/**
	 * The default operation for the Actions controller for Skynet scans for defined actions
	 * - If there are actions with the trigger_dt in the past, they get processed through their respective plugins
	 */
	public function op_Default()
	{
		$actions = $this-> actions-> scan();
		if(!$actions) // No actions found, silently shutdown
			return null;

		// For the moment, execute actions one by one
		// TODO: Find a way to group multiple similar actions and sent them to a "group_Execute" method (for example, group notifications meant for the same user during one day)

		foreach($actions as $action) {
			$outcome = $this-> actions-> execute($action);

			// TODO: Log outcome of action
		}
	}

	/**
	 * Testing action creation
	 */
	public function op_Create()
	{
		// Nothing here atm
	}
}