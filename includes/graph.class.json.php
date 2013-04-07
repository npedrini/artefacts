<?php
include_once "db.class.php";
include_once "logger.class.php";
include_once "includes/alchemy/module/AlchemyAPI.php";
include_once "includes/alchemy/module/AlchemyAPIParams.php";

class Graph
{
	
	const WIDTH = 100;
	const HEIGHT = 100;

	const SHOW_ROOT = true;
	const SHOW_TAGS = true;
	
	const TYPE_DREAM = "dream";
	const TYPE_TAG = "tag";
	
	public $alchemyApiKey;
	public $dateFrom;
	public $dateTo;
	public $highlightColor = '#CC3300';
	public $minTagValue;
	
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
		$this->minTagValue = 1;
		$this->nodes = array();
		$this->links = array();
		$this->tags = array();
    	$this->logger = new Logger();
    }
    
    /**
     * Populates class with graph data
     */
    function build()
    {
    	$data = array();
		
    	$root_node = $this->getRootNode();
    	$this->nodes[] = $root_node;
    	
		//	GET ALL DREAMS FOR SPECIFIED DATE RANGE
		$sql = "SELECT dreams.*, users.ip FROM `dreams` ";
		$sql .= "LEFT JOIN `users` on dreams.user_id=users.id ";
		$sql .= "WHERE occur_date >= '" . $this->dateFrom . "' AND occur_date <= '" . $this->dateTo . "' ";
		
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
				
				$dream->node_type = self::TYPE_DREAM;
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
						
						if( self::SHOW_TAGS )
						{
							$tag = (object)$t;
							
							if( isset($this->indexes['tags'][$tag->id]) )
							{
								$tag_node = $this->indexes['tags'][$tag->id];
							}
							else
							{
								$this->indexes['tags'][$tag->id] = $this->tags[] = $tag_node = (object)array('color'=>'#000000','id'=>$tag->id,'index'=>count($this->nodes),'node_type'=>'tag','tags'=>array(),'title'=>$tag->tag,'value'=>0);
								$this->indexes['tags_by_tag'][$tag->tag] = $tag_node;
							}
						}	
						
						$tag_node->value++;
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
		
		if( self::SHOW_TAGS )
		{
			foreach($this->dreams as $dream)
			{
				foreach($dream->tags as $tag)
				{
					$tag = $this->indexes['tags_by_tag'][$tag];
					
					if( $tag->value >= $this->minTagValue )
					{
						if( !array_search($tag,$this->nodes) ) 
						{
							$this->nodes[] = $tag;
						}
						
						$this->showTag( $tag, $dream );
					}
				}
			}
		}
    }
    
    function getRootNode()
    {
    	$root_node = (object)array();
    	$root_node->color2 = '#000000';
    	$root_node->color = '#000000';
    	$root_node->node_type = self::TYPE_DREAM;
    	$root_node->index = count($this->nodes);
    	$root_node->tags = array();
    	$root_node->title = 'A dream of the collective unconscious';
    	$root_node->value = 0;
    	
    	return $root_node;
    }
    
    function render()
    {
    	//$text = "";
    	
    	$paragraphs = array();
		$dreams_by_value = array();
		
		foreach($this->dreams as $dream)
		{
			if( $dream->node_type == self::TYPE_DREAM )
			{
				$index = array_search( $dream, $this->dreams );
				$dreams_by_value[ $index ] = $dream->value;
			}
		}
		
		asort( $dreams_by_value );
		
		foreach($dreams_by_value as $index=>$value)
		{
			$dream = $this->dreams[$index];
			$sentences = explode(".",$dream->description);
			
			for($i=0,$j=0;$i<count($sentences);$i++)
			{
				if( $sentences[$i] == null ) continue;
				if( !isset($paragraphs[$j]) ) $paragraphs[$j] = array();
				
				$paragraphs[$j][] = array( "index"=>$dream->index, "sentence"=>$sentences[$i] );
				
				$j++;
			}
		}
		
		/*
		if( self::SHOW_TAGS )
		{
			$sentences = array();
			
			foreach($paragraphs as $p)
				foreach($p as $sentence)
				$sentences[] = $sentence['sentence'];
			
			$text = implode(". ", $sentences);
			
			$alchemy = new AlchemyAPI();
			$alchemy->setAPIKey( $this->alchemyApiKey );
			
			$params = new AlchemyAPI_KeywordParams();
			$params->setMaxRetrieve( 20 );
			$params->setKeywordExtractMode( 'strict' );
				
			$result = json_decode( $alchemy->TextGetRankedKeywords( $text, AlchemyAPI::JSON_OUTPUT_MODE, $params ) );
			
			if( $result->status == "OK" )
			{
				$root_node = $this->nodes[0];
				
				foreach($result->keywords as $key=>$val)
				{
					$tag = $val->text;
					$tag = preg_replace( "/\./", "", $tag );
					
					if( isset($this->indexes['tags_by_tag'][$tag]) )
					{
						$tag_node = $this->indexes['tags_by_tag'][$tag];
					}
					else
					{
						$this->tags[] = $tag_node = (object)array('color'=>'#000000','id'=>-1,'index'=>count($this->nodes),'node_type'=>'atag','tags'=>array(),'title'=>$tag,'value'=>0);
						$this->indexes['tags_by_tag'][$tag] = $tag_node;
					}
					
					$tag_node->value++;
					
					if( $tag_node->value >= $this->minTagValue )
					{
						if( !array_search($tag_node,$this->nodes) )
						{
							$this->nodes[] = $tag_node;
						}
					
						//$this->showTag( $tag_node, $root_node );
					}
				}
			}
		}
		*/
		
		$this->nodes[0]->description = $paragraphs;
		
		foreach($this->nodes as $node)
		{
			if( $node->node_type == self::TYPE_TAG )
				$node->stroke = true;
			else
				$node->stroke = isset($node->color2) ? (hexdec(preg_replace("/#/","0x",$node->color2)) < 0x666666 ? true : false) : false;
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
		/*
		if( self::SHOW_TAGS == false ) return;
		
		if( isset($this->indexes['tags'][$tag->id]) )
			$tag_node = $this->indexes['tags'][$tag->id];
		else
			$this->indexes['tags'][$tag->id] = $this->tags[] = $this->nodes[] = $tag_node = (object)array('color'=>'#000000','id'=>$tag->id,'index'=>count($this->nodes),'node_type'=>'tag','tags'=>array(),'title'=>$tag->tag,'value'=>0);
		*/
		
		if( !isset($this->indexes['tags'][$tag->id]) ) return;
		
		$tag_node = $this->indexes['tags'][$tag->id];
		$tag_node->index = array_search($tag_node,$this->nodes);
		
		$source = $node;
		$target = $tag_node;
		
		if( $source !=null 
			&& $target !=null )
		{
			$key = $source->index.'_'.$target->index;
			
			if( !isset($this->keys[$key]) ) 
			{
				$this->links[] = (object)array('source'=>$source->index,'target'=>$target->index,'value'=>1,'type'=>'tag');
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