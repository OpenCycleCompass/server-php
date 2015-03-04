<?php
header ( 'Content-Type: text/html; charset=utf-8' );
date_default_timezone_set ( 'Europe/Berlin' );
include ('config.php');
include ('functions.php');
include("../classes/geocoding.class.php");
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

$geocoding = new Geocoding();

if(isset($_GET["getroute"])
	&& ( ( (isset($_GET["start_lat"]) && isset($_GET["start_lon"])) || isset($_GET["start"]) ) 
	&&   ( (isset($_GET["end_lat"]  ) && isset($_GET["end_lon"])  ) || isset($_GET["end"]  ) ) )) {
	// return to route as arrays of LatLngs

	if(isset($_GET["start_lat"])) {
		$start_lat = floatval($_GET["start_lat"]);
		$start_lon = floatval($_GET["start_lon"]);
	} else {
		$start = $geocoding->getCoordByAddr($_GET["start"]);
		if(isset($start["error"])) {
			die(json_encode(array("error" => "Start-Adresse nicht gefunden.", "addinfo_start" => $start)));
		}
		$start_lat = $start["lat"];
		$start_lon = $start["lon"];
	}

	if(isset($_GET["end_lat"])) {
		$end_lat = floatval($_GET["end_lat"]);
		$end_lon = floatval($_GET["end_lon"]);
	} else {
		$end = $geocoding->getCoordByAddr($_GET["end"]);
		if(isset($end["error"])) {
			die(json_encode(array("error" => "Ziel-Adresse nicht gefunden.", "addinfo_end" => $end)));
		}
		$end_lat = $end["lat"];
		$end_lon = $end["lon"];
	}

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
	
	if(isset($_GET["profile"])) {
		$profile = pg_escape_string($_GET["profile"]);
		if(!(existsProfile($profile, $pgr)===true)) {
			$profile = "default";
		}
	}
	else {
		$profile = "default";
	}
	$temp_table = str_replace("-","_",str_replace(".","_",uniqid("tmptbl_rt_", true)));
	// Generate route
	$query = "CREATE TEMP TABLE ".$temp_table." AS
	SELECT seq, id1 AS node, id2 AS edge, cost, ST_AsText(b.the_geom) AS geom_text, b.the_geom AS the_geom, b.length FROM pgr_dijkstra('
				SELECT gid AS id,
					source::integer,
					target::integer,
					(length * c.cost) AS cost
				FROM ways, classes c
				WHERE class_id = c.id AND c.profile = ''".$profile."'' ',
			" . $start_id . ", " . $end_id . ", false, false) a LEFT JOIN ways b ON (a.id2 = b.gid);
			
	ALTER TABLE ".$temp_table." ADD COLUMN dyncost numeric(16,8);
	UPDATE ".$temp_table." SET dyncost = 1;
	UPDATE ".$temp_table." SET dyncost = d.cost FROM dyncost d WHERE edge = d.way_id;
	SELECT geom_text, dyncost FROM  ".$temp_table.";
	";
	
	/*$query = "CREATE TEMP TABLE ".$temp_table." AS
	SELECT seq, id1 AS node, id2 AS edge, cost, ST_AsText(b.the_geom) AS geom_text, b.the_geom AS the_geom, b.length FROM pgr_dijkstra('
				SELECT AVG(gid) AS id,
					AVG(source)::integer AS source,
					AVG(target)::integer AS target,
					(AVG(length) * AVG(c.cost)) AS cost
				FROM ways, dyncost c
				WHERE gid = c.way_id',
			" . $start_id . ", " . $end_id . ", false, false) a LEFT JOIN ways b ON (a.id2 = b.gid);
	
	ALTER TABLE ".$temp_table." ADD COLUMN dyncost numeric(16,8);
	UPDATE ".$temp_table." SET dyncost = 1;
	UPDATE ".$temp_table." SET dyncost = d.cost FROM dyncost d WHERE edge = d.way_id;
	
	SELECT geom_text, dyncost FROM  ".$temp_table.";
	";*/
	
	// Send $query via email to jufo2@mytfg.de for debugging
	error_log("pgRouting Query: " . $query . " \nLast Error: " . pg_last_error() , 1, "jufo2@mytfg.de");
	
	$result = pg_query ( $query );
	if ( $result ) {
		$data = array();
		//$id = 0;
		$row_cnt = 0;
		$row_num = pg_num_rows($result);
		while ($row = pg_fetch_assoc($result)) {
			$geom = substr($row["geom_text"], 11, -1);
			$points = explode(",", $geom);
			$subdata = array();
			foreach($points as $point) {
				$point_a = explode(" ", $point);
				if($point_a[0] && $point_a[1]) {
					//$id++;
					if($row["dyncost"]==1) {
						$subdata[] = array("lat" => floatval($point_a[1]),
								"lon" => floatval($point_a[0]));
					}
					else {
						$subdata[] = array("lat" => floatval($point_a[1]),
								"lon" => floatval($point_a[0]),
								"time_factor" => floatval($row["dyncost"]));
					}
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
		
		$new_id = 0;
		$data_single_id = array();
		foreach($data_single as $data_single_node) {
			$data_single_id[$new_id] = $data_single_node;
			$data_single_id[$new_id]["id"] = $new_id;
			$new_id++;
		}

		$json_obj = array();
		$json_obj["points"] = $data_single_id;
		$json_obj["numpoints"] = $new_id;
		
		/*$query = "ALTER TABLE ".$temp_table." ADD COLUMN c_height numeric(16,8);
		UPDATE ".$temp_table." SET c_height = IBIS_getAltitude(ST_PointN(the_geom, 0));";
		$result = pg_query($query);
		if(!$result) {
			die("<pre>".pg_last_error());
		} */
		// Calulate exact Distance:
		//$query = "SELECT SUM(length)*1000 AS distance FROM  ".$temp_table.";";
		$query = "ALTER TABLE ".$temp_table." ADD COLUMN c_dist numeric(16,8);
		UPDATE ".$temp_table." SET c_dist = ST_Length_Spheroid(the_geom, 'SPHEROID[\"WGS 84\",6378137,298.257223563]');
		SELECT SUM(c_dist) AS exact_distance, SUM(length)*1000 AS distance FROM  ".$temp_table.";";
		$result = pg_query($query);
		if($result) {
			$row = pg_fetch_assoc($result);
			$json_obj["distance"] = floatval($row["exact_distance"]);
			$json_obj["inexact_distance"] = floatval($row["distance"]);
			$out = json_encode($json_obj);
			pg_free_result($result);
		} else {
			$out = json_encode(array("error" => "Keine Route gefunden. <pre>".pg_last_error()));
		}
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
