<?php
$data = $_POST['data'];
$data = preg_replace( "/data:image\/png;base64,/i", "", $data);

header('Content-Type:image/png');
header('Content-Disposition: attachment; filename=oneigram.png');

$im = new Imagick();
//$im->setBackgroundColor( new ImagickPixel('transparent') );
$im->readImageBlob( base64_decode( $data ) );
$im->setImageFormat("png32");

echo $im;

?>