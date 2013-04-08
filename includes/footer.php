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
	
	<!--<div style="font-size:.5em;">&copy; Artefacts <?php echo date("Y"); ?></div>-->
	
</div>