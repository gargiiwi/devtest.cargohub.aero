<?php
define('DS', DIRECTORY_SEPARATOR);
define('ROOT', dirname(dirname(__FILE__))); // one level above "htdocs"
readfile(ROOT. DS. "warehouse_mount". DS. $_GET['path']);