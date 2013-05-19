<?php
include_once "includes/dbobject.class.php";
include_once "includes/media.class.php";
include_once "includes/user.class.php";
include_once "includes/alchemy/module/AlchemyAPI.php";
include_once "includes/alchemy/module/AlchemyAPIParams.php";

class Dream extends DBObject
{
	const TABLE = "dreams";

	public $age;
	public $city;
	public $color;
	public $country;
	public $date;
	public $description;
	public $gender;
	public $latitude;
	public $longitude;
	public $ip;
	public $occur_date;
	public $origin;
	public $region;
	public $title;
	public $user_id;
	
	public $media;
	public $feelings;
	public $tags;

	public $email;

	public $alchemyApiKey;
	public $audioUpload;
	public $dateFormat;	//	TODO: set a default
	public $imageUpload;
	public $postToTumblr;
	public $status;
	public $timezone;
	public $tumblrPostEmail;
	
	function __construct( $id = null ) 
	{
		$this->fields = array('age','city','color','country','description','gender','latitude','longitude','occur_date','origin','region','title','user_id');
    	$this->table = self::TABLE;
    	
    	parent::__construct( $id );
    }
    
	protected function init()
	{
		parent::init();
		
		$this->color = "#333333";
    	$this->description = "";
    	$this->email = "";
    	$this->gender = "";
    	$this->feelings = array();
    	$this->tags = array();
    	$this->title = "";

		$this->timezone = "America/New_York";
    }

	public function load()
	{
		$success = parent::load();

		if( $success )
		{
			$user = new User($this->user_id);
			$this->ip = $user->ip;
			
			//	load tags
			$sql  = "SELECT tags.tag,tags.id FROM `dream_tags` LEFT JOIN tags ON dream_tags.tag_id=tags.id ";
			$sql .= "WHERE dream_tags.dream_id='" . $this->id . "'";
			$result = $this->db->query( $sql );
			
			$this->tags = array();
			
			while( $row = $result->fetch_assoc() ) 
			{
				$this->tags[] = (object)$row;
			}

			//	load media
			$sql  = "SELECT * FROM `" . Media::TABLE . "` ";
			$sql .= "WHERE dream_id='" . $this->id . "'";
			
			$result = $this->db->query( $sql );
			
			$this->media = array();
			
			while( $row = $result->fetch_assoc() ) 
			{
				$this->media[] = new Media( $row['id'] );
			}
		}
	}
	
