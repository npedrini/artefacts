/**
 * Initializes the visualization from a date 
 * @param {Date} date
 */
function initGraph( date )
{
	currentDate = date;
	
	//	clear d3 canvas
	d3.select("svg").remove();
	
	w = $('#visualization').width(),h = $('#visualization').height(),r = 300;
	x = d3.scale.linear().range([0, r]),y = d3.scale.linear().range([0, r]);
	
	vis = d3.select("#visualization")
		.append("svg:svg")
		.attr("width", "100%")
		.attr("height", "100%")
		.attr("viewBox", "0 0 " + w + " " + h);
	
  	var url = "json/graph.json.php?date="+date;
  	
  	showLoader();
  	
	d3.json(url, function(error, graph) 
	{
		hideLoader();
		
		if( force ) force.stop();
		
		force = d3.layout.force(.2).charge(-300).friction(.8).size([w, h]);
		//.gravity(.03).friction(.04).theta(.9).
		
		var x_pos = d3.scale.linear().range([0, w]),y_pos = d3.scale.linear().range([0, h]);
		
		x_pos.domain([0,graph.nodes.length]);
		y_pos.domain([0,graph.nodes.length]);
		
		thumbNodes = [];
		thumbnails = [];
		
		for(var i=1;i<graph.nodes.length;i++) 
		{
			var d = graph.nodes[i];
			
			var title = nodeTitle( d );
			var index,x=0,y=0;
			
			for(var j=0;j<title.length;j++)
			{
				if( (index = LETTERS.indexOf( title.charAt(j).toLowerCase() )) > -1 )
				{
					x += (index*100);
					y += (index*100);
				}
			}
			
			x = Math.abs(x%w);
			y = Math.abs(y%h);
			
			d.x = x;
			d.y = y;
			
			if( d.node_type == TYPE_ARTWORK
				&& d.image )
			{
				thumbNodes.push( d );
			}
		}
		
		$(thumbNodes).each
		(
			function()
			{
				$('<img/>').load
				(
					function(e)
					{
						var src = e.target.src;
						src = src.substr( src.lastIndexOf('/')+1 );
						thumbnails.push( src );
						
						for(var i=0;i<thumbNodes.length;i++)
						{
							if( thumbNodes[i].image.replace(/_lg/,'_sm') == src )
							{
								positionNodeTip( thumbNodes[i] );
							}
						}
					}
				)[0].src = "images/artworks/" + this.image.replace(/_lg/,'_sm');
			}
		);
    	
		var root = graph.nodes[0];
		root.x = w/2;
		root.y = h/2;
		root.fixed = true;
		
		rootNode = root;
		
		force
			.nodes(graph.nodes)
			.links(graph.links)
			.linkDistance( function(link,index){ return linkDistance(link,index); } )
			.linkStrength( .5 )
			.on("start", layoutStart)
			.on("end", layoutComplete)
			.start();
		
		var link = vis.selectAll("line.link")
			.data(graph.links)
			.enter()
			.append("line")
			.attr("class", "link")
			.style("stroke-opacity", function(d) { return linkOpacity(d); } )
			.style("stroke-width", 1 )
			.style("stroke", function(d) { return linkColor(d); } )
			.style("stroke-dasharray", function(d) { return linkDashArray(d); });
		
		var node = vis.selectAll("circle.node")
			.data(graph.nodes)
			.enter()
			.append("svg:circle")
			.attr("class", "node")
			.attr("filter", function(d) { return nodeFilter(d); } )
			.attr("id", function(d) { return 'node_'+d.index; } )
			.attr("r", function(d) { return nodeRadius(d); } )
			.attr("stroke-width", function(d){ return nodeStrokeWidth(d); } )
			.attr("title", function(d) { return nodeTitle(d,true); } )
			.style("cursor","pointer")
			.style("fill",function(d){ return nodeColor(d); })
			.style("fill-opacity",function(d){ return nodeFillOpacity(d); })
			.style("stroke", function(d){ return nodeStrokeColor(d); })
			.style("stroke-opacity", function(d){ return nodeStrokeOpacity(d); })
			.style("stroke-dasharray", function(d) { return nodeDashArray(d); })
			.on("mouseover", onNodeOver)
			.on("mouseout", onNodeOut)
			.on("click", onNodeClick)
			.on("dragstart", onNodeDragStart)
			.on("dragend", onNodeDragEnd)
			.call(force.drag);
      	
		nodes = graph.nodes;
		
		force.on
		(
			"tick", 
			function(e)
			{
				if( zooming || zoomed ) return;
				
				var q = d3.geom.quadtree(nodes),i = 0,n = nodes.length;
				
				while (++i < n) { q.visit( collide( nodes[i] ) ); }
				
				var p = 15;
				
				node.attr("cx", function(d) { return d.x = Math.max(p, Math.min(w-p, d.x)); } )
					.attr("cy", function(d) { return d.y = Math.max(p, Math.min(h-p, d.y)); } );
					
				link.attr("x1", function(d) { return d.source.x; })
					.attr("y1", function(d) { return d.source.y; })
					.attr("x2", function(d) { return d.target.x; })
					.attr("y2", function(d) { return d.target.y; });
			}
		);
		
		totalDreams = graph.dream_total;
		totalArtworks = graph.art_total;
		
		//	initialize tooltips now that nodes have been displayed
		$('circle.node').tipsy( { delayIn: 0, delayOut: 0, fade: false, gravity: 'sw', hoverlock:true, html: true, offset: 5, opacity: 1 } );
		
		updateInfo();
	});
}

