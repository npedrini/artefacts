<?php
include 'config/' . getenv('HTTP_APPLICATION_ENVIRONMENT') . "/config.php";
include 'includes/recaptcha/recaptchalib.php';

$publickey = "6LdEQd0SAAAAAHivyVeW7KROMA2upIL6Npbxr99D";
$privatekey = "6LdEQd0SAAAAAEPXsQsULbZ2R1qk1B2cC7f-nv4K";

# the response from reCAPTCHA
$resp = null;
# the error code from reCAPTCHA, if any
$error = null;

$mysqli = new mysqli( DB_HOST, DB_USER, DB_PASS );
$mysqli->select_db( DB_NAME );

$date_format = DATE_FORMAT;
$date_format = preg_replace( '/{{date}}/', 'j', $date_format );
$date_format = preg_replace( '/{{month}}/', 'n', $date_format );
$date_format = preg_replace( '/{{year}}/', 'Y', $date_format );

$form_values = array('name'=>'','email'=>'','comments'=>'');

$status = null;

if( isset($_POST['submit']) )
{
	$form_values = $_POST;
	
	$name = $mysqli->real_escape_string($_POST['name']);
	$email = $mysqli->real_escape_string($_POST['email']);
	$comment = $mysqli->real_escape_string($_POST['comments']);
	
	$ip = $_SERVER['REMOTE_ADDR'];
	
	if(is_null($name) || empty($name)) 
		$status = "Please enter your name";
	else if(is_null($comment) || empty($comment)) 
		$status = "Please enter your comment";
	
	//	check for spam via captcha
	if ( is_null($status) ) 
	{
		if( isset($_POST["recaptcha_response_field"]) )
		{
			$resp = recaptcha_check_answer
			(
				$privatekey,
				$_SERVER["REMOTE_ADDR"],
				$_POST["recaptcha_challenge_field"],
				$_POST["recaptcha_response_field"]
			);
			
			if ( !$resp->is_valid ) 
			{
				$error = $resp->error;
				$status = "The two words don't seem to match. Please re-type.";
			}
		}
		else
		{
			$status = "Robot!";
		}
    }
		
	if( is_null($status) )
	{
		$sql = "SELECT id FROM `comments` WHERE name='".$name."' AND comment='".$comment."'";
		$result = $mysqli->query($sql);
		
		if( $mysqli->affected_rows >= 1 )
		{
			$status = "This comment has already been added";
		}
		else
		{
			$sql = "INSERT INTO `comments` SET name='".$name."',email='".$email."',comment='".$comment."',date_created='".time()."',ip='".$ip."'";
			
			if( $mysqli->query($sql) )
			{
				$status = "Thank you! Your comment will appear here once approved.";
				$_POST = $form_values = array();
				
				//	send mail to admin
				$body = "The following comment has just been added to AnthroPosts by ".$name.":\n\n".$comment."\n\n- The Magic Robot";
				mail("davidpatman69@gmail.com, go@looklisten.net","Artefacts - New Comment",$body);
			}
			else
			{
				$status = "There was a problem adding your comment. Please try again later.";
			}
		}
	}
}

/*
$sql  = "SELECT COUNT(DISTINCT(artworks.id)) AS artwork_count, ";
$sql .= "COUNT(DISTINCT(artworks.artist)) AS artist_count, ";
$sql .= "COUNT(DISTINCT(dreams.id)) AS dream_count, ";
$sql .= "COUNT(DISTINCT(dreams.user_id)) AS dreamer_count ";
$sql .= "FROM `artworks`,`dreams`";
*/

$sql = "SELECT COUNT(DISTINCT(dreams.id)) AS dream_count, ";
$sql .= "COUNT(DISTINCT(dreams.user_id)) AS dreamer_count, ";
$sql .= "COUNT(DISTINCT(dreams.occur_date)) AS dream_dates ";
$sql .= "FROM `dreams`";

$result = $mysqli->query($sql);
$row = $result->fetch_assoc();

