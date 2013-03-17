<?php
include_once "includes/db.class.php";
include_once "includes/monadream.class.php";
include_once 'config/' . getenv('HTTP_APPLICATION_ENVIRONMENT') . "/config.php";

session_start();

$database = new Database();
$dream = new MonaDream();

//	set up 'tour not found' text, appending tag artwork call to action
$errorText = "<p>Oops, we had trouble locating your tour information. Please make sure you correctly typed the email address you used when recording your tour via The O. We use this only to link your dream to your tour information, and it will not be disclosed to anyone.</p>";

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

//if( isset($artwork) ) $errorText .= "<p>Haven't visited MONA yet, but interested in participating? Consider helping out by telling us what associations <span class='artwork_title'><a href='tag_artwork.php?id=".$artwork["id"]."'>".htmlspecialchars($artwork["title"])."</a></span> by <span class='artwork_artist'>".$artwork["artist"]."</span> brings to mind.</p>";

//	set up date format
$date_format = DATE_FORMAT;
$date_format = preg_replace( '/{{date}}/', 'j', $date_format );
$date_format = preg_replace( '/{{month}}/', 'n', $date_format );
$date_format = preg_replace( '/{{year}}/', 'Y', $date_format );

// 	set default date to yesterday in australia
$date = new DateTime( 'now', new DateTimeZone( TIME_ZONE ) );
$date->sub( new DateInterval("P01D") );

$dream->alchemyApiKey = ALCHEMY_API_KEY;
$dream->date_format = $date_format;
$dream->timezone = TIME_ZONE;

if( DEBUG )
{
	$dream->age = '33';
	$dream->color = '#ff3300';
	$dream->date = '19/1/2013';
	$dream->description = 'I am in a car with my friend Mark. Mark is totally blind and so am I. The interesting thing about this is that I am driving the car. I am driving the car from somewhere to my house. I don\'t know how I seem to know where to go, but I seem to know. I told him that I am going to drive us home and I\'m doing it. I always wished that I could drive although this is the first dream I have ever had where I\'m doing it. The main senses I used in this were hearing because I could listen to him and hear what he was saying, I could hear the other traffic around me. And feeling. I could feel the upholstery around me in the car, the steering wheel. I was driving the car, that\'s all there is to that dream.';
	$dream->email = 'go@looklisten.net';
	$dream->gender = 'female';
	$dream->tags = 'blind,driving,Mark,hearing,upholstery';
	$dream->title = 'me and mark';
}
else
{
	$dream->date = $date->format($date_format);
}

//	whether or not to disable fields other than those related to tour retrieval
$disable_fields = true;

//	record fact that user has visited this page (graph.json.php)
if( !isset($_SESSION['submission']) )
	$_SESSION['submission'] = 1;

