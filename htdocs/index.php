<?php

ini_set('max_execution_time', 30000); // 120 (seconds) = 2 Minutes
ini_set('max_input_time', 30000);
//phpinfo();
//exit();

// When uploading to IXL, change it
date_default_timezone_set("Europe/Amsterdam"); // Europe/Amsterdam ; Europe/Bucharest

/*
if(!in_array($_SERVER['REMOTE_ADDR'], array("192.168.1.200", "127.0.0.1"))) {
	echo '<div style="text-align: center;"><iframe width="1000" height="562" src="https://www.youtube.com/embed/ARJ8cAGm6JE" frameborder="0" allowfullscreen></iframe></div>';
	die;
}
*/

$maintenance = false;
if($maintenance && !in_array($_SERVER['REMOTE_ADDR'], array("83.86.188.191", "86.121.137.156", "83.81.213.3", "86.87.225.26"))) { // , "87.210.58.63","213.73.173.0"
?>
<html>
<head>
<title>SYSTEM UPGRADE</title>
<style>
	body {
		font-family: Verdana, Geneva, Arial, Helvetica, sans-serif;
		font-size: 12px;
		color: black;
		background: url("/img/bgs/gray_patt4.png");
	}
	.midscreen {
		text-align: center;
		position: absolute;
		top: 50%;
		left: 50%;
		width: 100%;
		height: 250px;
		margin-left: -50%;
		margin-top: -125px;
	}
	hr {
		background-color: gray;
		border: none;
		height: 1px;
		margin: 5px 0px 9px 0px;
	}
</style>
</head>
<body>
	<div class="midscreen">
		<img src="/img/cargohub.png" alt="CargoHub Connect" />
		<h2>SYSTEM UPGRADE</h2>
		<hr />
		<p>
			<b>CargoHub Connect</b> has been temporarely turned off for system upgrade and maintenance.<br />
			The update has started at 22:30 UTC and should be completed within several hours<br /><br />
			For more information, please contact us at: <a href="mailto:tech@cargohub.nl">tech@cargohub.nl</a>
		</p>
		<h3>We are sorry for the inconvenience</h3>
	</div>
</body>
</html>
<?php
die;
}

// Setup primary configuration ( where application is located, system type - Linux/Windows, which modifies the DS )
define('DS', DIRECTORY_SEPARATOR);
define('ROOT', dirname(dirname(__FILE__))); // one level above "htdocs"

// Include the MVC Core and init
include_once(ROOT. DS. 'mvc'. DS. 'core.php');
MVC_Core::init("cargoits.settings.yaml");

// T minus 31. You're go for auto-sequence start.
App_Router::launch();