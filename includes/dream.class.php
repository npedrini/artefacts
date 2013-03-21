<?php
include_once "includes/db.class.php";
include_once "includes/logger.class.php";
include_once "includes/alchemy/module/AlchemyAPI.php";
include_once "includes/alchemy/module/AlchemyAPIParams.php";

class Dream
{
	//	max size of file upload in bytes
	const MAX_BYTES = 3145728;
	
	public $age;
	public $color;
	public $date;
	public $description;
	public $email;
	public $gender;
	public $image;
	public $tags;
	public $title;
	public $user_id;
	
	public $file;
	
	public $alchemyApiKey;
	public $date_format;	//	TODO: set a default
	public $time_zone;
	public $status;
	
	public $logger;
	
	protected $db;
	
	function __construct() 
	{
    	$this->init();
    	
    	$this->db = new Database();
    }
    
    function init()
    {
    	$this->color = "#333333";
    	$this->description = "";
    	$this->email = "";
    	$this->gender = "female";
    	$this->tags = "";
    	$this->title = "";
    	
    	$this->logger = new Logger();
    	$this->timezone = "America/New_York";
    }
    
   function save()
	{
		$this->logger->clear();
		
		$disable_fields = false;
		
		//	validation
		$valid = $this->validate();
		
		//	get a user_id, either by adding new user or fetching id of existing
		if( $valid )
		{
			//	add user if doesn't exist in `users` table and get id
			$sql = "SELECT id FROM `users` WHERE email='".$this->email."'";
			$result = $this->db->query( $sql );
			
			if( $this->db->affected_rows > 0 )
			{
				$user = $result->fetch_assoc();
				$this->user_id = $user['id'];
				
				$this->logger->log( "user found..." );
			}
			else
			{
				$sql = "INSERT INTO `users` (email,ip) VALUES ('".$this->email."','".$_SERVER['REMOTE_ADDR']."')";
				$result = $this->db->query( $sql );
				
				$this->user_id = $this->db->insert_id;
				
				$this->logger->log( "user added..." );
			}
			
			if( !$this->user_id ) $valid = false;
		}
		
		//	require that there has been no dream for this user on this date
		/*
		if( $valid )
		{
			$sql = "SELECT id FROM `dreams` WHERE user_id='".$user_id."' AND occur_date = '".$date->format('Y-m-d')."'";
			$result = $this->db->query( $sql );
			
			if( $this->db->affected_rows > 0 )
			{
				$this->status = "It looks like this dream has already been submitted";
				$valid = false;
			}
		}
		*/
		
		if( $valid 
			&& isset($this->file)
			&& !empty($this->file["name"]) )
		{
			$extension = end( explode( ".", strtolower($this->file["name"]) ) );
			
			$mime_types = array('image/gif','image/jpeg','image/png');
			$file_extensions = array('gif','jpg','jpeg','png');
			
			if ( in_array( $this->file["type"], $mime_types )
				&& ($this->file["size"] < MAX_BYTES)
				&& in_array($extension, $file_extensions) )
			{
				if ($this->file["error"] == 0)
				{
					$image = time() . "." . $extension;
					
					if ( !move_uploaded_file( $this->file["tmp_name"], getcwd()."/images/dreams/" . $image ) )
					{
						$this->status = "Oops! We had trouble moving the image. Please try again later.";
						$valid = $disable_fields = false;
					}
				}
				else
				{
					$this->status = "Sorry, there was an error with the image: ".$this->file["error"];
					$valid = $disable_fields = false;
				}
			}
			else
			{
				$this->status = "Oops! Please upload a ".implode(', ',$extensions) . " image that is ".(self::MAX_BYTES/1024/1024)."MB or less";
				$valid = $disable_fields = false;
			}
		}
		
		//	import dream
		if( $valid )
		{
			$id = isset($this->id) ? $this->db->real_escape_string($this->id):null;
			
			if( !is_null($id) 
				&& !empty($id) )
			{
				$sql  = "UPDATE `dreams` SET user_id='".$this->user_id."',title='".$this->db->real_escape_string($this->title)."',description='".$this->db->real_escape_string($this->description)."',color='".$this->color."',image='".$this->db->real_escape_string($this->image)."',occur_date='".$this->date->format('Y-m-d')."',age='".$this->db->real_escape_string($this->age)."',gender='".$this->db->real_escape_string($this->gender)."' WHERE id ='".$id."'";
				
				$result = $this->db->query( $sql );
				
				if( $result ) 
					$dream_id = $id;	//	TODO: ?
				else
					$this->logger->log( "Error updating dream" );
			}
			
			$description = $this->db->real_escape_string($this->description);
			
			if( !isset($dream_id) )
			{
				$age = $this->db->real_escape_string($this->age);
				$color = $this->db->real_escape_string($this->color);
				$gender = $this->db->real_escape_string($this->gender);
				$image = $this->db->real_escape_string($this->image);
				$title = $this->db->real_escape_string($this->title);
				
				$date = DateTime::createFromFormat( $this->date_format, $this->date, new DateTimeZone($this->timezone) ); 
				
				$get_location = curl_init(); 
				curl_setopt($get_location, CURLOPT_URL, "http://freegeoip.net/json/");
				curl_setopt($get_location, CURLOPT_RETURNTRANSFER, 1);
				
				$location = curl_exec($get_location);
				$location = json_decode($location);
				
				$fields  = "user_id,title,description,color,image,occur_date,age,gender,";
				$fields .= "city,region,country,latitude,longitude";
				
				$values  = "'".$this->user_id."','".$title."','".$description."','".$color."','".$image."','".$date->format('Y-m-d')."','".$age."','".$gender."',";
				$values .= "'".$location->city."','".$location->region_name."','".$location->country_name."','".$location->latitude."','".$location->longitude."'";
				
				//	add dream
				$sql  = "INSERT INTO `dreams` (".$fields.") VALUES (".$values.")";
				
				$result = $this->db->query( $sql );
				$dream_id = $this->db->insert_id;
				
				if( !$result ) $this->logger->log( "Error updating dream" );
			}
			
			$this->id = $dream_id;
			
			if( !$result || !$this->id )
			{
				$this->status = "There was a problem submitting the dream.";
				
				$valid = false;
			}
			else
			{
				//	dream was added 
				//	restore form to default state by resetting values
				$this->status = "Dream added!";
				
				$this->init();
			}
			
			if( $valid ) $this->logger->log( "dream added..." );
		}
		
		//	add dream tags
		if( $valid )
		{
			$alchemy = new AlchemyAPI();
			$alchemy->setAPIKey( $this->alchemyApiKey );
			
			$params = new AlchemyAPI_KeywordParams();
			$params->setMaxRetrieve( 20 );
			$params->setKeywordExtractMode( 'strict' );
			
			$result = json_decode( $alchemy->TextGetRankedKeywords( $description, AlchemyAPI::JSON_OUTPUT_MODE, $params ) );
			
			if( $result->status == "OK" )
			{
				foreach($result->keywords as $key=>$val)
				{
					$tag = $val->text;
					$tag = preg_replace( "/\./", "", $tag );
					
					if( empty($tag) ) continue;
					
					$tag = strtolower( trim($tag) );
					$tag = $this->db->real_escape_string( $tag );
					
					//	get tag_id
					$sql = "SELECT id FROM `tags` WHERE tag='".$tag."'";
					$result = $this->db->query( $sql );
					
					if( $this->db->affected_rows > 0 )	//	tag exists
					{
						$tag_row = $result->fetch_assoc();
						$tag_id = $tag_row['id'];
					}
					else								//	tag does not exist
					{
						$sql = "INSERT INTO `tags` (tag) VALUES ('".$tag."')";
						$result = $this->db->query( $sql );
						$tag_id = $this->db->insert_id;
					}
					
					if( $tag_id )
					{
						$sql = "INSERT INTO `dream_tags` (dream_id,tag_id) VALUES ('".$dream_id."','".$tag_id."')";
						$result = $this->db->query( $sql );
					}
				}
			}
		}
		
		return $valid;
	}
	
	function validate()
	{
		$valid = true;
		
		//	validate required fields
		$required = array('date','description','email');
		
		foreach($required as $field)
			if( !isset($this->$field) || empty($this->$field) )
				$valid = false;
		
		if( $valid )
		{
			$this->status = "Please complete all the fields.";
		}
		
		//	validate email separately
		if( $valid )
		{
			if( !isset($this->email) ) 
			{
				$this->status = "Oops! Please enter your email.";
				$valid = false;
			}
			
			if( !filter_var($this->email, FILTER_VALIDATE_EMAIL) )
			{
				$this->status = "Oops! This doesn't look like a valid email.";
				$valid = false;
			}
		}
		
		/*
		//	validate for at least five tags
		if( $valid )
		{
			$tags = explode( ',', $this->tags );
			
			if( count($tags) < 5 )
			{
				$this->status = "Oops! Please enter at least five associations.";
				$valid = false;
			}
		}
		*/
		
		return $valid;
	}
	
	function setValues( $values, $file = null )
    {
    	foreach($values as $key=>$val)
    	{
    		$this->$key = $val;
    	}
    	
    	if( $file != null )
    		$this->file = $file;
    }
}

?>