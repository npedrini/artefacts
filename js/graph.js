function Graph (d3)
{
	this.d3 = d3;
	
	this.LETTERS = "abcdefghijklmnopqrstuvwxyz";
	this.LINK_OPACITY = .1;
	this.LINK_OPACITY_OVER = .8;
	this.NODE_OPACITY = 1;
	this.NODE_OPACITY_ALT = .3;
	this.MONA_ID = 962;
	this.TYPE_ARTWORK = 'artwork';
	this.TYPE_ARTIST = 'artist';
	this.TYPE_DREAM = 'dream';
	this.TYPE_TAG = 'tag';
	
	this.dragging = false;
	this.zoomed = false;
	this.zooming = false;
	this.higlightedNodeType = null;
	this.dateFormat = "mm/dd/yy";
	
	this.ringIntervals = {};
	
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
	
	  	var url = "api/graph/?date_from="+dateFrom+"&date_to="+dateTo;
	  	var self = this;
	  	
	  	this.d3.json(url, function(error,response){ self.loadComplete(error,response.result); } );
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
		soundEffectNodes = [];
		
		soundEffects = [];
		
		this.currentDateFrom = graph.date_from;
		this.currentDateTo = graph.date_to;
		
		var nodesIndexed = {};
		
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
			
			if( d.thumb_path )
			{
				thumbNodes.push( d );
			}
			
			if( d.soundeffect_path )
			{
				soundEffectNodes.push( d );
			}
			
			nodesIndexed[ d.index ] = d;
		}
		
		this.nodesIndexed = nodesIndexed;
		
		var self = this;
		
		$(thumbNodes).each
		(
			function()
			{
				$('<img/>').load
				(
					function(e)
					{
						for(var i=0;i<thumbNodes.length;i++)
						{
							if( thumbNodes[i].thumb_path == e.target.src )
							{
								self.positionNodeTip( thumbNodes[i] );
							}
						}
					}
				)[0].src = this.thumb_path;
			}
		);
		
		$(soundEffectNodes).each
		(
			function()
			{
				var id = this.soundeffect_path;
				var audioElement = $("#audio_" + id);
				
				if( !audioElement.length )
				{
					var path = "assets/audio/" + id + ".mp3";
					
					audioElement = $('<audio/>').attr("id","audio_" + id).attr("src",path).prop("volume",0.1);
					
					$('body').append(audioElement);
				}
			}
		);
    	
		var nodeChildren = {};
		
		for(var l in graph.links)
		{
			var link = graph.links[l];
			
			if( !nodeChildren[link.source] )
				nodeChildren[link.source] = [];
			
			if( !nodeChildren[link.target] )
				nodeChildren[link.target] = [];
			
			if( nodeChildren[link.source].indexOf(link.target) == -1 )
				nodeChildren[link.source].push(link.target);
			
			if( nodeChildren[link.target].indexOf(link.source) == -1 )
				nodeChildren[link.target].push(link.source);
		}
		
		this.nodeChildren = nodeChildren;
		
		var root = graph.nodes[0];
		root.x = this.w/2;
		root.y = this.h/2;
		root.fixed = true;
		
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
		
		//	node fill and outline wrapped in a group
		var nodes = this.vis.selectAll("g.node")
			.data(graph.nodes)
			.enter()
			.append("g")
			.attr("class", "node")
			.attr("id", function(d) { return 'node_'+d.index; })
			.attr("title", function(d) { return self.nodeTitle(d,true); })
			.style("cursor","pointer")
			.on("mouseover", function(d,s){ self.onNodeOver(d,s); })
			.on("mouseout", function(d){ self.onNodeOut(d); })
			.on("click", function(d){ self.onNodeClick(d); })
			.on("touchstart", function(d){ self.onNodeClick(d); })
			.on("dragstart", function(d){ self.onNodeDragStart(d); } )
			.on("dragend", function(d){ self.onNodeDragEnd(d); });
		
		//	node fill
		nodes.append("svg:circle")
			.attr("class", "inner")
			.attr("r", function(d) { return self.nodeRadius(d,!d.stroke); } )
			.style("fill",function(d){ return self.nodeColor(d); })
			.style("fill-opacity",function(d){ return self.nodeFillOpacity(d); });
		
		//	node outline
		nodes.append("svg:circle")
			.attr("class", "outer")
			.attr("r", function(d) { return self.nodeRadius(d,d.stroke); } )
			.style("fill","none")
			.style("stroke", function(d){ return self.nodeColor(d); })
			.style("stroke-opacity", function(d){ return self.nodeStrokeOpacity(d); })
			.style("stroke-dasharray", function(d) { return self.nodeDashArray(d); })
			.style("stroke-width", function(d) { return self.nodeStrokeWidth(d); });
			
		nodes.call(this.force.drag);
		
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
				
				nodes.attr("transform", function(d) { return "translate(" + Math.max(p, Math.min(self.w-p, d.x)) + "," + Math.max(p, Math.min(self.h-p, d.y)) + ")"; } )
				
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
		$('g.node').tipsy( { delayIn: 0, delayOut: 0, fade: false, gravity: 'sw', hoverlock:true, html: true, offset: 5, opacity: 1 } );
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
	
	this.showNodeByIndex = function(index){ this.showNode( this.nodes[index] ); };
	this.showNode = function(d){ this.quiet(); this.zoom(d); };
	
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
		
		this.node = d;
		
		this.x.domain([d.x - this.nodeRadius(d), d.x + this.nodeRadius(d)]);
		this.y.domain([d.y - this.nodeRadius(d), d.y + this.nodeRadius(d)]);
		
		var self = this;
		var t = this.vis.transition().duration(750).each('end',function(){self.onZoomIn();});
		var k = this.r / this.nodeRadius(this.node) / 2;
		
		t.selectAll("g.node")
			.attr("transform", function(d) { return 'translate(' + self.x(d.x) + ',' + self.y(d.y) + ')'; });
		
		t.selectAll("circle.inner")
			.attr("r", function(d) { return k * self.nodeRadius(d,!d.stroke); })
			.style("fill-opacity",function(d){ return self.nodeFillOpacity(d); });
		
		t.selectAll("circle.outer")
			.attr("r", function(d) { return k * self.nodeRadius(d,d.stroke); });
			
		t.selectAll("line")
			.attr("x1", function(d) { return self.x(d.source.x); })
			.attr("y1", function(d) { return self.y(d.source.y); })
			.attr("x2", function(d) { return self.x(d.target.x); })
			.attr("y2", function(d) { return self.y(d.target.y); });
		
		this.dispatchEvent( "zoomInStart", [this.node] );
		
		if( this.d3.event ) this.d3.event.stopPropagation();
	};
	
	this.zoomOut = function() 
	{
		if( !this.zoomed ) return;
		
		this.dispatchEvent( "zoomOutStart" );
		
		var self = this;
		
		var t = this.vis.transition().duration(750).each('end',function(){self.onZoomOut();});
		
		this.vis.selectAll("line").style("stroke-opacity", function(d){ return self.linkOpacity(d); } );
		
		t.selectAll("g.node")
			.attr("transform", function(d) { return 'translate(' + d.x + ',' + d.y + ')'; });
		
		t.selectAll("circle.inner")
			.attr("r", function(d) { return self.nodeRadius(d,!d.stroke); })
			.style("fill-opacity",function(d){ return self.nodeFillOpacity(d); });
		
		t.selectAll("circle.outer")
			.attr("r", function(d) { return self.nodeRadius(d,d.stroke); });
			
		t.selectAll("line")
			.attr("x1", function(d) { return d.source.x; })
			.attr("y1", function(d) { return d.source.y; })
			.attr("x2", function(d) { return d.target.x; })
			.attr("y2", function(d) { return d.target.y; });
		
		if( this.d3.event ) this.d3.event.stopPropagation();
	};

	this.onZoomIn = function()
	{
		this.dispatchEvent( "zoomInComplete", [this.node] );
		
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
			this.node = undefined;
		}
		
		this.force.alpha( .3 );
	};
	
	this.onNodeOver = function (d,s)
	{
		if( this.zooming || this.zoomed || this.dragging ) return;
		
		this.dispatchEvent( "nodeRollOver", [d] );
		
		if( !this.zoomed ) this.forceAlpha = this.force.alpha();
		
		this.force.stop();		//	pause graph resolution
		
		this.hoverNode = d;
		
		if( d.color2 )
		{
			this.vis.select('[id=node_'+d.index+']').style("fill", d.color2);
		}
		
		this.reverberate(d);
		
		var self = this;
		
		this.vis.selectAll("line").style("stroke-opacity", function(d){ return self.linkOpacityOver(d); } );
	};

	this.onNodeOut = function(d)
	{
		if( this.zooming || this.zoomed || this.dragging ) return;
		
		this.dispatchEvent( "nodeRollOut", [d] );
		
		this.hoverNode = null;
		
		this.vis.select('[id=node_'+d.index+']').style("fill", this.nodeColor(d) );
		
		if( !this.zoomed ) this.force.alpha( this.forceAlpha > .05 ? this.forceAlpha : .1 );
		
		var self = this;
		
		this.vis.selectAll("line").style("stroke-opacity", function(d){ return self.linkOpacity(d); } );
	};

	this.onNodeClick = function(d)
	{
		this.dragging = false;
		
		var node = this.vis.select('[id=node_'+d.index+']');
		
		if( d.color2 )
		{
			node.style("fill", d.color2);
		}
		
		this.showNode(d);
	};
	
	this.reverberate = function (d,nodes)
	{
		nodes = nodes || this.graph.nodes.slice();
		
		if( !d )
		{
			var index = Math.floor(Math.random() * nodes.length);
			
			d = nodes[ index ];
			
			nodes.splice(index,1);
			
			if( !$("#audio_" + d.soundeffect_path).length
				&& nodes.length ) 
				return this.reverberate(null,nodes);
		}
		
		this.pluck(d);
		
		if( this.nodeChildren[d.index] )
		{
			var self = this;
			
			$.each
			(
				this.nodeChildren[d.index],
				function(i,nodeIndex)
				{
					setTimeout
					(
						function()
						{
							self.pluck(self.nodesIndexed[nodeIndex]);
						}
						,(i+1)*500
					);
				}
			);
		}
	};
	
	this.pluck = function(d)
	{
		if( !d ) return;
		
		var hasAudio = $("#audio_" + d.soundeffect_path).length;
		if( !hasAudio ) return;
		
		var self = this;
		
		if( this.ringIntervals[ d.index ] )
			clearInterval( this.ringIntervals[ d.index ].interval );
		
		this.ringIntervals[ d.index ] = {count:1,interval:setInterval( function() { self.echo(d,self); },150 )};
		
		this.echo(d,self);
		
		//	play sound
		$("#audio_" + d.soundeffect_path )[0].src = $("#audio_" + d.soundeffect_path )[0].src;
		$("#audio_" + d.soundeffect_path )[0].play();
		
		this.force.alpha( this.forceAlpha > .05 ? this.forceAlpha : .05 );
	};
	
	this.echo = function(d,self)
	{
		var node = self.vis.select('[id=node_'+d.index+']');
		var circle = node.select("circle.outer");
		var radius = parseFloat(circle.attr("r"));
		
		var def = self.ringIntervals[ d.index];
		
		if( d == this.node || def.count >= Math.round(radius) )
			return clearInterval( def.interval );
		
		self.d3.select("svg")
			.append("circle")
				.attr("class","ring")
				.attr("r",radius)
				.attr("transform",node.attr("transform"))
				.style("fill-opacity",0)
				.style("stroke", circle.style("stroke") )
				.style("stroke-opacity",1)
				.style("pointer-events","none")
			.transition()
				.ease("linear")
				.duration(2000)
				.attr("r",radius*radius*2)
				.style("stroke-opacity",0)
				.remove();	
		
		def.count++;
	};
	
	this.quiet = function()
	{
		for(var i in this.ringIntervals)
			clearInterval( this.ringIntervals[i].interval );
		
		this.d3.selectAll(".ring").remove();
	};
	
	/**
	 * Nodes
	 */
	this.nodeRadius = function(d,outer) { return 4 + Math.min( 100, d.value * 1.5 ) + (outer&&d!=this.rootNode?2:0); };
	this.nodeColor = function(d) { return this.themeId == 1 ? (d.color2 != null ? d.color2 : '#fff') : d.color; };
	this.nodeStrokeColor = function(d) { return d.strokeContrast ? (this.themeId == 1?'#fff':'#000') : this.nodeColor(d); };
	this.nodeStrokeOpacity = function(d) 
	{ 
		if( this.higlightedNodeType != null ) 
			return d.node_type == this.higlightedNodeType ? 1 : .1;
		
		return d.node_type==this.TYPE_TAG?.3:this.nodeFillOpacity(d);
	};
	
	this.nodeStrokeWidth = function(d)
	{ 
		if(d.node_type==this.TYPE_DREAM || d.node_type==this.TYPE_ARTWORK) 
			return d.stroke ? 1 : 0;
		else if(d.node_type==this.TYPE_TAG)
			return 2;
			
		return 0;
	};

	this.nodeTitle = function(d,isTooltip)
	{
		isTooltip = isTooltip || false;
		
		var title = d.title ? d.title : ( d.description != null ? d.description.substr( 0, d.description.indexOf('.')+1 ) : '' );
		
		if( d.node_type==this.TYPE_ARTIST )
		{
			title = d.artist;
		}
		else if( d.node_type==this.TYPE_ARTWORK )
		{
			title = '<div style="margin-bottom:5px">' + d.title + '</div>';
		}
		else if( d.node_type==this.TYPE_TAG ) 
		{
			title = d.title;
		}
		else if( d.node_type==this.TYPE_DREAM )
		{
			var dateString;
			
			if( d.occur_date )
			{
				var occurDate = d.occur_date.split('-');
				dateString = DATE_FORMAT
					.replace( /{{date}}/, occurDate[2] )
					.replace( /{{month}}/, occurDate[1] - 1 )
					.replace( /{{year}}/, occurDate[0] );
			}
			
			title = (isTooltip?'DREAM' + (dateString ? ' (' + dateString + ')' : '' ) + ': ' : '') + (d.title ? d.title : ( d.description != null ? d.description.substr( 0, d.description.indexOf('.')+1 ) : '' ) );
		}
		
		title += isTooltip && d.thumb_path != undefined ? '<img onerror="$(this).hide();" src="' + d.thumb_path + '"/>' : '';
		
		return title;
	};

	this.nodeFillOpacity = function(d,defaultAlpha)
	{
		defaultAlpha = defaultAlpha || this.NODE_OPACITY;
		
		if( d == this.rootNode ) return this.NODE_OPACITY_ALT;
		if( d.node_type==this.TYPE_ARTIST || d.node_type==this.TYPE_TAG ) return 0;
		
		if( this.higlightedNodeType != null ) return d.node_type == this.higlightedNodeType ? 1 : .1;
		
		return defaultAlpha;
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
	
	this.highlightNodeType = function(node_type)
	{
		var self = this;

		this.higlightedNodeType = node_type != null ? node_type : null;
		
		this.vis.selectAll("circle.inner")
			.style("fill-opacity",function(d){ return self.nodeFillOpacity(d); });
		this.vis.selectAll("circle.outer")
			.style("stroke-opacity", function(d) { return self.nodeStrokeOpacity(d); } );
	};
	
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
		
		if( callbacks == undefined ) return;
		
		for (var i = 0, l = callbacks.length; i < l; i++) 
		{
			callbacks[i].apply(null, args);
	    };
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
			tip.css('top',d.fisheye?d.fisheye.y - (d.fisheye.z * this.nodeRadius(d)) - inner.offsetHeight - 20:(d.y - this.nodeRadius(d)) - inner.offsetHeight - 20);
		}
	};
};