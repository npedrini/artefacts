<div id="footer">

	<div id="navigation">
		<ul>
		<li><a href="index.php">Home</a></li>
		<?php
		foreach($links as $name=>$url)
			echo "<li>" . ($current != $name?"<a href='" . $url . "'>" . $name . "</a>":'<span class="link">' . $name . '</span>') . "</li>\n";
		?>
			<li><a href="mailto:<?php echo ADMIN_EMAIL; ?>">Feedback</a></li>
		</ul>
	</div>
	
</div>

<!-- google analytics -->
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