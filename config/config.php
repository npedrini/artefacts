<?php

/*	recipient for feedback link in footer	*/
define("ADMIN_EMAIL","");

/*	set theme based on cookie (0=white,1=black)	*/
define("THEME", isset($_COOKIE['theme']) ? ($_COOKIE['theme']==1?'black':'white') : 'black');

/*	display date format	*/
define("DATE_FORMAT","{{date}}/{{month}}/{{year}}");

/*	timezone for dates	*/
define("TIME_ZONE","Australia/Melbourne");

define("IMAGE_PATH","assets/");

define("ALCHEMY_API_KEY","");

define("DB_HOST","localhost");
define('DB_NAME',"artefacts");
define("DB_USER","");
define("DB_PASS","!");

/*	submitted dreams will be posted to address specified in TUMBLR_POST_EMAIL if POST_TO_TUMBLR = true	*/
define("POST_TO_TUMBLR",false);
define("TUMBLR_POST_EMAIL","");

define("MIN_TAG_VALUE",2);
define("MAX_DISPLAYED_DREAMS", 100);

define("EMBEDDED", isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER']!=="http://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'] : false );

define("DEBUG",true);

if( DEBUG )
	error_reporting(E_ALL);
?>