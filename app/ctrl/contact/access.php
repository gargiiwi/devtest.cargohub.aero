<?php
/**
 * Contact access controller - Used to handle logging in/out of external contacts (login with e-mail address + supplied password)
 * - Contact login / logout
 * - Contact password recovery
 * - List of managed contacts
 *
 * TODO: There is a "global/contact" controller, start moving functions from there into the /ctrl/contact structure
 *
 * @author Wavetrex (wavetrex@gmail.com)
 * @package CargoITS
 * @version 1.0.dev
 */
class App_Ctrl_Contact_Access extends App_Ctrl_Base
{
	/**
	 * Reference to the Contact Model - Helps dev by providing autocompletion
	 * @var App_Model_Contact
	 */
	private $con;

	/**
	 * Reference to the Contact Account Model
	 * @var App_Model_ContactAccount
	 */
	private $cac;

	/**
	 * Contact access controller Constructor 
	 */
	function __construct()
	{
		parent::__construct();

		// Controller specific models
		$this-> con = $this-> factory_Model('Contact', 'con');
		$this-> cac = $this-> factory_Model('ContactAccount', 'cac');
	}

	/**
	 * /contact/access - Default to the "Login" action
	 */
	public function action_Index(MVC_Request $in)
	{
		$this-> action_Login($in);
	}

	/**
	 * Redirect on successful login ( or if attempting to access the login page when already being logged in ):
	 * - Either go to a previously saved location under key "contact_login"
	 * - Or load the the contact account management page
	 *
	 */
 	private function redirect_After_login()
 	{
 		$location = $this-> get_Location("contact_login", true); // Remove on redirect

 		if($location) {
 			$hash = isset($location['data']['hash'])? "#".$location['data']['hash']: "";
 			return $this-> redirect($location['url'].$hash);
 		} else
 			$this-> redirect("/contact/account#issues");
 	}

	/**
	 * When a viewer is not logged in, a simple login form is displayed instead of the normal list/item
	 * -- It's only possible to find and view issues, communicate with staff or download attachments after "view_account" var exists in session
	 * -- This mini-account cannot be saved as a cookie to allow viewing from unsafe computers then closing the browsers which effectively logs the user out (by invalidating session)
	 *
	 * @param MVC_Request $in
	 * @param string $forward_action
	 */
	public function action_Login(MVC_Request $in)
	{
		// Check if already logged in - If so, redirect
		if(MVC_Core::$ss-> contact_account || MVC_Core::$ss-> user)
			return $this-> redirect_After_Login();

		// Is there a "return" parameter ? This function recognizes a few, and saves a return location for successful login
		if(isset($in-> return)) {
			switch($in-> return) {
				case 'vcard':
					$this-> save_Location("contact_login", "/contact/account#vcard");
					break;
				case 'issue-submit':
					$this-> save_Location("contact_login", "/issue?action=form");
					break;
				case 'convo':
					$this-> save_Location("contact_login", "/?action=external_comm&key=".$in-> key);
					break;
			}
		}

		$this-> CFG['components'] = array("modal");
		$this-> CFG['modules'][] = "contact_login";
		$this-> CFG['xjs'] = array(
			'jqform'=> true
		);

		$this-> prepare_Header("Customer Account Log-in", array("userbar", "evan"));
		$this->_out['header']['rightmenu'] = array(
			array(
				'link'=> "?action=recover_password",
				'text'=> "Forgot your Password?"
			)
		);

		// E-mail and password already present in the request ( E-mail link? )
		if(isset($in-> email))
			$this-> autofill_email = $in-> email;
		if(isset($in-> password))
			$this-> autofill_password = $in-> password;

		// Automatically press the login button? (Also, from E-mail link)
		if(isset($in-> autologin) && $in-> autologin==1)
			$this-> CFG['contact_login'] = array('autologin'=> true);

		// which body template to use, and run the view
		$this-> site_section = "contact". DS. "login.html";
		$this-> view("public.html");
	}

	/**
	 * Available for admin only, to login as another user without knowing their password.
	 * -- This function creates the contact account if it doesn't exist yet and assigns an automatic password (Like the Recover function)
	 */
	public function action_Login_As(MVC_Request $rq)
	{
		// This module is only available for user managers
		if(!$this-> user-> is_Unrestricted())
			return header("HTTP/1.0 403 No access to this operation");

		if(!$rq-> email)
			return header("HTTP/1.0 400 No E-mail was specified in the request");

		// Attempt to read the contact account information for the specified E-mail. If not found, the record is created.
		$cac = $this-> cac-> read($rq-> email);
		if(empty($cac)) {
			// Ok, a contact accound does not exist for this e-mail address...
			// ... however, is there any "isspost" contact or contacts with this address in the list of contacts themselves ? If so, create the contact account.
			$ctx = $this-> con-> locate("isspost|isslink", $rq-> email);

			if($ctx && count($ctx)>0) {
				// Make the contact account from the oldest contact
				$first_con = array_shift($ctx);

				$managed_ctx = array();
				foreach($ctx as $contact)
					$managed_ctx[] = (int)$contact['id'];
				$cac_xdata = array('ist_list'=> array()); // TODO: Find the issues that were submitted by the contacts having this e-mail address, for an accurate list. For now, just use cargo claim.

				$password = $this-> cac-> create($rq-> email, $first_con['id'], null, $managed_ctx, $cac_xdata);
				if(!$password)
					return header("HTTP/1.0 500 The e-mail address was found but the customer account could not be validated. Contact Wavetrex.");

				// Build the contact account info
				$cac = array(
					'contact_id'=> $first_con['id'],
					'managed_ctx'=> $managed_ctx,
					'cac_xdata'=> $cac_xdata
				);
			} else
				return header("HTTP/1.0 500 This E-mail has not been used to submit any record yet. Cannot create a Customer Account");
		}

		// Ready to proceed, logout first
		$this-> user-> do_Logout();
		// Found or Created - Login as the CAC
		$this-> cac-> do_Login($cac);

		$this-> evan-> reset();
		$this-> evan-> add(sprintf("Logged in as a customer account for E-mail: %s", $rq-> email));
		$this-> send_JSON(array('success'=> true, 'redirect'=> "/contact/account"));
	}

