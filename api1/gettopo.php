<?php
header ( 'Content-Type: text/html; charset=utf-8' );
date_default_timezone_set ( 'Europe/Berlin' );
include('config.php');
include('functions.php');

$pgr = pg_connect($pgr_connectstr);
if(!$pgr) die("Datenbankverbindung (PostgreSQL) nicht möglich. ".pg_last_error());

if(isset($_GET["getedges"]) && $_GET["getedges"]=="getedges" && isset($_GET["start_lat"]) && isset($_GET["start_lon"]) && isset($_GET["end_lat"]) && isset($_GET["end_lon"]) && ((!isset($_GET["cost"])) || ($_GET["cost"]=="static") || ($_GET["cost"]=="dynamic")) ){
	$start_lat = floatval($_GET["start_lat"]);
	$start_lon = floatval($_GET["start_lon"]);
	$end_lat = floatval($_GET["end_lat"]);
	$end_lon = floatval($_GET["end_lon"]);
	
	// Return point of track $_GET["track_id"]
	if(isset($_GET["profile"])) {
		$profile = pg_escape_string($_GET["profile"]);
	}
	else {
		$profile = "default";
	}
	if(isset($_GET["cost"]) && $_GET["cost"] == "static") {
		$query = "SELECT 
			ways.gid AS id,
			ST_AsText(ways.the_geom) AS geom, 
			classes.cost AS cost
		FROM 
			ways 
				JOIN classes 
					ON ways.class_id = classes.id 
		WHERE 
			ways.the_geom && ST_MakeEnvelope(" . $start_lon . ", " . $start_lat . ", " . $end_lon . ", " . $end_lat . ", 4326)
			AND classes.profile = '".$profile."'
		LIMIT 
			10000;";
	} else if(isset($_GET["cost"]) && $_GET["cost"] == "dynamic") {
		$query = "SELECT
			ways.gid AS id,
			ST_AsText(ways.the_geom) AS geom,
			dyncost.cost AS cost
		FROM
			ways
				JOIN dyncost
					ON ways.osm_id = dyncost.osm_id
		WHERE
			ways.the_geom && ST_MakeEnvelope(" . $start_lon . ", " . $start_lat . ", " . $end_lon . ", " . $end_lat . ", 4326)
		LIMIT
			10000;";
	} else {
		$query = "SELECT gid AS id, ST_AsText(the_geom) AS geom, 1 AS cost FROM ways WHERE ways.the_geom && ST_MakeEnvelope(" . $start_lon . ", " . $start_lat . ", " . $end_lon . ", " . $end_lat . ", 4326) LIMIT 10000;";
	}
	$result = pg_query($query);
	if($result) {
		$data = array();
		$row_counter = 0;
		while ($row = pg_fetch_assoc($result)) {
			$row_counter++;
			$gid = $row["id"];
			
			$subdata = array();
			if(isset($_GET["cost"])) {
				$subdata[] = array("cost" => $row["cost"]);
			}
			$subdata[] = array("gid" => $gid);
			
			$geom = substr($row["geom"], 11, -1);
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
	$out = json_encode(array("error" => "Keine oder falsche Eingabe."));
}

echo($out);
pg_close($pgr);
?>
