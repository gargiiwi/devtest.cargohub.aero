<?php

/**
 * Users Access related (Login/Logout/Account creation/Recovery)
 */
class App_Ctrl_Public_Access extends App_Ctrl_Base {

    /**
     * Communication Model - Used to for password recovery, account creation, etc.
     * @var App_Model_Comm
     */
    protected $comm;

    /**
     * Controller Contructor
     */
    function __construct() {
        parent::__construct();

        $this->comm = $this->factory_Model("Comm");
    } 

    /**
     * A little helper that redirects the user to his chosen location after login (or returns the redirect URL to be sent to JS)
     *
     * @param bool $auto_redirect If true, redirects to "page after login"; If false, just returns it
     */
    protected function redirect_After_Login($auto_redirect = true) { 
        //publication read only IWI start
        if(MVC_Core::$ss-> user['status']=='readonly'){
            $redirect_url = '/user/publication';
             $this->redirect($redirect_url);
             exit();
        }
         //publication read only IWI end
        // construct the redirect URL, depending on how the entry was requested
        if (MVC_Core::$ss->original_url) {
            $redirect_url = MVC_Core::$ss->original_url;
            unset(MVC_Core::$ss->original_url);
        } else
        {
        $redirect_url = MVC_Core::$ss-> user['preferences']['logon_redirect'];
           // $redirect_url = '/user/overview#dashboard';
        }

        // ... and redirect or return it
        if ($auto_redirect)
            $this->redirect($redirect_url);
        else
            return $redirect_url;
    }

//===== General user Authentication =====//

    /**
     * Login Form
     */
    public function action_Login(MVC_Request $rq) {
        // Already logged in ? Just go into the user's default controller
        if (MVC_Core::$ss->user)
            $this->redirect_After_Login();

        // not logged in, continue here:
        $this->CFG['components'] = array("modal");
        $this->CFG['modules'][] = "user/_access_login";
        $this->CFG['xjs'] = array(
            'jqform' => true
        );

        $this->prepare_Header("System User Login", array("userbar", "evan"));
        $this->_out['header']['rightmenu'] = array(
            array(
                'link' => "?action=reset_password",
                'text' => "Forgot your Password?"
            )
        );

        if ($rq->username)
            $this->username = $rq->username;

        // which body template to use, and run the view
        $this->site_section = "user" . DS . "access_login.html";

        if (MVC_Core::$ss->domain_CFG['html_settings']['domain'] == '5.61.253.183' || MVC_Core::$ss->domain_CFG['html_settings']['domain'] == 'cargohub.aero') {
            MVC_Core::$ss->domain_CFG['html_settings']['logo'] = 'cargoclaim_banner.png';
        }

        if (!empty(MVC_Core::$ss->domain_CFG['html_settings']['embedded']))
            $this->view("public_embedded.html");
        else
            $this->view("public.html");
    }

    /**
     * Automatically redirects to the proper area.
     * -- When user is logged in, redirects immediately to the user's default page
     * -- When not logged in, it shows the login controller
     */
    public function action_Entrance(MVC_Request $rq) {
        if (MVC_Core::$ss->user)
            $this->redirect_After_Login();
        else {
            // Attempt to detect if the cake is in the fridge.
            if ($this->user->check_The_Cake()) {
                $this->evan->add(MVC_NLS::get_Object("evan.user.autologon", true), "notify", array(
                    'permanent' => true,
                    'timeout' => time() + 15
                ));
                $this->redirect_After_Login();
            } else
                $this->action_Login($rq);
        }
    }

    /**
     * Authenticate the user via JSON
     */
    public function action_Authenticate(MVC_Request $post) {
        // required fields
        if (!$post->username || !$post->password)
            return $this->send_Fail_JSON("Please fill both fields");

        // attempt to login
        $access_test = $this->user->test_Access($post->username, $post->password, null, $post->otp);

        // figure out response
        $response = array(
            'success' => false,
            'redirect' => false,
            'reason' => "<b>Wrong username or password</b> Warning: Multiple failed attempts will block your IP address !"
        );
        switch ($access_test['status']) {
            case 'twofa':
                // Ask for the OTP then try again.
                $response = array('twofa' => true);
                break;

            // Normal status which allows the user to log in
            case 'admin':
            case 'supervisor':
            case 'jumper':
            case 'active':
            case 'invisible':
            case 'readonly':  //publication read only IWI
            case 'online':
                // Reset EVAN, any messages from outside are discarded
                $this->evan->reset();
                $login_result = $this->user->do_Login((int) $access_test['id'], ($post->remember == "true") ? $access_test['security'] : null);
                if ($login_result === true)
                    $response = array('success' => true);
                else
                    $response = array(
                        'success' => false,
                        'redirect' => true,
                        'reason' => $login_result
                    );
                break;

            case 'eula':
                // Save the ID and Security into session, they will be used when the user confirms the EULA
                MVC_Core::$ss->eula_check = $access_test;
                $response = array(
                    'success' => false,
                    'eula' => true
                );
                break;

            case 'disabled':
                $response = array(
                    'success' => false,
                    'redirect' => true,
                    'reason' => "We are sorry to inform you that your account has been disabled."
                );
                break;

            case 'ipdeny':
                $response = array(
                    'success' => false,
                    'redirect' => true,
                    'reason' => "While your account is ok, the IP address you're attempting to log-in from has been blacklisted. Sorry for that !"
                );
                break;

            case 'online_deny':
                $response = array(
                    'success' => false,
                    'redirect' => false,
                    'reason' => "Sorry, not allowing multiple logins for the same user !\nPlease check your other devices, you might be logged in on one of them."
                );
                break;

            case 'otp_fail':
                $response = array(
                    'success' => false,
                    'redirect' => false,
                    'reason' => "The PIN is incorrect, please try again or contact support@cargohub.nl"
                );
                break;
             
                
        }

        // send response and stop execution
        $this->send_JSON($response);
    }

//===== Password Reset/Change procedure =====//

