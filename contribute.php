<?php
session_start();

include 'config/' . getenv('HTTP_APPLICATION_ENVIRONMENT') . "/config.php";

define("ART_LOG_URL","api/get-art-log.php");
define("COORD_LOG_URL","api/get-coord-log.php");
define("MONA_ROOT","http://mona-vt.artpro.net.au/");

//ini_set('error_reporting', E_ALL);

$mysqli = new mysqli( DB_HOST, DB_USER, DB_PASS );
$mysqli->select_db( DB_NAME );

/*
//	INITIALIZE AN ARTWORK
//	look for artwork_id stored in form and initialize artwork to that if set
//	(this ensures user sees same randomly-selected artwork)
if( isset($_POST['artwork_id']) )
{
	$sql = "SELECT * FROM `artworks` WHERE id = '" . $mysqli->real_escape_string( $_POST['artwork_id'] ) . "'";
	
	$result = $mysqli->query( $sql );
	
	if( $mysqli->affected_rows > 0 )
		$artwork = $result->fetch_assoc();
}

//	if artwork isn't set (via hidden `artwork_id` variable), choose one randomly
if( !isset($artwork) )
{
	$sql = "SELECT * FROM `artworks` ORDER BY RAND() LIMIT 1";
	
	$result = $mysqli->query( $sql );
	
	if( $mysqli->affected_rows > 0 ) 
		$artwork = $result->fetch_assoc();
}
*/

//	set up 'tour not found' text, appending tag artwork call to action
$errorText = "<p>Oops, we had trouble locating your tour information. Please make sure you correctly typed the email address you used when recording your tour via The O. We use this only to link your dream to your tour information, and it will not be disclosed to anyone.</p>";

//if( isset($artwork) ) $errorText .= "<p>Haven't visited MONA yet, but interested in participating? Consider helping out by telling us what associations <span class='artwork_title'><a href='tag_artwork.php?id=".$artwork["id"]."'>".htmlspecialchars($artwork["title"])."</a></span> by <span class='artwork_artist'>".$artwork["artist"]."</span> brings to mind.</p>";

//	set up date format
$date_format = DATE_FORMAT;
$date_format = preg_replace( '/{{date}}/', 'j', $date_format );
$date_format = preg_replace( '/{{month}}/', 'n', $date_format );
$date_format = preg_replace( '/{{year}}/', 'Y', $date_format );

// 	set default date to yesterday in australia
$date = new DateTime( 'now', new DateTimeZone('Australia/Melbourne') );
$date->sub( new DateInterval("P01D") );

//	whether or not to disable fields other than those related to tour retrieval
$disable_fields = true;

//	default form values
$form_values = $value_defaults = array
	(
		'age'=>'',
		/*'artwork_id'=>$artwork['id'],*/
		'color'=>'#333333',
		'date'=>$date->format($date_format),
		'description'=>'',
		'email'=>'',
		'gender'=>'female',
		'tags'=>'',
		'title'=>''
	);

if( DEBUG )
{
	$form_values = $value_defaults = array
	(
		'age'=>'33',
		'color'=>'#ff3300',
		'date'=>'19/1/2013',
		'description'=>'I am in a car with my friend Mark. Mark is totally blind and so am I. The interesting thing about this is that I am driving the car. I am driving the car from somewhere to my house. I don\'t know how I seem to know where to go, but I seem to know. I told him that I am going to drive us home and I\'m doing it. I always wished that I could drive although this is the first dream I have ever had where I\'m doing it. The main senses I used in this were hearing because I could listen to him and hear what he was saying, I could hear the other traffic around me. And feeling. I could feel the upholstery around me in the car, the steering wheel. I was driving the car, that\'s all there is to that dream.',
		'email'=>'go@looklisten.net',
		'gender'=>'female',
		'tags'=>'blind,driving,Mark,hearing,upholstery',
		'title'=>'me and mark'
	);
	
	$disable_fields = false;
}

//	max size of file upload in bytes
$max_bytes = 1024 * 1024 * 3;

//	record fact that user has visited this page (graph.json.php)
if( !isset($_SESSION['submission']) )
	$_SESSION['submission'] = 1;