function positionNodeTip(d)
{
	var n = $('#node_'+d.index);
	var tip = n.data('tipsy').tip();
	
	if( tip )
	{
		var inner = tip.find('.tipsy-inner')[0];
		
		tip.css('left',d.fisheye?d.fisheye.x - 22:d.x);
		tip.css('top',d.fisheye?d.fisheye.y - (d.fisheye.z * nodeRadius(d)) - inner.offsetHeight - 20:(d.y - nodeRadius(d)) - inner.offsetHeight - 20);
	}
}
function collide(node) 
{
	var r = nodeRadius(node) + 100,
		nx1 = node.x - r,
		nx2 = node.x + r,
		ny1 = node.y - r,
		ny2 = node.y + r;
	
	return function(quad, x1, y1, x2, y2) 
	{
		if (quad.point && (quad.point !== node)) 
		{
			var x = node.x - quad.point.x,
				y = node.y - quad.point.y,
				l = Math.sqrt(x * x + y * y),
				r = nodeRadius(node) + nodeRadius(quad.point);
			
			if (l < r) 
			{
				l = (l - r) / l * .5;
				
				node.x -= x *= l;
				node.y -= y *= l;
				
				quad.point.x += x;
				quad.point.y += y;
			}
		
		}
		
		return x1 > nx2 || x2 < nx1 || y1 > ny2 || y2 < ny1;
	};
}

