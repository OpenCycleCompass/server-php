<?php
header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('Europe/Berlin');
include('config.php');
include('functions.php');

$pg = pg_connect($pgr_connectstr);
if(!$pg)
	die("Datenbankverbindung (PostgreSQL) nicht möglich. ".pg_last_error());

if(isset($_GET["newtoken"])) {
	// Create new unique token, safe it to db and return ist with the expiry date.

	// uniqid() generates a 23-character unique string with the giver prefix (ibis_)
	$token = uniqid("ibis_", true);

	$created = time(); // UNIX Timestamp
	// (Maximale) Gültigkeit eines Token: derzeit 10 Jahre / 3650 Tage
	$expiry = time() + (365 * 24 * 60 * 60 * 10);

	pg_query($pg, "INSERT INTO tokens (token, created, expiry) VALUES ('".$token."', '".$created."', '".$expiry."')");

	// Return/echo token with created and expiry timestamp as json
	$out = json_encode(array('token' => $token, 'created' => $created, 'expiry' => $expiry));
}
else if(isset($_GET["verify"]) && isset($_GET["token"])) {
	$out = json_encode(array('valid' => verify_token($_GET["token"], $pg)));
}
else {
	$out = json_encode(array("error" => "Keine oder falsche Eingabe."));
}
echo($out);
pg_close($pg);
?>

