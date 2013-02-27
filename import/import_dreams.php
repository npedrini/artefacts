<?php
include '../config/' . getenv('HTTP_APPLICATION_ENVIRONMENT') . "/config.php";

ini_set('error_reporting', E_ALL);

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

if ( ($handle = fopen("../dummy_data/dreams.csv", "r")) !== FALSE ) 
{
	$line = 0;
	
    while ( ($data = fgetcsv($handle, 1000, ",") ) !== FALSE ) 
    {
    	//	skip header
    	if( $line == 0 ) 
    	{
    		$line++;
    		continue;
    	}
        
        $description = $mysqli->real_escape_string( array_shift($data) );
        $color = array_pop($data);
        $feeling = array_pop($data);
        $raw_tags = $data;
        
        $date = new DateTime( 'now', new DateTimeZone('Australia/Melbourne') );
        
        //	get user
        $user_email = $mysqli->real_escape_string( "go@looklisten.net" );	//	TODO: get from dream record
       	
        $sql = "SELECT id FROM `users` WHERE email='".$user_email."'";
        $result = $mysqli->query( $sql );
        
        if( $mysqli->affected_rows > 0 )
        {
        	$user = $result->fetch_assoc();
        	$user_id = $user['id'];
        }
        else
        {
        	$sql = "INSERT INTO `users` (email) VALUES ('".$user_email."')";
        	$result = $mysqli->query( $sql );
        	$user_id = $mysqli->insert_id;
        }
        
       	if( !isset($user_id) || $user_id == null ) 
       		$user_id = 0;
        
        $sql = "INSERT INTO `dreams` (user_id,description,occur_date) VALUES ('".$user_id."','".$description."','".$date->format('Y-m-d')."')";
        $result = $mysqli->query( $sql );
        
        if( $result != null ) 
        {
        	$dream_id = $mysqli->insert_id;
        	
        	$tags_inserted = 0;
        	$tags = array();
        	
    		foreach($raw_tags as $tag)
			{
				if( empty($tag) ) continue;
				if( $tag == "mona" ) continue;
				
				$tags[] = $tag;
				
				$tag = strtolower( $tag );
				$tag = $mysqli->real_escape_string( $tag );
				
				//	get tag_id
				$sql = "SELECT id FROM `tags` WHERE tag='".$tag."'";
        		$result = $mysqli->query( $sql );
        		
        		if( $mysqli->affected_rows > 0 )	//	tag exists
				{
					$tag_row = $result->fetch_assoc();
					$tag_id = $tag_row['id'];
					
					$tags_shared_total++;
				}
				else								//	tag does not exist
				{
					$sql = "INSERT INTO `tags` (tag) VALUES ('".$tag."')";
					$result = $mysqli->query( $sql );
					$tag_id = $mysqli->insert_id;
					
					$tags_inserted++;
					$tags_inserted_total++;
				}
				
				if( !$tag_id ) die('tag id not found for tag '. $tag);
				
				$sql = "INSERT INTO `dream_tags` (dream_id,tag_id) VALUES ('".$dream_id."','".$tag_id."')";
        		$result = $mysqli->query( $sql );
			}
			
			echo "dream inserted...";
			echo $tags_inserted . "/" . count($tags) . " tags inserted";
			echo "<br/>";
        	
        	$tags_total += count($tags);
    	}
        
        $line++;
    }
    
    fclose($handle);
}
else
{
	echo "Problem reading file";
}
echo "<br/><br/>";
echo $tags_total . " total tags (" . $tags_inserted_total . " inserted, " . $tags_shared_total . " shared)<br/>";
?>