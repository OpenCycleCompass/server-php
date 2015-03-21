<?php
$start_microtime = microtime(true);
header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('Europe/Berlin');
include('config.php');
include('functions.php');

$pg = pg_connect($pgr_connectstr);
if(!$pg) die(json_encode(array("error" => "Datenbankverbindung (PostgreSQL) nicht möglich." . pg_last_error())));


include("../classes/ibisDem.class.php");
$dem = new ibisDem($pg);


if(isset($_GET["import"])) {
	$out = $dem->importDem();
}
else if(isset($_GET["clear"])) {
	$out = $dem->deleteImportedDem();
}
else if(isset($_GET["updatesql"])) {
	$out = $dem->updateStoredSqlProcedure();
}
else {
	$out = array("error" => "Eingaben fehlerhaft.");
}

$out["executiontime"] = (microtime(true)-$start_microtime);
echo(json_encode($out));

pg_close($pg);
?>