//	process form submit
if( isset($_POST['submit']) )
{
	//	set values to what user submitted in case there are errors
	$form_values = $_POST;
	
	//	enable all form fields in case there are errors
	$disable_fields = false;
	
	//	validation
	$valid = true;
	
	//	validate required fields
	$required = array('date','description','email');
	
	foreach($required as $field)
		if( !isset($_POST[$field]) || empty($_POST[$field]) )
			$valid = false;
	
	//	validate email separately
	if( $valid )
	{
		if( !isset($_POST['email']) ) 
		{
			$status = "Oops! Please enter your email.";
			$valid = false;
		}
		
		if( !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL) )
		{
			$status = "Oops! This doesn't look like a valid email.";
			$valid = false;
		}
	}
	
	//	validate for at least five tags
	if( $valid )
	{
		$tags = explode( ',', $_POST['tags'] );
		
		if( count($tags) < 5 )
		{
			$status = "Oops! Please enter at least five associations.";
			$valid = false;
		}
	}
	
	//	fetch mona o data files for user/date combination
	if( $valid )
	{
		$email = $_POST['email'];
		$date = DateTime::createFromFormat( $date_format, $_POST['date'], new DateTimeZone('Australia/Melbourne') ); 
		
		//	parse art and coord log
		$art_log_url = MONA_ROOT.ART_LOG_URL.'?email='.$email.'&date='.$date->format('Y-m-d');
		$coord_log_url = MONA_ROOT.COORD_LOG_URL.'?email='.$email.'&date='.$date->format('Y-m-d');
		
		if( !http_file_exists( $art_log_url ) || !http_file_exists( $coord_log_url ) ) 
		{
			$status = "Oops! We couldn't find any tour data for the specified date and email. If you're on a slower connection, please try again when it improves.";
			$valid = false;
		}
	}
	
	//	ensure date is valid (by checking if returned files are in proper format)
	if( $valid )
	{
		//	parse art log
		$art_log = file_get_contents( $art_log_url );
		$art_log = json_decode( $art_log );
		
		$coord_log = file_get_contents( $coord_log_url );	
		$coord_log = json_decode( $coord_log );
		
		if( $art_log == NULL || $coord_log == NULL ) 
		{
			$status = "Oops! We couldn't find any tour data for the specified date and email.";
			$valid = false;
		}
	}
	
	//	get a user_id, either by adding new user or fetching id of existing
	if( $valid )
	{
		//	add user if doesn't exist in `users` table and get id
		$sql = "SELECT id FROM `users` WHERE email='".$email."'";
		$result = $mysqli->query( $sql );
		
		if( $mysqli->affected_rows > 0 )
		{
			$user = $result->fetch_assoc();
			$user_id = $user['id'];
			
			if( DEBUG ) echo "user found...";
		}
		else
		{
			$sql = "INSERT INTO `users` (email,ip) VALUES ('".$email."','".$_SERVER['REMOTE_ADDR']."')";
			$result = $mysqli->query( $sql );
			$user_id = $mysqli->insert_id;
			
			if( DEBUG ) echo "user added...";
		}
		
		if( !$user_id ) $valid = false;
	}
	
	//	TODO: validate this specific dream has not been added
	
	//	require that there has been no dream for this user on this date
	/*
	if( $valid )
	{
		$sql = "SELECT id FROM `dreams` WHERE user_id='".$user_id."' AND occur_date = '".$date->format('Y-m-d')."'";
		$result = $mysqli->query( $sql );
		
		if( $mysqli->affected_rows > 0 )
		{
			$status = "It looks like this dream has already been submitted";
			$valid = false;
		}
	}
	*/
	
	//	upload image if specified
	$image = "";
	
	if( $valid 
		&& isset($_FILES["file"])
		&& !empty($_FILES["file"]["name"]) )
	{
		$mime_types = array('image/gif','image/jpeg','image/png');
		$extensions = array('gif','jpg','jpeg','png');
		
		$extension = end( explode( ".", strtolower($_FILES["file"]["name"]) ) );
		
		if ( in_array( $_FILES["file"]["type"], $mime_types )
			&& ($_FILES["file"]["size"] < $max_bytes)
			&& in_array($extension, $extensions) )
		{
			if ($_FILES["file"]["error"] == 0)
			{
				$image = time() . "." . $extension;
				
				if ( !move_uploaded_file( $_FILES["file"]["tmp_name"], getcwd()."/images/dreams/" . $image ) )
				{
					$status = "Oops! We had trouble moving the image. Please try again later.";
					$valid = $disable_fields = false;
				}
			}
			else
			{
				$status = "Sorry, there was an error with the image: ".$_FILES["file"]["error"];
				$valid = $disable_fields = false;
			}
		}
		else
		{
			$status = "Oops! Please upload a ".implode(', ',$extensions) . " image that is ".($max_bytes/1024/1024)."MB or less";
			$valid = $disable_fields = false;
		}
	}
	
	if( DEBUG && !$valid ) echo $status."<br/>";
	
	//	import dream
	if( $valid )
	{
		$title = $mysqli->real_escape_string($_POST['title']);
		$description = $mysqli->real_escape_string($_POST['description']);
		$color = $mysqli->real_escape_string($_POST['color']);
		//$color2 = $mysqli->real_escape_string($_POST['color2']);
		$age = $mysqli->real_escape_string($_POST['age']);
		$gender = $mysqli->real_escape_string($_POST['gender']);
		
		$id = isset($_POST['id']) ? $mysqli->real_escape_string($_POST['id']):null;
		
		if( !is_null($id) && !empty($id) )
		{
			$sql  = "UPDATE `dreams` SET user_id='".$user_id."',title='".$title."',description='".$description."',color='".$color."',image='".$image."',occur_date='".$date->format('Y-m-d')."',age='".$age."',gender='".$gender."' WHERE id ='".$id."'";
			
			$result = $mysqli->query( $sql );
			
			if( $result ) 
				$dream_id = $id;
			else if( DEBUG ) 
				echo "Error updating dream<br/>";
		}
		
		if( !isset($dream_id) )
		{
			//	add dream
			$sql  = "INSERT INTO `dreams` (user_id,title,description,color,image,occur_date,age,gender) ";
			$sql .= "VALUES ('".$user_id."','".$title."','".$description."','".$color."','".$image."','".$date->format('Y-m-d')."','".$age."','".$gender."')";
			
			$result = $mysqli->query( $sql );
			$dream_id = $mysqli->insert_id;
			
			if( !$result && DEBUG ) echo "Error updating dream<br/>";
		}
		
		$form_values['id'] = $dream_id;
		
		if( !$result || !$dream_id )
		{
			$status = "There was a problem submitting the dream.";
			$valid = false;
		}
		else
		{
			//	dream was added 
			//	restore form to default state by resetting values
			$status = "Dream added!";
			$form_values = $value_defaults;
		}
		
		if( $valid )
		{
			$disable_fields = true;
			
			unset( $_SESSION['submission'] );
			
			if( DEBUG ) echo "dream added...";
		}
	}
	
	//	add dream tags
	if( $valid )
	{
		$tags = explode( ',', $_POST['tags'] );
		
		foreach($tags as $tag)
		{
			if( empty($tag) ) continue;
			
			$tag = strtolower( trim($tag) );
			$tag = $mysqli->real_escape_string( $tag );
			
			//	get tag_id
			$sql = "SELECT id FROM `tags` WHERE tag='".$tag."'";
			$result = $mysqli->query( $sql );
			
			if( $mysqli->affected_rows > 0 )	//	tag exists
			{
				$tag_row = $result->fetch_assoc();
				$tag_id = $tag_row['id'];
			}
			else								//	tag does not exist
			{
				$sql = "INSERT INTO `tags` (tag) VALUES ('".$tag."')";
				$result = $mysqli->query( $sql );
				$tag_id = $mysqli->insert_id;
			}
			
			if( $tag_id )
			{
				$sql = "INSERT INTO `dream_tags` (dream_id,tag_id) VALUES ('".$dream_id."','".$tag_id."')";
				$result = $mysqli->query( $sql );
			}
		}
	}
	
	$valid_2 = $valid;
	
	//	add visit for this user/date combination
	
	//	add visit if it hasn't been added
	if( $valid_2 )
	{
		//	require that there has been no visit for user on this date
		$sql = "SELECT id FROM `visits` WHERE user_id='".$user_id."' AND visit_date='".$date->format('Y-m-d')."'";
		$result = $mysqli->query( $sql );
		
		if( $mysqli->affected_rows == 0 ) 
		{
			//	add visit to `visits` table and get id
			$sql = "INSERT INTO `visits` (user_id,visit_date) VALUES ('".$user_id."','".$date->format('Y-m-d')."')";
			$result = $mysqli->query( $sql );
			
			$visit_id = $mysqli->insert_id;
		
			if( !$visit_id ) $valid_2 = false;
			
			if( DEBUG ) echo "visit added...";
		}
		else
		{
			$valid_2 = false;
		}
	}

	//	add visit data and any new artworks
	if( $valid_2 )
	{
		foreach($art_log as $piece_id=>$piece)
		{
			$piece_title = $piece->t;$piece_title = $piece->t;
			$piece_artist = $piece->a;
			$piece_image = $piece->m;
			
			$sql = "SELECT id FROM `artworks` WHERE id='".$piece_id."'";
			$result = $mysqli->query( $sql );
			
			if( $mysqli->affected_rows == 0 )
			{
				$sql = "INSERT INTO `artworks` (id,title,artist,image) VALUES ('".$piece_id."','".$piece_title."','".$piece_artist."','".$piece_image."')";
				$result = $mysqli->query( $sql );
				
				//	import image
				if( $result != null )
				{
					$image = $piece_image;
					
					if( !preg_match( '/.jpg/', $image ) ) $image = $image . '.jpg';
					
					//	TODO: handle placeholder image
					if( $image != null 
						&& !strpos($image,'placeholder') )
					{
						$filename = getcwd()."/images/artworks/" . $image;
						
						//	copy image
						if( copy( 'http://mona-vt.artpro.net.au/data/media/' . $image, $filename ) )
						{
							//	get average color
							$size = getimagesize( $filename );
							$target = imagecreatetruecolor( 1, 1 );
							$source = imagecreatefromjpeg( $filename );
							
							imagecopyresampled( $target, $source, 0, 0, 0, 0, 1, 1, $size[0], $size[1] );
							
							$rgb = imagecolorat($target,0,0);
							$hex = rgbtohex( ($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF );
							
							$sql = "UPDATE `artworks` SET color='".$hex."' WHERE id='".$piece_id."'";
							$result = $mysqli->query( $sql );
						}		
					}
				}
			}
		}
		
		foreach($coord_log as $stop)
		{
			if( is_null($stop->i) ) continue;
			
			$art_id = $stop->i;							//	art id
			$room = isset($stop->r) ? $stop->r : null;	//	room, not always present
			$x = $stop->x;
			$y = $stop->y;
			$z = $stop->z;								//	zone
			
			$sql = "INSERT INTO `visit_data` (visit_id,artwork_id,x,y) VALUES ('".$visit_id."','".$art_id."','".$x."','".$y."')";
			$result = $mysqli->query( $sql );
		}
	}
	
	if( !$valid )
	{
		//	validation error
		$form_values = $_POST;
		if( isset($dream_id) ) $form_values['id'] = $dream_id;
		
		if( !isset($status) ) $status = "Please complete all the fields";
	}
	else
	{
		header("Location: index.php?status=".$status."#".$date->format('j/n/Y'));
	}
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
	$f=@fopen($url,"r"); 
	
	if($f) 
	{ 
		fclose($f); 
		return true; 
	}
	
	return false; 
}

