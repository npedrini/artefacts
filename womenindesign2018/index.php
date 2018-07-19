<?php
/*Receives data from JotForm Web Hook, sets origin and saves it to database*/


//database variables
$servername = "localhost";
$username = "artefact_zapier";
$password = "Chance69!";
$dbname = "artefact_dreams";
$dbtable = "dreams";

//connect to database
$mysqli = new mysqli($servername, $username, $password, $dbname);
 
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
} 
$sid = $mysqli->real_escape_string($_REQUEST['submissionID']);
 
//Get form field values and decode from rawRequest packet
$fieldvalues = $_REQUEST['rawRequest'];
$obj = json_decode($fieldvalues, true);
 
//Set origin
$origin = "women_in_design_2018";

//Save form fields as variables
$occur_day = $mysqli->real_escape_string($obj['q3_whenDid'][day]); // - need to sort out date format
$occur_month = $mysqli->real_escape_string($obj['q3_whenDid'][month]);
$occur_year = $mysqli->real_escape_string($obj['q3_whenDid'][year]);
$description = $mysqli->real_escape_string($obj['q4_describeYour']);
$title = $mysqli->real_escape_string($obj['q5_titleOf']);
$color = $mysqli->real_escape_string($obj['q9_aColour']);
//$image = $mysqli->real_escape_string($obj['anImage']); - need to fix determine what to do with image
 
$result = $mysqli->query("SELECT * FROM $dbtable WHERE user_id = '$sid'");
 
 //insert form fields into database table
$result = $mysqli->query("INSERT IGNORE INTO $dbtable (origin, description, title, color) VALUES ('$origin', '$description', '$title','$color')");

if ($result === false) {echo "SQL error:".$mysqli->error;}
		
		
$mysqli->close();
	

?>
