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
<?php if( $current != 'index.php' ) { ?>
<img src="images/beta.png" style="position:fixed;top:0px;left:0px;z-index:2"/>
<?php } ?>