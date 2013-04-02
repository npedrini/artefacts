<?php
session_start();

include_once "../includes/graph.class.json.php";
include_once '../config/' . getenv('HTTP_APPLICATION_ENVIRONMENT') . "/config.php";

$date_format = DATE_FORMAT;
$date_format = preg_replace( '/{{date}}/', 'j', $date_format );
$date_format = preg_replace( '/{{month}}/', 'n', $date_format );
$date_format = preg_replace( '/{{year}}/', 'Y', $date_format );

if( !isset( $_GET['date'] ) ) die( );

$date = DateTime::createFromFormat( $date_format, $_GET['date'], new DateTimeZone(TIME_ZONE) );

$graph = new Graph();
$graph->dateFrom = $date->format('Y-m-d');
$graph->dateTo = $date->format('Y-m-d');
$graph->minTagValue = 2;
$graph->build();

echo $graph->render();

if( isset($_SESSION['submission'])
	&& $_SESSION['submission'] == 1 )
{
	$dream = (object)array('id'=>'-1', 'user_id'=>'-1', 'description'=>'Your dream here', 'color'=>$graph->highlightColor, 'color2'=>$graph->highlightColor, 'index'=>$graph->getNodeCount(), 'interactive'=>false, 'node_type'=>'dream', 'tags'=>array(), 'value'=>0);
	
	$dreams[] = $dream;
	$nodes[] = $dream;
	
	unset( $_SESSION['submission'] );
}
?>