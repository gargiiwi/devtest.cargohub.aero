<?php
// Sleep a random amount of time before executing - This is done to prevent "cron" from running exactly at the same moment of time resulting in the same data being processed twice
sleep(rand(5, 55));

date_default_timezone_set("Europe/Amsterdam");

// Setup primary configuration ( where application is located, system type - Linux/Windows, which modifies the DS )
define('DS', DIRECTORY_SEPARATOR);
define('ROOT', dirname(dirname(__FILE__))); // one level above "htdocs"

// Include the MVC Core and init
include_once(ROOT. DS. 'mvc'. DS. 'core.php');
MVC_Core::init("cargoits.settings.yaml");
MVC_NLS::set_Language("English");

// "Cogito, ergo sum"
App_Skynet::consciousness();