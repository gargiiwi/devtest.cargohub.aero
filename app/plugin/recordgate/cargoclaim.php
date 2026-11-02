<?php
/**
 * Record gate plugin for "Air Cargo Claims"
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage Plugins
 * @version 2018.07
 */
class App_Plugin_Recordgate_Cargoclaim implements App_Plugin_Recordgate, App_Plugin_CMS
{
	/**
	 * Params from the constructor parameters
	 * @var array
	 */
	private $params;

	/**
	 * Initialization controller - Receives parameters for initialization
	 * @param array $params
	 * @param object $master Caller
	 */
	public function __construct($params, $master)
	{
		$this-> params = &$params;
	}

	/**
	 * Returns configuration for record gate. May include domain config and input processing.
	 * Also contains list of jqwidgets components used by this type
	 */
	public function get_Config()
	{
		// Fixed part
		$cfg = array(
			'rt_name'=> "Air Cargo Claim",
			'components'=> array('modal', 'contact_editor'),
			'jqwidgets'=> array('navigationbar', 'listbox'),
			'xjs'=> array(
				'jqform'=> true
			)
		);

		// Dynamic part
		$autofill = array();
		// If parameter "mawb" or "mawbp" is present in the request, pass it on to JavaScript so the AWB is autofilled
		if($this-> params['rq']-> mawbp) {
			$autofill['mawb'] = array('p'=> substr($this-> params['rq']-> mawbp, 0, 3), 's'=> '');
		} elseif($this-> params['rq']-> mawb) {
			$this-> params['rq']-> add_Filter("mawb", "MAWB");
			$autofill['mawb'] = $this-> params['rq']-> mawb?:array('p'=> '', 's'=> '');
		}

		// If an E-mail is present in the parameters, set this as autofill, but only if it checks as a valid E-mail
		$this-> params['rq']-> add_Filter('email', "Is_Email");
		if($this-> params['rq']-> email)
			$autofill['email'] = $this-> params['rq']-> email;

		// If CaC password is present, pass it on (for following links inside e-mails, to auto-login for claim viewing/updating)
		if($this-> params['rq']-> password)
			$autofill['password'] = $this-> params['rq']-> password;

		if(!empty($autofill))
			$cfg['autofill'] = $autofill;

		return $cfg;
	}

	/**
	 * Gate operation requests that come from the JS UI
	 * @param string $op
	 * @param array $data
	 */
	public function gate_Operation($op, $data, &$gate_cfg)
	{
		switch($op) {
			case 'mawb-check':
				return $this-> op_MAWB_Check($data);
			case 'email-check':
				return $this-> op_Email_Check($data);
			case 'contact-check':
				return $this-> op_Contact_Check($data);
			case 'user-redirect-url':
				return $this-> op_Setup_User_RedirectURL($data);
			case 'setup-form-autofill':
				return $this-> op_Setup_Form_AutoFill($data, $gate_cfg);
			default:
				throw new Exception("Invalid gate operation requested: {$op}", 400);
		}
	}

	private static $airlines_table = "data_airlines";

	/**
	 * Checks the MAWB number against the claims records
	 * - (But first it checks the prefix to be from a valid airline)
	 * @param string $mawb
	 * @return array Outcome of check
	 */
	private function op_MAWB_Check($mawb)
	{
		// 1. Test if the prefix is a valid airline
		$q=QueryFactory::select()
		-> table(self::$airlines_table)
		-> where("`AWB_prefix` = ?", $mawb['p'])
		-> limit(1);

		$r = MVC_Core::$db-> query($q);
		if(!$r || count($r) == 0) {
			return array(
				'mawb_outcome'=> "error",
				'message'=> "MAWB prefix {$mawb['p']} is not a valid airline ID assigned by IATA"
			);
		}

		// 2. Attempt to locate AWB number in the fields - The ID is "3" for "mawb" field of RT_id = 1
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> tab['ixf'], 'issue_id')
		-> where("`fk_id` = 3") // Air Cargo Claim MAWB field id = 3
		-> where("`value` = ?", $mawb['p'].'-'.$mawb['s'])
		// At this stage, there is no need to verify if there's multiple on the same e-mail. They will be checked again with Contact ID's in the Email_Check or Contact_Check methods
		-> limit(1);

		$r = MVC_Core::$db-> query($q);
		if(!$r || count($r) == 0)
			return array(
				'mawb_outcome'=> "not-found",
				'mawb'=> $mawb['p'].'-'.$mawb['s']
			);

