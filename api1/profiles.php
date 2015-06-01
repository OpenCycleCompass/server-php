<?php
$start_microtime = microtime(true);
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Europe/Berlin');
include('config.php');
include('functions.php');

$pg = pg_connect($pgr_connectstr);
if(!$pg) die(json_encode(array("error" => "Database (PostgreSQL) failed." . pg_last_error())));

// Start session
session_start();

function getProfileEditor($profile, $pg) {
	$query = "SELECT id, name, cost FROM classes WHERE profile = '".pg_escape_string($profile)."' AND id <> 99999 ORDER BY name ASC;";
	$result = pg_query($pg, $query);
	$entries = array();
	if(pg_num_rows($result) >= 1){
		while($row = pg_fetch_assoc($result)){
			$entries[] = array("name" => $row["name"], "cost" => floatval($row["cost"]), "id" => intval($row["id"]));
		}
		pg_free_result($result);
	}
	else {
		return json_encode(array("error" => "Profile not found or corrupted.".pg_last_error($pg)));
	}
	$amount_dyncost = 0;
	$query = "SELECT cost FROM classes WHERE profile = '".pg_escape_string($profile)."' AND id = 99999;";
	$result = pg_query($pg, $query);
	if(pg_num_rows($result) >= 1){
		if($row = pg_fetch_assoc($result)){
			$amount_dyncost = $row["cost"];
		}
		pg_free_result($result);
	}
	else {
		return json_encode(array("error" => "Profile not found or corrupted.".pg_last_error($pg)));
	}
	return array("name" => $profile,
		"entries" => $entries,
		"amount_dyncost" => $amount_dyncost);
}

// Process input
if(isset($_SESSION["auth_user"]) && $_SESSION["auth_user"]=="ok" && isset($_GET["profile"])) {
	$exists = existsProfile($_GET["profile"], $pg);
	if($exists===true) {
		$out = json_encode(getProfileEditor($_GET["profile"], $pg));
	}
	else {
		$out = json_encode(array("error" => "Profile unknown: ".$exists));
	}
}
else {
	$out = json_encode(array("error" => "No authentictaion or no profile specified."));
}

echo($out);

// Close PgSQL connection
pg_close($pg);
?>