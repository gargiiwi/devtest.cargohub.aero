<?php
/**
 * Contact-associated account, based on the e-mail addresses and a password
 * - The contact accounts are used as a low-security login to allow viewing the data intended for that contact (issues, conversations)
 * TODO: Rename this into "Customer Account"
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage Models
 * @version 2018.07
 */
class App_Model_ContactAccount extends MVC_Model
{
	/**
	 * Generates a random password from letters and numbers, in a similar way the TrackID is created, but without the dashes and with lower characters
	 * -- Includes bad words checking
	 * @param int $length How long the password will be ? By default, 10 characters
	 */
	private function generate_Password($length = 10)
	{
		// ----- vowels ---- consonants ------ numbers -- (some are removed because they cause easy confusion )
		$chars = "aeuy"."bdghjklmnpqrstvwxz"."123456789"; $l = strlen($chars)-1;

		while(1) {
			$pwd = '';
			for($c = 0; $c< $length; $c++)
				$pwd.=$chars[mt_rand(0, $l)];
			// discover some 3-4 letters "bad" words in the $pwd, and if they are present, restart the process. (No reason to go for 5-letter, the chance of that happening is extremely small)
			// TODO: Support custom word list, to addapt to other languages bad words
			// Note that not all alphanumeric characters are used, and since I, O, C, F and 0 are missing, many words cannot be formed.
			$badwords = array(
				'ass', 'gay', 'kum', 'pee', 'sex', 'suk', 'xxx', 'anal', 'anus', 'arse', 'butt', 'dyke', 'dump', 'jerk', 'j1zz', 'kunt', 'p1ss', 'puss', 'pusy', 'sh1t', 'slut', 'turd', 'twat', 'wank'
			);
			foreach($badwords as $word)
				if(strpos($pwd, $word)!==false)
					continue;
			break;
		}

		return $pwd;
	}

	//----- CRUD ----//

	/**
	 * Creates a Contact Account for a specific account
	 * - The contact accounts are used as a low-security login to allow viewing the data intended for that contact (issues, conversations)
	 *
	 * @param string $email The e-mail is unique. All contacts using this e-mail should appear in the "managed_ctx"
	 * @param int $contact_id The primary contact record associated with this account
	 * @param int $user_id If not null, this contact actually belongs to a user within the system.
	 * @param string|array $managed_ctx List of extra contact records under the same e-mail address, or a different address but still assigned to the same person (like business e-mail, personal e-mail, etc.)
	 * @param mixed $xdata Data associated with this e-mail account
	 * @return string|bool On success, the generated password; FALSE if the account could not be created (possibly because the e-mail or contact_id is already used)
	 */
	public function create($email, $contact_id, $user_id = null, $managed_ctx = null, $xdata = null)
	{
		$pwd = $this-> generate_Password();

		if(is_array($managed_ctx))
			$managed_ctx = implode(',', $managed_ctx);

		$cac = array(
			'email'=> $email,
			'password'=> $pwd,
			'contact_id'=> $contact_id,
			'user_id'=> $user_id,
			'managed_ctx'=> $managed_ctx
		);

		if(!is_null($xdata))
			$cac['xdata'] = json_encode($xdata);

		try {
			$q=QueryFactory::insert()
			-> table(MVC_Core::$db-> tab['cac'])
			-> values($cac);

			$r = MVC_Core::$db-> query($q);
			if($r)
				return $pwd;
			else
				return false;
		} catch(Exception $e) {
			// Possibly the uniqueness flag was triggered (the contact might be already linked in the same conditions)
			return false;
		}
	}

