<?php
header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('Europe/Berlin');
include('config.php');
include('functions.php');
$err_level = error_reporting(0);
$db = new mysqli($dbhost, $dbuser, $dbpass);
error_reporting($err_level);
if($db->connect_error) die("Datenbankverbindung nicht möglich.");
$db->set_charset('utf8');
$db->select_db($dbname);
if( isset($_GET["newtrack"]) && $_GET['newtrack']=="newtrack" && isset($_GET['user_token']) && isset($_GET['']) && isset($_GET['']) 
		&& isset($_GET['']) && isset($_GET[''])) 
	{
	// user_token passed by the app.
	$user_token = $db->real_escape_string($_GET['user_token']);
	if(verify_token($user_token, $db)) {
	// Create new unique track_id
	// uniqid() generates a 23-character unique string with the giver prefix (ibis_)
	$track_id = uniqid("tra_", true);

	// Created UNIX-timestamp
	$created = time(); 

	// Länge (in Metern) des Tracks
	$length = $db->real_escape_string($_GET['length']);
	
	// Dauer (in Sekunden) des Tracks
	$duration = $db->real_escape_string($_GET['duration']);

	// Name (vom User festgelegt) des Tracks; max. 49 chars
	$name = substr($db->real_escape_string($_GET['name']), 0, 48);

	// Beschreibung (vom User festgelegt) des Tracks; max. 249 chars
	$comment = substr($db->real_escape_string($_GET['comment']), 0, 248);

	$db->query("INSERT INTO `ibis_server-php`.`tracks` (`user_token`, `track_id`, `created`, `length`, `duration`, `name`, `comment`) 
	VALUES ('".$user_token."', '".$track_id."',  '".$created."',  '".$length."',  '".$duration."',  '".$name."', '".$comment."')");

	// Return/echo token with created and expiry timestamp as json
	$out = json_encode(array('track_id' => $track_id, 'created' => $created));
	} else {
		$out = json_encode(array("error" => "Der Token kann nicht verifiziert werden."));
	}
} else {
	$out = json_encode(array("error" => "Keine oder falsche Eingabe."));
}
echo($out);
$db->close();
?>
