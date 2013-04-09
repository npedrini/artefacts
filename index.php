<?php 
include 'config/' . getenv('HTTP_APPLICATION_ENVIRONMENT') . "/config.php";
include 'includes/session.php';

$date = new DateTime( 'now', new DateTimeZone(TIME_ZONE) );

$showIntro = isset($_SESSION['introShown']) ? false : true;

$_SESSION['introShown'] = true;

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
	
	$stats = array();
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
<link id="theme" rel="stylesheet" type="text/css" href="css/themes/<?php echo THEME; ?>/theme.css">
<link rel="stylesheet" href="css/aristo/Aristo.css">
<link rel="stylesheet" href="css/tipsy.css" type="text/css">
<link rel="stylesheet" href="css/style.css">
<style>.ui-datepicker { margin-left: -285px; margin-top: -230px; }</style>
<script type="text/javascript" src="js/lib/jquery-1.9.1.js"></script>
<script type="text/javascript" src="js/lib/jquery-ui-1.10.1.custom.min.js"></script>
<script type="text/javascript" src="js/lib/d3/d3.min.js"></script>
<script type="text/javascript" src="js/lib/jquery.tipsy.js"></script>
<script type="text/javascript" src="js/lib/jquery.cookie.js"></script>
<script type="text/javascript" src="js/lib/canvg/rgbcolor.js"></script> 
<script type="text/javascript" src="js/lib/canvg/canvg.js"></script> 
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
		
		$('#search').on("mouseover",function(e){ $(e.currentTarget).tipsy("show"); });
		$('#search').on("mouseout", function(e){ $(e.currentTarget).tipsy("hide"); });
		$('#icon_search').on("click", function(e){ $(e.currentTarget).parent().tipsy("hide"); });
		
		$('#footer').on("mouseenter",function(e){ $('#search,#gear,#theme_toggle').css('opacity',1);$("#settings").show(); });
		$('#settings').on("mouseleave", function(e){ $('#search,#gear,#theme_toggle').css('opacity',.5);$("#settings").hide(); });
		
		//	pointer cursor
		$('#header h1,#footer,#search').css('cursor', 'pointer');
		
		//	tooltips
		$('#search').tipsy( { gravity: 'e', offset: 10, opacity: 1, trigger: "manual" } );
		$('#theme_toggle,#save').tipsy( { gravity: 'e', offset: 10, opacity: 1 } );
		
		//	init opacity
		$('#search,#gear,#theme_toggle').css('opacity',.5);
		
		//	hide stuff
		$("#background,#foreground,#info,#settings,#intro,#date,#search_overlay").hide();
		
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

		graph = new Graph( d3 );
		graph.addEventListener( "loadStart", onGraphLoadStart );
		graph.addEventListener( "loadComplete", onGraphLoadComplete );
		graph.addEventListener( "zoomInStart", onGraphZoomInStart );
		graph.addEventListener( "zoomInComplete", onGraphZoomInComplete );
		graph.addEventListener( "zoomOutStart", onGraphZoomOutStart );
		
		setTheme( $.cookie("theme") != undefined ? $.cookie("theme") : 1 );
		
		updateInfo();
	}
);

function setTheme( id )
{
	themeId = id;
	
	$('head').remove("#theme").append('<link id="theme" rel="stylesheet" type="text/css" href="css/themes/'+(themeId==1?'black':'white')+'/theme.css">');

	//	TODO: decouple graph from theme
	graph.themeId = id;
	
	setThemeVis();
	
	$.cookie("theme", themeId);
	
	//$('#theme_toggle').attr('title', id==1?'Lights on':'Lights off');
}

function setThemeVis()
{
	if( graph.vis == undefined ) return;
	
	graph.vis.selectAll("circle.node").style("fill",function(d){ return graph.nodeColor(d); });
	graph.vis.selectAll("circle.node").style("stroke",function(d){ return graph.nodeStrokeColor(d); });
	graph.vis.selectAll("line.link").style("stroke",function(d){ return graph.linkColor(d); });
}

