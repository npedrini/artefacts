<?php
session_start();

DEFINE('HIGHLIGHT_COLOR','#CC3300');
DEFINE("MONA_ID",962);
DEFINE("WIDTH",100);
DEFINE("HEIGHT",100);

DEFINE("SHOW_MONA",isset($_GET['show_mona'])?$_GET['show_mona']=='true':true);
DEFINE("SHOW_TAGS",isset($_GET['show_tags'])?$_GET['show_tags']=='true':true);
DEFINE("SHOW_ARTISTS",isset($_GET['show_artists'])?$_GET['show_artists']=='true':false);

if( !isset( $_GET['date'] ) ) die( );

include '../config/' . getenv('HTTP_APPLICATION_ENVIRONMENT') . "/config.php";

$mysqli = new mysqli( DB_HOST, DB_USER, DB_PASS );
$mysqli->select_db( DB_NAME );

$date_format = DATE_FORMAT;
$date_format = preg_replace( '/{{date}}/', 'j', $date_format );
$date_format = preg_replace( '/{{month}}/', 'n', $date_format );
$date_format = preg_replace( '/{{year}}/', 'Y', $date_format );

$date = DateTime::createFromFormat( $date_format, $_GET['date'], new DateTimeZone('Australia/Melbourne') );

if( $date == null ) die();

$date_start = $date->format('Y-m-d');
$date_end = $date->format('Y-m-d');

$artworks = array();
$artists = array();
$dreams = array();
$tags = array();

$artworks_indexed = array();
$artists_indexed = array();
$tags_indexed = array();

global $nodes,$links,$tags;

$nodes = array();
$links = array();

$a_keys = array();
$d_keys = array();
$t_keys = array();

$nodes_structured = array();

function showTag( $tag, $node, &$nodes, &$tags_indexed, &$keys, &$links )
{
	if( SHOW_TAGS == false ) return;
	
	if( isset($tags_indexed[$tag->id]) )
		$tag_node = $tags_indexed[$tag->id];
	else
		$tags_indexed[$tag->id] = $tags[] = $nodes[] = $tag_node = (object)array('color'=>'#000000','id'=>$tag->id,'index'=>count($nodes),'node_type'=>'tag','tags'=>array(),'title'=>$tag->tag,'value'=>0);

	$source = $node;
	$target = $tag_node;
	
	if( $source !=null 
		&& $target !=null )
	{
		$key = $source->index.'_'.$target->index;
		
		//	add link for artist<>artwork relationship
		if( !isset($keys[$key]) ) 
		{
			$links[] = (object)array('source'=>$source->index,'target'=>$target->index,'value'=>0,'type'=>'tag');
			$keys[$key] = 1;
			
			$tag_node->value++;
		}
	}
}

function getNodeById( $id, $type, $nodes )
{
	foreach($nodes as $node)
	{
		if( $node->id == $id
			&& $node->node_type == $type )
		{
			return $node;
		}
	}
	
	return null;
}

function sanitizeTags(&$tags)
{
	$ts = array('mona','old');
	
	foreach($ts as $t) 
		if( array_search($t,$tags) > -1 ) 
			array_splice( $tags, array_search($t,$tags), 1 );
}

//	STEP 1: GET MONA NODE

if( SHOW_MONA )
{
	$result = $mysqli->query( "SELECT * FROM artworks WHERE id='".MONA_ID."'" );
	
	if( $mysqli->affected_rows > 0 )
	{
		while( $artwork = $result->fetch_assoc() )
		{
			$artwork = (object)$artwork;
			$artwork->tags = array();
			
			$sql  = "SELECT tag FROM `artwork_tags` LEFT JOIN tags ON artwork_tags.tag_id=tags.id WHERE artwork_tags.artwork_id='" . $artwork->id. "'";
			$result_tags = $mysqli->query( $sql );
			
			if( $mysqli->affected_rows > 0 )
				while( $t = $result_tags->fetch_assoc() ) 
					$artwork->tags[] = $t['tag'];
			
			sanitizeTags( $artwork->tags );
			
			$artwork->node_type = "artwork";
			$artwork->index = count($nodes);
			$artwork->value = 0;
			$artwork->color2 = $artwork->color;
			$artwork->color = '#000000';
			
			$artworks[] = $artwork;
			$nodes[] = $artwork;
			
			$artworks_indexed[$artwork->id] = $artwork;
			
			$mona_node = $artwork;
			$nodes_structured[0] = array( $mona_node );
		}
	}
}

