<?php
$to      = 'vincent@ixlhosting.nl';
$subject = 'test subject';
$message = 'hello';
$headers = array(
    'From' => 'info@cargohub.aero',
    'Reply-To' => 'info@cargohub.aero',
    'X-Mailer' => 'PHP/' . phpversion()
);

mail($to, $subject, $message, $headers);
?>

