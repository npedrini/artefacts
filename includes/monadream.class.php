<?php
include_once "includes/dream.class.php";

class MonaDream extends Dream
{
	const MONA_ROOT = "http://mona-vt.artpro.net.au/";
	const ART_LOG_URL = "api/get-art-log.php";
	const COORD_LOG_URL = "api/get-coord-log.php";
	
	public $artwork_id;
	public $art_log;
	public $coord_log;
	
	function save()
	{
		//	validation
		$valid = $this->validate();
		
		//	fetch mona o data files for user/date combination
		if( $valid )
		{
			$email = $this->email;
			$date = DateTime::createFromFormat( $this->date_format, $this->date, new DateTimeZone($this->timezone) ); 
			
			//	parse art and coord log
			$art_log_url = self::MONA_ROOT.self::ART_LOG_URL.'?email='.$email.'&date='.$date->format('Y-m-d');
			$coord_log_url = self::MONA_ROOT.self::COORD_LOG_URL.'?email='.$email.'&date='.$date->format('Y-m-d');
			
			if( !$this->http_file_exists( $art_log_url ) 
				|| !$this->http_file_exists( $coord_log_url ) ) 
			{
				$this->status = "Oops! We couldn't find any tour data for the specified date and email. If you're on a slower connection, please try again when it improves.";
				$valid = false;
			}
		}
		
		//	ensure date is valid (by checking if returned files are in proper format)
		if( $valid )
		{
			//	parse art log
			$this->art_log = file_get_contents( $art_log_url );
			$this->art_log = json_decode( $this->art_log );
			
			$this->coord_log = file_get_contents( $coord_log_url );	
			$this->coord_log = json_decode( $this->coord_log );
			
			if( $this->art_log == NULL || $this->coord_log == NULL ) 
			{
				$this->status = "Oops! We couldn't find any tour data for the specified date and email.";
				$valid = false;
			}
		}
		
		if( $valid ) $valid = parent::save();
		
		if( $valid )
		{
			//	add visit if it hasn't been added
			if( $valid )
			{
				$date = DateTime::createFromFormat( $this->date_format, $this->date, new DateTimeZone($this->timezone) ); 
				
				//	require that there has been no visit for user on this date
				$sql = "SELECT id FROM `visits` WHERE user_id='".$this->user_id."' AND visit_date='".$date->format('Y-m-d')."'";
				$result = $this->db->query( $sql );
				
				if( $this->db->affected_rows == 0 ) 
				{
					//	add visit to `visits` table and get id
					$sql = "INSERT INTO `visits` (user_id,visit_date) VALUES ('".$this->user_id."','".$date->format('Y-m-d')."')";
					$result = $this->db->query( $sql );
					
					$visit_id = $this->db->insert_id;
					
					if( !$visit_id ) $valid = false;
					
					$this->logger->log( "visit added..." );
				}
				else
				{
					$valid = false;
				}
			}
		
			//	add visit data and any new artworks
			if( $valid )
			{
				foreach($this->art_log as $piece_id=>$piece)
				{
					$piece_title = $piece->t;
					$piece_artist = $piece->a;
					$piece_image = $piece->m;
					
					$sql = "SELECT id FROM `artworks` WHERE id='".$piece_id."'";
					$result = $this->db->query( $sql );
					
					if( $this->db->affected_rows == 0 )
					{
						$sql = "INSERT INTO `artworks` (id,title,artist,image) VALUES ('".$piece_id."','".$piece_title."','".$piece_artist."','".$piece_image."')";
						$result = $this->db->query( $sql );
						
						//	import image
						if( $result != null )
						{
							$image = $piece_image;
							
							if( !preg_match( '/.jpg/', $image ) ) $image = $image . '.jpg';
							
							//	TODO: handle placeholder image
							if( $image != null 
								&& !strpos($image,'placeholder') )
							{
								$filename = getcwd() . "/images/artworks/" . $image;
								
								//	copy image
								if( copy( self::MONA_ROOT . 'data/media/' . $image, $filename ) )
								{
									//	get average color
									$size = getimagesize( $filename );
									$target = imagecreatetruecolor( 1, 1 );
									$source = imagecreatefromjpeg( $filename );
									
									imagecopyresampled( $target, $source, 0, 0, 0, 0, 1, 1, $size[0], $size[1] );
									
									$rgb = imagecolorat($target,0,0);
									$hex = $this->rgbtohex( ($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF );
									
									$sql = "UPDATE `artworks` SET color='".$hex."' WHERE id='".$piece_id."'";
									$result = $this->db->query( $sql );
								}		
							}
						}
					}
				}
				
				foreach($this->coord_log as $stop)
				{
					if( is_null($stop->i) ) continue;
					
					$art_id = $stop->i;							//	art id
					$room = isset($stop->r) ? $stop->r : null;	//	room, not always present
					$x = $stop->x;
					$y = $stop->y;
					$z = $stop->z;								//	zone
					
					$sql = "INSERT INTO `visit_data` (visit_id,artwork_id,x,y) VALUES ('".$visit_id."','".$art_id."','".$x."','".$y."')";
					$result = $this->db->query( $sql );
				}
			}
		}
		
		return $valid;
	}
	
	function rgbtohex($r, $g, $b) 
	{
		$hex = "#";
		$hex.= str_pad(dechex($r), 2, "0", STR_PAD_LEFT);
		$hex.= str_pad(dechex($g), 2, "0", STR_PAD_LEFT);
		$hex.= str_pad(dechex($b), 2, "0", STR_PAD_LEFT);
	
		return $hex;
	}
	
	function http_file_exists($url) 
	{ 
		$f = @fopen($url,"r"); 
		
		if($f) 
		{ 
			fclose($f); 
			return true; 
		}
		
		return false; 
	}
}