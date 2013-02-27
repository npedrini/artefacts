<?php
include 'config/' . getenv('HTTP_APPLICATION_ENVIRONMENT') . "/config.php";

$mysqli = new mysqli( DB_HOST, DB_USER, DB_PASS );
$mysqli->select_db( DB_NAME );

$result = $mysqli->query( "SELECT tag FROM `tags` ORDER BY tag" );

$tags = array();

while( $row = $result->fetch_assoc() )
{
	$tags[] = $row['tag'];
}

echo json_encode( $tags );
?>