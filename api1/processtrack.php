<?php
$start_microtime = microtime(true);
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Europe/Berlin');
include('config.php');
include('functions.php');

$pg = pg_connect($pgr_connectstr);
if(!$pg) die(json_encode(array("error" => "Database (PostgreSQL) failed." . pg_last_error())));


include('../classes/processTracks.class.php');
$processTracks = new processTracks($pg);

if(isset($_GET['track_id'])) {
	$out = array_merge($processTracks->processTrack(pg_escape_string($pg, $_GET['track_id'])), array("track_id" => $_GET['track_id']));
	$out["executiontime"] = (microtime(true)-$start_microtime);
	$out_r = json_encode($out);
}
else if(isset($_GET['all'])) {
	$out = $processTracks->processAllTracks();
	$out["executiontime"] = (microtime(true)-$start_microtime);
	$out_r = json_encode($out);
}
else if(isset($_GET['clear'])) {
	$out = $processTracks->deleteDynCosts();
	$out["executiontime"] = (microtime(true)-$start_microtime);
	$out_r = json_encode($out);
}
else if(isset($_GET['numseg'])) {
	$out = $processTracks->getDynCostNumSeg();
	$out["executiontime"] = (microtime(true)-$start_microtime);
	$out_r = json_encode($out);
}
else {
	$out = array("error" => "Eingaben fehlerhaft.");
	$out_r = json_encode($out);
}

echo($out_r);

pg_close($pg);
?>
