<?php
$start_microtime = microtime(true);
header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('Europe/Berlin');
include('config.php');
include('functions.php');
$err_level = error_reporting(0);
$my = new mysqli($my_host, $my_user, $my_pass);
error_reporting($err_level);
if($my->connect_error)
	die(json_encode(array("error" => "Datenbankverbindung nicht möglich.")));
$my->set_charset('utf8');
$my->select_db($my_name);

$pg = pg_connect($pgr_connectstr);
if(!$pg) die(json_encode(array("error" => "Datenbankverbindung (PostgreSQL) nicht möglich." . pg_last_error())));


include('../classes/processTracks.class.php');
$processTracks = new processTracks($pg, $my, "tt_nodes_", "tt_edges_");


if(isset($_GET['track_id'])) {
	$out = $processTracks->processTrack($my->real_escape_string($_GET['track_id']));
}
else if(isset($_GET['all'])) {
	$out = $processTracks->processAllTracks();
}
else if(isset($_GET['clear'])) {
	$out = $processTracks->deleteDynCosts();
}
else if(isset($_GET['list'])) {
	$query = "SELECT track_id FROM `ibis_server-php`.`tracks`;";
	if($result = $my->query($query)) {
		while($row = $result->fetch_assoc()) {
			echo("<a href=\"https://10.2.11.94/api1/processtrack.php?track_id=".$row["track_id"]."\">".$row["track_id"]."</a><br />\n");
		}
	}
	$out = "";
}
else {
	$out = array("error" => "Eingaben fehlerhaft.");
}

$out["executiontime"] = (microtime(true)-$start_microtime);
echo(json_encode($out));

pg_close($pg);
$my->close();
?>
