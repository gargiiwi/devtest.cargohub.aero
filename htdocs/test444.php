 
                            
                            <?php
// connect and login to FTP server
$ftp_server = "15.207.45.56";
 $ftp_user_name = "damagereports";
        $ftp_user_pass = "4@m@53~R3p0";
        $ftp_conn = ftp_connect($ftp_server);
$login = ftp_login($ftp_conn, $ftp_user_name, $ftp_user_pass);

$local_file = "tytryt.pdf";
$server_file = "/files/345_Menzies world cargo/11775_369-8959 5214/external_369-8959 5214.pdf";

  //$b = tmpfile();
//$b = fopen($a, 'r+');
$handle = fopen($local_file, 'w');

                            $metaDataPdf = stream_get_meta_data($b);
                            
                            
// download server file
if (ftp_fget($ftp_conn, $handle, $server_file, FTP_BINARY,0))
  {
  echo "Successfully written to $local_file.";
  
  
  if (!copy($local_file, "5556565656.pdf")) {
    echo "failed to copy $file...\n";
}
 

   
  }
else
  {
  echo "Error downloading $server_file.";
  }

// close connection
ftp_close($ftp_conn);
?>
                            
?>