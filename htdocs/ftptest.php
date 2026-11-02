<?php

// FTP server credentials
$ftp_server = '144.178.79.22';
$ftp_username = 'ag6';
$ftp_password = 'gs5r4rr8';

// Connect to FTP server
$ftp_conn = ftp_connect($ftp_server);
if (!$ftp_conn) {
    die('Could not connect to FTP server');
}

putenv('TMPDIR=/tmp/');


// Login to FTP server
$login = ftp_login($ftp_conn, $ftp_username, $ftp_password);
if (!$login) {
    die('FTP login failed');
}


//ftp_pasv($ftp_conn, true);
// Get the list of files (using ftp_nlist)
$file_list = ftp_nlist($ftp_conn, '/out');
if ($file_list) {
    echo "Files on the FTP server:\n";
    foreach ($file_list as $file) {
        echo "$file\n";
    }
} else {
    echo "Failed to get file list\n";
}

// Close the FTP connection
ftp_close($ftp_conn);