function zoom(d, i) 
{
	//if( running ) return;
	
	highlightRandomNodeStop();
	
	zooming = true;
	
	node = d;
	
	x.domain([d.x - nodeRadius(d), d.x + nodeRadius(d)]);
	y.domain([d.y - nodeRadius(d), d.y + nodeRadius(d)]);
	
	var t = vis.transition().duration(750).each('end',onZoomIn);
	var k = r / nodeRadius(node) / 2;
	
	t.selectAll("circle")
		.attr("cx", function(d) { return x(d.x); })
		.attr("cy", function(d) { return y(d.y); })
		.attr("r", function(d) { return k * nodeRadius(d); })
		.style("fill-opacity",function(d){ return nodeFillOpacity(d); });
	  
	t.selectAll("line")
		.attr("x1", function(d) { return x(d.source.x); })
		.attr("y1", function(d) { return y(d.source.y); })
		.attr("x2", function(d) { return x(d.target.x); })
		.attr("y2", function(d) { return y(d.target.y); });
	
	$('#node_info').remove();
	
	var node_info = "";
	
	if( node.node_type == TYPE_DREAM && node.id == -1 )
	{
		node_info += "<div id='node_info' class='module' style='position:absolute;z-index:1000;width:600px'>";
		node_info += "<div>This could be you! Click <a href='contribute.php'>here</a> to contribute a dream.</div>";
		node_info += "</div>";
	}
	else if( node.node_type == TYPE_DREAM )
	{
		node_info += "<div id='node_info' class='module' style='position:absolute;z-index:1000;width:600px'>";
		
		var title = node.title;
		var description = node.description;
		
		var qs = [];
		
		if( node.city != null ) qs.push( node.city );
		if( node.state != null ) qs.push( node.state );
		if( node.country != null ) qs.push( node.country );
		
		var map_url = "http://maps.google.com/maps?q=" + qs.join(', ');
		
		if( node == rootNode )
		{
			var paragraphs = [];
			
			for(var i=0,uid=0;i<description.length;i++)
			{
				var sentences = [];
				
				for(var j=0;j<description[i].length;j++)
				{
					var index = description[i][j].index;
					var id = 'line_' + uid;
					
					sentences.push( "<a id='"+id+"' class='dream_link' title='" + nodes[index].title + "' href='javascript:$(\"#"+id+"\").tipsy(\"hide\");showNodeByIndex("+index+")'>" + description[i][j].sentence + "</a>" );
					
					uid++;
				}
				
				paragraphs.push( sentences.join(". ") );
			}
			
			description = "<p>" + paragraphs.join( "</p><p>" ) + "</p>";
		}
		
		node_info += "<div class='body'>";
		node_info += "<div>" + title + "</div>";
		node_info += node != rootNode ? "<div style='margin-bottom:20px;font-size:x-small'>Dreamt in <a href='" + map_url + "'>" + node.city + "</a> at " + node.age + " on " + currentDate + "</div>" : "";
		node_info += "<div>" + stripslashes(description) + "</div>";
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
	else if( node.node_type == TYPE_ARTWORK )
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
	else if( node.node_type == TYPE_ARTIST )
	{
		node_info += "<div id='node_info' class='module' style='position:absolute;z-index:1000;width:600px'>";
		
		node_info += "<div><b><i>" + node.artist + "</i></b></div>";
		
		var works = new Array();
		
		for(var j=0;j<nodes.length;j++)
		{
			if(	nodes[j].node_type==TYPE_ARTWORK
				&& nodes[j].artist==node.artist )
			{
				works.push( "<i><a href='javascript:showNodeByIndex("+j+")'>" + nodeTitle(nodes[j]) + "</a></i>" );
				break;
			}
		}
		
		if( works.length ) node_info += works.join( ', ' );
		
		node_info += "</div>";
	}
	else if( node.node_type == "tag" )
	{
		node_info += "<div id='node_info' class='module' style='position:absolute;z-index:1000;width:600px'>";
		
		node_info += "<div class='header'>";
		node_info += "<div class='title'>\"" + nodeTitle(node) + "\"</div>";
		node_info += "</div>";
		
		node_info += "<div class='body' style='width:400px'>";
		
		var artworks = [];
		var dreams = [];
		
		getTagDescription( nodeTitle(node) );
		
		for(var j=0;j<nodes.length;j++)
		{
			if(	nodes[j].tags.indexOf( node.title ) > -1 )
			{
				if( nodes[j].node_type == TYPE_ARTIST )
					artworks.push( "<li><i><a href='javascript:showNodeByIndex("+j+")'>" + nodeTitle(nodes[j]) + "</a></i></li>" );
				else if( nodes[j].node_type == TYPE_DREAM )
					dreams.push( "<li><i><a href='javascript:showNodeByIndex("+j+")'>" + nodeTitle(nodes[j]) + "</a></i></li>" );
					
				break;
			}
		}
		
		if( artworks.length ) node_info += "<div style='margin-top:10px'>artworks: <ul>" + artworks.join(', ') + "</ul></div>";
		if( dreams.length ) node_info += "<div style='margin-top:10px'>dreams:  <ul>" + dreams.join(', ') + "</ul></div>";
		
		node_info += "</div>";
		node_info += "</div>";
	}
		
	$('body').append( node_info );
	$('#node_info').hide();
	$('a[title]').tipsy( { gravity: 'e', offset: 10, opacity: 1 } );
	
	if( d3.event ) d3.event.stopPropagation();
}

function zoomOut() 
{
	if( !zoomed ) return;
	
	$('#node_info').remove();
	
	var t = vis.transition().duration(750).each('end',onZoomOut);
	
	t.selectAll("circle")
		.attr("cx", function(d) { return d.x; })
		.attr("cy", function(d) { return d.y; })
		.attr("r", function(d) { return nodeRadius(d); })
		.style("fill-opacity",function(d){ return nodeFillOpacity(d); });
	
	t.selectAll("line")
		.attr("x1", function(d) { return d.source.x; })
		.attr("y1", function(d) { return d.source.y; })
		.attr("x2", function(d) { return d.target.x; })
		.attr("y2", function(d) { return d.target.y; });
	
	if( d3.event ) d3.event.stopPropagation();
}

function onZoomIn()
{
	var k = r / nodeRadius(node) / 2;
	
	var x_pos = x(node.x) + (k * nodeRadius(node)) + 40;
	var y_pos = y(node.y) - (k * nodeRadius(node)) + 20;
	
	$('#node_info').fadeIn();
	$('#node_info').css("left",x_pos);
	$('#node_info').css("top",y_pos);
	
	zoomed = true;
	zooming = false;
}

function onZoomOut()
{
	zoomed = false;
	zooming = false;
	
	if( node != undefined ) 
	{
		vis.select('[id=node_'+node.index+']').style("fill", nodeColor(node) );
		vis.select('[id=node_'+node.index+']').style("stroke", nodeColor(node) );
	}
	
	force.alpha( .3 );
}

function onNodeOver(d,s)
{
	if( zooming || zoomed || dragging ) return;
	
	if( !zoomed ) forceAlpha = force.alpha();
	
	//onActivity();		//	pause random node highlighting		
	force.stop();		//	pause graph resolution
	
	hoverNode = d;
	
	//	put rolled-over node on top
	//vis.selectAll("circle").sort( function (a, b) { return a.index != d.index ? -1 : 1; } );
	
	if( d.color2 )
	{
		d3.select(this).style("fill", d.color2);
	}
	
	vis.selectAll("line").style("stroke-opacity", function(d){ return linkOpacityOver(d); } );
}

function onNodeOut(d)
{
	if( zooming || zoomed || dragging ) return;
	
	hoverNode = null;
	
	d3.select(this).style("fill", nodeColor(d) );
	
	if( !zoomed ) force.alpha( forceAlpha > .05 ? forceAlpha : .1 );
	
	vis.selectAll("line").style("stroke-opacity", function(d){ return linkOpacity(d); } );
}

function onNodeClick(d)
{
	dragging = false;
	
	var node = d3.select(this);
	
	if( d.color2 )
	{
		node.style("fill", d.color2);
	}
	
	showNode(d);
}

function onNodeDragStart(d){ $( d3.select(this) ).tipsy("hide"); dragging = true; }
function onNodeDragEnd(d){ dragging = false; }

function showNodeByIndex(index)
{
	showNode( nodes[index] );
}

function showNode(d)
{
	zoom(d);
}

function nodeRadius(d)
{
	return Math.max( 5, Math.min( 100, d.value * 5 ) );
}

function nodeColor(d) { return themeId == 1 ? (d.color2 != null ? d.color2 : '#fff') : (d.color2!=null?d.color2:d.color); }
function nodeFilter(d) { return '';return d.node_type==TYPE_DREAM?"url(#blur)":""; }
function nodeStrokeColor(d) { return d.stroke ? d.color2 : (themeId == 1?'#fff':'#000'); }
function nodeStrokeOpacity(d) {return d.stroke || d.node_type == TYPE_TAG ? .3 : 1; }
function nodeStrokeWidth(d) 
{ 
	if(d.node_type==TYPE_ARTWORK) 
		return d.stroke ? 0 : .25;
	else if(d.node_type==TYPE_TAG && d.id != MONA_ID)
		return 2;
		
	return 0;
}

function nodeTitle(d,expanded)
{
	expanded = expanded || false;
	
	if( d.node_type==TYPE_ARTIST )
		return d.artist;
	else if( d.node_type==TYPE_ARTWORK )
	{
		var thumb = d.image.replace(/_lg/,'_sm');
		var title = '<div style="margin-bottom:5px">' + d.title + '</div>';
		title += expanded?'<img onerror="$(this).hide();" src="images/artworks/' + thumb + '"/>':'';
		
		return title;
	}
	else if( d.node_type==TYPE_TAG ) 
		return d.title;
	else if( d.node_type==TYPE_DREAM )
		return d.title ? d.title : ( d.description != null ? d.description.substr( 0, d.description.indexOf('.')+1 ) : '' );
	
	return d.description.substr( 0, d.description.indexOf('.')+1 );
}

function nodeFillOpacity(d)
{
	if( d == rootNode ) return NODE_OPACITY_ALT;
	if( d.node_type==TYPE_ARTIST || d.node_type==TYPE_TAG ) return 0;
	
	return NODE_OPACITY;
}

function nodeDashArray(d) { return d.node_type == TYPE_TAG ? "2,2" : ""; }

function linkDistance(link,index) { return nodeRadius(link.source) + nodeRadius(link.target) + (100 - (100/10*Math.min(10,link.value))); }
function linkOpacity(d) { return LINK_OPACITY; }
function linkColor(d) { return themeId == 1 ? '#fff':'#000'; }
function linkDashArray(d) { return d.type == 'museum_artwork' || d.type == 'museum_artist' ? "2,4" : ""; }
function linkOpacityOver(d) { return (d.source === hoverNode || d.target === hoverNode && !(d.source === rootNode || d.target === rootNode)) ? LINK_OPACITY_OVER : LINK_OPACITY; }

function layoutStart() { running = true; }
function layoutComplete() { running = false; }

/**
* submits artwork tags via ajax
*/
function tagArtwork( id, tags )
{
	$.ajax
	(
		{
			context: document.body,
			dataType: "json",
			url: "json/tag.json.php?id="+id+"&tags="+tags
		}
	)
	.done
	(
		function(data) 
		{
			if( data.success 
				&& data.id )
			{
				taggedArtworkIds.push( data.id );
			}
			
			$('#tag_action').html('<p>Thank you!</p>').show();
			
			hideTagArtwork();
		}
	);
}

function highlightRandomNodeStop()
{
	unhighlightNode();
	
	clearInterval( highlightRandomNode );
}

function highlightNode()
{
	if( hoverNode || zooming || zoomed ) return;
	
	unhighlightNode();
	
	var nodeIndex = Math.round( (nodes.length - 1) * Math.random() );
	highlightedNode = nodes[ nodeIndex ];
	
	var node = vis.select('[id=node_'+highlightedNode.index+']');
	
	if( highlightedNode.color2 != null ) node.style("fill", highlightedNode.color2);
	
	$( node ).tipsy("show");
}

function unhighlightNode()
{
	if( highlightedNode ) 
	{
		var node = vis.select('[id=node_'+highlightedNode.index+']');
		
		node.style("fill", nodeColor(highlightedNode) );
		
		$( node ).tipsy("hide");
	}
	
	if( hoverNode ) $( '#node_'+hoverNode.index ).tipsy("hide");
}

function stripslashes (str) 
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
}

const LETTERS = [ "a","b","c","d","f","g","h","i","j","k","l","m","n","o","p","q","r","s","t","u","v","w","x","y","z"];
const LINK_OPACITY = 0;
const LINK_OPACITY_OVER = .4;
const NODE_OPACITY = 1;
const NODE_OPACITY_ALT = .3;
const MONA_ID = 962;
const TYPE_ARTWORK = 'artwork';
const TYPE_ARTIST = 'artist';
const TYPE_DREAM = 'dream';
const TYPE_TAG = 'tag';

var running=false,zooming=false,zoomed=false,dragging=false;		//	state variables
var node,rootNode,hoverNode,highlightedNode,highlightRandomNode;	//	state-based node references
var w,h,r,x,y,radius,vis,force,forceAlpha,fishEye;					//	vis properties
var nodes,totalDreams,totalArtworks;
var taggedArtworkIds = [];
var thumbnails = [];
var thumbNodes = [];
var currentDate;