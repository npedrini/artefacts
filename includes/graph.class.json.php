<?php
include_once "db.class.php";
include_once "logger.class.php";

class Graph
{
	
	const WIDTH = 100;
	const HEIGHT = 100;

	const SHOW_ROOT = true;
	const SHOW_TAGS = true;
	
	public $date_from;
	public $date_to;
	public $highlightColor = '#CC3300';
	
	private $dreams;
	private $tags;
	private $indexes;
	private $keys;
	private $link_fields;
	
	private $links;
	
	function __construct() 
	{
    	$this->init();
    }
	
	function init()
    {
    	$this->link_fields = array
		(
			array( "field"=>"age" )
		);
		
		$this->db = new Database();
		$this->dreams = array();
		$this->keys = array();
		$this->indexes = array( "tags"=>array() );
		$this->nodes = array();
		$this->links = array();
		$this->tags = array();
    	$this->logger = new Logger();
    }
    
    function build()
    {
    	$data = array();
    	
    	$root_node = (object)array();
		$root_node->color2 = '#000000';
		$root_node->color = '#000000';
		$root_node->node_type = "dream";
		$root_node->index = count($this->nodes);
		$root_node->tags = array();
		$root_node->title = $this->date_from;
		$root_node->value = 0;
		
		$this->nodes[] = $root_node;
		
		//	STEP 2: GET ALL DREAMS FOR SPECIFIED DATE RANGE
		$sql = "SELECT dreams.*, users.ip FROM `dreams` ";
		$sql .= "LEFT JOIN `users` on dreams.user_id=users.id ";
		$sql .= "WHERE occur_date >= '" . $this->date_to . "' AND occur_date <= '" . $this->date_from . "'";
		
		$result = $this->db->query( $sql );
		
		if( $this->db->affected_rows > 0 )
		{
			while( $dream = $result->fetch_assoc() )
			{
				$dream = (object)$dream;
				$dream->tags = array();
				
				$sql  = "SELECT tags.tag,tags.id FROM `dream_tags` LEFT JOIN tags ON dream_tags.tag_id=tags.id ";
				$sql .= "WHERE dream_tags.dream_id='" . $dream->id . "'";
				
				$result_tags = $this->db->query( $sql );
				
				$dream->node_type = "dream";
				$dream->index = count($this->nodes);
				$dream->value = 0;
				$dream->color2 = ($dream->ip == $_SERVER['REMOTE_ADDR']) ? $this->highlightColor : $dream->color;
				$dream->color = 0x000000;
				$dream->tags = array();
				
				$this->dreams[] = $dream;
				$this->nodes[]  = $dream;
				
				if( $this->db->affected_rows > 0 )
				{
					while( $t = $result_tags->fetch_assoc() ) 
					{
						$dream->tags[] = $t['tag'];
						
						$this->showTag( (object)$t, $dream );
					}
				}
				
				if( $root_node !=null 
					&& $dream !=null )
				{
					$key = $root_node->index.'_'.$dream->index;
					
					if( !isset($this->keys[$key]) )
					{
						$this->links[] = (object)array('source'=>$root_node->index,'target'=>$dream->index,'value'=>1,'type'=>'artist_artwork');
						
						$this->keys[$key] = 1;
						
						$dream->value++;
					}
				}
				
				if( self::SHOW_ROOT 
					&& isset($root_node) ) 
					$root_node->value++;
			}
		}
    }
    
    function render()
    {
    	foreach($this->nodes as $node)
		{
			$node->stroke = isset($node->color2) ? (hexdec(preg_replace("/#/","0x",$node->color2)) > 0x666666/2 ? true : false) : false; 
		}
		
		$data = (object)array( 'nodes'=>$this->nodes, 'links'=>$this->links, 'dream_total'=>count($this->dreams), 'art_total'=>0 );
		
    	return $this->format( $data );
    }
    
    function format( $data )
    {
    	//	TODO: return based on format type
    	return json_encode($data);
    }
    
    function showTag( $tag, $node )
	{
		if( self::SHOW_TAGS == false ) return;
		
		if( isset($this->indexes['tags'][$tag->id]) )
			$tag_node = $this->indexes['tags'][$tag->id];
		else
			$this->indexes['tags'][$tag->id] = $this->tags[] = $this->nodes[] = $tag_node = (object)array('color'=>'#000000','id'=>$tag->id,'index'=>count($this->nodes),'node_type'=>'tag','tags'=>array(),'title'=>$tag->tag,'value'=>0);
	
		$source = $node;
		$target = $tag_node;
		
		if( $source !=null 
			&& $target !=null )
		{
			$key = $source->index.'_'.$target->index;
			
			if( !isset($keys[$key]) ) 
			{
				$this->links[] = (object)array('source'=>$source->index,'target'=>$target->index,'value'=>0,'type'=>'tag');
				$this->keys[$key] = 1;
				
				$tag_node->value++;
			}
		}
	}
	
	function getNodeById( $id, $type )
	{
		foreach($this->nodes as $node)
		{
			if( $node->id == $id
				&& $node->node_type == $type )
			{
				return $node;
			}
		}
		
		return null;
	}
	
	function getNodeCount()
	{
		return count($this->nodes);
	}
	
	/*
	function sanitizeTags(&$tags)
	{
		$ts = array('mona','old');
		
		foreach($ts as $t) 
			if( array_search($t,$tags) > -1 ) 
				array_splice( $tags, array_search($t,$tags), 1 );
	}
	*/
}

?>