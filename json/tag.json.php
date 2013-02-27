<?php
include '../config/' . getenv('HTTP_APPLICATION_ENVIRONMENT') . "/config.php";

if( isset($_GET['id']) 
	&& isset($_GET['tags']) )
{
	$id = $_GET['id'];
	$tags = $_GET['tags'];
	
	$sql = "SELECT * FROM `artworks` WHERE id = '".$id."'";
	
	$mysqli = new mysqli( DB_HOST, DB_USER, DB_PASS );
	$mysqli->select_db( DB_NAME );
	
	$result = $mysqli->query( $sql );
	
	if( $mysqli->affected_rows > 0 )
	{
		$artwork = $result->fetch_assoc();
		
		$tags = explode( ',', $tags );
		
		$added_tags = array();
		$existing_tags = array();
		
		foreach($tags as $tag)
		{
			$tag = $mysqli->real_escape_string(strtolower(trim($tag)) );
			
			$sql = "SELECT id FROM `tags` WHERE tag='" . $tag . "'";
			
			$result = $mysqli->query( $sql );
			
			if( $mysqli->affected_rows > 0 )
			{
				$row = $result->fetch_assoc();
				$tag_id = $row['id'];
				
				$existing_tags[] = $tag;
			}
			else
			{
				$sql = "INSERT INTO `tags` (tag) VALUES ('".$tag."')";
				
				$result = $mysqli->query( $sql );
				$tag_id = $mysqli->insert_id;
				
				$added_tags[] = $tag;
			}
			
			$sql = "SELECT id FROM `artwork_tags` WHERE artwork_id='" . $id. "' AND tag_id='" . $tag_id . "'";
			$result = $mysqli->query( $sql );
			
			if( $mysqli->affected_rows == 0 )
			{
				$sql = "INSERT INTO `artwork_tags` (artwork_id,tag_id,ip) VALUES ('" . $id. "','".$tag_id."','". $_SERVER["REMOTE_ADDR"]."')";
				$result = $mysqli->query( $sql );
			}
		}
		
		$result = (object)array('added_tags'=>$added_tags,'existing_tags'=>$existing_tags,'id'=>$id,'success'=>1);
	}
	else
	{
		//no artwork found
		$result = (object)array('error'=>'Unspecified error','success'=>0);
	}
}
else
{
	$result = (object)array('error'=>'Unspecified error','success'=>0);	
}

echo json_encode($result);

?>