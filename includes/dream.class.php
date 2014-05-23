<?php
include_once "includes/dbobject.class.php";
include_once "includes/media.class.php";
include_once "includes/user.class.php";
include_once "includes/alchemyapi_php/alchemyapi.php";

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
	public $username;
	
	public $alchemyApiKey;
	public $useAlchemy;
	public $audioUpload;
	public $dateFormat;	//	TODO: set a default
	public $imageUpload;
	public $isImport;
	public $postToTumblr;
	public $status;
	public $timezone;
	public $tumblrPostEmail;
	
	function __construct( $id = null, $db = null ) 
	{
		$this->fields = array('age','city','color','country','description','gender','latitude','longitude','occur_date','origin','region','title','user_id');
    	$this->table = self::TABLE;

    	parent::__construct( $id, $db );
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
		$this->useAlchemy = true;
    }

	public function load()
	{
		$success = parent::load();

		if( $success )
		{
			$user = new User($this->user_id,$this->db);
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
				$this->media[] = new Media( $row['id'], $this->db );
			}
		}
	}
	
  	public function save( $validate = true )
	{
		$this->logger->clear();
		
		//	validation
		$valid = !$validate || $this->validate();
		
		//	get a user_id, either by adding new user or fetching id of existing
		if( $valid )
		{
			$user = new User( null, $this->db );
			$user->email = $this->email;
			$user->name = $this->username;
			
			if( !is_null($user->email) && !empty($user->email) )
				$user->loadFromEmail();
			else if( !is_null($user->name) && !empty($user->name) )
				$user->loadFromName();
			
			if( $this->isImport )
				$user->implied = 1;
				
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

			$image = new Media(null,$this->db);
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
			
			$audio = new Media(null,$this->db);
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
			else
			{
				$this->latitude = "0";
				$this->longitude = "0";
			}
			
			$success = parent::save();
			
			if( $success )
			{
				$this->status = "Dream added!";
			}
			else
			{
				if( isset($this->errorMessage) )
					$this->status = $this->errorMessage;
				else
					$this->status = "Error updating dream";
				
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
			&& $this->useAlchemy )
		{
			$kb = strlen($this->description) / 1024;
			
			if( $kb <= 150 )
			{
				$alchemy = new AlchemyAPI($this->alchemyApiKey);
				
				$params = array();
				$params['maxRetrieve'] = 20;
				$params['keywordExtractMode'] = 'strict';
				$params['sentiment'] = 1;
				$params['showSourceText'] = 0;
				
				try
				{
					$result = $alchemy->keywords( 'text', $this->description, $params );
					$this->logger->log( "alchemy " . $result['status'] . ", " . count($result['keywords']) . " keywords, " . $this->description );
				}
				catch(Exception $e)
				{
					$this->logger->log( "alchemy, " . $result['status'] . ", " . $result['statusInfo'] );
				}
				
				if( isset($result)
					&& $result['status'] == "OK" )
				{
					foreach($result['keywords'] as $keyword)
					{
						$tag = stripslashes( $keyword['text'] );
						$tag = preg_replace( "/^\W|\W$|\./", "", $tag );

						$tag = strtolower( trim($tag) );
						$tag = $this->db->real_escape_string( $tag );
						
						//	get tag_id
						$sql = "SELECT id FROM `tags` WHERE tag='".$tag."'";
						$result2 = $this->db->query( $sql );
						
						if( $this->db->affected_rows > 0 )	//	tag exists
						{
							$tag_row = $result2->fetch_assoc();
							$tag_id = $tag_row['id'];
						}
						else								//	tag does not exist
						{
							$sql = "INSERT INTO `tags` (tag,alchemy) VALUES ('".$tag."','1')";
							$this->db->query( $sql );
							$tag_id = $this->db->insert_id;
						}
						
						if( $tag_id )
						{
							$sentiment = $keyword['sentiment'];
							
							$sql = "INSERT INTO `dream_tags` (dream_id,tag_id,sentiment_type,sentiment_score) VALUES ('".$this->id."','".$tag_id."','".$sentiment['type']."','".(isset($sentiment['score'])?$sentiment['score']:0)."')";
							$this->db->query( $sql );
						}
	
						$tags[] = $tag;
					}
				}
			}
			else
			{
				$this->logger->log( "Dream " . $this->id . " to big to process" . $kb );
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