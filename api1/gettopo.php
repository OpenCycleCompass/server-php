<?php
header ( 'Content-Type: text/html; charset=utf-8' );
date_default_timezone_set ( 'Europe/Berlin' );
include ('config.php');
include ('functions.php');
/*$err_level = error_reporting ( 0 );
$my = new mysqli ( $my_host, $my_user, $my_pass );
error_reporting ( $err_level );
if ($my->connect_error)
	die ( "Datenbankverbindung nicht möglich." );
$my->set_charset ( 'utf8' );
$my->select_db ( $my_name );*/

$pgr = pg_connect ( $pgr_connectstr );
if(!$pgr)
	die ( "Datenbankverbindung (PostgreSQL) nicht möglich." . pg_last_error () );

if(isset($_GET["getedges"]) && $_GET["getedges"]=="getedges" && isset($_GET["start_lat"]) && isset($_GET["start_lon"]) && isset($_GET["end_lat"]) && isset($_GET["end_lon"]) && ((!isset($_GET["cost"])) || ($_GET["cost"]=="static")) ){
	$start_lat = floatval($_GET["start_lat"]);
	$start_lon = floatval($_GET["start_lon"]);
	$end_lat = floatval($_GET["end_lat"]);
	$end_lon = floatval($_GET["end_lon"]);
	
	if(isset($_GET["cost"])) {
		$cost  = true;
	} else {
		$cost  = false;
	}
	
	// Return point of track $_GET["track_id"]
	$query = "SELECT gid, ST_AsText(the_geom), cost FROM ways WHERE ways.the_geom && ST_MakeEnvelope(" . $start_lon . ", " . $start_lat . ", " . $end_lon . ", " . $end_lat . ", 4326) LIMIT 10000;";
	$result = pg_query($query);
	if($result) {
		$data = array();
		$row_counter = 0;
		while ($row = pg_fetch_row($result)) {
			$row_counter++;
			$gid = $row[0];
			
			$subdata = array();
			if(isset($_GET["cost"]) && $_GET["cost"] == "static")
				$subdata[] = array("cost" => $row[2]);
			$subdata[] = array("gid" => $gid);
			
			$geom = substr($row[1], 11, -1);
			$points = explode(",", $geom);
			foreach($points as $point) {
				$point_a = explode(" ", $point);
				if($point_a[0] && $point_a[1]) {
					$subdata[] = array("lat" => $point_a[1],"lon" => $point_a[0]);
				}
			}
			$data[] = $subdata;
		}
		$data[] = array("row_counter" => $row_counter);
		$out = json_encode($data);
		pg_free_result ( $result );
	} else {
		$out = json_encode ( array (
				"error" => "Abfrage fehlgeschlagen."
		) );
	}
} else {
	$out = json_encode ( array (
			"error" => "Keine oder falsche Eingabe." 
	) );
}

echo ($out);
pg_close ( $pgr );
/*$my->close ();*/
?>