//	process form submit
if( isset($_POST['submit']) )
{
	//	set values to what user submitted in case there are errors
	$dream->setValues( $_POST );
	
	//	enable all form fields in case there are errors
	$disable_fields = false;
	
	//	save
	$success = $dream->save();
	
	if( DEBUG )
	{
		echo "<pre>";
		print_r( $dream->logger->log );
		echo "</pre>";
	}
	
	$status = $dream->status;
	
	if( !$success )
	{
		//	validation error
		$dream->setValues( $_POST );
		
		//if( isset($dream_id) ) $dream->id = $dream_id;
		
		if( !isset($status) ) $status = "Please complete all the fields";
	}
	else
	{
		unset( $_SESSION['submission'] );
		
		$disable_fields = true;
		$date = DateTime::createFromFormat( $date_format, $dream->date, new DateTimeZone(TIME_ZONE) ); 
		
		header("Location: index.php?status=".$status."#".$date->format('j/n/Y'));
	}
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
	
	var urls = ["<?php echo MonaDream::ART_LOG_URL; ?>","<?php echo MonaDream::COORD_LOG_URL; ?>"];
	
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
	
	showStatus( "<?php echo $errorText; ?>" );
	
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
			
			<input type="hidden" name="id" value="<?php echo isset($dream->id)?$dream->id:''; ?>"  />
			
			<div class="module">
				
				<div class='title'>Tour Information</div>
				
				<div class='body'>
						
					<div class="row">
						<label style="float:left" class="emphasized" for="datepicker">When was your tour?</label> <span class="help" rel="tooltip" title="Our site uses the data you recorded and saved on 'The O' while on your tour of MONA. You need to enter the exact date of your tour in order for your dream to show up in the oneirogram.">Why?</span>
						<div style='vertical-align: middle;clear:both;'>
							<input 
								id="datepicker" type="text" name="date" class="date big"
								value="<?php echo $dream->date; ?>" style='width:350px;display:inline;vertical-align: middle' />
							<div id="icon_checkmark" class='disabled' style='margin-left:20px;display:inline;vertical-align: middle'></div>
						</div>
						<div style='font-size:.7em;margin-top:10px;'>Can't remember? Click <a href="http://mona-vt.artpro.net.au/theo.php" target="_blank">here</a> to view your tour(s).</div>
					</div>
					
					<div class="row">
						<label style="float:left" class="emphasized" for="email">What email did you use on your tour?</label> <span class="help" rel="tooltip" title="We only ask for this initially so we can correlate your tour information with your dream and don't use it for any other purpose.">Why?</span>
						<input 
							id="email" type="text" name="email" class="big"
							placeholder="my@dream.com" 
							value="<?php echo $dream->email; ?>" style="clear:both;width:350px;display:inline-block;width:350px;"></input>
						<input id="find" type="button" value="Find" style="display:inline-block;width:60px;height:35px" /> 
					</div>
				</div>				
			</div>
			
			<div class="module" id="fields">
				
				<div class='title'>Dream Information</div>
				
				<div class="body">
				
					<div class="row">
						<label class="emphasized" for="description">Describe your dream</label>
						<textarea class="big" id="description" name="description" rows="8" placeholder=""><?php echo $dream->description; ?></textarea>
					</div>
					
					<div class="row">
						<label for="tags">Title of your dream</label>
						<input 
							id="title" type="text" name="title" class="big"
							maxlength="255"
							placeholder=""
							value="<?php echo $dream->title; ?>" 
							rel="tooltip" title="" />
					</div>
					
					<!--
					<div>
						<label style="float:left" class="emphasized" for="tags">Five associations</label> <span class="help" rel="tooltip" title="'Associations' are words or short phrases that spontaneously come to mind in response to the memory of a dream or any other kind of phenomenon that we experience when awake.">What?</span>
						<input 
							id="tags" type="text" name="tags" class="big"
							placeholder=""
							value="<?php echo $dream->tags; ?>" />
					</div>
					-->
					
					<div class="row">
						<label class="emphasized" for="description">A colour associated with your dream</label>
						<div>
							<input 
								id="colorpicker1" class="colorpicker" name="color" 
								type="minicolors" data-textfield="false"
								value="<?php echo $dream->color; ?>" />
						</div>
					</div>
					
					<div class="row">
						<label for="file">An image of your dream</label>
						<input 
							id="file" type="file" name="file" class="big"
							rel="tooltip" title=".jpg, .png or .gif under <?php echo ($dream->max_bytes/1024/1024); ?>MB" style="width:200px" />
					</div>
					
					<div class="row">
						<div style="vertical-align:top">
							<label for="gender">Gender you identify as</label>
							<div style='display:inline-block'><input id='gender_male' type='radio' name='gender'<?php echo $dream->gender=='male'?' checked':null ?> value="male"><label style='display:inline-block' for="gender_male">Male</label></div>
							<div style='display:inline-block'><input id='gender_female' type='radio' name='gender'<?php echo $dream->gender=='female'?' checked':null ?> value="female"><label style='display:inline-block' for="gender_female">Female</label></div>
							<div style='display:inline-block'><input id='gender_other' type='radio' name='gender'<?php echo $dream->gender=='other'?' checked':null ?> value="other"><label style='display:inline-block' for="gender_other">Other</label></div>
						</div>
					</div>
					
					<div class="row">
						<div style="vertical-align:top">
							<label for="age">Age</label>
							<input 
								id="age" type="text" name="age" class="big"
								placeholder=""
								value="<?php echo $dream->age; ?>" 
								rel="tooltip" title="" style="width:100px" />
						</div>
					</div>
					
					<div class="row">
						<input type="submit" name="submit" value="Contribute" />
					</div>
					
				</div>
				
			</div>	
					
		</form>
	
	</div>
	
</body>
</html>