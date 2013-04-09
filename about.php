<?php
include 'config/' . getenv('HTTP_APPLICATION_ENVIRONMENT') . "/config.php";
include 'includes/session.php';
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
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" type="text/css" href="css/themes/<?php echo THEME; ?>/theme.css">
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
	
	<div id="wrapper">
		
		<?php include "includes/header.php"; ?>
		
		<div id="content" class="narrow" style="display:inline-block;position:relative">
		
			<div class='subheader' style='margin-bottom:40px'>
				<h3 id='toggle-about' class='toggle'><a href='#' onclick='javascript:show("about")'>About</a></h3>
				<h3 id='toggle-faq' class='toggle'><a href='#' onclick='javascript:show("faq")'>Questions</a></h3>
				<h3 id='toggle-comments' class='toggle'><a href='#' onclick='javascript:show("comments")'>Comments</a></h3>
			</div>
			
			<div id="status" style="display:<?php echo isset($status)&&$status!=''?'block':'none';?>"><?php echo isset($status)?$status:null; ?></div>
			
			<div id='question-about' class='section'>
				
				<div style="font-size:.7em;margin-bottom:20px;">
				
					<b>Artefact / Artifact</b>, <i>n</i>
					
					<ul>
						<li>anything created by humans which gives information about the culture of its creator and users</li>
						<li>a product of individuals or groups (social beings) or of their social behavior</li>
						<li>an object in a digital environment</li>
					</ul>
					
					<br/>
					
					<b>Collective unconscious</b>, <i>n</i>
					
					<ul>
						<li>A part of the unconscious mind, expressed in humanity and all life forms with nervous systems, which describes how the structure of the psyche autonomously organizes experience.</li>
					</ul>
					
				</div>
				
				<p><b>Artefacts of the Collective Unconscious</b> is an ongoing digital collection of data about dreams, to which anyone is free to browse or contribute. The collection is founded on the principles of <span class='underlined'>social dreaming</span>: the idea that dreams can tell us not just about the individual dreamer, but about our collective experience of the world: that by sharing dreams create a pictures and patterns that can illuminate concerns and hopes about about our future together.</p>

				<p>As well as collecting and collating dream data, we represent the data in visual form using what we have called an 'oneirogram'. This is a force diagram which uses the <a href="http://www.alchemyapi.com" target="_blank">AlchemyAPI</a> natural language processor to analyse themes and concepts in the dream text.</p>

				<p>In future, we aim to allow integration of dream data with other applications and platforms which are used to record and store dreams.</p>

				<p>Curators: <a href='http://www.createassociate.com.au/' target='_blank'>David Patman</a> and <a href='http://www.looklisten.net/' target='_blank'>Noah Pedrini</a></p>
				
			</div>
			
			<div id='question-faq' class='section'>
				
				<div>
					<div class='question'>Why are you doing this?</div>
					<div class='answer'>
						<p>We're interested in the social aspect of dreams - what they can tell us about experiences we have in common, rather than just our personal history.</p>
					</div>
				</div>
				
				<div>
					<div class='question'>Isn't my dream just about me?</div>
					<div class='answer'><p>Part of your dream is likely to be about your personal experience that is unique to you, and only you will be able to understand that part. However, part of your dream will also be about the experiences that you share with other people: e.g. your family, your friends, colleagues. We hope that by looking at lots of dreams that we'll be able to see common themes and patterns emerge.</p>
					</div>
				</div>
				
				<div>
					<div class='question'>What's an 'oneirogram'?</div>
					<div class='answer'><p>We invented what we call an oneirogram as a way to visualise connections between dreams using the <a href="http://www.alchemyapi.com" target="_blank">AlchemyAPI</a> natural language processor. The oneirogram can show dreams for specific dates, locations, tags and other data selected by the user.</p></div>
				</div>
				
				<div>
					<div class='question'>How are connections made?</div>
					<div class='answer'>
						<p>We aggregate the text of all dreams for the selected parameters (date, location, tags) and determine thematic tags for the 'combined dream'. The combined dream tags are then compared with tags for the individual dreams. If a dream shares a tag with another dream, a line (or 'edge') is drawn between them. The more connections a dream or tag has, the larger its node is shown.</p>
					</div>
				</div>
				
				<div>
					<div class='question'>How will you use my dreams and my personal information?</div>
					<div class='answer'>
						<p>We will store your dream data in our digital collection, but no personal information. However, anyone will be able to see the text of the dream you record, so you may wish to disguise any details that you think could identify you or others.</p>
					</div>
				</div>
				
				<div>
					<div class='question'>Can I use your site to keep a personal dream journal?</div>
					<div class='answer'>
						<p>Yes! If you tag each of your dreams with a unique identifier, you can search for and visualise just your dreams.</p>
					</div>
				</div>
				
				<div>
					<div class='question'>Can I edit my dreams once they have been recorded?</div>
					<div class='answer'>
						<p>At this point, no. We would need to create a personal account to enable you to do this. We may consider doing this in a future release. Please contact us at <a href="mailto:dreams@artefactsofthecollectiveunconscious.net">dreams@artefactsofthecollectiveunconscious.net</a> if you need a dream removed, e.g. if you have inadvertently recorded identifying information about yourself or someone else.</p>
					</div>
				</div>
				
				<div>
					<div class='question'>How did you do it?</div>
					<div class='answer'>
						<p>When a user submits a dream on Artefacts, we load and parse the corresponding tour data collected via this system and migrate it to a MySQL database. This allows us to create a JSON file representing node-link relationships between artworks viewed for a given day, the dreams that were dreamt that night, and the associations that bind them. This data file is then used to drive the visualization.</p><p>The visualization itself is a <a href="http://en.wikipedia.org/wiki/Force-directed_graph_drawing" target="_blank">force-directed layout</a> built using the <a href="http://d3js.org/" target="_blank">D3js</a> javascript library, and the image export feature leverages the <a href="http://code.google.com/p/canvg/" target="_blank">canvg</a> library. A few of the other javascript libraries we use include <a href="http://jquery.com/" target="_blank">jQuery</a>, <a href="http://jqueryui.com/" target="_blank">jQuery UI</a>, and <a href="http://onehackoranother.com/projects/jquery/tipsy/" target="_blank">tipsy</a>.</p><p>A big thank you to thank the development communities who made and contributed to all of these great open-source projects, the designers over at <a href="http://thenounproject.com/" target="_blank">The Noun Project</a> for letting us use a few of their icons, and of course, <a href='http://www.mona.net.au/' target='_blank'>MONA</a>.</p>
					</div>
				</div>
				
				<div>
					<div class='question'>Did you know that 'artefact' is spelled wrong?</div>
					<div class='answer'><p>Yes!</p></div>
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
							<textarea id="comments" name="comments" class="big"><?php echo isset($form_values['comments'])?$form_values['comments']:''; ?></textarea>
						</div>
					</div>
					
					<?php
					echo recaptcha_get_html($publickey, $error);
					?>
					
					<input type="submit" name="submit" value="Submit" />
					
				</form>
			
			</div>
			
			<div id="sidebar">
				
				<div id="stats"><?php echo implode( ', ', $stats ); ?></div>
				
				<div id="quote">
					<span class="quote"></span><br/>
					<span class="author"></span>
				</div>
				
			</div>
		
		</div>	<!-- 	end content  -->
		
		<div id="push"></div>
		
	</div> <!-- 	end wrapper  -->
	
	<?php include "includes/footer.php" ?>
	
</body>

</html>