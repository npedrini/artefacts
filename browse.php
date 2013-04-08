<?php
include 'config/' . getenv('HTTP_APPLICATION_ENVIRONMENT') . "/config.php";
include 'includes/session.php';

$date_format = DATE_FORMAT;
$date_format = preg_replace( '/{{date}}/', 'j', $date_format );
$date_format = preg_replace( '/{{month}}/', 'n', $date_format );
$date_format = preg_replace( '/{{year}}/', 'Y', $date_format );

$values = array('date'=>'', 'search'=>'');

$mysqli = new mysqli( DB_HOST, DB_USER, DB_PASS );
$mysqli->select_db( DB_NAME );

$visited_works = array();
$filters = array();

if( isset($_GET['did']) )
{
	$sql  = "SELECT * FROM `dreams` ";
	$sql .= "WHERE id = '" . $mysqli->real_escape_string( $_GET['did'] ) . "'";
	
	$result = $mysqli->query( $sql );
	
	if( $mysqli->affected_rows > 0 )
	{
		$dream = $result->fetch_assoc();
		
		//	get tags
		$tags = array();
		
		$sql  = "SELECT tag_id AS id,tag FROM `dream_tags` ";
		$sql .= "LEFT JOIN tags ON dream_tags.tag_id=tags.id ";
		$sql .= "WHERE dream_tags.dream_id='" . $dream['id'] . "'";
		
		$result = $mysqli->query( $sql );
		while( $tag = $result->fetch_assoc() ) $tags[] = $tag;
		
		$dream['tags'] = $tags;
		
		/*
		//	get visited works by this user on this date
		$sql  = "SELECT DISTINCT(artworks.id), artworks.image, artworks.title FROM `artworks` ";
		$sql .= "LEFT JOIN `visit_data` ON visit_data.artwork_id=artworks.id ";
		$sql .= "LEFT JOIN `visits` ON visits.id=visit_data.visit_id ";
		$sql .= "WHERE visits.user_id='".$dream['user_id']."' AND visits.visit_date='".$dream['occur_date']."'";
		
		$result = $mysqli->query( $sql );
		
		while( $artwork = $result->fetch_assoc() )
		{
			$visited_works[] = $artwork;
		}
		*/
	}
}
else if( isset($_GET['aid']) )
{
	$sql  = "SELECT * FROM `artworks` ";
	$sql .= "WHERE id = '" . $mysqli->real_escape_string( $_GET['aid'] ) . "'";
	
	$result = $mysqli->query( $sql );
	
	if( $mysqli->affected_rows > 0 )
	{
		$artwork = $result->fetch_assoc();
		
		//	get tags
		$tags = array();
		
		$sql  = "SELECT tag_id AS id,tag FROM `artwork_tags` ";
		$sql .= "LEFT JOIN tags ON artwork_tags.tag_id=tags.id ";
		$sql .= "WHERE artwork_tags.artwork_id='" . $artwork['id'] . "'";
		
		$result = $mysqli->query( $sql );
		while( $tag = $result->fetch_assoc() ) $tags[] = $tag;
		
		$artwork['tags'] = $tags;
		
		/*
		//	get visited works by this user on this date
		$sql  = "SELECT DISTINCT(artworks.id), artworks.image, artworks.title FROM `artworks` ";
		$sql .= "LEFT JOIN `visit_data` ON visit_data.artwork_id=artworks.id ";
		$sql .= "LEFT JOIN `visits` ON visits.id=visit_data.visit_id ";
		$sql .= "WHERE visits.user_id='".$dream['user_id']."' AND visits.visit_date='".$dream['occur_date']."'";
		
		$result = $mysqli->query( $sql );
		
		while( $artwork = $result->fetch_assoc() )
		{
			$visited_works[] = $artwork;
		}
		*/
	}
}
else
{
	$dreams = array();
	$where = array();
	
	if( isset($_GET['search']) 
		&& !empty($_GET['search']) )
	{
		$tags = explode( " ", $_GET['search'] );
		
		$tags_filter = array();
		
		/*
		$sql  = "SELECT DISTINCT(dreams.id), dreams.occur_date, dreams.title, dreams.description, tags.id AS tag_id, tags.tag FROM `dreams` ";
		$sql .= "LEFT JOIN dream_tags ON dream_tags.dream_id=dreams.id ";
		$sql .= "LEFT JOIN tags ON tags.id=dream_tags.tag_id ";
		*/
		
		$sql  = "SELECT DISTINCT(dreams.id), dreams.occur_date, dreams.title, dreams.description, dreams.gender, dreams.age ";
		$sql .= "FROM `dreams`,`tags`,`dream_tags` ";
		
		$where[] = "dream_tags.dream_id=dreams.id";
		$where[] = "tags.id=dream_tags.tag_id";
		
		$or = array();
		
		foreach($tags as $tag) 
		{
			$tag = strtolower(trim($tag));
			
			$or[] = "tags.tag = '".$mysqli->real_escape_string($tag)."'";
			
			$or[] = "dreams.title LIKE '%".$mysqli->real_escape_string($tag)."%'";
			$or[] = "dreams.description LIKE '%".$mysqli->real_escape_string($tag)."%'";
						
			$tags_filter[] = "<i>" . $tag . "</i>";
		}
		
		$where[] = "(" . join( " OR ", $or ) . ")";
		
		$filters[] = implode(',',$tags_filter);
		
		$values['search'] = $_GET['search'];
	}
	else
	{
		$sql  = "SELECT * FROM `dreams` ";
	}
	
	if( isset($_GET['date']) 
		&& !empty($_GET['date']) )
	{
		$date = DateTime::createFromFormat( $date_format, $_GET['date'], new DateTimeZone('Australia/Melbourne') );
		
		$where[] = "occur_date='".$date->format('Y-m-d')."'";
		
		$filters[] = "on ".$date->format('d/n/y');
		
		$values['date'] = $_GET['date'];
	}
	
	if( count($where) ) $sql .= "WHERE " . join( " AND ", $where ) . " ";
	
	$sql .= "ORDER BY occur_date DESC";
	
	$result = $mysqli->query( $sql );
		
	if( $mysqli->affected_rows > 0 )
		while( $d = $result->fetch_assoc() ) 
			if( !array_search($d,$dreams) )
				$dreams[] = $d;
}

