<?php 
include 'config/' . getenv('HTTP_APPLICATION_ENVIRONMENT') . "/config.php";

session_start();

$date = new DateTime( 'now', new DateTimeZone('Australia/Melbourne') );
$hour = $date->format("G");

if( $hour >= 18 ) $mona_state = "unwinding";
else if( $hour >= 12 ) $mona_state = "awake";
else if( $hour >= 6 ) $mona_state = "rising";
else $mona_state = "asleep";

$showIntro = isset($_SESSION['introShown']) ? false : true;
$_SESSION['introShown'] = true;
?>
<!DOCTYPE HTML>
<html>
<meta charset="utf-8">
<head>
<title>Artefacts of the Collective Unconscious</title>
<meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
<link href='http://fonts.googleapis.com/css?family=Cedarville+Cursive|Open+Sans' rel='stylesheet' type='text/css'>
<link id="theme" rel="stylesheet" type="text/css" href="css/themes/<?php echo $_COOKIE['theme']==1?'black':'white'; ?>/theme.css">
<link rel="stylesheet" href="css/aristo/Aristo.css">
<link rel="stylesheet" href="css/tipsy.css" type="text/css">
<link rel="stylesheet" href="css/style.css">
<style>.ui-datepicker { margin-left: -285px; margin-top: -230px; }</style>
<script type="text/javascript" src="js/lib/jquery-1.9.1.js"></script>
<script type="text/javascript" src="js/lib/jquery-ui-1.10.1.custom.min.js"></script>
<script type="text/javascript" src="js/lib/d3/d3.min.js"></script>
<script type="text/javascript" src="js/lib/d3/d3.fisheye.js"></script>
<script type="text/javascript" src="js/lib/jquery.tipsy.js"></script>
<script type="text/javascript" src="js/lib/jquery.cookie.js"></script>
<script type="text/javascript" src="http://canvg.googlecode.com/svn/trunk/rgbcolor.js"></script> 
<script type="text/javascript" src="http://canvg.googlecode.com/svn/trunk/canvg.js"></script> 
<script type="text/javascript" src="js/theme.js"></script>
<script type="text/javascript" src="js/graph.js"></script>
<script type="text/javascript">
$(document).ready
(
	function()
	{
		//	events
		$(window).on("click", onClick);
		$(window).on("mouseup", onMouseUp);
		
		$('#header h1').on("mouseenter",function(e){ $('#info').fadeIn(); });
		$('#header h1').on("mouseleave",function(e){ $('#info').hide(); });
		
		$('#dateselect').on("mouseover",function(e){ $(e.currentTarget).tipsy("show"); });
		$('#dateselect').on("mouseout", function(e){ $(e.currentTarget).tipsy("hide"); });
		
		$('#footer').on("mouseenter",function(e){ $('#dateselect,#gear,#theme_toggle').css('opacity',1);$("#settings").show(); });
		$('#settings').on("mouseleave", function(e){ $('#dateselect,#gear,#theme_toggle').css('opacity',.5);$("#settings").hide(); });
		
		//	pointer cursor
		$('#header h1,#footer,#dateselect').css('cursor', 'pointer');
		
		//	tooltips
		$('#dateselect').tipsy( { gravity: 'e', offset: 10, opacity: 1, trigger: "manual" } );
		$('#theme_toggle,#save').tipsy( { gravity: 'e', offset: 10, opacity: 1 } );
		
		//	init opacity
		$('#dateselect,#gear,#theme_toggle').css('opacity',.5);
		
		//	hide stuff
		$("#background,#foreground,#info,#settings,intro").hide();
		
		//	fade intro
		var showIntro = <?php echo $showIntro?'true':'false' ?>;
		
		if( showIntro )
		{
			$('body').addClass('hidden');
			
			$('#line1').fadeIn(1000);
			$('#line2').delay(1000).fadeIn(1000);
			$('#intro').show();
		}
		else
		{
			show();
		}
		
		updateInfo();
	}
);

