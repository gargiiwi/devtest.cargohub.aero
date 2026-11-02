<?php
// Load PHPMailer 6.x
require_once __DIR__ . '/src/PHPMailer.php';
require_once __DIR__ . '/src/SMTP.php';
require_once __DIR__ . '/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer as PHPMailer6;
use PHPMailer\PHPMailer\Exception;

// Wrapper class to maintain old code compatibility
class PHPMailer extends PHPMailer6
{
    // You can add any small backward compatibility changes here if needed
}
