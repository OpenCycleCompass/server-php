<?php
header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('Europe/Berlin');
include('config.php');
$db = new mysqli($dbhost, $dbuser, $dbpass);
if($db->connect_error) die('Datenbankverbindung nicht möglich. ');
$db->select_db($dbname);

if(isset($_GET['newtoken']) && $_GET['newtoken']=="newtoken" ) {
	// Create new unique token, safe it to db and return ist with the expiry date.

	// uniqid() generates a 23-character unique string with the giver prefix (ibis_)
	$token = uniqid("ibis_", true);

	$created = time(); // UNIX Timestamp
	// (Maximale) gültigkeit eines Token: derzeit 1Jahe/365Tage
	$expiry = time() + (365 * 24 * 60 * 60);

	$db->query("INSERT INTO `ibis_server-php`.`tokens` (`token`, `created`, `expiry`) VALUES ('".$token."', '".time()."', '".time()."')");

	// Return/echo token with created and expiry timestamp as json
	$out = json_encode(array('token' => $token, 'created' => $created, 'expiry' => $expiry));
}
// else if ()
//...
else {
	$out = json_encode(null);
}
echo($out);
$db->close();
?>