function isMobile()
{
	var a = (navigator.userAgent||navigator.vendor||window.opera);
	return /android.+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i.test(a)||/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|e\-|e\/|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(di|rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|xda(\-|2|g)|yas\-|your|zeto|zte\-/i.test(a.substr(0,4));
}

function onMouseUp(e) { dragging = false; }
function onClick(e)
{
	//	zoom out when clicking anywhere but on a node
	if( $(e.target).is('svg') ) zoomOut();
}

/**
 * saves a png using canvg
**/
function save()
{
	var svg = new XMLSerializer().serializeToString( $('svg')[0] ).replace("href","xlink:href")
	
	console.log( svg );
	
	canvg('canvas',svg);

	var canvas = document.getElementById( "canvas" );
	var data = canvas.toDataURL( "image/png" );
				
	$('#save_form #data').val( data );
	$('#save_form').submit();
}

/**
* shows main d3 visualization
**/
function show()
{
	$("#intro").remove();
	$('body').removeClass('hidden');
	$("#background,#foreground").fadeIn();
	
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
			
			if( availableDates.length )
			{
				//	create date format string
				var df = DATE_FORMAT
					.replace( /{{date}}/, 'd' )
					.replace( /{{month}}/, 'm' )
					.replace( /{{year}}/, 'yy' );
				
				//	initialize date packer
				$("#datepicker").datepicker
				(
					{
						beforeShow: function(){ $('#date').tipsy("hide"); },
						beforeShowDay: shouldEnableDate,
						dateFormat: df,
						buttonImageOnly: true,
						minDate: availableDates[ availableDates.length - 1 ],
						maxDate: availableDates[0],
						onSelect: onDateChange,
						showOn: "button"
					}
				);
				
				$("#dateselect").attr("title","Set Date");
				
				$.extend($.datepicker,{_checkOffset:function(inst,offset,isFixed){return offset}});
				
				//	get init date from hash
				var hash = window.location.hash;
				hash = hash.substr( hash.indexOf('#')+ 1 );
				
				//	set initial date
				$("#datepicker").val( availableDates.indexOf( hash ) > -1 ? hash : availableDates[0] );
				
				onDateChange();
			}
		}
	);
}

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

/**
 * Selects a date
 */
function onDateChange()
{
	var date = $("#datepicker").datepicker( "getDate" );
	
	var dateString = DATE_FORMAT
		.replace( /{{date}}/, date.getDate() )
		.replace( /{{month}}/, date.getMonth() + 1 )
		.replace( /{{year}}/, date.getFullYear() );
	
	window.location.hash = dateString;
	
	initGraph( dateString );
}

/**
 * Updates info drop down
 */
function updateInfo()
{
	var date = $("#datepicker").datepicker( "getDate" );
	
	if( totalDreams == null || totalArtworks  == null || date  == null ) return;
	
	var text = 'Showing ' + totalDreams + ' dreams and ' + totalArtworks + ' artworks from tours on ' + date.getDate() + ' ' + MONTHS[date.getMonth()] + ' ' + date.getFullYear();
	$('#info').html( text );
}

function showTagArtwork()
{
	$('#tag_action').hide();
	$('#tag_form').show();
	$('#tag_form input').focus();
}

function hideTagArtwork()
{
	$('#tag_form > input').val('');
	$('#tag_action').show();
	$('#tag_form').hide();
}

function toggleTheme()
{
	$('#theme_toggle').tipsy('hide');
	setTheme( themeId == 1 ? 0 : 1 ); 
}

function showLoader()
{
	$("body").append( "<div id='loader' class='centered'><div style='width:50px;'><img src='css/themes/<?php echo $_COOKIE['theme']==1?'black':'white'; ?>/loader.gif' /></div></div>" );
}

function hideLoader()
{
	$("#loader").remove();
}

function getTagDescription( tag )
{
	$.ajax
	(
		{
			dataType: 'xml',
			url: 'proxy.php?csurl=http://lookup.dbpedia.org/api/search.asmx/KeywordSearch&QueryString=' + tag,
		}
	)
	.done
	(
		function(xml) 
		{
			console.log(xml,$(xml).find("Result").first());
		}
	);
}

var MONTHS = [ "January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December" ];
var DATE_FORMAT = "<?php echo DATE_FORMAT; ?>";
var availableDates;
var inactivityTimer;
</script>
</head>

<body id="vis" style="margin: 0px;">
	
	<div id="intro" class="centered">
	
		<div style='width:200px;margin-left:auto;margin-right:auto;text-align:center'>
			<a href="javascript:show();"><span id="line1">It's <?php echo $date->format('g:ia'); ?> in Hobart.</span><br/><span id="line2">MONA is <?php echo $mona_state; ?>.</span></a>
		</div>
		
	</div>
	
	<div id="background"></div>
	
	<div id="foreground" style="height:100%">
		
		<?php include "includes/header.php"; ?>
		
		<div id="visualization"></div>
	
		<div id="footer">
			
			<div id="settings">
				
				<div id="save" class="setting" title="Save" style="height:20px">
					<form id="save_form" action="svg.php" method="POST" target="_blank" style="margin:0px">
						<input type="hidden" id="data" name="data" />
						<div id="icon_save" onclick="javascript:save()"></div>
					</form>
				</div>
				<div id="theme_toggle" class="setting"><a href="javascript:toggleTheme()"><div id="icon_theme"></div></a></div>
				<div id="dateselect" class="setting"><input type="hidden" id="datepicker" /></div>
				
			</div>
			
			<div id="gear" class="setting">
				<div id="icon_gear" style="display:inline-block;vertical-align:middle;"></div>
			</div>
			
		</div>
		
	</div>
	
	<canvas id="canvas" width="600px" height="600px"></canvas>	
	
</body>

</html>