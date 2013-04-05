<?php
session_start();

if( isset($_GET['origin']) )
{
	$_SESSION['origin'] = strtolower( $_GET['origin'] );
}

?>