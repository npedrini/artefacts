<?php
include_once "config/config.php";
include_once "includes/dbobject.class.php";

class Media extends DBObject
{
	const TABLE = "media";

	//	max size of file upload in bytes
	const MAX_BYTES = 3145728;

	const TYPE_JPG = "image/jpeg";
	const TYPE_PNG = "image/png";
	const TYPE_GIF = "image/gif";
	const TYPE_MP3 = "audio/mp3";

	public $dream_id;
	public $name;
	public $mime_type;

	public $tempFile;
	
	protected $validMimeTypes = array( self::TYPE_JPG, self::TYPE_PNG, self::TYPE_GIF, self::TYPE_MP3 );
	protected $validExtensions = array('gif','jpg','jpeg','png');
	
	function __construct( $id = null ) 
	{
		$this->fields = array('dream_id','name','mime_type');
		$this->table = self::TABLE;

		parent::__construct( $id );
    }
	
	public function save()
	{
		$success = parent::save();

		if( $success
			&& isset($this->tempFile) )
		{
			$extension = $this->getExtension();

			//	save original
			$original = $this->name . "." . $extension;
			$path = $this->getPath(null,true);

			$this->logger->log( "moving " . $this->tempFile["tmp_name"] . " to " . $path ); 
			
			$success = move_uploaded_file( $this->tempFile["tmp_name"], $path );
			
			if( $success
				&& $this->isImage() )
			{
				$thumbs = array
				(
					array('width'=>200,'suffix'=>'small'),
					array('width'=>500,'suffix'=>'med')	
				);
				
				foreach($thumbs as $thumb)
				{
					$thumb_path = $this->getPath($thumb['suffix'],true);
					
					$this->logger->log( "thumbnailing " . $this->tempFile["tmp_name"] . " to " . $thumb_path );
					
					$image = new Imagick( $path );
					$image->scaleImage( $thumb['width'], 0 );
					$image->writeImage( $thumb_path );
					$image->destroy();
				}
			}
			else if( !$success )
			{
				$this->errorMessage = "Oops! We had trouble moving the file. Please try again later.";
				
				$success = false;
			}
		}
		else
		{
			$this->status = "Sorry, there was an error with the file: ".$this->tempFile["error"];
			$success = false;
		}

		return $success;
	}

	public function delete()
	{
		$success = parent::delete();

		if( $success )
		{
			$path = $this->getPath(null,true);
			$this->logger->log( "deleting " . $path );
			
			unlink( $path );
			
			if( $this->isImage() )
			{
				unlink( getcwd() . $this->getPath('small') );
				unlink( getcwd() . $this->getPath('med') );
			}
		}
	}

	public function validate()
	{
		$valid = true;
		
		if( !in_array( $this->tempFile["type"], $this->validMimeTypes ) )
		{
			$this->errorMessage = "Oops! Please upload a ".implode(', ',$this->validExtensions);
			$valid = false;
		}
		else if( $this->tempFile["size"] > self::MAX_BYTES )
		{
			$this->errorMessage = "Oops! Please upload a file that is ".(self::MAX_BYTES/1024/1024)."MB or less";
			$valid = false;
		}
 		else if($this->tempFile["error"] != 0)
		{
			$this->errorMessage = "Sorry, there was an error with file image: ".$this->tempFile["error"];
			$valid = false;
		}

		return $valid;
	}
	
	public function isImage()
	{
		return $this->mime_type == self::TYPE_JPG || $this->mime_type == self::TYPE_PNG || $this->mime_type == self::TYPE_GIF;
	}

	public function isAudio()
	{
		return $this->mime_type == self::TYPE_MP3;
	}

	public function getExtension()
	{
		if( $this->mime_type == self::TYPE_JPG ) return "jpg";
		if( $this->mime_type == self::TYPE_PNG ) return "png";
		if( $this->mime_type == self::TYPE_GIF ) return "gif";
		if( $this->mime_type == self::TYPE_MP3 ) return "mp3";

		return null;
	}
	
	public function getPath( $size = null, $includeRoot = false )
	{
		$root = $includeRoot ? getcwd() . "/" : '';
		$extension = $this->getExtension();
		
		if( $this->isImage() 
			&& !is_null($size) )
		{
			return $root . IMAGE_PATH . $this->name . '_' . $size . '.' . $extension;
		}
		
		return $root . IMAGE_PATH . $this->name . '.' . $extension;
	}
}

?>