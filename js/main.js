(function()
{
	$(document).ready
	(
		function()
		{
			//	look for and display `status` param in qs
			var status = window.status;

			if( status != '' )
			{
				$("#header")
					.prepend
					(
						$( "<div id='flash'>" + status + "</div>" )
							.css('cursor','pointer')
							.on('click',function(){ $(this).remove(); } )
					);
			}

			$("#info").hide();

			//	window listeners
			$(window).on("click", onClick);
			$(window).on("mouseup", onMouseUp);

			//	fade in/out site info on header mouseenter/leave
			$('#header h1').on("mouseenter",function(e){ $('#info').fadeIn(); });
			$('#header h1').on("mouseleave",function(e){ $('#info').hide(); });

			$('#search').on("mouseover",function(e){ $(e.currentTarget).tipsy("show"); });
			$('#search').on("mouseout", function(e){ $(e.currentTarget).tipsy("hide"); });
			$('#searchIcon').on("click", function(e){ $(e.currentTarget).parent().tipsy("hide"); toggleSearch(); });
			$('#saveIcon').on("click", save);
			$('#searchClose').on("click", toggleSearch);
			$('#searchButton').on("click", doSearch);
			$('#themeIcon').on("click", toggleTheme);

			//	pointer cursor
			$('#header h1,#footer,#search,#helpIcon').css('cursor', 'pointer');

			//	go to about page on help icon click
			$('#helpIcon').on("click",function(e){ window.location.href = "about.php"; } );

			$('#intro a').on("click",show);

			//	tooltips
			$('#search').tipsy( { gravity: 'e', offset: 10, opacity: 1, trigger: "manual" } );
			$('#theme,#save,#help').tipsy( { gravity: 'e', offset: 10, opacity: 1 } );

			//	fade intro
			var showIntro = window.showIntro;

			isEmbedded = window.isEmbedded;

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
			graph.dateFormat = DATE_FORMAT;
			graph.addEventListener( "loadStart", onGraphLoadStart );
			graph.addEventListener( "loadComplete", onGraphLoadComplete );
			graph.addEventListener( "zoomInStart", onGraphZoomInStart );
			graph.addEventListener( "zoomInComplete", onGraphZoomInComplete );
			graph.addEventListener( "zoomOutStart", onGraphZoomOutStart );
			graph.addEventListener( "nodeRollOver", onGraphNodeRollOver );
			graph.addEventListener( "nodeRollOut", onGraphNodeRollOut );

			window.graph = graph;

			$('#gear,#help,#search,#theme').css('opacity',.3);

			setTheme( isEmbedded ? 1 : ($.cookie("theme") != undefined ? $.cookie("theme") : 1) );

			if( document.url.indexOf("dream-design") > -1 ) {
			 setTheme(0);
			}
			updateInfo();
		}
	);

	function showLoader()
	{
		$("body").append( "<div id='loader' class='centered'><div style='width:50px;'><img src='css/themes/" + window.defaultTheme + "/loader.gif' /></div></div>" );
	}

	function hideLoader()
	{
		$("#loader").remove();
	}

	function setTheme( id )
	{
		themeId = id;

		$('head').remove("#theme").append('<link id="theme" rel="stylesheet" type="text/css" href="css/themes/'+(themeId==1?'black':'white')+'/theme.css">');

		if( isEmbedded )
			$('head').remove("#themeOverrides").append("<style>body, #background, #header { background: transparent; background-image: none; }</style>");

		//	TODO: decouple graph from theme
		graph.themeId = id;

		setThemeVis();

		$("#legend div:nth-child(3) image").attr("xlink:href","css/themes/"+(themeId==1?'black':'white')+"/icons/gear.png");

		$.cookie("theme", themeId);
	}

	function setThemeVis()
	{
		if( graph.vis == undefined ) return;

		graph.vis.selectAll("circle.inner").style("fill",function(d){ return graph.nodeColor(d); });
		graph.vis.selectAll("circle.outer").style("stroke",function(d){ return graph.nodeStrokeColor(d); });
		graph.vis.selectAll("line.link").style("stroke",function(d){ return graph.linkColor(d); });
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
		var svg = new XMLSerializer().serializeToString( $('#visualization > svg')[0] ).replace("href","xlink:href");

		$('canvas').attr('width',$('#visualization').width());
		$('canvas').attr('height',$('#visualization').height());

		canvg('canvas',svg);

		var canvas = document.getElementById( "canvas" );
		var data = canvas.toDataURL( "image/png" );

		$('#formSave #data').val( data );
		$('#formSave').submit();

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

		if( isEmbedded )
			$("#foreground").fadeIn();
		else
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
				url: 'api/dates/'
			}
		)
		.done
		(
			function(response)
			{
				//	store list of available dates
				availableDates = response.results;

				if( availableDates.length )
				{
					//	create date format string
					var df = DATE_FORMAT
						.replace( /{{date}}/, 'd' )
						.replace( /{{month}}/, 'm' )
						.replace( /{{year}}/, 'yy' );

					$( "#dateFrom" ).datepicker
					(
						{
							autoSize:true,
							beforeShowDay: shouldEnableDate,
							dateFormat: df,
							changeMonth: true,
							constrainInput: true,
							minDate: availableDates[ availableDates.length - 1 ],
							maxDate: availableDates[0],
							numberOfMonths: 1,
							onClose: function( selectedDate )
							{
								$( "#dateTo" ).datepicker( "option", "minDate", selectedDate );
							}
						}
					);

					$( "#dateTo" ).datepicker
					(
						{
							autoSize:true,
							beforeShowDay: shouldEnableDate,
							dateFormat: df,
							changeMonth: true,
							constrainInput: true,
							minDate: availableDates[ availableDates.length - 1 ],
							maxDate: availableDates[0],
							numberOfMonths: 1,
							onClose: function( selectedDate )
							{
					        	$( "#dateFrom" ).datepicker( "option", "maxDate", selectedDate );
					      	}
					    }
					);

					$("#search > .icon").attr("title","Search");

					$.extend($.datepicker,{_checkOffset:function(inst,offset,isFixed){return offset}});

					//	get init date from hash
					var hash = getHash();
					hash = hash.split(":");

					//	set initial date
					$("#dateFrom").val( availableDates.indexOf( hash[0] ) > -1 ? hash[0] : availableDates[0] );
					$("#dateTo").val( availableDates.indexOf( hash[1] ) > -1 ? hash[1] : availableDates[0] );

					doSearch();
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
		$('#searchPane').css( "display" ) == "none" ? showSearch() : hideSearch();
	}

	function showSearch()
	{
		if( $('#searchPane').css( "display" ) == "visible" ) return;

		var position = $('#searchIcon').offset();

		$('#searchPane').css( {'left':position.left - $('#searchPane').width() - 10,'top':position.top - 10} );
		$('#searchPane').fadeIn(250);
	}

	function hideSearch()
	{
		if( $('#searchPane').css( "display" ) == "none" ) return;

		$('#searchPane').hide();
	}

	/**
	 * Selects a date
	 */
	function doSearch()
	{
		var dateFrom = $("#dateFrom").datepicker( "getDate" );
		var dateTo = $("#dateTo").datepicker( "getDate" );

		var dateFromString = DATE_FORMAT
			.replace( /{{date}}/, dateFrom.getDate() )
			.replace( /{{month}}/, dateFrom.getMonth() + 1 )
			.replace( /{{year}}/, dateFrom.getFullYear() );

		var dateToString = DATE_FORMAT
			.replace( /{{date}}/, dateTo.getDate() )
			.replace( /{{month}}/, dateTo.getMonth() + 1 )
			.replace( /{{year}}/, dateTo.getFullYear() );

		hideNodeInfo();

		graph.load( dateFromString, dateToString );

		hideSearch();
	}

	/**
	 * Updates info drop down
	 */
	function updateInfo()
	{
		//	TODO: use date format

		var dateFrom = $("#dateFrom").datepicker( "getDate" );
		var dateTo = $("#dateTo").datepicker( "getDate" );

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

		//	narrow search
		if( window.expandRange
			&& graph.totalDreams < 10
			&& dataLoadAttempts < availableDates.length
			&& !dateSpecified )
		{
			$("#dateFrom").val( availableDates[dataLoadAttempts] );

			doSearch();
		}
		else if(!dateSearch)
		{
			setHash( graph.currentDateFrom + (graph.currentDateTo ? ':' + graph.currentDateTo : '') );

			$("#dateFrom").val( graph.currentDateFrom );
			$("#dateTo").val( graph.currentDateTo );

			if( !isEmbedded)
				drawLegend();

			updateInfo();
			setThemeVis();
			play();
		}
			else
			{
				setHash();

				if( !isEmbedded)
				drawLegend();

				updateInfo();
				setThemeVis();
				play();
			}


	}

	function play()
	{
		pause();

		playInterval = setInterval( function(){ graph.reverberate();play(); }, (5 + Math.random() * 10) * 1000 );
	}

	function pause()
	{
		clearInterval( playInterval );
	}

	function onGraphZoomInStart( node )
	{
		pause();

		$('#nodeInfo').remove();

		var nodeInfo = "";

		if( node.node_type == graph.TYPE_DREAM && node.id == -1 )
		{
			nodeInfo += "<div id='nodeInfo' class='module' style='position:absolute;z-index:1000;width:600px'>";
			nodeInfo += "<div>This could be you! Click <a href='contribute.php'>here</a> to contribute a dream.</div>";
			nodeInfo += "</div>";
		}
		else if( node.node_type == graph.TYPE_DREAM )
		{
			nodeInfo += "<div id='nodeInfo' class='module' style='position:absolute;z-index:1000;width:600px'>";

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

			nodeInfo += "<div class='body'>";
			nodeInfo += "<div>" + title + "</div>";

			nodeInfo += "<div style='margin-bottom:20px;font-size:x-small'>";
			if( node == graph.rootNode )
				nodeInfo += graph.currentDateFrom + " to " + graph.currentDateTo;
			else
				nodeInfo += "Dreamt on " + graph.currentDateFrom + " in <a href='" + map_url + "' target='_blank'>" + node.city + "</a>";
			nodeInfo += "</div>";

			if( node.audio_path != null )
			{
				nodeInfo += "<audio controls='controls' preload='auto' style='margin: 15px;'><source src='" + node.audio_path + "'></audio>";
			}

			nodeInfo += "<div>" + stripslashes(description) + "</div>";

			if( node.image_path != '' && node.image_path != undefined ) nodeInfo += "<img style='margin-top: 15px;' src='" + node.image_path + "' />";

			nodeInfo += "</div>";

			if( node.tags.length )
			{
				var tags = [];

				for(var i=0;i<node.tags.length;i++)
				{
					tags.push( node.tags[i] );
				}

				nodeInfo += "<div class='footer'>associations: " + tags.join(', ') + "</div>";
			}

			nodeInfo += "</div>";
		}
		else if( node.node_type == graph.TYPE_ARTWORK )
		{
			var artist = node.artist;

			nodeInfo += "<div id='nodeInfo' class='module' style='position:absolute;z-index:1000;width:600px'>";

			nodeInfo += "<div class='header'>";
			nodeInfo += "<div class='title artworkTitle'>" + node.title + (node.year != null ? ', ' + node.year : '') + "</div>";
			nodeInfo += "<div class='subtitle artworkArtist'>" + artist + "</div>";
			nodeInfo += "</div>";

			nodeInfo += "<div class='body'>";
			nodeInfo += "<img src='images/artworks/" + node.image + "' />";
			nodeInfo += "</div>";

			nodeInfo += "<div class='footer'>";
			nodeInfo += "<div style='font-size:.5em;font-style:italic'>Image sourced from <a href='http://mona-vt.artpro.net.au/theo.php'>MONA</a></div>";

			if( taggedArtworkIds.indexOf( node.id ) == -1 )
			{
				nodeInfo += "<div id='tagArtwork' style='font-size:.7em;'><a href='#' onclick=\"javascript:showTagArtwork()\">Help us tag this artwork</a></div>";

				nodeInfo += "<form id='tagArtworkForm' method='get' style='display:none'>";
				nodeInfo += "<input type='text' name='tags' style='width:200px;padding:.5em' placeholder='a,b,c' /><br/>";
				nodeInfo += "<a href='javascript:tagArtwork( " + node.id + ", $(\"#tagArtworkForm > input\").val() );' style='font-size:.7em;'>submit</a> ";
				nodeInfo += "<a href='#' style='font-size:.7em;' onclick=\"javascript:hideTagArtwork()\">cancel</a>";
				nodeInfo += "</form>";
			}

			nodeInfo += "</div>";
			nodeInfo += "</div>";
		}
		else if( node.node_type == graph.TYPE_ARTIST )
		{
			nodeInfo += "<div id='nodeInfo' class='module' style='position:absolute;z-index:1000;width:600px'>";

			nodeInfo += "<div><b><i>" + node.artist + "</i></b></div>";

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

			if( works.length ) nodeInfo += works.join( ', ' );

			nodeInfo += "</div>";
		}
		else if( node.node_type == graph.TYPE_TAG )
		{
			nodeInfo += "<div id='nodeInfo' class='module' style='position:absolute;z-index:1000;width:600px'>";

			nodeInfo += "<div class='header'>";
			nodeInfo += "<div class='title'>\"" + graph.nodeTitle(node) + "\"</div>";
			nodeInfo += "</div>";

			nodeInfo += "<div class='body' style='width:400px'>";

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

			if( artworks.length ) nodeInfo += "<div style='margin-top:10px'>artworks: <ul>" + artworks.join('\n') + "</ul></div>";
			if( dreams.length ) nodeInfo += "<div style='margin-top:10px'>dreams:  <ul>" + dreams.join('\n') + "</ul></div>";

			nodeInfo += "</div>";
			nodeInfo += "</div>";
		}

		$('body').append( nodeInfo );

		$('#nodeInfo').hide();
		$('a[title]').tipsy( { gravity: 'e', offset: 10, opacity: 1 } );
	}

	function onGraphZoomInComplete( node )
	{
		var k = graph.r / graph.nodeRadius(node) / 2;

		var el = graph.vis.select('[id=node_'+node.index+']');
		var pos = el[0][0].getScreenCTM();

		var x_pos = pos.f + (k * graph.nodeRadius(node)) + 40;
		var y_pos = pos.e - (k * graph.nodeRadius(node)) + 20;

		$('#nodeInfo').fadeIn();
		$('#nodeInfo').css("left",x_pos);
		$('#nodeInfo').css("top",y_pos);
	}

	function onGraphZoomOutStart()
	{
		play();

		hideNodeInfo();
	}

	function onGraphNodeRollOver()
	{
		pause();
	}

	function onGraphNodeRollOut()
	{
		play();
	}

	function drawLegend()
	{
		if( $("#legend").children().length > 1 ) return;

		var legendItems =
			[
				{label:"Dream",node_type:'dream',tooltip:'Contributed dreams'},
				{label:"Connection",node_type:'tag',tooltip:'Common words or phrases'},
				{label:"Settings",image_url:"gear.png",width:20,height:20}
			];

		var width=60,height=40;

		for(var i=0,x=width/2,y=height/2;i<legendItems.length;i++)
		{
			var item = legendItems[i];

			var g = "<div class='legendItem' width='"+width+"px' height='"+height+"px'>";

			g += "<svg width='"+width+"px' height='"+height+"px'>";
			g += "<g>";

			if( item.node_type )
			{
				var d = { node_type: item.node_type, value: 3 };

				g += "<circle class='node' r='" + graph.nodeRadius(d) + "' cx='" + x + "px' cy='" + y + "px' style='" + ('fill-opacity:' + graph.nodeFillOpacity(d,1)) + "' />";
				g += "<circle class='nodeOutline' r='" + graph.nodeRadius(d) + "' cx='" + x + "px' cy='" + y + "px' style='" + ('fill:none;stroke-dasharray:'+graph.nodeDashArray(d)+';stroke-width:'+graph.nodeStrokeWidth(d)) + "' />";
			}
			else if( item.image_url )
			{
				item.image_url = "css/themes/" + (themeId==1?'black':'white') + "/icons/" + item.image_url;

				g += "<image xlink:href='" + item.image_url + "' x='" + (width-item.width)/2 + "px' y='" + (height-item.height)/2 + "px' width='" + item.width + "px' height='" + item.height + "px' />";
			}

			g += "<text x='" + x + "px' y='" + (y+20) + "px' text-anchor='middle'>" + item.label + "</text>";
			g += "</g>";
			g += "</svg>";

			g += "</div>";

			$("#legend").append( g );

			if( item.node_type )
			{
				$("#legend div:nth-child(" + (i+1) + ")").attr('data-node_type',item.node_type);
				$("#legend div:nth-child(" + (i+1) + ")").on("mouseenter",function(){ graph.highlightNodeType( $(this).attr('data-node_type') ); } );
				$("#legend div:nth-child(" + (i+1) + ")").on("mouseleave",function(){ graph.highlightNodeType(); } );
			}

			if( item.tooltip )
			{
				$("#legend div:nth-child(" + (i+1) + ")").attr('title',item.tooltip);
			}
		}

		$("#legend div").css("opacity",.5);
		$("#legend div").on("mouseenter",function(){ $(this).css("opacity",1); } );
		$("#legend div").on("mouseleave",function(){ $(this).css("opacity",.5); } );

		$("#legend div:nth-child(3)").on("mouseenter",function(e){ $('#gear,#help,#search,#theme').css('opacity',1);$("#settings").show(); });
		$("#legend div:nth-child(1),#legend div:nth-child(2)").on("mouseenter",hideSettings);

		$('#settings').on("mouseleave", hideSettings);

		$('.legendItem').tipsy( { gravity: 's', offset: 0, opacity: 1} );

		$("#legend").show();
	}

	function hideSettings()
	{
		$('#gear,#help,#search,#theme').css('opacity',.5);
		$("#settings").hide();
	}

	function hideNodeInfo()
	{
		$('#nodeInfo').remove();
	}

	function showTagArtwork()
	{
		$('#tagArtwork').hide();
		$('#tagArtworkForm').show();
		$('#tagArtworkForm input').focus();
	}

	function hideTagArtwork()
	{
		$('#tagArtworkForm > input').val('');
		$('#tagArtwork').show();
		$('#tagArtworkForm').hide();
	}

	function toggleTheme()
	{
		$('#theme').tipsy('hide');
		setTheme( themeId == 1 ? 0 : 1 );
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
	var availableDates;
	var inactivityTimer;
	var graph;
	var dataLoadAttempts = 0;
	var themeId;
	var playInterval;
	var isEmbedded;
})();
