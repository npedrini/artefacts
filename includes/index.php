<?php


use WatsonSDK\Common\WatsonCredential;
use WatsonSDK\Services\NaturalLanguageUnderstanding\AnalyzeModel;
use WatsonSDK\Services\NaturalLanguageUnderstanding;
require 'watson/autoload.php';




$nlu = new NaturalLanguageUnderstanding( WatsonCredential::initWithCredentials('99b00872-a550-48b1-8546-fc565e213036','w3SKunEBDxZ2') );
$model = new AnalyzeModel('Test this dream text for tags', [ 'keywords' => [ 'sentiment' => true]]);
$response = $nlu->analyze($model);
$result = json_decode($response->getContent(), true);
 //echo $result->getContent();

echo '<pre>'; print_r($result); echo '</pre>';

?>