    /**
     * Password Reset Form
     */
    public function action_Reset_Password() {
        // Already logged in ? Just go into the user's default controller
        if (MVC_Core::$ss->user)
            $this->redirect_After_Login();

        // Display forgot_main modules
        $this->CFG['components'] = array("modal");
        $this->CFG['modules'][] = "user/_access_pwreset";
        $this->CFG['jqwidgets'][] = array("validator");
        $this->CFG['xjs'] = array(
            'jqform' => true
        );

        $this->prepare_Header("Reset password", array("userbar", "evan"));
        $this->_out['header']['rightmenu'] = array(
            array(
                'link' => "?action=login",
                'text' => "Nevermind, I remembered it!"
            )
        );

        // which body template to use, and run the view
        $this->site_section = "user" . DS . "access_pwreset.html";
        if (!empty(MVC_Core::$ss->domain_CFG['html_settings']['embedded']))
            $this->view("public_embedded.html");
        else
            $this->view("public.html");
    }

    /**
     * Sends the password reset code if the e-mail provided is correct (AJAX function)
     *
     * @param MVC_Request $rq
     */
    public function action_Send_PWReset_Code(MVC_Request $rq) {
        $user_id = $this->user->locate_Unique($rq->email, "email");
        if ($user_id === false) {
//            $this->evan->add(sprintf("The password reset code has been sent to <b>%s</b>. Please check your e-mail inbox !", trim($rq->email)), 'send');
            $this->evan->add(sprintf("The password reset code can not been sent to <b>%s</b>. Email not exist !", trim($rq->email)), 'send');
            $this->send_JSON(array('success' => true, 'redirect' => "?action=login"));
        } else {
            // load the user data for the specified e-mail
            $user = $this->user->read($user_id, array('user', 'contact'));

//            IWI security for changing admin password from login page start
            if($user['status']=='admin'){
               $this->evan->add(sprintf("The password reset code can not been sent to <b>%s</b>. Please contact with admin!", trim($rq->email)), 'send');
            $this->send_JSON(array('success' => true, 'redirect' => "?action=reset_password"));
            }else{
                $mailData['name'] = $user['name'];
            $mailData['email'] = $rq->email;
            $mailData['user_id'] =  $user['id'];
            $mailData['username'] =  $user['user'];
            $this->action_Send_auto_generated_password($mailData);
            }
//            IWI security for changing admin password from login page end

            /*

              //        print_r($user);exit();
              // generate the unique "pwreset" id for the current user
              $user_update = array(
              'pwreset' => sha1(microtime(1) . uniqid())
              );
              $this->user->update($user_id, $user_update);
              $user['pwreset'] = $user_update['pwreset'];
              // Add name/e-mail into contact data
              $user['contact']['email'] = $user['email'];
              $user['contact']['name'] = $user['name'];

              $msg_id = $this->comm->create_System_Message($user['contact_id'], "user_password_reset_code.html", $user);
              //            print_r($msg_id);exit();
              if (!$msg_id)
              return header("HTTP/1.0 500 " . MVC_NLS::get_Object("fail.sysmsg.msgrecord", true));

              $r = $this->comm->email_Message($msg_id, null, null, true, true); // true = standalone message, no conversation.
              if (!$r)
              return header("HTTP/1.0 500 " . MVC_NLS::get_Object("fail.sysmsg.emailsend", true));

              $this->evan->add(sprintf("The password reset code has been sent to <b>%s</b>. Please check your e-mail inbox !", trim($rq->email)), 'send');
              $this->send_JSON(array('success' => true, 'redirect' => "?action=login"));
             * 
             */
        }
    }

    
    
