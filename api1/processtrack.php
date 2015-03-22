<?php
$start_microtime = microtime(true);
header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('Europe/Berlin');
include('config.php');
include('functions.php');

$pg = pg_connect($pgr_connectstr);
if(!$pg) die(json_encode(array("error" => "Datenbankverbindung (PostgreSQL) nicht möglich." . pg_last_error())));


include('../classes/processTracks.class.php');
$processTracks = new processTracks($pg);


if(isset($_GET['track_id'])) {
	$out = $processTracks->processTrack(pg_escape_string($pg, $_GET['track_id']));
}
else if(isset($_GET['all'])) {
	$out = $processTracks->processAllTracks();
}
else if(isset($_GET['clear'])) {
	$out = $processTracks->deleteDynCosts();
}
else if(isset($_GET['list'])) {
	$query = "SELECT track_id FROM tracks;";
	if($result = pg_query($pg, $query)) {
		while($row = pg_fetch_assoc($result)) {
			echo("<a href=\"https://10.2.11.94/api1/processtrack.php?track_id=".$row["track_id"]."\">".$row["track_id"]."</a><br />\n");
		}
		pg_free_result($result);
	}
	$out = "";
}
else {
	$out = array("error" => "Eingaben fehlerhaft.");
}

$out["executiontime"] = (microtime(true)-$start_microtime);
echo(json_encode($out));

pg_close($pg);
?>