//	STEP 2: GET ALL DREAMS FOR SPECIFIED DATE RANGE
$sql = "SELECT dreams.*, users.ip FROM `dreams` ";
$sql .= "LEFT JOIN `users` on dreams.user_id=users.id ";
$sql .= "WHERE occur_date >= '" . $date_end . "' AND occur_date <= '" . $date_start . "'";

$result = $mysqli->query( $sql );

if( $mysqli->affected_rows > 0 )
{
	while( $dream = $result->fetch_assoc() )
	{
		$dream = (object)$dream;
		$dream->tags = array();
		
		$sql  = "SELECT tags.tag,tags.id FROM `dream_tags` LEFT JOIN tags ON dream_tags.tag_id=tags.id WHERE dream_tags.dream_id='" . $dream->id . "'";
		$result_tags = $mysqli->query( $sql );
		
		$dream->node_type = "dream";
		$dream->index = count($nodes);
		$dream->value = 0;
		$dream->color2 = $dream->ip == $_SERVER['REMOTE_ADDR'] ? HIGHLIGHT_COLOR : $dream->color;
		$dream->color = 0x000000;
		
		$dreams[] = $dream;
		$nodes[] = $dream;
		
		if( $mysqli->affected_rows > 0 )
		{
			while( $t = $result_tags->fetch_assoc() ) 
			{
				$dream->tags[] = $t['tag'];
				
				showTag( (object)$t, $dream, $nodes, $tags_indexed, $t_keys, $links );
			}
		}
	}
}

if( isset($_SESSION['submission'])
	&& $_SESSION['submission'] == 1 )
{
	$dream = (object)array('id'=>'-1', 'user_id'=>'-1', 'description'=>'Your dream here', 'color'=>HIGHLIGHT_COLOR, 'color2'=>HIGHLIGHT_COLOR, 'index'=>count($nodes), 'interactive'=>false, 'node_type'=>'dream', 'tags'=>array(), 'value'=>0);
		
	$dreams[] = $dream;
	$nodes[] = $dream;
	
	unset( $_SESSION['submission'] );
}

//	get all artworks

/*
$sql  = "SELECT DISTINCT visit_data.artwork_id,artworks.* FROM visits ";
$sql .= "RIGHT JOIN visit_data ON visits.id = visit_data.visit_id ";
$sql .= "RIGHT JOIN artworks ON artworks.id = visit_data.artwork_id ";
$sql .= "WHERE visits.visit_date >= '" . $date_end . "' AND visits.visit_date <= '" . $date_start . "'";
$result = $mysqli->query( $sql );
*/

/*
//	calculate artwork<>artwork links based on tag matches
foreach($artworks as $item)
{
	if( strtolower($item->title) == 'mona' ) $mona_node = $item;
	
	foreach($artworks as $sibling)
	{
		if( $item->id != $sibling->id )
		{
			$common_tags = array_intersect( $item->tags, $sibling->tags );
			
			if( count($common_tags) > 1 )
			{
				$source = getNodeById( $item->id, "artwork", $nodes );
				$target = getNodeById( $sibling->id, "artwork", $nodes );
				
				if( $source !=null 
					&& $target !=null )
				{
					$key = $source->index.'_'.$target->index;
					$key_rev = $target->index.'_'.$source->index;
					
					if( isset($a_keys[$key]) || isset($a_keys[$key_rev]) ) continue;
					
					//$links[] = (object)array('source'=>$source->index,'target'=>$target->index,'value'=>count($common_tags),'type'=>'aa');
					
					$a_keys[ $key ] = true;
					
					$source->value++;
					$target->value++;
				}
			}
		}
	}
}

if( $mona_node != null )
{
	foreach($artworks as $item)
	{
		if( $item->id != $mona_node->id )
		{
			$source = getNodeById( $item->id, "artwork", $nodes );
			$target = getNodeById( $mona_node->id, "artwork", $nodes );
			
			if( $source !=null 
				&& $target !=null )
			{
				$key = $source->index.'_'.$target->index;
				$key_rev = $target->index.'_'.$source->index;
				
				if( isset($a_keys[$key]) || isset($a_keys[$key_rev]) ) continue;
				
				//$links[] = (object)array('source'=>$source->index,'target'=>$target->index,'value'=>1,'type'=>'aa');
				
				$a_keys[ $key ] = true;
				
				//$source->value++;
				$target->value++;
			}
		}
	}
}
*/