	/**
	 * Reads the information about this contact account. Can be used to check the login information
	 *
	 * @param string $email_or_contact_id The e-mail being searched for or a numeric contact ID
	 * @param array @wtr What to read, may contain "contact" (primary contact info), "managed" (all contacts in the "managed_ctx" field), "user" (associated user information if this contact account belongs to a user)
	 * @return array|bool Returns FALSE when the Contact Account was not found (no such e-mail)
	 */
	public function read($email_or_contact_id, $wtr = array())
	{
		$wtr = self::wtr_Helper($wtr);

		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> table('cac'), 'email,password,logon_dt,contact_id,user_id,managed_ctx,xdata')
		-> limit(1);

		if(in_array('contact', $wtr))
			// Read detailed contact information for the user (contact xdata)
			$q-> table(MVC_Core::$db-> table('ctx'), QueryFactory::autoalias_Add_Prefix("name,email,xdata,count_msgs,note_id", '_ctx_'), 'left', '`cac`.`contact_id` = `ctx`.`id`');

		// TODO: "managed", "user", "contact_note"

		if(is_numeric($email_or_contact_id))
			$q-> where("`cac`.contact_id = ?", $email_or_contact_id);
		else
			$q-> where("`cac`.email = ?", $email_or_contact_id);

		$r = MVC_Core::$db-> query($q);
		if(!$r || count($r)==0)
			return false;

		// Found, read the data
		$r-> split_by_prefixes = array(
			// extra tables
			'contact'=> "_ctx_"
		);

		$cac = $r-> current();

		$cac['id'] = (int)$cac['contact_id'];

		if(!is_null($cac['xdata']))
			$cac['xdata'] = @json_decode($cac['xdata'], true);

		if(in_array('contact', $wtr) && !is_null($cac['contact']['xdata']))
			// Also decode contact xdata if present
			$cac['contact']['xdata'] = @json_decode($cac['contact']['xdata'], true);