function isMobile()
{
	var a = (navigator.userAgent||navigator.vendor||window.opera);
	return /android.+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i.test(a)||/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|e\-|e\/|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(di|rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|xda(\-|2|g)|yas\-|your|zeto|zte\-/i.test(a.substr(0,4));
}

function onMouseUp(e) { dragging = false; }
function onClick(e)
{
	//	zoom out when clicking anywhere but on a node
	if( $(e.target).is('svg') ) graph.zoomOut();
}

/**
 * saves a png using canvg
**/
function save()
{
	//graph.vis.selectAll("circle.node").attr("filter", function(d) { return ''; } );
	
	var svg = new XMLSerializer().serializeToString( $('svg')[0] ).replace("href","xlink:href");
	
	//graph.vis.selectAll("circle.node").attr("filter", function(d) { return graph.nodeFilter(d); } );
	
	$('canvas').attr('width',$('#visualization').width());
	$('canvas').attr('height',$('#visualization').height());

	canvg('canvas',svg);

	var canvas = document.getElementById( "canvas" );
	var data = canvas.toDataURL( "image/png" );
				
	$('#save_form #data').val( data );
	$('#save_form').submit();

	$('canvas').attr('width',0);
	$('canvas').attr('height',0);
}

/**
* shows main d3 visualization
**/
function show()
{
	$("#intro").remove();
	$('body').removeClass('hidden');
	$("#background,#foreground").fadeIn();
	
	initSearch();
}

function initSearch()
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
			
			if( availableDates.length )
			{
				//	create date format string
				var df = DATE_FORMAT
					.replace( /{{date}}/, 'd' )
					.replace( /{{month}}/, 'm' )
					.replace( /{{year}}/, 'yy' );

				$( "#date_from" ).datepicker
				(
					{
						autoSize:true,
						beforeShowDay: shouldEnableDate,
						dateFormat: df,
						changeMonth: true,
						minDate: availableDates[ availableDates.length - 1 ],
						maxDate: availableDates[0],
						numberOfMonths: 1,
						onClose: function( selectedDate ) 
						{
				        	$( "#date_to" ).datepicker( "option", "minDate", selectedDate );
						}
					}
				);

				$( "#date_to" ).datepicker
				(
					{
						autoSize:true,
						beforeShowDay: shouldEnableDate,
						dateFormat: df,
						changeMonth: true,
						minDate: availableDates[ availableDates.length - 1 ],
						maxDate: availableDates[0],
						numberOfMonths: 1,
						onClose: function( selectedDate ) 
						{
				        	$( "#date_from" ).datepicker( "option", "maxDate", selectedDate );
				      	}
				    }
				);
				
				$("#search > .icon").attr("title","Search");
				
				$.extend($.datepicker,{_checkOffset:function(inst,offset,isFixed){return offset}});
				
				//	get init date from hash
				var hash = getHash();
				hash = hash.split(":");
				
				//	set initial date
				$("#date_from").val( availableDates.indexOf( hash[0] ) > -1 ? hash[0] : availableDates[0] );
				$("#date_to").val( availableDates.indexOf( hash[1] ) > -1 ? hash[1] : availableDates[0] );
				
				search();
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

function toggleSearch()
{
	if( $('#search_overlay').css( "display" ) == "none" )
	{
		var position = $('#icon_search').offset();
		
		$('#search_overlay').css( {'left':position.left - $('#search_overlay').width() - 10,'top':position.top - 20} );
		$('#search_overlay').fadeIn(250);
	}
	else
	{
		$('#search_overlay').hide();
	}
}

/**
 * Selects a date
 */
function search()
{
	var date_from = $("#date_from").datepicker( "getDate" );
	var date_to = $("#date_to").datepicker( "getDate" );
	
	var dateFromString = DATE_FORMAT
		.replace( /{{date}}/, date_from.getDate() )
		.replace( /{{month}}/, date_from.getMonth() + 1 )
		.replace( /{{year}}/, date_from.getFullYear() );

	var dateToString = DATE_FORMAT
		.replace( /{{date}}/, date_to.getDate() )
		.replace( /{{month}}/, date_to.getMonth() + 1 )
		.replace( /{{year}}/, date_to.getFullYear() );

	hideNodeInfo();
	
	graph.load( dateFromString, dateToString );
}

/**
 * Updates info drop down
 */
function updateInfo()
{
	//	TODO: use date format
	
	var dateFrom = $("#date_from").datepicker( "getDate" );
	var dateTo = $("#date_to").datepicker( "getDate" );
	
	if( graph.totalDreams == null || dateFrom  == null ) return;
	
	var text = 'Showing ' + graph.totalDreams + ' dreams for ' + dateFrom.getDate() + ' ' + MONTHS[dateFrom.getMonth()] + ' ' + dateFrom.getFullYear();
	
	if( dateTo != dateFrom ) text += " to " + dateTo.getDate() + ' ' + MONTHS[dateTo.getMonth()] + ' ' + dateTo.getFullYear();
	
	$('#info').html( text );
}

/**
 * D3 wrapper event handlers
 */
function onGraphLoadStart(error,g)
{
	showLoader();
}

function onGraphLoadComplete(error,g)
{
	hideLoader();

	dataLoadAttempts++;

	var hash = getHash();
	hash = hash.split(':');
	
	var dateSpecified = availableDates.indexOf( hash[0] ) > -1;
	
	if( graph.totalDreams < 10
		&& dataLoadAttempts < availableDates.length 
		&& !dateSpecified )
	{
		console.log( 'Expanding search', availableDates[dataLoadAttempts] );
		
		$("#date_from").val( availableDates[dataLoadAttempts] );

		search();
	}
	else
	{
		setHash( graph.currentDateFrom + (graph.currentDateTo ? ':' + graph.currentDateTo : '') );
		
		updateInfo();
		setThemeVis();
	}
}

function onGraphZoomInStart( node )
{
	$('#node_info').remove();
	
	var node_info = "";
	
	if( node.node_type == graph.TYPE_DREAM && node.id == -1 )
	{
		node_info += "<div id='node_info' class='module' style='position:absolute;z-index:1000;width:600px'>";
		node_info += "<div>This could be you! Click <a href='contribute.php'>here</a> to contribute a dream.</div>";
		node_info += "</div>";
	} 
	else if( node.node_type == graph.TYPE_DREAM ) 
	{
		node_info += "<div id='node_info' class='module' style='position:absolute;z-index:1000;width:600px'>";
		
		var title = graph.nodeTitle(node);
		var description = node.description;
		
		var qs = [];
		
		if( node.city != null ) qs.push( node.city );
		if( node.state != null ) qs.push( node.state );
		if( node.country != null ) qs.push( node.country );
		
		var map_url = "http://maps.google.com/maps?q=" + qs.join(', ');
		
		if( node == graph.rootNode )
		{
			var paragraphs = [];

			for(var i=0,uid=0;i<description.length;i++)
			{
				var sentences = [];
				var index = description[i][0].index;
				
				var sentencesForDream = [];
				
				for(var j=0;j<description[i].length;j++)
				{
					if( description[i][j].index != index || j == description[i].length-1 )
					{
						var explanation = description[i][j-1].explanation;
						
						if( j == description[i].length-1 ) 
						{
							if( description[i][j].index != index )
							{
								sentencesForDream = [description[i][j].sentence];
								explanation = description[i][j].explanation;
							}
							else
							{
								sentencesForDream.push( description[i][j].sentence );
							}
						}
						
						var id = 'line_' + uid;
						
						sentences.push( "<a id='"+id+"' class='dream_link' title='" + explanation + "' href='javascript:$(\"#"+id+"\").tipsy(\"hide\");graph.showNodeByIndex("+index+")'>" + sentencesForDream.join(". ") + "</a>" );
						
						sentencesForDream = [];
						
						index = description[i][j].index;
						
						uid++;
					}

					sentencesForDream.push( description[i][j].sentence );
				}
				
				paragraphs.push( sentences.join(". ") );
			}
			
			description = "<p>" + paragraphs.join( "</p>.<p>" ) + ".</p>";
		}
		
		node_info += "<div class='body'>";
		node_info += "<div>" + title + "</div>";
		
		node_info += "<div style='margin-bottom:20px;font-size:x-small'>";
		if( node == graph.rootNode )
			node_info += graph.currentDateFrom + " to " + graph.currentDateTo;
		else
			node_info += "Dreamt in <a href='" + map_url + "' target='_blank'>" + node.city + "</a> at " + node.age + " on " + graph.currentDateFrom;
		node_info += "</div>";
		
		node_info += "<div>" + stripslashes(description) + "</div>";
		
		if( node.image != '' && node.image != undefined ) node_info += "<img style='margin-top: 15px;' src='<?php echo IMAGE_PATH; ?>" + node.image + "' />";
		
		node_info += "</div>";
		
		if( node.tags.length ) 
		{
			var tags = [];
			
			for(var i=0;i<node.tags.length;i++)
			{
				tags.push( node.tags[i] );
				
				/*
				for(var j=0;j<nodes.length;j++)
				{
					if(	nodes[j].node_type==TYPE_TAG
						&& nodes[j].title==node.tags[i] )
					{
						tags.push( "<a href='javascript:showNodeByIndex("+j+")'>" + nodeTitle(nodes[j]) + "</a>" );
						break;
					}
				}
				*/
			}
						
			node_info += "<div class='footer'>associations: " + tags.join(', ') + "</div>";
		}
		
		node_info += "</div>";
	}
	else if( node.node_type == graph.TYPE_ARTWORK )
	{
		var artist = node.artist;
		
		node_info += "<div id='node_info' class='module' style='position:absolute;z-index:1000;width:600px'>";
		
		node_info += "<div class='header'>";
		node_info += "<div class='title artwork_title'>" + node.title + (node.year != null ? ', ' + node.year : '') + "</div>";
		node_info += "<div class='subtitle artwork_artist'>" + artist + "</div>";
		node_info += "</div>";
		
		node_info += "<div class='body'>";
		node_info += "<img src='images/artworks/" + node.image + "' />";
		node_info += "</div>";
		
		node_info += "<div class='footer'>";
		node_info += "<div style='font-size:.5em;font-style:italic'>Image sourced from <a href='http://mona-vt.artpro.net.au/theo.php'>MONA</a></div>";
		
		if( taggedArtworkIds.indexOf( node.id ) == -1 )
		{
			node_info += "<div id='tag_action' style='font-size:.7em;'><a href='#' onclick=\"javascript:showTagArtwork()\">Help us tag this artwork</a></div>";
			
			node_info += "<form id='tag_form' method='get' style='display:none'>";
			node_info += "<input type='text' name='tags' style='width:200px;padding:.5em' placeholder='a,b,c' /><br/>";
			node_info += "<a href='javascript:tagArtwork( " + node.id + ", $(\"#tag_form > input\").val() );' style='font-size:.7em;'>submit</a> ";
			node_info += "<a href='#' style='font-size:.7em;' onclick=\"javascript:hideTagArtwork()\">cancel</a>";
			node_info += "</form>";
		}
		
		node_info += "</div>";
		node_info += "</div>";
	}
	else if( node.node_type == graph.TYPE_ARTIST )
	{
		node_info += "<div id='node_info' class='module' style='position:absolute;z-index:1000;width:600px'>";
		
		node_info += "<div><b><i>" + node.artist + "</i></b></div>";
		
		var works = new Array();
		
		for(var j=0;j<nodes.length;j++)
		{
			if(	nodes[j].node_type==graph.TYPE_ARTWORK
				&& nodes[j].artist==node.artist )
			{
				works.push( "<i><a href='javascript:graph.showNodeByIndex("+j+")'>" + graph.nodeTitle(nodes[j]) + "</a></i>" );
				break;
			}
		}
		
		if( works.length ) node_info += works.join( ', ' );
		
		node_info += "</div>";
	}
	else if( node.node_type == graph.TYPE_TAG )
	{
		node_info += "<div id='node_info' class='module' style='position:absolute;z-index:1000;width:600px'>";
		
		node_info += "<div class='header'>";
		node_info += "<div class='title'>\"" + graph.nodeTitle(node) + "\"</div>";
		node_info += "</div>";
		
		node_info += "<div class='body' style='width:400px'>";
		
		var artworks = [];
		var dreams = [];
		
		for(var j=0;j<graph.nodes.length;j++)
		{
			if(	graph.nodes[j].tags.indexOf( node.title ) > -1 )
			{
				if( graph.nodes[j].node_type == graph.TYPE_ARTIST )
					artworks.push( "<li><i><a href='javascript:graph.showNodeByIndex("+j+")'>" + graph.nodeTitle(graph.nodes[j]) + "</a></i></li>" );
				else if( graph.nodes[j].node_type == graph.TYPE_DREAM )
					dreams.push( "<li><i><a href='javascript:graph.showNodeByIndex("+j+")'>" + graph.nodeTitle(graph.nodes[j]) + "</a></i></li>" );
			}
		}
		
		if( artworks.length ) node_info += "<div style='margin-top:10px'>artworks: <ul>" + artworks.join('\n') + "</ul></div>";
		if( dreams.length ) node_info += "<div style='margin-top:10px'>dreams:  <ul>" + dreams.join('\n') + "</ul></div>";
		
		node_info += "</div>";
		node_info += "</div>";
	}
		
	$('body').append( node_info );
	$('#node_info').hide();
	$('a[title]').tipsy( { gravity: 'e', offset: 10, opacity: 1 } );
}

function onGraphZoomInComplete( node )
{
	var k = graph.r / graph.nodeRadius(node) / 2;
	
	var x_pos = graph.x(node.x) + (k * graph.nodeRadius(node)) + 40;
	var y_pos = graph.y(node.y) - (k * graph.nodeRadius(node)) + 20;
	
	$('#node_info').fadeIn();
	$('#node_info').css("left",x_pos);
	$('#node_info').css("top",y_pos);
}

function onGraphZoomOutStart()
{
	hideNodeInfo();
}

function hideNodeInfo()
{
	$('#node_info').remove();
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

/**
 * show/hide spinner
 */
function showLoader()
{
	$("body").append( "<div id='loader' class='centered'><div style='width:50px;'><img src='css/themes/<?php echo THEME; ?>/loader.gif' /></div></div>" );
}

function hideLoader()
{
	$("#loader").remove();
}

/**
 * get/set url hash
 */
function getHash()
{
	var hash = window.location.hash;
	return hash.substr( hash.indexOf('#')+ 1 );
}

function setHash(hash)
{
	window.location.hash = hash;
}

/**
 * utility
 */
function stripslashes(str) 
{
	return (str + '').replace(/\\(.?)/g, function (s, n1) 
		{
			switch (n1) 
			{
				case '\\':
				  return '\\';
				case '0':
				  return '\u0000';
				case '':
				  return '';
				default:
				  return n1;
			}
		}
	);
};

var MONTHS = [ "January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December" ];
var DATE_FORMAT = "<?php echo DATE_FORMAT; ?>";
var availableDates;
var inactivityTimer;
var graph;
var dataLoadAttempts = 0;
var themeId;
</script>
</head>

<body id="vis" style="margin: 0px;">
	
	<div id="intro" class="centered">
	
		<div style='width:300px;margin-left:auto;margin-right:auto;text-align:center'>
			<?php if( isset($_SESSION['origin']) && $_SESSION['origin']=='mona' ) { ?>
			<span id="line1" class="emphasized"><a href="javascript:show();">Artefacts of the Collective Unconscious</a></span><br/><span id="line2">A repository of MONA visitor dreams, sponsored by MONA Market.</span><div style="margin-top:50px"><img src='images/mona.png'/></div>
			<?php } else { ?>
			<a href="javascript:show();">
				<span id="line1"><?php echo implode( ', ', $stats ); ?></span>
			</a>
			<?php } ?>
		</div>
		
	</div>
	
	<div id="background"></div>
	
	<div id="foreground" style="height:100%">
		
		<?php include "includes/header.php"; ?>
		
		<div id="visualization"></div>
	
		<div id="footer">
			
			<div id="settings">
				
				<div id="save" class="setting" title="Save" style="height:20px">
					
					<form id="save_form" action="svg.php" method="POST" target="_blank" style="margin:0px;">
						<input type="hidden" id="data" name="data" />
						<div id="icon_save" onclick="javascript:save()"></div>
					</form>
					
				</div>
				
				<div id="theme_toggle" class="setting" title="Lights">
					<div id="icon_theme" onclick="javascript:toggleTheme()"></div>
				</div>
				
				<div id="search" class="setting" title="Search">
					<div id="icon_search" onclick="toggleSearch();"></div>
				</div>
				
			</div>
			
			<div id="gear" class="setting">
				<div id="icon_gear" style="display:inline-block;vertical-align:middle;"></div>
			</div>
			
		</div>
		
	</div>
	
	<canvas id="canvas" width="0px" height="0px"></canvas>	
	
	<div id="search_overlay">
		
		<div class="header">
			<a href="#" onclick="javascript:toggleSearch();">Close</a>
		</div>
		
		<div class="content">
			<span>From:</span><input id="date_from" type="text" name="from" placeholder="From" style="display:inline-block" />
			<span>To:</span><input id="date_to" type="text" name="to" placeholder="To" style="display:inline-block" />
			<input type="button" value="Go" onclick="javascript:search();toggleSearch();" />
		</div>
		
	</div>
	
</body>

</html>