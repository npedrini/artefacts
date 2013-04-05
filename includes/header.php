<script type="text/javascript">
$(document).ready
(
	function()
	{
		var status = "<?php echo isset($_GET['status'])?$_GET['status']:'';?>";
		
		if( status != '' )
		{
			
			$("#header")
				.prepend
				( 
					$( "<div id='flash'>" + status + "</div>" )
						.css('cursor','pointer')
						.on('click',function(){ $(this).remove() } ) 
				);
				
		}
	}
);
</script>
<script type="text/javascript">
	var _gaq = _gaq || [];
	_gaq.push(['_setAccount', 'UA-38616854-1']);
	_gaq.push(['_trackPageview']);
	
	(function() {
	var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
	ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
	var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
	})();
</script>
<?php
$links = array
(
	'Contribute'=>'contribute.php',
	'Dreams'=>'browse.php',
	'About'=>'about.php'
);

$current = "index.php"; 

foreach($links as $name=>$url)
	if( basename($_SERVER["SCRIPT_NAME"]) == $url )
		$current = $name;
?>
<div id="header">
	<div>
		<h1><?php echo $current != 'index.php' ? "<a href='index.php'>Artefacts of the Collective Unconscious</a>" : "Artefacts of the Collective Unconscious"; ?></h1>
		<div id="navigation">
			<ul>
				<?php
				foreach($links as $name=>$url)
					echo "<li>" . ($current != $name?"<a href='" . $url . "'>" . $name . "</a>":'<span class="link">' . $name . '</span>') . "</li>\n";
				?>
			</ul>
		</div>
		<?php echo $current == 'index.php' ? '<div style="clear:both"><div id="info"></div></div>' : null; ?>
	</div>
</div>