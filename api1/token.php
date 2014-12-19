<?php
header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('Europe/Berlin');
include('config.php');
$err_level = error_reporting(0);
$db = new mysqli($dbhost, $dbuser, $dbpass);
error_reporting($err_level);
if($db->connect_error) die("Datenbankverbindung nicht möglich.");
$db->set_charset('utf8');
$db->select_db($dbname);
if( isset($_GET["newtoken"]) && $_GET['newtoken']=="newtoken" ) {
	// Create new unique token, safe it to db and return ist with the expiry date.

	// uniqid() generates a 23-character unique string with the giver prefix (ibis_)
	$token = uniqid("ibis_", true);

	$created = time(); // UNIX Timestamp
	// (Maximale) gültigkeit eines Token: derzeit 1Jahe/365Tage
	$expiry = time() + (365 * 24 * 60 * 60);

	$db->query("INSERT INTO `ibis_server-php`.`tokens` (`token`, `created`, `expiry`) VALUES ('".$token."', '".$created."', '".$expiry."')");

	// Return/echo token with created and expiry timestamp as json
	$out = json_encode(array('token' => $token, 'created' => $created, 'expiry' => $expiry));
}
else {
	$out = json_encode(array("error" => "Keine oder falsche Eingabe."));
}
echo($out);
$db->close();
?>