    public function action_Send_auto_generated_password($mailData) {
        $user_id = trim($mailData['user_id']);
        $username = trim($mailData['username']);
        $new_pass = $this->user->generate_Random_Password();

        $s_pass = $this->user->getSequrePass($username, $new_pass);
        $to = $mailData['email'];
        $new_pass_e = $this->user->return_encryp_password($to, $new_pass);
        $qrr = "UPDATE `users` SET security='$s_pass' WHERE id='$user_id' ";
        MVC_Core::$db->query($qrr);
        $subject = "Auto Generated Password";
        $mailData = array(
            'user_name' => $mailData['name'],
            'username' => $mailData['username'],
            'contact_email' => $to,
            'password' => $new_pass,
        );
        $template = $this->action_Get_mail_template($mailData);
        $m_comm = new App_Model_Comm();
        $content = $template;
        $r = $m_comm->send_custom_email($to, $mailData['name'], $content, $subject); // true = standalone message, no conversation.

        if ($r) {
            $this->evan->add(sprintf("The password has been sent to <b>%s</b>. Please check your e-mail inbox !", trim($rq->email)), 'send');
            $this->send_JSON(array('success' => true, 'redirect' => "?action=login"));
        } else {
            $this->evan->add(sprintf("Opps, please try again!", trim($rq->email)), 'send');
            $this->send_JSON(array('success' => true, 'redirect' => "?action=login"));
        }
    }
    

    public function action_Get_mail_template($data) {
        $msg = '';
        $msg .= "<div style='max-width: 750px; margin: 30px auto; border: 1px solid lightgray; padding: 20px; border-radius: 10px; background-color: #eee;'>
    <h2>Dear " . $data['user_name'] . ",</h2>
    <p>This message was sent as the result of a <b>password reset procedure:</p>
    <p >Your Username is : " . $data['username'] . "</p>
    <p >Your new auto generated password is : " . $data['password'] . "</p>

<!--    <div style=\"padding: 40px; text-align: center; font-size: 1.5em;\">
            <a href=\"https://www.cargohub.aero/access?action=update_password&amp;email=" . $data['contact_email'] . "&amp;code=" . $data['password'] . "\" style=\"display: block; border-radius: 7px; background-color: #589acc; width: 380px; margin: 0 auto; padding: 12px 0; text-decoration: none; color: white;\">Click here to reset your password</a>
    </div>-->


    <p>If you did not request this, please contact <a href='mailto:support@cargohub.nl'>support@cargohub.nl</a><br />
    If the reset link is not followed, your account will continue to function normally.</p>

            <hr style=\"background-color: lightgray; border: none; height: 1px; margin: 5px 0 9px;\" />
    <h4 style='text-align: center;'>Thank you for using Cargohub Connect !</h4>
    </div>";

        return $msg;
    }

    /**
     * This should normally be called when the user is clicking a link in the e-mail about password reset
     *
     * @param MVC_Request $rq
     */
    public function action_Update_Password(MVC_Request $rq) {
        if (!$rq->code || !$rq->email) {
            $this->evan->add("Incorrect parameters. This function only works with a link that was provided by E-mail.", 'error');
            return $this->redirect("?action=login");
        }

        $user_id = $this->user->locate_Unique($rq->code, "pwreset");
        if ($user_id === false) {
            $this->evan->add("Invalid password reset code. Perhaps the password was already changed?", 'error');
            return $this->redirect("?action=login");
        }

        // load the user data for the specified e-mail
        $user = $this->user->read($user_id, array('user', 'contact'));
        if ($user['email'] != $rq->email) {
            $this->evan->add("The password reset code does not match the User E-mail. Please contact support@cargohub.nl", 'error');
            return $this->redirect("?action=login");
        }

        // pwreset code was found, matches the user's e-mail - Present the password reset form:
        // Display forgot_main modules
        $this->CFG['components'] = array("modal");
        $this->CFG['modules'][] = "user/_access_pwupdate";
        $this->CFG['jqwidgets'][] = array("validator");
        $this->CFG['xjs'] = array(
            'jqform' => true,
            'pwstrength' => true
        );

        $this->prepare_Header("Change password", array("userbar", "evan"));
        $this->_out['header']['rightmenu'] = array(
            array(
                'link' => "?action=login",
                'text' => "Nevermind, I remembered it!"
            )
        );

        $this->username = $user['user'];
        $this->email = $user['email'];
        $this->code = $rq->code;

        // which body template to use, and run the view
        $this->site_section = "user" . DS . "access_pwupdate.html";
        if (!empty(MVC_Core::$ss->domain_CFG['html_settings']['embedded']))
            $this->view("public_embedded.html");
        else
            $this->view("public.html");

        /*
          $user_update = array(
          'user'=> $user['user'],
          'password'=> $this-> user-> generate_Random_Password(),
          'pwreset'=> null // The code is no longer available after the password was reset
          );
          $this-> user-> update($user_id, $user_update);

          $user['password'] = $user_update['password'];
          // Add name/e-mail into contact data
          $user['contact']['email'] = $user['email'];
          $user['contact']['name'] = $user['name'];

          $msg_id = $this-> comm-> create_System_Message($user['contact_id'], "user_password_generated.html", $user);

          if(!$msg_id) {
          $this-> evan-> add(MVC_NLS::get_Object("fail.sysmsg.msgrecord", true), 'error');
          $this-> redirect("?action=login");
          }

          $r = $this-> comm-> email_Message($msg_id, null, "email". DS. "comm_sendmail_default.css", null, true); // true = standalone message, no conversation.
          if(!$r)
          $this-> evan-> add(MVC_NLS::get_Object("fail.sysmsg.emailsend", true), 'error');
          else
          $this-> evan-> add(sprintf("Your new password has been sent to <b>%s</b>. Please check your e-mail inbox !", $user['email']), 'send');

          $this-> redirect("?action=login");
         */
    }

