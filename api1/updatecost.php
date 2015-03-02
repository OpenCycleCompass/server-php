<?php
header ( 'Content-Type: text/html; charset=utf-8' );
date_default_timezone_set ( 'Europe/Berlin' );
include ('config.php');
include ('functions.php');

$pg = pg_connect($pgr_connectstr);
if(!$pg)
	die("Datenbankverbindung (PostgreSQL) nicht möglich.".pg_last_error($pg));

if(isset($_GET["profile"])) {
	$query = "SELECT id FROM classes WHERE profile = '".pg_escape_string($_GET["profile"])."';";
	$result = pg_query($query);
	if($result) {
		$error_cnt = 0;
		$query2 = "";
		while($row = pg_fetch_assoc($result)){
			if(isset($_GET[$row["id"]])) {
				$val = floatval($_GET[$row["id"]]);
			}
			else {
				$val = 1;
				$error_cnt++;
			}
			$query2 .= "UPDATE classes SET cost = ".$val." WHERE profile = '".pg_escape_string($_GET["profile"])."' AND id = ".intval($row["id"]).";\n";
		}
		pg_free_result($result);
		$result = pg_query($pg, $query2);
		//echo($query2);
		if($result) {
			$out = json_encode(array("success" => "Profile ".$_GET["profile"]." successfully updated. (".$error_cnt." Fehler)"));
		}
		else {
			die(json_encode(array("error" => "Profile ".$_GET["profile"]." was not updated. ".pg_last_error($pg))));
		}
	}
	else {
		die(json_encode(array("error" => "Profile not found or corrupted. ".pg_last_error($pg))));
	}
} else if(isset($_GET["getprofiles"])) {
	$query = "SELECT profile FROM classes GROUP BY profile;";
	$result = pg_query($query);
	if($result) {
		$profiles = array();
		while($row = pg_fetch_assoc($result)){
			$profiles[] = $row["profile"];
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
