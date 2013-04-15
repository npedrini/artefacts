<?php

include "../config//config.php";

$mysqli = new mysqli( DB_HOST, DB_USER, DB_PASS );
$mysqli->select_db( DB_NAME );

$result = $mysqli->query( "SELECT DISTINCT(occur_date) FROM `dreams` ORDER BY occur_date DESC" );

$dates = array();

$date_format = DATE_FORMAT;
$date_format = preg_replace( '/{{date}}/', 'j', $date_format );
$date_format = preg_replace( '/{{month}}/', 'n', $date_format );
$date_format = preg_replace( '/{{year}}/', 'Y', $date_format );

if( $mysqli->affected_rows > 0 )
{
	while( $dream = $result->fetch_assoc() )
	{
		$date = DateTime::createFromFormat( 'Y-m-d', $dream['occur_date'], new DateTimeZone('Australia/Melbourne') ); 
		
		$dates[] = $date->format( $date_format );
	}
}

echo json_encode( array('dates'=>$dates) );
?>