?>
<!DOCTYPE HTML>
<html>
<head>
<title>Artefacts of the Collective Unconscious</title>
<link href='http://fonts.googleapis.com/css?family=Cedarville+Cursive|Open+Sans' rel='stylesheet' type='text/css'>
<link rel="stylesheet" type="text/css" href="css/themes/<?php echo $_COOKIE['theme']==1?'black':'white'; ?>/theme.css">
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/aristo/Aristo.css">
<link rel="stylesheet" href="css/jquery.miniColors.css">
<link rel="stylesheet" href="css/tipsy.css" type="text/css">
<script type="text/javascript" src="js/lib/jquery-1.9.1.js"></script>
<script type="text/javascript" src="js/lib/jquery-ui-1.10.1.custom.min.js"></script>
<script type="text/javascript" src="js/lib/jquery.miniColors.js"></script>
<script type="text/javascript" src="js/lib/jquery.tipsy.js"></script>
<script type="text/javascript" src="js/lib/jquery.cookie.js"></script>
<script type="text/javascript">
$(document).ready
(
	function()
	{
		var df = dateFormat
					.replace( /{{date}}/, 'd' )
					.replace( /{{month}}/, 'm' )
					.replace( /{{year}}/, 'yy' );
		
		$("#datepicker").datepicker
		(
			{
				dateFormat: df,
				maxDate: '+0'
			}
		);
		
		$('input[type=minicolors]').on( 'change', function(){ onColorChange() } );
		
		$('#find').bind('click', function() { validate() } );
		
		$('span[rel=tooltip]').tipsy( { gravity:'w', offset:5 } );
		
		$('input[rel=tooltip]').tipsy( { gravity:'w', offset:5, trigger: 'manual' } );
		$('input[rel=tooltip]').focus( function() { $(this).tipsy("show"); } );
		$('input[rel=tooltip]').blur( function() { $(this).tipsy("hide"); } );
		
		$('input[rel=tooltip][type=file]').on( 'mouseover', function(){ $(this).tipsy("show"); } );
		$('input[rel=tooltip][type=file]').on( 'mouseout', function(){ $(this).tipsy("hide"); } );
		
		onColorChange();
		
		<?php if ($disable_fields) { ?>
		enableFields(false);
		<?php } ?>
	}
);