	/**
	 * AJAX Authenticate - Checks the E-mail/Password before actually submitting/reloading the page
	 * - Returns an error if no match was found
	 */
	public function action_Authenticate(MVC_Request $post)
	{
		// required fields
		if(!$post-> email || !$post-> password)
			return header("HTTP/1.0 400 Please fill E-mail address and Password");

		$test = $this-> cac-> test_Login($post-> email, $post-> password);
		if($test) {
			$this-> cac-> do_Login($test);

			if($post-> autoforward)
				header("Location: ?action=login");
			else
				$this-> send_JSON(array('success'=> true));
		} else
			return header("HTTP/1.0 403 Authentication failed. You may try to obtain a new password.");
	}

	/**
	 * Displays an input for the user to put in their e-mail address with the purpose of recovering the contact account password
	 */
	public function action_Recover_Password(MVC_Request $in)
	{
		// When already logged in, this function serves no purpose, so go back to index
		if(MVC_Core::$ss-> contact_account || MVC_Core::$ss-> user)
			return $this-> redirect("/");

		$this-> CFG['components'] = array("modal");
		// Display forgot_main modules
		$this-> CFG['modules'][] = "contact/_pwsend";
		$this-> CFG['xjs'] = array(
			'jqform'=> true
		);

		$this-> prepare_Header("Recover contact password", array("userbar", "evan"));
		$this->_out['header']['rightmenu'] = array(
			array(
				'link'=> "?action=login",
				'text'=> "Nevermind, I remembered it!"
			)
		);

		// which body template to use, and run the view
		$this-> site_section = "contact". DS. "recover_password.html";
		if(!empty(MVC_Core::$ss-> domain_CFG['html_settings']['embedded']))
			$this-> view("public_embedded.html");
		else
			$this-> view("public.html");
	}

	/**
	 * Sends an e-mail containing the "Contact Account" password to the e-mail address sent in the request
	 * - First it verifies that the contact account actually exists and it has an allocated password. If not, returns an error
	 */
	public function action_Send_Contact_Password(MVC_Request $post)
	{
		if(!$post-> email)
			return header("HTTP/1.0 400 No e-mail address present in the request");

		$cac = $this-> cac-> read($post-> email, array('contact'));
		if(empty($cac)) {
			// Ok, a contact accound does not exist for this e-mail address...
			// ... however, is there any "isspost" contact or contacts with this address in the list of contacts themselves ? If so, create the contact account.
			$ctx = $this-> con-> locate("isspost|isslink", $post-> email);

			if($ctx && count($ctx)>0) {
				// Make the contact account from the oldest contact
				$first_con = array_shift($ctx);

				$managed_ctx = array();
				foreach($ctx as $contact)
					$managed_ctx[] = (int)$contact['id'];

				$password = $this-> cac-> create($post-> email, $first_con['id'], null, $managed_ctx, array('ist_list'=> array(1))); // TODO: Find the issues that were submitted by the contacts having this e-mail address, for an accurate list. For now, just use cargo claim.
				if(!$password)
					return header("HTTP/1.0 500 The e-mail address was found but due to a database error your password could not be sent");

				// Build the contact account info
				$cac = array(
					'email'=> $post-> email,
					'password'=> $password,
					'contact_id'=> $first_con['id'],
					'contact'=> $first_con
				);
			} else
				return header("HTTP/1.0 404 Unfortnately, the e-mail you have entered does not exist in our database");
		}

		// Generate a system message and then send it through E-mail, using the Communication model.
		// A conversation is not created, and this system sent message (from_id = 0) is not connected to any conversation (And can't be seen inside the system or replied).
		// The only purpose is to use the same mechanism for sending e-mails like any other communication, and have a record of the e-mail being sent.
		$m_comm = new App_Model_Comm();
		// TODO: A future admin module that accesses all communication should be able to find/view it.
		$msg_id = $m_comm-> create_System_Message($cac['contact_id'], "contact_account_password.html", $cac);
		if(!$msg_id)
			return header("HTTP/1.0 500 Unable to send the recovery E-mail. Please contact support");

		$r = $m_comm-> email_Message($msg_id, null, null, true, true); // true = standalone message, no conversation.
		if(!$r)
			return header("HTTP/1.0 500 Unable to send the recovery E-mail. Please contact support");

		// Finally...
		$this-> evan-> add(sprintf("Your Free-access account password has been sent to <b>%s</b>. Please check your E-mail !", $post-> email), 'send');
		$this-> send_JSON(array('success'=> true));
	}

}