    /**
     * This should normally be called when the user is clicking a link in the e-mail about Auth reset
     *
     * @param MVC_Request $rq
     *
     * @throws Exception
     */
     
     
    public function action_Reset_Auth(MVC_Request $rq) {
        if (!$rq->code || !$rq->email) {
            $this->evan->add("Incorrect parameters. This function only works with a link that was provided by E-mail.", 'error');
            return $this->redirect("?action=login");
        }

        $user_id = $this->user->locate_Unique($rq->code, "pwreset");
        if ($user_id === false) {
            $this->evan->add("Invalid password reset code. Perhaps the password was already changed?", 'error');
            return $this->redirect("?action=login");
        }

        if ($this->user->resetTwoFactorAuth($user_id))
            $this->evan->add("Two-Factor Authentication reset done", 'success');
        else
            $this->evan->add("Two-Factor Authentication reset failed!", 'error');

        return $this->redirect("?action=login");
    }

    /**
     * Sets the new password for the user
     * @param MVC_Request $rq
     */
    public function action_Set_PWNew(MVC_Request $rq) {
        // First, verify E-mail and code AGAIN.
        if (!$rq->code || !$rq->email)
            return header("HTTP/1.0 400 Reset code and E-mail were not present in the request");

        $user_id = $this->user->locate_Unique($rq->code, "pwreset");
        if ($user_id === false)
            return header("HTTP/1.0 404 Invalid password reset code. Perhaps the password was already chanded?");

        // load the user data for the specified e-mail
        $user = $this->user->read($user_id, array('user', 'contact'));
        if ($user['email'] != $rq->email)
            return header("HTTP/1.0 403 The password reset code doesn't match the User E-mail. Please contact support@cargohub.nl");

        // All good, code found, e-mail matches. Update the user

        $user_update = array(
            'user' => $user['user'],
            'password' => $rq->password,
            'pwreset' => null // The code is no longer available after the password is changed
        );

        $this->user->update($user_id, $user_update);

        $msg_id = $this->comm->create_System_Message($user['contact_id'], "user_password_changed.html", $user);

        if (!$msg_id) {
            $this->evan->add(MVC_NLS::get_Object("fail.sysmsg.msgrecord", true), 'error');
            $this->redirect("?action=login");
        }

        $r = $this->comm->email_Message($msg_id, null, null, true, true); // true = standalone message, no conversation.
        if (!$r)
            return header("HTTP/1.0 500 " . MVC_NLS::get_Object("fail.sysmsg.emailsend", true));

        $this->evan->add("Your password has been changed ! Proceed to login", 'send');
        $this->send_JSON(array('success' => true, 'redirect' => "https://www.cargohub.aero/access?action=login"));
    }

    /**
     * Get recent updates from admin center
     */
    public function action_Recent_Updates(MVC_Request $rq) {
        $m_con = new App_Model_Cms();
        try {
            $result = $m_con->fetch_RecentUpdates($rq->day, $rq->limit);
        } catch (Exception $e) {
            
        }
        $this->send_JSON(array('success' => true, 'result' => $result));
    }

    /**
     * @return bool|MVC_Database_Result|mysqli_result
     * @throws Exception
     */
    public function action_Process_Tasks() {
        $m_task = new App_Model_Task();
        return $m_task->check_Inactive_Users_Task();
    }

    /**
     * @return bool|MVC_Database_Result|mysqli_result
     * @throws Exception
     */
    public function action_Process_Ovd_Tasks() {
        $m_task = new App_Model_Task();
        return $m_task->check_Ovd_task();
    }

}