		return $cac;
	}

	/**
	 * Updates information about a contact account, updating one or more of the various parameters (At least one must be non-null)
	 *
	 * @param string|int $email_or_contact_id
	 * @param string|array $managed_ctx
	 * @param string|mixed $xdata
	 * @param bool $update_logon_dt If set True, it updates the "logon_dt" to current date
	 * @param bool $regen_password If set True, the system generates a new password automatically
	 */
	public function update($email_or_contact_id, $managed_ctx = null, $xdata = null, $update_logon_dt = false, $regen_password = false, $new_email = null)
	{
		$cac_update = array();

		if($update_logon_dt)
			$cac_update['logon_dt'] = date("Y-m-d H:i:s");

		if($regen_password)
			$cac_update['password'] = $this-> generate_Password();

		if(!is_null($xdata))
			$cac_update['xdata'] = json_encode($xdata);

		if(is_array($managed_ctx))
			$managed_ctx = implode(',', array_filter($managed_ctx));

		if(!is_null($managed_ctx))
			$cac_update['managed_ctx'] = $managed_ctx;

		if(!is_null($new_email))
			$cac_update['email'] = $new_email;

		if(empty($cac_update))
			return false; // Nothing to update

		// update frontend accounts table
		$q=QueryFactory::update()
		-> table(MVC_Core::$db-> tab['cac'])
		-> values($cac_update)
		-> limit(1);

		if(is_numeric($email_or_contact_id))
			$q-> where("contact_id = ?", $email_or_contact_id);
		else
			$q-> where("email = ?", $email_or_contact_id);

		try {
			if(!MVC_Core::$db-> query($q))
				return false;

			if(isset($cac_update['password']))
				return $cac_update['password'];
			else
				return true;
		} catch(Exception $e) {
			// error, could not update?
			return false;
		}
	}

	/**
	 * Moves a contact account from an original contact to a new one (e-mail changes), password does not.
	 * This operation is not reversible, as original e-mail is replaced ! This method is only used by the shadow contact mode (copy when E-mail changes)
	 *
	 * @param int $original_id
	 * @param int $new_id
	 * @param string $new_email
	 */
	public function update_to_Contact($original_id, $new_id, $new_email)
	{
		$q=QueryFactory::update()
		-> table(MVC_Core::$db-> tab['cac'])
		-> values(array(
			'email'=> "'{$new_email}'",
			'contact_id'=> $new_id,
			'managed_ctx'=> "IF(`managed_ctx` IS NULL OR `managed_ctx`='', {$original_id}, CONCAT_WS(',', managed_ctx, {$original_id}))"
		), null, true)
		-> where("`contact_id` = ?", $original_id)
		-> limit(1);

		if(!MVC_Core::$db-> query($q))
			return false;
		return true;
	}

	/**
	 * Removes a contact account. This should be done following the deletion of it's associated primary contact
	 * @param string|int $email_or_contact_id
	 */
	public function delete($email_or_contact_id)
	{
		// delete from users table
		$q=QueryFactory::delete()
		-> table(MVC_Core::$db-> tab['cac'])
		-> limit(1);

		if(is_numeric($email_or_contact_id))
			$q-> where("contact_id = ?", $email_or_contact_id);
		else
			$q-> where("email = ?", $email_or_contact_id);

		return MVC_Core::$db-> query($q);
	}

	/**
	 * Tests an e-mail and password against the contact account table
	 *
	 * @param string $email
	 * @param string $password
	 */
	public function test_Login($email, $password)
	{
//		$q=QueryFactory::select()
//		-> table(MVC_Core::$db-> tab['cac'], 'contact_id,user_id,managed_ctx,xdata')
//		-> where("`email` = ?", $email)
//		-> where("`password` = ?", $password)
//		-> limit(1);
$q="SELECT `contact_id`, `user_id`, `managed_ctx`, `xdata` FROM `contact_accounts` WHERE `email` = '$email' AND `password` = '$password' LIMIT 1";
		$r = MVC_Core::$db-> query($q);
		if($r && $r-> num_rows == 1) {
			// email was found, read the data
			$data = $r-> current();
			if(!empty($data['xdata']))
				$data['xdata'] = @json_decode($data['xdata'], true);

			$data['email'] = $email;
			$data['password'] = $password;
			return $data;
		}

		// error, fail to find e-mail and matching password - Add or increment an entry to the "crk" table
		// -- When reaching a certain number of failed logins, this will completely disable access to the website from this IP address for a period of time (anti-brute-force-crack feature)
		$ip_lu = sprintf('%u', ip2long($_SERVER['REMOTE_ADDR']));
		$q=QueryFactory::insert()
		-> table(MVC_Core::$db-> tab['crk'])
		-> values(array('ip'=> $ip_lu));

		$q = $q. " ON DUPLICATE KEY UPDATE `number`=`number`+1";

		MVC_Core::$db-> query($q);
		return false;
	}

	/**
	 * Creates the session entry which allows a contact to access their data
	 * -- Updates contact account "logon_dt" to current time
	 *
	 * @param array $cac Information about the Contact Account
	 * @param bool $update_time Save the login time in the database
	 */
	public function do_Login($cac, $update_time = true)
	{
		// Read the contact what is being logged in (with full xdata)
		$m_con = new App_Model_Contact();

		$contact = $m_con-> read($cac['contact_id'], false); // Don't read ixdata, not needed in the session - Only when editing the contact
		if(empty($contact))
			return false; // Error, could not read the contact??

		// create the session information
		MVC_Core::$ss-> contact_account = array(
			'id'=> (int)$cac['contact_id'],
			'email'=> $cac['email'],
			'password'=> $cac['password'],
			'login_time'=> time(),
			'contact'=> $contact,
			'managed_ctx'=> $cac['managed_ctx'],
			'cac_xdata'=> $cac['xdata']
		);

		// update logon time in frontend accounts table
		if($update_time)
			$this-> update($cac['contact_id'], null, null, true);
	}

	/**
	 * Delets the session entry for frontend user preventing viewing of issues
	 */
	public function do_Logout()
	{
		unset(MVC_Core::$ss-> contact_account);
	}

}