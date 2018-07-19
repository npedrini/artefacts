<?php
namespace WatsonSDK\Services;

use WatsonSDK\Common\WatsonCredential;
use WatsonSDK\Services\NaturalLanguageUnderstanding\AnalyzeModel;

require 'vendor/autoload.php';
include 'vendor/cognitivebuild/watsonphpsdk/Source/Services/NaturalLanguageUnderstanding.php';

$nlu = new NaturalLanguageUnderstanding( WatsonCredential::initWithCredentials('apikey','YNeZEVJ0S8wzUUVANNn9z9qc74O13xux5ZF-mpeHNkyc') );
$model = new AnalyzeModel('Watson PHP SDK for IBM Watson Developer Cloud.', [ 'keywords' => [ 'limit' => 5 ] ]);
$result = $nlu->analyze($model);
echo $result->getContent();


?>
