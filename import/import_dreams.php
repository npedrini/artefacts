<?php
set_include_path( "../" );

include "config/" . getenv('HTTP_APPLICATION_ENVIRONMENT') . "/config.php";
include "includes/dream.class.php";

$mysqli = new mysqli( DB_HOST, DB_USER, DB_PASS );
$mysqli->select_db( DB_NAME );

$mysqli->query("TRUNCATE TABLE `users`");		//	empty users table
$mysqli->query("TRUNCATE TABLE `tags`");		//	empty tags table
$mysqli->query("TRUNCATE TABLE `dreams`");		//	empty dreams table
$mysqli->query("TRUNCATE TABLE `dream_tags`");	//	empty dream_tags table

echo "<pre>";

$tags_inserted_total = 0;
$tags_shared_total = 0;
$tags_total = 0;

$successes = 0;
$errors = 0;

if ( ($handle = fopen("../dummy_data/dreams.csv", "r")) !== FALSE ) 
{
	$date_format = DATE_FORMAT;
	$date_format = preg_replace( "/{{date}}/", "j", $date_format );
	$date_format = preg_replace( "/{{month}}/", "n", $date_format );
	$date_format = preg_replace( "/{{year}}/", "Y", $date_format );

	$line = 0;
	
    while ( ($data = fgetcsv($handle, 1000, ",") ) !== FALSE ) 
    {
    	//	skip header
    	if( $line == 0 ) 
    	{
    		$line++;
    		continue;
    	}

		$dream = new Dream();
		$dream->age = 1;
		$dream->alchemyApiKey = ALCHEMY_API_KEY;
		$dream->dateFormat = $date_format;
		$dream->origin = "mona";
		$dream->postToTumblr = POST_TO_TUMBLR;
		$dream->timezone = TIME_ZONE;
		$dream->tumblrPostEmail = TUMBLR_POST_EMAIL;

		$dream->date = array_shift($data);
        $dream->description = array_shift($data);
        $dream->color = array_shift($data);
		$dream->email = array_shift($data);
		
		$tags = array();

		foreach($raw_tags as $tag)
		{
			if( empty($tag) ) continue;
			
			$tags[] = $tag;
		}

		$dream->tags = $tags;
		
		if( $dream->save() )
			$successes++;
		else
			$errors++;
        
        $line++;
    }
    
    fclose($handle);
}
else
{
	echo "Problem reading file";
}
echo "<br/><br/>";
echo $successes . " dreams inserted successfully, " . $errors . " errors";

?>