$stats_temp = array
(
	/*'museum'=>1,
	'artworks'=>$row['artwork_count'],
	'artists'=>$row['artist_count'],*/
	'dreams'=>$row['dream_count'],
	'dreamers'=>$row['dreamer_count'],
	'nights'=>$row['dream_dates']
);

arsort($stats_temp);

$stats = array();
foreach($stats_temp as $label=>$value) $stats[] = $value . ' ' . $label;
?>
<!DOCTYPE HTML>
<html>
<head>
<title>Artefacts of the Collective Unconscious</title>
<link href='http://fonts.googleapis.com/css?family=Cedarville+Cursive|Open+Sans|Roboto+Condensed' rel='stylesheet' type='text/css'>
<link rel="stylesheet" type="text/css" href="css/themes/<?php echo THEME; ?>/theme.css">
<link rel="stylesheet" href="css/style.css">
<script type="text/javascript" src="js/lib/jquery-1.9.1.js"></script>
<script type="text/javascript" src="js/lib/jquery.cookie.js"></script>
<script type="text/javascript" src="js/random-quote.js"></script>
<script type="text/javascript">
$(document).ready
(
	function()
	{
		$('.section').each( function() { $(this).hide(); } );
		$('.answer').each( function() { $(this).hide(); } )
		$('.question').css('cursor','pointer');
		
		$('.question').on
		(
			'click',
			function()
			{
				$(this).parent().find('.answer').toggle();		
			}
		);
		
		var hash = window.location.hash;
		hash = hash.substr( hash.indexOf('#')+1 );
		
		if( hash=='' ) hash = "about";
		
		if( hash 
			&& $('#question-' + hash + ' .togglable') )
		{
			show( hash );
		}
		
		showRandomQuoteStart();
	}
);

function show( id )
{
	if( cid ) 
	{
		$('#question-'+cid).hide();
		$('#toggle-'+cid+' a').removeClass('selected');
	}
	
	$('#question-'+id).show();
	$('#toggle-'+id+' a').addClass('selected');
	
	cid = id;
}

var RecaptchaOptions = { theme : 'white' };
 
var cid;

</script>
</head>

<body>
	
	<div id="foreground">
	
		<?php include "includes/header.php"; ?>
		
		<div id="content" class="narrow" style="display:inline-block;position:relative">
		
			<div class='subheader' style='margin-bottom:40px'>
				<h3 id='toggle-about' class='toggle'><a href='#' onclick='javascript:show("about")'>About</a></h3>
				<h3 id='toggle-faq' class='toggle'><a href='#' onclick='javascript:show("faq")'>Questions</a></h3>
				<h3 id='toggle-comments' class='toggle'><a href='#' onclick='javascript:show("comments")'>Comments</a></h3>
			</div>
			
			<div id="status" style="display:<?php echo isset($status)&&$status!=''?'block':'none';?>"><?php echo isset($status)?$status:null; ?></div>
			
			<div id='question-about' class='section'>
				
				<p>Artefacts of the collective unconscious is an ongoing digital collection of dreams recorded by visitors to the <a href='http://www.mona.net.au/' target='_blank'>Museum of Old and New Art</a> (MONA), located in Hobart, Tasmania. The purpose of the collection is to enable study and exploration of connections between art and dreams.</p>

<p>Museum visitors are invited to donate their dreams and information about their tour via an online form sent on the day after their museum visit. Visitor dreams are catalogued each day, correlating dream data with information about the artworks visitors responded to using 'The O', an indoor location system developed by <a href='http://artprocessors.net' target='_blank'>Art Processors</a>.</p>

<p>Visitors are asked to include a colour to describe their dream, and five 'associations'. The associations are not intended as descriptors, but words which spontaneously come to mind when thinking about their dream. This material is used to identify connections between dreams and artworks, and to visualise these using in the form of what we have called an 'oneirogram'.</p>

