<?php

include_once ($_SERVER['DOCUMENT_ROOT'] . "/includes/session.php");

include_once ($_SERVER['DOCUMENT_ROOT'] . "/config/config.php");
include_once ($_SERVER['DOCUMENT_ROOT'] . "/includes/db.class.php");
include_once ($_SERVER['DOCUMENT_ROOT'] . "/includes/dream.class.php");
include_once ($_SERVER['DOCUMENT_ROOT'] . "/includes/media.class.php");

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

		$status = "Oops, something went wrong.";
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
			$status = "Oops! Something went wrong.";
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

<!doctype html>
<html>
  <head>
    <meta charset="utf-8">
    <meta content="IE=edge,chrome=1" http-equiv="X-UA-Compatible">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Contribute – DREAMdesign</title>
    <link href="./assets/stylesheets/application.css" rel="stylesheet" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/parallax/3.1.0/parallax.min.js"></script>
    <script src="./assets/javascripts/application.js"></script>
    <link href="https://fonts.googleapis.com/css?family=Karla:400,400i,700,700i" rel="stylesheet">
  </head>
  <body class="contribute">
    <header class="body-header">
      <nav class="menu-desktop">
        <a href="./home.html" class="menu-desktop__logo">    <img src="./assets/images/dream-design-logo.svg" alt="Dream design logo" />
        </a>
        <ul class="menu-desktop__list">
          <li class="menu-desktop__item">
            <a href="./about.html" class="menu-desktop__link">        <img src="./assets/images/dream-design-link.svg" class="menu-desktop__rings" alt="Dream design link" />
            <span itemprop="name">About</span>
            </a>
          </li>
          <li class="menu-desktop__item">
            <a href="./gallery.html" class="menu-desktop__link">        <img src="./assets/images/dream-design-link.svg" class="menu-desktop__rings" alt="Dream design link" />
            <span itemprop="name">Gallery</span>
            </a>
          </li>
          <li class="menu-desktop__item">
            <a href="./contribute.php" class="menu-desktop__link">        <img src="./assets/images/dream-design-link.svg" class="menu-desktop__rings" alt="Dream design link" />
            <span itemprop="name">Contribute</span>
            </a>
          </li>
        </ul>
      </nav>
    </header>
    <main class="body-content">
      <section class="contribute-section">
        <form method="post" enctype="multipart/form-data" accept-charset="UTF-8">
          <div class="module" id="fields">
            <div class="title">Dream Information</div>
            <div class="body">
              <div class="row">
                <label style="float:left" for="datepicker">When did you have your dream?</label>
                <input id="datepicker" name="date" class="date big hasDatepicker" value="22/7/2018" style="width:350px;display:inline;vertical-align: middle" type="text">
              </div>
              <div class="row">
                <label for="description">Describe your dream</label>
                <textarea class="big" id="description" name="description" rows="8" placeholder=""></textarea>
              </div>
              <div class="row">
                <label for="tags">Title of your dream</label>
                <input id="title" name="title" class="big" maxlength="255" placeholder="" value="" rel="tooltip" original-title="" style="background-image: url(&quot;data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAAAXNSR0IArs4c6QAAAfBJREFUWAntVk1OwkAUZkoDKza4Utm61iP0AqyIDXahN2BjwiHYGU+gizap4QDuegWN7lyCbMSlCQjU7yO0TOlAi6GwgJc0fT/fzPfmzet0crmD7HsFBAvQbrcrw+Gw5fu+AfOYvgylJ4TwCoVCs1ardYTruqfj8fgV5OUMSVVT93VdP9dAzpVvm5wJHZFbg2LQ2pEYOlZ/oiDvwNcsFoseY4PBwMCrhaeCJyKWZU37KOJcYdi27QdhcuuBIb073BvTNL8ln4NeeR6NRi/wxZKQcGurQs5oNhqLshzVTMBewW/LMU3TTNlO0ieTiStjYhUIyi6DAp0xbEdgTt+LE0aCKQw24U4llsCs4ZRJrYopB6RwqnpA1YQ5NGFZ1YQ41Z5S8IQQdP5laEBRJcD4Vj5DEsW2gE6s6g3d/YP/g+BDnT7GNi2qCjTwGd6riBzHaaCEd3Js01vwCPIbmWBRx1nwAN/1ov+/drgFWIlfKpVukyYihtgkXNp4mABK+1GtVr+SBhJDbBIubVw+Cd/TDgKO2DPiN3YUo6y/nDCNEIsqTKH1en2tcwA9FKEItyDi3aIh8Gl1sRrVnSDzNFDJT1bAy5xpOYGn5fP5JuL95ZjMIn1ya7j5dPGfv0A5eAnpZUY3n5jXcoec5J67D9q+VuAPM47D3XaSeL4AAAAASUVORK5CYII=&quot;); background-repeat: no-repeat; background-attachment: scroll; background-size: 16px 18px; background-position: 98% 50%;" type="text">
              </div>
              <div class="row">
                <label for="description">A colour associated with your dream</label>
                <div>
                  <span class="minicolors"><input id="colorpicker1" class="colorpicker minicolors-hidden" name="color" data-textfield="false" value="#333333" data-default="" data-slider="hue" size="7" maxlength="7" type="minicolors"><span class="minicolors-swatch"><span style="background-color: rgb(51, 51, 51);"></span></span><span class="minicolors-panel minicolors-slider-hue" style="display: none;"><span class="minicolors-slider"><span class="minicolors-picker" style="top: 0px;"></span></span><span class="minicolors-opacity-slider"><span class="minicolors-picker"></span></span><span class="minicolors-grid" style="background-color: rgb(255, 0, 0);"><span class="minicolors-grid-inner"></span><span class="minicolors-picker" style="top: 120px; left: 0px;"><span></span></span></span></span></span>
                </div>
              </div>
              <div class="row">
                <label for="file">An image of your dream</label>
                <input id="file" name="image_file" class="big" rel="tooltip" style="width:200px" original-title=".jpg, .png or .gif under 3MB" type="file">
              </div>

              <div class="row">
                <input id="email" type="hidden" name="email" value="michelleboyde69@gmail.com" />isplay:inline;vertical-align: middle" rel="tooltip" original-title="We won't share this with anybody, promise." type="text">
              </div>
              <div class="g-recaptcha" data-sitekey="6LccMWEUAAAAAGKLMa1GoYLbF9nAXg9UZqNxlCnQ">
                <div style="width: 304px; height: 78px;">
                  <div><iframe src="https://www.google.com/recaptcha/api2/anchor?ar=1&amp;k=6LccMWEUAAAAAGKLMa1GoYLbF9nAXg9UZqNxlCnQ&amp;co=aHR0cDovL3d3dy5hcnRlZmFjdHNvZnRoZWNvbGxlY3RpdmV1bmNvbnNjaW91cy5uZXQ6ODA.&amp;hl=en&amp;v=v1531759913576&amp;size=normal&amp;cb=36coklf2z7m1" role="presentation" scrolling="no" sandbox="allow-forms allow-popups allow-same-origin allow-scripts allow-top-navigation allow-modals allow-popups-to-escape-sandbox" width="304" height="78" frameborder="0"></iframe></div>
                  <textarea id="g-recaptcha-response" name="g-recaptcha-response" class="g-recaptcha-response" style="width: 250px; height: 40px; border: 1px solid #c1c1c1; margin: 10px 25px; padding: 0px; resize: none;  display: none; "></textarea>
                </div>
              </div>
              <br>
              <div class="row">
                <input name="submit" value="Contribute" type="submit">
              </div>
            </div>
          </div>
        </form>
      </section>
    </main>
  </body>
</html>