$isMain = !( isset($dream) || isset($artwork) );
?>
<!DOCTYPE HTML>
<html>
<head>
<title>Artefacts of the Collective Unconscious</title>
<meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
<link href='http://fonts.googleapis.com/css?family=Cedarville+Cursive|Open+Sans|Roboto+Condensed' rel='stylesheet' type='text/css'>
<link rel="stylesheet" type="text/css" href="css/themes/<?php echo THEME; ?>/theme.css">
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/aristo/Aristo.css">
<link rel="stylesheet" href="css/tipsy.css" type="text/css">
<script type="text/javascript" src="js/lib/jquery-1.9.1.js"></script>
<script type="text/javascript" src="js/lib/jquery-ui-1.10.1.custom.min.js"></script>
<script type="text/javascript" src="js/lib/jquery.tipsy.js"></script>
<script type="text/javascript" src="js/random-quote.js"></script>
<script type="text/javascript">
$(document).ready
(
	function()
	{
		//	get dates for which there is data
		$.ajax
		(
			{
				dataType: 'json',
				url: 'json/dates.json.php'
			}
		)
		.done
		(
			function(data) 
			{
				//	store list of available dates
				availableDates = data.dates;
				
				//	create date format string
				var df = dateFormat
					.replace( /{{date}}/, 'd' )
					.replace( /{{month}}/, 'm' )
					.replace( /{{year}}/, 'yy' );
				
				//	initialize date packer
				$("#datepicker").datepicker
				(
					{
						beforeShowDay: shouldEnableDate,
						dateFormat: df,
						minDate: availableDates[ availableDates.length - 1 ],
						maxDate: availableDates[0]
					}
				);
				
				$.extend($.datepicker,{_checkOffset:function(inst,offset,isFixed){return offset}});
			}
		);
		
		$('a[rel=tooltip], img[rel=tooltip]').tipsy( { gravity:'w', offset: 5, opacity: 1 } );
		
		$('input[rel=tooltip]').tipsy( { gravity:'s', offset:5, opacity: 1, trigger: 'manual' } );
		$('input[rel=tooltip]').focus( function() { $(this).tipsy("show"); } );
		$('input[rel=tooltip]').blur( function() { $(this).tipsy("hide"); } );
		
		$('img').on
		(
			'mouseover',
			function(e)
			{
				$('img').each
				(
					function(index)
					{
						if( $(this).attr('src') != $(e.currentTarget).attr('src') )
							$(this).css('opacity',.2);
					}
				);
			}
		);
		
		$('img').on ( 'mouseout', function() { $('img').each( function(index,el) { $(el).css('opacity',1); } ); } );
		
		if( <?php echo $isMain ? 'true' : 'false'; ?> )
			showRandomQuoteStart();
	}
);