function validate()
{
	var date = $("#datepicker").datepicker( "getDate" );
	var email = $("#email").val();
	
	if( date == '' || email=='' ) 
	{
		showStatus("Please enter the date of your tour and your email");
		return;
	}
	
	date = new Date( date );
	date = date.getFullYear() + '-' + (date.getMonth() + 1) + '-' + date.getDate();
	
	responseCount = 0;
	
	var baseurl = 'http://mona-vt.artpro.net.au/';
	
	var urls = ["<?php echo ART_LOG_URL; ?>","<?php echo COORD_LOG_URL; ?>"];
	
	for(var i=0;i<urls.length;i++)
	{
		//	fetch art log
		
		var url = baseurl+urls[i];
		
		$.ajax
		(
			{
				url: 'proxy.php?csurl=' + url + '&email='+email+'&date='+date,
				success: function(data){ onValidate(data) },
				error: function(data){ onValidateError(data) },
				dataType: 'json'
			}
		);
	}
}

function onValidate(data)
{
	responseCount++;
	
	if( responseCount < 2 ) return;
	
	showStatus("Your tour has been found!");
	
	enableFields( true );
}

function onValidateError()
{
	responseCount++;
	
	if( responseCount < 2 ) return;
	
	showStatus("<?php echo $errorText; ?>");
	
	enableFields( false );
	
	console.log( 'onValidateError' );
}

