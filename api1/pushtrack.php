<?php
$start_microtime = microtime(true);
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Europe/Berlin');
include('config.php');
include('functions.php');

$pg = pg_connect($pgr_connectstr);
if(!$pg) die(json_encode(array("error" => "Database (PostgreSQL) failed." . pg_last_error())));

include("../classes/geocoding.class.php");

if( isset($_GET["newtrack"])
		&& isset($_GET['user_token'])
		&& isset($_GET['length'])
		&& isset($_GET['duration'])
		&& isset($_GET['name'])
		&& isset($_GET['comment'])
		&& (isset($_GET['data']) || isset($_POST['data'])) ) {
	
	// user_token passed by the app.
	$user_token = pg_escape_string($_GET['user_token']);
	if (verify_token($user_token, $pg)) {
		// Create new unique track_id
		// uniqid() generates a 23-character unique string with the giver prefix (ibis_)
		$track_id = uniqid("tra_", true);
		
		// Created UNIX-timestamp
		$created = time();
		
		// Länge (in Metern) des Tracks
		$length = intval(pg_escape_string($_GET['length']));
		
		// Dauer (in Sekunden) des Tracks
		$duration = intval(pg_escape_string($_GET['duration']));
		
		// Name (vom User festgelegt) des Tracks; max. 49 chars
		$name = substr(pg_escape_string($_GET['name']), 0, 48 );
		
		// Beschreibung (vom User festgelegt) des Tracks; max. 249 chars
		$comment = substr(pg_escape_string($_GET['comment']), 0, 248 );
		
		// Public: Track is public availible (anonymous)
		if(isset($_GET ['public']))
			if($_GET ['public'] == "true")
				$public = 1;
			else
				$public = 0;
		else
			$public = 1;
		
		$track_string = "";
		
		// data: json-encoded user track
		// array of (lat, lon, alt, time, speed, additional-info (not used so far))
		$nodes = 0;
		$first = true;
		if(isset($_POST['data'])) {
			$data_raw = $_POST['data'];
		} else {
			$data_raw = $_GET['data'];
		}
		$data = json_decode($data_raw, true, 3);
		if(count($data)>=1){
			foreach ($data as $element) {
				if(isset($element["lat"]) && isset($element["lon"]) && isset($element["tst"])){
					$time = floatval($element["tst"]); 	// UNIX timestamp ist Festkommazahl mit 3 Nachlommastellen
					$lat = floatval($element["lat"]); 	// lat, lon und alt sind Gleitkommazahlen
					$lon = floatval($element["lon"]);
					if(isset($element["alt"])) {
						$alt = floatval($element["alt"]);
					} else {
						$alt = "NULL";
					}
					if(isset($element["spe"])) {
						$spe = floatval($element["spe"]);
					} else {
						$spe = "NULL";
					}
					// Länge (in Metern) des Tracks
					if($element["acc"]) {
						$acc = floatval($element["acc"]);
					} else {
						$acc = 0;
					}
					if($first) {
						$first = false;
						$first_lat = $lat;
						$first_lon = $lon;
					}
					$query = "INSERT INTO rawdata_server_php (lat, lon, alt, time, speed, track_id, the_geom, acc)
					VALUES (" . $lat . ",  " . $lon . ",  " . $alt . ", " . $time . ", " . $spe . ", '" . $track_id . "', ST_SetSRID(ST_MakePoint(".$lon.",".$lat."),4326), '".$acc."')";
					$result = pg_query ( $query );
					if ( $result ) {
						pg_free_result ( $result );
						$nodes++;
						$track_string .= $time.$lat.$lon;
					}
					// Effizenz? Evtl alle Querys sammeln und gemeinsam ausführen?
				}
			}
			
			// Generate hash of track: $track_string
			
			$hash = sha1($track_string);
			
			$city = NULL;
			$geocoding = new Geocoding();
			$city = $geocoding->getCityByCoord($first_lat, $first_lon);
			
			pg_query($pg, "INSERT INTO tracks "
					."(user_token, track_id, created, length, duration, nodes, name, "
						."comment, public, hash, city, data_raw) "
					."VALUES ('" . $user_token . "', '" . $track_id . "',  '" . $created . "', "
						."'" . $length . "',  '" . $duration . "',    '" . $nodes . "',  '" . $name . "', "
						."'" . $comment . "', '" . $public . "', '" . $hash . "', '" . pg_escape_string($city) . "', "
						."'" . pg_escape_string($data_raw) . "')" );
			// Hier wird user_token mit track_id verknüpft: DATENSCHUTZ/SPARSAMKEIT? (TODO)

			// TODO Erfolg der query überprüfen? !!!

			// Return/echo token with created and expiry timestamp as json
			$out = json_encode ( array (
					'track_id' => $track_id,
					'created' => $created,
					'nodes' => $nodes 
			) );

		} else {
			$out = json_encode ( array (
					"error" => "Keine Track-Daten enthalten." 
			) );
		}
	} else {
		$out = json_encode(array("error" => "Der Token kann nicht verifiziert werden."));
	}
} else {
	if(!isset($_POST["data"])) {
		$out = json_encode(array("error" => "Keine oder falsche Eingabe. \"data\" fehlt"));
	} else {
		$out = json_encode(array("error" => "Keine oder falsche Eingabe."));
	}
}
echo($out);
pg_close($pg);
?>