/*
//	calculate dream<>dream links based on tag matches
foreach($dreams as $item)
{
	foreach($dreams as $sibling)
	{
		if( $item->id != $sibling->id )
		{
			$common_tags = array_intersect( $item->tags, $sibling->tags );
			
			if( count($common_tags) )
			{
				$source = getNodeById( $item->id, "dream", $nodes );
				$target = getNodeById( $sibling->id, "dream", $nodes );
				
				if( $source !=null 
					&& $target !=null )
				{
					$key = $source->index.'_'.$target->index;
					$key_rev = $target->index.'_'.$source->index;
					
					if( isset($d_keys[$key]) || isset($d_keys[$key_rev]) ) continue;
					
					$links[] = (object)array('source'=>$source->index,'target'=>$target->index,'value'=>count($common_tags),'type'=>'dd');
				
					$d_keys[ $key ] = true;
					
					$source->value++;
					$target->value++;
				}
			}
		}
	}
}
*/

//	calculate dream<>artwork links based on tag matches
foreach($dreams as $dream)
{
	$sql  = "SELECT DISTINCT visits.id,users.ip FROM visits ";
	$sql .= "RIGHT JOIN users ON users.id = visits.user_id ";
	$sql .= "WHERE visits.user_id='" . $dream->user_id . "' AND visits.visit_date >= '" . $date_end . "' AND visits.visit_date <= '" . $date_start . "'";
	
	$result = $mysqli->query( $sql );
	
	if( $mysqli->affected_rows > 0 )
	{
		$visit = $result->fetch_assoc();
		
		$visit_id = $visit['id'];
		
		if( $visit_id == null ) continue;
		
		//	this should handle case where artwork hasn't been added to system
		$sql  = "SELECT artworks.* FROM visit_data ";
		$sql .= "RIGHT JOIN artworks ON visit_data.artwork_id=artworks.id ";
		$sql .= "WHERE visit_data.visit_id='" . $visit_id . "' AND artworks.id <> '" . MONA_ID . "'";
		
		$result = $mysqli->query( $sql );
		
		if( $mysqli->affected_rows > 0 )
		{
			while( $artwork = $result->fetch_assoc() ) 
			{
				//	update artwork node if it already exists
				if( isset($artworks_indexed[$artwork['id']]) )
				{
					$artwork = (object)$artworks_indexed[$artwork['id']];
					
					//	highlight artwork if matches current user's ip (from dream)
					if( $visit['ip'] == $_SERVER['REMOTE_ADDR'] )
					{
						$artwork = $artworks_indexed[$artwork->id];
						$artwork->color2 = $artwork->color;
						$artwork->color = HIGHLIGHT_COLOR;
					}
				}
				
				//	add new node for artwork
				else
				{
					$artwork = (object)$artwork;
					
					$artwork->tags = array();
					
					$sql  = "SELECT tag FROM `artwork_tags` LEFT JOIN tags ON artwork_tags.tag_id=tags.id WHERE artwork_tags.artwork_id='" . $artwork->id. "'";
					$result_tags = $mysqli->query( $sql );
					
					if( $mysqli->affected_rows > 0 )
						while( $t = $result_tags->fetch_assoc() ) 
							$artwork->tags[] = $t['tag'];
					
					sanitizeTags( $artwork->tags );
					
					$artwork->node_type = "artwork";
					$artwork->index = count($nodes);
					$artwork->value = 0;
					$artwork->color2 = $artwork->color;
					$artwork->color = '#000000';
					
					$artworks[] = $nodes[] = $artwork;
					
					$artworks_indexed[$artwork->id] = $artwork;
				}
				
				//	update artist node if it already exists
				if( isset($artists_indexed[$artwork->artist]) )
				{
					$artist = $artists_indexed[$artwork->artist];
				}
				
				//	add new node for artist
				else
				{
					$id = strtolower($artwork->artist);
					
					$artist = (object)array('artist'=>$artwork->artist,'color'=>'#000000','id'=>$id,'index'=>count($nodes),'node_type'=>'artist','tags'=>array(),'value'=>0);
					
					$artists[] = $artist;
					
					//	don't add a node representing 'unknown' artists
					if( SHOW_ARTISTS 
						&& strtolower($artwork->artist) != 'unknown' )
						$nodes[] = $artist;
					
					$artists_indexed[$artwork->artist] = $artist;
				}
			
				if( SHOW_ARTISTS
					&& $artwork->artist )
				{
					$source = getNodeById( $artist->id, "artist", $nodes );
					$artwork = getNodeById( $artwork->id, "artwork", $nodes );
					
					if( $source !=null 
						&& $artwork !=null )
					{
						$key = $source->index.'_'.$artwork->index;
					
						if( !isset($keys[$key]) )
						{
							//	add link for artist<>artwork relationship
							$links[] = (object)array('source'=>$artist->index,'target'=>$artwork->index,'value'=>1,'type'=>'artist_artwork');
							
							$keys[$key] = 1;
							
							$artist->value++;
						}
					}
				}
								
				$common_tags = array_intersect( $dream->tags, $artwork->tags );
				
				/*
				if( count($common_tags) == 1 
					&& isset($common_tags[0]) 
					&& ($common_tags[0] == 'mona' || $common_tags[0] == 'old') ) continue;
				*/
				
				if( count($common_tags) )
				{
					$source = getNodeById( $artwork->id, "artwork", $nodes );
					$target = getNodeById( $dream->id, "dream", $nodes );
					
					if( $source !=null 
						&& $target !=null )
					{
						$links[] = (object)array('source'=>$source->index,'target'=>$target->index,'value'=>count($common_tags),'type'=>'artwork_dream');
						
						$source->value++;
						$target->value++;
					}
				}
				
				if( SHOW_MONA ) $mona_node->value++;
			}
		}
	}
}

