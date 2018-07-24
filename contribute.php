<?php
include_once "includes/session.php";

include_once "config/config.php";
include_once "includes/db.class.php";
include_once "includes/dream.class.php";
include_once "includes/media.class.php";

$formDatabase = new Database();

//	set up 'tour not found' text, appending tag artwork call to action
$errorText = "<p>Oops, we had trouble locating your tour information. Please make sure you correctly typed the email address you used when recording your tour via The O. We use this only to link your dream to your tour information, and it will not be disclosed to anyone.</p>";

//	set up date format
$date_format = DATE_FORMAT;
$date_format = preg_replace( '/{{date}}/', 'j', $date_format );
$date_format = preg_replace( '/{{month}}/', 'n', $date_format );
$date_format = preg_replace( '/{{year}}/', 'Y', $date_format );

// 	set default date to yesterday in australia
$date = new DateTime( 'now', new DateTimeZone( TIME_ZONE ) );
$date->sub( new DateInterval("P01D") );

$dreamDefault = new Dream();
$dreamDefault->alchemyApiKey = ALCHEMY_API_KEY;
$dreamDefault->dateFormat = $date_format;
$dreamDefault->origin = isset($_SESSION['origin'])?$_SESSION['origin']:null;
$dreamDefault->postToTumblr = POST_TO_TUMBLR;
$dreamDefault->timezone = TIME_ZONE;
$dreamDefault->tumblrPostEmail = TUMBLR_POST_EMAIL;
$dreamDefault->date = $date->format($date_format);

if( false )
{
	$testValues = array
	(
		'age' => '33',
		'color' => '#ff3300',
		'date' => '19/1/2013',
		'description' => 'I am in a car with my friend Mark. Mark is totally blind and so am I. The interesting thing about this is that I am driving the car. I am driving the car from somewhere to my house. I don\'t know how I seem to know where to go, but I seem to know. I told him that I am going to drive us home and I\'m doing it. I always wished that I could drive although this is the first dream I have ever had where I\'m doing it. The main senses I used in this were hearing because I could listen to him and hear what he was saying, I could hear the other traffic around me. And feeling. I could feel the upholstery around me in the car, the steering wheel. I was driving the car, that\'s all there is to that dream.',
		'email' => 'go@looklisten.net',
		'gender' => 'female',
		'feelings' => array('6','5'),
		'tags' => 'blind,driving,Mark,hearing,upholstery',
		'title' => 'me and mark'
	);

	$dreamDefault->setValues( $testValues );
}

$dream = $dreamDefault;

//	whether or not to disable fields other than those related to tour retrieval
$disable_fields = true;

//	record fact that user has visited this page (graph.json.php)
if( !isset($_SESSION['submission']) )
	$_SESSION['submission'] = 1;

$formData = EMBEDDED ? $_GET : $_POST;

