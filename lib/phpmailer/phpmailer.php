<?php

require_once __DIR__ . '/src/Exception.php';
require_once __DIR__ . '/src/PHPMailer.php';
require_once __DIR__ . '/src/SMTP.php';

if (!class_exists('PHPMailer', false)) {
    class_alias(
        \PHPMailer\PHPMailer\PHPMailer::class,
        'PHPMailer'
    );
}

if (!class_exists('phpmailerException', false)) {
    class_alias(
        \PHPMailer\PHPMailer\Exception::class,
        'phpmailerException'
    );
}