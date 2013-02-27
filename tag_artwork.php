<?php
include 'config/' . getenv('HTTP_APPLICATION_ENVIRONMENT') . "/config.php";

$mysqli = new mysqli( DB_HOST, DB_USER, DB_PASS );
$mysqli->select_db( DB_NAME );

if( isset($_GET['id']) )
{
	$sql = "SELECT * FROM `artworks` WHERE id='" . $mysqli->real_escape_string($_GET['id']) . "'";
	
	$result = $mysqli->query( $sql );
	
	if( $mysqli->affected_rows > 0 )
	{
		$artwork = $result->fetch_assoc();
		$tags = array();
		
		if( empty($artwork['image']) )
		{
			$artwork = null;
		}
	}
}

if( isset($_POST['submit']) )
{
	$id = $_POST['id'];
	
	$sql = "SELECT * FROM `artworks` WHERE id = '".$id."'";
	
	$result = $mysqli->query( $sql );
	
	if( $mysqli->affected_rows > 0 )
	{
		$artwork = $result->fetch_assoc();
		
		$tags = explode( ',', $_POST['tags'] );
		
		foreach($tags as $tag)
		{
			$tag = $mysqli->real_escape_string(strtolower(trim($tag)) );
			
			$sql = "SELECT id FROM `tags` WHERE tag='" . $tag . "'";
			
			$result = $mysqli->query( $sql );
			
			if( $mysqli->affected_rows > 0 )
			{
				$row = $result->fetch_assoc();
				$tag_id = $row['id'];
			}
			else
			{
				$sql = "INSERT INTO `tags` (tag) VALUES ('".$tag."')";
				
				$result = $mysqli->query( $sql );
				$tag_id = $mysqli->insert_id;
			}
			
			$sql = "SELECT id FROM `artwork_tags` WHERE artwork_id='" . $id. "' AND tag_id='" . $tag_id . "'";
			$result = $mysqli->query( $sql );
			
			if( $mysqli->affected_rows == 0 )
			{
				$sql = "INSERT INTO `artwork_tags` (artwork_id,tag_id,ip) VALUES ('" . $id. "','".$tag_id."','". $_SERVER["REMOTE_ADDR"]."')";
				$result = $mysqli->query( $sql );
			}
		}
	}
}

if( !isset($artwork) || $artwork == null || empty($artwork['image']) || !file_exists( 'images/artworks/'.$artwork['image'] ) )
{
	$artwork = null;
	
	while( $artwork == null )
	{
		$sql = "SELECT * FROM `artworks` ORDER BY RAND() LIMIT 1";
		
		$result = $mysqli->query( $sql );
		
		if( $mysqli->affected_rows > 0 )
		{
			$artwork = $result->fetch_assoc();
			$tags = array();
			
			if( !file_exists( 'images/artworks/'.$artwork['image'] ) ) 
				$artwork = null;
		}
	}
}

if( $artwork )
{
	$sql  = "SELECT tag FROM `artwork_tags`";
	$sql .= " LEFT JOIN tags ON artwork_tags.tag_id=tags.id";
	$sql .= " WHERE artwork_tags.artwork_id='" . $artwork['id']. "'";
	
	$result = $mysqli->query( $sql );
	
	$tags = array();
	
	if( $mysqli->affected_rows > 0 )
	{
		while( $row = $result->fetch_assoc() ) $tags[] = $row['tag'];
	}
}

?>
<html>
<head>
<title>Artefacts of the Collective Unconscious</title>
<link href='http://fonts.googleapis.com/css?family=Cedarville+Cursive|Open+Sans' rel='stylesheet' type='text/css'>
<link rel="stylesheet" type="text/css" href="css/themes/<?php echo $_COOKIE['theme']==1?'black':'white'; ?>/theme.css">
<link rel="stylesheet" href="css/style.css" />
<link rel="stylesheet" href="css/tipsy.css" type="text/css" />
<script type="text/javascript" src="js/lib/jquery-1.9.1.js"></script>
<script type="text/javascript" src="js/lib/jquery-ui-1.10.1.custom.min.js"></script>
<script type="text/javascript" src="js/lib/jquery.tools.min.js"></script>
<script type="text/javascript" src="js/lib/jquery.tipsy.js"></script>
<script type="text/javascript">
$(document).ready
(
	function()
	{
		$('input[rel=tooltip]').tipsy( { gravity:'w', offset:5, trigger: 'manual' } );
		$('input[rel=tooltip]').focus( function() { $(this).tipsy("show"); } );
		$('input[rel=tooltip]').blur( function() { $(this).tipsy("hide"); } );
	}
);
</script>
</head>

<body>

	<?php include "includes/header.php"; ?>
	
	<div id="content" style="width:500px">
	
		<div id="subheader">
		
			<h2>Artwork Associations</h2>
			
			<p>We rely on the community to help add associations to artworks in the MONA collection. Now it's your turn!</p>
			<p class='action'>What comes to mind when you look at <span class='artwork_title'><?php echo $artwork['title']; ?></span><?php echo ($artwork['artist']!='Unknown' ? " by <span class='artwork_artist'>" . $artwork['artist'] . '</span>' : '') ?>?</p>
			
			<?php if ( isset($status) ) { ?>
			<div id="status"><?php echo $status; ?></div>
			<?php } ?>
			
		</div>
		
		<div style="display: table-cell; padding: 40px; background-color: <?php echo $artwork['color']; ?>; text-align: center; vertical-align: middle; margin-bottom: 40px">
			<image src="images/artworks/<?php echo $artwork['image']; ?>" />
		</div>
		
		<form method="post">
			
			<input type="hidden" name="id" value="<?php echo $artwork['id']; ?>" />
			
			<div>
				<label for="tags"></label>
				<input 
					id="tags" type="text" name="tags" class="big" 
					placeholder=""
					rel="tooltip" title="(comma separated)"></input>
			</div>
			
			<div>
				<input type="submit" name="submit" value="Submit" />
			</div>
			
		</form>
	
	</div>

</body>

</html>