//	process form submit
if( isset($formData['submit']) )
{
	$path = parse_url($_SERVER["REQUEST_URI"]);

	$url = "http://" . $_SERVER['HTTP_HOST'] . (isset($path['path']) ? substr($path['path'],0,strrpos($path['path'],'/')) : '') . "/api/dream/".(isset($formData['id'])?$formData['id']:'');

	foreach($_FILES as $key=>$file)
		if( $file['tmp_name'] )
			$formData[$key] = '@' . $file['tmp_name'] . ';filename=' . $file['name'] . ';type=' . $file['type'];

	$curl = curl_init();

	curl_setopt($curl, CURLOPT_POST, 1);
	curl_setopt($curl, CURLOPT_POSTFIELDS, $formData);
	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

	$response = curl_exec($curl);

	if ($response === false)
	{
		$info = curl_getinfo($curl);

		curl_close($curl);

		$status = "Oops, something went wrong with curl.";
	}
	else
	{
		$response = (object)json_decode($response);

		curl_close($curl);
	}

	//	enable all form fields in case there are errors
	$disable_fields = false;

	//	save
	$success = $response->success;

	if( !$success && isset($response->error) )
		$status = $response->error;

	if( isset($response->result) )
		$dream = $response->result;

	if( EMBEDDED )
	{
		if( !$success )
		{
			print_r($response);

			if( !$status )
				$status = $response->error ? $response->error : "There were problems with your submission.";

			echo $status . " Click <a href='javascript:window.history.back()'>here</a> to go back.";
		}
		else
		{
			if( isset($formData['redirect_url']) )
			{
				header("Location: " . urldecode($formData['redirect_url']) );
			}
			else
			{
				echo "Your dream was submitted";
			}
		}

		die();
	}

	if( !$success )
	{
		$dream = $dreamDefault;

		//	validation error
		$dream->setValues( $formData );

		if( !isset($status) )
			$status = "Oops! Something went wrong with data validation.";
	}
	else
	{
		unset( $_SESSION['submission'] );

		$disable_fields = true;
		$date = DateTime::createFromFormat( $date_format, $dream->date, new DateTimeZone(TIME_ZONE) );
		$status = "Your dream was submitted";

		header("Location: index.php?status=".$status."#".$date->format('j/n/Y'));
	}
}
?>
<!DOCTYPE HTML>
<html>
<head>
<title>Artefacts of the Collective Unconscious</title>
<link href='http://fonts.googleapis.com/css?family=Cedarville+Cursive|Open+Sans' rel='stylesheet' type='text/css'>
<link rel="stylesheet" type="text/css" href="css/themes/<?php echo THEME; ?>/theme.css">
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/aristo/Aristo.css">
<link rel="stylesheet" href="css/jquery.miniColors.css">
<link rel="stylesheet" href="css/tipsy.css" type="text/css">
<script type="text/javascript" src="js/lib/jquery-1.9.1.js"></script>
<script type="text/javascript" src="js/lib/jquery-ui-1.10.1.custom.min.js"></script>
<script type="text/javascript" src="js/lib/jquery.miniColors.js"></script>
<script type="text/javascript" src="js/lib/jquery.tipsy.js"></script>
<script type="text/javascript" src="js/lib/jquery.cookie.js"></script>
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
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

		$('span[rel=tooltip]').tipsy( { gravity:'w', offset:5 } );

		$('input[rel=tooltip]').tipsy( { gravity:'w', offset:5, trigger: 'manual' } );
		$('input[rel=tooltip]').focus( function() { $(this).tipsy("show"); } );
		$('input[rel=tooltip]').blur( function() { $(this).tipsy("hide"); } );

		$('input[rel=tooltip][type=file]').on( 'mouseover', function(){ $(this).tipsy("show"); } );
		$('input[rel=tooltip][type=file]').on( 'mouseout', function(){ $(this).tipsy("hide"); } );
	}
);

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

	<div id="wrapper">

		<?php include "includes/header.php"; ?>

		<div id="content" style='width:500px'>

			<div id="subheader">
				<p>To contribute your dream to the digital collection, please complete the information below.</p>
			</div>

			<div id="status" style="display:<?php echo isset($status)&&$status!=''?'block':'none';?>"><?php echo isset($status)?$status:null; ?></div>

			<form method="post" enctype="multipart/form-data" accept-charset="UTF-8">

				<input type="hidden" name="id" value="<?php echo isset($dream->id)?$dream->id:''; ?>"  />
				
				<div class="module" id="fields">

					<div class='title'>Dream Information</div>

					<div class="body">

						<div class="row">
							<label style="float:left" for="datepicker">When did you have your dream?</label>
							<input id="datepicker" type="text" name="date" class="date big"
									value="<?php echo $dream->date; ?>" style='width:350px;display:inline;vertical-align: middle' />
						</div>

						<div class="row">
							<label for="description">Describe your dream</label>
							<textarea class="big" id="description" name="description" rows="8" placeholder=""><?php echo stripslashes($dream->description); ?></textarea>
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

						<div class="row">
							<label for="description">A colour associated with your dream</label>
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
								id="file" type="file" name="image_file" class="big"
								rel="tooltip" title=".jpg, .png or .gif under <?php echo (Media::MAX_BYTES/1024/1024); ?>MB" style="width:200px" />
						</div>

						<div class="row">
							<label for="file">A recording of your dream</label>
							<input
								id="file" type="file" name="audio_file" class="big"
								rel="tooltip" title=".mp3 under <?php echo (Media::MAX_BYTES/1024/1024); ?>MB" style="width:200px" />
						</div>

						<div class="row">
							<div style="vertical-align:top">
								<label for="gender">Gender you identify as</label>
								<div style='display:inline-block'><input id='gender_male' type='radio' name='gender'<?php echo $dream->gender=='male'?' checked':null ?> value="male"><label style='display:inline-block' for="gender_male">Male</label></div>
								<div style='display:inline-block'><input id='gender_female' type='radio' name='gender'<?php echo $dream->gender=='female'?' checked':null ?> value="female"><label style='display:inline-block' for="gender_female">Female</label></div>
							</div>
						</div>

						<div class="row">
							<div style="vertical-align:top">
								<label for="age" class="inline-label">Age</label>
								<input
									id="age" class="big" type="text" name="age"
									placeholder=""
									value="<?php echo $dream->age; ?>"
									rel="tooltip" title="" style="width:50px" />
							</div>
						</div>

						<div class="row">
							<label for="gender" class="inline-label">Email</label>
							<input id="email" type="text" name="email" class="date big"
									value="<?php echo $dream->email; ?>" style='width:350px;display:inline;vertical-align: middle'
									rel="tooltip" title="We won't share this with anybody, promise." />
						</div>
						<div class="g-recaptcha" data-sitekey="6LccMWEUAAAAAGKLMa1GoYLbF9nAXg9UZqNxlCnQ"></div>
						<br>
						<div class="row">
							<input type="submit" name="submit" value="Contribute" />
						</div>

					</div>

				</div>

			</form>

		</div>	<!-- 	end content  -->

		<div id="push"></div>

	</div>	<!-- 	end wrapper  -->

	<?php include "includes/footer.php" ?>

</body>
</html>
