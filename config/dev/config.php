<?php
define("DEBUG",true);
DEFINE("THEME", isset($_COOKIE['theme']) ? ($_COOKIE['theme']==1?'black':'white') : 'black');
define("DATE_FORMAT","{{date}}/{{month}}/{{year}}");
define("IMAGE_PATH",'images/artworks/');
define("TIME_ZONE",'Australia/Melbourne');
define("ALCHEMY_API_KEY",'b66fcdf41275b600e1f08eae25f6498e55fce606');

define("DB_HOST","localhost");
define("DB_USER","root");
define("DB_PASS","Adm1nHats1lu!");
define('DB_NAME','artefacts');

ini_set('error_reporting', 1);

DEFINE('TEST','X');
?>