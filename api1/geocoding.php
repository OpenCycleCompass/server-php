<?php
$start_microtime = microtime(true);
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Europe/Berlin');
include('config.php');
include('functions.php');

$pg = pg_connect($pgr_connectstr);
if(!$pg) die(json_encode(array("error" => "Database (PostgreSQL) failed." . pg_last_error())));


include("../classes/geocoding.class.php");
$geocoding = new Geocoding();


if(isset($_GET["getaddr"]) && isset($_GET["addr"])) {
	$out = $geocoding->getCoordByAddr(escapeshellarg($_GET["addr"]));
}
else if(isset($_GET["getcity"])) {
	if(isset($_GET["osmid"])) {
		$out = $geocoding->getCityByOsmId(intval($_GET["osmid"]));
	}
	else if(isset($_GET["lat"]) && isset($_GET["lon"])) {
		$out = $geocoding->getCityByCoord(floatval($_GET["lat"]), floatval($_GET["lon"]));
	}
	else {
		$out = array("error" => "Eingaben fehlerhaft.");
	}
}
else {
	$out = array("error" => "Eingaben fehlerhaft.");
}

$out["executiontime"] = (microtime(true)-$start_microtime);
echo(json_encode($out));

pg_close($pg);
?>
