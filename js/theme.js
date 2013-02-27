$(document).ready
(
	function()
	{
		setTheme( $.cookie("theme") != undefined ? $.cookie("theme") : 1 );
	}
);

function setTheme( id )
{
	themeId = id;
	
	$('head').remove("#theme").append('<link id="theme" rel="stylesheet" type="text/css" href="css/themes/'+(themeId==1?'black':'white')+'/theme.css">');
	
	setThemeVis();
	
	$.cookie("theme", themeId);
	
	$('#theme_toggle').attr('title', id==1?'Lights on':'Lights off');
}

function setThemeVis()
{
	if( vis == undefined ) return;
	
	vis.selectAll("circle.node").style("fill",function(d){ return nodeColor(d); });
	vis.selectAll("circle.node").style("stroke",function(d){ return nodeStrokeColor(d); });
	vis.selectAll("line.link").style("stroke",function(d){ return linkColor(d); });
}

var vis;
var themeId;