function Graph (d3,imagePath)
{
	this.d3 = d3;
	this.imagePath = imagePath;
	
	this.LETTERS = "abcdefghijklmnopqrstuvwxyz";
	this.LINK_OPACITY = 0;
	this.LINK_OPACITY_OVER = .4;
	this.NODE_OPACITY = 1;
	this.NODE_OPACITY_ALT = .3;
	this.MONA_ID = 962;
	this.TYPE_ARTWORK = 'artwork';
	this.TYPE_ARTIST = 'artist';
	this.TYPE_DREAM = 'dream';
	this.TYPE_TAG = 'tag';
	
	this._events = {};
	
	this.load = function( dateFrom, dateTo )
	{
		this.dispatchEvent( "loadStart" );
		
		this.currentDateFrom = dateFrom;
		this.currentDateTo = dateTo;
		
		//	clear d3 canvas
		this.d3.select("svg").remove();
		
		this.w = $('#visualization').width(),this.h = $('#visualization').height(),this.r = 300;
		this.x = this.d3.scale.linear().range([0, this.r]),this.y = this.d3.scale.linear().range([0, this.r]);
		
		this.vis = this.d3.select("#visualization")
			.append("svg:svg")
			.attr("width", "100%")
			.attr("height", "100%")
			.attr("viewBox", "0 0 " + this.w + " " + this.h);
		
		var defs = this.vis.append("svg:defs");
		
		defs.append("svg:filter")
					.attr("id", "blur")
					.attr("x", "-30%")
					.attr("y", "-30%")
					.attr("width", "140%")
					.attr("height", "140%")
					.append("svg:feGaussianBlur")
					.attr("stdDeviation", 2);
		
	  	var url = "json/graph.json.php?date_from="+dateFrom+"&date_to="+dateTo;
	  	var self = this;
	  	
	  	this.d3.json(url, function(error,graph){ self.loadComplete(error,graph); } );
	};
	
	this.loadComplete = function(error, graph)
	{
		//	stop layout if cooling
		if( this.force ) this.force.stop();
		
		this.force = this.d3.layout.force(.2).charge(-300).friction(.8).size([this.w, this.h]);
		
		var x_pos = this.d3.scale.linear().range([0, this.w]),y_pos = this.d3.scale.linear().range([0, this.h]);
		
		x_pos.domain([0,graph.nodes.length]);
		y_pos.domain([0,graph.nodes.length]);
		
		thumbNodes = [];
		thumbnails = [];
		
		//	set initial node positions from title for consistent layout
		for(var i=1;i<graph.nodes.length;i++) 
		{
			var d = graph.nodes[i];
			
			var title = this.nodeTitle( d );
			var index,x=0,y=0;
			
			for(var j=0;j<title.length;j++)
			{
				if( (index = this.LETTERS.indexOf( title.charAt(j).toLowerCase() )) > -1 )
				{
					x += (index*100);
					y += (index*100);
				}
			}
			
			x = Math.abs(x%this.w);
			y = Math.abs(y%this.h);
			
			d.x = d.px = x;
			d.y = d.py = y;
			
			/*
			if( d.node_type == this.TYPE_ARTWORK
				&& d.image )
			{
				thumbNodes.push( d );
			}
			*/
		}
		
		/*
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
    	*/
    	
		var root = graph.nodes[0];
		root.x = this.w/2;
		root.y = this.h/2;
		root.fixed = true;
		
		var self = this;
		
		this.rootNode = root;
		
		this.force
			.nodes(graph.nodes)
			.links(graph.links)
			.linkStrength( .5 )
			.linkDistance( function(link,index){ return self.linkDistance(link,index); } )
			.on("start", this.onLayoutStart)
			.on("end", this.onLayoutComplete)
			.start();
		
		var links = this.vis.selectAll("line.link")
			.data(graph.links)
			.enter()
			.append("line")
			.attr("class", "link")
			.style("stroke-opacity", function(d) { return self.linkOpacity(d); } )
			.style("stroke-width", 1 )
			.style("stroke", function(d) { return self.linkColor(d); } )
			.style("stroke-dasharray", function(d) { return self.linkDashArray(d); });
		
		var nodes = this.vis.selectAll("circle.node")
			.data(graph.nodes)
			.enter()
			.append("svg:circle")
			.attr("class", "node")
			.attr("filter", function(d) { return self.nodeFilter(d); } )
			.attr("id", function(d) { return 'node_'+d.index; } )
			.attr("r", function(d) { return self.nodeRadius(d); } )
			.attr("stroke-width", function(d){ return self.nodeStrokeWidth(d); } )
			.attr("title", function(d) { return self.nodeTitle(d,true); } )
			.style("cursor","pointer")
			.style("fill",function(d){ return self.nodeColor(d); })
			.style("fill-opacity",function(d){ return self.nodeFillOpacity(d); })
			.style("stroke", function(d){ return self.nodeStrokeColor(d); })
			.style("stroke-opacity", function(d){ return self.nodeStrokeOpacity(d); })
			.style("stroke-dasharray", function(d) { return self.nodeDashArray(d); })
			.on("mouseover", function(d,s){ self.onNodeOver(d,s); })
			.on("mouseout", function(d){ self.onNodeOut(d); })
			.on("click", function(d){ self.onNodeClick(d); })
			.on("dragstart", function(d){ self.onNodeDragStart(d); })
			.on("dragend", function(d){ self.onNodeDragEnd(d); })
			.call(this.force.drag);
		
		this.nodes = graph.nodes;
		
		this.force.on
		(
			"tick", 
			function(e)
			{
				if( self.zooming || self.zoomed ) return;
				
				var q = self.d3.geom.quadtree(self.nodes),i = 0,n = self.nodes.length;
				
				while (++i < n) { q.visit( self.collide( self.nodes[i] ) ); }
				
				var p = 15;
				
				nodes.attr("cx", function(d) { return d.x = Math.max(p, Math.min(self.w-p, d.x)); } )
					.attr("cy", function(d) { return d.y = Math.max(p, Math.min(self.h-p, d.y)); } );
				
				links.attr("x1", function(d) { return d.source.x; })
					.attr("y1", function(d) { return d.source.y; })
					.attr("x2", function(d) { return d.target.x; })
					.attr("y2", function(d) { return d.target.y; });
			}
		);
		
		this.totalDreams = graph.dream_total;
		this.totalArtworks = graph.art_total;
		this.graph = graph;
		
		this.dispatchEvent( "loadComplete" );
		
		//	initialize tooltips now that nodes have been displayed
		$('circle.node').tipsy( { delayIn: 0, delayOut: 0, fade: false, gravity: 'sw', hoverlock:true, html: true, offset: 5, opacity: 1 } );
	};
	
	this.collide = function(node) 
	{
		var r  = node.r + 100,
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
					r = node.r + quad.point.r;
				
				if (l < r) 
				{
					l = (l - r) / l * .5;
					
					node.x -= x *= l;
					node.y -= y *= l;
					
					quad.point.x += x;
					quad.point.y += y;
				};			
			}
			
			return x1 > nx2 || x2 < nx1 || y1 > ny2 || y2 < ny1;
		};
	};
	
	this.showNodeByIndex = function(index){ this.showNode( nodes[index] ); };
	this.showNode = function(d){ this.zoom(d); };
	
	/**
	 * Internal event handlers
	 */
	this.onLayoutStart = function() { this.running = true; };
	this.onLayoutComplete = function() { this.running = false; };
	this.onNodeDragStart = function(d){ $( this.d3.select(this) ).tipsy("hide"); this.dragging = true; };
	this.onNodeDragEnd = function(d){ this.dragging = false; };
	
	this.zoom = function(d, i) 
	{
		this.zooming = true;
		
		var node = d;
		this.node = d;
		
		this.x.domain([d.x - this.nodeRadius(d), d.x + this.nodeRadius(d)]);
		this.y.domain([d.y - this.nodeRadius(d), d.y + this.nodeRadius(d)]);
		
		var self = this;
		var t = this.vis.transition().duration(750).each('end',function(){self.onZoomIn();});
		var k = this.r / this.nodeRadius(this.node) / 2;
		
		t.selectAll("circle")
			.attr("cx", function(d) { return self.x(d.x); })
			.attr("cy", function(d) { return self.y(d.y); })
			.attr("r", function(d) { return k * self.nodeRadius(d); })
			.style("fill-opacity",function(d){ return self.nodeFillOpacity(d); });
		  
		t.selectAll("line")
			.attr("x1", function(d) { return self.x(d.source.x); })
			.attr("y1", function(d) { return self.y(d.source.y); })
			.attr("x2", function(d) { return self.x(d.target.x); })
			.attr("y2", function(d) { return self.y(d.target.y); });
		
		//	TODO: externalize?
		
		$('#node_info').remove();
		
		var node_info = "";
		
		if( node.node_type == this.TYPE_DREAM && node.id == -1 )
		{
			node_info += "<div id='node_info' class='module' style='position:absolute;z-index:1000;width:600px'>";
			node_info += "<div>This could be you! Click <a href='contribute.php'>here</a> to contribute a dream.</div>";
			node_info += "</div>";
		} 
		else if( node.node_type == this.TYPE_DREAM ) 
		{
			node_info += "<div id='node_info' class='module' style='position:absolute;z-index:1000;width:600px'>";
			
			var title = node.title;
			var description = node.description;
			
			var qs = [];
			
			if( node.city != null ) qs.push( node.city );
			if( node.state != null ) qs.push( node.state );
			if( node.country != null ) qs.push( node.country );
			
			var map_url = "http://maps.google.com/maps?q=" + qs.join(', ');
			
			if( node == this.rootNode )
			{
				var paragraphs = [];
				
				for(var i=0,uid=0;i<description.length;i++)
				{
					var sentences = [];
					
					for(var j=0;j<description[i].length;j++)
					{
						var index = description[i][j].index;
						var id = 'line_' + uid;
						
						sentences.push( "<a id='"+id+"' class='dream_link' title='" + this.nodes[index].title + "' href='javascript:$(\"#"+id+"\").tipsy(\"hide\");showNodeByIndex("+index+")'>" + description[i][j].sentence + "</a>" );
						
						uid++;
					}
					
					paragraphs.push( sentences.join(". ") );
				}
				
				description = "<p>" + paragraphs.join( "</p><p>" ) + "</p>";
			}
			
			node_info += "<div class='body'>";
			node_info += "<div>" + title + "</div>";
			node_info += node != this.rootNode ? "<div style='margin-bottom:20px;font-size:x-small'>Dreamt in <a href='" + map_url + "' target='_blank'>" + node.city + "</a> at " + node.age + " on " + this.currentDateFrom + "</div>" : "";
			node_info += "<div>" + this.stripslashes(description) + "</div>";
			
			console.log( node.image );
			
			if( node.image != '' && node.image != undefined ) node_info += "<img src='" + this.imagePath + node.image + "' />";
			
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
		else if( node.node_type == this.TYPE_ARTWORK )
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
		else if( node.node_type == this.TYPE_ARTIST )
		{
			node_info += "<div id='node_info' class='module' style='position:absolute;z-index:1000;width:600px'>";
			
			node_info += "<div><b><i>" + node.artist + "</i></b></div>";
			
			var works = new Array();
			
			for(var j=0;j<nodes.length;j++)
			{
				if(	nodes[j].node_type==this.TYPE_ARTWORK
					&& nodes[j].artist==node.artist )
				{
					works.push( "<i><a href='javascript:showNodeByIndex("+j+")'>" + this.nodeTitle(nodes[j]) + "</a></i>" );
					break;
				}
			}
			
			if( works.length ) node_info += works.join( ', ' );
			
			node_info += "</div>";
		}
		else if( node.node_type == this.TYPE_TAG )
		{
			node_info += "<div id='node_info' class='module' style='position:absolute;z-index:1000;width:600px'>";
			
			node_info += "<div class='header'>";
			node_info += "<div class='title'>\"" + this.nodeTitle(node) + "\"</div>";
			node_info += "</div>";
			
			node_info += "<div class='body' style='width:400px'>";
			
			var artworks = [];
			var dreams = [];
			
			for(var j=0;j<this.nodes.length;j++)
			{
				if(	this.nodes[j].tags.indexOf( node.title ) > -1 )
				{
					if( this.nodes[j].node_type == this.TYPE_ARTIST )
						artworks.push( "<li><i><a href='javascript:showNodeByIndex("+j+")'>" + this.nodeTitle(this.nodes[j]) + "</a></i></li>" );
					else if( this.nodes[j].node_type == this.TYPE_DREAM )
						dreams.push( "<li><i><a href='javascript:showNodeByIndex("+j+")'>" + this.nodeTitle(this.nodes[j]) + "</a></i></li>" );
						
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
		
		if( this.d3.event ) this.d3.event.stopPropagation();
	};
	
	this.zoomOut = function() 
	{
		if( !this.zoomed ) return;
		
		$('#node_info').remove();
		
		var self = this;
		var t = this.vis.transition().duration(750).each('end',function(){self.onZoomOut();});
		
		t.selectAll("circle")
			.attr("cx", function(d) { return d.x; })
			.attr("cy", function(d) { return d.y; })
			.attr("r", function(d) { return self.nodeRadius(d); })
			.style("fill-opacity",function(d){ return self.nodeFillOpacity(d); });
		
		t.selectAll("line")
			.attr("x1", function(d) { return d.source.x; })
			.attr("y1", function(d) { return d.source.y; })
			.attr("x2", function(d) { return d.target.x; })
			.attr("y2", function(d) { return d.target.y; });
		
		if( this.d3.event ) this.d3.event.stopPropagation();
	};

	this.onZoomIn = function()
	{
		var k = this.r / this.nodeRadius(this.node) / 2;
		
		var x_pos = this.x(this.node.x) + (k * this.nodeRadius(this.node)) + 40;
		var y_pos = this.y(this.node.y) - (k * this.nodeRadius(this.node)) + 20;
		
		$('#node_info').fadeIn();
		$('#node_info').css("left",x_pos);
		$('#node_info').css("top",y_pos);
		
		this.zoomed = true;
		this.zooming = false;
	};

	this.onZoomOut = function()
	{
		var self = this;
		
		this.zoomed = false;
		this.zooming = false;
		
		if( this.node != undefined ) 
		{
			this.vis.select('[id=node_'+this.node.index+']').style("fill", self.nodeColor(self.node) );
			this.vis.select('[id=node_'+this.node.index+']').style("stroke", self.nodeStrokeColor(self.node) );
		}
		
		this.force.alpha( .3 );
	};
	
	this.onNodeOver = function (d,s)
	{
		if( this.zooming || this.zoomed || this.dragging ) return;
		
		if( !this.zoomed ) this.forceAlpha = this.force.alpha();
		
		this.force.stop();		//	pause graph resolution
		
		this.hoverNode = d;
		
		if( d.color2 )
		{
			this.vis.select('[id=node_'+d.index+']').style("fill", d.color2);
		}
		
		var self = this;
		
		this.vis.selectAll("line").style("stroke-opacity", function(d){ return self.linkOpacityOver(d); } );
	};

	this.onNodeOut = function(d)
	{
		if( this.zooming || this.zoomed || this.dragging ) return;
		
		this.hoverNode = null;
		
		this.vis.select('[id=node_'+d.index+']').style("fill", this.nodeColor(d) );
		
		if( !this.zoomed ) this.force.alpha( this.forceAlpha > .05 ? this.forceAlpha : .1 );
		
		var self = this;
		this.vis.selectAll("line").style("stroke-opacity", function(d){ return self.linkOpacity(d); } );
	};

	this.onNodeClick = function(d)
	{
		dragging = false;
		
		var node = this.vis.select('[id=node_'+d.index+']');
		
		if( d.color2 )
		{
			node.style("fill", d.color2);
		}
		
		this.showNode(d);
	};
	
	/**
	 * Nodes
	 */
	this.nodeRadius = function(d) { return 5 + Math.min( 100, d.value * 1.5 ); };
	this.nodeColor = function(d) { return this.themeId == 1 ? (d.color2 != null ? d.color2 : '#fff') : d.color; };
	this.nodeFilter = function(d) { return "";return d.node_type==this.TYPE_DREAM?"url(#blur)":""; };
	this.nodeStrokeColor = function(d) { return d.stroke ? (this.themeId == 1?'#fff':'#000') : d.color2; };
	this.nodeStrokeOpacity = function(d) { return d.stroke ? .3 : 1; };
	
	this.nodeStrokeWidth = function(d)
	{ 
		if(d.node_type==this.TYPE_DREAM || d.node_type==this.TYPE_ARTWORK) 
			return d.stroke ? .5 : 0;
		else if(d.node_type==this.TYPE_TAG)
			return 2;
			
		return 0;
	};

	this.nodeTitle = function(d,expanded)
	{
		expanded = expanded || false;
		
		if( d.node_type==this.TYPE_ARTIST )
			return d.artist;
		else if( d.node_type==this.TYPE_ARTWORK )
		{
			var thumb = d.image.replace(/_lg/,'_sm');
			var title = '<div style="margin-bottom:5px">' + d.title + '</div>';
			title += expanded?'<img onerror="$(this).hide();" src="images/artworks/' + thumb + '"/>':'';
			
			return title;
		}
		else if( d.node_type==this.TYPE_TAG ) 
			return d.title;
		else if( d.node_type==this.TYPE_DREAM )
			return d.title ? d.title : ( d.description != null ? d.description.substr( 0, d.description.indexOf('.')+1 ) : '' );
		
		return d.description.substr( 0, d.description.indexOf('.')+1 );
	};

	this.nodeFillOpacity = function(d)
	{
		if( d == this.rootNode ) return this.NODE_OPACITY_ALT;
		if( d.node_type==this.TYPE_ARTIST || d.node_type==this.TYPE_TAG ) return 0;
		
		return this.NODE_OPACITY;
	};

	this.nodeDashArray = function(d) { return d.node_type == this.TYPE_TAG ? "2,2" : ""; };

	/**
	 * Edges
	 */
	this.linkDistance = function(link,index) { return this.nodeRadius(link.source) + this.nodeRadius(link.target) + (100 - (100/10*Math.min(10,link.value))); };
	this.linkOpacity = function(d) { return this.LINK_OPACITY; };
	this.linkColor = function(d) { return this.themeId == 1 ? '#fff':'#000'; };
	this.linkDashArray = function(d) { return d.type == 'museum_artwork' || d.type == 'museum_artist' ? "2,4" : ""; };
	this.linkOpacityOver = function(d) { return (d.source === this.hoverNode || d.target === this.hoverNode) ? this.LINK_OPACITY_OVER : this.LINK_OPACITY; };
	
	/**
	 * Event dispatching
	 */
	this.addEventListener = function(eventName, callback) 
	{
		var events = this._events,callbacks = events[eventName] = events[eventName] || [];
		callbacks.push(callback);
	};
	  
	this.dispatchEvent = function(eventName, args) 
	{
		var callbacks = this._events[eventName];
		
		for (var i = 0, l = callbacks.length; i < l; i++) 
		{
			callbacks[i].apply(null, args);
	    };
	};
	
	/**
	 * Utility
	 */
	this.stripslashes = function(str) 
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
	
	/**
	 * Deprecated
	 */
	this.tagArtwork = function( id, tags )
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
				
				this.hideTagArtwork();
			}
		);
	};
	
	this.positionNodeTip = function(d)
	{
		var n = $('#node_'+d.index);
		var tip = n.data('tipsy').tip();
		
		if( tip )
		{
			var inner = tip.find('.tipsy-inner')[0];
			
			tip.css('left',d.fisheye?d.fisheye.x - 22:d.x);
			tip.css('top',d.fisheye?d.fisheye.y - (d.fisheye.z * nodeRadius(d)) - inner.offsetHeight - 20:(d.y - nodeRadius(d)) - inner.offsetHeight - 20);
		}
	};
	
	
	
};