function enableFields( enable )
{
	$('#icon_checkmark').removeClass('disabled enabled').addClass( enable ? 'endabled':'disabled' );
	$('#fields input,#fields textarea,#fields select').each( function(index) { $(this).attr('disabled',!enable) } );
}

function onColorChange()
{
	var input = $(this), hex = $('#colorpicker1').val();
	//$('#header > h1 > a').css('color',hex);
}

function showStatus(status)
{
	$('#status').html(status);
	$('#status').show();
}

//	http://www.alchemyapi.com/api/keyword/textc.html
//	http://access.alchemyapi.com/calls/text/TextGetRankedConcepts?apikey=b66fcdf41275b600e1f08eae25f6498e55fce606&maxRetrieve=20&outputMode=json&text=I%20am%20sitting%20in%20Sandra's%20kitchen.%20I%20know%20its%20a%20kitchen%20because%20it%20is%20very%20similar%20to%20my%20kitchen.%20The%20layout%20is%20exactly%20the%20same.%20I%20am%20eating%20some%20delicious%20sandwiches%20made%20out%20of%20crusty%20rye%20bread%20and%20ham%20and%20cheese.%20Big,%20thick%20sandwiches.%20In%20reality%20I%20have%20no%20idea%20who%20this%20Sandra%20is%20but%20in%20my%20dreams%20I%20appear%20to%20know%20her.%20She%20is%20a%20German%20woman%20with%20a%20high%20voice%20and%20a%20very%20pleasant%20accent,%20she's%20very%20friendly.%20I%20am%20enjoying%20these%20delicious%20sandwiches%20so%20much%20that%20I%20don't%20want%20to%20leave,%20but%20I%20have%20to%20catch%20a%20train.%20I%20know%20I%20have%20to%20catch%20a%20train,%20I%20keep%20telling%20Sandra%20that%20I%20have%20to%20catch%20a%20train%20but%20she%20keeps%20offering%20me%20more%20sandwiches%20and%20I%20keep%20staying%20to%20eat%20them%20even%20though%20I%20have%20to%20catch%20this%20train%20to%20where%20ever%20it%20is,%20I%20don't%20know%20where.%20It's%20not%20made%20clear%20in%20the%20dream.%20I%20remember%20the%20taste%20of%20the%20delicious%20ham%20and%20cheese%20and%20the%20rye%20bread,%20hearing%20Sandra's%20laughter%20and%20high%20pitched%20chatter,%20being%20aware%20of%20being%20in%20her%20kitchen,%20sitting%20at%20her%20table,%20touching%20the%20table%20with%20my%20fingertips,%20tapping%20my%20feet%20on%20the%20floor%20and%20this%20impending%20urgency%20to%20catch%20this%20train,%20although%20it%20wasn't%20so%20urgent,%20I%20really%20didn't%20want%20to%20leave%20because%20I%20was%20enjoying%20her%20company%20and%20the%20sandwiches%20so%20much.
/*
function getTags()
{
	clearInterval( tagTimer );
	
	$("#tag_help").hide();
	
	$.ajax
	(
		{
			dataType: 'json',
			url: 'proxy.php?csurl=http://access.alchemyapi.com/calls/text/TextGetRankedKeywords&apikey=b66fcdf41275b600e1f08eae25f6498e55fce606&maxRetrieve=20&keywordExtractMode=strict&outputMode=json&text=' + $('#description').val(),
		}
	)
	.done
	(
		function(data) 
		{
			if( data.status == "OK" )
			{
				var keywords = [];
				
				for(var i=0;i<data.keywords.length;i++)
				{
					keywords.push( data.keywords[i].text );
				}
			}
			
			$("#tags").val( keywords.join(', ') );
			
			console.log(data,data.status);
		}
	);
}

function onTagFocus()
{
	clearInterval( tagTimer );
	tagTimer = setTimeout( onTagTimerTimeout, 5000 );
}

function onTagTimerTimeout()
{
	if( $("#tags").val() == "" 
		&& $("#description").val() != "" )
	{
		$("#tag_help").show();
	}
}
*/