$nodes_structured[1] = array();

if( SHOW_MONA )
{
	$source = getNodeById( $mona_node->id, "artwork", $nodes );
	
	foreach($artworks as $artwork)
	{
		$artist = $artists_indexed[$artwork->artist];
		
		//	draw links between mona and artists who have more than one work (and aren't unknown)
		if( SHOW_ARTISTS 
			&& strtolower($artist->artist) != 'unknown' )
		{
			$target = getNodeById( $artist->id, "artist", $nodes );
			
			$link = (object)array('source'=>$source->index,'target'=>$target->index,'value'=>$artist->value,'type'=>'museum_artist');
			
			if( $source !=null 
				&& $target !=null )
			{
				$links[] = $link;
				
				$source->value++;
				
				array_push( $nodes_structured[1], $target );
			}
		}
		
		//	draw links between mona and "orphaned" artworks
		else
		{
			$target = getNodeById( $artwork->id, "artwork", $nodes );
			
			if( $source !=null 
				&& $target !=null )
			{
				$links[] = (object)array('source'=>$source->index,'target'=>$target->index,'value'=>$artist->value,'type'=>'museum_artwork');
				
				$source->value++;
				
				array_push( $nodes_structured[1], $target );
			}
		}
	}
}

/*
$x = WIDTH/2/WIDTH;
$y = HEIGHT/2/HEIGHT;

$mona_node->fixed = true;
$mona_node->x = $x;
$mona_node->y = $y;

$r = WIDTH/2;
$d = 0;

for($i=0;$i<count($nodes_structured[1]);$i++)
{
	$a = $d * (M_PI/180);
	
	$nodes_structured[1][$i]->fixed = true;
	$nodes_structured[1][$i]->x = $x + (($r * cos($a)) / WIDTH);
	$nodes_structured[1][$i]->y = $y + (($r * sin($a)) / HEIGHT);
	
	$d += 360/count($nodes_structured[1]);
}
*/

$data = (object)array( 'nodes'=>$nodes, 'links'=>$links, 'dream_total'=>count($dreams), 'art_total'=>count($artworks) );

/*
echo "<pre>";
//print_r($nodes);
//print_r($links);
echo "</pre>";
*/

echo json_encode( $data );
?>