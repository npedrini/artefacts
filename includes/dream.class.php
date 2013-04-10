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
	public $feelings;
	public $gender;
	public $image;
	public $origin;
	public $tags;
	public $title;
	public $user_id;
	
	public $file;
	
	public $alchemyApiKey;
	public $dateFormat;	//	TODO: set a default
	public $postToTumblr;
	public $status;
	public $timezone;
	public $tumblrPostEmail;
	
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
    	$this->gender = "";
    	$this->feelings = array();
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

				if( $result )
					$this->logger->log( "user added..." );
				else
					$this->logger->log( "problem adding user..." );
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
				&& ($this->file["size"] < self::MAX_BYTES)
				&& in_array($extension, $file_extensions) )
			{
				if ($this->file["error"] == 0)
				{
					$image_name = time();
					$image_raw = $image_name . "_orig." . $extension;

					if ( !move_uploaded_file( $this->file["tmp_name"], getcwd()."/images/dreams/" . $image_raw ) )
					{
						$this->status = "Oops! We had trouble moving the image. Please try again later.";
						$valid = $disable_fields = false;
					}
					else
					{
						$this->image = $image_name . "." . $extension;
						
						$thumb = new Imagick( getcwd()."/images/dreams/" . $image_raw );
						$thumb->scaleImage( 500, 0 );
						$thumb->writeImage( getcwd() . "/images/dreams/" . $this->image );
						$thumb->destroy();
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
		
		$get_location = curl_init(); 
		curl_setopt($get_location, CURLOPT_URL, "http://freegeoip.net/json/");
		curl_setopt($get_location, CURLOPT_RETURNTRANSFER, 1);
				
		$location = curl_exec($get_location);
		$location = json_decode($location);

		//	import dream
		if( $valid )
		{
			$id = isset($this->id) ? $this->db->real_escape_string($this->id):null;
			
			$age = $this->db->real_escape_string($this->age);
			$color = $this->db->real_escape_string($this->color);
			$description = $this->db->real_escape_string($this->description);
			$email = $this->db->real_escape_string($this->email);
			$gender = $this->db->real_escape_string($this->gender);
			$image = $this->db->real_escape_string($this->image);
			$origin = $this->db->real_escape_string($this->origin);
			$title = $this->db->real_escape_string($this->title);
				
			$date = DateTime::createFromFormat( $this->dateFormat, $this->date, new DateTimeZone($this->timezone) ); 

			$fields  = array( "age", "city", "color", "country", "description", "gender", "image", "latitude", "longitude", "occur_date", "origin", "region", "title", "user_id" );
			$values  = array( $age, $location->city, $color, $location->country_name, $description, $gender, $image, $location->latitude, $location->longitude, $date->format('Y-m-d'), $origin, $location->region_name, $title, $this->user_id );

			if( !is_null($id) 
				&& !empty($id) )
			{
				$sql  = "UPDATE `dreams` SET user_id='".$this->user_id."',";
				$sql .= "age='".$this->db->real_escape_string($this->age)."',";
				$sql .= "color='".$this->color."',";
				$sql .= "description='".$this->db->real_escape_string($this->description)."',";
				$sql .= "gender='".$this->db->real_escape_string($this->gender)."',";
				$sql .= "image='".$this->db->real_escape_string($this->image)."',";
				$sql .= "occur_date='".$this->date->format('Y-m-d')."',";
				$sql .= "origin='".$this->db->real_escape_string($this->origin)."',";
				$sql .= "title='".$this->db->real_escape_string($this->title)."' ";
				$sql .= "WHERE id ='".$id."'";
				
				$result = $this->db->query( $sql );
				
				if( $result ) 
					$dream_id = $id;	//	TODO: ?
				else
					$this->logger->log( "Error updating dream" );
			}
			
			if( !isset($dream_id) )
			{
				$where = array();

				for($i=0;$i<count($fields);$i++) $where[] = $fields[$i]."='".$values[$i]."'";

				$sql = "SELECT id FROM `dreams` WHERE ".implode(" AND ",$where);
				$result = $this->db->query( $sql );

				if( $this->db->affected_rows == 0 )
				{
					//	add dream
					$sql  = "INSERT INTO `dreams` (".implode(",",$fields).") VALUES ('".implode("','",$values)."')";

					$result = $this->db->query( $sql );
					$dream_id = $this->id = $this->db->insert_id;

					if( !$result || !$this->id )
					{
						$this->status = "There was a problem submitting the dream.";
				
						$valid = false;
					}
					else
					{
						//	dream was added 
						$this->status = "Dream added!";
					}
				}
				else
				{
					$valid = false;
					$this->status = "Ooops! It looks like this dream already exists.";
				}	
			}
		}
		
		$tags = array();

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
					$tag = stripslashes( $val->text );
					$tag = preg_replace( "/^\W|\W$|\./", "", $tag );

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
						$sql = "INSERT INTO `tags` (tag,alchemy) VALUES ('".$tag."','1')";
						$result = $this->db->query( $sql );
						$tag_id = $this->db->insert_id;
					}
					
					if( $tag_id )
					{
						$sql = "INSERT INTO `dream_tags` (dream_id,tag_id) VALUES ('".$dream_id."','".$tag_id."')";
						$result = $this->db->query( $sql );
					}

					$tags[] = $tag;
				}
			}
		}
		
		if( $valid 
			&& $this->postToTumblr 
			&& $this->tumblrPostEmail != null )
		{
			$to = $this->tumblrPostEmail;
			$subject = isset($this->title)?$this->title:"untitled";
			
			$body = $this->description;
			$body .= ("\n\nDreamt on " . $this->date . " by a " . $this->age . " year old " . ($this->gender == "male" ? "man" : "woman") . " in " . $location->city);
			$body .= ("\n\nhttp://artefactsofthecollectiveunconscious.net/browse.php?did=" . $dream_id);
			
			foreach($tags as $tag) $tagline .= ("#".$tag." ");
			$body .= "\n\n".$tagline;
			
			mail( $to, $subject, $body );		
		}
	
		if( $valid )
		{
			foreach($this->feelings as $feeling_id)
			{
				if( $feeling_id )
				{
					$sql = "INSERT INTO `dream_feelings` (dream_id,feeling_id) VALUES ('".$dream_id."','".$feeling_id."')";					
					$result = $this->db->query( $sql );
				}
			}
		}
		
		if( $valid ) $this->init();
		
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

		if( !$valid )
		{
			$this->status = "Please complete all the fields.";
		}
		
		//  validate email separately
		else if( !filter_var($this->email, FILTER_VALIDATE_EMAIL) )
		{
			$this->status = "Oops! This doesn't look like a valid email.";
			$valid = false;
		}
		
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