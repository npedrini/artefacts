<?php 
set_include_path("../");

include_once "config/config.php";
include_once "includes/db.class.php";

session_start();

date_default_timezone_set(TIME_ZONE);

$resource = isset($_GET['resource'])?$_GET['resource']:null;
$resourceId = isset($_GET['resourceId'])?$_GET['resourceId']:null;
$modifier = isset($_GET['modifier'])?$_GET['modifier']:null;

$response = (object)array("success"=>1);

$items = array();

//	set up date format
$date_format = DATE_FORMAT;
$date_format = preg_replace( '/{{date}}/', 'j', $date_format );
$date_format = preg_replace( '/{{month}}/', 'n', $date_format );
$date_format = preg_replace( '/{{year}}/', 'Y', $date_format );

// 	set default date to yesterday in australia
$date = new DateTime( 'now', new DateTimeZone( TIME_ZONE ) );
$date->sub( new DateInterval("P01D") );

switch( $resource )
{
	case "dream":
		
		include_once "includes/dream.class.php";
		
		switch( $_SERVER['REQUEST_METHOD'] )
		{
			case "POST":
			
			$dream = new Dream($resourceId);
			$dream->alchemyApiKey = ALCHEMY_API_KEY;
			$dream->dateFormat = $date_format;
			$dream->origin = isset($_SESSION['origin'])?$_SESSION['origin']:null;
			$dream->postToTumblr = POST_TO_TUMBLR;
			$dream->timezone = TIME_ZONE;
			$dream->tumblrPostEmail = TUMBLR_POST_EMAIL;
			
			//	set values to what user submitted in case there are errors
			$dream->setValues( $_POST, isset($_FILES['image_file'])?$_FILES['image_file']:null, isset($_FILES['audio_file'])?$_FILES['audio_file']:null );
			
			//	save
			$success = $dream->save();
			
			if( !$success )
			{
				$response->success = 0;
				$response->error = $dream->status;
				$response->errors = $dream->getLog();
			}
			else
			{
				$response->result = $dream;
			}
			
			break;
		}
		
		break;
		
	case "graph":
	
		switch( $_SERVER['REQUEST_METHOD'] )
		{
			case "GET":
			
			include_once "includes/graph.class.json.php";
			
			if( !isset( $_GET['date_from'] ) ) die( );
			
			$dateFrom = DateTime::createFromFormat( $date_format, $_GET['date_from'], new DateTimeZone(TIME_ZONE) );
			
			$graph = new Graph();
			$graph->alchemyApiKey = ALCHEMY_API_KEY;
			$graph->dateFrom = $dateFrom->format('Y-m-d');
			$graph->maxDreams = MAX_DISPLAYED_DREAMS;
			$graph->maxKeywords = 30;
			$graph->minTagValue = MIN_TAG_VALUE;
			if( isset($_SESSION[origin]) ) 
			{
  				$graph->origin = $_SESSION[origin];
			}
			
			if( isset($_GET['date_to']) )
			{
				$dateTo = DateTime::createFromFormat( $date_format, $_GET['date_to'], new DateTimeZone(TIME_ZONE) );
				$graph->dateTo = $dateTo->format('Y-m-d');
			}
			
			$graph->build();
			
			$data = $graph->render();
			$data->date_from=date($date_format,strtotime($graph->dateFrom));
			$data->date_to=date($date_format,strtotime($graph->dateTo));
			
			$response->result = $data;
			
			if( isset($_SESSION['submission'])
				&& $_SESSION['submission'] == 1 )
			{
				$highlightColor = "#cc3300";
			
				$dream = (object)array('id'=>'-1', 'user_id'=>'-1', 'description'=>'Your dream here', 'color'=>$highlightColor, 'color2'=>$highlightColor, 'index'=>$graph->getNodeCount(), 'interactive'=>false, 'node_type'=>'dream', 'tags'=>array(), 'value'=>0);
			
				$dreams[] = $dream;
				$nodes[] = $dream;
			
				unset( $_SESSION['submission'] );
			}
			
			break;
		}
		
		break;
		
	case "dates":
		
		switch( $_SERVER['REQUEST_METHOD'] )
		{
			case "GET":
				
				$db = new Database();
				
				$result = $db->query( "SELECT DISTINCT(occur_date) FROM `dreams` ORDER BY occur_date DESC" );
				
				if( $db->affected_rows > 0 )
				{
					while( $dream = $result->fetch_assoc() )
					{
						$date = DateTime::createFromFormat( 'Y-m-d', $dream['occur_date'], new DateTimeZone('Australia/Melbourne') );
				
						$items[] = $date->format( $date_format );
					}
				}
				
				break;
		}
		
		break;
		
}

if( isset($items) && count($items) )
	$response->results = $items;

echo utf8_encode(json_encode($response));
?>