<p>Curators: <a href='http://www.createassociate.com.au/' target='_blank'>David Patman</a> and <a href='http://www.looklisten.net/' target='_blank'>Noah Pedrini</a></p>
				
			</div>
			
			<div id='question-faq' class='section'>
				
				<div>
					<div class='question'>My dream isn't about the museum or its artworks &ndash; does it count?</div>
					<div class='answer'>
						<p>Dreams come from the unconscious mind and work differently to the way our thought processes do when we're awake. Like art, dreams are symbolic and we often need to let go of what we think is logical and rational to understand what they could mean. So, although MONA wasn't in your dream, this doesn't mean that your MONA experience isn't relevant to what you dreamt. We think that by looking at lots of visitor dreams we'll start to see patterns and connections that will help us get insight into what the dreams mean overall.</p>
					</div>
				</div>
				
				<div>
					<div class='question'>Isn't my dream just about me?</div>
					<div class='answer'><p>Part of your dream is likely to be about your personal experience that is unique to you, and only you will be able to understand that part. However, part of your dream will also be about the experiences that you share with other people: e.g. your family, your friends, colleagues &ndash; and also other MONA visitors! We hope that by looking at lots of dreams from MONA visitors that we'll be able to see common themes and patterns emerge. This won't tell us about you personally, but it could tell us about how the MONA experience affects people more generally.</p>
					</div>
				</div>
				
				<div>
					<div class='question'>What is an association and how do I add them to my dream?</div>
					<div class='answer'><p>By 'associations' are words or short phrases that spontaneously come to mind in response to the memory of a dream or any other kind of phenomenon that we experience when awake. It's a bit like 'brainstorming', where you can let your mind wander without having to think whether or not it makes sense, or whether it's related to what the dream seems to be about. So don't feel like your associations need to describe or classify your dream &ndash; let your imagination roam free. It's surprising what can come up!</p></div>
				</div>
				
				<div>
					<div class='question'>What's an 'oneirogram'?</div>
					<div class='answer'>
						<p>We invented what we call an oneirogram as a way to visualise connections between a collection of dreams and artefacts from waking life of which the dreamers have a shared experience or knowledge. It relies on dreamers making 'associations' to their dreams and to the artefacts (in this case MONA artworks), which we then represent graphically using JavaScript and HTML5 coding. Each day the oneirogram changes to integrate what visitors dreamt about on the previous night. You could think of it as visualising MONA's dream for that day.</p>
					</div>
				</div>
				
				<div>
					<div class='question'>How are connections made?</div>
					<div class='answer'>
						<p>The oneirogram uses associations to make connections between dreams and artworks. If a dream and an artwork (or another dream) have the same association, a line (or 'edge') is drawn between them. The more connections a dream or artwork has, the larger its node shown. This way you can see which dreams and artworks seem to be the most significant on a given day, and groups of dreams and artworks that seem to represent something in common.</p>
					</div>
				</div>
				
				<div>
					<div class='question'>Why won't my tour data show up?</div>
					<div class='answer'>
						<p>Our site uses the data you recorded and saved on 'The O' while on your tour of MONA. To save your tour you needed to type in an email address. You need to enter exactly the same email address for your tour to show up in the oneirogram.</p>
					</div>
				</div>
				
				<div>
					<div class='question'>How will you use my dreams and my personal information?</div>
					<div class='answer'>
						<p>We will store your dream and tour information in our digital collection, with your email address removed. We only ask for this initially so we can correlate your tour information with your dream and don't use it for any other purpose. Anyone will be able to see the text of the dream you record, so you may wish to disguise any details that you think could identify you.</p>
					</div>
				</div>
				
				<div>
					<div class='question'>How did you do it?</div>
					<div class='answer'>
						<p>Instead of traditional signage, The Museum of Old and New Art uses an innovative mobile app developed by <a href="http://artprocessors.net/" target="_blank">Art Processors</a> to facilitate interactive tours. When a user submits a dream on Artefacts, we load and parse the corresponding tour data collected via this system and migrate it to a MySQL database. This allows us to create a JSON file representing node-link relationships between artworks viewed for a given day, the dreams that were dreamt that night, and the associations that bind them. This data file is then used to drive the visualization.</p><p>The visualization itself is a <a href="http://en.wikipedia.org/wiki/Force-directed_graph_drawing" target="_blank">force-directed layout</a> built using the <a href="http://d3js.org/" target="_blank">D3js</a> javascript library, and the image export feature leverages the <a href="http://code.google.com/p/canvg/" target="_blank">canvg</a> library. A few of the other javascript libraries we use include <a href="http://jquery.com/" target="_blank">jQuery</a>, <a href="http://jqueryui.com/" target="_blank">jQuery UI</a>, and <a href="http://onehackoranother.com/projects/jquery/tipsy/" target="_blank">tipsy</a>.</p><p>A big thank you to thank the development communities who made and contributed to all of these great open-source projects, the designers over at <a href="http://thenounproject.com/" target="_blank">The Noun Project</a> for letting us use a few of their icons, and of course, <a href='http://www.mona.net.au/' target='_blank'>MONA</a>.</p>
					</div>
				</div>
				
				<div>
					<div class='question'>Did you know that 'artefact' is spelled wrong?</div>
					<div class='answer'><p>Are you American?</p></div>
				</div>
			
			</div>
			
			<div id='question-comments' class='section'>
				
				<div style="margin-bottom:20px;">
				<?php
				//	EXISTING COMMENTS
				$sql = "SELECT * FROM `comments` ORDER BY date_created DESC";
				//$sql = "SELECT * FROM `comments` WHERE approved='1' ORDER BY date_created DESC";
				
				$results = $mysqli->query($sql);
				
				if( $mysqli->affected_rows > 0 )
				{
					while( $row = $results->fetch_assoc() )
					{
						$date = new DateTime( 'now', new DateTimeZone('Australia/Melbourne') );
						$date->setTimestamp( $row['date_created'] );
						
						echo "<div class='comment framed'>";
						
						echo "<div class='header'>".$row['name']." said:</div>";
						echo nl2br($row['comment']);
						
						echo "<div class='footer'>".$date->format("d F, Y")."</div>";
						
						echo "</div>";
					}
				}
				?>
				
				</div>
				
				<form action="#comments" method="post">
					
					<div>
						<label class="emphasized" for="name">Name</label>
						<div>
							<input id="name" type="text" name="name" value="<?php echo isset($form_values['name'])?$form_values['name']:''; ?>" class="big" />
						</div>
					</div>
					
					<div>
						<label class="emphasized" for="email">Email</label>
						<div>
							<input id="email" type="text" name="email" value="<?php echo isset($form_values['email'])?$form_values['email']:''; ?>" class="big" />
						</div>
					</div>
					
					<div>
						<label class="emphasized" for="comments">Comments</label>
						<div>
							<textarea id="comments" type="text" name="comments" class="big"><?php echo isset($form_values['comments'])?$form_values['comments']:''; ?></textarea>
						</div>
					</div>
					
					<?php
					echo recaptcha_get_html($publickey, $error);
					?>
					
					<input type="submit" name="submit" value="Submit" />
					
				</form>
			
			</div>
			
		</div>
		
		<div id="sidebar">
			
			<div id="stats"><?php echo implode( ', ', $stats ); ?></div>
			
			<div id="quote">
				<span class="quote"></span><br/>
				<span class="author"></span>
			</div>
			
		</div>
		
		<div id="footer" style="width:200px;text-align:left">
			<div><a href="http://www.momahobart.net.au/" target="_blank"><img src='images/mona.png'/></a></div>
			<div style='font-size:.6em;'>Establishment of the collection was sponsored by the <a href="http://www.momahobart.net.au/" target="_blank">MONA Market</a></div>
		</div>
	
	</div>
	
</body>

</html>