<?php
include_once "../config/config.php";

ini_set('error_reporting', E_ALL);
ini_set('max_execution_time',1200);

$mysqli = new mysqli( DB_HOST, DB_USER, DB_PASS );
$mysqli->select_db( DB_NAME );

$mysqli->query("TRUNCATE TABLE `artworks`");		//	empty artworks table
$mysqli->query("TRUNCATE TABLE `artwork_tags`");	//	empty artwork_tags table

$tags_inserted_total = 0;
$tags_shared_total = 0;
$tags_total = 0;

$dir = getcwd()."/../images/artworks/";

if( file_exists( $dir ) )
{
	foreach(scandir($dir) as $file) 
	{
		if ('.' === $file || '..' === $file) continue;
		if (is_dir("$dir/$file")) rmdir_recursive("$dir/$file");
		else unlink("$dir/$file");
	}
}

echo "<pre>";

if ( ($handle = fopen("../dummy_data/artworks.csv", "r")) !== FALSE ) 
{
	$line = 0;
	
    while ( ($data = fgetcsv($handle, 1000, ",") ) !== FALSE ) 
    {
    	/*
    	//	skip header
    	if( $line == 0 ) 
    	{
    		$line++;
    		continue;
    	}
        */
        
        $id = array_shift($data);
        $title = $mysqli->real_escape_string( array_shift($data) );
        $artist = $mysqli->real_escape_string( array_shift($data) );
        $year = $mysqli->real_escape_string( array_shift($data) );
        $image = basename( array_shift($data) );
        $raw_tags = $data;
        
        if( !empty($image) && !preg_match( '/.jpg/', $image ) ) $image = $image . '.jpg';
        
        $sql = "INSERT INTO `artworks` (id,title,artist,year,image) VALUES ('".$id."','".$title."','".$artist."','".$year."','".$image."')";
        $result = $mysqli->query( $sql );
        
        echo "artwork inserted...";
        
        if( $result != null ) 
        {
        	$artwork_id = $id;
        	
        	//	insert tags
			$tags_inserted = 0;
        	$tags = array();
        	
    		foreach($raw_tags as $tag)
			{
				if( empty($tag) ) continue;
				
				//if( $tag == "mona" ) continue;
				
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
				
				$sql = "INSERT INTO `artwork_tags` (artwork_id,tag_id) VALUES ('".$artwork_id."','".$tag_id."')";
        		$result = $mysqli->query( $sql );
			}
			
			echo $tags_inserted . "/" . count($tags) . " tags inserted...";
			
			if( $image != null 
				&& !strpos($image,'placeholder') )
			{
				$filename = $dir . $image;
				
				//	TODO: handle placeholder image
				
				//	copy image
				if( copy( 'http://mona-vt.artpro.net.au/data/media/' . $image, $filename ) )
				{
					//	get average color
					$size = getimagesize( $filename );
					$target = imagecreatetruecolor( 1, 1 );
					$source = imagecreatefromjpeg( $filename );
					
					imagecopyresampled( $target, $source, 0, 0, 0, 0, 1, 1, $size[0], $size[1] );
					
					$rgb = imagecolorat($target,0,0);
					$hex = rgbtohex( ($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF );
					
					$sql = "UPDATE `artworks` SET color='".$hex."' WHERE id='".$id."'";
					$result = $mysqli->query( $sql );
					
					$image = preg_replace('/_lg/','_sm',$image);
					$filename = $dir . $image;
					
					copy( 'http://mona-vt.artpro.net.au/data/media/' . $image, $filename );
					
					echo "image inserted...";
				}
				else
				{
					echo "image not inserted <b>($image)</b>...";
				}				
			}
			
			$tags_total += count($tags);
    	}
        
        echo "<br/>";
    }
    
    fclose($handle);
}

echo "<br/><br/>";
echo $tags_total . " total tags (" . $tags_inserted_total . " inserted, " . $tags_shared_total . " shared)<br/>";

function rgbtohex($r, $g, $b) 
{
	$hex = "#";
	$hex.= str_pad(dechex($r), 2, "0", STR_PAD_LEFT);
	$hex.= str_pad(dechex($g), 2, "0", STR_PAD_LEFT);
	$hex.= str_pad(dechex($b), 2, "0", STR_PAD_LEFT);

	return $hex;
	}
?>