var dateFormat = "<?php echo DATE_FORMAT; ?>";
var responseCount;
var tagTimer;
</script>
</head>

<body>

	<?php include "includes/header.php"; ?>
	
	<div id="content" style='width:500px'>
		
		<div id="subheader">
			<p>To contribute your dream to the digital collection, please complete the information below.</p>
		</div>
		
		<div id="status" style="display:<?php echo isset($status)&&$status!=''?'block':'none';?>"><?php echo isset($status)?$status:null; ?></div>
		
		<form method="post" enctype="multipart/form-data">
			
			<input type="hidden" name="id" value="<?php echo isset($form_values['id'])?$form_values['id']:''; ?>"  />
			
			<!--
			<input type="hidden" name="artwork_id" value="<?php echo isset($form_values['artwork_id'])?$form_values['artwork_id']:''; ?>"  />
			-->
			
			<div class="module">
				
				<div class='title'>Tour Information</div>
				
				<div class='body'>
						
					<div class="row">
						<label style="float:left" class="emphasized" for="datepicker">When was your tour?</label> <span class="help" rel="tooltip" title="Our site uses the data you recorded and saved on 'The O' while on your tour of MONA. You need to enter the exact date of your tour in order for your dream to show up in the oneirogram.">Why?</span>
						<div style='vertical-align: middle;clear:both;'>
							<input 
								id="datepicker" type="text" name="date" class="date big"
								value="<?php echo $form_values['date']; ?>" style='width:350px;display:inline;vertical-align: middle' />
							<div id="icon_checkmark" class='disabled' style='margin-left:20px;display:inline;vertical-align: middle'></div>
						</div>
						<div style='font-size:.7em;margin-top:10px;'>Can't remember? Click <a href="http://mona-vt.artpro.net.au/theo.php" target="_blank">here</a> to view your tour(s).</div>
					</div>
					
					<div class="row">
						<label style="float:left" class="emphasized" for="email">What email did you use on your tour?</label> <span class="help" rel="tooltip" title="We only ask for this initially so we can correlate your tour information with your dream and don't use it for any other purpose.">Why?</span>
						<input 
							id="email" type="text" name="email" class="big"
							placeholder="my@dream.com" 
							value="<?php echo $form_values['email']; ?>" style="clear:both;width:350px;display:inline-block;width:350px;"></input>
						<input id="find" type="button" value="Find" style="display:inline-block;width:60px;height:35px" /> 
					</div>
				</div>				
			</div>
			
			<div class="module" id="fields">
				
				<div class='title'>Dream Information</div>
				
				<div class="body">
				
					<div class="row">
						<label class="emphasized" for="description">Describe your dream</label>
						<textarea class="big" id="description" name="description" rows="8" placeholder=""><?php echo $form_values['description']; ?></textarea>
					</div>
					
					<div class="row">
						<label for="tags">Title of your dream</label>
						<input 
							id="title" type="text" name="title" class="big"
							maxlength="255"
							placeholder=""
							value="<?php echo $form_values['title']; ?>" 
							rel="tooltip" title="" />
					</div>
					
					<div>
						<label style="float:left" class="emphasized" for="tags">Five associations</label> <span class="help" rel="tooltip" title="'Associations' are words or short phrases that spontaneously come to mind in response to the memory of a dream or any other kind of phenomenon that we experience when awake.">What?</span>
						<input 
							id="tags" type="text" name="tags" class="big"
							placeholder=""
							value="<?php echo $form_values['tags']; ?>" />
						<!--<div id="tag_help" style='font-size:.7em;margin-top:5px'>Give up? Click <a href='javascript:getTags()'>here</a> for some help.</div>-->
					</div>
					
					<div class="row">
						<label class="emphasized" for="description">A colour associated with your dream</label>
						<div>
							<input 
								id="colorpicker1" class="colorpicker" name="color" 
								type="minicolors" data-textfield="false"
								value="<?php echo $form_values['color']; ?>"  />
							<!--
							<input 
								id="colorpicker2" class="colorpicker" name="color2" 
								type="minicolors" data-textfield="false"
								value="<?php echo $form_values['color2']; ?>" />
							-->
						</div>
					</div>
					
					<div class="row">
						<label for="file">An image of your dream</label>
						<input 
							id="file" type="file" name="file" class="big"
							rel="tooltip" title=".jpg, .png or .gif under <?php echo ($max_bytes/1024/1024); ?>MB" style="width:200px" />
					</div>
					
					<div class="row">
						<div style="vertical-align:top">
							
							<label for="gender">Gender you identify as</label>
							<div style='display:inline-block'><input id='gender_male' type='radio' name='gender'<?php echo $form_values['gender']=='male'?' checked':null ?> value="male"><label style='display:inline-block' for="gender_male">Male</label></div>
							<div style='display:inline-block'><input id='gender_female' type='radio' name='gender'<?php echo $form_values['gender']=='female'?' checked':null ?> value="female"><label style='display:inline-block' for="gender_female">Female</label></div>
							<div style='display:inline-block'><input id='gender_other' type='radio' name='gender'<?php echo $form_values['gender']=='other'?' checked':null ?> value="other"><label style='display:inline-block' for="gender_other">Other</label></div>
	
						</div>
					</div>
					
					<div class="row">
						<div style="vertical-align:top">
							<label for="age">Age</label>
							<input 
								id="age" type="text" name="age" class="big"
								placeholder=""
								value="<?php echo $form_values['age']; ?>" 
								rel="tooltip" title="" style="width:100px" />
						</div>
					</div>
					
					<div class="row">
						<input type="submit" name="submit" value="Contribute" />
					</div>
				
			</div>	
					
		</form>
	
	</div>
	
</body>
</html>