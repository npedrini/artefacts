<?php
error_reporting(E_ALL);

set_include_path( "../" );

include_once "config/config.php";
include_once "includes/db.class.php";
include_once "includes/dream.class.php";

header( 'Content-type: text/html; charset=utf-8' );

set_time_limit(300);
ob_implicit_flush(true);

$time_start = microtime(true);
$log = false;

$db = new Database();

$delete = isset($_GET['delete']) && $_GET['delete']=='1' ? true : false;

if( $delete )
{
	$db->query("TRUNCATE TABLE `users`");		//	empty users table
	$db->query("TRUNCATE TABLE `tags`");		//	empty tags table
	$db->query("TRUNCATE TABLE `dreams`");		//	empty dreams table
	$db->query("TRUNCATE TABLE `dream_tags`");	//	empty dream_tags table
	$db->query("TRUNCATE TABLE `media`");		//	empty media table
}

echo "<pre>";

$tags_inserted_total = 0;
$tags_shared_total = 0;
$tags_total = 0;

$successes = 0;
$errors = 0;

if ( ($handle = fopen("../dummy_data/dreambank_dreams.csv", "r")) !== FALSE ) 
{
	$date_format = 'j-M-Y';
	$line = 0;
	
	$start = isset($_GET['start']) ? (int)$_GET['start'] : 1;
	$size = isset($_GET['size']) ? (int)$_GET['size'] : -1;
	
	if( $log ) error_log("\r\rstart=".$start.", size=".$size.", delete=".$delete."\r",3,"import_log");
	
	$valid = true;
	
    while ( ($data = fgetcsv($handle) ) !== FALSE && $valid ) 
    {
    	//	skip header
    	if( $line == 0 ) 
    	{
    		$line++;
    		continue;
    	}
    	
    	if( $line < $start )
    	{
    		$line++;
    		continue;
    	}
    	
    	if( $size>-1 && $line > $start + $size ) 
    	{
    		$valid = false;
    		continue;
    	}
    	
    	//"Date","Title","Text","User","Age","Gender","Location"
		$dream = new Dream(null,$db);
		$dream->alchemyApiKey = ALCHEMY_API_KEY;
		$dream->useAlchemy = true;
		$dream->dateFormat = $date_format;
		$dream->origin = "dreambank";
		$dream->timezone = TIME_ZONE;
		$dream->tumblrPostEmail = TUMBLR_POST_EMAIL;
		$dream->postToTumblr = false;
		$dream->email = "go@looklisten.net";
		$dream->date = $data[0];
        $dream->title = $data[1];
		$dream->description = $data[2];
        $dream->age = $data[4];
		$dream->gender = strtolower($data[5])=="f"?"female":"male";
		$dream->country = $data[6];
		
		if( $dream->save() )
		{
			$successes++;
		}
		else
		{
			$errors++;
		}
		
		$output = $line." - ".implode("; ",$dream->getLog())."\r";
		
		echo $output;
		
		flush();
		ob_flush();
		
		if( $log ) error_log($output,3,"import_log");
		
        $line++;
    }
    
    fclose($handle);
}
else
{
	echo "Problem reading file";
}
echo "</pre>";

$time_end = microtime(true);

echo "<br/><br/>";
echo $successes . " dreams inserted successfully, " . $errors . " errors";
echo "<br/><br/>";
echo ($time_end - $time_start) . " seconds";
?>