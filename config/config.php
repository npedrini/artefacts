<?php

define("ADMIN_EMAIL","dpatman69@gmail.com");

define("THEME", isset($_COOKIE['theme']) ? ($_COOKIE['theme']==1?'black':'white') : 'black');

define("DATE_FORMAT","{{date}}/{{month}}/{{year}}");
define("TIME_ZONE",'Australia/Melbourne');

define("IMAGE_PATH",'images/dreams/');

define("ALCHEMY_API_KEY",'b66fcdf41275b600e1f08eae25f6498e55fce606');

define("DB_HOST","localhost");
define('DB_NAME',"artefacts");
define("DB_USER","");
define("DB_PASS","");

/*	submitted dreams will be posted to TUMBLR_POST_EMAIL if true	*/
define("POST_TO_TUMBLR",true);
define("TUMBLR_POST_EMAIL","dgsnreawd6icq@tumblr.com");

define("MIN_TAG_VALUE",2);

define("DEBUG",true);

ini_set('error_reporting', 1);
?>