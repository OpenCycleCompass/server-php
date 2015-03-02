<?php
header ( 'Content-Type: text/html; charset=utf-8' );
date_default_timezone_set ( 'Europe/Berlin' );
include ('config.php');
include ('functions.php');

$pg = pg_connect($pgr_connectstr);
if(!$pg)
	die("Datenbankverbindung (PostgreSQL) nicht möglich.".pg_last_error($pg));

session_start();

if(isset($_GET["profile"]) && isset($_SESSION["auth_user"]) && $_SESSION["auth_user"]=="ok") {
	$query = "SELECT id FROM classes WHERE profile = '".pg_escape_string($_GET["profile"])."';";
	$result = pg_query($query);
	if($result) {
		$error_cnt = 0;
		$error_str = "";
		$query2 = "";
		while($row = pg_fetch_assoc($result)){
			if($row["id"]!=99999) {
				if(isset($_GET[$row["id"]])) {
					$val = floatval($_GET[$row["id"]]);
				}
				else {
					$val = 1;
					$error_cnt++;
					$error_str .= $row["id"];
				}
				$query2 .= "UPDATE classes SET cost = ".$val." WHERE profile = '".pg_escape_string($_GET["profile"])."' AND id = ".intval($row["id"]).";\n";
			}
		}
		if(isset($_GET["amount_dyncost"])) {
			$val = floatval($_GET["amount_dyncost"]);
		}
		else {
			$val = 0.5;
			$error_cnt++;
		}
		$query2 .= "UPDATE classes SET cost = ".$val." WHERE profile = '".pg_escape_string($_GET["profile"])."' AND id = 99999;\n";
		pg_free_result($result);
		$result = pg_query($pg, $query2);
		//echo($query2);
		if($result) {
			$out = json_encode(array("success" => "Profile ".$_GET["profile"]." successfully updated. (".$error_cnt." Fehler/".$error_str.")"));
		}
		else {
			die(json_encode(array("error" => "Profile ".$_GET["profile"]." was not updated. ".pg_last_error($pg))));
		}
	}
	else {
		die(json_encode(array("error" => "Profile not found or corrupted. ".pg_last_error($pg))));
	}
} else if(isset($_GET["getprofiles"])) {
	if(isset($_GET["lang"])){
		$lang = pg_escape_string($_GET["lang"]);
	}
	else {
		$lang = "de-DE";
	}
	$query = "SELECT name,description FROM profiles WHERE lang = '".$lang."' ORDER BY name ASC;";
	$result = pg_query($query);
	if($result) {
		$profiles = array();
		while($row = pg_fetch_assoc($result)){
			$profiles[$row["name"]] = $row["description"];
		}
		pg_free_result($result);
		$out = json_encode($profiles);
	}
	else {
		die(json_encode(array("error" => "No Profiles found. ".pg_last_error($pg))));
	}
}
else {
	$out = json_encode(array("error" => "Keine oder falsche Eingabe."));
}

echo ($out);

pg_close ( $pgr );
?>