/**
 * Returns whether or not date a given date should be enabled
 * @param {Date} date 
 * @return {Array} shouldEnableDate
 */
function shouldEnableDate( date )
{
	var dateString = date.getDate() + "/" + (date.getMonth() + 1) + "/" + date.getFullYear();
	
	if ( $.inArray(dateString, availableDates) > -1 )  return [true,''];
    
	return [false, "There are no dreams for this day"];
}

var dateFormat = "<?php echo DATE_FORMAT; ?>";
var availableDates;
</script>
<style>
form > div { display: inline-block; margin-right: 5px; }
form input[type=text], form textarea { padding: .5em; outline-width: 0; }
</style>
</head>

<body>

	<?php include "includes/header.php"; ?>
	
	<div id="content" class="<?php echo $isMain?'narrow':'wide'; ?>" style='display:inline-block'>
		
		<div id="status" style="display:<?php echo isset($status)&&$status!=''?'block':'none';?>"><?php echo isset($status)?$status:null; ?></div>
		
		<?php if( $isMain ) { ?>
		<form method="get">
	
			<div>
				<input 
					id="search" type="text" name="search" 
					placeholder="Search" style="width:300px;"
					value="<?php echo $values['search']; ?>" 
					rel="tooltip" title="" />
			</div>
			
			<div>
				<input 
					id="datepicker" type="text" name="date" class="date"
					value="<?php echo $values['date']; ?>"  style='display:inline;vertical-align: middle;width:75px;'
					placeholder="Date" 
					rel="tooltip" title="" />
			</div>
			
			<div>
				<input type="submit" name="submit" value="Find" />
			</div>
					
		</form>
		<?php } ?>
		<?php
		$nl = "\n\n\t\t\t";
		
		if( isset($dream) )
		{
			$date = new DateTime( $dream['occur_date'], new DateTimeZone('Australia/Melbourne') );
			
			echo $nl."<div class='module'>";
			
			echo $nl."\t<div class='header'>";
			
			if( isset($dream['title']) ) 
			{
				echo $nl."\t\t<div class='title'>" . $dream['title'] . "</div>";
				echo $nl."\t\t<div class='subtitle'>".$date->format('d F, Y')."</div>";
			}
			else
			{
				echo $nl."\t\t<div class='title'>".$date->format('d F, Y')."</div>";
			}
			
			echo $nl."\t</div>";
			
			echo $nl."\t<div class='body'>";
			echo $nl."\t\t<div style='font-size:1em;line-height:1.8em;'>".stripslashes(nl2br($dream['description']))."</div>";
			echo $nl."\t</div>";
			
			echo $nl."\t<div class='footer'>";
			
			$tags = array();
			foreach($dream['tags'] as $tag) $tags[] = "<a href='browse.php?search=".$tag['tag']."'>".$tag['tag']."</a>";
			
			echo $nl."\t\t<div class='footer'>associations: " . implode( ', ' , $tags ) . "</div>";
			
			echo $nl."\t</div>";	//	end footer
			
			echo $nl."\t</div>";	//	end module
			
			/*
			echo $nl."\t<div id='images' style='margin-top:20px'>";
			
			foreach($visited_works as $artwork)
			{
				echo "<a href='browse.php?aid=".$artwork['id']."'><img style='display:inline-block;' height='50px' src='".IMAGE_PATH.$artwork['image']."' rel='tooltip' title='".$artwork['title']."'/></a>";
			}
			
			echo $nl."\t</div>";	//	end images
			*/
			
			echo $nl."</div>";
		}
		/*
		else if( isset($artwork) )
		{
			echo $nl."<div class='module'>";
			
			echo $nl."\t<div class='header'>";
			
			$title = $artwork['title'] . (!is_null($artwork['year']) ? ", " . $artwork['year'] : "");
			
			if( isset($artwork['title']) ) 
			{
				echo $nl."\t\t<div class='title artwork_title'>" . $title . "</div>";
				echo $nl."\t\t<div class='subtitle artwork_artist'>" . $artwork['artist'] . "</div>";
			}
			else
			{
				echo $nl."\t\t<div class='title artwork_title'>" . $title . "</div>";
			}
			
			echo $nl."\t</div>";
			
			//style='background-color:".$artwork['color'] . "
			
			echo $nl."\t<div class='body'>";
			echo $nl."\t\t<img style='display:inline-block;' src='".IMAGE_PATH.$artwork['image']."' />";
			echo $nl."\t\t<div style='font-size:.5em;font-style:italic'>Image sourced from <a href='http://mona-vt.artpro.net.au/theo.php'>MONA</a></div>";
			echo $nl."\t</div>";	
			
			echo $nl."</div>";	//	end
		}
		*/
		else
		{
			$result_text = "dream".(count($dreams)==1?"":"s");
			
			$criteria = count($filters) ? " matching " . implode( ' and ' , $filters ).": <a href='browse.php' style='font-size:.7em;'>Clear</a>" : ":";
			
			echo $nl."<h3 style='margin-bottom:20px;'>We found <b>" . count($dreams) . " " . $result_text . "</b>".$criteria."</h3>";
			
			echo $nl."\t<div>";
			
			foreach($dreams as $dream)
			{
				$date = new DateTime( $dream['occur_date'], new DateTimeZone('Australia/Melbourne') );
				
				if( !isset($current_date) || $dream['occur_date'] != $current_date )
				{
					if( isset($current_date) )
					{
						echo $nl."\t</div>\n\t</div>";
					}
					
					$date = new DateTime( $dream['occur_date'], new DateTimeZone('Australia/Melbourne') );
					
					echo $nl."\t\t<div class='module'>";
					echo $nl."\t\t\t<div class='subtitle'>" . $date->format('d F, Y') . "</div>";
					echo $nl."\t\t<div class='body'>";
					
					$current_date = $dream['occur_date'];
				}
				
				$title_description = implode( ' ', array_splice( explode( ' ', $dream['description'] ), 0, 10 ) ) . '...';
				$title_description = substr( $title_description, 0, strpos($title_description,'.')+1 );
				
				$title = isset($dream['title']) && !empty($dream['title']) ? $dream['title'] : $title_description;
				
				$tootlip = ($dream['gender']==1?'male':'female').(!empty($dream['age'])?', age '.$dream['age']:'');
				
				echo $nl."\t<div class='result'>";
				echo $nl."\t\t<a rel='tooltip' title='".$tootlip."' href='browse.php?did=".$dream['id']."'>".$title."</a>";
				//echo "\n\t\t<div>".$date->format('M d, Y')."</div>";
				echo $nl."\t</div>";
			}
			
			echo $nl."\t\t</div>";
			echo $nl."\t</div>";
			echo $nl."</div>";
		}
		?>
		
	</div>
	
	<div id="sidebar">
		<div id="quote">
			<span class="quote"></span><br/>
			<span class="author"></span>
		</div>
	</div>
	
	<?php include "includes/footer.php" ?>
	
</body>
</html>