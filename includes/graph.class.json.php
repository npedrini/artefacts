<?php
set_include_path("../");

include_once "includes/db.class.php";
include_once "includes/dream.class.php";
include_once "includes/logger.class.php";
include_once "includes/alchemyapi_php/alchemyapi.php";

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
	public $maxKeywords = 20;
	public $maxDreams = 100;
	public $minTagValue;
	public $origin;	
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
		$sql = "SELECT id FROM `dreams` ";
		$sql .= "WHERE occur_date >= '" . $this->dateFrom . "' ";
		if( isset($this->dateTo) )
			$sql .= "AND occur_date <= '" . $this->dateTo . "' ";
		if(isset($this->origin) 
		{
        		$sql .= "AND origin = '" . $origin . "' ";
		}
			
		$sql .= "LIMIT 0," . $this->maxDreams;
		
		$result = $this->db->query( $sql );
		
		//	recalculate min and max dates, since requested
		//	date range will likely be invalid if limit is 
		//	imposed
		$minDate = strtotime( $this->dateTo );
		$maxDate = strtotime( $this->dateFrom );
		
		if( $this->db->affected_rows > 0 )
		{
			while( $row = $result->fetch_assoc() )
			{
				$dream = new Dream( $row['id'] );
				$dream = (object)$dream;

				$node = (object)array();
				$node->age = $dream->age;
				$node->city = $dream->city;
				$node->color2 = $dream->color;
				$node->color = 0x000000;
				$node->description = $dream->description;
				$node->gender = $dream->gender;
				$node->node_type = self::TYPE_DREAM;
				$node->occur_date = $dream->occur_date;
				$node->index = count($this->nodes);
				$node->tags = array();			
				$node->title = $dream->title;
				$node->value = 0;
				
				$minDate = min( strtotime( $dream->occur_date ), $minDate );
				$maxDate = max( strtotime( $dream->occur_date ), $maxDate );
				
				if( $dream->getImage() ) 
				{
					$node->image_path = $dream->getImage()->getPath('med');
					$node->thumb_path = $dream->getImage()->getPath('small');
				}

				if( $dream->getAudio() ) 
				{
					$node->audio_path = $dream->getAudio()->getPath();
				}
				
				$this->dreams[] = $node;
				$this->nodes[]  = $node;
				
				foreach($dream->tags as $tag)
				{
					$node->tags[] = $tag->tag;

					if( self::SHOW_TAGS )
					{
						if( isset($this->indexes['tags'][$tag->tag]) )
						{
							$tag_node = $this->indexes['tags'][$tag->tag];
						}
						else
						{
							$this->indexes['tags'][$tag->tag] = $this->tags[] = $tag_node = (object)array('color'=>'#000000','id'=>$tag->id,'index'=>count($this->nodes),'node_type'=>'tag','tags'=>array(),'title'=>$tag->tag,'value'=>0);
						}
					}
						
					$tag_node->value++;
				}

				if( self::SHOW_ROOT 
					&& isset($root_node) ) 
					$root_node->value++;
			}
			
			$this->dateFrom = date( "Y-m-d", $minDate );
			$this->dateTo = date( "Y-m-d", $maxDate );
		}
		
		//	add tag nodes and dream<>tag links
		if( self::SHOW_TAGS )
		{
			foreach($this->dreams as $dream)
			{
				foreach($dream->tags as $tag)
				{
					$tag = $this->indexes['tags'][$tag];
					
					if( $tag->value >= $this->minTagValue )
					{
						if( !array_search($tag,$this->nodes) ) 
						{
							$this->nodes[] = $tag;
						}
						
						$this->addTag( $tag, $dream );
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
    	$root_node->title = 'Collective dream';
    	$root_node->value = 0;
    	
    	return $root_node;
    }
    
    function render()
    {
		$sounds = array("A","As","B","C","Cs","D","Ds","E","F","Fs","G","Gs");

    	$paragraphs = array();

		//	build indexed array of dream values
		$dreams_by_value = array();
		
		foreach($this->dreams as $dream)
		{
			if( $dream->node_type == self::TYPE_DREAM )
			{
				$index = array_search( $dream, $this->dreams );
				$dreams_by_value[ $index ] = $dream->value;
			}

			if( $dream->value )
			{
				$sound_id = $sounds[ floor( count($sounds)/0xFFFFFF*hexdec($dream->color2)) ];
				$sound_id .= min( $dream->value, 3 );

				$dream->soundeffect_path = $sound_id;
			}			
		}
		
		//	sort array of values by value
		arsort( $dreams_by_value );
		
		//	textualization
		$paragraphs[0] = array();
		
		$total_weight = 0;
		foreach($dreams_by_value as $index=>$value) 
			$total_weight += max(1,$this->dreams[$index]->value);

		$cursor_position = 0;
		$i=0;

		foreach($dreams_by_value as $index=>$value)
		{
			$dream = $this->dreams[$index];
			
			$sentences = preg_split( "/(\.+\s*)/", $dream->description );
			
			$influence = max(1,$dream->value) / $total_weight;
			$excerpt_count = ceil( $influence * count($sentences) );
			$start = round(count($sentences)*$cursor_position);
			$length = min( count($sentences)-$start-1, $excerpt_count );

			$excerpt = array_slice( $sentences, $start, $length );
			$explanation = count($excerpt) . " sentences  taken from the " . $this->cardinalize($i+1) . " most influential dream";

			for($j=0;$j<count($excerpt);$j++)
			{
				$paragraphs[0][] = array( "index"=>$dream->index, "sentence"=>$sentences[$j], "explanation"=>$explanation );
			}
			
			$cursor_position = ($start+$length)/count($sentences);
			$i++;
		}
		
		//	get alchemy tags for synthesized root node dynamically
		if( self::SHOW_TAGS )
		{
			//	flatten node content into a string
			$text = "";
			
			$sentences = array();
			
			foreach($paragraphs as $p)
				foreach($p as $sentence)
					$sentences[] = $sentence['sentence'];
			
			$text = implode(". ", $sentences);

			//	alchemy
			$alchemy = new AlchemyAPI($this->alchemyApiKey);
			
			$params = array();
			$params['maxRetrieve'] = $this->maxKeywords;
			$params['keywordExtractMode'] = 'strict';
			$params['sentiment'] = 0;
			$params['showSourceText'] = 0;
			
			try {
				
				//	parse response
				$result =$alchemy->keywords( 'text', $text, $params );				
			} catch (Exception $e){}

			if( isset($result)
				&& $result['status'] == "OK" )
			{
				$root_node = $this->nodes[0];
				
				foreach($result['keywords'] as $keyword)
				{
					$tag = stripslashes( $keyword['text'] );
					$tag = preg_replace( "/\./", "", $tag );
					
					if( isset($this->indexes['tags'][$tag]) )
					{
						$tag_node = $this->indexes['tags'][$tag];
					}
					else
					{
						$this->indexes['tags'][$tag] = $this->tags[] = $tag_node = (object)array('color'=>'#000000','id'=>-1,'index'=>count($this->nodes),'node_type'=>'tag','tags'=>array(),'title'=>$tag,'value'=>0);
					}
							
					if( $tag_node->value >= $this->minTagValue )
					{
						if( !array_search($tag_node,$this->nodes) )
						{
							$this->nodes[] = $tag_node;
						}
								
						$this->addTag( $tag_node, $root_node );
					}
				}
			}
		}
		
		$this->nodes[0]->description = $paragraphs;
		
		foreach($this->nodes as $node)
		{
			if( $node->node_type == self::TYPE_TAG 
				|| ($node->node_type == self::TYPE_DREAM && (isset($node->ip) && $node->ip == $_SERVER['REMOTE_ADDR'])) )
				$node->stroke = true;
			else
				$node->stroke = $node->strokeContrast = isset($node->color2) ? (hexdec(preg_replace("/#/","0x",$node->color2)) < 0x666666 ? true : false) : false;
		}
		
		$data = (object)array( 'nodes'=>$this->nodes, 'links'=>$this->links, 'dream_total'=>count($this->dreams), 'art_total'=>0 );
		
    	return $data;
    }
    
    function addTag( $tag, $node )
	{
		if( !isset($this->indexes['tags'][$tag->title]) ) return;
		
		$tag_node = $this->indexes['tags'][$tag->title];
		$tag_node->index = array_search($tag_node,$this->nodes);
		
		if( !is_null($node) )
		{
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
					
					$source->value++;
				}
			}
		}
	}
	
	function cardinalize($n)
	{
		//	> 10
		if( $n > 10 ) return $n."th";
		
		//	<= 10
		switch( (int)substr($n,strlen((string)$n)-1) )
		{
			case 0:
				return $n."th";
			case 1:
				return $n."st";
			case 2:
				return $n."nd";
			case 3:
				return $n."rd";
			default:
				return $n."th";
		}

		return $n;
	}
	
	function getNodeCount()
	{
		return count($this->nodes);
	}
}

?>
