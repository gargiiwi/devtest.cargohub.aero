<?php

define('DS', DIRECTORY_SEPARATOR);
define('ROOT', dirname(dirname(__FILE__))); // one level above "htdocs"

// Include the MVC Core and init

//echo ROOT. DS. 'lib'. DS. 'phpmailer'. DS. 'phpmailer.php';
include_once(ROOT. DS. 'lib'. DS. 'phpmailer'. DS. 'phpmailer.php');

$mail = new PHPMailer();
 
   
 
 try {
    //Server settings
                         // Enable verbose debug output
   // $mail->isSMTP();                                            // Send using SMTP
    $mail->Host       = 'mail.cargohubmailer.aero';                    // Set the SMTP server to send through
    $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
    $mail->Username   = 'aramex.customs@cargohubmailer.aero';                     // SMTP username
    $mail->Password   = '58FBa44tEeN7efYYwWXe';                               // SMTP password
    
    $mail->Port       = 587;                                    // TCP port to connect to, use 465 for `PHPMailer::ENCRYPTION_SMTPS` above
 $mail->Mailer = "mail";
$mail->SMTPSecure = 'tls';
    //Recipients
    $mail->setFrom('aramex.customs@cargohubmailer.aero', 'Customs Aramex NL');
    $mail->addAddress('tech@cargohub.nl', 'Anirban');     // Add a recipient
    
    // Attachments
    
    // Content
    $mail->isHTML(true);                                  // Set email format to HTML
    $mail->Subject = 'Here is the subject';
    $mail->Body    = 'This is the HTML message body <b>in bold!</b>';
    $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';
   // var_dump($mail);
    
    $mail->send();
    
     print("<pre>".print_r($mail,true)."</pre>");
    echo 'Message has been sent';
} catch (Exception $e) {
    echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
}



 

 
?>