  	public function save()
	{
		$this->logger->clear();
		
		//	validation
		$valid = $this->validate();

		//	get a user_id, either by adding new user or fetching id of existing
		if( $valid )
		{
			$user = new User();
			$user->email = $this->email;
			$user->loadFromEmail();

			if( $user->id )
			{
				$this->logger->log( "user found..." );
			}
			else
			{
				$user->ip = $_SERVER['REMOTE_ADDR'];
			}

			$success = $user->save();

			$this->user_id = $user->id;
			
			if( is_null($this->user_id) ) $valid = false;
		}
		
		if( $valid 
			&& isset($this->imageUpload)
			&& !empty($this->imageUpload["name"]) 
			&& $this->imageUpload["error"] == 0 )
		{
			$this->logger->log( "saving image..." );

			$image = new Media();
			$image->name = time();
			$image->mime_type = $this->imageUpload['type'];
			$image->tempFile = $this->imageUpload;
			
			if( $image->validate() )
			{
				if( $image->save() )
				{
					$this->logger->log( "image saved..." );
				}
				else
				{
					$this->status = $image->errorMessage;
					$valid = false;

					$this->logger->log( "error saving image..." );
				}				
			}
			else
			{
				$this->status = $image->errorMessage;
				$valid = false;
			}
		}

		if( $valid 
			&& isset($this->audioUpload)
			&& !empty($this->audioUpload["name"]) 
			&& $this->audioUpload["error"] == 0 )
		{
			$this->logger->log( "saving audio..." );
			
			$audio = new Media();
			$audio->name = time();
			$audio->mime_type = $this->audioUpload['type'];
			$audio->tempFile = $this->audioUpload;
			
			if( $audio->validate() )
			{
				if( $audio->save() )
				{
					$this->logger->log( "audio saved..." );
				}
				else
				{
					$this->status = $audio->errorMessage;
					$valid = false;
			
					$this->logger->log( "error saving audio..." );
				}				
			}
			else
			{
				$this->status = $audio->errorMessage;
				$valid = false;

				$this->logger->log( "error validating audio..." );
			}
		}
		
		$get_location = curl_init();
		curl_setopt($get_location, CURLOPT_URL, "http://freegeoip.net/json/" . $_SERVER['REMOTE_ADDR'] );
		curl_setopt($get_location, CURLOPT_RETURNTRANSFER, 1);

		$location = curl_exec($get_location);
		$location = json_decode($location);

		//	import dream
		if( $valid )
		{
			$date = DateTime::createFromFormat( $this->dateFormat, $this->date, new DateTimeZone($this->timezone) ); 
			$this->occur_date = $date->format('Y-m-d');
			
			if( $location )
			{
				$this->city = $location->city;
				$this->country = $location->country_name;
				$this->latitude = $location->latitude;
				$this->longitude = $location->longitude;
			}

			$success = parent::save();
			
			if( $success )
			{
				$this->status = "Dream added!";
			}
			else
			{
				$this->status = isset($this->errorMessage)?$this->errorMessage:"Error updating dream";
				$valid = false;
			}
		}
		
		if( isset($image) )
		{
			if( !is_null($this->id) 
				&& !empty($this->id) )
			{
				$image->dream_id = $this->id;
				$image->save();
			}
			else
			{
				$image->delete();
			}

			$this->logger->log( $image->logger->log );
		}

		if( isset($audio) )
		{
			if( !is_null($this->id) 
				&& !empty($this->id) )
			{
				$audio->dream_id = $this->id;
				$audio->save();
			}
			else
			{
				$audio->delete();
			}

			$this->logger->log( $audio->logger->log );
		}

		$tags = array();

		//	add dream tags
		if( $valid 
			&& !is_null($this->alchemyApiKey) )
		{
			$alchemy = new AlchemyAPI();
			$alchemy->setAPIKey( $this->alchemyApiKey );
			
			$params = new AlchemyAPI_KeywordParams();
			$params->setMaxRetrieve( 20 );
			$params->setKeywordExtractMode( 'strict' );
			
			$result = json_decode( $alchemy->TextGetRankedKeywords( $this->description, AlchemyAPI::JSON_OUTPUT_MODE, $params ) );
			
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
						$sql = "INSERT INTO `dream_tags` (dream_id,tag_id) VALUES ('".$this->id."','".$tag_id."')";
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
			$body .= ("\n\nhttp://artefactsofthecollectiveunconscious.net/browse.php?did=" . $this->id);
			
			$tagline = '';
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
					$sql = "INSERT INTO `dream_feelings` (dream_id,feeling_id) VALUES ('".$this->id."','".$feeling_id."')";					
					$result = $this->db->query( $sql );
				}
			}
		}
		
		if( $valid ) $this->init();
		
		return $valid;
	}
	
	protected function validate()
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
	
	protected function getErrorMessage( $id )
	{
		if( $id == self::ERROR_RECORD_EXISTS )
			return "Ooops! It looks like this dream already exists.";
		
		return parent::getErrorMessage($id);
	}

	public function getAudio()
	{
		foreach($this->media as $media)
			if( $media->isAudio() )
				return $media;
	}

	public function getImage()
	{
		foreach($this->media as $media)
			if( $media->isImage() )
				return $media;
	}

	public function setValues( $values, $imageUpload = null, $audioUpload = null )
    {
    	foreach($values as $key=>$val)
    	{
    		$this->$key = $val;
    	}
    	
    	if( $imageUpload != null )
    		$this->imageUpload = $imageUpload;

		if( $audioUpload != null )
    		$this->audioUpload = $audioUpload;
    }
}

?>