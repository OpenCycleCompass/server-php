<?php
header ( 'Content-Type: text/html; charset=utf-8' );
date_default_timezone_set ( 'Europe/Berlin' );
include ('config.php');
include ('functions.php');
$err_level = error_reporting ( 0 );
$my = new mysqli ( $my_host, $my_user, $my_pass );
error_reporting ( $err_level );
if ($my->connect_error)
	die ( "Datenbankverbindung nicht möglich." );
$my->set_charset ( 'utf8' );
$my->select_db ( $my_name );

$pgr = pg_connect ( $pgr_connectstr );
if(!$pgr)
	die ( "Datenbankverbindung (PostgreSQL) nicht möglich." . pg_last_error () );

if(isset($_GET["getroute"]) && $_GET["getroute"]=="getroute" && isset($_GET["start_lat"]) && isset($_GET["start_lon"]) && isset($_GET["end_lat"]) && isset($_GET["end_lon"])){
	// return to route as arrays of LatLngs
	$start_lat = floatval($_GET["start_lat"]);
	$start_lon = floatval($_GET["start_lon"]);
	$end_lat = floatval($_GET["end_lat"]);
	$end_lon = floatval($_GET["end_lon"]);
	
	// Start point
	$query = "SELECT id::integer FROM ways_vertices_pgr ORDER BY the_geom <-> ST_GeomFromText('POINT(" . $start_lon . " " . $start_lat . ")',4326) LIMIT 1";
	$result = pg_query($query);
	$row = pg_fetch_row($result);
	pg_free_result($result);
	$start_id = $row[0];
	//echo "Start: ".$start_id;
	
	// End point
	$query = "SELECT id::integer FROM ways_vertices_pgr ORDER BY the_geom <-> ST_GeomFromText('POINT(" . $end_lon . " " . $end_lat . ")',4326) LIMIT 1";
	$result = pg_query($query);
	$row = pg_fetch_row($result);
	pg_free_result($result);
	$end_id = $row[0];
	//echo "End: ".$end_id;
	
	// Generate route
	$query = "SELECT seq, id1 AS node, id2 AS edge, cost, ST_AsText(b.the_geom) FROM pgr_dijkstra('
				SELECT gid AS id,
					source::integer,
					target::integer,
					(length * c.cost) AS cost
				FROM ways, classes c
				WHERE class_id = c.id',
			" . $start_id . ", " . $end_id . ", false, false) a LEFT JOIN ways b ON (a.id2 = b.gid);";
			
	// Send $query via email to jufo2@mytfg.de for debugging
	//error_log("pgRouting Query: " . $query, 1, "jufo2@mytfg.de");
	
	$result = pg_query ( $query );
	if ( $result ) {
		$data = array();
		$id = 0;
		$row_cnt = 0;
		$row_num = pg_num_rows($result);
		while ($row = pg_fetch_row($result)) {
			$node = $row[1];
			$edge = $row[2];
			$cost = $row[3];
			
			$geom = substr($row[4], 11, -1);
			$points = explode(",", $geom);
			$subdata = array();
			foreach($points as $point) {
				$point_a = explode(" ", $point);
				if($point_a[0] && $point_a[1]) {
					$id++;
					$subdata[] = array("id" => $id, "lat" => $point_a[1],"lon" => $point_a[0]);
				}
			}
			if(!empty($subdata)) {
				$data[] = $subdata;
			}
			$row_cnt++;
		}
		// Nächstes Subarray drehen, wenn letztes Element des Subarray nicht dem ersten Element des nächsten Subarray entspricht.
		$data_size = sizeof($data);
		if($data_size>1) {
			// Sonderbehandlung für das allererste Subarray:
			if($data[0][0]["lat"] == $data[1][0]["lat"] || $data[0][0]["lat"] == $data[1][sizeof($data[1])-1]["lat"]) {
				$data[0] = array_reverse($data[0]);
			}
			for($cnt = 0; $cnt < ($data_size-1); $cnt++) {
				if($data[$cnt][sizeof($data[$cnt])-1]["lat"] != $data[$cnt+1][0]["lat"]) {
					$data[$cnt+1] = array_reverse($data[$cnt+1]);
				}
				// Immer (außer beim letzten Subarray) das letzte Element entfernen 
				// 	(sonst doppelt, da identisch mit erstem Element des nächsten Subarray)
				array_pop($data[$cnt]);
			}
		}
		// Delete empty elements in array and concat Subarrays to single array
		$data_single = array();
		foreach($data as $element) {
			if(!empty($element)) {
				$data_single = array_merge($data_single,$element);
			}
		}
		$out = json_encode($data_single);
		pg_free_result ( $result );
	} else {
		$out = json_encode ( array (
				"error" => "Keine Route gefunden."
		) );
	}
} else if(isset($_GET["getid"]) && $_GET["getid"]=="getid" && isset($_GET["lat"]) && isset($_GET["lon"])){
	$lat = floatval($_GET["lat"]);
	$lon = floatval($_GET["lon"]);
	
	// Start point
	$query = "SELECT id::integer FROM ways_vertices_pgr ORDER BY the_geom <-> ST_GeomFromText('POINT(" . $lon . " " . $lat . ")',4326) LIMIT 1";
	$result = pg_query($query);
	if($result){
		$row = pg_fetch_row($result);
		$id = $row[0];
		pg_free_result($result);
		$out = json_encode(array("id" => $id, "query" => $query));
	} else {
		$out = json_encode ( array (
			"error" => "Keine ID gefunden."
		) );
	}
} else {
	$out = json_encode ( array (
			"error" => "Keine oder falsche Eingabe." 
	) );
}

echo ($out);
pg_close ( $pgr );
$my->close ();
?>
