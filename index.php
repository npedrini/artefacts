<?php 
include_once "config/config.php";
include_once "includes/session.php";

$date = new DateTime( 'now', new DateTimeZone(TIME_ZONE) );

$showIntro = isset($_SESSION['introShown']) ? false : true;
$expandRange = isset($_GET['expand']) ? $_GET['expand']==="true" : true;

$_SESSION['introShown'] = true;

$stats = array();

if( $showIntro )
{
	//	get stats
	$mysqli = new mysqli( DB_HOST, DB_USER, DB_PASS );
	$mysqli->select_db( DB_NAME );
	
	$sql = "SELECT COUNT(DISTINCT(dreams.id)) AS dream_count, ";
	$sql .= "COUNT(DISTINCT(dreams.user_id)) AS dreamer_count, ";
	$sql .= "COUNT(DISTINCT(dreams.occur_date)) AS dream_dates ";
	$sql .= "FROM `dreams`";
	
	$result = $mysqli->query($sql);
	$row = $result->fetch_assoc();
	
	$stats_temp = array
	(
		'dreams'=>$row['dream_count'],
		'dreamers'=>$row['dreamer_count'],
		'nights'=>$row['dream_dates']
	);
	
	arsort($stats_temp);
	
	foreach($stats_temp as $label=>$value) 
		$stats[] = $value . ' ' . $label;
}
?>
<!DOCTYPE HTML>
<html>
<meta charset="utf-8">
<head>
<title>Artefacts of the Collective Unconscious</title>
<meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
<link href='http://fonts.googleapis.com/css?family=Cedarville+Cursive|Open+Sans' rel='stylesheet' type='text/css'>
<link rel="stylesheet" href="css/aristo/Aristo.css">
<link rel="stylesheet" href="css/tipsy.css" type="text/css">
<link rel="stylesheet" href="css/style.css">
<link id="theme" rel="stylesheet" type="text/css" href="css/themes/<?php echo THEME; ?>/theme.css">
<style>.ui-datepicker { margin-left: -285px; margin-top: -230px; }</style>
</head>

<body id="vis" style="margin: 0px;">
	
	<div id="intro" class="centered">
	
		<div>
			<?php if( isset($_SESSION['origin']) && $_SESSION['origin']=='mona' ) { ?>
			<span id="line1" class="emphasized"><a>Artefacts of the Collective Unconscious</a></span><br/><span id="line2">A repository of MONA visitor dreams, sponsored by MONA Market.</span><div style="margin-top:50px"><img src='images/mona.png'/></div>
			<?php } else { ?>
			<a>
				<span id="line1"><?php echo implode( ', ', $stats ); ?></span>
			</a>
			<?php } ?>
		</div>
		
	</div>
	
	<div id="background"></div>
	
	<div id="foreground" style="height:100%">
		
		<?php 
		if( !EMBEDDED )
			include "includes/header.php"; 
		?>
		
		<!-- d3 -->
		<div id="visualization"></div>
		
		<div id="footer">
			
			<!-- controls -->
			<div id="settings">
				
				<!-- search -->
				<div id="search" class="setting" title="Search">
					<div id="searchIcon"></div>
				</div>
				
				<!-- theme toggle -->
				<div id="theme" class="setting" title="Lights">
					<div id="themeIcon"></div>
				</div>
				
				<div id="save" class="setting" title="Export">
					
					<form id="formSave" action="svg.php" method="POST" target="_blank" style="margin:0px;">
						<input type="hidden" id="data" name="data"></input>
						<div id="saveIcon"></div>
					</form>
					
				</div>
				
				<div id="help" class="setting" title="Help">
					<div id="helpIcon"></div>
				</div>
				
			</div>
			<!-- end settings -->
			
			<div id="legend"></div>	
			
		</div>
		<!-- end footer -->
		
	</div>
	<!-- end foreground -->
	
	<div id="searchPane">
		
		<div class="header">
			<a id="searchClose" href="#">Close</a>
		</div>
		
		<div class="content">
			<span>From:</span><input id="dateFrom" type="text" name="from" placeholder="From" style="display:inline-block" />
			<span>To:</span><input id="dateTo" type="text" name="to" placeholder="To" style="display:inline-block" />
			<input id="searchButton" type="button" value="Go" />
		</div>
		
	</div>
	
	<canvas id="canvas"></canvas>
	
	<script type="text/javascript" src="js/lib/jquery-1.9.1.js"></script>
	<script type="text/javascript" src="js/lib/jquery-ui-1.10.1.custom.min.js"></script>
	<script type="text/javascript" src="js/lib/d3/d3.min.js"></script>
	<script type="text/javascript" src="js/lib/jquery.tipsy.js"></script>
	<script type="text/javascript" src="js/lib/jquery.cookie.js"></script>
	<script type="text/javascript" src="js/lib/canvg/rgbcolor.js"></script> 
	<script type="text/javascript" src="js/lib/canvg/canvg.js"></script> 
	<script type="text/javascript" src="js/graph.js"></script>
	<script type="text/javascript" src="js/main.js"></script>
	<script type="text/javascript">
	var showIntro = <?php echo $showIntro?'true':'false' ?>;
	var expandRange = <?php echo $expandRange?'true':'false' ?>;
	var status = "<?php echo isset($_GET['status'])?$_GET['status']:"";?>";
	var isEmbedded = <?php echo EMBEDDED?'true':'false' ?>;
	var defaultTheme = "<?php echo THEME; ?>";
	var DATE_FORMAT = "<?php echo DATE_FORMAT; ?>";
	</script>
	
</body>
</html>