		return array(
			'mawb_outcome'=> "mawb-located"
		);
	}

	/**
	 * Combines the MAWB search with a contact ID (or list of contact ID's) to identify exact claim.
	 * - Modifies the $check parameter with the results
	 * @param array $check
	 */
	private function combo_Check_MAWB(&$check)
	{
		// 1. Test if the prefix is a valid airline (only if asked)
		if(!empty($check['prefix_check'])) {
			$prefix = substr($check['mawb'], 0, 3);

			$q=QueryFactory::select()
			-> table(self::$airlines_table)
			-> where("`AWB_prefix` = ?", $prefix)
			-> limit(1);

			$r = MVC_Core::$db-> query($q);
			if(!$r || count($r) == 0) {
				$check['mawb_outcome'] = "error";
				$check['message'] = "MAWB prefix {$prefix} is not a valid airline ID assigned by IATA";
				return;
			}
		}

		// 2. Check records and filter for both MAWB and contact IDs
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> table('ixf'), 'issue_id')
		-> where("`ixf`.fk_id = 3") // Air Cargo Claim MAWB field id = 3
		-> where("`ixf`.value = ?", $check['mawb'])
		-> table(MVC_Core::$db-> table('c2i'), array('contact_id', 'org_id', 'user_id'), 'left', "(issue_id)")
		-> where("`c2i`.role = 'submitter'")
		-> table(MVC_Core::$db-> table('iss'), array('track', 'submit_id'), 'left', "`iss`.id = `ixf`.issue_id");


		if(isset($check['list'])) {
			$q-> where("`c2i`.contact_id IN(?)", $check['list']);
			unset($check['list']);
		} elseif(isset($check['con_id']))
			$q-> where("`c2i`.contact_id = ?", $check['con_id']);

		$r = MVC_Core::$db-> query($q);
		if(!$r || count($r) == 0) {
			// The found claim(s) are not submitted under current e-mail or contact ID (or list)

			// 3. In case there's no results, and this flag is on, it still checks if the claim exists, but doesn't belong to the specified contact(s)
			if(!empty($check['not_owned_check'])) {
				$q=QueryFactory::select()
				-> table(MVC_Core::$db-> tab['ixf'], 'issue_id')
				-> where("`fk_id` = 3") // Air Cargo Claim MAWB field id = 3
				-> where("`value` = ?", $check['mawb'])
				-> limit(1);

				$r = MVC_Core::$db-> query($q);
				if(!$r || count($r) == 0)
					$check['mawb_outcome'] = "not-found";
				else
					$check['mawb_outcome'] = "not-owned"; // Found but not for the specified contacts
			} else
				$check['mawb_outcome'] = "no-results";

		} elseif(count($r) == 1) {
			// E-mail is the submitter of unique claim with the specified MAWB number - After logging in through AJAX, the Gate redirect to the claim itself
			// (either customer acc or platform user - two different redirects)
			$check['mawb_outcome'] = "unique-claim";
			$rec = $r-> current();
			// Id and Track ID necessary to point to the proper record (customer or staff)
			$check['con_id'] = (int)$rec['submit_id'];
			$check['rec_id'] = (int)$rec['issue_id'];
			$check['track_id'] = $rec['track'];
		} else {
			// E-mail is the submitter of several claim(s) with the specified MAWB number - Redirect to grid/overview is necessary to select which claim will be accessed
			// -- This is an unfortunate result of this Record Gate not being available until now
			// (either customer acc or platform user - Injecting search parameter in the grid not yet supported)
			$check['mawb_outcome'] = "multiple-claims";
			$check['count'] = count($r);
			unset($check['list']); // List is not useful here, since it will redirect anyway
		}
	}

	/**
	 * Contact model, used by the Email_Check and Contact_Check operations
	 * @var App_Model_Contact
	 */
	private $con;

	/**
	 * Checks the E-mail in all the staff and submitter e-mails for claim records
	 *
	 * @param array $data
	 * @return array Outcome of check
	 *
	 */
	private function op_Email_Check($data)
	{
		$this-> con = new App_Model_Contact();

		// Try user address first ( user is not logged in but uses "user_self" addresses - The system recognizes it's users )
		$con_found = $this-> con-> locate("user_self", $data['email'], null, null, null, null, false, true);
		if(!empty($con_found)) {
			$result = array(
				'outcome'=> "platform-user",
				'con_id'=> (int)$con_found['id'],
				'org_id'=> (int)$con_found['org_id']
			);
		}

		if(!isset($result)) {
			// Search in previously submitted records, submitter contacts (isspost)
			$contact_ids = $this-> con-> locate("isspost", $data['email'], null, null, null, null, true);
			if(!empty($contact_ids)) {
				$result = array(
					'outcome'=> "customer-account"//,
				);
				if(count($contact_ids) == 1)
					$result['con_id'] = (int)$contact_ids[0];
				else
					$result['list'] = $contact_ids;
			}
		}

		// When a claim with AWB is already located, the e-mail of the submitter must be checked along with the MAWB number, to determine if the specified e-mail is the submitter
		if(isset($result) && $data['outcome'] == 'mawb-located') {
			$result['mawb'] = $data['mawb'];
			$this-> combo_Check_MAWB($result);
		}

		// Check against organization target contacts - The mail box is for the submitter, not for the recipient !
		if(!isset($result)) {
			$q= QueryFactory::select()
			-> table(MVC_Core::$db-> table('ctx'), false)
			-> table(MVC_Core::$db-> table('otc'), 'org_id', 'left', "`ctx`.id = `otc`.contact_id")
			-> where("`ctx`.type = 'org_self'")
			-> where("`ctx`.email = ?", $data['email'])
			-> limit(1);

			$r = MVC_Core::$db-> query($q);
			if(count($r) > 0) {
				$result = array(
					'outcome'=> "invalid-address",
					'email'=> $data['email']
				);
			}
		}

		// Draft contacts aren't checked against claims - With this outcome, the gate UI will show the contact or allow it to be edited
		if(!isset($result)) {
			// Search in contact drafts (unlinked), but not older than 2 hours - The contact info might have just been entered with another browser (or on another device)
			$con_found = $this-> con-> locate("draft", $data['email'], null, null, null, null, false, true);
			if(!empty($con_found)) {
				$result = array(
					'outcome'=> "draft-contact",
					'con_id'=> (int)$con_found['id']
				);
			}
		}

		if(!isset($result)) {
			// Still nothing ? It means this is brand new e-mail. Return that result
			$result = array(
				'outcome'=> "not-found",
				'email'=> $data['email']
			);
		}
		return $result;
	}

	/**
	 * Customer account, used by op_Contact_Check
	 * @var App_Model_ContactAccount
	 */
	private $cac;

	/**
	 * When contact ID is already known (selected from a list?), it checks along MAWB number to determine if the found claim(s) were submitted by that contact
	 * - This is similar to the E-mail search, but directly with a single contact ID
	 * - However, it also checks all "managed contacts" in the same Customer Account (since by logging in with that account, all claims from all those contacts should be accessible)
	 * @param array $check
	 */
	private function op_Contact_Check($check)
	{
		$this-> cac = new App_Model_ContactAccount();
		$cac = $this-> cac-> read($check['con_id']);
		if($cac) {
			if(!empty($cac['managed_ctx'])) {
				$check['list'] = explode(',', $cac['managed_ctx']);
				array_unshift($check['list'], $cac['id']);
				array_filter($check['list'], function(&$id) { $id = (int)$id; });
			}

		} else {
			// No contact account exists, only verify against the contact ID
		}
		$this-> combo_Check_MAWB($check);

		unset($check['prefix_check'], $check['not_owned_check']); // These are not needed in the return
		return $check;
	}

	/**
	 * Saves the URI for platform user login auto-redirect
	 * @param unknown $data
	 * @return array
	 */
	private function op_Setup_User_RedirectURL($data)
	{
		MVC_Core::$ss-> original_url = $data['uri'];

		// group= ?
		return array('outcome'=> 'success');
	}

	/**
	 * Saves the information entered or selected in the Gate so it can be used in the submit form
	 * - Later, the form will not be required to enter that information anymore (it will be read-only), having the gate as the only point to enter that data.
	 * @param array $data
	 * @return array
	 */
	private function op_Setup_Form_AutoFill($data, &$gate_cfg)
	{
		$gate_cfg['form_autofill'] = $data;

		return array('outcome'=> 'success');
	}

	/**
	 * Checks existing stored gate configuration data (usually inside MVC_Core::$cache-> gate_cfg) if it's sufficient to autoforward
	 * -- If yes, it returns the URL to which main gate controller will auto-redirect without loading the gate UI
	 * -- It may also setup other parameters necessary for the receiving module
	 * FIXME: Not implemented
	 *
	 * @param array $stored_gate_cfg
	 */
	public function check_Gate_Redirect($stored_gate_cfg)
	{
// 		dd($stored_gate_cfg);

		return false;
	}

//---- Implementation of App_Plugin_CMS ----//

	/**
	 * Renders the HTML output
	 *
	 * @param string $param_string
	 */
	public function render_Output($param_string = null)
	{
		return $this-> params['drytpl']-> render_File("issues". DS. "cargoclaim". DS. "_gate.html");
	}

	/**
	 * This has no purpose here, as the gate handles requests through "gate_Operation"
	 * TODO: Maybe change gate_Operation into op_Hub so the two plugin types are compatible?
	 */
	public function op_Hub(MVC_Request $in)
	{
	}

}